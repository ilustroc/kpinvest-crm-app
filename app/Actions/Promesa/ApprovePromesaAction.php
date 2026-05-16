<?php

namespace App\Actions\Promesa;

use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaWorkflowService;
use Illuminate\Support\Facades\Gate;

class ApprovePromesaAction
{
    public function __construct(private readonly PromesaWorkflowService $workflow)
    {
    }

    public function execute(User $user, PromesaPago $promesa, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('approve-promesa', $promesa);

        $this->workflow->approve($promesa, (int) $user->id, $note);
    }
}
