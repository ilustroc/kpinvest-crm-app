<?php

namespace App\Support\Migrations;

final class V1V3Baseline
{
    public const HISTORICAL_MIGRATIONS = [
        '2019_12_14_000001_create_personal_access_tokens_table',
        '2025_08_15_164655_create_pagos_lotes_table',
        '2025_08_15_164655_create_pagos_propia_table',
        '2025_08_15_192105_create_pagos_caja_cusco_castigada_table',
        '2025_11_02_220020_update_users_drop_team_supervisor_add_active',
        '2025_11_05_172046_add_supervisor_id_to_users',
        '2025_11_07_224706_agregar_link_descarga',
        '2025_11_13_155533_create_asignar_clientes_table',
        '2026_01_12_221108_create_cliente_bloqueos_table',
        '2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago',
    ];

    public const BOOTSTRAP_MIGRATIONS = [
        '2026_05_15_000001_create_users_table',
        '2026_05_15_000002_create_clientes_tables',
        '2026_05_15_000003_create_pagos_tables',
        '2026_05_15_000004_create_promesas_tables',
        '2026_05_15_000005_create_cna_solicitudes_table',
        '2026_05_15_000006_create_integracion_tables',
    ];

    public const NON_BASELINE_MIGRATIONS = [
        '2026_05_15_000007_normalize_user_roles_v3',
        '2026_05_21_122013_create_notifications_table',
        '2026_10_29_121500_update_users_drop_is_active_and_normalize_roles',
        '2026_10_29_123000_rename_pagos_propia_to_pagos',
    ];

    public const BASELINE_BATCH = 1;

    public static function isAllowedTestSchema(string $schema): bool
    {
        return (bool) preg_match('/^kpinvest_prod_restore_baseline_test(?:_[a-z0-9]+)*$/', $schema);
    }
}
