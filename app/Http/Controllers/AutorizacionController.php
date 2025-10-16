<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\PromesaPago;
use App\Models\CnaSolicitud;
use App\Models\PagoPropia as Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\WorkflowMailer;

class AutorizacionController extends Controller
{
    public function index(Request $req)
    {
        $user   = Auth::user();
        $q      = trim((string)($req->q ?? ''));
        $status = $req->status;

        // ===== PROMESAS
        $promesas = PromesaPago::query()
            ->with(['operaciones'])
            ->leftJoin('users as u','u.id','=','promesas_pago.user_id')
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('promesas_pago.dni','like',"%{$q}%")
                      ->orWhere('promesas_pago.nota','like',"%{$q}%")
                      ->orWhere('promesas_pago.operacion','like',"%{$q}%")
                      ->orWhereHas('operaciones', fn($qq)=>$qq->where('operacion','like',"%{$q}%"));
                });
            });

        if (strtolower($user->role) === 'supervisor') {
            $promesas->where('promesas_pago.workflow_estado','pendiente');
        } else {
            $promesas->where('promesas_pago.workflow_estado','preaprobada');
        }
        if (!empty($status)) {
            $promesas->where('promesas_pago.workflow_estado', $status);
        }

        $rows = $promesas->select('promesas_pago.*','u.name as creador_nombre')
                         ->orderByDesc('promesas_pago.fecha_promesa')
                         ->get();

        // ===== Prefetch por DNI (fallback). clientes_cuentas usa numdoc
        $dnis = $rows->pluck('dni')->filter()->unique()->values()->all();
        $opsByDni = [];
        if ($dnis) {
            $opsByDni = DB::table('clientes_cuentas')
                ->select(['numdoc as dni','operacion'])
                ->whereIn('numdoc', $dnis)
                ->get()
                ->groupBy('dni')
                ->map(fn($g)=>$g->pluck('operacion')->filter()->values()->all())
                ->all();
        }

        // ===== Cuentas por operación (ajustado a columnas vigentes)
        $opsAll = $rows->flatMap(function($p) use ($opsByDni){
                if ($p->relationLoaded('operaciones') && $p->operaciones->count()) {
                    return $p->operaciones->pluck('operacion');
                }
                if (!empty($p->operacion)) {
                    return collect(array_filter(array_map('trim', explode(',', (string)$p->operacion))));
                }
                return collect($opsByDni[$p->dni] ?? []);
            })
            ->filter()->unique()->values()->all();

        // ===== Cuentas por operación (incluye fecha_castigo) =====
        $ccByOp = [];
        if (!empty($opsAll)) {
            $ccByOp = DB::table('clientes_cuentas')
                ->select([
                    'operacion',
                    'numdoc as dni',
                    'nombre as titular',
                    'entidad',
                    'producto',
                    'deuda_capital',
                    'deuda_total',
                    'fecha_castigo',
                ])
                ->whereIn('operacion', $opsAll)
                ->get()
                ->keyBy('operacion');
        }

        // ===== Enriquecer filas (removidas columnas obsoletas) =====
        $rows = $rows->map(function($p) use ($opsByDni,$ccByOp) {

            $ops = $p->relationLoaded('operaciones') && $p->operaciones->count()
                ? $p->operaciones->pluck('operacion')->map(fn($x)=>(string)$x)->values()
                : collect(array_filter(array_map('trim', explode(',', (string)($p->operacion ?? '')))));
            if ($ops->isEmpty()) $ops = collect($opsByDni[$p->dni] ?? []);

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
                    'producto'      => (string)($cc->producto ?? ''),
                    'saldo_capital' => (float)($cc->deuda_capital ?? 0), // alias para el front
                    'deuda_total'   => (float)($cc->deuda_total   ?? 0),
                    'fecha_castigo' => $cc->fecha_castigo ? (string)$cc->fecha_castigo : null, // <-- NUEVO
                    // (opcional por compatibilidad con vistas viejas)
                    'anio_castigo'  => $cc->fecha_castigo ? (int)substr((string)$cc->fecha_castigo, 0, 4) : null,
                ];
            }

            $p->titular       = $titulares->filter()->unique()->implode(' / ');
            $p->deuda_total   = $sumDeu;
            $p->saldo_capital = $sumCap;
            $p->cuentas_json  = $cuentas;

            return $p;
        });

        // ===== Cronogramas (si existe tabla)
        $ids = $rows->pluck('id')->filter()->all();
        $cuotasById = collect();
        if (!empty($ids) && Schema::hasTable('promesa_cuotas')) {
            $cuotasById = DB::table('promesa_cuotas')
                ->select('promesa_id','nro','fecha','monto','es_balon')
                ->whereIn('promesa_id', $ids)
                ->orderBy('promesa_id')->orderBy('nro')
                ->get()
                ->groupBy('promesa_id');
        }
        $rows = $rows->map(function($p) use ($cuotasById){
            $list = $cuotasById[$p->id] ?? collect();
            $p->has_balon   = (int)$list->contains('es_balon', 1);
            $p->cuotas_json = $list->map(function($c){
                return [
                    'nro'      => (int)($c->nro ?? 0),
                    'fecha'    => (string)($c->fecha ?? '—'),
                    'monto'    => (float)($c->monto ?? 0),
                    'es_balon' => (bool)($c->es_balon ?? false),
                ];
            })->values();
            return $p;
        });

        // ===== CNA (bandeja)
        $cnaBase = CnaSolicitud::query()
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function($x) use ($q){
                    $x->where('dni','like',"%{$q}%")
                      ->orWhere('nro_carta','like',"%{$q}%")
                      ->orWhere('producto','like',"%{$q}%")
                      ->orWhere('observacion','like',"%{$q}%");
                });
            });

        if (strtolower($user->role) === 'supervisor') {
            $cnaBase->where('workflow_estado','pendiente');
        } else {
            $cnaBase->where('workflow_estado','preaprobada');
        }
        if (!empty($status)) {
            $cnaBase->where('workflow_estado', $status);
        }

        $cnaRows = $cnaBase->orderByDesc('created_at')
            ->paginate(10, ['*'], 'page_cna')
            ->withQueryString();

        // Producto por operación (opcional)
        $opsAllCna = collect($cnaRows->items())
            ->flatMap(fn($c) => (array)($c->operaciones ?? []))
            ->filter()->map(fn($op)=>(string)$op)->unique()->values()->all();

        $prodByOp = [];
        if (!empty($opsAllCna)) {
            $prodByOp = DB::table('clientes_cuentas')
                ->select('operacion','producto')
                ->whereIn('operacion', $opsAllCna)
                ->get()
                ->mapWithKeys(fn($r) => [(string)$r->operacion => (string)($r->producto ?? '—')])
                ->all();
        }

        return view('autorizacion.index', [
            'rows'         => $rows,
            'cnaRows'      => $cnaRows,
            'prodByOp'     => $prodByOp,
            'q'            => $q,
            'isSupervisor' => strtolower($user->role) === 'supervisor',
        ]);
    }

    // ===== SUPERVISOR =====
    public function preaprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        if (($promesa->workflow_estado ?? 'pendiente') !== 'pendiente') {
            return back()->withErrors('Solo se puede pre-aprobar una promesa Pendiente.');
        }

        $promesa->update([
            'workflow_estado'     => 'preaprobada',
            'pre_aprobado_por'    => Auth::id(),
            'pre_aprobado_at'     => now(),
            'nota_preaprobacion'  => trim((string)$req->input('nota_estado')) ?: null,
            'rechazado_por'       => null,
            'rechazado_at'        => null,
            'nota_rechazo'        => null,
        ]);

        $this->sendMailSafely(fn()=>WorkflowMailer::promesaPreaprobada($promesa), 'promesaPreaprobada', ['promesa_id'=>$promesa->id]);
        return back()->with('ok', 'Promesa pre-aprobada.');
    }

    public function rechazarSup(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        if (($promesa->workflow_estado ?? 'pendiente') !== 'pendiente') {
            return back()->withErrors('Solo se puede rechazar una promesa Pendiente.');
        }

        $promesa->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'nota_rechazo'    => substr((string)$req->input('nota_estado'), 0, 500),
        ]);

        $this->sendMailSafely(
            fn()=>WorkflowMailer::promesaRechazadaSup($promesa, $req->input('nota_estado')),
            'promesaRechazadaSup',
            ['promesa_id'=>$promesa->id]
        );
        return back()->with('ok', 'Promesa rechazada por supervisor.');
    }

    // ===== ADMIN =====
    public function aprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        if (($promesa->workflow_estado ?? '') !== 'preaprobada') {
            return back()->withErrors('Solo se puede aprobar una promesa Pre-aprobada.');
        }

        $promesa->update([
            'workflow_estado'    => 'aprobada',
            'aprobado_por'       => Auth::id(),
            'aprobado_at'        => now(),
            'nota_aprobacion'    => trim((string)$req->input('nota_estado')) ?: null,
            'rechazado_por'      => null,
            'rechazado_at'       => null,
            'nota_rechazo'       => null,
        ]);

        $this->sendMailSafely(
            fn()=>WorkflowMailer::promesaResuelta($promesa, true, $req->input('nota_estado')),
            'promesaResuelta.aprobar',
            ['promesa_id'=>$promesa->id]
        );
        return back()->with('ok', 'Promesa APROBADA.');
    }

    public function rechazarAdmin(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        if (($promesa->workflow_estado ?? '') !== 'preaprobada') {
            return back()->withErrors('Solo se puede rechazar una promesa Pre-aprobada.');
        }

        $promesa->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'nota_rechazo'    => substr((string)$req->input('nota_estado'), 0, 500),
        ]);

        $this->sendMailSafely(
            fn()=>WorkflowMailer::promesaResuelta($promesa, false, $req->input('nota_estado')),
            'promesaResuelta.rechazar',
            ['promesa_id'=>$promesa->id]
        );
        return back()->with('ok', 'Promesa rechazada por administrador.');
    }

    private function authorizeActionFor(string $role)
    {
        $user = Auth::user();
        if (!in_array(strtolower($user->role), [$role, 'sistemas'])) {
            abort(403, 'No autorizado.');
        }
    }

    // ===== LISTA PAGOS POR DNI (unificada, pagos_propia nuevo esquema)
    public function pagosDni(string $dni)
    {
        try {
            $dni = trim($dni);

            $rows = Pago::query()
                ->where('dni', $dni)
                ->orderByDesc('lote_id')   // lote más reciente
                ->orderByDesc('fecha')     // y fecha más reciente
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
            \Log::error('pagosDni error', ['dni' => $dni, 'msg' => $e->getMessage()]);
            return response()->json([
                'dni'   => $dni,
                'pagos' => [],
                'error' => 'No se pudo obtener los pagos',
            ], 500);
        }
    }

    /* =========================
     * Helpers (mailer seguro)
     * ========================= */
    private function sendMailSafely(callable $fn, string $context, array $extra = []): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            \Log::error('WorkflowMailer error: '.$context, $extra + [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // No interrumpimos la UX si falla el correo
        }
    }
}
