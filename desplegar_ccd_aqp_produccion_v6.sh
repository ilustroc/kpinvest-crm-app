#!/usr/bin/env bash
# ==============================================================================
# KP INVEST — Desplegador Automatizado CCD AQP V6 a Producción (V6.1)
# ==============================================================================
# Uso:
#   ./desplegar_ccd_aqp_produccion_v6.sh --dry-run   # Simulación sin escrituras
#   ./desplegar_ccd_aqp_produccion_v6.sh --apply     # Ejecución autorizada
# ==============================================================================

set -euo pipefail
umask 077

echo "=============================================================================="
echo "  KP INVEST — DESPLEGADOR CCD AQP V6 A PRODUCCIÓN (V6.1)"
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
      echo "  --dry-run   Simula la migración completa sin realizar escrituras ni modificaciones."
      echo "  --apply     Ejecuta la migración aditiva autorizada hacia producción."
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

echo "Modo de ejecución: $MODE"
echo "Directorio Staging:    $STAGING_DIR"
echo "Directorio Producción: $PROD_DIR"
echo ""

# ------------------------------------------------------------------------------
# 1. Comprobación de binarios requeridos
# ------------------------------------------------------------------------------
for bin in php mysql mysqldump sha256sum df cmp cp tar; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    echo "ERROR: Binario requerido no encontrado en PATH: $bin" >&2
    exit 1
  fi
done

# ------------------------------------------------------------------------------
# 2. Extracción de credenciales de .env sin exponer contraseñas en logs
# ------------------------------------------------------------------------------
get_env_var() {
  local env_file="$1"
  local var_name="$2"
  local default_val="${3:-}"
  if [ -f "$env_file" ]; then
    local val
    val=$(grep -E "^${var_name}=" "$env_file" | tail -n 1 | cut -d= -f2- | tr -d '"\r' || true)
    if [ -n "$val" ]; then
      echo "$val"
      return 0
    fi
  fi
  echo "$default_val"
}

STAGING_ENV="${STAGING_DIR}/.env"
PROD_ENV="${PROD_DIR}/.env"

if [ ! -f "$STAGING_ENV" ]; then
  echo "ERROR: Archivo .env de staging no encontrado: $STAGING_ENV" >&2
  exit 1
fi

if [ ! -f "$PROD_ENV" ]; then
  echo "ERROR: Archivo .env de producción no encontrado: $PROD_ENV" >&2
  exit 1
fi

SRC_DB_HOST="${STAGING_DB_HOST:-$(get_env_var "$STAGING_ENV" "DB_HOST" "127.0.0.1")}"
SRC_DB_PORT="${STAGING_DB_PORT:-$(get_env_var "$STAGING_ENV" "DB_PORT" "3306")}"
SRC_DB_NAME="${STAGING_DB_DATABASE:-$(get_env_var "$STAGING_ENV" "DB_DATABASE" "u480021566_prueba")}"
SRC_DB_USER="${STAGING_DB_USERNAME:-$(get_env_var "$STAGING_ENV" "DB_USERNAME")}"
SRC_DB_PASS="${STAGING_DB_PASSWORD:-$(get_env_var "$STAGING_ENV" "DB_PASSWORD")}"

TGT_DB_HOST="${PROD_DB_HOST:-$(get_env_var "$PROD_ENV" "DB_HOST" "127.0.0.1")}"
TGT_DB_PORT="${PROD_DB_PORT:-$(get_env_var "$PROD_ENV" "DB_PORT" "3306")}"
TGT_DB_NAME="${PROD_DB_DATABASE:-$(get_env_var "$PROD_ENV" "DB_DATABASE" "u480021566_kpinvest_bd")}"
TGT_DB_USER="${PROD_DB_USERNAME:-$(get_env_var "$PROD_ENV" "DB_USERNAME")}"
TGT_DB_PASS="${PROD_DB_PASSWORD:-$(get_env_var "$PROD_ENV" "DB_PASSWORD")}"

