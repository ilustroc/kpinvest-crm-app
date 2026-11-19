# KP INVEST — Informe de Migración a Producción CCD V6 (AQP4 / AQP5)

## 1. Resumen Ejecutivo y Objetivos

El presente documento describe la arquitectura, controles de integridad, plan de respaldo y el procedimiento de ejecución del desplegador y migrador **V6** para el traslado de las **10,224 asociaciones CCD AQP4/AQP5** y sus correspondientes archivos PDF privados desde el entorno de staging (`u480021566_prueba`) al entorno de producción real (`u480021566_kpinvest_bd`) de **KP INVEST**.

### Datos de Entornos

| Parámetro | Staging (Origen) | Producción (Destino) |
| :--- | :--- | :--- |
| **Host / Servidor** | Hostinger Cloud SSH | Hostinger Cloud SSH |
| **Ruta de Aplicación** | `/home/u480021566/apps/kpinvest-crm-pruebas` | `/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm` |
| **Base de Datos** | `u480021566_prueba` | `u480021566_kpinvest_bd` |
| **Repositorio Git** | `ilustroc/kpinvest-crm-app` | `ilustroc/kpinvest-crm-app` |
| **Rama de Despliegue** | `codex/ccd-aqp-v6-produccion` | `codex/ccd-aqp-v6-produccion` |

---

## 2. Línea Base Verificada y Huellas Criptográficas

Ambas bases de datos presentan una concordancia idéntica en el universo de clientes y cuentas:

- **Total `ccd_clientes`:** 275,987 registros.
- **Total `clientes_cuentas`:** 282,595 registros.
- **CCD AQP4:** 7,024 registros.
- **CCD AQP5:** 3,200 registros.
- **Total universo AQP:** 10,224 registros.

### Huellas Criptográficas SHA-256 Certificadas

Las siguientes firmas SHA-256 son estrictamente validadas por el servicio migrador antes de permitir cualquier operación:

- **Firma CCD AQP (ID, documento, PDF, cosecha):**
  `861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b`
- **Firma Cuentas AQP (ID, documento, cuenta, operación, entidad, cosecha):**
  `0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f`

### Atribución y Métodos de Verificación de las Asociaciones en Staging

Las 10,224 asociaciones confirmadas en staging se componen exactamente de:

1. **1 asociación** con método `operacion_pdf_aqp_auditada_v3` (DNI: `05378290`, CCD ID: `206263`).
2. **7,023 asociaciones AQP4** con método `operacion_pdf_aqp_auditada_v5`.
3. **3,200 asociaciones AQP5** con método `operacion_pdf_aqp_auditada_v5`.

> [!IMPORTANT]
> **Transparencia en la Evidencia y Auditoría Humana:**
> Las evidencias y métodos originales de cada asociación son preservados de forma íntegra. Únicamente la muestra controlada de **40 registros** contó con revisión visual humana directa (V4.1); los **10,184 registros restantes** provienen de verificación algorítmica documental estricta (V5/V3). El migrador no altera los métodos ni atribuye falsamente revisión humana a los 10,224 registros.

---

## 3. Arquitectura Aditiva y Diferencias de Esquema

Producción cuenta con `ccd_clientes` y `clientes_cuentas`, pero carece del módulo documental y asociativo CCD. La migración V6 es **estrictamente aditiva**:

1. **Esquema:** Se ejecutan de manera aislada y exclusiva las dos migraciones específicas mediante `--path=`:
   - `database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php` (crea `ccd_archivos` y agrega columna `ccd_archivo_id` a `ccd_clientes`).
   - `database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php` (crea `ccd_cliente_cuenta`).
2. **Archivos PDF:** Se transfieren **exclusivamente los 10,224 PDF únicos** necesarios (~2.618 GiB). **Bajo ninguna circunstancia se transfieren los 47 GB del repositorio documental**.
3. **Módulos Existentes:** No se tocan ni modifican tablas o archivos de pagos, promesas, CNA, autenticación o usuarios.
4. **Configuración de Entorno:** No se sobrescribe ni altera el archivo `.env` de producción.

---

## 4. Componentes Desarrollados

