# OLA 8 - Administracion de usuarios (inventario)

- Estado: **inventario preparado el 2026-10-27. No implementada.**
- Alcance: auditoria de componentes, vistas, rutas, permisos y comportamiento actual. No se modificaron controladores, servicios, modelos, migraciones, rutas ni permisos.
- Preview auditado: `http://localhost:8091`, usuario `admin@preview.test`.
- Rama: `feature/meridian-ui-v3`.

## 1. Acceso y rutas

| Ruta | Metodo | Controlador | Nombre | Uso |
| --- | --- | --- | --- | --- |
| `/administracion` | GET | `AdminUsersController@index` | `administracion.index` | Listado, filtros y modales |
| `/administracion/usuarios` | POST | `AdminUsersController@store` | `administracion.usuarios.store` | Crear usuario |
| `/administracion/usuarios/{user}/toggle` | PATCH | `AdminUsersController@toggle` | `administracion.usuarios.toggle` | Activar/desactivar |
| `/administracion/usuarios/{user}/password` | PATCH | `AdminUsersController@updatePassword` | `administracion.usuarios.password` | Cambiar password |

- Todas las rutas estan bajo `can:access-admin-users`.
- El acceso lateral se renderiza con `@can('access-admin-users')` en `components/layout/sidebar.blade.php`.
- No existe ruta, metodo de controlador ni formulario de **edicion** de usuario.

## 2. Listado actual

Vista: `resources/views/placeholders/administracion.blade.php`.

- Contenedor raiz: `[data-admin-users-page]`.
- Filtros: busqueda `q` por nombre/email, checkbox `inactivos` y botones `Aplicar`/`Limpiar`.
- Paginacion: `UserService::getFilteredQuery(...)->paginate(15)`.
- Tabla actual: `x-ui.card` + `x-ui.table`.
- Columnas: `Nombre`, `Email`, `Rol`, `Supervisor`, `Estado`, `Acciones`.
- Acciones por fila:
  - `Password` si `UserPolicy::updatePassword` lo permite.
  - `Desactivar`/`Activar` si `UserPolicy::toggleStatus` lo permite y no es el usuario actual.
  - No hay accion `Editar`.
- Estado vacio: fila con `colspan="6"` y texto `Sin usuarios.`
- El boton `Nuevo usuario` solo aparece si `UserIndexViewModel::roleOptions()` no esta vacio.

Auditoria DOM del preview:

- 2 usuarios visibles: administrador y asesor.
- El administrador solo tiene `Password`; el asesor tiene `Password` y `Desactivar`.
- No hay controles de edicion.
- No hay paginacion visible porque solo hay 2 usuarios.
- Confirmacion de desactivacion: `onsubmit="return confirm(...)"` nativo.

## 3. Roles y permisos

Fuente central: `app/Support/Authorization/Roles.php`, `app/Policies/UserPolicy.php`, `app/Services/UserService.php`, `app/Services/Admin/UserStatusService.php`.

| Rol | Acceso a Administracion | Roles que puede crear | Visibilidad del listado | Gestion de usuarios | Cambio de password |
| --- | --- | --- | --- | --- | --- |
| `administrador` | Si | administrador, supervisor, asesor, soporte | Todos | Todos | Todos |
| `supervisor` | Si | asesor, soporte | El mismo + sus asesor/soporte directos (`supervisor_id`) | Solo asesor/soporte de su equipo | El mismo + su equipo |
| `soporte` | Si | Ninguno | Solo el mismo | Ninguno | Solo el mismo |
| `asesor` | No | - | - | - | - |

Reglas relevantes:

- `Roles::creatableUserRoles()` determina las opciones del select de rol.
- `UserService::applyRoleVisibility()` aplica la visibilidad por rol.
- `UserPolicy::manage()` usa `Roles::canManageUser()`.
- `UserPolicy::toggleStatus()` impide desactivarse a si mismo.
- `UserStatusService::toggle()` impide dejar el sistema sin administradores activos.
- `UserPolicy::updatePassword()` permite el propio password o el de un usuario gestionable.
- `Roles::ADMIN_USERS_ROLES` incluye administrador, supervisor y soporte; asesor no accede.
- No existe un editor de permisos por usuario: los permisos son fijos por rol.

## 4. Supervisores y asesores

