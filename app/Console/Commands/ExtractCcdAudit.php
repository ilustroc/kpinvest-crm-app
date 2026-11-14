<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdExtractionMatcher;
use App\Services\Ccd\CcdPdfIdentifierExtractor;
use App\Services\Ccd\CcdPdfSourceResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExtractCcdAudit extends Command
{
    protected $signature = 'ccd:extract-audit
        {--ccd-id= : ID exacto de ccd_clientes a revisar}
        {--dni= : Limita la auditoría a un documento}
        {--after-id=0 : Reanuda desde el ID de ccd_clientes indicado}
        {--limit=100 : Máximo de CCD a procesar, entre 1 y 100}
        {--output= : Nombre del CSV privado, sin ruta}';

    protected $description = 'Extrae identificadores de PDFs CCD en modo de auditoría de sólo lectura';

    public function handle(
        CcdPdfSourceResolver $sources,
        CcdPdfIdentifierExtractor $extractor,
        CcdExtractionMatcher $matcher,
    ): int {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        $ccdRows = $this->query($options)->get();

        if ($ccdRows->isEmpty()) {
            $this->warn('No se encontraron CCD para los filtros indicados. No se escribió ningún registro ni CSV.');

            return self::SUCCESS;
        }

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $output = 'audits/'.$options['output'];

        if ($disk->exists($output)) {
            $this->error('El CSV privado indicado ya existe; elija otro nombre para no sobrescribirlo.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $path = $disk->path($temporary);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('No se pudo preparar el directorio privado para el CSV de auditoría.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('No se pudo crear el CSV privado de auditoría.');

            return self::FAILURE;
        }

        $counts = [
            'procesados' => 0,
            'coincidencia_exacta' => 0,
            'coincidencia_cuenta' => 0,
            'ambigua' => 0,
            'sin_coincidencia' => 0,
            'sin_texto' => 0,
            'error' => 0,
        ];

        try {
            fputcsv($handle, [
                'ccd_id',
                'numdoc',
                'cosecha',
                'fuente',
                'dnis_extraidos',
                'cuentas_extraidas',
                'operaciones_extraidas',
                'entidades_extraidas',
                'operacion_candidata',
                'clasificacion',
                'evidencia',
            ]);

            foreach ($ccdRows as $ccd) {
                $row = $this->audit($ccd, $sources, $extractor, $matcher);
                $counts['procesados']++;
                $counts[$row['clasificacion']]++;

                fputcsv($handle, array_map([$this, 'safeCsv'], [
                    $ccd->id,
                    $ccd->numdoc,
                    $ccd->cosecha,
                    $row['source'],
                    implode('|', $row['dnis']),
                    implode('|', $row['cuentas']),
                    implode('|', $row['operaciones']),
                    implode('|', $row['entidades']),
                    $row['operacion_candidata'],
                    $row['clasificacion'],
                    $row['evidence'],
                ]));
            }
        } catch (Throwable $exception) {
            report($exception);
            fclose($handle);
            $disk->delete($temporary);
            $this->error('La auditoría no pudo completar el CSV privado. Revise el log de la aplicación.');

            return self::FAILURE;
        }

        fclose($handle);
        $disk->move($temporary, $output);

        $this->table(['Métrica', 'Cantidad'], [
            ['CCD procesados', $counts['procesados']],
            ['Coincidencias exactas', $counts['coincidencia_exacta']],
            ['Coincidencias por cuenta', $counts['coincidencia_cuenta']],
            ['Ambiguos', $counts['ambigua']],
            ['Sin coincidencia', $counts['sin_coincidencia']],
            ['Sin texto', $counts['sin_texto']],
            ['Errores técnicos', $counts['error']],
        ]);
        $this->info("CSV privado generado: {$output}");
        $this->comment('La auditoría no insertó, actualizó ni eliminó asociaciones, CCD o PDFs.');

        return self::SUCCESS;
    }

    /** @param array{ccd_id:?int,dni:?string,after_id:int,limit:int,output:string} $options */
    private function query(array $options)
    {
        $query = CcdCliente::query()
            ->orderBy('id')
            ->select(['id', 'numdoc', 'pdf', 'cosecha', 'ccd_archivo_id']);

        if ($options['ccd_id'] !== null) {
            $query->whereKey($options['ccd_id']);
        } else {
            $query->where('id', '>', $options['after_id']);
        }

        if ($options['dni'] !== null) {
            $query->where('numdoc', $options['dni']);
        }

        return $query->limit($options['limit']);
    }

    /** @return array{source:string,dnis:array<int,string>,cuentas:array<int,string>,operaciones:array<int,string>,entidades:array<int,string>,operacion_candidata:?string,clasificacion:string,evidence:string} */
    private function audit(
        CcdCliente $ccd,
        CcdPdfSourceResolver $sources,
        CcdPdfIdentifierExtractor $extractor,
        CcdExtractionMatcher $matcher,
    ): array {
        $source = $sources->resolve($ccd);

        if ($source['status'] !== 'ok' || $source['path'] === null) {
            return [
                'source' => $source['source'] ?? 'no_disponible',
                'dnis' => [],
                'cuentas' => [],
                'operaciones' => [],
                'entidades' => [],
                'operacion_candidata' => null,
                'clasificacion' => 'error',
                'evidence' => $source['evidence'],
            ];
        }

        $extraction = $extractor->extract($source['path']);
        $match = $matcher->classify($ccd, $extraction);

        return [
            'source' => (string) $source['source'],
            'dnis' => $extraction['dnis'],
            'cuentas' => $extraction['cuentas'],
            'operaciones' => $extraction['operaciones'],
            'entidades' => $extraction['entidades'],
            'operacion_candidata' => $match['operacion_candidata'],
            'clasificacion' => $match['clasificacion'],
            'evidence' => $match['evidence'],
        ];
    }

    /** @return array{ccd_id:?int,dni:?string,after_id:int,limit:int,output:string}|null */
    private function validatedOptions(): ?array
    {
        $ccdId = $this->positiveIntegerOption('ccd-id');
        $afterId = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);
        $limit = $this->positiveIntegerOption('limit');
        $requestedOutput = trim((string) $this->option('output'));

        if ($afterId === false || $afterId < 0 || $limit === null || $limit > 100) {
            $this->error('--after-id debe ser un entero no negativo y --limit debe estar entre 1 y 100.');

            return null;
        }

        if ($this->option('ccd-id') !== null && $ccdId === null) {
            $this->error('--ccd-id debe ser un entero positivo.');

            return null;
        }

        if ($requestedOutput !== '' && $requestedOutput !== basename($requestedOutput)) {
            $this->error('--output debe ser un nombre de archivo CSV simple, sin directorios.');

            return null;
        }

        $output = $requestedOutput === ''
            ? 'ccd-extract-audit-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.csv'
            : $requestedOutput;

        if (! str_ends_with(strtolower($output), '.csv') || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/', $output)) {
            $this->error('--output debe ser un nombre de archivo CSV simple, sin directorios.');

            return null;
        }

        return [
            'ccd_id' => $ccdId,
            'dni' => trim((string) $this->option('dni')) ?: null,
            'after_id' => $afterId,
            'limit' => $limit,
            'output' => $output,
        ];
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : null;
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
