<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\AsignacionImport;

class IntegracionAsignacionController extends Controller
{
    public function index()
    {
        return view('placeholders.integracion-asignacion');
    }

    public function template()
    {
        $headers = ['NUMDOC', 'OPERACION', 'NAME'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, ['72119599', '0514478', 'Isabel']);
            fclose($out);
        }, 'template_asignacion.csv');
    }

    public function import(Request $r, AsignacionImport $import)
    {
        $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);

        [$ok, $skip, $err] = $import->execute($r->file('archivo')->getRealPath());

        return back()
            ->with('ok', "Importados: {$ok}. Omitidos: {$skip}.")
            ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
    }
}