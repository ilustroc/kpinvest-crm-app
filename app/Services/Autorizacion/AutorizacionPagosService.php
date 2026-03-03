<?php

namespace App\Services\Autorizacion;

use App\Models\PagoPropia;
use Illuminate\Support\Collection;

class AutorizacionPagosService
{
    public function pagosPorDni(string $dni, int $limit = 500): Collection
    {
        $dni = trim($dni);

        // límite de seguridad (para no devolver miles y miles)
        $limit = max(1, min($limit, 2000));

        return PagoPropia::query()
            ->where('dni', $dni)
            ->orderByDesc('lote_id')
            ->orderByDesc('fecha')
            ->limit($limit)
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
            ->map(function ($r) {
                return [
                    'operacion'      => (string)($r->operacion ?? ''),
                    'fecha'          => $r->fecha ? (string)$r->fecha : null,
                    'monto_pagado'   => (float)($r->monto_pagado ?? 0),
                    'gestor'         => (string)($r->gestor ?? ''),
                    'entidad'        => (string)($r->entidad ?? ''),
                    'cosecha'        => (string)($r->cosecha ?? ''),
                    'cuenta_recaudo' => (string)($r->cuenta_recaudo ?? ''),
                    'nombre_cliente' => (string)($r->nombre_cliente ?? ''),
                ];
            });
    }
}