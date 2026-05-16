# 23 - Refactor backend Promesas V3

## Objetivo

Ordenar el backend del modulo Promesas sin cambiar rutas, nombres de rutas, estructura de base de datos, formularios, redirects, mensajes principales ni reglas funcionales.

Esta fase corresponde a:

```text
Fase 8.2 - Refactor backend del modulo Promesas
```

## Archivos revisados

- `app/Http/Controllers/PromesaController.php`.
- `app/Http/Controllers/AutorizacionController.php`.
- `app/Http/Controllers/PromesaPdfController.php`.
- `app/Services/Promesas/PromesaCreator.php`.
- `app/Services/PromesaWorkflowService.php`.
- `app/Models/PromesaPago.php`.
- `app/Models/PromesaCuota.php`.
- `app/Models/PromesaOperacion.php`.
- `app/Models/ClienteCuenta.php`.
- `app/Models/PagoPropia.php`.
- `routes/web/promesas.php`.
- `routes/web/clientes.php`.
- `tests/Feature/V3WorkflowSmokeTest.php`.

## Responsabilidades detectadas antes del refactor

### `PromesaController`

- Recibia el POST de creacion desde Cliente.
- Delegaba en `PromesaCreator`.

### `PromesaCreator`

Concentraba:

- Normalizacion de operaciones seleccionadas.
- Normalizacion de fechas y montos del cronograma.
- Validacion de suma de cronograma contra monto convenio.
- Creacion de promesa.
- Asociacion de operaciones.
- Creacion de cuotas.
- Marcado de cuota balon.
- Auto workflow por rol:
  - administrador: aprobada.
  - supervisor: preaprobada.
  - asesor/soporte: pendiente.
- Envio best-effort de correos.
- Mensaje flash final.

### `AutorizacionController`

Concentraba:

- Consulta de bandeja de promesas.
- Filtro por equipo.
- Busqueda por DNI, nota u operacion.
- Filtro de estado segun rol.
- Enriquecimiento de promesas con titular, cuentas, deudas y cronograma.
- Workflow de promesas: preaprobar, aprobar y rechazar.
- Logica de CNA en la misma pantalla.

### `PromesaPdfController`

Concentraba:

- Seleccion de plantilla DOCX.
- Carga de operaciones, cliente y deudas.
- Construccion de tablas de operaciones y cronograma.
- Llenado de plantilla.
- Inspeccion de DOCX.
- Conversion con iLovePDF.
- Fallback a DOCX cuando faltan claves o falla conversion.
- Logging de diagnostico.

## Servicios creados

### `app/Services/Promesa/PromesaCreationService.php`

Responsabilidad:

- Crear promesas desde `StorePromesaRequest`.
- Mantener auto workflow por rol.
- Asociar operaciones.
- Crear cuotas.
- Enviar notificaciones best-effort.
- Retornar `[PromesaPago, mensaje]`.

### `app/Services/Promesa/PromesaScheduleService.php`

Responsabilidad:

- Normalizar operaciones seleccionadas.
- Normalizar fechas y montos de cronograma.
- Validar consistencia de convenio.
- Preparar filas de cuotas.
- Marcar cuota balon sin cambiar persistencia.

### `app/Services/Promesa/PromesaWorkflowService.php`

Responsabilidad:

- Preaprobar promesa.
- Aprobar promesa.
- Rechazar como supervisor.
- Rechazar como administrador.
- Validar estado actual antes de cambiar.
- Mantener correos best-effort.

Reemplaza al servicio legacy:

- `app/Services/PromesaWorkflowService.php`, eliminado por quedar duplicado.

### `app/Services/Promesa/PromesaDocumentService.php`

Responsabilidad:

- Generar acuerdo de promesa.
- Llenar plantilla DOCX.
- Inspeccionar DOCX generado.
- Intentar conversion con iLovePDF.
- Entregar DOCX como fallback si no hay claves o falla conversion.

### `app/Services/Promesa/PromesaQueryService.php`

Responsabilidad:

- Consultar bandeja de promesas para Autorizacion.
- Aplicar visibilidad por equipo.
- Aplicar filtros de busqueda y estado.
- Enriquecer filas con operaciones, cuentas, deudas y cronograma.

