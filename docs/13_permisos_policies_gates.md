# 13 - Permisos, Policies y Gates V3

## Estado

- [+] Roles actuales revisados.
- [+] Validaciones de roles actuales identificadas.
- [+] Base de permisos centralizada creada.
- [+] `UserPolicy` creada.
- [+] Gates iniciales registrados.
- [+] Documentacion inicial creada.
- [!] Permisos internos de Promesas, CNA y Cliente aun mezclan controladores, rutas y vistas.

## Roles actuales

Roles detectados en codigo:

- `administrador`.
- `sistemas`.
- `supervisor`.
- `asesor`.
- `soporte`.
- `usuario`.

Roles detectados en base local:

- `administrador`.
- `supervisor`.
- `asesor`.
- `soporte`.

Nota: `sistemas` y `usuario` estan contemplados por codigo, aunque no aparecieron en el listado local revisado.

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

## Gates iniciales

Gates definidos:

- `access-dashboard`: acceso general a Dashboard para usuarios autenticados activos.
- `access-admin-users`: administracion de usuarios.
- `access-reportes`: reportes.
- `access-integracion`: importaciones e integraciones.
- `review-promesas`: bandeja/workflow de promesas.
- `review-cna`: bandeja/workflow de CNA.
- `delete-client-payments`: eliminacion de pagos desde cliente.

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
- [!] Algunas rutas tenian diferencias con el sidebar. Ejemplo: `sistemas` aparecia en UI pero no siempre en middleware.
- [!] Reportes no tenian middleware especifico antes de esta fase.
- [!] Promesas y CNA aun tienen autorizaciones internas en controladores.
- [!] Cliente tiene permisos de eliminacion de pagos directamente en controlador/vista.

## Recomendacion V3

No mover todos los permisos de golpe.

Orden recomendado:

1. Mantener `Roles` como fuente central de listas de roles.
2. Migrar rutas a `can:*` por modulo.
3. Crear `PromesaPolicy` para workflow de promesas.
4. Crear `CnaPolicy` para workflow y descargas CNA.
5. Crear `ClientePolicy` para acciones de cliente, pagos y visibilidad.
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
- [!] Promesas, CNA y Cliente quedan para Fase 5 porque son flujos criticos.
