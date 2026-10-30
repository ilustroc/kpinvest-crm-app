<?php

namespace App\Support\Migrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class BaselineMigrationGuard
{
    public const DESTRUCTIVE_COMMANDS = [
        'migrate:rollback',
        'migrate:reset',
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    public static function blockDestructiveCommandsWhenBaselineIsPresent(?string $command = null, ?string $connection = null): void
    {
        try {
            $schema = Schema::connection($connection);
            $db = DB::connection($connection);

            if (! $schema->hasTable('migrations')) {
                return;
            }

            $migrations = $db->table('migrations')->pluck('migration');
            $hasHistoricalAnchor = $migrations->contains('2019_12_14_000001_create_personal_access_tokens_table');
            $hasBootstrap = $migrations->intersect(V1V3Baseline::BOOTSTRAP_MIGRATIONS)->count() === count(V1V3Baseline::BOOTSTRAP_MIGRATIONS);

            if ($hasHistoricalAnchor && $hasBootstrap) {
                $commandName = $command ?? 'este comando';
                throw new RuntimeException("Bloqueado: el comando '{$commandName}' no está permitido porque este esquema contiene un baseline V1 → V3. Los comandos destructivos (migrate:rollback, migrate:reset, migrate:fresh, migrate:refresh, db:wipe) requieren una operación de recuperación aprobada; sus operaciones DDL de reversión/eliminación no son seguras para este baseline.");
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Si la conexión no está disponible, se conserva el comportamiento normal de Artisan.
        }
    }
}

