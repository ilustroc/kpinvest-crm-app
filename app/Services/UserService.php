<?php

namespace App\Services;

use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Database\Eloquent\Builder;

class UserService
{
    /** Construye la consulta filtrada segun el rol del usuario autenticado. */
    public function getFilteredQuery(User $me, array $filters): Builder
    {
        $q = trim($filters['q'] ?? '');
        $showInactivos = $filters['inactivos'] ?? false;

        $query = User::query()
            ->with('supervisor')
            ->where('active', $showInactivos ? 0 : 1)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(fn ($w) => $w->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%"));
            })
            ->orderBy('name');

        return $this->applyRoleVisibility($query, $me);
    }

    /** Aplica las restricciones de visibilidad por rol. */
    private function applyRoleVisibility(Builder $query, User $me): Builder
    {
        return match (Roles::normalize($me->role)) {
            Roles::ADMINISTRADOR => $query,
            Roles::SUPERVISOR => $query->where(fn ($w) => $w->where('supervisor_id', $me->id)->orWhere('id', $me->id)),
            Roles::SOPORTE => $query->where('id', $me->id),
            default => abort(403),
        };
    }

    /** Logica para determinar el supervisor_id al crear/editar. */
    public function resolveSupervisorId(User $me, string $role, ?int $requestedSupId): ?int
    {
        if (!in_array($role, [Roles::ASESOR, Roles::SOPORTE], true)) {
            return null;
        }

        if (Roles::normalize($me->role) === Roles::SUPERVISOR) {
            return $me->id;
        }

        if ($requestedSupId) {
            $exists = User::where('id', $requestedSupId)
                ->where('role', Roles::SUPERVISOR)
                ->where('active', 1)
                ->exists();

            return $exists ? $requestedSupId : abort(422, 'Supervisor invalido.');
        }

        return null;
    }

    /** Verifica si el usuario actual puede gestionar a otro. */
    public function canManage(User $me, User $target): bool
    {
        return Roles::canManageUser($me, $target);
    }
}