### 4.1. Servicio Central de Migración
- **Archivo:** [`app/Services/Ccd/CcdProductionMigratorService.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/app/Services/Ccd/CcdProductionMigratorService.php)
- **Responsabilidades:**
  - `preflightValidation()`: Valida conexiones, nombres de BD, conteos y firmas SHA-256.
  - `checkDiskSpace()`: Verifica disponibilidad de al menos 5 GiB libres en el disco destino.
  - `transferAndVerifyPdfs()`: Copia los 10,224 PDFs de staging a producción validando su SHA-256 físico contra el hash catalogado en `ccd_archivos.sha256`.
  - `migrateCcdArchivos()`: Inserta en lotes de 500 registros en `ccd_archivos` con idempotencia.
  - `linkCcdArchivoId()`: Vincula `ccd_clientes.ccd_archivo_id` para los 10,224 registros en lotes transaccionales.
  - `validateAssociationsPreInsert()`: Verifica unicidad, IDs existentes, no colisión con confirmaciones incompatibles previas.
  - `migrateAssociations()`: Inserta las 10,224 asociaciones en `ccd_cliente_cuenta` preservando `metodo`, `evidence` y fechas.
  - `runPostMigrationAudit()`: Auditoría de 15 puntos verificando cardinalidad, claves foráneas, ausencia de duplicados e integridad.
  - `generateManifest()`: Emite manifiesto JSON con firmas SHA-256 de todas las etapas y lo guarda en `storage/app/private/ccd/manifests/`.

### 4.2. Comando Artisan Especializado
- **Archivo:** [`app/Console/Commands/MigrateCcdAqpProductionV6.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/app/Console/Commands/MigrateCcdAqpProductionV6.php)
- **Firma:** `php artisan ccd:migrate-aqp-production-v6`
- **Opciones de Seguridad:**
  - `--dry-run`: Auditoría completa y validación sin escrituras en base de datos.
  - `--apply`: Ejecución real que requiere obligatoriamente:
    - `--authorization=AUTORIZAR_MIGRACION_PRODUCCION_CCD_V6`
    - `--confirmation=CONFIRMAR_TRANSFERENCIA_AQP_DEFINITIVA`

