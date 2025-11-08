<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use App\Models\CnaSolicitud;
use App\Models\PagoPropia as Pago;
use App\Models\User;
use App\Services\PromesaWorkflowService;
use App\Support\Traits\HasTeamVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AutorizacionController extends Controller
{
    use HasTeamVisibility;

    /**
     * Bandeja de autorización (Promesas + CNA).
     */
    public function index(Request $req)
    {
        $user    = Auth::user();
        $q       = trim((string)($req->q ?? ''));
        $status  = $req->status;
        $teamIds = $this->teamUserIds($user);

        // ===== PROMESAS
        $promesas = PromesaPago::query()
            ->with(['operaciones'])
            ->leftJoin('users as u', 'u.id', '=', 'promesas_pago.user_id');

        // Filtro por equipo
        if (!empty($teamIds)) {
            $promesas->whereIn('promesas_pago.user_id', $teamIds);
        }

        // Búsqueda
        if ($q !== '') {
            $promesas->where(function ($x) use ($q) {
                $x->where('promesas_pago.dni', 'like', "%{$q}%")
                  ->orWhere('promesas_pago.nota', 'like', "%{$q}%")
                  ->orWhere('promesas_pago.operacion', 'like', "%{$q}%")
                  ->orWhereHas('operaciones', fn($qq) => $qq->where('operacion', 'like', "%{$q}%"));
            });
        }

        // Estado según rol (por defecto)
        if (strtolower($user->role) === 'supervisor') {
            $promesas->where('promesas_pago.workflow_estado', 'pendiente');
        } else {
            $promesas->where('promesas_pago.workflow_estado', 'preaprobada');
        }

        // Filtro explícito de estado (si se envía)
        if (!empty($status)) {
            $promesas->where('promesas_pago.workflow_estado', $status);
        }

        $rows = $promesas->select('promesas_pago.*', 'u.name as creador_nombre')
            ->orderByDesc('promesas_pago.fecha_promesa')
            ->get();

        // ===== Prefetch por DNI (fallback de operaciones). clientes_cuentas usa numdoc
        $dnis = $rows->pluck('dni')->filter()->unique()->values()->all();
        $opsByDni = [];
        if ($dnis) {
            $opsByDni = DB::table('clientes_cuentas')
                ->select(['numdoc as dni', 'operacion'])
                ->whereIn('numdoc', $dnis)
                ->get()
                ->groupBy('dni')
                ->map(fn($g) => $g->pluck('operacion')->filter()->values()->all())
                ->all();
        }

        // ===== Cargar info de cuentas por operación (incluye fecha_castigo)
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

        // ===== Enriquecer filas de promesas
        $rows = $rows->map(function ($p) use ($opsByDni, $ccByOp) {

            $ops = $p->relationLoaded('operaciones') && $p->operaciones->count()
                ? $p->operaciones->pluck('operacion')->map(fn($x) => (string)$x)->values()
                : collect(array_filter(array_map('trim', explode(',', (string)($p->operacion ?? '')))));
            if ($ops->isEmpty()) {
                $ops = collect($opsByDni[$p->dni] ?? []);
            }

            $p->operacion = $ops->implode(', ');
            $p->ops_list  = $ops->values();

            $sumCap = 0.0; $sumDeu = 0.0;
            $titulares = collect();
            $cuentas = [];

            foreach ($ops as $op) {
                $cc = $ccByOp[$op] ?? null;
                if (!$cc) continue;

                $sumCap += (float)($cc->deuda_capital ?? 0);
                $sumDeu += (float)($cc->deuda_total   ?? 0);

                if (!empty($cc->titular)) $titulares->push($cc->titular);

                $cuentas[] = [
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

            $p->titular       = $titulares->filter()->unique()->implode(' / ');
            $p->deuda_total   = $sumDeu;
            $p->saldo_capital = $sumCap;
            $p->cuentas_json  = $cuentas;

            return $p;
        });

        // ===== Cronogramas (si existe tabla promesa_cuotas)
        $ids = $rows->pluck('id')->filter()->all();
        $cuotasById = collect();
        if (!empty($ids) && Schema::hasTable('promesa_cuotas')) {
            $cuotasById = DB::table('promesa_cuotas')
                ->select('promesa_id', 'nro', 'fecha', 'monto', 'es_balon')
                ->whereIn('promesa_id', $ids)
                ->orderBy('promesa_id')->orderBy('nro')
                ->get()
                ->groupBy('promesa_id');
        }
        $rows = $rows->map(function ($p) use ($cuotasById) {
            $list = $cuotasById[$p->id] ?? collect();
            $p->has_balon   = (int)$list->contains('es_balon', 1);
            $p->cuotas_json = $list->map(function ($c) {
                return [
                    'nro'      => (int)($c->nro ?? 0),
                    'fecha'    => (string)($c->fecha ?? '—'),
                    'monto'    => (float)($c->monto ?? 0),
                    'es_balon' => (bool)($c->es_balon ?? false),
                ];
            })->values();
            return $p;
        });

        // ===== CNA (bandeja paginada)
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

        if (strtolower($user->role) === 'supervisor') {
            $cnaBase->where('workflow_estado', 'pendiente');
        } else {
            $cnaBase->where('workflow_estado', 'preaprobada');
        }
        if (!empty($status)) {
            $cnaBase->where('workflow_estado', $status);
        }

        $cnaRows = $cnaBase->orderByDesc('created_at')
            ->paginate(10, ['*'], 'page_cna');

        /** @var \Illuminate\Pagination\LengthAwarePaginator $cnaRows */
        $cnaRows->withQueryString();

        // Producto por operación (para la bandeja CNA)
        $opsAllCna = collect($cnaRows->items())
            ->flatMap(fn($c) => (array)($c->operaciones ?? []))
            ->filter()->map(fn($op) => (string)$op)->unique()->values()->all();

        $prodByOp = [];
        if (!empty($opsAllCna)) {
            $prodByOp = DB::table('clientes_cuentas')
                ->select('operacion', 'producto')
                ->whereIn('operacion', $opsAllCna)
                ->get()
                ->mapWithKeys(fn($r) => [(string)$r->operacion => (string)($r->producto ?? '—')])
                ->all();
        }

        return view('autorizacion.index', [
            'rows'         => $rows,           // Promesas (colección enriquecida)
            'cnaRows'      => $cnaRows,        // CNA (paginado)
            'prodByOp'     => $prodByOp,
            'q'            => $q,
            'isSupervisor' => strtolower($user->role) === 'supervisor',
        ]);
    }

    /**
     * SUPERVISOR: Pre-aprobar promesa.
     */
    public function preaprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        try {
            PromesaWorkflowService::preaprobar($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa pre-aprobada.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * SUPERVISOR: Rechazar promesa (estado Pendiente).
     */
    public function rechazarSup(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        try {
            PromesaWorkflowService::rechazarSup($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa rechazada por supervisor.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * ADMIN: Aprobar promesa (estado Pre-aprobada).
     */
    public function aprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        try {
            PromesaWorkflowService::aprobar($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa APROBADA.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * ADMIN: Rechazar promesa (estado Pre-aprobada).
     */
    public function rechazarAdmin(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        try {
            PromesaWorkflowService::rechazarAdmin($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa rechazada por administrador.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * Autorización por rol (admin/sistemas o supervisor).
     */
    private function authorizeActionFor(string $role)
    {
        $user = Auth::user();
        if (!in_array(strtolower($user->role), [$role, 'sistemas'])) {
            abort(403, 'No autorizado.');
        }
    }

    /**
     * API: Pagos por DNI (esquema pagos_propia nuevo).
     */
    public function pagosDni(string $dni)
    {
        try {
            $dni = trim($dni);

            $rows = Pago::query()
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
                ->map(function ($r) {
                    return [
                        'operacion'      => (string) ($r->operacion ?? ''),
                        'fecha'          => $r->fecha ? (string) $r->fecha : null,
                        'monto_pagado'   => (float) ($r->monto_pagado ?? 0),
                        'gestor'         => (string) ($r->gestor ?? ''),
                        'entidad'        => (string) ($r->entidad ?? ''),
                        'cosecha'        => (string) ($r->cosecha ?? ''),
                        'cuenta_recaudo' => (string) ($r->cuenta_recaudo ?? ''),
                        'nombre_cliente' => (string) ($r->nombre_cliente ?? ''),
                    ];
                });

            return response()->json([
                'dni'   => $dni,
                'pagos' => $rows,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('pagosDni error', ['dni' => $dni, 'msg' => $e->getMessage()]);
            return response()->json([
                'dni'   => $dni,
                'pagos' => [],
                'error' => 'No se pudo obtener los pagos',
            ], 500);
        }
    }
}
