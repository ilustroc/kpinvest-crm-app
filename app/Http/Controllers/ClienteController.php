<?php

namespace App\Http\Controllers;

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
            $ccdByCosecha = collect();   // << nuevo
            $ccdCosechaKeys = [];        // << nuevo

            if (Schema::hasTable('ccd_clientes')) {
                $colsCcd = DB::getSchemaBuilder()->getColumnListing('ccd_clientes');
                $selCcd  = collect(['id','numdoc','pdf','cosecha','link','codigo'])
                    ->filter(fn($c)=>in_array($c,$colsCcd))->all();

                $ccdDocs = CcdCliente::query()
                    ->where('numdoc',$dni)
                    ->orderByDesc('id')
                    ->get($selCcd);

                $ccdByDni = collect([$dni => $ccdDocs->values()]);
                if (in_array('codigo',$colsCcd)) $ccdByCodigo = $ccdDocs->groupBy('codigo');

                // normalizador (mismo para ambos lados)
                $norm = fn($s) => preg_replace('/[\s_]+/','', strtoupper(trim((string)$s)));

                // índice por cosecha en CCD
                $ccdByCosecha = $ccdDocs->groupBy(fn($d) => $norm($d->cosecha));

                // llave de cosecha para cada cuenta
                foreach ($cuentas as $cta) {
                    $orig = (string)($cta->cosecha ?? '');
                    $key  = $this->mapCosechaClientesToCcd($orig); // p.ej. CONFIANZA_4 -> CONFIANZA4, CAJAAQP4 -> AQP4
                    $ccdCosechaKeys[$orig] = $norm($key);
                }
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

            // === PROMESAS (sin myTeamUserIds)
            $promesas = PromesaPago::query()
                ->where('dni', $dni)
                ->with(['operaciones', 'cuotas' => fn($q) => $q->orderBy('nro')])
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
                'ccdDocs','ccdByDni','ccdByCodigo',
                'ccdByCosecha','ccdCosechaKeys',
                'cnasByCuenta','cnasByOperacion','pagosGrouped','nextNroCarta','totPagos'
            ));
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) throw $e;
            Log::error('ClienteController.show', ['dni'=>$dni,'err'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
            return back()->withErrors('Error cargando el cliente: '.$e->getMessage());
        }
    }

    // Helpers (ponlo dentro del controller)
    private function mapCosechaClientesToCcd(?string $c): ?string {
        if (!$c) return null;
        $c = strtoupper(trim($c));

        // === Mapeos explícitos (BBVA y casos especiales)
        $direct = [
            'BBVA1' => 'BBVA_1_2', 'BBVA2' => 'BBVA_1_2',
            'BBVA3' => 'BBVA_3_4', 'BBVA4' => 'BBVA_3_4',
            'BBVA5' => 'BBVA_5',
            'BBVA6' => 'BBVA_6',
            'BBVA7' => 'BBVA_7_8', 'BBVA8' => 'BBVA_7_8',
        ];
        if (isset($direct[$c])) return $direct[$c];

        // CONFIANZA_4  -> CONFIANZA4
        if (preg_match('/^CONFIANZA_(\d{1,2})$/', $c, $m)) return 'CONFIANZA'.$m[1];

        // COMPARTAMOS_1 -> COMPARTAMOS1
        if (preg_match('/^COMPARTAMOS_(\d{1,2})$/', $c, $m)) return 'COMPARTAMOS'.$m[1];

        // CAJAAQP1 -> AQP1
        if (preg_match('/^CAJAAQP(\d{1,2})$/', $c, $m)) return 'AQP'.$m[1];

        return $c; // fallback
    }
}
