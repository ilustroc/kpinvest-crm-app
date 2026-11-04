# Comprueba que el enmascaramiento sintetico es determinista.
#
# Reinicia solo kp_crm_staging_masked (base desechable), ejecuta dos veces el
# mismo preflight+mask y compara el SHA-256 de un dump canonico. No toca la
# referencia ni mueve datos fuera de staging.
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

function Reset-MaskedDatabase {
    $configPath = '/tmp/.g15det-reset.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $script:StagingDbContainer -ContainerPath $configPath
        $sql = "DROP DATABASE IF EXISTS ``kp_crm_staging_masked``;`n" +
            "CREATE DATABASE ``kp_crm_staging_masked`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $probe = Invoke-DockerCapture -Arguments @(
            'exec', $script:StagingDbContainer, 'mariadb', "--defaults-extra-file=$configPath",
            '--batch', '--skip-column-names', '-e', $sql
        )
        if ($probe.code -ne 0) { throw 'No se pudo reiniciar kp_crm_staging_masked' }
    }
    finally {
        Remove-ContainerClientConfig -Container $script:StagingDbContainer -ContainerPath $configPath
    }
}

function Get-MaskedDumpHash {
    $configPath = '/tmp/.g15det-dump.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $script:StagingDbContainer -ContainerPath $configPath
        $command = "mariadb-dump --defaults-extra-file=$configPath" +
            ' --skip-comments --skip-dump-date --order-by-primary --skip-extended-insert' +
            ' --no-tablespaces --single-transaction --skip-lock-tables' +
            ' --default-character-set=utf8mb4 kp_crm_staging_masked | sha256sum'
        $probe = Invoke-DockerCapture -Arguments @('exec', $script:StagingDbContainer, 'sh', '-c', $command)
        if ($probe.code -ne 0) { throw 'No se pudo calcular el hash del dump enmascarado' }
        return (($probe.text -split '\s+') | Where-Object { $_ -ne '' } | Select-Object -First 1)
    }
    finally {
        Remove-ContainerClientConfig -Container $script:StagingDbContainer -ContainerPath $configPath
    }
}

function Read-JsonFile {
    param([Parameter(Mandatory = $true)][string]$Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { return $null }
    try { return (Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json) } catch { return $null }
}

$runs = @()
foreach ($pass in 1..2) {
    Reset-MaskedDatabase
    & (Join-Path $PSScriptRoot 'Invoke-Masking.ps1') -Execute | Out-Null
    $manifest = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\mask_manifest.json')
    $validation = Read-JsonFile -Path (Join-Path $script:RepoRoot 'storage\staging\reports\masking_validation.json')
    $runs += [ordered]@{
        pass = $pass
        dump_sha256 = Get-MaskedDumpHash
        total_rows = if ($null -ne $manifest) { [int]$manifest.masked.total_rows } else { -1 }
        verification_ok = if ($null -ne $validation) { [bool]$validation.ok } else { $false }
    }
}

$deterministic = ($runs.Count -eq 2) -and
    ($runs[0].dump_sha256 -eq $runs[1].dump_sha256) -and
    ($runs[0].dump_sha256 -ne '') -and
    ($runs[0].total_rows -eq 61) -and
    ($runs[1].total_rows -eq 61) -and
    $runs[0].verification_ok -and $runs[1].verification_ok

$report = [ordered]@{
    ok = $deterministic
    generated_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    database = 'kp_crm_staging_masked'
    expected_total_rows = 61
    hash_equal = ($runs.Count -eq 2) -and ($runs[0].dump_sha256 -eq $runs[1].dump_sha256)
    runs = $runs
    note = 'Dump canonico: sin comentarios, sin fecha, orden por PK, inserts de una fila.'
}
$path = Write-JsonReport -Name 'g15_determinism.json' -Data $report
Write-Host ("Hash 1: " + $runs[0].dump_sha256)
Write-Host ("Hash 2: " + $runs[1].dump_sha256)
Write-Host ("Determinista: " + $deterministic)
Write-Host "Reporte: $path"

if (-not $deterministic) {
    throw 'El enmascaramiento no fue determinista'
}
