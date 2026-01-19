<?php

namespace App\Services\Promesas;

use App\Http\Requests\StorePromesaRequest;
use App\Models\PromesaPago;
use App\Models\PromesaOperacion;
use App\Models\PromesaCuota;
use App\Support\WorkflowMailer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PromesaCreator
{
    /**
     * Crea la promesa y retorna [PromesaPago $promesa, string $mensaje]
     */
    public function createFromRequest(string $dni, StorePromesaRequest $r): array
    {
        $tipo = $r->input('tipo');
        $opsSel = collect($r->input('operaciones', []))
            ->map(fn($op)=>trim((string)$op))->filter()->unique()->values()->all();

        // Normalizaciones
        $cronFechas = array_map(fn($f)=>Carbon::parse($f)->toDateString(), (array)$r->input('cron_fecha', []));
        $cronMontos = array_map(fn($m)=>(float)$m, (array)$r->input('cron_monto', []));
        $cronBalon  = (int)$r->input('cron_balon', 0);

        DB::beginTransaction();
        try {
            $base = [
                'dni'                 => $dni,
                'nota'                => $r->input('nota'),
                'tipo'                => $tipo,
                'telefono'            => $r->input('telefono'),
                'workflow_estado'     => 'pendiente',
                'user_id'             => optional(Auth::user())->id,
            ];

            if (in_array($tipo,['convenio','convenio_balon'], true)) {
                $n = max(1, (int)$r->input('nro_cuotas'));
                $firstDate = Carbon::parse($cronFechas[0] ?? now());

                $data = array_merge($base, [
                    'fecha_promesa'  => now()->toDateString(),
                    'fecha_pago'     => $firstDate->toDateString(),
                    'cuota_dia'      => (int)$firstDate->day,
                    'nro_cuotas'     => $n,
                    'monto_convenio' => (float)$r->input('monto_convenio'),
                    'monto_cuota'    => round(array_sum($cronMontos) / $n, 2),
                ]);
            } else {
                $fecha = Carbon::parse($r->input('fecha_pago'));
                $data = array_merge($base, [
                    'fecha_promesa' => $fecha->toDateString(),
                    'fecha_pago'    => $fecha->toDateString(),
                    'monto'         => (float)$r->input('monto_cancel'),
                ]);
            }

            $promesa = PromesaPago::create($data);

            // Relación de operaciones + campo plano de legacy
            $promesa->operacion = implode(', ', $opsSel);
            $promesa->save();

            if ($opsSel) {
                $now = now();
                PromesaOperacion::insert(array_map(fn($op)=>[
                    'promesa_id'=>$promesa->id,'operacion'=>$op,
                    'created_at'=>$now,'updated_at'=>$now
                ], $opsSel));
            }

            if (in_array($tipo,['convenio','convenio_balon'], true)) {
                $now = now();
                $rows = [];
                foreach ($cronFechas as $i => $f) {
                    $rows[] = [
                        'promesa_id'=>$promesa->id,'nro'=>$i+1,
                        'fecha'=>Carbon::parse($f)->toDateString(),
                        'monto'=>(float)($cronMontos[$i] ?? 0),
                        'es_balon'=> ($tipo==='convenio_balon' && $cronBalon === ($i+1)) ? 1 : 0,
                        'created_at'=>$now,'updated_at'=>$now,
                    ];
                }
                PromesaCuota::insert($rows);
            }

            // Auto workflow simple por rol (igual a tu lógica)
            $meRole = strtolower((string)(optional(Auth::user())->role ?? ''));
            $now = now();
            if (in_array($meRole,['administrador','sistemas'], true)) {
                $promesa->workflow_estado = 'aprobada';
                $promesa->pre_aprobado_por = Auth::id(); $promesa->pre_aprobado_at = $now;
                $promesa->aprobado_por     = Auth::id(); $promesa->aprobado_at     = $now;
                $promesa->save();
            } elseif ($meRole === 'supervisor') {
                $promesa->workflow_estado = 'preaprobada';
                $promesa->pre_aprobado_por = Auth::id(); $promesa->pre_aprobado_at = $now;
                $promesa->save();
            }

            DB::commit();

            // Mails (best-effort)
            try {
                if (in_array($meRole,['administrador','sistemas'], true))      WorkflowMailer::promesaResuelta($promesa, true);
                elseif ($meRole === 'supervisor')                               WorkflowMailer::promesaPreaprobada($promesa);
                else                                                             WorkflowMailer::promesaPendiente($promesa);
            } catch (\Throwable $ignored) {}

            $msg = match (true) {
                in_array($meRole,['administrador','sistemas'], true) => 'Propuesta registrada y APROBADA.',
                $meRole === 'supervisor'                             => 'Propuesta registrada y PRE-APROBADA.',
                default                                              => 'Propuesta registrada y enviada para autorización.',
            };

            return [$promesa, $msg];

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
