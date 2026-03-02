<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Services\Clientes\ClienteSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClienteSearchController extends Controller
{
    public function __construct(private ClienteSearchService $svc) {}

    /** GET /clientes/lookup?q=...  */
    public function lookup(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if ($q === '') return back()->withInput()->with('quick_error', 'Ingresa DNI, Operación o Nombre.');

        // 1) DNI exacto / Operación exacta
        if (preg_match('/^\d{6,}$/', $q)) {
            if ($dni = $this->svc->findDniByExactDni($q)) {
                return redirect()->route('clientes.show', $dni);
            }
            if ($dniOp = $this->svc->findDniByExactOperacion($q)) {
                return redirect()->route('clientes.show', $dniOp);
            }
        }

        // 2) candidatos
        $cands = $this->svc->candidateDnies($q, 20);

        if ($cands->isEmpty()) return back()->withInput()->with('quick_error', 'Cliente no ubicado.');
        if ($cands->count() === 1) return redirect()->route('clientes.show', $cands->first());

        $rows = $this->svc->rowsByDnies($cands);

        return back()->withInput()->with('quick_list', $rows->toArray());
    }

    /** GET /clientes/suggest?q=... (JSON) */
    public function suggest(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);

        $rows = $this->svc->suggestRows($q, 5)->map(function ($x) {
            $meta = trim(($x->operacion ?? '') . (($x->cosecha ?? '') ? ' · ' . $x->cosecha : ''));
            return [
                'value' => $x->dni,
                'title' => $x->dni . ' » ' . Str::limit((string)$x->nombre, 40, '…'),
                'meta'  => $meta,
                'url'   => route('clientes.show', $x->dni),
            ];
        });

        return response()->json($rows);
    }
}