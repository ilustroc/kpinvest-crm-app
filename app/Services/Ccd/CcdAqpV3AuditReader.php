<?php

namespace App\Services\Ccd;

use DomainException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class CcdAqpV3AuditReader
{
    private const REQUIRED_COLUMNS = [
        'ccd_id',
        'clasificacion_anterior',
        'motivo_anterior',
        'clasificacion_nueva',
        'resultado_comparacion',
        'motivo_nuevo',
        'evidencia_operacion',
        'evidencia_dni',
        'fuente',
        'cosecha',
    ];

    /**
     * @param  array<int,string>  $inputs
     * @return array{
     *   candidates:array<int,array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}>,
     *   reports:array<int,array{name:string,sha256:string,rows:int}>,
     *   total:int,
     *   counts:array{AQP4:int,AQP5:int}
     * }
     */
    public function read(array $inputs): array
    {
        if ($inputs === []) {
            throw new DomainException('Debe indicar al menos un CSV de auditoría V3.');
        }

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $candidates = [];
        $reports = [];
        $duplicates = [];

        foreach (array_values(array_unique($inputs)) as $input) {
            $this->assertSafeName($input);
            $path = 'audits/'.$input;

            if (! $disk->exists($path)) {
                throw new DomainException("No existe el CSV privado {$path}.");
            }

            $reportHash = $this->sha256($disk, $path);
            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                throw new DomainException("No se pudo leer el CSV privado {$path}.");
            }

            $reportRows = 0;

            try {
                $header = fgetcsv($stream);

                if (! is_array($header)) {
                    throw new DomainException("El CSV {$path} no contiene encabezados válidos.");
                }

                $header[0] = ltrim((string) ($header[0] ?? ''), "\xEF\xBB\xBF");
                $columns = array_flip($header);

                foreach (self::REQUIRED_COLUMNS as $required) {
                    if (! array_key_exists($required, $columns)) {
                        throw new DomainException("El CSV {$path} no corresponde al esquema V3: falta {$required}.");
                    }
                }

                $line = 1;

                while (($row = fgetcsv($stream)) !== false) {
                    $line++;

                    if ($row === [null] || $row === []) {
                        continue;
                    }

                    $candidate = $this->candidate($row, $columns, $input, $reportHash, $line);
                    $id = $candidate['ccd_id'];

                    if (isset($candidates[$id])) {
                        $duplicates[$id] = true;

                        continue;
                    }

                    $candidates[$id] = $candidate;
                    $reportRows++;
                }
            } finally {
                fclose($stream);
            }

            $reports[] = [
                'name' => $input,
                'sha256' => $reportHash,
                'rows' => $reportRows,
            ];
        }

        if ($duplicates !== []) {
            throw new DomainException('La auditoría V3 contiene CCD duplicadas entre los CSV proporcionados.');
        }

        ksort($candidates);
        $counts = ['AQP4' => 0, 'AQP5' => 0];

        foreach ($candidates as $candidate) {
            $counts[$candidate['cosecha']]++;
        }

        return [
            'candidates' => $candidates,
            'reports' => $reports,
            'total' => count($candidates),
            'counts' => $counts,
        ];
    }

    /**
     * @param  array<int,string|null>  $row
     * @param  array<string,int>  $columns
     * @return array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}
     */
    private function candidate(array $row, array $columns, string $input, string $reportHash, int $line): array
    {
        $value = static fn (string $column): string => trim((string) ($row[$columns[$column]] ?? ''));
        $id = filter_var($value('ccd_id'), FILTER_VALIDATE_INT);
        $cosecha = strtoupper($value('cosecha'));
        $valid = $id !== false && $id > 0
            && in_array($cosecha, ['AQP4', 'AQP5'], true)
            && $value('clasificacion_anterior') === 'revision'
            && $value('motivo_anterior') === 'dni_documental_no_corrobable'
            && $value('clasificacion_nueva') === 'elegible'
            && $value('resultado_comparacion') === 'ahora_elegible'
            && $value('motivo_nuevo') === 'operacion_y_codigo_cliente_aqp_documentales_verificados'
            && str_starts_with($value('evidencia_operacion'), 'operacion_')
            && $value('evidencia_dni') === CcdDocumentDniRecognizer::AQP_CODE_VERIFIED
            && $value('fuente') === 'privado';

        if (! $valid) {
            throw new DomainException("El CSV audits/{$input} contiene una fila que no es una elegible AQP V3 válida (línea {$line}).");
        }

        return [
            'ccd_id' => (int) $id,
            'cosecha' => $cosecha,
            'source_report' => $input,
            'source_sha256' => $reportHash,
        ];
    }

    private function assertSafeName(string $input): void
    {
        if ($input !== basename($input) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $input)) {
            throw new DomainException('Cada CSV V3 debe indicarse mediante un nombre simple dentro de audits/.');
        }
    }

    private function sha256(FilesystemAdapter $disk, string $path): string
    {
        $absolute = $disk->path($path);
        $hash = is_file($absolute) ? hash_file('sha256', $absolute) : false;

        if (! is_string($hash)) {
            throw new DomainException("No se pudo calcular la huella del CSV privado {$path}.");
        }

        return $hash;
    }
}
