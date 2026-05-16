<?php

namespace App\Actions\Promesa;

use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaWorkflowService;
use Illuminate\Support\Facades\Gate;

class RejectPromesaAction
{
    public function __construct(private readonly PromesaWorkflowService $workflow)
    {
    }

    public function asSupervisor(User $user, PromesaPago $promesa, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('reject-promesa-supervisor', $promesa);

        $this->workflow->rejectBySupervisor($promesa, (int) $user->id, $note);
    }

    public function asAdministrator(User $user, PromesaPago $promesa, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('reject-promesa-admin', $promesa);

        $this->workflow->rejectByAdministrator($promesa, (int) $user->id, $note);
    }
}
