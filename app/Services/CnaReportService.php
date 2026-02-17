<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CnaReportService
{
    private string $tCna = 'cna_solicitudes';
    private string $tCli = 'clientes_cuentas';
    private string $tUsr = 'users';

    public function getFilteredQuery(array $filters)
    {
        $fechaBaseSql  = $this->fechaBaseSql();
        $opPrimariaSql = $this->opPrimariaSql();

        $qb = DB::table("{$this->tCna} as cna")
            ->leftJoin("{$this->tCli} as cc", function ($j) use ($opPrimariaSql) {
                $j->on('cc.numdoc', '=', 'cna.dni');
                $j->whereRaw("cc.operacion = {$opPrimariaSql}");
            })
            ->leftJoin("{$this->tUsr} as u_crea", 'u_crea.id', '=', 'cna.user_id')
            ->leftJoin("{$this->tUsr} as u_pre",  'u_pre.id',  '=', 'cna.pre_aprobado_por')
            ->leftJoin("{$this->tUsr} as u_apr",  'u_apr.id',  '=', 'cna.aprobado_por');

        $qb->selectRaw("
            cna.id,
            cna.dni as documento,
            cna.titular as cliente,
            COALESCE(cc.entidad,'') as entidad,
            cna.nro_carta as cna_nro,
            DATE_FORMAT(cna.aprobado_at, '%Y-%m-%d %H:%i:%s') as cna_fec,
            CASE
                WHEN RIGHT(cna.nro_carta, 2) = 'F2' OR RIGHT(cna.nro_carta, 1) = 'F' 
                THEN 'FONDO DE INVERSIÓN PRIVADO FIT CAPITAL ACREENCIAS II'
                ELSE 'KP INVEST S.A.C'
            END as fondo_inv,
            DATE_FORMAT(cna.fecha_pago_realizado, '%Y-%m') as anio_mes,
            cna.monto_pagado as cna_imp,
            COALESCE(cc.cuenta, '') as nro_cuenta,
            {$opPrimariaSql} as nro_operacion,
            COALESCE(u_crea.name, CAST(cna.user_id AS CHAR)) as gestor,
            COALESCE(u_pre.name,  CAST(cna.pre_aprobado_por AS CHAR)) as gen_gestor,
            COALESCE(u_apr.name,  CAST(cna.aprobado_por AS CHAR)) as apr_gestor,
            COALESCE(cna.workflow_estado, '') as estado
        ");

        // Filtros de fecha
        if (!empty($filters['from'])) $qb->whereDate(DB::raw($fechaBaseSql), '>=', $filters['from']);
        if (!empty($filters['to']))   $qb->whereDate(DB::raw($fechaBaseSql), '<=', $filters['to']);

        // Filtros Multiselect
        if (!empty($filters['estado']))  $qb->whereIn('cna.workflow_estado', (array)$filters['estado']);
        if (!empty($filters['gestor']))  $qb->whereIn('u_crea.name', (array)$filters['gestor']);
        if (!empty($filters['entidad'])) $qb->whereIn('cc.entidad', (array)$filters['entidad']);

        return $qb;
    }

    public function getFacets(array $filters): array
    {
        $key = "rpt_cna_facets_v2:" . md5(json_encode($filters));

        return Cache::remember($key, 120, function () use ($filters) {
            return [
                'estados'   => $this->fetchFacet('cna.workflow_estado', $filters),
                'gestores'  => $this->fetchFacet('u_crea.name', $filters),
                'entidades' => $this->fetchFacet('cc.entidad', $filters),
            ];
        });
    }

    private function fetchFacet(string $field, array $filters): array
    {
        $tempFilters = $filters;
        $cleanField = last(explode('.', $field));
        unset($tempFilters[$cleanField]);

        return $this->getFilteredQuery($tempFilters)
            ->whereNotNull($field)->where($field, '<>', '')
            ->select($field)->distinct()->orderBy($field)
            ->pluck($cleanField)->values()->all();
    }

    private function fechaBaseSql(): string { return "COALESCE(cna.aprobado_at, cna.created_at)"; }

    private function opPrimariaSql(): string {
        return "COALESCE(NULLIF(CASE WHEN cna.operaciones IS NOT NULL AND JSON_VALID(cna.operaciones) 
                THEN JSON_UNQUOTE(JSON_EXTRACT(cna.operaciones,'$[0]')) ELSE cna.operaciones END, ''), '')";
    }
}