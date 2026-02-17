<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PromiseExport
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
            'Tipo_Neg','Entidad','Fecha','Cliente','Telefono','Nrodoc','Negociador','Estado',
            'Operacion','Moneda','Deuda_Act','Capital_Act','Cuotas','Fec_Pag','Pago_Ini','Glosa_Neg'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $this->query->orderByDesc('pp_created_at')->chunk(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $item) {
                $sheet->fromArray([
                    (string)$item->tipo_neg, (string)$item->entidad, (string)$item->fecha,
                    (string)$item->cliente, (string)$item->telefono, (string)$item->nrodoc,
                    (string)$item->negociador, (string)$item->estado, (string)$item->operacion,
                    (string)$item->moneda, (float)$item->deuda_act, (float)$item->capital_act,
                    (int)$item->cuotas, (string)$item->fec_pag, (float)$item->pago_ini, (string)$item->glosa_neg
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("F{$row}", (string)$item->nrodoc, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("I{$row}", (string)$item->operacion, DataType::TYPE_STRING);
                $row++;
            }
        });

        foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        (new Xlsx($xlsx))->save($tmp);
        return $tmp;
    }
}