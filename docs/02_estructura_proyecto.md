# 02 - Estructura del Proyecto

## Resumen Laravel

La aplicacion conserva la estructura clasica de Laravel:

- `app/Http/Controllers`: controladores web.
- `app/Models`: modelos Eloquent principales.
- `app/Services`: servicios de negocio y reportes.
- `app/Imports`: importadores CSV.
- `app/Exports`: exportadores XLSX.
- `app/Support`: utilidades compartidas.
- `app/Traits`: trait para importacion CSV.
- `resources/views`: vistas Blade.
- `routes/web.php`: rutas web principales.
- `config`: configuracion Laravel y servicios externos.
- `database/migrations`: migraciones parciales.
- `public/css` y `public/js`: assets servidos directamente.

## Directorios de aplicacion

### `app/Http/Controllers`

Contiene la mayor parte de la logica HTTP. Hay controladores pequenos y delegados, como `PromesaController`, pero tambien controladores grandes con responsabilidades mixtas:

- `CnaController`: creacion, workflow, correlativos, plantillas, DOCX/PDF y descargas.
- `AutorizacionController`: bandeja de promesas y CNA, enriquecimiento de data y acciones de workflow.
- `PromesaPdfController`: generacion de acuerdo de pago en DOCX/PDF.
- `ClienteController`: vista integral del cliente, pagos, CCD, CNA y promesas.

### `app/Models`

Modelos principales:

- `User`
- `ClienteCuenta`
- `PromesaPago`
- `PromesaOperacion`
- `PromesaCuota`
- `CnaSolicitud`
- `PagoLote`
- `PagoPropia`
- `CcdCliente`
- `AsignarCliente`

Algunos modelos estan muy completos (`PromesaPago`, `CnaSolicitud`) y otros funcionan como modelos simples de tabla (`ClienteCuenta`, `CcdCliente`, `AsignarCliente`).

### `app/Services`

Servicios identificados:

- `Dashboard/DashboardStatsService`: KPIs, series, comparativos y filtros del dashboard.
- `PaymentReportService`: filtros y facets para reporte de pagos.
- `PromiseReportService`: consulta compleja para reporte de promesas.
- `CnaReportService`: consulta compleja para reporte CNA.
- `UserService`: reglas de visibilidad y gestion de usuarios.
- `PromesaWorkflowService`: transiciones de workflow para promesas.
- `Promesas/PromesaCreator`: creacion de promesas y cuotas.

### `app/Imports`

Importadores CSV:

- `DataImport`: carga/actualiza `clientes_cuentas`.
- `AsignacionImport`: carga `asignar_clientes` validando contra maestro.
- `CcdImport`: carga registros de `ccd_clientes`.
- `PagosImport`: carga pagos en `pagos_propia`.

Todos dependen de `CsvImportTrait` para delimitador, encoding, fechas y numeros.

### `app/Exports`

Exportadores XLSX:

- `PaymentExport`
- `PromiseExport`
- `CnaExport`

Usan PhpSpreadsheet y generan archivos temporales descargables.

### `app/Support`

- `WorkflowMailer`: envio de correos para promesas y CNA.
- `Traits/HasTeamVisibility`: resolucion de visibilidad por equipo.

### `resources/views`

Vistas principales:

- `layouts/app.blade.php`: layout principal, sidebar y assets.
- `auth/login.blade.php`: login.
- `panel/resumen.blade.php`: panel principal.
- `dashboard/index.blade.php`: dashboard estadistico.
- `clientes/show.blade.php`: vista mas grande y critica del cliente.
- `autorizacion/index.blade.php`: bandeja de aprobaciones.
- `reportes/*`: reportes y tablas parciales.
- `placeholders/*`: administracion e integraciones.
- `mail/*`: plantillas de correo.

## Frontend y assets

El proyecto tiene Vite configurado, pero muchas vistas usan assets directos:

- `public/css/app.css`
- `public/css/layout/app.css`
- `public/css/dashboard-stats.css`
- `public/css/reportes/*.css`
- `public/js/reportes/*.js`
- `public/js/dashboard-stats.js`
- `public/js/admin/admin.js`

Tambien hay CSS embebido extenso en `resources/views/layouts/app.blade.php` y JavaScript embebido en vistas grandes como `clientes/show.blade.php` y `panel/resumen.blade.php`.

## Automatizaciones

No se encontraron Jobs propios en `app/Jobs` ni comandos propios en `app/Console/Commands`.

`app/Console/Kernel.php` no agenda tareas activas. Solo existe el comando demo `inspire` en `routes/console.php`.

## Pruebas

Las pruebas actuales son las pruebas base de Laravel:

- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

No hay cobertura funcional para flujos criticos como promesas, CNA, importaciones, reportes o autorizaciones.

