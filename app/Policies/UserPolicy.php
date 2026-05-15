<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Roles;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Roles::canAccessAdminUsers($user);
    }

    public function create(User $user): bool
    {
        return Roles::creatableUserRoles($user) !== [];
    }

    public function manage(User $user, User $target): bool
    {
        return Roles::canManageUser($user, $target);
    }

    public function toggleStatus(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->manage($user, $target);
    }

    public function updatePassword(User $user, User $target): bool
    {
        return $user->id === $target->id || $this->manage($user, $target);
    }
}
