# 16 - Limpieza de Roles V3

## Objetivo

Reducir los roles del CRM a una lista final y mantener permisos mas faciles de auditar.

## Roles anteriores

Roles historicos detectados:

- `administrador`.
- `supervisor`.
- `asesor`.
- `sistemas`.
- `soporte`.
- `usuario`.

## Roles eliminados

Se eliminaron como roles validos de V3:

- `sistemas`.
- `usuario`.

## Roles finales permitidos

V3 permite solo:

- `administrador`.
- `supervisor`.
- `asesor`.
- `soporte`.

## Limpieza aplicada en codigo

Archivos actualizados:

- `app/Support/Authorization/Roles.php`.
- `app/Providers/AuthServiceProvider.php`.
- `app/Policies/UserPolicy.php`.
- `app/Http/Requests/StoreUserRequest.php`.
- `app/Services/UserService.php`.
- `app/ViewModels/Admin/UserIndexViewModel.php`.
- `app/Http/Controllers/AdminUsersController.php`.
- `app/Http/Controllers/AutorizacionController.php`.
- `app/Http/Controllers/CnaController.php`.
- `app/Http/Controllers/ClienteController.php`.
- `app/Http/Controllers/PanelController.php`.
- `app/Services/Promesas/PromesaCreator.php`.
- `app/Http/Middleware/BlockClienteAccess.php`.
- `app/Support/Traits/HasTeamVisibility.php`.
- `resources/views/components/layout/sidebar.blade.php`.
- `resources/views/clientes/show.blade.php`.
- `resources/views/panel/resumen.blade.php`.
- `tests/Feature/V3LocalFlowTest.php`.

## Migracion de limpieza

Archivo:

```text
database/migrations/2026_05_15_000007_normalize_user_roles_v3.php
```

Acciones:

```sql
UPDATE users SET role = 'soporte' WHERE role IN ('sistemas', 'usuario');
ALTER TABLE users MODIFY role ENUM('administrador','supervisor','asesor','soporte') NOT NULL DEFAULT 'soporte';
```

## Estado de la base local

Consulta realizada:

```text
roles: administrador, supervisor, asesor, soporte
old_roles: 0
```

No habia usuarios locales con rol `sistemas` ni `usuario`.

## Cambios de comportamiento esperados

- `administrador` queda como rol maximo.
- `sistemas` deja de actuar como alias de administrador.
- `usuario` deja de existir como rol creable o gestionable.
- `soporte` permanece como rol operativo.
- Supervisores pueden crear/gestionar asesores y soporte segun reglas actuales.

## Riesgos

- [!] Si un dump productivo futuro contiene usuarios `sistemas`, pasaran a `soporte`.
- [!] Si habia procesos externos que dependian de `sistemas`, deben actualizarse antes de produccion.
- [!] Si algun usuario `sistemas` era administrador operativo, debe reasignarse manualmente a `administrador` antes del deploy.
- [!] La migracion de limpieza no debe ejecutarse en produccion sin revisar un dump reciente.

## Validaciones realizadas

- [+] `rg` para localizar referencias a `sistemas` y `usuario` como roles.
- [+] Codigo actualizado para usar solo roles finales.
- [+] Migracion probada en base limpia.
- [+] `php artisan route:list --except-vendor`.
- [+] `php artisan test`.
- [+] `npm run build`.
- [+] `php -l` en archivos PHP nuevos/modificados.

## Pendiente antes de produccion

- Revisar dump productivo reciente para contar usuarios con `sistemas` o `usuario`.
- Decidir si algun usuario `sistemas` debe pasar a `administrador` en vez de `soporte`.
- Validar permisos manualmente con cuentas de cada rol final.
- Confirmar accesos a reportes, integraciones, administracion, autorizacion, clientes y dashboard.
