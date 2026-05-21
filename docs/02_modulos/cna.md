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

## Correos

Los correos de CNA usan `WorkflowMailer`:

- Asesor crea: estado `pendiente`, correo al supervisor con `Abrir autorizacion`.
- Supervisor crea o preaprueba: estado `preaprobada`, correo a administradores con `Abrir autorizacion`.
- Administrador crea o resuelve: estado final, correo informativo con `Ver estado` hacia `clientes.show`.

Detalle completo en [Correos de workflow](correos_workflow.md).

## Tests

`tests/Feature/V3CnaModuleTest.php` cubre creacion, workflow, permisos, descargas y fallback.
`tests/Feature/V3MailWorkflowTest.php` cubre correos y destinos por rol.

## Pendientes

- Confirmar plantillas DOCX reales en entorno de deploy.
- Validacion manual visual del workflow CNA.
