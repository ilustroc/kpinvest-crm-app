# KP INVEST — Informe de Migración a Producción CCD V6 (AQP4 / AQP5) — Versión 6.1

## 1. Resumen Ejecutivo y Objetivos

El presente documento describe la arquitectura técnica, controles de integridad, protección de credenciales, plan de respaldo y el procedimiento de ejecución del desplegador y migrador **V6.1** para el traslado de las **10,224 asociaciones CCD AQP4/AQP5** y sus archivos PDF privados correspondientes desde el entorno de staging (`u480021566_prueba`) al entorno de producción real (`u480021566_kpinvest_bd`) de **KP INVEST**.

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

- **Firma CCD AQP:** `861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b`
- **Firma Cuentas AQP:** `0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f`

### Método de Serialización para el Cálculo de Firmas

Para garantizar consistencia matemática exacta con la auditoría original:
1. **Firma CCD:** Consulta `SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id ASC;`.
2. **Firma Cuentas:** Consulta `SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5') ORDER BY id ASC;`.
3. **Formato:** Cada fila se serializa uniendo sus campos con tabulación horizontal (`\t`, ASCII `0x09`) y finalizando con salto de línea Unix (`\n`, ASCII `0x0A`), sin incluir cabeceras de columnas (equivalente exacto a `mysql -N -B` canalizado hacia `sha256sum`).
4. **Codificación:** UTF-8 binario.

### Atribución y Métodos de Verificación de las Asociaciones en Staging

Las 10,224 asociaciones confirmadas en staging se componen exactamente de:

1. **1 asociación** con método `operacion_pdf_aqp_auditada_v3`:
   - Corresponde al CCD del piloto confirmado **ID `153552`** (DNI `05378290`).
2. **7,023 asociaciones AQP4** con método `operacion_pdf_aqp_auditada_v5`.
3. **3,200 asociaciones AQP5** con método `operacion_pdf_aqp_auditada_v5`.

> [!IMPORTANT]
> **Transparencia en la Evidencia y Auditoría Humana:**
> Las evidencias y métodos originales de cada asociación son preservados de forma íntegra. Únicamente la muestra controlada de **40 registros** contó con revisión visual humana directa (V4.1); los **10,184 registros restantes** provienen de verificación algorítmica documental estricta (V5/V3). El migrador no altera los métodos ni atribuye falsamente revisión humana a los 10,224 registros.

---

## 3. Arquitectura Aditiva y Mejoras de Seguridad V6.1

### 3.1. Dry-Run Real Sin Escrituras
- Producción actualmente carece de `ccd_archivos`, `ccd_cliente_cuenta` y de la columna `ccd_clientes.ccd_archivo_id`.
- En modo `--dry-run`, el migrador **no exige que estas tablas existan en destino**.
- El dry-run inspecciona el esquema de producción, comprueba las 2 migraciones pendientes, verifica la integridad de los 10,224 PDFs en almacenamiento origen, verifica la disponibilidad de espacio en disco en el directorio existente sin crear carpetas, y comprueba las referencias cruzadas.
- **Cero escrituras:** no crea directorios en destino, no ejecuta migraciones, no modifica código y no genera manifiestos en producción.

### 3.2. Integración Aditiva de Archivos sin Sobrescritura Indiscriminada
- Se eliminó el reemplazo ciego con `cp`.
- La sincronización analiza cada archivo del módulo CCD de forma aditiva:
  - Archivos nuevos: se instalan con permisos 0644.
  - Archivos idénticos: se conservan intactos.
  - Archivos existentes que difieren: se realiza un respaldo individual previo en `${BACKUP_DIR}/modified_files/<ruta_relativa>` antes de actualizar.
- Se preservan de forma garantizada la autenticación y los módulos de clientes, pagos, promesas y CNA.

