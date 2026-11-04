<?php

namespace App\Services\Asignacion;

use App\Models\AsignarCliente;
use App\Models\ClienteCuenta;
use App\Models\User;
use App\Services\Panel\PanelDashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClienteAssignmentService
{
    /**
     * Resolve the active ("vigente") approved assignment for each account in the collection.
     *
     * Rule:
     * 1. Filter estado = 'aprobada'.
     * 2. Group by real account identity (cliente_cuenta_id, or numdoc + operacion when unambiguous).
     * 3. Pick highest fecha_solicitud.
     * 4. Tie-breaker: highest id.
     *
     * @param Collection<int, ClienteCuenta|object> $cuentas
     * @return array{
     *   by_cuenta_id: array<int, AsignarCliente>,
     *   by_operation: array<string, AsignarCliente>,
     *   advisor_name_by_operation: array<string, string|null>,
     *   advisor_name_by_cuenta_id: array<int, string|null>,
     *   ambiguous_operations: array<string, bool>
     * }
     */
    public function resolveActiveAssignmentsForAccounts(Collection $cuentas): array
    {
        if ($cuentas->isEmpty()) {
            return [
                'by_cuenta_id' => [],
                'by_operation' => [],
                'advisor_name_by_operation' => [],
                'advisor_name_by_cuenta_id' => [],
                'ambiguous_operations' => [],
            ];
        }

        $numdocs = $cuentas
            ->pluck('numdoc')
            ->filter(fn ($v) => $v !== null && (string) $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();

        $operaciones = $cuentas
            ->pluck('operacion')
            ->filter(fn ($v) => $v !== null && (string) $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();

        $cuentaIds = $cuentas
            ->pluck('id')
            ->filter(fn ($v) => $v !== null && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        // Detect ambiguous (numdoc, operacion) pairs in clientes_cuentas
        $pairCounts = [];
        foreach ($cuentas as $cuenta) {
            $pairKey = $this->pairKey((string) ($cuenta->numdoc ?? ''), (string) ($cuenta->operacion ?? ''));
            $pairCounts[$pairKey] = ($pairCounts[$pairKey] ?? 0) + 1;
        }

        // Also check DB if multiple accounts exist for the same (numdoc, operacion) outside the passed collection
        if ($numdocs->isNotEmpty() && $operaciones->isNotEmpty()) {
            $dbCounts = ClienteCuenta::query()
                ->select('numdoc', 'operacion', DB::raw('COUNT(*) as total'))
                ->whereIn('numdoc', $numdocs->all())
                ->whereIn('operacion', $operaciones->all())
                ->groupBy('numdoc', 'operacion')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($dbCounts as $row) {
                $pairKey = $this->pairKey((string) $row->numdoc, (string) $row->operacion);
                $pairCounts[$pairKey] = max($pairCounts[$pairKey] ?? 0, (int) $row->total);
            }
        }

        $ambiguousPairs = [];
        $ambiguousOperations = [];
        foreach ($pairCounts as $pairKey => $count) {
            if ($count > 1) {
                $ambiguousPairs[$pairKey] = true;
                [, $op] = explode('|', $pairKey, 2);
                $ambiguousOperations[$op] = true;
            }
        }

        $hasLinearHistory = Schema::hasColumn('asignar_clientes', 'estado');

        // Query approved assignments matching either cliente_cuenta_id or (numdoc, operacion)
        $approvedRecords = AsignarCliente::query()
            ->when($hasLinearHistory, function (Builder $q): void {
                $q->with(['asesorDestino:id,name,email,role', 'solicitante:id,name,email,role', 'aprobadoPor:id,name,email,role'])
                    ->where('estado', AsignarCliente::ESTADO_APROBADA);
            })
            ->where(function (Builder $query) use ($cuentaIds, $numdocs, $operaciones, $hasLinearHistory): void {
                if ($hasLinearHistory && $cuentaIds->isNotEmpty()) {
                    $query->whereIn('cliente_cuenta_id', $cuentaIds->all());
                }
                if ($numdocs->isNotEmpty() && $operaciones->isNotEmpty()) {
                    $query->orWhere(function (Builder $sub) use ($numdocs, $operaciones): void {
                        $sub->whereIn('numdoc', $numdocs->all())
                            ->whereIn('operacion', $operaciones->all());
                    });
                }
            })
            ->get();

        // Sort all approved records by the canonical rule:
        // 1. Highest fecha_solicitud (NULL treated as '1970-01-01 00:00:00')
        // 2. Highest id (tie-breaker)
        $sortedApproved = $approvedRecords->sort(function (AsignarCliente $a, AsignarCliente $b) use ($hasLinearHistory): int {
            if ($hasLinearHistory) {
                $tsA = $a->fecha_solicitud ? $a->fecha_solicitud->format('Y-m-d H:i:s.u') : '1970-01-01 00:00:00.000000';
                $tsB = $b->fecha_solicitud ? $b->fecha_solicitud->format('Y-m-d H:i:s.u') : '1970-01-01 00:00:00.000000';

                if ($tsA !== $tsB) {
                    return $tsB <=> $tsA;
                }
            }

            return ((int) $b->id) <=> ((int) $a->id);
        })->values();

        // Index winners by cliente_cuenta_id and by unambiguous (numdoc, operacion)
        $winnerByCuentaId = [];
        $winnerByPairKey = [];

        foreach ($sortedApproved as $record) {
            $cid = $record->cliente_cuenta_id ? (int) $record->cliente_cuenta_id : null;
            $pairKey = $this->pairKey((string) $record->numdoc, (string) $record->operacion);

            if ($cid !== null && !isset($winnerByCuentaId[$cid])) {
                $winnerByCuentaId[$cid] = $record;
            }

            // A record can resolve by (numdoc, operacion) only if (numdoc, operacion) is not ambiguous
            if (!isset($ambiguousPairs[$pairKey]) && !isset($winnerByPairKey[$pairKey])) {
                $winnerByPairKey[$pairKey] = $record;
            }
        }

        $byCuentaId = [];
        $byOperation = [];
        $advisorNameByOperation = [];
        $advisorNameByCuentaId = [];

        foreach ($cuentas as $cuenta) {
            $cid = isset($cuenta->id) ? (int) $cuenta->id : 0;
            $numdoc = (string) ($cuenta->numdoc ?? '');
            $operacion = (string) ($cuenta->operacion ?? '');
            $pairKey = $this->pairKey($numdoc, $operacion);
            $isAmbiguous = isset($ambiguousPairs[$pairKey]);

            $candidates = [];
            if ($cid > 0 && isset($winnerByCuentaId[$cid])) {
                $candidates[] = $winnerByCuentaId[$cid];
            }
            if (!$isAmbiguous && isset($winnerByPairKey[$pairKey])) {
                $candidates[] = $winnerByPairKey[$pairKey];
            }

            $winner = $this->pickVigenteRecord($candidates);

            if ($winner !== null) {
                if ($cid > 0) {
                    $byCuentaId[$cid] = $winner;
                    $advisorNameByCuentaId[$cid] = $winner->asesor_nombre;
                }
                if (!$isAmbiguous && $operacion !== '') {
                    $byOperation[$operacion] = $winner;
                    $advisorNameByOperation[$operacion] = $winner->asesor_nombre;
                }
            } else {
                if ($cid > 0) {
                    $advisorNameByCuentaId[$cid] = null;
                }
                if ($operacion !== '' && !array_key_exists($operacion, $advisorNameByOperation)) {
                    $advisorNameByOperation[$operacion] = null;
                }
            }
        }

        return [
            'by_cuenta_id' => $byCuentaId,
            'by_operation' => $byOperation,
            'advisor_name_by_operation' => $advisorNameByOperation,
            'advisor_name_by_cuenta_id' => $advisorNameByCuentaId,
            'ambiguous_operations' => $ambiguousOperations,
        ];
    }

    /**
     * Resolve active advisor names keyed by operation for a single numdoc.
     *
     * @param Collection<int, ClienteCuenta>|null $cuentas
     * @return Collection<string, string|null>
     */
    public function advisorsByOperation(string $numdoc, ?Collection $cuentas = null): Collection
    {
        $cuentas = $cuentas ?? ClienteCuenta::query()
            ->select(['id', 'numdoc', 'operacion', 'entidad', 'producto'])
            ->where('numdoc', $numdoc)
            ->get();

        if ($cuentas->isEmpty()) {
            return collect();
        }

        $resolved = $this->resolveActiveAssignmentsForAccounts($cuentas);

        return collect($resolved['advisor_name_by_operation']);
    }

    /**
     * Resolve the single active ("vigente") assignment for a specific account.
     */
    public function resolveActiveForAccount(string $numdoc, string $operacion, ?int $clienteCuentaId = null): ?AsignarCliente
    {
        $dummy = (object) [
            'id' => $clienteCuentaId,
            'numdoc' => $numdoc,
            'operacion' => $operacion,
        ];

        $resolved = $this->resolveActiveAssignmentsForAccounts(collect([$dummy]));

        if ($clienteCuentaId !== null && isset($resolved['by_cuenta_id'][$clienteCuentaId])) {
            return $resolved['by_cuenta_id'][$clienteCuentaId];
        }

        return $resolved['by_operation'][$operacion] ?? null;
    }

    /**
     * Create manual assignment request(s) from Ficha 360°.
     *
     * @param array<int, string> $operaciones
     * @return Collection<int, AsignarCliente>
     */
    public function requestManualAssignments(User $actor, string $numdoc, array $operaciones, string $motivo): Collection
    {
        Gate::forUser($actor)->authorize('create-cliente-asignacion');

        $cleanMotivo = trim($motivo);
        if ($cleanMotivo === '') {
            throw ValidationException::withMessages([
                'motivo' => ['El motivo de la solicitud de asignación es obligatorio.'],
            ]);
        }

        $normalizedOps = collect($operaciones)
            ->map(fn ($op) => trim((string) $op))
            ->filter(fn (string $op) => $op !== '')
            ->unique()
            ->values();

        if ($normalizedOps->isEmpty()) {
            throw ValidationException::withMessages([
                'operaciones' => ['Debes seleccionar al menos una operación o cuenta para solicitar la asignación.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $numdoc, $normalizedOps, $cleanMotivo): Collection {
            $cuentas = ClienteCuenta::query()
                ->where('numdoc', $numdoc)
                ->whereIn('operacion', $normalizedOps->all())
                ->lockForUpdate()
                ->get();

            $cuentasByOp = $cuentas->groupBy(fn (ClienteCuenta $c) => (string) $c->operacion);

            $missingOps = [];
            $ambiguousOps = [];
            $resolvedCuentas = [];

            foreach ($normalizedOps as $op) {
                $group = $cuentasByOp->get($op);
                if (!$group || $group->isEmpty()) {
                    $missingOps[] = $op;
                    continue;
                }
                if ($group->count() > 1) {
                    $ambiguousOps[] = $op;
                    continue;
                }
                $resolvedCuentas[] = $group->first();
            }

            if (!empty($missingOps)) {
                throw ValidationException::withMessages([
                    'operaciones' => [
                        'Las siguientes operaciones no pertenecen al cliente (' . $numdoc . '): ' . implode(', ', $missingOps) . '.',
                    ],
                ]);
            }

            if (!empty($ambiguousOps)) {
                throw ValidationException::withMessages([
                    'operaciones' => [
                        'Las siguientes operaciones presentan ambigüedad en clientes_cuentas (múltiples cuentas con el mismo documento y operación): ' . implode(', ', $ambiguousOps) . '.',
                    ],
                ]);
            }

            // Lock existing pending requests for this client & advisor to avoid race-condition duplicates
            $existingPendientes = AsignarCliente::query()
                ->where('numdoc', $numdoc)
                ->whereIn('operacion', $normalizedOps->all())
                ->where('asesor_destino_user_id', $actor->id)
                ->where('estado', AsignarCliente::ESTADO_PENDIENTE)
                ->lockForUpdate()
                ->get();

            if ($existingPendientes->isNotEmpty()) {
                $dupOps = $existingPendientes->pluck('operacion')->unique()->implode(', ');
                throw ValidationException::withMessages([
                    'operaciones' => [
                        "Ya tienes una solicitud de asignación pendiente para la(s) operación(es): {$dupOps}.",
                    ],
                ]);
            }

            $now = Carbon::now('America/Lima');
            $actorName = trim(str_replace("\xC2\xA0", ' ', (string) $actor->name));
            $created = collect();

            foreach ($resolvedCuentas as $cuenta) {
                $asignacion = AsignarCliente::query()->create([
                    'numdoc' => (string) $cuenta->numdoc,
                    'operacion' => (string) $cuenta->operacion,
                    'cliente_cuenta_id' => (int) $cuenta->id,
                    'name' => $actorName,
                    'solicitante_user_id' => (int) $actor->id,
                    'asesor_destino_user_id' => (int) $actor->id,
                    'fecha_solicitud' => $now,
                    'motivo' => $cleanMotivo,
                    'estado' => AsignarCliente::ESTADO_PENDIENTE,
                    'aprobado_por_user_id' => null,
                    'fecha_aprobacion' => null,
                    'observacion_resolucion' => null,
                    'origen' => AsignarCliente::ORIGEN_MANUAL,
                ]);

                $created->push($asignacion);
            }

            return $created;
        });
    }

    /**
     * Approve a pending assignment request.
     */
    public function approve(User $actor, AsignarCliente $asignacion, ?string $observacion = null): AsignarCliente
    {
        Gate::forUser($actor)->authorize('approve-asignacion');

        $updated = DB::transaction(function () use ($actor, $asignacion, $observacion): AsignarCliente {
            /** @var AsignarCliente $locked */
            $locked = AsignarCliente::query()
                ->whereKey($asignacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->estado !== AsignarCliente::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'asignacion' => [
                        "La solicitud de asignación #{$locked->id} ya fue procesada (estado actual: {$locked->estado}).",
                    ],
                ]);
            }

            $cleanObs = $observacion !== null && trim($observacion) !== ''
                ? trim($observacion)
                : null;

            $locked->forceFill([
                'estado' => AsignarCliente::ESTADO_APROBADA,
                'aprobado_por_user_id' => (int) $actor->id,
                'fecha_aprobacion' => Carbon::now('America/Lima'),
                'observacion_resolucion' => $cleanObs,
            ])->save();

            return $locked->fresh(['solicitante', 'asesorDestino', 'aprobadoPor', 'clienteCuenta']);
        });

        PanelDashboardService::invalidate();

        return $updated;
    }

    /**
     * Reject a pending assignment request.
     */
    public function reject(User $actor, AsignarCliente $asignacion, string $motivoRechazo): AsignarCliente
    {
        Gate::forUser($actor)->authorize('reject-asignacion');

        $cleanMotivo = trim($motivoRechazo);
        if ($cleanMotivo === '') {
            throw ValidationException::withMessages([
                'motivo_rechazo' => ['Debes ingresar el motivo del rechazo de la solicitud de asignación.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $asignacion, $cleanMotivo): AsignarCliente {
            /** @var AsignarCliente $locked */
            $locked = AsignarCliente::query()
                ->whereKey($asignacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->estado !== AsignarCliente::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'asignacion' => [
                        "La solicitud de asignación #{$locked->id} ya fue procesada (estado actual: {$locked->estado}).",
                    ],
                ]);
            }

            $locked->forceFill([
                'estado' => AsignarCliente::ESTADO_RECHAZADA,
                'aprobado_por_user_id' => (int) $actor->id,
                'fecha_aprobacion' => Carbon::now('America/Lima'),
                'observacion_resolucion' => $cleanMotivo,
            ])->save();

            return $locked->fresh(['solicitante', 'asesorDestino', 'aprobadoPor', 'clienteCuenta']);
        });
    }

    /**
     * Retrieve assignment history for a client (Ficha 360°), ordered chronologically descending.
     *
     * @return Collection<int, AsignarCliente>
     */
    public function historyForClient(string $numdoc, int $limit = 50): Collection
    {
        $hasLinearHistory = Schema::hasColumn('asignar_clientes', 'fecha_solicitud');

        $query = AsignarCliente::query()
            ->where('numdoc', $numdoc);

        if ($hasLinearHistory) {
            $query->with([
                'solicitante:id,name,email,role',
                'asesorDestino:id,name,email,role',
                'aprobadoPor:id,name,email,role',
                'clienteCuenta:id,numdoc,operacion,cuenta,nombre,entidad,producto,cosecha,deuda_capital,deuda_total',
            ])
            ->orderByRaw("COALESCE(fecha_solicitud, '1970-01-01 00:00:00') DESC")
            ->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $records = $query->limit($limit)->get();

        if ($hasLinearHistory) {
            $this->hydrateFallbackAccounts($records);
        }

        return $records;
    }

    /**
     * Paginated & filterable query for Bandeja de Autorizaciones and general assignment history.
     *
     * @param array<string, mixed> $filters
     */
    public function paginateHistory(array $filters = [], int $perPage = 15, string $pageName = 'asignaciones_page'): LengthAwarePaginator
    {
        $query = AsignarCliente::query()
            ->with([
                'solicitante:id,name,email,role',
                'asesorDestino:id,name,email,role',
                'aprobadoPor:id,name,email,role',
                'clienteCuenta:id,numdoc,operacion,cuenta,nombre,entidad,producto,cosecha,deuda_capital,deuda_total',
            ]);

        $estado = strtolower(trim((string) ($filters['estado'] ?? '')));
        if (in_array($estado, [
            AsignarCliente::ESTADO_PENDIENTE,
            AsignarCliente::ESTADO_APROBADA,
            AsignarCliente::ESTADO_RECHAZADA,
        ], true)) {
            $query->where('estado', $estado);
        }

        $origen = strtolower(trim((string) ($filters['origen'] ?? '')));
        if (in_array($origen, [
            AsignarCliente::ORIGEN_MANUAL,
            AsignarCliente::ORIGEN_CARGA_MASIVA,
        ], true)) {
            $query->where('origen', $origen);
        }

        $numdoc = trim((string) ($filters['numdoc'] ?? ''));
        if ($numdoc !== '') {
            $query->where('numdoc', $numdoc);
        }

        $operacion = trim((string) ($filters['operacion'] ?? ''));
        if ($operacion !== '') {
            $query->where('operacion', $operacion);
        }

        $solicitanteId = (int) ($filters['solicitante_user_id'] ?? 0);
        if ($solicitanteId > 0) {
            $query->where('solicitante_user_id', $solicitanteId);
        }

        $asesorId = (int) ($filters['asesor_destino_user_id'] ?? 0);
        if ($asesorId > 0) {
            $query->where('asesor_destino_user_id', $asesorId);
        }

        $desde = trim((string) ($filters['desde'] ?? ''));
        if ($desde !== '') {
            $query->where('fecha_solicitud', '>=', $desde . ' 00:00:00');
        }

        $hasta = trim((string) ($filters['hasta'] ?? ''));
        if ($hasta !== '') {
            $query->where('fecha_solicitud', '<=', $hasta . ' 23:59:59');
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('numdoc', 'like', "%{$search}%")
                    ->orWhere('operacion', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('motivo', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByRaw("FIELD(estado, 'pendiente', 'aprobada', 'rechazada') ASC")
            ->orderByRaw("COALESCE(fecha_solicitud, '1970-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $collection = collect($paginator->items());
        $this->hydrateFallbackAccounts($collection);

        return $paginator;
    }

    /**
     * Batch-hydrate fallback ClienteCuenta relationships when cliente_cuenta_id is null
     * and (numdoc, operacion) is unambiguous, plus active advisor for each row's account.
     *
     * @param Collection<int, AsignarCliente> $records
     */
    public function hydrateFallbackAccounts(Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $numdocs = $records->pluck('numdoc')->filter()->unique()->values();
        $operaciones = $records->pluck('operacion')->filter()->unique()->values();

        if ($numdocs->isEmpty() || $operaciones->isEmpty()) {
            return;
        }

        $cuentas = ClienteCuenta::query()
            ->select(['id', 'numdoc', 'operacion', 'cuenta', 'nombre', 'entidad', 'producto', 'cosecha', 'deuda_capital', 'deuda_total'])
            ->whereIn('numdoc', $numdocs->all())
            ->whereIn('operacion', $operaciones->all())
            ->get();

        $cuentasByPair = $cuentas->groupBy(fn (ClienteCuenta $c) => $this->pairKey((string) $c->numdoc, (string) $c->operacion));

        foreach ($records as $record) {
            if (!$record->relationLoaded('clienteCuenta') || $record->clienteCuenta === null) {
                $pairKey = $this->pairKey((string) $record->numdoc, (string) $record->operacion);
                $group = $cuentasByPair->get($pairKey);
                if ($group && $group->count() === 1) {
                    $record->setRelation('clienteCuenta', $group->first());
                }
            }
        }
    }

    /**
     * Compare candidate approved assignments and return the winner according to the rule:
     * 1. Highest fecha_solicitud
     * 2. Highest id
     *
     * @param array<int, AsignarCliente> $candidates
     */
    private function pickVigenteRecord(array $candidates): ?AsignarCliente
    {
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (AsignarCliente $a, AsignarCliente $b): int {
            $tsA = $a->fecha_solicitud ? $a->fecha_solicitud->format('Y-m-d H:i:s.u') : '1970-01-01 00:00:00.000000';
            $tsB = $b->fecha_solicitud ? $b->fecha_solicitud->format('Y-m-d H:i:s.u') : '1970-01-01 00:00:00.000000';

            if ($tsA !== $tsB) {
                return $tsB <=> $tsA;
            }

            return ((int) $b->id) <=> ((int) $a->id);
        });

        return $candidates[0];
    }

    private function pairKey(string $numdoc, string $operacion): string
    {
        return trim($numdoc) . '|' . trim($operacion);
    }
}
