<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Ilovepdf\Ilovepdf;
use Carbon\Carbon;
use Throwable;
use ZipArchive;

class PromesaPdfController extends Controller
{
    /**
     * Metadatos simples del archivo (para ver si es ZIP, tamaño, hash, etc.)
     */
    private function fileMeta(string $path): array
    {
        clearstatcache(true, $path);

        $meta = [
            'path'   => $path,
            'exists' => is_file($path),
            'size'   => null,
            'mtime'  => null,
            'sha1'   => null,
            'head2'  => null, // "PK" si es zip
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

    /**
     * Inspección del DOCX:
     * - ¿abre como ZIP?
     * - ¿tiene partes esenciales?
     * - ¿hay algún XML interno inválido (y cuál)?
     */
    private function inspectDocx(string $docxAbs): array
    {
        $out = [
            'zip_ok'       => false,
            'has_types'    => false,
            'has_document' => false,
            'xml_ok'       => null,  // null = no evaluado (si zip no abrió), true/false
            'xml_error'    => null,  // primer error encontrado
        ];

        if (!is_file($docxAbs)) {
            $out['xml_ok'] = null;
            $out['xml_error'] = ['reason' => 'file_not_found'];
            return $out;
        }

        $zip = new ZipArchive();
        $open = $zip->open($docxAbs);

        if ($open !== true) {
            $out['xml_ok'] = null;
            $out['xml_error'] = ['reason' => 'zip_open_failed', 'code' => $open];
            return $out;
        }

        $out['zip_ok'] = true;
        $out['has_types']    = ($zip->locateName('[Content_Types].xml') !== false);
        $out['has_document'] = ($zip->locateName('word/document.xml') !== false);

        // Parseo básico de XML internos para identificar XML roto (causa común de DamagedFile)
        $out['xml_ok'] = true;

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name) continue;

            // XML y RELS (rels también son XML)
            $isXml = str_ends_with(strtolower($name), '.xml') || str_ends_with(strtolower($name), '.rels');
            if (!$isXml) continue;

            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') continue;

            libxml_clear_errors();

            // LIBXML_NONET: evita cargar recursos externos
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

    public function acuerdo(PromesaPago $promesa)
    {
        $reqId = now()->format('YmdHis') . '_' . bin2hex(random_bytes(3));
        $docxOut = null;
        $pdfOut  = null;

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
            $fmtDebt = fn($v) => number_format((float)$v, 2, '.', ',');
            $fmtNo00 = function ($v) {
                $v = (float)$v;
                return fmod($v,1.0)==0.0 ? number_format($v,0,'.',',') : number_format($v,2,'.',',');
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
                        ? round($montoTotal / max(1,count($ops)), 2)
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

            // ---- Llenar plantilla
            $doc = new TemplateProcessor($tpl);

            $creador = DB::table('users')->where('id',$promesa->user_id)->value('name');
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
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                $doc->cloneRowAndSetValues('operacion', $tablaOps ?: [[
                    'operacion'=>'','deuda_total'=>$fmtDebt(0),'monto_divido'=>$fmtNo00($montoTotal)
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

            // Directorio temporal (SIN CAMBIOS, como lo querías)
            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            // Guardar DOCX (MISMO NOMBRE)
            $docxOut = $tmpDir . "/Conv_{$promesa->dni}.docx";
            $pdfOut  = $tmpDir . "/Conv_{$promesa->dni}.pdf";

            $doc->saveAs($docxOut);

            // ===== INSPECTOR (LOG) =====
            $meta = $this->fileMeta($docxOut);
            $insp = $this->inspectDocx($docxOut);

            Log::info('PROMESA_DOCX_INSPECT', [
                'req_id'     => $reqId,
                'promesa_id' => $promesa->id,
                'dni'        => $promesa->dni,
                'tpl'        => $tpl,
                'ops_count'  => count($ops),
                'cuotas_count' => $promesa->relationLoaded('cuotas') ? $promesa->cuotas->count() : null,
                'docx'       => $meta,
                'inspect'    => $insp,
            ]);

            // iLovePDF
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
                $task->addFile($docxOut);

                $step = 'execute';
                $task->execute();

                $step = 'download';
                $task->download($tmpDir);

                $cands = glob($tmpDir . "/Conv_{$promesa->dni}*.pdf");
                if (!$cands) {
                    throw new \RuntimeException('iLovePDF no devolvió un PDF en el directorio de salida');
                }
                $pdfOut = $cands[0];

            } catch (Throwable $e) {
                // Log extendido para saber "qué está mal" del DOCX cuando iLovePDF dice DamagedFile
                Log::warning('iLovePDF falló, entregando DOCX', [
                    'req_id'     => $reqId,
                    'promesa_id' => $promesa->id,
                    'dni'        => $promesa->dni,
                    'step'       => $step ?? 'unknown',
                    'msg'        => $e->getMessage(),
                    'exception'  => get_class($e),
                    'docx'       => $this->fileMeta($docxOut),
                    'inspect'    => $this->inspectDocx($docxOut),
                ]);

                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }

            return response()->file($pdfOut, [
                'Content-Type'  => 'application/pdf',
                'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
            ]);

        } catch (Throwable $e) {
            Log::error('Error generando Conv PDF', [
                'req_id'     => $reqId,
                'promesa_id' => $promesa->id ?? null,
                'dni'        => $promesa->dni ?? null,
                'msg'        => $e->getMessage(),
                'exception'  => get_class($e),
            ]);

            if (!empty($docxOut ?? null) && is_file($docxOut)) {
                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }

            abort(500, 'No se pudo generar el PDF del acuerdo.');
        }
    }

    // Escapa una cadena para que sea segura en XML (plantilla DOCX)
    private function xmlSafe($v): string
    {
        $v = (string)($v ?? '');

        // Si ya viniera con entidades (&amp;), lo normaliza primero
        $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Escapa para XML (clave para DOCX)
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

}
