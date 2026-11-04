# Restringe los permisos del directorio externo de staging y comprueba que no
# queden artefactos con secretos en el nombre. No imprime valores sensibles.
[CmdletBinding()]
param([switch]$Execute)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$action = 'restringir ACL de C:\KP-CRM-Staging y verificar rutas/artefactos'
Assert-Execute -Execute $Execute.IsPresent -Action $action

$stagingRoot = Split-Path -Parent $script:StagingEnvFile
$envFile = $script:StagingEnvFile
$envMap = Get-StagingEnv
Assert-StagingEnvSafe -EnvMap $envMap

$keyPath = [System.IO.Path]::GetFullPath($envMap['STAGING_MASK_KEY_FILE'])
$importPath = [System.IO.Path]::GetFullPath($envMap['STAGING_IMPORT_DIR'])

if (-not (Test-Path -LiteralPath $stagingRoot -PathType Container)) {
    throw "No existe el directorio externo de staging: $stagingRoot"
}
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) {
    throw "No existe el archivo de entorno externo: $envFile"
}
if (-not (Test-Path -LiteralPath $keyPath -PathType Leaf)) {
    throw "No existe la clave HMAC: $keyPath"
}
if (-not (Test-Path -LiteralPath $importPath -PathType Container)) {
    throw "No existe el directorio de importacion: $importPath"
}

# La carpeta contenedora debe ser privada. Los archivos ya tenian ACL explicita,
# pero el directorio permitia a Usuarios/Authenticated Users crear o reemplazar
# archivos (aunque no leerlos). Se elimina la herencia y se concede solo al
# usuario actual, SYSTEM y Administradores.
#
# No se usa '/T /grant:r ...:(OI)(CI)F': al aplicar banderas de herencia a un
# archivo, icacls puede dejarlo con una DACL vacia (nadie puede leerlo). Por eso
# se recorren directorios y archivos por separado y cada archivo recibe 'F'.
$user = "$env:USERDOMAIN\$env:USERNAME"
$directories = @(Get-Item -LiteralPath $stagingRoot -Force) +
    @(Get-ChildItem -LiteralPath $stagingRoot -Recurse -Force -Directory)
$files = @(Get-ChildItem -LiteralPath $stagingRoot -Recurse -Force -File)

foreach ($directory in $directories) {
    & icacls $directory.FullName /inheritance:r `
        /grant:r "${user}:(OI)(CI)F" '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' `
        /C | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudieron restringir los permisos de $($directory.FullName)"
    }
}
foreach ($file in $files) {
    & icacls $file.FullName /inheritance:r `
        /grant:r "${user}:F" '*S-1-5-18:F' '*S-1-5-32-544:F' `
        /C | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudieron restringir los permisos de $($file.FullName)"
    }
}

# Una DACL vacia es una negacion implicita de todo, incluido el propietario.
# Se detecta para no volver a dejar archivos ilegibles por accidente.
$unreadable = @()
foreach ($item in @($directories) + @($files)) {
    $itemAcl = Get-Acl -LiteralPath $item.FullName
    if ($itemAcl.Access.Count -eq 0) {
        $unreadable += $item.FullName
    }
}
if ($unreadable.Count -ne 0) {
    throw "Hay $($unreadable.Count) rutas con DACL vacia bajo $stagingRoot. " +
        'Ejecute icacls para restaurar el acceso antes de continuar.'
}

$acl = (& icacls $stagingRoot) -join "`n"
$forbiddenTrustees = @('BUILTIN\Usuarios', 'NT AUTHORITY\Authenticated Users', 'Everyone', 'Todos')
$aclLeaks = @()
foreach ($trustee in $forbiddenTrustees) {
    if ($acl -match [regex]::Escape($trustee)) { $aclLeaks += $trustee }
}

# Un nombre de archivo o directorio nunca debe contener una asignacion de
# secreto. Esto detecta el artefacto que creo Compose cuando STAGING_IMPORT_DIR
# tenia un salto de linea y absorbio la linea siguiente del .env.
$allowedSecretPaths = @(
    [System.IO.Path]::GetFullPath((Join-Path $stagingRoot 'secrets')),
    [System.IO.Path]::GetFullPath($keyPath)
)
$suspicious = @()
Get-ChildItem -LiteralPath $stagingRoot -Recurse -Force -ErrorAction Stop |
    Where-Object {
        $full = [System.IO.Path]::GetFullPath($_.FullName)
        $isExpected = $allowedSecretPaths -contains $full
        (-not $isExpected) -and (
            $_.Name -match '=' -or $_.Name -match '(?i)(password|secret|token|hmac)'
        )
    } |
    ForEach-Object { $suspicious += $_.FullName }

if ($suspicious.Count -ne 0) {
    throw "Hay $($suspicious.Count) rutas con nombres sospechosos bajo $stagingRoot. " +
        'Retire los artefactos y rote los secretos afectados antes de continuar.'
}

$report = [ordered]@{
    ok = ($aclLeaks.Count -eq 0)
    staging_root = $stagingRoot
    env_file_outside_repo = -not $envFile.StartsWith($script:RepoPrefix, [System.StringComparison]::OrdinalIgnoreCase)
    key_outside_repo = -not $keyPath.StartsWith($script:RepoPrefix, [System.StringComparison]::OrdinalIgnoreCase)
    import_outside_repo = -not $importPath.StartsWith($script:RepoPrefix, [System.StringComparison]::OrdinalIgnoreCase)
    acl_forbidden_trustees = $aclLeaks
    acl_empty_paths = $unreadable.Count
    suspicious_paths = $suspicious.Count
    acl_applied = $true
}

$path = Write-JsonReport -Name 'g15_secrets_acl.json' -Data $report
Write-Host 'ACL del directorio externo restringida y rutas verificadas.'
Write-Host "Reporte: $path"

if ($aclLeaks.Count -ne 0) {
    throw "La ACL todavia incluye: $($aclLeaks -join ', ')"
}
