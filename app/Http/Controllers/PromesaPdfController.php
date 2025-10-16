<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Carbon\Carbon;
use Throwable;

class PromesaPdfController extends Controller
{
    public function acuerdo(PromesaPago $promesa)
    {
        try {
            // 1) Plantilla
            $tpl = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
            if (!is_file($tpl)) {
                abort(404, 'No se encontró la plantilla DOCX en: '.$tpl);
            }

            // Relaciones
            $promesa->loadMissing(['operaciones', 'cuotas']);

            // 2) Operaciones incluidas
            $ops = [];
            if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
                $ops = $promesa->operaciones->pluck('operacion')->filter()->map(fn($x)=>(string)$x)->values()->all();
            } elseif (!empty($promesa->operacion)) {
                $ops = array_filter(array_map('trim', explode(',', (string)$promesa->operacion)));
            }
            if (empty($ops)) {
                $ops = DB::table('clientes_cuentas')
                    ->where('numdoc', $promesa->dni)
                    ->pluck('operacion')->filter()->values()->all();
            }

            // 3) Datos de cliente (nuevo esquema)
            $cli = DB::table('clientes_cuentas')
                ->where('numdoc', $promesa->dni)
                ->orderByDesc('updated_at')
                ->first(['numdoc','nombre','direccion']);

            $numdoc    = (string)($cli->numdoc    ?? $promesa->dni);
            $nombre    = (string)($cli->nombre    ?? '');
            $direccion = (string)($cli->direccion ?? '');

            // Nombre del usuario (para ${name})
            $userName = (string) DB::table('users')->where('id', $promesa->user_id)->value('name') ?: '';

            // 4) Datos por operación + distribución proporcional del monto
            $byOp = DB::table('clientes_cuentas')
                ->select('operacion','deuda_total')
                ->whereIn('operacion', $ops)
                ->get()
                ->keyBy('operacion');

            $fmtMoney = fn($v) => number_format((float)$v, 2, '.', ',');     // 28,165.17
            $fmtDate  = fn($v) => Carbon::parse($v)->format('d/m/Y');        // 18/10/2025

            $montoTotal = $promesa->tipo === 'convenio'
                ? (float)($promesa->monto_convenio ?? 0)
                : (float)($promesa->monto ?? 0);

            $sumDeu = 0.0;
            foreach ($ops as $op) {
                $sumDeu += (float)($byOp[$op]->deuda_total ?? 0);
            }

            $tablaOps = [];
            $acum = 0.0;
            foreach ($ops as $i => $op) {
                $deu = (float)($byOp[$op]->deuda_total ?? 0);

                if ($sumDeu > 0) {
                    if ($i < count($ops) - 1) {
                        $parte = round($montoTotal * ($deu / $sumDeu), 2);
                        $acum += $parte;
                    } else {
                        $parte = round($montoTotal - $acum, 2);
                    }
                } else {
                    if ($i < count($ops) - 1) {
                        $parte = round($montoTotal / max(1, count($ops)), 2);
                        $acum += $parte;
                    } else {
                        $parte = round($montoTotal - $acum, 2);
                    }
                }

                $tablaOps[] = [
                    'operacion'   => (string)$op,
                    'deuda_total' => $fmtMoney($deu),
                    'monto'       => $fmtMoney($parte),
                ];
            }

            // 5) Cronograma
            $rowsCrono = [];
            if ($promesa->relationLoaded('cuotas') && $promesa->cuotas->count()) {
                foreach ($promesa->cuotas as $c) {
                    $rowsCrono[] = [
                        'nro_cuotas'  => str_pad((int)$c->nro, 2, '0', STR_PAD_LEFT),
                        'monto_cuota' => $fmtMoney($c->monto),
                        'fecha_pago'  => $fmtDate($c->fecha),
                    ];
                }
            } else {
                if ($promesa->tipo === 'convenio') {
                    $n   = (int)($promesa->nro_cuotas ?? 1) ?: 1;
                    $mon = (float)($promesa->monto_cuota ?? 0);
                    if ($mon <= 0 && (float)($promesa->monto_convenio ?? 0) > 0 && $n > 0) {
                        $mon = (float)$promesa->monto_convenio / $n;
                    }
                    $rowsCrono[] = [
                        'nro_cuotas'  => str_pad($n, 2, '0', STR_PAD_LEFT),
                        'monto_cuota' => $fmtMoney($mon),
                        'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                    ];
                } else {
                    $rowsCrono[] = [
                        'nro_cuotas'  => '01',
                        'monto_cuota' => $fmtMoney($promesa->monto ?? 0),
                        'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                    ];
                }
            }

            // 6) Llenar DOCX
            $doc = new TemplateProcessor($tpl);

            // Encabezado y campos simples
            $doc->setValue('id',            str_pad((string)$promesa->id, 4, '0', STR_PAD_LEFT));
            $doc->setValue('fecha_promesa', $promesa->fecha_promesa ? $fmtDate($promesa->fecha_promesa) : '');
            $doc->setValue('nombre',        $nombre);
            $doc->setValue('numdoc',        $numdoc);
            $doc->setValue('telefono',      (string)($promesa->telefono ?? ''));
            $doc->setValue('direccion',     $direccion);
            $doc->setValue('name',          $userName);

            // Para la leyenda "S/.${monto}" o si la plantilla tiene el typo "moto"
            $doc->setValue('monto', $fmtMoney($montoTotal));

            // Tabla de operaciones
            if (method_exists($doc, 'cloneRowAndSetValues') && count($tablaOps) > 0) {
                $doc->cloneRowAndSetValues('operacion', $tablaOps);
            } else {
                $nRows = max(1, count($tablaOps));
                $doc->cloneRow('operacion', $nRows);
                if ($tablaOps) {
                    foreach ($tablaOps as $i => $r) {
                        $idx = $i + 1;
                        $doc->setValue("operacion#{$idx}",   $r['operacion']);
                        $doc->setValue("deuda_total#{$idx}", $r['deuda_total']);
                        $doc->setValue("monto#{$idx}",       $r['monto']);
                    }
                } else {
                    $doc->setValue('operacion#1',   '');
                    $doc->setValue('deuda_total#1', $fmtMoney(0));
                    $doc->setValue('monto#1',       $fmtMoney($montoTotal));
                }
            }

            // Tabla de cronograma
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                $doc->cloneRowAndSetValues('nro_cuotas', $rowsCrono);
            } else {
                $doc->cloneRow('nro_cuotas', count($rowsCrono));
                foreach ($rowsCrono as $i => $r) {
                    $idx = $i + 1;
                    $doc->setValue("nro_cuotas#{$idx}",  $r['nro_cuotas']);
                    $doc->setValue("monto_cuota#{$idx}", $r['monto_cuota']);
                    $doc->setValue("fecha_pago#{$idx}",  $r['fecha_pago']);
                }
            }

            // 7) Guardar DOCX y (si se puede) convertir con iLovePDF
            $tmpDir  = storage_path('app/tmp');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            $docxOut = $tmpDir . "/Conv_{$promesa->dni}.docx";
            $pdfOut  = $tmpDir . "/Conv_{$promesa->dni}.pdf";

            $doc->saveAs($docxOut);

            // Intento iLovePDF si el SDK y las llaves existen
            $pub = env('ILOVEPDF_PUBLIC_KEY');
            $sec = env('ILOVEPDF_SECRET_KEY');

            if (class_exists(\Ilovepdf\Ilovepdf::class) && $pub && $sec) {
                try {
                    $ilp  = new \Ilovepdf\Ilovepdf($pub, $sec);
                    $task = $ilp->newTask('officepdf'); // convierte DOCX -> PDF
                    $task->addFile($docxOut);
                    $task->execute();
                    // descarga al directorio temporal (el SDK coloca el nombre automáticamente)
                    $task->download($tmpDir);

                    // Busca el PDF más nuevo y lo renombra a nuestro nombre final
                    $latest = collect(glob($tmpDir.'/*.pdf'))
                        ->sortByDesc(fn($p)=>filemtime($p))
                        ->first();
                    if ($latest) {
                        @rename($latest, $pdfOut);
                        return response()->file($pdfOut, [
                            'Content-Type' => 'application/pdf',
                            'Cache-Control'=> 'private, max-age=0, no-store, no-cache, must-revalidate',
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('iLovePDF falló, devolviendo DOCX', ['err'=>$e->getMessage()]);
                }
            }

            // Si no hay iLovePDF o falló, devolvemos el DOCX (mantiene el formato original)
            return response()->download($docxOut, basename($docxOut));
        } catch (Throwable $e) {
            Log::error('Error generando Conv PDF', [
                'promesa_id' => $promesa->id ?? null,
                'msg'        => $e->getMessage(),
            ]);
            abort(500, 'No se pudo generar el documento.');
        }
    }
}
