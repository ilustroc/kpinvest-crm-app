# Ejecuta el enmascaramiento completo. Requiere -Execute y aprobacion explicita.
# No toca las bases originales.
[CmdletBinding()]
param([switch]$Execute)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation
Assert-Execute -Execute $Execute.IsPresent -Action 'preflight + enmascarar kp_crm_v1_reference -> kp_crm_staging_masked'

Invoke-StagingTools -Role 'ro' -Arguments @('php', 'tools/migration/cli.php', 'preflight')
Invoke-StagingTools -Role 'mask' -Arguments @('php', 'tools/migration/cli.php', 'mask', '--execute', '--confirm=staging-mask')
Write-Host 'Enmascaramiento y verificacion automatica completados. Revise storage/staging/reports.'
