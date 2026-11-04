# Inicia staging. Por defecto solo las dos bases; la app requiere -IncludeApp y
# debe arrancar unicamente despues de enmascarar y verificar. No copia datos.
#
# Las redes son externas y las administra Set-StagingNetwork.ps1. Si faltan,
# ejecute primero:
#   scripts/migration/Set-StagingNetwork.ps1 -Execute
[CmdletBinding()]
param(
    [switch]$Execute,
    [switch]$IncludeApp
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$services = @('staging-ref-db', 'staging-db')
if ($IncludeApp) { $services += @('staging-app', 'staging-web') }
Assert-Execute -Execute $Execute.IsPresent -Action ('docker compose up -d --no-recreate ' + ($services -join ' '))

# --no-recreate evita que un cambio de configuracion recree las bases y sus
# volumenes. Si hace falta recrear por un cambio autorizado, use el
# procedimiento documentado y solicite aprobacion.
Invoke-Compose -Arguments (@('up', '-d', '--no-recreate') + $services)
if ($IncludeApp) {
    Write-Host 'Staging iniciado. Las bases no publican puerto; el proxy escucha solo en 127.0.0.1:8090.'
    Write-Host 'Verifique el aislamiento antes de cargar cualquier dato.'
}
else {
    Write-Host 'Solo las bases de staging fueron iniciadas.'
    Write-Host 'Use -IncludeApp solo después de enmascarar y verificar.'
}
