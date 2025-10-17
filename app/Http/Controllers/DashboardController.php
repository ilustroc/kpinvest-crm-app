<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PagoPropia as Pago;
use App\Models\PromesaPago;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        /* =============== Usuario y rol =============== */
        $user     = Auth::user();
        $isAsesor = strtolower((string)($user->role ?? '')) === 'asesor';
        $asesorNombre = trim((string)($user->name ?? ''));

        /* ================== Filtros ================== */
        $mesParam = $r->query('mes', now('America/Lima')->format('Y-m')); // YYYY-MM
        $fCosecha = trim((string)$r->query('cosecha', ''));
        $fEntidad = trim((string)$r->query('entidad', ''));
        // El filtro "Asesor" SOLO aplica para no-asesor
        $fAsesor  = $isAsesor ? '' : trim((string)$r->query('asesor',  '') ?: (string)$r->query('gestor',''));

        /* ========== Rango del mes (zona Lima) ========== */
        try {
            $inicioMes = Carbon::createFromFormat('Y-m', $mesParam, 'America/Lima')->startOfMonth();
        } catch (\Throwable $e) {
            $inicioMes = now('America/Lima')->startOfMonth();
            $mesParam  = $inicioMes->format('Y-m');
        }
        $finMes = (clone $inicioMes)->endOfMonth();

        // Ventana 12M para series
        $ini12 = (clone $inicioMes)->subMonths(11)->startOfMonth();
        $fin12 = (clone $finMes)->endOfMonth();

        /* ====== Base de pagos + filtros seleccionados ====== */
        $base = Pago::query();

        // Si es asesor, restringimos SIEMPRE por su nombre (pagos_propias.gestor)
        if ($isAsesor && $asesorNombre !== '') {
            $base->where('gestor', $asesorNombre);
        } elseif (!$isAsesor && $fAsesor !== '') {
            $base->where('gestor', 'like', "%{$fAsesor}%");
        }

        if ($fCosecha !== '') $base->where('cosecha', $fCosecha);
        if ($fEntidad !== '') $base->where('entidad', $fEntidad);

        /* ================= KPIs del MES ================= */
        // PROMESAS (conteo y monto). Si es asesor, por su user_id
        $pdpQuery = PromesaPago::whereBetween('created_at', [$inicioMes, $finMes]);
        if ($isAsesor) {
            $pdpQuery->where('user_id', $user->id);
        }
        $pdpGen   = (clone $pdpQuery)->count();
        $pdpMonto = (float) (clone $pdpQuery)
            ->selectRaw("SUM(CASE WHEN tipo='convenio' THEN COALESCE(monto_convenio,0) ELSE COALESCE(monto,0) END) as t")
            ->value('t');

        // PAGOS del mes (ya con restricciones de arriba)
        $pagosNum   = (clone $base)->whereBetween('fecha', [$inicioMes, $finMes])->count();
        $pagosMonto = (float) ((clone $base)->whereBetween('fecha', [$inicioMes, $finMes])->sum('monto_pagado'));

        $k = [
            'pdp_gen'     => $pdpGen,
            'pdp_monto'   => $pdpMonto,
            'pagos_num'   => $pagosNum,
            'pagos_monto' => $pagosMonto,
        ];

        /* ====== Serie últimos 12 meses (monto) ====== */
        $serieMonto = (clone $base)
            ->selectRaw("DATE_FORMAT(fecha,'%Y-%m') as ym, SUM(monto_pagado) as total")
            ->whereBetween('fecha', [$ini12, $fin12])
            ->groupBy('ym')->orderBy('ym')
            ->pluck('total','ym');

        $meses = [];
        $serie_pagos_monto = [];
        $cursor = $ini12->copy();
        for ($i=0; $i<12; $i++) {
            $key = $cursor->format('Y-m');
            $meses[] = strtoupper($cursor->locale('es')->isoFormat('MMM'));
            $serie_pagos_monto[] = (float) ($serieMonto[$key] ?? 0);
            $cursor->addMonth();
        }

        /* ====== Serie diaria del mes seleccionado (monto) ====== */
        $daily = (clone $base)
            ->selectRaw('DATE(fecha) as f, SUM(monto_pagado) as s')
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('f')->orderBy('f')->pluck('s','f');

        $dias = [];
        $serie_pagos_dia = [];
        $daysInMonth = $inicioMes->daysInMonth;
        for ($d=1; $d <= $daysInMonth; $d++) {
            $date = $inicioMes->copy()->day($d)->toDateString();
            $dias[] = str_pad($d, 2, '0', STR_PAD_LEFT);
            $serie_pagos_dia[] = (float)($daily[$date] ?? 0);
        }

        /* ====== Distribución (mes seleccionado) ====== */
        $topEntMes = (clone $base)
            ->selectRaw('COALESCE(NULLIF(TRIM(entidad),""),"—") as entidad, SUM(monto_pagado) as total')
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('entidad')
            ->orderByDesc('total')->limit(8)->get();

        // Para asesores NO mostramos ranking de asesores, pero preparamos datos por si rol != asesor
        $entLabels  = $topEntMes->pluck('entidad');
        $entData    = $topEntMes->pluck('total')->map(fn($v)=>(float)$v);

        $asesLabels = collect();
        $asesData   = collect();
        if (!$isAsesor) {
            $topAsesMes = (clone $base)
                ->selectRaw('COALESCE(NULLIF(TRIM(gestor),""),"—") as gestor, SUM(monto_pagado) as total')
                ->whereBetween('fecha', [$inicioMes, $finMes])
                ->groupBy('gestor')
                ->orderByDesc('total')->limit(10)->get();

            $asesLabels = $topAsesMes->pluck('gestor');
            $asesData   = $topAsesMes->pluck('total')->map(fn($v)=>(float)$v);
        }

        /* ====== Opciones de selects DEPENDIENTES DEL MES ====== */
        $optsMes = Pago::query()->whereBetween('fecha', [$inicioMes, $finMes]);
        if ($isAsesor && $asesorNombre !== '') {
            $optsMes->where('gestor', $asesorNombre);
        }

        $cosechas  = (clone $optsMes)->whereNotNull('cosecha')->select('cosecha')->distinct()->orderBy('cosecha')->pluck('cosecha');
        $entidades = (clone $optsMes)->whereNotNull('entidad')->select('entidad')->distinct()->orderBy('entidad')->pluck('entidad');

        // Lista de asesores SOLO para no-asesor (filtro visible)
        $asesores  = $isAsesor ? collect() :
            (clone $optsMes)->whereNotNull('gestor')->select('gestor')->distinct()->orderBy('gestor')->pluck('gestor');

        return view('dashboard.index', [
            'mes'               => $mesParam,
            'k'                 => $k,
            'isAsesor'          => $isAsesor,

            'meses'             => $meses,
            'serie_pagos_monto' => $serie_pagos_monto,

            'dias'              => $dias,
            'serie_pagos_dia'   => $serie_pagos_dia,

            'entLabels'         => $entLabels,
            'entData'           => $entData,
            'asesLabels'        => $asesLabels,
            'asesData'          => $asesData,

            'cosechas'          => $cosechas,
            'entidades'         => $entidades,
            'asesores'          => $asesores,

            'fCosecha'          => $fCosecha,
            'fEntidad'          => $fEntidad,
            'fAsesor'           => $fAsesor,
        ]);
    }
}
