<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CcdCliente;

class IntegracionCcdController extends Controller
{
    // ======= Descargar plantilla =======
    public function template()
    {
        $headers = ['NUMDOC','PDF','COSECHA','LINK'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $headers);
            fputcsv($out, ['71234567','https://dominio.com/archivos/juan.pdf','2024-01','https://enlace/descarga']);
            fclose($out);
        }, 'template_ccd.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // ======= Importar CSV =======
    public function import(Request $r)
    {
        $r->validate(['archivo'=>['required','file','mimes:csv,txt','max:40960']]);
        $path = $r->file('archivo')->getRealPath();

        [$ok,$skip,$err] = $this->doImport($path);

        return back()
            ->with('ok', "Importados: {$ok}, Omitidos: {$skip}")
            ->with('warn', implode("\n", array_slice($err, 0, 10)));
    }

    // ======= Lógica principal =======
    private function doImport(string $filepath): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0, 0, ['No se pudo abrir el archivo']];

        // Detecta delimitador , o ;
        $first = fgets($fh);
        if ($first === false) { fclose($fh); return [0, 0, ['Archivo vacío']]; }
        $del = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
        rewind($fh);

        // Lee encabezados
        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { fclose($fh); return [0, 0, ['Sin encabezados']]; }

        $clean = function ($s) {
            $s = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], '', (string)$s); // BOM/nbsp
            return strtoupper(trim($s));
        };

        // Mapa de columnas
        $idx = [];
        foreach ($headers as $i => $h) {
            $k = $clean($h);
            if (in_array($k, ['NUMDOC','DNI'])) $idx['numdoc'] = $i;
            elseif ($k === 'PDF')           $idx['pdf'] = $i;
            elseif ($k === 'COSECHA')       $idx['cosecha'] = $i;
            elseif ($k === 'LINK')          $idx['link'] = $i;
        }

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;

        // Filas
        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;

            $numdoc = isset($idx['numdoc']) ? trim((string)($row[$idx['numdoc']] ?? '')) : '';
            if ($numdoc === '') { $skip++; $err[] = "Fila {$rowNum}: falta NUMDOC."; continue; }

            $data = [
                'numdoc'  => $numdoc,
                'pdf'     => isset($idx['pdf'])     ? trim((string)($row[$idx['pdf']] ?? '')) : null,
                'cosecha' => isset($idx['cosecha']) ? trim((string)($row[$idx['cosecha']] ?? '')) : null,
                'link'    => isset($idx['link'])    ? trim((string)($row[$idx['link']] ?? '')) : null,
            ];

            try {
                // Inserta tal cual (permite repetir numdoc)
                CcdCliente::insert($data);
                $ok++;
            } catch (\Throwable $e) {
                $skip++; $err[] = "Fila {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);
        return [$ok, $skip, $err];
    }

    // ======= Vista principal =======
    public function index()
    {
        return view('placeholders.integracion-ccd');
    }
}
