# Verificacion de solo lectura sobre la copia enmascarada.
# No requiere -Execute porque no modifica datos.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

Invoke-StagingTools -Role 'ro' -Arguments @('php', 'tools/migration/cli.php', 'verify')
Write-Host 'Verificacion completada. Reporte: storage/staging/reports/masking_validation.json'
