# 17 - Validacion de Roles V3

## Objetivo

Validar que los roles finales de V3 funcionen con los accesos reales actuales del CRM.

Roles finales:

- `administrador`.
- `supervisor`.
- `asesor`.
- `soporte`.

Roles eliminados:

- `sistemas`.
- `usuario`.

## Usuarios usados

Se usaron usuarios locales creados por tests Feature dentro de transacciones de base de datos.

Resultado:

- [+] Los usuarios de prueba no quedan persistidos al terminar los tests.
- [+] No se usaron usuarios productivos reales.
- [+] No se recrearon roles eliminados como roles validos.

## Tabla de validacion

| Rol | Accesos esperados | Accesos validados | Pendiente | Observaciones |
| --- | --- | --- | --- | --- |
| administrador | Dashboard, administracion de usuarios, reportes, integraciones, autorizaciones, clientes, promesas, CNA. | Dashboard, administracion, reportes, integraciones, autorizacion, cliente, promesas, CNA, usuarios, exports e imports. | Validacion manual visual en navegador. | Rol maximo operativo. |
| supervisor | Dashboard, clientes, autorizaciones, reportes, integraciones y administracion limitada segun reglas actuales. | Dashboard, administracion, reportes, integraciones, autorizacion y cliente. Puede preaprobar/rechazar como supervisor. | Validar visualmente alcance de administracion por equipo. | No tiene flujo de aprobacion final de administrador. |
| asesor | Dashboard, busqueda/vista de clientes, crear promesa y CNA. Sin administracion, reportes, integraciones ni autorizacion. | Dashboard, cliente, creacion de promesas, creacion de CNA. Se valido 403 en administracion, reportes, integraciones y autorizacion. | Validar manualmente vistas legacy completas de cliente. | Es rol operativo de gestion. |
| soporte | Dashboard, administracion segun reglas actuales, reportes, integraciones y clientes. Sin autorizacion. | Dashboard, administracion, reportes, integraciones y cliente. Se valido 403 en autorizacion. | Confirmar con negocio si soporte debe mantener administracion e integraciones. | Soporte no aprueba Promesas/CNA. |

## Tests relacionados

Archivo:

```text
tests/Feature/V3RoleAccessTest.php
```

Cobertura:

- [+] Acceso a rutas principales por rol final.
- [+] Restricciones para `asesor`.
- [+] Restricciones para `soporte`.
- [+] Login bloqueado para usuario inactivo.
- [+] Rechazo de roles `sistemas` y `usuario` al crear usuarios.

Archivo:

```text
tests/Feature/V3WorkflowSmokeTest.php
```

Cobertura relacionada:

- [+] Acciones de administracion de usuarios.
- [+] Creacion y workflow de promesas.
- [+] Creacion y workflow de CNA.
- [+] Exports e imports desde rol administrador.

## Riesgos detectados

- [!] Supervisor y soporte tienen acceso a administracion de usuarios segun reglas actuales, pero con alcance limitado por servicio/policy.
- [!] Promesas y CNA todavia tienen parte de permisos dentro de controladores.
- [!] Cliente todavia mezcla permisos entre middleware, controlador y vista.

## Decision actual

Mantener los accesos actuales y cubrirlos con tests antes de mover reglas a Policies mas especificas.

No cambiar permisos de negocio sin confirmacion funcional.
