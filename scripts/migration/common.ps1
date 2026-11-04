$ErrorActionPreference = 'Stop'

$script:RepoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$script:StagingCompose = Join-Path $script:RepoRoot 'compose.crm.staging.yml'
$script:StagingDbContainer = 'kpcrm-staging-db'
$script:StagingRefContainer = 'kpcrm-staging-ref-db'
# El archivo de entorno real no vive en el repositorio: el CRM actual monta el
# repositorio completo y, de otro modo, podria leer las credenciales locales.
$script:StagingEnvFile = if ([string]::IsNullOrWhiteSpace($env:KP_CRM_STAGING_ENV_FILE)) {
    'C:\KP-CRM-Staging\.env.staging'
}
else {
    $env:KP_CRM_STAGING_ENV_FILE
}
$script:StagingEnvFile = [System.IO.Path]::GetFullPath($script:StagingEnvFile)
$script:RepoPrefix = $script:RepoRoot.TrimEnd('\') + '\'
if ($script:StagingEnvFile.StartsWith($script:RepoPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'El archivo .env.staging debe estar fuera del repositorio.'
}

function Set-RepoLocation {
    Set-Location $script:RepoRoot
}

function Read-DotEnv {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "No existe el archivo de entorno: $Path"
    }

    $values = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }
        $index = $trimmed.IndexOf('=')
        if ($index -lt 1) { continue }
        $key = $trimmed.Substring(0, $index).Trim()
        $value = $trimmed.Substring($index + 1)
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $values[$key] = $value
    }

    return $values
}

function Get-StagingEnv {
    return Read-DotEnv -Path $script:StagingEnvFile
}

function Assert-StagingEnvSafe {
    param([hashtable]$EnvMap)

    if ($null -eq $EnvMap) { $EnvMap = Get-StagingEnv }

    $required = @(
        'STAGING_APP_KEY',
        'STAGING_DB_ROOT_PASSWORD',
        'STAGING_DB_ETL_PASSWORD',
        'STAGING_DB_RESTORE_PASSWORD',
        'STAGING_DB_REF_RO_PASSWORD',
        'STAGING_DB_MASK_PASSWORD',
        'STAGING_DB_APP_PASSWORD',
        'STAGING_DB_RO_PASSWORD',
        'STAGING_TEST_PASSWORD',
        'STAGING_MASK_KEY_FILE',
        'STAGING_IMPORT_DIR'
    )
    foreach ($key in $required) {
        if (-not $EnvMap.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($EnvMap[$key])) {
            throw "Falta $key en el archivo de entorno externo (.env.staging)"
        }
        if ($EnvMap[$key] -match '^REPLACE_WITH_') {
            throw "$key todavia contiene el valor de plantilla"
        }
    }

    # Un valor de ruta jamas debe contener '=' ni saltos de linea: si el .env
    # esta mal formado, Compose puede absorber la linea siguiente y filtrar un
    # secreto en el nombre de un directorio o en una etiqueta del contenedor.
    foreach ($key in @('STAGING_IMPORT_DIR', 'STAGING_MASK_KEY_FILE')) {
        $value = $EnvMap[$key]
        if ($value -match '[=\r\n]') {
            throw "$key contiene caracteres invalidos; revise el .env.staging"
        }
        if ($value -notmatch '^[A-Za-z]:[\\/]') {
            throw "$key debe ser una ruta absoluta de Windows"
        }
    }

    $importPath = [System.IO.Path]::GetFullPath($EnvMap['STAGING_IMPORT_DIR'])
    $keyPath = [System.IO.Path]::GetFullPath($EnvMap['STAGING_MASK_KEY_FILE'])
    foreach ($pair in @(
        @{ Key = 'STAGING_IMPORT_DIR'; Path = $importPath; Leaf = 'import'; Type = 'Container' },
        @{ Key = 'STAGING_MASK_KEY_FILE'; Path = $keyPath; Leaf = 'mask_hmac_key'; Type = 'Leaf' }
    )) {
        if ($pair.Path.StartsWith($script:RepoPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "$($pair.Key) debe estar fuera del repositorio: $($pair.Path)"
        }
        if ((Split-Path -Leaf $pair.Path) -ne $pair.Leaf) {
            throw "$($pair.Key) debe apuntar a $($pair.Leaf)"
        }
        if (-not (Test-Path -LiteralPath $pair.Path -PathType $pair.Type)) {
            throw "$($pair.Key) no existe o no es del tipo esperado: $($pair.Path)"
        }
    }

    foreach ($key in @(
        'STAGING_APP_KEY',
        'STAGING_DB_ROOT_PASSWORD',
        'STAGING_DB_ETL_PASSWORD',
        'STAGING_DB_RESTORE_PASSWORD',
        'STAGING_DB_REF_RO_PASSWORD',
        'STAGING_DB_MASK_PASSWORD',
        'STAGING_DB_APP_PASSWORD',
        'STAGING_DB_RO_PASSWORD'
    )) {
        if ($EnvMap[$key].Length -lt 16) {
            throw "$key es demasiado corto; use al menos 16 caracteres aleatorios"
        }
        if ($EnvMap[$key] -match "['\\]") {
            throw "$key contiene comillas o barras invertidas; use solo caracteres seguros para SQL"
        }
    }
    if ($EnvMap['STAGING_TEST_PASSWORD'].Length -lt 12) {
        throw 'STAGING_TEST_PASSWORD debe tener al menos 12 caracteres'
    }
}

function Assert-Docker {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Docker no esta disponible en PATH'
    }
}

