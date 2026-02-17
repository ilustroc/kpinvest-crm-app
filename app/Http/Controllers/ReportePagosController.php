<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentReportService;
use App\Exports\PaymentExport;

class ReportePagosController extends Controller
{
    protected $service;

    public function __construct(PaymentReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $this->parseFilters($request);
        
        $rows = $this->service->getFilteredQuery($filters)
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        $facets = $this->service->getFacets($filters);

        if ($request->ajax()) {
            return view('reportes.pagos_table', compact('rows'));
        }

        return view('reportes.pagos', array_merge(
            compact('rows'),
            $filters,
            $facets,
            [
                'defaultFrom' => now()->startOfMonth()->toDateString(),
                'defaultTo'   => now()->endOfMonth()->toDateString()
            ]
        ));
    }

    public function facets(Request $request)
    {
        return response()->json($this->service->getFacets($this->parseFilters($request)));
    }

    public function export(Request $request)
    {
        set_time_limit(300); // Dar tiempo al servidor para generar el Excel masivo
        
        $query = $this->service->getFilteredQuery($this->parseFilters($request));
        $path = (new PaymentExport($query))->export();
        $filename = 'pagos_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function parseFilters(Request $r): array
    {
        return [
            'from'       => $r->query('from', now()->startOfMonth()->toDateString()),
            'to'         => $r->query('to', now()->endOfMonth()->toDateString()),
            'gestor'     => (array) $r->query('gestor', []),
            'gestorSel'  => (array) $r->query('gestor', []),
            'cosecha'    => (array) $r->query('cosecha', []),
            'cosechaSel' => (array) $r->query('cosecha', []),
            'entidad'    => (array) $r->query('entidad', []),
            'entidadSel' => (array) $r->query('entidad', []),
            'q'          => (string) $r->query('q', ''),
        ];
    }
}