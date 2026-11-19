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

run_tgt_sql() {
  local sql="$1"
  MYSQL_PWD="${TGT_DB_PASS}" mysql -h "${TGT_DB_HOST}" -P "${TGT_DB_PORT}" -u "${TGT_DB_USER}" -N -B "${TGT_DB_NAME}" -e "$sql"
}

# 1. Comprobación de dependencias posteriores
echo "==> [Paso 1/3] Verificando dependencias y ausencia de datos posteriores..."

TABLE_EXISTS=$(run_tgt_sql "SHOW TABLES LIKE 'ccd_cliente_cuenta';")
if [ -n "$TABLE_EXISTS" ]; then
  OTHER_HARVESTS=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha NOT IN ('AQP4', 'AQP5');" || echo "0")
  if [ "$OTHER_HARVESTS" -gt 0 ]; then
    echo "ERROR: No se puede revertir ccd_cliente_cuenta: contiene $OTHER_HARVESTS asociaciones de cosechas distintas a AQP4/AQP5." >&2
    exit 1
  fi
fi

# 2. Reversión explícita de las dos migraciones CCD por ruta exacta
echo "==> [Paso 2/3] Revirtiendo exclusivamente las dos migraciones CCD..."
(
  cd "$PROD_DIR"
  # Revertir primero la tabla de asociaciones dependiente
  php artisan migrate:rollback --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --force
  # Revertir luego ccd_archivos y la columna ccd_archivo_id
  php artisan migrate:rollback --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --force
)

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
