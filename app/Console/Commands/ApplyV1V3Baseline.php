<?php

namespace App\Console\Commands;

use App\Support\Migrations\V1V3Baseline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ApplyV1V3Baseline extends Command
{
    protected $signature = 'baseline:apply
        {--database= : Laravel connection name (uses the default connection when omitted)}
        {--schema= : Schema to inspect; required as a safeguard}
        {--dry-run : Simulate only; this is the default mode}
        {--apply : Write the baseline after all validations pass}
        {--confirm-schema= : Must exactly equal --schema when --apply is used}';

    protected $description = 'Register the six V3 bootstrap migrations without running their up() methods';

    public function handle(): int
    {
        $schema = (string) $this->option('schema');
        $apply = (bool) $this->option('apply');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $schema)) {
            return $this->reject('Debe indicar --schema=<nombre_seguro>.');
        }

        if ($apply && $this->option('confirm-schema') !== $schema) {
            return $this->reject('La escritura requiere --apply y --confirm-schema exactamente igual a --schema.');
        }

        $connection = DB::connection($this->option('database') ?: null);

        if ($connection->getDatabaseName() !== $schema || $connection->selectOne('SELECT DATABASE() AS name')->name !== $schema) {
            return $this->reject('La conexión no apunta exactamente al esquema solicitado.');
        }

        if ($connection->getDriverName() !== 'mysql' || ! str_contains(strtolower((string) $connection->selectOne('SELECT VERSION() AS version')->version), 'mariadb')) {
            return $this->reject('El baseline sólo admite una conexión MariaDB validada.');
        }

        if ($apply && (($connection->getConfig('host') ?? null) !== 'kpcrm-restore-db' || ! V1V3Baseline::isAllowedTestSchema($schema))) {
            return $this->reject('Escritura bloqueada: sólo se admite una copia local kpinvest_prod_restore_baseline_test* en kpcrm-restore-db.');
        }

        if (! $this->runPreflight($schema)) {
            return self::FAILURE;
        }

        try {
            $state = $this->validateMigrationHistory($connection);
        } catch (RuntimeException $exception) {
            return $this->reject($exception->getMessage());
        }

        if ($state === 'applied') {
            $this->info('Baseline ya registrado completamente; no se escribió nada.');

            return self::SUCCESS;
        }

        $batch = V1V3Baseline::BASELINE_BATCH;
        $this->line('Plan: registrar las seis migraciones bootstrap en batch '.$batch.'; el último batch histórico permanece intacto.');

        if (! $apply) {
            $this->info('Dry-run correcto: no se ejecutó DDL ni se escribió en migrations. Use --apply --confirm-schema='.$schema.' sólo en la copia temporal autorizada.');

            return self::SUCCESS;
        }

        $lock = 'kp-invest-v1-v3-baseline:'.sha1($schema);

        if ((int) ($connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock])->acquired ?? 0) !== 1) {
            return $this->reject('No se pudo obtener el bloqueo de baseline; otra operación puede estar en curso.');
        }

        try {
            // Repetir el preflight bajo el bloqueo elimina la ventana entre validar y registrar.
            if (! $this->runPreflight($schema)) {
                return self::FAILURE;
            }

            $connection->transaction(function () use ($connection, $batch) {
                $state = $this->validateMigrationHistory($connection, true);

                if ($state === 'applied') {
                    return;
                }

                $connection->table('migrations')->insert(array_map(
                    fn (string $migration) => ['migration' => $migration, 'batch' => $batch],
                    V1V3Baseline::BOOTSTRAP_MIGRATIONS
                ));
            });
        } catch (Throwable $exception) {
            return $this->reject('No se aplicó el baseline: '.$exception->getMessage());
        } finally {
            $connection->select('SELECT RELEASE_LOCK(?)', [$lock]);
        }

        $this->info('Baseline registrado: seis filas en migrations; no se invocó ningún método up() ni DDL.');

        return self::SUCCESS;
    }

    private function runPreflight(string $schema): bool
    {
        $arguments = ['--schema' => $schema];

        if ($this->option('database')) {
            $arguments['--database'] = $this->option('database');
        }

        $exitCode = Artisan::call('baseline:preflight', $arguments);
        $this->output->write(Artisan::output());

        if ($exitCode !== self::SUCCESS) {
            $this->error('El preflight falló; no habrá escrituras.');

            return false;
        }

        return true;
    }

    private function validateMigrationHistory($connection, bool $forUpdate = false): string
    {
        $query = $connection->table('migrations')->select('migration', 'batch');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $rows = $query->get();
        $names = $rows->pluck('migration');
        $missingHistorical = collect(V1V3Baseline::HISTORICAL_MIGRATIONS)->diff($names);

        if ($missingHistorical->isNotEmpty()) {
            throw new RuntimeException('Faltan migraciones históricas esperadas: '.$missingHistorical->implode(', ').'.');
        }

        $bootstrapCount = $names->intersect(V1V3Baseline::BOOTSTRAP_MIGRATIONS)->count();
        $forbidden = $names->intersect(V1V3Baseline::NON_BASELINE_MIGRATIONS);
        $allowedNames = collect(V1V3Baseline::HISTORICAL_MIGRATIONS)->merge(V1V3Baseline::BOOTSTRAP_MIGRATIONS);
        $unexpected = $names->diff($allowedNames);

        if ($forbidden->isNotEmpty() || $unexpected->isNotEmpty()) {
            throw new RuntimeException('El historial contiene migraciones no permitidas para este baseline.');
        }

        if (! in_array($bootstrapCount, [0, count(V1V3Baseline::BOOTSTRAP_MIGRATIONS)], true)) {
            throw new RuntimeException('El bootstrap V3 está parcialmente registrado; se rechaza para evitar un estado parcial.');
        }

        if ($bootstrapCount === count(V1V3Baseline::BOOTSTRAP_MIGRATIONS)) {
            return 'applied';
        }

        if ($rows->count() !== count(V1V3Baseline::HISTORICAL_MIGRATIONS)) {
            throw new RuntimeException('Se esperaban exactamente 10 migraciones históricas antes de aplicar el baseline.');
        }

        $lastHistoricalBatch = (int) $rows->max('batch');

        if ($lastHistoricalBatch <= V1V3Baseline::BASELINE_BATCH) {
            throw new RuntimeException('No existe un batch histórico posterior que proteja el baseline de un rollback ordinario.');
        }

        return 'pending';
    }

    private function reject(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
