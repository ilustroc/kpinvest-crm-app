<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /** Construye la consulta filtrada según el rol del usuario autenticado */
    public function getFilteredQuery(User $me, array $filters): Builder
    {
        $q = trim($filters['q'] ?? '');
        $showInactivos = $filters['inactivos'] ?? false;

        $query = User::query()
            ->with('supervisor')
            ->where('active', $showInactivos ? 0 : 1)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(fn($w) => $w->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%"));
            })
            ->orderBy('name');

        return $this->applyRoleVisibility($query, $me);
    }

    /** Aplica las restricciones de visibilidad por Rol */
    private function applyRoleVisibility(Builder $query, User $me): Builder
    {
        return match ($me->role) {
            'administrador', 'sistemas' => $query,
            'supervisor' => $query->where(fn($w) => $w->where('supervisor_id', $me->id)->orWhere('id', $me->id)),
            'soporte' => $query->where('role', 'usuario'),
            default => abort(403),
        };
    }

    /** Lógica para determinar el supervisor_id al crear/editar */
    public function resolveSupervisorId(User $me, string $role, ?int $requestedSupId): ?int
    {
        if (!in_array($role, ['asesor', 'soporte'])) {
            return null;
        }

        if ($me->role === 'supervisor') {
            return $me->id;
        }

        if ($requestedSupId) {
            $exists = User::where('id', $requestedSupId)
                ->where('role', 'supervisor')
                ->where('active', 1)
                ->exists();
            return $exists ? $requestedSupId : abort(422, 'Supervisor inválido.');
        }

        return null;
    }

    /** Verifica si el usuario actual puede gestionar a otro (Toggle/Password) */
    public function canManage(User $me, User $target): bool
    {
        if (in_array($me->role, ['administrador', 'sistemas'])) return true;

        if ($me->role === 'supervisor') {
            return in_array($target->role, ['asesor', 'soporte']) && $target->supervisor_id === $me->id;
        }

        if ($me->role === 'soporte') {
            return $target->role === 'usuario';
        }

        return false;
    }
}