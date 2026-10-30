<?php

namespace App\Services\Promesa;

use App\Http\Requests\StorePromesaRequest;
use App\Models\PromesaCuota;
use App\Models\PromesaOperacion;
use App\Models\PromesaPago;
use App\Support\Authorization\Roles;
use App\Support\WorkflowNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PromesaCreationService
{
    public function __construct(private readonly PromesaScheduleService $schedule)
    {
    }

    /**
     * Crea la promesa y retorna [PromesaPago $promesa, string $mensaje].
     */
    public function createFromRequest(string $dni, StorePromesaRequest $request): array
    {
        $type = (string) $request->input('tipo');
        $operations = $this->schedule->selectedOperations($request);
        $dates = $this->schedule->normalizedDates($request);
        $amounts = $this->schedule->normalizedAmounts($request);

        $this->schedule->assertConventionSchedule($request, $amounts, $dates);

        DB::beginTransaction();

        try {
            $user = $request->user() ?: Auth::user();
            $role = Roles::normalize($user?->role);
            $userId = $user?->id;
            $now = now();

            $workflowEstado = 'pendiente';
            $preAprobadoPor = null;
            $preAprobadoAt = null;
            $aprobadoPor = null;
            $aprobadoAt = null;

            if ($role === Roles::ADMINISTRADOR) {
                $workflowEstado = 'aprobada';
                $preAprobadoPor = $userId;
                $preAprobadoAt = $now;
                $aprobadoPor = $userId;
                $aprobadoAt = $now;
            } elseif ($role === Roles::SUPERVISOR) {
                $workflowEstado = 'preaprobada';
                $preAprobadoPor = $userId;
                $preAprobadoAt = $now;
            }

            $base = [
                'dni' => $dni,
                'nota' => $request->input('nota'),
                'tipo' => $type,
                'telefono' => $request->input('telefono'),
                'workflow_estado' => $workflowEstado,
                'user_id' => $userId,
                'pre_aprobado_por' => $preAprobadoPor,
                'pre_aprobado_at' => $preAprobadoAt,
                'aprobado_por' => $aprobadoPor,
                'aprobado_at' => $aprobadoAt,
            ];

            $data = in_array($type, ['convenio', 'convenio_balon'], true)
                ? $this->conventionData($request, $dates, $amounts, $base)
                : $this->cancellationData($request, $base);

            $promesa = PromesaPago::create($data);
            $promesa->operacion = implode(', ', $operations);
            $promesa->save();

            $this->createOperations($promesa, $operations);
            $this->createQuotas($promesa, $request, $dates, $amounts);

            DB::commit();

            $this->notifyCreation($promesa, $role);

            return [$promesa, $this->messageForRole($role)];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function conventionData(StorePromesaRequest $request, array $dates, array $amounts, array $base): array
    {
        $quotaCount = max(1, (int) $request->input('nro_cuotas'));
        $firstDate = Carbon::parse($dates[0] ?? now());

        return array_merge($base, [
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => $firstDate->toDateString(),
            'cuota_dia' => (int) $firstDate->day,
            'nro_cuotas' => $quotaCount,
            'monto_convenio' => (float) $request->input('monto_convenio'),
            'monto_cuota' => round(array_sum($amounts) / $quotaCount, 2),
        ]);
    }

    private function cancellationData(StorePromesaRequest $request, array $base): array
    {
        $date = Carbon::parse($request->input('fecha_pago'));

        return array_merge($base, [
            'fecha_promesa' => $date->toDateString(),
            'fecha_pago' => $date->toDateString(),
            'monto' => (float) $request->input('monto_cancel'),
        ]);
    }

    private function createOperations(PromesaPago $promesa, array $operations): void
    {
        if ($operations === []) {
            return;
        }

        $now = now();

        PromesaOperacion::query()->insert(array_map(fn ($operation) => [
            'promesa_id' => $promesa->id,
            'operacion' => $operation,
            'created_at' => $now,
            'updated_at' => $now,
        ], $operations));
    }

    private function createQuotas(PromesaPago $promesa, StorePromesaRequest $request, array $dates, array $amounts): void
    {
        if (! in_array((string) $request->input('tipo'), ['convenio', 'convenio_balon'], true)) {
            return;
        }

        $rows = collect($this->schedule->quotaRows($request, $dates, $amounts))
            ->map(fn (array $row) => ['promesa_id' => $promesa->id] + $row)
            ->all();

        if ($rows !== []) {
            PromesaCuota::query()->insert($rows);
        }
    }

    private function notifyCreation(PromesaPago $promesa, string $role): void
    {
        try {
            if ($role === Roles::ADMINISTRADOR) {
                WorkflowNotifier::promesaResuelta($promesa, true);
            } elseif ($role === Roles::SUPERVISOR) {
                WorkflowNotifier::promesaPreaprobada($promesa);
            } else {
                WorkflowNotifier::promesaPendiente($promesa);
            }
        } catch (\Throwable) {
        }
    }

    private function messageForRole(string $role): string
    {
        return match ($role) {
            Roles::ADMINISTRADOR => 'Propuesta registrada y APROBADA.',
            Roles::SUPERVISOR => 'Propuesta registrada y PRE-APROBADA.',
            default => 'Propuesta registrada y enviada para autorizacion.',
        };
    }
}
