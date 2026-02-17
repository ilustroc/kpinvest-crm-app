<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CnaReportService;
use App\Exports\CnaExport;

class ReporteCnaController extends Controller
{
    protected $service;

    public function __construct(CnaReportService $service) { $this->service = $service; }

    public function index(Request $r)
    {
        $filters = $this->parseFilters($r);
        
        $rows = $this->service->getFilteredQuery($filters)
            ->orderByDesc('cna_fec')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $facets = $this->service->getFacets($filters);

        if ($r->ajax()) return view('reportes.cna_table', compact('rows'));

        return view('reportes.cna', array_merge(compact('rows'), $filters, $facets));
    }

    public function facets(Request $r)
    {
        return response()->json($this->service->getFacets($this->parseFilters($r)));
    }

    public function export(Request $r)
    {
        set_time_limit(300);
        $query = $this->service->getFilteredQuery($this->parseFilters($r));
        $path = (new CnaExport($query))->export();
        $filename = 'reporte_cna_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function parseFilters(Request $r): array
    {
        return [
            'from'        => $r->query('from', now()->startOfMonth()->toDateString()),
            'to'          => $r->query('to', now()->toDateString()),
            'defaultFrom' => now()->startOfMonth()->toDateString(),
            'defaultTo'   => now()->toDateString(),
            'estado'      => (array)$r->query('estado', []),
            'estadoSel'   => (array)$r->query('estado', []),
            'gestor'      => (array)$r->query('gestor', []),
            'gestorSel'   => (array)$r->query('gestor', []),
            'entidad'     => (array)$r->query('entidad', []),
            'entidadSel'  => (array)$r->query('entidad', []),
        ];
    }
}