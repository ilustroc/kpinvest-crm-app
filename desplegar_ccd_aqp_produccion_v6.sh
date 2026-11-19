#!/usr/bin/env bash
# ==============================================================================
# KP INVEST — Desplegador Automatizado CCD AQP V6.2 a Producción LEGACY
# ==============================================================================
# Uso:
#   ./desplegar_ccd_aqp_produccion_v6.sh --dry-run   # Simulación sin escrituras
#   ./desplegar_ccd_aqp_produccion_v6.sh --apply     # Ejecución autorizada
# ==============================================================================

set -euo pipefail
umask 077

echo "=============================================================================="
echo "  KP INVEST — DESPLEGADOR CCD AQP V6.2 A PRODUCCIÓN LEGACY"
echo "=============================================================================="

# Rutas estándar del entorno Hostinger
STAGING_DIR="${STAGING_DIR:-/home/u480021566/apps/kpinvest-crm-pruebas}"
PROD_DIR="${PROD_DIR:-/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm}"

# Constantes de negocio certificadas
EXPECTED_TOTAL_CCD=275987
EXPECTED_TOTAL_CUENTAS=282595
EXPECTED_AQP4_TOTAL=7024
EXPECTED_AQP5_TOTAL=3200
EXPECTED_AQP_TOTAL=10224
EXPECTED_V3_METHOD=1
EXPECTED_V5_METHOD=10223

EXPECTED_CCD_SHA256="861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b"
EXPECTED_CUENTAS_SHA256="0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f"
MIN_FREE_SPACE_GIB=5

# Tokens requeridos para --apply
APPLY_AUTH_TOKEN="AUTORIZAR_MIGRACION_PRODUCCION_CCD_V6"
APPLY_CONF_TOKEN="CONFIRMAR_TRANSFERENCIA_AQP_DEFINITIVA"

# ------------------------------------------------------------------------------
# Manejo de parámetros CLI
# ------------------------------------------------------------------------------
MODE=""
CONFIRM_FLAG=false

for arg in "$@"; do
  case "$arg" in
    --dry-run)
      MODE="dry-run"
      ;;
    --apply)
      MODE="apply"
      ;;
    --confirm)
      CONFIRM_FLAG=true
      ;;
    --help|-h)
      echo "Uso: $0 [--dry-run | --apply] [--confirm]"
      echo ""
      echo "Opciones:"
      echo "  --dry-run   Simula las 5 fases completas sin realizar escrituras ni modificaciones."
      echo "  --apply     Ejecuta las 5 fases de migración aditiva autorizada hacia producción."
      echo "  --confirm   Evita la confirmación interactiva en modo --apply."
      exit 0
      ;;
    *)
      echo "ERROR: Parámetro desconocido: $arg" >&2
      exit 1
      ;;
  esac
done

if [ -z "$MODE" ]; then
  echo "ERROR: Debe especificar exactamente uno de: --dry-run o --apply." >&2
  exit 1
fi

echo "Modo de ejecución:    $MODE"
echo "Directorio Staging:    $STAGING_DIR"
echo "Directorio Producción: $PROD_DIR"
echo ""

# ------------------------------------------------------------------------------
# Comprobación de binarios requeridos
# ------------------------------------------------------------------------------
for bin in php mysql mysqldump sha256sum df cmp cp tar; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    echo "ERROR: Binario requerido no encontrado en PATH: $bin" >&2
    exit 1
  fi
done

# ------------------------------------------------------------------------------
# Extracción de credenciales efectivas de Laravel (sin parser frágil de .env)
# ------------------------------------------------------------------------------
CRED_DIR="${HOME}/private/.ccd_credentials"
mkdir -p "$CRED_DIR"
chmod 700 "$CRED_DIR"
CRED_FILE="${CRED_DIR}/ccd_v62_deploy_${$}_$(date +%s).json"

cleanup_credentials() {
  if [ -f "$CRED_FILE" ]; then
    rm -f "$CRED_FILE"
  fi
}
trap cleanup_credentials EXIT INT TERM

