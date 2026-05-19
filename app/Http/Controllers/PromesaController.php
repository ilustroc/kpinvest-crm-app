<?php

namespace App\Http\Controllers;

use App\Actions\Promesa\CreatePromesaAction;
use App\Http\Requests\StorePromesaRequest;
use Illuminate\Http\RedirectResponse;

class PromesaController extends Controller
{
    public function __construct(private readonly CreatePromesaAction $createPromesa)
    {
    }

    public function store(string $dni, StorePromesaRequest $request): RedirectResponse
    {
        [, $message] = $this->createPromesa->execute($dni, $request);

        return back()->with('ok', $message);
    }
}
