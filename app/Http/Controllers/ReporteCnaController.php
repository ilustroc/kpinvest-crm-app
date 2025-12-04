<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Carbon\Carbon;

class ReporteCnaController extends Controller
{
    private string $tCna = 'cna_solicitudes';
    private string $tCli = 'clientes_cuentas';
    private string $tUsr = 'users';

    public function index(Request $r)
    {
        $qb   = $this->baseQuery($r);
        $rows = $qb->paginate(25)->withQueryString();

        $from       = $r->query('from', Carbon::today()->startOfMonth()->toDateString());
        $to         = $r->query('to',   Carbon::today()->toDateString());
        $estado     = $r->query('estado', '');
        $negociador = $r->query('negociador', '');
        $q          = $r->query('q', '');

        return view('reportes.cna', compact('rows','from','to','estado','negociador','q'));
    }

    public function export(Request $r)
    {
        $rows = $this->baseQuery($r)->orderBy('cna_fecha_base')->get();

        $xlsx  = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row   = 1;

        $headers = [
            'Id','Documento','Cliente','Cna_Nro','Cna_Fec','Fondo_Inv','Año_Mes',
            'Cna_Imp','Nro_Cuenta','Nro_Operación','Gestor','Estado','Gen_Gestor','Apr_Gestor'
        ];
        $sheet->fromArray($headers, null, "A{$row}"); $row++;

        foreach ($rows as $r) {
            $sheet->fromArray([
                (int)$r->id,
                (string)$r->documento,
                (string)$r->cliente,
                (string)$r->cna_nro,
                (string)$r->cna_fec,
                (string)$r->fondo_inv,
                (string)$r->anio_mes,
                $r->cna_imp !== null ? (float)$r->cna_imp : '',
                (string)$r->nro_cuenta,
                (string)$r->nro_operacion,
                (string)$r->gestor,
                (string)$r->estado,
                (string)$r->gen_gestor,
                (string)$r->apr_gestor,
            ], null, "A{$row}");

            // Documento como TEXTO (conserva ceros a la izquierda)
            $sheet->setCellValueExplicit("B{$row}", (string)$r->documento, DataType::TYPE_STRING);
            $row++;
        }

        // Auto-size
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c = 'A'; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cna_');
        (new Xlsx($xlsx))->save($tmp);

        return response()->download(
            $tmp,
            'reporte_cna_'.now()->format('Ymd_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /**
     * Base query con todas las columnas requeridas y filtros.
     * Una fila por solicitud CNA (con datos complementarios del cliente).
     */
    private function baseQuery(Request $r)
    {
        if (!Schema::hasTable($this->tCna)) {
            abort(500, "No existe la tabla {$this->tCna}.");
        }

        // SQL literales reutilizables
        $fechaBaseSql  = "COALESCE(cna.aprobado_at, cna.created_at)";
        $opPrimariaSql = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cna.operaciones, '$[0]')), cna.operaciones)";

        $qb = DB::table("{$this->tCna} as cna")
            // Join por DNI + operación (cuando 'operaciones' es JSON)
            ->leftJoin("{$this->tCli} as cc", function ($j) {
                $j->on('cc.numdoc', '=', 'cna.dni');
                $j->whereRaw("
                    cna.operaciones IS NOT NULL
                    AND JSON_VALID(cna.operaciones)
                    AND JSON_CONTAINS(cna.operaciones, JSON_QUOTE(cc.operacion))
                ");
            })
            // Usuarios: creador, pre-aprobador y aprobador
            ->leftJoin("{$this->tUsr} as u_crea", 'u_crea.id', '=', 'cna.user_id')
            ->leftJoin("{$this->tUsr} as u_pre",  'u_pre.id',  '=', 'cna.pre_aprobado_por')
            ->leftJoin("{$this->tUsr} as u_apr",  'u_apr.id',  '=', 'cna.aprobado_por');

        // Selección
        $qb->selectRaw("
            cna.id                                          as id,
            cna.dni                                         as documento,
            cna.titular                                     as cliente,
            cna.nro_carta                                   as cna_nro,
            DATE_FORMAT(cna.aprobado_at, '%Y-%m-%d %H:%i:%s') as cna_fec,
            CASE
                WHEN RIGHT(cna.nro_carta, 2) = 'F2' THEN 'FONDO DE INVERSIÓN PRIVADO FIT CAPITAL ACREENCIAS II'
                WHEN RIGHT(cna.nro_carta, 1) = 'F'  THEN 'FONDO DE INVERSIÓN PRIVADO FIT CAPITAL ACREENCIAS II'
                ELSE 'KP INVEST S.A.C'
            END                                             as fondo_inv,
            DATE_FORMAT(cna.fecha_pago_realizado, '%Y-%m')  as anio_mes,
            cna.monto_pagado                                as cna_imp,
            COALESCE(cc.cuenta, '')                         as nro_cuenta,
            {$opPrimariaSql}                                as nro_operacion,

            -- >>> nombres desde users (fallback al valor original si no hay match)
            COALESCE(u_crea.name, CAST(cna.user_id AS CHAR))        as gestor,
            COALESCE(u_pre.name,  CAST(cna.pre_aprobado_por AS CHAR)) as gen_gestor,
            COALESCE(u_apr.name,  CAST(cna.aprobado_por AS CHAR))     as apr_gestor,

            COALESCE(cna.workflow_estado, '')               as estado,
            {$fechaBaseSql}                                 as cna_fecha_base
        ");

        // ===== Filtros =====
        $from       = $r->query('from');
        $to         = $r->query('to');
        $estado     = trim((string)$r->query('estado',''));
        $negociador = trim((string)$r->query('negociador',''));
        $q          = trim((string)$r->query('q',''));

        if ($from) $qb->whereDate(DB::raw($fechaBaseSql), '>=', $from);
        if ($to)   $qb->whereDate(DB::raw($fechaBaseSql), '<=', $to);

        if ($estado !== '') {
            $qb->where('cna.workflow_estado','like',"%{$estado}%");
        }

        if ($negociador !== '') {
            $qb->where(function($w) use ($negociador){
                $w->where('u_crea.name','like',"%{$negociador}%")
                ->orWhere('u_pre.name','like',"%{$negociador}%")
                ->orWhere('u_apr.name','like',"%{$negociador}%")
                ->orWhere('cna.user_id','like',"%{$negociador}%")
                ->orWhere('cna.pre_aprobado_por','like',"%{$negociador}%")
                ->orWhere('cna.aprobado_por','like',"%{$negociador}%");
            });
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $opPrimariaSql) {
                $w->where('cna.dni','like',"%{$q}%")
                ->orWhere('cna.titular','like',"%{$q}%")
                ->orWhere('cna.nro_carta','like',"%{$q}%")
                ->orWhere('cna.operaciones','like',"%{$q}%")
                ->orWhere(DB::raw($opPrimariaSql),'like',"%{$q}%")
                ->orWhere('cc.cuenta','like',"%{$q}%");
            });
        }

        $qb->orderByDesc(DB::raw($fechaBaseSql))
        ->orderByDesc('cna.id');

        return $qb;
    }
}
