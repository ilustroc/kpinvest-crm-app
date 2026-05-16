<?php

namespace App\Actions\Promesa;

use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaDocumentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeneratePromesaAgreementAction
{
    public function __construct(private readonly PromesaDocumentService $documents)
    {
    }

    public function execute(User $user, PromesaPago $promesa): Response|BinaryFileResponse
    {
        Gate::forUser($user)->authorize('generate-promesa-agreement', $promesa);

        return $this->documents->agreementResponse($promesa);
    }
}
