# KP INVEST CRM V3 — INFORME TÉCNICO FASE P1.1
## Seguridad Funcional, Estabilidad y Remediación Frontend

**Proyecto:** `C:\GitHub\KP-CRM-Meridian-ui`  
**Entorno Local:** `http://localhost:8091`  
**Base Histórica Protegida:** `kp_crm_revision_v3` (MariaDB 11.8.9)  
**Base Aislada de Pruebas:** `kp_crm_testing`  
**Stack Activo:** Laravel 10, PHP 8.3.36, Apache 2.4.69, Vite, Tailwind CSS (Meridian UI)  
**Fecha:** 01 de Noviembre de 2026  
**Estado:** Remedición completada con verificación automatizada. **Detenido para revisión; cero commits, cero push.**

---

## 1. PRESERVACIÓN ESTRICTA DE LA FASE P0

Todas las reglas y salvaguardas consolidadas en la entrega P0 se han mantenido intactas y verificadas:

1. **Cuentas Compartidas por Empresa:**
   - Multi-sesión concurrente ilimitada por usuario y por IP.
   - Sin desconexión de sesiones preexistentes ante nuevos inicios de sesión.
   - Sin bloqueos automáticos de cuentas o IPs por intentos fallidos que interrumpan a las agencias.
2. **Auditoría de Autenticación Ligera:**
   - Registro en `storage/logs/auth.log` con rotación (`DailyLogChannel`, 30 días) y sanitización estricta de credenciales (`[FILTERED]`).
3. **Acceso Global de Lectura:**
   - Asesores, supervisores, administradores y soporte pueden consultar cualquier cliente de cualquier cartera sin restricciones artificiales.
4. **Documentos Oficiales Exclusivamente Aprobados:**
   - Generación y descarga de Acuerdos de Pago y Cartas de No Adeudo (CNA) restringidas a `workflow_estado = 'aprobada'`.
5. **Eliminación Transaccional de Pagos:**
   - Autorizada para `administrador`, `supervisor` y `soporte`; prohibida para `asesor`.
   - Registro en log exclusivamente tras confirmación de eliminación en base de datos.
6. **Entorno de Ejecución:**
   - PHP 8.3.36 (construcción con parches críticos CVE-2026).
7. **Integridad de Base Histórica:**
   - Conteos en `kp_crm_revision_v3` verificados y salvaguardados al 100 % (`users`: 53, `pagos`: 43,193, `clientes_cuentas`: 282,595, `cna_solicitudes`: 2,601, `promesas_pago`: 4,445).

---

## 2. SEGURIDAD DE OPERACIONES FINANCIERAS

### 2.1 Validación Estricta de Operaciones por Cliente (`numdoc` vs `operacion`)
- **Problema previo:** `StorePromesaRequest` validaba que el arreglo `operaciones` fuera una lista de cadenas (`string|max:50`), pero no corroboraba que dichas operaciones pertenecieran efectivamente a la deuda registrada del cliente consultado (`numdoc = $dni`). Esto permitía asociar operaciones ajenas o inexistentes a una propuesta de pago.
- **Remediación en dos capas (Defense-in-Depth):**
  1. **Capa FormRequest (`app/Http/Requests/StorePromesaRequest.php`):**
     Se incorporó en `withValidator` una consulta a `clientes_cuentas` filtrando por `numdoc = $dni` y `whereIn('operacion', $operaciones)`. Cualquier discrepancia (`array_diff`) interrumpe el ciclo de validación retornando el error de formulario:
     `'Las operaciones seleccionadas no corresponden al cliente.'`
  2. **Capa de Dominio/Servicio (`app/Services/Promesa/PromesaCreationService.php`):**
     En `createFromRequest()`, antes de abrir la transacción de base de datos, se verifica que la totalidad de las operaciones seleccionadas pertenezcan al DNI en `clientes_cuentas`, lanzando una `\DomainException` defensiva en caso de discordancia.
  3. **Módulo CNA (`app/Services/Cna/CnaCreationService.php`):**
     Se verificó que la validación equivalente ya estuviera activa en la creación de CNA, garantizando que ninguna solicitud de no adeudo pueda emitirse sobre deudas que no pertenezcan al titular.

