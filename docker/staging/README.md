# Staging aislado de KP CRM

Este directorio contiene solo la inicialización del MariaDB de staging. No
modifica `compose.crm.yml`, `u480021566_kpinvest_bd`, `kpinvest_v3_migrate_test`
ni `kp_crm_local`.

## Qué crea

- Proyecto Docker `kpcrm-staging`, cinco redes y dos volúmenes.
  - `kpcrm_staging_ref` (`internal=true`): ref-db + tools.
  - `kpcrm_staging_tools_db` (`internal=true`): db de proceso + tools.
  - `kpcrm_staging_private` (`internal=true`): db de proceso + app.
  - `kpcrm_staging_app_net` (`internal=true`): app + proxy.
  - `kpcrm_staging_web` (bridge): solo el proxy; publica `127.0.0.1:8090`.
  - Volúmenes: `kpcrm_staging_mariadb_data` (db) y
    `kpcrm_staging_ref_data` (ref-db).
- Contenedores `kpcrm-staging-ref-db`, `kpcrm-staging-db`,
  `kpcrm-staging-app`, `kpcrm-staging-web` y `kpcrm-staging-tools` (one-off,
  perfil `tools`). `kpcrm-staging-ref-db` aísla la referencia sin enmascarar;
  la app no comparte red con él.
- Bases nuevas, todas `utf8mb4_unicode_ci`:
  - `kp_crm_v1_reference`
  - `kp_crm_staging_masked`
  - `kp_crm_mask_work`
  - `kp_crm_v3_validation`
  - `kp_crm_restore_rehearsal`
- Usuarios con permisos separados:
  - `kp_stage_restore`: DDL/DML en `kp_crm_v1_reference` solo durante la
    ventana de restauración; después se revoca la referencia y conserva
    `kp_crm_restore_rehearsal`.
  - `kp_stage_etl`: cuenta heredada **bloqueada**, sin privilegios.
  - `kp_stage_ref_ro`: `SELECT` solo en la referencia.
  - `kp_stage_mask`: `SELECT` en la referencia; DDL/DML en masked y work.
  - `kp_stage_app`: DML y tablas temporales solo en `kp_crm_v3_validation`.
  - `kp_stage_ro`: `SELECT` en referencia, masked, validación y rehearsal.

## Secretos

- El archivo de entorno real debe vivir **fuera del repositorio** (por ejemplo,
  `C:\KP-CRM-Staging\.env.staging`) y se pasa con `--env-file`. El CRM actual
  monta el repositorio completo; por eso no se deja dentro. La plantilla
  `.env.staging.example` sí se versiona, sin valores reales.
- La clave HMAC de pseudonimización debe vivir **fuera del repositorio** y se
  monta solo en `staging-tools` como `/run/secrets/mask_hmac_key`.
- La app no recibe la clave HMAC ni el usuario ETL.
- `scripts/migration/New-StagingSecrets.ps1` genera el archivo externo y la
  clave sin imprimir valores.

## Red y puertos

- La base no publica puerto al host y no tiene egreso.
- La app no publica puerto, no tiene egreso y solo ve la base de validación.
- El proxy es el único servicio con puerto de host:
  `127.0.0.1:8090`. No está conectado a la red privada de la base y no recibe
  secretos ni datos.
- `staging-tools` es one-off, sin puerto, y se ejecuta con
  `docker compose run --rm --no-deps`. Sin `--no-deps` Compose puede recrear
  `staging-db`.

## Inicialización

El script `initdb/01-init.sql.sh` solo se ejecuta cuando el volumen de MariaDB
está vacío. No se debe reutilizar el volumen de otra base ni ejecutar `down -v`
sobre staging salvo que se quiera destruir la copia temporal.

No se copian datos reales en esta fase. La restauración y el enmascaramiento
requieren la autorización posterior a G1.
