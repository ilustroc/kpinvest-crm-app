<?php

namespace App\Services\Cna;

use App\Models\CnaSolicitud;
use App\Models\Pago;
use App\Models\User;
use App\Support\Authorization\Roles;
use App\Support\Traits\HasTeamVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CnaQueryService
{
    use HasTeamVisibility;

    public function authorizationData(User $user, string $search = '', ?string $status = null): array
    {
        $query = CnaSolicitud::query();
        $teamIds = $this->teamUserIds($user);

        if ($teamIds !== []) {
            $query->whereIn('user_id', $teamIds);
        }

        $cnaDocCol = Schema::hasColumn('cna_solicitudes', 'numdoc') ? 'numdoc' : 'dni';

        if ($search !== '') {
            $query->where(function ($where) use ($search, $cnaDocCol) {
                $where->where($cnaDocCol, 'like', "%{$search}%")
                    ->orWhere('nro_carta', 'like', "%{$search}%")
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('observacion', 'like', "%{$search}%");
            });
        }

        if (Roles::normalize($user->role) === Roles::SUPERVISOR) {
            $query->where('workflow_estado', 'pendiente');
        } else {
            $query->where('workflow_estado', 'preaprobada');
        }

        if (! empty($status)) {
            $query->where('workflow_estado', $status);
        }

        $cnaRows = $query->orderByDesc('created_at')
            ->paginate(10, ['*'], 'page_cna');

        $cnaRows->withQueryString();

        $operations = collect($cnaRows->items())
            ->flatMap(fn ($cna) => (array) ($cna->operaciones ?? []))
            ->filter()
            ->map(fn ($operation) => (string) $operation)
            ->unique()
            ->values()
            ->all();

        $dnis = collect($cnaRows->items())
            ->map(fn ($cna) => $cna->numdoc ?? $cna->dni)
            ->filter()
            ->map(fn ($dni) => trim((string) $dni))
            ->unique()
            ->values()
            ->all();

        $prodByOp = [];

        if ($operations !== []) {
            $prodByOp = DB::table('clientes_cuentas')
                ->select('numdoc', 'operacion', 'producto')
                ->whereIn('operacion', $operations)
                ->when($dnis !== [], fn ($q) => $q->whereIn('numdoc', $dnis))
                ->get()
                ->flatMap(fn ($row) => [
                    "{$row->numdoc}_{$row->operacion}" => (string) ($row->producto ?? '-'),
                    (string) $row->operacion => (string) ($row->producto ?? '-'),
                ])
                ->all();
        }

        $pagosByDni = [];

        if ($dnis !== []) {
            $pagosCol = Schema::hasColumn('pagos', 'numdoc') ? 'numdoc' : 'dni';
            $pagos = Pago::query()
                ->whereIn($pagosCol, $dnis)
                ->orderByDesc('lote_id')
                ->orderByDesc('fecha')
                ->get([
                    "{$pagosCol} as numdoc",
                    "{$pagosCol} as dni",
                    'operacion',
                    'fecha',
                    'monto_pagado',
                    'gestor',
                    'entidad',
                    'cosecha',
                    'cuenta_recaudo',
                    'nombre_cliente',
                ]);

            $pagosByDni = $pagos->groupBy(fn ($row) => trim((string) ($row->numdoc ?? $row->dni)))
                ->map(fn ($group) => $group->map(fn ($row) => [
                    'operacion' => (string) ($row->operacion ?? ''),
                    'fecha' => $row->fecha ? (string) $row->fecha : null,
                    'monto_pagado' => (float) ($row->monto_pagado ?? 0),
                    'gestor' => (string) ($row->gestor ?? ''),
                    'entidad' => (string) ($row->entidad ?? ''),
                    'cosecha' => (string) ($row->cosecha ?? ''),
                    'cuenta_recaudo' => (string) ($row->cuenta_recaudo ?? ''),
                    'nombre_cliente' => (string) ($row->nombre_cliente ?? ''),
                ])->values()->all())
                ->all();
        }

        return [
            'cnaRows' => $cnaRows,
            'prodByOp' => $prodByOp,
            'pagosByDni' => $pagosByDni,
        ];
    }
}
