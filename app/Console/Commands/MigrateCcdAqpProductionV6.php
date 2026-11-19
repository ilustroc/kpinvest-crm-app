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
        {--credentials-file= : Ruta a un archivo JSON privado (0600) con las credenciales de BD origen y destino}
        {--source-host=127.0.0.1 : Host MySQL de la base origen}
        {--source-port=3306 : Puerto MySQL de la base origen}
        {--source-db=u480021566_prueba : Base de datos origen (staging)}
        {--source-user= : Usuario MySQL de la base origen}
        {--source-password= : Contraseña MySQL origen (alternativa; preferir --credentials-file o CCD_SOURCE_DB_PASSWORD)}
        {--target-host=127.0.0.1 : Host MySQL de la base destino}
        {--target-port=3306 : Puerto MySQL de la base destino}
        {--target-db=u480021566_kpinvest_bd : Base de datos destino (producción)}
        {--target-user= : Usuario MySQL de la base destino}
        {--target-password= : Contraseña MySQL destino (alternativa; preferir --credentials-file o CCD_TARGET_DB_PASSWORD)}
        {--source-storage=/home/u480021566/apps/kpinvest-crm-pruebas/storage/app/private/ccd : Ruta física de almacenamiento privado origen}
        {--target-storage=/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm/storage/app/private/ccd : Ruta física de almacenamiento privado destino}
        {--batch-size=500 : Tamaño del lote transaccional para inserciones}
        {--skip-files : Omite la copia y verificación física de PDFs (solo para diagnóstico de BD o pruebas)}
        {--manifest= : Ruta de salida para el manifiesto CSV de migración (solo en --apply)}';

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

        // Carga segura de credenciales (priorizando archivo privado o variables de entorno)
        $credentials = $this->resolveCredentials();
        if ($credentials === null) {
            return self::FAILURE;
        }

        $sourcePdo = $this->createPdo(
            $credentials['source']['host'],
            $credentials['source']['port'],
            $credentials['source']['database'],
            $credentials['source']['username'],
            $credentials['source']['password']
        );

        if ($sourcePdo === null) {
            $this->error('No se pudo establecer conexión PDO con la base de datos origen.');

            return self::FAILURE;
        }

        $targetPdo = $this->createPdo(
            $credentials['target']['host'],
            $credentials['target']['port'],
            $credentials['target']['database'],
            $credentials['target']['username'],
            $credentials['target']['password']
        );

        if ($targetPdo === null) {
            $this->error('No se pudo establecer conexión PDO con la base de datos destino.');

            return self::FAILURE;
        }

        // En dry-run NO se genera manifiesto en destino
        $manifest = null;
        if ($apply) {
            $manifest = $this->option('manifest')
                ? (string) $this->option('manifest')
                : storage_path('app/private/ccd/audits/ccd-v6-produccion-manifest-'.date('Ymd-His').'.csv');
        }

        $this->info('=== KP INVEST — Despliegue CCD AQP V6 a Producción ===');
        $this->line('Modo: '.($apply ? 'APLICACIÓN REAL (--apply)' : 'SIMULACIÓN INTEGRAL (--dry-run)'));
        $this->line("Origen: {$credentials['source']['database']} @ {$credentials['source']['host']}");
        $this->line("Destino: {$credentials['target']['database']} @ {$credentials['target']['host']}");
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

        if ($dryRun) {
            $this->table(
                ['Métrica de Simulación', 'Valor'],
                [
                    ['Modo', 'DRY-RUN (Simulación sin escrituras)'],
                    ['Conteo ccd_clientes verificado', number_format($result['preflight']['ccd_total'])],
                    ['Conteo clientes_cuentas verificado', number_format($result['preflight']['cuentas_total'])],
                    ['Huella CCD AQP conforme', substr($result['preflight']['ccd_sha256'], 0, 16).'...'],
                    ['Huella Cuentas AQP conforme', substr($result['preflight']['cuentas_sha256'], 0, 16).'...'],
                    ['PDFs verificados en origen', number_format($result['files_verified'])],
                    ['Registros ccd_archivos elegibles', number_format($result['archivos_eligible'])],
                    ['Vínculos ccd_archivo_id elegibles', number_format($result['links_eligible'])],
                    ['Asociaciones AQP elegibles', number_format($result['associations_eligible'])],
                    ['Migraciones pendientes en destino', count($result['target_schema']['pending_migrations']) > 0
                        ? implode(', ', $result['target_schema']['pending_migrations'])
                        : 'Ninguna (esquema ya actualizado)'],
                    ['Escrituras efectuadas', '0 (Garantía estricta de solo lectura)'],
                ]
            );

            $this->info('Simulación DRY-RUN completada satisfactoriamente.');

            return self::SUCCESS;
        }

        // Apply table
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

    /**
     * @return array{
     *   source: array{host: string, port: int, database: string, username: string, password: string},
     *   target: array{host: string, port: int, database: string, username: string, password: string}
     * }|null
     */
    private function resolveCredentials(): ?array
    {
        $credFile = $this->option('credentials-file');
        $fileCreds = [];

        if ($credFile !== null && $credFile !== '') {
            if (! is_file($credFile) || ! is_readable($credFile)) {
                $this->error("El archivo de credenciales no existe o no es legible: {$credFile}");

                return null;
            }

            // Validar permisos restrictivos en entornos tipo Unix
            if (DIRECTORY_SEPARATOR !== '\\') {
                $perms = fileperms($credFile) & 0777;
                if ($perms > 0600) {
                    $this->error(sprintf(
                        'Permisos inseguros en archivo de credenciales (%04o). Deben ser 0600 o más restrictivos.',
                        $perms
                    ));

                    return null;
                }
            }

            $raw = file_get_contents($credFile);
            $parsed = json_decode((string) $raw, true);
            if (! is_array($parsed)) {
                $this->error('Formato inválido en archivo de credenciales JSON.');

                return null;
            }
            $fileCreds = $parsed;
        }

        $sourceHost = (string) ($fileCreds['source']['host'] ?? $this->option('source-host'));
        $sourcePort = (int) ($fileCreds['source']['port'] ?? $this->option('source-port'));
        $sourceDb = (string) ($fileCreds['source']['database'] ?? $this->option('source-db'));
        $sourceUser = (string) ($fileCreds['source']['username'] ?? ($this->option('source-user') ?: config('database.connections.mysql.username')));
        $sourcePass = (string) ($fileCreds['source']['password'] ?? (getenv('CCD_SOURCE_DB_PASSWORD') ?: ($this->option('source-password') ?: config('database.connections.mysql.password'))));

        $targetHost = (string) ($fileCreds['target']['host'] ?? $this->option('target-host'));
        $targetPort = (int) ($fileCreds['target']['port'] ?? $this->option('target-port'));
        $targetDb = (string) ($fileCreds['target']['database'] ?? $this->option('target-db'));
        $targetUser = (string) ($fileCreds['target']['username'] ?? ($this->option('target-user') ?: config('database.connections.mysql.username')));
        $targetPass = (string) ($fileCreds['target']['password'] ?? (getenv('CCD_TARGET_DB_PASSWORD') ?: ($this->option('target-password') ?: config('database.connections.mysql.password'))));

        return [
            'source' => [
                'host' => $sourceHost,
                'port' => $sourcePort,
                'database' => $sourceDb,
                'username' => $sourceUser,
                'password' => $sourcePass,
            ],
            'target' => [
                'host' => $targetHost,
                'port' => $targetPort,
                'database' => $targetDb,
                'username' => $targetUser,
                'password' => $targetPass,
            ],
        ];
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
            // No registrar contraseñas en mensajes de error
            $this->error("Error conectando a la base de datos `{$db}` en `{$host}:{$port}`. Verifique credenciales y permisos de red.");

            return null;
        }
    }
}
