# Validacion de roles

## Roles finales

| Rol | Accesos esperados | Estado |
| --- | --- | --- |
| administrador | Dashboard, Admin, Reportes, Integraciones, Autorizacion, Clientes, Promesas, CNA | Validado por tests |
| supervisor | Dashboard, Clientes, Autorizacion, Reportes segun regla, equipo asignado | Validado por tests |
| asesor | Dashboard, busqueda/vista cliente, creacion operativa segun regla | Validado por tests |
| soporte | Accesos operativos permitidos, sin aprobacion workflow | Validado por tests |

## Roles eliminados

- `sistemas`
- `usuario`

No son roles validos en codigo, selects, requests, policies ni gates. Pueden aparecer solo como referencia historica o prueba negativa.

## Tests relacionados

- `tests/Feature/V3RoleAccessTest.php`
- `tests/Feature/V3WorkflowSmokeTest.php`
- `tests/Feature/V3ClienteModuleTest.php`
- `tests/Feature/V3PromesaModuleTest.php`
- `tests/Feature/V3CnaModuleTest.php`
- `tests/Feature/V3AutorizacionModuleTest.php`

## Pendiente

Validar en navegador real con usuarios locales de cada rol antes de Fase 9.