### 3.3. Rechazo de Conflictos y Eliminación de `ON DUPLICATE KEY UPDATE`
- Se eliminó completamente la sobrescritura silenciosa mediante `ON DUPLICATE KEY UPDATE`.
- **Política de Inserción e Idempotencia:**
  - Si un registro ya existe en destino y todos sus campos de negocio son idénticos: se considera **idempotente** y se continúa sin duplicar.
  - Si un registro ya existe en destino pero difiere en cualquier campo de negocio: se **detiene inmediatamente la ejecución**, se hace rollback de la transacción y se registra el conflicto de integridad sin modificar la fila preexistente.
  - Esta política aplica uniformemente a `ccd_archivos`, `ccd_clientes.ccd_archivo_id` y `ccd_cliente_cuenta`.

### 3.4. Protección de Credenciales MySQL
- Se eliminó el paso de contraseñas mediante argumentos CLI (`--source-password`, `--target-password`) que puedan exponerse en la tabla de procesos (`ps aux`).
- Se implementó soporte para un archivo JSON de credenciales privado con permisos restrictivos (`0600`) o variables de entorno del proceso (`CCD_SOURCE_DB_PASSWORD`, `CCD_TARGET_DB_PASSWORD`).
- El desplegador genera dinámicamente un archivo temporal privado en `${HOME}/private/.ccd_credentials/`, asegurado con `umask 077` y `chmod 600`, el cual es destruido automáticamente al término o interrupción del script mediante un `trap`.

---

## 4. Componentes y Scripts Disponibles