function Assert-OutsideRepo {
    param([Parameter(Mandatory = $true)][string]$Path)

    $full = [System.IO.Path]::GetFullPath($Path)
    $root = $script:RepoRoot.TrimEnd('\') + '\'
    if ($full.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "La ruta debe estar fuera del repositorio: $full"
    }
    return $full
}

function Assert-Execute {
    param(
        [Parameter(Mandatory = $true)][bool]$Execute,
        [Parameter(Mandatory = $true)][string]$Action
    )

    if (-not $Execute) {
        Write-Host "PLAN (no ejecutado): $Action"
        Write-Host 'Agregue -Execute para confirmar esta operacion local.'
        exit 0
    }
}

function Assert-SafeSqlDump {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$AllowedDatabase
    )

    if ($AllowedDatabase -notmatch '^kp_crm_[a-z0-9_]+$') {
        throw "Base destino no permitida para el escaneo: $AllowedDatabase"
    }

    $text = [System.IO.File]::ReadAllText($Path, [System.Text.Encoding]::UTF8)
    if ([string]::IsNullOrWhiteSpace($text)) {
        throw 'El dump esta vacio'
    }

    # Comentarios normales: se eliminan. Comentarios ejecutables /*! ... */ de
    # MySQL/MariaDB: se conserva su contenido y se escanea como SQL. Tambien se
    # cubre /*M! ... */ de MariaDB.
    $text = [regex]::Replace($text, '(?s)/\*(?![!]|M!).*?\*/', ' ')
    # Los comentarios ejecutables llevan una version numerica antes de la
    # sentencia (/*!50000 ... */, /*M!100100 ... */). Se descarta para que el
    # escaneo vea la sentencia real al inicio.
    $text = [regex]::Replace($text, '(?s)/\*(?:!|M!)\d*\s*(.*?)\*/', ' $1 ')
    $text = [regex]::Replace($text, '(?m)--(?=\s|$).*$', ' ')
    $text = [regex]::Replace($text, '(?m)^\s*#.*$', ' ')

    # Cada patron debe empezar una sentencia. Se usa (?:^|;) porque un archivo
    # manipulado puede poner una sentencia peligrosa en la misma linea despues
    # de un INSERT valido. El contenido del match no se imprime: puede incluir
    # datos personales.
    $checks = @(
        @{ Name = 'USE'; Pattern = '(?im)(?:^|;)\s*USE\b' },
        @{ Name = 'CREATE/DROP/ALTER DATABASE'; Pattern = '(?im)(?:^|;)\s*(?:CREATE|DROP|ALTER)\s+(?:DATABASE|SCHEMA)\b' },
        @{ Name = 'CREATE/ALTER/DROP/RENAME USER'; Pattern = '(?im)(?:^|;)\s*(?:CREATE|ALTER|DROP|RENAME)\s+USER\b' },
        @{ Name = 'GRANT/REVOKE/FLUSH/INSTALL/SHUTDOWN'; Pattern = '(?im)(?:^|;)\s*(?:GRANT|REVOKE|FLUSH|INSTALL|UNINSTALL|SHUTDOWN)\b' },
        @{ Name = 'SET GLOBAL/PASSWORD'; Pattern = '(?im)(?:^|;)\s*SET\s+(?:GLOBAL|@@GLOBAL|PASSWORD)\b' },
        @{ Name = 'LOAD DATA/XML'; Pattern = '(?im)(?:^|;)\s*LOAD\s+(?:DATA|XML)\b' },
        @{ Name = 'TRIGGER/PROCEDURE/FUNCTION/EVENT/VIEW/SERVER/TABLESPACE'; Pattern = '(?im)(?:^|;)\s*(?:CREATE|DROP|ALTER)\s+(?:TRIGGER|PROCEDURE|FUNCTION|EVENT|VIEW|SERVER|TABLESPACE|LOGFILE\s+GROUP)\b' },
        @{ Name = 'replicacion'; Pattern = '(?im)(?:^|;)\s*(?:CHANGE|START|STOP)\s+(?:MASTER|SLAVE)\b|(?:^|;)\s*(?:RESET\s+MASTER|PURGE\s+BINARY\s+LOGS)\b' },
        @{ Name = 'INTO OUTFILE/DUMPFILE'; Pattern = '(?i)\bINTO\s+(?:OUTFILE|DUMPFILE)\b' },
        @{ Name = 'DEFINER'; Pattern = '(?i)\bDEFINER\s*=' },
        @{ Name = 'DELIMITER'; Pattern = '(?im)(?:^|;)\s*DELIMITER\b' },
        @{ Name = 'HANDLER'; Pattern = '(?im)(?:^|;)\s*HANDLER\b' }
    )

    foreach ($check in $checks) {
        $match = [regex]::Match($text, $check.Pattern)
        if ($match.Success) {
            $line = ($text.Substring(0, $match.Index) -split "`n").Count
            throw "El dump contiene una construccion no permitida ($($check.Name)) cerca de la linea $line. Solo se aceptan estructuras y datos de $AllowedDatabase."
        }
    }

    return $true
}