# Cargar configuraciones efectivas de base de datos desde Laravel
php -r '
  $stagingDir = $argv[1];
  $prodDir = $argv[2];
  $credOut = $argv[3];

  function getLaravelDbConfig(string $dir, string $prefix, string $defaultHost, string $defaultDb): array {
      $dir = rtrim($dir, "/\\");
      $autoload = $dir . "/vendor/autoload.php";
      $bootstrap = $dir . "/bootstrap/app.php";

      $cfg = [];
      if (file_exists($autoload) && file_exists($bootstrap)) {
          $code = "require \$argv[1].\"/vendor/autoload.php\"; \$app=require \$argv[1].\"/bootstrap/app.php\"; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \$conn=config(\"database.default\",\"mysql\"); echo json_encode(config(\"database.connections.{\$conn}\", []));";
          $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
          $proc = @proc_open([PHP_BINARY ?: "php", "-r", $code, $dir], $descriptors, $pipes);
          if (is_resource($proc)) {
              fclose($pipes[0]);
              $out = stream_get_contents($pipes[1]);
              fclose($pipes[1]);
              fclose($pipes[2]);
              proc_close($proc);
              $decoded = json_decode(trim($out), true);
              if (is_array($decoded)) {
                  $cfg = $decoded;
              }
          }
      }

      $host = getenv($prefix."_DB_HOST") ?: ($cfg["host"] ?? $defaultHost);
      $port = (int) (getenv($prefix."_DB_PORT") ?: ($cfg["port"] ?? 3306));
      $db   = getenv($prefix."_DB_DATABASE") ?: ($cfg["database"] ?? $defaultDb);
      $user = getenv($prefix."_DB_USERNAME") ?: ($cfg["username"] ?? "");
      $pass = (string) (getenv($prefix."_DB_PASSWORD") ?: ($cfg["password"] ?? ""));

      return [
          "host" => $host,
          "port" => $port,
          "database" => $db,
          "username" => $user,
          "password" => $pass,
      ];
  }

  $src = getLaravelDbConfig($stagingDir, "STAGING", "localhost", "u480021566_prueba");
  $tgt = getLaravelDbConfig($prodDir, "PROD", "127.0.0.1", "u480021566_kpinvest_bd");

  file_put_contents($credOut, json_encode(["source" => $src, "target" => $tgt], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$STAGING_DIR" "$PROD_DIR" "$CRED_FILE"
chmod 600 "$CRED_FILE"

# Función PDO segura para ejecutar consultas SQL sin exponer contraseñas en ps
run_sql_query() {
  local env_type="$1"
  local sql="$2"
  php -r '
    $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
    $c = $creds["'"$env_type"'"];
    $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->query($argv[1]);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo implode("\t", $row) . "\n";
    }
  ' "$sql"
}

run_src_sql() {
  run_sql_query "source" "$1"
}

run_tgt_sql() {
  run_sql_query "target" "$1"
}

# ==============================================================================
# FASE 1: VALIDACIONES Y RESPALDO
# ==============================================================================
echo "==> [Fase 1/5] Validaciones de preflight, firmas SHA-256 certificadas y espacio..."

# 1.1 Verificación de conectividad origen y destino
php -r '
  $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
  foreach (["source" => "Staging", "target" => "Producción"] as $key => $label) {
      $c = $creds[$key];
      try {
          $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
              PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          ]);
          echo "  Conectividad con {$label} ({$c["username"]}@{$c["host"]}:{$c["database"]}): OK\n";
      } catch (Exception $e) {
          fwrite(STDERR, "ERROR: No se pudo conectar a {$label}: " . $e->getMessage() . "\n");
          exit(1);
      }
  }
'

# 1.2 Verificación de conteo de registros en ambas bases
SRC_CCD_COUNT=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes;")
TGT_CCD_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes;")
SRC_CTA_COUNT=$(run_src_sql "SELECT COUNT(*) FROM clientes_cuentas;")
TGT_CTA_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM clientes_cuentas;")
SRC_AQP4_COUNT=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4';")
TGT_AQP4_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4';")
SRC_AQP5_COUNT=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5';")
TGT_AQP5_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5';")

if [ "$SRC_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ] || [ "$TGT_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ]; then
  echo "ERROR: Conteo de ccd_clientes no coincide con 275,987 (Origen: $SRC_CCD_COUNT, Destino: $TGT_CCD_COUNT)." >&2
  exit 1
fi

if [ "$SRC_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ] || [ "$TGT_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ]; then
  echo "ERROR: Conteo de clientes_cuentas no coincide con 282,595 (Origen: $SRC_CTA_COUNT, Destino: $TGT_CTA_COUNT)." >&2
  exit 1
fi

if [ "$SRC_AQP4_COUNT" -ne "$EXPECTED_AQP4_TOTAL" ] || [ "$TGT_AQP4_COUNT" -ne "$EXPECTED_AQP4_TOTAL" ]; then
  echo "ERROR: Conteo de AQP4 no coincide con 7,024 (Origen: $SRC_AQP4_COUNT, Destino: $TGT_AQP4_COUNT)." >&2
  exit 1
