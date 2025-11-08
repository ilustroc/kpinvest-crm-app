<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromesaRequest;
use App\Services\Promesas\PromesaCreator;
use Illuminate\Http\RedirectResponse;

class PromesaController extends Controller
{
    public function store(string $dni, StorePromesaRequest $req): RedirectResponse
    {
        [$promesa, $msg] = app(PromesaCreator::class)->createFromRequest($dni, $req);

        return back()->with('ok', $msg);
    }
}
