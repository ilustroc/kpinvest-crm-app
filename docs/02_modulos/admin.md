# Modulo Admin

## Alcance

Administracion de usuarios, roles, activacion/desactivacion y cambio de contrasena.

## Rutas

Prefijo `admin` protegido por `can:access-admin-users`:

- `admin.index`
- `admin.usuarios.store`
- `admin.usuarios.toggle`
- `admin.usuarios.password`

## Backend V3

- `AdminUsersController` usa `UserService`.
- `UserStatusService` protege activacion/desactivacion.
- `UserIndexViewModel` prepara opciones de pantalla.
- `UserPolicy` centraliza permisos.

## Roles permitidos

- `administrador`
- `supervisor`
- `asesor`
- `soporte`

## Frontend

Administracion fue la primera pantalla piloto migrada a Tailwind/Vite.

## Tests

Los flujos de administracion estan cubiertos en tests de roles y smoke tests.

## Pendientes

- Validacion manual visual.
- Confirmar que siempre exista al menos un administrador activo antes de deploy.
