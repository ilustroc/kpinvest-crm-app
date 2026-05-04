<?php

namespace App\Imports;

use App\Traits\CsvImportTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataImport
{
    use CsvImportTrait;

    public function execute(string $filepath): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0, 0, ['No se pudo abrir el archivo']];

        $first = fgets($fh);
        if ($first === false) { fclose($fh); return [0, 0, ['Archivo vacío']]; }
        $del = $this->detectDelimiter($first);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { fclose($fh); return [0, 0, ['Sin encabezados']]; }

        $map = $this->mapHeaders($headers);

        // Configuración de performance para grandes volúmenes
        DB::connection()->disableQueryLog();
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $ok = 0; $skip = 0; $err = []; $rowNum = 1;
        $batch = []; $BATCH_SIZE = 2000;
        $now = Carbon::now()->toDateTimeString();

        // Columnas a actualizar si el registro ya existe
        $updateCols = [
            'cuenta', 'nombre', 'producto', 'dpto', 'provincia', 'distrito', 'direccion',
            'entidad', 'cosecha', 'fecha_compra', 'fecha_castigo', 'moneda',
            'deuda_capital', 'interes', 'deuda_total', 'updated_at'
        ];

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $data = $this->mapRowToData($row, $map, $now);

            if (empty($data['numdoc']) || empty($data['operacion'])) {
                $skip++;
                continue;
            }

            $batch[] = $data;
            if (count($batch) >= $BATCH_SIZE) {
                $this->flush($batch, $ok, $skip, $err, $updateCols);
                $batch = [];
            }
        }

        $this->flush($batch, $ok, $skip, $err, $updateCols);
        fclose($fh);

        return [$ok, $skip, $err];
    }

    private function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $norm = $this->toUtf8($h);
            if ($norm !== null) {
                $norm = str_replace("\xEF\xBB\xBF", '', $norm);
                $norm = preg_replace('/^\x{FEFF}/u', '', $norm);
                $norm = trim($norm);
                $norm = strtoupper($norm);
                $norm = preg_replace('/\s+/', '_', $norm);
                $norm = preg_replace('/[^A-Z0-9_]/', '', $norm);
            }
            if ($norm === 'ENTIDAD_FINANCIERA') {
                $norm = 'ENTIDAD';
            }
            $map[$i] = $norm;
        }
        return $map;
    }

    private function mapRowToData(array $row, array $map, string $now): array
    {
        $data = ['created_at' => $now, 'updated_at' => $now];
        foreach ($row as $i => $val) {
            $key = $map[$i] ?? null;
            if (!$key) continue;

            $v = $this->toUtf8($val);

            switch ($key) {
                case 'NUMDOC':        $data['numdoc']        = $v; break;
                case 'OPERACION':     $data['operacion']     = $v; break;
                case 'CUENTA':        $data['cuenta']        = $v; break;
                case 'NOMBRE':        $data['nombre']        = $v; break;
                case 'PRODUCTO':      $data['producto']      = $v; break;
                case 'DPTO':          $data['dpto']          = $v; break;
                case 'PROVINCIA':     $data['provincia']     = $v; break;
                case 'DISTRITO':      $data['distrito']      = $v; break;
                case 'DIRECCION':     $data['direccion']     = $v; break;
                case 'ENTIDAD':       $data['entidad']       = $v; break;
                case 'COSECHA':       $data['cosecha']       = $v; break;
                case 'MONEDA':        $data['moneda']        = $v; break;
                case 'FECHA_COMPRA':  $data['fecha_compra']  = $this->parseDate($v); break;
                case 'FECHA_CASTIGO': $data['fecha_castigo'] = $this->parseDate($v); break;
                case 'DEUDA_CAPITAL': $data['deuda_capital'] = $this->parseNumber($v); break;
                case 'INTERES':       $data['interes']       = $this->parseNumber($v); break;
                case 'DEUDA_TOTAL':   $data['deuda_total']   = $this->parseNumber($v); break;
            }
        }
        return $data;
    }

    private function flush(array $batch, &$ok, &$skip, &$err, array $updateCols): void
    {
        if (empty($batch)) return;
        try {
            DB::table('clientes_cuentas')->upsert($batch, ['numdoc', 'operacion'], $updateCols);
            $ok += count($batch);
        } catch (\Throwable $e) {
            $skip += count($batch);
            $err[] = "Error en lote: " . $e->getMessage();
        }
    }
}