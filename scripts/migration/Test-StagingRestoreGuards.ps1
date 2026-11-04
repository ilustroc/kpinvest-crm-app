# Pruebas negativas de G1.5 para dumps y restauraciones.
#
# - Escaner de dumps: acepta estructura/datos y rechaza sentencias peligrosas,
#   incluidas las escondidas en comentarios ejecutables o tras un INSERT.
# - Ventana cerrada: restaurar sin -Enable*Restore falla antes de escribir.
# - Destino no vacio: restaurar sobre la referencia poblada falla y cierra la
#   ventana.
# - Fallo controlado: un dump sintetico invalido deja el ensayo a medias, pero
#   el usuario de restauracion pierde la escritura y la ventana queda cerrada.
#
# Solo usa kp_crm_v1_reference (lectura) y kp_crm_restore_rehearsal (desechable).
# No toca u480021566_kpinvest_bd, kpinvest_v3_migrate_test ni kp_crm_local.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$envMap = Get-StagingEnv
Assert-StagingEnvSafe -EnvMap $envMap
Assert-Docker
Assert-ContainerRunning -Name $script:StagingDbContainer
Assert-ContainerRunning -Name $script:StagingRefContainer

$script:Results = New-Object System.Collections.Generic.List[object]
$tempRoot = Join-Path $env:TEMP 'opencode\g15-restore-guards'
if (-not (Test-Path -LiteralPath $tempRoot)) {
    New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
}
$tempRoot = (Resolve-Path -LiteralPath $tempRoot).Path

function Add-Result {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][bool]$Ok,
        [string]$Detail = ''
    )
    $script:Results.Add([pscustomobject]@{
        name = $Name
        ok = $Ok
        detail = $Detail
    })
    $mark = if ($Ok) { 'PASS' } else { 'FAIL' }
    Write-Host ("[{0}] {1} {2}" -f $mark, $Name, $Detail)
}

function Invoke-DockerCapture {
    param([Parameter(Mandatory = $true)][string[]]$Arguments)

    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & docker @Arguments 2>&1
        $code = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $old
    }

    return [pscustomobject]@{
        code = $code
        output = @($output)
        text = (($output | ForEach-Object { [string]$_ }) -join "`n")
    }
}

function Invoke-WithRootConfig {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$Sql
    )

    $configPath = '/tmp/.g15restore-' + [guid]::NewGuid().ToString('N') + '.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $Container -ContainerPath $configPath
        return Invoke-DockerCapture -Arguments @(
            'exec', $Container, 'mariadb', "--defaults-extra-file=$configPath",
            '--batch', '--skip-column-names', '--database=information_schema', '-e', $Sql
        )
    }
    finally {
        Remove-ContainerClientConfig -Container $Container -ContainerPath $configPath
    }
}

function Invoke-RoleSql {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$User,
        [Parameter(Mandatory = $true)][string]$PasswordKey,
        [Parameter(Mandatory = $true)][string]$Sql
    )

    $configPath = '/tmp/.g15role-' + [guid]::NewGuid().ToString('N') + '.cnf'
    try {
        New-ContainerClientConfig -User $User -Password $envMap[$PasswordKey] `
            -Container $Container -ContainerPath $configPath
        return Invoke-DockerCapture -Arguments @(
            'exec', $Container, 'mariadb', "--defaults-extra-file=$configPath",
            '--batch', '--skip-column-names', '-e', $Sql
        )
    }
    finally {
        Remove-ContainerClientConfig -Container $Container -ContainerPath $configPath
    }
}

function Read-JsonFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { return $null }
    try {
        return (Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json)
    }
    catch {
        return $null
    }
}

function Get-TableCount {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$Database
    )

    $probe = Invoke-WithRootConfig -Container $Container `
        -Sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Database' AND TABLE_TYPE='BASE TABLE';"
    if ($probe.code -ne 0) { return -1 }
    return [int]($probe.text.Trim())
}

function Reset-SyntheticDatabase {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][ValidateSet('kp_crm_restore_rehearsal')][string]$Database
    )

    $sql = "DROP DATABASE IF EXISTS ``$Database``;`n" +
        "CREATE DATABASE ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $probe = Invoke-WithRootConfig -Container $Container -Sql $sql
    if ($probe.code -ne 0) {
        throw "No se pudo reiniciar la base sintetica $Database"
    }
}

