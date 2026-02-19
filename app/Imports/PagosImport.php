<?php

namespace App\Imports;

use App\Models\PagoPropia;
use App\Traits\CsvImportTrait;

class PagosImport
{
    use CsvImportTrait;

    public function execute(string $filepath, int $loteId): array
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
            return [0, 0, ['No se pudieron leer los encabezados']];
        }

        $idx = $this->mapHeaders($headers);   // <- ahora normaliza
        $map = $this->getColumnMap();

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $data = ['lote_id' => $loteId];

            foreach ($map as $headerClean => $col) {
                if (!isset($idx[$headerClean])) continue;

                $pos = $idx[$headerClean];
                $raw = $row[$pos] ?? null;
                $val = $this->toUtf8($raw);

                switch ($headerClean) {
                    case 'FECHA':
                        $data[$col] = $this->parseDate($val);
                        break;
                    case 'MONTO':
                        $data[$col] = $this->parseNumber($val);
                        break;
                    default:
                        $data[$col] = is_string($val) ? trim($val) : $val;
                        break;
                }
            }

            // Si no hay data útil, omitir
            if (empty($data['dni']) && empty($data['operacion']) && empty($data['nombre_cliente'])) {
                $skip++;
                continue;
            }

            try {
                PagoPropia::create($data);
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
            $idx[$this->cleanHeader($h)] = $i;
        }
        return $idx;
    }

    private function cleanHeader($h): string
    {
        $h = $this->toUtf8((string)$h);

        // Quitar BOM si viene en el primer encabezado
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);

        $h = trim($h);
        $h = strtoupper($h);

        // Espacios y separadores a _
        $h = str_replace([' ', '-', '/', '\\', '.'], '_', $h);
        $h = preg_replace('/_+/', '_', $h);

        // Solo A-Z, 0-9 y _
        $h = preg_replace('/[^A-Z0-9_]/', '', $h);

        return $h;
    }

    private function getColumnMap(): array
    {
        return [
            'FECHA'              => 'fecha',
            'DNI'                => 'dni',
            'NOMBRE'             => 'nombre_cliente',
            'OPERACION'          => 'operacion',
            'MONTO'              => 'monto_pagado',
            'AGENTE'             => 'gestor',
            'COSECHA'            => 'cosecha',
            'CUENTA_RECAUDO'     => 'cuenta_recaudo',
            'ENTIDAD_FINANCIERA' => 'entidad',
        ];
    }
}
