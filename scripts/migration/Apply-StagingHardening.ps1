# Aplica la matriz de privilegios G1.5 sobre los dos MariaDB de staging.
# No recrea contenedores ni toca volumenes. Solo recrea las cuentas kp_stage_*
# y sus GRANT; no modifica datos de las bases.
#
# Por defecto:
#   - kp_crm_v1_reference no tiene escritura para kp_stage_restore.
#   - kp_crm_restore_rehearsal no tiene escritura para kp_stage_restore.
# Use -EnableReferenceRestore o -EnableRehearsalRestore solo inmediatamente
# antes de una restauracion autorizada; Restore-Reference.ps1 cierra la ventana.
[CmdletBinding()]
param(
    [switch]$Execute,
    [switch]$EnableReferenceRestore,
    [switch]$EnableRehearsalRestore
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$action = 'recrear cuentas kp_stage_* y aplicar GRANT G1.5 en ref-db y db'
if ($EnableReferenceRestore) { $action += ' con escritura temporal en reference' }
if ($EnableRehearsalRestore) { $action += ' con escritura temporal en rehearsal' }
Assert-Execute -Execute $Execute.IsPresent -Action $action
Assert-Docker
Assert-ContainerRunning -Name $script:StagingRefContainer
Assert-ContainerRunning -Name $script:StagingDbContainer

$envMap = Get-StagingEnv
Assert-StagingEnvSafe -EnvMap $envMap

$refSql = @'
DROP USER IF EXISTS 'kp_stage_etl'@'%';
DROP USER IF EXISTS 'kp_stage_restore'@'%';
DROP USER IF EXISTS 'kp_stage_ref_ro'@'%';
DROP USER IF EXISTS 'kp_stage_mask'@'%';
DROP USER IF EXISTS 'kp_stage_ro'@'%';
DROP USER IF EXISTS 'kp_stage_app'@'%';

CREATE USER 'kp_stage_etl'@'%' IDENTIFIED BY '__ETL__' ACCOUNT LOCK;

CREATE USER 'kp_stage_restore'@'%' IDENTIFIED BY '__RESTORE__';

CREATE USER 'kp_stage_ref_ro'@'%' IDENTIFIED BY '__REF_RO__';
GRANT SELECT ON `kp_crm_v1_reference`.* TO 'kp_stage_ref_ro'@'%';

CREATE USER 'kp_stage_mask'@'%' IDENTIFIED BY '__MASK__';
GRANT SELECT ON `kp_crm_v1_reference`.* TO 'kp_stage_mask'@'%';

CREATE USER 'kp_stage_ro'@'%' IDENTIFIED BY '__RO__';
GRANT SELECT ON `kp_crm_v1_reference`.* TO 'kp_stage_ro'@'%';

__REFERENCE_WRITE__
FLUSH PRIVILEGES;
'@

$dbSql = @'
DROP USER IF EXISTS 'kp_stage_etl'@'%';
DROP USER IF EXISTS 'kp_stage_restore'@'%';
DROP USER IF EXISTS 'kp_stage_ref_ro'@'%';
DROP USER IF EXISTS 'kp_stage_mask'@'%';
DROP USER IF EXISTS 'kp_stage_app'@'%';
DROP USER IF EXISTS 'kp_stage_ro'@'%';

CREATE USER 'kp_stage_etl'@'%' IDENTIFIED BY '__ETL__' ACCOUNT LOCK;

CREATE USER 'kp_stage_restore'@'%' IDENTIFIED BY '__RESTORE__';

CREATE USER 'kp_stage_mask'@'%' IDENTIFIED BY '__MASK__';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON `kp_crm_staging_masked`.* TO 'kp_stage_mask'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON `kp_crm_mask_work`.* TO 'kp_stage_mask'@'%';

CREATE USER 'kp_stage_app'@'%' IDENTIFIED BY '__APP__';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES
  ON `kp_crm_v3_validation`.* TO 'kp_stage_app'@'%';

CREATE USER 'kp_stage_ro'@'%' IDENTIFIED BY '__RO__';
GRANT SELECT ON `kp_crm_staging_masked`.* TO 'kp_stage_ro'@'%';
GRANT SELECT ON `kp_crm_v3_validation`.* TO 'kp_stage_ro'@'%';
GRANT SELECT ON `kp_crm_restore_rehearsal`.* TO 'kp_stage_ro'@'%';

__REHEARSAL_WRITE__
FLUSH PRIVILEGES;
'@

$referenceWrite = ''
if ($EnableReferenceRestore) {
    $referenceWrite = @'
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON `kp_crm_v1_reference`.* TO 'kp_stage_restore'@'%';
'@
}

$rehearsalWrite = ''
if ($EnableRehearsalRestore) {
    $rehearsalWrite = @'
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
  LOCK TABLES ON `kp_crm_restore_rehearsal`.* TO 'kp_stage_restore'@'%';
'@
}

function Expand-HardeningSql {
    param([string]$Sql, [bool]$ReferenceSql)
    $result = $Sql
    $result = $result.Replace('__ETL__', $envMap['STAGING_DB_ETL_PASSWORD'])
    $result = $result.Replace('__RESTORE__', $envMap['STAGING_DB_RESTORE_PASSWORD'])
    $result = $result.Replace('__MASK__', $envMap['STAGING_DB_MASK_PASSWORD'])
    $result = $result.Replace('__RO__', $envMap['STAGING_DB_RO_PASSWORD'])
    if ($ReferenceSql) {
        $result = $result.Replace('__REF_RO__', $envMap['STAGING_DB_REF_RO_PASSWORD'])
        $result = $result.Replace('__REFERENCE_WRITE__', $referenceWrite)
    }
    else {
        $result = $result.Replace('__APP__', $envMap['STAGING_DB_APP_PASSWORD'])
        $result = $result.Replace('__REHEARSAL_WRITE__', $rehearsalWrite)
    }
    return $result
}

$refSql = Expand-HardeningSql -Sql $refSql -ReferenceSql $true
$dbSql = Expand-HardeningSql -Sql $dbSql -ReferenceSql $false

$refRoot = '/tmp/.kpcrm-ref-hardening.cnf'
$dbRoot = '/tmp/.kpcrm-db-hardening.cnf'
try {
    New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
        -Container $script:StagingRefContainer -ContainerPath $refRoot
    New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
        -Container $script:StagingDbContainer -ContainerPath $dbRoot

    Invoke-ContainerSql -Container $script:StagingRefContainer -Sql $refSql -RootConfig $refRoot | Out-Null
    Invoke-ContainerSql -Container $script:StagingDbContainer -Sql $dbSql -RootConfig $dbRoot | Out-Null

    $refUsers = & docker exec $script:StagingRefContainer mariadb --defaults-extra-file=$refRoot `
        --batch --skip-column-names -e "SELECT User FROM mysql.user WHERE User LIKE 'kp_stage_%' ORDER BY User;" 2>&1
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron leer las cuentas de ref-db' }
    $dbUsers = & docker exec $script:StagingDbContainer mariadb --defaults-extra-file=$dbRoot `
        --batch --skip-column-names -e "SELECT User FROM mysql.user WHERE User LIKE 'kp_stage_%' ORDER BY User;" 2>&1
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron leer las cuentas de db' }

    # La app no debe existir en el servidor de referencia.
    $appOnRef = & docker exec $script:StagingRefContainer mariadb --defaults-extra-file=$refRoot `
        --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.user WHERE User='kp_stage_app';" 2>&1
    if ($LASTEXITCODE -ne 0 -or [int]($appOnRef | Select-Object -First 1) -ne 0) {
        throw 'kp_stage_app no debe existir en el servidor de referencia'
    }

    # En el servidor de proceso no debe quedar ningun grant sobre la
    # referencia legacy (aunque la base este vacia).
    $legacyRefGrants = & docker exec $script:StagingDbContainer mariadb --defaults-extra-file=$dbRoot `
        --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.db WHERE Db='kp_crm_v1_reference' AND User LIKE 'kp_stage_%';" 2>&1
    if ($LASTEXITCODE -ne 0 -or [int]($legacyRefGrants | Select-Object -First 1) -ne 0) {
        throw 'Quedaron GRANT sobre kp_crm_v1_reference en el servidor de proceso'
    }

    # Comprueba que no haya privilegios globales distintos de USAGE.
    foreach ($pair in @(
        @{ Container = $script:StagingRefContainer; Config = $refRoot; Users = $refUsers },
        @{ Container = $script:StagingDbContainer; Config = $dbRoot; Users = $dbUsers }
    )) {
        $pairContainer = [string]$pair.Container
        $pairConfig = [string]$pair.Config
        foreach ($user in @($pair.Users | Where-Object { $_ -ne '' })) {
            $grants = & docker exec $pairContainer mariadb --defaults-extra-file=$pairConfig `
                --batch --skip-column-names -e "SHOW GRANTS FOR '$user'@'%';" 2>&1
            if ($LASTEXITCODE -ne 0) { throw "No se pudieron leer los GRANT de $user" }
            foreach ($grant in @($grants)) {
                if ($grant -match 'ON\s+\*\.\*' -and $grant -notmatch '\bUSAGE\b') {
                    throw "El usuario $user tiene privilegios globales: $grant"
                }
            }
        }
    }

    $report = [ordered]@{
        ok = $true
        reference_container = $script:StagingRefContainer
        process_container = $script:StagingDbContainer
        reference_users = @($refUsers | ForEach-Object { [string]$_ })
        process_users = @($dbUsers | ForEach-Object { [string]$_ })
        reference_write_window = if ($EnableReferenceRestore) { 'enabled-temporarily' } else { 'locked' }
        rehearsal_write_window = if ($EnableRehearsalRestore) { 'enabled-temporarily' } else { 'locked' }
        app_user_on_reference = $false
        legacy_reference_grants_on_process = 0
        container_recreated = $false
        volume_modified = $false
    }
    $path = Write-JsonReport -Name 'g15_privileges_apply.json' -Data $report
    Write-Host 'Cuentas y privilegios G1.5 aplicados en ambos servidores sin recrear contenedores.'
    Write-Host "Reporte: $path"
}
finally {
    Remove-ContainerClientConfig -Container $script:StagingRefContainer -ContainerPath $refRoot
    Remove-ContainerClientConfig -Container $script:StagingDbContainer -ContainerPath $dbRoot
}
