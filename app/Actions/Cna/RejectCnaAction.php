<?php

namespace App\Actions\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Services\Cna\CnaWorkflowService;
use Illuminate\Support\Facades\Gate;

class RejectCnaAction
{
    public function __construct(private readonly CnaWorkflowService $workflow)
    {
    }

    public function asSupervisor(User $user, CnaSolicitud $cna, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('reject-cna-supervisor', $cna);

        $this->workflow->rejectBySupervisor($cna, $user, $note);
    }

    public function asAdministrator(User $user, CnaSolicitud $cna, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('reject-cna-admin', $cna);

        $this->workflow->rejectByAdministrator($cna, $user, $note);
    }
}
