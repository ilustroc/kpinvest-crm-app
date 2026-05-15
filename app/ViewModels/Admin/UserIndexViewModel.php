<?php

namespace App\ViewModels\Admin;

use App\Models\User;
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
        return match ($this->currentUser->role) {
            'administrador', 'sistemas' => [
                'supervisor' => 'Supervisor',
                'asesor' => 'Asesor',
                'soporte' => 'Soporte',
                'usuario' => 'Usuario',
            ],
            'supervisor' => [
                'asesor' => 'Asesor',
                'soporte' => 'Soporte',
            ],
            'soporte' => [
                'usuario' => 'Usuario',
            ],
            default => [],
        };
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
        if (in_array($this->currentUser->role, ['administrador', 'sistemas'], true)) {
            return true;
        }

        if ($this->currentUser->role === 'supervisor') {
            return in_array($target->role, ['asesor', 'soporte'], true)
                && $target->supervisor_id === $this->currentUser->id;
        }

        if ($this->currentUser->role === 'soporte') {
            return $target->role === 'usuario';
        }

        return false;
    }

    public function canUpdatePassword(User $target): bool
    {
        return $this->currentUser->id === $target->id || $this->canManage($target);
    }
}
