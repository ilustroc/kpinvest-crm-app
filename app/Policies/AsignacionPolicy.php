<?php

namespace App\Policies;

use App\Models\AsignarCliente;
use App\Models\User;
use App\Support\Authorization\Roles;

class AsignacionPolicy
{
    public function viewWorkflow(User $user): bool
    {
        return Roles::canReviewAsignaciones($user);
    }

    public function create(User $user): bool
    {
        return Roles::has($user, Roles::FINAL_ROLES);
    }

    public function approve(User $user, ?AsignarCliente $asignacion = null): bool
    {
        if (!Roles::canReviewAsignaciones($user)) {
            return false;
        }

        if ($asignacion !== null && $asignacion->estado !== AsignarCliente::ESTADO_PENDIENTE) {
            return false;
        }

        return true;
    }

    public function reject(User $user, ?AsignarCliente $asignacion = null): bool
    {
        if (!Roles::canReviewAsignaciones($user)) {
            return false;
        }

        if ($asignacion !== null && $asignacion->estado !== AsignarCliente::ESTADO_PENDIENTE) {
            return false;
        }

        return true;
    }
}
