<?php

namespace App\Services\Cliente;

use App\Models\PagoPropia as Pago;
use Illuminate\Support\Collection;

class ClientePaymentService
{
    public function paymentsForDni(string $dni): Collection
    {
        return Pago::query()
            ->where('dni', $dni)
            ->orderByDesc('fecha')
            ->get([
                'id',
                'fecha',
                'dni',
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

    public function deleteForDni(string $dni, Collection $ids): int
    {
        return Pago::query()
            ->whereIn('id', $ids)
            ->where('dni', $dni)
            ->delete();
    }
}
