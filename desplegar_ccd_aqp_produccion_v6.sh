#!/usr/bin/env bash
# ==============================================================================
# KP INVEST — DESPLIEGUE CONTROLADO CCD AQP V6 A PRODUCCIÓN
# Migración completa de 10,224 asociaciones y PDFs AQP4/AQP5
#
# Base origen (staging):    u480021566_prueba
# Base destino (prod):      u480021566_kpinvest_bd
# App origen (staging):     /home/u480021566/apps/kpinvest-crm-pruebas
# App destino (prod):       /home/u480021566/domains/impulse-go.com/public_html/plataforma-crm
# ==============================================================================

set -euo pipefail
umask 077

# Constantes oficiales de validación
EXPECTED_TOTAL_CCD=275987
EXPECTED_TOTAL_CUENTAS=282595
EXPECTED_AQP_TOTAL=10224
EXPECTED_AQP4_TOTAL=7024
EXPECTED_AQP5_TOTAL=3200
EXPECTED_V3_METHOD=1
EXPECTED_V5_METHOD=10223
EXPECTED_CCD_SHA256="861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b"
EXPECTED_CUENTAS_SHA256="0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f"
MIN_FREE_SPACE_GIB=5

APPLY_AUTH_TOKEN="AUTORIZAR_MIGRACION_PRODUCCION_CCD_V6"
APPLY_CONF_TOKEN="CONFIRMAR_TRANSFERENCIA_AQP_DEFINITIVA"

# Rutas por defecto en Hostinger
STAGING_DIR="${STAGING_DIR:-/home/u480021566/apps/kpinvest-crm-pruebas}"
PROD_DIR="${PROD_DIR:-/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm}"

MODE=""
CONFIRM_FLAG=false

# ------------------------------------------------------------------------------
# Manejo de argumentos
# ------------------------------------------------------------------------------
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
      cat << 'EOF'
Uso: desplegar_ccd_aqp_produccion_v6.sh [OPCIONES]

Opciones requeridas:
  --dry-run       Ejecuta la validación integral y simula sin modificar producción.
  --apply         Ejecuta la migración autorizada a producción.

Opciones adicionales:
  --confirm       Confirma la ejecución no interactiva en modo --apply.
  -h, --help      Muestra esta ayuda.

Variables de entorno opcionales:
  STAGING_DIR     Ruta raíz de la app staging (defecto: /home/u480021566/apps/kpinvest-crm-pruebas)
  PROD_DIR        Ruta raíz de la app producción (defecto: /home/u480021566/domains/impulse-go.com/public_html/plataforma-crm)
EOF
      exit 0
      ;;
    *)
      echo "ERROR: Opción desconocida: $arg" >&2
      exit 1
      ;;
  esac
done

if [ -z "$MODE" ]; then
  echo "ERROR: Debe especificar exactamente --dry-run o --apply." >&2
  exit 1
fi

echo "=============================================================================="
echo "  KP INVEST — DESPLEGADOR CCD AQP V6 A PRODUCCIÓN"
echo "  Modo: $MODE"
echo "  Fecha: $(date -u +"%Y-%m-%d %H:%M:%SZ")"
echo "  Staging: $STAGING_DIR"
echo "  Producción: $PROD_DIR"
echo "=============================================================================="

# ------------------------------------------------------------------------------
# 1. Comprobación de binarios requeridos
# ------------------------------------------------------------------------------
for bin in php mysql mysqldump sha256sum df find cp tar; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    echo "ERROR: Binario requerido no encontrado en PATH: $bin" >&2
    exit 1
  fi
done

# ------------------------------------------------------------------------------
# 2. Extracción segura de credenciales de .env
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

run_src_sql() {
  local sql="$1"
  MYSQL_PWD="${SRC_DB_PASS}" mysql -h "${SRC_DB_HOST}" -P "${SRC_DB_PORT}" -u "${SRC_DB_USER}" -N -B "${SRC_DB_NAME}" -e "$sql"
}

run_tgt_sql() {
  local sql="$1"
  MYSQL_PWD="${TGT_DB_PASS}" mysql -h "${TGT_DB_HOST}" -P "${TGT_DB_PORT}" -u "${TGT_DB_USER}" -N -B "${TGT_DB_NAME}" -e "$sql"
}

# ------------------------------------------------------------------------------
# 3. Preflight: Validación de firmas y conteos
# ------------------------------------------------------------------------------
echo "==> [Paso 1/7] Ejecutando validaciones preflight..."

