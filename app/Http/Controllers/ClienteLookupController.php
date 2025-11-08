<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteLookupController extends Controller
{
    /** Buscar y redirigir por DNI/operación/nombre (misma lógica actual) */
    public function quickLookup(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if ($q === '') return back()->withInput()->with('quick_error', 'Ingresa DNI, Operación o Nombre.');

        $base = DB::table('clientes_cuentas');

        // 1) DNI exacto
        if (preg_match('/^\d{6,}$/', $q)) {
            $dni = (clone $base)->where('numdoc', $q)->value('numdoc');
            if ($dni) return redirect()->route('clientes.show', $dni);

            // 2) Operación exacta
            $dniOp = (clone $base)->where('operacion', $q)->value('numdoc');
            if ($dniOp) return redirect()->route('clientes.show', $dniOp);
        }

        // 3) Coincidencias LIKE (lista de candidatos)
        $cands = (clone $base)
            ->selectRaw('numdoc, MAX(updated_at) as u')
            ->where(function($w) use ($q){
                $w->where('numdoc','like',"%{$q}%")
                  ->orWhere('operacion','like',"%{$q}%")
                  ->orWhere('nombre','like',"%{$q}%");
            })
            ->groupBy('numdoc')
            ->orderByDesc('u')
            ->limit(20)
            ->pluck('numdoc');

        if ($cands->isEmpty())   return back()->withInput()->with('quick_error','Cliente no ubicado.');
        if ($cands->count() === 1) return redirect()->route('clientes.show', $cands->first());

        $rows = DB::table('clientes_cuentas')
            ->whereIn('numdoc', $cands)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni','nombre','operacion','cosecha','updated_at'])
            ->unique('dni')
            ->values();

        return back()->withInput()->with('quick_list', $rows->toArray());
    }

    /** Autocomplete JSON */
    public function suggest(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if ($q === '') return response()->json([]);

        $dnies = DB::table('clientes_cuentas')
            ->selectRaw('numdoc, MAX(updated_at) as u')
            ->where(function($w) use ($q){
                $w->where('numdoc','like',"%{$q}%")
                  ->orWhere('operacion','like',"%{$q}%")
                  ->orWhere('nombre','like',"%{$q}%");
            })
            ->groupBy('numdoc')
            ->orderByDesc('u')
            ->limit(8)
            ->pluck('numdoc');

        $rows = DB::table('clientes_cuentas')
            ->whereIn('numdoc',$dnies)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni','nombre','operacion','cosecha'])
            ->unique('dni')
            ->values()
            ->map(fn($r)=>[
                'dni'=>$r->dni,'nombre'=>$r->nombre,'operacion'=>$r->operacion,
                'cosecha'=>$r->cosecha,'url'=>route('clientes.show',$r->dni)
            ]);

        return response()->json($rows);
    }
}
