<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\IOFactory;
use Carbon\Carbon;
use Throwable;

class PromesaPdfController extends Controller
{
    public function acuerdo(PromesaPago $promesa)
    {
        try {
            // 1) Plantilla (mismo nombre que usas)
            $tpl = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
            if (!is_file($tpl)) {
                abort(404, 'No se encontró la plantilla DOCX en: '.$tpl);
            }

            // Carga relaciones necesarias
            $promesa->loadMissing(['operaciones', 'cuotas']);

            // 2) Datos base del cliente (nuevo esquema: numdoc/nombre/direccion)
            $ops = [];
            if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
                $ops = $promesa->operaciones->pluck('operacion')->filter()->map(fn($x)=>(string)$x)->values()->all();
            } elseif (!empty($promesa->operacion)) {
                $ops = array_filter(array_map('trim', explode(',', (string)$promesa->operacion)));
            }

            // Fallback: si no hay detalle, toma todas las operaciones del DNI
            if (empty($ops)) {
                $ops = DB::table('clientes_cuentas')
                    ->where('numdoc', $promesa->dni)
                    ->pluck('operacion')->filter()->values()->all();
            }

            // Datos del cliente (por DNI)
            $cli = DB::table('clientes_cuentas')
                ->where('numdoc', $promesa->dni)
                ->orderByDesc('updated_at')
                ->first(['numdoc','nombre','direccion']);

            $numdoc    = (string)($cli->numdoc    ?? $promesa->dni);
            $nombre    = (string)($cli->nombre    ?? '');
            $direccion = (string)($cli->direccion ?? '');

            // 3) Deudas por operación para la tabla y distribución proporcional
            $byOp = DB::table('clientes_cuentas')
                ->select('operacion','deuda_total')
                ->whereIn('operacion', $ops)
                ->get()
                ->keyBy('operacion');

            $fmtMoney = fn($v) => number_format((float)$v, 2, '.', ','); // 28,165.17
            $fmtDate  = fn($v) => Carbon::parse($v)->format('Y-m-d');

            // Monto a repartir (convenio usa monto_convenio; cancelación usa monto)
            $montoTotal = $promesa->tipo === 'convenio'
                ? (float)($promesa->monto_convenio ?? 0)
                : (float)($promesa->monto ?? 0);

            // Suma de deudas
            $sumDeu = 0.0;
            foreach ($ops as $op) {
                $sumDeu += (float)($byOp[$op]->deuda_total ?? 0);
            }

            // Filas para la tabla de operaciones
            $tablaOps = [];
            $acum = 0.0;
            foreach ($ops as $i => $op) {
                $deu = (float)($byOp[$op]->deuda_total ?? 0);
                // proporcional (última fila ajusta)
                if ($sumDeu > 0) {
                    if ($i < count($ops) - 1) {
                        $parte = round($montoTotal * ($deu / $sumDeu), 2);
                        $acum += $parte;
                    } else {
                        $parte = round($montoTotal - $acum, 2);
                    }
                } else {
                    // si no hay deudas registradas, reparte equitativo
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

            // 4) Cronograma (usa promesa_cuotas; si no hay, aplica fallbacks solicitados)
            $rowsCrono = [];
            if ($promesa->relationLoaded('cuotas') && $promesa->cuotas->count()) {
                foreach ($promesa->cuotas as $c) {
                    $num = str_pad((int)$c->nro, 2, '0', STR_PAD_LEFT);
                    $rowsCrono[] = [
                        'nro_cuotas'  => $num,
                        'monto_cuota' => $fmtMoney($c->monto),
                        'fecha_pago'  => $fmtDate($c->fecha),
                    ];
                }
            } else {
                if ($promesa->tipo === 'convenio') {
                    // Si no hay detalle: 1 fila con nro_cuotas del registro, monto_cuota si existe
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
                } else { // cancelación
                    $rowsCrono[] = [
                        'nro_cuotas'  => '01',
                        'monto_cuota' => $fmtMoney($promesa->monto ?? 0),
                        'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                    ];
                }
            }

            // 5) Llenar DOCX
            $doc = new TemplateProcessor($tpl);

            // Campos simples del encabezado
            $doc->setValue('id',             str_pad((string)$promesa->id, 4, '0', STR_PAD_LEFT));
            $doc->setValue('fecha_promesa',  $promesa->fecha_promesa ? $fmtDate($promesa->fecha_promesa) : '');
            $doc->setValue('nombre',         $nombre);
            $doc->setValue('numdoc',         $numdoc);
            $doc->setValue('telefono',       (string)($promesa->telefono ?? ''));
            $doc->setValue('direccion',      $direccion);

            // Tabla: Operaciones / Deuda total / Monto para cancelación
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                // Clona por marcador 'operacion' (los otros deben estar en la misma fila)
                if (count($tablaOps) > 0) {
                    $doc->cloneRowAndSetValues('operacion', $tablaOps);
                } else {
                    // al menos una fila vacía si no hay
                    $doc->setValue('operacion',   '');
                    $doc->setValue('deuda_total', $fmtMoney(0));
                    $doc->setValue('monto',       $fmtMoney($montoTotal));
                }
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

            // Tabla: Cronograma
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

            // 6) Guardar y convertir a PDF con mPDF
            $tmpDir  = storage_path('app/tmp');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            $docxOut = $tmpDir . "/Acuerdo_{$promesa->dni}_{$promesa->id}.docx";
            $pdfOut  = $tmpDir . "/Acuerdo_{$promesa->dni}_{$promesa->id}.pdf";

            $doc->saveAs($docxOut);

            Settings::setPdfRendererName(Settings::PDF_RENDERER_MPDF);
            Settings::setPdfRendererPath(base_path('vendor/mpdf/mpdf'));

            $phpWord = IOFactory::load($docxOut);
            IOFactory::createWriter($phpWord, 'PDF')->save($pdfOut);

            return response()->file($pdfOut, [
                'Content-Type' => 'application/pdf',
                'Cache-Control'=> 'private, max-age=0, no-store, no-cache, must-revalidate',
            ]);
        } catch (Throwable $e) {
            Log::error('Error generando Acuerdo PDF', [
                'promesa_id' => $promesa->id ?? null,
                'msg'        => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            if (!empty($docxOut ?? null) && is_file($docxOut)) {
                return response()->download($docxOut, "Acuerdo_{$promesa->dni}.docx");
            }
            abort(500, 'No se pudo generar el PDF del acuerdo.');
        }
    }
}
