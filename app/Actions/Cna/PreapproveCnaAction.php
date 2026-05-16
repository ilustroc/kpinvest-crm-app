<?php

namespace App\Actions\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Services\Cna\CnaWorkflowService;
use Illuminate\Support\Facades\Gate;

class PreapproveCnaAction
{
    public function __construct(private readonly CnaWorkflowService $workflow)
    {
    }

    public function execute(User $user, CnaSolicitud $cna, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('preapprove-cna', $cna);

        $this->workflow->preapprove($cna, $user, $note);
    }
}