Nota:

- La parte CNA de `AutorizacionController@index` fue refactorizada despues en Fase 8.3 hacia `CnaQueryService`.

## Actions creadas

- `app/Actions/Promesa/CreatePromesaAction.php`.
- `app/Actions/Promesa/PreapprovePromesaAction.php`.
- `app/Actions/Promesa/ApprovePromesaAction.php`.
- `app/Actions/Promesa/RejectPromesaAction.php`.
- `app/Actions/Promesa/GeneratePromesaAgreementAction.php`.

Estas Actions encapsulan operaciones puntuales y validan permisos mediante Gates.

## Policy creada

### `app/Policies/PromesaPolicy.php`

Permisos definidos:

- `create`.
- `viewWorkflow`.
- `preapprove`.
- `approve`.
- `rejectAsSupervisor`.
- `rejectAsAdministrator`.
- `generateAgreement`.

Gates registrados:

- `create-promesa`.
- `review-promesas`.
- `preapprove-promesa`.
- `approve-promesa`.
- `reject-promesa-supervisor`.
- `reject-promesa-admin`.
- `generate-promesa-agreement`.

Reglas mantenidas:

- `supervisor` preaprueba y rechaza promesas pendientes.
- `administrador` aprueba y rechaza promesas preaprobadas.
- `asesor` no puede aprobar ni preaprobar.
- Los roles finales siguen siendo `administrador`, `supervisor`, `asesor`, `soporte`.

## Controladores modificados

### `PromesaController`

Ahora:

- Recibe `StorePromesaRequest`.
- Llama a `CreatePromesaAction`.
- Retorna `back()->with('ok', $message)`.

### `AutorizacionController`

Ahora:

- Usa `PromesaQueryService` para bandeja de promesas.
- Usa Actions para workflow de promesas.
- La logica CNA fue separada despues en Fase 8.3 hacia `CnaQueryService` y Actions/Services de CNA.
- Conserva rutas, redirects y mensajes actuales.

### `PromesaPdfController`

Ahora:

- Llama a `GeneratePromesaAgreementAction`.
- La generacion real vive en `PromesaDocumentService`.

## Compatibilidad legacy

Se mantiene:

- `app/Services/Promesas/PromesaCreator.php`.

Ahora funciona como wrapper de compatibilidad hacia `PromesaCreationService`. Esto evita romper referencias internas futuras o codigo que aun pueda resolver esa clase.

## Cuota balon

Comportamiento actual confirmado:

- El request puede enviar `tipo=convenio_balon`.
- El modelo `PromesaPago` normaliza `tipo` a `convenio` porque solo acepta `convenio` y `cancelacion`.
- La cuota balon se conserva en `promesa_cuotas.es_balon = 1`.

Decision V3:

- No cambiar persistencia en esta fase.
- No crear migracion.
- Documentar que `convenio_balon` es una variante de UI/flujo y la persistencia diferencial vive en las cuotas.

## Tests creados

Archivo:

- `tests/Feature/V3PromesaModuleTest.php`.

Cobertura:

- Crear promesa de cancelacion.
- Crear promesa de convenio.
- Crear promesa con cuota balon.
- Preaprobar como supervisor.
- Aprobar como administrador.
- Rechazar como supervisor.
- Rechazar como administrador.
- Bloquear aprobacion/preaprobacion para asesor.
- Generar acuerdo de promesa con fallback DOCX.

## Comportamiento mantenido

- Misma ruta `clientes.promesas.store`.
- Mismas rutas de Autorizacion:
  - `autorizacion.preaprobar`.
  - `autorizacion.rechazar.sup`.
  - `autorizacion.aprobar`.
  - `autorizacion.rechazar.admin`.
- Misma ruta `promesas.acuerdo`.
- Mismos middlewares.
- Mismos estados de workflow.
- Mismos redirects.
- Mismos mensajes flash principales.
- Mismo fallback DOCX si iLovePDF no tiene claves.
- Misma estructura de base de datos.

## Pendientes

- Refactor backend de CNA completado en `docs/24_refactor_backend_cna_v3.md`.
- Revisar si en una fase futura conviene persistir `convenio_balon` como tipo propio; requeriria migracion y validacion productiva.
