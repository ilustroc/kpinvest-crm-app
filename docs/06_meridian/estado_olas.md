# Estado de olas - Rediseno Meridian UI

- Rama: `feature/meridian-ui-v3`
- Ultima actualizacion: 2026-10-27
- Alcance: capa visual y de interaccion. No incluye cambios de logica de negocio.
- Repositorio de trabajo: `C:\GitHub\KP-CRM-Meridian-ui`

## Resumen de olas

| Ola | Alcance | Estado |
| --- | --- | --- |
| Ola 1 | Shell Meridian, Resumen operativo, Dashboard, notificaciones | Cerrada |
| Ola 2 | Ficha de cliente, cuentas, pagos, paneles CNA y promesas | Cerrada |
| Ola 3 | Login, navegacion, estados de carga y vacios | Cerrada |
| Ola 4 | Refinamiento de login y accesos | Cerrada |
| Ola 5 | Ajustes finales de acceso y consistencia visual | Cerrada |
| Ola 6 | Reportes de Pagos, Promesas y CNA; Autorizaciones | **Completada y aprobada definitivamente el 2026-10-23** |
| Ola 7 | Modulos operativos restantes | **7A y 7B finalizadas y aprobadas.** 7B aprobada el 2026-10-27. Las antiguas 7C a 7E se renombran como OLA 8 a OLA 10. Ver `ola7_auditoria.md` |
| OLA 8 | Administracion de usuarios | **Inventario preparado el 2026-10-27. No implementada; pendiente de aprobacion.** Ver `ola8_inventario_administracion.md` |
| OLA 9 | Integraciones y carga de archivos | Reservada, no iniciada |
| OLA 10 | Notificaciones y estados operativos | Reservada, no iniciada |

Las olas 1 a 5 se consideran cerradas por las aprobaciones y capturas previas. La Ola 6, la Ola 7A y la Ola 7B cuentan con aprobacion definitiva registrada en este documento. Los ajustes globales posteriores a 7A no reabren su alcance funcional. La Ola 7B quedo finalizada y aprobada el 2026-10-27. La OLA 8 solo tiene inventario; no se implemento ningun cambio y se detiene para aprobacion. Las OLA 9 y OLA 10 permanecen reservadas.

## Organizacion de capturas

Las capturas se movieron desde la raiz plana a subcarpetas por ola, sin sobrescribir ni eliminar evidencia:

```text
C:\KP-CRM-Meridian-ui-preview\screenshots\
├── OLA1/
├── OLA2/
├── OLA3/
├── OLA4/
├── OLA5/
├── OLA6/
├── OLA7A/
├── OLA7B/
├── OLA8/       (reservada, vacia)
├── OLA9/       (reservada, vacia)
├── OLA10/      (reservada, vacia)
├── MANIFEST.csv
└── README.md
```

Conteo verificado el 2026-10-28:

| Carpeta | Imagenes | Otros | Total |
| --- | ---: | ---: | ---: |
| OLA1 | 10 | 0 | 10 |
| OLA2 | 15 | 0 | 15 |
| OLA3 | 13 | 0 | 13 |
| OLA4 | 8 | 0 | 8 |
| OLA5 | 8 | 0 | 8 |
| OLA6 | 39 | 0 | 39 |
| OLA7A | 69 | 1 (`ola7a-audit-results.json`) | 70 |
| OLA7B | 62 | 0 | 62 |
| OLA8 | 0 | 0 | 0 |
| OLA9 | 0 | 0 | 0 |
| OLA10 | 0 | 0 | 0 |
| **Total** | **224** | **1** | **225** |

Total: **225 archivos**, **32,805,871 bytes**. `MANIFEST.csv` tiene **225 filas** y coincide con los archivos reales.

`MANIFEST.csv` registra la ruta relativa, carpeta, nombre, extension, tamano y fecha de cada archivo. `README.md` documenta la estructura y la convencion. Los scripts de captura se actualizaron para escribir en `OLA1` a `OLA7B`; `capture-paths.js` expone `waveDir()`, `waveFromName()` y `shotPath()` para las olas nuevas.

