<?php

namespace App\Http\Controllers;

use App\Services\Cliente\ClienteLookupService;
use Illuminate\Http\Request;

class ClienteLookupController extends Controller
{
    public function __construct(private readonly ClienteLookupService $lookup)
    {
    }

    public function quickLookup(Request $request)
    {
        $result = $this->lookup->quickLookup((string) $request->query('q', ''));

        return match ($result['type']) {
            'redirect' => redirect()->route('clientes.show', $result['dni']),
            'list' => back()->withInput()->with('quick_list', $result['rows']->toArray()),
            default => back()->withInput()->with('quick_error', $result['message']),
        };
    }

    public function suggest(Request $request)
    {
        return response()->json(
            $this->lookup->suggest((string) $request->query('q', '')),
        );
    }
}
