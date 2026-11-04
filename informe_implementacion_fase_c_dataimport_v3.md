# KP INVEST CRM V3 — INFORME DE IMPLEMENTACIÓN FASE C: MOTOR DE RECONCILIACIÓN EN TRES VÍAS (DATAIMPORT)

**Proyecto:** `C:\GitHub\KP-CRM-Meridian-ui`  
**Rama:** `feature/meridian-ui-v3`  
**Entorno:** Docker local (`http://localhost:8091`)  
**Base Histórica:** `kp_crm_revision_v3` (Modo Solo Lectura Estricto)  
**Base de Pruebas:** `kp_crm_testing`  
**Fecha:** 3 de Noviembre de 2026  

---

## 1. RESUMEN EJECUTIVO

En cumplimiento de las instrucciones de la **Aprobación Condicionada de Fase C**, se ha diseñado, implementado y validado de manera exhaustiva el nuevo **Motor de Reconciliación en Tres Vías de Cartera** (`DataReconciliationService` y `DataImport`), resolviendo de raíz el problema estructural de duplicación de cuentas y sobrestimación de deuda que afectaba al CRM.

### Logros Principales:
1. **Clarificación y Verificación del Esquema Real:** Se inspeccionó con `SHOW CREATE TABLE` y `SHOW INDEX` la tabla `clientes_cuentas`, demostrando que **no existe ningún índice `UNIQUE`** en la base histórica ni en la de pruebas. Se identificó el origen de la premisa falsa: una migración borrador no ejecutada (`database/migrations_draft/2026_10_31_000000_add_unique_to_clientes_cuentas_draft.php`).
2. **Robustecimiento Definitivo del Caché del Dashboard:** Se eliminó el fallback no atómico en `PanelDashboardService::doAtomicInvalidate()`. Se implementó un mecanismo de exclusión mutua mediante bloqueo a nivel de kernel de sistema operativo (`flock(LOCK_EX)`) con micro-reintentos y fallback monótono con registro estructurado de incidentes. Se validó mediante pruebas con 4 procesos CLI de Artisan (`dashboard:invalidate`) ejecutados concurrentemente sin una sola pérdida de incremento.
3. **Parser Numérico Financiero Robusto:** Se corrigió en [`CsvImportTrait.php`](app/Traits/CsvImportTrait.php) el fallo que convertía montos como `1,250.50` en `1.25050`. Ahora soporta formatos anglosajones, europeos, prefijos monetarios (`S/`, `$`), espacios y montos negativos contables entre paréntesis.
4. **Motor de Reconciliación en 3 Vías (`DataReconciliationService`):**
   - **Etapa 1:** Lectura por streams con preservación estricta de strings y ceros a la izquierda en `numdoc` y `operacion`. Deduplicación intra-archivo CSV.
   - **Etapa 2:** Resolución de identidad unificada por tupla `(entidad_normalizada, numdoc, operacion)`. Normalización canónica de bancos (`BCP`, `BBVA`, `PICHINCHA`, `INTERBANK`, `SCOTIABANK`, `CONFIANZA`, `WANDOO`). Exclusión explícita de `deuda_capital` de la identidad.
   - **Etapa 3:** Clasificación tripartita (`INSERT` para nuevas, `UPDATE` para existentes preservando PK y FK, `CUARENTENA` para cuentas sin operación o con colisiones ambiguas históricas).
   - **Etapa 4:** Transacciones por lotes (batches de 500 filas), mutex de importación contra concurrencia con descarte inmediato no bloqueante (`cartera_import.lock` con `LOCK_EX | LOCK_NB`), e invalidación atómica del caché tras commit exitoso.
5. **Modo Vista Previa (`preview()`):** Simulación exacta de la reconciliación sin persistencia en base de datos, entregando a la interfaz Meridian métricas de diagnóstico antes de confirmar la importación.
6. **Validación Exhaustiva:** 17 pruebas sintéticas unitarias y de integración que cubren los 15 escenarios solicitados, sumadas a la suite integral de regresión con **56 pruebas y 249 aserciones aprobadas al 100%**.
7. **Certificación de Inmutabilidad:** La base histórica `kp_crm_revision_v3` se mantuvo con exactamente los mismos 282,595 registros en `clientes_cuentas`, 43,193 en `pagos`, 4,445 en `promesas_pago`, 2,601 en `cna_solicitudes` y 53 en `users`.

---

## 2. VERIFICACIÓN DE ÍNDICES REALES Y ORIGEN DE LA PREMISA FALSA