Convencion para las siguientes etapas:

```text
ola{N}-{momento}-{vista|modulo}-{contexto?}-{ancho}x{alto}[-full].png
```

- `{momento}`: `antes`, `despues`, `valid`, `fix`, `audit`, `final`, `debug` o `diag`.
- `{vista|modulo}`: `administracion-usuarios`, `integraciones`, `notificaciones`, etc.
- `{contexto?}`: `admin`, `supervisor`, `modal-editar-usuario`, etc.
- Estandar de viewport: `1440x900`, `1280x800`, `768x1024`, `390x844`.
- `-full` solo para capturas `fullPage`.

Ejemplos:

```text
OLA8/ola8-antes-administracion-usuarios-1440x900.png
OLA8/ola8-despues-administracion-usuarios-1440x900.png
OLA8/ola8-despues-administracion-usuarios-390x844.png
OLA8/ola8-modal-editar-usuario-1440x900.png
OLA9/ola9-antes-integraciones-1440x900.png
OLA10/ola10-despues-notificaciones-1440x900.png
```

## Ola 6 - Cierre aprobado

### Alcance aprobado visualmente

- Reporte de Pagos.
- Reporte de Promesas (PDP).
- Reporte de CNA.
- Modulo de Autorizaciones.
- Tablas, filtros, indicadores, exportaciones y modales asociados.

### Validacion de cierre

- `/reportes/cna` responde HTTP 200 despues de limpiar cache y reiniciar el contenedor preview.
- CNA muestra 2 registros, paginacion `1 / 1` y filtros operativos.
- Sin `ErrorException`, `Permission denied` ni trazas de Laravel en el HTML.
- Sin errores de consola, errores JavaScript, requests fallidos ni respuestas 4xx/5xx en la validacion.
- Sin scroll horizontal de pagina en 1440, 1280, 768 y 390 px.
- No se modificaron `app/`, `routes/`, `database/`, `config/` ni `resources/lang/`.

### Evidencia visual de referencia

Las capturas aprobadas viven fuera del repositorio, en el entorno preview:

