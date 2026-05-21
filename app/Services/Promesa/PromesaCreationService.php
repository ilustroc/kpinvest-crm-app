<?php

namespace App\Services\Promesa;

use App\Http\Requests\StorePromesaRequest;
use App\Models\PromesaCuota;
use App\Models\PromesaOperacion;
use App\Models\PromesaPago;
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
            $base = [
                'dni' => $dni,
                'nota' => $request->input('nota'),
                'tipo' => $type,
                'telefono' => $request->input('telefono'),
                'workflow_estado' => 'pendiente',
                'user_id' => optional(Auth::user())->id,
            ];

            $data = in_array($type, ['convenio', 'convenio_balon'], true)
                ? $this->conventionData($request, $dates, $amounts, $base)
                : $this->cancellationData($request, $base);

            $promesa = PromesaPago::create($data);
            $promesa->operacion = implode(', ', $operations);
            $promesa->save();

            $this->createOperations($promesa, $operations);
            $this->createQuotas($promesa, $request, $dates, $amounts);

            $role = strtolower((string) (optional(Auth::user())->role ?? ''));
            $this->applyInitialWorkflowByRole($promesa, $role);

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

    private function applyInitialWorkflowByRole(PromesaPago $promesa, string $role): void
    {
        $now = now();

        if ($role === 'administrador') {
            $promesa->workflow_estado = 'aprobada';
            $promesa->pre_aprobado_por = Auth::id();
            $promesa->pre_aprobado_at = $now;
            $promesa->aprobado_por = Auth::id();
            $promesa->aprobado_at = $now;
            $promesa->save();
            return;
        }

        if ($role === 'supervisor') {
            $promesa->workflow_estado = 'preaprobada';
            $promesa->pre_aprobado_por = Auth::id();
            $promesa->pre_aprobado_at = $now;
            $promesa->save();
        }
    }

    private function notifyCreation(PromesaPago $promesa, string $role): void
    {
        try {
            if ($role === 'administrador') {
                WorkflowNotifier::promesaResuelta($promesa, true);
            } elseif ($role === 'supervisor') {
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
            'administrador' => 'Propuesta registrada y APROBADA.',
            'supervisor' => 'Propuesta registrada y PRE-APROBADA.',
            default => 'Propuesta registrada y enviada para autorizacion.',
        };
    }
}