function Invoke-Compose {
    param([Parameter(Mandatory = $true)][string[]]$Arguments)

    Assert-Docker
    & docker compose --project-name kpcrm-staging -f $script:StagingCompose --env-file $script:StagingEnvFile @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose fallo con codigo $LASTEXITCODE"
    }
}

function Get-StagingDbRoleCredential {
    param([Parameter(Mandatory = $true)][ValidateSet('ro', 'mask', 'ref_ro', 'restore')][string]$Role)

    $roles = @{
        ro      = @{ User = 'kp_stage_ro';      PasswordKey = 'STAGING_DB_RO_PASSWORD' }
        mask    = @{ User = 'kp_stage_mask';    PasswordKey = 'STAGING_DB_MASK_PASSWORD' }
        ref_ro  = @{ User = 'kp_stage_ref_ro';  PasswordKey = 'STAGING_DB_REF_RO_PASSWORD' }
        restore = @{ User = 'kp_stage_restore'; PasswordKey = 'STAGING_DB_RESTORE_PASSWORD' }
    }

    return $roles[$Role]
}

function Invoke-StagingTools {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('ro', 'mask')][string]$Role,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    $envMap = Get-StagingEnv
    Assert-StagingEnvSafe -EnvMap $envMap
    Assert-ContainerRunning -Name $script:StagingDbContainer
    Assert-ContainerRunning -Name $script:StagingRefContainer
    $credential = Get-StagingDbRoleCredential -Role $Role
    $passwordKey = $credential.PasswordKey
    if (-not $envMap.ContainsKey($passwordKey) -or [string]::IsNullOrWhiteSpace($envMap[$passwordKey])) {
        throw "Falta $passwordKey en el archivo de entorno externo (.env.staging)"
    }

    $oldUser = $env:DB_USERNAME
    $oldPassword = $env:DB_PASSWORD
    $oldRole = $env:DB_ROLE
    try {
        # docker compose run -e VAR (sin valor) toma la variable del proceso
        # padre; la contrasena no se imprime ni queda en los argumentos.
        $env:DB_USERNAME = $credential.User
        $env:DB_PASSWORD = $envMap[$passwordKey]
        $env:DB_ROLE = $Role
        Invoke-Compose -Arguments (@(
            'run', '--rm', '--no-deps', '-e', 'DB_ROLE', '-e', 'DB_USERNAME', '-e', 'DB_PASSWORD',
            'staging-tools'
        ) + $Arguments)
    }
    finally {
        $env:DB_USERNAME = $oldUser
        $env:DB_PASSWORD = $oldPassword
        $env:DB_ROLE = $oldRole
    }
}

