# Modulo CNA

## Alcance

Gestiona creacion de solicitudes CNA, seleccion de operaciones, numeracion, workflow, generacion de documentos DOCX/PDF y descargas.

## Backend V3

Servicios:

- `CnaCreationService`
- `CnaNumberingService`
- `CnaWorkflowService`
- `CnaDocumentService`
- `CnaQueryService`

Actions:

- `CreateCnaAction`
- `PreapproveCnaAction`
- `ApproveCnaAction`
- `RejectCnaAction`
- `DownloadCnaDocumentAction`

Policy:

- `CnaPolicy`

## Controladores

- `CnaController` delega creacion, workflow y descargas.
- `AutorizacionController` usa `CnaQueryService` para la bandeja CNA.

## Documentos

`CnaDocumentService` mantiene nombres, rutas y fallback actuales. Si faltan plantillas o claves externas, el flujo debe conservar el fallback documentado.

## Comportamiento preservado

- Rutas y nombres de rutas.
- Estados del workflow.
- Mensajes y redirects.
- Formularios POST y CSRF.
- Descarga DOCX/PDF o fallback.

## Tests

`tests/Feature/V3CnaModuleTest.php` cubre creacion, workflow, permisos, descargas y fallback.

## Pendientes

- Confirmar plantillas DOCX reales en entorno de deploy.
- Validacion manual visual del workflow CNA.