function Invoke-RestoreExpectFailure {
    param(
        [Parameter(Mandatory = $true)][string]$DumpPath,
        [Parameter(Mandatory = $true)][string]$TargetDatabase
    )

    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & (Join-Path $PSScriptRoot 'Restore-Reference.ps1') `
            -DumpPath $DumpPath -TargetDatabase $TargetDatabase -Execute 2>&1
        $code = $LASTEXITCODE
        if ($null -eq $code -or $code -eq 0) {
            # Un script PowerShell que lanza una excepcion no siempre fija
            # LASTEXITCODE; la senal fiable es el reporte JSON de fallo.
            $code = 1
        }
        $text = (($output | ForEach-Object { [string]$_ }) -join "`n")
    }
    catch {
        $code = 1
        $text = $_.Exception.Message
    }
    finally {
        $ErrorActionPreference = $old
    }

    return [pscustomobject]@{
        code = $code
        text = $text
    }
}

# --- Escaner de dumps -------------------------------------------------------
$repoFixture = Join-Path $script:RepoRoot 'tests\fixtures\staging\synthetic_v1.sql'
# Restore-Reference.ps1 exige que el dump este fuera del repositorio, igual que
# un dump operativo. Se usa una copia temporal del fixture sintetico.
$outsideFixture = Join-Path $tempRoot 'synthetic_v1.sql'
Copy-Item -LiteralPath $repoFixture -Destination $outsideFixture -Force
$goodScan = $false
try {
    $goodScan = Assert-SafeSqlDump -Path $repoFixture -AllowedDatabase 'kp_crm_v1_reference'
}
catch {
    $goodScan = $false
}
Add-Result -Name 'dump-scan:fixture-allowed' -Ok ([bool]$goodScan) -Detail 'synthetic-fixture'

$scanCases = @(
    @{ Name = 'use'; Sql = "USE kp_crm_v1_reference;`nCREATE TABLE t (id INT);"; Allowed = $false },
    @{ Name = 'create-database'; Sql = "CREATE DATABASE evil;"; Allowed = $false },
    @{ Name = 'drop-schema'; Sql = "DROP SCHEMA evil;"; Allowed = $false },
    @{ Name = 'alter-database'; Sql = "ALTER DATABASE evil CHARACTER SET utf8mb4;"; Allowed = $false },
    @{ Name = 'create-user'; Sql = "CREATE USER 'x'@'%' IDENTIFIED BY 'x';"; Allowed = $false },
    @{ Name = 'grant'; Sql = "GRANT ALL ON *.* TO 'x'@'%';"; Allowed = $false },
    @{ Name = 'flush'; Sql = "FLUSH PRIVILEGES;"; Allowed = $false },
    @{ Name = 'set-global'; Sql = "SET GLOBAL max_connections=1000;"; Allowed = $false },
    @{ Name = 'load-data'; Sql = "LOAD DATA INFILE '/etc/passwd' INTO TABLE t;"; Allowed = $false },
    @{ Name = 'outfile'; Sql = "SELECT * FROM t INTO OUTFILE '/tmp/x';"; Allowed = $false },
    @{ Name = 'definer'; Sql = "CREATE DEFINER='root'@'localhost' VIEW v AS SELECT 1;"; Allowed = $false },
    @{ Name = 'delimiter'; Sql = "DELIMITER //"; Allowed = $false },
    @{ Name = 'handler'; Sql = "HANDLER t OPEN;"; Allowed = $false },
    @{ Name = 'after-insert-same-line'; Sql = "INSERT INTO t VALUES (1); DROP DATABASE evil;"; Allowed = $false },
    @{ Name = 'executable-comment'; Sql = "/*!50000 DROP DATABASE evil */"; Allowed = $false },
    @{ Name = 'mariadb-executable-comment'; Sql = "/*M!100100 DROP DATABASE evil */"; Allowed = $false },
    @{ Name = 'normal-comment-allowed'; Sql = "-- DROP DATABASE evil`n# GRANT ALL ON *.* TO x`n/* CREATE USER x */`nCREATE TABLE t (id INT);"; Allowed = $true },
    @{ Name = 'insert-allowed'; Sql = "CREATE TABLE t (id INT);`nINSERT INTO t VALUES (1);"; Allowed = $true }
)