# 3.1 Conteos ccd_clientes
SRC_CCD_COUNT=$(run_src_sql "SELECT COUNT(*) FROM ccd_clientes;")
TGT_CCD_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes;")
echo "  ccd_clientes total: origen=$SRC_CCD_COUNT | destino=$TGT_CCD_COUNT (esperado: $EXPECTED_TOTAL_CCD)"
if [ "$SRC_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ] || [ "$TGT_CCD_COUNT" -ne "$EXPECTED_TOTAL_CCD" ]; then
  echo "ERROR: Conteo de ccd_clientes no coincide con el valor certificado." >&2
  exit 1
fi

# 3.2 Conteos clientes_cuentas
SRC_CTA_COUNT=$(run_src_sql "SELECT COUNT(*) FROM clientes_cuentas;")
TGT_CTA_COUNT=$(run_tgt_sql "SELECT COUNT(*) FROM clientes_cuentas;")
echo "  clientes_cuentas total: origen=$SRC_CTA_COUNT | destino=$TGT_CTA_COUNT (esperado: $EXPECTED_TOTAL_CUENTAS)"
if [ "$SRC_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ] || [ "$TGT_CTA_COUNT" -ne "$EXPECTED_TOTAL_CUENTAS" ]; then
  echo "ERROR: Conteo de clientes_cuentas no coincide con el valor certificado." >&2
  exit 1
fi

# 3.3 Conteos AQP4 / AQP5
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

# 3.4 Firmas SHA-256
echo "  Calculando huellas SHA-256 para verificación cruzada..."
SRC_CCD_HASH=$(run_src_sql "SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)
TGT_CCD_HASH=$(run_tgt_sql "SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)

if [ "$SRC_CCD_HASH" != "$TGT_CCD_HASH" ]; then
  echo "ERROR: Huellas SHA-256 de ccd_clientes AQP difieren entre origen y destino." >&2
  exit 1
fi
echo "  Firma CCD AQP cruzada: $SRC_CCD_HASH (Coincidencia exacta)"

SRC_CTA_HASH=$(run_src_sql "SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)
TGT_CTA_HASH=$(run_tgt_sql "SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5') ORDER BY id;" | sha256sum | cut -d' ' -f1)

if [ "$SRC_CTA_HASH" != "$TGT_CTA_HASH" ]; then
  echo "ERROR: Huellas SHA-256 de clientes_cuentas AQP difieren entre origen y destino." >&2
  exit 1
fi
echo "  Firma Cuentas AQP cruzada: $SRC_CTA_HASH (Coincidencia exacta)"

# 3.5 Asociaciones de Staging
STAGING_ASSOC=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada';")
STAGING_V3=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v3';")
STAGING_V5=$(run_src_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = 'operacion_pdf_aqp_auditada_v5';")

if [ "$STAGING_ASSOC" -ne "$EXPECTED_AQP_TOTAL" ] || [ "$STAGING_V3" -ne "$EXPECTED_V3_METHOD" ] || [ "$STAGING_V5" -ne "$EXPECTED_V5_METHOD" ]; then
  echo "ERROR: Staging no presenta la distribución esperada (Total: $STAGING_ASSOC, V3: $STAGING_V3, V5: $STAGING_V5)." >&2
  exit 1
fi
echo "  Asociaciones en Staging: $STAGING_ASSOC (1 v3, 10,223 v5) (Conforme)"

# ------------------------------------------------------------------------------
# 4. Comprobación de espacio en disco
# ------------------------------------------------------------------------------
echo "==> [Paso 2/7] Verificando espacio disponible en disco..."
PROD_STORAGE="${PROD_DIR}/storage/app/private/ccd"
mkdir -p "$PROD_STORAGE"

FREE_SPACE_KB=$(df -k "$PROD_STORAGE" | awk 'NR==2 {print $4}')
FREE_SPACE_GIB=$(( FREE_SPACE_KB / 1024 / 1024 ))
echo "  Espacio libre en destino: ${FREE_SPACE_GIB} GiB (Requerido: >= ${MIN_FREE_SPACE_GIB} GiB)"

if [ "$FREE_SPACE_GIB" -lt "$MIN_FREE_SPACE_GIB" ]; then
  echo "ERROR: Espacio insuficiente en disco para alojar los 2.618 GiB de PDFs privados." >&2
  exit 1
fi

# ------------------------------------------------------------------------------
# En modo DRY-RUN, ejecutamos la simulación completa sin escrituras y salimos
# ------------------------------------------------------------------------------
if [ "$MODE" = "dry-run" ]; then
  echo "==> [DRY-RUN] Ejecutando simulación integral con ccd:migrate-aqp-production-v6..."
  (
    cd "$STAGING_DIR"
    php artisan ccd:migrate-aqp-production-v6 \
      --dry-run \
      --source-host="$SRC_DB_HOST" \
      --source-port="$SRC_DB_PORT" \
      --source-db="$SRC_DB_NAME" \
      --source-user="$SRC_DB_USER" \
      --source-password="$SRC_DB_PASS" \
      --target-host="$TGT_DB_HOST" \
      --target-port="$TGT_DB_PORT" \
      --target-db="$TGT_DB_NAME" \
      --target-user="$TGT_DB_USER" \
      --target-password="$TGT_DB_PASS" \
      --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
      --target-storage="${PROD_DIR}/storage/app/private/ccd"
  )
  echo "=============================================================================="
  echo "  DRY-RUN CONFORME: Todas las validaciones pasaron satisfactoriamente."
  echo "  No se realizaron escrituras en la base de producción ni en disco."
  echo "=============================================================================="
  exit 0
fi

# ==============================================================================
# SECCIÓN APPLY: Ejecución con escrituras
# ==============================================================================

if [ "$CONFIRM_FLAG" = false ]; then
  echo ""
  echo "ADVERTENCIA: Está a punto de ejecutar la MIGRACIÓN A PRODUCCIÓN."
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

echo "==> [Paso 3/7] Realizando respaldo previo de tablas y configuración en ${BACKUP_DIR}..."

# Respaldo de BD destino
PROD_DUMP="${BACKUP_DIR}/prod_pre_migracion_${TIMESTAMP}.sql"
MYSQL_PWD="${TGT_DB_PASS}" mysqldump \
  -h "${TGT_DB_HOST}" -P "${TGT_DB_PORT}" -u "${TGT_DB_USER}" \
  --single-transaction --quick --skip-lock-tables --no-tablespaces \
  "${TGT_DB_NAME}" ccd_clientes clientes_cuentas migrations > "${PROD_DUMP}"

chmod 600 "${PROD_DUMP}"
sha256sum "${PROD_DUMP}" | tee "${PROD_DUMP}.sha256"
test -s "${PROD_DUMP}"
echo "  Respaldo de BD generado exitosamente."

# Respaldo de archivos de configuración
cp "${PROD_DIR}/.env" "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
chmod 600 "${BACKUP_DIR}/prod_env_${TIMESTAMP}.bak"
if [ -f "${PROD_DIR}/config/filesystems.php" ]; then
  cp "${PROD_DIR}/config/filesystems.php" "${BACKUP_DIR}/prod_filesystems_${TIMESTAMP}.bak"
fi
echo "  Respaldo de configuración generado exitosamente."

# ------------------------------------------------------------------------------
# 5. Instalación aditiva del código CCD en producción
# ------------------------------------------------------------------------------
echo "==> [Paso 4/7] Sincronizando código y componentes del módulo CCD en producción..."

# Migraciones
mkdir -p "${PROD_DIR}/database/migrations"
cp -n "${STAGING_DIR}/database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php" "${PROD_DIR}/database/migrations/"
cp -n "${STAGING_DIR}/database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php" "${PROD_DIR}/database/migrations/"

# Configuración
cp -n "${STAGING_DIR}/config/ccd.php" "${PROD_DIR}/config/"

# Modelos
mkdir -p "${PROD_DIR}/app/Models"
cp "${STAGING_DIR}/app/Models/CcdArchivo.php" "${PROD_DIR}/app/Models/"
cp "${STAGING_DIR}/app/Models/CcdCliente.php" "${PROD_DIR}/app/Models/"
cp "${STAGING_DIR}/app/Models/CcdClienteCuenta.php" "${PROD_DIR}/app/Models/"
cp "${STAGING_DIR}/app/Models/ClienteCuenta.php" "${PROD_DIR}/app/Models/"

# Controladores y Rutas
mkdir -p "${PROD_DIR}/app/Http/Controllers"
cp "${STAGING_DIR}/app/Http/Controllers/CcdDocumentController.php" "${PROD_DIR}/app/Http/Controllers/"
cp "${STAGING_DIR}/routes/web/clientes.php" "${PROD_DIR}/routes/web/"

# Vistas y Servicios
mkdir -p "${PROD_DIR}/app/Services/Cliente"
cp "${STAGING_DIR}/app/Services/Cliente/ClienteProfileService.php" "${PROD_DIR}/app/Services/Cliente/"
mkdir -p "${PROD_DIR}/resources/views/clientes"
cp "${STAGING_DIR}/resources/views/clientes/show.blade.php" "${PROD_DIR}/resources/views/clientes/"
if [ -f "${STAGING_DIR}/resources/views/components/clientes/documents-panel.blade.php" ]; then
  mkdir -p "${PROD_DIR}/resources/views/components/clientes"
  cp "${STAGING_DIR}/resources/views/components/clientes/documents-panel.blade.php" "${PROD_DIR}/resources/views/components/clientes/"
fi

# Configuración de disco privado en filesystems.php si falta
if ! grep -q "ccd-private" "${PROD_DIR}/config/filesystems.php"; then
  echo "  Añadiendo configuración de disco ccd-private a filesystems.php..."
  # Se inyecta antes de 'public' o al final de disks
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

# Limpieza de cachés de Laravel
(
  cd "$PROD_DIR"
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
)
echo "  Componentes instalados y cachés limpiadas."

# ------------------------------------------------------------------------------
# 6. Ejecución de las 2 migraciones en producción
# ------------------------------------------------------------------------------
echo "==> [Paso 5/7] Ejecutando exclusivamente las 2 migraciones CCD en producción..."

(
  cd "$PROD_DIR"
  php artisan migrate --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --force
  php artisan migrate --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --force
)

# Validar existencia de tablas en producción
run_tgt_sql "DESCRIBE ccd_archivos;" >/dev/null
run_tgt_sql "DESCRIBE ccd_cliente_cuenta;" >/dev/null
run_tgt_sql "SHOW COLUMNS FROM ccd_clientes LIKE 'ccd_archivo_id';" | grep -q "ccd_archivo_id"
echo "  Esquema de BD actualizado con éxito."

# ------------------------------------------------------------------------------
# 7. Migración completa de archivos y asociaciones
# ------------------------------------------------------------------------------
echo "==> [Paso 6/7] Ejecutando transferencia de PDFs y migración transaccional de datos..."

MANIFEST_PATH="${PROD_DIR}/storage/app/private/ccd/audits/ccd-v6-produccion-manifest-${TIMESTAMP}.csv"
mkdir -p "$(dirname "$MANIFEST_PATH")"

(
  cd "$STAGING_DIR"
  php artisan ccd:migrate-aqp-production-v6 \
    --apply \
    --authorization="$APPLY_AUTH_TOKEN" \
    --confirmation="$APPLY_CONF_TOKEN" \
    --source-host="$SRC_DB_HOST" \
    --source-port="$SRC_DB_PORT" \
    --source-db="$SRC_DB_NAME" \
    --source-user="$SRC_DB_USER" \
    --source-password="$SRC_DB_PASS" \
    --target-host="$TGT_DB_HOST" \
    --target-port="$TGT_DB_PORT" \
    --target-db="$TGT_DB_NAME" \
    --target-user="$TGT_DB_USER" \
    --target-password="$TGT_DB_PASS" \
    --source-storage="${STAGING_DIR}/storage/app/private/ccd" \
    --target-storage="${PROD_DIR}/storage/app/private/ccd" \
    --manifest="$MANIFEST_PATH"
)

# ------------------------------------------------------------------------------
# 8. Auditoría final post-despliegue
# ------------------------------------------------------------------------------
echo "==> [Paso 7/7] Realizando comprobaciones directas en base de datos de producción..."

FINAL_ARCHIVOS=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_archivos;")
FINAL_LINKED=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_clientes WHERE ccd_archivo_id IS NOT NULL;")
FINAL_ASSOC=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta WHERE estado = 'confirmada';")
FINAL_AQP4=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha = 'AQP4' AND cca.estado = 'confirmada';")
FINAL_AQP5=$(run_tgt_sql "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha = 'AQP5' AND cca.estado = 'confirmada';")

echo "------------------------------------------------------------------------------"
echo "  RESULTADOS FINALES EN PRODUCCIÓN:"
echo "  ccd_archivos:                $FINAL_ARCHIVOS / $EXPECTED_AQP_TOTAL"
echo "  ccd_clientes vinculados:     $FINAL_LINKED / $EXPECTED_AQP_TOTAL"
echo "  ccd_cliente_cuenta total:    $FINAL_ASSOC / $EXPECTED_AQP_TOTAL"
echo "  Asociaciones AQP4:           $FINAL_AQP4 / $EXPECTED_AQP4_TOTAL"
echo "  Asociaciones AQP5:           $FINAL_AQP5 / $EXPECTED_AQP5_TOTAL"
echo "  Manifiesto:                  $MANIFEST_PATH"
if [ -f "$MANIFEST_PATH" ]; then
  echo "  Manifiesto SHA-256:          $(sha256sum "$MANIFEST_PATH" | cut -d' ' -f1)"
fi
echo "------------------------------------------------------------------------------"

if [ "$FINAL_ARCHIVOS" -ne "$EXPECTED_AQP_TOTAL" ] || \
   [ "$FINAL_LINKED" -ne "$EXPECTED_AQP_TOTAL" ] || \
   [ "$FINAL_ASSOC" -ne "$EXPECTED_AQP_TOTAL" ] || \
   [ "$FINAL_AQP4" -ne "$EXPECTED_AQP4_TOTAL" ] || \
   [ "$FINAL_AQP5" -ne "$EXPECTED_AQP5_TOTAL" ]; then
  echo "ERROR CRÍTICO: Los conteos finales en producción no coinciden con los esperados." >&2
  exit 1
fi

echo "=============================================================================="
echo "  DESPLIEGUE CCD AQP V6 A PRODUCCIÓN COMPLETADO SATISFACTORIAMENTE"
echo "=============================================================================="
exit 0
