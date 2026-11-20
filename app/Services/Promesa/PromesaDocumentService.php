<?php

namespace App\Services\Promesa;

use App\Models\PromesaPago;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ilovepdf\Ilovepdf;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use ZipArchive;

class PromesaDocumentService
{
    /**
     * Fake PDF converter closure for tests (avoids consuming real iLovePDF credits).
     */
    public static ?\Closure $fakePdfConverter = null;

    /**
     * Localiza la ruta absoluta de la plantilla oficial Word del acuerdo de pago.
     * Revisa en orden de prioridad: config, almacenamiento privado de plantillas y recursos.
     */
    public function resolveTemplatePath(): string
    {
        $candidates = array_values(array_filter([
            config('promesas.template_path'),
            storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx'),
            resource_path('templates/Acuerdo_de_Pago_DNI_{dni}.docx'),
            storage_path('app/templates/Acuerdo_de_Pago.docx'),
            resource_path('templates/Acuerdo_de_Pago.docx'),
        ]));

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $storageDefault = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
                if ($candidate !== $storageDefault && ! is_file($storageDefault)) {
                    $storageDir = dirname($storageDefault);
                    if (! is_dir($storageDir)) {
                        @mkdir($storageDir, 0770, true);
                    }
                    if (is_dir($storageDir) && is_writable($storageDir)) {
                        @copy($candidate, $storageDefault);
                    }
                }

                return $candidate;
            }
        }

        throw new \RuntimeException('No se encontró la plantilla oficial del acuerdo de pago en las ubicaciones autorizadas.');
    }

    public function agreementResponse(PromesaPago $promesa): Response|BinaryFileResponse
    {
        $workflow = strtolower(trim((string) ($promesa->workflow_estado ?: $promesa->estado)));
        if ($workflow !== 'aprobada') {
            abort(403, 'Solo se puede generar y descargar el acuerdo oficial para promesas aprobadas.');
        }

        $pdfPrivateDir = storage_path('app/promesas/pdfs');
        $docxPrivateDir = storage_path('app/promesas/docx');
        if (! is_dir($pdfPrivateDir)) {
            @mkdir($pdfPrivateDir, 0770, true);
        }
        if (! is_dir($docxPrivateDir)) {
            @mkdir($docxPrivateDir, 0770, true);
        }

        $docNum = (string) ($promesa->numdoc ?: $promesa->dni);
        $dniRaw = (string) ($promesa->dni ?: $promesa->numdoc);
        $pdfPrivateFile = "{$pdfPrivateDir}/Acuerdo_{$promesa->id}_{$docNum}.pdf";
        $docxPrivateFile = "{$docxPrivateDir}/Acuerdo_{$promesa->id}_{$docNum}.docx";

        // 8. Reutilizar un PDF previamente generado cuando exista y sea válido, evitando consumir créditos innecesariamente.
        $candidatePdfs = array_unique([
            $pdfPrivateFile,
            "{$pdfPrivateDir}/Acuerdo_{$promesa->id}_{$dniRaw}.pdf",
            "{$pdfPrivateDir}/Conv_{$docNum}.pdf",
            "{$pdfPrivateDir}/Conv_{$dniRaw}.pdf",
        ]);

        foreach ($candidatePdfs as $candidatePdf) {
            if (is_file($candidatePdf) && filesize($candidatePdf) > 0) {
                return response()->file($candidatePdf, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="Acuerdo_'.$promesa->id.'_'.$docNum.'.pdf"',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        $requestId = now()->format('YmdHis').'_'.bin2hex(random_bytes(3));
        $isolatedTmpDir = storage_path('app/tmp/p_'.$promesa->id.'_'.bin2hex(random_bytes(6)));
        $docxOut = null;
        $pdfOut = null;

        try {
            try {
                $template = $this->resolveTemplatePath();
            } catch (Throwable $e) {
                Log::error('Error localizando plantilla oficial de acuerdo', [
                    'req_id' => $requestId,
                    'promesa_id' => $promesa->id,
                    'dni' => $docNum,
                    'searched_paths' => [
                        storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx'),
                        resource_path('templates/Acuerdo_de_Pago_DNI_{dni}.docx'),
                        storage_path('app/templates/Acuerdo_de_Pago.docx'),
                        resource_path('templates/Acuerdo_de_Pago.docx'),
                    ],
                    'msg' => $e->getMessage(),
                ]);

                abort(404, 'La plantilla oficial del acuerdo de pago no se encuentra disponible. Contacte al administrador.');
            }

            if (! is_dir($isolatedTmpDir)) {
                @mkdir($isolatedTmpDir, 0770, true);
            }

            $promesa->loadMissing(['operaciones', 'cuotas']);

            $operations = $this->operationsFor($promesa);
            $client = $this->clientFor($promesa);
            $operationRows = $this->operationRows($operations, $promesa);
            $scheduleRows = $this->scheduleRows($promesa);

            $document = new TemplateProcessor($template);
            $this->fillDocument($document, $promesa, $client, $operationRows, $scheduleRows);

            $docxOut = $isolatedTmpDir."/Acuerdo_{$promesa->id}_{$docNum}.docx";
            $document->saveAs($docxOut);
            @copy($docxOut, $docxPrivateFile);

            Log::info('PROMESA_DOCX_INSPECT', [
                'req_id' => $requestId,
                'promesa_id' => $promesa->id,
                'dni' => $docNum,
                'tpl' => $template,
                'ops_count' => count($operations),
                'cuotas_count' => $promesa->relationLoaded('cuotas') ? $promesa->cuotas->count() : null,
                'docx' => $this->fileMeta($docxOut),
                'inspect' => $this->inspectDocx($docxOut),
            ]);

            try {
                $pdfTemp = $this->convertToPdf($docxOut, $isolatedTmpDir, $promesa);
                @rename($pdfTemp, $pdfPrivateFile);
                @unlink($docxOut);
                @rmdir($isolatedTmpDir);

                return response()->file($pdfPrivateFile, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="Acuerdo_'.$promesa->id.'_'.$docNum.'.pdf"',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            } catch (Throwable $e) {
                Log::warning('iLovePDF fallo, entregando DOCX', [
                    'req_id' => $requestId,
                    'promesa_id' => $promesa->id,
                    'dni' => $docNum,
                    'msg' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);

                if (! is_file($docxPrivateFile) && is_file($docxOut)) {
                    @copy($docxOut, $docxPrivateFile);
                }
                if (is_file($docxOut)) {
                    @unlink($docxOut);
                }
                if (is_dir($isolatedTmpDir)) {
                    @rmdir($isolatedTmpDir);
                }

                return response()->download($docxPrivateFile, "Acuerdo_{$promesa->id}_{$docNum}.docx", [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('Error generando Conv PDF', [
                'req_id' => $requestId,
                'promesa_id' => $promesa->id ?? null,
                'dni' => $docNum ?? null,
                'msg' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            if (is_file($docxPrivateFile)) {
                return response()->download($docxPrivateFile, "Acuerdo_{$promesa->id}_{$docNum}.docx", [
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            abort(500, 'No se pudo generar el documento del acuerdo.');
        }
    }

    private function operationsFor(PromesaPago $promesa): array
    {
        if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
            return $promesa->operaciones->pluck('operacion')->map(fn ($operation) => (string) $operation)->all();
        }

        if (! empty($promesa->operacion)) {
            return array_filter(array_map('trim', explode(',', (string) $promesa->operacion)));
        }

        $docNum = (string) ($promesa->numdoc ?? $promesa->dni);

        return DB::table('clientes_cuentas')
            ->where('numdoc', $docNum)
            ->pluck('operacion')
            ->filter()
            ->all();
    }

    private function clientFor(PromesaPago $promesa): object
    {
        $docNum = (string) ($promesa->numdoc ?? $promesa->dni);

        $client = DB::table('clientes_cuentas')
            ->where('numdoc', $docNum)
            ->orderByDesc('updated_at')
            ->first(['numdoc', 'nombre', 'direccion']);

        return (object) [
            'numdoc' => (string) ($client->numdoc ?? $docNum),
            'nombre' => (string) ($client->nombre ?? ''),
            'direccion' => (string) ($client->direccion ?? ''),
        ];
    }

    private function operationRows(array $operations, PromesaPago $promesa): object
    {
        $docNum = (string) ($promesa->numdoc ?? $promesa->dni);

        $rows = DB::table('clientes_cuentas')
            ->select('operacion', 'deuda_total', 'entidad')
            ->where('numdoc', $docNum)
            ->whereIn('operacion', $operations)
            ->get();

        $byOperation = $rows->keyBy('operacion');
        $entity = (string) ($rows->pluck('entidad')->filter()->countBy()->sortDesc()->keys()->first()
            ?? DB::table('clientes_cuentas')->where('numdoc', $docNum)->value('entidad') ?? '');

        $amount = $promesa->tipo === 'convenio'
            ? (float) ($promesa->monto_convenio ?? 0)
            : (float) ($promesa->monto ?? 0);

        $debtSum = $operations
            ? array_sum(array_map(fn ($operation) => (float) ($byOperation[$operation]->deuda_total ?? 0), $operations))
            : 0.0;

        $table = [];
        $accumulated = 0.0;

        foreach ($operations as $index => $operation) {
            $debt = (float) ($byOperation[$operation]->deuda_total ?? 0);

            if ($debtSum > 0) {
                $part = ($index < count($operations) - 1)
                    ? round($amount * ($debt / $debtSum), 2)
                    : round($amount - $accumulated, 2);
            } else {
                $part = ($index < count($operations) - 1)
                    ? round($amount / max(1, count($operations)), 2)
                    : round($amount - $accumulated, 2);
            }

            if ($index < count($operations) - 1) {
                $accumulated += $part;
            }

            $table[] = [
                'operacion' => (string) $operation,
                'deuda_total' => $this->formatDebt($debt),
                'monto_divido' => $this->formatWithoutTrailingZeros($part),
            ];
        }

        return (object) [
            'entity' => $entity,
            'amount' => $amount,
            'table' => $table,
        ];
    }

    private function scheduleRows(PromesaPago $promesa): object
    {
        $rows = [];
        $rawSum = 0.0;

        if ($promesa->relationLoaded('cuotas') && $promesa->cuotas->count()) {
            foreach ($promesa->cuotas as $quota) {
                $rawSum += (float) $quota->monto;
                $rows[] = [
                    'nro_cuotas' => str_pad((int) $quota->nro, 2, '0', STR_PAD_LEFT),
                    'monto_cuota' => $this->formatWithoutTrailingZeros($quota->monto),
                    'fecha_pago' => $this->formatDate($quota->fecha),
                ];
            }
        } elseif ($promesa->tipo === 'convenio') {
            $quotaCount = (int) ($promesa->nro_cuotas ?? 1) ?: 1;
            $amount = (float) ($promesa->monto_cuota ?? 0);

            if ($amount <= 0 && (float) ($promesa->monto_convenio ?? 0) > 0) {
                $amount = (float) $promesa->monto_convenio / $quotaCount;
            }

            $rawSum += $amount;
            $rows[] = [
                'nro_cuotas' => str_pad($quotaCount, 2, '0', STR_PAD_LEFT),
                'monto_cuota' => $this->formatWithoutTrailingZeros($amount),
                'fecha_pago' => $this->formatDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
            ];
        } else {
            $amount = (float) ($promesa->monto ?? 0);
            $rawSum += $amount;
            $rows[] = [
                'nro_cuotas' => '01',
                'monto_cuota' => $this->formatWithoutTrailingZeros($amount),
                'fecha_pago' => $this->formatDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
            ];
        }

        return (object) [
            'rows' => $rows,
            'rawSum' => $rawSum,
        ];
    }

    private function fillDocument(
        TemplateProcessor $document,
        PromesaPago $promesa,
        object $client,
        object $operationRows,
        object $scheduleRows
    ): void {
        $creator = DB::table('users')->where('id', $promesa->user_id)->value('name');

        $document->setValue('name', (string) ($creator ?? ''));
        $document->setValue('id', str_pad((string) $promesa->id, 4, '0', STR_PAD_LEFT));
        $document->setValue('created_at', $promesa->created_at ? $this->formatDate($promesa->created_at) : '');
        $document->setValue('nombre', $this->xmlSafe($client->nombre));
        $document->setValue('numdoc', $client->numdoc);
        $document->setValue('telefono', (string) ($promesa->telefono ?? ''));
        $document->setValue('direccion', $this->xmlSafe($client->direccion));
        $document->setValue('entidad', $operationRows->entity);
        $document->setValue('monto', $this->formatWithoutTrailingZeros($scheduleRows->rawSum));

        $fallbackOperation = [[
            'operacion' => '',
            'deuda_total' => $this->formatDebt(0),
            'monto_divido' => $this->formatWithoutTrailingZeros($operationRows->amount),
        ]];

        if (method_exists($document, 'cloneRowAndSetValues')) {
            $document->cloneRowAndSetValues('operacion', $operationRows->table ?: $fallbackOperation);
        } else {
            $this->cloneRowsManually($document, 'operacion', $operationRows->table ?: $fallbackOperation, [
                'operacion',
                'deuda_total',
                'monto_divido',
            ]);
        }

        if (method_exists($document, 'cloneRowAndSetValues')) {
            $document->cloneRowAndSetValues('nro_cuotas', $scheduleRows->rows);
        } else {
            $this->cloneRowsManually($document, 'nro_cuotas', $scheduleRows->rows, [
                'nro_cuotas',
                'monto_cuota',
                'fecha_pago',
            ]);
        }
    }

    private function cloneRowsManually(TemplateProcessor $document, string $anchor, array $rows, array $keys): void
    {
        $document->cloneRow($anchor, count($rows));

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            foreach ($keys as $key) {
                $document->setValue("{$key}#{$number}", $row[$key] ?? '');
            }
        }
    }

    private function convertToPdf(string $docxOut, string $isolatedTmpDir, PromesaPago $promesa): string
    {
        $docNum = (string) ($promesa->numdoc ?: $promesa->dni);

        // 8. Conversión a PDF simulada en pruebas automatizadas, sin consumir créditos reales.
        if (self::$fakePdfConverter !== null) {
            $converter = self::$fakePdfConverter;

            return $converter($docxOut, $isolatedTmpDir, $promesa);
        }

        if (app()->environment('testing') && (! config('services.ilovepdf.public') || ! config('services.ilovepdf.secret'))) {
            $simulatedPdf = $isolatedTmpDir."/Acuerdo_{$promesa->id}_{$docNum}.pdf";
            file_put_contents($simulatedPdf, "%PDF-1.4 Simulated Agreement for Promesa #{$promesa->id}\n%%EOF");

            return $simulatedPdf;
        }

        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');

        if (! $public || ! $secret) {
            throw new \RuntimeException('Llaves iLovePDF no configuradas');
        }

        $ilovepdf = new Ilovepdf($public, $secret);
        $task = $ilovepdf->newTask('officepdf');
        $task->setOutputFilename("Acuerdo_{$promesa->id}_{$docNum}");
        $task->addFile($docxOut);
        $task->execute();
        $task->download($isolatedTmpDir);

        $expectedPdf = $isolatedTmpDir."/Acuerdo_{$promesa->id}_{$docNum}.pdf";
        if (is_file($expectedPdf)) {
            return $expectedPdf;
        }

        $fallbackPdf = $isolatedTmpDir.'/'.pathinfo($docxOut, PATHINFO_FILENAME).'.pdf';
        if (is_file($fallbackPdf)) {
            return $fallbackPdf;
        }

        $files = @scandir($isolatedTmpDir);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && str_ends_with(strtolower($file), '.pdf')) {
                    $candidate = $isolatedTmpDir.'/'.$file;
                    if (is_file($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        throw new \RuntimeException('iLovePDF no devolvio un PDF en el directorio de salida');
    }

    private function fileMeta(string $path): array
    {
        clearstatcache(true, $path);

        $meta = [
            'path' => $path,
            'exists' => is_file($path),
            'size' => null,
            'mtime' => null,
            'sha1' => null,
            'head2' => null,
        ];

        if (! is_file($path)) {
            return $meta;
        }

        $meta['size'] = @filesize($path) ?: null;
        $meta['mtime'] = @filemtime($path) ?: null;

        $handle = @fopen($path, 'rb');
        if ($handle) {
            $meta['head2'] = @fread($handle, 2) ?: null;
            @fclose($handle);
        }

        if (($meta['size'] ?? 0) > 0) {
            $meta['sha1'] = @sha1_file($path) ?: null;
        }

        return $meta;
    }

    private function inspectDocx(string $docxAbs): array
    {
        $out = [
            'zip_ok' => false,
            'has_types' => false,
            'has_document' => false,
            'xml_ok' => null,
            'xml_error' => null,
        ];

        if (! is_file($docxAbs)) {
            $out['xml_error'] = ['reason' => 'file_not_found'];

            return $out;
        }

        $zip = new ZipArchive;
        $open = $zip->open($docxAbs);

        if ($open !== true) {
            $out['xml_error'] = ['reason' => 'zip_open_failed', 'code' => $open];

            return $out;
        }

        $out['zip_ok'] = true;
        $out['has_types'] = ($zip->locateName('[Content_Types].xml') !== false);
        $out['has_document'] = ($zip->locateName('word/document.xml') !== false);
        $out['xml_ok'] = true;

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! $name) {
                continue;
            }

            $isXml = str_ends_with(strtolower($name), '.xml') || str_ends_with(strtolower($name), '.rels');
            if (! $isXml) {
                continue;
            }

            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') {
                continue;
            }

            libxml_clear_errors();
            $ok = @$dom->loadXML($xml, LIBXML_NONET);

            if (! $ok) {
                $errors = libxml_get_errors();
                $first = $errors[0] ?? null;

                $out['xml_ok'] = false;
                $out['xml_error'] = [
                    'entry' => $name,
                    'msg' => $first ? trim($first->message) : 'xml_parse_failed',
                    'line' => $first->line ?? null,
                    'column' => $first->column ?? null,
                    'level' => $first->level ?? null,
                    'code' => $first->code ?? null,
                ];
                break;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $zip->close();

        return $out;
    }

    private function xmlSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');
        $value = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function formatDebt(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    private function formatWithoutTrailingZeros(mixed $value): string
    {
        $value = (float) $value;

        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0, '.', ',')
            : number_format($value, 2, '.', ',');
    }

    private function formatDate(mixed $value): string
    {
        return Carbon::parse($value)->format('d/m/Y');
    }
}
