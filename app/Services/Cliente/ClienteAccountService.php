<?php

namespace App\Services\Cliente;

use App\Models\ClienteCuenta;
use App\Services\Asignacion\ClienteAssignmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ClienteAccountService
{
    public function __construct(
        private readonly ClienteAssignmentService $assignments,
    ) {
    }

    public function accountsForDni(string $dni): Collection
    {
        return $this->accountsForNumdoc($dni);
    }

    public function accountsForNumdoc(string $numdoc): Collection
    {
        return ClienteCuenta::query()
            ->where('numdoc', $numdoc)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'numdoc',
                'nombre',
                'cuenta',
                'operacion',
                'entidad',
                'producto',
                'cosecha',
                'moneda',
                'deuda_capital',
                'interes',
                'deuda_total',
            ]);
    }

    public function advisorsByOperation(string $dni, ?Collection $cuentas = null): Collection
    {
        if (! Schema::hasTable('asignar_clientes')) {
            return collect();
        }

        return $this->assignments->advisorsByOperation($dni, $cuentas);
    }

    public function addPaymentAndAdvisorData(
        Collection $cuentas,
        Collection $pagosGrouped,
        Collection $accountByOperation,
        Collection $advisorByOperation,
        array $resolvedAssignments = []
    ): Collection {
        $byCuentaId = $resolvedAssignments['by_cuenta_id'] ?? [];
        $byOperation = $resolvedAssignments['by_operation'] ?? [];
        $advisorByCuentaId = $resolvedAssignments['advisor_name_by_cuenta_id'] ?? [];

        return $cuentas->map(function ($account) use (
            $pagosGrouped,
            $accountByOperation,
            $advisorByOperation,
            $byCuentaId,
            $byOperation,
            $advisorByCuentaId
        ) {
            $group = $pagosGrouped->get($account->operacion) ?? collect();
            $account->pagos_count = $group->count();
            $account->pagos_sum = (float) $group->sum('monto_pagado');

            $currentAccount = trim((string) ($account->cuenta ?? ''));
            $mappedAccount = trim((string) ($accountByOperation[$account->operacion] ?? ''));
            $account->cuenta = $currentAccount !== ''
                ? $currentAccount
                : ($mappedAccount !== '' ? $mappedAccount : $account->operacion);

            $cid = isset($account->id) ? (int) $account->id : 0;
            if ($cid > 0 && array_key_exists($cid, $advisorByCuentaId)) {
                $account->asesor = $advisorByCuentaId[$cid];
            } else {
                $account->asesor = $advisorByOperation->get($account->operacion) ?? null;
            }

            $account->is_operationally_cancelled = $this->isOperationallyCancelled($account->asesor);

            $account->asignacion_vigente = ($cid > 0 && isset($byCuentaId[$cid]))
                ? $byCuentaId[$cid]
                : ($byOperation[$account->operacion] ?? null);

            return $account;
        });
    }

    /**
     * Totales exclusivos de la Ficha 360°. Se calculan después de resolver
     * la asignación vigente por cuenta, por lo que no confunden operaciones
     * repetidas de entidades distintas ni solicitudes pendientes/rechazadas.
     *
     * @return array{capital: float, deuda: float}
     */
    public function financialTotals(Collection $accounts): array
    {
        $activeAccounts = $accounts->reject(
            fn ($account) => (bool) ($account->is_operationally_cancelled
                ?? $this->isOperationallyCancelled($account->asesor ?? null))
        );

        return [
            'capital' => (float) $activeAccounts->sum(
                fn ($account) => (float) ($account->deuda_capital ?? $account->saldo_capital ?? 0)
            ),
            'deuda' => (float) $activeAccounts->sum(
                fn ($account) => (float) ($account->deuda_total ?? 0)
            ),
        ];
    }

    /**
     * La nomenclatura histórica usa ambas etiquetas. La comparación es
     * deliberadamente exacta: no excluye etiquetas que sólo contengan el
     * texto como parte de otro nombre operativo.
     */
    public function isOperationallyCancelled(?string $advisor): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $advisor))));

        return in_array($normalized, ['CANCELADO', 'CANCELADOS'], true);
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