foreach ($case in $scanCases) {
    $path = Join-Path $tempRoot ('scan-' + $case.Name + '.sql')
    [System.IO.File]::WriteAllText($path, [string]$case.Sql, [System.Text.UTF8Encoding]::new($false))
    $accepted = $true
    try {
        Assert-SafeSqlDump -Path $path -AllowedDatabase 'kp_crm_v1_reference' | Out-Null
    }
    catch {
        $accepted = $false
    }
    Add-Result -Name ('dump-scan:' + $case.Name) -Ok ($accepted -eq [bool]$case.Allowed) `
        -Detail ('expected=' + $(if ($case.Allowed) { 'allowed' } else { 'rejected' }))
    Remove-Item -LiteralPath $path -Force -ErrorAction SilentlyContinue
}

# --- Ensayo: ventana cerrada ------------------------------------------------
Reset-SyntheticDatabase -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal'
$closedWindow = Invoke-RestoreExpectFailure -DumpPath $outsideFixture -TargetDatabase 'kp_crm_restore_rehearsal'
$closedReport = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\restore_kp_crm_restore_rehearsal_failed.json')
$closedOk = ($closedWindow.text -match 'ventana de restauracion no esta abierta') -and
    ($null -ne $closedReport) -and ($closedReport.ok -eq $false) -and
    ($closedReport.write_window_closed -eq $true)
Add-Result -Name 'restore:closed-window-rejected' -Ok $closedOk -Detail 'no-write-window'
Add-Result -Name 'restore:closed-window-no-tables' -Ok ((Get-TableCount -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal') -eq 0) -Detail 'expected=0'

# --- Ensayo: dump invalido, fallo controlado y cierre de ventana ------------
& (Join-Path $PSScriptRoot 'Apply-StagingHardening.ps1') -Execute -EnableRehearsalRestore | Out-Null
$invalidDump = Join-Path $tempRoot 'invalid-synthetic.sql'
[System.IO.File]::WriteAllText(
    $invalidDump,
    "CREATE TABLE ``g15_probe`` (``id`` INT NOT NULL) ENGINE=InnoDB;`n" +
    "INSERT INTO ``g15_probe`` VALUES (1);`n" +
    "THIS IS NOT VALID SQL;`n",
    [System.Text.UTF8Encoding]::new($false)
)
$invalid = Invoke-RestoreExpectFailure -DumpPath $invalidDump -TargetDatabase 'kp_crm_restore_rehearsal'
$invalidReport = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\restore_kp_crm_restore_rehearsal_failed.json')
$invalidOk = ($invalid.code -ne 0) -and ($null -ne $invalidReport) -and
    ($invalidReport.ok -eq $false) -and ($invalidReport.write_window_closed -eq $true)
