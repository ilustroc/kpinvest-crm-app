<?php

namespace App\Exports;

use App\Support\ExportSanitizer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssignmentExport
{
    public function __construct(private readonly mixed $query)
    {
    }

    public function export(): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $headers = ['ID', 'Fecha solicitud', 'Documento', 'Cliente', 'Operación', 'Solicitante', 'Asesor destino', 'Motivo', 'Origen', 'Estado', 'Usuario que resolvió', 'Fecha resolución'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;

        $this->query->chunkByIdDesc(1000, function ($items) use (&$row, $sheet): void {
            foreach ($items as $item) {
                $sheet->fromArray([
                    (int) $item->id,
                    ExportSanitizer::sanitize((string) $item->fecha_solicitud),
                    ExportSanitizer::sanitize((string) $item->numdoc),
                    ExportSanitizer::sanitize((string) $item->cliente),
                    ExportSanitizer::sanitize((string) $item->operacion),
                    ExportSanitizer::sanitize((string) $item->solicitante),
                    ExportSanitizer::sanitize((string) $item->asesor_destino),
                    ExportSanitizer::sanitize((string) $item->motivo),
                    ExportSanitizer::sanitize((string) $item->origen),
                    ExportSanitizer::sanitize((string) $item->estado),
                    ExportSanitizer::sanitize((string) $item->resuelto_por),
                    ExportSanitizer::sanitize((string) $item->fecha_aprobacion),
                ], null, "A{$row}");
                $row++;
            }
        }, 'ac.id', 'id');

        $path = tempnam(sys_get_temp_dir(), 'asignaciones_');
        (new Xlsx($sheet->getParent()))->save($path);

        return $path;
    }
}
