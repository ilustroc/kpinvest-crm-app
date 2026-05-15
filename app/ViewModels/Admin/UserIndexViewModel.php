<?php

namespace App\ViewModels\Admin;

use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserIndexViewModel
{
    public function __construct(
        public readonly LengthAwarePaginator $users,
        public readonly Collection $supervisores,
        public readonly array $filters,
        public readonly User $currentUser
    ) {}

    public function showInactive(): bool
    {
        return (bool) ($this->filters['inactivos'] ?? false);
    }

    public function search(): string
    {
        return (string) ($this->filters['q'] ?? '');
    }

    public function roleOptions(): array
    {
        $labels = [
            Roles::ADMINISTRADOR => 'Administrador',
            Roles::SUPERVISOR => 'Supervisor',
            Roles::ASESOR => 'Asesor',
            Roles::SOPORTE => 'Soporte',
        ];

        return collect(Roles::creatableUserRoles($this->currentUser))
            ->mapWithKeys(fn (string $role) => [$role => $labels[$role] ?? ucfirst($role)])
            ->all();
    }

    public function supervisorOptions(): array
    {
        return $this->supervisores
            ->map(fn (User $supervisor) => [
                'id' => $supervisor->id,
                'label' => $supervisor->name.' - '.$supervisor->email,
            ])
            ->values()
            ->all();
    }

    public function canManage(User $target): bool
    {
        return $this->currentUser->can('manage', $target);
    }

    public function canUpdatePassword(User $target): bool
    {
        return $this->currentUser->can('updatePassword', $target);
    }
}
