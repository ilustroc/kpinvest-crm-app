<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use App\Services\Autorizacion\AutorizacionInboxService;
use App\Services\PromesaWorkflowService;
use App\Support\Traits\HasTeamVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutorizacionController extends Controller
{
    use HasTeamVisibility;

    private AutorizacionInboxService $inbox;

    public function __construct(AutorizacionInboxService $inbox)
    {
        $this->inbox = $inbox;
    }

    // Lista de promesas de pago para autorización (supervisor/admin).
    public function index(Request $req)
    {
        $user    = Auth::user();
        $q       = trim((string)($req->q ?? ''));
        $status  = $req->status;
        $teamIds = $this->teamUserIds($user);

        $data = $this->inbox->buildIndexData(
            teamIds: $teamIds,
            role: (string)$user->role,
            q: $q,
            status: $status
        );

        // mantiene querystring en la paginación de CNA
        $data['cnaRows']->withQueryString();

        return view('autorizacion.index', $data);
    }

    /**
     * SUPERVISOR: Pre-aprobar promesa.
     */
    public function preaprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        try {
            PromesaWorkflowService::preaprobar($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa pre-aprobada.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * SUPERVISOR: Rechazar promesa (estado Pendiente).
     */
    public function rechazarSup(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('supervisor');

        try {
            PromesaWorkflowService::rechazarSup($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa rechazada por supervisor.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * ADMIN: Aprobar promesa (estado Pre-aprobada).
     */
    public function aprobar(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        try {
            PromesaWorkflowService::aprobar($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa APROBADA.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * ADMIN: Rechazar promesa (estado Pre-aprobada).
     */
    public function rechazarAdmin(Request $req, PromesaPago $promesa)
    {
        $this->authorizeActionFor('administrador');

        try {
            PromesaWorkflowService::rechazarAdmin($promesa, $req->input('nota_estado'));
            return back()->with('ok', 'Promesa rechazada por administrador.');
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * Autorización por rol (admin/sistemas o supervisor).
     */
    private function authorizeActionFor(string $role)
    {
        $user = Auth::user();
        if (!in_array(strtolower($user->role), [$role, 'sistemas'])) {
            abort(403, 'No autorizado.');
        }
    }
}