Add-Result -Name 'restore:invalid-dump-fails' -Ok $invalidOk -Detail 'controlled-failure'
Add-Result -Name 'restore:invalid-partial-table' -Ok ((Get-TableCount -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal') -eq 1) -Detail 'expected=1'

$restoreWrite = Invoke-RoleSql -Container $script:StagingDbContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_restore_rehearsal.__g15_probe (id INT);'
Add-Result -Name 'restore:invalid-closes-write' -Ok ($restoreWrite.code -ne 0) -Detail 'expected=denied'

$roRead = Invoke-RoleSql -Container $script:StagingDbContainer `
    -User 'kp_stage_ro' -PasswordKey 'STAGING_DB_RO_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_restore_rehearsal.g15_probe;'
Add-Result -Name 'restore:ro-can-audit-partial' -Ok ($roRead.code -eq 0) -Detail 'expected=allowed'

Reset-SyntheticDatabase -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal'
Add-Result -Name 'restore:rehearsal-cleaned' -Ok ((Get-TableCount -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal') -eq 0) -Detail 'expected=0'

# --- Referencia: rechazo de destino no vacio y cierre de ventana ------------
$referenceTablesBefore = Get-TableCount -Container $script:StagingRefContainer -Database 'kp_crm_v1_reference'
& (Join-Path $PSScriptRoot 'Apply-StagingHardening.ps1') -Execute -EnableReferenceRestore | Out-Null
$nonEmpty = Invoke-RestoreExpectFailure -DumpPath $outsideFixture -TargetDatabase 'kp_crm_v1_reference'
$nonEmptyReport = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\restore_kp_crm_v1_reference_failed.json')
$nonEmptyOk = ($nonEmpty.text -match 'no esta vacia') -and ($null -ne $nonEmptyReport) -and
    ($nonEmptyReport.ok -eq $false) -and ($nonEmptyReport.write_window_closed -eq $true)
Add-Result -Name 'restore:non-empty-target-rejected' -Ok $nonEmptyOk -Detail 'no-overwrite'
Add-Result -Name 'restore:reference-intact' -Ok ((Get-TableCount -Container $script:StagingRefContainer -Database 'kp_crm_v1_reference') -eq $referenceTablesBefore) -Detail ('tables=' + $referenceTablesBefore)

$restoreRefWrite = Invoke-RoleSql -Container $script:StagingRefContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_v1_reference.__g15_probe (id INT);'
Add-Result -Name 'restore:non-empty-closes-write' -Ok ($restoreRefWrite.code -ne 0) -Detail 'expected=denied'

$successReport = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\restore_kp_crm_v1_reference.json')
$successOk = ($null -ne $successReport) -and ($successReport.ok -eq $true) -and
    ($successReport.tables -eq 14) -and ($successReport.write_window_closed -eq $true)
Add-Result -Name 'restore:success-report-preserved' -Ok $successOk -Detail '14-tables'

# --- Estado final -----------------------------------------------------------
# Si una prueba negativa hubiera dejado el probe, se retira antes del conteo.
# El resultado de la prueba ya quedo registrado arriba.
Invoke-WithRootConfig -Container $script:StagingRefContainer `
    -Sql 'DROP TABLE IF EXISTS kp_crm_v1_reference.__g15_probe;' | Out-Null
Invoke-WithRootConfig -Container $script:StagingDbContainer `
    -Sql 'DROP TABLE IF EXISTS kp_crm_restore_rehearsal.__g15_probe;' | Out-Null

# Cierre defensivo de ambas ventanas y verificacion real de los GRANT.
& (Join-Path $PSScriptRoot 'Apply-StagingHardening.ps1') -Execute | Out-Null
$refGrants = Invoke-RoleSql -Container $script:StagingRefContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' -Sql 'SHOW GRANTS;'
$rehearsalGrants = Invoke-RoleSql -Container $script:StagingDbContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' -Sql 'SHOW GRANTS;'
Add-Result -Name 'state:reference-window-closed' -Ok ($refGrants.text -notmatch 'kp_crm_v1_reference') -Detail 'restore-user-no-grant'
Add-Result -Name 'state:rehearsal-window-closed' -Ok ($rehearsalGrants.text -notmatch 'kp_crm_restore_rehearsal') -Detail 'restore-user-no-grant'

$referenceTables = Get-TableCount -Container $script:StagingRefContainer -Database 'kp_crm_v1_reference'
$maskedTables = Get-TableCount -Container $script:StagingDbContainer -Database 'kp_crm_staging_masked'
$rehearsalTables = Get-TableCount -Container $script:StagingDbContainer -Database 'kp_crm_restore_rehearsal'
$finalOk = ($referenceTables -eq 14) -and ($maskedTables -eq 14) -and ($rehearsalTables -eq 0)
Add-Result -Name 'state:final' -Ok $finalOk -Detail ("ref=$referenceTables;masked=$maskedTables;rehearsal=$rehearsalTables")

# Limpieza de dumps temporales (no quedan copias de datos ni SQL de prueba).
Get-ChildItem -LiteralPath $tempRoot -Filter '*.sql' -File -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

$failures = @($script:Results | Where-Object { -not $_.ok })
$report = [ordered]@{
    ok = ($failures.Count -eq 0)
    generated_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    reference_container = $script:StagingRefContainer
    process_container = $script:StagingDbContainer
    reference_tables = $referenceTables
    masked_tables = $maskedTables
    rehearsal_tables = $rehearsalTables
    write_windows = 'closed'
    test_count = $script:Results.Count
    failure_count = $failures.Count
    failures = @($failures | ForEach-Object { [ordered]@{ name = $_.name; detail = $_.detail } })
    tests = @($script:Results | ForEach-Object { [ordered]@{ name = $_.name; ok = $_.ok; detail = $_.detail } })
}
$path = Write-JsonReport -Name 'g15_restore_guards.json' -Data $report
Write-Host ''
Write-Host ("Pruebas: {0}; fallos: {1}" -f $script:Results.Count, $failures.Count)
Write-Host "Reporte: $path"

if ($failures.Count -ne 0) {
    throw ("Fallo las pruebas de restauracion G1.5: " + (($failures | ForEach-Object { $_.name }) -join ', '))
}
