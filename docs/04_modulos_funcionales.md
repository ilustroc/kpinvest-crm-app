# 04 - Modulos Funcionales

## Autenticacion

Controladores y vistas:

- `AuthController`
- `resources/views/auth/login.blade.php`

Funcion:

- Login por email/password.
- Verifica que `active = 1`.
- Regenera sesion al iniciar.
- Logout con invalidacion de sesion y token CSRF.

Riesgo:

- No se observa flujo propio de recuperacion de contrasena.
- La tabla tiene `active` e `is_active`, pero el login usa `active`.

## Panel principal

Controlador y vista:

- `PanelController`
- `resources/views/panel/resumen.blade.php`

Funcion:

- Muestra KPIs del dia.
- Muestra pagos del mes.
- Muestra pendientes segun rol.
- Permite busqueda rapida de clientes.
- Presenta proximas cuotas para supervisores/admins.

Tablas usadas:

- `promesas_pago`
- `pagos_propia`
- `promesa_cuotas`
- `cna_solicitudes`

## Dashboard estadistico

Controlador, servicio y vista:

- `DashboardController`
- `Dashboard/DashboardStatsService`
- `resources/views/dashboard/index.blade.php`

Funcion:

- KPIs de promesas y pagos.
- Serie de pagos de 12 meses.
- Comparativos por entidad y asesor.
- Filtros por mes, dia habil, cosecha, entidad y asesor.

Riesgo:

- La logica de dias habiles y feriados esta codificada en el servicio.
- El dashboard filtra asesor por nombre de gestor en pagos, no por `user_id`.

## Clientes

Controladores y vistas:

- `ClienteController`
- `ClienteLookupController`
- `resources/views/clientes/show.blade.php`

Funcion:

- Busca cliente por DNI, operacion o nombre.
- Muestra cuentas del cliente.
- Agrupa pagos por operacion.
- Muestra documentos CCD.
- Muestra promesas y cronogramas.
- Muestra solicitudes CNA por cuenta/operacion.
- Permite registrar promesas y CNA.
- Permite borrar pagos para roles autorizados.

Tablas usadas:

- `clientes_cuentas`
- `pagos_propia`
- `ccd_clientes`
- `promesas_pago`
- `promesa_operaciones`
- `promesa_cuotas`
- `cna_solicitudes`
- `asignar_clientes`
- `cliente_bloqueos`

Parte critica:

- `resources/views/clientes/show.blade.php` es una vista grande con mucho HTML y JavaScript embebido.
- `ClienteController@show` concentra varias consultas y mapeos de datos.

## Promesas de pago

Controladores, servicios, modelos:

- `PromesaController`
- `PromesaCreator`
- `PromesaWorkflowService`
- `PromesaPago`
- `PromesaOperacion`
- `PromesaCuota`
- `StorePromesaRequest`

Funcion:

- Crea promesas tipo convenio, convenio con cuota balon o cancelacion.
- Relaciona multiples operaciones con una promesa.
- Crea cronograma de cuotas.
- Autoaprueba segun rol administrador/sistemas.
- Preaprueba si crea un supervisor.
- Envia correos de workflow.

Flujo:

- `pendiente`
- `preaprobada`
- `aprobada`
- `rechazada`
- `rechazada_sup`

Parte critica:

- `PromesaPago` mantiene compatibilidad legacy entre `estado` y `workflow_estado`.
- La columna plana `operacion` convive con la tabla `promesa_operaciones`.

## CNA

Controlador, modelo y soporte:

- `CnaController`
- `CnaSolicitud`
- `WorkflowMailer`

Funcion:

- Registra solicitudes CNA por DNI y operaciones.
- Genera numeracion por serie segun cosecha/origen.
- Maneja workflow supervisor/admin.
- Genera DOCX desde plantillas.
- Convierte DOCX a PDF usando iLovePDF.
- Permite descargar PDF/DOCX aprobados.

Series detectadas:

- KP Invest SAC.
- Fondo Acreencia Arequipa.
- Acreencia II.

Parte critica:

- `CnaController` concentra validacion, workflow, correlativos, plantillas, conversion y descargas.
- La generacion depende de plantillas fisicas en `storage/app/templates`.
- iLovePDF es una dependencia externa para PDF.

## Autorizaciones

Controlador y vista:

- `AutorizacionController`
- `resources/views/autorizacion/index.blade.php`

Funcion:

- Bandeja de promesas y CNA pendientes o preaprobadas.
- Filtra por rol y equipo.
- Permite preaprobar, aprobar y rechazar.
- Consulta pagos por DNI para contexto de aprobacion.

Parte critica:

- El controlador arma mucha informacion agregada para las vistas.
- Las reglas de visibilidad dependen de `supervisor_id` y roles.

## Reportes

Controladores:

- `ReportePagosController`
- `ReportePromesasController`
- `ReporteCnaController`

Servicios:

- `PaymentReportService`
- `PromiseReportService`
- `CnaReportService`

Exports:

- `PaymentExport`
- `PromiseExport`
- `CnaExport`

Funcion:

- Filtros por fecha y multiples selectores.
- Facets AJAX para listas dependientes.
- Tablas paginadas.
- Exportacion XLSX.

Parte critica:

- `PromiseReportService` y `CnaReportService` usan SQL complejo con subconsultas y `selectRaw`.
- Los exports masivos pueden consumir tiempo y memoria.

## Integraciones CSV

Controladores:

- `IntegracionDataController`
- `IntegracionAsignacionController`
- `IntegracionCcdController`
- `IntegracionPagosController`

Importadores:

- `DataImport`
- `AsignacionImport`
- `CcdImport`
- `PagosImport`

Funcion:

- Descarga de plantillas CSV.
- Carga de archivos CSV/TXT.
- Normalizacion de encoding, fechas y numeros.
- Registro de pagos por lote.

Parte critica:

- Los imports modifican tablas productivas.
- `DataImport` puede procesar grandes volumenes con `set_time_limit(0)` y `memory_limit` elevado.
- Debe validarse la existencia de indices unicos antes de depender de `upsert`.

## Administracion de usuarios

Controlador, servicio y vista:

- `AdminUsersController`
- `UserService`
- `StoreUserRequest`
- `resources/views/placeholders/administracion.blade.php`

Funcion:

- Lista usuarios activos/inactivos.
- Crea usuarios.
- Activa/desactiva usuarios.
- Cambia contrasenas.
- Aplica restricciones por rol.

Parte critica:

- Evita desactivar el ultimo administrador activo.
- No permite autodesactivacion.

## Correos

Soporte:

- `WorkflowMailer`
- `resources/views/mail/*.blade.php`

Funcion:

- Notifica promesas pendientes, preaprobadas, rechazadas o resueltas.
- Notifica CNA pendientes, preaprobadas, rechazadas o resueltas.

Riesgo:

- Algunas vistas candidatas referenciadas no existen y se usa fallback.
- `WorkflowMail` implementa `ShouldQueue`, pero no parece usado directamente por `WorkflowMailer`.

