<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\CcdImport;

class IntegracionCcdController extends Controller
{
    /**
     * Vista principal del módulo CCD.
     */
    public function index()
    {
        return view('placeholders.integracion-ccd');
    }

    /**
     * Descarga de plantilla CSV normalizada.
     */
    public function template()
    {
        $headers = ['NUMDOC', 'PDF', 'COSECHA', 'LINK'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, ['71234567', 'https://dominio.com/archivos/juan.pdf', '2024-01', 'https://enlace/descarga']);
            fclose($out);
        }, 'template_ccd.csv');
    }

    /**
     * Proceso de importación inyectando el servicio CcdImport.
     */
    public function import(Request $r, CcdImport $import)
    {
        $r->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']
        ]);

        [$ok, $skip, $err] = $import->execute($r->file('archivo')->getRealPath());

        return back()
            ->with('ok', "Importados: {$ok}. Omitidos: {$skip}.")
            ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
    }
}