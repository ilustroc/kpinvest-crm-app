<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Ilovepdf\Ilovepdf;
use Carbon\Carbon;
use Throwable;

class PromesaPdfController extends Controller
{
    public function acuerdo(PromesaPago $promesa)
    {
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
                $ops = DB::table('clientes_cuentas')->where('numdoc',$promesa->dni)->pluck('operacion')->filter()->all();
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
            $fmtDebt = fn($v) => number_format((float)$v, 2, '.', ','); // para deuda_total
            $fmtNo00 = function ($v) { // para monto_divido, monto, monto_cuota
                $v = (float)$v;
                return fmod($v,1.0)==0.0 ? number_format($v,0,'.',',') : number_format($v,2,'.',',');
            };
            $fmtDate = fn($v) => \Carbon\Carbon::parse($v)->format('d/m/Y');

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
            $sumaCronoRaw = 0.0; // <--- suma de monto_cuota

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
                    if ($mon <= 0 && (float)($promesa->monto_convenio ?? 0) > 0) $mon = (float)$promesa->monto_convenio / $n;
                    $sumaCronoRaw += $mon;
                    $rowsCrono[] = [
                        'nro_cuotas'  => str_pad($n, 2, '0', STR_PAD_LEFT),
                        'monto_cuota' => $fmtNo00($mon),
                        'fecha_pago'  => $fmtDate($promesa->fecha_pago ?? $promesa->fecha_promesa ?? now()),
                    ];
                } else { // cancelación
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
            $doc->setValue('nombre',        $nombre);
            $doc->setValue('numdoc',        $numdoc);
            $doc->setValue('telefono',      (string)($promesa->telefono ?? ''));
            $doc->setValue('direccion',     $direccion);
            $doc->setValue('entidad',       $entidad);

            // === ${monto} ahora es la suma de ${monto_cuota}
            $doc->setValue('monto', $fmtNo00($sumaCronoRaw));

            // Tabla de operaciones
            if (method_exists($doc, 'cloneRowAndSetValues')) {
                $doc->cloneRowAndSetValues('operacion', $tablaOps ?: [['operacion'=>'','deuda_total'=>$fmtDebt(0),'monto_divido'=>$fmtNo00($montoTotal)]]);
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

            // Directorio temporal
            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            // Guardar DOCX
            $docxOut = $tmpDir . "/Conv_{$promesa->dni}.docx";
            $pdfOut  = $tmpDir . "/Conv_{$promesa->dni}.pdf";
            $doc->saveAs($docxOut);

            // iLovePDF
            try {
                $public = config('services.ilovepdf.public');
                $secret = config('services.ilovepdf.secret');
                if (!$public || !$secret) {
                    throw new \RuntimeException('Llaves iLovePDF no configuradas');
                }

                $ilovepdf = new \Ilovepdf\Ilovepdf($public, $secret);
                $task = $ilovepdf->newTask('officepdf');
                $task->setOutputFilename("Conv_{$promesa->dni}");
                $task->addFile($docxOut);
                $task->execute();
                $task->download($tmpDir);

                $cands = glob($tmpDir . "/Conv_{$promesa->dni}*.pdf");
                if (!$cands) {
                    throw new \RuntimeException('iLovePDF no devolvió un PDF en el directorio de salida');
                }
                $pdfOut = $cands[0];
            } catch (\Throwable $e) {
                \Log::warning('iLovePDF falló, entregando DOCX', ['msg' => $e->getMessage()]);
                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }

            return response()->file($pdfOut, [
                'Content-Type'  => 'application/pdf',
                'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Error generando Conv PDF', ['promesa_id'=>$promesa->id ?? null, 'msg'=>$e->getMessage()]);
            if (!empty($docxOut ?? null) && is_file($docxOut)) {
                return response()->download($docxOut, "Conv_{$promesa->dni}.docx");
            }
            abort(500, 'No se pudo generar el PDF del acuerdo.');
        }
    }
}
