# Stage 2B — Validación funcional local del CRM V3

**Fecha:** 2026-10-21  
**Estado:** Stage 2B completado con limitaciones controladas. Meridian **no iniciado**.  
**Alcance:** validación local aislada; no se tocó producción, la base de referencia ni el entorno `8088`.

## 1. Resumen ejecutivo

- La validación se hizo en `kp_crm_v3_validation`, sobre el contenedor `kpcrm-staging-db`, usando como única fuente `kp_crm_staging_masked`.
- Se copiaron **14 tablas, 153 columnas y 725,766 filas** de negocio. El destino conserva las 725,766 filas y agrega **1 administrador sintético** (50 usuarios en total).
- El esquema origen/destino es idéntico: **14 tablas InnoDB**, **8 FKs**, **1 CHECK**, collation `utf8mb4_unicode_ci`, mismas columnas, índices y relaciones.
- La base enmascarada ya traía el baseline V3: **18 filas en `migrations` = 10 históricas + 8 V3**. Las 8 migraciones V3 figuran como `Ran`; no hay pendientes. **No se ejecutó `php artisan migrate`**.
- La suite funcional terminó en **32 tests aprobados, 347 assertions, 2 warnings no fallidos**. Los tests de escritura usaron `DatabaseTransactions`; los checksums no cambiaron después de la suite.
- La matriz HTTP de solo lectura pasó en **26/26 rutas** (25 `200`, 1 `302` esperado), sin `500` ni `419`.
- Login, dashboard, logout, clientes, autorizaciones, reportes, exportaciones, administración e integración funcionan en `http://localhost:8090`.
- El DOCX real de promesa se generó correctamente (`200`, 7,556 bytes, ZIP OOXML válido). El **PDF real no está disponible** porque faltan claves de iLovePDF; la aplicación entrega DOCX como fallback.
- Las descargas CNA de los registros históricos devuelven `404` porque las rutas `docx_path`/`pdf_path` apuntan a archivos que no existen en el staging. La generación con documentos sintéticos sí está cubierta por la suite.
- Se conservaron los problemas históricos sin corregirlos: fechas anómalas, grupos duplicados, promesas con monto cero y diferencias entre `pagos_lotes` y pagos reales.
- No se recrearon contenedores persistentes, no se borraron volúmenes y no se modificó el entorno `8088`.
- No hay commit, push ni despliegue.

## 2. Identificación de contenedores, servidores y bases

| Elemento | Identificación |
|---|---|
| Origen | `kp_crm_staging_masked` |
| Servidor de proceso | `kpcrm-staging-db` (`staging-db:3306`), MariaDB 11.8.9 |
| Destino | `kp_crm_v3_validation` |
| Servidor de destino | El mismo `kpcrm-staging-db` |
| Proxy staging | `kpcrm-staging-web`, publicado en `127.0.0.1:8090` |
| Aplicación staging | `kpcrm-staging-app`, sin puerto publicado |
| Referencia aislada | `kpcrm-staging-ref-db` (red `kpcrm_staging_ref`); no usada como fuente |
| CRM principal | `kpcrm-meridian-app-1`, `http://localhost:8088`; no modificado |
| Producción IMPULSE | No accedida ni modificada |

## 3. Esquema, compatibilidad y baseline de migraciones

### 3.1 Comparación origen/destino

- `mariadb-dump --no-data` de origen y destino: **NO SCHEMA DIFFERENCES**.
- Tablas: **14/14**.
- Columnas: **153**.
- FKs: **8**.
- CHECK: **1** (`cna_solicitudes`, `json_valid(operaciones)`).
- Motor: InnoDB en todas las tablas.
- Collation de tabla: `utf8mb4_unicode_ci` en las 14 tablas.
- Collation especial: `cna_solicitudes.operaciones` es `longtext` con `utf8mb4_bin`, como en la migración V3.
- Índices y nombres de FKs: coinciden con la definición V3.
- Diferencia no funcional: el CHECK del origen quedó con el nombre autogenerado `cna_solicitudes_chk_1`; la migración V3 declara `cna_solicitudes_operaciones_json_check`. La semántica es la misma. No se renombró.

### 3.2 Baseline V3

La tabla `migrations` del origen y del destino contiene **18 filas**:

- 10 migraciones históricas.
- 8 migraciones V3:
  - `2026_05_15_000001_create_users_table`
  - `2026_05_15_000002_create_clientes_tables`
  - `2026_05_15_000003_create_pagos_tables`
  - `2026_05_15_000004_create_promesas_tables`
  - `2026_05_15_000005_create_cna_solicitudes_table`
  - `2026_05_15_000006_create_integracion_tables`
  - `2026_05_15_000007_normalize_user_roles_v3`
  - `2026_05_21_122013_create_notifications_table`

