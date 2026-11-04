# DIAGNÓSTICO TÉCNICO Y DISEÑO DE ARQUITECTURA: FASE C — DATAIMPORT Y RECONCILIACIÓN (UPSERT)
**Proyecto:** KP INVEST CRM V3 (`C:\GitHub\KP-CRM-Meridian-ui`)  
**Rama:** `feature/meridian-ui-v3`  
**Entorno de Análisis:** Docker Local (`http://localhost:8091` / PHP 8.3.36 / MariaDB 11.8.9)  
**Base Histórica Evaluada:** `kp_crm_revision_v3` (Modo Solo Lectura Estricto)  
**Base de Pruebas:** `kp_crm_testing`  
**Fecha:** Noviembre 2026  
**Fase:** P1.2 — Preparación y Diseño Técnico de Fase C

---

## 1. RESUMEN DEL DIAGNÓSTICO

La importación masiva de carteras (`app/Imports/DataImport.php`) es el proceso crítico que alimenta la base de clientes y deudas de KP INVEST CRM V3 (`clientes_cuentas`, actualmente con **282,595 registros**).

El análisis técnico profundo del código actual y de los datos históricos reales en MariaDB reveló un **riesgo arquitectónico severo**:

> [!WARNING]
> **El mito del `upsert` sin índice UNIQUE en MariaDB:**  
> En `app/Imports/DataImport.php` (línea 123) se ejecuta:
> ```php
> DB::table('clientes_cuentas')->upsert($batch, ['numdoc', 'operacion'], $updateCols);
> ```
> En MySQL / MariaDB, el método `upsert()` de Laravel compila la sentencia SQL:  
> `INSERT INTO clientes_cuentas (...) VALUES (...) ON DUPLICATE KEY UPDATE ...`  
> El segundo argumento `['numdoc', 'operacion']` es **completamente ignorado por el compilador de MySQL de Laravel** (solo aplica para PostgreSQL/SQLite). En MariaDB, la cláusula `ON DUPLICATE KEY UPDATE` **únicamente se activa si existe una restricción `PRIMARY KEY` o `UNIQUE` en la tabla**.  
> Como la tabla `clientes_cuentas` **no tiene ningún índice UNIQUE**, **cada reimportación de un archivo CSV inserta el 100% de las filas como registros nuevos duplicados**, inflando artificialmente las carteras y duplicando las deudas de los clientes.

---

## 2. AUDITORÍA EMPÍRICA DE DATOS HISTÓRICOS (`kp_crm_revision_v3`)

Se ejecutaron consultas de diagnóstico de solo lectura sobre los 282,595 registros históricos de `kp_crm_revision_v3`:

### A. Clientes con Múltiples Cuentas Legítimas
- **Total de clientes con más de una cuenta:** **18,178 clientes**.
- **Regla de negocio:** Un mismo cliente (`numdoc`) posee legítimamente múltiples productos o préstamos en la misma o en distintas entidades financieras (ej. Tarjeta de Crédito, Préstamo Personal, Sobregiro).
- **Conclusión:** `numdoc` **nunca es único** por sí solo en la tabla `clientes_cuentas`.

### B. Mismo Código de Operación en Clientes Diferentes
- **Operaciones compartidas por clientes distintos:** **962 códigos de operación** pertenecen a 2 o más personas con distinto DNI.
- **Causa bancaria:** Distintas entidades (ej. Banco Pichincha y Financiera Confianza) generan numeraciones correlativas internas independientes que coinciden numéricamente.
- **Conclusión:** `operacion` **nunca es única globalmente**.

### C. Duplicados Históricos de `(numdoc, operacion)`
Al buscar duplicados exactos del par `(numdoc, operacion)` en los 282,595 registros, se hallaron únicamente **5 casos históricos**, los cuales revelan la complejidad de negocio:

