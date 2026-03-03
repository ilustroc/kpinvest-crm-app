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
    public function __construct(private CnaService $cnaSvc) {}

    public function generateOutputsFromTemplate(CnaSolicitud $cna): void
    {
        $ops = is_array($cna->operaciones)
            ? $cna->operaciones
            : (json_decode($cna->operaciones ?? '[]', true) ?: []);
        $ops = array_values(array_filter(array_map('strval', $ops)));

        $rows = DB::table('clientes_cuentas')
            ->select('operacion','cuenta','cosecha','entidad')
            ->whereIn('operacion', $ops)
            ->get();

        $cosecha = (string)($rows->pluck('cosecha')->filter()->unique()->first() ?? '');
        $origen  = $this->cnaSvc->originFromCosecha($cosecha) ?? 'KP INVEST SAC';
        $cfg     = $this->cnaSvc->seriesConfig($origen);
        $tpl     = $cfg['template'];

        if (!is_file($tpl)) {
            throw new \RuntimeException('Plantilla no encontrada para origen: '.$origen.' ('.$tpl.')');
        }

        $docxDir = 'cna/docx'; $pdfDir = 'cna/pdfs';
        Storage::makeDirectory($docxDir);
        Storage::makeDirectory($pdfDir);

        $docxRel  = $docxDir."/CNA {$cna->nro_carta} - {$cna->dni}.docx";
        $pdfRel   =  $pdfDir."/CNA {$cna->nro_carta} - {$cna->dni}.pdf";

        $tp = new TemplateProcessor($tpl);

        $titular = $cna->titular ?: DB::table('clientes_cuentas')
            ->where('numdoc', $cna->dni)
            ->value('nombre');

        Carbon::setLocale('es');
        $aprobadoAtStr = ($cna->aprobado_at ? Carbon::parse($cna->aprobado_at) : now())
            ->translatedFormat('d \\de F \\de Y');

        $cuenta  = (string)($rows->pluck('cuenta')->filter()->unique()->first() ?? ($ops[0] ?? ''));
        $entidad = (string)($rows->pluck('entidad')->filter()->unique()->first() ?? '');

        $tp->setValue('nro_carta',   $cna->nro_carta);
        $tp->setValue('nombre',      (string)$titular);
        $tp->setValue('numdoc',      $cna->dni);
        $tp->setValue('aprobado_at', $aprobadoAtStr);

        $n = max(count($ops), 1);
        if ($n > 1) $tp->cloneRow('operacion', $n);

        for ($i=0; $i<$n; $i++) {
            $idx = $i+1;
            $op  = $ops[$i] ?? ($ops[0] ?? '');
            $tp->setValue("operacion#{$idx}", $op);
            $tp->setValue("cuenta#{$idx}",    $cuenta);
            $tp->setValue("entidad#{$idx}",   $entidad);
        }
        $tp->setValue('operacion', implode(', ', $ops));
        $tp->setValue('cuenta',    $cuenta);
        $tp->setValue('entidad',   $entidad);

        $tp->saveAs(storage_path('app/'.$docxRel));

        $this->ensurePdfFromDocx(storage_path('app/'.$docxRel), storage_path('app/'.$pdfRel));

        $cna->docx_path = $docxRel;
        $cna->pdf_path  = (is_file(storage_path('app/'.$pdfRel))) ? $pdfRel : null;
        $cna->save();
    }

    private function ensurePdfFromDocx(string $docxAbs, string $pdfAbs): void
    {
        @unlink($pdfAbs);

        try {
            $this->convertDocxToPdfViaIlovepdf($docxAbs, $pdfAbs);
        } catch (\Throwable $e) {
            Log::warning('iLovePDF falló, entregando DOCX', [
                'msg'   => $e->getMessage(),
                'class' => get_class($e),
                'code'  => $e->getCode(),
            ]);
        }
    }

    private function convertDocxToPdfViaIlovepdf(string $docxAbs, string $pdfAbs): void
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');
        if (!$public || !$secret) throw new \RuntimeException('Faltan claves de iLovePDF.');

        $sdk  = new Ilovepdf($public, $secret);
        $task = $sdk->newTask('officepdf');
        $task->addFile($docxAbs);
        $task->execute();

        $outDir = dirname($pdfAbs);
        if (!is_dir($outDir)) @mkdir($outDir, 0775, true);

        $task->download($outDir);

        $expected = $outDir.'/'.basename($docxAbs, '.docx').'.pdf';
        if (is_file($expected) && $expected !== $pdfAbs) {
            @unlink($pdfAbs);
            @rename($expected, $pdfAbs);
        }

        if (!is_file($pdfAbs)) {
            throw new \RuntimeException('No se pudo localizar el PDF descargado por iLovePDF.');
        }
    }
}