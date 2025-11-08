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

class ReportePromesasController extends Controller
{
    // Tablas
    private string $table       = 'promesas_pago';        // principal (alias pp)
    private string $opsTable    = 'promesa_operaciones';  // detalle   (alias po)
    private string $cuotasTable = 'promesa_cuotas';       // cuotas    (alias pc)

    public function index(Request $r)
    {
        $qb   = $this->baseQuery($r);
        $rows = $qb->paginate(25)->withQueryString();

        $from       = $r->query('from', Carbon::today()->startOfMonth()->toDateString());
        $to         = $r->query('to',   Carbon::today()->toDateString());
        $estado     = $r->query('estado', '');
        $negociador = $r->query('negociador', '');
        $q          = $r->query('q', '');

        return view('reportes.pdp', compact('rows','from','to','estado','negociador','q'));
    }

    public function export(Request $r)
    {
        $qb   = $this->baseQuery($r)->orderBy('pp_created_at');
        $rows = $qb->get();

        $xlsx  = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet(); $row = 1;

        $headers = [
            'Tipo_Neg','Entidad','Fecha','Cliente','Telefono','Nrodoc','Negociador','Situacion',
            'Operacion','Moneda','Deuda_Act','Capital_Act','Cuotas','Fec_Pag','Pago_Ini','Glosa_Neg'
        ];
        $sheet->fromArray($headers, null, "A{$row}"); $row++;

        foreach ($rows as $r2) {
            $sheet->fromArray([
                (string)$r2->tipo_neg,
                (string)$r2->entidad,
                (string)$r2->fecha,
                (string)$r2->cliente,
                (string)$r2->telefono,
                (string)$r2->nrodoc,
                (string)$r2->negociador,
                (string)$r2->situacion,
                (string)$r2->operacion,
                (string)$r2->moneda,
                $r2->deuda_act   !== null ? (float)$r2->deuda_act   : '',
                $r2->capital_act !== null ? (float)$r2->capital_act : '',
                $r2->cuotas      !== null ? (int)$r2->cuotas        : '',
                (string)$r2->fec_pag,
                $r2->pago_ini    !== null ? (float)$r2->pago_ini    : '',
                (string)$r2->glosa_neg,
            ], null, "A{$row}");

            $sheet->setCellValueExplicit("F{$row}", (string)$r2->nrodoc, DataType::TYPE_STRING);
            $row++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c='A'; $c <= $lastCol; $c++) $sheet->getColumnDimension($c)->setAutoSize(true);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        (new Xlsx($xlsx))->save($tmp);

        return response()->download(
            $tmp,
            'reporte_promesas_'.now()->format('Ymd_His').'.xlsx',
            [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ]
        )->deleteFileAfterSend(true);
    }

    /**
     * Query base: UNA FILA POR OPERACIÓN.
     */
    private function baseQuery(Request $r)
    {
        if (!Schema::hasTable($this->table)) {
            abort(500, "No existe la tabla {$this->table}.");
        }

        // Subquery: primera cuota por promesa (por nro mínimo)
        $subSql = "
            SELECT pc.promesa_id,
                   pc.fecha AS first_fecha,
                   pc.monto AS first_monto
            FROM {$this->cuotasTable} pc
            JOIN (
                SELECT promesa_id, MIN(nro) AS min_nro
                FROM {$this->cuotasTable}
                GROUP BY promesa_id
            ) x ON x.promesa_id = pc.promesa_id AND x.min_nro = pc.nro
        ";

        $qb = DB::table("{$this->table} as pp")
            ->leftJoin("{$this->opsTable} as po", 'po.promesa_id', '=', 'pp.id')
            ->leftJoin('clientes_cuentas as cc', DB::raw('COALESCE(po.operacion, pp.operacion)'), '=', 'cc.operacion')
            ->leftJoin('users as us', 'us.id', '=', 'pp.user_id')
            ->leftJoin(DB::raw("({$subSql}) as fq"), 'fq.promesa_id', '=', 'pp.id');

        $qb->selectRaw("
            pp.tipo                                                as tipo_neg,
            COALESCE(cc.entidad,'')                               as entidad,
            DATE_FORMAT(pp.created_at, '%Y-%m-%d %H:%i:%s')       as fecha,
            COALESCE(cc.nombre,'')                                as cliente,
            COALESCE(pp.telefono,'')                              as telefono,
            COALESCE(pp.dni, cc.numdoc, '')                       as nrodoc,
            COALESCE(us.name,'')                                  as negociador,
            COALESCE(pp.workflow_estado,'')                       as situacion,
            COALESCE(po.operacion, pp.operacion, '')              as operacion,
            COALESCE(cc.moneda,'')                                as moneda,
            cc.deuda_total                                        as deuda_act,
            cc.deuda_capital                                      as capital_act,
            pp.nro_cuotas                                         as cuotas,

            /* ===== Fecha de pago según tipo ===== */
            CASE
              WHEN pp.tipo = 'cancelacion'
                   THEN DATE_FORMAT(pp.fecha_pago, '%Y-%m-%d')
              WHEN pp.tipo IN ('convenio','convenio_balon')
                   THEN DATE_FORMAT(fq.first_fecha, '%Y-%m-%d')
              ELSE DATE_FORMAT(COALESCE(fq.first_fecha, pp.fecha_pago), '%Y-%m-%d')
            END                                                   as fec_pag,

            /* ===== Monto según tipo ===== */
            CASE
              WHEN pp.tipo = 'cancelacion'
                   THEN pp.monto
              WHEN pp.tipo IN ('convenio','convenio_balon')
                   THEN fq.first_monto
              ELSE COALESCE(fq.first_monto, COALESCE(pp.monto, pp.monto_cuota))
            END                                                   as pago_ini,

            COALESCE(pp.nota,'')                                  as glosa_neg,
            pp.created_at                                         as pp_created_at
        ");

        // ===== Filtros =====
        $from   = $r->query('from');
        $to     = $r->query('to');
        $estado = trim((string)$r->query('estado',''));
        $negoc  = trim((string)$r->query('negociador',''));
        $q      = trim((string)$r->query('q',''));

        if ($from) $qb->whereDate('pp.created_at','>=',$from);
        if ($to)   $qb->whereDate('pp.created_at','<=',$to);

        if ($estado !== '' && Schema::hasColumn($this->table, 'workflow_estado')) {
            $qb->where('pp.workflow_estado','like',"%{$estado}%");
        }

        if ($negoc !== '') {
            $qb->where(function($w) use ($negoc){
                $w->where('us.name','like',"%{$negoc}%")
                  ->orWhere('us.email','like',"%{$negoc}%");
            });
        }

        if ($q !== '') {
            $qb->where(function($w) use ($q){
                $w->where('pp.dni','like',"%{$q}%")
                  ->orWhere(DB::raw('COALESCE(po.operacion, pp.operacion)'), 'like', "%{$q}%")
                  ->orWhere('cc.nombre','like',"%{$q}%")
                  ->orWhere('cc.entidad','like',"%{$q}%")
                  ->orWhere('pp.telefono','like',"%{$q}%")
                  ->orWhere('pp.nota','like',"%{$q}%");
            });
        }

        $qb->orderByDesc('pp.created_at')->orderByDesc('pp.id');

        return $qb;
    }
}
