<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdPrivateImportService;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BenchmarkPrivateCcd extends Command
{
    protected $signature = 'ccd:benchmark-private
        {source : Carpeta local dentro de la raíz de importación autorizada}
        {--cosecha= : Cosecha exacta a evaluar}
        {--sample=100 : Cantidad máxima de PDF de la muestra determinista}
        {--measure-copy : Medir una copia efímera a almacenamiento temporal}';

    protected $description = 'Benchmark de solo lectura para el importador CCD privado';

    public function handle(CcdPrivateImportService $importer): int
    {
        $cosecha = (string) $this->option('cosecha');
        $sampleSize = filter_var($this->option('sample'), FILTER_VALIDATE_INT);

        if ($cosecha === '' || $sampleSize === false || $sampleSize < 1 || $sampleSize > 1000) {
            $this->error('Indique --cosecha y una muestra entre 1 y 1000.');

            return self::FAILURE;
        }

        $queryCount = 0;
        $queryTimeMs = 0.0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += $query->time;
        });

        try {
            $inventoryStarted = hrtime(true);
            $files = $this->resolveFiles((string) $this->argument('source'));
            $inventoryMs = $this->elapsedMs($inventoryStarted);
            $sample = $this->deterministicSample($files, $sampleSize);

            $databaseStarted = hrtime(true);
            $records = CcdCliente::query()
                ->where('cosecha', $cosecha)
                ->get(['id', 'pdf', 'cosecha', 'ccd_archivo_id'])
                ->filter(fn (CcdCliente $ccd): bool => (string) $ccd->cosecha === $cosecha)
                ->values();
            $databaseInventoryMs = $this->elapsedMs($databaseStarted);
        } catch (Throwable) {
            $this->error('No se pudo construir el inventario de benchmark.');

            return self::FAILURE;
        }

        $recordsByName = $records->groupBy(fn (CcdCliente $ccd): string => (string) $ccd->pdf);
        $exactMatches = 0;
        $validationMs = 0.0;
        $hashMs = 0.0;
        $copyMs = 0.0;
        $bytes = 0;
        $validationErrors = 0;

        foreach ($sample as $path) {
            if (($recordsByName->get(basename($path))?->count() ?? 0) === 1) {
                $exactMatches++;
            }

            try {
                $validationStarted = hrtime(true);
                $size = filesize($path);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
                $header = file_get_contents($path, false, null, 0, 5);
                $validationMs += $this->elapsedMs($validationStarted);

                if ($size === false || $size < 1 || $mime !== 'application/pdf' || $header !== '%PDF-') {
                    throw new RuntimeException('PDF inválido.');
                }

                $bytes += $size;
                $hashStarted = hrtime(true);
                $hash = hash_file('sha256', $path);
                $hashMs += $this->elapsedMs($hashStarted);

                if ($hash === false) {
                    throw new RuntimeException('No se pudo calcular SHA-256.');
                }

                if ((bool) $this->option('measure-copy')) {
                    $copyStarted = hrtime(true);
                    $this->copyToTemporaryStream($path, $size);
                    $copyMs += $this->elapsedMs($copyStarted);
                }
            } catch (Throwable) {
                $validationErrors++;
            }
        }

        $pipelineQueriesBefore = $queryCount;
        $pipelineQueryTimeBefore = $queryTimeMs;
        $pipelineErrors = 0;
        $pipelineStarted = hrtime(true);

        foreach ($sample as $path) {
            try {
                $importer->process($path, $cosecha, false);
            } catch (Throwable) {
                $pipelineErrors++;
            }
        }

        $pipelineMs = $this->elapsedMs($pipelineStarted);
        $pipelineQueries = $queryCount - $pipelineQueriesBefore;
        $pipelineSqlMs = $queryTimeMs - $pipelineQueryTimeBefore;
        $sampleCount = count($sample);
        $mebibytes = $bytes / 1_048_576;

        $this->table(['Métrica agregada', 'Resultado'], [
            ['PDF en el origen', count($files)],
            ['Registros de la cosecha', $records->count()],
            ['PDF en la muestra', $sampleCount],
            ['Coincidencias exactas', $exactMatches],
            ['Errores de validación', $validationErrors],
            ['Errores del pipeline actual', $pipelineErrors],
            ['Inventario de archivos (ms)', $this->number($inventoryMs)],
            ['Inventario MariaDB (ms)', $this->number($databaseInventoryMs)],
            ['Validación PDF total (ms)', $this->number($validationMs)],
            ['SHA-256 total (ms)', $this->number($hashMs)],
            ['SHA-256 MiB/s', $this->rate($mebibytes, $hashMs)],
            ['Copia temporal total (ms)', $this->option('measure-copy') ? $this->number($copyMs) : 'no medida'],
            ['Copia temporal MiB/s', $this->option('measure-copy') ? $this->rate($mebibytes, $copyMs) : 'no medida'],
            ['Pipeline actual total (ms)', $this->number($pipelineMs)],
            ['Pipeline actual archivos/s', $this->rate($sampleCount, $pipelineMs)],
            ['Consultas SQL del pipeline', $pipelineQueries],
            ['Consultas SQL por documento', $sampleCount > 0 ? $this->number($pipelineQueries / $sampleCount) : '0'],
            ['Tiempo SQL informado (ms)', $this->number($pipelineSqlMs)],
            ['Memoria pico (MiB)', $this->number(memory_get_peak_usage(true) / 1_048_576)],
        ]);
        $this->info('Benchmark completado sin modificar MariaDB ni el almacenamiento CCD privado.');

        return ($validationErrors + $pipelineErrors) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, string> */
    private function resolveFiles(string $source): array
    {
        $root = realpath((string) config('ccd.import_root'));
        $resolved = realpath($source);

        if ($root === false || $resolved === false || ! is_dir($resolved)) {
            throw new RuntimeException('Origen inválido.');
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedSource = rtrim(str_replace('\\', '/', $resolved), '/');

        if ($normalizedSource !== $normalizedRoot && ! str_starts_with($normalizedSource, $normalizedRoot.'/')) {
            throw new RuntimeException('Origen fuera de la raíz autorizada.');
        }

        $files = [];

        foreach (new \DirectoryIterator($resolved) as $entry) {
            if ($entry->isDot() || ! $entry->isFile() || $entry->isLink() || strtolower($entry->getExtension()) !== 'pdf') {
                continue;
            }

            $path = $entry->getRealPath();

            if ($path === false) {
                throw new RuntimeException('No se pudo resolver un PDF.');
            }

            $files[] = $path;
        }

        usort($files, static fn (string $left, string $right): int => strcmp(basename($left), basename($right)));

        return $files;
    }

    /** @param array<int, string> $files
     * @return array<int, string>
     */
    private function deterministicSample(array $files, int $sampleSize): array
    {
        $total = count($files);

        if ($total <= $sampleSize) {
            return $files;
        }

        if ($sampleSize === 1) {
            return [$files[0]];
        }

        $sample = [];

        for ($position = 0; $position < $sampleSize; $position++) {
            $index = (int) floor($position * ($total - 1) / ($sampleSize - 1));
            $sample[] = $files[$index];
        }

        return $sample;
    }

    private function copyToTemporaryStream(string $source, int $expectedSize): void
    {
        $input = fopen($source, 'rb');
        $temporary = tmpfile();

        if ($input === false) {
            if (is_resource($temporary)) {
                fclose($temporary);
            }

            throw new RuntimeException('No se pudo abrir el flujo de origen.');
        }

        if ($temporary === false) {
            fclose($input);

            throw new RuntimeException('No se pudo abrir el flujo temporal.');
        }

        try {
            $copied = stream_copy_to_stream($input, $temporary);

            if ($copied !== $expectedSize) {
                throw new RuntimeException('La copia temporal quedó incompleta.');
            }
        } finally {
            fclose($input);
            fclose($temporary);
        }
    }

    private function elapsedMs(int $started): float
    {
        return (hrtime(true) - $started) / 1_000_000;
    }

    private function rate(float|int $units, float $milliseconds): string
    {
        return $milliseconds > 0 ? $this->number($units / ($milliseconds / 1000)) : '0';
    }

    private function number(float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
