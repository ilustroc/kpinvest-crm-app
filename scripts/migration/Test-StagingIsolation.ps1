# Verifica el aislamiento G1.5 de staging sin modificar datos persistentes.
# Solo crea archivos de cliente temporales dentro de los contenedores y, en la
# prueba positiva de la app, una tabla TEMPORARY que desaparece al cerrar la
# conexion.
#
# Comprueba:
#   - pertenencia real a redes y ausencia de rutas entre app/web/db/ref/tools
#   - egreso a Internet desde app, db, ref y tools
#   - montajes y ausencia de .env*, HMAC, /import, reportes y dumps en la app
#   - matriz efectiva de privilegios con conexiones reales (no solo GRANT)
#   - salud de /healthz y /login a traves de 127.0.0.1:8090
#
# No imprime contrasenas ni valores de datos.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
Set-RepoLocation

$envMap = Get-StagingEnv
Assert-StagingEnvSafe -EnvMap $envMap
Assert-Docker

$appContainer = 'kpcrm-staging-app'
$webContainer = 'kpcrm-staging-web'
$dbContainer = $script:StagingDbContainer
$refContainer = $script:StagingRefContainer
foreach ($container in @($appContainer, $webContainer, $dbContainer, $refContainer)) {
    Assert-ContainerRunning -Name $container
}

$script:Results = New-Object System.Collections.Generic.List[object]

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

function Get-ContainerIp {
    param([Parameter(Mandatory = $true)][string]$Container)

    $result = Invoke-DockerCapture -Arguments @(
        'inspect', $Container, '--format',
        '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}'
    )
    if ($result.code -ne 0) { return '' }
    return (($result.text -split '\s+') | Where-Object { $_ -ne '' } | Select-Object -First 1)
}

function Invoke-AppPhp {
    param(
        [Parameter(Mandatory = $true)][string]$Code,
        [string[]]$ExtraArguments = @()
    )

    return Invoke-DockerCapture -Arguments (@(
        'exec'
    ) + $ExtraArguments + @(
        $appContainer, 'php', '-r', $Code
    ))
}

function Invoke-SqlProbe {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$User,
        [Parameter(Mandatory = $true)][string]$PasswordKey,
        [Parameter(Mandatory = $true)][string]$Sql
    )

    $configPath = '/tmp/.g15probe-' + [guid]::NewGuid().ToString('N') + '.cnf'
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

function Add-SqlResult {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][object]$Probe,
        [Parameter(Mandatory = $true)][bool]$ExpectSuccess
    )

    $ok = if ($ExpectSuccess) { $Probe.code -eq 0 } else { $Probe.code -ne 0 }
    $state = if ($Probe.code -eq 0) { 'allowed' } else { 'denied' }
    Add-Result -Name $Name -Ok $ok -Detail $state
}

# --- Redes reales -----------------------------------------------------------
$expectedNetworks = @{
    $appContainer = @('kpcrm_staging_private', 'kpcrm_staging_app_net')
    $webContainer = @('kpcrm_staging_app_net', 'kpcrm_staging_web')
    $dbContainer  = @('kpcrm_staging_private', 'kpcrm_staging_tools_db')
    $refContainer = @('kpcrm_staging_ref')
}
$networkMembership = @{}
foreach ($container in @($appContainer, $webContainer, $dbContainer, $refContainer)) {
    $result = Invoke-DockerCapture -Arguments @(
        'inspect', $container, '--format',
        '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}} {{end}}'
    )
    $actual = @(($result.text -split '\s+') | Where-Object { $_ -ne '' } | Sort-Object)
    $networkMembership[$container] = $actual
    $expected = @($expectedNetworks[$container] | Sort-Object)
    $same = ($actual.Count -eq $expected.Count)
    foreach ($name in $expected) {
        if ($actual -notcontains $name) { $same = $false }
    }
    Add-Result -Name ("networks:" + $container) -Ok $same -Detail ($actual -join ',')
}

$refIp = Get-ContainerIp -Container $refContainer
$dbIp = Get-ContainerIp -Container $dbContainer
Add-Result -Name 'network:ref-ip-known' -Ok ($refIp -ne '') -Detail 'resolved-by-docker-inspect'
Add-Result -Name 'network:db-ip-known' -Ok ($dbIp -ne '') -Detail 'resolved-by-docker-inspect'

