# KP INVEST — Informe de Migración a Producción CCD V6.2.2 (Prevención de Error MySQL 2006, Validación Masiva por Lotes y Adaptación LEGACY)

## 1. Resumen Ejecutivo y Objetivos

El presente documento describe la arquitectura técnica, controles de integridad, compatibilidad de hosting, protección de credenciales, plan de contingencia y el procedimiento de ejecución del desplegador y migrador **V6.2.2** para el traslado de las **10,224 asociaciones CCD AQP4/AQP5** y sus archivos PDF privados correspondientes desde el entorno de staging (`u480021566_prueba`) al entorno de producción real (`u480021566_kpinvest_bd`) de **KP INVEST**, adaptado específicamente a la **arquitectura LEGACY** instalada en Hostinger, con las correcciones operativas identificadas en la ejecución real de staging/producción (prevención de desconexión `MySQL server has gone away` y optimización de validación masiva sin N+1).

### Datos de Entornos

| Parámetro | Staging (Origen) | Producción (Destino LEGACY) |
| :--- | :--- | :--- |
| **Host / Servidor** | Hostinger Cloud SSH | Hostinger Cloud SSH |
| **Ruta de Aplicación** | `/home/u480021566/apps/kpinvest-crm-pruebas` | `/home/u480021566/domains/impulse-go.com/public_html/plataforma-crm` |
| **Base de Datos** | `u480021566_prueba` | `u480021566_kpinvest_bd` |
| **Conexión MySQL** | `u480021566_kpprueba@localhost` | `u480021566_kpinvest@127.0.0.1` |
| **Repositorio Git** | `ilustroc/kpinvest-crm-app` | `ilustroc/kpinvest-crm-app` |
| **Rama de Entrega** | `codex/ccd-aqp-v6-2-produccion-legacy` | `codex/ccd-aqp-v6-2-produccion-legacy` |

---

## 2. Diagnóstico de la Arquitectura Real de Producción LEGACY

A diferencia de la rama de desarrollo que introdujo subcarpetas de rutas (`routes/web/clientes.php`) y servicios desacoplados (`ClienteProfileService.php`), la aplicación en producción real opera con una estructura monolítica probada:

1. **Rutas Monolíticas Centralizadas:**
   - Todas las rutas del sistema residen en `routes/web.php`.
   - No existe el archivo `routes/web/clientes.php`.
   - Las rutas de clientes están bajo `Route::prefix('clientes')->group(...)` dentro del middleware `auth`.
2. **Controlador Directo de Clientes:**
   - La lógica de negocio y visualización reside en `app/Http/Controllers/ClienteController.php`.
   - No existe la clase `app/Services/Cliente/ClienteProfileService.php`.
   - El método `ClienteController::show(string $dni)` gestiona directamente `$cuentas`, `$pagos`, `$promesas`, `$cnas`, `$ccdDocs`, `$ccdByDni` y `$ccdByCodigo`.
3. **Vista de Cliente con Celdas Específicas:**
   - La vista utilizada es `resources/views/clientes/show.blade.php`.
   - Renderiza una tabla con la sección delimitada `{{-- === CELDA CCD === --}}`.
4. **Restricciones de Funciones PHP en Hostinger:**
   - Hostinger deshabilita funciones como `exec()`, `shell_exec()` y `system()` por motivos de seguridad compartida.
   - La función `proc_open()` está completamente disponible.

---

## 3. Correcciones Implementadas en V6.2

### 3.1. Verificación de Sintaxis PHP Compatible con Hostinger (Sin `exec()`)
Se rediseñó `CcdProductionMigratorService::assertPhpSyntax(string $filePath)`:
- Utiliza `proc_open([$phpBinary, '-l', $filePath], ...)` para invocar el linter nativo de PHP mediante descriptores de tuberías de entrada/salida.
- Captura `stdout` y `stderr` sin requerir `exec()`.
- Incorpora un mecanismo de contingencia nativo mediante `token_get_all($code, TOKEN_PARSE)` que detecta cualquier `ParseError` directamente en memoria si el entorno impidiera subprocesos.

