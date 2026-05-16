<?php

namespace App\Services\Promesa;

use App\Models\PromesaPago;
use App\Models\User;
use App\Support\Traits\HasTeamVisibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromesaQueryService
{
    use HasTeamVisibility;

    public function authorizationRows(User $user, string $query = '', ?string $status = null): Collection
    {
        $teamIds = $this->teamUserIds($user);
        $query = trim($query);

        $promesas = PromesaPago::query()
            ->with(['operaciones'])
            ->leftJoin('users as u', 'u.id', '=', 'promesas_pago.user_id');

        if ($teamIds !== []) {
            $promesas->whereIn('promesas_pago.user_id', $teamIds);
        }

        if ($query !== '') {
            $promesas->where(function ($where) use ($query) {
                $where->where('promesas_pago.dni', 'like', "%{$query}%")
                    ->orWhere('promesas_pago.nota', 'like', "%{$query}%")
                    ->orWhere('promesas_pago.operacion', 'like', "%{$query}%")
                    ->orWhereHas('operaciones', fn ($operation) => $operation->where('operacion', 'like', "%{$query}%"));
            });
        }

        if (strtolower((string) $user->role) === 'supervisor') {
            $promesas->where('promesas_pago.workflow_estado', 'pendiente');
        } else {
            $promesas->where('promesas_pago.workflow_estado', 'preaprobada');
        }

        if (! empty($status)) {
            $promesas->where('promesas_pago.workflow_estado', $status);
        }

        $rows = $promesas->select('promesas_pago.*', 'u.name as creador_nombre')
            ->orderByDesc('promesas_pago.fecha_promesa')
            ->get();

        return $this->addScheduleData(
            $this->addAccountData($rows),
        );
    }

    private function addAccountData(Collection $rows): Collection
    {
        $dnis = $rows->pluck('dni')->filter()->unique()->values()->all();

        $opsByDni = $this->operationsByDni($dnis);
        $clientByDni = $this->clientByDni($dnis);
        $allAccountsByDni = $this->allAccountsByDni($dnis);

        $operations = $rows->flatMap(function ($promesa) use ($opsByDni) {
            if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
                return $promesa->operaciones->pluck('operacion');
            }

            if (! empty($promesa->operacion)) {
                return collect(array_filter(array_map('trim', explode(',', (string) $promesa->operacion))));
            }

            return collect($opsByDni[$promesa->dni] ?? []);
        })->filter()->unique()->values()->all();

        $accountByDniOperation = $this->accountsByDniOperation($dnis, $operations);

        return $rows->map(function ($promesa) use ($opsByDni, $clientByDni, $allAccountsByDni, $accountByDniOperation) {
            $dni = trim((string) $promesa->dni);

            $operations = $promesa->relationLoaded('operaciones') && $promesa->operaciones->count()
                ? $promesa->operaciones->pluck('operacion')->map(fn ($operation) => trim((string) $operation))->filter()->values()
                : collect(array_filter(array_map('trim', explode(',', (string) ($promesa->operacion ?? '')))));

            if ($operations->isEmpty()) {
                $operations = collect($opsByDni[$dni] ?? [])->map(fn ($operation) => trim((string) $operation))->filter()->values();
            }

            $promesa->operacion = $operations->implode(', ');
            $promesa->ops_list = $operations->values();

            $capitalSum = 0.0;
            $debtSum = 0.0;
            $includedAccounts = [];

            foreach ($operations as $operation) {
                $key = $dni.'|'.trim((string) $operation);
                $account = $accountByDniOperation[$key] ?? null;

                if (! $account) {
                    continue;
                }

                $capitalSum += (float) ($account->deuda_capital ?? 0);
                $debtSum += (float) ($account->deuda_total ?? 0);

                $includedAccounts[] = [
                    'operacion' => (string) $account->operacion,
                    'entidad' => (string) ($account->entidad ?? ''),
                    'cosecha' => (string) ($account->cosecha ?? ''),
                    'producto' => (string) ($account->producto ?? ''),
                    'saldo_capital' => (float) ($account->deuda_capital ?? 0),
                    'deuda_total' => (float) ($account->deuda_total ?? 0),
                    'fecha_castigo' => $account->fecha_castigo ? (string) $account->fecha_castigo : null,
                    'anio_castigo' => $account->fecha_castigo ? (int) substr((string) $account->fecha_castigo, 0, 4) : null,
                ];
            }

            $promesa->titular = (string) ($clientByDni[$dni] ?? '-');
            $promesa->deuda_total = $debtSum;
            $promesa->saldo_capital = $capitalSum;
            $promesa->cuentas_json = $includedAccounts;
            $promesa->cuentas_cliente_json = $allAccountsByDni[$dni] ?? [];

            return $promesa;
        });
    }

    private function addScheduleData(Collection $rows): Collection
    {
        $ids = $rows->pluck('id')->filter()->all();
        $quotasById = collect();

        if ($ids !== [] && Schema::hasTable('promesa_cuotas')) {
            $quotasById = DB::table('promesa_cuotas')
                ->select('promesa_id', 'nro', 'fecha', 'monto', 'es_balon')
                ->whereIn('promesa_id', $ids)
                ->orderBy('promesa_id')
                ->orderBy('nro')
                ->get()
                ->groupBy('promesa_id');
        }

        return $rows->map(function ($promesa) use ($quotasById) {
            $quotas = $quotasById[$promesa->id] ?? collect();
            $promesa->has_balon = (int) $quotas->contains('es_balon', 1);
            $promesa->cuotas_json = $quotas->map(fn ($quota) => [
                'nro' => (int) ($quota->nro ?? 0),
                'fecha' => (string) ($quota->fecha ?? '-'),
                'monto' => (float) ($quota->monto ?? 0),
                'es_balon' => (bool) ($quota->es_balon ?? false),
            ])->values();

            return $promesa;
        });
    }

    private function operationsByDni(array $dnis): array
    {
        if ($dnis === []) {
            return [];
        }

        return DB::table('clientes_cuentas')
            ->select(['numdoc as dni', 'operacion'])
            ->whereIn('numdoc', $dnis)
            ->get()
            ->groupBy('dni')
            ->map(fn ($group) => $group->pluck('operacion')->filter()->values()->all())
            ->all();
    }

    private function clientByDni(array $dnis): array
    {
        if ($dnis === []) {
            return [];
        }

        return DB::table('clientes_cuentas')
            ->select(['numdoc as dni', DB::raw('MAX(nombre) as titular')])
            ->whereIn('numdoc', $dnis)
            ->groupBy('numdoc')
            ->pluck('titular', 'dni')
            ->all();
    }

    private function allAccountsByDni(array $dnis): array
    {
        if ($dnis === []) {
            return [];
        }

        return DB::table('clientes_cuentas')
            ->select([
                'numdoc as dni',
                'operacion',
                'entidad',
                'cosecha',
                'producto',
                'deuda_capital',
                'deuda_total',
                'fecha_castigo',
            ])
            ->whereIn('numdoc', $dnis)
            ->orderBy('operacion')
            ->get()
            ->groupBy('dni')
            ->map(function ($group) {
                return $group->map(fn ($account) => [
                    'operacion' => (string) $account->operacion,
                    'entidad' => (string) ($account->entidad ?? ''),
                    'cosecha' => (string) ($account->cosecha ?? ''),
                    'producto' => (string) ($account->producto ?? ''),
                    'saldo_capital' => (float) ($account->deuda_capital ?? 0),
                    'deuda_total' => (float) ($account->deuda_total ?? 0),
                    'fecha_castigo' => $account->fecha_castigo ? (string) $account->fecha_castigo : null,
                    'anio_castigo' => $account->fecha_castigo ? (int) substr((string) $account->fecha_castigo, 0, 4) : null,
                ])->values();
            })
            ->all();
    }

    private function accountsByDniOperation(array $dnis, array $operations): array
    {
        if ($dnis === [] || $operations === []) {
            return [];
        }

        return DB::table('clientes_cuentas')
            ->select([
                'operacion',
                'numdoc as dni',
                'nombre as titular',
                'entidad',
                'cosecha',
                'producto',
                'deuda_capital',
                'deuda_total',
                'fecha_castigo',
            ])
            ->whereIn('operacion', $operations)
            ->whereIn('numdoc', $dnis)
            ->get()
            ->mapWithKeys(function ($row) {
                $dni = trim((string) $row->dni);
                $operation = trim((string) $row->operacion);

                return ["{$dni}|{$operation}" => $row];
            })
            ->all();
    }
}
