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
    private string $table    = 'promesas_pago';          // principal (alias pp)
    private string $opsTable = 'promesa_operaciones';    // detalle   (alias po)

    public function index(Request $r)
    {
        $qb   = $this->baseQuery($r);
        $rows = $qb->paginate(25)->withQueryString();

        // Filtros para la vista (si la usas)
        $from        = $r->query('from', Carbon::today()->startOfMonth()->toDateString());
        $to          = $r->query('to',   Carbon::today()->toDateString());
        $estado      = $r->query('estado', '');
        $negociador  = $r->query('negociador', '');
        $q           = $r->query('q', '');

        // Ajusta el nombre de la vista si corresponde
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
                (string)$r2->fecha,         // Y-m-d H:i:s
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
                (string)$r2->fec_pag,       // Y-m-d
                $r2->pago_ini    !== null ? (float)$r2->pago_ini    : '',
                (string)$r2->glosa_neg,
            ], null, "A{$row}");

            // Nrodoc como texto (conservar ceros)
            $sheet->setCellValueExplicit("F{$row}", (string)$r2->nrodoc, DataType::TYPE_STRING);
            $row++;
        }

        // Auto-size
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
     * Query base: UNA FILA POR OPERACIÓN con las columnas solicitadas.
     */
    private function baseQuery(Request $r)
    {
        if (!Schema::hasTable($this->table)) {
            abort(500, "No existe la tabla {$this->table}.");
        }

        $qb = DB::table("{$this->table} as pp")
            ->leftJoin("{$this->opsTable} as po", 'po.promesa_id', '=', 'pp.id')
            // operación efectiva: detalle si existe, si no el legacy de pp
            ->leftJoin('clientes_cuentas as cc', DB::raw('COALESCE(po.operacion, pp.operacion)'), '=', 'cc.operacion')
            ->leftJoin('users as us', 'us.id', '=', 'pp.user_id');

        // SELECT — nombres/orden exactamente como el layout pedido
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
            DATE_FORMAT(pp.fecha_pago, '%Y-%m-%d')                as fec_pag,
            CASE
              WHEN COALESCE(pp.monto,0) > 0 THEN pp.monto
              ELSE pp.monto_cuota
            END                                                   as pago_ini,
            COALESCE(pp.nota,'')                                  as glosa_neg,
            pp.created_at                                         as pp_created_at
        ");

        // ===== Filtros =====
        $from       = $r->query('from');
        $to         = $r->query('to');
        $estado     = trim((string)$r->query('estado',''));
        $negoc      = trim((string)$r->query('negociador',''));
        $q          = trim((string)$r->query('q',''));

        if ($from) $qb->whereDate('pp.created_at','>=',$from);
        if ($to)   $qb->whereDate('pp.created_at','<=',$to);

        if ($estado !== '' && Schema::hasColumn($this->table, 'workflow_estado')) {
            $qb->where('pp.workflow_estado','like',"%{$estado}%");
        }

        // Filtro por negociador (usuario creador)
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

        // Orden por fecha de creación y id (desc)
        $qb->orderByDesc('pp.created_at')->orderByDesc('pp.id');

        return $qb;
    }
}
