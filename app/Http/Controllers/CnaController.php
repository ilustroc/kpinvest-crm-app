<?php

namespace App\Http\Controllers;

use App\Models\CnaSolicitud;
use App\Services\Cna\CnaDocumentService;
use App\Services\Cna\CnaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CnaController extends Controller
{
    public function __construct(
        private CnaService $cnaSvc,
        private CnaDocumentService $docSvc,
    ) {}

    // =========================================================
    // CREAR SOLICITUD
    // =========================================================
    public function store(Request $request, string $dni)
    {
        try {
            $data = $request->validate([
                'titular'               => ['nullable','string','max:150'],
                'nota'                  => ['nullable','string','max:1000'],
                'observacion'           => ['nullable','string','max:1000'],
                'fecha_pago_realizado'  => ['required','date'],
                'monto_pagado'          => ['required','numeric','min:0.01','max:999999999.99'],
                'operaciones'           => ['required','array','min:1'],
                'operaciones.*'         => ['string','max:50'],
                'cuenta'                => ['nullable','string','max:50'], // compat (se ignora)
            ], [], [
                'fecha_pago_realizado'  => 'fecha de pago realizado',
                'monto_pagado'          => 'monto pagado',
                'operaciones'           => 'operaciones',
            ]);

            $dni = trim($dni);

            // Crea solicitud (correlativo + workflow auto si admin/sistemas)
            $solicitud = $this->cnaSvc->create($dni, $data);

            // Si es admin/sistemas => aprobada automática + generar docs, NO correos
            if ($this->cnaSvc->isAdminAuto()) {
                $this->docSvc->generateOutputsFromTemplate($solicitud);

                return redirect()->route('clientes.show', $dni)
                    ->with('ok', "CNA APROBADA automáticamente. N.º {$solicitud->nro_carta}");
            }

            // Caso normal => notificar pendiente
            $this->cnaSvc->notifyPendiente($solicitud);

            return redirect()->route('clientes.show', $dni)
                ->with('ok', "Solicitud de CNA enviada. N.º {$solicitud->nro_carta}");

        } catch (\Throwable $e) {
            Log::error('CNA store error', [
                'dni'  => $dni,
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->route('clientes.show', $dni)
                ->withErrors('No se pudo guardar la CNA: '.$e->getMessage())
                ->withInput();
        }
    }

    // =========================================================
    // FLUJO DE APROBACIÓN
    // =========================================================
    public function preaprobar(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('supervisor');

        try {
            $this->cnaSvc->preaprobar($cna, $request->input('nota_estado'));
            return back()->with('ok', 'CNA pre-aprobada.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    public function rechazarSup(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('supervisor');

        try {
            $this->cnaSvc->rechazarSup($cna, $request->input('nota_estado'));
            return back()->with('ok', 'CNA rechazada por supervisor.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    public function aprobar(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');

        try {
            // workflow
            $this->cnaSvc->aprobar($cna, $request->input('nota_estado'));

            // Genera DOCX + PDF
            $this->docSvc->generateOutputsFromTemplate($cna->fresh());

            return back()->with('ok', 'CNA aprobada y archivos generados.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    public function rechazarAdmin(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');

        try {
            $this->cnaSvc->rechazarAdmin($cna, $request->input('nota_estado'));
            return back()->with('ok', 'CNA rechazada por administrador.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    // =========================================================
    // DESCARGAS
    // =========================================================
    public function pdf(int $id)
    {
        $cna = CnaSolicitud::findOrFail($id);
        if ($cna->workflow_estado !== 'aprobada') abort(403, 'Solo disponible para CNA aprobadas.');
        return $this->downloadPreferred($cna, 'pdf');
    }

    public function docx(int $id)
    {
        $cna = CnaSolicitud::findOrFail($id);
        if ($cna->workflow_estado !== 'aprobada') abort(403, 'Solo disponible para CNA aprobadas.');
        return $this->downloadPreferred($cna, 'docx');
    }

    private function downloadPreferred(CnaSolicitud $cna, string $primary)
    {
        // 1) Si guardaste paths en DB, prioriza eso
        $dbPdf  = $cna->pdf_path  ? (string)$cna->pdf_path  : null;
        $dbDocx = $cna->docx_path ? (string)$cna->docx_path : null;

        // 2) Fallback al nombre estándar
        $base = sprintf('CNA %s - %s', $cna->nro_carta, $cna->dni);
        $stdPdf  = "cna/pdfs/{$base}.pdf";
        $stdDocx = "cna/docx/{$base}.docx";

        $paths = [
            'pdf'  => ['rels' => array_values(array_filter([$dbPdf,  $stdPdf ])),  'name' => "{$base}.pdf"],
            'docx' => ['rels' => array_values(array_filter([$dbDocx, $stdDocx])), 'name' => "{$base}.docx"],
        ];

        $first  = $paths[$primary];
        $second = $paths[$primary === 'pdf' ? 'docx' : 'pdf'];

        foreach ($first['rels'] as $rel) {
            if (Storage::exists($rel)) return Storage::download($rel, $first['name']);
        }
        foreach ($second['rels'] as $rel) {
            if (Storage::exists($rel)) return Storage::download($rel, $second['name']);
        }

        abort(404, 'Archivo no encontrado.');
    }

    // =========================================================
    // HELPERS (SEGURIDAD)
    // =========================================================
    private function authorizeRole(string $role): void
    {
        $user = Auth::user();
        if (!$user || !in_array(strtolower((string)$user->role), [$role, 'sistemas'], true)) {
            abort(403, 'No autorizado.');
        }
    }
}