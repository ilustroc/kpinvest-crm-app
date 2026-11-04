# Aplica la topologia G1.5 sin recrear contenedores ni volumenes.
# Solo crea/conecta/desconecta endpoints de red.
#
#   staging_ref (internal):       ref-db + tools
#   staging_tools_db (internal):  db de proceso + tools
#   staging_private (internal):   db de proceso + app
#   staging_app_net (internal):   app + proxy web
#   staging_web (bridge):         solo proxy web, publica 127.0.0.1:8090
[CmdletBinding()]
param([switch]$Execute)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation
Assert-Execute -Execute $Execute.IsPresent -Action 'crear/conectar redes G1.5 y verificar aislamiento'
Assert-Docker

$private = 'kpcrm_staging_private'
$appNet = 'kpcrm_staging_app_net'
$webNet = 'kpcrm_staging_web'
$toolsDb = 'kpcrm_staging_tools_db'
$refNet = 'kpcrm_staging_ref'
$legacy = 'kpcrm_staging_net'

function Ensure-Network {
    param([string]$Name, [bool]$Internal)
    $found = & docker network ls --filter "name=^$Name$" --format '{{.Name}}'
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron listar las redes Docker' }
    if ($found -notcontains $Name) {
        $cmdArgs = @('network', 'create')
        if ($Internal) { $cmdArgs += '--internal' }
        $cmdArgs += $Name
        & docker @cmdArgs | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "No se pudo crear la red $Name" }
    }

    $actual = & docker network inspect $Name --format '{{.Internal}}'
    if ($LASTEXITCODE -ne 0) { throw "No se pudo inspeccionar la red $Name" }
    if ([bool]::Parse($actual) -ne $Internal) {
        throw "La red $Name existe con internal=$actual; se esperaba internal=$Internal"
    }
}

function Get-NetworkNames {
    param([string]$Container)
    $json = & docker inspect $Container --format '{{json .NetworkSettings.Networks}}' 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($json)) { return @() }
    return @((ConvertFrom-Json $json).PSObject.Properties.Name)
}

function Connect-IfNeeded {
    param([string]$Container, [string]$Network, [string]$Alias = '')
    if ((Get-NetworkNames -Container $Container) -contains $Network) { return }
    $cmdArgs = @('network', 'connect')
    if ($Alias -ne '') { $cmdArgs += @('--alias', $Alias) }
    $cmdArgs += @($Network, $Container)
    & docker @cmdArgs | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "No se pudo conectar $Container a $Network" }
}

function Disconnect-IfNeeded {
    param([string]$Container, [string]$Network)
    if ((Get-NetworkNames -Container $Container) -notcontains $Network) { return }
    & docker network disconnect $Network $Container | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "No se pudo desconectar $Container de $Network" }
}

function Assert-ExactNetworks {
    param([string]$Container, [string[]]$Expected)
    $actual = @(Get-NetworkNames -Container $Container)
    foreach ($net in $Expected) {
        if ($actual -notcontains $net) { throw "$Container no esta conectado a $net" }
    }
    foreach ($net in $actual) {
        if ($Expected -notcontains $net) { throw "$Container tiene una red no autorizada: $net" }
    }
}

Ensure-Network -Name $private -Internal $true
Ensure-Network -Name $appNet -Internal $true
Ensure-Network -Name $webNet -Internal $false
Ensure-Network -Name $toolsDb -Internal $true
Ensure-Network -Name $refNet -Internal $true

$existing = @(& docker ps -a --format '{{.Names}}')

# El servidor de proceso necesita una red exclusiva para tools. La app no se
# conecta a ella, de modo que tools y app no comparten L2.
if ($existing -contains $script:StagingDbContainer) {
    Connect-IfNeeded -Container $script:StagingDbContainer -Network $toolsDb -Alias 'staging-db'
    Connect-IfNeeded -Container $script:StagingDbContainer -Network $private -Alias 'staging-db'
}

# El servidor de referencia solo comparte red con tools.
if ($existing -contains $script:StagingRefContainer) {
    Connect-IfNeeded -Container $script:StagingRefContainer -Network $refNet -Alias 'staging-ref-db'
}

