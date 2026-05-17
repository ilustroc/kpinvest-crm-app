<?php

namespace App\Services\Autorizacion;

use App\Models\PagoPropia as Pago;
use Illuminate\Support\Collection;

class AutorizacionPaymentLookupService
{
    public function pagosByDni(string $dni): Collection
    {
        $dni = trim($dni);

        return Pago::query()
            ->where('dni', $dni)
            ->orderByDesc('lote_id')
            ->orderByDesc('fecha')
            ->get([
                'operacion',
                'fecha',
                'monto_pagado',
                'gestor',
                'entidad',
                'cosecha',
                'cuenta_recaudo',
                'nombre_cliente',
            ])
            ->map(fn ($row) => [
                'operacion' => (string) ($row->operacion ?? ''),
                'fecha' => $row->fecha ? (string) $row->fecha : null,
                'monto_pagado' => (float) ($row->monto_pagado ?? 0),
                'gestor' => (string) ($row->gestor ?? ''),
                'entidad' => (string) ($row->entidad ?? ''),
                'cosecha' => (string) ($row->cosecha ?? ''),
                'cuenta_recaudo' => (string) ($row->cuenta_recaudo ?? ''),
                'nombre_cliente' => (string) ($row->nombre_cliente ?? ''),
            ]);
    }
}
