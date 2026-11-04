<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdImportBatchPlanner;
use App\Services\Ccd\CcdPrivateImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportPrivateCcd extends Command
{
    protected $signature = 'ccd:import-private
        {source : Carpeta local o archivo PDF dentro de la raíz de importación autorizada}
        {--cosecha= : Cosecha exacta que debe coincidir con ccd_clientes}
        {--file= : Nombre exacto de un solo PDF dentro de la carpeta de origen}
        {--batch-size=0 : Cantidad de documentos pendientes que se seleccionarán de forma determinista}
        {--limit=0 : Alias heredado de --batch-size}
        {--apply : Copiar y asociar los documentos seleccionados}
        {--confirm-cosecha= : Confirmación exacta obligatoria de la cosecha al aplicar}';

    protected $description = 'Simula o importa documentos CCD a almacenamiento privado por archivo o por lotes controlados';

    public function handle(CcdPrivateImportService $importer, CcdImportBatchPlanner $planner): int
    {
        $cosecha = (string) $this->option('cosecha');
        $apply = (bool) $this->option('apply');
        $batchSize = $this->batchSize();

        if ($cosecha === '') {
            $this->error('Debe indicar --cosecha.');

            return self::FAILURE;
        }

        if ($batchSize === null) {
            return self::FAILURE;
        }

        if ($apply && ! $this->validateApplyGuards($cosecha, $batchSize)) {
            return self::FAILURE;
        }

        try {
            $allFiles = $this->resolveFiles((string) $this->argument('source'));
            $filesToInspect = $this->filesToInspect($allFiles);
        } catch (Throwable) {
            $this->error('No se pudo validar el origen dentro de la raíz autorizada.');

            return self::FAILURE;
        }

        $records = CcdCliente::query()
            ->where('cosecha', $cosecha)
            ->get(['id', 'pdf', 'cosecha', 'ccd_archivo_id'])
            ->filter(fn (CcdCliente $ccd): bool => (string) $ccd->cosecha === $cosecha)
            ->values();

        $recordsByName = $records->groupBy(fn (CcdCliente $ccd): string => (string) $ccd->pdf);
        $localNames = array_map(static fn (string $path): string => basename($path), $allFiles);
        $localNameCounts = array_count_values($localNames);
        $localWithoutDatabase = 0;

        foreach ($localNameCounts as $name => $count) {
            if (! $recordsByName->has($name)) {
                $localWithoutDatabase += $count;
            }
        }

        $databaseWithoutLocal = $records
            ->filter(fn (CcdCliente $ccd): bool => ! isset($localNameCounts[(string) $ccd->pdf]))
            ->count();
        $duplicateLocalNames = count(array_filter($localNameCounts, static fn (int $count): bool => $count > 1));
        $ambiguousDatabaseNames = $recordsByName
            ->filter(static fn ($matches): bool => $matches->count() > 1)
            ->count();

        $inspections = [];

        foreach ($filesToInspect as $file) {
            $name = basename($file);
            $matches = $recordsByName->get($name);

            if ($matches === null || $matches->isEmpty()) {
                $inspections[] = ['source' => $file, 'status' => 'local_without_database'];

                continue;
            }

            if ($matches->count() !== 1) {
                $inspections[] = ['source' => $file, 'status' => 'conflict'];

                continue;
            }

            try {
                $result = $importer->process($file, $cosecha, false);
                $inspections[] = [
                    'source' => $file,
                    'status' => $result['status'],
                    'sha256' => $result['sha256'],
                ];
            } catch (Throwable) {
                $inspections[] = ['source' => $file, 'status' => 'error'];
            }
        }

        $plan = $planner->plan($inspections, $batchSize);
        $counts = $plan['counts'];
        $externalCritical = $databaseWithoutLocal + $duplicateLocalNames + $ambiguousDatabaseNames;
        $critical = $plan['critical'] + $externalCritical;

        $this->table(['Validación', 'Cantidad'], [
            ['Registros de la cosecha', $records->count()],
            ['PDF encontrados', count($allFiles)],
            ['Ya importados y correctos', $counts['already_imported']],
            ['Pendientes', $plan['pending']],
            ['Nuevos', $counts['would_import']],
            ['Reutilizables', $counts['would_reuse']],
            ['Reparables', $counts['would_repair']],
            ['Conflictos', $counts['conflict'] + $ambiguousDatabaseNames + $duplicateLocalNames],
            ['Errores', $counts['error']],
            ['Archivos locales sin BD', $localWithoutDatabase],
            ['Registros BD sin archivo local', $databaseWithoutLocal],
            ['Contenidos SHA-256 duplicados', $plan['duplicate_hashes']],
            ['Seleccionados para el lote', count($plan['selected'])],
        ]);

        if ($critical > 0) {
            $this->error('Se detectaron inconsistencias críticas. No se realizará ninguna importación.');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->info('Simulación completada sin escribir archivos ni modificar MariaDB.');

            return self::SUCCESS;
        }

        if ($plan['selected'] === []) {
            $this->info('No hay documentos pendientes para el lote solicitado.');

            return self::SUCCESS;
        }

        $applied = [
            'imported' => 0,
            'reused' => 0,
            'repaired' => 0,
            'already_imported' => 0,
        ];

        foreach ($plan['selected'] as $item) {
            try {
                $result = $importer->process($item['source'], $cosecha, true);
                $status = $result['status'];

                if (! array_key_exists($status, $applied)) {
                    throw new \DomainException('El importador devolvió un estado no permitido.');
                }

                $applied[$status]++;
            } catch (Throwable) {
                $this->error('El lote se detuvo ante el primer error. Puede reanudarse de forma idempotente.');

                return self::FAILURE;
            }
        }

        $remaining = CcdCliente::query()
            ->where('cosecha', $cosecha)
            ->whereRaw('BINARY `cosecha` = BINARY ?', [$cosecha])
            ->whereNull('ccd_archivo_id')
            ->count();

        $this->table(['Resultado del lote', 'Cantidad'], [
            ['Importados', $applied['imported']],
            ['Reutilizados', $applied['reused']],
            ['Reparados', $applied['repaired']],
            ['Ya correctos al aplicar', $applied['already_imported']],
            ['Pendientes después del lote', $remaining],
        ]);
        $this->info('Lote completado. Los documentos históricos pdf y link no fueron modificados.');

        return self::SUCCESS;
    }

    private function batchSize(): ?int
    {
        $batch = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT);
        $legacy = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($batch === false || $legacy === false || $batch < 0 || $legacy < 0) {
            $this->error('--batch-size y --limit deben ser enteros no negativos.');

            return null;
        }

        if ($batch > 0 && $legacy > 0) {
            $this->error('Use solamente --batch-size o el alias heredado --limit.');

            return null;
        }

        return $batch > 0 ? $batch : $legacy;
    }

    private function validateApplyGuards(string $cosecha, int $batchSize): bool
    {
        if ((string) $this->option('confirm-cosecha') !== $cosecha) {
            $this->error('La aplicación requiere --confirm-cosecha con el valor exacto de --cosecha.');

            return false;
        }

        $requiredUser = (string) config('ccd.import_user', 'www-data');
        $runningUser = $this->runningUser();

        if ($runningUser === null || ! hash_equals($requiredUser, $runningUser)) {
            $this->error('La importación debe ejecutarse con el usuario de sistema autorizado.');

            return false;
        }

        if ($this->option('file') === null && ($batchSize < 1 || $batchSize > 100)) {
            $this->error('La aplicación por carpeta requiere --batch-size entre 1 y 100.');

            return false;
        }

        if ($this->option('file') !== null && $batchSize > 1) {
            $this->error('--file solo permite un documento por ejecución.');

            return false;
        }

        return true;
    }

    /** @return array<int, string> */
    private function resolveFiles(string $source): array
    {
        $root = realpath((string) config('ccd.import_root'));
        $resolved = realpath($source);

        if ($root === false || $resolved === false) {
            throw new \DomainException('La ruta no existe.');
        }

        $this->assertInsideRoot($resolved, $root);

        if (is_file($resolved)) {
            return [$resolved];
        }

        if (! is_dir($resolved)) {
            throw new \DomainException('El origen no es una carpeta ni un archivo.');
        }

        $files = [];
        $iterator = new \DirectoryIterator($resolved);

        foreach ($iterator as $entry) {
            if ($entry->isDot() || ! $entry->isFile() || $entry->isLink()) {
                continue;
            }

            if (strtolower($entry->getExtension()) !== 'pdf') {
                continue;
            }

            $path = $entry->getRealPath();

            if ($path === false) {
                throw new \DomainException('No se pudo resolver un PDF local.');
            }

            $this->assertInsideRoot($path, $root);
            $files[] = $path;
        }

        usort($files, static fn (string $left, string $right): int => strcmp(basename($left), basename($right)));

        return $files;
    }

    /** @param array<int, string> $allFiles
     * @return array<int, string>
     */
    private function filesToInspect(array $allFiles): array
    {
        $requested = $this->option('file');

        if ($requested === null) {
            return $allFiles;
        }

        $requested = (string) $requested;

        if ($requested === '' || basename($requested) !== $requested) {
            throw new \DomainException('--file debe ser un nombre de archivo sin ruta.');
        }

        $matches = array_values(array_filter(
            $allFiles,
            static fn (string $path): bool => basename($path) === $requested
        ));

        if (count($matches) !== 1) {
            throw new \DomainException('--file no tiene una coincidencia exacta e inequívoca.');
        }

        return $matches;
    }

    private function assertInsideRoot(string $path, string $root): void
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');

        if ($normalizedPath !== $normalizedRoot && ! str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            throw new \DomainException('El origen está fuera de la raíz autorizada.');
        }
    }

    private function runningUser(): ?string
    {
        if (! function_exists('posix_geteuid') || ! function_exists('posix_getpwuid')) {
            return null;
        }

        $account = posix_getpwuid(posix_geteuid());

        return is_array($account) ? ($account['name'] ?? null) : null;
    }
}
