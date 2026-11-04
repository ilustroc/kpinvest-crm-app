<?php

namespace App\Exports;

use App\Support\ExportSanitizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            'Tipo_Neg', 'Entidad', 'Fecha', 'Cliente', 'Telefono', 'Nrodoc', 'Negociador', 'Estado',
            'Operacion', 'Moneda', 'Deuda_Act', 'Capital_Act', 'Cuotas', 'Fec_Pag', 'Pago_Ini', 'Glosa_Neg',
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $this->query->orderByDesc('pp_created_at')->chunk(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $item) {
                $sheet->fromArray([
                    ExportSanitizer::sanitize((string) $item->tipo_neg),
                    ExportSanitizer::sanitize((string) $item->entidad),
                    ExportSanitizer::sanitize((string) $item->fecha),
                    ExportSanitizer::sanitize((string) $item->cliente),
                    ExportSanitizer::sanitize((string) $item->telefono),
                    ExportSanitizer::sanitize((string) $item->nrodoc),
                    ExportSanitizer::sanitize((string) $item->negociador),
                    ExportSanitizer::sanitize((string) $item->estado),
                    ExportSanitizer::sanitize((string) $item->operacion),
                    ExportSanitizer::sanitize((string) $item->moneda),
                    (float) $item->deuda_act,
                    (float) $item->capital_act,
                    (int) $item->cuotas,
                    ExportSanitizer::sanitize((string) $item->fec_pag),
                    (float) $item->pago_ini,
                    ExportSanitizer::sanitize((string) $item->glosa_neg),
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("F{$row}", ExportSanitizer::sanitize((string) $item->nrodoc), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("I{$row}", ExportSanitizer::sanitize((string) $item->operacion), DataType::TYPE_STRING);
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
