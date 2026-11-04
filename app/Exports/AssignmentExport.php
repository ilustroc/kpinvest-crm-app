<?php

namespace App\Exports;

use App\Support\ExportSanitizer;
use Illuminate\Database\Query\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssignmentExport
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function export(): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $headers = ['ID', 'Fecha solicitud', 'Documento', 'Cliente', 'Operación', 'Solicitante', 'Asesor destino', 'Motivo', 'Origen', 'Estado', 'Usuario que resolvió', 'Fecha resolución'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;

        foreach ($this->query->cursor() as $item) {
            $documento = ExportSanitizer::sanitize((string) $item->numdoc);
            $operacion = ExportSanitizer::sanitize((string) $item->operacion);

            $sheet->fromArray([
                (int) $item->id,
                ExportSanitizer::sanitize((string) ($item->fecha_solicitud ?: 'Histórico')),
                $documento,
                ExportSanitizer::sanitize((string) $item->cliente),
                $operacion,
                ExportSanitizer::sanitize((string) $item->solicitante),
                ExportSanitizer::sanitize((string) $item->asesor_destino),
                ExportSanitizer::sanitize((string) $item->motivo),
                ExportSanitizer::sanitize((string) $item->origen),
                ExportSanitizer::sanitize((string) $item->estado),
                ExportSanitizer::sanitize((string) $item->resuelto_por),
                ExportSanitizer::sanitize((string) ($item->fecha_aprobacion ?: 'Sin información')),
            ], null, "A{$row}");
            $sheet->setCellValueExplicit("C{$row}", $documento, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$row}", $operacion, DataType::TYPE_STRING);
            $row++;
        }

        $path = tempnam(sys_get_temp_dir(), 'asignaciones_');
        (new Xlsx($sheet->getParent()))->save($path);

        return $path;
    }
}
