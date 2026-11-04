<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Masker.php';
require_once __DIR__ . '/Verifier.php';

ini_set('memory_limit', '1024M');
set_time_limit(0);

/**
 * @return array<string, string|bool>
 */
function parse_options(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--execute') {
            $options['execute'] = true;
            continue;
        }
        if (str_starts_with($arg, '--confirm=')) {
            $options['confirm'] = substr($arg, 10);
            continue;
        }
        throw new RuntimeException("Opcion no reconocida: {$arg}");
    }

    return $options;
}

function print_plan(): void
{
    echo <<<TXT
PLAN DE STAGING (no ejecutado)
1. docker compose -f compose.crm.staging.yml --env-file C:\KP-CRM-Staging\.env.staging up -d --no-recreate staging-db
2. php tools/migration/cli.php preflight
3. scripts/migration/Restore-Reference.ps1 -DumpPath <dump.sql> -Execute
4. php tools/migration/cli.php mask --execute --confirm=staging-mask
5. php tools/migration/cli.php verify
6. construir la base kp_crm_v3_validation y ejecutar el checklist funcional
7. ensayar rollback sobre kp_crm_restore_rehearsal

La mascara solo se ejecuta con --execute --confirm=staging-mask.
No se permite tocar u480021566_kpinvest_bd, kpinvest_v3_migrate_test, kp_crm_local
ni ningun host que no sea staging-db.

TXT;
}

function require_db_role(string $role): void
{
    assert_staging_environment();
    if (env_value('DB_ROLE') !== $role) {
        throw new RuntimeException("Este comando requiere DB_ROLE={$role}");
    }
}

function command_preflight(): void
{
    require_db_role('ro');
    $key = mask_key();
    $databases = staging_databases();
    $source = db_connect($databases['reference'], 'DB_REFERENCE_HOST', 'DB_REFERENCE_PORT');
    $target = db_connect($databases['masked'], 'DB_HOST', 'DB_PORT');
    $serverReference = assert_server_compatibility($source);
    $serverTarget = assert_server_compatibility($target);
    $reference = assert_reference_schema($source, false);
    $roles = $reference['ready'] ? assert_allowed_roles($source, $databases['reference']) : [];

    $targets = [];
    $failures = [];
    foreach (['masked', 'work', 'validation', 'rehearsal'] as $label) {
        $count = database_table_count($target, $databases[$label]);
        $targets[$label] = $count;
        if (in_array($label, ['masked', 'validation', 'rehearsal'], true) && $count !== 0) {
            $failures[] = "La base {$label} no esta vacia ({$count} tablas)";
        }
    }

    $warnings = [];
    if (!$reference['ready']) {
        $warnings[] = 'La base de referencia esta vacia; restaure el dump autorizado antes de enmascarar.';
    }

    $report = [
        'ok' => $failures === [],
        'failures' => $failures,
        'warnings' => $warnings,
        'databases' => $databases,
        'server_reference' => [
            'version' => $serverReference['version'],
            'sql_mode' => $serverReference['sql_mode'],
            'character_set_server' => $serverReference['charset_server'],
            'collation_server' => $serverReference['collation_server'],
            'lower_case_table_names' => (int) $serverReference['lower_case_table_names'],
            'max_allowed_packet' => (int) $serverReference['max_allowed_packet'],
            'time_zone' => $serverReference['time_zone'],
            'current_user' => $serverReference['current_user_name'],
        ],
        'server_target' => [
            'version' => $serverTarget['version'],
            'sql_mode' => $serverTarget['sql_mode'],
            'character_set_server' => $serverTarget['charset_server'],
            'collation_server' => $serverTarget['collation_server'],
            'lower_case_table_names' => (int) $serverTarget['lower_case_table_names'],
            'max_allowed_packet' => (int) $serverTarget['max_allowed_packet'],
            'time_zone' => $serverTarget['time_zone'],
            'current_user' => $serverTarget['current_user_name'],
        ],
        'reference' => $reference,
        'roles' => $roles,
        'targets' => $targets,
        'mask_key_ok' => strlen($key) >= 32,
        'test_password_configured' => strlen(env_value('STAGING_TEST_PASSWORD')) >= 12,
    ];

    report_write('preflight.json', $report);
    json_out($report);

    if (!$report['ok']) {
        exit(1);
    }
}

