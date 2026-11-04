# Restaura un dump SQL autorizado en kp_crm_v1_reference (servidor de
# referencia aislado) o en el ensayo de rollback (servidor de proceso).
# Requiere -Execute y una ventana de escritura abierta explicitamente.
# No toca u480021566_kpinvest_bd, kpinvest_v3_migrate_test ni kp_crm_local.
#
# Cualquier fallo, incluso antes de copiar el dump (ruta invalida, destino no
# vacio, dump rechazado por el escaner), cierra la ventana de escritura. Nunca
# se deja kp_stage_restore con permiso de escritura por una excepcion.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$DumpPath,
    [ValidateSet('kp_crm_v1_reference', 'kp_crm_restore_rehearsal')]
    [string]$TargetDatabase = 'kp_crm_v1_reference',
    [switch]$Execute
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

Assert-Execute -Execute $Execute.IsPresent -Action "restaurar $DumpPath en $TargetDatabase"
Assert-Docker

$container = Get-StagingDatabaseContainer -Database $TargetDatabase
$clientConfig = '/tmp/.kpcrm-restore.cnf'
$roConfig = '/tmp/.kpcrm-restore-check.cnf'
$remoteDump = '/tmp/kpcrm-reference-restore.sql'
$remoteSession = '/tmp/kpcrm-session.sql'
$fullDump = $null
$dumpHash = ''
$locked = $false
$lockError = $null

try {
    $resolved = Resolve-Path -LiteralPath $DumpPath -ErrorAction Stop
    $fullDump = Assert-OutsideRepo -Path $resolved.Path
    if ([System.IO.Path]::GetExtension($fullDump).ToLowerInvariant() -ne '.sql') {
        throw 'El dump debe ser un archivo .sql sin comprimir'
    }
    Assert-SafeSqlDump -Path $fullDump -AllowedDatabase $TargetDatabase

    Assert-ContainerRunning -Name $container

    $envMap = Get-StagingEnv
    Assert-StagingEnvSafe -EnvMap $envMap
    foreach ($key in @('STAGING_DB_RESTORE_PASSWORD', 'STAGING_DB_RO_PASSWORD')) {
        if (-not $envMap.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($envMap[$key])) {
            throw "Falta $key en el archivo de entorno externo (.env.staging)"
        }
    }

    $dumpHash = (Get-FileHash -LiteralPath $fullDump -Algorithm SHA256).Hash

    New-ContainerClientConfig -User 'kp_stage_restore' -Password $envMap['STAGING_DB_RESTORE_PASSWORD'] -Container $container -ContainerPath $clientConfig
    New-ContainerClientConfig -User 'kp_stage_ro' -Password $envMap['STAGING_DB_RO_PASSWORD'] -Container $container -ContainerPath $roConfig

    # La ventana debe estar abierta; si no, se falla antes de copiar el dump.
    $grants = & docker exec $container mariadb --defaults-extra-file=$clientConfig `
        --batch --skip-column-names -e "SHOW GRANTS FOR 'kp_stage_restore'@'%';" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudieron leer los permisos del usuario de restauracion'
    }
    if (($grants -join "`n") -notmatch [regex]::Escape($TargetDatabase) -or
        ($grants -join "`n") -notmatch '(?i)\b(INSERT|CREATE)\b') {
        $flag = if ($TargetDatabase -eq 'kp_crm_v1_reference') { '-EnableReferenceRestore' } else { '-EnableRehearsalRestore' }
        throw "La ventana de restauracion no esta abierta para $TargetDatabase. Ejecute Apply-StagingHardening.ps1 -Execute $flag"
    }

    # El usuario de restauracion pierde SELECT al bloquear la base;
    # information_schema ocultaria las tablas y el control de destino no vacio
    # daria un falso negativo. La comprobacion se hace con el rol de solo
    # lectura, que conserva SELECT.
    $existing = Invoke-ContainerScalar -Sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$TargetDatabase' AND TABLE_TYPE='BASE TABLE';" -Container $container -ContainerPath $roConfig
    if ([int]$existing -ne 0) {
        throw "$TargetDatabase no esta vacia ($existing tablas). No se sobrescribe; revise y recreela solo si corresponde."
    }

    & docker cp $fullDump "${container}:$remoteDump"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar el dump al contenedor' }

    $session = "SET NAMES utf8mb4;`nSET time_zone='+00:00';`nSET FOREIGN_KEY_CHECKS=0;`n"
    $session | & docker exec -i $container sh -c "cat > $remoteSession"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la sesion de restauracion' }

    & docker exec -i $container sh -c "cat $remoteSession $remoteDump | mariadb --defaults-extra-file=$clientConfig --default-character-set=utf8mb4 $TargetDatabase"
    if ($LASTEXITCODE -ne 0) { throw 'La restauracion fallo' }

    $tables = [int](Invoke-ContainerScalar -Sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$TargetDatabase' AND TABLE_TYPE='BASE TABLE';" -Container $container -ContainerPath $clientConfig)
    if ($tables -ne 14) {
        throw "La base destino quedo con $tables tablas; se esperaban 14"
    }

    $migrations = [int](Invoke-ContainerScalar -Sql "SELECT COUNT(*) FROM $TargetDatabase.migrations;" -Container $container -ContainerPath $clientConfig)

    if ($TargetDatabase -eq 'kp_crm_v1_reference') {
        Lock-ReferenceDatabase
    }
    else {
        Lock-RehearsalDatabase
    }
    $locked = $true

    $report = [ordered]@{
        ok = $true
        database = $TargetDatabase
        container = $container
        dump_sha256 = $dumpHash
        dump_security_scan = 'ok'
        tables = $tables
        migrations = $migrations
        write_window_closed = $locked
        reference_restore_user_write = if ($TargetDatabase -eq 'kp_crm_v1_reference') { 'revoked' } else { 'not-applicable' }
        rehearsal_restore_user_write = if ($TargetDatabase -eq 'kp_crm_restore_rehearsal') { 'revoked' } else { 'not-applicable' }
    }
    $path = Write-JsonReport -Name ("restore_" + $TargetDatabase + '.json') -Data $report
    Write-Host "Restauracion completada: $tables tablas, $migrations migraciones."
    Write-Host "Ventana cerrada: $TargetDatabase ya no admite escritura de restauracion."
    Write-Host "Reporte: $path"
}
catch {
    $errorMessage = $_.Exception.Message
    if (-not $locked) {
        try {
            # Cualquier fallo (ruta invalida, dump rechazado, destino no vacio,
            # SQL invalido) debe cerrar la ventana. Lock es idempotente.
            if ($TargetDatabase -eq 'kp_crm_v1_reference') { Lock-ReferenceDatabase }
            else { Lock-RehearsalDatabase }
            $locked = $true
        }
        catch {
            $lockError = $_.Exception.Message
        }
    }
    $failure = [ordered]@{
        ok = $false
        database = $TargetDatabase
        container = $container
        dump_sha256 = $dumpHash
        error = $errorMessage
        write_window_closed = $locked
        lock_error = $lockError
    }
    $failurePath = Write-JsonReport -Name ("restore_" + $TargetDatabase + '_failed.json') -Data $failure
    Write-Host "Fallo registrado: $failurePath"
    throw
}
finally {
    try { & docker exec $container rm -f $remoteDump $remoteSession 2>$null | Out-Null } catch { }
    Remove-ContainerClientConfig -Container $container -ContainerPath $clientConfig
    Remove-ContainerClientConfig -Container $container -ContainerPath $roConfig
}
