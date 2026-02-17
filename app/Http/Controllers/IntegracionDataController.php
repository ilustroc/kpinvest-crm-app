<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\DataImport;

class IntegracionDataController extends Controller
{
    /**
     * Vista principal.
     */
    public function index()
    {
        return view('placeholders.integracion-data');
    }

    /**
     * Descarga de plantilla.
     */
    public function template()
    {
        $headers = [
            'NUMDOC','CUENTA','OPERACION','NOMBRE','PRODUCTO',
            'DPTO','PROVINCIA','DISTRITO','DIRECCION',
            'ENTIDAD','COSECHA','FECHA_COMPRA','FECHA_CASTIGO',
            'MONEDA','DEUDA_CAPITAL','INTERES','DEUDA_TOTAL',
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fclose($out);
        }, 'template_clientes_master.csv');
    }

    /**
     * Importación delegada a la clase DataImport.
     */
    public function import(Request $r, DataImport $import)
    {
        $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);
        
        [$ok, $skip, $err] = $import->execute($r->file('archivo')->getRealPath());

        return back()
            ->with('ok', "Importados/Actualizados: {$ok}, Omitidos: {$skip}")
            ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
    }
}