- El formulario de alta muestra un select `supervisor_id` cuando el rol elegido es `asesor` o `soporte`.
- Si el usuario autenticado es supervisor, el formulario usa un `input hidden` con su propio id y oculta el select.
- `UserService::resolveSupervisorId()`:
  - Para roles distintos de asesor/soporte devuelve `NULL`.
  - Para supervisor autenticado asigna su propio id.
  - Para administrador exige que el `supervisor_id` solicitado exista, sea supervisor y este activo; si no, responde 422.
- `StoreUserRequest` valida `supervisor_id` como entero nullable; la validacion de existencia/rol/estado se hace en el servicio.
- En el preview actual no hay supervisores activos. Por tanto, el select de supervisor del alta de asesor/soporte queda vacio y el JS lo marca como requerido: **no se puede crear un asesor/soporte hasta crear primero un supervisor activo**. Es una condicion de datos y un caso de UX a resolver en OLA 8.
- No hay pantalla dedicada para crear/editar supervisores: se crean como usuarios con rol `supervisor`.

## 5. Formularios existentes

### Crear usuario

Modal: `#modalCreateUser`.

| Campo | Tipo | Requerido | Notas |
| --- | --- | --- | --- |
| `name` | text | Si | Max. 120 |
| `email` | email | Si | Unico, max. 190 |
| `role` | select | Si | Opciones segun rol autenticado |
| `supervisor_id` | select/hidden | Condicional | Requerido por JS para asesor/soporte; el backend lo valida |
| `password` | password | Si | Min. 6; sin confirmacion en el alta |

- El formulario se envia por POST a `administracion.usuarios.store`.
- El JS `resources/js/modules/admin/users.js` muestra/oculta el supervisor segun el rol.
- No hay campo de confirmacion de password en el alta; si se escribe mal, no se detecta en el formulario.

### Editar usuario

- **No existe.** No hay modal, formulario, ruta ni metodo `update`.
- Para implementarlo se requeriria, como minimo, una ruta PATCH, un metodo de controlador, validacion de email unico/rol/supervisor y una policy de actualizacion. Eso excede la restriccion actual de no tocar rutas/controladores/permisos y debe autorizarse explicitamente.
- Alternativa de bajo impacto: mantener el alta y el cambio de password, y no prometer edicion en OLA 8 hasta definir el alcance backend.

### Cambio de password

- Un modal `#pw-usr-{id}` por usuario habilitado.
- Campos `password` y `password_confirmation`, ambos requeridos, min. 6.
- Botones `Ver`/`Ocultar` por campo mediante `data-password-toggle`.
- Se envia por PATCH a `administracion.usuarios.password`.
- `updatePassword()` valida `confirmed` y aplica `Hash::make`.
- No hay medidor de fortaleza ni politica de complejidad adicional.

## 6. Activacion y desactivacion

- Accion `Desactivar` o `Activar` dentro de un formulario PATCH.
- Confirmacion actual: `confirm()` nativo del navegador.
- `UserStatusService` evita:
  - desactivar la propia cuenta;
  - desactivar al ultimo administrador activo.
- No hay campo de motivo, auditoria visual ni historial de cambios en la UI.
- Existe `x-ui.confirm-dialog` reutilizable, pero no se usa en esta vista.

## 7. Filtros, paginacion y busqueda

- Busqueda por `name` o `email` con `LIKE`.
- Filtro `inactivos=1` para ver cuentas inactivas; por defecto solo activas.
- No hay filtro por rol, supervisor, equipo ni rango de fechas.
- Paginacion fija de 15 por pagina, con `withQueryString()`.
- Atajo de teclado `Ctrl/Cmd + K` para enfocar la busqueda.
- El checkbox de inactivos se autoenvia al cambiar (`setupInactiveSwitch`).

## 8. Confirmaciones

- Desactivacion/activacion: `confirm()` nativo.
- Cambio de password: el propio modal actua como confirmacion; no hay dialogo adicional.
- Alta: no hay confirmacion previa; se envia el formulario.
- Componente disponible sin usar: `resources/views/components/ui/confirm-dialog.blade.php` (y alias `feedback.confirm-dialog`).
- Para OLA 8 se recomienda migrar las confirmaciones a `x-ui.confirm-dialog` sin cambiar las rutas ni las reglas.

## 9. Componentes reutilizables existentes

Layout y estructura:

- `x-layout.page-shell`
- `x-layout.page-header`
- `x-ui.card`
- `x-ui.table`
- `x-tables.table`, `x-tables.th`, `x-tables.td`, `x-tables.empty-row`, `x-tables.pagination`

Formularios y acciones:

- `x-ui.input`
- `x-ui.select`
- `x-ui.textarea`
- `x-ui.button`
- `x-ui.modal`
- `x-ui.confirm-dialog`
- `x-ui.badge`
- `x-ui.alert` / `x-feedback.alert`
- `x-ui.empty-state`
- `x-ui.dropdown`
- `x-ui.date`
- `x-ui.metric-strip`, `x-ui.stat-card`

Comportamiento JS:

- `resources/js/core/modal.js` (apertura, foco, Escape, restauracion).
- `resources/js/modules/admin/users.js` (modales, password visible, inactivos, Ctrl/Cmd+K, supervisor condicional).
- `resources/js/app.js` importa el modulo de administracion.

## 10. Pruebas y cobertura actual

- `tests/Feature/V3RoleAccessTest.php`:
  - accesos por rol a `/administracion`;
  - login de usuario inactivo;
  - rechazo de roles retirados (`sistemas`, `usuario`) al crear.
- No hay pruebas especificas de:
  - creacion exitosa de usuario;
  - activacion/desactivacion;
  - cambio de password;
  - asignacion de supervisor;
  - edicion de usuario (no existe);
  - validacion visual responsive de Administracion.

## 11. Hallazgos y decisiones pendientes

1. **Edicion de usuarios:** no existe. Si OLA 8 debe incluir editar nombre, email, rol o supervisor, se requiere autorizacion para tocar ruta, controlador, request y policy.
2. **Permisos:** no hay editor de permisos. Los permisos son fijos por rol; cualquier matriz editable implica un cambio de producto y backend.
3. **Supervisores:** no hay CRUD dedicado. Crear asesor/soporte requiere un supervisor activo; el preview actual no tiene ninguno y el select queda vacio.
4. **Confirmaciones:** reemplazar `confirm()` nativo por `x-ui.confirm-dialog` es una mejora visual de bajo riesgo, sin cambios de backend.
5. **Filtros:** solo existen busqueda y activos/inactivos. Agregar filtros por rol/supervisor requiere decidir si se hace en backend o solo en cliente; el listado ya esta paginado en servidor.
6. **Password:** el alta no pide confirmacion; el cambio si. Conviene uniformar el criterio sin cambiar la validacion minima de 6 caracteres.
7. **Componentes:** la vista usa `x-ui.table`/`x-ui.card`; las pantallas nuevas usan `x-tables.*` y `page-shell`. Migrar solo la vista, sin tocar componentes globales, reduce regresiones.
8. **Tests:** faltan pruebas de CRUD y de UI; OLA 8 deberia agregarlas si se autoriza tocar backend.

## 12. Criterios propuestos para OLA 8 (no implementados)

- Mantener las rutas y reglas de permisos actuales salvo autorizacion expresa.
- Usar `page-shell`, `page-header`, `x-tables.*`, `x-ui.confirm-dialog`, `x-ui.empty-state` y `x-tables.pagination`.
- Conservar la busqueda, el filtro de inactivos y la paginacion de 15.
- Mostrar estados vacios claros cuando no haya usuarios o supervisores disponibles.
- Validar 1440x900 y 390x844; tablas con scroll interno, sin overflow de pagina.
- Capturas con la convencion nueva:
  - `OLA8/ola8-antes-administracion-usuarios-1440x900.png`
  - `OLA8/ola8-despues-administracion-usuarios-1440x900.png`
  - `OLA8/ola8-despues-administracion-usuarios-390x844.png`
  - `OLA8/ola8-modal-crear-usuario-1440x900.png`
  - `OLA8/ola8-modal-editar-usuario-1440x900.png` (solo si se autoriza edicion)
  - `OLA8/ola8-modal-cambiar-password-1440x900.png`
  - `OLA8/ola8-confirmar-desactivar-1440x900.png`
- No implementar hasta recibir aprobacion explicita de alcance y de los cambios backend necesarios.

## 13. Evidencia de la auditoria

- Revision de codigo: `routes/web/admin.php`, `AdminUsersController`, `UserService`, `UserStatusService`, `StoreUserRequest`, `UserIndexViewModel`, `UserPolicy`, `Roles`, `HasTeamVisibility`, `administracion.blade.php`, `users.js`.
- Revision DOM del preview: `/administracion` con el administrador demo; sin errores de consola, `pageerror` ni respuestas >= 400.
- No se crearon, modificaron ni eliminaron usuarios durante esta auditoria.
- No se implemento ningun cambio de OLA 8.
