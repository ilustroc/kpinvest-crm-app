# KP INVEST CRM V3 — INFORME DE LIMPIEZA ESTRUCTURAL DE BASE DE DATOS
## Eliminación de Columnas Heredadas, Ampliación de `ccd_clientes.numdoc` y Compatibilidad de Esquemas

**Fecha:** 4 de Noviembre de 2026  
**Proyecto:** `C:\GitHub\KP-CRM-Meridian-ui`  
**Rama de trabajo:** `feature/meridian-ui-v3`  
**Ambiente de ejecución y pruebas:** Docker local (`kp_crm_testing`) en `http://localhost:8091`  
**Base histórica:** `kp_crm_revision_v3` (estrictamente preservada en modo SOLO LECTURA, 0 modificaciones)  
**Estado de la suite de regresión:** 114 pruebas ejecutadas, 807 aserciones, 0 fallos.

---

## 1. RESUMEN EJECUTIVO

Siguiendo la autorización de la dirección técnica, se procedió a realizar la limpieza estructural del esquema de base de datos del CRM Meridian V3. Esta intervención resuelve de manera definitiva tres inconsistencias residuales de versiones previas (V1/V2):

1. **Eliminación de `promesa_operaciones.cartera`:**
   Se retiró la columna `cartera` y su índice asociado (`promesa_operaciones_cartera_index`). El diagnóstico confirmó que el 100% de los 4,748 registros históricos contenían `cartera = NULL`. La tabla maestra de deudas (`clientes_cuentas`) no posee columna `cartera` (clasifica las operaciones por `entidad`, `producto` y `cosecha`), por lo que dicha columna era un residuo arquitectónico inerte. Las relaciones operativas entre `promesas_pago`, `promesa_operaciones` y `clientes_cuentas` se preservan íntegramente mediante `promesa_id`, `numdoc` y `operacion`.
2. **Eliminación de `pagos_lotes.tipo`:**
   Se auditó la tabla `pagos_lotes` en el histórico: los 401 lotes existentes presentaban invariablemente el valor `'propia'`. Esta columna no aporta criterio de clasificación en el CRM V3. Se eliminó la columna, se adaptó el controlador [`IntegracionPagosController`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Http/Controllers/IntegracionPagosController.php) y el modelo [`PagoLote`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Models/PagoLote.php) para prescindir de ella, conservando la integridad de lotes, IDs y claves foráneas hacia `pagos.lote_id`.
3. **Ampliación de `ccd_clientes.numdoc`:**
   La columna se amplió de `VARCHAR(12)` a `VARCHAR(30)`. Esto homologa `ccd_clientes` con `clientes_cuentas`, `cliente_bloqueos`, `cna_solicitudes` y `promesas_pago`, permitiendo almacenar y consultar con holgura DNIs (8 dígitos), RUCs (11 dígitos) y Carnés de Extranjería / PTP (hasta 15 caracteres alfanuméricos), manteniendo inalterados los 275,987 registros de indexación y los 186,287 archivos PDF respaldados.
4. **Verificación y Resiliencia `pagos` vs. `pagos_propia`:**
   Se analizó la coexistencia histórica de ambas denominaciones (Hostinger original utilizaba `pagos_propia`, mientras que el entorno de desarrollo y staging consolidó `pagos`). Se comprobó que las migraciones y modelos operan de forma tolerante y segura en cualquiera de los dos esquemas mediante detección dinámica en tiempo de ejecución.

---

## 2. ESQUEMA ANTERIOR VS. NUEVO ESQUEMA (DDL)

### 2.1 Tabla `promesa_operaciones`

```sql
-- ESQUEMA ANTERIOR
CREATE TABLE `promesa_operaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promesa_id` bigint(20) unsigned NOT NULL,
  `operacion` varchar(50) NOT NULL,
  `cartera` varchar(50) DEFAULT NULL,           -- [ELIMINADA]
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `promesa_operaciones_promesa_id_operacion_unique` (`promesa_id`,`operacion`),
  KEY `promesa_operaciones_operacion_index` (`operacion`),
  KEY `promesa_operaciones_cartera_index` (`cartera`), -- [ELIMINADO]
  CONSTRAINT `promesa_operaciones_promesa_id_foreign` FOREIGN KEY (`promesa_id`) REFERENCES `promesas_pago` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVO ESQUEMA LIMPIO
CREATE TABLE `promesa_operaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promesa_id` bigint(20) unsigned NOT NULL,
  `operacion` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `promesa_operaciones_promesa_id_operacion_unique` (`promesa_id`,`operacion`),
  KEY `promesa_operaciones_operacion_index` (`operacion`),
  CONSTRAINT `promesa_operaciones_promesa_id_foreign` FOREIGN KEY (`promesa_id`) REFERENCES `promesas_pago` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabla `pagos_lotes`

```sql
-- ESQUEMA ANTERIOR
CREATE TABLE `pagos_lotes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,                  -- [ELIMINADA]
  `archivo` varchar(255) DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `total_registros` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVO ESQUEMA LIMPIO
CREATE TABLE `pagos_lotes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `archivo` varchar(255) DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `total_registros` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Tabla `ccd_clientes`