- [`app/Services/Ccd/CcdProductionMigratorService.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/app/Services/Ccd/CcdProductionMigratorService.php): Motor central de migración, inspección de esquema, validación de huellas y auditoría.
- [`app/Console/Commands/MigrateCcdAqpProductionV6.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/app/Console/Commands/MigrateCcdAqpProductionV6.php): Comando Artisan `ccd:migrate-aqp-production-v6` con soporte para `--credentials-file`, `--dry-run`, `--apply` y tokens de seguridad.
- [`desplegar_ccd_aqp_produccion_v6.sh`](file:///c:/GitHub/KP-CRM-Meridian-ui/desplegar_ccd_aqp_produccion_v6.sh): Desplegador para Hostinger con respaldos previos, sincronización aditiva, migraciones selectivas y auditoría post-migración.
- [`revertir_ccd_aqp_produccion_v6.sh`](file:///c:/GitHub/KP-CRM-Meridian-ui/revertir_ccd_aqp_produccion_v6.sh): Reversor específico para deshacer de forma segura las dos migraciones CCD y restaurar archivos respaldados.
- [`tests/Feature/CcdAqpProductionMigrationV6Test.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/tests/Feature/CcdAqpProductionMigrationV6Test.php): Suite de pruebas automatizadas que valida dry-run en destino limpio, migraciones selectivas, rechazo de colisiones, idempotencia y streaming PDF.

---

## 5. Procedimiento de Ejecución en Hostinger (Paso a Paso)

### Paso 1: Conexión SSH y Descarga de la Rama V6.1

```bash
# Conectar por SSH
ssh u480021566@<hostinger-ip> -p <puerto>

cd /home/u480021566/domains/impulse-go.com/public_html/plataforma-crm

# Descargar la rama actualizada
git fetch origin codex/ccd-aqp-v6-produccion
git checkout codex/ccd-aqp-v6-produccion

# Otorgar permisos de ejecución a los scripts
chmod +x desplegar_ccd_aqp_produccion_v6.sh revertir_ccd_aqp_produccion_v6.sh
```

### Paso 2: Ejecución de Simulación Integral (`--dry-run`)

```bash
./desplegar_ccd_aqp_produccion_v6.sh --dry-run
```

**Verificaciones realizadas en `--dry-run`:**
- Comprobación de binarios del sistema.
- Generación de credenciales temporales en archivo privado 0600 (sin exposición en `ps`).
- Preflight: 275,987 en `ccd_clientes` y 282,595 en `clientes_cuentas` en ambas bases.
- Huellas SHA-256 idénticas en ambas bases de datos.
- Espacio en disco destino verificado (> 5 GiB libres).
- Detección de las 2 migraciones pendientes en producción.
- Verificación de los 10,224 PDFs en almacenamiento origen sin tocar destino.
- Resultado: **0 escrituras efectuadas, código de salida 0**.

### Paso 3: Ejecución Autorizada en Producción (`--apply`)

Una vez validada satisfactoriamente la simulación:

```bash
./desplegar_ccd_aqp_produccion_v6.sh --apply
```

**Flujo ejecutado en `--apply`:**
1. Respaldo previo de tablas afectadas en `${HOME}/private/backups/ccd-v6-produccion-<timestamp>/`.
2. Sincronización aditiva de archivos con respaldo individual de cualquier archivo preexistente modificado en `${BACKUP_DIR}/modified_files/`.
3. Inyección aditiva del disco `ccd-private` en `config/filesystems.php`.
4. Ejecución exclusiva de las 2 migraciones selectivas CCD:
   - `2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php`
   - `2026_11_13_000001_create_ccd_cliente_cuenta_table.php`
5. Transferencia física y validación SHA-256 de los 10,224 PDFs AQP (~2.618 GiB).
6. Inserción transaccional por lotes (500) en `ccd_archivos`, `ccd_clientes.ccd_archivo_id` y `ccd_cliente_cuenta` con detección estricta de conflictos.
7. Auditoría post-migración y emisión de manifiesto firmado en `storage/app/private/ccd/audits/`.

---

## 6. Consultas SQL de Auditoría y Verificación Post-Migración

Ejecutar las siguientes consultas directamente en la base de producción `u480021566_kpinvest_bd`:

```sql
-- 1. Validar total de asociaciones migradas por método de verificación y cosecha
SELECT 
    cc.cosecha,
    ca.estado,
    ca.metodo_verificacion,
    COUNT(*) AS total
FROM ccd_cliente_cuenta ca
JOIN clientes_cuentas cc ON ca.cliente_cuenta_id = cc.id
GROUP BY cc.cosecha, ca.estado, ca.metodo_verificacion
ORDER BY cc.cosecha, ca.metodo_verificacion;

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

-- 3. Validar que todo ccd_archivo vinculado exista y esté catalogado
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

## 7. Plan de Rollback Específico

Se descartó la recomendación genérica `php artisan migrate:rollback --step=2` debido al riesgo de deshacer migraciones no deseadas o restaurar volcados completos que sobreescribirían nuevas operaciones concurrentes de clientes o pagos en producción.

En caso de requerir reversión:

```bash
# Ejecutar la reversión específica de las 2 migraciones CCD
./revertir_ccd_aqp_produccion_v6.sh --confirm

# (Opcional) Si se desea restaurar archivos modificados desde un respaldo previo:
./revertir_ccd_aqp_produccion_v6.sh --confirm --restore-files="${HOME}/private/backups/ccd-v6-produccion-<timestamp>"
```

El script verifica antes de la reversión que no existan datos de cosechas posteriores en `ccd_cliente_cuenta`, revierte las dos migraciones en orden de dependencia inverso (`ccd_cliente_cuenta` primero y luego `ccd_archivos`), restaura los archivos modificados si se solicita, y limpia las cachés de Laravel.

---

## 8. Resultados de la Suite de Pruebas

- **Pruebas de Integración y Regresión V6.1:** 10 pasadas, 55 aserciones ([`tests/Feature/CcdAqpProductionMigrationV6Test.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/tests/Feature/CcdAqpProductionMigrationV6Test.php)).
- **Suite Completa CCD:** 120 pasadas, 580 aserciones, 0 errores, 0 fallos.
- **Suite General CRM (Pagos, Promesas, CNA, Clientes, Auth):** 252 pasadas, 1,670 aserciones, 0 errores, 0 fallos.
- **Compilación de Assets Frontend (`npm run build`):** 38 módulos transformados en 5.14s sin errores.
- **Laravel Pint:** 100% de cumplimiento de estilo en todos los archivos del módulo.