fi

if [ "$SRC_AQP5_COUNT" -ne "$EXPECTED_AQP5_TOTAL" ] || [ "$TGT_AQP5_COUNT" -ne "$EXPECTED_AQP5_TOTAL" ]; then
  echo "ERROR: Conteo de AQP5 no coincide con 3,200 (Origen: $SRC_AQP5_COUNT, Destino: $TGT_AQP5_COUNT)." >&2
  exit 1
fi
echo "  Conteos tabulares conforme: 275,987 CCDs, 282,595 Cuentas (7,024 AQP4, 3,200 AQP5)"

# 1.3 Verificación de firmas SHA-256 certificadas
calculate_sha_php() {
  local env_type="$1"
  local table_type="$2"
  php -r '
    require "'"$STAGING_DIR"'/vendor/autoload.php";
    $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
    $c = $creds["'"$env_type"'"];
    $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $svc = new App\Services\Ccd\CcdProductionMigratorService();
    if ("'"$table_type"'" === "ccd") {
        echo $svc->calculateCcdAqpSha256($pdo);
    } else {
        echo $svc->calculateCuentasSha256($pdo);
    }
  '
}

SRC_CCD_HASH=$(calculate_sha_php "source" "ccd")
TGT_CCD_HASH=$(calculate_sha_php "target" "ccd")

if [ "$SRC_CCD_HASH" != "$TGT_CCD_HASH" ]; then
  echo "ERROR: Las huellas SHA-256 de ccd_clientes AQP no coinciden entre origen ($SRC_CCD_HASH) y destino ($TGT_CCD_HASH)." >&2
  exit 1
fi

if [ "$SRC_CCD_HASH" != "$EXPECTED_CCD_SHA256" ]; then
  echo "ERROR: Huellas SHA-256 de ccd_clientes AQP ($SRC_CCD_HASH) difieren de la firma certificada ($EXPECTED_CCD_SHA256)." >&2
  exit 1
fi
echo "  Firma CCD AQP conforme: $SRC_CCD_HASH"

SRC_CTA_HASH=$(calculate_sha_php "source" "cuentas")
TGT_CTA_HASH=$(calculate_sha_php "target" "cuentas")

if [ "$SRC_CTA_HASH" != "$TGT_CTA_HASH" ]; then
  echo "ERROR: Las huellas SHA-256 de clientes_cuentas no coinciden entre origen ($SRC_CTA_HASH) y destino ($TGT_CTA_HASH)." >&2
  exit 1
fi

if [ "$SRC_CTA_HASH" != "$EXPECTED_CUENTAS_SHA256" ]; then
  echo "ERROR: Huellas SHA-256 de clientes_cuentas ($SRC_CTA_HASH) difieren de la firma certificada ($EXPECTED_CUENTAS_SHA256)." >&2
  exit 1
fi
echo "  Firma Cuentas (282,595 registros) conforme: $SRC_CTA_HASH"

# 1.4 Verificación de asociaciones en Staging
STAGING_ASSOC=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada';")
STAGING_V3=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v3';")
STAGING_V5=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v5';")

if [ "$STAGING_ASSOC" -ne "$EXPECTED_AQP_TOTAL" ] || [ "$STAGING_V3" -ne "$EXPECTED_V3_METHOD" ] || [ "$STAGING_V5" -ne "$EXPECTED_V5_METHOD" ]; then
  echo "ERROR: Staging no presenta la distribución esperada (Total: $STAGING_ASSOC, V3: $STAGING_V3, V5: $STAGING_V5)." >&2
  exit 1
fi
echo "  Asociaciones en Staging: $STAGING_ASSOC (1 v3, 10,223 v5) (Conforme)"

# 1.5 Comprobación de espacio en disco
FREE_SPACE_KB=$(df -k "$PROD_DIR" | awk 'NR==2 {print $4}')
FREE_SPACE_GIB=$(( FREE_SPACE_KB / 1024 / 1024 ))
echo "  Espacio libre en destino: ${FREE_SPACE_GIB} GiB (Requerido: >= ${MIN_FREE_SPACE_GIB} GiB)"

if [ "$FREE_SPACE_GIB" -lt "$MIN_FREE_SPACE_GIB" ]; then
  echo "ERROR: Espacio insuficiente en disco para alojar los 2.618 GiB de PDFs privados." >&2
  exit 1
