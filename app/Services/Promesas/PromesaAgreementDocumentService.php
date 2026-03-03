<?php

namespace App\Services\Promesas;

use App\Models\PromesaPago;
use Carbon\Carbon;
use Ilovepdf\Ilovepdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class PromesaAgreementDocumentService
{
    public function build(PromesaPago $promesa): array
    {
        $reqId = now()->format('YmdHis') . '_' . bin2hex(random_bytes(3));

        $promesa->loadMissing(['operaciones','cuotas']);

        $tpl = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
        if (!is_file($tpl)) {
            throw new \RuntimeException('No se encontró la plantilla: '.$tpl);
        }

        // Directorio temporal (SIN CAMBIOS)
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $dni = trim((string)$promesa->dni);

        // Nombres (MISMO NOMBRE)
        $docxAbs = $tmpDir . "/Conv_{$dni}.docx";
        $pdfAbs  = $tmpDir . "/Conv_{$dni}.pdf";

        // ===== Operaciones involucradas
        $ops = $this->resolveOperaciones($promesa);

        // ===== Cliente (numdoc/nombre/direccion)
        $cli = DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->orderByDesc('updated_at')
            ->first(['numdoc','nombre','direccion']);

        $numdoc    = (string)($cli->numdoc ?? $dni);
        $nombre    = (string)($cli->nombre ?? '');
        $direccion = (string)($cli->direccion ?? '');

        // ===== Deudas por operación + entidad predominante
        $opsRows = DB::table('clientes_cuentas')
            ->select('operacion','deuda_total','entidad')
            ->whereIn('operacion', $ops)
            ->get();

        $byOp    = $opsRows->keyBy('operacion');
        $entidad = (string)(
            $opsRows->pluck('entidad')->filter()->countBy()->sortDesc()->keys()->first()
            ?? DB::table('clientes_cuentas')->where('numdoc', $dni)->value('entidad')
            ?? ''
        );

        // ===== Formateadores
        $fmtDebt = fn($v) => number_format((float)$v, 2, '.', ',');
        $fmtNo00 = function ($v) {
            $v = (float)$v;
            return fmod($v, 1.0) == 0.0 ? number_format($v, 0, '.', ',') : number_format($v, 2, '.', ',');
        };
        $fmtDate = fn($v) => Carbon::parse($v)->format('d/m/Y');

        // ===== Monto a repartir (columna monto_divido)
        $montoTotal = $promesa->tipo === 'convenio'
            ? (float)($promesa->monto_convenio ?? 0)
            : (float)($promesa->monto ?? 0);

        $sumDeu = $ops ? array_sum(array_map(fn($op) => (float)($byOp[$op]->deuda_total ?? 0), $ops)) : 0.0;

        // ===== Tabla operaciones
        $tablaOps = [];
        $acum = 0.0;

        foreach ($ops as $i => $op) {
            $deu = (float)($byOp[$op]->deuda_total ?? 0);

            if ($sumDeu > 0) {
                $parte = ($i < count($ops)-1)
                    ? round($montoTotal * ($deu / $sumDeu), 2)
                    : round($montoTotal - $acum, 2);
                if ($i < count($ops)-1) $acum += $parte;
            } else {
                $parte = ($i < count($ops)-1)
                    ? round($montoTotal / max(1, count($ops)), 2)
                    : round($montoTotal - $acum, 2);
                if ($i < count($ops)-1) $acum += $parte;
            }

            $tablaOps[] = [
                'operacion'    => (string)$op,
                'deuda_total'  => $fmtDebt($deu),
                'monto_divido' => $fmtNo00($parte),
            ];
        }

        // ===== Cronograma (y suma para ${monto})
        [$rowsCrono, $sumaCronoRaw] = $this->buildCronograma($promesa, $fmtNo00, $fmtDate);

        // ===== Llenar plantilla
        $doc = new TemplateProcessor($tpl);

        $creador = DB::table('users')->where('id', $promesa->user_id)->value('name');

        $doc->setValue('name', (string)($creador ?? ''));
        $doc->setValue('id', str_pad((string)$promesa->id, 4, '0', STR_PAD_LEFT));
        $doc->setValue('created_at', $promesa->created_at ? $fmtDate($promesa->created_at) : '');

        $doc->setValue('nombre',    $this->xmlSafe($nombre));
        $doc->setValue('numdoc',    $numdoc);
        $doc->setValue('telefono',  (string)($promesa->telefono ?? ''));
        $doc->setValue('direccion', $this->xmlSafe($direccion));
        $doc->setValue('entidad',   $entidad);

        // ${monto} = suma de cuotas
        $doc->setValue('monto', $fmtNo00($sumaCronoRaw));

        // Tabla de operaciones
        $this->fillTable($doc, 'operacion', $tablaOps ?: [[
            'operacion' => '',
            'deuda_total' => $fmtDebt(0),
            'monto_divido' => $fmtNo00($montoTotal),
        ]]);

        // Tabla de cronograma
        $this->fillTable($doc, 'nro_cuotas', $rowsCrono);

        // Guardar DOCX
        $doc->saveAs($docxAbs);

        // ===== INSPECTOR LOG
        Log::info('PROMESA_DOCX_INSPECT', [
            'req_id'       => $reqId,
            'promesa_id'   => $promesa->id,
            'dni'          => $dni,
            'tpl'          => $tpl,
            'ops_count'    => count($ops),
            'cuotas_count' => $promesa->relationLoaded('cuotas') ? $promesa->cuotas->count() : null,
            'docx'         => $this->fileMeta($docxAbs),
            'inspect'      => $this->inspectDocx($docxAbs),
        ]);

        // Convertir a PDF por iLovePDF
        $pdfOk = false;

        try {
            $this->convertDocxToPdfViaIlovepdf($docxAbs, $tmpDir, "Conv_{$dni}");

            $cands = glob($tmpDir . "/Conv_{$dni}*.pdf");
            if (!$cands) {
                throw new \RuntimeException('iLovePDF no devolvió un PDF en el directorio de salida');
            }

            // toma el primero (como tu versión)
            $pdfAbs = $cands[0];
            $pdfOk = true;

        } catch (Throwable $e) {
            Log::warning('iLovePDF falló, entregando DOCX', [
                'req_id'     => $reqId,
                'promesa_id' => $promesa->id,
                'dni'        => $dni,
                'msg'        => $e->getMessage(),
                'exception'  => get_class($e),
                'docx'       => $this->fileMeta($docxAbs),
                'inspect'    => $this->inspectDocx($docxAbs),
            ]);
        }

        return [
            'req_id'   => $reqId,
            'dni'      => $dni,
            'docxAbs'  => $docxAbs,
            'pdfAbs'   => $pdfAbs,
            'pdfOk'    => $pdfOk,
        ];
    }

    // ===================== Helpers =====================

    private function resolveOperaciones(PromesaPago $promesa): array
    {
        $ops = [];

        if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
            $ops = $promesa->operaciones->pluck('operacion')->map(fn($x) => (string)$x)->all();
        } elseif (!empty($promesa->operacion)) {
            $ops = array_filter(array_map('trim', explode(',', (string)$promesa->operacion)));
        }

        if (empty($ops)) {
            $ops = DB::table('clientes_cuentas')
                ->where('numdoc', $promesa->dni)
                ->pluck('operacion')
                ->filter()
                ->map(fn($x) => (string)$x)
                ->all();
        }

        $ops = array_values(array_unique(array_filter(array_map('strval', $ops))));
        if (!$ops) throw new \RuntimeException('No se pudo determinar operaciones para la promesa.');

        return $ops;
    }

    private function buildCronograma(PromesaPago $promesa, callable $fmtNo00, callable $fmtDate): array
    {
        $rowsCrono = [];
        $sumaCronoRaw = 0.0;

        if ($promesa->relationLoaded('cuotas') && $promesa->cuotas->count()) {
            foreach ($promesa->cuotas as $c) {
                $sumaCronoRaw += (float)$c->monto;
                $rowsCrono[] = [
                    'nro_cuotas'  => str_pad((int)$c->nro, 2, '0', STR_PAD_LEFT),
                    'monto_cuota' => $fmtNo00($c->monto),
                    'fecha_pago'  => $fmtDate($c->fecha),
                ];
            }
        } else {
            if ($promesa->tipo === 'convenio') {
                $n = (int)($promesa->nro_cuotas ?? 1) ?: 1;
                $mon = (float)($promesa->monto_cuota ?? 0);

                if ($mon <= 0 && (float)($promesa->monto_convenio ?? 0) > 0) {
                    $mon = (float)$promesa->monto_convenio / $n;
                }

                $sumaCronoRaw += $mon;
                $rowsCrono[] = [
                    'nro_cuotas'  => str_pad($n, 2, '0', STR_PAD_LEFT),
                    'monto_cuota' => $fmtNo00($mon),
                    'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                ];
            } else {
                $mon = (float)($promesa->monto ?? 0);
                $sumaCronoRaw += $mon;
                $rowsCrono[] = [
                    'nro_cuotas'  => '01',
                    'monto_cuota' => $fmtNo00($mon),
                    'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                ];
            }
        }

        return [$rowsCrono, $sumaCronoRaw];
    }

    private function fillTable(TemplateProcessor $doc, string $key, array $rows): void
    {
        if (method_exists($doc, 'cloneRowAndSetValues')) {
            $doc->cloneRowAndSetValues($key, $rows);
            return;
        }

        $doc->cloneRow($key, count($rows));

        foreach ($rows as $i => $r) {
            $idx = $i + 1;
            foreach ($r as $k => $v) {
                $doc->setValue("{$k}#{$idx}", (string)$v);
            }
        }
    }

    private function convertDocxToPdfViaIlovepdf(string $docxAbs, string $outDir, string $outputFilename): void
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');
        if (!$public || !$secret) throw new \RuntimeException('Llaves iLovePDF no configuradas');

        $ilovepdf = new Ilovepdf($public, $secret);
        $task = $ilovepdf->newTask('officepdf');
        $task->setOutputFilename($outputFilename);
        $task->addFile($docxAbs);
        $task->execute();
        $task->download($outDir);
    }

    // ============ Inspector / XML safe ============

    private function fileMeta(string $path): array
    {
        clearstatcache(true, $path);

        $meta = [
            'path'   => $path,
            'exists' => is_file($path),
            'size'   => null,
            'mtime'  => null,
            'sha1'   => null,
            'head2'  => null,
        ];

        if (!is_file($path)) return $meta;

        $meta['size']  = @filesize($path) ?: null;
        $meta['mtime'] = @filemtime($path) ?: null;

        $fh = @fopen($path, 'rb');
        if ($fh) {
            $meta['head2'] = @fread($fh, 2) ?: null;
            @fclose($fh);
        }

        if (($meta['size'] ?? 0) > 0) {
            $meta['sha1'] = @sha1_file($path) ?: null;
        }

        return $meta;
    }

    private function inspectDocx(string $docxAbs): array
    {
        $out = [
            'zip_ok'       => false,
            'has_types'    => false,
            'has_document' => false,
            'xml_ok'       => null,
            'xml_error'    => null,
        ];

        if (!is_file($docxAbs)) {
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
        $out['has_types']    = ($zip->locateName('[Content_Types].xml') !== false);
        $out['has_document'] = ($zip->locateName('word/document.xml') !== false);

        $out['xml_ok'] = true;

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name) continue;

            $isXml = str_ends_with(strtolower($name), '.xml') || str_ends_with(strtolower($name), '.rels');
            if (!$isXml) continue;

            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') continue;

            libxml_clear_errors();
            $ok = @$dom->loadXML($xml, LIBXML_NONET);

            if (!$ok) {
                $errs = libxml_get_errors();
                $e0 = $errs[0] ?? null;

                $out['xml_ok'] = false;
                $out['xml_error'] = [
                    'entry'  => $name,
                    'msg'    => $e0 ? trim($e0->message) : 'xml_parse_failed',
                    'line'   => $e0->line ?? null,
                    'column' => $e0->column ?? null,
                    'level'  => $e0->level ?? null,
                    'code'   => $e0->code ?? null,
                ];
                break;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $zip->close();
        return $out;
    }

    private function xmlSafe($v): string
    {
        $v = (string)($v ?? '');
        $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}