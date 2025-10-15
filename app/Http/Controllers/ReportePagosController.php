<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\PagoPropia as Pago;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReportePagosController extends Controller
{
    public function index(Request $r)
    {
        $from   = $r->query('from');
        $to     = $r->query('to');
        $gestor = $r->query('gestor');
        $q      = $r->query('q');

        $rows = $this->buildQuery($from, $to, $gestor, $q)
            ->orderByDesc('fecha')
            ->paginate(10)
            ->withQueryString();

        return view('reportes.pagos', compact('rows','from','to','gestor','q'));
    }

    public function export(Request $r)
    {
        $from   = $r->query('from');
        $to     = $r->query('to');
        $gestor = $r->query('gestor');
        $q      = $r->query('q');

        $query    = $this->buildQuery($from, $to, $gestor, $q);
        $filename = 'pagos_'.now()->format('Ymd_His').'.xlsx';

        $xlsx  = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row   = 1;

        // Encabezados nuevos
        $headers = [
            'Fecha','DNI','Nombre','Operacion','Monto','Agente','Cosecha','Cuenta_Recaudo','Entidad Financiera'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $fmtDate = static function($v){
            if ($v instanceof \DateTimeInterface) return $v->format('d/m/Y');
            if (empty($v)) return null;
            try { return \Carbon\Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable $e) { return null; }
        };

        (clone $query)->orderBy('id')->chunkById(1000, function ($items) use (&$row, $sheet, $fmtDate) {
            foreach ($items as $r) {
                $sheet->fromArray([
                    $fmtDate($r->fecha),
                    (string)$r->dni,
                    $r->nombre_cliente,
                    (string)$r->operacion,
                    (float)$r->monto_pagado,
                    $r->gestor,
                    $r->cosecha,
                    $r->cuenta_recaudo,
                    $r->entidad,
                ], null, "A{$row}");

                // Forzar texto en DNI y Operacion
                $sheet->setCellValueExplicit("B{$row}", (string)$r->dni,       DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", (string)$r->operacion, DataType::TYPE_STRING);
                $row++;
            }
        }, 'id');

        // Auto-size columnas
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c = 'A'; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        (new Xlsx($xlsx))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Filtros para el reporte (campos nuevos)
     */
    private function buildQuery(?string $from, ?string $to, ?string $gestor, ?string $q)
    {
        $qb = Pago::query();

        if ($q) {
            $qb->where(function($qq) use ($q){
                $qq->where('dni','like',"%{$q}%")
                   ->orWhere('operacion','like',"%{$q}%")
                   ->orWhere('nombre_cliente','like',"%{$q}%")
                   ->orWhere('entidad','like',"%{$q}%")
                   ->orWhere('cosecha','like',"%{$q}%")
                   ->orWhere('cuenta_recaudo','like',"%{$q}%");
            });
        }

        if ($from)   $qb->whereDate('fecha','>=',$from);
        if ($to)     $qb->whereDate('fecha','<=',$to);
        if ($gestor) $qb->where('gestor','like',"%{$gestor}%");

        return $qb;
    }

    private function fmtDate($v): ?string
    {
        if (!$v) return null;
        if ($v instanceof Carbon) return $v->format('Y-m-d');
        try { return Carbon::parse((string)$v)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }
}
