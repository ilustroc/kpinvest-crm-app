#!/usr/bin/env bash
# ==============================================================================
# KP INVEST — Reversión Específica de Migración CCD V6 (AQP4 / AQP5)
# ==============================================================================
# Reversión explícita y segura de las dos migraciones CCD sin restaurar
# ciegamente tablas completas ni afectar datos concurrentes de producción.
#
# Uso:
#   ./revertir_ccd_aqp_produccion_v6.sh [--confirm] [--restore-files=DIR_RESPALDO]
# ==============================================================================

set -euo pipefail
umask 077

echo "=============================================================================="
echo "  KP INVEST — REVERSIÓN ESPECÍFICA CCD AQP V6"
echo "=============================================================================="

PROD_DIR="${PROD_DIR:-/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm}"
CONFIRM_FLAG=false
RESTORE_FILES_DIR=""

for arg in "$@"; do
  case "$arg" in
    --confirm)
      CONFIRM_FLAG=true
      ;;
    --restore-files=*)
      RESTORE_FILES_DIR="${arg#*=}"
      ;;
    --help|-h)
      echo "Uso: $0 [--confirm] [--restore-files=/ruta/al/respaldo]"
      exit 0
      ;;
    *)
      echo "ERROR: Parámetro desconocido: $arg" >&2
      exit 1
      ;;
  esac
done

if [ "$CONFIRM_FLAG" = false ]; then
  echo "ADVERTENCIA: Esta operación revertirá las 2 migraciones CCD en producción."
  echo "Escriba 'CONFIRMAR_REVERSION' para continuar:"
  read -r user_input
  if [ "$user_input" != "CONFIRMAR_REVERSION" ]; then
    echo "Operación cancelada por el usuario."
    exit 1
  fi
fi

PROD_ENV="${PROD_DIR}/.env"
if [ ! -f "$PROD_ENV" ]; then
  echo "ERROR: Archivo .env de producción no encontrado: $PROD_ENV" >&2
  exit 1
fi

get_env_var() {
  local env_file="$1"
  local var_name="$2"
  local default_val="${3:-}"
  grep -E "^${var_name}=" "$env_file" | tail -n 1 | cut -d= -f2- | tr -d '"\r' || echo "$default_val"
}

TGT_DB_HOST="${PROD_DB_HOST:-$(get_env_var "$PROD_ENV" "DB_HOST" "127.0.0.1")}"
TGT_DB_PORT="${PROD_DB_PORT:-$(get_env_var "$PROD_ENV" "DB_PORT" "3306")}"
TGT_DB_NAME="${PROD_DB_DATABASE:-$(get_env_var "$PROD_ENV" "DB_DATABASE" "u480021566_kpinvest_bd")}"
TGT_DB_USER="${PROD_DB_USERNAME:-$(get_env_var "$PROD_ENV" "DB_USERNAME")}"
TGT_DB_PASS="${PROD_DB_PASSWORD:-$(get_env_var "$PROD_ENV" "DB_PASSWORD")}"

# Credenciales seguras mediante serializador nativo PHP
CRED_DIR="${HOME}/private/.ccd_credentials"
mkdir -p "$CRED_DIR"
chmod 700 "$CRED_DIR"
CRED_FILE="${CRED_DIR}/ccd_v6_rev_${$}_$(date +%s).json"

cleanup_credentials() {
  if [ -f "$CRED_FILE" ]; then
    rm -f "$CRED_FILE"
  fi
}
trap cleanup_credentials EXIT INT TERM

