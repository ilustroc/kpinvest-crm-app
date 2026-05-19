# Permisos, Policies y Gates

## Roles finales

Roles validos en V3:

- `administrador`
- `supervisor`
- `asesor`
- `soporte`

Los roles `sistemas` y `usuario` fueron eliminados como roles validos. Si aparecen en documentacion o pruebas negativas, son historicos.

## Archivo central

`app/Support/Authorization/Roles.php` centraliza:

- Roles finales.
- Roles con acceso a administracion.
- Roles con acceso a reportes.
- Roles con acceso a integraciones.
- Roles revisores de workflow.
- Reglas de administracion de usuarios.

## Gates

Los Gates se registran en `app/Providers/AuthServiceProvider.php`.

Principales:

- `access-dashboard`
- `access-admin-users`
- `access-reportes`
- `access-integracion`
- `review-promesas`
- `review-cna`
- `view-cliente`
- `search-clientes`
- `delete-client-payments`
- `create-cliente-promesa`
- `create-cliente-cna`
- `create-promesa`
- `preapprove-promesa`
- `approve-promesa`
- `reject-promesa-supervisor`
- `reject-promesa-admin`
- `generate-promesa-agreement`
- `create-cna`
- `preapprove-cna`
- `approve-cna`
- `reject-cna-supervisor`
- `reject-cna-admin`
- `generate-cna-document`
- `download-cna-document`

## Policies

- `UserPolicy`: administracion de usuarios.
- `ClientePolicy`: busqueda, vista, creacion de promesa/CNA y eliminacion de pagos.
- `PromesaPolicy`: creacion, workflow y generacion de acuerdo.
- `CnaPolicy`: creacion, workflow, documentos y descargas.

## Reglas actuales

- Administrador aprueba Promesas y CNA.
- Supervisor preaprueba y rechaza en etapa supervisor.
- Asesor opera cliente/promesa/CNA segun reglas actuales, pero no aprueba workflow.
- Soporte conserva accesos operativos definidos, sin aprobar workflow.

## Pendientes

- Reemplazar middlewares `role:*` restantes por Gates cuando sea seguro.
- Validar manualmente visibilidad en navegador por cada rol.
