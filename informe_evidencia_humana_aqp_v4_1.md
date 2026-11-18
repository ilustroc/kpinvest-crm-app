# KP INVEST — CCD AQP V4.1: evidencia humana obligatoria

## Resultado

V4.1 vincula el mecanismo controlado de AQP4/AQP5 con el archivo privado
aprobado:

```text
ccd-aqp-revision-humana-20261118-211134-aprobada.csv
SHA-256: 2ebbfa12df1e0dad537c8fdb57d60075c5b4cb995b97d71d56b4e67c9cb80942
```

No se ejecutó `--apply`, no se activó ninguna variable de autorización y no se
modificaron asociaciones. La vía genérica `ccd:associate-verified` continúa
bloqueando la evidencia AQP.

## Controles incorporados

Antes de seleccionar un CCD, `ccd:associate-aqp-audited` ahora exige:

1. SHA-256 físico exacto del CSV humano aprobado.
2. Cuarenta CCD únicas: 20 AQP4 y 20 AQP5.
3. Cinco CCD en cada combinación AQP4/AQP5 y Q1/Q2/Q3/Q4.
4. `identidad_revisada=SI`, `operacion_revisada=SI` y
   `resultado_manual=APROBADO` en cada fila.
5. Observación vacía o inequívocamente neutral (`SIN OBSERVACIONES`, `NINGUNA`,
   `OK` o la observación conforme oficial `Revision visual declarada conforme por el responsable - 2026-11-18`).
   Cualquier otra observación detiene el proceso.
6. Existencia del CCD en uno de los doce CSV V3 proporcionados.
7. Coincidencia exacta de cosecha y de la huella SHA-256 del CSV V3 que contiene
   ese CCD.

La nueva opción `--ccd-id` selecciona exactamente un documento. Durante el
piloto, `--apply` exige esta opción y rechaza CCD ajenas a los 40 registros
aprobados.

La aplicación conserva además los controles V4: base exacta de staging,
variables de entorno, dos textos de autorización, PDF disponible, SHA-256 del
PDF, operación exacta, cuenta única, DNI, código de cliente AQP, asociaciones
preexistentes, transacción, bloqueo e idempotencia.

La huella humana se escribe tanto en el CSV privado de ejecución como en la
evidencia de una futura asociación. El CSV no incluye DNI, nombre ni operación.
Incluso una invocación interna directa de `applyAqpAudited()` rechaza una huella
humana diferente de la aprobada.

## Archivos

- `app/Services/Ccd/CcdAqpHumanReviewReader.php`: lector y validador estricto
  del CSV humano.
- `app/Console/Commands/AssociateAuditedAqpCcd.php`: integración de la revisión,
  `--ccd-id` y restricción del piloto a un único registro.
- `app/Services/Ccd/CcdAqpControlledAssociationService.php`: propagación y
  control de la evidencia humana.
- `app/Services/Ccd/CcdVerifiedAssociationService.php`: validación final de la
  huella aprobada y trazabilidad persistente.
- `tests/Feature/CcdAqpControlledAssociationTest.php`: pruebas V4.1.

No se añadió una migración ni se modificaron el controlador CCD, ZIP V12, PDF,
CNA, pagos, promesas o reglas financieras.

## Preparación segura de los doce CSV V3 en Hostinger

Los nombres reales no forman parte del repositorio. Este bloque localiza los
doce archivos por las huellas registradas en el CSV humano; falla si una huella
no tiene exactamente un archivo físico correspondiente. Debe ejecutarse en una
misma sesión SSH antes de los comandos posteriores:

```bash
cd /home/u480021566/apps/kpinvest-crm-pruebas
set -euo pipefail
umask 077

AUDIT_DIR="storage/app/private/ccd/audits"
HUMAN_REVIEW="ccd-aqp-revision-humana-20261118-211134-aprobada.csv"
HUMAN_SHA="2ebbfa12df1e0dad537c8fdb57d60075c5b4cb995b97d71d56b4e67c9cb80942"
HUMAN_PATH="${AUDIT_DIR}/${HUMAN_REVIEW}"

printf '%s  %s\n' "${HUMAN_SHA}" "${HUMAN_PATH}" | sha256sum --check --strict

mapfile -t REQUIRED_V3_HASHES < <(
  tail -n +2 "${HUMAN_PATH}" |
  cut -d, -f4 |
  tr -d '"\r ' |
  sort -u
)

if [ "${#REQUIRED_V3_HASHES[@]}" -ne 12 ]; then
  echo "ERROR: la revisión humana no referencia exactamente 12 huellas V3" >&2
  exit 1
fi

V3_ARGS=()
for digest in "${REQUIRED_V3_HASHES[@]}"; do
  matches=()
  while IFS= read -r -d '' file; do
    if [ "$(sha256sum "${file}" | cut -d' ' -f1)" = "${digest}" ]; then
      matches+=("${file}")
    fi
  done < <(find "${AUDIT_DIR}" -maxdepth 1 -type f -name '*.csv' ! -name "${HUMAN_REVIEW}" -print0)

  if [ "${#matches[@]}" -ne 1 ]; then
    echo "ERROR: la huella V3 ${digest} tiene ${#matches[@]} archivos físicos" >&2
    exit 1
  fi

  V3_ARGS+=("--input=$(basename "${matches[0]}")")
done
```

## Verificación de integridad de los 40 registros

Este comando es `dry-run`. Lee los 12 CSV, valida el archivo humano completo y
revalida los 40 PDF, operaciones e identidades sin escribir asociaciones:

```bash
php artisan ccd:associate-aqp-audited \
  "${V3_ARGS[@]}" \
  --human-review="${HUMAN_REVIEW}" \
  --human-review-sha256="${HUMAN_SHA}" \
  --limit=40 \
  --output="ccd-aqp-v4-1-integridad-40-$(date +%Y%m%d-%H%M%S).csv"
```

