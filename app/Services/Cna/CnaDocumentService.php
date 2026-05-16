<?php

namespace App\Services\Cna;

use App\Models\CnaSolicitud;
use Carbon\Carbon;
use Ilovepdf\Ilovepdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class CnaDocumentService
{
    public function __construct(private readonly CnaNumberingService $numbering)
    {
    }

    public function generateOutputsFromTemplate(CnaSolicitud $cna): void
    {
        $operations = is_array($cna->operaciones)
            ? $cna->operaciones
            : (json_decode($cna->operaciones ?? '[]', true) ?: []);

        $operations = array_values(array_filter(array_map('strval', $operations)));

        $rows = DB::table('clientes_cuentas')
            ->select('operacion', 'cuenta', 'cosecha', 'entidad')
            ->whereIn('operacion', $operations)
            ->get();

        $cosecha = (string) ($rows->pluck('cosecha')->filter()->unique()->first() ?? '');
        $origin = $this->numbering->originFromCosecha($cosecha) ?? 'KP INVEST SAC';
        $config = $this->numbering->seriesConfig($origin);
        $template = $config['template'];

        if (! is_file($template)) {
            throw new \RuntimeException('Plantilla no encontrada para origen: '.$origin.' ('.$template.')');
        }

        $docxDir = 'cna/docx';
        $pdfDir = 'cna/pdfs';
        Storage::makeDirectory($docxDir);
        Storage::makeDirectory($pdfDir);

        $docxRel = $docxDir."/CNA {$cna->nro_carta} - {$cna->dni}.docx";
        $pdfRel = $pdfDir."/CNA {$cna->nro_carta} - {$cna->dni}.pdf";

        $processor = new TemplateProcessor($template);

        $titular = $cna->titular ?: DB::table('clientes_cuentas')
            ->where('numdoc', $cna->dni)
            ->value('nombre');

        Carbon::setLocale('es');
        $approvedAt = ($cna->aprobado_at ? Carbon::parse($cna->aprobado_at) : now())
            ->translatedFormat('d \\de F \\de Y');

        $cuenta = (string) ($rows->pluck('cuenta')->filter()->unique()->first() ?? ($operations[0] ?? ''));
        $entidad = (string) ($rows->pluck('entidad')->filter()->unique()->first() ?? '');

        $processor->setValue('nro_carta', $cna->nro_carta);
        $processor->setValue('nombre', (string) $titular);
        $processor->setValue('numdoc', $cna->dni);
        $processor->setValue('aprobado_at', $approvedAt);

        $rowsCount = max(count($operations), 1);

        if ($rowsCount > 1) {
            $processor->cloneRow('operacion', $rowsCount);
        }

        for ($i = 0; $i < $rowsCount; $i++) {
            $index = $i + 1;
            $operation = $operations[$i] ?? ($operations[0] ?? '');

            $processor->setValue("operacion#{$index}", $operation);
            $processor->setValue("cuenta#{$index}", $cuenta);
            $processor->setValue("entidad#{$index}", $entidad);
        }

        $processor->setValue('operacion', implode(', ', $operations));
        $processor->setValue('cuenta', $cuenta);
        $processor->setValue('entidad', $entidad);

        $processor->saveAs(storage_path('app/'.$docxRel));

        $this->ensurePdfFromDocx(storage_path('app/'.$docxRel), storage_path('app/'.$pdfRel));

        $cna->docx_path = $docxRel;
        $cna->pdf_path = is_file(storage_path('app/'.$pdfRel)) ? $pdfRel : null;
        $cna->save();
    }

    public function download(CnaSolicitud $cna, string $primary)
    {
        if ($cna->workflow_estado !== 'aprobada') {
            abort(403, 'Solo disponible para CNA aprobadas.');
        }

        $base = sprintf('CNA %s - %s', $cna->nro_carta, $cna->dni);
        $paths = [
            'pdf' => ['rel' => "cna/pdfs/{$base}.pdf", 'name' => "{$base}.pdf"],
            'docx' => ['rel' => "cna/docx/{$base}.docx", 'name' => "{$base}.docx"],
        ];

        $first = $paths[$primary];
        $second = $paths[$primary === 'pdf' ? 'docx' : 'pdf'];

        if (Storage::exists($first['rel'])) {
            return Storage::download($first['rel'], $first['name']);
        }

        if (Storage::exists($second['rel'])) {
            return Storage::download($second['rel'], $second['name']);
        }

        abort(404, 'Archivo no encontrado.');
    }

    private function ensurePdfFromDocx(string $docxAbs, string $pdfAbs): void
    {
        @unlink($pdfAbs);

        try {
            $this->convertDocxToPdfViaIlovepdf($docxAbs, $pdfAbs);
        } catch (\Throwable $e) {
            Log::warning('iLovePDF falló, entregando DOCX', [
                'msg' => $e->getMessage(),
                'class' => get_class($e),
                'code' => $e->getCode(),
            ]);
        }
    }

    private function convertDocxToPdfViaIlovepdf(string $docxAbs, string $pdfAbs): void
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');

        if (! $public || ! $secret) {
            throw new \RuntimeException('Faltan claves de iLovePDF.');
        }

        $sdk = new Ilovepdf($public, $secret);
        $task = $sdk->newTask('officepdf');
        $task->addFile($docxAbs);
        $task->execute();

        $outDir = dirname($pdfAbs);
        if (! is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $task->download($outDir);

        $expected = $outDir.'/'.basename($docxAbs, '.docx').'.pdf';
        if (is_file($expected) && $expected !== $pdfAbs) {
            @unlink($pdfAbs);
            @rename($expected, $pdfAbs);
        }

        if (! is_file($pdfAbs)) {
            throw new \RuntimeException('No se pudo localizar el PDF descargado por iLovePDF.');
        }
    }
}
