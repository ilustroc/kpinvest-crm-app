# Notificaciones realtime con Reverb

## Objetivo

La Fase 8.9 agrega actualizacion en tiempo real para la campanita usando Laravel Reverb, Laravel Echo y `pusher-js`.

Esta fase no implementa Web Push, Service Worker ni notificaciones con el navegador cerrado.

## Dependencias

Backend:

```text
laravel/reverb
```

Frontend:

```text
laravel-echo
pusher-js
```

## Variables de entorno

`.env.example` define valores locales sin secretos reales:

```text
BROADCAST_CONNECTION=reverb
BROADCAST_DRIVER=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_CLIENT_TIMEOUT=1
REVERB_CLIENT_CONNECT_TIMEOUT=1

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

En produccion estos valores deben reemplazarse por credenciales y host reales.

## Canal privado

Se usa el canal privado estandar de Laravel Notifications:

```text
private-App.Models.User.{id}
```

`routes/channels.php` autoriza solo al propietario:

```php
return (int) $user->id === (int) $id;
```

## Flujo

1. Promesa/CNA dispara `WorkflowNotifier`.
2. `WorkflowNotifier` guarda notificacion database.
3. La misma notificacion se emite por canal `broadcast`.
4. Laravel Reverb entrega el evento WebSocket.
5. Laravel Echo lo recibe en `resources/js/modules/layout/notifications.js`.
6. La campanita actualiza contador, panel, toast y sonido.

## Fallback

Si Reverb no esta corriendo:

- la campanita sigue consultando `/notificaciones/unread-count`;
- el panel sigue cargando `/notificaciones`;
- los errores WebSocket no bloquean la UI;
- el workflow y los correos no se rompen.
- el cliente backend de broadcast usa timeouts cortos para evitar esperas largas si el servidor no esta arriba.

## Comandos locales

En una terminal:

```bash
php artisan reverb:start
```

En otra terminal:

```bash
npm run dev
```

Luego abrir la app local, iniciar sesion y generar una Promesa/CNA que notifique al usuario conectado.

## Produccion

Reverb necesita proceso permanente, por ejemplo con Supervisor/systemd o el sistema de procesos del hosting.

Tambien se debe confirmar:

- host publico o interno del WebSocket;
- TLS si aplica;
- `BROADCAST_CONNECTION=reverb`;
- variables `VITE_REVERB_*` durante build;
- politica de origen permitida.

## Pruebas

`tests/Feature/V3RealtimeNotificationTest.php` valida:

- las notificaciones usan canales `database` y `broadcast`;
- el payload broadcast conserva CTA y URLs;
- el canal privado solo autoriza al usuario propietario.

## Pendiente

- Web Push para navegador cerrado.
- Service Worker.
- Estrategia de reconexion/monitoreo productivo mas detallada.
