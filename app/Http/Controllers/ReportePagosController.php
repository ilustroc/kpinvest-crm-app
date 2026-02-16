<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\PagoPropia as Pago;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReportePagosController extends Controller
{
    public function index(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to', $defaultTo);

        $gestorSel  = (array) $r->query('gestor', []);
        $cosechaSel = (array) $r->query('cosecha', []);
        $entidadSel = (array) $r->query('entidad', []);
        $q          = (string) $r->query('q', '');

        $rows = $this->buildQuery($from, $to, $gestorSel, $cosechaSel, $entidadSel, $q)
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        // Facets (listas) SOLO por rango de fechas
        [$gestores, $cosechas, $entidades] = $this->getFacets($from, $to, $gestorSel, $cosechaSel, $entidadSel, $q);

        if ($r->ajax()) {
            return view('reportes.pagos_table', compact('rows'));
        }

        return view('reportes.pagos', compact(
            'rows','from','to','q',
            'gestores','cosechas','entidades',
            'gestorSel','cosechaSel','entidadSel',
            'defaultFrom','defaultTo'
        ));
    }

    // Endpoint para refrescar listas según fechas
    public function facets(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to', $defaultTo);

        $gestorSel  = (array) $r->query('gestor', []);
        $cosechaSel = (array) $r->query('cosecha', []);
        $entidadSel = (array) $r->query('entidad', []);
        $q          = (string) $r->query('q', '');

        [$gestores, $cosechas, $entidades] = $this->getFacets($from, $to, $gestorSel, $cosechaSel, $entidadSel, $q);

        return response()->json([
            'gestores'  => $gestores,
            'cosechas'  => $cosechas,
            'entidades' => $entidades,
        ]);
    }

    private function normSel(array $arr): array
    {
        return array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
    }

    private function applyDateAndQ($qb, ?string $from, ?string $to, string $q): void
    {
        if ($from && $to) $qb->whereBetween('fecha', [$from, $to]);
        elseif ($from)    $qb->where('fecha', '>=', $from);
        elseif ($to)      $qb->where('fecha', '<=', $to);

        $q = trim($q);
        if ($q !== '') {
            $qb->where(function($qq) use ($q){
                if (ctype_digit($q)) {
                    $qq->where('dni', $q)->orWhere('dni','like',"%{$q}%");
                } else {
                    $qq->where('nombre_cliente','like',"%{$q}%");
                }
            });
        }
    }

    /**
     * Facets en cascada: cada lista se calcula con los otros filtros activos.
     */
    private function getFacets(string $from, string $to, array $gestorSel, array $cosechaSel, array $entidadSel, string $q): array
    {
        $gestorSel  = $this->normSel($gestorSel);
        $cosechaSel = $this->normSel($cosechaSel);
        $entidadSel = $this->normSel($entidadSel);

        $key = 'rpt_pagos_facets_v2:' . md5(json_encode([
            'from'=>$from,'to'=>$to,'g'=>$gestorSel,'c'=>$cosechaSel,'e'=>$entidadSel,'q'=>$q
        ]));

        return Cache::remember($key, 120, function() use ($from,$to,$gestorSel,$cosechaSel,$entidadSel,$q) {

            // 1) Gestores (aplica Entidad + Cosecha, pero NO gestor)
            $qbG = Pago::query();
            $this->applyDateAndQ($qbG, $from, $to, $q);
            if ($entidadSel) $qbG->whereIn('entidad', $entidadSel);
            if ($cosechaSel) $qbG->whereIn('cosecha', $cosechaSel);

            $gestores = $qbG->whereNotNull('gestor')->where('gestor','<>','')
                ->select('gestor')->distinct()->orderBy('gestor')
                ->pluck('gestor')->values()->all();

            // 2) Cosechas (aplica Gestor + Entidad, pero NO cosecha)
            $qbC = Pago::query();
            $this->applyDateAndQ($qbC, $from, $to, $q);
            if ($gestorSel)  $qbC->whereIn('gestor', $gestorSel);
            if ($entidadSel) $qbC->whereIn('entidad', $entidadSel);

            $cosechas = $qbC->whereNotNull('cosecha')->where('cosecha','<>','')
                ->select('cosecha')->distinct()->orderBy('cosecha')
                ->pluck('cosecha')->values()->all();

            // 3) Entidades (aplica Gestor + Cosecha, pero NO entidad)
            $qbE = Pago::query();
            $this->applyDateAndQ($qbE, $from, $to, $q);
            if ($gestorSel)  $qbE->whereIn('gestor', $gestorSel);
            if ($cosechaSel) $qbE->whereIn('cosecha', $cosechaSel);

            $entidades = $qbE->whereNotNull('entidad')->where('entidad','<>','')
                ->select('entidad')->distinct()->orderBy('entidad')
                ->pluck('entidad')->values()->all();

            return [$gestores, $cosechas, $entidades];
        });
    }

    public function export(Request $r)
    {
        $now = now();
        $defaultFrom = $now->copy()->startOfMonth()->toDateString();
        $defaultTo   = $now->copy()->endOfMonth()->toDateString();

        $from = $r->query('from', $defaultFrom);
        $to   = $r->query('to', $defaultTo);

        $gestorSel  = (array) $r->query('gestor', []);
        $cosechaSel = (array) $r->query('cosecha', []);
        $entidadSel = (array) $r->query('entidad', []);
        $q          = (string) $r->query('q', '');

        $query = $this->buildQuery($from, $to, $gestorSel, $cosechaSel, $entidadSel, $q);
        $filename = 'pagos_' . now()->format('Ymd_His') . '.xlsx';

        $xlsx  = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row   = 1;

        $headers = [
            'Fecha','DNI','Nombre','Operacion','Monto','Agente','Cosecha','Cuenta_Recaudo','Entidad Financiera'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $fmtDate = static function($v){
            if ($v instanceof \DateTimeInterface) return $v->format('d/m/Y');
            if (empty($v)) return null;
            try { return Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable) { return null; }
        };

        (clone $query)->orderBy('id')->chunkById(1000, function ($items) use (&$row, $sheet, $fmtDate) {
            foreach ($items as $r) {
                $sheet->fromArray([
                    $fmtDate($r->fecha),
                    (string)$r->dni,
                    $r->nombre_cliente,
                    (string)$r->operacion,
                    (float)$r->monto_pagado,
                    $r->gestor,
                    $r->cosecha,
                    $r->cuenta_recaudo,
                    $r->entidad,
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("B{$row}", (string)$r->dni, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", (string)$r->operacion, DataType::TYPE_STRING);
                $row++;
            }
        }, 'id');

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c = 'A'; $c <= $lastCol; $c++) $sheet->getColumnDimension($c)->setAutoSize(true);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        (new Xlsx($xlsx))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    private function buildQuery(?string $from, ?string $to, array $gestorSel, array $cosechaSel, array $entidadSel, ?string $q)
    {
        $qb = Pago::query();

        if ($from && $to)      $qb->whereBetween('fecha', [$from, $to]);
        else if ($from)        $qb->where('fecha', '>=', $from);
        else if ($to)          $qb->where('fecha', '<=', $to);

        $gestorSel = array_values(array_filter(array_map('trim', $gestorSel)));
        if ($gestorSel) $qb->whereIn('gestor', $gestorSel);

        $cosechaSel = array_values(array_filter(array_map('trim', $cosechaSel)));
        if ($cosechaSel) $qb->whereIn('cosecha', $cosechaSel);

        $entidadSel = array_values(array_filter(array_map('trim', $entidadSel)));
        if ($entidadSel) $qb->whereIn('entidad', $entidadSel);

        $q = trim((string)$q);
        if ($q !== '') {
            $qb->where(function($qq) use ($q){
                if (ctype_digit($q)) {
                    $qq->where('dni', $q)->orWhere('dni','like',"%{$q}%");
                } else {
                    $qq->where('nombre_cliente','like',"%{$q}%");
                }
            });
        }

        return $qb;
    }
}
