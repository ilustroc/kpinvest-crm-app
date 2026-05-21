# Notificaciones internas

## Objetivo

La Fase 8.8 agrega una campanita interna para Promesas y CNA usando Laravel Notifications con canal `database`.
La Fase 8.9 agrega actualizacion realtime con Laravel Reverb y Echo.

No incluye todavia Web Push, Service Worker ni notificaciones con el navegador cerrado.

## Tabla usada

Se usa la tabla Laravel `notifications`.

Migracion local creada:

```text
database/migrations/2026_05_21_122013_create_notifications_table.php
```

La migracion se probo en local. Produccion no debe tocarse hasta la fase de deploy controlado.

## Tipos de notificacion

### Accion requerida

Se crea cuando el receptor debe revisar o aprobar:

- Supervisor debe preaprobar Promesa.
- Supervisor debe preaprobar CNA.
- Administrador debe aprobar Promesa.
- Administrador debe aprobar CNA.

Datos principales:

- `type`: `promesa_action_required` o `cna_action_required`.
- `module`: `promesa` o `cna`.
- `entity_id`.
- `dni`.
- `cliente`.
- `estado`.
- `title`.
- `message`.
- `action_label`: `Abrir autorizacion`.
- `action_url`: ruta `autorizacion` filtrada.
- `created_by`.
- `created_by_name`.

### Estado informativo

Se crea cuando el receptor solo debe consultar el estado:

- Promesa enviada, preaprobada, aprobada o rechazada.
- CNA enviada, preaprobada, aprobada o rechazada.
- Solicitudes creadas por administrador y aprobadas directamente.

Datos principales:

- `type`: `promesa_status` o `cna_status`.
- `module`: `promesa` o `cna`.
- `action_label`: `Ver estado`.
- `action_url`: ruta `clientes.show`.

## Reglas de destinatarios

Las notificaciones reutilizan las reglas centralizadas de `WorkflowMailer`:

- Si crea un asesor, se notifica al supervisor asignado.
- Si el asesor no tiene supervisor activo con correo valido, se usa fallback a administradores activos.
- Si crea un supervisor o si un supervisor preaprueba, se notifica a administradores activos.
- Si un administrador aprueba/rechaza, se informa al creador.
- Si un administrador crea directamente, la solicitud queda aprobada y recibe notificacion informativa.

## Implementacion

- `app/Support/WorkflowNotifier.php` coordina correos y notificaciones database.
- `app/Support/WorkflowMailer.php` conserva la logica de destinatarios, contexto y URLs.
- `app/Notifications/Workflow/WorkflowActionRequiredNotification.php` guarda acciones pendientes.
- `app/Notifications/Workflow/WorkflowStatusNotification.php` guarda estados informativos.
- `app/Services/Notification/UserNotificationService.php` lista, cuenta y marca notificaciones.
- `app/Http/Controllers/NotificationController.php` expone endpoints JSON.
- `routes/web/notificaciones.php` contiene rutas protegidas por `auth` y `active`.

## Campanita

Componente:

```text
resources/views/components/layout/notifications-bell.blade.php
```

Integracion:

```text
resources/views/components/layout/topbar.blade.php
```

JS modular:

```text
resources/js/modules/layout/notifications.js
```

La campanita:

- muestra contador de no leidas;
- usa badge `9+`;
- abre panel de notificaciones;
- permite abrir el CTA y marcar como leida;
- permite marcar todas como leidas;
- reproduce un sonido corto cuando detecta nuevas no leidas;
- se actualiza en tiempo real si Reverb esta corriendo;
- mantiene fetch como fallback si WebSocket no esta disponible.

## Realtime

Detalle tecnico:

```text
docs/02_modulos/notificaciones_realtime.md
```

## Rutas

```text
GET  /notificaciones
GET  /notificaciones/unread-count
POST /notificaciones/{id}/leer
POST /notificaciones/leer-todas
```

## Pruebas

`tests/Feature/V3NotificationModuleTest.php` valida:

- Promesa creada por asesor notifica al supervisor.
- Promesa creada por supervisor notifica al administrador.
- CNA creada por asesor notifica al supervisor.
- CNA creada por supervisor notifica al administrador.
- Notificaciones de accion usan `Abrir autorizacion`.
- Notificaciones informativas usan `Ver estado`.
- Endpoints de listado, contador y marcado funcionan.
- Un usuario no puede marcar notificaciones ajenas.

## Pendiente

- Evaluar polling suave, Reverb o Echo en una fase futura.
- Evaluar Web Push si se requiere notificar fuera del sistema abierto.
- Validar sonido en navegadores reales, porque algunos bloquean audio hasta interaccion del usuario.