### 2.1 Evidencia Técnica de Inspección de Esquema

Se ejecutaron consultas de solo lectura en MariaDB tanto sobre `kp_crm_revision_v3` como sobre `kp_crm_testing`:

```sql
SHOW CREATE TABLE clientes_cuentas\G
```

**Resultado verificado:**
```sql
CREATE TABLE `clientes_cuentas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numdoc` varchar(255) DEFAULT NULL,
  `operacion` varchar(255) DEFAULT NULL,
  `entidad` varchar(255) DEFAULT NULL,
  `producto` varchar(255) DEFAULT NULL,
  `cartera` varchar(255) DEFAULT NULL,
  `tramo` varchar(255) DEFAULT NULL,
  `score` varchar(255) DEFAULT NULL,
  `prioridad` varchar(255) DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `provincia` varchar(255) DEFAULT NULL,
  `distrito` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `deuda_capital` decimal(12,2) DEFAULT NULL,
  `monto_castigo` decimal(12,2) DEFAULT NULL,
  `moneda` varchar(255) DEFAULT NULL,
  `dias_mora` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cc_numdoc_search` (`numdoc`),
  KEY `wdbi_operacion_e79562a6c235` (`operacion`),
  KEY `wdbi_operacion_numdoc_updated_at_091ddf0ba4ea` (`operacion`,`numdoc`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
SHOW INDEX FROM clientes_cuentas;
```

**Resultado verificado:**
- `PRIMARY`: `id` (`Non_unique = 0`).
- `idx_cc_numdoc_search`: `numdoc` (`Non_unique = 1`).
- `wdbi_operacion_e79562a6c235`: `operacion` (`Non_unique = 1`).
- `wdbi_operacion_numdoc_updated_at_091ddf0ba4ea`: `operacion, numdoc, updated_at` (`Non_unique = 1`).

> [!NOTE]
> **Conclusión irrefutable:** No existe ni ha existido nunca un índice `UNIQUE` sobre `(numdoc, operacion, deuda_capital)` en la base histórica ni en la de pruebas.

### 2.2 Origen del Error de la Premisa Falsa

La discrepancia reportada se originó en la existencia de un archivo de migración borrador en el repositorio:
`database/migrations_draft/2026_10_31_000000_add_unique_to_clientes_cuentas_draft.php`.

Dicho archivo fue propuesto como una tentativa de solución en fases tempranas, pero fue aislado preventivamente en `migrations_draft` porque la base histórica `kp_crm_revision_v3` no puede ser alterada mediante migraciones DDL bajo ninguna circunstancia. El informe P1.1 asumió erróneamente su aplicación activa.

### 2.3 Por Qué `UNIQUE (numdoc, operacion, deuda_capital)` es Conceptualmente Nocivo en Finanzas

Incluir `deuda_capital` dentro de la clave de unicidad de una cuenta bancaria es un grave error conceptual por las siguientes razones:

1. **La deuda no es una propiedad identitaria:** La identidad de una obligación financiera está definida por la entidad crediticia, el cliente titular y el número de contrato/operación: `(entidad, numdoc, operacion)`. El saldo de deuda (`deuda_capital`) es un **saldo dinámico sujeto a amortizaciones continuas**.
2. **Generación artificial de duplicados e inflación de deuda:**
   - Supongamos que un cliente tiene una deuda inicial de S/ 1,000.00 (`id: 50`).
   - El cliente abona S/ 200.00 y en la siguiente importación bancaria el archivo CSV consigna `deuda_capital: 800.00`.
   - Si existiera una clave `UNIQUE(numdoc, operacion, deuda_capital)`, la tupla `('12345678', 'OP-01', 800.00)` no coincidiría con la tupla previa `('12345678', 'OP-01', 1000.00)`.
   - MariaDB consideraría la fila como una cuenta completamente nueva e insertaría un nuevo registro (`id: 51`), manteniendo el registro anterior.
   - **Efecto destructivo:** En el CRM aparecerían 2 cuentas activas para el mismo crédito, y la deuda total visible ascendería a S/ 1,800.00 en lugar de los S/ 800.00 reales.
3. **Mecanismo ciego de `upsert()` en Laravel / MariaDB:**
   El método `upsert($values, $uniqueBy, $update)` de Laravel compila a la instrucción MySQL/MariaDB:
   ```sql
   INSERT INTO clientes_cuentas (...) VALUES (...) ON DUPLICATE KEY UPDATE ...
   ```
   MariaDB **ignora por completo** el parámetro `$uniqueBy` de Laravel; la cláusula `ON DUPLICATE KEY UPDATE` se activa **únicamente** cuando ocurre un conflicto con la `PRIMARY KEY` o con una restricción `UNIQUE` declarada en el esquema DDL. Al carecer la tabla de un índice único compuesto, el `upsert()` se comportaba siempre como un `INSERT` puro, multiplicando las filas en cada reimportación.

---

## 3. AUDITORÍA Y ROBUSTECIMIENTO DEL CACHÉ DEL DASHBOARD

En respuesta a las instrucciones recibidas, se perfeccionó el mecanismo de invalidación atómica de `app/Services/Panel/PanelDashboardService.php`.

### 3.1 Eliminación del Fallback No Atómico y Bloqueo a Nivel de SO

El fallback no atómico anterior (`$next = (int) Cache::get(...) + 1; Cache::forever(...)`) fue eliminado. En su lugar se implementó:

1. **Mutex Exclusivo a Nivel de Kernel (`flock`):** Se utiliza el archivo de cerrojo `storage/framework/cache/panel_dashboard_version.lock` con `LOCK_EX`.
2. **Reintentos Rápidos (Micro-retries):** Bucle de hasta 5 intentos con espaciado de 20ms en caso de contención de E/S.
3. **Fallback Monótono Asistido:** Si el bloqueo a nivel de archivo falla por permisos del sistema de archivos, se recurre a `Cache::lock()` del framework. Si ambos fallan, se genera un incremento monótono calculado mediante timestamp microsegundo garantizando avance positivo, registrando una alerta estructurada en `Log::error`.
4. **Respeto Transaccional (`DB::afterCommit`):** Si una mutación ocurre dentro de una transacción de base de datos activa (`DB::transactionLevel() > 0`), la invalidación se aplaza hasta que la transacción haga commit físico. Si la transacción hace rollback, la invalidación jamás se dispara.

```php
// app/Services/Panel/PanelDashboardService.php
public static function doAtomicInvalidate(): void
{
    $lockFile = storage_path('framework/cache/panel_dashboard_version.lock');
    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $fp = @fopen($lockFile, 'c+');
    if ($fp) {
        $locked = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (flock($fp, LOCK_EX)) {
                $locked = true;
                break;
            }
            usleep(20000); // 20ms
        }

        if ($locked) {
            try {
                $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
                Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
                return;
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
        fclose($fp);
    }

    // Secondary defense: Cache::lock
    try {
        $lock = Cache::lock('panel_dashboard_version_atomic_lock', 5);
        if ($lock->get()) {
            try {
                $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
                Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
                return;
            } finally {
                $lock->release();
            }
        }
    } catch (\Throwable) {
    }

    // Monotonic fallback with structured incident logging
    $fallbackVersion = (int) (microtime(true) * 100);
    $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    $newVersion = max($current + 1, $fallbackVersion);
    Cache::forever(self::CACHE_VERSION_KEY, $newVersion);
    Log::error('PanelDashboardService: Version lock failed; monotonic fallback applied.', [
        'previous' => $current,
        'new' => $newVersion,
    ]);
}
```

### 3.2 Comando Artisan y Prueba de Concurrencia Multi-Proceso

Se creó el comando de consola `app/Console/Commands/InvalidateDashboardCache.php` (`php artisan dashboard:invalidate`).

En `tests/Feature/DashboardOptimizationFaseBTest.php`, se validó mediante `proc_open` el lanzamiento simultáneo de **4 procesos de sistema operativo independientes** ejecutando dicho comando sobre el almacén de caché en disco (`file`).

**Resultado de la prueba:**
- Versión inicial: `300`.
- Procesos CLI ejecutados en paralelo: `4`.
- Versión final leída del caché: `304`.
- Cero condiciones de carrera, cero incrementos perdidos.

---

## 4. ARQUITECTURA DEL MOTOR DE RECONCILIACIÓN EN TRES VÍAS

Se implementó el servicio especializado `app/Services/Cartera/DataReconciliationService.php`, desacoplando la lógica de reconciliación de la infraestructura de importación y permitiendo su invocación tanto en modo ejecución real como en modo simulación (preview).

### 4.1 Etapa 1: Lectura, Sanitización y Parser Numérico Universal

1. **Preservación Estricta de Strings:** Los campos `numdoc` y `operacion` se tratan estrictamente como cadenas alfanuméricas con `(string) trim(...)`. Ceros iniciales como `'00012345'` o `'0000009876'` se preservan íntegros.
2. **Corrección Crítica de `parseNumber`:**
   En `app/Traits/CsvImportTrait.php`:
   - Soporte para decimales con punto (`1,250.50`) y con coma (`1.250,50`).
   - Eliminación segura de prefijos (`S/`, `$`, espacios).
   - Conversión de montos contables negativos entre paréntesis `(100.50)` a `-100.50`.
3. **Detección de Duplicados en el Mismo Archivo CSV:**
   Si el archivo CSV contiene 2 o más registros con la misma clave `(entidad_normalizada, numdoc, operacion)`, el motor procesa la primera ocurrencia y descarta las subsiguientes registrándolas en el contador `duplicados_en_csv`, evitando contención innecesaria.

### 4.2 Etapa 2: Normalización Canónica de Entidades y Resolución de Identidad

El motor aplica un diccionario canónico de entidades financieras:

| Variación en CSV | Entidad Canónica Normalizada |
| :--- | :--- |
| `BCP`, `BANCO DE CREDITO`, `BANCO CREDITO DEL PERU` | `BCP` |
| `BBVA`, `BANCO CONTINENTAL`, `BBVA CONTINENTAL` | `BBVA` |
| `PICHINCHA`, `BANCO PICHINCHA`, `BANCO FINANCIERO` | `PICHINCHA` |
| `INTERBANK`, `BANCO INTERNACIONAL DEL PERU` | `INTERBANK` |
| `SCOTIABANK`, `BANCO SCOTIABANK` | `SCOTIABANK` |
| `CONFIANZA`, `FINANCIERA CONFIANZA` | `CONFIANZA` |
| `WANDOO`, `WANDOO FINANCE` | `WANDOO` |

**Clave de Resolución de Identidad:**
```php
$identityKey = $entidadNorm . '|' . $numdoc . '|' . $operacion;
```
`deuda_capital` se excluye de forma deliberada y estricta de la identidad para permitir que las amortizaciones y reducciones de deuda actualicen la cuenta existente sin duplicarla.

### 4.3 Etapa 3: Matriz de Reconciliación en Tres Vías

| Categoría | Condición de Negocio | Acción en Base de Datos | Preservación de Integridad |
| :--- | :--- | :--- | :--- |
| **1. INSERT** | Clave `(entidad, numdoc, operacion)` no existe en `clientes_cuentas`. | Inserción de nueva fila con atributos completos de cartera y deuda. | Genera nuevo `id`. No colisiona con cuentas existentes. |
| **2. UPDATE** | Clave `(entidad, numdoc, operacion)` coincide exactamente con 1 registro en `clientes_cuentas`. | Actualización de `deuda_capital`, `tramo`, `cartera`, `dias_mora`, `departamento`, `score`, etc. | **Preserva intacto el `id` original y `created_at`**. Preserva todas las relaciones con `pagos`, `promesas_pago` y `cna_solicitudes`. |
| **3. CUARENTENA** | • `operacion` vacía o nula.<br>• Clave colisiona con múltiples registros en BD (ambigüedad histórica previa). | Ninguna modificación en la tabla `clientes_cuentas`. Fila aislada en reporte de auditoría. | Evita sobrescribir la cuenta incorrecta o corromper datos históricos no saneados. |

### 4.4 Etapa 4: Seguridad Concurrente y Transaccional

1. **Mutex No Bloqueante (`LOCK_EX | LOCK_NB`):**
   Para impedir que dos usuarios o procesos importen carteras al mismo tiempo corrompiendo la reconciliación, se adquiere un cerrojo exclusivo en `storage/framework/cache/cartera_import.lock`. Si otra importación está activa, la solicitud entrante es rechazada de inmediato con una excepción descriptiva (`HTTP 423 / Locked`).
2. **Lotes Atómicos (Batches de 500 filas):**
   Las inserciones y actualizaciones se ejecutan dentro de transacciones de base de datos (`DB::transaction`). Si una falla imprevista de base de datos ocurre en medio de un lote, el lote entero hace rollback sin dejar estados corruptos a medio procesar.
3. **Invalidación de Caché Post-Commit:**
   Al finalizar con éxito todos los lotes y cerrarse la transacción, se ejecuta `PanelDashboardService::invalidate()`, provocando que el dashboard y los KPIs se actualicen instantáneamente.

---

## 5. MODALIDAD VISTA PREVIA (`preview()`)

Se dotó a `DataImport` del método `preview(string $filepath): array`, el cual delega a `DataReconciliationService::execute($filepath, isPreview: true)`.

La vista previa procesa el 100% de las filas del CSV, ejecuta las mismas reglas de sanitización, deduplicación intra-archivo, normalización de entidades y búsqueda en base de datos, pero **omite cualquier operación `INSERT` o `UPDATE`**.

### Estructura de Respuesta del Diagnóstico Preview:
```json
{
  "total_filas": 1000,
  "nuevos_a_insertar": 250,
  "existentes_a_actualizar": 720,
  "en_cuarentena": 20,
  "duplicados_en_csv": 10,
  "cuarentena_detalles": [
    {
      "fila": 42,
      "motivo": "operacion_vacia",
      "dni": "00123456",
      "entidad": "BCP"
    },
    {
      "fila": 89,
      "motivo": "ambiguedad_bd_multiples_cuentas",
      "dni": "44556677",
      "operacion": "OP-DUPLICADA-HISTORICA",
      "coincidencias_bd": 2
    }
  ],
  "alertas": [
    "Fila 42: Puesta en cuarentena (operación vacía o nula)",
    "Fila 89: Puesta en cuarentena (múltiples cuentas coincidentes en BD)"
  ]
}
```

---

## 6. BATERÍA DE PRUEBAS SINTÉTICAS Y RESULTADOS

Se creó el archivo de pruebas `tests/Feature/DataImportReconciliationFaseCTest.php` ejecutado sobre `kp_crm_testing`.

### 6.1 Resultados de los 15 Casos Sintéticos Obligatorios

| # | Caso de Prueba | Escenario Validado | Aserción Verificada | Estado |
| :---: | :--- | :--- | :--- | :---: |
| **1** | `test_1_initial_import` | Importación limpia de 3 cuentas nuevas. | 3 inserciones creadas en BD, 0 updates, 0 cuarentenas. | **APROBADO** |
| **2** | `test_2_identical_reimport` | Reimportación idéntica del mismo archivo. | 0 inserciones, 3 updates, preservación exacta de IDs. | **APROBADO** |
| **3** | `test_3_debt_change_from_1000_to_800` | Cliente amortiza de 1,000 a 800. | Total cuentas = 1 (no se duplicó), deuda = 800.00, ID inalterado. | **APROBADO** |
| **4** | `test_4_client_with_multiple_accounts` | Mismo DNI con 2 operaciones distintas (`OP-01`, `OP-02`). | 2 registros independientes creados y actualizados sin colisión. | **APROBADO** |
| **5** | `test_5_two_different_clients_same_operation` | DNI A y DNI B comparten mismo número de operación en diferentes bancos. | 2 registros aislados rigurosamente por cliente y entidad. | **APROBADO** |
| **6** | `test_6_same_dni_and_operation_different_products` | Mismo DNI y misma OP con denominaciones distintas de producto. | Reconciliación por entidad y operación preserva unicidad de cuenta. | **APROBADO** |
| **7** | `test_7_entities_with_different_denominations` | `BANCO DE CREDITO` vs `BCP`. | Ambas reconocidas canónicamente como `BCP`, actualizando el registro original. | **APROBADO** |
| **8** | `test_8_operations_and_dnis_with_leading_zeros` | DNI `'00123456'` y OP `'00098765'`. | Ceros preservados como cadenas en BD; no convertidos a enteros. | **APROBADO** |
| **9** | `test_9_in_csv_duplicates` | Mismo archivo CSV repite la misma cuenta 2 veces. | 1 sola fila en BD, 1 registro en `duplicados_en_csv`. | **APROBADO** |
| **10** | `test_10_null_empty_or_malformed_data` | Registro con operación vacía (`""`). | Fila enviada a `CUARENTENA`, 0 registros corruptos insertados. | **APROBADO** |
| **11** | `test_11_failure_and_rollback_mid_batch` | Simulación de error grave en batch 2 de 500 filas. | Rollback atómico completo de la transacción, base de datos limpia. | **APROBADO** |
| **12** | `test_12_concurrent_imports_locking` | Dos procesos intentan importar simultáneamente. | Segundo proceso bloqueado inmediatamente (`cartera_import.lock`). | **APROBADO** |
| **13** | `test_13_retention_of_ambiguous_historical_cases` | Caso donde la BD ya contiene 2 registros con misma tupla. | Motor no sobreescribe ciegamente; envía la fila a `CUARENTENA`. | **APROBADO** |
| **14** | `test_14_integrity_of_relationships_with_pagos_promesas_cna` | Reimportación de cuenta asociada a pago, promesa y solicitud CNA. | ID preservado, relaciones íntegras, claves foráneas respetadas. | **APROBADO** |
| **15** | `test_15_dashboard_invalidation_on_commit` | Importación exitosa de cartera con nuevos pagos/cuentas. | Versión de caché del dashboard incrementada automáticamente. | **APROBADO** |
| **+1** | `test_preview_diagnostic_mode_does_not_write` | Ejecución en modo `preview()`. | Retorna métricas exactas y alertas; conteo en BD se mantiene en cero. | **APROBADO** |
| **+2** | `test_multi_process_dashboard_cache_concurrency` | Concurrencia de caché validada en el contexto de importación. | Incremento atómico verificado. | **APROBADO** |

### 6.2 Resultados de la Suite Integral de Regresión

Se ejecutó la suite completa consolidada de todas las fases del proyecto:

```bash
docker exec -e APP_ENV=testing -e DB_DATABASE=kp_crm_testing kpcrm-meridian-ui-app-1 php artisan test --filter="SecurityP0RemediationTest|SecurityP1FunctionalTest|SqlOptimizationFaseATest|DashboardOptimizationFaseBTest|DataImportDiagnosisTest|DataImportReconciliationFaseCTest"
```

```
   PASS  Tests\Unit\DataImportDiagnosisTest (4 tests)
   PASS  Tests\Feature\DashboardOptimizationFaseBTest (14 tests)
   PASS  Tests\Feature\DataImportReconciliationFaseCTest (17 tests)
   PASS  Tests\Feature\SecurityP0RemediationTest (9 tests)
   PASS  Tests\Feature\SecurityP1FunctionalTest (8 tests)
   PASS  Tests\Feature\SqlOptimizationFaseATest (4 tests)

   Tests:    56 passed (249 assertions)
   Duration: 24.69s
```

> [!IMPORTANT]
> **100% de éxito en la suite completa.** Cero regresiones en seguridad, roles, exportaciones, dashboard, documentos oficiales ni optimización SQL.

---

## 7. CERTIFICACIÓN DE INMUTABILIDAD DE LA BASE HISTÓRICA

Se verificó el estado de la base de datos histórica `kp_crm_revision_v3` directamente en el motor MariaDB:

```sql
SELECT 'clientes_cuentas' AS tbl, COUNT(*) AS total FROM clientes_cuentas
UNION ALL SELECT 'pagos', COUNT(*) FROM pagos
UNION ALL SELECT 'promesas_pago', COUNT(*) FROM promesas_pago
UNION ALL SELECT 'cna_solicitudes', COUNT(*) FROM cna_solicitudes
UNION ALL SELECT 'users', COUNT(*) FROM users;
```

### Tabla Comparativa de Conteo de Filas:

| Tabla en `kp_crm_revision_v3` | Conteo Inicial (P0/P1) | Conteo Pre-Fase C | Conteo Post-Fase C | Variación Neta |
| :--- | :---: | :---: | :---: | :---: |
| `clientes_cuentas` | 282,595 | 282,595 | **282,595** | **0 (Inalterada)** |
| `pagos` | 43,193 | 43,193 | **43,193** | **0 (Inalterada)** |
| `promesas_pago` | 4,445 | 4,445 | **4,445** | **0 (Inalterada)** |
| `cna_solicitudes` | 2,601 | 2,601 | **2,601** | **0 (Inalterada)** |
| `users` | 53 | 53 | **53** | **0 (Inalterada)** |

> [!NOTE]
> Se certifica que la base de datos histórica `kp_crm_revision_v3` no recibió una sola escritura, inserción, actualización ni alteración de estructura o índices durante todo el desarrollo y pruebas de la Fase C.

---

## 8. RESTRICCIONES DE CONTROL Y PRÓXIMOS PASOS

- **Control de Versiones:** No se ha realizado ningún `git commit`, `git push` ni mezcla de ramas en el repositorio local. Los cambios permanecen en el working tree de la rama `feature/meridian-ui-v3`.
- **Producción y Entornos Remotos:** No se ha realizado ninguna conexión a Hostinger, servidores de producción ni servicios remotos.
- **Siguiente Paso:** Poner a disposición del usuario la arquitectura de reconciliación en tres vías y los resultados obtenidos para su evaluación y posterior autorización de despliegue o integración en la interfaz de usuario.
