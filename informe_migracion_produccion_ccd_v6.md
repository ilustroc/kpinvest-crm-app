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

Las siguientes firmas SHA-256 son estrictamente validadas por el servicio migrador y el desplegador antes de permitir cualquier operación:

- **Firma CCD AQP:** `861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b`
- **Firma Cuentas (universo total):** `0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f`

### Método de Serialización Unificado para el Cálculo de Firmas

Para garantizar consistencia matemática exacta con la auditoría original:
1. **Firma CCD AQP:** Consulta `SELECT id, numdoc, pdf, cosecha FROM ccd_clientes WHERE cosecha IN ('AQP4', 'AQP5') ORDER BY id ASC;` (10,224 registros).
2. **Firma Cuentas Total:** Consulta `SELECT id, numdoc, cuenta, operacion, entidad, cosecha FROM clientes_cuentas ORDER BY id ASC;` (la totalidad de los 282,595 registros, sin filtro de cosecha).
3. **Formato y Codificación:** Cada fila se serializa mediante `json_encode([...], JSON_UNESCAPED_UNICODE) . "\n"` por registro de forma secuencial mediante streaming, procesando candidatos indexados y asociativos ordenados por `id ASC`.
4. **Unificación Bash-PHP:** El desplegador Bash (`desplegar_ccd_aqp_produccion_v6.sh`) invoca directamente las funciones PHP de `CcdProductionMigratorService` a través del archivo privado de credenciales. Se eliminó la serialización tabulada por `mysql -N -B` y el filtrado indebido de cuentas AQP, garantizando que el cálculo de firmas en Bash y PHP sea 100% idéntico. Ambos entornos validan:
   - Concordancia cruzada idéntica: `origen == destino`.
   - Coincidencia exacta contra las firmas de referencia oficiales.

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

### 3.2. Integración Aditiva Quirúrgica y Verificación de Compatibilidad
- Se eliminó el reemplazo ciego con `cp` y scripts externos de parsing.
- La integración se realiza mediante el método `CcdProductionMigratorService::verifyAndIntegrateComponents()`:
  - **Archivos nuevos:** Se instalan con permisos 0664 previa validación de sintaxis (`php -l`).
  - **Componentes existentes de CRM:**
    - `ClienteCuenta.php`: Si el archivo de producción ya cuenta con `ccdLinks()`, se deja **completamente intacto**. Si carece de él, se valida la estructura del modelo, se inyecta la relación `ccdLinks(): HasMany` antes de la llave de cierre, se crea un respaldo individual previo y se valida con `php -l`.
    - `CcdCliente.php`: Inyecta exclusivamente las relaciones `archivo()` y `cuentaLinks()` faltantes sin sobreescribir código existente.
    - `routes/web/clientes.php`: Inyecta la ruta `ccd.show` dentro del grupo de clientes solo si no se encuentra ya declarada.
    - `ClienteProfileService.php`: Inyecta la lógica de filtrado de documentos confirmados dentro de `ccdData()` solo si no está presente.
    - `resources/views/clientes/show.blade.php`: Inyecta el visualizador de documentos confirmados conservando el diseño de la tabla.
    - `config/filesystems.php`: Inyecta la definición del disco `ccd-private` de forma nativa mediante PHP (sin requerir intérpretes secundarios como Python).
  - Si cualquier archivo presenta una estructura incompatible, el proceso **aborta inmediatamente antes de modificar el entorno de producción**.
- Se preservan de forma 100% garantizada la autenticación y los módulos de clientes, pagos, promesas y CNA.

