<?php

namespace App\Actions\Cna;

use App\Models\User;
use App\Services\Cna\CnaCreationService;
use Illuminate\Support\Facades\Gate;

class CreateCnaAction
{
    public function __construct(private readonly CnaCreationService $cnas)
    {
    }

    public function execute(User $user, string $dni, array $data): array
    {
        Gate::forUser($user)->authorize('create-cna');

        return $this->cnas->createFromData($dni, $data, $user);
    }
}