```sql
-- ESQUEMA ANTERIOR
CREATE TABLE `ccd_clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numdoc` varchar(12) NOT NULL,                -- [ANCHO ANTERIOR: 12]
  `pdf` varchar(500) DEFAULT NULL,
  `cosecha` varchar(100) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `ccd_archivo_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ccd_clientes_ccd_archivo_id_foreign` (`ccd_archivo_id`),
  KEY `ccd_clientes_numdoc_cosecha_index` (`numdoc`,`cosecha`),
  KEY `ccd_clientes_cosecha_pdf_index` (`cosecha`,`pdf`),
  CONSTRAINT `ccd_clientes_ccd_archivo_id_foreign` FOREIGN KEY (`ccd_archivo_id`) REFERENCES `ccd_archivos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVO ESQUEMA ESTANDARIZADO
CREATE TABLE `ccd_clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numdoc` varchar(30) NOT NULL,                -- [NUEVO ANCHO: 30]
  `pdf` varchar(500) DEFAULT NULL,
  `cosecha` varchar(100) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `ccd_archivo_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ccd_clientes_ccd_archivo_id_foreign` (`ccd_archivo_id`),
  KEY `ccd_clientes_numdoc_cosecha_index` (`numdoc`,`cosecha`),
  KEY `ccd_clientes_cosecha_pdf_index` (`cosecha`,`pdf`),
  CONSTRAINT `ccd_clientes_ccd_archivo_id_foreign` FOREIGN KEY (`ccd_archivo_id`) REFERENCES `ccd_archivos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. DEPENDENCIAS ENCONTRADAS Y CORREGIDAS

### 3.1 Referencias a `promesa_operaciones.cartera`
- **Modelo Eloquent [`PromesaOperacion`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Models/PromesaOperacion.php):** Se removió `'cartera'` de la propiedad `$fillable`. El modelo ahora solo gestiona `promesa_id` y `operacion`.
- **Relaciones multi-operación:** Se verificó que las promesas con múltiples operaciones vinculan exactamente las deudas mediante `(promesa_id, operacion)` y el `numdoc` de la promesa cabecera, sin verse afectadas por la eliminación.
- **Otras columnas `cartera` en el sistema:** Se mantuvo intacta la columna `cartera` en la tabla `asignar_clientes` y los conceptos de cartera en el módulo de reconciliación de cuentas de clientes [`DataReconciliationService`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Services/Cartera/DataReconciliationService.php).

### 3.2 Referencias a `pagos_lotes.tipo`
- **Controlador [`IntegracionPagosController`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Http/Controllers/IntegracionPagosController.php):**
  - En el método `index()`, se sustituyó la consulta rígida:
    `PagoLote::where('tipo', 'propia')->latest('id')->first()`
    por:
    `PagoLote::latest('id')->first()`
  - En el método `import()`, se retiró el parámetro `'tipo' => 'propia'` del array de creación del lote:
    ```php
    $lote = PagoLote::create([
        'archivo'         => $path,
        'usuario_id'      => Auth::id(),
        'total_registros' => 0,
    ]);
    ```
- **Modelo Eloquent [`PagoLote`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Models/PagoLote.php):**
  - Se eliminó `'tipo'` de `$fillable`.
  - Se implementó un accesor defensivo `getTipoAttribute()` que retorna `'propia'` por defecto si algún código legado consultase el atributo en memoria, asegurando cero fallos.
- **Vistas y Reportes:**
  - Se confirmó que [`resources/views/placeholders/integracion-pagos.blade.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/resources/views/placeholders/integracion-pagos.blade.php) únicamente utiliza `$ultimoLote->id`, `$ultimoLote->created_at` y `$ultimoLote->total_registros`. No muestra ni filtra por `tipo`.
