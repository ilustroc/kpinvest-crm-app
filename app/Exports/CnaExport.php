<?php

namespace App\Exports;

use App\Support\ExportSanitizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CnaExport
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
            'Id', 'Documento', 'Cliente', 'Entidad', 'Cna_Nro', 'Cna_Fec', 'Fondo_Inv', 'Año_Mes',
            'Cna_Imp', 'Nro_Cuenta', 'Nro_Operación', 'Gestor', 'Estado', 'Gen_Gestor', 'Apr_Gestor',
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $this->query->chunkByIdDesc(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $r) {
                $sheet->fromArray([
                    (int) $r->id,
                    ExportSanitizer::sanitize((string) $r->documento),
                    ExportSanitizer::sanitize((string) $r->cliente),
                    ExportSanitizer::sanitize((string) $r->entidad),
                    ExportSanitizer::sanitize((string) $r->cna_nro),
                    ExportSanitizer::sanitize((string) $r->cna_fec),
                    ExportSanitizer::sanitize((string) $r->fondo_inv),
                    ExportSanitizer::sanitize((string) $r->anio_mes),
                    $r->cna_imp !== null ? (float) $r->cna_imp : '',
                    ExportSanitizer::sanitize((string) $r->nro_cuenta),
                    ExportSanitizer::sanitize((string) $r->nro_operacion),
                    ExportSanitizer::sanitize((string) $r->gestor),
                    ExportSanitizer::sanitize((string) $r->estado),
                    ExportSanitizer::sanitize((string) $r->gen_gestor),
                    ExportSanitizer::sanitize((string) $r->apr_gestor),
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("B{$row}", ExportSanitizer::sanitize((string) $r->documento), DataType::TYPE_STRING);
                $row++;
            }
        }, 'cna.id', 'id');

        foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cna_');
        (new Xlsx($xlsx))->save($tmp);

        return $tmp;
    }
}