fi

TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
BACKUP_DIR="${HOME}/private/backups/ccd-v6-produccion-${TIMESTAMP}"

if [ "$MODE" = "apply" ]; then
  echo ""
  echo "ADVERTENCIA: Está a punto de ejecutar la MIGRACIÓN A PRODUCCIÓN (--apply)."
  if [ "$CONFIRM_FLAG" = false ]; then
    echo "Escriba 'CONFIRMAR_PRODUCCION' para continuar:"
    read -r user_input
    if [ "$user_input" != "CONFIRMAR_PRODUCCION" ]; then
      echo "Operación cancelada por el usuario."
      exit 1
    fi
  fi

  mkdir -p "$BACKUP_DIR"
  chmod 700 "$BACKUP_DIR"
  echo "  Generando respaldo SQL previo en ${BACKUP_DIR}..."

  PROD_DUMP="${BACKUP_DIR}/prod_pre_migracion_${TIMESTAMP}.sql"
  TGT_DUMP_PASS=$(php -r '$c = json_decode(file_get_contents("'"$CRED_FILE"'"), true); echo $c["target"]["password"];')
  TGT_DUMP_USER=$(php -r '$c = json_decode(file_get_contents("'"$CRED_FILE"'"), true); echo $c["target"]["username"];')
  TGT_DUMP_HOST=$(php -r '$c = json_decode(file_get_contents("'"$CRED_FILE"'"), true); echo $c["target"]["host"];')
  TGT_DUMP_PORT=$(php -r '$c = json_decode(file_get_contents("'"$CRED_FILE"'"), true); echo $c["target"]["port"];')
  TGT_DUMP_DB=$(php -r '$c = json_decode(file_get_contents("'"$CRED_FILE"'"), true); echo $c["target"]["database"];')

  MYSQL_PWD="${TGT_DUMP_PASS}" mysqldump \
    -h "${TGT_DUMP_HOST}" -P "${TGT_DUMP_PORT}" -u "${TGT_DUMP_USER}" \
    --single-transaction --quick --skip-lock-tables --no-tablespaces \
    "${TGT_DUMP_DB}" ccd_clientes clientes_cuentas migrations > "${PROD_DUMP}"

  unset TGT_DUMP_PASS TGT_DUMP_USER TGT_DUMP_HOST TGT_DUMP_PORT TGT_DUMP_DB
  chmod 600 "${PROD_DUMP}"
  sha256sum "${PROD_DUMP}" | tee "${PROD_DUMP}.sha256"
  test -s "${PROD_DUMP}"
  echo "  Respaldo SQL generado exitosamente."

  if [ -f "${PROD_DIR}/.env" ]; then
    cp "${PROD_DIR}/.env" "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
    chmod 600 "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
  fi
  if [ -f "${PROD_DIR}/config/filesystems.php" ]; then
    cp "${PROD_DIR}/config/filesystems.php" "${BACKUP_DIR}/prod_filesystems_${TIMESTAMP}.bak"
  fi
fi

# ==============================================================================
# FASE 2: INSTALACIÓN DE TABLAS CCD
# ==============================================================================
echo ""
echo "==> [Fase 2/5] Comprobación e instalación de esquema CCD (migraciones selectivas)..."

if [ "$MODE" = "dry-run" ]; then
  php -r '
    require "'"$STAGING_DIR"'/vendor/autoload.php";
    $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
    $c = $creds["target"];
    $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $svc = new App\Services\Ccd\CcdProductionMigratorService();
    $schema = $svc->inspectTargetSchema($pdo);
    echo "  [DRY-RUN] Tabla ccd_archivos: " . ($schema["has_ccd_archivos"] ? "EXISTE" : "PENDIENTE DE MIGRAR") . "\n";
    echo "  [DRY-RUN] Tabla ccd_cliente_cuenta: " . ($schema["has_ccd_cliente_cuenta"] ? "EXISTE" : "PENDIENTE DE MIGRAR") . "\n";
    echo "  [DRY-RUN] Columna ccd_clientes.ccd_archivo_id: " . ($schema["has_ccd_archivo_id_column"] ? "EXISTE" : "PENDIENTE DE MIGRAR") . "\n";
  '
else
  # En modo apply: ejecutar las 2 migraciones selectivas CCD
  (
    cd "$PROD_DIR"
    php artisan migrate --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --force
    php artisan migrate --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --force
  )
  echo "  Migraciones selectivas ejecutadas exitosamente."
