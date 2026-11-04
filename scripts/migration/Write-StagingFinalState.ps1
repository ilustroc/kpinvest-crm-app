# Genera storage/staging/reports/g15_final_state.json con el estado real de
# contenedores, redes, volumenes, bases y ventanas de restauracion.
# Es de solo lectura: usa archivos de cliente temporales dentro de los
# contenedores y no imprime contrasenas ni hashes de credenciales.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$envMap = Get-StagingEnv
Assert-StagingEnvSafe -EnvMap $envMap
Assert-Docker

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

function Invoke-RootSql {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$Sql
    )

    $configPath = '/tmp/.g15state-' + [guid]::NewGuid().ToString('N') + '.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
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

function Get-DatabaseState {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$Database
    )

    $tablesProbe = Invoke-RootSql -Container $Container `
        -Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Database' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;"
    if ($tablesProbe.code -ne 0) {
        return [ordered]@{ exists = $false; tables = -1; rows = -1 }
    }
    $tables = @($tablesProbe.text -split "`n" | Where-Object { $_.Trim() -ne '' })
    $rows = 0
    foreach ($table in $tables) {
        $clean = $table.Trim()
        if ($clean -notmatch '^[A-Za-z0-9_]+$') { continue }
        $countProbe = Invoke-RootSql -Container $Container `
            -Sql "SELECT COUNT(*) FROM ``$Database``.``$clean``;"
        if ($countProbe.code -eq 0) {
            $rows += [int]$countProbe.text.Trim()
        }
    }
    return [ordered]@{
        exists = $true
        tables = $tables.Count
        rows = $rows
    }
}

function Get-SanitizedGrants {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$User
    )

    $probe = Invoke-RootSql -Container $Container -Sql "SHOW GRANTS FOR '$User'@'%';"
    if ($probe.code -ne 0) { return @() }
    $grants = @()
    foreach ($line in @($probe.text -split "`n" | Where-Object { $_.Trim() -ne '' })) {
        # Nunca persistir el hash de la contrasena en el reporte.
        $clean = [regex]::Replace($line.Trim(), "IDENTIFIED BY PASSWORD '[^']*'", 'IDENTIFIED BY PASSWORD <redacted>')
        $grants += $clean
    }
    return $grants
}