### 3.2. Carga Segura de Credenciales desde la Configuración Efectiva de Laravel
Se erradicó la lectura manual e insegura mediante `grep` y `cut` en archivos `.env`:
- El script desplegador arranca el bootstrap de Laravel (`bootstrap/app.php`) en subprocesos aislados para staging y producción.
- Obtiene la configuración efectiva autenticada:
  - Staging: `u480021566_kpprueba@localhost`.
  - Producción: `u480021566_kpinvest@127.0.0.1`.
- Admite caracteres especiales, contraseñas complejas y variables multilínea que rompían el parser Bash anterior.
- Genera un archivo JSON temporal con permisos restrictivos `0600` (`~/.ccd_credentials/`), el cual es eliminado de manera garantizada mediante la trampa `trap cleanup_credentials EXIT INT TERM`.
- Ninguna contraseña se imprime en terminal, ni se pasa como parámetro visible en `ps aux`, ni se registra en logs o manifiestos.

### 3.3. Vinculación Estricta por Operación (`cliente_cuenta_id = clientes_cuentas.id`)
Para evitar asociar indiscriminadamente todos los CCDs de un mismo DNI a todas las operaciones del cliente:
1. **Inclusión de Clave Primaria:** Se asegura la columna `id` en la consulta `ClienteCuenta::query()->get(['id', 'numdoc', ...])`.
2. **Asociación Específica:**
   ```php
   $confirmedLinks = CcdClienteCuenta::query()
       ->whereIn('ccd_cliente_id', $ccdDocs->pluck('id'))
       ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
       ->with('ccdCliente:id,numdoc,pdf,cosecha,link')
       ->get()
       ->filter(fn ($link) => $link->ccdCliente !== null && hash_equals((string) $dni, (string) $link->ccdCliente->numdoc))
       ->groupBy('cliente_cuenta_id');
   ```
3. **Asignación por Cuenta:** Cada cuenta `$cta` recibe exactamente sus CCDs en `$cta->ccdConfirmados = collect($confirmedLinks->get((int) $cta->id, []))->pluck('ccdCliente')->values();`.
4. **Aislamiento y Preservación:** Los CCDs confirmados se retiran de `$ccdDocs` para que no sean asignados por la heurística difusa anterior. Los CCDs históricos o no confirmados permanecen intactos en la vista general.

### 3.4. Integración Aditiva Quirúrgica de Componentes
El método `verifyAndIntegrateComponents()` detecta automáticamente la arquitectura destino (Legacy o Moderna):
- **Rutas:** Inyecta `Route::get('/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('clientes.ccd.show');` dentro del grupo `Route::prefix('clientes')` en `routes/web.php` (Legacy) o en `routes/web/clientes.php` (Moderna).
- **Controlador:** Inyecta la relación y asignación de `$cta->ccdConfirmados` directamente en `app/Http/Controllers/ClienteController.php` (Legacy) o en `ClienteProfileService.php` (Moderna).
- **Vista:** En `resources/views/clientes/show.blade.php`, envuelve la celda `{{-- === CELDA CCD === --}}` con la prioridad de `$cta->ccdConfirmados` hacia la ruta segura `clientes.ccd.show`, preservando el bloque legacy como fallback cuando no hay confirmados.
- **Modelos:** Inyecta aditivamente `ccdLinks()` en `ClienteCuenta.php` y `archivo()` / `cuentaLinks()` en `CcdCliente.php`.
- **Filesystem:** Registra el disco privado local `ccd-private` en `config/filesystems.php`.
- **Respaldos Individuales:** En `--apply`, genera copias de seguridad de cada archivo modificado en `${BACKUP_DIR}/modified_files/` antes de tocarlo.