- **Casos de prueba:**
  - Se actualizaron [`V3AutorizacionModuleTest`](file:///C:/GitHub/KP-CRM-Meridian-ui/tests/Feature/V3AutorizacionModuleTest.php) y [`V3WorkflowSmokeTest`](file:///C:/GitHub/KP-CRM-Meridian-ui/tests/Feature/V3WorkflowSmokeTest.php) para instanciar lotes sin requerir el atributo obsoleto.

---

## 4. VALIDACIÓN DE `pagos` Y `pagos_propia`

Se realizó una auditoría minuciosa del origen y comportamiento de ambas tablas:

1. **Estructura en Producción (Hostinger):**
   La base de datos original (`u480021566_kpinvest_bd.sql`) define la tabla bajo el nombre `pagos_propia` con clave foránea `fk_pagos_propia_lote` hacia `pagos_lotes(id)`.
2. **Estructura en Desarrollo y Staging:**
   La migración histórica `database/migrations/2026_10_29_123000_rename_pagos_propia_to_pagos.php` renombró la tabla a `pagos`.
3. **Resiliencia en Migraciones V3:**
   Todas las migraciones desarrolladas en esta fase emplean detección dinámica:
   ```php
   $pagosTable = Schema::hasTable('pagos') ? 'pagos' : (Schema::hasTable('pagos_propia') ? 'pagos_propia' : null);
   ```
   Esto asegura que, independientemente de si el script corre en un entorno donde aún no se ha ejecutado el renombrado o en uno consolidado, la operación DDL localiza la tabla correcta sin abortar ni duplicar registros.
4. **Modelo Eloquent [`Pago`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Models/Pago.php):**
   Mantiene su mapeo hacia la tabla `pagos` y administra transparentemente el documento mediante los accesores/mutadores de transición `numdoc` / `dni`.

---

## 5. MIGRACIONES DESARROLLADAS Y PRUEBAS DE REVERSIBILIDAD

Se implementaron tres migraciones Laravel independientes y reversibles:

### 5.1 Migración 1: `2026_11_04_000002_drop_cartera_from_promesa_operaciones.php`
- **Archivo:** [`database/migrations/2026_11_04_000002_drop_cartera_from_promesa_operaciones.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/database/migrations/2026_11_04_000002_drop_cartera_from_promesa_operaciones.php)
- **Operación `up()`:** Comprueba la existencia del índice `promesa_operaciones_cartera_index` y lo elimina; luego comprueba la existencia de la columna `cartera` y la elimina vía `ALTER TABLE`.
- **Operación `down()`:** Recrea la columna `cartera VARCHAR(50) DEFAULT NULL` tras `operacion` y restituye el índice `promesa_operaciones_cartera_index`.

### 5.2 Migración 2: `2026_11_04_000003_drop_tipo_from_pagos_lotes.php`
- **Archivo:** [`database/migrations/2026_11_04_000003_drop_tipo_from_pagos_lotes.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/database/migrations/2026_11_04_000003_drop_tipo_from_pagos_lotes.php)
- **Operación `up()`:** Busca cualquier índice en `information_schema` vinculado a la columna `tipo` en `pagos_lotes` y lo elimina; posteriormente elimina la columna `tipo`.
- **Operación `down()`:** Recrea la columna `tipo VARCHAR(50) NOT NULL DEFAULT 'propia'` tras `id`.

### 5.3 Migración 3: `2026_11_04_000004_widen_ccd_clientes_numdoc.php`
- **Archivo:** [`database/migrations/2026_11_04_000004_widen_ccd_clientes_numdoc.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/database/migrations/2026_11_04_000004_widen_ccd_clientes_numdoc.php)
- **Operación `up()`:** Ejecuta `ALTER TABLE ccd_clientes MODIFY COLUMN numdoc VARCHAR(30) NOT NULL`.
- **Operación `down()`:** Restaura `ALTER TABLE ccd_clientes MODIFY COLUMN numdoc VARCHAR(12) NOT NULL`.

### 5.4 Ciclo de Validación `up` -> `down` -> `up`
1. **Ejecución ascendente:**
   ```
   2026_11_04_000002_drop_cartera_from_promesa_operaciones .......... 61ms DONE
   2026_11_04_000003_drop_tipo_from_pagos_lotes ..................... 12ms DONE
   2026_11_04_000004_widen_ccd_clientes_numdoc ...................... 10ms DONE
   ```
2. **Prueba de Rollback (`migrate:rollback --step=3`):**
   ```
   2026_11_04_000004_widen_ccd_clientes_numdoc ...................... 94ms DONE
   2026_11_04_000003_drop_tipo_from_pagos_lotes ..................... 15ms DONE
   2026_11_04_000002_drop_cartera_from_promesa_operaciones .......... 24ms DONE
   ```
   *Se verificó mediante `SHOW COLUMNS` que las estructuras previas se reconstituyeron íntegramente.*
3. **Re-aplicación final:**
   Las 3 migraciones se volvieron a ejecutar satisfactoriamente, dejando `kp_crm_testing` en el estado definitivo limpio.

---

## 6. PRUEBAS DE SEGURIDAD, ESTABILIDAD Y REGRESIÓN

Se creó una clase de prueba automatizada dedicada:  
[`tests/Feature/StructuralCleanupTest.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/tests/Feature/StructuralCleanupTest.php)

Esta suite evalúa:
1. `test_promesa_operaciones_cartera_is_removed_and_multi_operations_work`:
   - Verifica ausencia física de la columna `cartera` e índice.
   - Crea un cliente con múltiples deudas y una promesa asociando 2 operaciones simultáneas en `promesa_operaciones`. Confirma que el registro y consulta opera con éxito.
2. `test_pagos_lotes_tipo_is_removed_and_lote_relationships_work`:
   - Verifica ausencia física de la columna `tipo` en `pagos_lotes`.
   - Crea un lote sin `tipo`, le asocia múltiples pagos y evalúa las relaciones `hasMany` y `belongsTo` (`$lote->pagos` y `$pago->lote`).
   - Comprueba que `PagoLote::latest('id')` resuelve el último lote sin error.
3. `test_ccd_clientes_numdoc_supports_up_to_30_chars_and_all_document_types`:
   - Comprueba que el tipo de dato en el diccionario de datos es `varchar(30)`.
   - Inserta y consulta un DNI con ceros a la izquierda (`00234567`), un RUC (`20601234567`) y un Carné de Extranjería / PTP (`CE-00984512-XYZ`).
   - Verifica que las cadenas no sufren truncamiento y preservan los ceros iniciales.

### Resultados de la Suite Completa de Regresión
Se ejecutó la totalidad de pruebas automatizadas del proyecto sobre `kp_crm_testing`:

```
   PASS  Tests\Unit\CcdImportBatchPlannerTest (4 tests)
   PASS  Tests\Unit\DataImportDiagnosisTest (4 tests)
   PASS  Tests\Unit\ExampleTest (1 test)
   PASS  Tests\Feature\DashboardOptimizationFaseBTest (14 tests)
   PASS  Tests\Feature\DataImportReconciliationFaseCTest (21 tests)
   PASS  Tests\Feature\ExampleTest (1 test)
   PASS  Tests\Feature\FrontendOptimizationFaseDTest (5 tests)
   PASS  Tests\Feature\IntegralFunctionalAuditP13Test (10 tests)
   PASS  Tests\Feature\SecurityP0RemediationTest (9 tests)
   PASS  Tests\Feature\SecurityP1FunctionalTest (8 tests)
   PASS  Tests\Feature\SqlOptimizationFaseATest (4 tests)
   PASS  Tests\Feature\StructuralCleanupTest (3 tests)
   PASS  Tests\Feature\V3AutorizacionModuleTest (4 tests)
   PASS  Tests\Feature\V3ClienteModuleTest (4 tests)
   PASS  Tests\Feature\V3CnaModuleTest (2 tests)
   PASS  Tests\Feature\V3MailWorkflowTest (3 tests)
   PASS  Tests\Feature\V3NotificationModuleTest (4 tests)
   PASS  Tests\Feature\V3PromesaModuleTest (3 tests)
   PASS  Tests\Feature\V3RealtimeNotificationTest (2 tests)
   PASS  Tests\Feature\V3RoleAccessTest (2 tests)
   PASS  Tests\Feature\V3WorkflowSmokeTest (4 tests)

  Tests:    2 skipped, 114 passed (807 assertions)
  Duration: 51.54s
```

---

## 7. INTEGRIDAD DE LA BASE HISTÓRICA (`kp_crm_revision_v3`)

Se ratifica que la base histórica se conservó en **modo de solo lectura estricto**. Los conteos fueron verificados antes y después de todas las pruebas:

```sql
SELECT 'clientes_cuentas' AS tbl, count(*) AS total FROM clientes_cuentas
UNION ALL SELECT 'pagos', count(*) FROM pagos
UNION ALL SELECT 'promesas_pago', count(*) FROM promesas_pago
UNION ALL SELECT 'cna_solicitudes', count(*) FROM cna_solicitudes
UNION ALL SELECT 'users', count(*) FROM users
UNION ALL SELECT 'pagos_lotes', count(*) FROM pagos_lotes
UNION ALL SELECT 'promesa_operaciones', count(*) FROM promesa_operaciones
UNION ALL SELECT 'ccd_clientes', count(*) FROM ccd_clientes;
```

**Resultados auditados:**
```
tbl                 total
clientes_cuentas    282595
pagos                43193
promesas_pago         4445
cna_solicitudes       2601
users                   53
pagos_lotes            401
promesa_operaciones   4748
ccd_clientes        275987
```
**Variación observada:** **0 registros**. Ninguna tabla histórica sufrió modificaciones.

---

## 8. PROCEDIMIENTO DE RESPALDO Y ROLLBACK PARA PREPRODUCCIÓN Y PRODUCCIÓN

> [!IMPORTANT]
> Las operaciones `DROP COLUMN` son destructivas a nivel físico de los datos alojados en dichas columnas. Aunque en este caso `cartera` era 100% `NULL` y `tipo` era 100% `'propia'`, el método `down()` únicamente restaura la definición de la columna. Para recuperar los datos exactos que existían antes de la migración es indispensable contar con el respaldo previo.

### Paso 1: Respaldo previo antes de aplicar migraciones
Generar un respaldo completo de las tres tablas objetivo antes del despliegue:
```bash
mariadb-dump -u [USER] -p[PASS] --single-transaction --routines [DATABASE] promesa_operaciones pagos_lotes ccd_clientes > backup_tablas_objetivo_$(date +%Y%m%d_%H%M%S).sql
```
*(En el entorno de desarrollo local, se generó y preservó el respaldo verificado en `database/backup_target_tables_pre_cleanup.sql`)*.

### Paso 2: Ejecución de las migraciones
```bash
php artisan down --message="Actualización estructural de esquema V3."
php artisan migrate --path=database/migrations --force
php artisan cache:clear
php artisan up
```

### Paso 3: Procedimiento de Reversión (Rollback)
Si fuese necesario revertir las tres migraciones:
```bash
php artisan down
php artisan migrate:rollback --step=3 --path=database/migrations --force
# En caso de requerir reincorporar los valores previos de tipo='propia':
mariadb -u [USER] -p[PASS] [DATABASE] < backup_tablas_objetivo_[TIMESTAMP].sql
php artisan cache:clear
php artisan up
```

---

## 9. EVALUACIÓN DE RIESGOS PENDIENTES

| Área | Nivel de Riesgo | Mitigación Implementada |
| :--- | :---: | :--- |
| **Pérdida de datos en `cartera`** | Ninguno | Diagnosticado previamente: 100% de los 4,748 registros eran `NULL`. |
| **Pérdida de datos en `tipo`** | Ninguno | Diagnosticado previamente: 100% de los 401 lotes tenían el valor fijo `'propia'`. El modelo [`PagoLote`](file:///C:/GitHub/KP-CRM-Meridian-ui/app/Models/PagoLote.php) mantiene un accesor que devuelve `'propia'` por defecto si es solicitado. |
| **Truncamiento en `ccd_clientes`** | Ninguno | La operación fue de ampliación (`VARCHAR(12)` a `VARCHAR(30)`), lo que nunca trunca datos existentes y no requiere reconstruir índices. |
| **Inconsistencias `pagos` / `pagos_propia`** | Bajo | La migración `2026_11_04_000001_standardize_dni_to_numdoc` detecta el nombre dinámicamente; las claves foráneas hacia `pagos_lotes` apuntan al campo `id`, el cual no fue alterado. |
| **Rendimiento de Consultas** | Óptimo | Se eliminó un índice innecesario en `promesa_operaciones`, ahorrando espacio en disco e I/O de inserción. |

---

## 10. ESTADO FINAL DE CONTROL DE VERSIONES

- **Commits realizados:** 0
- **Push remotos:** 0
- **Merges:** 0
- **Diseño UX/UI Meridian:** Preservado al 100%
- **Archivos de respaldo:** Disponibles en `database/backup_kp_crm_testing_pre_cleanup.sql` y `database/backup_target_tables_pre_cleanup.sql`
- **Suite de pruebas:** 114 pruebas aprobadas, 807 aserciones, 0 fallos.
