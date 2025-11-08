<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\PagoPropia as Pago;
use App\Models\ClienteCuenta;
use App\Models\CcdCliente;
use App\Models\PromesaPago;
use App\Models\User;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ClienteController extends Controller
{
    public function show(string $dni)
    {
        try {
            // === CUENTAS
            $cuentas = ClienteCuenta::query()
                ->where('numdoc', $dni)
                ->orderByDesc('updated_at')
                ->get([
                    'numdoc','nombre','cuenta','operacion','entidad','producto','cosecha',
                    'deuda_capital','interes','deuda_total',
                ]);
            abort_if($cuentas->isEmpty(), 404);
            $titular = $cuentas->first()->nombre ?? '—';

            // === PAGOS
            $pagos = Pago::query()
                ->where('dni',$dni)->orderByDesc('fecha')
                ->get(['fecha','dni','operacion','entidad','nombre_cliente','monto_pagado','gestor','cosecha','cuenta_recaudo']);
            $totPagos = (float) $pagos->sum('monto_pagado');

            // === CCD
            $ccdDocs = collect(); $ccdByDni = collect(); $ccdByCodigo = collect();
            if (Schema::hasTable('ccd_clientes')) {
                $colsCcd = DB::getSchemaBuilder()->getColumnListing('ccd_clientes');
                $selCcd  = collect(['id','numdoc','pdf','cosecha','link','codigo'])
                            ->filter(fn($c)=>in_array($c,$colsCcd))->all();
                $ccdDocs = CcdCliente::query()->where('numdoc',$dni)->orderByDesc('id')->get($selCcd);
                $ccdByDni = collect([$dni => $ccdDocs->values()]);
                if (in_array('codigo',$colsCcd)) $ccdByCodigo = $ccdDocs->groupBy('codigo');
            }

            // === Mapas de pagos
            $op2cta = $pagos->groupBy('operacion')->map(function ($grp) {
                $freq = $grp->pluck('cuenta_recaudo')->filter()->countBy();
                return $freq->isEmpty() ? null : $freq->sortDesc()->keys()->first();
            });
            $pagosGrouped = $pagos->groupBy('operacion');

            $cuentas = $cuentas->map(function ($c) use ($pagosGrouped, $op2cta) {
                $grupo = $pagosGrouped->get($c->operacion) ?? collect();
                $c->pagos_count = $grupo->count();
                $c->pagos_sum   = (float) $grupo->sum('monto_pagado');
                $ctaDb  = trim((string)($c->cuenta ?? ''));
                $ctaMap = trim((string)($op2cta[$c->operacion] ?? ''));
                $c->cuenta = $ctaDb !== '' ? $ctaDb : ($ctaMap !== '' ? $ctaMap : $c->operacion);
                return $c;
            });

            // === PROMESAS
            $teamIds = $this->myTeamUserIds();
            $promesas = PromesaPago::query()
                ->where('dni',$dni)
                ->when(!empty($teamIds), fn($q)=>$q->whereIn('user_id',$teamIds))
                ->with(['operaciones','cuotas'=>fn($q)=>$q->orderBy('nro')])
                ->orderByDesc('fecha_promesa')
                ->get();

            // === CNA (si existe)
            $cnasByCuenta = collect(); $cnasByOperacion = collect();
            if (Schema::hasTable('cna_solicitudes')) {
                $colsCna = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');
                $want    = ['id','dni','nro_carta','operaciones','workflow_estado','created_at','pdf_path','docx_path'];
                $selCna  = collect($want)->filter(fn($c)=>in_array($c,$colsCna))->values()->all();

                $cnas = DB::table('cna_solicitudes')->select($selCna)->where('dni',$dni)->orderByDesc('created_at')->get();
                $mapCta=[]; $mapOp=[];
                foreach ($cnas as $row) {
                    $opsRaw = $row->operaciones ?? '[]';
                    $opsArr = is_array($opsRaw) ? $opsRaw : (json_decode($opsRaw, true) ?: []);
                    if (!is_array($opsArr)) $opsArr = array_filter(array_map('trim', explode(',', (string)$opsRaw)));
                    foreach ($opsArr as $op) {
                        $mapOp[$op] = $mapOp[$op] ?? collect();
                        $mapOp[$op]->push((object)[
                            'id'=>$row->id,'nro_carta'=>$row->nro_carta ?? $row->id,
                            'workflow_estado'=>$row->workflow_estado ?? 'pendiente',
                            'created_at'=>$row->created_at,'pdf_path'=>$row->pdf_path ?? null,'docx_path'=>$row->docx_path ?? null,
                        ]);
                        $ctaDb = optional($cuentas->firstWhere('operacion',$op))->cuenta;
                        $cta   = (string)($ctaDb ?: ($op2cta[$op] ?? $op));
                        $mapCta[$cta] = $mapCta[$cta] ?? collect();
                        $mapCta[$cta]->push((object)[
                            'id'=>$row->id,'nro_carta'=>$row->nro_carta ?? $row->id,
                            'workflow_estado'=>$row->workflow_estado ?? 'pendiente',
                            'created_at'=>$row->created_at,'pdf_path'=>$row->pdf_path ?? null,'docx_path'=>$row->docx_path ?? null,
                        ]);
                    }
                }
                $cnasByCuenta = collect($mapCta);
                $cnasByOperacion = collect($mapOp);
            }

            // Próximo correlativo (si tuvieses la columna)
            $nextNroCarta = null;
            if (Schema::hasTable('cna_solicitudes')) {
                $colsCna = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');
                if (in_array('correlativo',$colsCna)) {
                    $maxCorr = (int) DB::table('cna_solicitudes')->max('correlativo');
                    $nextNroCarta = str_pad(($maxCorr ?: 0) + 1, 6, '0', STR_PAD_LEFT);
                }
            }

            return view('clientes.show', compact(
                'dni','titular','cuentas','pagos','promesas',
                'ccdDocs','ccdByDni','ccdByCodigo','cnasByCuenta','cnasByOperacion',
                'pagosGrouped','nextNroCarta','totPagos'
            ));
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) throw $e;
            Log::error('ClienteController.show', ['dni'=>$dni,'err'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
            return back()->withErrors('Error cargando el cliente: '.$e->getMessage());
        }
    }

    /** Igual a tu helper actual */
    private function myTeamUserIds(): array
    {
        $me = Auth::user(); if (!$me) return [];
        $role = strtolower((string)$me->role);
        if (in_array($role,['administrador','sistemas','soporte'])) return [];
        if ($role === 'supervisor') {
            $ids = User::where('supervisor_id',$me->id)->pluck('id')->all();
            $ids[] = $me->id; return $ids;
        }
        return [$me->id];
    }
}