function command_mask(array $options): void
{
    if (($options['execute'] ?? false) !== true || ($options['confirm'] ?? '') !== 'staging-mask') {
        print_plan();
        return;
    }

    require_db_role('mask');
    $key = mask_key();
    $databases = staging_databases();
    $source = db_connect($databases['reference'], 'DB_REFERENCE_HOST', 'DB_REFERENCE_PORT');
    assert_server_compatibility($source);
    assert_reference_schema($source, true);

    $target = db_connect($databases['masked'], 'DB_HOST', 'DB_PORT');
    assert_server_compatibility($target);
    $masker = new Masker(
        $source,
        $target,
        $databases['reference'],
        $databases['masked'],
        $key,
        env_value('STAGING_TEST_PASSWORD')
    );

    $result = $masker->maskAll();
    unset($masker);

    $manifest = [
        'ok' => true,
        'masked' => $result,
    ];
    report_write('mask_manifest.json', $manifest);

    $verifier = new Verifier(
        $source,
        $target,
        $databases['reference'],
        $databases['masked']
    );
    $verification = $verifier->verify();
    report_write('masking_validation.json', $verification);

    $manifest['verification_ok'] = $verification['ok'];
    $manifest['verification_failures'] = $verification['failures'];
    report_write('mask_manifest.json', $manifest);

    json_out([
        'mask' => $result,
        'verification' => [
            'ok' => $verification['ok'],
            'failures' => $verification['failures'],
        ],
    ]);

    if (!$verification['ok']) {
        exit(1);
    }
}

function command_verify(): void
{
    require_db_role('ro');
    mask_key();
    $databases = staging_databases();

    $source = db_connect($databases['reference'], 'DB_REFERENCE_HOST', 'DB_REFERENCE_PORT');
    assert_server_compatibility($source);
    assert_reference_schema($source, true);

    $target = db_connect($databases['masked'], 'DB_HOST', 'DB_PORT');
    assert_server_compatibility($target);
    if (database_table_count($target, $databases['masked']) === 0) {
        throw new RuntimeException('La base enmascarada esta vacia; ejecute el enmascaramiento primero');
    }

    $verifier = new Verifier(
        $source,
        $target,
        $databases['reference'],
        $databases['masked']
    );
    $report = $verifier->verify();
    report_write('masking_validation.json', $report);
    json_out($report);

    if (!$report['ok']) {
        exit(1);
    }
}

function print_help(): void
{
    echo <<<TXT
Uso: php tools/migration/cli.php <comando> [opciones]

Comandos:
  preflight   Verifica aislamiento, version, charset, collation, sql_mode,
              grants, secreto HMAC, esquema de referencia y bases vacias.
  mask        Copia y enmascara la referencia. Requiere:
              --execute --confirm=staging-mask
  verify      Verifica conteos, FKs, unicas, sumas, roles, casos conocidos,
              PII residual exacta, formatos sinteticos, JSON y rutas.
  plan        Muestra el plan sin ejecutar nada.
  help        Muestra esta ayuda.

Seguridad:
  - Solo DB_HOST=staging-db y bases kp_crm_*.
  - No imprime secretos ni valores de datos.
  - No normaliza roles: si aparece un rol fuera de la lista permitida, falla.
  - Los tokens personales no se copian.

TXT;
}

try {
    $command = $argv[1] ?? 'help';
    $options = parse_options(array_slice($argv, 2));

    switch ($command) {
        case 'preflight':
            command_preflight();
            break;
        case 'mask':
            command_mask($options);
            break;
        case 'verify':
            command_verify();
            break;
        case 'plan':
            print_plan();
            break;
        case 'help':
        case '--help':
        case '-h':
            print_help();
            break;
        default:
            throw new RuntimeException("Comando no reconocido: {$command}");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
