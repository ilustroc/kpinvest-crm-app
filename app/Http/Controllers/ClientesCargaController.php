<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClienteCuenta;

class ClientesCargaController extends Controller
{
    // ======= Plantilla con el NUEVO esquema =======
    public function templateClientesMaster()
    {
        $headers = [
            'NUMDOC','CUENTA','OPERACION','NOMBRE','PRODUCTO',
            'DPTO','PROVINCIA','DISTRITO','DIRECCION',
            'ENTIDAD','COSECHA','FECHA_COMPRA','FECHA_CASTIGO',
            'DEUDA_CAPITAL','INTERES','DEUDA_TOTAL',
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $headers);
            fclose($out);
        }, 'template_clientes_master.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // ======= Importador (NUEVO esquema) =======
    public function importClientesMaster(Request $r)
    {
        $r->validate(['archivo'=>['required','file','mimes:csv,txt','max:40960']]);
        $path = $r->file('archivo')->getRealPath();

        [$ok,$skip,$err] = $this->doImportClientesMaster($path);

        return back()
            ->with('ok', "Importados: {$ok}, Omitidos: {$skip}")
            ->with('warn', implode("\n", array_slice($err, 0, 10)));
    }

    private function doImportClientesMaster(string $filepath): array
    {
        $fh = fopen($filepath,'r'); if(!$fh) return [0,0,['No se pudo abrir el archivo']];

        $first = fgets($fh); if($first===false){ fclose($fh); return [0,0,['Archivo vacío']]; }
        $del = (substr_count($first,';') > substr_count($first,',')) ? ';' : ',';
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if(!$headers){ fclose($fh); return [0,0,['Sin encabezados']]; }

        // ---------- Normalizadores ----------
        $norm = function(string $s): string {
            $s = preg_replace('/^\xEF\xBB\xBF/u','',$s);       // BOM
            $s = str_replace("\xC2\xA0",' ',$s);               // NBSP
            $s = strtoupper(trim($s));
            $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
            $s = preg_replace('/[^A-Z0-9]+/','_',$s);
            return trim($s,'_');
        };
        $map = [];
        foreach ($headers as $i=>$h) $map[$i] = $norm($h);

        // Permite pequeñas variaciones de naming/acentos
        $alias = [
            'ENTIDAD_FINANCIERA'=> 'ENTIDAD',
        ];

        $toUtf8 = function(?string $s): ?string {
            if ($s===null) return null; $s = trim($s);
            if ($s==='') return null;
            if (!mb_check_encoding($s,'UTF-8')) {
                $s = mb_convert_encoding($s,'UTF-8','UTF-8, ISO-8859-1, Windows-1252');
            }
            return preg_replace('/[\x00-\x1F\x7F]/u','',$s);
        };

        $num = function(?string $v): ?float {
            if ($v===null) return null; $v=trim($v); if($v==='') return null;
            if (str_contains($v,',') && str_contains($v,'.')) { $v=str_replace(['.',' '],'',$v); $v=str_replace(',','.',$v); }
            elseif (str_contains($v,',')) { $v=str_replace(['.',' '],'',$v); $v=str_replace(',','.',$v); }
            else { $v=str_replace(' ','',$v); }
            return is_numeric($v) ? (float)$v : null;
        };

        $date = function(?string $v): ?string {
            if(!$v) return null; $v=trim($v);
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#',$v,$m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
            if (preg_match('#^\d{4}-\d{2}-\d{2}$#',$v)) return $v;
            return null;
        };

        // ---------- Bucle ----------
        $ok=0; $skip=0; $err=[]; $rowNum=1;

        while(($row=fgetcsv($fh,0,$del))!==false){
            $rowNum++; $data=[];

            foreach($row as $i=>$val){
                $k = $map[$i] ?? null; if(!$k) continue;
                $k = $alias[$k] ?? $k;
                $val = $toUtf8((string)$val);

                switch ($k) {
                    case 'NUMDOC':         $data['numdoc']        = $val; break;
                    case 'OPERACION':      $data['operacion']      = $val; break;
                    case 'CUENTA':         $data['cuenta']         = $val; break;
                    case 'NOMBRE':         $data['nombre']         = $val; break;
                    case 'PRODUCTO':       $data['producto']       = $val; break;
                    case 'DPTO':           $data['dpto']           = $val; break;
                    case 'PROVINCIA':      $data['provincia']      = $val; break;
                    case 'DISTRITO':       $data['distrito']       = $val; break;
                    case 'DIRECCION':      $data['direccion']      = $val; break;
                    case 'ENTIDAD':        $data['entidad']        = $val; break;
                    case 'COSECHA':        $data['cosecha']        = $val; break;
                    case 'FECHA_COMPRA':   $data['fecha_compra']   = $date($val); break;
                    case 'FECHA_CASTIGO':  $data['fecha_castigo']  = $date($val); break;
                    case 'DEUDA_CAPITAL':  $data['deuda_capital']  = $num($val); break;
                    case 'INTERES':        $data['interes']        = $num($val); break;
                    case 'DEUDA_TOTAL':    $data['deuda_total']    = $num($val); break;
                }
            }

            // Clave mínima requerida
            if (empty($data['numdoc']) || empty($data['operacion'])) {
                $skip++; $err[]="Fila {$rowNum}: faltan NUMDOC u OPERACION."; continue;
            }

            try {
                ClienteCuenta::updateOrCreate(
                    ['numdoc' => $data['numdoc'], 'operacion' => $data['operacion']],
                    $data
                );
                $ok++;
            } catch(\Throwable $e){
                $skip++; $err[]="Fila {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);
        return [$ok,$skip,$err];
    }
}
