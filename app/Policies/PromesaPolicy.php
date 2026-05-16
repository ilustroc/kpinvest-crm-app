<?php

namespace App\Policies;

use App\Models\PromesaPago;
use App\Models\User;
use App\Support\Authorization\Roles;

class PromesaPolicy
{
    public function create(User $user): bool
    {
        return in_array(Roles::normalize($user->role), Roles::FINAL_ROLES, true);
    }

    public function viewWorkflow(User $user): bool
    {
        return Roles::canReviewWorkflow($user);
    }

    public function preapprove(User $user, ?PromesaPago $promesa = null): bool
    {
        return Roles::normalize($user->role) === Roles::SUPERVISOR;
    }

    public function approve(User $user, ?PromesaPago $promesa = null): bool
    {
        return Roles::normalize($user->role) === Roles::ADMINISTRADOR;
    }

    public function rejectAsSupervisor(User $user, ?PromesaPago $promesa = null): bool
    {
        return $this->preapprove($user, $promesa);
    }

    public function rejectAsAdministrator(User $user, ?PromesaPago $promesa = null): bool
    {
        return $this->approve($user, $promesa);
    }

    public function generateAgreement(User $user, PromesaPago $promesa): bool
    {
        return in_array(Roles::normalize($user->role), Roles::FINAL_ROLES, true);
    }
}
