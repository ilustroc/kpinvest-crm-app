# 24 - Refactor backend CNA V3

## Objetivo

Ordenar el backend del modulo CNA sin cambiar rutas, nombres de rutas, estructura de base de datos, formularios, redirects, mensajes principales ni reglas funcionales.

Esta fase corresponde a:

```text
Fase 8.3 - Refactor backend del modulo CNA
```

## Archivos revisados

- `app/Http/Controllers/CnaController.php`.
- `app/Http/Controllers/AutorizacionController.php`.
- `app/Models/CnaSolicitud.php`.
- `app/Models/ClienteCuenta.php`.
- `routes/web/cna.php`.
- `routes/web/clientes.php`.
- `resources/views/autorizacion/index.blade.php`.
- `resources/views/components/cna/*`.
- `tests/Feature/V3WorkflowSmokeTest.php`.

## Responsabilidades detectadas antes del refactor

### `CnaController`

Concentraba:

- Creacion de solicitudes CNA desde Cliente.
- Validacion del request.
- Verificacion de operaciones contra el DNI.
- Deteccion de origen por cosecha/entidad.
- Numeracion de carta.
- Autoaprobacion cuando crea un administrador.
- Workflow de supervisor y administrador.
- Generacion de DOCX.
- Intento de conversion PDF con iLovePDF.
- Fallback a DOCX.
- Descargas PDF/DOCX.
- Seleccion de plantillas.
- Logging de errores de creacion y conversion.

### `AutorizacionController`

Concentraba:

- Consulta de bandeja de Promesas.
- Consulta de bandeja CNA.
- Filtros por busqueda, estado, rol y equipo.
- Enriquecimiento de operaciones CNA con producto.

## Servicios creados

### `app/Services/Cna/CnaCreationService.php`

Responsabilidad:

- Crear solicitudes CNA desde datos validados.
- Verificar que las operaciones pertenezcan al DNI.
- Determinar producto y origen.
- Usar `CnaNumberingService` para generar `nro_carta`.
- Mantener autoaprobacion de administrador.
- Mantener correo de CNA pendiente.
- Retornar `[CnaSolicitud, mensaje]`.

### `app/Services/Cna/CnaNumberingService.php`

Responsabilidad:

- Determinar origen desde cosecha/entidad.
- Resolver serie, sufijo y plantilla.
- Calcular el siguiente numero de carta con bloqueo sobre `cna_solicitudes`.
- Mantener formato y criterio actual de numeracion.

### `app/Services/Cna/CnaWorkflowService.php`

Responsabilidad:

- Preaprobar CNA.
- Aprobar CNA.
- Rechazar CNA como supervisor.
- Rechazar CNA como administrador.
- Validar estados actuales antes de cambiar.
- Mantener notas de aprobacion/preaprobacion.
- Generar documentos al aprobar.
- Mantener correos actuales.

### `app/Services/Cna/CnaDocumentService.php`

Responsabilidad:

- Seleccionar plantilla DOCX segun origen.
- Llenar placeholders actuales.
- Generar DOCX.
- Intentar conversion con iLovePDF.
- Mantener fallback a DOCX cuando faltan claves o falla conversion.
- Descargar PDF o DOCX segun disponibilidad.

### `app/Services/Cna/CnaQueryService.php`

Responsabilidad:

- Consultar bandeja CNA para Autorizacion.
- Aplicar visibilidad por equipo.
- Aplicar filtros por busqueda y estado.
- Aplicar estado base segun rol.
- Enriquecer operaciones con producto para la vista.

## Actions creadas

- `app/Actions/Cna/CreateCnaAction.php`.
- `app/Actions/Cna/PreapproveCnaAction.php`.
- `app/Actions/Cna/ApproveCnaAction.php`.
- `app/Actions/Cna/RejectCnaAction.php`.
- `app/Actions/Cna/DownloadCnaDocumentAction.php`.

No se creo `GenerateCnaDocumentAction` porque la generacion ocurre dentro del flujo de creacion/autoaprobacion y aprobacion. La descarga queda centralizada en `DownloadCnaDocumentAction`.

## Policy creada

### `app/Policies/CnaPolicy.php`

Permisos definidos:

- `create`.
- `viewWorkflow`.
- `preapprove`.
- `approve`.
- `rejectAsSupervisor`.
- `rejectAsAdministrator`.
- `generateDocument`.
- `downloadDocument`.

Gates registrados:

- `create-cna`.
- `review-cna`.
- `preapprove-cna`.
- `approve-cna`.
- `reject-cna-supervisor`.
- `reject-cna-admin`.
- `generate-cna-document`.
- `download-cna-document`.

Reglas mantenidas:

- `supervisor` preaprueba y rechaza CNA pendientes.
- `administrador` aprueba y rechaza CNA preaprobadas.
- `asesor` no puede aprobar ni preaprobar.
- Los roles finales siguen siendo `administrador`, `supervisor`, `asesor`, `soporte`.

## Controladores modificados

### `CnaController`

Ahora:

- Valida el request de creacion.
- Llama a `CreateCnaAction`.
- Llama a Actions de workflow.
- Llama a `DownloadCnaDocumentAction` para descargas.
- Mantiene rutas, redirects y mensajes actuales.

### `AutorizacionController`

Ahora:

- Usa `AutorizacionIndexService` para componer la bandeja.
- `AutorizacionIndexService` coordina `PromesaQueryService` y `CnaQueryService`.
- Ya no arma directamente la consulta CNA ni el mapa de productos.

## Documentos CNA y fallback

Comportamiento mantenido:

- Plantilla KP Invest: `storage/app/templates/cna_kpinvest.docx`.
- Plantilla Fondo Acreencia Arequipa: `storage/app/templates/cna_fondo_acreencia_arequipa.docx`.
- Plantilla Acreencia II: `storage/app/templates/cna_fondo_acreencia_arequipa_2.docx`.
- Nombre DOCX: `CNA {nro_carta} - {dni}.docx`.
- Nombre PDF: `CNA {nro_carta} - {dni}.pdf`.
- Si iLovePDF no tiene claves o falla, se conserva el DOCX.
- Si se solicita PDF y no existe, se intenta entregar DOCX como fallback.
- Si no existe ningun archivo, se mantiene `404 Archivo no encontrado`.

## Tests creados

Archivo:

- `tests/Feature/V3CnaModuleTest.php`.

Cobertura:

- Crear CNA como asesor y dejarla pendiente.
- Crear CNA como administrador y autoaprobar.
- Preaprobar como supervisor.
- Rechazar como supervisor.
- Aprobar como administrador.
- Rechazar como administrador.
- Bloquear aprobacion/preaprobacion para asesor/supervisor no autorizado.
- Descargar DOCX.
- Validar ruta PDF con fallback a DOCX.

## Comportamiento mantenido

- Misma ruta `clientes.cna.store`.
- Mismas rutas de workflow:
  - `cna.preaprobar`.
  - `cna.rechazar.sup`.
  - `cna.aprobar`.
  - `cna.rechazar.admin`.
- Mismas rutas de descarga:
  - `cna.docx`.
  - `cna.pdf`.
- Mismos middlewares.
- Mismos estados de workflow.
- Mismos redirects.
- Mismos mensajes flash principales.
- Mismo fallback documental.
- Misma estructura de base de datos.

## Pendientes

- Refactor especifico de `AutorizacionController` completado en `docs/25_refactor_backend_autorizacion_v3.md`.
- Revisar manualmente las plantillas reales en local/staging antes de deploy.
- Validar visualmente acciones de workflow CNA en navegador real.
