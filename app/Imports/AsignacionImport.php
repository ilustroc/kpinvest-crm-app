<?php

namespace App\Imports;

use App\Models\AsignarCliente;
use App\Traits\CsvImportTrait;
use Illuminate\Support\Facades\DB;

class AsignacionImport
{
    use CsvImportTrait;

    public function execute(string $filepath): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0, 0, ['No se pudo abrir el archivo']];

        $firstLine = fgets($fh);
        if ($firstLine === false) { fclose($fh); return [0, 0, ['Archivo vacío']]; }
        $del = $this->detectDelimiter($firstLine);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { fclose($fh); return [0, 0, ['Sin encabezados']]; }

        $idx = $this->mapHeaders($headers);
        if (!isset($idx['numdoc'], $idx['operacion'], $idx['name'])) {
            fclose($fh);
            return [0, 0, ['Encabezados incompletos: se requiere NUMDOC, OPERACION, NAME']];
        }

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;
        $CHUNK_SIZE = 2000;
        $buffer = [];

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $numdoc = $this->toUtf8($row[$idx['numdoc']] ?? '');
            $oper   = $this->toUtf8($row[$idx['operacion']] ?? '');
            $name   = $this->toUtf8($row[$idx['name']] ?? '');

            if ($numdoc === '' || $oper === '' || $name === '') {
                $skip++;
                $err[] = "Fila {$rowNum}: Datos incompletos.";
                continue;
            }

            $buffer[] = ['__row' => $rowNum, 'numdoc' => $numdoc, 'operacion' => $oper, 'name' => $name];

            if (count($buffer) >= $CHUNK_SIZE) {
                $this->processBuffer($buffer, $ok, $skip, $err);
                $buffer = [];
            }
        }

        $this->processBuffer($buffer, $ok, $skip, $err);
        fclose($fh);

        return [$ok, $skip, $err];
    }

    private function mapHeaders(array $headers): array
    {
        $idx = [];
        foreach ($headers as $i => $h) {
            $k = $this->toUtf8($h);
            if ($k === 'NUMDOC')    $idx['numdoc'] = $i;
            if ($k === 'OPERACION') $idx['operacion'] = $i;
            if ($k === 'NAME')      $idx['name'] = $i;
        }
        return $idx;
    }

    private function processBuffer(array $buffer, &$ok, &$skip, &$err): void
    {
        if (empty($buffer)) return;

        $numdocs = array_unique(array_column($buffer, 'numdoc'));
        $opers   = array_unique(array_column($buffer, 'operacion'));

        $validPairs = DB::table('clientes_cuentas')
            ->select(DB::raw("CONCAT(numdoc, '|', operacion) as pair"))
            ->whereIn('numdoc', $numdocs)
            ->whereIn('operacion', $opers)
            ->pluck('pair')
            ->flip()
            ->toArray();

        foreach ($buffer as $b) {
            $key = $b['numdoc'] . '|' . $b['operacion'];

            if (!isset($validPairs[$key])) {
                $skip++;
                $err[] = "Fila {$b['__row']}: El cliente {$b['numdoc']} Op {$b['operacion']} NO existe en maestro.";
                continue;
            }

            try {
                AsignarCliente::updateOrCreate(
                    ['numdoc' => $b['numdoc'], 'operacion' => $b['operacion']],
                    ['name' => $b['name']]
                );
                $ok++;
            } catch (\Throwable $e) {
                $skip++;
                $err[] = "Fila {$b['__row']}: " . $e->getMessage();
            }
        }
    }
}