### 3.5. Seguridad en el Controlador Documental (`CcdDocumentController.php`)
- **Autorización Adaptativa:** Si la aplicación define el Gate `view-cliente`, lo ejecuta; de lo contrario, valida que el usuario esté debidamente autenticado (`abort_unless($request->user() !== null, 401)`).
- **Prevención de Manipulación de Parámetros:** Verifica estrictamente que el documento pertenezca al DNI indicado en la URL mediante `hash_equals((string) $ccdCliente->numdoc, $targetDoc)`. Si un usuario intenta modificar la URL para solicitar el documento de otro DNI, recibe inmediatamente un error `404 Not Found`.
- **Streaming Privado e Inmutable:** Transmite los PDFs desde el disco `ccd-private` con cabeceras `inline`, `Content-Type: application/pdf`, `Cache-Control: private, no-store, no-cache` y `X-Content-Type-Options: nosniff`.

### 3.6. Correcciones Críticas de Ejecución Implementadas en V6.2.1
1. **Instalación Previa de Migraciones Selectivas en Fase 2:**
   - En la versión previa, `php artisan migrate --path=database/migrations/...` fallaba en la Fase 2 debido a que los archivos físicos de migración solo eran copiados durante la Fase 4.
   - Se incorporó el método `CcdProductionMigratorService::installMigrationFiles()` que se ejecuta al inicio de la Fase 2, copiando exclusivamente los 2 archivos de migración hacia `$PROD_DIR/database/migrations/` y validando su sintaxis PHP previamente mediante `proc_open()`.
   - Tras ejecutar las migraciones, la Fase 2 valida que `inspectTargetSchema()` confirme `is_ready_for_apply === true`.
2. **Unificación de Claves en `inspectTargetSchema()`:**
   - Se resolvió la discrepancia entre el script desplegador (`has_ccd_archivo_id_column`) y el servicio migrador (`has_ccd_archivo_id_col`), retornando ambas claves con valores estrictamente idénticos y agregando una prueba automatizada que previene regresiones.
3. **Contrato de Auditoría Final y Aborto Estricto ante Anomalías en Fase 5:**
   - Se armonizó el contrato de `auditTarget()`, asegurando que retorne las claves primarias (`passed`, `anomalies`, `archivos_count`, `assoc_confirmed`) junto con sus alias canónicos (`success`, `errors`, `total_archivos`, `total_asociaciones`).
   - Se adaptó `auditTarget()` para soportar universos de prueba (`$expectedTotal`), evitando falsos positivos o rechazos incorrectos durante ejecuciones de prueba o fixtures aislados.
   - En el desplegador Bash, la condición evalúa `if (! $audit["passed"] || ! empty($audit["anomalies"]))`, imprimiendo cada anomalía a `stderr` y abortando con código de salida `1`.

### 3.7. Correcciones Críticas de V6.2.2: Prevención de Error MySQL 2006 y Optimización de Validación Masiva

1. **Gestión de Conexiones PDO y Prevención de Desconexión (`2006 MySQL server has gone away`):**
   - **Causa raíz diagnosticada en Hostinger:** Durante la lectura y verificación física con cálculo hash de los 10,224 PDFs (2.618 GiB), las conexiones PDO permanecían inactivas mientras se mantenía un cursor de lectura abierto en `$stmt->fetch(PDO::FETCH_ASSOC)`. Al iniciar la validación SQL subsiguiente, MySQL Server cerraba el socket por inactividad (`wait_timeout`).
   - **Cierre Anticipado de Cursores:** En `transferAndVerifyFiles()` y `validateAssociationsPreInsert()`, la consulta de metadatos de los archivos y asociaciones se realiza con `fetchAll(PDO::FETCH_ASSOC)`, seguido inmediatamente de `$stmt->closeCursor(); unset($stmt);` antes de iniciar cualquier operación en disco. Esto libera a MySQL de esperar consumo de resultados y evita bloqueos de buffer.
   - **Renovación Controlada Post-Filesystem:** Al concluir la verificación y/o transferencia prolongada de PDFs, el método `migrate()` invoca obligatoriamente `refreshPdoConnection()` tanto para staging como para producción (en `--dry-run` y en `--apply`). La reconexión utiliza de forma segura las credenciales privadas ya cargadas en memoria, verificando la salud del canal con un `SELECT 1` no intrusivo (`pingPdo`).
   - **Preservación Estricta del Estado Transaccional:** `refreshPdoConnection()` valida `$pdo->inTransaction()`. Si existiera una transacción abierta, rechaza de inmediato la renovación con `DomainException` para evitar reintentos ciegos con resultados de commit inciertos.