# ------------------------------------------------------------------------------
# 3. Mecanismo privado de credenciales (evita pasar contraseñas por argumentos ps)
# ------------------------------------------------------------------------------
CRED_DIR="${HOME}/private/.ccd_credentials"
mkdir -p "$CRED_DIR"
chmod 700 "$CRED_DIR"
CRED_FILE="${CRED_DIR}/ccd_v6_${$}_$(date +%s).json"

cleanup_credentials() {
  if [ -f "$CRED_FILE" ]; then
    rm -f "$CRED_FILE"
  fi
}
trap cleanup_credentials EXIT INT TERM

cat <<EOF > "$CRED_FILE"
{
  "source": {
    "host": "${SRC_DB_HOST}",
    "port": ${SRC_DB_PORT},
    "database": "${SRC_DB_NAME}",
    "username": "${SRC_DB_USER}",
    "password": "${SRC_DB_PASS}"
  },
  "target": {
    "host": "${TGT_DB_HOST}",
    "port": ${TGT_DB_PORT},
    "database": "${TGT_DB_NAME}",
    "username": "${TGT_DB_USER}",
    "password": "${TGT_DB_PASS}"
  }
}
EOF
chmod 600 "$CRED_FILE"

# Funciones de consulta SQL seguras con MYSQL_PWD en entorno interno
run_src_sql() {
  local sql="$1"
  MYSQL_PWD="${SRC_DB_PASS}" mysql -h "${SRC_DB_HOST}" -P "${SRC_DB_PORT}" -u "${SRC_DB_USER}" -N -B "${SRC_DB_NAME}" -e "$sql"
}

run_tgt_sql() {
  local sql="$1"
  MYSQL_PWD="${TGT_DB_PASS}" mysql -h "${TGT_DB_HOST}" -P "${TGT_DB_PORT}" -u "${TGT_DB_USER}" -N -B "${TGT_DB_NAME}" -e "$sql"
}

# ------------------------------------------------------------------------------
# 4. Preflight: Validación de firmas y conteos
# ------------------------------------------------------------------------------
echo "==> [Paso 1/6] Ejecutando validaciones preflight de datos certificados..."

# Conteos ccd_clientes
SRC_CCD_COUNT=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes;")
TGT_CCD_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes;")
echo "  ccd_clientes total: origen=$SRC_CCD_COUNT | destino=$TGT_CCD_COUNT (esperado: $EXPECTED_TOTAL_CCD)"

if [ "$SRC_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ] || [ "$TGT_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ]; then
  echo "ERROR: Conteo de ccd_clientes no coincide con el valor certificado." >&2
  exit 1
fi

# Conteos clientes_cuentas
SRC_CTA_COUNT=$(run_src_sql "SELECT COUNT(*) FROM clientes_cuentas;")
TGT_CTA_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM clientes_cuentas;")
echo "  clientes_cuentas total: origen=$SRC_CTA_COUNT | destino=$TGT_CTA_COUNT (esperado: $EXPECTED_TOTAL_CUENTAS)"

if [ "$SRC_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ] || [ "$TGT_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ]; then
  echo "ERROR: Conteo de clientes_cuentas no coincide con el valor certificado." >&2
  exit 1
fi

# Conteos AQP4 / AQP5
SRC_AQP4=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4';")
TGT_AQP4=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4';")
SRC_AQP5=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5';")
TGT_AQP5=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5';")

if [ "$SRC_AQP4" -ne "$EXPECTED_AQP4_TOTAL" ] || [ "$TGT_AQP4" -ne "$EXPECTED_AQP4_TOTAL" ] || \
   [ "$SRC_AQP5" -ne "$EXPECTED_AQP5_TOTAL" ] || [ "$TGT_AQP5" -ne "$EXPECTED_AQP5_TOTAL" ]; then
  echo "ERROR: Distribución AQP4/AQP5 en ccd_clientes no coincide." >&2
  exit 1
fi
echo "  CCD AQP4: $SRC_AQP4 | CCD AQP5: $SRC_AQP5 (Conforme)"

