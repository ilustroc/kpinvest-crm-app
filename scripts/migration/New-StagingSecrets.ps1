# Genera .env.staging y la clave HMAC fuera del repositorio.
# El archivo de entorno no se coloca en el repo porque el CRM actual monta el
# repo completo y podria leer las credenciales. No imprime secretos.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$MaskKeyPath,
    [string]$ImportDir = '',
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$keyPath = Assert-OutsideRepo -Path $MaskKeyPath
if ($ImportDir -eq '') {
    $ImportDir = Join-Path (Split-Path -Parent $keyPath) 'import'
}
$importPath = Assert-OutsideRepo -Path $ImportDir
if ((Split-Path -Leaf $importPath) -ne 'import') {
    throw "El directorio de importacion debe llamarse exactamente 'import': $importPath"
}

if ((Test-Path -LiteralPath $script:StagingEnvFile) -and -not $Force) {
    throw "Ya existe el archivo de entorno $script:StagingEnvFile. Use -Force solo si desea reemplazarlo."
}
if ((Test-Path -LiteralPath $keyPath) -and -not $Force) {
    throw "Ya existe la clave HMAC en $keyPath. Use -Force solo si desea reemplazarla."
}

function New-RandomHex {
    param([Parameter(Mandatory = $true)][int]$Bytes)

    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $buffer = New-Object byte[] $Bytes
        $rng.GetBytes($buffer)
        return ([System.BitConverter]::ToString($buffer)).Replace('-', '').ToLowerInvariant()
    }
    finally {
        $rng.Dispose()
    }
}

$appKeyBytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try { $rng.GetBytes($appKeyBytes) } finally { $rng.Dispose() }

$appKey = 'base64:' + [System.Convert]::ToBase64String($appKeyBytes)
$rootPassword = New-RandomHex -Bytes 32
$etlPassword = New-RandomHex -Bytes 24
$restorePassword = New-RandomHex -Bytes 24
$refRoPassword = New-RandomHex -Bytes 24
$maskPassword = New-RandomHex -Bytes 24
$appPassword = New-RandomHex -Bytes 24
$roPassword = New-RandomHex -Bytes 24
$testPassword = 'Stg-' + (New-RandomHex -Bytes 24)
$hmacKey = New-RandomHex -Bytes 32

$keyParent = Split-Path -Parent $keyPath
if (-not (Test-Path -LiteralPath $keyParent)) {
    New-Item -ItemType Directory -Path $keyParent -Force | Out-Null
}
$envParent = Split-Path -Parent $script:StagingEnvFile
if (-not (Test-Path -LiteralPath $envParent)) {
    New-Item -ItemType Directory -Path $envParent -Force | Out-Null
}
if (-not (Test-Path -LiteralPath $importPath)) {
    New-Item -ItemType Directory -Path $importPath -Force | Out-Null
}

[System.IO.File]::WriteAllText($keyPath, $hmacKey, [System.Text.UTF8Encoding]::new($false))

$composeKeyPath = $keyPath.Replace('\', '/')
$composeImportPath = $importPath.Replace('\', '/')
if ($composeKeyPath -match '[=\r\n]' -or $composeImportPath -match '[=\r\n]') {
    throw 'Las rutas del .env.staging contienen caracteres invalidos'
}
$content = @"
# Generado por scripts/migration/New-StagingSecrets.ps1. No versionar.
STAGING_APP_KEY=$appKey
STAGING_DB_ROOT_PASSWORD=$rootPassword
STAGING_DB_ETL_PASSWORD=$etlPassword
STAGING_DB_RESTORE_PASSWORD=$restorePassword
STAGING_DB_REF_RO_PASSWORD=$refRoPassword
STAGING_DB_MASK_PASSWORD=$maskPassword
STAGING_DB_APP_PASSWORD=$appPassword
STAGING_DB_RO_PASSWORD=$roPassword
STAGING_TEST_PASSWORD=$testPassword
STAGING_MASK_KEY_FILE=$composeKeyPath
STAGING_IMPORT_DIR=$composeImportPath
"@

[System.IO.File]::WriteAllText($script:StagingEnvFile, $content, [System.Text.UTF8Encoding]::new($false))

if ($env:OS -eq 'Windows_NT') {
    $user = "$env:USERDOMAIN\$env:USERNAME"
    $stagingRoot = Split-Path -Parent $script:StagingEnvFile
    & icacls $stagingRoot /inheritance:r `
        /grant:r "${user}:(OI)(CI)F" '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' `
        /T /C | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron restringir los permisos del directorio de staging.' }
    & icacls $keyPath /inheritance:r /grant:r "${user}:(R,W)" /C | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron restringir los permisos de la clave HMAC.' }
    & icacls $script:StagingEnvFile /inheritance:r /grant:r "${user}:(R,W)" /C | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron restringir los permisos de .env.staging.' }
}

Write-Host "Creado $script:StagingEnvFile"
Write-Host "Clave HMAC fuera del repositorio: $keyPath"
Write-Host "Directorio de importacion: $importPath"
Write-Host 'No se imprimen secretos. Restrinja los permisos de estos archivos al usuario local.'
