<?php

namespace App\Exports;

use App\Support\ExportSanitizer;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            'Fecha', 'Documento', 'Nombre', 'Operacion', 'Monto',
            'Agente', 'Cosecha', 'Cuenta_Recaudo', 'Entidad Financiera',
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $this->query->orderBy('id')->chunkById(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $item) {
                $doc = (string) ($item->numdoc ?? $item->dni);
                $sheet->fromArray([
                    ExportSanitizer::sanitize($this->formatDate($item->fecha)),
                    ExportSanitizer::sanitize($doc),
                    ExportSanitizer::sanitize((string) $item->nombre_cliente),
                    ExportSanitizer::sanitize((string) $item->operacion),
                    (float) $item->monto_pagado,
                    ExportSanitizer::sanitize((string) $item->gestor),
                    ExportSanitizer::sanitize((string) $item->cosecha),
                    ExportSanitizer::sanitize((string) $item->cuenta_recaudo),
                    ExportSanitizer::sanitize((string) $item->entidad),
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("B{$row}", ExportSanitizer::sanitize($doc), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", ExportSanitizer::sanitize((string) $item->operacion), DataType::TYPE_STRING);
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
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y');
        }
        if (empty($v)) {
            return null;
        }
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $v;
        }
    }
}
