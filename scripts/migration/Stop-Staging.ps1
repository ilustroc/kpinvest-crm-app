# Detiene los contenedores de staging sin borrar el volumen de MariaDB.
[CmdletBinding()]
param([switch]$Execute)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation
Assert-Execute -Execute $Execute.IsPresent -Action 'docker compose down (sin -v)'

Invoke-Compose -Arguments @('down', '--remove-orphans')
Write-Host 'Contenedores detenidos. Los volumenes kpcrm_staging_mariadb_data y kpcrm_staging_ref_data se conservan.'
