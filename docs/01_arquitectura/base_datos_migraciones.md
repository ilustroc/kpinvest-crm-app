# Base de datos y migraciones V3

## Fuente de verdad

El archivo `u480021566_kpinvest_bd.sql` representa la estructura real usada como referencia para V3.

## Estrategia

- No confiar en migraciones historicas antiguas.
- Reconstruir un baseline V3 desde el SQL real.
- Probar migraciones solo en base local desechable.
- No ejecutar migraciones en produccion hasta definir estrategia de baseline.

## Migraciones V3

- `2026_05_15_000001_create_users_table.php`
- `2026_05_15_000002_create_clientes_tables.php`
- `2026_05_15_000003_create_pagos_tables.php`
- `2026_05_15_000004_create_promesas_tables.php`
- `2026_05_15_000005_create_cna_solicitudes_table.php`
- `2026_05_15_000006_create_integracion_tables.php`
- `2026_05_15_000007_normalize_user_roles_v3.php`

## Tablas principales

- `users`
- `clientes_cuentas`
- `cliente_bloqueos`
- `pagos_lotes`
- `pagos_propia`
- `promesas_pago`
- `promesa_operaciones`
- `promesa_cuotas`
- `cna_solicitudes`
- `asignar_clientes`
- `ccd_clientes`

## Prueba local realizada

Las migraciones fueron probadas en la base desechable `kpinvest_v3_migrate_test` con:

```bash
php artisan migrate
php artisan migrate:status
php artisan migrate:rollback
php artisan migrate
```

## Diferencias conocidas

- `users.role` queda normalizado a roles finales de V3.
- En la base local principal importada desde produccion, el baseline aparece pendiente porque no debe ejecutarse sobre tablas existentes sin marcar baseline.

## Reglas de seguridad

- No usar `migrate:fresh` sobre bases con datos importantes.
- No correr seeders en produccion sin revision.
- No borrar tablas ni columnas sin dump reciente y pruebas.
- Antes de produccion, descargar dump actualizado y repetir la validacion sobre copia.