function Read-ReportSummary {
    param([Parameter(Mandatory = $true)][string]$Name)

    $path = Join-Path $script:RepoRoot ('storage\staging\reports\' + $Name)
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { return $null }
    try {
        $json = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
    }
    catch {
        return $null
    }
    return [ordered]@{
        ok = if ($null -ne $json.PSObject.Properties['ok']) { $json.ok } else { $null }
        test_count = if ($null -ne $json.PSObject.Properties['test_count']) { $json.test_count } else { $null }
        failure_count = if ($null -ne $json.PSObject.Properties['failure_count']) { $json.failure_count } else { $null }
        hash_equal = if ($null -ne $json.PSObject.Properties['hash_equal']) { $json.hash_equal } else { $null }
        total_rows = if ($null -ne $json.PSObject.Properties['masked']) { $json.masked.total_rows } else { $null }
        verification_ok = if ($null -ne $json.PSObject.Properties['verification_ok']) { $json.verification_ok } else { $null }
        tables = if ($null -ne $json.PSObject.Properties['tables']) { $json.tables } else { $null }
        migrations = if ($null -ne $json.PSObject.Properties['migrations']) { $json.migrations } else { $null }
        write_window_closed = if ($null -ne $json.PSObject.Properties['write_window_closed']) { $json.write_window_closed } else { $null }
    }
}

$stagingContainers = @(
    'kpcrm-staging-ref-db',
    'kpcrm-staging-db',
    'kpcrm-staging-app',
    'kpcrm-staging-web'
)
$containerState = [ordered]@{}
foreach ($name in $stagingContainers) {
    $inspect = Invoke-DockerCapture -Arguments @(
        'inspect', $name, '--format',
        '{{json .State}}|{{.Image}}|{{.RestartCount}}|{{.State.StartedAt}}'
    )
    if ($inspect.code -ne 0) {
        $containerState[$name] = [ordered]@{ running = $false }
        continue
    }
    $parts = $inspect.text.Trim() -split '\|', 4
    $state = $parts[0] | ConvertFrom-Json
    $networks = Invoke-DockerCapture -Arguments @(
        'inspect', $name, '--format',
        '{{range $n, $_ := .NetworkSettings.Networks}}{{$n}} {{end}}'
    )
    $containerState[$name] = [ordered]@{
        running = [bool]$state.Running
        status = [string]$state.Status
        health = if ($null -ne $state.Health) { [string]$state.Health.Status } else { 'none' }
        image = [string]$parts[1]
        restarts = [int]$parts[2]
        started_at = [string]$parts[3]
        networks = @(($networks.text -split '\s+') | Where-Object { $_ -ne '' } | Sort-Object)
    }
}

$networkState = [ordered]@{}
foreach ($network in @(
    'kpcrm_staging_ref',
    'kpcrm_staging_tools_db',
    'kpcrm_staging_private',
    'kpcrm_staging_app_net',
    'kpcrm_staging_web'
)) {
    $inspect = Invoke-DockerCapture -Arguments @(
        'inspect', $network, '--format',
        '{{.Internal}}|{{range .Containers}}{{.Name}} {{end}}'
    )
    if ($inspect.code -ne 0) {
        $networkState[$network] = [ordered]@{ exists = $false }
        continue
    }
    $parts = $inspect.text.Trim() -split '\|', 2
    $members = @()
    if ($parts.Count -gt 1) {
        $members = @(($parts[1] -split '\s+') | Where-Object { $_ -ne '' } | Sort-Object)
    }
    $networkState[$network] = [ordered]@{
        exists = $true
        internal = ([string]$parts[0] -eq 'true')
        members = $members
    }
}

$volumeState = @()
$volumeProbe = Invoke-DockerCapture -Arguments @(
    'volume', 'ls', '--format', '{{.Name}}|{{.Driver}}|{{.Mountpoint}}'
)
foreach ($line in @($volumeProbe.text -split "`n" | Where-Object { $_ -match '^kpcrm_staging' })) {
    $parts = $line.Trim() -split '\|', 3
    $users = Invoke-DockerCapture -Arguments @(
        'ps', '-a', '--filter', "volume=$($parts[0])", '--format', '{{.Names}}'
    )
    $volumeState += [ordered]@{
        name = $parts[0]
        driver = $parts[1]
        mountpoint = $parts[2]
        used_by = @(($users.text -split "`n") | Where-Object { $_.Trim() -ne '' })
    }
}

$databases = [ordered]@{}
foreach ($pair in @(
    @{ Container = $script:StagingRefContainer; Name = 'kp_crm_v1_reference' },
    @{ Container = $script:StagingDbContainer; Name = 'kp_crm_staging_masked' },
    @{ Container = $script:StagingDbContainer; Name = 'kp_crm_mask_work' },
    @{ Container = $script:StagingDbContainer; Name = 'kp_crm_v3_validation' },
    @{ Container = $script:StagingDbContainer; Name = 'kp_crm_restore_rehearsal' }
)) {
    $container = [string]$pair.Container
    $name = [string]$pair.Name
    $databases[$name] = Get-DatabaseState -Container $container -Database $name
    $databases[$name]['container'] = $container
}

$grants = [ordered]@{}
foreach ($pair in @(
    @{ Label = 'reference'; Container = $script:StagingRefContainer; Users = @('kp_stage_etl', 'kp_stage_restore', 'kp_stage_ref_ro', 'kp_stage_mask', 'kp_stage_ro') },
    @{ Label = 'process'; Container = $script:StagingDbContainer; Users = @('kp_stage_etl', 'kp_stage_restore', 'kp_stage_mask', 'kp_stage_app', 'kp_stage_ro') }
)) {
    $label = [string]$pair.Label
    $container = [string]$pair.Container
    $grants[$label] = [ordered]@{}
    foreach ($user in @($pair.Users)) {
        $grants[$label][$user] = @(Get-SanitizedGrants -Container $container -User $user)
    }
}

$restoreWindows = [ordered]@{
    reference_open = ((@($grants['reference']['kp_stage_restore']) -join "`n") -match 'kp_crm_v1_reference')
    rehearsal_open = ((@($grants['process']['kp_stage_restore']) -join "`n") -match 'kp_crm_restore_rehearsal')
}

$healthz = $false
$healthzBody = ''
$loginStatus = 0
try {
    $health = Invoke-WebRequest -Uri 'http://127.0.0.1:8090/healthz' -UseBasicParsing -TimeoutSec 20
    $healthzBody = if ($health.Content -is [byte[]]) { [System.Text.Encoding]::UTF8.GetString($health.Content) } else { [string]$health.Content }
    $healthz = ($health.StatusCode -eq 200) -and ($healthzBody.Trim() -eq 'ok')
}
catch { }
try {
    $login = Invoke-WebRequest -Uri 'http://127.0.0.1:8090/login' -UseBasicParsing -TimeoutSec 30
    $loginStatus = [int]$login.StatusCode
}
catch { }

$crmStatus = 0
try {
    $crm = Invoke-WebRequest -Uri 'http://127.0.0.1:8088/' -UseBasicParsing -TimeoutSec 20
    $crmStatus = [int]$crm.StatusCode
}
catch { }

$report = [ordered]@{
    ok = ($restoreWindows.reference_open -eq $false) -and
        ($restoreWindows.rehearsal_open -eq $false) -and
        ($databases['kp_crm_v1_reference'].tables -eq 14) -and
        ($databases['kp_crm_staging_masked'].tables -eq 14) -and
        ($databases['kp_crm_staging_masked'].rows -eq 61) -and
        ($databases['kp_crm_restore_rehearsal'].tables -eq 0) -and
        $healthz -and ($loginStatus -eq 200)
    generated_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    stage2_started = $false
    historical_data_processed = $false
    containers = $containerState
    networks = $networkState
    volumes = $volumeState
    databases = $databases
    restore_windows = $restoreWindows
    grants = $grants
    health = [ordered]@{
        staging_healthz = $healthz
        staging_healthz_body = $healthzBody.Trim()
        staging_login_status = $loginStatus
        existing_crm_8088_status = $crmStatus
    }
    reports = [ordered]@{
        isolation = Read-ReportSummary -Name 'g15_isolation.json'
        restore_guards = Read-ReportSummary -Name 'g15_restore_guards.json'
        determinism = Read-ReportSummary -Name 'g15_determinism.json'
        preflight = Read-ReportSummary -Name 'preflight.json'
        mask_manifest = Read-ReportSummary -Name 'mask_manifest.json'
        masking_validation = Read-ReportSummary -Name 'masking_validation.json'
        privileges = Read-ReportSummary -Name 'g15_privileges_apply.json'
        secrets_acl = Read-ReportSummary -Name 'g15_secrets_acl.json'
        reference_restore = Read-ReportSummary -Name 'restore_kp_crm_v1_reference.json'
        reference_restore_failed = Read-ReportSummary -Name 'restore_kp_crm_v1_reference_failed.json'
    }
}
$path = Write-JsonReport -Name 'g15_final_state.json' -Data $report
Write-Host "Reporte final: $path"
Write-Host ("ok={0}; ref={1} tablas; masked={2} filas; rehearsal={3} tablas; ventanas ref={4}, ensayo={5}" -f
    $report.ok,
    $databases['kp_crm_v1_reference'].tables,
    $databases['kp_crm_staging_masked'].rows,
    $databases['kp_crm_restore_rehearsal'].tables,
    $(if ($restoreWindows.reference_open) { 'ABIERTA' } else { 'cerrada' }),
    $(if ($restoreWindows.rehearsal_open) { 'ABIERTA' } else { 'cerrada' }))

if (-not $report.ok) {
    throw 'El estado final de staging no cumple las condiciones esperadas'
}
