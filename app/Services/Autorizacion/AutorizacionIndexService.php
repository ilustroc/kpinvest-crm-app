<?php

namespace App\Services\Autorizacion;

use App\Models\AsignarCliente;
use App\Models\User;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\Cna\CnaQueryService;
use App\Services\Promesa\PromesaQueryService;
use App\Support\Authorization\Roles;
use App\ViewModels\Autorizacion\AutorizacionIndexViewModel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

        $asignacionFilters = [
            'estado' => trim((string) ($request->input('asig_estado', 'pendiente'))),
            'q' => trim((string) ($request->input('asig_q', ''))),
            'numdoc' => trim((string) ($request->input('asig_numdoc', ''))),
            'operacion' => trim((string) ($request->input('asig_operacion', ''))),
            'solicitante_user_id' => (int) ($request->input('asig_solicitante', 0)),
            'asesor_destino_user_id' => (int) ($request->input('asig_asesor', 0)),
            'origen' => trim((string) ($request->input('asig_origen', ''))),
            'desde' => trim((string) ($request->input('asig_desde', ''))),
            'hasta' => trim((string) ($request->input('asig_hasta', ''))),
        ];

        $hasLinearHistory = Schema::hasTable('asignar_clientes') && Schema::hasColumn('asignar_clientes', 'estado');

        $asignacionRows = $hasLinearHistory
            ? $this->assignments->paginateHistory($asignacionFilters, 15, 'asignaciones_page')
            : new LengthAwarePaginator([], 0, 15, 1, ['pageName' => 'asignaciones_page']);

        if ($hasLinearHistory) {
            $this->enrichAsignacionPageItems(collect($asignacionRows->items()));
        }

        $asignacionUsers = Schema::hasTable('users')
            ? User::query()->select(['id', 'name', 'role'])->orderBy('name')->get()
            : collect();

        return new AutorizacionIndexViewModel(
            rows: $promesaRows,
            cnaRows: $cnaRows,
            prodByOp: $prodByOp,
            q: $query,
            status: $status,
            isSupervisor: $role === Roles::SUPERVISOR,
            pagosByDni: $pagosByDni,
            asignacionRows: $asignacionRows,
            asignacionFilters: $asignacionFilters,
            asignacionUsers: $asignacionUsers,
            canReviewPromesasCna: $canReviewPromesasCna,
            canReviewAsignaciones: $canReviewAsignaciones,
            roleLabel: $roleLabel,
        );
    }

    /**
     * Batch-enrich paginated assignment rows with current active advisor and operation history
     * without N+1 queries.
     *
     * @param Collection<int, AsignarCliente> $items
     */
    private function enrichAsignacionPageItems(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $dummyAccounts = $items->map(fn (AsignarCliente $a) => (object) [
            'id' => $a->cliente_cuenta_id ?: ($a->clienteCuenta?->id ?? null),
            'numdoc' => $a->numdoc,
            'operacion' => $a->operacion,
        ]);

        $resolvedActive = $this->assignments->resolveActiveAssignmentsForAccounts($dummyAccounts);

        $numdocs = $items->pluck('numdoc')->filter()->unique()->values();
        $operaciones = $items->pluck('operacion')->filter()->unique()->values();

        $historyRows = AsignarCliente::query()
            ->with(['solicitante:id,name', 'asesorDestino:id,name', 'aprobadoPor:id,name'])
            ->whereIn('numdoc', $numdocs->all())
            ->whereIn('operacion', $operaciones->all())
            ->orderByRaw("COALESCE(fecha_solicitud, '1970-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->get();

        $historyByPair = $historyRows->groupBy(fn (AsignarCliente $h) => trim((string) $h->numdoc) . '|' . trim((string) $h->operacion));

        foreach ($items as $item) {
            $cid = $item->cliente_cuenta_id ?: ($item->clienteCuenta?->id ?? 0);
            $vigente = ($cid > 0 && isset($resolvedActive['by_cuenta_id'][$cid]))
                ? $resolvedActive['by_cuenta_id'][$cid]
                : ($resolvedActive['by_operation'][$item->operacion] ?? null);

            $item->asesor_vigente_actual = $vigente?->asesor_nombre ?? 'Sin asignar';

            $pairKey = trim((string) $item->numdoc) . '|' . trim((string) $item->operacion);
            $pairHistory = ($historyByPair->get($pairKey) ?? collect())->take(15)->map(fn (AsignarCliente $h) => [
                'id' => $h->id,
                'fecha_solicitud' => $h->fecha_solicitud ? $h->fecha_solicitud->format('d/m/Y H:i') : 'Histórico',
                'solicitante' => $h->solicitante_nombre ?? 'Sistema / Histórico',
                'asesor' => $h->asesor_nombre ?? 'Sin asignar',
                'motivo' => $h->motivo ?: 'Asignación histórica',
                'estado' => $h->estado,
                'origen' => $h->origen,
                'aprobado_por' => $h->aprobador_nombre,
                'fecha_aprobacion' => $h->fecha_aprobacion ? $h->fecha_aprobacion->format('d/m/Y H:i') : null,
                'observacion_resolucion' => $h->observacion_resolucion,
            ])->values()->all();

            $item->historial_operacion_json = $pairHistory;
        }
    }
}
