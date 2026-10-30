<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\PagoLote;
use App\Imports\PagosImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class IntegracionPagosController extends Controller
{
    public function index()
    {
        $ultimoLote = PagoLote::where('tipo', 'propia')->latest('id')->first();
        $pagos = collect();
        if ($ultimoLote) {
            $pagos = Pago::where('lote_id', $ultimoLote->id)
                ->latest('id')
                ->take(25)
                ->get();
        }
        return view('placeholders.integracion-pagos', compact('ultimoLote', 'pagos'));
    }

    public function template()
    {
        $headers = [
            'Fecha', 'DNI', 'Nombre', 'Operacion', 'Monto', 
            'Agente', 'Cosecha', 'Cuenta_Recaudo', 'Entidad Financiera'
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fclose($out);
        }, 'template_pagos.csv');
    }

    public function import(Request $r, PagosImport $import)
    {
        $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:20480']]);

        $file = $r->file('archivo');
        $path = $file->storeAs('integracion/pagos', time() . '_' . $file->getClientOriginalName());

        $lote = PagoLote::create([
            'tipo'            => 'propia',
            'archivo'         => $path,
            'usuario_id'      => Auth::id(),
            'total_registros' => 0,
        ]);

        [$ok, $skip, $errores] = $import->execute(Storage::path($path), $lote->id);
        
        $lote->update(['total_registros' => $ok]);

        return redirect()
            ->route('integracion.pagos.index')
            ->with('ok', "Pagos ▸ Procesados: {$ok}. Omitidos: {$skip}.")
            ->with('warn', !empty($errores) ? implode("\n", array_slice($errores, 0, 5)) : null);
    }
}
