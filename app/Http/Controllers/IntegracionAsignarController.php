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
        if ($first === false) {
            fclose($fh);
            return [0,0,['Archivo vacío']];
        }
        $del = (substr_count($first,';') > substr_count($first,',')) ? ';' : ',';
        rewind($fh);

        // Encabezados
        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) {
            fclose($fh);
            return [0,0,['Sin encabezados']];
        }

        $clean = fn($s) => strtoupper(trim(str_replace(["\xEF\xBB\xBF","\xC2\xA0"],'', (string)$s)));

        $idx = [];
        foreach ($headers as $i=>$h) {
            $k = $clean($h);
            if ($k === 'NUMDOC')    $idx['numdoc'] = $i;
            if ($k === 'OPERACION') $idx['operacion'] = $i;
            if ($k === 'NAME')      $idx['name'] = $i;
        }

        if (!isset($idx['numdoc'], $idx['operacion'], $idx['name'])) {
            fclose($fh);
            return [0,0,['Encabezados incompletos: se requiere NUMDOC, OPERACION, NAME']];
        }

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;

        // Procesar en bloques para no matar al server
        $CHUNK_SIZE = 2000;
        $buffer = [];

        $flushChunk = function() use (&$buffer, &$ok, &$skip, &$err) {
            if (empty($buffer)) return;

            // Armar set de pares (numdoc, operacion) del bloque
            $pairs = [];
            foreach ($buffer as $b) {
                $key = $b['numdoc'].'|'.$b['operacion'];
                $pairs[$key] = $b;
            }

            // Sacar listas para el whereIn
            $numdocs = array_values(array_unique(array_column($buffer, 'numdoc')));
            $opers   = array_values(array_unique(array_column($buffer, 'operacion')));

            // Traer solo los pares que existen en clientes_cuentas
            $validPairs = [];
            if ($numdocs && $opers) {
                $rows = DB::table('clientes_cuentas')
                    ->select('numdoc','operacion')
                    ->whereIn('numdoc', $numdocs)
                    ->whereIn('operacion', $opers)
                    ->get();

                foreach ($rows as $r) {
                    /** @var \stdClass $r */
                    $validPairs[$r->numdoc.'|'.$r->operacion] = true;
                }
            }

            // Procesar cada fila del bloque usando el mapa en memoria
            foreach ($buffer as $b) {
                $rowNum = $b['__row'];
                $numdoc = $b['numdoc'];
                $oper   = $b['operacion'];
                $name   = $b['name'];
                $key    = $numdoc.'|'.$oper;

                if (!isset($validPairs[$key])) {
                    $skip++;
                    $err[] = "Fila {$rowNum}: El cliente {$numdoc} con operación {$oper} NO existe en clientes_cuentas.";
                    continue;
                }

                try {
                    AsignarCliente::updateOrCreate(
                        ['numdoc'=>$numdoc,'operacion'=>$oper],
                        ['name'=>$name]
                    );
                    $ok++;
                } catch (\Throwable $e) {
                    $skip++;
                    $err[] = "Fila {$rowNum}: ".$e->getMessage();
                }
            }

            // Vaciar buffer
            $buffer = [];
        };

        // Leer filas
        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;

            $numdoc    = trim($row[$idx['numdoc']] ?? '');
            $operacion = trim($row[$idx['operacion']] ?? '');
            $name      = trim($row[$idx['name']] ?? '');

            if ($numdoc === '')    { $skip++; $err[]="Fila {$rowNum}: falta NUMDOC"; continue; }
            if ($operacion === '') { $skip++; $err[]="Fila {$rowNum}: falta OPERACION"; continue; }
            if ($name === '')      { $skip++; $err[]="Fila {$rowNum}: falta NAME"; continue; }

            $buffer[] = [
                '__row'     => $rowNum,
                'numdoc'    => $numdoc,
                'operacion' => $operacion,
                'name'      => $name,
            ];

            if (count($buffer) >= $CHUNK_SIZE) {
                $flushChunk();
            }
        }

        // Último bloque
        $flushChunk();

        fclose($fh);
        return [$ok, $skip, $err];
    }


    // =======================
    // VISTA PRINCIPAL
    // =======================
    public function index()
    {
        return view('placeholders.integracion-asignar');
    }
}
