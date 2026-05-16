# 13 - Permisos, Policies y Gates V3

## Estado

- [+] Roles actuales revisados.
- [+] Validaciones de roles actuales identificadas.
- [+] Base de permisos centralizada creada.
- [+] `UserPolicy` creada.
- [+] Gates iniciales registrados.
- [+] Documentacion inicial creada.
- [+] Roles `sistemas` y `usuario` eliminados como roles validos de V3.
- [+] Accesos basicos por rol final validados con `tests/Feature/V3RoleAccessTest.php`.
- [+] Usuario inactivo validado con test de login bloqueado.
- [+] Permisos internos de Cliente centralizados en `ClientePolicy`.
- [+] Permisos internos de Promesas centralizados en `PromesaPolicy`.
- [+] Permisos internos de CNA centralizados en `CnaPolicy`.

## Roles actuales

Roles anteriores detectados en codigo/base:

- `administrador`.
- `supervisor`.
- `asesor`.
- `soporte`.
- `sistemas`.
- `usuario`.

Roles eliminados en V3:

- `sistemas`.
- `usuario`.

Roles finales permitidos:

- `administrador`.
- `supervisor`.
- `asesor`.
- `soporte`.

Estado de la base local revisada:

- No habia usuarios con rol `sistemas` ni `usuario`.
- La migracion V3 convierte esos roles a `soporte` si aparecen en otro dump.

## Donde se validan permisos hoy

Actualmente los permisos aparecen en:

- `routes/web/*.php`, mediante middleware `role:*` y ahora algunos `can:*`.
- `app/Http/Middleware/RoleMiddleware.php`.
- `app/Services/UserService.php`.
- `app/Support/Traits/HasTeamVisibility.php`.
- `app/Http/Controllers/AutorizacionController.php`.
- `app/Http/Controllers/CnaController.php`.
- `app/Http/Controllers/ClienteController.php`.
- Vistas Blade, especialmente sidebar, administracion, cliente y panel.

## Base implementada

Archivos creados o actualizados:

- `app/Support/Authorization/Roles.php`.
- `app/Policies/UserPolicy.php`.
- `app/Providers/AuthServiceProvider.php`.
- `routes/web/admin.php`.
- `routes/web/integracion.php`.
- `routes/web/reportes.php`.
- `resources/views/components/layout/sidebar.blade.php`.
- `app/Http/Requests/StoreUserRequest.php`.
- `app/Services/UserService.php`.
- `app/Http/Controllers/AutorizacionController.php`.
- `app/Http/Controllers/CnaController.php`.
- `app/Http/Controllers/ClienteController.php`.
- `app/Services/Promesas/PromesaCreator.php`.
- `app/Policies/ClientePolicy.php`.
- `app/Policies/PromesaPolicy.php`.
- `app/Policies/CnaPolicy.php`.

## Gates iniciales

Gates definidos:

- `access-dashboard`: acceso general a Dashboard para usuarios autenticados activos.
- `access-admin-users`: administracion de usuarios.
- `access-reportes`: reportes.
- `access-integracion`: importaciones e integraciones.
- `review-promesas`: bandeja/workflow de promesas.
- `review-cna`: bandeja/workflow de CNA.
- `delete-client-payments`: eliminacion de pagos desde cliente.
- `view-cliente`: vista de Cliente.
- `search-clientes`: busqueda de Cliente.
- `create-cliente-promesa`: creacion de promesa desde Cliente.
- `create-cliente-cna`: creacion de CNA desde Cliente.
- `create-promesa`: creacion de Promesa.
- `preapprove-promesa`: preaprobacion de Promesa.
- `approve-promesa`: aprobacion de Promesa.
- `reject-promesa-supervisor`: rechazo de Promesa como supervisor.
- `reject-promesa-admin`: rechazo de Promesa como administrador.
- `generate-promesa-agreement`: generacion de acuerdo de Promesa.
- `create-cna`: creacion de CNA.
- `preapprove-cna`: preaprobacion de CNA.
- `approve-cna`: aprobacion de CNA.
- `reject-cna-supervisor`: rechazo de CNA como supervisor.
- `reject-cna-admin`: rechazo de CNA como administrador.
- `generate-cna-document`: generacion de documento CNA.
- `download-cna-document`: descarga de documento CNA.

## Policy inicial

Policy creada:

```text
app/Policies/UserPolicy.php
```

Metodos:

- `viewAny`.
- `create`.
- `manage`.
- `toggleStatus`.
- `updatePassword`.

Objetivo:

- Sacar reglas de usuarios desde controladores y vistas.
- Evitar repetir reglas de administrador, supervisor y soporte.
- Mantener el comportamiento actual de administracion.

## Riesgos detectados

- [!] Hay reglas de roles repetidas en varias capas.
- [+] Se elimino el rol `sistemas` de reglas de admin/aprobacion.
- [+] Se elimino el rol `usuario` como rol creable/gestionable.
- [!] Reportes no tenian middleware especifico antes de esta fase.
- [+] Promesas usa Actions + Gates para workflow y acuerdo.
- [+] CNA usa Actions + Gates para workflow y descargas.
- [+] Cliente usa Action/Gate para eliminacion de pagos.

## Recomendacion V3

No mover todos los permisos de golpe.

Orden recomendado:

1. Mantener `Roles` como fuente central de listas de roles.
2. Migrar rutas a `can:*` por modulo.
3. Mantener `PromesaPolicy` para workflow de promesas.
4. Mantener `CnaPolicy` para workflow y descargas CNA.
5. Mantener `ClientePolicy` para acciones de cliente, pagos y visibilidad.
6. Crear `ReportePolicy` si los reportes empiezan a diferir por rol.
7. Reemplazar validaciones Blade por `@can`.
8. Agregar tests de permisos por rol.

## Modulos que deben tener permisos propios

- Admin: usuarios, roles, estado y contrasenas.
- Cliente: vista de cliente, eliminacion de pagos, creacion de promesas y CNA.
- Promesa: creacion, preaprobacion, aprobacion, rechazo y documento.
- CNA: creacion, preaprobacion, aprobacion, rechazo, DOCX y PDF.
- Reportes: acceso, filtros, exports.
- Integracion: importaciones y templates.
- Dashboard: acceso y filtros por rol.

## Estado final de esta fase

- [+] Base de permisos V3 creada.
- [+] Admin, Integracion y Reportes ya pasan por Gates.
- [+] Sidebar ya consulta Gates.
- [+] Administracion de usuarios usa `UserPolicy`.
- [+] Roles finales reducidos a `administrador`, `supervisor`, `asesor`, `soporte`.
- [+] Migracion de limpieza de roles creada.
- [+] Administrador, supervisor, asesor y soporte validados contra rutas principales.
- [+] `sistemas` y `usuario` rechazados como roles creables.
- [+] `ClientePolicy`, `PromesaPolicy` y `CnaPolicy` creadas.
- [+] Tests de Cliente, Promesas y CNA cubren permisos basicos de modulos criticos.
- [!] Supervisor y soporte conservan acceso a administracion segun reglas actuales, pero no con alcance total de administrador.
- [!] Autorizacion puede separarse luego en ViewModel/modulo propio para reducir aun mas la bandeja.