`C:\KP-CRM-Meridian-ui-preview\screenshots\OLA6\`

Conjuntos aprobados:

- `OLA6/ola6-despues-pagos-*`
- `OLA6/ola6-despues-promesas-*`
- `OLA6/ola6-despues-cna-*`
- `OLA6/ola6-despues-autorizacion-*`

Referencia principal de CNA:

- `OLA6/ola6-despues-cna-1440x900.png`
- `OLA6/ola6-despues-cna-1440x900-full.png`
- `OLA6/ola6-despues-cna-1280x800.png`
- `OLA6/ola6-despues-cna-768x1024.png`
- `OLA6/ola6-despues-cna-390x844.png`

Las capturas `ola6-antes-pagos-*`, `ola6-antes-promesas-*` y `ola6-antes-autorizacion-*` se conservan como evidencia comparativa. Las capturas `ola6-antes-cna-*` se retiraron porque contenian unicamente la pagina de excepcion de Laravel y no representaban un diseno funcional anterior. No forman parte de la referencia aprobada.

## Incidente de permisos en el preview

### Sintoma

Al abrir `/reportes/cna` en el preview aparecia:

```text
file_put_contents(.../storage/framework/cache/data/45/59/...):
Failed to open stream: Permission denied
```

La traza apuntaba a `App\Services\CnaReportService::getFacets()` en la llamada a `Cache::remember`.

### Causa

- Apache ejecuta PHP como `www-data`.
- Algunos directorios y archivos de `storage/framework/cache` y `storage/framework/sessions` habian quedado con owner `root`.
- Esos archivos fueron generados por ejecuciones previas de comandos Artisan como root dentro del contenedor.
- `www-data` no podia crear ni sobrescribir la clave de cache de los facets de CNA.

No fue un problema de diseno ni de logica de negocio. Fue un problema de permisos del entorno preview.

### Correccion aplicada

Solo se intervinieron permisos y cache del contenedor preview:

```bash
docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
docker compose exec -T -u www-data app php artisan cache:clear
docker compose restart app
```

Verificacion posterior:

- Cero archivos con owner o grupo `root` en `storage/framework`, `storage/logs` y `bootstrap/cache`.
- Los nuevos archivos de cache quedaron como `www-data:www-data`.
- `/reportes/cna` volvio a responder 200 tras reiniciar el contenedor.

### Recomendacion operativa

Ejecutar los comandos Artisan que escriban en `storage` con el usuario `www-data`:

```bash
docker compose exec -u www-data app php artisan <comando>
```

Evitar ejecutar Artisan como root dentro del contenedor. Si algun comando requiere root, ejecutar despues:

```bash
docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
```

Esto aplica a `cache:clear`, `view:clear`, `config:clear`, `route:clear`, `queue:*`, comandos de pruebas y cualquier proceso que escriba en `storage/`.

## Pendiente funcional de Ola 6

La aprobacion visual no sustituye la validacion funcional de los workflows. Queda registrado como pendiente:

- Ejecucion controlada de los flujos de preaprobacion, aprobacion y rechazo de promesas.
- Ejecucion controlada de los flujos de preaprobacion, aprobacion y rechazo de CNA.
- Uso exclusivo de datos sinteticos.
- Aislamiento transaccional por escenario, con rollback al finalizar.
- Verificacion de transiciones de estado, permisos por rol, correos, notificaciones, realtime y documentos PDF/DOCX.
- Sin ejecucion sobre produccion ni sobre datos reales.

Este pendiente debe cerrarse antes de considerar los workflows listos para produccion.

## Ola 7A - Promesas, ficha de cliente y correcciones de auditoria (aprobada)

- Estado: implementada visualmente el 2026-10-23, auditada, corregida y aprobada. Los ajustes globales del 2026-10-26 preservan su modelo operativo y sus correcciones visuales.
- Alcance:
  - modal de alta, resumen de operaciones, condiciones de la promesa, cronograma editable, consulta de cronograma e historial de propuestas en la ficha;
  - lectura funcional de cuentas, operaciones, entidades, cosechas, asesores, pagos, promesas, CNA y documentos CCD;
  - eliminacion del panel lateral duplicado y de la tarjeta lateral de CNA;
  - contexto de la operacion seleccionada dentro de la tabla de cuentas (retirado en 7B.3);
  - limpieza de la franja de metricas redundantes en los reportes de Pagos, Promesas y CNA;
  - correccion de overflow, recortes y distribucion en ficha, tablas, modales y reportes.
- Sin cambios en controladores, servicios, modelos, policies, rutas, migraciones, base de datos, workflows, permisos ni calculos financieros.
- Archivos intervenidos: `components/promesas/*`, `js/modules/promesas/*`, `clientes/show`, `components/clientes/*`, `components/cna/debt-selector`, `js/modules/cna/form.js`, `reportes/*`, `js/modules/reportes/filters.js`, `resources/css/app.css` y componentes base de tabla.
- Auditoria: consultas agregadas de solo lectura sobre la base enmascarada; sin exponer datos personales. Hallazgos y decisiones en `ola7_auditoria.md`.
- Capturas: `OLA7A/ola7a-antes-*` como referencia previa y `OLA7A/ola7a-audit-*` como evidencia de la correccion, en `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7A\`.
- Verificacion: build Vite, `view:cache`, Playwright 38/38 en 1440x900, 1280x800, 768x1024 y 390x844, sin overflow de pagina, consola limpia y exportaciones 200; revision global de 11 rutas por 4 anchos sin overflow; prueba unitaria Laravel 1/1; `git diff --check` limpio.
- Aprobacion: Ola 7A aprobada. La Ola 7B se finalizo y aprobo el 2026-10-27; las antiguas sub-olas 7C a 7E se renombran como OLA 8 a OLA 10 y siguen detenidas hasta recibir autorizacion explicita.

## Ajustes globales posteriores a Ola 7A (2026-10-26)

Alcance autorizado: consolidar la identidad visual de KP Invest CRM Meridian, actualizar el correo de soporte del login y homogeneizar la presentacion monetaria sin alterar layouts, comportamiento backend ni logica financiera. No se avanzo a Ola 7B.

### Identidad visual

- Primario verde `#00A81C`, verde oscuro `#008517`, verde tinta `#006F13`, verde suave `#EAF8ED`.
- Secundario azul `#1769E0`, azul oscuro `#0B4FC4`; corporativo navy `#062B60`.
- Fondo `#F5F7FA`, superficie blanca `#FFFFFF`, borde `#E4E7EC`, texto `#111827`.
- El verde se aplica a marca, navegacion activa, acciones primarias, filtros seleccionados, checkboxes, foco, controles de formulario, tabs e indicadores principales.
- El azul se mantiene como secundario para enlaces, informacion complementaria y series de graficos. Se preservan los estados semanticos rojo, ambar, verde y azul.
- Los botones con texto blanco y los estados de foco usan `#008517`. El texto e iconos pequenos sobre fondos verdes suaves o claros usan `#006F13` para cumplir WCAG AA.
- El logo original de KP Invest no fue modificado.
- Los cambios se aplicaron mediante tokens y componentes compartidos en `resources/css/app.css`, `components/ui/*`, `components/layout/*`, `components/tables/*` y las vistas de login, dashboards, ficha de cliente, reportes, autorizaciones, promesas y CNA.

### Correo de soporte

- Unico cambio: el enlace de soporte del login paso de `impulse.conciliacion-cobranza@mgi-go.com` a `jesus.garcia@kpinvest.pe`, conservando `mailto:`.
- Archivo: `resources/views/auth/login.blade.php`.
- No se modificaron destinatarios de notificaciones, correos operativos, SMTP ni flujos de negocio.

### Formato monetario

- Formato de presentacion: separador de miles con coma, decimales con punto, siempre dos decimales, negativos preservados y simbolo de moneda cuando corresponde. Para PEN se muestra `S/`.
- Helpers de solo presentacion: `app/Support/Money.php` y `resources/js/core/money.js`.
- Se respeta la moneda real de cada operacion. No se convierte automaticamente todo a soles; las monedas compuestas o no reconocidas se muestran sin simbolo.
- Se agrego `moneda` al `select` de `ClienteAccountService::accountsForDni()` unicamente para renderizar la moneda por operacion. No se modificaron calculos, deuda, promesas, distribucion de cuotas, redondeos, validaciones, payloads, parametros, contratos API, importaciones CSV ni valores numericos XLSX.
- Los inputs monetarios editables conservan su valor numerico sin separadores de miles. No se formateo el valor enviado al servidor.
- No se aplican separadores monetarios a DNI, cuentas, operaciones, cosechas, telefonos, IDs, fechas ni enteros no monetarios.

### Validacion ejecutada

- `npm run build`: correcto.
- `php artisan view:cache`: correcto.
- Suite Laravel completa sobre base descartable independiente: **28 passed, 4 skipped, 2 deprecated (298 assertions)**. Los 4 skipped corresponden a smoke tests que requieren usuario/cliente local sintetico.
- Capturas Playwright `OLA7A/ola7a-global-despues-*`: login, resumen, dashboard, ficha de cliente, tres reportes, autorizaciones, detalle, decision, modal de promesa, cronograma, modal CNA de cliente y modal CNA de autorizacion.
- Revision global de 11 rutas por 4 anchos: **44/44 sin overflow de pagina**, sin errores de consola, errores JavaScript, requests fallidos ni respuestas 4xx/5xx.
- Contraste WCAG AA: **0 fallos** en login, resumen, dashboard, ficha, reportes y autorizaciones. Se corrigio el ultimo caso de `Analitica de cobranza` usando verde tinta.
- Filtros y exportaciones: Pagos 14 -> 10 filas, Promesas 7 -> 4, CNA 3 -> 3; las tres exportaciones respondieron HTTP 200 con firma XLSX `PK`.
- XLSX: los montos permanecen numericos y sin formato de moneda. Pagos `Monto` 450/280/175; Promesas `Deuda_Act`, `Capital_Act` y `Pago_Ini` numericos; CNA `Cna_Imp` 450/280/850. No se encontraron cadenas monetarias en celdas de datos.
- `git diff --check`: limpio.
- Capturas comparativas: `OLA7A/ola7a-global-antes-*` y `OLA7A/ola7a-global-despues-*` en `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7A\`. Las referencias `antes` de login y dashboard son las ultimas capturas previas disponibles de las olas 5 y 3; el resto usa `OLA7A/ola7a-despues-*` y `OLA6/ola6-despues-*`.

### Pendiente y limites

- Ola 7B se implemento y aprobo despues de estos ajustes; las antiguas olas 7C a 7E se renombran como OLA 8 a OLA 10 y siguen detenidas.
- El preview actual solo contiene operaciones PEN. La representacion no-PEN se valido a nivel de helper para USD, EUR y monedas compuestas; falta una captura de vista con datos no-PEN reales.
- No se hicieron commits, push ni despliegues. Solo se inicio el preview local `8091` con autorizacion expresa; no se tocaron 8088, 8090, staging ni produccion.

## Ola 7B - Solicitudes CNA y documentos (finalizada y aprobada)

- Estado: finalizada y aprobada el 2026-10-27. Cierre registrado con las correcciones finales de ficha de cliente, Autorizaciones y tablas de supervisor/administrador.
- Alcance:
  - modal de solicitud CNA con cabecera de cliente, cuenta, entidad, cosecha/lote, origen, serie, plantilla, pagos, operaciones incluidas, monto en vivo, restauracion de `old()` y resumen de footer;
  - columna CNA de la ficha de cliente con estado real, numero/conteo, documento disponible/pendiente/no encontrado/sin archivo y accion `Solicitar nueva` tras rechazo;
  - tabla CNA de autorizacion con datos de la solicitud; modal con flujo, panel documental, notas, contexto de entidad/cosecha, enlace a ficha y pagos marcados como CNA;
  - componente reutilizable `components/cna/document-state` en modos `badge`, `compact` y `panel`;
  - foco, trampa de Tab, Escape, restauracion de overflow/foco y `data-modal-auto-open` en `core/modal.js`.
- Sin cambios en controladores, servicios, modelos, policies, migraciones, rutas, payloads, validaciones, calculos, permisos ni transiciones. La inclusion de operaciones de la misma cuenta se conserva. El unico cambio de servicio en el arbol de trabajo es la exposicion de `moneda` de 7A.2.
- Estados cubiertos: disponible PDF+DOCX, DOCX sin PDF, pendiente de generacion, aprobada sin archivo, archivo no encontrado, pendiente de aprobacion, aprobada y rechazada. Descargas 200/403/404 segun estado; DOCX `PK`, PDF `%PDF`; fallback PDF->DOCX preservado.
- Verificacion: `npm run build`, `view:cache`, Playwright `validate-ola7b.js` 29/29 en 1440/1280/768/390, suite Laravel 28 passed / 4 skipped / 2 warnings (298 assertions), `V3CnaModuleTest` 2/2, smoke post-limpieza con 7 rutas 200 y sin errores de consola, `git diff --check` limpio.
- Capturas: `OLA7B/ola7b-antes-*`, `OLA7B/ola7b-despues-*`, `OLA7B/ola7b-valid-*`, `OLA7B/ola7b-fix-*`, `OLA7B/ola7b-valid-cliente-docx-only-1440x900.png` y `OLA7B/ola7b-smoke-cleanup-1440x900.png` en `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7B\`.
- Limpieza: usuarios sinteticos `910001`/`910002`, CNA `910001` a `910006`, cliente `00000101` con sus 4 operaciones y 3 pagos, archivos CNA/template y base `kp_crm_ui_test` eliminados. El preview conserva 3 CNA y 2 usuarios demo.
- Limites: la tabla de autorizacion ya no muestra la columna Estado; el estado completo y el panel documental CNA estan en el modal de detalle. La validacion visual no-PEN sigue limitada por los datos del preview.

### Revision final de Autorizaciones

Fecha: 2026-10-27.

- Bandejas por rol: administrador mostro 4 promesas y 3 CNA, todas en `preaprobada`; supervisor mostro 2 promesas y 1 CNA, todas en `pendiente`. Los estados se verificaron con `data-estado` en cada fila y con los conteos de la base.
- Acciones por rol: administrador dispone de `Aprobar` y `Rechazar`; supervisor dispone de `Preaprobar` y `Rechazar`. No se renderizan acciones cruzadas.
- Refuerzo backend: las acciones de promesa y CNA llaman `Gate::forUser(...)->authorize(...)`; `PromesaPolicy` y `CnaPolicy` reservan `preapprove`/`reject-as-supervisor` al supervisor y `approve`/`reject-as-administrator` al administrador. El middleware de ruta admite ambos roles, pero la policy impide la ejecucion cruzada. No se encontro contradiccion de permisos.
- Modales: los modales de promesa y CNA abren correctamente; `#t_estado` y `#cna_estado` conservan el estado completo (`preaprobada`/`pendiente`) y el modal CNA mantiene el panel documental.
- Sin columna Estado: no quedan badges de estado en las tablas. Las bandejas solo muestran el estado propio de cada rol; los demas estados no forman parte de estas bandejas y siguen disponibles en reportes e historial. No se oculta informacion necesaria.
- Sticky y recorte: a 1440 px las acciones quedan visibles y la ultima celda de datos no se solapa con la columna sticky; a 390 px no hay overflow de pagina, las tablas tienen scroll horizontal y las acciones son accesibles tras desplazar.
- Errores: sin errores de consola, `pageerror`, requests fallidos ni respuestas HTTP >= 400.
- Resultado: 80 comprobaciones Playwright, 0 fallos. Evidencia de soporte: `C:\KP-CRM-Meridian-ui-preview\ola7b-final-validation.json` (fuera de las carpetas de capturas). El supervisor y la CNA temporal usados para la prueba se eliminaron y `asesor.supervisor_id` se restauro a `NULL`.

- OLA 8 a OLA 10 permanecen detenidas hasta autorizacion explicita; OLA 8 solo cuenta con inventario.

### Correccion puntual 1 - Bloque Contexto de la operacion (2026-10-28)

- Objetivo: eliminar el bloque visual `CONTEXTO DE LA OPERACION` de la ficha de cliente y dejar la seccion `Cuentas y operaciones` con el encabezado, el boton `Generar propuesta` y la tabla.
- Hallazgo: la vista `clientes/show.blade.php` ya no incluia el bloque en el DOM del preview; quedaba el componente huerfano `resources/views/components/clientes/operation-context.blade.php`, sin referencias en vistas.
- Cambio: se elimino el componente huerfano. No se modificaron la tabla, columnas, checkboxes, botones, controladores, servicios, modelos, rutas, permisos, migraciones ni bases de datos. El hook JS `setupOperationContext()` se conserva con su guarda y queda inerte al no existir el panel; no se toco para no alterar la seleccion.
- Cache: `php artisan view:clear`, `php artisan view:cache` y `php artisan optimize:clear` ejecutados como `www-data`; `storage/framework/views` sin coincidencias de `data-operation-context` ni del texto del bloque.
- Validacion: `npm run build` correcto; 21 comprobaciones Playwright, 0 fallos. Se verifico ausencia del bloque, seleccion individual, seleccion total, contador y habilitacion de `Generar propuesta`, apertura del modal de propuesta, apertura y cierre del modal CNA, navegacion interna `Ver operaciones` -> `#cuentas`, responsive 1440x900 y 390x844, scroll horizontal interno de la tabla, sin overflow de pagina y sin errores de consola, `pageerror`, requests fallidos ni respuestas >= 400.
- Capturas: `OLA7B/ola7b-fix-contexto-cliente-1440x900.png` y `OLA7B/ola7b-fix-contexto-cliente-390x844.png`.
- Evidencia de soporte: `C:\KP-CRM-Meridian-ui-preview\ola7b-contexto-cliente-validation.json` y `C:\KP-CRM-Meridian-ui-preview\ola7b-contexto-cliente-nav-validation.json`.
- `MANIFEST.csv` actualizado a 219 filas; total 218 PNG + 1 JSON, 31,952,120 bytes.

### Correccion puntual 2 - Asesores en encabezado (2026-10-28)

- Objetivo: retirar del encabezado general de la ficha cualquier asesor, resumen de asesores activos o asignacion global del DNI; conservar la columna `Asesor` por operacion.
- Auditoria: el indicador se generaba solo en `resources/views/components/clientes/header.blade.php`, a partir de `$cuentas->pluck('asesor')` y un `match` que producia textos como `Asesor: ...`, `N asesores activos`, `Sin asesor asignado` o `CANCELADOS`. Era presentacion pura; no intervenia en seleccion, promesas, CNA ni persistencia.
- Cambio: se eliminaron del header el calculo de asignaciones y el bloque visual del asesor. Se conservaron DNI, cuentas, operaciones, pagos y las tarjetas de deuda total, deuda capital, pagos y promesas. No se toco la tabla, sus `data-asesor`, la logica de seleccion ni el backend.
- Validacion: 29 comprobaciones Playwright, 0 fallos, sobre el DNI `00000100` con 4 operaciones y asignaciones distintas `Asesor Uno`, `Asesor Dos`, `CANCELADOS`, `Asesor Uno`. Encabezado sin texto, nombres ni `data-asesor`; tabla con columna `Asesor` y las cuatro asignaciones; fila `CANCELADOS` conserva `S/ 5,600.00` y la deuda total del encabezado sigue en `S/ 45,000.00`; seleccion individual y multiple, `Generar propuesta`, modal CNA, 1440x900 y 390x844 sin overflow de pagina, tabla con scroll horizontal interno y sin errores de consola/red.
- Capturas: `OLA7B/ola7b-fix-asesor-cliente-1440x900.png` y `OLA7B/ola7b-fix-asesor-cliente-390x844.png`.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-asesor-cliente-validation.json`.
- `MANIFEST.csv` actualizado a 221 filas; total 220 PNG + 1 JSON, 32,189,753 bytes; OLA7B con 58 PNG.

### Correccion puntual 3 - Descargas CNA compactas (2026-10-28)

- Objetivo: dejar la columna CNA de `Cuentas y operaciones` con una sola accion de descarga por solicitud: PDF si existe; DOCX solo si no hay PDF; estado neutro sin enlace cuando no hay archivo.
- Auditoria: `clientes/show.blade.php` renderiza la CNA mas reciente por operacion (`sortByDesc('created_at')`) e indica `xN` cuando hay varias; la celda usa `x-cna.document-state` en modo `compact`. El componente determina disponibilidad con `pdf_path`/`docx_path` y `Storage::exists()`. Las rutas `cna.pdf` y `cna.docx` delegan en `CnaDocumentService`, que solo permite `workflow_estado = aprobada` y conserva el fallback interno al formato secundario. No fue necesario modificar backend, rutas, modelos, servicios ni migraciones.
- Cambio: solo la rama `compact` de `resources/views/components/cna/document-state.blade.php`. PDF disponible muestra un unico icono rojo; sin PDF y con DOCX muestra un unico icono azul; sin archivo muestra un icono neutro sin enlace. Cada enlace tiene 32x32 px, SVG, `aria-label`, `title`, foco visible y navegacion por teclado. La rama `panel` de Autorizacion no se modifico. Pendiente/preaprobada conserva `Al aprobar`; rechazada conserva `Solicitar nueva` y no se vuelve descargable por tener rutas de archivo.
- Validacion: `npm run build` y `view:cache` correctos; Playwright **46/46 comprobaciones, 0 fallos** con DNI sintetico `00000999` y 7 operaciones. Se cubrieron PDF+DOCX, solo DOCX, sin archivo, pendiente, preaprobada, rechazada, operacion con dos CNA y operaciones distintas. Las descargas reales entregaron `%PDF` para PDF y DOCX valido (`[Content_Types].xml` y `word/document.xml`) para Word; pendiente/preaprobada/rechazada devolvieron **403** aun con archivos existentes; aprobada sin archivo devolvio **404**.
- Ambiguedad documentada: la tabla conserva el comportamiento vigente de mostrar solo la CNA mas reciente por operacion y el badge `xN`; no existe selector de CNA historica, por lo que el icono descarga la CNA mas reciente. Ademas, la UI comprueba `pdf_path`/`docx_path`, mientras el servicio resuelve la descarga por la convencion `CNA {nro_carta} - {dni}`; los registros generados por el sistema cumplen esa convencion y los datos sinteticos se nombraron igual. No se cambio el servicio por la restriccion de alcance.
- Capturas: `OLA7B/ola7b-fix-descarga-cna-1440x900.png`, `OLA7B/ola7b-fix-descarga-cna-390x844.png`, `OLA7B/ola7b-fix-descarga-cna-pdf.png` y `OLA7B/ola7b-fix-descarga-cna-word.png`.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-cna-download-validation.json`, `C:\KP-CRM-Meridian-ui-preview\validate-ola7b-cna-downloads.js` y `C:\KP-CRM-Meridian-ui-preview\downloads-ola7b-cna\`.
- `MANIFEST.csv` actualizado a 225 filas; total 224 PNG + 1 JSON, 32,805,871 bytes; OLA7B con 62 PNG. Los datos sinteticos `00000999`, CNA `990001` a `990008`, asignaciones y archivos se eliminaron al terminar.

## OLA 8 - Administracion de usuarios (inventario)

- Estado: inventario preparado el 2026-10-27. **No implementada.**
- Documento completo: `docs/06_meridian/ola8_inventario_administracion.md`.
- Alcance inventariado: listado, roles/permisos, supervisores/asesores, alta, edicion (ausente), activacion/desactivacion, cambio de password, filtros, paginacion, confirmaciones y componentes reutilizables.
- Hallazgos principales:
  - el listado actual usa `x-ui.card`/`x-ui.table`, busqueda por nombre/email, filtro de inactivos y paginacion de 15;
  - no existe formulario, ruta ni metodo de edicion de usuario;
  - no existe editor de permisos: los permisos son fijos por rol;
  - no hay CRUD dedicado de supervisores; sin un supervisor activo, el alta de asesor/soporte queda bloqueada por el select requerido;
  - la desactivacion usa `confirm()` nativo aunque existe `x-ui.confirm-dialog`;
  - el alta no pide confirmacion de password; el cambio de password si.
- No se modificaron controladores, servicios, modelos, migraciones, rutas ni permisos. No se crearon ni modificaron usuarios durante la auditoria de OLA 8.
- Se requiere aprobacion de alcance antes de implementar; cualquier edicion o matriz de permisos necesita autorizacion backend explicita.

## Restricciones vigentes

- No modificar los modulos ya aprobados de las olas 1 a 6.
- No hacer commits, push ni despliegues sin autorizacion explicita.
- No modificar los entornos 8088, 8090 ni produccion.
- Mantener el trabajo en `C:\GitHub\KP-CRM-Meridian-ui`, rama `feature/meridian-ui-v3`.
