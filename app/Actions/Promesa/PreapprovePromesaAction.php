<?php

namespace App\Actions\Promesa;

use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaWorkflowService;
use Illuminate\Support\Facades\Gate;

class PreapprovePromesaAction
{
    public function __construct(private readonly PromesaWorkflowService $workflow)
    {
    }

    public function execute(User $user, PromesaPago $promesa, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('preapprove-promesa', $promesa);

        $this->workflow->preapprove($promesa, (int) $user->id, $note);
    }
}