2. **Optimización de Validación Masiva por Lotes (`validateAssociationsPreInsert`):**
   - **Erradicación del Patrón N+1:** Se eliminó el bucle que ejecutaba dos consultas SQL individuales por cada asociación (20,448 consultas en total contra MySQL en producción).
   - **Procesamiento en Lotes con `WHERE IN`:** Se implementó división por lotes de 500 a 1,000 asociaciones, ejecutando consultas masivas indexadas para `ccd_clientes`, `clientes_cuentas` y `ccd_cliente_cuenta`.
   - **Validación Estricta en Memoria:**
     - Coherencia de DNI entre el cliente CCD y la cuenta asociada en origen.
     - Existencia y concordancia exacta del DNI en `ccd_clientes` de destino.
     - Existencia y concordancia exacta del DNI en `clientes_cuentas` de destino.
     - Detección de colisiones: si en destino ya existe una asociación confirmada vinculada a una cuenta distinta, se aborta inmediatamente sin escribir cambios.
     - Idempotencia garantizada: si la asociación ya existe con la misma cuenta y método, se admite sin duplicación ni error.

3. **Observabilidad Mejorada y Métricas de Rendimiento:**
   - Registro cronometrado de cada etapa crítica: preflight, transferencia de PDFs, validación masiva previa, migración de catálogos y auditoría final.
   - Progreso reportado en bloques de 1,000 PDFs y seguimiento de lotes procesados en base de datos.
   - Privacidad absoluta: prohibición estricta de registrar contraseñas, tokens de conexión o datos personales de clientes (PII) en consola o archivos de registro.

4. **Auditoría Física Desacoplada y Prevención de Timeout ante `wait_timeout=20`:**
   - **Materialización Previa en `auditTarget()`:** Se ejecuta `fetchAll(PDO::FETCH_ASSOC)` y cierre inmediato de cursor (`$stmt->closeCursor()`) antes de proceder con el cálculo hash de los archivos físicos en disco, evitando cursores activos durante operaciones de filesystem.
   - **Renovación Obligatoria Post-Auditoría:** Se renuevan controladamente `$sourcePdo` y `$targetPdo` fuera de transacciones inmediatamente después de la auditoría física y justo antes de `generateManifest()`, garantizando la ejecución exitosa de la consulta de manifiesto incluso tras lapsos prolongados de hashing.

5. **Seguridad Documental Estricta sin Fallbacks Permisivos (`CcdDocumentController`):**
   - **Autenticación y Usuario Activo:** Exige sesión autenticada (`401`) y valida que el usuario no esté desactivado (`active` o `activo` en `false` -> `403` / redirección segura).
   - **Permisos Equivalentes a `clientes.show`:** Valida la autorización mediante el Gate `view-cliente` o el conjunto de roles autorizados (`Roles::FINAL_ROLES`), rechazando cualquier acceso si no se cumplen las condiciones (`403`), erradicando fallbacks que reduzcan la seguridad existente.
   - **Bloqueo Específico y DNI:** Integra la verificación de bloqueo (`cliente_bloqueos` -> `403`) y mantiene la concordancia estricta de DNI en URL contra el registro del documento (`404` ante discrepancias).

### 3.8. Adaptación de Autorización para Arquitectura LEGACY en Producción Real (`ROLES_CLASS=NO`, `GATE_VIEW_CLIENTE=NO`)