### 3.3. Rechazo de Conflictos y Eliminación de `ON DUPLICATE KEY UPDATE`
- Se eliminó completamente la sobrescritura silenciosa mediante `ON DUPLICATE KEY UPDATE`.
- **Política de Inserción e Idempotencia:**
  - Si un registro ya existe en destino y todos sus campos de negocio son idénticos: se considera **idempotente** y se continúa sin duplicar.
  - Si un registro ya existe en destino pero difiere en cualquier campo de negocio: se **detiene inmediatamente la ejecución**, se hace rollback de la transacción y se registra el conflicto de integridad sin modificar la fila preexistente.
  - Esta política aplica uniformemente a `ccd_archivos`, `ccd_clientes.ccd_archivo_id` y `ccd_cliente_cuenta`.

### 3.4. Protección de Credenciales MySQL con Serializador Nativo
- Se eliminó el paso de contraseñas mediante argumentos CLI (`--source-password`, `--target-password`) que puedan exponerse en la tabla de procesos (`ps aux`).
- La generación del JSON de credenciales privadas se realiza mediante un serializador nativo en PHP (`json_encode()`), garantizando el soporte seguro de contraseñas complejas con caracteres especiales (comillas, barras invertidas, signos de dólar).
- El archivo temporal se crea en `${HOME}/private/.ccd_credentials/`, asegurado con permisos estrictos `0600`, y se destruye automáticamente al finalizar o abortar la ejecución mediante un handler `trap`.

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

## 7. Plan de Rollback Específico y Blindaje de Lotes

Se descartó la recomendación genérica `php artisan migrate:rollback --step=2` debido al riesgo de deshacer migraciones no deseadas o restaurar volcados completos que sobreescribirían nuevas operaciones concurrentes de clientes o pagos en producción.

### Salvaguardas previas a la reversión (`validateRollbackSafety`):
1. **Aislamiento de Lotes:** Comprueba que no existan otras migraciones no-CCD registradas en el mismo lote (`batch`) en la tabla `migrations`. Si existieran, la reversión selectiva por ruta garantiza que solo se deshagan las migraciones CCD sin afectar a las demás.
2. **Protección contra Migraciones Posteriores:** Comprueba que no se hayan ejecutado migraciones posteriores a CCD (lote superior al de CCD).
3. **Ausencia de Datos Ajenos:** Verifica que `ccd_cliente_cuenta` no contenga asociaciones de otras cosechas y que `ccd_archivos` no tenga referencias externas activas.
4. **Verificación de Claves Foráneas:** Revisa `information_schema.KEY_COLUMN_USAGE` para asegurar que ninguna tabla ajena dependa de `ccd_archivos` o `ccd_cliente_cuenta`.
5. **Verificación Post-Reversión:** Confirma que las tablas y columnas CCD se hayan eliminado, pero certifica que `ccd_clientes` conserve íntegros sus 275,987 registros y `clientes_cuentas` sus 282,595 registros.

En caso de requerir reversión:

```bash
# Ejecutar la reversión específica y segura de las 2 migraciones CCD
./revertir_ccd_aqp_produccion_v6.sh --confirm

# (Opcional) Si se desea restaurar archivos modificados desde un respaldo previo:
./revertir_ccd_aqp_produccion_v6.sh --confirm --restore-files="${HOME}/private/backups/ccd-v6-produccion-<timestamp>"
```

---

## 8. Resultados de la Suite de Pruebas

- **Pruebas de Integración y Regresión V6.1:** 12 pasadas, 88 aserciones ([`tests/Feature/CcdAqpProductionMigrationV6Test.php`](file:///c:/GitHub/KP-CRM-Meridian-ui/tests/Feature/CcdAqpProductionMigrationV6Test.php)).
- **Suite Completa CCD:** 127 pasadas, 651 aserciones, 0 errores, 0 fallos.
- **Suite General CRM (Pagos, Promesas, CNA, Clientes, Auth):** 252 pasadas, 1,670 aserciones, 0 errores, 0 fallos.
- **Compilación de Assets Frontend (`npm run build`):** 38 módulos transformados en 5.14s sin errores.
- **Laravel Pint:** 100% de cumplimiento de estilo en todos los archivos del módulo.