### 2.2 Auditoría de Transiciones y Autorización Backend
- Se verificó que todas las acciones de transición de estado en Promesas y CNA:
  - `ApprovePromesaAction`, `PreapprovePromesaAction`, `RejectPromesaAction`
  - `ApproveCnaAction`, `PreapproveCnaAction`, `RejectCnaAction`
- Ejecutan autorización rigurosa mediante `Gate::forUser($user)->authorize(...)` respaldada por `PromesaPolicy` y `CnaPolicy`, prohibiendo que usuarios asesores o sin privilegio ejecuten cambios de estado vía endpoints directos, con independencia de la visibilidad de controles en frontend.
- Los servicios de flujo (`PromesaWorkflowService` y `CnaWorkflowService`) validan el estado de origen antes de permitir transiciones (solo `pendiente` puede preaprobarse o rechazarse por supervisor; solo `preaprobada` puede aprobarse o rechazarse por administrador).

---

## 3. EXPORTACIONES Y ARCHIVOS (CSV/EXCEL FORMULA INJECTION)

### 3.1 Neutralización de Inyección de Fórmulas
- **Riesgo:** Si un campo de texto (nombres de cliente, glosas de negociación, notas, gestores, cuentas) importado desde fuentes externas o ingresado por usuarios comienza con los caracteres `=`, `+`, `-`, `@`, `\t` (tab) o `\r` (retorno de carro), aplicaciones de hojas de cálculo como Microsoft Excel y LibreOffice Calc lo interpretan como una fórmula ejecutable al abrir el archivo `.xlsx` o `.csv`.
- **Implementación de `ExportSanitizer` (`app/Support/ExportSanitizer.php`):**
  Se creó una clase de soporte reutilizable que detecta cadenas que inicien con dichos caracteres (incluso precedidos de espacios en blanco) y antepone un apóstrofe `'` (`'=` , `'+`, `'-`, `'@`). Esto fuerza al visor de hojas de cálculo a tratar la celda estrictamente como texto literal sin ejecutar código ni fórmulas.
- **Aplicación en exportadores:**
  - `app/Exports/CnaExport.php`
  - `app/Exports/PaymentExport.php`
  - `app/Exports/PromiseExport.php`
  Todas las celdas textuales son procesadas por `ExportSanitizer::sanitize()`.
- **Preservación de Datos:** Los valores primitivos numéricos (`float`, `int`) se mantienen inalterados como números en la hoja, y los datos originales en la base de datos MariaDB **no son alterados en absoluto**.

### 3.2 Corrección de Referencia Ambigua a `id` en `CnaExport`
- **Problema previo:** En `CnaExport.php`, la consulta ejecutaba `->orderByDesc('id')->chunkById(1000, ..., 'id')`. Dado que `CnaReportService` une `cna_solicitudes as cna` con `clientes_cuentas as cc` y `users as u_crea/u_pre/u_apr`, MariaDB arrojaba un error crítico: `Column 'id' in field list is ambiguous`.
- **Remediación:**
  1. En `app/Exports/CnaExport.php`, se especificó la calificación de tabla `cna.id` junto con el método nativo de Laravel `chunkByIdDesc`:
     ```php
     $this->query->chunkByIdDesc(1000, function ($items) use (&$row, $sheet) {
         // ...
     }, 'cna.id', 'id');
     ```
  2. En `app/Http/Controllers/ReporteCnaController.php` (línea 21), se actualizó el ordenamiento unívoco a `->orderByDesc('cna.id')`.

---

## 4. SEGURIDAD FRONTEND (HALLAZGOS AUDITORÍA)