# --- App: DNS, rutas permitidas y rutas prohibidas --------------------------
$dnsDb = Invoke-DockerCapture -Arguments @('exec', $appContainer, 'getent', 'hosts', 'staging-db')
Add-Result -Name 'app:dns-staging-db' -Ok ($dnsDb.code -eq 0) -Detail 'expected-resolve'

$dnsRef = Invoke-DockerCapture -Arguments @('exec', $appContainer, 'getent', 'hosts', 'staging-ref-db')
Add-Result -Name 'app:dns-staging-ref-db' -Ok ($dnsRef.code -ne 0) -Detail 'expected-no-resolve'

$phpDb = '$s=@fsockopen(''staging-db'',3306,$e,$m,3); echo $s?''OPEN'':''CLOSED'';'
$probe = Invoke-AppPhp -Code $phpDb
Add-Result -Name 'app:tcp-staging-db-3306' -Ok (($probe.code -eq 0) -and ($probe.text -match 'OPEN')) -Detail 'expected-open'

$phpRef = '$s=@fsockopen(''staging-ref-db'',3306,$e,$m,3); echo $s?''OPEN'':''CLOSED'';'
$probe = Invoke-AppPhp -Code $phpRef
Add-Result -Name 'app:tcp-staging-ref-db-3306' -Ok (($probe.code -eq 0) -and ($probe.text -match 'CLOSED')) -Detail 'expected-closed'

$phpRefIp = '$s=@fsockopen(getenv(''TARGET_IP''),3306,$e,$m,3); echo $s?''OPEN'':''CLOSED'';'
$probe = Invoke-AppPhp -Code $phpRefIp -ExtraArguments @('-e', "TARGET_IP=$refIp")
Add-Result -Name 'app:tcp-ref-ip-3306' -Ok (($probe.code -eq 0) -and ($probe.text -match 'CLOSED')) -Detail 'expected-no-route'

$phpEgress = '$s=@fsockopen(''1.1.1.1'',443,$e,$m,3); echo $s?''OPEN'':''CLOSED'';'
$probe = Invoke-AppPhp -Code $phpEgress
Add-Result -Name 'app:egress-internet' -Ok (($probe.code -eq 0) -and ($probe.text -match 'CLOSED')) -Detail 'expected-blocked'

# --- App: credenciales reales ------------------------------------------------
$phpValidation = '$p=new PDO(''mysql:host=staging-db;port=3306;dbname=kp_crm_v3_validation;charset=utf8mb4'',getenv(''DB_USERNAME''),getenv(''DB_PASSWORD''));$p->exec(''CREATE TEMPORARY TABLE __g15_probe (id INT)'');$p->exec(''INSERT INTO __g15_probe VALUES (1)'');$n=(int)$p->query(''SELECT COUNT(*) FROM __g15_probe'')->fetchColumn();echo $n===1?''OK'':''BAD'';'
$probe = Invoke-AppPhp -Code $phpValidation
Add-Result -Name 'app:db-validation-dml' -Ok (($probe.code -eq 0) -and ($probe.text -match 'OK')) -Detail 'expected-allowed'

$phpMasked = 'try{$p=new PDO(''mysql:host=staging-db;port=3306;dbname=kp_crm_staging_masked;charset=utf8mb4'',getenv(''DB_USERNAME''),getenv(''DB_PASSWORD''));$p->query(''SELECT COUNT(*) FROM users'');echo ''ALLOWED'';}catch(PDOException $e){echo ''DENIED'';}'
$probe = Invoke-AppPhp -Code $phpMasked
Add-Result -Name 'app:db-masked-read' -Ok (($probe.code -eq 0) -and ($probe.text -match 'DENIED')) -Detail 'expected-denied'

# --- Web: sin ruta a las bases ni a la referencia ---------------------------
foreach ($target in @('staging-db', 'staging-ref-db')) {
    $dns = Invoke-DockerCapture -Arguments @('exec', $webContainer, 'getent', 'hosts', $target)
    Add-Result -Name ("web:dns-" + $target) -Ok ($dns.code -ne 0) -Detail 'expected-no-resolve'
}

$webToDb = Invoke-DockerCapture -Arguments @('exec', $webContainer, 'nc', '-z', '-w', '3', $dbIp, '3306')
Add-Result -Name 'web:tcp-db-ip-3306' -Ok ($webToDb.code -ne 0) -Detail 'expected-no-route'

