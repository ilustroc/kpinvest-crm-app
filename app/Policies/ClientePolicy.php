<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Roles;

class ClientePolicy
{
    public function view(User $user): bool
    {
        return in_array(Roles::normalize($user->role), Roles::FINAL_ROLES, true);
    }

    public function search(User $user): bool
    {
        return $this->view($user);
    }

    public function deletePayment(User $user): bool
    {
        return Roles::canDeleteClientPayments($user);
    }

    public function createPromesa(User $user): bool
    {
        return $this->view($user);
    }

    public function createCna(User $user): bool
    {
        return $this->view($user);
    }
}
