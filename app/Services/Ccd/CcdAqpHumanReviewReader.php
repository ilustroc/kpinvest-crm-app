<?php

namespace App\Services\Ccd;

use DomainException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class CcdAqpHumanReviewReader
{
    public const APPROVED_FILENAME = 'ccd-aqp-revision-humana-20261118-211134-aprobada.csv';

    public const APPROVED_SHA256 = '2ebbfa12df1e0dad537c8fdb57d60075c5b4cb995b97d71d56b4e67c9cb80942';

    public const APPROVED_TOTAL = 40;

    public const APPROVED_AQP4 = 20;

    public const APPROVED_AQP5 = 20;

    public const APPROVED_PER_STRATUM = 5;

    private const REQUIRED_COLUMNS = [
        'ccd_id',
        'cosecha',
        'tramo_historico',
        'auditoria_v3_sha256',
        'identidad_revisada',
        'operacion_revisada',
        'resultado_manual',
        'observacion',
    ];

    private const NEUTRAL_OBSERVATIONS = ['', 'SIN OBSERVACIONES', 'NINGUNA', 'OK'];

    /**
     * @param  array{candidates:array<int,array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}>}  $audit
     * @return array{
     *   approved:array<int,array{ccd_id:int,cosecha:string,stratum:string,audit_sha256:string,human_review_sha256:string}>,
     *   sha256:string,
     *   total:int,
     *   counts:array{AQP4:int,AQP5:int},
     *   strata:array<string,int>
     * }
     */
    public function read(
        string $filename,
        string $expectedSha256,
        array $audit,
        int $expectedTotal,
        int $expectedAqp4,
        int $expectedAqp5,
        int $expectedPerStratum,
    ): array {
        $this->assertSafeName($filename);

        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)) {
            throw new DomainException('La huella esperada de revisión humana es inválida.');
        }

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $path = 'audits/'.$filename;

        if (! $disk->exists($path)) {
            throw new DomainException("No existe el CSV privado de revisión humana {$path}.");
        }

        $actualSha256 = $this->sha256($disk, $path);

        if (! hash_equals($expectedSha256, $actualSha256)) {
            throw new DomainException('El SHA-256 del archivo de revisión humana no coincide con el aprobado.');
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new DomainException('No se pudo leer el CSV privado de revisión humana.');
        }

        $approved = [];
        $counts = ['AQP4' => 0, 'AQP5' => 0];
        $strata = [];

        try {
            $header = fgetcsv($stream);

            if (! is_array($header)) {
                throw new DomainException('El CSV de revisión humana no contiene encabezados válidos.');
            }

            $header[0] = ltrim((string) ($header[0] ?? ''), "\xEF\xBB\xBF");
            $columns = array_flip($header);

            foreach (self::REQUIRED_COLUMNS as $required) {
                if (! array_key_exists($required, $columns)) {
                    throw new DomainException("El CSV de revisión humana no contiene la columna {$required}.");
                }
            }

            $line = 1;

            while (($row = fgetcsv($stream)) !== false) {
                $line++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                $value = static fn (string $column): string => trim((string) ($row[$columns[$column]] ?? ''));
                $id = filter_var($value('ccd_id'), FILTER_VALIDATE_INT);
                $harvest = strtoupper($value('cosecha'));
                $stratum = strtoupper($value('tramo_historico'));
                $auditSha256 = strtolower($value('auditoria_v3_sha256'));
                $observation = $this->normalizeText($value('observacion'));

                if ($id === false || $id < 1 || ! in_array($harvest, ['AQP4', 'AQP5'], true)
                    || ! in_array($stratum, ['Q1', 'Q2', 'Q3', 'Q4'], true)
                    || $this->normalizeText($value('identidad_revisada')) !== 'SI'
                    || $this->normalizeText($value('operacion_revisada')) !== 'SI'
                    || $this->normalizeText($value('resultado_manual')) !== 'APROBADO'
                    || ! in_array($observation, self::NEUTRAL_OBSERVATIONS, true)) {
                    throw new DomainException("La revisión humana contiene una fila no aprobada o inconsistente (línea {$line}).");
                }

                if (isset($approved[(int) $id])) {
                    throw new DomainException('La revisión humana contiene CCD duplicadas.');
                }

                $candidate = $audit['candidates'][(int) $id] ?? null;

                if ($candidate === null || $candidate['cosecha'] !== $harvest
                    || ! hash_equals($candidate['source_sha256'], $auditSha256)) {
                    throw new DomainException("La revisión humana no corresponde a la auditoría V3 original (línea {$line}).");
                }

                $approved[(int) $id] = [
                    'ccd_id' => (int) $id,
                    'cosecha' => $harvest,
                    'stratum' => $stratum,
                    'audit_sha256' => $auditSha256,
                    'human_review_sha256' => $actualSha256,
                ];
                $counts[$harvest]++;
                $strata[$harvest.'_'.$stratum] = ($strata[$harvest.'_'.$stratum] ?? 0) + 1;
            }
        } finally {
            fclose($stream);
        }

        if (count($approved) !== $expectedTotal || $counts['AQP4'] !== $expectedAqp4 || $counts['AQP5'] !== $expectedAqp5
            || $expectedAqp4 + $expectedAqp5 !== $expectedTotal) {
            throw new DomainException('La revisión humana no coincide con la distribución total aprobada.');
        }

        foreach (['AQP4', 'AQP5'] as $harvest) {
            foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $stratum) {
                if (($strata[$harvest.'_'.$stratum] ?? 0) !== $expectedPerStratum) {
                    throw new DomainException('La revisión humana no contiene la distribución aprobada por cuartil.');
                }
            }
        }

        ksort($approved);

        return [
            'approved' => $approved,
            'sha256' => $actualSha256,
            'total' => count($approved),
            'counts' => $counts,
            'strata' => $strata,
        ];
    }

    private function normalizeText(string $value): string
    {
        $normalized = strtr(mb_strtoupper(trim($value)), [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ]);

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    private function assertSafeName(string $filename): void
    {
        if ($filename !== basename($filename) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $filename)) {
            throw new DomainException('El archivo de revisión humana debe ser un nombre CSV simple dentro de audits/.');
        }
    }

    private function sha256(FilesystemAdapter $disk, string $path): string
    {
        $absolute = $disk->path($path);
        $hash = is_file($absolute) ? hash_file('sha256', $absolute) : false;

        if (! is_string($hash)) {
            throw new DomainException('No se pudo calcular la huella del archivo de revisión humana.');
        }

        return strtolower($hash);
    }
}