# Firmas SHA-256 (serialización tabulada ordenada por ID sin cabeceras)
echo "  Calculando huellas SHA-256 para verificación cruzada..."
SRC_CCD_HASH=$(run_src_sql "SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)
TGT_CCD_HASH=$(run_tgt_sql "SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)

if [ "$SRC_CCD_HASH" != "$EXPECTED_CCD_SHA256" ] || [ "$TGT_CCD_HASH" != "$EXPECTED_CCD_SHA256" ]; then
  echo "ERROR: Huellas SHA-256 de ccd_clientes AQP difieren de la firma certificada ($EXPECTED_CCD_SHA256)." >&2
  exit 1
fi
echo "  Firma CCD AQP conforme: $SRC_CCD_HASH"

SRC_CTA_HASH=$(run_src_sql "SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)
TGT_CTA_HASH=$(run_tgt_sql "SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)

if [ "$SRC_CTA_HASH" != "$EXPECTED_CUENTAS_SHA256" ] || [ "$TGT_CTA_HASH" != "$EXPECTED_CUENTAS_SHA256" ]; then
  echo "ERROR: Huellas SHA-256 de clientes_cuentas AQP difieren de la firma certificada ($EXPECTED_CUENTAS_SHA256)." >&2
  exit 1
fi
echo "  Firma Cuentas AQP conforme: $SRC_CTA_HASH"

# Asociaciones de Staging
STAGING_ASSOC=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada';")
STAGING_V3=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v3';")
STAGING_V5=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v5';")

if [ "$STAGING_ASSOC" -ne "$EXPECTED_AQP_TOTAL" ] || [ "$STAGING_V3" -ne "$EXPECTED_V3_METHOD" ] || [ "$STAGING_V5" -ne "$EXPECTED_V5_METHOD" ]; then
  echo "ERROR: Staging no presenta la distribución esperada (Total: $STAGING_ASSOC, V3: $STAGING_V3, V5: $STAGING_V5)." >&2
  exit 1
fi
echo "  Asociaciones en Staging: $STAGING_ASSOC (1 v3, 10,223 v5) (Conforme)"

# ------------------------------------------------------------------------------
# 5. Comprobación de espacio en disco (sin crear directorios en dry-run)
# ------------------------------------------------------------------------------
echo "==> [Paso 2/6] Verificando espacio disponible en disco..."
FREE_SPACE_KB=$(df -k "$PROD_DIR" | awk 'NR==2 {print $4}')
FREE_SPACE_GIB=$(( FREE_SPACE_KB / 1024 / 1024 ))
echo "  Espacio libre en sistema de destino: ${FREE_SPACE_GIB} GiB (Requerido: >= ${MIN_FREE_SPACE_GIB} GiB)"

if [ "$FREE_SPACE_GIB" -lt "$MIN_FREE_SPACE_GIB" ]; then
  echo "ERROR: Espacio insuficiente en disco para alojar los 2.618 GiB de PDFs privados." >&2
  exit 1
fi

# ------------------------------------------------------------------------------
# Definición de archivos CCD para sincronización aditiva segura
# ------------------------------------------------------------------------------
CCD_FILES=(
  "database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php"
  "database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php"
  "config/ccd.php"
  "app/Models/CcdArchivo.php"
  "app/Models/CcdClienteCuenta.php"
  "app/Models/CcdCliente.php"
  "app/Models/ClienteCuenta.php"
  "app/Http/Controllers/CcdDocumentController.php"
  "app/Console/Commands/MigrateCcdAqpProductionV6.php"
  "app/Services/Ccd/CcdProductionMigratorService.php"
  "app/Services/Cliente/ClienteProfileService.php"
  "routes/web/clientes.php"
  "resources/views/clientes/show.blade.php"
)

# ------------------------------------------------------------------------------
# En modo DRY-RUN: Simulación estricta sin escrituras y salida limpia
# ------------------------------------------------------------------------------
if [ "$MODE" = "dry-run" ]; then
  echo "==> [Paso 3/6] [DRY-RUN] Comprobando integración aditiva de archivos sin escribir cambios..."
  for rel_file in "${CCD_FILES[@]}"; do
    src_f="${STAGING_DIR}/${rel_file}"
    tgt_f="${PROD_DIR}/${rel_file}"
    if [ ! -f "$src_f" ]; then
      echo "  [ADVERTENCIA] Archivo origen no encontrado: $rel_file"
    elif [ ! -f "$tgt_f" ]; then
      echo "  [DRY-RUN] Archivo nuevo a instalar: $rel_file"
    elif cmp -s "$src_f" "$tgt_f"; then
      echo "  [DRY-RUN] Archivo existente idéntico: $rel_file"
    else
      echo "  [DRY-RUN] Archivo existente difiere; se respaldará individualmente en --apply: $rel_file"
    fi
  done

  echo ""
  echo "==> [Paso 4/6] [DRY-RUN] Ejecutando simulación mediante ccd:migrate-aqp-production-v6..."
  (
    cd "$STAGING_DIR"
    php artisan ccd:migrate-aqp-production-v6 \
      --dry-run \
      --credentials-file="$CRED_FILE" \
      --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
      --target-storage="${PROD_DIR}/storage/app/private/ccd"
  )

  echo ""
  echo "=============================================================================="
  echo "  SIMULACIÓN DRY-RUN COMPLETADA SATISFACTORIAMENTE"
  echo "  - CERO escrituras realizadas en base de datos de producción."
  echo "  - CERO directorios o archivos modificados en el CRM de producción."
  echo "  - Puede proceder con --apply cuando cuente con la autorización requerida."
  echo "=============================================================================="
  exit 0
fi

# ==============================================================================
# SECCIÓN APPLY: Ejecución con escrituras autorizadas
# ==============================================================================
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

TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
BACKUP_DIR="${HOME}/private/backups/ccd-v6-produccion-${TIMESTAMP}"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "==> [Paso 3/6] Realizando respaldo previo en ${BACKUP_DIR}..."

# Respaldo de BD destino (solo tablas afectadas)
PROD_DUMP="${BACKUP_DIR}/prod_pre_migracion_${TIMESTAMP}.sql"
MYSQL_PWD="${TGT_DB_PASS}" mysqldump \
  -h "${TGT_DB_HOST}" -P "${TGT_DB_PORT}" -u "${TGT_DB_USER}" \
  --single-transaction --quick --skip-lock-tables --no-tablespaces \
  "${TGT_DB_NAME}" ccd_clientes clientes_cuentas migrations > "${PROD_DUMP}"

chmod 600 "${PROD_DUMP}"
sha256sum "${PROD_DUMP}" | tee "${PROD_DUMP}.sha256"
test -s "${PROD_DUMP}"
echo "  Respaldo SQL generado exitosamente."

# Respaldo de configuración base
cp "${PROD_DIR}/.env" "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
chmod 600 "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
if [ -f "${PROD_DIR}/config/filesystems.php" ]; then
  cp "${PROD_DIR}/config/filesystems.php" "${BACKUP_DIR}/prod_filesystems_${TIMESTAMP}.bak"
fi

# ------------------------------------------------------------------------------
# 6. Sincronización aditiva con respaldo individual de archivos modificados
# ------------------------------------------------------------------------------
echo "==> [Paso 4/6] Sincronizando componentes del módulo CCD de forma aditiva..."
MODIFIED_FILES_DIR="${BACKUP_DIR}/modified_files"

for rel_file in "${CCD_FILES[@]}"; do
  src_f="${STAGING_DIR}/${rel_file}"
  tgt_f="${PROD_DIR}/${rel_file}"

  if [ ! -f "$src_f" ]; then
    continue
  fi

  if [ ! -f "$tgt_f" ]; then
    mkdir -p "$(dirname "$tgt_f")"
    cp "$src_f" "$tgt_f"
    chmod 644 "$tgt_f"
    echo "  [NUEVO] Instalado: $rel_file"
  elif cmp -s "$src_f" "$tgt_f"; then
    echo "  [INTACTO] Sin cambios: $rel_file"
  else
    # Archivo existente difiere: respaldar versión previa antes de actualizar
    bak_dest="${MODIFIED_FILES_DIR}/${rel_file}"
    mkdir -p "$(dirname "$bak_dest")"
    cp "$tgt_f" "$bak_dest"
    cp "$src_f" "$tgt_f"
    chmod 644 "$tgt_f"
    echo "  [ACTUALIZADO CON RESPALDO] $rel_file -> respaldo guardado en $bak_dest"
  fi
done

# Configuración de disco ccd-private en filesystems.php (aditivo)
if ! grep -q "ccd-private" "${PROD_DIR}/config/filesystems.php"; then
  echo "  Configurando disco ccd-private en filesystems.php..."
  mkdir -p "${MODIFIED_FILES_DIR}/config"
  cp "${PROD_DIR}/config/filesystems.php" "${MODIFIED_FILES_DIR}/config/filesystems.php"

  python3 -c "
with open('${PROD_DIR}/config/filesystems.php', 'r') as f:
    c = f.read()
if 'ccd-private' not in c:
    disk_cfg = '''        \'ccd-private\' => [
            \'driver\' => \'local\',
            \'root\' => storage_path(\'app/private/ccd\'),
            \'visibility\' => \'private\',
            \'directory_visibility\' => \'private\',
            \'permissions\' => [
                \'file\' => [
                    \'public\' => 0660,
                    \'private\' => 0660,
                ],
                \'dir\' => [
                    \'public\' => 0770,
                    \'private\' => 0770,
                ],
            ],
            \'throw\' => true,
        ],
'''
    pos = c.find(\"'disks' => [\")
    if pos != -1:
        end_brace = c.find('[', pos) + 1
        new_c = c[:end_brace] + '\n' + disk_cfg + c[end_brace:]
        with open('${PROD_DIR}/config/filesystems.php', 'w') as f:
            f.write(new_c)
" || true
fi

# ------------------------------------------------------------------------------
# 7. Ejecución exclusiva de las 2 migraciones selectivas CCD
# ------------------------------------------------------------------------------
echo "==> [Paso 5/6] Ejecutando exclusivamente las 2 migraciones selectivas CCD..."
(
  cd "$PROD_DIR"
  php artisan migrate --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --force
  php artisan migrate --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --force
)

# ------------------------------------------------------------------------------
# 8. Transferencia de PDFs y migración transaccional de datos
# ------------------------------------------------------------------------------
echo "==> [Paso 6/6] Ejecutando transferencia de PDFs y migración transaccional de datos..."

MANIFEST_DIR="${PROD_DIR}/storage/app/private/ccd/audits"
mkdir -p "$MANIFEST_DIR"
MANIFEST_PATH="${MANIFEST_DIR}/ccd-v6-produccion-manifest-${TIMESTAMP}.csv"

(
  cd "$STAGING_DIR"
  php artisan ccd:migrate-aqp-production-v6 \
    --apply \
    --authorization="$APPLY_AUTH_TOKEN" \
    --confirmation="$APPLY_CONF_TOKEN" \
    --credentials-file="$CRED_FILE" \
    --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
    --target-storage="${PROD_DIR}/storage/app/private/ccd" \
    --manifest="$MANIFEST_PATH"
)

# Limpieza de cachés de Laravel en producción
(
  cd "$PROD_DIR"
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
)

echo ""
echo "=============================================================================="
echo "  DESPLIEGUE CCD AQP V6 A PRODUCCIÓN COMPLETADO SATISFACTORIAMENTE"
echo "  Respaldo previo: $BACKUP_DIR"
echo "  Manifiesto:      $MANIFEST_PATH"
if [ -f "$MANIFEST_PATH" ]; then
  echo "  Manifiesto SHA:  $(sha256sum "$MANIFEST_PATH" | cut -d' ' -f1)"
fi
echo "=============================================================================="
exit 0