function Assert-ContainerRunning {
    param([Parameter(Mandatory = $true)][string]$Name)

    Assert-Docker
    $running = & docker ps --filter "name=^$Name$" --format '{{.Names}}'
    if ($LASTEXITCODE -ne 0 -or ($running -notcontains $Name)) {
        throw "El contenedor $Name no esta en ejecucion. Inicie staging-db primero."
    }
}

function New-ContainerClientConfig {
    param(
        [Parameter(Mandatory = $true)][string]$User,
        [Parameter(Mandatory = $true)][string]$Password,
        [string]$Container = 'kpcrm-staging-db',
        [string]$ContainerPath = '/tmp/.my.cnf'
    )

    $content = "[client]`nuser=$User`npassword=$Password`nhost=127.0.0.1`n"
    $content | & docker exec -i $Container sh -c "umask 077; cat > $ContainerPath"
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo crear el archivo temporal de cliente MariaDB dentro del contenedor'
    }
}

function Remove-ContainerClientConfig {
    param(
        [string]$Container = 'kpcrm-staging-db',
        [string]$ContainerPath = '/tmp/.my.cnf'
    )

    & docker exec $Container rm -f $ContainerPath 2>$null | Out-Null
}

function Invoke-ContainerSql {
    param(
        [Parameter(Mandatory = $true)][string]$Container,
        [Parameter(Mandatory = $true)][string]$Sql,
        [string]$RootConfig = '/tmp/.kpcrm-root-sql.cnf'
    )

    # El SQL puede contener contrasenas. Se envia por stdin a un archivo
    # temporal con umask 077 y se ejecuta con el archivo de cliente; nunca se
    # pasa como argumento de proceso ni se imprime.
    $remote = '/tmp/.kpcrm-apply.sql'
    try {
        $Sql | & docker exec -i $Container sh -c "umask 077; cat > $remote"
        if ($LASTEXITCODE -ne 0) { throw "No se pudo preparar el SQL en $Container" }
        $output = & docker exec $Container sh -c "mariadb --defaults-extra-file=$RootConfig --batch --skip-column-names < $remote" 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "La aplicacion de SQL fallo en $Container"
        }
        return $output
    }
    finally {
        & docker exec $Container rm -f $remote 2>$null | Out-Null
    }
}

function Get-StagingDatabaseContainer {
    param([Parameter(Mandatory = $true)][string]$Database)

    switch ($Database) {
        'kp_crm_v1_reference' { return $script:StagingRefContainer }
        'kp_crm_staging_masked' { return $script:StagingDbContainer }
        'kp_crm_mask_work' { return $script:StagingDbContainer }
        'kp_crm_v3_validation' { return $script:StagingDbContainer }
        'kp_crm_restore_rehearsal' { return $script:StagingDbContainer }
        default { throw "Base no reconocida para staging: $Database" }
    }
}

