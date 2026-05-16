<?php

namespace App\Services\Promesa;

use App\Models\PromesaPago;
use Carbon\Carbon;
use Ilovepdf\Ilovepdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class PromesaDocumentService
{
    public function agreementResponse(PromesaPago $promesa): Response|BinaryFileResponse
    {
        $requestId = now()->format('YmdHis').'_'.bin2hex(random_bytes(3));
        $docxOut = null;
        $pdfOut = null;

        try {
            $template = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
            if (! is_file($template)) {
                abort(404, 'No se encontro la plantilla: '.$template);
            }

            $promesa->loadMissing(['operaciones', 'cuotas']);

            $operations = $this->operationsFor($promesa);
            $client = $this->clientFor($promesa);
            $operationRows = $this->operationRows($operations, $promesa);
            $scheduleRows = $this->scheduleRows($promesa);

            $document = new TemplateProcessor($template);
            $this->fillDocument($document, $promesa, $client, $operationRows, $scheduleRows);

            $tmpDir = storage_path('app/tmp');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0775, true);
            }

            $docxOut = $tmpDir."/Conv_{$promesa->dni}.docx";
            $pdfOut = $tmpDir."/Conv_{$promesa->dni}.pdf";

            $document->saveAs($docxOut);

            Log::info('PROMESA_DOCX_INSPECT', [
                'req_id' => $requestId,
                'promesa_id' => $promesa->id,
                'dni' => $promesa->dni,
                'tpl' => $template,
                'ops_count' => count($operations),
                'cuotas_count' => $promesa->relationLoaded('cuotas') ? $promesa->cuotas->count() : null,
                'docx' => $this->fileMeta($docxOut),
                'inspect' => $this->inspectDocx($docxOut),
            ]);

            try {
                $pdfOut = $this->convertToPdf($docxOut, $tmpDir, $promesa);
            } catch (Throwable $e) {
                Log::warning('iLovePDF fallo, entregando DOCX', [
                    'req_id' => $requestId,
                    'promesa_id' => $promesa->id,
                    'dni' => $promesa->dni,
                    'step' => $step ?? 'unknown',
                    'msg' => $e->getMessage(),
                    'exception' => get_class($e),
                    'docx' => $this->fileMeta($docxOut),
                    'inspect' => $this->inspectDocx($docxOut),
                ]);

                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }

            return response()->file($pdfOut, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
            ]);
        } catch (Throwable $e) {
            Log::error('Error generando Conv PDF', [
                'req_id' => $requestId,
                'promesa_id' => $promesa->id ?? null,
                'dni' => $promesa->dni ?? null,
                'msg' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            if (! empty($docxOut) && is_file($docxOut)) {
                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }

            abort(500, 'No se pudo generar el PDF del acuerdo.');
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

        return DB::table('clientes_cuentas')
            ->where('numdoc', $promesa->dni)
            ->pluck('operacion')
            ->filter()
            ->all();
    }

    private function clientFor(PromesaPago $promesa): object
    {
        $client = DB::table('clientes_cuentas')
            ->where('numdoc', $promesa->dni)
            ->orderByDesc('updated_at')
            ->first(['numdoc', 'nombre', 'direccion']);

        return (object) [
            'numdoc' => (string) ($client->numdoc ?? $promesa->dni),
            'nombre' => (string) ($client->nombre ?? ''),
            'direccion' => (string) ($client->direccion ?? ''),
        ];
    }

    private function operationRows(array $operations, PromesaPago $promesa): object
    {
        $rows = DB::table('clientes_cuentas')
            ->select('operacion', 'deuda_total', 'entidad')
            ->whereIn('operacion', $operations)
            ->get();

        $byOperation = $rows->keyBy('operacion');
        $entity = (string) ($rows->pluck('entidad')->filter()->countBy()->sortDesc()->keys()->first()
            ?? DB::table('clientes_cuentas')->where('numdoc', $promesa->dni)->value('entidad') ?? '');

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

    private function convertToPdf(string $docxOut, string $tmpDir, PromesaPago $promesa): string
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');

        if (! $public || ! $secret) {
            throw new \RuntimeException('Llaves iLovePDF no configuradas');
        }

        $ilovepdf = new Ilovepdf($public, $secret);
        $task = $ilovepdf->newTask('officepdf');
        $task->setOutputFilename("Conv_{$promesa->dni}");
        $task->addFile($docxOut);
        $task->execute();
        $task->download($tmpDir);

        $candidates = glob($tmpDir."/Conv_{$promesa->dni}*.pdf");

        if (! $candidates) {
            throw new \RuntimeException('iLovePDF no devolvio un PDF en el directorio de salida');
        }

        return $candidates[0];
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

        $zip = new ZipArchive();
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

        $dom = new \DOMDocument();

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
