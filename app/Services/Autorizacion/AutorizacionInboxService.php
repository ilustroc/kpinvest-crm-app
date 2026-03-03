<?php

namespace App\Services\Autorizacion;

use App\Models\PromesaPago;
use App\Models\CnaSolicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutorizacionInboxService
{
    public function buildIndexData(array $teamIds, string $role, string $q = '', ?string $status = null): array
    {
        $isSupervisor = strtolower($role) === 'supervisor';

        // ===== PROMESAS (base)
        $promesas = PromesaPago::query()
            ->with(['operaciones'])
            ->leftJoin('users as u', 'u.id', '=', 'promesas_pago.user_id');

        if (!empty($teamIds)) {
            $promesas->whereIn('promesas_pago.user_id', $teamIds);
        }

        if ($q !== '') {
            $promesas->where(function ($x) use ($q) {
                $x->where('promesas_pago.dni', 'like', "%{$q}%")
                  ->orWhere('promesas_pago.nota', 'like', "%{$q}%")
                  ->orWhere('promesas_pago.operacion', 'like', "%{$q}%")
                  ->orWhereHas('operaciones', fn ($qq) => $qq->where('operacion', 'like', "%{$q}%"));
            });
        }

        // estado por defecto según rol
        $promesas->where('promesas_pago.workflow_estado', $isSupervisor ? 'pendiente' : 'preaprobada');

        // filtro explícito si viene
        if (!empty($status)) {
            $promesas->where('promesas_pago.workflow_estado', $status);
        }

        $rows = $promesas
            ->select('promesas_pago.*', 'u.name as creador_nombre')
            ->orderByDesc('promesas_pago.fecha_promesa')
            ->get();

        // ===== ENRIQUECIMIENTO PROMESAS
        $rows = $this->enrichPromesas($rows);

        // ===== CNA (paginada)
        $cnaRows = $this->buildCnaPaginator($teamIds, $isSupervisor, $q, $status);

        // Producto por operación (CNA)
        $prodByOp = $this->buildProductoByOperacionForCna($cnaRows);

        return [
            'rows'         => $rows,
            'cnaRows'      => $cnaRows,
            'prodByOp'     => $prodByOp,
            'q'            => $q,
            'isSupervisor' => $isSupervisor,
        ];
    }

    private function enrichPromesas($rows)
    {
        $dnis = $rows->pluck('dni')->filter()->unique()->values()->all();

        // ops por dni (fallback)
        $opsByDni = [];
        if (!empty($dnis)) {
            $opsByDni = DB::table('clientes_cuentas')
                ->select(['numdoc as dni', 'operacion'])
                ->whereIn('numdoc', $dnis)
                ->get()
                ->groupBy('dni')
                ->map(fn ($g) => $g->pluck('operacion')->filter()->values()->all())
                ->all();
        }

        // titular por dni
        $clienteByDni = [];
        if (!empty($dnis)) {
            $clienteByDni = DB::table('clientes_cuentas')
                ->select(['numdoc as dni', DB::raw('MAX(nombre) as titular')])
                ->whereIn('numdoc', $dnis)
                ->groupBy('numdoc')
                ->pluck('titular', 'dni')
                ->all();
        }

        // todas las cuentas del cliente por dni (para acordeón)
        $ccAllByDni = [];
        if (!empty($dnis)) {
            $ccAllByDni = DB::table('clientes_cuentas')
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
                ->map(function ($g) {
                    return $g->map(function ($cc) {
                        return [
                            'operacion'     => (string)$cc->operacion,
                            'entidad'       => (string)($cc->entidad ?? ''),
                            'cosecha'       => (string)($cc->cosecha ?? ''),
                            'producto'      => (string)($cc->producto ?? ''),
                            'saldo_capital' => (float)($cc->deuda_capital ?? 0),
                            'deuda_total'   => (float)($cc->deuda_total   ?? 0),
                            'fecha_castigo' => $cc->fecha_castigo ? (string)$cc->fecha_castigo : null,
                            'anio_castigo'  => $cc->fecha_castigo ? (int)substr((string)$cc->fecha_castigo, 0, 4) : null,
                        ];
                    })->values();
                })
                ->all();
        }

        // operaciones de todas las promesas (para precargar cuentas por operación)
        $opsAll = $rows->flatMap(function ($p) use ($opsByDni) {
                if ($p->relationLoaded('operaciones') && $p->operaciones->count()) {
                    return $p->operaciones->pluck('operacion');
                }
                if (!empty($p->operacion)) {
                    return collect(array_filter(array_map('trim', explode(',', (string)$p->operacion))));
                }
                return collect($opsByDni[$p->dni] ?? []);
            })
            ->filter()->unique()->values()->all();

        $ccByOp = [];
        if (!empty($opsAll)) {
            $ccByOp = DB::table('clientes_cuentas')
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
                ->whereIn('operacion', $opsAll)
                ->get()
                ->keyBy('operacion');
        }

        // enriquecer cada promesa
        $rows = $rows->map(function ($p) use ($opsByDni, $ccByOp, $clienteByDni, $ccAllByDni) {

            $ops = $p->relationLoaded('operaciones') && $p->operaciones->count()
                ? $p->operaciones->pluck('operacion')->map(fn ($x) => (string)$x)->values()
                : collect(array_filter(array_map('trim', explode(',', (string)($p->operacion ?? '')))));

            if ($ops->isEmpty()) {
                $ops = collect($opsByDni[$p->dni] ?? []);
            }

            $p->operacion = $ops->implode(', ');
            $p->ops_list  = $ops->values();

            $sumCap = 0.0; $sumDeu = 0.0;
            $cuentasIncluidas = [];

            foreach ($ops as $op) {
                $cc = $ccByOp[$op] ?? null;
                if (!$cc) continue;

                $sumCap += (float)($cc->deuda_capital ?? 0);
                $sumDeu += (float)($cc->deuda_total   ?? 0);

                $cuentasIncluidas[] = [
                    'operacion'     => (string)$cc->operacion,
                    'entidad'       => (string)($cc->entidad ?? ''),
                    'cosecha'       => (string)($cc->cosecha ?? ''),
                    'producto'      => (string)($cc->producto ?? ''),
                    'saldo_capital' => (float)($cc->deuda_capital ?? 0),
                    'deuda_total'   => (float)($cc->deuda_total   ?? 0),
                    'fecha_castigo' => $cc->fecha_castigo ? (string)$cc->fecha_castigo : null,
                    'anio_castigo'  => $cc->fecha_castigo ? (int)substr((string)$cc->fecha_castigo, 0, 4) : null,
                ];
            }

            $p->titular = (string)($clienteByDni[$p->dni] ?? '—');

            $p->deuda_total   = $sumDeu;
            $p->saldo_capital = $sumCap;

            $p->cuentas_json         = $cuentasIncluidas;
            $p->cuentas_cliente_json = $ccAllByDni[$p->dni] ?? [];

            return $p;
        });

        // cronogramas (si existe)
        $ids = $rows->pluck('id')->filter()->all();

        if (!empty($ids) && Schema::hasTable('promesa_cuotas')) {
            $cuotasById = DB::table('promesa_cuotas')
                ->select('promesa_id', 'nro', 'fecha', 'monto', 'es_balon')
                ->whereIn('promesa_id', $ids)
                ->orderBy('promesa_id')->orderBy('nro')
                ->get()
                ->groupBy('promesa_id');

            $rows = $rows->map(function ($p) use ($cuotasById) {
                $list = $cuotasById[$p->id] ?? collect();
                $p->has_balon = (int)$list->contains('es_balon', 1);

                // OJO: aquí antes usabas cuotas_json también para "cuentas incluidas".
                // Para no romper tu vista, guardo cronograma en otra propiedad:
                $p->cronograma_json = $list->map(function ($c) {
                    return [
                        'nro'      => (int)($c->nro ?? 0),
                        'fecha'    => (string)($c->fecha ?? '—'),
                        'monto'    => (float)($c->monto ?? 0),
                        'es_balon' => (bool)($c->es_balon ?? false),
                    ];
                })->values();

                return $p;
            });
        } else {
            // por compatibilidad si tu vista espera estas props
            $rows = $rows->map(function ($p) {
                $p->has_balon = 0;
                $p->cronograma_json = collect();
                return $p;
            });
        }

        return $rows;
    }

    private function buildCnaPaginator(array $teamIds, bool $isSupervisor, string $q, ?string $status)
    {
        $cnaBase = CnaSolicitud::query();

        if (!empty($teamIds)) {
            $cnaBase->whereIn('user_id', $teamIds);
        }

        if ($q !== '') {
            $cnaBase->where(function ($x) use ($q) {
                $x->where('dni', 'like', "%{$q}%")
                  ->orWhere('nro_carta', 'like', "%{$q}%")
                  ->orWhere('producto', 'like', "%{$q}%")
                  ->orWhere('observacion', 'like', "%{$q}%");
            });
        }

        $cnaBase->where('workflow_estado', $isSupervisor ? 'pendiente' : 'preaprobada');

        if (!empty($status)) {
            $cnaBase->where('workflow_estado', $status);
        }

        return $cnaBase->orderByDesc('created_at')
            ->paginate(10, ['*'], 'page_cna');
    }

    private function buildProductoByOperacionForCna($cnaRows): array
    {
        $opsAllCna = collect($cnaRows->items())
            ->flatMap(fn ($c) => (array)($c->operaciones ?? []))
            ->filter()->map(fn ($op) => (string)$op)->unique()->values()->all();

        if (empty($opsAllCna)) return [];

        return DB::table('clientes_cuentas')
            ->select('operacion', 'producto')
            ->whereIn('operacion', $opsAllCna)
            ->get()
            ->mapWithKeys(fn ($r) => [(string)$r->operacion => (string)($r->producto ?? '—')])
            ->all();
    }
}