| Código | Descripción del Hallazgo | Corrección Aplicada | Archivos Modificados |
| :--- | :--- | :--- | :--- |
| **`FE-SEC-01`** | Volcado masivo y duplicado de PII e historial financiero en atributos HTML `data-*` | Se eliminaron los atributos redundantes `data-crono-b64`, `data-cuentas-b64`, `data-operaciones-b64` y el volcado masivo `data-pagos-b64`. El detalle de cronogramas y cuentas se parsea de forma segura desde los atributos JSON limpios existentes, y los pagos de CNA se consultan bajo demanda al abrir la ficha (`GET /autorizacion/pagos/{dni}`). En `clientes/show.blade.php` se eliminaron los atributos huérfanos `data-cuenta` y `data-cna-reason`. | `resources/views/autorizacion/index.blade.php`<br>`resources/views/clientes/show.blade.php`<br>`resources/js/modules/autorizacion/index.js` |
| **`FE-SEC-04`** | Serialización JSON cruda `{!! json_encode(...) !!}` sin `JSON_HEX_TAG` en bloques `<script>` | Se reemplazó `{!! json_encode(...) !!}` por la directiva segura `@json(...)`, previniendo Stored XSS derivado de etiquetas `</script>` en nombres o valores exportados al gráfico. | `resources/views/panel/resumen.blade.php`<br>`resources/views/dashboard/index.blade.php` |
| **`FE-SEC-05`** | Redirecciones DOM (`window.location.href`) desde atributos sin validación de mismo origen | Se implementó el helper central `toSafeInternalUrl(rawUrl)` en `core/http.js` validando `parsed.origin === window.location.origin` y protocolos seguros (`http:`, `https:`). Se aplicó en las redirecciones de notificaciones y en los enlaces a clientes de autorizaciones. | `resources/js/core/http.js`<br>`resources/js/modules/layout/notifications.js`<br>`resources/js/modules/autorizacion/index.js`<br>`resources/js/modules/layout/client-search.js` |
| **`FE-SEC-06`** | Cabeceras de caché débiles en descargas privadas de documentos | Se aseguraron las cabeceras `'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0'` y `'Pragma' => 'no-cache'` en todas las salidas de documentos de `CnaDocumentService`, `PromesaDocumentService` y `CcdDocumentController`. | `app/Services/Cna/CnaDocumentService.php`<br>`app/Services/Promesa/PromesaDocumentService.php`<br>`app/Http/Controllers/CcdDocumentController.php` |

---

## 5. ESTABILIDAD FUNCIONAL (HALLAZGOS AUDITORÍA)

| Código | Descripción del Hallazgo | Corrección Aplicada | Archivos Modificados |
| :--- | :--- | :--- | :--- |
| **`FE-FUNC-01`** | Condición de carrera con `DOMContentLoaded` en scripts diferidos (`type="module"`) | Se reemplazó `document.addEventListener('DOMContentLoaded', ...)` por el helper central `ready(...)` en los módulos de analítica y administración de usuarios, garantizando su inicialización incluso si el bundle termina de evaluarse tras dispararse el evento nativo. | `resources/js/modules/dashboard/stats.js`<br>`resources/js/modules/admin/users.js` |
| **`FE-FUNC-02`** | Fallo silencioso de peticiones AJAX al expirar la sesión por inactividad (`401`, `419`, `302 /login`) | Se creó la función `checkAuthResponse(response)` en `core/http.js` que detecta códigos `401`, `419` o redirecciones hacia `/login`. Al expirar, redirige automáticamente a `/login?expired=1`. Se añadió en `auth/login.blade.php` un banner informativo contextual en tono ámbar Meridian. | `resources/js/core/http.js`<br>`resources/js/modules/layout/notifications.js`<br>`resources/js/modules/layout/client-search.js`<br>`resources/js/modules/autorizacion/index.js`<br>`resources/views/auth/login.blade.php` |
| **`FE-FUNC-05`** | Corrupción de entidades HTML en buscador global y race conditions por peticiones desincronizadas | 1. Se reescribió `highlight(text, query)` en `client-search.js` para buscar coincidencias sobre el texto plano unescaped antes de escapar individualmente los fragmentos con `escapeHtml()`, impidiendo que caracteres como `&` o `'` se corrompan.<br>2. Se incorporó `AbortController` tanto en `client-search.js` como en `setupCnaDetail()` para cancelar peticiones anteriores en vuelo cuando el usuario teclea rápido o navega entre fichas. | `resources/js/modules/layout/client-search.js`<br>`resources/js/modules/autorizacion/index.js` |
| **`FE-FUNC-06`** | Envíos duplicados y ausencia de estado de carga en modales de decisión y administración | Se implementó el helper global `initSubmitOnce(root)` en `core/forms.js` inicializado desde `app.js`. Se añadieron los atributos `data-once` y `data-loading-text` a los formularios de decisión de autorizaciones y formularios de usuarios (creación, edición y contraseña) para bloquear el botón y mostrar retroalimentación inmediata durante la espera de backend/iLovePDF. | `resources/js/core/forms.js`<br>`resources/js/app.js`<br>`resources/views/components/autorizacion/decision-modal.blade.php`<br>`resources/views/placeholders/administracion.blade.php` |
| **`FE-FUNC-07`** | Botones de envío bloqueados permanentemente al navegar hacia atrás (`bfcache`) | Se registraron listeners para el evento `window.addEventListener('pageshow', (e) => { if (e.persisted) ... })` en `core/forms.js` y `modules/auth/login.js`, restableciendo botones deshabilitados y banderas de estado cuando el navegador restaura la vista desde la caché de navegación. | `resources/js/core/forms.js`<br>`resources/js/modules/auth/login.js` |