`php artisan migrate:status` muestra las 8 migraciones V3 como `Ran` y **no hay pendientes**. La lista visible se limita a las migraciones cuyo archivo existe en el repositorio; las 10 históricas no están presentes como archivos, pero sus filas se conservaron. No se ejecutó `artisan migrate`, `migrate:fresh` ni `db:wipe`.

### 3.3 Compatibilidad MySQL 8.0

Se importó **solo el esquema** en un MySQL 8.0 desechable local:

- Versión local disponible: **MySQL 8.0.46** (no había imagen exacta 8.0.42).
- Resultado: **14 tablas, 8 FKs, 1 CHECK**, collation `utf8mb4_unicode_ci`, importación sin errores.
- El contenedor desechable se eliminó al terminar.
- No se validó contra un servidor MySQL 8.0.42 exacto. El DDL no usa features posteriores a 8.0.42; aun así, la comprobación exacta en 8.0.42 queda como paso previo a producción.

## 4. Copia y conciliación de datos

### 4.1 Conteos exactos

| Tabla | Origen | Destino |
|---|---:|---:|
| `asignar_clientes` | 222,926 | 222,926 |
| `ccd_clientes` | 222,781 | 222,781 |
| `cliente_bloqueos` | 11 | 11 |
| `clientes_cuentas` | 229,389 | 229,389 |
| `cna_solicitudes` | 1,457 | 1,457 |
| `migrations` | 18 | 18 |
| `notifications` | 2 | 2 |
| `pagos_lotes` | 216 | 216 |
| `pagos_propia` | 40,780 | 40,780 |
| `personal_access_tokens` | 0 | 0 |
| `promesa_cuotas` | 2,818 | 2,818 |
| `promesa_operaciones` | 2,739 | 2,739 |
| `promesas_pago` | 2,580 | 2,580 |
| `users` | 49 | 50 (49 históricos + 1 sintético) |
| **Total** | **725,766** | **725,767** |

Los checksums de las 14 tablas de negocio coinciden entre origen y destino. `users` difiere únicamente por el administrador sintético; los 49 usuarios históricos se conservaron con sus mismos roles, flags y hashes bcrypt.

- La copia se hizo con `mariadb-dump --single-transaction` usando `kp_stage_ro` como lector.
- La importación se hizo con un usuario DDL temporal `kp_stage_ddl`, con privilegios solo en `kp_crm_v3_validation`.
- El dump temporal (97,356,492 bytes) se eliminó al terminar.
- El usuario `kp_stage_ddl` y su archivo de contraseña fueron eliminados al cerrar Stage 2B.
- `kp_stage_app` conserva únicamente DML y privilegios de tablas temporales/bloqueo sobre `kp_crm_v3_validation`; no tiene DDL permanente ni acceso a la base enmascarada o de referencia.

## 5. Seguridad y aislamiento

### 5.1 Redes

| Contenedor | Redes | Observación |
|---|---|---|
| `kpcrm-staging-app` | `kpcrm_staging_app_net`, `kpcrm_staging_private` | Solo app, proxy y base de validación |
| `kpcrm-staging-web` | `kpcrm_staging_app_net`, `kpcrm_staging_web` | Publica `127.0.0.1:8090` |
| `kpcrm-staging-db` | `kpcrm_staging_private`, `kpcrm_staging_tools_db` | Sin contenedores vecinos en tools |
| `kpcrm-staging-ref-db` | `kpcrm_staging_ref` | Totalmente separada; la app no tiene ruta |

La app no comparte red con `kpcrm-staging-ref-db` ni con el entorno `8088`.

### 5.2 Montajes y variables

- La app monta código del repositorio en solo lectura y únicamente `storage/staging/{app,framework,logs,bootstrap-cache}` en escritura.
- No hay montajes de `.env`, HMAC, backups, dumps, bases enmascaradas ni secretos de referencia.
- `DB_HOST=staging-db`, `DB_DATABASE=kp_crm_v3_validation`, `DB_USERNAME=kp_stage_app`.
- `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8090`.
- `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=log`, `CACHE_STORE=file`, `SESSION_DRIVER=file`.
- `ILOVEPDF_PUBLIC` e `ILOVEPDF_SECRET` están vacíos.
- No hay claves HMAC en la app.
- No hay worker de colas ni servicio de correo real.

### 5.3 Corrección operativa de staging