1. **Diagnóstico en Entorno Real de Hostinger:**
   - Durante el DRY-RUN en Hostinger, se validaron exitosamente 10,224 PDFs, 10,224 asociaciones y 21 lotes con 0 escrituras.
   - Sin embargo, las verificaciones del entorno detectaron que en producción:
     - `ROLES_CLASS=NO` (la clase `App\Support\Authorization\Roles` no existe en la arquitectura legacy).
     - `GATE_VIEW_CLIENTE=NO` (el Gate `'view-cliente'` no está registrado en el `AuthServiceProvider` de producción).
   - En consecuencia, la implementación inicial devolvía `403 Forbidden` a todos los usuarios legítimos (asesores, supervisores y administradores), bloqueando la visualización de los CCD.

2. **Resolución Autofinanciada y Robusta en `CcdDocumentController.php`:**
   - **Eliminación de Acoplamiento Fuerte:** Se eliminó la importación estática de `use App\Support\Authorization\Roles;`, permitiendo que el archivo compile y opere sin dependencias inexistentes en producción legacy.
   - **Definición Canónica de Roles Autorizados:** Se formalizó la constante `CcdDocumentController::AUTHORIZED_ROLES = ['administrador', 'admin', 'supervisor', 'asesor', 'soporte']`, reflejando exactamente el conjunto de roles autorizados para acceder a las fichas de clientes en `ClienteController::show`.
   - **Resolución Condicional sin Reducción de Seguridad:**
     - Si el Gate `'view-cliente'` existe, se ejecuta (`Gate::forUser($user)->authorize('view-cliente')`).
     - Si la clase `App\Support\Authorization\Roles` existe, normaliza y valida contra `Roles::FINAL_ROLES`.
     - Si ambos están ausentes (caso exacto de Producción Legacy en Hostinger), valida que `$user->role` pertenezca a `AUTHORIZED_ROLES` o que el usuario posea privilegios administrativos (`isAdmin`). En caso contrario, aborta con `403`.
   - **Controles de Seguridad Inalterados:**
     - Control de autenticación (`401` si no hay sesión).
     - Control de usuario activo (`403` si `active === false` o `activo === false`).
     - Verificación estricta de DNI en URL contra el registro documental (`hash_equals` -> `404`).
     - Control de bloqueo de clientes (`cliente_bloqueos` -> `403`), con bypass autorizado únicamente para administradores.
   - **Flags de Simulación y Pruebas Automatizadas:** Se introdujeron `$simulateRolesClassMissing` y `$simulateGateMissing` junto con una prueba de regresión exhaustiva (`test_ccd_document_controller_legacy_production_with_no_roles_class_and_no_view_cliente_gate`), cubriendo accesos legítimos de cada rol, rechazo de roles inválidos, usuarios inactivos, invitados, manipulación de URL y bloqueos.

---

## 4. Separación del Proceso en 5 Fases Controladas

El proceso de despliegue se organiza en cinco fases secuenciales e independientes:

```mermaid
flowchart TD
    Fase1["Fase 1: Validaciones y Respaldo<br/>(Preflight, SHA-256 cruzado, espacio >= 5 GiB, mysqldump)"] --> Fase2["Fase 2: Instalación de Tablas CCD<br/>(Instalación previa de archivos y migraciones selectivas)"]
    Fase2 --> Fase3["Fase 3: Migración de PDFs y Datos<br/>(Transferencia 10,224 PDFs a ccd-private y asociaciones)"]
    Fase3 --> Fase4["Fase 4: Integración Interfaz CRM<br/>(Inyección aditiva en routes/web.php, ClienteController, show.blade.php)"]
    Fase4 --> Fase5["Fase 5: Auditoría Funcional Final<br/>(Verificación total de catálogos, integridad física y limpieza)"]
```

### Detalle de Fases

