<?php

namespace App\Actions\Promesa;

use App\Http\Requests\StorePromesaRequest;
use App\Services\Promesa\PromesaCreationService;
use Illuminate\Support\Facades\Gate;

class CreatePromesaAction
{
    public function __construct(private readonly PromesaCreationService $promesas)
    {
    }

    public function execute(string $dni, StorePromesaRequest $request): array
    {
        Gate::authorize('create-promesa');

        return $this->promesas->createFromRequest($dni, $request);
    }
}
