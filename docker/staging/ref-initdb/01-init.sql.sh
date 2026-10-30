#!/bin/bash
# Inicializacion del MariaDB dedicado a la referencia sin enmascarar.
# Solo se ejecuta cuando el volumen kpcrm_staging_ref_data esta vacio.
#
# G1.5: este servidor solo contiene kp_crm_v1_reference. La aplicacion V3 no
# tiene usuario ni red hacia este contenedor. El usuario de restauracion no
# recibe escritura hasta que se abre la ventana con
# Apply-StagingHardening.ps1 -EnableReferenceRestore.
#
# Las contrasenas llegan por variables de entorno y no se imprimen.

set -e

: "${MARIADB_ROOT_PASSWORD:?Falta MARIADB_ROOT_PASSWORD}"
: "${STAGING_DB_ETL_PASSWORD:?Falta STAGING_DB_ETL_PASSWORD}"
: "${STAGING_DB_RESTORE_PASSWORD:?Falta STAGING_DB_RESTORE_PASSWORD}"
: "${STAGING_DB_REF_RO_PASSWORD:?Falta STAGING_DB_REF_RO_PASSWORD}"
: "${STAGING_DB_MASK_PASSWORD:?Falta STAGING_DB_MASK_PASSWORD}"
: "${STAGING_DB_RO_PASSWORD:?Falta STAGING_DB_RO_PASSWORD}"

mariadb --protocol=socket -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`kp_crm_v1_reference\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Cuenta heredada: se conserva bloqueada y sin privilegios.
CREATE USER IF NOT EXISTS 'kp_stage_etl'@'%' IDENTIFIED BY '${STAGING_DB_ETL_PASSWORD}';
ALTER USER 'kp_stage_etl'@'%' IDENTIFIED BY '${STAGING_DB_ETL_PASSWORD}' ACCOUNT LOCK;

-- Restauracion: sin grants por defecto. La ventana la abre
-- Apply-StagingHardening.ps1 -EnableReferenceRestore y Restore-Reference.ps1
-- la cierra inmediatamente despues.
CREATE USER IF NOT EXISTS 'kp_stage_restore'@'%' IDENTIFIED BY '${STAGING_DB_RESTORE_PASSWORD}';
ALTER USER 'kp_stage_restore'@'%' IDENTIFIED BY '${STAGING_DB_RESTORE_PASSWORD}';

-- Lectura de referencia para el ETL y para validacion. Nunca escritura.
CREATE USER IF NOT EXISTS 'kp_stage_ref_ro'@'%' IDENTIFIED BY '${STAGING_DB_REF_RO_PASSWORD}';
ALTER USER 'kp_stage_ref_ro'@'%' IDENTIFIED BY '${STAGING_DB_REF_RO_PASSWORD}';
GRANT SELECT ON \`kp_crm_v1_reference\`.* TO 'kp_stage_ref_ro'@'%';

CREATE USER IF NOT EXISTS 'kp_stage_mask'@'%' IDENTIFIED BY '${STAGING_DB_MASK_PASSWORD}';
ALTER USER 'kp_stage_mask'@'%' IDENTIFIED BY '${STAGING_DB_MASK_PASSWORD}';
GRANT SELECT ON \`kp_crm_v1_reference\`.* TO 'kp_stage_mask'@'%';

CREATE USER IF NOT EXISTS 'kp_stage_ro'@'%' IDENTIFIED BY '${STAGING_DB_RO_PASSWORD}';
ALTER USER 'kp_stage_ro'@'%' IDENTIFIED BY '${STAGING_DB_RO_PASSWORD}';
GRANT SELECT ON \`kp_crm_v1_reference\`.* TO 'kp_stage_ro'@'%';

FLUSH PRIVILEGES;
SQL