function Lock-DatabaseWrites {
    param(
        [Parameter(Mandatory = $true)][string]$Database,
        [string]$Container = '',
        [string[]]$ReadUsers = @()
    )

    if ($Container -eq '') { $Container = Get-StagingDatabaseContainer -Database $Database }
    $envMap = Get-StagingEnv
    if (-not $envMap.ContainsKey('STAGING_DB_ROOT_PASSWORD') -or
        [string]::IsNullOrWhiteSpace($envMap['STAGING_DB_ROOT_PASSWORD'])) {
        throw 'Falta STAGING_DB_ROOT_PASSWORD para bloquear la base'
    }

    $rootConfig = '/tmp/.kpcrm-root-lock.cnf'
    try {
        New-ContainerClientConfig -User 'root' -Password $envMap['STAGING_DB_ROOT_PASSWORD'] `
            -Container $Container -ContainerPath $rootConfig

        # Quita toda escritura de la base al usuario de restauracion y a la
        # cuenta ETL heredada. REVOKE no es idempotente si el GRANT ya no
        # existe; se consulta antes.
        $restoreGrants = & docker exec $Container mariadb --defaults-extra-file=$rootConfig `
            --batch --skip-column-names -e "SHOW GRANTS FOR 'kp_stage_restore'@'%';" 2>&1
        $etlGrants = & docker exec $Container mariadb --defaults-extra-file=$rootConfig `
            --batch --skip-column-names -e "SHOW GRANTS FOR 'kp_stage_etl'@'%';" 2>&1
        $revokes = @()
        if (($restoreGrants -join "`n") -match [regex]::Escape($Database)) {
            $revokes += "REVOKE ALL PRIVILEGES ON ``$Database``.* FROM 'kp_stage_restore'@'%';"
        }
        if (($etlGrants -join "`n") -match [regex]::Escape($Database)) {
            $revokes += "REVOKE ALL PRIVILEGES ON ``$Database``.* FROM 'kp_stage_etl'@'%';"
        }
        $grants = @($revokes)
        foreach ($user in $ReadUsers) {
            $grants += "GRANT SELECT ON ``$Database``.* TO '$user'@'%';"
        }
        $grants += 'FLUSH PRIVILEGES;'
        $sql = $grants -join "`n"
        $output = & docker exec $Container mariadb --defaults-extra-file=$rootConfig `
            --batch --skip-column-names -e $sql 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "No se pudo bloquear la base $Database en $Container"
        }

        foreach ($user in @('kp_stage_restore', 'kp_stage_etl')) {
            $current = & docker exec $Container mariadb --defaults-extra-file=$rootConfig `
                --batch --skip-column-names -e "SHOW GRANTS FOR '$user'@'%';" 2>&1
            if ($LASTEXITCODE -ne 0) {
                throw "No se pudieron verificar los GRANT de $user en $Container"
            }
            if (($current -join "`n") -match [regex]::Escape($Database)) {
                throw "La base $Database sigue teniendo privilegios para $user en $Container"
            }
        }
        foreach ($user in $ReadUsers) {
            $current = & docker exec $Container mariadb --defaults-extra-file=$rootConfig `
                --batch --skip-column-names -e "SHOW GRANTS FOR '$user'@'%';" 2>&1
            if ($LASTEXITCODE -ne 0) {
                throw "No se pudieron verificar los GRANT de lectura de $user en $Container"
            }
            if (($current -join "`n") -notmatch ('SELECT ON `' + [regex]::Escape($Database) + '`\.\*')) {
                throw "El usuario de lectura $user perdio SELECT en $Database"
            }
        }
    }
    finally {
        Remove-ContainerClientConfig -Container $Container -ContainerPath $rootConfig
    }
}

function Lock-ReferenceDatabase {
    Lock-DatabaseWrites -Database 'kp_crm_v1_reference' -Container $script:StagingRefContainer `
        -ReadUsers @('kp_stage_ref_ro', 'kp_stage_mask', 'kp_stage_ro')
}

function Lock-RehearsalDatabase {
    Lock-DatabaseWrites -Database 'kp_crm_restore_rehearsal' -Container $script:StagingDbContainer `
        -ReadUsers @('kp_stage_ro')
}

function Invoke-ContainerScalar {
    param(
        [Parameter(Mandatory = $true)][string]$Sql,
        [string]$Database = 'information_schema',
        [string]$Container = 'kpcrm-staging-db',
        [string]$ContainerPath = '/tmp/.my.cnf'
    )

    $output = & docker exec $Container mariadb --defaults-extra-file=$ContainerPath `
        --default-character-set=utf8mb4 --batch --skip-column-names --database=$Database -e $Sql
    if ($LASTEXITCODE -ne 0) {
        throw 'La consulta de verificacion fallo'
    }

    return ($output | Select-Object -First 1)
}

function Write-JsonReport {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][object]$Data
    )

    $dir = Join-Path $script:RepoRoot 'storage\staging\reports'
    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $path = Join-Path $dir $Name
    $json = $Data | ConvertTo-Json -Depth 12
    [System.IO.File]::WriteAllText($path, $json + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
    return $path
}
