<?php

namespace App\Http\Controllers;

use App\Models\PagoLote;
use App\Models\PagoPropia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PlaceholdersPagosController extends Controller
{
    // GET /integracion/pagos
    public function index()
    {
        $ultimoLotePropia = PagoLote::where('tipo','propia')->latest('id')->first();

        $pagosPropia = collect();
        if ($ultimoLotePropia) {
            $pagosPropia = PagoPropia::where('lote_id', $ultimoLotePropia->id)
                ->latest('id')->take(25)->get();
        }

        return view('placeholders.integracion-pagos', compact('ultimoLotePropia','pagosPropia'));
    }

    // ===== Plantilla CSV (encabezados sin variaciones) =====
    public function template()
    {
        $headers = [
            'Fecha',
            'DNI',
            'Nombre',
            'Operación',
            'Monto',
            'Agente',
            'Cosecha',
            'Cuenta_Recaudo',
            'Entidad Financiera',
        ];

        $csv = implode(',', $headers) . "\n";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_pagos.csv"',
        ]);
    }

    // ===== Importación CSV =====
    public function import(Request $r)
    {
        $r->validate(['archivo' => ['required','file','mimes:csv,txt','max:20480']]);

        $file = $r->file('archivo');
        $path = $file->storeAs('integracion/pagos', time().'_'.$file->getClientOriginalName());

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

    /* ================== Helpers ================== */

    private function detectDelimiter(string $firstLine): string {
        return (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
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

    // ===== Import real con encabezados EXACTOS =====
    private function importCsvPropia(string $filepath, int $loteId): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) return [0,0,['No se pudo abrir el archivo']];

        $first = fgets($fh);
        if ($first === false) { fclose($fh); return [0,0,['Archivo vacío']]; }
        $del = $this->detectDelimiter($first);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) { fclose($fh); return [0,0,['No se pudieron leer los encabezados']]; }

        // Índices por encabezado EXACTO (recorta espacios y limpia BOM)
        $idx = [];
        foreach ($headers as $i => $h) {
            $h = preg_replace('/^\xEF\xBB\xBF/u', '', (string)$h); // BOM
            $idx[trim($h)] = $i;
        }

        // Mapa encabezado -> columna BD
        $map = [
            'Fecha'              => 'fecha',
            'DNI'                => 'dni',
            'Nombre'             => 'nombre_cliente',
            'Operación'          => 'operacion',
            'Monto'              => 'monto_pagado',
            'Agente'             => 'gestor',
            'Cosecha'            => 'cosecha',
            'Cuenta_Recaudo'     => 'cuenta_recaudo',
            'Entidad Financiera' => 'entidad',
        ];

        $ok=0; $skip=0; $err=[]; $rowNum=1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $data = ['lote_id' => $loteId];

            foreach ($map as $header => $col) {
                if (!array_key_exists($header, $idx)) continue; // si falta, se deja null
                $val = $this->toUtf8($row[$idx[$header]] ?? null);

                switch ($header) {
                    case 'Fecha':
                        $data[$col] = $this->parseDate($val);
                        break;
                    case 'Monto':
                        $data[$col] = $this->parseNumber($val);
                        break;
                    default:
                        $data[$col] = ($val !== '') ? $val : null;
                        break;
                }
            }

            // Validación mínima
            if (empty($data['dni']) && empty($data['operacion']) && empty($data['nombre_cliente'])) {
                $skip++; $err[] = "Fila {$rowNum}: sin claves mínimas (DNI/Operación/Nombre)."; continue;
            }

            try { PagoPropia::create($data); $ok++; }
            catch (\Throwable $e) { $skip++; $err[] = "Fila {$rowNum}: ".$e->getMessage(); }
        }

        fclose($fh);
        return [$ok,$skip,$err];
    }
}
