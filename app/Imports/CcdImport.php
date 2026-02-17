<?php

namespace App\Imports;

use App\Models\CcdCliente;
use App\Traits\CsvImportTrait;

class CcdImport
{
    use CsvImportTrait;

    public function execute(string $filepath): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0, 0, ['No se pudo abrir el archivo']];

        $firstLine = fgets($fh);
        if ($firstLine === false) { 
            fclose($fh); 
            return [0, 0, ['Archivo vacío']]; 
        }
        $del = $this->detectDelimiter($firstLine);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { 
            fclose($fh); 
            return [0, 0, ['Sin encabezados']]; 
        }

        $idx = $this->mapHeaders($headers);

        if (!isset($idx['numdoc'])) {
            fclose($fh);
            return [0, 0, ['Falta columna obligatoria: NUMDOC']];
        }

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;

            $numdoc = $this->toUtf8($row[$idx['numdoc']] ?? '');
            
            if ($numdoc === '') { 
                $skip++; 
                continue; 
            }

            $data = [
                'numdoc'  => $numdoc,
                'pdf'     => isset($idx['pdf'])     ? $this->toUtf8($row[$idx['pdf']] ?? null)     : null,
                'cosecha' => isset($idx['cosecha']) ? $this->toUtf8($row[$idx['cosecha']] ?? null) : null,
                'link'    => isset($idx['link'])    ? $this->toUtf8($row[$idx['link']] ?? null)    : null,
            ];

            try {
                // Mantenemos insert para permitir historial de expedientes por cliente
                CcdCliente::insert($data);
                $ok++;
            } catch (\Throwable $e) {
                $skip++; 
                $err[] = "Fila {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($fh);
        return [$ok, $skip, $err];
    }

    private function mapHeaders(array $headers): array
    {
        $idx = [];
        foreach ($headers as $i => $h) {
            $k = $this->toUtf8($h);
            if (in_array($k, ['NUMDOC', 'DNI'])) $idx['numdoc'] = $i;
            elseif ($k === 'PDF')                $idx['pdf'] = $i;
            elseif ($k === 'COSECHA')            $idx['cosecha'] = $i;
            elseif ($k === 'LINK')               $idx['link'] = $i;
        }
        return $idx;
    }
}