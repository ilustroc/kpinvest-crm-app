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
        $dnis = $rows->pluck('dni')
            ->map(fn ($dni) => $this->normalizeValue($dni))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $opsByDni = $this->operationsByDni($dnis);
        $clientByDni = $this->clientByDni($dnis);
        $allAccountsByDni = $this->allAccountsByDni($dnis);

        $operations = $rows->flatMap(fn ($promesa) => $this->operationsForPromesa($promesa, $opsByDni))
            ->unique()
            ->values()
            ->all();

        $accountByDniOperation = $this->accountsByDniOperation($dnis, $operations);

        return $rows->map(function ($promesa) use ($opsByDni, $clientByDni, $allAccountsByDni, $accountByDniOperation) {
            $dni = $this->normalizeValue($promesa->dni);
            $operations = $this->operationsForPromesa($promesa, $opsByDni);

            $promesa->operacion = $operations->implode(', ');
            $promesa->ops_list = $operations->values();

            $includedAccounts = [];

            foreach ($operations as $operation) {
                $key = $this->accountKey($dni, $operation);
                $account = $accountByDniOperation[$key] ?? null;

                if (! $account) {
                    continue;
                }

                $includedAccounts[] = $this->accountPayload($account);
            }

            if ($includedAccounts === []) {
                $includedAccounts = $this->accountsFromClientList(
                    $allAccountsByDni[$dni] ?? [],
                    $operations,
                );
            }

            if ($includedAccounts === [] && $operations->isNotEmpty()) {
                $includedAccounts = $this->placeholderAccounts($operations);
            }

            $capitalSum = collect($includedAccounts)->sum(fn ($account) => (float) ($account['saldo_capital'] ?? 0));
            $debtSum = collect($includedAccounts)->sum(fn ($account) => (float) ($account['deuda_total'] ?? 0));

            $promesa->titular = (string) ($clientByDni[$dni] ?? '-');
            $promesa->deuda_total = $debtSum;
            $promesa->saldo_capital = $capitalSum;
            $promesa->cuentas_json = $includedAccounts;
            $promesa->cuentas_cliente_json = array_values($allAccountsByDni[$dni] ?? []);

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
            ->whereIn(DB::raw('TRIM(numdoc)'), $dnis)
            ->get()
            ->groupBy(fn ($row) => $this->normalizeValue($row->dni))
            ->map(fn ($group) => $this->normalizeList($group->pluck('operacion'))->all())
            ->all();
    }

    private function clientByDni(array $dnis): array
    {
        if ($dnis === []) {
            return [];
        }

        return DB::table('clientes_cuentas')
            ->select(['numdoc as dni', DB::raw('MAX(nombre) as titular')])
            ->whereIn(DB::raw('TRIM(numdoc)'), $dnis)
            ->groupBy('numdoc')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->normalizeValue($row->dni) => $row->titular])
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
            ->whereIn(DB::raw('TRIM(numdoc)'), $dnis)
            ->orderBy('operacion')
            ->get()
            ->groupBy(fn ($row) => $this->normalizeValue($row->dni))
            ->map(function ($group) {
                return $group->map(fn ($account) => $this->accountPayload($account))->values()->all();
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
            ->whereIn(DB::raw('TRIM(operacion)'), $operations)
            ->whereIn(DB::raw('TRIM(numdoc)'), $dnis)
            ->get()
            ->mapWithKeys(function ($row) {
                return [$this->accountKey($row->dni, $row->operacion) => $row];
            })
            ->all();
    }

    private function operationsForPromesa(PromesaPago $promesa, array $opsByDni): Collection
    {
        $dni = $this->normalizeValue($promesa->dni);

        if ($promesa->relationLoaded('operaciones') && $promesa->operaciones->count()) {
            return $this->normalizeList($promesa->operaciones->pluck('operacion'));
        }

        if (! empty($promesa->operacion)) {
            return $this->normalizeList(explode(',', (string) $promesa->operacion));
        }

        return $this->normalizeList($opsByDni[$dni] ?? []);
    }

    private function accountsFromClientList(array $accounts, Collection $operations): array
    {
        $operationLookup = array_flip($operations->map(fn ($operation) => $this->normalizeValue($operation))->all());

        return collect($accounts)
            ->filter(fn ($account) => isset($operationLookup[$this->normalizeValue($account['operacion'] ?? '')]))
            ->map(fn ($account) => $this->accountPayload($account))
            ->values()
            ->all();
    }

    private function placeholderAccounts(Collection $operations): array
    {
        return $operations->map(fn ($operation) => [
            'operacion' => $this->normalizeValue($operation),
            'entidad' => '',
            'cosecha' => '',
            'producto' => '',
            'saldo_capital' => 0.0,
            'deuda_total' => 0.0,
            'fecha_castigo' => null,
            'anio_castigo' => null,
        ])->values()->all();
    }

    private function accountPayload(object|array $account): array
    {
        $value = fn (string $key, mixed $default = null) => is_array($account)
            ? ($account[$key] ?? $default)
            : ($account->{$key} ?? $default);

        $date = $value('fecha_castigo');

        return [
            'operacion' => $this->normalizeValue($value('operacion')),
            'entidad' => (string) ($value('entidad', '') ?? ''),
            'cosecha' => (string) ($value('cosecha', '') ?? ''),
            'producto' => (string) ($value('producto', '') ?? ''),
            'saldo_capital' => (float) ($value('saldo_capital', $value('deuda_capital', 0)) ?? 0),
            'deuda_total' => (float) ($value('deuda_total', 0) ?? 0),
            'fecha_castigo' => $date ? (string) $date : null,
            'anio_castigo' => $date ? (int) substr((string) $date, 0, 4) : null,
        ];
    }

    private function normalizeList(iterable $values): Collection
    {
        return collect($values)
            ->map(fn ($value) => $this->normalizeValue($value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();
    }

    private function normalizeValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function accountKey(mixed $dni, mixed $operation): string
    {
        return $this->normalizeValue($dni).'|'.$this->normalizeValue($operation);
    }
}