- El proxy usaba `Host $host`, lo que eliminaba `:8090` de las redirecciones. Se cambió solo el proxy de staging a `Host $http_host` y se añadieron `X-Forwarded-Host` y `X-Forwarded-Port`; `nginx -t` pasó y se hizo `nginx -s reload`.
- `storage/app/tmp` estaba en 755/root y bloqueaba la generación DOCX por HTTP. Se ajustó a `www-data:www-data` 775, sin reiniciar contenedores.
- No se recreó ningún contenedor persistente, no se borraron volúmenes y no se tocó la configuración del `8088`.

## 6. Validación funcional

### 6.1 Suite automatizada

Comando efectivo, con entorno de test explícito para evitar que `APP_ENV=staging` del contenedor neutralizara PHPUnit:

- `APP_ENV=testing`
- `SESSION_DRIVER=array`
- `MAIL_MAILER=array`
- `CACHE_DRIVER=array`
- `QUEUE_CONNECTION=sync`
- `BROADCAST_CONNECTION=null`

Resultado: **32 passed, 347 assertions, 2 warnings**. Los warnings no fueron fallos y no se reprodujeron en reruns aislados. Los tests de escritura usaron `DatabaseTransactions`; se compararon checksums antes/después y no hubo cambios.

Cobertura funcional de la suite:

- Login/logout, rol y acceso.
- Dashboard y estadísticas.
- Clientes, búsqueda, perfil, cuentas y eliminación de pagos.
- Autorizaciones y workflows de promesas/CNA.
- Promesas, cuotas y acuerdos DOCX/PDF con fallback.
- CNA, aprobación, documentos y fallback.
- Notificaciones y tiempo real.
- Reportes y exportaciones.
- Importaciones de pagos, data, asignación y CCD.
- Correos con `Mail::fake`/array, sin envío real.

### 6.2 Matriz HTTP real

Se ejecutaron **26 rutas de solo lectura** con sesión del administrador sintético:

- **25** respuestas `200`.
- **1** respuesta `302` esperada (`/clientes/lookup?q=42178653` redirige al perfil).
- **0** respuestas `500`, `419` o errores inesperados.
- Exportaciones de pagos, promesas y CNA: XLSX válidos (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`).
- Plantillas de integración: descargan CSV como adjunto, aunque el `Content-Type` es `text/html`; incidencia menor de cabecera.
- `/notificaciones` y `/notificaciones/unread-count` devuelven JSON, coherente con el uso AJAX de la UI.
- Evidencia: `C:\KP-CRM-Staging\stage2b\http_readonly_results.json`.

### 6.3 Documentos

- **Promesa:** `GET /promesas/1/acuerdo` devolvió `200`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, 7,556 bytes, firma ZIP `PK\x03\x04`.
  - Evidencia: `C:\KP-CRM-Staging\stage2b\promesa_1_acuerdo.docx`
  - SHA-256: `24E2E2F56F9B752B70E0A2853002A9098538C49BA7F93D8FAD4C97197A560A7A`.
- **PDF real:** no disponible; iLovePDF no tiene claves. El servicio entrega DOCX como fallback y registra un warning.
- **CNA histórico:** `GET /cna/1/docx` y `/cna/1/pdf` devuelven `404` porque los archivos referenciados no existen en el staging. No se auto-corrigieron rutas ni se generaron archivos sobre registros históricos.
- La generación CNA y el fallback DOCX están cubiertos por la suite con registros y plantillas sintéticas.

## 7. Problemas históricos observados (sin corregir)

| Hallazgo | Conteo / evidencia |
|---|---:|
| `promesas_pago.fecha_promesa < 1900-01-01` | 3 |
| `promesas_pago.fecha_pago < 1900-01-01` | 5 |
| `promesa_cuotas.fecha < 1900-01-01` | 6 |
| `promesas_pago.monto = 0` | 708 |
| Pagos con monto cero | 0 |
| Montos negativos en pagos/promesas/cuotas/CNA | 0 |
| Fechas futuras en compras/pagos/promesas | 0 |
| Grupos duplicados por clave natural en `clientes_cuentas` | 8 |
| Grupos duplicados por clave natural en `pagos_propia` | 729 |
| Lotes con `total_registros` distinto del conteo real | Múltiples; ejemplos: lote 6: 34,527 vs 0; lote 5: 34,527 vs 0; lote 3: 34,515 vs 0; lote 2: 30,788 vs 0; lote 4: 180 vs 0; lote 147: 21 vs 18; lote 7: 37,499 vs 37,497; lote 200: 91 vs 89; lote 150: 3 vs 1; lote 115: 31 vs 30 |

- Usuarios históricos: 49, de los cuales 5 tienen `active=0` e `is_active=1`. No hay usuarios históricos con `active=1` e `is_active=0`. La aplicación usa `active` para autenticación; no se modificó ningún usuario histórico.
- Los datos anómalos se midieron y se dejaron intactos. Los reportes y perfiles los renderizan sin errores.

## 8. Capturas y evidencia visual

Directorio: `C:\KP-CRM-Staging\stage2b\screenshots`

| Archivo | Pantalla |
|---|---|
| `01_login.png` | Login sin credenciales visibles |
| `02_dashboard.png` | Dashboard/estadísticas |
| `03_cliente_42178653.png` | Perfil de cliente enmascarado |
| `04_autorizacion.png` | Bandeja de autorizaciones |
| `05_reporte_pagos.png` | Reporte de pagos |
| `06_administracion.png` | Administración de usuarios |
| `07_integracion_pagos.png` | Integración/importación de pagos |
| `08_reporte_cna.png` | Reporte CNA |

Las capturas usan el registro enmascarado `42178653`, nombres `CLIENTE_*`, agentes `GESTOR_*` y usuarios `USUARIO_*`. No se observaron credenciales ni PII residual en las pantallas revisadas. La captura de login no contiene campos completos.

## 9. Credenciales y acceso

- URL: `http://localhost:8090`
- Usuario sintético: `stage2b.admin@example.test`
- Rol: `administrador`
- Estado: activo
- Contraseña: **no se incluye en este informe, en logs, en el repositorio ni en la respuesta del agente**.
- Archivo protegido fuera del repositorio: `C:\KP-CRM-Staging\secrets\stage2b_admin_credentials.txt`

El usuario fue creado exclusivamente para Stage 2B, con contraseña aleatoria y hash bcrypt. No se reutilizaron contraseñas ni hashes históricos. El fallo inicial de login por contraseña en claro en el primer intento de creación se corrigió con bcrypt explícito; no se modificó código de la aplicación y se limpió el log local que contenía ese valor.

## 10. Incidencias, limitaciones y pendientes

1. **PDF real no disponible:** faltan claves de iLovePDF; el fallback DOCX funciona. Se requiere decidir si se habilitan claves de staging o se acepta DOCX para la validación visual.
2. **CNA histórico sin archivos:** las rutas de documentos CNA aprobados apuntan a archivos ausentes; las descargas devuelven `404`. La generación con fixtures sintéticos pasa. No se corrigieron datos históricos.
3. **Content-Type de plantillas de integración:** descargan CSV con `text/html`; el archivo funciona, pero conviene corregir la cabecera.
4. **Nombre del CHECK de CNA:** `cna_solicitudes_chk_1` frente al nombre declarado en la migración. No afecta la aplicación; requiere decisión antes de una reconstrucción futura del esquema.
5. **Warnings de PHPUnit:** 2 warnings no fallidos, no reproducidos en reruns aislados; no afectaron las 347 assertions.
6. **MySQL 8.0.42 exacto:** el esquema importó sin errores en MySQL 8.0.46 desechable; falta la comprobación con la versión exacta de producción.
7. **Proxy y permisos de storage:** los ajustes son de staging y quedan sin commit; no se aplicaron al `8088`.
8. **Históricos:** fechas inválidas, duplicados, promesas en cero y totales de lotes inconsistentes siguen presentes por diseño; no se auto-corrigieron.

## 11. Recomendación para iniciar Meridian

**Recomendación:** se puede iniciar el rediseño visual Meridian en un worktree/rama aislado y con datos enmascarados, sin tocar producción ni el entorno `8088`, pero **no debe desplegarse** todavía.

Antes de convertir Meridian en la línea principal conviene cerrar:

1. La estrategia de PDF (claves iLovePDF o aceptación formal de DOCX).
2. La generación/restauración de archivos CNA para registros históricos, o una decisión de producto para mostrar “no disponible”.
3. El `Content-Type` de las plantillas CSV.
4. La comprobación exacta en MySQL 8.0.42 y la decisión sobre el nombre del CHECK.
5. La sustitución/rotación del administrador sintético y la limpieza de artefactos de validación.

Con esas salvedades, el CRM V3 está funcionalmente validado para continuar con la fase de diseño Meridian en un entorno controlado.

## 12. Cierre y cumplimiento

- No se ejecutó `migrate`, `migrate:fresh` ni `db:wipe`.
- No se modificó `kp_crm_v1_reference` ni la base de referencia aislada.
- No se accedió a producción IMPULSE.
- No se modificó el entorno `8088`; solo se comprobó su salud (`302`/`200`).
- No se recrearon contenedores persistentes ni se borraron volúmenes.
- Se eliminaron el arnés de tests copiado al contenedor, el usuario DDL temporal, su contraseña y el dump temporal.
- El repositorio sigue sin commit/push/despliegue; los cambios de staging quedan locales y sin versionar.
- **Meridian no se implementó.**
