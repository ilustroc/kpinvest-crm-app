# 15 - Baseline de Migraciones V3

## Objetivo

Crear un baseline limpio de migraciones Laravel para reconstruir la estructura real del CRM desde cero en V3.

## Por que se eliminaron migraciones anteriores

Las migraciones anteriores se eliminaron porque no representaban la base real completa:

- No creaban tablas core como `users`, `clientes_cuentas`, `pagos_propia`, `promesas_pago`, `promesa_cuotas`, `cna_solicitudes`, `ccd_clientes` y `pagos_lotes`.
- Eran parches parciales sobre una base ya existente.
- La tabla `migrations` local tenia registros de migraciones cuyos archivos no existian en el proyecto.
- No servian como fuente confiable para reconstruir una base limpia.

## SQL de referencia

Archivo usado como fuente:

```text
u480021566_kpinvest_bd.sql
```

Ese archivo representa la estructura real conocida de la base productiva al momento del analisis.

## Migraciones eliminadas

```text
2025_11_02_220020_update_users_drop_team_supervisor_add_active.php
2025_11_05_172046_add_supervisor_id_to_users.php
2025_11_13_155533_create_asignar_clientes_table.php
2026_01_12_221108_create_cliente_bloqueos_table.php
2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago.php
```

## Migraciones nuevas

```text
2026_05_15_000001_create_users_table.php
2026_05_15_000002_create_clientes_tables.php
2026_05_15_000003_create_pagos_tables.php
2026_05_15_000004_create_promesas_tables.php
2026_05_15_000005_create_cna_solicitudes_table.php
2026_05_15_000006_create_integracion_tables.php
2026_05_15_000007_normalize_user_roles_v3.php
```

## Tablas cubiertas

Tablas de negocio cubiertas por migraciones V3:

- `users`.
- `clientes_cuentas`.
- `cliente_bloqueos`.
- `pagos_lotes`.
- `pagos_propia`.
- `promesas_pago`.
- `promesa_cuotas`.
- `promesa_operaciones`.
- `cna_solicitudes`.
- `asignar_clientes`.
- `ccd_clientes`.

Tabla Laravel/Sanctum cubierta por migracion vendor:

- `personal_access_tokens`.

Tabla administrada por Laravel al ejecutar migraciones:

- `migrations`.

## Tablas Laravel estandar no incluidas

No se crearon estas tablas porque no existen en el SQL real actual:

- `password_reset_tokens`.
- `sessions`.
- `cache`.
- `jobs`.
- `failed_jobs`.

Si V3 las necesita luego, se deben agregar mediante migraciones nuevas, no dentro del baseline retroactivo.

## Prueba en base limpia

Base desechable usada:

```text
kpinvest_v3_migrate_test
```

Comando:

```bash
php artisan migrate
```

Resultado:

- [+] Migraciones ejecutadas correctamente.
- [+] `php artisan migrate:status` valido todas como `Ran`.
- [+] Se exporto estructura con `mysqldump --no-data`.
- [+] Indices coinciden con la base real.
- [+] Claves foraneas coinciden con la base real.

## Prueba de rollback en base desechable

Base usada:

```text
kpinvest_v3_migrate_test
```

Comandos ejecutados:

```bash
php artisan migrate:rollback
php artisan migrate
php artisan migrate:status
```

Resultado:

- [+] `migrate:rollback` bajo correctamente la migracion vendor de Sanctum y las migraciones V3.
- [+] `php artisan migrate` volvio a crear todas las tablas del baseline.
- [+] `php artisan migrate:status` quedo con todas las migraciones en estado `Ran`.
- [+] Los metodos `down()` funcionaron en la base desechable.

Alcance:

- La prueba se hizo solo en `kpinvest_v3_migrate_test`.
- No se uso la base local principal importada desde produccion.
- No se toco produccion.

## Diferencias frente al SQL real

Diferencia intencional:

```text
users.role
SQL real: enum('administrador','supervisor','asesor','sistemas','soporte','usuario') default 'usuario'
V3:       enum('administrador','supervisor','asesor','soporte') default 'soporte'
```

Motivo:

- V3 elimina los roles `sistemas` y `usuario`.
- La migracion `2026_05_15_000007_normalize_user_roles_v3.php` convierte valores antiguos a `soporte`.

## Como probar de nuevo

Crear base limpia local:

```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS kpinvest_v3_migrate_test; CREATE DATABASE kpinvest_v3_migrate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Ejecutar migraciones apuntando temporalmente a esa base:

```bash
DB_DATABASE=kpinvest_v3_migrate_test php artisan migrate
DB_DATABASE=kpinvest_v3_migrate_test php artisan migrate:status
```

En PowerShell:

```powershell
$env:DB_DATABASE='kpinvest_v3_migrate_test'
php artisan migrate
php artisan migrate:status
```

Exportar estructura:

```bash
mysqldump -u root -p --no-data --skip-comments kpinvest_v3_migrate_test > estructura_generada_v3.sql
```

## Importante para produccion

No ejecutar estas migraciones directamente sobre produccion todavia.

Razon:

- El baseline crea tablas que ya existen en produccion.
- Antes de produccion hay que definir una estrategia: base nueva, baseline marcado como aplicado, o plan SQL incremental.
- La limpieza de roles debe revisarse contra un dump productivo reciente.

## Antes de pasar a produccion

Checklist minimo:

- Descargar dump actualizado.
- Importarlo en local/staging.
- Probar estrategia de baseline.
- Confirmar usuarios con roles antiguos.
- Confirmar backups.
- Confirmar rollback tecnico en copia reciente.
- Definir rollback operativo para produccion.
- Ejecutar pruebas funcionales completas.
- No desplegar si una migracion falla sobre la copia reciente.
