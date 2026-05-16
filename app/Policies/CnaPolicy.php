<?php

namespace App\Policies;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Support\Authorization\Roles;

class CnaPolicy
{
    public function create(User $user): bool
    {
        return in_array(Roles::normalize($user->role), Roles::FINAL_ROLES, true);
    }

    public function viewWorkflow(User $user): bool
    {
        return Roles::canReviewWorkflow($user);
    }

    public function preapprove(User $user, ?CnaSolicitud $cna = null): bool
    {
        return Roles::normalize($user->role) === Roles::SUPERVISOR;
    }

    public function approve(User $user, ?CnaSolicitud $cna = null): bool
    {
        return Roles::normalize($user->role) === Roles::ADMINISTRADOR;
    }

    public function rejectAsSupervisor(User $user, ?CnaSolicitud $cna = null): bool
    {
        return $this->preapprove($user, $cna);
    }

    public function rejectAsAdministrator(User $user, ?CnaSolicitud $cna = null): bool
    {
        return $this->approve($user, $cna);
    }

    public function generateDocument(User $user, CnaSolicitud $cna): bool
    {
        return $this->approve($user, $cna);
    }

    public function downloadDocument(User $user, CnaSolicitud $cna): bool
    {
        return in_array(Roles::normalize($user->role), Roles::FINAL_ROLES, true);
    }
}
