<?php

namespace App\Console\Commands;

use App\Services\Ccd\CcdProductionMigratorService;
use DomainException;
use Illuminate\Console\Command;
use PDO;
use Throwable;

class MigrateCcdAqpProductionV6 extends Command
{
    public const APPLY_AUTHORIZATION = 'AUTORIZAR_MIGRACION_PRODUCCION_CCD_V6';

    public const APPLY_CONFIRMATION = 'CONFIRMAR_TRANSFERENCIA_AQP_DEFINITIVA';

    protected $signature = 'ccd:migrate-aqp-production-v6
        {--dry-run : Valida completamente el estado de origen y destino sin escribir cambios}
        {--apply : Ejecuta la migración controlada hacia producción}
        {--authorization= : Primera autorización requerida con --apply}
        {--confirmation= : Segunda confirmación requerida con --apply}
        {--source-host=127.0.0.1 : Host MySQL de la base origen}
        {--source-port=3306 : Puerto MySQL de la base origen}
        {--source-db=u480021566_prueba : Base de datos origen (staging)}
        {--source-user= : Usuario MySQL de la base origen}
        {--source-password= : Contraseña MySQL de la base origen}
        {--target-host=127.0.0.1 : Host MySQL de la base destino}
        {--target-port=3306 : Puerto MySQL de la base destino}
        {--target-db=u480021566_kpinvest_bd : Base de datos destino (producción)}
        {--target-user= : Usuario MySQL de la base destino}
        {--target-password= : Contraseña MySQL de la base destino}
        {--source-storage=/home/u480021566/apps/kpinvest-crm-pruebas/storage/app/private/ccd : Ruta física de almacenamiento privado origen}
        {--target-storage=/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm/storage/app/private/ccd : Ruta física de almacenamiento privado destino}
        {--batch-size=500 : Tamaño del lote transaccional para inserciones}
        {--skip-files : Omite la copia y verificación física de PDFs (solo para diagnóstico de BD o pruebas)}
        {--manifest= : Ruta de salida para el manifiesto CSV de migración}';

    protected $description = 'Ejecuta o simula la migración completa de asociaciones y PDFs CCD AQP4/AQP5 a producción';

    public function handle(CcdProductionMigratorService $migrator): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run');

        if (! $apply && ! $dryRun) {
            $this->error('Debe especificar --dry-run para simulación o --apply para ejecución autorizada.');

            return self::FAILURE;
        }

        if ($apply && $dryRun) {
            $this->error('Las opciones --apply y --dry-run son mutuamente excluyentes.');

            return self::FAILURE;
        }

        if ($apply) {
            $auth = (string) $this->option('authorization');
            $conf = (string) $this->option('confirmation');

            if ($auth !== self::APPLY_AUTHORIZATION || $conf !== self::APPLY_CONFIRMATION) {
                $this->error('El modo --apply requiere ambas autorizaciones explícitas.');
                $this->line('  --authorization='.self::APPLY_AUTHORIZATION);
                $this->line('  --confirmation='.self::APPLY_CONFIRMATION);

                return self::FAILURE;
            }
        }

        $sourcePdo = $this->createPdo(
            (string) $this->option('source-host'),
            (int) $this->option('source-port'),
            (string) $this->option('source-db'),
            (string) ($this->option('source-user') ?: config('database.connections.mysql.username')),
            (string) ($this->option('source-password') ?: config('database.connections.mysql.password')),
        );

        if ($sourcePdo === null) {
            $this->error('No se pudo establecer conexión PDO con la base de datos origen.');

            return self::FAILURE;
        }

        $targetPdo = $this->createPdo(
            (string) $this->option('target-host'),
            (int) $this->option('target-port'),
            (string) $this->option('target-db'),
            (string) ($this->option('target-user') ?: config('database.connections.mysql.username')),
            (string) ($this->option('target-password') ?: config('database.connections.mysql.password')),
        );

        if ($targetPdo === null) {
            $this->error('No se pudo establecer conexión PDO con la base de datos destino.');

            return self::FAILURE;
        }

        $manifest = $this->option('manifest')
            ? (string) $this->option('manifest')
            : storage_path('app/private/ccd/audits/ccd-v6-produccion-manifest-'.date('Ymd-His').'.csv');

        $this->info('=== KP INVEST — Despliegue CCD AQP V6 a Producción ===');
        $this->line('Modo: '.($apply ? 'APLICACIÓN REAL (--apply)' : 'SIMULACIÓN INTEGRAL (--dry-run)'));
        $this->line('Origen: '.(string) $this->option('source-db').' @ '.(string) $this->option('source-host'));
        $this->line('Destino: '.(string) $this->option('target-db').' @ '.(string) $this->option('target-host'));
        $this->newLine();

        try {
            $result = $migrator->migrate([
                'source_pdo' => $sourcePdo,
                'target_pdo' => $targetPdo,
                'source_storage' => (string) $this->option('source-storage'),
                'target_storage' => (string) $this->option('target-storage'),
                'apply' => $apply,
                'batch_size' => (int) $this->option('batch-size'),
                'skip_files' => (bool) $this->option('skip-files'),
                'manifest_path' => $manifest,
                'logger' => fn (string $msg) => $this->line("  [INFO] {$msg}"),
            ]);
        } catch (DomainException $e) {
            $this->error('ABORTADO: '.$e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('ERROR CRÍTICO: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Resultados de la Operación ===');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Modo', $result['mode']],
                ['PDFs transferidos', number_format($result['files_transferred'])],
                ['PDFs verificados', number_format($result['files_verified'])],
                ['Registros ccd_archivos', number_format($result['archivos_migrated'])],
                ['Vínculos ccd_archivo_id', number_format($result['links_updated'])],
                ['Asociaciones confirmadas', number_format($result['associations_migrated'])],
                ['Auditoría post-migración', $result['audit']['passed'] ? 'CONFORME (0 anomalías)' : 'FALLIDA'],
                ['Manifiesto SHA-256', $result['manifest_sha256'] ?? 'N/A'],
            ]
        );

        if (! $result['audit']['passed']) {
            $this->error('La auditoría posterior reportó anomalías referenciales o documentales.');

            return self::FAILURE;
        }

        $this->info('Migración CCD AQP V6 finalizada con éxito.');

        return self::SUCCESS;
    }

    private function createPdo(string $host, int $port, string $db, string $user, string $pass): ?PDO
    {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 30,
            ]);
        } catch (Throwable $e) {
            $this->error("Error conectando a {$db}@{$host}: {$e->getMessage()}");

            return null;
        }
    }
}