---

## 6. ANÁLISIS DE UNICIDAD DE CUENTAS (`clientes_cuentas`)

### 6.1 Estado Actual del Esquema y Definición de Claves
La tabla `clientes_cuentas` en `kp_crm_revision_v3` presenta la siguiente estructura de índices:
- `PRIMARY KEY (id)`
- `KEY idx_cc_numdoc_search (numdoc, entidad, moneda, deuda_total, deuda_capital)`
- `KEY wdbi_operacion_e79562a6c235 (operacion)`
- `KEY wdbi_operacion_numdoc_updated_at_091ddf0ba4ea (operacion, numdoc, updated_at)`

Las columnas clave son `numdoc varchar(30) DEFAULT NULL`, `operacion varchar(50) DEFAULT NULL` y `deuda_capital decimal(18,2) DEFAULT NULL`.

### 6.2 Comportamiento de MariaDB ante Valores `NULL` en Índices Únicos
De acuerdo con el estándar SQL y la implementación del motor InnoDB en MariaDB:
> **Regla de nulidad:** Un índice `UNIQUE (col1, col2, col3)` **no** considera los valores `NULL` como duplicados (`NULL != NULL`).
Si una fila contiene `operacion IS NULL` o `deuda_capital IS NULL`, MariaDB permitirá insertar ilimitadas filas idénticas con los mismos valores nulos sin disparar violación de unicidad.

### 6.3 Riesgos de Unicidad con `deuda_capital` y Duplicación de Saldos
Si la unicidad se define rígidamente como `UNIQUE (numdoc, operacion, deuda_capital)`:
1. **Duplicación por amortización o recálculo:**  
   Cuando un cliente abona una cuota o se realiza una nueva importación mensual de cartera donde el saldo amortizó (p. ej. de `S/ 5,000.00` a `S/ 4,800.00`), el nuevo registro tiene una `deuda_capital` diferente. Al no coincidir los 3 campos, el motor inserta una nueva fila en lugar de actualizar la existente.  
2. **Consecuencia:** La ficha del cliente mostrará dos cuentas con el mismo número de operación, sumando incorrectamente el saldo dos veces en los totales de cartera y en las tarjetas analíticas del dashboard.

### 6.4 Propuesta de Algoritmo Seguro de Importación (Sin Migraciones ni Modificación de BD)
Para fases operativas futuras, la estrategia recomendada para importar carteras sin alterar la base histórica ni generar inconsistencias consiste en:

```
[Archivo CSV de Cartera]
         │
         ▼
[Validación Sintáctica y Tipado en Memoria (Batch)]
  - Normalización de numdoc (trim, ltrim de ceros, formato DNI/RUC)
  - Limpieza de operacion (coalescencia de nulos a cadena no vacía identificable)
  - Coalescencia de deuda_capital (null -> 0.00)
         │
         ▼
[Tabla Temporal de Staging (Memory / Temp Table)]
  `staging_import_cuentas (batch_id, numdoc, operacion, entidad, producto, cosecha, deuda_capital, ...)`
         │
         ▼
[Deduplicación Intra-Archivo]
  - Selección de la fila más reciente o de mayor saldo por tupla (numdoc, operacion)
         │
         ▼
[Upsert Controlado sobre clientes_cuentas]
  INSERT INTO clientes_cuentas (numdoc, operacion, entidad, producto, cosecha, moneda, deuda_capital, deuda_total, ...)
  VALUES (...)
  ON DUPLICATE KEY UPDATE
      deuda_capital = VALUES(deuda_capital),
      deuda_total   = VALUES(deuda_total),
      entidad       = VALUES(entidad),
      producto      = VALUES(producto),
      cosecha       = VALUES(cosecha),
      updated_at    = NOW();
```
*Nota: Este análisis es estrictamente documental; no se ejecutó ninguna migración ni se alteró `DataImport` en cumplimiento con las instrucciones del usuario.*

---

## 7. VERIFICACIÓN Y PRUEBAS AUTOMATIZADAS

### 7.1 Nueva Suite de Pruebas `SecurityP1FunctionalTest`
Se ejecutó la suite completa en el entorno de pruebas aislado `kp_crm_testing`:
- `test_promesa_creation_rejects_operations_not_belonging_to_client`: **PASSED** (Rechazo inmediato de promesas con operaciones de terceros clientes).
- `test_promesa_creation_succeeds_when_operations_belong_to_client`: **PASSED** (Aprobación limpia cuando las operaciones corresponden al cliente).
- `test_cna_creation_rejects_operations_not_belonging_to_client`: **PASSED** (Rechazo de solicitudes CNA con operaciones inexistentes o de terceros).
- `test_export_sanitizer_neutralizes_formula_injection_characters`: **PASSED** (Neutralización de `=`, `+`, `-`, `@`, `\t`, `\r` y preservación de primitivos numéricos).
- `test_cna_export_runs_without_ambiguous_column_id_sql_error`: **PASSED** (Generación de reporte Excel de CNA sin ambigüedad en `cna.id`).
- `test_unapproved_cna_document_download_is_forbidden`: **PASSED** (`403 Forbidden` al intentar descargar cartas CNA en estado no aprobado).

**Resultado:** `OK (6 tests, 23 assertions) - 100% aprobado`.

### 7.2 Regresión P0 `SecurityP0RemediationTest`
Se verificaron los 9 casos de prueba de la primera entrega:
- Aislamiento multi-sesión concurrente de 6 clientes simultáneos con logout independiente: **PASSED**.
- Prevención de enumeración de usuarios: **PASSED**.
- Eliminación de pagos transaccional con control de roles: **PASSED**.
- Consulta global de carteras sin restricciones: **PASSED**.
- Bloqueo de acuerdos y CNA en estado no aprobado: **PASSED**.

**Resultado:** `OK (9 tests, 72 assertions) - 100% aprobado sin regresiones`.

### 7.3 Verificación de Assets Compilados (Vite)
- Ejecutado `npm run build` en local:
  - `public/build/assets/app-B6LF3UOT.css` (`60.17 kB` / `11.26 kB` gzip).
  - `public/build/assets/app-CD3b27WY.js` (`340.66 kB` / `111.71 kB` gzip).
  - Transpilación de 36 módulos sin errores sintácticos.

---

## 8. CONCLUSIÓN Y SIGUIENTES PASOS

La Fase P1.1 ha sido implementada y validada en su totalidad con éxito:
1. No se modificó ninguna fila en la base histórica `kp_crm_revision_v3`.
2. Se mantuvieron al 100 % las decisiones de negocio de P0 (cuentas compartidas, login libre de bloqueos automáticos, acceso global y PHP 8.3.36).
3. Se blindó la vinculación financiera de deudas en promesas y CNA.
4. Se erradicó la inyección de fórmulas CSV/Excel y el error de columna ambigua en reportes.
5. Se corrigieron los hallazgos frontend críticos y de seguridad (`FE-SEC-01`, `04`, `05`, `06` y `FE-FUNC-01`, `02`, `05`, `06`, `07`).

> [!IMPORTANT]
> **RESTRICCIÓN ACTIVA:** Conforme a las instrucciones, la ejecución se ha **detenido deliberadamente** para revisión del usuario. **No se ha ejecutado ningún commit ni push.**
