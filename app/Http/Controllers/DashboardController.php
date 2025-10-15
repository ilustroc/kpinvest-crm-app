<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PagoPropia as Pago;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        // ====== Filtros ======
        $mesParam   = $r->query('mes', now('America/Lima')->format('Y-m')); // YYYY-MM
        $supervisor = (int) $r->query('supervisor_id', 0);

        // Nuevos filtros
        $fCosecha = trim((string)$r->query('cosecha', ''));  // ej. CAJAAQP1
        $fEntidad = trim((string)$r->query('entidad', ''));  // ej. CAJA AREQUIPA
        $fAsesor  = trim((string)$r->query('asesor', ''));   // alias/gestor
        if (!$fAsesor) {
            // compat: si llega como "gestor"
            $fAsesor = trim((string)$r->query('gestor', ''));
        }

        // ====== Rango del mes (zona: Lima) ======
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

        // ====== Base (tabla única de pagos) + filtros ======
        $base = Pago::query();

        if ($fCosecha !== '') $base->where('cosecha', $fCosecha);
        if ($fEntidad !== '') $base->where('entidad', $fEntidad);
        if ($fAsesor  !== '') $base->where('gestor', 'like', "%{$fAsesor}%");

        // (Opcional) filtro por supervisor -> mapear a aliases de gestores
        if ($supervisor > 0) {
            $sup = User::find($supervisor);
            // TODO: $aliases = $sup?->asesores()->pluck('alias')->filter();
            // if ($aliases && $aliases->count()) { $base->whereIn('gestor', $aliases); }
        }

        // ====== KPIs del mes ======
        $k = [
            'ccd_gen'      => 0,
            'pagos_num'    => (clone $base)->whereBetween('fecha', [$inicioMes, $finMes])->count(),
            'pagos_monto'  => (float) ((clone $base)
                                ->whereBetween('fecha', [$inicioMes, $finMes])
                                ->sum('monto_pagado')),
            'pdp_gen'      => 0,
            'pdp_vig'      => 0,
            'pdp_cumpl'    => 0,
            'pdp_caidas'   => 0,
        ];

        // ====== Serie últimos 12 meses (monto y #pagos) ======
        $serieMonto = (clone $base)
            ->selectRaw("DATE_FORMAT(fecha,'%Y-%m') as ym, SUM(monto_pagado) as total")
            ->whereBetween('fecha', [$ini12, $fin12])
            ->groupBy('ym')->orderBy('ym')
            ->pluck('total','ym');

        $serieCount = (clone $base)
            ->selectRaw("DATE_FORMAT(fecha,'%Y-%m') as ym, COUNT(*) as num")
            ->whereBetween('fecha', [$ini12, $fin12])
            ->groupBy('ym')->orderBy('ym')
            ->pluck('num','ym');

        $meses = []; $serie_pagos_monto = []; $serie_pagos_num = [];
        $cursor = $ini12->copy();
        for ($i=0; $i<12; $i++) {
            $key = $cursor->format('Y-m');
            $meses[] = strtoupper($cursor->locale('es')->isoFormat('MMM'));
            $serie_pagos_monto[] = (float) ($serieMonto[$key] ?? 0);
            $serie_pagos_num[]   = (int)   ($serieCount[$key] ?? 0);
            $cursor->addMonth();
        }

        // ====== Distribución (mes seleccionado) ======
        $topEntMes = (clone $base)
            ->selectRaw('COALESCE(NULLIF(TRIM(entidad),""),"—") as entidad, SUM(monto_pagado) as total')
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('entidad')
            ->orderByDesc('total')->limit(8)->get();

        $topAsesMes = (clone $base)
            ->selectRaw('COALESCE(NULLIF(TRIM(gestor),""),"—") as gestor, SUM(monto_pagado) as total')
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('gestor')
            ->orderByDesc('total')->limit(10)->get();

        $entLabels = $topEntMes->pluck('entidad');
        $entData   = $topEntMes->pluck('total')->map(fn($v)=>(float)$v);

        $asesLabels= $topAsesMes->pluck('gestor');
        $asesData  = $topAsesMes->pluck('total')->map(fn($v)=>(float)$v);

        // ====== Listas para selects (todas) ======
        $cosechas  = Pago::query()->select('cosecha')->whereNotNull('cosecha')->distinct()->orderBy('cosecha')->pluck('cosecha');
        $entidades = Pago::query()->select('entidad')->whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad');
        $asesores  = Pago::query()->select('gestor') ->whereNotNull('gestor') ->distinct()->orderBy('gestor')->pluck('gestor');

        $supervisores = User::where('role','supervisor')->select('id','name')->orderBy('name')->get();

        return view('dashboard.index', [
            'mes'              => $mesParam,
            'supervisorId'     => $supervisor,
            'supervisores'     => $supervisores,
            'k'                => $k,

            'meses'            => $meses,
            'serie_pagos_monto'=> $serie_pagos_monto,
            'serie_pagos_num'  => $serie_pagos_num,

            'entLabels'        => $entLabels,
            'entData'          => $entData,
            'asesLabels'       => $asesLabels,
            'asesData'         => $asesData,

            'cosechas'         => $cosechas,
            'entidades'        => $entidades,
            'asesores'         => $asesores,

            'fCosecha'         => $fCosecha,
            'fEntidad'         => $fEntidad,
            'fAsesor'          => $fAsesor,

            'gestiones'        => collect(),
            'cartera'          => 'unica',
        ]);
    }
}