fi

# ==============================================================================
# FASE 3: MIGRACIÓN DE LOS 10,224 PDFS Y ASOCIACIONES
# ==============================================================================
echo ""
echo "==> [Fase 3/5] Migración transaccional de PDFs y asociaciones CCD..."

MANIFEST_PATH=""
if [ "$MODE" = "apply" ]; then
  MANIFEST_DIR="${PROD_DIR}/storage/app/private/ccd/audits"
  mkdir -p "$MANIFEST_DIR"
  MANIFEST_PATH="${MANIFEST_DIR}/ccd-v6-produccion-manifest-${TIMESTAMP}.csv"
fi

(
  cd "$STAGING_DIR"
  if [ "$MODE" = "dry-run" ]; then
    php artisan ccd:migrate-aqp-production-v6 \
      --dry-run \
      --credentials-file="$CRED_FILE" \
      --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
      --target-storage="${PROD_DIR}/storage/app/private/ccd"
  else
    php artisan ccd:migrate-aqp-production-v6 \
      --apply \
      --authorization="$APPLY_AUTH_TOKEN" \
      --confirmation="$APPLY_CONF_TOKEN" \
      --credentials-file="$CRED_FILE" \
      --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
      --target-storage="${PROD_DIR}/storage/app/private/ccd" \
      --manifest="$MANIFEST_PATH"
  fi
)

# ==============================================================================
# FASE 4: INTEGRACIÓN DE LA INTERFAZ DEL CRM (ADAPTADA A PRODUCCIÓN LEGACY)
# ==============================================================================
echo ""
echo "==> [Fase 4/5] Integración de interfaz aditiva del CRM (rutas, controlador, vista)..."

php -r '
  require "'"$STAGING_DIR"'/vendor/autoload.php";
  $svc = new App\Services\Ccd\CcdProductionMigratorService();
  $apply = ("'"$MODE"'" === "apply");
  $backupDir = $apply ? "'"$BACKUP_DIR"'" : null;
  $svc->verifyAndIntegrateComponents(
      "'"$STAGING_DIR"'",
      "'"$PROD_DIR"'",
      $apply,
      $backupDir,
      function (string $msg) { echo "  {$msg}\n"; }
  );
'

# ==============================================================================
# FASE 5: AUDITORÍA FUNCIONAL FINAL
# ==============================================================================
echo ""
echo "==> [Fase 5/5] Auditoría funcional final..."

if [ "$MODE" = "dry-run" ]; then
  echo "  [DRY-RUN] Simulando auditoría final post-migración: conforme."
  echo ""
  echo "=============================================================================="
  echo "  SIMULACIÓN DRY-RUN V6.2 COMPLETADA SATISFACTORIAMENTE"
  echo "  - CERO escrituras realizadas en base de datos de producción."
  echo "  - CERO directorios o archivos modificados en el CRM de producción."
  echo "  - Integración compatible comprobada con arquitectura LEGACY de producción."
  echo "  - Puede proceder con --apply cuando cuente con la debida autorización."
  echo "=============================================================================="
  exit 0
else
  # En modo apply: ejecutar auditoría sobre target
  php -r '
    require "'"$STAGING_DIR"'/vendor/autoload.php";
    $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
    $c = $creds["target"];
    $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $svc = new App\Services\Ccd\CcdProductionMigratorService();
    $audit = $svc->auditTarget($pdo, "'"$PROD_DIR"'/storage/app/private/ccd", false);
    if (!$audit["success"]) {
        fwrite(STDERR, "ERROR en auditoría post-migración:\n" . implode("\n", $audit["errors"]) . "\n");
        exit(1);
    }
    echo "  Auditoría conforme: {$audit["total_archivos"]} archivos catalogados, {$audit["total_asociaciones"]} asociaciones confirmadas.\n";
  '

  # Limpieza de cachés de Laravel en producción
  (
    cd "$PROD_DIR"
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
  )

  echo ""
  echo "=============================================================================="
  echo "  DESPLIEGUE CCD AQP V6.2 A PRODUCCIÓN LEGACY COMPLETADO CON ÉXITO"
  echo "  Respaldo previo: $BACKUP_DIR"
  echo "  Manifiesto:      $MANIFEST_PATH"
  if [ -f "$MANIFEST_PATH" ]; then
    echo "  Manifiesto SHA:  $(sha256sum "$MANIFEST_PATH" | cut -d' ' -f1)"
  fi
  echo "=============================================================================="
  exit 0
fi
