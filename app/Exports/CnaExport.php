<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CnaExport
{
    protected $query;

    public function __construct($query) { $this->query = $query; }

    public function export()
    {
        $xlsx = new Spreadsheet();
        $sheet = $xlsx->getActiveSheet();
        $row = 1;

        $headers = [
            'Id','Documento','Cliente','Entidad','Cna_Nro','Cna_Fec','Fondo_Inv','Año_Mes',
            'Cna_Imp','Nro_Cuenta','Nro_Operación','Gestor','Estado','Gen_Gestor','Apr_Gestor'
        ];
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $this->query->orderByDesc('id')->chunkById(1000, function ($items) use (&$row, $sheet) {
            foreach ($items as $r) {
                $sheet->fromArray([
                    (int)$r->id, (string)$r->documento, (string)$r->cliente, (string)$r->entidad,
                    (string)$r->cna_nro, (string)$r->cna_fec, (string)$r->fondo_inv, (string)$r->anio_mes,
                    $r->cna_imp !== null ? (float)$r->cna_imp : '', (string)$r->nro_cuenta,
                    (string)$r->nro_operacion, (string)$r->gestor, (string)$r->estado,
                    (string)$r->gen_gestor, (string)$r->apr_gestor
                ], null, "A{$row}");

                $sheet->setCellValueExplicit("B{$row}", (string)$r->documento, DataType::TYPE_STRING);
                $row++;
            }
        }, 'id');

        foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cna_');
        (new Xlsx($xlsx))->save($tmp);
        return $tmp;
    }
}