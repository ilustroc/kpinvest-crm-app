<?php

namespace App\Http\Controllers;

use App\Models\PagoLote;
use App\Models\PagoPropia; // (por ahora seguimos usando este modelo hasta unificar a `pagos`)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PlaceholdersPagosController extends Controller
{
    // GET /integracion/pagos
    public function index()
    {
        // Último lote del flujo único (antes "propia")
        $ultimoLotePropia = PagoLote::where('tipo', 'propia')->latest('id')->first();

        $pagosPropia = collect();
        if ($ultimoLotePropia) {
            $pagosPropia = PagoPropia::where('lote_id', $ultimoLotePropia->id)
                ->latest('id')
                ->take(25)
                ->get();
        }

        // Vista unificada
        return view('placeholders.integracion-pagos', compact('ultimoLotePropia', 'pagosPropia'));
    }

    // ======= Plantilla CSV (flujo único) =======
    public function template()
    {
        $headers = [
            'DNI','OPERACION','ENTIDAD','EQUIPOS','NOMBRE_CLIENTE',
            'PRODUCTO','MONEDA','FECHA_DE_PAGO','MONTO_PAGADO','CONCATENAR',
            'FECHA','PAGADO_EN_SOLES','GESTOR','STATUS'
        ];

        $csv = implode(',', $headers) . "\n";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_pagos.csv"',
        ]);
    }

    // ======= Importación CSV (flujo único) =======
    public function import(Request $r)
    {
        $r->validate(['archivo' => ['required','file','mimes:csv,txt','max:20480']]);

        $file = $r->file('archivo');
        $path = $file->storeAs('integracion/pagos', time().'_'.$file->getClientOriginalName());

        // Por compatibilidad dejamos tipo='propia' (lo renombramos cuando migremos a la tabla única)
        $lote = PagoLote::create([
            'tipo'            => 'propia',
            'archivo'         => $path,
            'usuario_id'      => Auth::id(),
            'total_registros' => 0,
        ]);

        [$ok, $skip, $errores] = $this->importCsvPropia(Storage::path($path), $lote->id);
        $lote->update(['total_registros' => $ok]);

        return redirect()
            ->route('integracion.pagos')
            ->with('ok', "Pagos ▸ OK: {$ok}. Omitidos: {$skip}.")
            ->with('warn', implode("\n", array_slice($errores, 0, 5)));
    }

    // ================== HELPERS ==================
    private function detectDelimiter(string $firstLine): string {
        return (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    }

    private function norm(string $h): string {
        $h = preg_replace('/^\xEF\xBB\xBF/u', '', $h); // BOM
        $h = str_replace("\xC2\xA0", ' ', $h);         // NBSP
        $h = strtoupper(trim($h));
        $h = str_replace(
            [' ', '-', 'Á','É','Í','Ó','Ú','Ü','Ñ','º','°','.'],
            ['_','_','A','E','I','O','U','U','N','','',''],
            $h
        );
        $h = preg_replace('/[^A-Z0-9_]/', '_', $h);
        $h = trim($h, '_');
        if ($h === 'N') $h = 'NRO';
        return $h;
    }

    private function parseDate(?string $v): ?string {
        if (!$v) return null;
        $v = trim($v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) return $v;
        return null;
    }

    private function parseNumber(?string $v): ?float {
        if ($v === null) return null;
        $v = trim($v);
        if ($v==='') return null;
        if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
            $v = str_replace(['.', ' '], '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (strpos($v, ',') !== false) {
            $v = str_replace(['.', ' '], '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(' ', '', $v);
        }
        return is_numeric($v) ? (float)$v : null;
    }

    private function toUtf8(?string $s): ?string{
        if ($s === null) return null;
        $s = trim($s);
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
    }

    // ======= Import CSV (antes "propia", ahora flujo único) =======
    private function importCsvPropia(string $filepath, int $loteId): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0, 0, ['No se pudo abrir el archivo']];

        $first = fgets($fh);
        if ($first === false) { fclose($fh); return [0, 0, ['Archivo vacío']]; }
        $del = $this->detectDelimiter($first);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { fclose($fh); return [0, 0, ['No se pudieron leer los encabezados']]; }

        $map = [];
        foreach ($headers as $i => $h) $map[$i] = $this->norm($h);

        $expect = [
            'DNI'               => 'dni',
            'OPERACION'         => 'operacion',
            'ENTIDAD'           => 'entidad',
            'EQUIPOS'           => 'equipos',
            'NOMBRE_CLIENTE'    => 'nombre_cliente',
            'PRODUCTO'          => 'producto',
            'MONEDA'            => 'moneda',
            'FECHA_DE_PAGO'     => 'fecha_de_pago',
            'MONTO_PAGADO'      => 'monto_pagado',
            'CONCATENAR'        => 'concatenar',
            'FECHA'             => 'fecha',
            'PAGADO_EN_SOLES'   => 'pagado_en_soles',
            'GESTOR'            => 'gestor',
            'STATUS'            => 'status',
        ];

        $ok=0; $skip=0; $err=[]; $rowNum=1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $data = ['lote_id' => $loteId];

            foreach ($row as $i => $val) {
                $key = $map[$i] ?? null;
                if (!$key || !isset($expect[$key])) continue;

                $attr = $expect[$key];
                $val  = $this->toUtf8((string)$val);

                if (in_array($key, ['FECHA_DE_PAGO','FECHA'])) {
                    $data[$attr] = $this->parseDate($val);
                } elseif (in_array($key, ['MONTO_PAGADO','PAGADO_EN_SOLES'])) {
                    $data[$attr] = $this->parseNumber($val);
                } else {
                    $data[$attr] = ($val !== '') ? $val : null;
                }
            }

            // Claves mínimas
            if (empty($data['dni']) && empty($data['operacion']) && empty($data['nombre_cliente'])) {
                $skip++; $err[] = "Fila {$rowNum}: sin claves mínimas (DNI/OPERACION/NOMBRE_CLIENTE)."; continue;
            }

            try { PagoPropia::create($data); $ok++; }
            catch (\Throwable $e) { $skip++; $err[] = "Fila {$rowNum}: ".$e->getMessage(); }
        }

        fclose($fh);
        return [$ok, $skip, $err];
    }
}
