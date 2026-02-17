<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Query\Builder;

class PromiseReportService
{
    public function getFilteredQuery(array $filters): Builder
    {
        // 1. Definimos las subconsultas simples (sin agregaciones pesadas aquí)
        $opFirstSql = "SELECT po.promesa_id, po.operacion FROM promesa_operaciones po 
                       JOIN (SELECT promesa_id, MIN(id) AS min_id FROM promesa_operaciones GROUP BY promesa_id) x 
                       ON x.promesa_id = po.promesa_id AND x.min_id = po.id";

        $subCuotaSql = "SELECT pc.promesa_id, pc.fecha AS first_fecha, pc.monto AS first_monto FROM promesa_cuotas pc 
                        JOIN (SELECT promesa_id, MIN(nro) AS min_nro FROM promesa_cuotas GROUP BY promesa_id) x 
                        ON x.promesa_id = pc.promesa_id AND x.min_nro = pc.nro";

        $opKeySql = "COALESCE(pp.operacion, po_first.operacion)";

        // 2. Query Principal: Solo traemos lo básico de la promesa
        $qb = DB::table("promesas_pago as pp")
            ->leftJoin(DB::raw("({$opFirstSql}) as po_first"), 'po_first.promesa_id', '=', 'pp.id')
            ->leftJoin('users as us', 'us.id', '=', 'pp.user_id')
            ->leftJoin(DB::raw("({$subCuotaSql}) as fq"), 'fq.promesa_id', '=', 'pp.id');

        // 3. Selección de campos (Movimos las sumas a nivel de aplicación o subqueries escalares simples)
        $qb->selectRaw("
            pp.id as pp_id,
            pp.tipo as tipo_neg,
            pp.dni as nrodoc,
            pp.created_at as pp_created_at,
            DATE_FORMAT(pp.created_at, '%Y-%m-%d %H:%i:%s') as fecha,
            COALESCE(pp.telefono, '') as telefono,
            COALESCE(us.name, '') as negociador,
            COALESCE(pp.workflow_estado, '') as estado,
            COALESCE({$opKeySql}, '') as operacion,
            pp.nro_cuotas as cuotas,
            CASE
              WHEN pp.tipo = 'cancelacion' THEN DATE_FORMAT(pp.fecha_pago, '%Y-%m-%d')
              ELSE DATE_FORMAT(fq.first_fecha, '%Y-%m-%d')
            END as fec_pag,
            CASE
              WHEN pp.tipo = 'cancelacion' THEN pp.monto
              ELSE fq.first_monto
            END as pago_ini,
            COALESCE(pp.nota, '') as glosa_neg,
            -- Subqueries escalares (son más rápidas que un JOIN agrupado en muchos casos)
            (SELECT nombre FROM clientes_cuentas WHERE numdoc = pp.dni LIMIT 1) as cliente,
            (SELECT GROUP_CONCAT(DISTINCT entidad SEPARATOR ', ') FROM clientes_cuentas WHERE numdoc = pp.dni) as entidad,
            (SELECT COALESCE(GROUP_CONCAT(DISTINCT moneda SEPARATOR ', '), 'PEN') FROM clientes_cuentas WHERE numdoc = pp.dni) as moneda,
            (SELECT SUM(deuda_total) FROM clientes_cuentas WHERE numdoc = pp.dni) as deuda_act,
            (SELECT SUM(deuda_capital) FROM clientes_cuentas WHERE numdoc = pp.dni) as capital_act
        ");

        // 4. Filtros
        $this->applyFilters($qb, $filters);

        return $qb;
    }

    private function applyFilters($qb, $filters)
    {
        if (!empty($filters['from'])) $qb->whereDate('pp.created_at', '>=', $filters['from']);
        if (!empty($filters['to']))   $qb->whereDate('pp.created_at', '<=', $filters['to']);
        if (!empty($filters['estado']))  $qb->whereIn('pp.workflow_estado', (array)$filters['estado']);
        if (!empty($filters['tipo']))    $qb->whereIn('pp.tipo', (array)$filters['tipo']);
        
        if (!empty($filters['entidad'])) {
            $qb->whereExists(function ($query) use ($filters) {
                $query->select(DB::raw(1))
                    ->from('clientes_cuentas')
                    ->whereColumn('numdoc', 'pp.dni')
                    ->whereIn('entidad', (array)$filters['entidad']);
            });
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $qb->where(function ($w) use ($q) {
                $w->where('pp.dni', 'like', "%{$q}%")
                  ->orWhere('pp.nota', 'like', "%{$q}%");
            });
        }
    }

    public function getFacets(array $filters): array
    {
        // Cache por 10 minutos para no recalcular esto constantemente
        return Cache::remember('rpt_pdp_facets_v5:' . md5(json_encode($filters)), 600, function () use ($filters) {
            $base = $this->getFilteredQuery(array_merge($filters, ['estado' => [], 'tipo' => [], 'entidad' => []]));

            return [
                'estados'   => (clone $base)->select('pp.workflow_estado')->distinct()->pluck('workflow_estado')->filter()->all(),
                'tipos'     => (clone $base)->select('pp.tipo')->distinct()->pluck('tipo')->filter()->all(),
                'entidades' => DB::table('clientes_cuentas')->distinct()->orderBy('entidad')->pluck('entidad')->filter()->all(),
            ];
        });
    }
}