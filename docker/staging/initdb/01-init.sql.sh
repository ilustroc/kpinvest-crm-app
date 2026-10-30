#!/bin/bash
# Inicializacion del MariaDB de proceso de staging. Solo se ejecuta cuando el
# volumen kpcrm_staging_mariadb_data esta vacio. No borra ni modifica bases
# existentes.
#
# G1.5: este servidor NO contiene la referencia sin enmascarar. Contiene las
# bases derivadas y la base de validacion de la app. La referencia vive en
# kpcrm-staging-ref-db, en una red a la que la app no pertenece.
#
# Las contrasenas llegan por variables de entorno del contenedor y no se
# imprimen. El script es sourced por el entrypoint oficial de MariaDB.

set -e

: "${MARIADB_ROOT_PASSWORD:?Falta MARIADB_ROOT_PASSWORD}"
: "${STAGING_DB_ETL_PASSWORD:?Falta STAGING_DB_ETL_PASSWORD}"
: "${STAGING_DB_RESTORE_PASSWORD:?Falta STAGING_DB_RESTORE_PASSWORD}"
: "${STAGING_DB_MASK_PASSWORD:?Falta STAGING_DB_MASK_PASSWORD}"
: "${STAGING_DB_APP_PASSWORD:?Falta STAGING_DB_APP_PASSWORD}"
: "${STAGING_DB_RO_PASSWORD:?Falta STAGING_DB_RO_PASSWORD}"

mariadb --protocol=socket -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`kp_crm_staging_masked\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`kp_crm_mask_work\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`kp_crm_v3_validation\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`kp_crm_restore_rehearsal\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Cuenta heredada: bloqueada y sin privilegios.
CREATE USER IF NOT EXISTS 'kp_stage_etl'@'%' IDENTIFIED BY '${STAGING_DB_ETL_PASSWORD}';
ALTER USER 'kp_stage_etl'@'%' IDENTIFIED BY '${STAGING_DB_ETL_PASSWORD}' ACCOUNT LOCK;

-- Restauracion del ensayo de rollback: sin grants por defecto. La ventana la
-- abre Apply-StagingHardening.ps1 -EnableRehearsalRestore. La referencia no se
-- restaura en este servidor.
CREATE USER IF NOT EXISTS 'kp_stage_restore'@'%' IDENTIFIED BY '${STAGING_DB_RESTORE_PASSWORD}';
ALTER USER 'kp_stage_restore'@'%' IDENTIFIED BY '${STAGING_DB_RESTORE_PASSWORD}';

-- Enmascaramiento: escribe solo masked/work. Lee la referencia en el otro
-- servidor con el mismo nombre de rol.
CREATE USER IF NOT EXISTS 'kp_stage_mask'@'%' IDENTIFIED BY '${STAGING_DB_MASK_PASSWORD}';
ALTER USER 'kp_stage_mask'@'%' IDENTIFIED BY '${STAGING_DB_MASK_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON \`kp_crm_staging_masked\`.* TO 'kp_stage_mask'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON \`kp_crm_mask_work\`.* TO 'kp_stage_mask'@'%';

-- Aplicacion V3: solo DML y temporales en la base de validacion.
CREATE USER IF NOT EXISTS 'kp_stage_app'@'%' IDENTIFIED BY '${STAGING_DB_APP_PASSWORD}';
ALTER USER 'kp_stage_app'@'%' IDENTIFIED BY '${STAGING_DB_APP_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES
  ON \`kp_crm_v3_validation\`.* TO 'kp_stage_app'@'%';

-- Validacion de solo lectura: no puede escribir en ninguna base.
CREATE USER IF NOT EXISTS 'kp_stage_ro'@'%' IDENTIFIED BY '${STAGING_DB_RO_PASSWORD}';
ALTER USER 'kp_stage_ro'@'%' IDENTIFIED BY '${STAGING_DB_RO_PASSWORD}';
GRANT SELECT ON \`kp_crm_staging_masked\`.* TO 'kp_stage_ro'@'%';
GRANT SELECT ON \`kp_crm_v3_validation\`.* TO 'kp_stage_ro'@'%';
GRANT SELECT ON \`kp_crm_restore_rehearsal\`.* TO 'kp_stage_ro'@'%';

FLUSH PRIVILEGES;
SQL
