<?php

namespace App\Services\Cliente;

use App\Models\Pago;
use Illuminate\Support\Collection;

class ClientePaymentService
{
    public function paymentsForDni(string $dni): Collection
    {
        return $this->paymentsForNumdoc($dni);
    }

    public function paymentsForNumdoc(string $numdoc): Collection
    {
        $col = \Illuminate\Support\Facades\Schema::hasColumn('pagos', 'numdoc') ? 'numdoc' : 'dni';

        return Pago::query()
            ->where($col, $numdoc)
            ->orderByDesc('fecha')
            ->get([
                'id',
                'fecha',
                "{$col} as numdoc",
                "{$col} as dni",
                'operacion',
                'entidad',
                'nombre_cliente',
                'monto_pagado',
                'gestor',
                'cosecha',
                'cuenta_recaudo',
            ]);
    }

    public function total(Collection $pagos): float
    {
        return (float) $pagos->sum('monto_pagado');
    }

    public function groupedByOperation(Collection $pagos): Collection
    {
        return $pagos->groupBy('operacion');
    }

    public function accountByOperation(Collection $pagos): Collection
    {
        return $pagos->groupBy('operacion')->map(function (Collection $group) {
            $frequency = $group->pluck('cuenta_recaudo')->filter()->countBy();

            return $frequency->isEmpty() ? null : $frequency->sortDesc()->keys()->first();
        });
    }

    public function normalizePaymentIds(mixed $ids): Collection
    {
        return collect($ids)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
    }

    public function deleteForDni(\App\Models\User $user, string $dni, Collection $ids, ?string $motivo = null): int
    {
        return $this->deleteForNumdoc($user, $dni, $ids, $motivo);
    }

    public function deleteForNumdoc(\App\Models\User $user, string $numdoc, Collection $ids, ?string $motivo = null): int
    {
        $col = \Illuminate\Support\Facades\Schema::hasColumn('pagos', 'numdoc') ? 'numdoc' : 'dni';

        $toDelete = Pago::query()
            ->whereIn('id', $ids)
            ->where($col, $numdoc)
            ->get();

        if ($toDelete->isEmpty()) {
            return 0;
        }

        $operaciones = $toDelete->pluck('operacion')->filter()->unique()->values()->all();
        $targetIds = $toDelete->pluck('id')->all();
        $ip = request()->ip() ?: '127.0.0.1';

        try {
            $deletedCount = \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $numdoc, $col) {
                return Pago::query()
                    ->whereIn('id', $ids)
                    ->where($col, $numdoc)
                    ->delete();
            });

            if ($deletedCount > 0) {
                \App\Services\Panel\PanelDashboardService::invalidate();

                \Illuminate\Support\Facades\Log::info('PAGOS_DELETED', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'ip' => $ip,
                    'numdoc' => $numdoc,
                    'dni' => $numdoc,
                    'operaciones' => $operaciones,
                    'payment_ids' => $targetIds,
                    'count' => $deletedCount,
                    'motivo' => $motivo ?: 'Eliminación manual desde ficha de cliente',
                    'deleted_at' => now()->toIso8601String(),
                ]);
            }

            return $deletedCount;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PAGOS_DELETE_FAILED', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'ip' => $ip,
                'numdoc' => $numdoc,
                'dni' => $numdoc,
                'operaciones' => $operaciones,
                'payment_ids' => $targetIds,
                'error_message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);

            throw $e;
        }
    }
}