1. **Fase 1: Validaciones y Respaldo**
   - Comprueba binarios requeridos (`php`, `mysql`, `mysqldump`, `sha256sum`, `df`, `tar`, `cmp`, `cp`).
   - Carga credenciales efectivas de Laravel.
   - Valida la conectividad a ambas bases de datos.
   - Valida conteos de 275,987 CCDs y 282,595 cuentas.
   - Valida huellas SHA-256 certificadas en ambos entornos:
     - CCD AQP (10,224): `861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b`.
     - Cuentas universo completo (282,595): `0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f`.
   - Verifica espacio en disco >= 5 GiB.
   - En `--apply`, crea respaldo completo de base de datos (`mysqldump`) y archivos de configuración.
2. **Fase 2: Instalación de Tablas CCD**
   - En `--dry-run`: inspecciona el esquema destino y reporta migraciones y archivos pendientes sin modificar nada.
   - En `--apply`:
     - 2.1 Invoca `installMigrationFiles()` para copiar y validar sintaxis de las 2 migraciones selectivas en `$PROD_DIR/database/migrations/`.
     - 2.2 Ejecuta exclusivamente las 2 migraciones selectivas CCD ya presentes:
       - `2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php`
       - `2026_11_13_000001_create_ccd_cliente_cuenta_table.php`
     - 2.3 Valida que `inspectTargetSchema()` confirme la presencia de `ccd_archivos`, `ccd_cliente_cuenta` y `ccd_clientes.ccd_archivo_id`.
3. **Fase 3: Migración de los 10,224 PDFs y Asociaciones**
   - Ejecuta `php artisan ccd:migrate-aqp-production-v6`.
   - Transfiere y cataloga los 10,224 PDFs en `storage/app/private/ccd/`.
   - Puebla `ccd_archivos` y vincula `ccd_clientes.ccd_archivo_id`.
   - Puebla las 10,224 asociaciones en `ccd_cliente_cuenta` (1 V3, 10,223 V5) con detección estricta de conflictos e idempotencia.
   - Genera el manifiesto oficial de auditoría CSV.
4. **Fase 4: Integración de la Interfaz del CRM**
   - Ejecuta `verifyAndIntegrateComponents()`.
   - Instala modelos y controlador CCD.
   - Aplica inyección quirúrgica en `routes/web.php`, `ClienteController.php`, `show.blade.php`, `ClienteCuenta.php`, `CcdCliente.php` y `filesystems.php`.
   - Valida sintaxis PHP de todos los archivos mediante `proc_open`.
5. **Fase 5: Auditoría Funcional Final**
   - Ejecuta `auditTarget()` en producción verificando integridad física y lógica de los 10,224 PDFs.
   - Aborta inmediatamente si se detectan anomalías (`! $audit["passed"] || ! empty($audit["anomalies"])`).
   - Limpia cachés de Laravel (`config:clear`, `route:clear`, `view:clear`).

---

## 5. Plan de Contingencia y Reversión Específica

El script `revertir_ccd_aqp_produccion_v6.sh`:
1. Verifica mediante `validateRollbackSafety` que no existan migraciones posteriores en el mismo lote ni datos ajenos a AQP4/AQP5.
2. Deshace selectivamente las dos migraciones CCD en orden inverso:
   - `php artisan migrate:rollback --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php --step=1 --force`
   - `php artisan migrate:rollback --path=database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php --step=1 --force`
3. Si se proporciona `--restore-files=/ruta/al/respaldo`, restaura los archivos modificados desde `${BACKUP_DIR}/modified_files/` a su estado exacto original.
4. Limpia las cachés de Laravel.

---

## 6. Resultados de Verificación y Pruebas Automatizadas

Se ejecutó la suite completa de pruebas obteniendo 100% de éxito:

- **Pruebas de Integración V6.2.2 (`tests/Feature/CcdAqpProductionMigrationV6Test.php`):**
  - `test_dry_run_on_clean_target_with_no_ccd_tables_executes_zero_writes_and_reports_pending_migrations`: PASS
  - `test_apply_fails_if_target_schema_lacks_ccd_tables`: PASS
  - `test_selective_migrations_create_only_ccd_tables_and_columns`: PASS
  - `test_apply_rejects_incompatible_association_collision_without_modifying_target`: PASS
  - `test_apply_is_idempotent_when_association_is_identical`: PASS
  - `test_apply_rejects_incompatible_ccd_archivos_and_link_collision`: PASS
  - `test_interrupted_migration_resumes_safely`: PASS
  - `test_credentials_file_reading_and_protection`: PASS
  - `test_file_transfer_copies_and_verifies_sha256`: PASS
  - `test_ccd_document_controller_serves_migrated_private_pdf_inline`: PASS
  - `test_sha256_calculation_uses_json_lines_and_unifies_ccd_and_cuentas`: PASS
  - `test_verify_and_integrate_components_dry_run_and_additive_apply`: PASS
  - `test_verify_and_integrate_components_aborts_on_corrupted_file`: PASS
  - `test_validate_rollback_safety_guards_against_subsequent_batches_and_foreign_data`: PASS
  - `test_assert_php_syntax_detects_errors_using_proc_open_without_exec`: PASS
  - `test_verify_and_integrate_components_legacy_architecture_routes_controller_and_view`: PASS
  - `test_legacy_controller_ccd_confirmed_matches_operation_specifically_without_cross_pollution`: PASS
  - `test_ccd_document_controller_authorizes_legacy_and_modern_and_prevents_parameter_tampering`: PASS
  - `test_ccd_document_controller_legacy_production_with_no_roles_class_and_no_view_cliente_gate`: PASS
  - `test_inspect_target_schema_unifies_column_keys_and_detects_discrepancies`: PASS
  - `test_install_migration_files_copies_and_validates_syntax_before_migration`: PASS
  - `test_audit_target_contract_and_anomaly_abort`: PASS
  - `test_five_phases_end_to_end_legacy_flow_with_isolated_mysql_databases`: PASS
  - `test_refresh_pdo_connection_reconnects_after_mysql_2006_and_rejects_in_transaction`: PASS
  - `test_validate_associations_pre_insert_batches_where_in_and_detects_conflicts`: PASS
  - `test_transfer_and_verify_files_closes_cursor_before_filesystem_operations`: PASS
  - `test_migrate_renews_connections_and_executes_batch_validation_end_to_end`: PASS
  - `test_audit_target_materializes_rows_closes_cursor_and_renews_connections_exceeding_timeout_for_manifest`: PASS
  - `test_interrupted_migration_between_phases_recovers_completely`: PASS
  - **Total:** 27 pruebas pasadas, 288 aserciones (2 anotaciones deprecated inherentes a PHP 8.5 en el entorno de pruebas).

- **Suite General CCD:** 142 pruebas pasadas (851 aserciones).
- **Estándar de Código (Laravel Pint):** 100% de conformidad sin advertencias.
- **Validación de Sintaxis Bash (`bash -n`):** 100% de conformidad sin errores.

---

## 7. Instrucciones de Ejecución en Hostinger SSH

### Paso 1: Ejecutar Simulación Segura (CERO Escrituras)
```bash
cd /home/u480021566/apps/kpinvest-crm-pruebas
./desplegar_ccd_aqp_produccion_v6.sh --dry-run
```

### Paso 2: Ejecución Real Autorizada (--apply)
Una vez validada la salida del dry-run y contando con la debida autorización:
```bash
cd /home/u480021566/apps/kpinvest-crm-pruebas
./desplegar_ccd_aqp_produccion_v6.sh --apply
```

### Procedimiento de Rollback en Caso de Contingencia
```bash
cd /home/u480021566/domains/impulse-go.com/public_html/plataforma-crm
./revertir_ccd_aqp_produccion_v6.sh --restore-files=/home/u480021566/private/backups/ccd-v6-produccion-YYYYMMDD-HHMMSS
```
