<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\UserService;
use RuntimeException;

class UserStatusService
{
    public function __construct(
        private UserService $userService
    ) {}

    public function toggle(User $actor, User $target): string
    {
        if ($target->id === $actor->id) {
            throw new RuntimeException('No puedes desactivarte a ti mismo.');
        }

        if ($target->role === 'administrador' && $target->active) {
            $activeAdmins = User::where('role', 'administrador')
                ->where('active', 1)
                ->count();

            if ($activeAdmins <= 1) {
                throw new RuntimeException('Debe quedar al menos un administrador activo.');
            }
        }

        if (!$this->userService->canManage($actor, $target)) {
            throw new RuntimeException('No tienes permisos para gestionar este usuario.');
        }

        $target->active = !$target->active;
        $target->save();

        return $target->active ? 'Usuario activado.' : 'Usuario desactivado.';
    }
}