$webToRef = Invoke-DockerCapture -Arguments @('exec', $webContainer, 'nc', '-z', '-w', '3', $refIp, '3306')
Add-Result -Name 'web:tcp-ref-ip-3306' -Ok ($webToRef.code -ne 0) -Detail 'expected-no-route'

# /healthz del proxy es local a nginx; la app se prueba con /login, que pasa
# por Apache/Laravel y responde 200.
$webToApp = $null
$webAttempts = @()
for ($attempt = 1; $attempt -le 3; $attempt++) {
    $probe = Invoke-DockerCapture -Arguments @(
        'exec', $webContainer, 'curl', '-sS', '-o', '/dev/null', '-w', '%{http_code}',
        '--max-time', '30', 'http://staging-app/login'
    )
    $webAttempts += ("try{0}=code:{1},http:{2}" -f $attempt, $probe.code, $probe.text.Trim())
    if (($probe.code -eq 0) -and ($probe.text.Trim() -eq '200')) {
        $webToApp = $probe
        break
    }
    Start-Sleep -Milliseconds 750
}
Add-Result -Name 'web:http-staging-app-login' -Ok ($null -ne $webToApp) -Detail ($webAttempts -join '; ')

# El proxy esta en una red bridge para publicar 127.0.0.1:8090. Se registra
# su egreso como riesgo residual, no como fallo de la app ni de las bases.
$webEgress = Invoke-DockerCapture -Arguments @(
    'exec', $webContainer, 'sh', '-c',
    'curl -sS --max-time 5 -o /dev/null https://example.com >/dev/null 2>&1; echo exit=$?'
)
Add-Result -Name 'web:egress-internet' -Ok $true -Detail (($webEgress.text -replace '\s+', ' ').Trim())

# --- DB de proceso y referencia: sin egreso ni ruta cruzada -----------------
$bashEgress = 'timeout 5 bash -c ''exec 3<>/dev/tcp/1.1.1.1/443'' >/dev/null 2>&1; echo $?'
foreach ($container in @($dbContainer, $refContainer)) {
    $probe = Invoke-DockerCapture -Arguments @('exec', $container, 'bash', '-c', $bashEgress)
    Add-Result -Name ("egress:" + $container) -Ok (($probe.code -eq 0) -and ($probe.text.Trim() -ne '0')) -Detail 'expected-blocked'
}

$bashRefToDb = 'timeout 5 bash -c ''exec 3<>/dev/tcp/{0}/3306'' >/dev/null 2>&1; echo $?' -f $dbIp
$probe = Invoke-DockerCapture -Arguments @('exec', $refContainer, 'bash', '-c', $bashRefToDb)
Add-Result -Name 'ref:tcp-db-ip-3306' -Ok (($probe.code -eq 0) -and ($probe.text.Trim() -ne '0')) -Detail 'expected-no-route'

$bashDbToRef = 'timeout 5 bash -c ''exec 3<>/dev/tcp/{0}/3306'' >/dev/null 2>&1; echo $?' -f $refIp
$probe = Invoke-DockerCapture -Arguments @('exec', $dbContainer, 'bash', '-c', $bashDbToRef)
Add-Result -Name 'db:tcp-ref-ip-3306' -Ok (($probe.code -eq 0) -and ($probe.text.Trim() -ne '0')) -Detail 'expected-no-route'

# --- App: montajes y secretos ------------------------------------------------
$mountResult = Invoke-DockerCapture -Arguments @('inspect', $appContainer, '--format', '{{json .Mounts}}')
$appMounts = New-Object System.Collections.Generic.List[object]
if ($mountResult.code -eq 0) {
    $parsedMounts = $mountResult.text | ConvertFrom-Json
    foreach ($mount in $parsedMounts) {
        $appMounts.Add($mount)
    }
}
else {
    Add-Result -Name 'app:mounts-readable' -Ok $false -Detail 'inspect-failed'
}

