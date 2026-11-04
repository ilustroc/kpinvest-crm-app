<?php

namespace App\Services\Autorizacion;

use App\Models\AsignarCliente;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\Cna\CnaQueryService;
use App\Services\Promesa\PromesaQueryService;
use App\Support\Authorization\Roles;
use App\ViewModels\Autorizacion\AutorizacionIndexViewModel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AutorizacionIndexService
{
    public function __construct(
        private readonly PromesaQueryService $promesas,
        private readonly CnaQueryService $cnas,
        private readonly ClienteAssignmentService $assignments,
    ) {
    }

    public function fromRequest(Request $request): AutorizacionIndexViewModel
    {
        $user = $request->user();
        $role = Roles::normalize($user->role);
        $canReviewPromesasCna = Roles::canReviewWorkflow($user);
        $canReviewAsignaciones = Roles::canReviewAsignaciones($user);

        $roleLabel = match ($role) {
            Roles::SUPERVISOR => 'Supervisor',
            Roles::SOPORTE => 'Soporte',
            default => 'Administrador',
        };

        $query = trim((string) ($request->input('q') ?? ''));
        $status = $request->input('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        if ($canReviewPromesasCna) {
            $promesaRows = $this->promesas->authorizationRows($user, $query, $status);
            $cnaData = $this->cnas->authorizationData($user, $query, $status);
            $cnaRows = $cnaData['cnaRows'];
            $prodByOp = $cnaData['prodByOp'];
            $pagosByDni = $cnaData['pagosByDni'] ?? [];
        } else {
            $promesaRows = collect();
            $cnaRows = new LengthAwarePaginator([], 0, 10, 1, ['pageName' => 'page_cna']);
            $prodByOp = [];
            $pagosByDni = [];
        }

        $hasLinearHistory = Schema::hasTable('asignar_clientes') && Schema::hasColumn('asignar_clientes', 'estado');

        $asignacionRows = $hasLinearHistory
            ? $this->assignments->paginateHistory(['estado' => AsignarCliente::ESTADO_PENDIENTE], 15, 'asignaciones_page')
            : new LengthAwarePaginator([], 0, 15, 1, ['pageName' => 'asignaciones_page']);

        return new AutorizacionIndexViewModel(
            rows: $promesaRows,
            cnaRows: $cnaRows,
            prodByOp: $prodByOp,
            q: $query,
            status: $status,
            isSupervisor: $role === Roles::SUPERVISOR,
            pagosByDni: $pagosByDni,
            asignacionRows: $asignacionRows,
            canReviewPromesasCna: $canReviewPromesasCna,
            canReviewAsignaciones: $canReviewAsignaciones,
            roleLabel: $roleLabel,
        );
    }

}
