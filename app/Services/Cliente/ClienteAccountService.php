<?php

namespace App\Services\Cliente;

use App\Models\AsignarCliente;
use App\Models\ClienteCuenta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ClienteAccountService
{
    public function accountsForDni(string $dni): Collection
    {
        return ClienteCuenta::query()
            ->where('numdoc', $dni)
            ->orderByDesc('updated_at')
            ->get([
                'numdoc',
                'nombre',
                'cuenta',
                'operacion',
                'entidad',
                'producto',
                'cosecha',
                'deuda_capital',
                'interes',
                'deuda_total',
            ]);
    }

    public function advisorsByOperation(string $dni): Collection
    {
        if (! Schema::hasTable('asignar_clientes')) {
            return collect();
        }

        return AsignarCliente::query()
            ->where('numdoc', $dni)
            ->orderByDesc('id')
            ->get(['numdoc', 'operacion', 'name'])
            ->groupBy('operacion')
            ->map(fn (Collection $group) => optional($group->first())->name);
    }

    public function addPaymentAndAdvisorData(
        Collection $cuentas,
        Collection $pagosGrouped,
        Collection $accountByOperation,
        Collection $advisorByOperation
    ): Collection {
        return $cuentas->map(function ($account) use ($pagosGrouped, $accountByOperation, $advisorByOperation) {
            $group = $pagosGrouped->get($account->operacion) ?? collect();
            $account->pagos_count = $group->count();
            $account->pagos_sum = (float) $group->sum('monto_pagado');

            $currentAccount = trim((string) ($account->cuenta ?? ''));
            $mappedAccount = trim((string) ($accountByOperation[$account->operacion] ?? ''));
            $account->cuenta = $currentAccount !== ''
                ? $currentAccount
                : ($mappedAccount !== '' ? $mappedAccount : $account->operacion);

            $account->asesor = $advisorByOperation->get($account->operacion) ?? null;

            return $account;
        });
    }

    public function mapCosechaClientesToCcd(?string $cosecha): ?string
    {
        if (! $cosecha) {
            return null;
        }

        $cosecha = strtoupper(trim($cosecha));

        $direct = [
            'BBVA1' => 'BBVA_1_2',
            'BBVA2' => 'BBVA_1_2',
            'BBVA3' => 'BBVA_3_4',
            'BBVA4' => 'BBVA_3_4',
            'BBVA5' => 'BBVA_5',
            'BBVA6' => 'BBVA_6',
            'BBVA7' => 'BBVA_7_8',
            'BBVA8' => 'BBVA_7_8',
        ];

        if (isset($direct[$cosecha])) {
            return $direct[$cosecha];
        }

        if (preg_match('/^CONFIANZA_(\d{1,2})$/', $cosecha, $matches)) {
            return 'CONFIANZA'.$matches[1];
        }

        if (preg_match('/^COMPARTAMOS_(\d{1,2})$/', $cosecha, $matches)) {
            return 'COMPARTAMOS'.$matches[1];
        }

        if (preg_match('/^CAJAAQP(\d{1,2})$/', $cosecha, $matches)) {
            return 'AQP'.$matches[1];
        }

        return $cosecha;
    }
}