El resultado esperado es: 40 procesadas, cero fallos de integridad y ninguna
escritura en `ccd_cliente_cuenta`.

## Dry-run de un CCD aprobado

Reemplazar `CCD_ID_APROBADO` por un ID presente en el archivo aprobado:

```bash
CCD_ID="CCD_ID_APROBADO"
case "${CCD_ID}" in (*[!0-9]*|'') echo 'CCD_ID inválido' >&2; exit 1;; esac

php artisan ccd:associate-aqp-audited \
  "${V3_ARGS[@]}" \
  --human-review="${HUMAN_REVIEW}" \
  --human-review-sha256="${HUMAN_SHA}" \
  --ccd-id="${CCD_ID}" \
  --limit=1 \
  --output="ccd-aqp-v4-1-dry-run-${CCD_ID}-$(date +%Y%m%d-%H%M%S).csv"
```

## Respaldo previo a una futura aplicación

No ejecutar una aplicación sin un respaldo íntegro. Las credenciales deben
proporcionarse de forma interactiva o mediante un archivo de opciones privado;
no deben escribirse en el historial de la terminal:

```bash
BACKUP_DIR="${HOME}/private/backups/ccd-aqp-v4-1"
BACKUP_FILE="${BACKUP_DIR}/ccd_cliente_cuenta-$(date +%Y%m%d-%H%M%S).sql"
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

mysqldump \
  --single-transaction \
  --quick \
  --skip-lock-tables \
  --no-tablespaces \
  -h DB_HOST_REAL \
  -P 3306 \
  -u DB_USERNAME_REAL \
  -p \
  u480021566_prueba ccd_cliente_cuenta > "${BACKUP_FILE}"

chmod 600 "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" | tee "${BACKUP_FILE}.sha256"
test -s "${BACKUP_FILE}"
```

`DB_HOST_REAL` y `DB_USERNAME_REAL` deben sustituirse por los valores de la
aplicación de pruebas. El `-p` solicita la contraseña sin incluirla en el
comando.

## Aplicación futura de un único CCD

**Comando reservado: no fue ejecutado en V4.1.** Sólo puede usarse después de
revisar el dry-run, comprobar el respaldo y autorizar expresamente el piloto.
Además, en ese momento deben habilitarse temporalmente en staging las tres
variables; continúan en `false` por defecto en el código:

```dotenv
CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true
CCD_AQP_ASSOCIATION_APPLY_ENABLED=true
CCD_AQP_HUMAN_REVIEW_APPROVED=true
```

Después de actualizar esas variables y ejecutar `php artisan config:clear`, el
comando futuro para el mismo `CCD_ID` es:

```bash
php artisan ccd:associate-aqp-audited \
  "${V3_ARGS[@]}" \
  --human-review="${HUMAN_REVIEW}" \
  --human-review-sha256="${HUMAN_SHA}" \
  --ccd-id="${CCD_ID}" \
  --limit=1 \
  --apply \
  --authorization=CONFIRMAR_ASOCIACION_AQP_AUDITADA \
  --confirmation=REVALIDAR_PDF_Y_CONFIRMAR_AQP \
  --output="ccd-aqp-v4-1-aplicacion-${CCD_ID}-$(date +%Y%m%d-%H%M%S).csv"
```

Al terminar una futura ventana autorizada, las tres variables deben volver a
`false` y debe ejecutarse `php artisan config:clear`.

## Verificación posterior en MySQL

La consulta es de solo lectura y no muestra DNI, nombre ni operación:

```bash
mysql \
  -h DB_HOST_REAL \
  -P 3306 \
  -u DB_USERNAME_REAL \
  -p \
  --database=u480021566_prueba \
  --execute="SELECT ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, confirmado_por_user_id, confirmado_at, SHA2(evidencia, 256) AS evidencia_sha256 FROM ccd_cliente_cuenta WHERE ccd_cliente_id = ${CCD_ID} ORDER BY id;"
```

Para el piloto, debe existir como máximo una asociación `confirmada`; el método
esperado es `operacion_pdf_aqp_auditada_v3`. La evidencia debe contener las
huellas V3 y humana, verificables mediante su SHA-256 sin imprimirla completa.

## Pruebas ejecutadas

```text
Prueba focalizada V4.1: 16 aprobadas, 108 aserciones.
Regresión CCD: 113 aprobadas, 546 aserciones.
Suite Laravel: 241 aprobadas, 2 omitidas, 1622 aserciones.
npm run build: correcto (Vite 5.4.21, 38 módulos transformados).
git diff --check: correcto.
Pint --test en archivos modificados: correcto.
```

Se validó además la corrección puntual para la observación oficial
`Revision visual declarada conforme por el responsable - 2026-11-18` y el rechazo
estricto de observaciones arbitrarias, así como la corrección de la ruta física
`AUDIT_DIR` a `storage/app/private/ccd/audits`.

Las dos pruebas omitidas dependen de datos locales opcionales y no corresponden
al módulo CCD. Los mensajes de error observados durante la suite pertenecen a
casos negativos deliberados que terminaron aprobados.

## Riesgos restantes

- Los nombres reales de los doce CSV V3 sólo existen en el servidor. El bloque
  de preparación los resuelve por huella, sin confiar en el nombre.
- La validez documental se vuelve a comprobar en cada ejecución; un PDF ausente
  o cuya huella cambió detiene el lote.
- La aprobación de 40 documentos habilita únicamente el piloto individual. No
  habilita una aplicación masiva de los 10,224 CCD.
- La reversión de una futura escritura no es automática. Debe partir del
  respaldo y de una revisión explícita; V4.1 no elimina asociaciones.
