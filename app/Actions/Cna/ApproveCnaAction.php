<?php

namespace App\Actions\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Services\Cna\CnaWorkflowService;
use Illuminate\Support\Facades\Gate;

class ApproveCnaAction
{
    public function __construct(private readonly CnaWorkflowService $workflow)
    {
    }

    public function execute(User $user, CnaSolicitud $cna, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('approve-cna', $cna);

        $this->workflow->approve($cna, $user, $note);
    }
}
