<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CcdCliente;

class IntegracionCcdController extends Controller
{
    // ======= Descargar plantilla =======
    public function template()
    {
        $headers = ['CODIGO','DNI','NOMBRE','CARTERA','PDF'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $headers);
            fputcsv($out, ['ABC123','71234567','JUAN PEREZ','CARTERA 1','https://dominio.com/archivos/juan.pdf']);
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
        $fh = fopen($filepath,'r');
        if(!$fh) return [0,0,['No se pudo abrir el archivo']];

        $first = fgets($fh);
        if($first===false){ fclose($fh); return [0,0,['Archivo vacío']]; }
        $del = (substr_count($first,';') > substr_count($first,',')) ? ';' : ',';
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if(!$headers){ fclose($fh); return [0,0,['Sin encabezados']]; }

        // --- Normalización de encabezados ---
        $norm = fn(string $s): string =>
            trim(preg_replace('/[^A-Z0-9]+/','_',strtr(strtoupper(trim(
                str_replace(["\xEF\xBB\xBF","\xC2\xA0"],' ',$s)
            )), ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'])), '_');

        $map = [];
        foreach ($headers as $i=>$h) $map[$i] = $norm($h);

        // --- Bucle de filas ---
        $ok=0; $skip=0; $err=[]; $rowNum=1;

        while(($row=fgetcsv($fh,0,$del))!==false){
            $rowNum++; $data=[];

            foreach($row as $i=>$val){
                $k = $map[$i] ?? null; if(!$k) continue;
                $val = trim($val);

                switch ($k) {
                    case 'CODIGO':  $data['codigo']  = $val; break;
                    case 'DNI':     $data['dni']     = $val; break;
                    case 'NOMBRE':  $data['nombre']  = $val; break;
                    case 'CARTERA': $data['cartera'] = $val; break;
                    case 'PDF':     $data['pdf']     = $val; break;
                }
            }

            // Validación mínima
            if (empty($data['dni'])) {
                $skip++; $err[]="Fila {$rowNum}: falta DNI."; continue;
            }

            try {
                CcdCliente::updateOrCreate(
                    ['dni' => $data['dni']],
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

    // ======= Vista principal =======
    public function index()
    {
        return view('placeholders.integracion-ccd');
    }
}
