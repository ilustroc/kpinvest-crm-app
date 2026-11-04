<?php

namespace App\Http\Controllers;

use App\Imports\AsignacionImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            fputcsv($out, ['72119599', '0514478', 'AKEMY FLORES']);
            fclose($out);
        }, 'template_asignacion.csv');
    }

    public function preview(Request $r, AsignacionImport $import)
    {
        $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);

        $file = $r->file('archivo');
        $tempPath = $file->store('temp_imports');

        try {
            [, , $err, $summary] = $import->preview(Storage::path($tempPath), $r->user()?->id);

            session([
                'asignacion_import_preview' => $summary,
                'asignacion_import_temp_path' => $tempPath,
                'asignacion_import_rejected' => $summary['detalles_rechazados'] ?? [],
            ]);

            return back()
                ->with('preview_active', true)
                ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
        } catch (\Throwable $e) {
            if (Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            return back()->withErrors(['archivo' => 'Error al generar vista previa: ' . $e->getMessage()]);
        }
    }

    public function import(Request $r, AsignacionImport $import)
    {
        $tempPath = $r->input('temp_path');

        if ($tempPath && Storage::exists($tempPath)) {
            $filePath = Storage::path($tempPath);
        } else {
            $r->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:40960']]);
            $filePath = $r->file('archivo')->getRealPath();
        }

        try {
            [$ok, $skip, $err, $summary] = $import->execute($filePath, $r->user()?->id);

            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
                session()->forget(['asignacion_import_preview', 'asignacion_import_temp_path']);
            }

            session([
                'asignacion_import_result' => $summary,
                'asignacion_import_rejected' => $summary['detalles_rechazados'] ?? [],
            ]);

            $nuevas = $summary['nuevas_asignaciones'] ?? 0;
            $sinCambio = $summary['sin_cambio_efectivo'] ?? 0;

            return back()
                ->with('ok', "Importados: {$ok} (Nuevas asignaciones: {$nuevas}, Sin cambio efectivo: {$sinCambio}). Omitidos: {$skip}.")
                ->with('warn', !empty($err) ? implode("\n", array_slice($err, 0, 10)) : null);
        } catch (\Throwable $e) {
            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            return back()->withErrors(['archivo' => 'Error durante la importación: ' . $e->getMessage()]);
        }
    }

    public function downloadErrors()
    {
        $rejected = session('asignacion_import_rejected', []);

        if (empty($rejected)) {
            return back()->with('warn', 'No hay reporte de filas observadas o rechazadas en la sesión actual.');
        }

        return response()->streamDownload(function () use ($rejected) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fila', 'NUMDOC', 'OPERACION', 'ASESOR', 'Categoría', 'Motivo']);

            foreach ($rejected as $row) {
                fputcsv($out, [
                    $row['fila'] ?? '-',
                    $row['numdoc'] ?? '-',
                    $row['operacion'] ?? '-',
                    $row['asesor'] ?? '-',
                    $row['categoria'] ?? '-',
                    $row['motivo'] ?? '-',
                ]);
            }

            fclose($out);
        }, 'reporte_observaciones_asignacion.csv');
    }
}
