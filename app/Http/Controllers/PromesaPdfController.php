<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use Carbon\Carbon;
use Ilovepdf\Ilovepdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class PromesaPdfController extends Controller
{
    /**
     * Limpia caracteres de control que pueden romper el XML interno del DOCX.
     */
    private function cleanDocxText($v): string
    {
        $v = (string)($v ?? '');
        // elimina caracteres de control invisibles (mantiene tab/newline/return)
        $v = preg_replace('/[^\P{C}\t\n\r]/u', '', $v);
        return trim($v);
    }

    /**
     * Metadatos para diagnosticar corrupción/colisiones.
     */
    private function docxMeta(string $path): array
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

        // Primeros 2 bytes: un DOCX válido debe iniciar con "PK" (zip)
        $fh = @fopen($path, 'rb');
        if ($fh) {
            $meta['head2'] = @fread($fh, 2) ?: null;
            @fclose($fh);
        }

        // Hash (puede costar un poco, pero es clave para ver si cambió entre pasos)
        if (($meta['size'] ?? 0) > 0) {
            $meta['sha1'] = @sha1_file($path) ?: null;
        }

        return $meta;
    }

    /**
     * Validación "barata" de DOCX (zip + partes esenciales). Ahorra créditos si falla.
     */
    private function validateDocx(string $path): array
    {
        $res = [
            'zip_ok'        => false,
            'has_types'     => false,
            'has_document'  => false,
            'error'         => null,
        ];

        if (!is_file($path)) {
            $res['error'] = 'file_not_found';
            return $res;
        }

        $zip = new ZipArchive();
        $ok = $zip->open($path);

        if ($ok !== true) {
            $res['error'] = 'zip_open_failed_'.$ok;
            return $res;
        }

        $res['zip_ok'] = true;
        $res['has_types']    = ($zip->locateName('[Content_Types].xml') !== false);
        $res['has_document'] = ($zip->locateName('word/document.xml') !== false);

        $zip->close();

        if (!$res['has_types'] || !$res['has_document']) {
            $res['error'] = 'missing_parts';
        }

        return $res;
    }

    public function acuerdo(PromesaPago $promesa)
    {
        $docxOut = null;
        $pdfOut  = null;

        // Id de request para rastrear colisiones
        $reqId = now()->format('YmdHis') . '_' . bin2hex(random_bytes(3));

        try {
            $tpl = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
            if (!is_file($tpl)) abort(404, 'No se encontró la plantilla: '.$tpl);

            $promesa->loadMissing(['operaciones','cuotas']);

            // ---- Operaciones involucradas
            $ops = [];
            if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
                $ops = $promesa->operaciones->pluck('operacion')->map(fn($x)=>(string)$x)->all();
            } elseif (!empty($promesa->operacion)) {
                $ops = array_filter(array_map('trim', explode(',', (string)$promesa->operacion)));
            }
            if (empty($ops)) {
                $ops = DB::table('clientes_cuentas')
                    ->where('numdoc',$promesa->dni)
                    ->pluck('operacion')
                    ->filter()
                    ->all();
            }

            // ---- Datos del cliente (numdoc/nombre/direccion)
            $cli = DB::table('clientes_cuentas')
                ->where('numdoc',$promesa->dni)
                ->orderByDesc('updated_at')
                ->first(['numdoc','nombre','direccion']);

            $numdoc    = (string)($cli->numdoc ?? $promesa->dni);
            $nombre    = (string)($cli->nombre ?? '');
            $direccion = (string)($cli->direccion ?? '');

            // ---- Deudas por operación + ENTIDAD predominante
            $opsRows = DB::table('clientes_cuentas')
                ->select('operacion','deuda_total','entidad')
                ->whereIn('operacion',$ops)
                ->get();

            $byOp     = $opsRows->keyBy('operacion');
            $entidad  = (string)($opsRows->pluck('entidad')->filter()->countBy()->sortDesc()->keys()->first()
                        ?? DB::table('clientes_cuentas')->where('numdoc',$promesa->dni)->value('entidad') ?? '');

            // ---- Helpers de formato
            $fmtDebt = fn($v) => number_format((float)$v, 2, '.', ','); // deuda_total
            $fmtNo00 = function ($v) {
                $v = (float)$v;
                return fmod($v, 1.0) == 0.0 ? number_format($v, 0, '.', ',') : number_format($v, 2, '.', ',');
            };
            $fmtDate = fn($v) => Carbon::parse($v)->format('d/m/Y');

            // ---- "Monto a repartir" SOLO para columna ${monto_divido}
            $montoTotal = $promesa->tipo === 'convenio'
                ? (float)($promesa->monto_convenio ?? 0)
                : (float)($promesa->monto ?? 0);

            $sumDeu = $ops ? array_sum(array_map(fn($op)=>(float)($byOp[$op]->deuda_total ?? 0), $ops)) : 0.0;

            // ---- Filas de la tabla de operaciones
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
                    'operacion'     => (string)$op,
                    'deuda_total'   => $fmtDebt($deu),
                    'monto_divido'  => $fmtNo00($parte),
                ];
            }

            // ---- Cronograma (y suma para ${monto})
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

            // ---- Plantilla
            $doc = new TemplateProcessor($tpl);

            // Limpieza (evita XML roto)
            $creador = DB::table('users')->where('id',$promesa->user_id)->value('name');

            $doc->setValue('name',       $this->cleanDocxText($creador ?? ''));
            $doc->setValue('id',         $this->cleanDocxText(str_pad((string)$promesa->id, 4, '0', STR_PAD_LEFT)));
            $doc->setValue('created_at', $this->cleanDocxText($promesa->created_at ? $fmtDate($promesa->created_at) : ''));
            $doc->setValue('nombre',     $this->cleanDocxText($nombre));
            $doc->setValue('numdoc',     $this->cleanDocxText($numdoc));
            $doc->setValue('telefono',   $this->cleanDocxText($promesa->telefono ?? ''));
            $doc->setValue('direccion',  $this->cleanDocxText($direccion));
            $doc->setValue('entidad',    $this->cleanDocxText($entidad));

            // ${monto} = suma de cuotas
            $doc->setValue('monto', $this->cleanDocxText($fmtNo00($sumaCronoRaw)));

            // Sanitiza tablas
            $tablaOps = array_map(fn($r) => [
                'operacion'    => $this->cleanDocxText($r['operacion'] ?? ''),
                'deuda_total'  => $this->cleanDocxText($r['deuda_total'] ?? ''),
                'monto_divido' => $this->cleanDocxText($r['monto_divido'] ?? ''),
            ], $tablaOps);

            $rowsCrono = array_map(fn($r) => [
                'nro_cuotas'  => $this->cleanDocxText($r['nro_cuotas'] ?? ''),
                'monto_cuota' => $this->cleanDocxText($r['monto_cuota'] ?? ''),
                'fecha_pago'  => $this->cleanDocxText($r['fecha_pago'] ?? ''),
            ], $rowsCrono);

            // Tabla de operaciones
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                $doc->cloneRowAndSetValues('operacion', $tablaOps ?: [[
                    'operacion'=>'',
                    'deuda_total'=>$fmtDebt(0),
                    'monto_divido'=>$fmtNo00($montoTotal),
                ]]);
            } else {
                $nRows = max(1, count($tablaOps));
                $doc->cloneRow('operacion', $nRows);
                if ($tablaOps) {
                    foreach ($tablaOps as $i=>$r) {
                        $idx = $i+1;
                        $doc->setValue("operacion#{$idx}",    $r['operacion']);
                        $doc->setValue("deuda_total#{$idx}",  $r['deuda_total']);
                        $doc->setValue("monto_divido#{$idx}", $r['monto_divido']);
                    }
                } else {
                    $doc->setValue('operacion#1','');
                    $doc->setValue('deuda_total#1',$fmtDebt(0));
                    $doc->setValue('monto_divido#1',$fmtNo00($montoTotal));
                }
            }

            // Tabla de cronograma
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                $doc->cloneRowAndSetValues('nro_cuotas', $rowsCrono);
            } else {
                $doc->cloneRow('nro_cuotas', count($rowsCrono));
                foreach ($rowsCrono as $i=>$r) {
                    $idx = $i+1;
                    $doc->setValue("nro_cuotas#{$idx}",  $r['nro_cuotas']);
                    $doc->setValue("monto_cuota#{$idx}", $r['monto_cuota']);
                    $doc->setValue("fecha_pago#{$idx}",  $r['fecha_pago']);
                }
            }

            // ===== TMP (subcarpeta por request para evitar colisiones) =====
            $baseTmp = storage_path('app/tmp/promesas');
            if (!is_dir($baseTmp)) @mkdir($baseTmp, 0775, true);

            $tmpDir = $baseTmp . "/{$promesa->dni}/{$reqId}";
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            // Mantienes el NOMBRE visible Conv_{dni}; internamente está aislado por reqId
            $docxOut = $tmpDir . "/Conv_{$promesa->dni}.docx";
            $pdfOut  = $tmpDir . "/Conv_{$promesa->dni}.pdf";

            // Guardar DOCX
            $doc->saveAs($docxOut);

            // Log estado del DOCX (post-save)
            $metaSaved = $this->docxMeta($docxOut);
            $valid = $this->validateDocx($docxOut);

            Log::info('PROMESA_DOCX generado', [
                'req_id'     => $reqId,
                'promesa_id' => $promesa->id,
                'dni'        => $promesa->dni,
                'tpl'        => $tpl,
                'ops_count'  => count($ops),
                'cuotas_count' => $promesa->relationLoaded('cuotas') ? $promesa->cuotas->count() : null,
                'docx'       => $metaSaved,
                'docx_valid' => $valid,
            ]);

            // Si el DOCX ya salió inválido, NI INTENTES iLovePDF (ahorra créditos)
            if (!$valid['zip_ok'] || !$valid['has_types'] || !$valid['has_document']) {
                Log::warning('PROMESA_DOCX inválido, se evita iLovePDF', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'docx'       => $metaSaved,
                    'docx_valid' => $valid,
                ]);

                if (ob_get_length()) { @ob_end_clean(); }
                return response()
                    ->download($docxOut, "Conv_{$promesa->dni}.docx")
                    ->deleteFileAfterSend(true);
            }

            // ===== iLovePDF =====
            try {
                $public = config('services.ilovepdf.public');
                $secret = config('services.ilovepdf.secret');
                if (!$public || !$secret) {
                    throw new \RuntimeException('Llaves iLovePDF no configuradas');
                }

                $step = 'init';
                $ilovepdf = new Ilovepdf($public, $secret);

                $step = 'newTask';
                $task = $ilovepdf->newTask('officepdf');

                $step = 'setOutputFilename';
                $task->setOutputFilename("Conv_{$promesa->dni}");

                $step = 'addFile';
                $metaBeforeAdd = $this->docxMeta($docxOut);
                Log::info('PROMESA_iLovePDF', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'step'       => $step,
                    'docx'       => $metaBeforeAdd,
                ]);
                $task->addFile($docxOut);

                // Verifica si cambió el DOCX (colisión) antes de ejecutar
                $step = 'pre_execute_check';
                $metaPreExec = $this->docxMeta($docxOut);
                if (($metaPreExec['sha1'] ?? null) !== ($metaBeforeAdd['sha1'] ?? null)) {
                    Log::warning('PROMESA_DOCX cambió antes de execute (posible colisión)', [
                        'req_id'     => $reqId,
                        'promesa_id' => $promesa->id,
                        'dni'        => $promesa->dni,
                        'before'     => $metaBeforeAdd,
                        'now'        => $metaPreExec,
                    ]);
                }

                $step = 'execute';
                Log::info('PROMESA_iLovePDF', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'step'       => $step,
                ]);
                $task->execute();

                $step = 'download';
                Log::info('PROMESA_iLovePDF', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'step'       => $step,
                    'out_dir'    => $tmpDir,
                ]);
                $task->download($tmpDir);

                // Esperado: Conv_{dni}.pdf dentro del tmpDir
                if (!is_file($pdfOut)) {
                    $cands = glob($tmpDir . "/Conv_{$promesa->dni}*.pdf");
                    if (!$cands) {
                        throw new \RuntimeException('iLovePDF no devolvió un PDF en el directorio de salida');
                    }
                    $pdfOut = $cands[0];
                }

            } catch (Throwable $e) {
                Log::warning('iLovePDF falló, entregando DOCX', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'step'       => $step ?? 'unknown',
                    'msg'        => $e->getMessage(),
                    'exception'  => get_class($e),
                    'docx'       => $this->docxMeta($docxOut),
                    'docx_valid' => $this->validateDocx($docxOut),
                    'tmpDir'     => $tmpDir,
                ]);

                if (ob_get_length()) { @ob_end_clean(); }
                return response()
                    ->download($docxOut, "Conv_{$promesa->dni}.docx")
                    ->deleteFileAfterSend(true);
            }

            // Respuesta PDF (nombre visible: Conv_{dni}.pdf)
            if (ob_get_length()) { @ob_end_clean(); }
            return response()
                ->file($pdfOut, [
                    'Content-Type'  => 'application/pdf',
                    'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
                ])
                ->deleteFileAfterSend(true);

        } catch (Throwable $e) {
            Log::error('Error generando Conv PDF', [
                'req_id'     => $reqId,
                'promesa_id' => $promesa->id ?? null,
                'dni'        => $promesa->dni ?? null,
                'msg'        => $e->getMessage(),
                'exception'  => get_class($e),
            ]);

            if (!empty($docxOut) && is_file($docxOut)) {
                if (ob_get_length()) { @ob_end_clean(); }
                return response()
                    ->download($docxOut, "Conv_{$promesa->dni}.docx")
                    ->deleteFileAfterSend(true);
            }

            abort(500, 'No se pudo generar el PDF del acuerdo.');
        }
    }
}
