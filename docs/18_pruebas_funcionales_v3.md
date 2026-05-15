# 18 - Pruebas Funcionales V3

## Objetivo

Documentar las pruebas funcionales reales ejecutadas en Fase 6 sobre localhost y base local.

Produccion no se toca.

## Rollback de migraciones

Base desechable:

```text
kpinvest_v3_migrate_test
```

Comandos:

```bash
php artisan migrate:rollback
php artisan migrate
php artisan migrate:status
```

Resultado:

- [+] Rollback ejecutado correctamente.
- [+] Migraciones volvieron a subir correctamente.
- [+] `migrate:status` quedo con todas las migraciones en `Ran`.

## Tests creados

```text
tests/Feature/V3RoleAccessTest.php
tests/Feature/V3WorkflowSmokeTest.php
```

## Pruebas de usuarios

- [+] Login de invitado redirige a login.
- [+] Login de usuario inactivo queda bloqueado.
- [+] Crear usuario.
- [+] Activar/desactivar usuario.
- [+] Cambiar contrasena.
- [+] Rechazar roles eliminados `sistemas` y `usuario`.

## Pruebas de roles

- [+] Administrador: dashboard, administracion, reportes, integraciones, autorizacion y cliente.
- [+] Supervisor: dashboard, administracion limitada, reportes, integraciones, autorizacion y cliente.
- [+] Asesor: dashboard y cliente; sin administracion, reportes, integraciones ni autorizacion.
- [+] Soporte: dashboard, administracion, reportes, integraciones y cliente; sin autorizacion.

## Pruebas de promesas

- [+] Crear promesa de cancelacion.
- [+] Crear promesa de convenio.
- [+] Crear promesa con cuota balon.
- [+] Preaprobar como supervisor.
- [+] Rechazar como supervisor.
- [+] Aprobar como administrador.
- [+] Rechazar como administrador.
- [+] Generar acuerdo de promesa usando plantilla local temporal.
- [+] Validar fallback a DOCX cuando iLovePDF no tiene claves.

Observacion:

- [!] El flujo de cuota balon genera cuota marcada como balon. El modelo `PromesaPago` normaliza el campo `tipo` a valores legacy (`convenio`/`cancelacion`), por lo que conviene revisar esto en una fase posterior antes de usar `tipo=convenio_balon` como estado persistente.

## Pruebas de CNA

- [+] Crear CNA.
- [+] Preaprobar como supervisor.
- [+] Rechazar como supervisor.
- [+] Aprobar como administrador.
- [+] Rechazar como administrador.
- [+] Descargar DOCX.
- [+] Validar ruta PDF con fallback a DOCX cuando no hay PDF generado.

Observacion:

- [!] En local no hay claves iLovePDF configuradas. El fallback DOCX funciona y queda registrado en logs.

## Pruebas de reportes

- [+] Reporte de pagos renderiza.
- [+] Reporte de promesas renderiza.
- [+] Reporte CNA renderiza.
- [+] Export reporte de pagos.
- [+] Export reporte de promesas.
- [+] Export reporte CNA.
- [+] Facets/filtros AJAX de pagos.
- [+] Paginacion parcial AJAX de pagos, promesas y CNA.

## Pruebas de importaciones

Se usaron CSV pequenos generados en tests, con datos transaccionales.

- [+] Importar pagos CSV.
- [+] Importar data maestra CSV.
- [+] Importar asignaciones CSV.
- [+] Importar CCD CSV.

## Frontend y navegador

Validado por tests HTTP:

- [+] Vistas principales renderizan.
- [+] Rutas AJAX de reportes responden.
- [+] Vistas migradas cargan por Vite segun checklist previo.

Pendiente manual:

- [!] Consola del navegador sin errores.
- [!] Modales en navegador real.
- [!] Interaccion visual de filtros y paginacion.
- [!] Revision desktop.
- [!] Revision responsive/mobile.
- [!] Vistas legacy con Bootstrap condicional.

## Comandos de verificacion

Ejecutados al cierre de Fase 6:

```bash
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test
npm run build
git diff --check
```

Tambien:

```bash
php -l tests/Feature/V3RoleAccessTest.php
php -l tests/Feature/V3WorkflowSmokeTest.php
```

Resultados:

- [+] `php artisan route:list --except-vendor`: 53 rutas listadas.
- [+] `php artisan migrate:status`: migracion Sanctum `Ran`; migraciones V3 `Pending` en base local principal, esperado porque el baseline no debe ejecutarse sobre tablas existentes.
- [+] `php artisan test`: 12 tests pasaron, 141 assertions.
- [+] `npm run build`: build correcto.
- [+] `git diff --check`: sin errores.
- [+] `php -l`: sin errores de sintaxis en tests nuevos.

## Riesgos restantes

- [!] No reemplaza una prueba manual de negocio con usuarios reales de prueba.
- [!] No valida archivos CSV grandes de produccion.
- [!] No valida conversion PDF real con iLovePDF porque no hay claves locales.
- [!] No valida responsive/mobile con navegador real.

## Decision

La Fase 6 queda con cobertura automatizada inicial suficiente para continuar, pero antes de deploy se debe ejecutar una ronda manual con navegador y un dump productivo reciente.