if ($existing -contains 'kpcrm-staging-app') {
    Connect-IfNeeded -Container 'kpcrm-staging-app' -Network $private -Alias 'staging-app'
    Connect-IfNeeded -Container 'kpcrm-staging-app' -Network $appNet -Alias 'staging-app'
    Disconnect-IfNeeded -Container 'kpcrm-staging-app' -Network $toolsDb
    Disconnect-IfNeeded -Container 'kpcrm-staging-app' -Network $refNet
}

if ($existing -contains 'kpcrm-staging-web') {
    Connect-IfNeeded -Container 'kpcrm-staging-web' -Network $appNet -Alias 'staging-web'
    Connect-IfNeeded -Container 'kpcrm-staging-web' -Network $webNet -Alias 'staging-web'
    Disconnect-IfNeeded -Container 'kpcrm-staging-web' -Network $private
    Disconnect-IfNeeded -Container 'kpcrm-staging-web' -Network $toolsDb
    Disconnect-IfNeeded -Container 'kpcrm-staging-web' -Network $refNet
}

# Red legacy: se retira solo si no es NetworkMode de ningun contenedor y no
# tiene endpoints. Si existe con egreso, se detiene para revision manual.
$legacyIsNetworkMode = $false
foreach ($name in @($script:StagingDbContainer, $script:StagingRefContainer, 'kpcrm-staging-app', 'kpcrm-staging-web')) {
    if ($existing -notcontains $name) { continue }
    $mode = & docker inspect $name --format '{{.HostConfig.NetworkMode}}'
    if ($LASTEXITCODE -eq 0 -and $mode -eq $legacy) { $legacyIsNetworkMode = $true }
}
$legacyExists = (& docker network ls --filter "name=^$legacy$" --format '{{.Name}}') -contains $legacy
if ($legacyExists) {
    $legacyInternal = & docker network inspect $legacy --format '{{.Internal}}'
    if ($LASTEXITCODE -eq 0 -and -not [bool]::Parse($legacyInternal)) {
        throw "La red legacy $legacy existe con egreso; detenga y revise antes de continuar"
    }
    if (-not $legacyIsNetworkMode) {
        $attached = & docker network inspect $legacy --format '{{len .Containers}}'
        if ($LASTEXITCODE -eq 0 -and [int]$attached -eq 0) {
            & docker network rm $legacy | Out-Null
            if ($LASTEXITCODE -ne 0) { throw "No se pudo retirar la red legacy $legacy" }
        }
    }
}

# Verificacion estructural: cada contenedor solo ve sus redes.
if ($existing -contains $script:StagingDbContainer) {
    Assert-ExactNetworks -Container $script:StagingDbContainer -Expected @($private, $toolsDb)
}
if ($existing -contains $script:StagingRefContainer) {
    Assert-ExactNetworks -Container $script:StagingRefContainer -Expected @($refNet)
}
if ($existing -contains 'kpcrm-staging-app') {
    Assert-ExactNetworks -Container 'kpcrm-staging-app' -Expected @($private, $appNet)
}
if ($existing -contains 'kpcrm-staging-web') {
    Assert-ExactNetworks -Container 'kpcrm-staging-web' -Expected @($appNet, $webNet)
}

$report = [ordered]@{
    ok = $true
    networks = @(
        [ordered]@{ name = $refNet; internal = $true; members = @(& docker network inspect $refNet --format '{{json .Containers}}') },
        [ordered]@{ name = $toolsDb; internal = $true; members = @(& docker network inspect $toolsDb --format '{{json .Containers}}') },
        [ordered]@{ name = $private; internal = $true; members = @(& docker network inspect $private --format '{{json .Containers}}') },
        [ordered]@{ name = $appNet; internal = $true; members = @(& docker network inspect $appNet --format '{{json .Containers}}') },
        [ordered]@{ name = $webNet; internal = $false; members = @(& docker network inspect $webNet --format '{{json .Containers}}') }
    )
    app_reference_network = ((Get-NetworkNames -Container 'kpcrm-staging-app') -contains $refNet)
    web_db_network = ((Get-NetworkNames -Container 'kpcrm-staging-web') -contains $private)
    legacy_network_removed = -not ((& docker network ls --filter "name=^$legacy$" --format '{{.Name}}') -contains $legacy)
    container_recreated = $false
    volume_modified = $false
}
$path = Write-JsonReport -Name 'g15_network_apply.json' -Data $report
Write-Host 'Redes G1.5 aplicadas sin recrear contenedores ni volumenes.'
Write-Host "Reporte: $path"