| Numdoc | Operación | Entidad | Cuenta | Capital Fila A | Capital Fila B | Diagnóstico de Negocio |
| :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| `73111269` | `12213625002` | WANDOO | `73111269` | S/ 567.74 | S/ 134.01 | **Múltiples tramos/desembolsos** del mismo micropréstamo. |
| `41792795` | `6018870` | FINANCIERA CONFIANZA | `1021109` | S/ 11,625.84 | S/ 11,625.84 | Fila A: `C. JUDICIAL`, Fila B: `PREJUDICIAL` (**cambio de etapa**). |
| `02272384` | `8650762` | FINANCIERA CONFIANZA | `4101016` | S/ 5,728.38 | S/ 5,728.38 | Fila A: `JUDICIAL SIN DEMANDA`, Fila B: `CARTERA CASTIGADA`. |
| `01488877` | `8673513` | FINANCIERA CONFIANZA | `4210411` | S/ 6,055.96 | S/ 6,055.96 | Fila A: `JUDICIAL SIN DEMANDA`, Fila B: `CARTERA CASTIGADA`. |
| `74015899` | `7363864` | FINANCIERA CONFIANZA | `3844234` | S/ 190.20 | S/ 190.20 | **Reimportación duplicada idéntica** (21/10/2025 y 19/11/2025). |

### D. Ceros a la Izquierda en DNI y Operaciones
- **DNIs con ceros a la izquierda (`numdoc LIKE '0%'`):** **34,579 registros**. (Común en departamentos como Loreto y Piura).
- **Operaciones con ceros a la izquierda (`operacion LIKE '0%'`):** **3,928 registros**.
- **Regla estricta:** Si el importador o el formateo numérico convierte estos valores a entero (`int`), el DNI `'01488877'` se convierte en `'1488877'`, rompiendo la búsqueda del cliente y la vinculación con promesas, pagos y CNA.

### E. Operaciones Vacías o Nulas
- **Registros con DNI u operación vacía/nula en histórico:** **0 registros**.
- La base histórica no admite registros sin identificación; cualquier fila entrante sin `numdoc` u `operacion` debe ser descartada o retenida en cuarentena.

---

## 3. CONFLICTO CRÍTICO: POR QUÉ `UNIQUE(numdoc, operacion, deuda_capital)` ES INVIABLE

Una propuesta previa sugería crear una restricción `UNIQUE (numdoc, operacion, deuda_capital)` para habilitar el `upsert`. 

El análisis técnico demuestra que esta clave compuesta es **conceptualmente errónea y destructiva**:

```
[ Cartera Mes 1 ] ──► Inserta: DNI 77770001, OP-100, Capital S/ 1,000.00
                      Clave en BD: (77770001, OP-100, 1000.00)

[ Cartera Mes 2 ] ──► Llega: DNI 77770001, OP-100, Capital S/ 800.00 (El cliente amortizó S/ 200)
                      Clave entrante: (77770001, OP-100, 800.00)

[ Conflicto en MariaDB ]
¿Coincide la clave UNIQUE? NO. (77770001, OP-100, 1000.00) != (77770001, OP-100, 800.00)
ACCION DE MARIADB: INSERTA UNA SEGUNDA FILA.

[ Resultado en el CRM ]
El cliente ahora tiene DOS cuentas para la misma deuda:
- Fila 1: OP-100, Capital S/ 1,000.00
- Fila 2: OP-100, Capital S/ 800.00
¡La deuda mostrada en el CRM pasa a ser S/ 1,800.00 en lugar de actualizarse a S/ 800.00!
```

> [!CAUTION]
> `deuda_capital` es un **atributo financiero mutable**, jamás un identificador de identidad. Incluir montos de deuda en una clave de unicidad corrompe los saldos de los clientes en cada amortización.

---

## 4. DISEÑO TÉCNICO PROPUESTO PARA FASE C

Para lograr una importación masiva que sea **idempotente, atómica, veloz y segura**, se propone un modelo de **Reconciliación en 3 Vías** basado en una tabla temporal de staging en memoria o intermedia:

```
                  [ Archivo CSV de Cartera ]
                              │
                              ▼
        [ Etapa 1: Pre-validación y Sanitización ]
        ├── Preserva ceros a la izquierda (Strings estrictos)
        ├── Limpieza de inyección CSV / Fórmulas
        └── Detección de filas malformadas (DNI u Op vacía)
                              │
               ┌──────────────┴──────────────┐
               ▼                             ▼
     [ Filas Válidas ]             [ Filas con Errores ]
               │                             │
               ▼                             ▼
[ Etapa 2: Staging / Memoria ]       [ Bitácora / Cuarentena ]
               │
               ▼
   [ Reconciliación en 3 Vías ]
   (Identidad Lógica: entidad + numdoc + operacion)
   ──────────────────────────────────────────────────────────
   1. INSERT:   No existe en BD para la entidad y cliente.
   2. UPDATE:   Existe exactamente 1 registro coincidente.
                Actualiza: deuda_capital, interes, deuda_total,
                producto, cuenta, cosecha, fechas, updated_at.
   3. RETENCIÓN: Existen 2 o más registros con la misma operación
                (como los 5 casos históricos de Wandoo/Confianza),
                o inconsistencia de titular.
                              │
                              ▼
      [ Etapa 3: Commit Atómico en Transacción DB ]
                              │
                              ▼
      [ Invalidation: PanelDashboardService::invalidate() ]
```

### Reglas de Clasificación de la Reconciliación:

1. **Vía 1 — INSERCIÓN (Nuevas Cuentas):**
   - Si para la tupla `(entidad, numdoc, operacion)` no existe ningún registro previo en `clientes_cuentas`, se inserta como una cuenta nueva.
2. **Vía 2 — ACTUALIZACIÓN (Cuentas Existentes Inequívocas):**
   - Si para `(entidad, numdoc, operacion)` existe exactamente **un registro en la base de datos**, se actualizan sus saldos (`deuda_capital`, `deuda_total`, `interes`), datos de contacto, producto y etapa judicial. No se crea una fila nueva.
3. **Vía 3 — RETENCIÓN EN CUARENTENA (Casos Ambiguos):**
   - Si existen registros duplicados históricos para ese mismo `(entidad, numdoc, operacion)` (como los 5 casos de Wandoo y Financiera Confianza), o si la fila entrante carece de `operacion`, la fila no se sobreescribe a ciegas. Se envía a un reporte de excepciones para revisión del supervisor sin interrumpir el lote.

---

## 5. DEMOSTRACIÓN MEDIANTE PRUEBAS SINTÉTICAS

En [`tests/Unit/DataImportDiagnosisTest.php`](file:///C:/GitHub/KP-CRM-Meridian-ui/tests/Unit/DataImportDiagnosisTest.php) se implementaron y aprobaron al 100% las pruebas de laboratorio que certifican estos principios:

1. `test_upsert_without_unique_index_duplicates_rows_in_mariadb`: Demuestra empíricamente que ejecutar `upsert()` con el esquema actual genera 2 filas duplicadas en MariaDB.
2. `test_why_unique_on_deuda_capital_conflicts_with_debt_updates`: Demuestra cómo una clave única basada en deuda capital divide la cuenta al amortizar.
3. `test_leading_zeros_preservation_in_import_data`: Demuestra que `01488877` y `0008673513` se recuperan intactos como cadenas de texto.
4. `test_three_way_reconciliation_logic`: Demuestra la correcta segregación algorítmica entre `INSERT`, `UPDATE` y `QUARANTINE`.

---

## 6. INTEGRACIÓN CON EL DASHBOARD

Al finalizar la carga masiva en `DataImport`, se invocará de manera transaccional:

```php
if ($ok > 0) {
    \App\Services\Panel\PanelDashboardService::invalidate();
}
```

Gracias a la implementación de `afterCommit` y bloqueo atómico con `flock(LOCK_EX)` consolidada en la Fase B, el Dashboard invalidará sus métricas inmediatamente después de confirmarse la transacción de importación, garantizando que los supervisores y directores visualicen los saldos frescos sin demoras ni lecturas sucias.

---

## 7. DECLARACIÓN DE DETENCIÓN FORMAL

En estricto cumplimiento de las restricciones acordadas:
- **No se han ejecutado modificaciones de escritura en `app/Imports/DataImport.php` ni en la base histórica `kp_crm_revision_v3`.**
- **No se han alterado índices de base de datos ni ejecutado migraciones.**
- El presente documento constituye el diagnóstico integral y la propuesta de arquitectura para la revisión y aprobación conjunta antes de iniciar la implementación de la Fase C.
