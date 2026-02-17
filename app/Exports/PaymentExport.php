<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PaymentExport
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function export()
    {
        $xlsx = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row = 1;

        $headers = [
            'Fecha', 'DNI', 'Nombre', 'Operacion', 'Monto', 
            'Agente', 'Cosecha', 'Cuenta_Recaudo', 'Entidad Financiera'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        // Usamos chunkById para manejar grandes volúmenes de datos
        $this->query->orderBy('id')->chunkById(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $item) {
                $sheet->fromArray([
                    $this->formatDate($item->fecha),
                    (string)$item->dni,
                    $item->nombre_cliente,
                    (string)$item->operacion,
                    (float)$item->monto_pagado,
                    $item->gestor,
                    $item->cosecha,
                    $item->cuenta_recaudo,
                    $item->entidad,
                ], null, "A{$row}");

                // Formateo explícito de celdas sensibles
                $sheet->setCellValueExplicit("B{$row}", (string)$item->dni, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", (string)$item->operacion, DataType::TYPE_STRING);
                $row++;
            }
        });

        // Auto-ajustar columnas
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        for ($c = 'A'; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_pagos_');
        (new Xlsx($xlsx))->save($tmp);
        
        return $tmp;
    }

    private function formatDate($v)
    {
        if ($v instanceof \DateTimeInterface) return $v->format('d/m/Y');
        if (empty($v)) return null;
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $v;
        }
    }
}