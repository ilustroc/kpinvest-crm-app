<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReportePromesasController extends Controller
{
    // Tablas
    private string $table       = 'promesas_pago';        // pp
    private string $cuotasTable = 'promesa_cuotas';       // pc

    public function index(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to',   $defaultTo);

        // Multi-select
        $estadoSel  = (array) $r->query('estado', []);
        $tipoSel    = (array) $r->query('tipo_neg', []);
        $entidadSel = (array) $r->query('entidad', []);

        $q = (string) $r->query('q', '');

        $rows = $this->buildQuery($from, $to, $estadoSel, $tipoSel, $entidadSel, $q)
            ->paginate(25)
            ->withQueryString();

        // Facets "cascada" (dependen de los filtros actuales)
        [$estados, $tipos, $entidades] = $this->getFacets($from, $to, $estadoSel, $tipoSel, $entidadSel, $q);

        // Partial/AJAX: solo tabla
        if ($r->ajax() || $r->boolean('partial')) {
            return view('reportes.pdp_table', compact('rows'));
        }

        return view('reportes.pdp', compact(
            'rows',
            'from','to','q',
            'defaultFrom','defaultTo',
            'estados','tipos','entidades',
            'estadoSel','tipoSel','entidadSel'
        ));
    }

    /**
     * Endpoint para refrescar listas (facets) según filtros activos.
     */
    public function facets(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to',   $defaultTo);

        $estadoSel  = (array) $r->query('estado', []);
        $tipoSel    = (array) $r->query('tipo_neg', []);
        $entidadSel = (array) $r->query('entidad', []);
        $q          = (string) $r->query('q', '');

        [$estados, $tipos, $entidades] = $this->getFacets($from, $to, $estadoSel, $tipoSel, $entidadSel, $q);

        return response()->json([
            'estados'   => $estados,
            'tipos'     => $tipos,
            'entidades' => $entidades,
        ]);
    }

    public function export(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to',   $defaultTo);

        $estadoSel  = (array) $r->query('estado', []);
        $tipoSel    = (array) $r->query('tipo_neg', []);
        $entidadSel = (array) $r->query('entidad', []);
        $q          = (string) $r->query('q', '');

        $query = $this->buildQuery($from, $to, $estadoSel, $tipoSel, $entidadSel, $q)
            ->orderByDesc('pp_created_at')
            ->orderByDesc('pp_id');

        $filename = 'promesas_' . now()->format('Ymd_His') . '.xlsx';

        $xlsx  = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row   = 1;

        $headers = [
            'Tipo_Neg','Entidad','Fecha','Cliente','Telefono','Nrodoc','Negociador','Estado',
            'Operacion','Moneda','Deuda_Act','Capital_Act','Cuotas','Fec_Pag','Pago_Ini','Glosa_Neg'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        // Para no reventar memoria
        $query->chunk(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $r2) {
                $sheet->fromArray([
                    (string)$r2->tipo_neg,
                    (string)$r2->entidad,
                    (string)$r2->fecha,
                    (string)$r2->cliente,
                    (string)$r2->telefono,
                    (string)$r2->nrodoc,
                    (string)$r2->negociador,
                    (string)$r2->estado,
                    (string)$r2->operacion,
                    (string)$r2->moneda,
                    $r2->deuda_act   !== null ? (float)$r2->deuda_act   : '',
                    $r2->capital_act !== null ? (float)$r2->capital_act : '',
                    $r2->cuotas      !== null ? (int)$r2->cuotas        : '',
                    (string)$r2->fec_pag,
                    $r2->pago_ini    !== null ? (float)$r2->pago_ini    : '',
                    (string)$r2->glosa_neg,
                ], null, "A{$row}");

                // DNI y Operación como texto
                $sheet->setCellValueExplicit("F{$row}", (string)$r2->nrodoc, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("I{$row}", (string)$r2->operacion, DataType::TYPE_STRING);

                $row++;
            }
        });

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c = 'A'; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        (new Xlsx($xlsx))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    /**
     * ===== Query base: 1 fila por operación (po.operacion si existe; sino pp.operacion)
     */
    private function buildQuery(
        ?string $from,
        ?string $to,
        array $estadoSel,
        array $tipoSel,
        array $entidadSel,
        ?string $q
    ) {
        if (!Schema::hasTable($this->table)) {
            abort(500, "No existe la tabla {$this->table}.");
        }

        // Subquery: primera cuota por promesa (mínimo nro)
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
            pp.id                                                 as pp_id,
            pp.tipo                                               as tipo_neg,
            COALESCE(cc.entidad,'')                               as entidad,
            DATE_FORMAT(pp.created_at, '%Y-%m-%d %H:%i:%s')       as fecha,
            COALESCE(cc.nombre,'')                                as cliente,
            COALESCE(pp.telefono,'')                              as telefono,
            COALESCE(pp.dni, cc.numdoc, '')                       as nrodoc,
            COALESCE(us.name,'')                                  as negociador,
            COALESCE(pp.workflow_estado,'')                       as estado,
            COALESCE(po.operacion, pp.operacion, '')              as operacion,
            COALESCE(cc.moneda,'')                                as moneda,
            cc.deuda_total                                        as deuda_act,
            cc.deuda_capital                                      as capital_act,
            pp.nro_cuotas                                         as cuotas,

            CASE
              WHEN pp.tipo = 'cancelacion'
                   THEN DATE_FORMAT(pp.fecha_pago, '%Y-%m-%d')
              WHEN pp.tipo IN ('convenio','convenio_balon')
                   THEN DATE_FORMAT(fq.first_fecha, '%Y-%m-%d')
              ELSE DATE_FORMAT(COALESCE(fq.first_fecha, pp.fecha_pago), '%Y-%m-%d')
            END                                                   as fec_pag,

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
        if ($from) $qb->whereDate('pp.created_at', '>=', $from);
        if ($to)   $qb->whereDate('pp.created_at', '<=', $to);

        $estadoSel = array_values(array_filter(array_map('trim', $estadoSel)));
        if ($estadoSel) $qb->whereIn('pp.workflow_estado', $estadoSel);

        $tipoSel = array_values(array_filter(array_map('trim', $tipoSel)));
        if ($tipoSel) $qb->whereIn('pp.tipo', $tipoSel);

        $entidadSel = array_values(array_filter(array_map('trim', $entidadSel)));
        if ($entidadSel) $qb->whereIn('cc.entidad', $entidadSel);

        $q = trim((string)$q);
        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('pp.dni', 'like', "%{$q}%")
                  ->orWhere(DB::raw('COALESCE(po.operacion, pp.operacion)'), 'like', "%{$q}%")
                  ->orWhere('cc.nombre', 'like', "%{$q}%")
                  ->orWhere('cc.entidad', 'like', "%{$q}%")
                  ->orWhere('pp.telefono', 'like', "%{$q}%")
                  ->orWhere('pp.nota', 'like', "%{$q}%");
            });
        }

        $qb->orderByDesc('pp.created_at')->orderByDesc('pp.id');

        return $qb;
    }

    /**
     * Facets tipo "cascada":
     * - Estados: aplican filtros (tipo, entidad, q, fechas) pero NO estado
     * - Tipos:   aplican filtros (estado, entidad, q, fechas) pero NO tipo
     * - Entidad: aplican filtros (estado, tipo, q, fechas)   pero NO entidad
     */
    private function getFacets(
        string $from,
        string $to,
        array $estadoSel,
        array $tipoSel,
        array $entidadSel,
        string $q
    ): array {
        $norm = function ($arr) {
            return array_values(array_filter(array_map('trim', (array)$arr)));
        };

        $estadoSel  = $norm($estadoSel);
        $tipoSel    = $norm($tipoSel);
        $entidadSel = $norm($entidadSel);
        $q          = trim((string)$q);

        $key = 'rpt_pdp_facets_v1:' . md5(json_encode([
            'from'=>$from,'to'=>$to,'estado'=>$estadoSel,'tipo'=>$tipoSel,'entidad'=>$entidadSel,'q'=>$q
        ]));

        return Cache::remember($key, 300, function () use ($from,$to,$estadoSel,$tipoSel,$entidadSel,$q) {

            // Base común: fechas + búsqueda
            $base = $this->buildQuery($from, $to, [], [], [], $q);

            // ===== Estados (aplica tipo+entidad, ignora estado)
            $qEstados = clone $base;
            if ($tipoSel)    $qEstados->whereIn('pp.tipo', $tipoSel);
            if ($entidadSel) $qEstados->whereIn('cc.entidad', $entidadSel);

            $estados = (clone $qEstados)
                ->whereNotNull('pp.workflow_estado')->where('pp.workflow_estado','<>','')
                ->select('pp.workflow_estado')->distinct()->orderBy('pp.workflow_estado')
                ->pluck('pp.workflow_estado')->values()->all();

            // ===== Tipos (aplica estado+entidad, ignora tipo)
            $qTipos = clone $base;
            if ($estadoSel)  $qTipos->whereIn('pp.workflow_estado', $estadoSel);
            if ($entidadSel) $qTipos->whereIn('cc.entidad', $entidadSel);

            $tipos = (clone $qTipos)
                ->whereNotNull('pp.tipo')->where('pp.tipo','<>','')
                ->select('pp.tipo')->distinct()->orderBy('pp.tipo')
                ->pluck('pp.tipo')->values()->all();

            // ===== Entidades (aplica estado+tipo, ignora entidad)
            $qEnt = clone $base;
            if ($estadoSel) $qEnt->whereIn('pp.workflow_estado', $estadoSel);
            if ($tipoSel)   $qEnt->whereIn('pp.tipo', $tipoSel);

            $entidades = (clone $qEnt)
                ->whereNotNull('cc.entidad')->where('cc.entidad','<>','')
                ->select('cc.entidad')->distinct()->orderBy('cc.entidad')
                ->pluck('cc.entidad')->values()->all();

            return [$estados, $tipos, $entidades];
        });
    }
}
