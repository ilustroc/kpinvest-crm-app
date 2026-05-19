# Modulo Promesas

## Alcance

Gestiona creacion de promesas de pago, cronogramas, operaciones asociadas, cuota balon, workflow de aprobacion y generacion de acuerdo.

## Backend V3

Servicios:

- `PromesaCreationService`
- `PromesaScheduleService`
- `PromesaWorkflowService`
- `PromesaDocumentService`
- `PromesaQueryService`

Actions:

- `CreatePromesaAction`
- `PreapprovePromesaAction`
- `ApprovePromesaAction`
- `RejectPromesaAction`
- `GeneratePromesaAgreementAction`

Policy:

- `PromesaPolicy`

## Controladores

- `PromesaController` delega creacion en `CreatePromesaAction`.
- `AutorizacionController` delega workflow de promesas en Actions.
- `PromesaPdfController` delega generacion de acuerdo en `PromesaDocumentService`.

## Compatibilidad legacy

`app/Services/Promesas/PromesaCreator.php` se mantiene como wrapper legacy y delega en `PromesaCreationService`.

## Cuota balon

La variante de cuota balon se conserva sin cambio de persistencia. La cuota se marca en `promesa_cuotas.es_balon`; no se cambia el modelo ni enums en esta fase.

## Comportamiento preservado

- Creacion de cancelacion, convenio y cuota balon.
- Estados y flujo de supervisor/administrador.
- Redirects y mensajes.
- Fallback DOCX cuando no hay claves iLovePDF.
- Rutas y nombres de rutas.

## Tests

`tests/Feature/V3PromesaModuleTest.php` cubre creacion, workflow, permisos y acuerdo con fallback.

## Pendientes

- Evaluar persistencia explicita de `convenio_balon` solo si se planifica migracion segura.
- Validacion manual visual del flujo completo en navegador.
