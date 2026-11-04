<?php

namespace App\Http\Controllers;

use App\Exports\AssignmentExport;
use App\Services\AssignmentReportService;
use Illuminate\Http\Request;

class ReporteAsignacionesController extends Controller
{
    public function __construct(private readonly AssignmentReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $rows = $this->reports->filteredQuery($filters)->paginate(25)->withQueryString();

        return view('reportes.asignaciones', compact('rows', 'filters'));
    }

    public function export(Request $request)
    {
        $path = (new AssignmentExport($this->reports->filteredQuery($this->filters($request))))->export();

        return response()->download($path, 'asignaciones_'.now()->format('Ymd_His').'.xlsx')->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        return $request->only(['from', 'to', 'numdoc', 'operacion', 'asesor', 'estado', 'origen']);
    }
}
