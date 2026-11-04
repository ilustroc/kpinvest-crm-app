<?php

namespace App\Http\Controllers;

use App\Imports\DataImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
     * Vista previa diagnóstica sin escribir en base de datos.
     */
    public function preview(Request $r, DataImport $import)
    {
        $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);

        $file = $r->file('archivo');
        $tempPath = $file->store('temp_imports');

        try {
            [$processed, $quarantine, $errors, $summary] = $import->preview(Storage::path($tempPath));

            session([
                'data_import_preview' => $summary,
                'data_import_temp_path' => $tempPath,
                'data_import_quarantine' => $summary['detalles_cuarentena'] ?? [],
            ]);

            return back()->with('preview_active', true);
        } catch (\Throwable $e) {
            if (Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            return back()->withErrors(['archivo' => 'Error al analizar la vista previa: ' . $e->getMessage()]);
        }
    }

    /**
     * Importación delegada a DataImport con reconciliación en tres vías.
     */
    public function import(Request $r, DataImport $import)
    {
        $tempPath = $r->input('temp_path');
        $expectedFingerprint = $r->input('db_fingerprint');

        if ($tempPath && Storage::exists($tempPath)) {
            $filePath = Storage::path($tempPath);
        } else {
            $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);
            $filePath = $r->file('archivo')->getRealPath();
        }

        try {
            [$ok, $skip, $err, $summary] = $import->execute($filePath, $expectedFingerprint);

            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
                session()->forget(['data_import_preview', 'data_import_temp_path']);
            }

            session([
                'data_import_result' => $summary,
                'data_import_quarantine' => $summary['detalles_cuarentena'] ?? [],
            ]);

            $msg = "Importación finalizada ▸ Procesados: {$ok} (INSERT: {$summary['insert']}, UPDATE: {$summary['update']}, SIN_CAMBIOS: {$summary['sin_cambios']}). En cuarentena / omitidos: {$skip}.";

            return back()
                ->with('ok', $msg)
                ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
        } catch (\Throwable $e) {
            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            return back()->withErrors(['archivo' => 'Error durante la importación: ' . $e->getMessage()]);
        }
    }

    /**
     * Descarga del reporte de filas en cuarentena.
     */
    public function downloadErrors()
    {
        $quarantine = session('data_import_quarantine', []);

        if (empty($quarantine)) {
            return back()->with('warn', 'No hay reporte de cuarentena disponible en la sesión actual.');
        }

        return response()->streamDownload(function () use ($quarantine) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fila', 'DNI', 'Operación', 'Cuenta', 'Entidad', 'Motivo']);

            foreach ($quarantine as $row) {
                fputcsv($out, [
                    $row['fila'] ?? '-',
                    $row['numdoc'] ?? '-',
                    $row['operacion'] ?? '-',
                    $row['cuenta'] ?? '-',
                    $row['entidad'] ?? '-',
                    $row['motivo'] ?? '-',
                ]);
            }
            fclose($out);
        }, 'reporte_cuarentena_cartera.csv');
    }
}