TGT_P="$TGT_DB_PASS" CRED_OUT="$CRED_FILE" php -r '
  $data = [
    "target" => [
      "host" => getenv("TGT_DB_HOST"),
      "port" => (int) (getenv("TGT_DB_PORT") ?: 3306),
      "database" => getenv("TGT_DB_NAME"),
      "username" => getenv("TGT_DB_USER"),
      "password" => (string) getenv("TGT_P"),
    ],
  ];
  file_put_contents(getenv("CRED_OUT"), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
'
unset TGT_P CRED_OUT
chmod 600 "$CRED_FILE"

# 1. Comprobación exhaustiva de seguridad antes de la reversión
echo "==> [Paso 1/3] Verificando dependencias, lotes de migración y ausencia de datos posteriores..."

php -r '
  require "'"$PROD_DIR"'/vendor/autoload.php";
  $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
  $c = $creds["target"];
  $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $svc = new App\Services\Ccd\CcdProductionMigratorService();
  $check = $svc->validateRollbackSafety($pdo);
  if (!$check["safe"]) {
      fwrite(STDERR, "ERROR: La reversión no es segura por las siguientes razones:\n");
      foreach ($check["reasons"] as $r) {
          fwrite(STDERR, "  - {$r}\n");
      }
      exit(1);
  }
  foreach ($check["warnings"] as $w) {
      echo "  [ADVERTENCIA] {$w}\n";
  }
  echo "  Comprobaciones de seguridad previas conformes.\n";
'

# 2. Reversión explícita y selectiva de las dos migraciones CCD por ruta exacta
echo "==> [Paso 2/3] Revirtiendo exclusivamente las dos migraciones CCD de forma aislada..."
(
  cd "$PROD_DIR"
  # Revertir primero la tabla de asociaciones dependiente
  php artisan migrate:rollback --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --step=1 --force
  # Revertir luego ccd_archivos y la columna ccd_archivo_id
  php artisan migrate:rollback --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --step=1 --force
)

# Verificación post-reversión
php -r '
  require "'"$PROD_DIR"'/vendor/autoload.php";
  $creds = json_decode(file_get_contents("'"$CRED_FILE"'"), true);
  $c = $creds["target"];
  $pdo = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["database"]};charset=utf8mb4", $c["username"], $c["password"], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $svc = new App\Services\Ccd\CcdProductionMigratorService();
  if ($svc->tableExists($pdo, "ccd_cliente_cuenta")) {
      fwrite(STDERR, "ERROR: ccd_cliente_cuenta aún existe tras rollback.\n");
      exit(1);
  }
  if ($svc->tableExists($pdo, "ccd_archivos")) {
      fwrite(STDERR, "ERROR: ccd_archivos aún existe tras rollback.\n");
      exit(1);
  }
  if ($svc->columnExists($pdo, "ccd_clientes", "ccd_archivo_id")) {
      fwrite(STDERR, "ERROR: columna ccd_clientes.ccd_archivo_id aún existe tras rollback.\n");
      exit(1);
  }
  $ccdCount = (int) $pdo->query("SELECT COUNT(*) FROM ccd_clientes")->fetchColumn();
  $ctaCount = (int) $pdo->query("SELECT COUNT(*) FROM clientes_cuentas")->fetchColumn();
  echo "  Verificación de esquema conforme: tablas CCD revertidas con éxito.\n";
  echo "  ccd_clientes intacto: {$ccdCount} registros.\n";
  echo "  clientes_cuentas intacto: {$ctaCount} registros.\n";
'

# 3. Restauración selectiva de archivos respaldados (si se especificó directorio)
if [ -n "$RESTORE_FILES_DIR" ] && [ -d "${RESTORE_FILES_DIR}/modified_files" ]; then
  echo "==> [Paso 3/3] Restaurando archivos modificados desde ${RESTORE_FILES_DIR}/modified_files..."
  (
    cd "${RESTORE_FILES_DIR}/modified_files"
    find . -type f | while read -r f; do
      rel="${f#./}"
      dest="${PROD_DIR}/${rel}"
      mkdir -p "$(dirname "$dest")"
      cp "$f" "$dest"
      echo "  Restaurado: $rel"
    done
  )
else
  echo "==> [Paso 3/3] No se especificó directorio de respaldo para restaurar archivos de código."
fi

# Limpieza de cachés de Laravel
(
  cd "$PROD_DIR"
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
)

echo ""
echo "=============================================================================="
echo "  REVERSIÓN CCD COMPLETADA CON ÉXITO"
echo "  Las dos migraciones CCD fueron deshechas y el esquema volvió a su estado base."
echo "=============================================================================="
exit 0
