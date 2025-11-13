<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AsignarCliente;
use Illuminate\Support\Facades\DB;

class IntegracionAsignarController extends Controller
{
    // =======================
    // DESCARGAR PLANTILLA CSV
    // =======================
    public function template()
    {
        $headers = ['NUMDOC','OPERACION','NAME'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $headers);
            fputcsv($out, ['72119599','0514478','Isabel']);
            fclose($out);
        }, 'template_asignar_clientes.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // =======================
    // IMPORTAR CSV
    // =======================
    public function import(Request $r)
    {
        $r->validate([
            'archivo' => ['required','file','mimes:csv,txt','max:40960']
        ]);

        $path = $r->file('archivo')->getRealPath();

        [$ok,$skip,$err] = $this->doImport($path);

        return back()
            ->with('ok', "Importados/Actualizados: {$ok}, Omitidos: {$skip}")
            ->with('warn', implode("\n", array_slice($err, 0, 20)));
    }

    // =======================
    // LÓGICA PRINCIPAL
    // =======================
    private function doImport(string $filepath): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0,0,['No se pudo abrir el archivo']];

        // Detectar delimitador
        $first = fgets($fh);
        $del = (substr_count($first,';') > substr_count($first,',')) ? ';' : ',';
        rewind($fh);

        // Encabezados
        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) return [0,0,['Archivo vacío o sin encabezados']];

        $clean = fn($s) => strtoupper(trim(str_replace(["\xEF\xBB\xBF","\xC2\xA0"],'', (string)$s)));

        $idx = [];
        foreach ($headers as $i=>$h) {
            $k = $clean($h);
            if ($k === 'NUMDOC')    $idx['numdoc'] = $i;
            if ($k === 'OPERACION') $idx['operacion'] = $i;
            if ($k === 'NAME')      $idx['name'] = $i;
        }

        if (!isset($idx['numdoc'], $idx['operacion'], $idx['name']))
            return [0,0,['Encabezados incompletos: se requiere NUMDOC, OPERACION, NAME']];

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;

            $numdoc    = trim($row[$idx['numdoc']] ?? '');
            $operacion = trim($row[$idx['operacion']] ?? '');
            $name      = trim($row[$idx['name']] ?? '');

            if ($numdoc === '')     { $skip++; $err[]="Fila {$rowNum}: falta NUMDOC"; continue; }
            if ($operacion === '')  { $skip++; $err[]="Fila {$rowNum}: falta OPERACION"; continue; }
            if ($name === '')       { $skip++; $err[]="Fila {$rowNum}: falta NAME"; continue; }

            // ============================
            // VALIDAR QUE EXISTE EN clientes_cuentas
            // ============================
            $exists = DB::table('clientes_cuentas')
                ->where('numdoc', $numdoc)
                ->where('operacion', $operacion)
                ->exists();

            if (!$exists) {
                $skip++;
                $err[] = "Fila {$rowNum}: El cliente {$numdoc} con operación {$operacion} NO existe en clientes_cuentas.";
                continue;
            }

            // ============================
            // INSERTAR O ACTUALIZAR
            // ============================
            try {
                AsignarCliente::updateOrCreate(
                    [
                        'numdoc'    => $numdoc,
                        'operacion' => $operacion
                    ],
                    [
                        'name'      => $name
                    ]
                );
                $ok++;
            } catch (\Throwable $e) {
                $skip++;
                $err[] = "Fila {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);
        return [$ok,$skip,$err];
    }

    // =======================
    // VISTA PRINCIPAL
    // =======================
    public function index()
    {
        return view('placeholders.integracion-asignar');
    }
}
