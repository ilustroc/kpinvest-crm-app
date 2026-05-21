# Correos de Workflow

## Objetivo

Los correos de Promesas y CNA notifican acciones pendientes o cambios de estado sin reemplazar la bandeja de Autorizacion ni la vista del Cliente.

Desde Fase 8.8, el mismo flujo tambien genera notificaciones internas en la tabla `notifications` mediante `WorkflowNotifier`.

## Regla de botones

- `Abrir autorizacion`: solo se usa cuando el receptor tiene una accion pendiente.
- `Ver estado`: se usa para correos informativos y apunta a `clientes.show`.

`Abrir autorizacion` apunta a la ruta `autorizacion` con filtros:

```text
/autorizacion?tipo={promesa|cna}&id={id}&q={dni}&status={pendiente|preaprobada}
```

`Ver estado` apunta a:

```text
/clientes/{dni}
```

## Flujo por rol creador

### Asesor

- Promesa/CNA queda en estado `pendiente`.
- Se envia correo al supervisor asignado.
- Si el asesor no tiene supervisor activo con correo valido, se envia a administradores activos como fallback.
- El boton para el supervisor o fallback administrativo es `Abrir autorizacion`.
- Cuando supervisor preaprueba, se envia correo a administradores activos.

### Supervisor

- Promesa/CNA queda en estado `preaprobada`.
- No requiere preaprobacion de otro supervisor.
- Se envia correo a administradores activos.
- El boton es `Abrir autorizacion`.

### Administrador

- Promesa/CNA queda en estado `aprobada`.
- No pasa por bandeja de preaprobacion/aprobacion.
- El correo informativo, cuando se envia, usa `Ver estado` y apunta a la ficha del cliente.

## Tipos de correo

| Modulo | Evento | Receptor | Boton | Destino |
| --- | --- | --- | --- | --- |
| Promesa | Asesor crea | Supervisor asignado o administradores fallback | Abrir autorizacion | Autorizacion filtrada |
| Promesa | Supervisor crea o preaprueba | Administradores activos | Abrir autorizacion | Autorizacion filtrada |
| Promesa | Administrador aprueba/rechaza | Creador | Ver estado | Cliente |
| CNA | Asesor crea | Supervisor asignado o administradores fallback | Abrir autorizacion | Autorizacion filtrada |
| CNA | Supervisor crea o preaprueba | Administradores activos | Abrir autorizacion | Autorizacion filtrada |
| CNA | Administrador aprueba/rechaza | Creador | Ver estado | Cliente |

## Datos incluidos

Promesas:

- Cliente.
- Documento.
- Operacion u operaciones.
- Tipo de promesa.
- Monto.
- Fecha de pago.
- Asesor.
- Estado actual.
- Observacion y nota de decision si aplica.

CNA:

- Nro. carta.
- Cliente.
- Documento.
- Operacion u operaciones.
- Procede de / asesor.
- Estado actual.
- Observacion y nota de decision si aplica.

## Implementacion

- `app/Support/WorkflowMailer.php` centraliza destinatarios, asuntos, CTA y rutas.
- `app/Support/WorkflowNotifier.php` reutiliza esas reglas y genera correos + notificaciones internas.
- `app/Mail/WorkflowMail.php` representa el correo.
- `resources/views/mail/workflow.blade.php` contiene el diseno HTML compatible con clientes de correo.
- `PromesaCreationService` y `PromesaWorkflowService` disparan correos de Promesas.
- `CnaCreationService` y `CnaWorkflowService` disparan correos de CNA.

## Pruebas

`tests/Feature/V3MailWorkflowTest.php` valida:

- Flujo de creacion por asesor, supervisor y administrador.
- Botones correctos segun si hay accion pendiente o solo estado informativo.
- Destinos correctos: Autorizacion para acciones, Cliente para estado.
- Correos informativos de resolucion.

`tests/Feature/V3NotificationModuleTest.php` valida el equivalente interno en campanita y endpoints JSON.