### 4.3. Desplegador Automatizado Hostinger
- **Archivo:** [`desplegar_ccd_aqp_produccion_v6.sh`](file:///c:/GitHub/KP-CRM-Meridian-ui/desplegar_ccd_aqp_produccion_v6.sh)
- **Características:**
  - `set -euo pipefail` y `umask 077`.
  - Respaldo automático con timestamp en `${HOME}/private/backups/ccd-v6-produccion-YYYYMMDD-HHMMSS/` tanto de tablas MySQL (`ccd_clientes`, `clientes_cuentas`) como de archivos de configuración.
  - Verificación previa de espacio libre (> 5 GiB).
  - Sincronización aditiva del código y dependencias.
  - Ejecución selectiva de migraciones (`php artisan migrate --path=...`).
  - Modo `--dry-run` para preflight completo.
  - Modo `--apply` para ejecución protegida y emisión de manifiesto.

### 4.4. Suite de Pruebas Automatizadas
- **Archivo:** [`tests/Feature/CcdAqpProductionMigrationV6Test.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/tests/Feature/CcdAqpProductionMigrationV6Test.php)
- 7 casos de prueba cubriendo:
  1. Detección y rechazo inmediato por discrepancia de hashes/conteos en preflight.
  2. Verificación de copia de PDFs con validación SHA-256 estricta.
  3. Detección de archivos físicos corruptos o con firma SHA-256 discrepante.
  4. Bloqueo de `--apply` sin tokens exactos de autorización y confirmación.
  5. Migración atómica, lote transaccional e idempotencia sin duplicación.
  6. Generación del manifiesto final auditado con huella SHA-256.
  7. Visualización autorizada y segura de PDFs en producción mediante `CcdDocumentController` con streaming inline y mitigación de IDOR.

---

## 5. Procedimiento de Ejecución en Hostinger (Paso a Paso)

### Paso 1: Conexión SSH y Preparación de Directorios de Respaldo

Conectarse al servidor Hostinger e inicializar la sesión en el entorno de producción:

```bash
# Iniciar sesión SSH en Hostinger
ssh u480021566@<hostinger-ip> -p <puerto>

# Crear directorio privado de respaldos con permisos estrictos
mkdir -p "${HOME}/private/backups"
chmod 700 "${HOME}/private/backups"
```

### Paso 2: Actualización de Código en Producción (Rama V6)

Actualizar el repositorio en la carpeta de producción sin modificar `.env`:

```bash
cd /home/u480021566/domains/impulse-go.com/public_html/plataforma-crm

# Verificar estado y asegurar que .env no sea modificado
git fetch origin codex/ccd-aqp-v6-produccion
git checkout codex/ccd-aqp-v6-produccion

# Otorgar permisos de ejecución al desplegador
chmod +x desplegar_ccd_aqp_produccion_v6.sh
```

### Paso 3: Ejecución de Simulación Integral (`--dry-run`)

Ejecutar la validación completa en modo solo lectura:

```bash
./desplegar_ccd_aqp_produccion_v6.sh --dry-run
```

**Comprobaciones de la simulación:**
- Preflight de base de datos origen y destino: conteos 275,987 en `ccd_clientes` y 282,595 en `clientes_cuentas`.
- Huellas SHA-256 verificadas idénticas en ambas bases.
- Espacio en disco verificado (> 5 GiB libres).
- Verificación de los 10,224 PDFs en staging y simulación de transferencia.
- Verificación de esquema destino (validando necesidad de las 2 migraciones).
- Resumen en pantalla: 0 escrituras efectuadas, código de salida `0`.

### Paso 4: Ejecución Autorizada en Producción (`--apply`)

Una vez validada satisfactoriamente la simulación y confirmada la autorización formal, ejecutar el despliegue real:

```bash
./desplegar_ccd_aqp_produccion_v6.sh --apply
```

**Flujo ejecutado por el script en `--apply`:**
1. Respaldo previo de tablas y configuración a `${HOME}/private/backups/ccd-v6-produccion-<timestamp>/`.
2. Ejecución de las 2 migraciones selectivas CCD (`2026_10_30_120000` y `2026_11_13_000001`).
3. Verificación y transferencia física de los 10,224 PDFs a `storage/app/private/ccd/` validando su SHA-256.
4. Inserción de 10,224 registros en `ccd_archivos` (chunks de 500).
5. Actualización de `ccd_clientes.ccd_archivo_id` (chunks de 500).
6. Inserción de 10,224 asociaciones en `ccd_cliente_cuenta` (chunks de 500).
7. Auditoría post-migración de 15 puntos.
8. Generación del archivo manifiesto `manifest-ccd-aqp-v6-<timestamp>.json`.

---

## 6. Consultas SQL de Auditoría y Verificación Post-Migración

Ejecutar las siguientes consultas directamente en la base de producción `u480021566_kpinvest_bd`:

```sql
-- 1. Validar total de asociaciones migradas por cosecha y estado
SELECT 
    cc.cosecha,
    ca.estado,
    ca.metodo,
    COUNT(*) as total
FROM ccd_cliente_cuenta ca
JOIN clientes_cuentas cc ON ca.cliente_cuenta_id = cc.id
GROUP BY cc.cosecha, ca.estado, ca.metodo
ORDER BY cc.cosecha, ca.metodo;

-- Resultado esperado:
-- CAJAAQP4 | confirmada | operacion_pdf_aqp_auditada_v3 |     1
-- CAJAAQP4 | confirmada | operacion_pdf_aqp_auditada_v5 |  7023
-- CAJAAQP5 | confirmada | operacion_pdf_aqp_auditada_v5 |  3200
-- Total: 10,224

-- 2. Validar que no existan duplicados en ccd_cliente_cuenta
SELECT ccd_cliente_id, COUNT(*) 
FROM ccd_cliente_cuenta 
GROUP BY ccd_cliente_id 
HAVING COUNT(*) > 1;
-- Resultado esperado: 0 filas

-- 3. Validar que todo ccd_archivo vinculado exista físicamente y esté catalogado
SELECT COUNT(*) 
FROM ccd_clientes c
LEFT JOIN ccd_archivos a ON c.ccd_archivo_id = a.id
WHERE c.cosecha IN ('AQP4', 'AQP5') AND a.id IS NULL;
-- Resultado esperado: 0 filas

-- 4. Validar correspondencia exacta de DNI entre ccd_clientes y clientes_cuentas
SELECT COUNT(*)
FROM ccd_cliente_cuenta ca
JOIN ccd_clientes cl ON ca.ccd_cliente_id = cl.id
JOIN clientes_cuentas cc ON ca.cliente_cuenta_id = cc.id
WHERE cl.numdoc <> cc.numdoc;
-- Resultado esperado: 0 filas
```

---

## 7. Plan de Rollback (Contingencia)

En caso de cualquier anomalía imprevista durante o después de la ejecución:

```bash
# 1. Ubicar el respaldo generado automáticamente antes de la migración:
BACKUP_DIR=$(ls -td ${HOME}/private/backups/ccd-v6-produccion-* | head -1)

# 2. Restaurar las tablas afectadas desde el respaldo SQL:
mysql -u u480021566_kpinvest_usr -p u480021566_kpinvest_bd < "${BACKUP_DIR}/target_pre_migration.sql"

# 3. Revertir las 2 migraciones CCD si fuera necesario:
php artisan migrate:rollback --step=2
```

---

## 8. Resultados de Pruebas de Regresión y Calidad

- **Pruebas de la suite V6:** 7 passed, 34 assertions ([`tests/Feature/CcdAqpProductionMigrationV6Test.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/tests/Feature/CcdAqpProductionMigrationV6Test.php)).
- **Suite completa de regresión CCD:** 120 passed, 580 assertions (0 errores, 0 fallos).
- **Suite general del CRM (Pagos, Promesas, CNA, Clientes, Auth):** 252 passed, 1,670 assertions (0 errores, 0 fallos).
- **Compilación de assets frontend (`npm run build`):** 38 módulos transformados en 5.14s sin errores.
- **Estilo de código Laravel Pint:** 100% de cumplimiento en todos los archivos nuevos y modificados.