$appSources = @($appMounts | ForEach-Object { [string]$_.Source })
$appDestinations = @($appMounts | ForEach-Object { [string]$_.Destination })
$repoPrefix = $script:RepoRoot.TrimEnd('\') + '\'
$allowedAppMounts = @(
    (Join-Path $script:RepoRoot 'app'),
    (Join-Path $script:RepoRoot 'bootstrap'),
    (Join-Path $script:RepoRoot 'config'),
    (Join-Path $script:RepoRoot 'database'),
    (Join-Path $script:RepoRoot 'public'),
    (Join-Path $script:RepoRoot 'resources'),
    (Join-Path $script:RepoRoot 'routes'),
    (Join-Path $script:RepoRoot 'vendor'),
    (Join-Path $script:RepoRoot 'composer.json'),
    (Join-Path $script:RepoRoot 'artisan'),
    (Join-Path $script:RepoRoot 'storage\staging\app'),
    (Join-Path $script:RepoRoot 'storage\staging\framework'),
    (Join-Path $script:RepoRoot 'storage\staging\logs'),
    (Join-Path $script:RepoRoot 'storage\staging\bootstrap-cache')
)
$unexpectedMounts = @()
foreach ($source in $appSources) {
    try {
        $full = [System.IO.Path]::GetFullPath($source)
    }
    catch {
        $unexpectedMounts += ("invalid-source:" + $source)
        continue
    }
    $allowed = $false
    foreach ($candidate in $allowedAppMounts) {
        if ($full.Equals([System.IO.Path]::GetFullPath($candidate), [System.StringComparison]::OrdinalIgnoreCase)) {
            $allowed = $true
            break
        }
    }
    if (-not $allowed) { $unexpectedMounts += $full }
}
Add-Result -Name 'app:mounts-allowlist' -Ok ($unexpectedMounts.Count -eq 0) -Detail ("count=" + $appMounts.Count)

foreach ($expected in @(
    '/var/www/html/app',
    '/var/www/html/config',
    '/var/www/html/storage/app',
    '/var/www/html/storage/framework',
    '/var/www/html/storage/logs'
)) {
    Add-Result -Name ("app:mount-present:" + $expected) -Ok ($appDestinations -contains $expected) -Detail 'expected'
}

$forbiddenMounts = @(
    '/var/www/html',
    '/var/www/html/.env',
    '/var/www/html/.env.staging',
    '/var/www/html/tests',
    '/var/www/html/storage/staging/reports',
    '/import',
    '/reports',
    '/run/secrets'
)
foreach ($forbidden in $forbiddenMounts) {
    Add-Result -Name ("app:mount-absent:" + $forbidden) -Ok ($appDestinations -notcontains $forbidden) -Detail 'expected-absent'
}

$secretChecks = @(
    @{ Name = 'app:no-env-root'; Command = 'test ! -e /var/www/html/.env' },
    @{ Name = 'app:no-env-staging-root'; Command = 'test ! -e /var/www/html/.env.staging' },
    @{ Name = 'app:no-hmac-secret'; Command = 'test ! -e /run/secrets/mask_hmac_key' },
    @{ Name = 'app:no-import-dir'; Command = 'test ! -e /import' },
    @{ Name = 'app:no-reports-dir'; Command = 'test ! -e /reports' },
    @{ Name = 'app:no-tests-dir'; Command = 'test ! -e /var/www/html/tests' },
    @{ Name = 'app:no-staging-reports'; Command = 'test ! -e /var/www/html/storage/staging/reports' }
)
foreach ($check in $secretChecks) {
    $probe = Invoke-DockerCapture -Arguments @('exec', $appContainer, 'sh', '-c', [string]$check.Command)
    Add-Result -Name ([string]$check.Name) -Ok ($probe.code -eq 0) -Detail 'expected-absent'
}

# Busquedas sin comillas dobles: PowerShell 5.1 las elimina al pasar a nativo.
foreach ($search in @(
    @{ Name = 'app:no-env-files-anywhere'; Pattern = '.env*'; Depth = '3' },
    @{ Name = 'app:no-sql-files-anywhere'; Pattern = '*.sql'; Depth = '5' },
    @{ Name = 'app:no-original-sql-backup'; Pattern = 'u480021566*'; Depth = '6' }
)) {
    $findArgs = 'find /var/www/html -maxdepth ' + [string]$search.Depth +
        ' -name ''' + [string]$search.Pattern + ''' -print -quit'
    $probe = Invoke-DockerCapture -Arguments @('exec', $appContainer, 'sh', '-c', $findArgs)
    Add-Result -Name ([string]$search.Name) -Ok (($probe.code -eq 0) -and ($probe.text.Trim() -eq '')) -Detail 'expected-none'
}

# --- Tools: solo ve tools, import y el secreto que necesita -----------------
$toolsScript = @'
echo "root="$(ls -A /var/www/html | tr '\n' ',')
echo "env="$(find /var/www/html -maxdepth 2 -name '.env*' | wc -l)
echo "sql="$(find /var/www/html -name '*.sql' | wc -l)
echo "secret="$(test -r /run/secrets/mask_hmac_key && echo yes || echo no)
echo "import="$(test -r /import/synthetic_v1.sql && echo yes || echo no)
echo "reports="$(test -d /reports && echo yes || echo no)
echo "appdir="$(test -d /var/www/html/app && echo yes || echo no)
'@
$toolsResult = Invoke-DockerCapture -Arguments @(
    'compose', '--project-name', 'kpcrm-staging', '-f', $script:StagingCompose,
    '--env-file', $script:StagingEnvFile,
    'run', '--rm', '--no-deps', '--entrypoint', 'sh', 'staging-tools', '-c', $toolsScript
)
$toolsText = $toolsResult.text
Add-Result -Name 'tools:run-probe' -Ok ($toolsResult.code -eq 0) -Detail 'one-off'
Add-Result -Name 'tools:root-only-tools' -Ok ($toolsText -match '(?m)^root=tools,?$') -Detail 'expected-tools-only'
Add-Result -Name 'tools:no-env-files' -Ok ($toolsText -match '(?m)^env=0$') -Detail 'expected-absent'
Add-Result -Name 'tools:no-sql-under-html' -Ok ($toolsText -match '(?m)^sql=0$') -Detail 'expected-absent'
Add-Result -Name 'tools:hmac-readable' -Ok ($toolsText -match '(?m)^secret=yes$') -Detail 'required-for-mask'
Add-Result -Name 'tools:import-readable' -Ok ($toolsText -match '(?m)^import=yes$') -Detail 'required-for-restore'
Add-Result -Name 'tools:reports-mounted' -Ok ($toolsText -match '(?m)^reports=yes$') -Detail 'required-for-evidence'
Add-Result -Name 'tools:no-app-code' -Ok ($toolsText -match '(?m)^appdir=no$') -Detail 'expected-absent'

# --- Matriz efectiva de privilegios -----------------------------------------
$appPositive = Invoke-SqlProbe -Container $dbContainer -User 'kp_stage_app' `
    -PasswordKey 'STAGING_DB_APP_PASSWORD' `
    -Sql 'CREATE TEMPORARY TABLE kp_crm_v3_validation.__g15_probe (id INT); INSERT INTO kp_crm_v3_validation.__g15_probe VALUES (1); SELECT COUNT(*) FROM kp_crm_v3_validation.__g15_probe; DROP TEMPORARY TABLE kp_crm_v3_validation.__g15_probe;'
Add-SqlResult -Name 'grants:app-validation-dml' -Probe $appPositive -ExpectSuccess $true

Add-SqlResult -Name 'grants:app-masked-read' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_app' -PasswordKey 'STAGING_DB_APP_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_staging_masked.users;') -ExpectSuccess $false

Add-SqlResult -Name 'grants:app-masked-write' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_app' -PasswordKey 'STAGING_DB_APP_PASSWORD' `
    -Sql 'CREATE TEMPORARY TABLE kp_crm_staging_masked.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:app-absent-on-ref' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_app' -PasswordKey 'STAGING_DB_APP_PASSWORD' -Sql 'SELECT 1;') -ExpectSuccess $false

Add-SqlResult -Name 'grants:ro-masked-read' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_ro' -PasswordKey 'STAGING_DB_RO_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_staging_masked.users;') -ExpectSuccess $true

Add-SqlResult -Name 'grants:ro-ref-read' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_ro' -PasswordKey 'STAGING_DB_RO_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_v1_reference.users;') -ExpectSuccess $true

Add-SqlResult -Name 'grants:ro-masked-write' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_ro' -PasswordKey 'STAGING_DB_RO_PASSWORD' `
    -Sql 'CREATE TEMPORARY TABLE kp_crm_staging_masked.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:ro-ref-write' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_ro' -PasswordKey 'STAGING_DB_RO_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_v1_reference.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:mask-ref-read' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_mask' -PasswordKey 'STAGING_DB_MASK_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_v1_reference.users;') -ExpectSuccess $true

Add-SqlResult -Name 'grants:mask-masked-write' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_mask' -PasswordKey 'STAGING_DB_MASK_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_staging_masked.__g15_probe (id INT); DROP TABLE kp_crm_staging_masked.__g15_probe;') -ExpectSuccess $true

Add-SqlResult -Name 'grants:mask-ref-write' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_mask' -PasswordKey 'STAGING_DB_MASK_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_v1_reference.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:mask-validation-write' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_mask' -PasswordKey 'STAGING_DB_MASK_PASSWORD' `
    -Sql 'CREATE TEMPORARY TABLE kp_crm_v3_validation.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:restore-ref-read' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_v1_reference.users;') -ExpectSuccess $false

Add-SqlResult -Name 'grants:restore-ref-write' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_v1_reference.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:restore-masked-read' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_restore' -PasswordKey 'STAGING_DB_RESTORE_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_staging_masked.users;') -ExpectSuccess $false

Add-SqlResult -Name 'grants:refro-ref-read' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_ref_ro' -PasswordKey 'STAGING_DB_REF_RO_PASSWORD' `
    -Sql 'SELECT COUNT(*) FROM kp_crm_v1_reference.users;') -ExpectSuccess $true

Add-SqlResult -Name 'grants:refro-ref-write' -Probe (Invoke-SqlProbe -Container $refContainer `
    -User 'kp_stage_ref_ro' -PasswordKey 'STAGING_DB_REF_RO_PASSWORD' `
    -Sql 'CREATE TABLE kp_crm_v1_reference.__g15_probe (id INT);') -ExpectSuccess $false

Add-SqlResult -Name 'grants:etl-locked' -Probe (Invoke-SqlProbe -Container $dbContainer `
    -User 'kp_stage_etl' -PasswordKey 'STAGING_DB_ETL_PASSWORD' -Sql 'SELECT 1;') -ExpectSuccess $false

# Auditoria: ninguna cuenta kp_stage_* debe tener privilegios globales y no
# debe haber grants de la app en ref-db ni de la referencia legacy en db.
$rootChecks = @(
    @{ Container = $dbContainer; Sql = "SELECT COUNT(*) FROM mysql.db WHERE Db='kp_crm_v1_reference' AND User LIKE 'kp_stage_%';"; Name = 'audit:no-legacy-ref-grants-on-db'; Expect = '0' },
    @{ Container = $refContainer; Sql = "SELECT COUNT(*) FROM mysql.user WHERE User='kp_stage_app';"; Name = 'audit:no-app-user-on-ref'; Expect = '0' },
    # MariaDB 11.8 guarda el bloqueo de cuenta en mysql.global_priv.Priv JSON,
    # no en una columna account_locked.
    @{ Container = $dbContainer; Sql = "SELECT COUNT(*) FROM mysql.global_priv WHERE User='kp_stage_etl' AND JSON_VALUE(Priv,'`$.account_locked')='1';"; Name = 'audit:etl-account-locked'; Expect = '1' }
)
foreach ($check in $rootChecks) {
    $configPath = '/tmp/.g15root-' + [guid]::NewGuid().ToString('N') + '.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $check.Container -ContainerPath $configPath
        $probe = Invoke-DockerCapture -Arguments @(
            'exec', $check.Container, 'mariadb', "--defaults-extra-file=$configPath",
            '--batch', '--skip-column-names', '-e', $check.Sql
        )
    }
    finally {
        Remove-ContainerClientConfig -Container $check.Container -ContainerPath $configPath
    }
    Add-Result -Name $check.Name -Ok (($probe.code -eq 0) -and ($probe.text.Trim() -eq $check.Expect)) -Detail ('expected=' + $check.Expect)
}

foreach ($pair in @(
    @{ Container = $dbContainer; Label = 'db' },
    @{ Container = $refContainer; Label = 'ref' }
)) {
    $container = [string]$pair.Container
    $label = [string]$pair.Label
    $configPath = '/tmp/.g15root-' + [guid]::NewGuid().ToString('N') + '.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $container -ContainerPath $configPath
        $users = Invoke-DockerCapture -Arguments @(
            'exec', $container, 'mariadb', "--defaults-extra-file=$configPath",
            '--batch', '--skip-column-names',
            '-e', "SELECT User FROM mysql.user WHERE User LIKE 'kp_stage_%' ORDER BY User;"
        )
        $globalViolations = @()
        foreach ($user in @($users.text -split "`n" | Where-Object { $_.Trim() -ne '' })) {
            $grants = Invoke-DockerCapture -Arguments @(
                'exec', $container, 'mariadb', "--defaults-extra-file=$configPath",
                '--batch', '--skip-column-names',
                '-e', "SHOW GRANTS FOR '$($user.Trim())'@'%';"
            )
            foreach ($line in @($grants.text -split "`n")) {
                if ($line -match 'ON\s+\*\.\*' -and $line -notmatch '\bUSAGE\b') {
                    $globalViolations += $line.Trim()
                }
            }
        }
    }
    finally {
        Remove-ContainerClientConfig -Container $container -ContainerPath $configPath
    }
    Add-Result -Name ("audit:no-global-grants-" + $label) -Ok ($globalViolations.Count -eq 0) -Detail ("violations=" + $globalViolations.Count)
}

# --- Salud del punto de entrada ---------------------------------------------
$health = $null
try {
    $health = Invoke-WebRequest -Uri 'http://127.0.0.1:8090/healthz' -UseBasicParsing -TimeoutSec 20
}
catch { }
$healthOk = $false
$healthBody = ''
if ($null -ne $health) {
    $healthBody = if ($health.Content -is [byte[]]) { [System.Text.Encoding]::UTF8.GetString($health.Content) } else { [string]$health.Content }
    $healthOk = ($health.StatusCode -eq 200) -and ($healthBody.Trim() -eq 'ok')
}
Add-Result -Name 'host:healthz' -Ok $healthOk -Detail '127.0.0.1:8090'

$loginStatus = 0
try {
    $login = Invoke-WebRequest -Uri 'http://127.0.0.1:8090/login' -UseBasicParsing -TimeoutSec 30
    $loginStatus = [int]$login.StatusCode
}
catch { }
Add-Result -Name 'host:login' -Ok ($loginStatus -eq 200) -Detail ("status=" + $loginStatus)

$portResult = Invoke-DockerCapture -Arguments @('port', $webContainer)
$portText = $portResult.text.Trim()
Add-Result -Name 'host:port-bound-loopback' -Ok ($portText -match '^80/tcp -> 127\.0\.0\.1:8090$') -Detail $portText

# --- Reporte ----------------------------------------------------------------
$failures = @($script:Results | Where-Object { -not $_.ok })
$report = [ordered]@{
    ok = ($failures.Count -eq 0)
    generated_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    containers = [ordered]@{
        app = $appContainer
        web = $webContainer
        db = $dbContainer
        ref = $refContainer
    }
    networks = $networkMembership
    mounts = [ordered]@{
        app = @($appMounts | ForEach-Object { [ordered]@{ source = [string]$_.Source; destination = [string]$_.Destination; rw = [bool]$_.RW } })
    }
    health = [ordered]@{
        healthz = $healthOk
        healthz_body = $healthBody.Trim()
        login_status = $loginStatus
    }
    test_count = $script:Results.Count
    failure_count = $failures.Count
    failures = @($failures | ForEach-Object { [ordered]@{ name = $_.name; detail = $_.detail } })
    tests = @($script:Results | ForEach-Object { [ordered]@{ name = $_.name; ok = $_.ok; detail = $_.detail } })
    residual_risks = @(
        'El proxy nginx conserva egreso a Internet porque publica 127.0.0.1:8090 sobre una red bridge; no recibe secretos ni datos.',
        'Las pruebas de red usan la IP real del contenedor destino, no solo DNS, para descartar rutas laterales.'
    )
}
$path = Write-JsonReport -Name 'g15_isolation.json' -Data $report
Write-Host ''
Write-Host ("Pruebas: {0}; fallos: {1}" -f $script:Results.Count, $failures.Count)
Write-Host "Reporte: $path"

if ($failures.Count -ne 0) {
    throw ("Fallo el aislamiento G1.5: " + (($failures | ForEach-Object { $_.name }) -join ', '))
}
