<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PromiseReportService;
use App\Exports\PromiseExport;

class ReportePromesasController extends Controller
{
    protected $service;

    public function __construct(PromiseReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        ini_set('max_execution_time', 120); // Prevenir timeout en consultas pesadas
        $filters = $this->parseFilters($request);
        
        $rows = $this->service->getFilteredQuery($filters)
            ->orderByDesc('pp_created_at')
            ->paginate(25)
            ->withQueryString();

        $facets = $this->service->getFacets($filters);

        if ($request->ajax() || $request->boolean('partial')) {
            return view('reportes.pdp_table', compact('rows'));
        }

        return view('reportes.pdp', array_merge(
            compact('rows'),
            $filters,
            $facets,
            ['defaultFrom' => now()->startOfMonth()->toDateString(), 'defaultTo' => now()->endOfMonth()->toDateString()]
        ));
    }

    public function facets(Request $request)
    {
        return response()->json($this->service->getFacets($this->parseFilters($request)));
    }

    public function export(Request $request)
    {
        set_time_limit(300); // Dar más tiempo para el Excel masivo
        $query = $this->service->getFilteredQuery($this->parseFilters($request));
        $path = (new PromiseExport($query))->export();

        return response()->download($path, 'promesas_'.now()->format('Ymd_His').'.xlsx')
                         ->deleteFileAfterSend(true);
    }

    private function parseFilters(Request $r): array
    {
        return [
            'from'       => $r->query('from', now()->startOfMonth()->toDateString()),
            'to'         => $r->query('to', now()->endOfMonth()->toDateString()),
            'estado'     => (array) $r->query('estado', []),
            'estadoSel'  => (array) $r->query('estado', []), // Compatibilidad Blade
            'tipoSel'    => (array) ($r->query('tipo_neg', $r->query('tipo', []))),
            'tipo'       => (array) ($r->query('tipo_neg', $r->query('tipo', []))),
            'entidad'    => (array) $r->query('entidad', []),
            'entidadSel' => (array) $r->query('entidad', []),
            'q'          => (string) $r->query('q', ''),
        ];
    }
}