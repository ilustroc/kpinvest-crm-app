# Ola 7 - Auditoria de vistas y plan de implementacion

- Estado: **7A aprobada; 7B finalizada y aprobada el 2026-10-27. OLA 8 a OLA 10 detenidas.**
- Fecha: 2026-10-27
- Rama: `feature/meridian-ui-v3`
- Objetivo: completar la migracion visual Meridian de los modulos operativos restantes sin cambiar logica de negocio.
- Aprobacion de 7A registrada. 7B fue autorizada, implementada, validada y aprobada el 2026-10-27. Las antiguas sub-olas 7C a 7E se renombran como OLA 8 a OLA 10 y permanecen detenidas; OLA 8 solo tiene inventario.

## 1. Alcance propuesto

Modulos incluidos:

1. Promesas de pago y cronogramas.
2. Solicitudes CNA y documentos.
3. Administracion de usuarios.
4. Integraciones y carga de archivos.
5. Notificaciones y estados operativos.

Fuera de alcance de Ola 7:

- Controladores, servicios, acciones, modelos, migraciones, rutas y reglas de negocio.
- Persistencia, calculos, permisos, validaciones backend y contratos de datos.
- Plantillas de correo, PDF o DOCX, salvo que se apruebe una tarea separada.
- Entornos 8088, 8090 y produccion.
- Reapertura de las pantallas aprobadas en Olas 1 a 6.

## 2. Estado actual de la capa Meridian

### 2.1 Ya heredan Meridian y no deben reabrirse

| Area | Evidencia |
| --- | --- |
| Layout global, sidebar, topbar, breadcrumb | `layouts/app`, `components/layout/*` |
| Campanita de notificaciones y menu de usuario | `components/layout/notifications-bell`, `topbar`, `user-menu.js` |
| Resumen operativo y Dashboard | `panel/resumen`, `dashboard/index` |
| Ficha de cliente, cabecera, cuentas, pagos | `clientes/show`, `components/clientes/*` |
| Reportes de Pagos, Promesas y CNA | `reportes/*` |
| Autorizaciones y sus modales | `autorizacion/index`, `components/autorizacion/*` |
| Login | `auth/login` |
| Componentes base | `components/ui/*`, `components/tables/*`, `components/layout/*` |

Estas vistas se usan como referencia visual y no se modifican en Ola 7. Cualquier cambio global en `x-ui.modal`, `x-ui.button` o `app.css` que las afecte requiere revalidacion y reaprobacion de Ola 6.

### 2.2 Heredan Meridian parcialmente

| Area | Vista o componente | Que ya hereda | Brecha principal |
| --- | --- | --- | --- |
| Promesas | `components/promesas/create-modal` | `x-ui.modal`, botones Meridian | Cuerpo del formulario con controles legacy y jerarquia visual antigua |
| Promesas | `components/promesas/promise-type-fields` | Contenedor y tabla Meridian | Usa `x-forms.*`, input date nativo, sin errores inline ni estados de validacion consistentes |
| Promesas | `components/promesas/promise-summary` | Ninguno relevante | Bloque gris simple, sin resumen de operaciones ni metricas |
| Promesas | `components/promesas/payment-schedule` | `x-ui.modal`, `x-tables.table` | `tbody` vacio sin estado inicial, sin resumen de cuotas, sin total visible ni ajuste movil |
| CNA | `components/cna/create-modal` | `x-ui.modal`, botones Meridian | Formulario con `x-forms.*`, sin contexto documental ni validacion visual |
| CNA | `components/cna/debt-selector` | Ninguno relevante | Caja informativa plana, sin resumen de cuentas/operaciones ni advertencias |
| Admin | `placeholders/administracion` | `x-ui.card`, `x-ui.table`, `x-ui.input`, `x-ui.modal` | Sin `page-shell`/`page-header`, sin metricas, tabla con `th` nativos, paginacion Laravel directa, `confirm()` nativo |
| Integraciones | `placeholders/integracion-data` | `page-shell`, `page-header`, `x-ui.card` | Formulario de archivo unico, sin dropzone, sin precheck ni resumen de resultado |
| Integraciones | `placeholders/integracion-asignacion` | Igual que Data | Duplicacion de estructura y validacion limitada |
| Integraciones | `placeholders/integracion-ccd` | Igual que Data | Duplicacion de estructura y validacion limitada |
| Integraciones | `placeholders/integracion-pagos` | Shell, cards, tablas, precheck | Input de archivo legacy, sin dropzone, sin historial de lotes, tabla sin contenedor Meridian |
| Notificaciones | `components/layout/notifications-bell` | Meridian completo | Solo faltan estados de error/reintento y consistencia con el resto |
| Estados | `components/clientes/status-badge` | Badge Meridian | Mapeo por coincidencia de texto; falta tabla canonica de etiquetas y tonos |

### 2.3 Componentes legacy que siguen en uso

- `components/forms/*`: `field`, `input`, `select`, `date`, `textarea`, `label`, `error`.
- `components/feedback/*`: `alert`, `empty-state`, `confirm-dialog`, `loading`.
- Tablas con `<table>`, `<th>` o `<td>` nativos en Admin.
- `confirm()` nativo en acciones de Admin.
- Paginacion Laravel directa con `links()` en Admin.

Estos componentes no se eliminan en Ola 7. Se migran por pantalla y se dejan sin uso al final, con una busqueda global que lo confirme.

### 2.4 Inventario por modulo

#### Promesas de pago y cronogramas

- La ficha de cliente, el historial de promesas y la tabla de pagos ya son Meridian y estan aprobados.
- El flujo de creacion sigue concentrado en modales dentro de `clientes/show`:
  - seleccion de operaciones;
  - tipo de propuesta;
  - generacion y edicion de cronograma;
  - convenio con cuota balon o cancelacion;
  - resumen y confirmacion.
- `promises/form.js` y `promises/schedule.js` concentran el comportamiento y no requieren cambios de negocio.
- Brechas visuales: formulario denso, campos legacy, falta de resumen claro, totales y errores poco visibles, cronograma sin estado vacio ni adaptacion movil cuidada.

#### Solicitudes CNA y documentos

- La CNA se gestiona desde la columna de cada operacion; la tarjeta lateral duplicada se retiro en 7A.1.
- El modal de solicitud y el selector de deuda se actualizaron en 7A.1 para mostrar cuenta de deuda, entidad, cosecha/lote, origen/plantilla y operaciones incluidas.
- Los documentos CCD se muestran en una tarjeta independiente, asociados por DNI y cosecha, no por operacion.
- Los documentos CNA se resuelven con enlaces a PDF/DOCX ya existentes. No hay visor, carga ni historial documental dentro de la ficha.
- Brechas restantes: estados de exito/error y acciones documentales mas claras, si el producto lo autoriza.

#### Administracion de usuarios

- La pagina es funcional y ya usa varios componentes Meridian, pero conserva estructura de placeholder.
- Brechas: no tiene cabecera de pagina ni metricas; el filtro de inactivos esta dentro de una card; la tabla usa `th` nativos y no usa el patron de tabla contenido; la paginacion no usa `x-tables.pagination`; las confirmaciones usan `confirm()`; el estado vacio es un texto simple.
- `admin/users.js` funciona y puede conservarse casi completo.

#### Integraciones y carga de archivos

- Existen cuatro pantallas: Data, Asignacion, CCD y Pagos.
- Data, Asignacion y CCD son casi identicas y solo muestran un input de archivo y un boton.
- Pagos tiene un precheck de encabezados y tipos, tabla de incidencias y ultimo lote, pero con estilos parciales.
- No hay dropzone comun, resumen de resultado homogeneo, historial de importaciones ni estados de progreso/error consistentes.
- `integracion/imports.js` solo valida Pagos; el resto solo deshabilita el boton al enviar.
- Los servicios de importacion no se tocan.

#### Notificaciones y estados operativos

- La campanita, el panel desplegable, el contador y el realtime ya son Meridian.
- `NotificationController@index` devuelve JSON; no existe una pagina dedicada de notificaciones.
- Los estados de workflow se muestran con `x-clientes.status-badge`, que interpreta el texto del estado.
- Brechas: falta definir si Ola 7 incluye una pagina completa de notificaciones; falta una tabla canonica de estados y tonos; faltan estados de error/reintento en la campanita.

## 3. Orden de implementacion propuesto

El orden prioriza uso diario, reutilizacion de patrones y riesgo tecnico.

### 7A - Promesas de pago y cronogramas

Estado de ejecucion: **implementada, auditada, corregida y aprobada**. No se avanzo a 7B.

Complejidad: media. Prioridad: alta.

Objetivo: redisenar el flujo de propuesta y cronograma sobre la ficha de cliente ya aprobada.

Archivos previstos:

- `resources/views/components/promesas/create-modal.blade.php`
- `resources/views/components/promesas/promise-type-fields.blade.php`
- `resources/views/components/promesas/promise-summary.blade.php`
- `resources/views/components/promesas/payment-schedule.blade.php`
- `resources/js/modules/promesas/form.js`
- `resources/js/modules/promesas/schedule.js`

Criterios de aceptacion:

- Convenio, convenio con cuota balon y cancelacion se ven y validan con claridad.
- El cronograma muestra estado inicial, total, diferencia contra el monto convenio y errores visibles.
- La seleccion de operaciones se resume antes de guardar.
- Funciona en 1440, 1280, 768 y 390 px.
- No se altera el payload enviado ni las reglas de calculo.
- Se conservan `promise-history` y `payments-table` sin cambios, salvo un defecto visual demostrable.

Riesgo: concentra JavaScript de calculo. Cualquier cambio debe limitarse a presentacion y validacion visual, sin tocar formulas.

### 7A.1 Auditoria funcional y correcciones aplicadas

Fecha: 2026-10-23.

Metodo: revision del esquema, del codigo y de consultas agregadas de solo lectura sobre `kp_crm_staging_masked` con el usuario `kp_stage_ro`. No se expusieron datos personales, no se modifico la base auditada y no se conecto el preview a staging.

#### Hallazgos del modelo

| Tema | Hallazgo agregado | Decision de presentacion |
| --- | --- | --- |
| DNI, cuenta y operacion | 229.389 filas, 213.830 DNI, 228.562 operaciones y 215.523 cuentas; hasta 14 operaciones por DNI y 14 por cuenta; 14.377 DNI con varias operaciones; 12.909 cuentas con varias operaciones; 822 operaciones compartidas entre DNI; 5 pares DNI+operacion duplicados | No asumir relacion 1:1. Mostrar cuenta y operacion en columnas separadas y no unir solo por DNI |
| Asignacion de asesores | 222.926 asignaciones, 37 valores distintos de asignacion, 408 DNI con 2 valores y 6.458 operaciones de cartera sin asignacion; la clave real es `(numdoc, operacion)` | Asignar y mostrar asesor por operacion. `CANCELADOS` es un estado de asignacion, no un asesor activo |
| Entidad y cosecha | 5 entidades y 28 cosechas; una cosecha pertenece a una sola entidad y una entidad tiene varias cosechas | Mantener `ENTIDAD` como institucion financiera y `COSECHA` como lote de cartera; no tratarla como anio, categoria o estado |
| Pagos | 40.780 pagos, 13.211 DNI y 13.105 operaciones; 476 pagos sin operacion en cartera y 478 sin coincidencia por DNI+operacion; `cuenta_recaudo` tiene 7 valores y siempre esta poblada | Presentar pagos por DNI+operacion. `CUENTA RECAUDO` es el medio o destino del pago, no la cuenta de deuda |
| Promesas | 2.580 promesas, 2.739 relaciones, 154 promesas con varias operaciones y 0 operaciones fuera del DNI | Usar `promesa_operaciones` como relacion real y mostrar el alcance de cada propuesta |
| CNA | 1.457 CNA, 80 con varias operaciones, 0 operaciones fuera del DNI y 0 CNA con operaciones de cuentas distintas; se persiste por DNI + JSON de operaciones, sin FK de cuenta | Mostrar el estado CNA por operacion y usar la cuenta solo como contexto de deuda. El modal conserva el comportamiento existente de incluir las operaciones de la misma cuenta |
| Documentos CCD | Se vinculan por DNI + cosecha mapeada, no por operacion | Mantener una tarjeta independiente de Documentos CCD, sin mezclarla con la CNA |

#### Cambios visuales y de presentacion

- Se elimino el panel lateral `profile-panel` y la tarjeta lateral `cna-panel`.
- Se creo `components/clientes/operation-context` para mostrar el contexto de la operacion seleccionada dentro de la tabla de cuentas; el componente se retiro en la correccion 7B.3.
- Se creo `components/clientes/documents-panel` para los documentos CCD, separado de la CNA.
- `clientes/show` usa el ancho completo de la ficha; la tabla separa operacion, cuenta de deuda, entidad, cosecha, asesor, CNA y pagos.
- La CNA por fila se toma de `cnasByOperacion`; una operacion sin CNA muestra `Solicitar`.
- El encabezado separa asesores activos de operaciones en `CANCELADOS` y explica que la deuda total es la suma de operaciones.
- La tabla de pagos aclara que `CUENTA RECAUDO` es el medio de pago.
- El modal CNA muestra cuenta de deuda, entidad, cosecha/lote, origen/plantilla y operaciones incluidas; no crea CNA ni modifica asociaciones historicas.
- Se retiro la franja de metricas redundantes de los reportes de Pagos, Promesas y CNA; se conservaron titulo, filtros, tabla, paginacion, exportacion, estados vacios y carga.
- Las tablas de reportes muestran una indicacion visible de desplazamiento horizontal y conservan scroll interno.
- Se retiraron `overflow-x: hidden` de `body` y `overflow-x: clip` de `html`; el control de overflow se resolvio con `min-width: 0`, flex/grid y scroll interno, sin ocultar contenido globalmente.

#### Escenario sintetico de validacion

Se uso un escenario local en el preview con dos cuentas de deuda, cuatro operaciones, dos entidades, dos cosechas, tres valores de asignacion (incluida una operacion en `CANCELADOS`), pagos por operacion, dos promesas (una multioperacion), una CNA que cubre solo una operacion y dos documentos CCD. No se usaron datos personales reales.

#### Validacion ejecutada

- `npm run build`: correcto.
- `php artisan view:cache` ejecutado como `www-data`: correcto.
- `validate-ola7a-audit.js`: 38/38 comprobaciones aprobadas.
- Viewports 1440x900, 1280x800, 768x1024 y 390x844 sin overflow de pagina.
- Consola, errores de pagina, requests fallidos y respuestas 4xx/5xx: limpios.
- Exportaciones de Pagos, Promesas y CNA: HTTP 200.
- Revision global de 11 rutas por 4 anchos (44 combinaciones): sin overflow de pagina y sin errores.
- Prueba unitaria Laravel: 1/1. Las pruebas Feature usan `DatabaseTransactions` sobre la base de preview y no se ejecutaron para no escribir en ella; la validacion funcional se hizo con Playwright sobre datos sinteticos.
- `git diff --check`: limpio.

Capturas de la correccion:

- `ola7a-audit-cliente-1440x900.png`
- `ola7a-audit-cliente-1280x800.png`
- `ola7a-audit-cliente-768x1024.png`
- `ola7a-audit-cliente-390x844.png` (página completa)
- `ola7a-audit-cna-modal-1440x900.png`
- `ola7a-audit-reporte-reportes-pagos-1440x900.png`
- `ola7a-audit-reporte-reportes-promesas-1440x900.png`
- `ola7a-audit-reporte-reportes-cna-1440x900.png`
- `ola7a-audit-results.json`

Las capturas previas `ola7a-antes-*` se conservan como referencia comparativa.

#### Pendiente y limites

- La Ola 7A fue aprobada. No se inicia 7B.
- El modal CNA conserva el comportamiento existente de incluir todas las operaciones de la misma cuenta de deuda. Si el producto requiere seleccionar solo una operacion, es un cambio funcional que requiere autorizacion.
- Cualquier cambio de backend, esquema, clave de asociacion, regla de CNA o asignacion requiere autorizacion explicita.

### 7A.2 Ajustes globales de identidad, soporte y formato monetario

Fecha: 2026-10-26.

Alcance: consolidar la identidad de KP Invest CRM Meridian, actualizar el correo de soporte del login y homogeneizar la presentacion monetaria. No se modificaron layouts, estructura aprobada, comportamiento backend ni logica financiera. No se avanzo a 7B.

#### Identidad visual

- Tokens principales: primario `#00A81C`, verde oscuro `#008517`, verde tinta `#006F13`, verde suave `#EAF8ED`, borde verde `#CDEBD5`.
- Secundario azul `#1769E0`, azul oscuro `#0B4FC4`, navy `#062B60`.
- Fondo `#F5F7FA`, superficie `#FFFFFF`, borde `#E4E7EC`, texto `#111827`.
- El verde se usa en marca, navegacion activa, acciones primarias, filtros seleccionados, checkboxes, foco, controles, tabs e indicadores principales.
- El azul se mantiene como secundario para enlaces, informacion complementaria y series de graficos. Los estados semanticos rojo, ambar, verde y azul no se reemplazaron.
- Botones con texto blanco y foco usan `#008517`. Texto e iconos pequenos sobre fondos verdes suaves o claros usan `#006F13` para WCAG AA.
- El logo original de KP Invest no fue alterado.
- La implementacion se concentro en `resources/css/app.css`, `components/ui/*`, `components/layout/*`, `components/tables/*`, login, dashboards, ficha de cliente, reportes, autorizaciones, promesas y CNA.

#### Correo de soporte

- El unico cambio fue el enlace de soporte del login: de `impulse.conciliacion-cobranza@mgi-go.com` a `jesus.garcia@kpinvest.pe`.
- Se conservo `mailto:` y el enlace usa el azul secundario.
- No se cambiaron destinatarios de notificaciones, correos operativos, SMTP, plantillas ni flujos de negocio.

#### Formato monetario

- Se agrego `app/Support/Money.php` con `format()` y `symbol()` para PHP.
- Se agrego `resources/js/core/money.js` con `formatMoney()`, `formatMoneyCompact()` y `currencySymbol()` para JavaScript.
- Convencion: coma para miles, punto para decimales, siempre dos decimales, negativos preservados y simbolo cuando corresponde. PEN se muestra como `S/`.
- Se respeta la moneda de la operacion. No hay conversion automatica a soles. Monedas no reconocidas o compuestas se muestran sin simbolo.
- `ClienteAccountService::accountsForDni()` ahora selecciona `moneda` solo para presentacion. No se modificaron deuda, promesas, cuotas, redondeos, validaciones, payloads, parametros, contratos API, importaciones CSV ni valores numericos XLSX.
- Los inputs monetarios editables conservan su valor numerico sin separadores. No se altero el valor enviado al servidor.
- No se aplican separadores monetarios a DNI, cuentas, operaciones, cosechas, telefonos, IDs, fechas ni enteros no monetarios.

#### Validacion ejecutada

- `npm run build`: correcto.
- `php artisan view:cache`: correcto.
- Suite Laravel completa en base descartable independiente: **28 passed, 4 skipped, 2 deprecated (298 assertions)**. Los 4 skipped son smoke tests que requieren usuario/cliente local sintetico.
- Capturas Playwright `ola7a-global-despues-*`: login 1440/390, resumen, dashboard 1440/768/390, ficha 1440/768/390, reportes Pagos/Promesas/CNA, autorizaciones, detalle, decision, modal de promesa, cronograma, modal CNA de cliente y modal CNA de autorizacion.
- Revision global de 11 rutas por 4 anchos: **44/44 sin overflow**, sin errores de consola, errores JavaScript, requests fallidos ni respuestas 4xx/5xx.
- Contraste WCAG AA: **0 fallos** en login, resumen, dashboard, ficha, reportes y autorizaciones. El ultimo caso corregido fue `Analitica de cobranza`, que paso de `#008517` a `#006F13`.
- Filtros: Pagos 14 -> 10 filas, Promesas 7 -> 4, CNA 3 -> 3; estados vacios y chips de filtro operativos.
- Exportaciones: las tres respondieron HTTP 200 con firma XLSX `PK` y contenido `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.
- XLSX: montos numericos sin formato de moneda. Pagos `Monto` 450/280/175; Promesas `Deuda_Act`, `Capital_Act` y `Pago_Ini` numericos; CNA `Cna_Imp` 450/280/850. No se encontraron cadenas monetarias en celdas de datos.
- `git diff --check`: limpio.
- Capturas comparativas: `OLA7A/ola7a-global-antes-*` y `OLA7A/ola7a-global-despues-*` en `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7A\`. Las referencias `antes` de login y dashboard son las ultimas capturas previas disponibles de las olas 5 y 3; el resto usa `OLA7A/ola7a-despues-*` y `OLA6/ola6-despues-*`.

#### Pendiente y limites

- 7B finalizada y aprobada el 2026-10-27; OLA 8 a OLA 10 no iniciadas y requieren autorizacion explicita.
- El preview actual solo contiene operaciones PEN. La representacion USD, EUR y de monedas compuestas se valido a nivel de helper; falta una captura de vista con datos no-PEN reales.
- No se hicieron commits, push ni despliegues. Solo se inicio el preview local `8091` con autorizacion expresa; no se tocaron 8088, 8090, staging ni produccion.

### 7B - Solicitudes CNA y documentos

Estado de ejecucion: **finalizada y aprobada el 2026-10-27**. No se inicio OLA 8.

Complejidad: media. Prioridad: alta.

Dependencia: reutiliza los patrones de modal y formulario definidos en 7A.

Archivos previstos:

- `resources/views/components/cna/create-modal.blade.php`
- `resources/views/components/cna/debt-selector.blade.php`
- `resources/js/modules/cna/form.js`
- Ajustes acotados en `components/cna/debt-selector.blade.php` y `components/clientes/documents-panel.blade.php` solo si se aprueba mejorar acciones documentales.

Criterios de aceptacion:

- El modal muestra cuenta, cosecha, operaciones y contexto de deuda con jerarquia Meridian.
- El formulario usa los controles visuales vigentes y muestra errores junto al campo.
- Los estados de solicitud y los enlaces PDF/DOCX son claros y accesibles.
- No se modifica la generacion de documentos ni el flujo de estados.
- Funciona en desktop y movil.

Riesgo: el CNA combina solicitud, workflow y documentos. No se debe mezclar la mejora visual con cambios de generacion o almacenamiento.

#### 7B.1 Implementacion y validacion

Fecha: 2026-10-26.

Estado: **implementada, validada y aprobada el 2026-10-27**. No se inicio OLA 8.

Alcance ejecutado:

- Nuevo `components/cna/document-state` con modos `badge`, `compact` y `panel`. El estado se deriva del workflow y de `docx_path`/`pdf_path` con `Storage::exists()`; no se agrego un campo de estado backend.
- Nuevo `components/cna/request-button` que conserva `genCnaBtn`, `data-modal-open`, `data-dni`, `data-cuenta`, `data-oper`, `data-cosecha`, `data-entidad`, `data-producto`, `data-pagos-count`, `data-pagos-sum` y `data-moneda`.
- Modal de solicitud CNA y selector de deuda con cabecera del cliente, cuenta, entidad, cosecha/lote, origen, serie, plantilla, pagos registrados, total pagado, operaciones incluidas, monto en vivo, restauracion de `old()` input, aviso documental y resumen en el footer.
- `js/modules/cna/form.js` conserva la inclusion de todas las operaciones de la misma cuenta. Solo agrega presentacion, contexto, resumen de pagos, monto en vivo y restauracion de la seleccion previa.
- Ficha de cliente: columna CNA con estado real, numero/conteo de CNA, documento disponible, pendiente, no encontrado o sin archivo, y accion `Solicitar nueva` cuando la ultima solicitud fue rechazada. No se reincorporo la tarjeta CNA duplicada retirada en 7A.
- Autorizacion: tabla CNA con datos de la solicitud; el estado y el panel documental se muestran en el modal; modal con flujo de autorizacion, notas, entidad/cosecha desde el endpoint existente de pagos, enlace a la ficha del cliente y filas de pago marcadas como CNA. No se ofrecen descargas antes de la aprobacion.
- `js/core/modal.js`: foco inicial, trampa de Tab, cierre con Escape, restauracion de overflow/foco y soporte de `data-modal-auto-open`.
- No se modificaron controladores, servicios, modelos, policies, migraciones, rutas, payloads, validaciones, calculos, permisos ni transiciones. El unico cambio de servicio presente en el arbol de trabajo es la exposicion de `moneda` documentada en 7A.2; 7B no agrego cambios backend.

Estados y documentos cubiertos:

- Disponible con PDF y DOCX.
- DOCX disponible sin PDF.
- Pendiente de generacion (preaprobada).
- Aprobada sin archivo o con fallo de generacion.
- Archivo registrado no encontrado.
- Pendiente de aprobacion.
- Aprobada y rechazada.
- Descargas: HTTP 200 para aprobada con ambos formatos; HTTP 403 para preaprobada, rechazada y pendiente; HTTP 404 para aprobada sin archivo y archivo faltante.
- DOCX con `application/vnd.openxmlformats-officedocument.wordprocessingml.document` y firma `PK`; PDF con `application/pdf` y firma `%PDF`.
- Se preservo el fallback backend: si falta el PDF, `cna.pdf` entrega el DOCX; la UI no muestra el formato no disponible.

Validacion ejecutada:

- `npm run build`: correcto.
- `php artisan view:cache`: correcto.
- Playwright `validate-ola7b.js`: **29/29 comprobaciones aprobadas**, en 1440x900, 1280x800, 768x1024 y 390x844; sin errores de consola ni `pageerror`.
- Suite Laravel completa sobre base descartable `kp_crm_ui_test`: **28 passed, 4 skipped, 2 warnings (298 assertions)**. `V3CnaModuleTest`: **2 tests / 27 assertions**. Los 4 skipped son smoke tests que requieren usuario/cliente local sintetico; los 2 warnings son del bootstrap por `.env` ausente en el contenedor.
- Smoke posterior a la limpieza: login, Resumen, Dashboard, ficha de cliente, Autorizacion y reportes de Pagos/Promesas/CNA responden HTTP 200; los modales CNA y de propuesta abren sin IDs duplicados; verde/azul, Inter y formato monetario se mantienen; sin errores de consola.
- `git diff --check`: limpio.

#### 7B.2 Ajuste de UI posterior

Fecha: 2026-10-27.

- Ficha de cliente: se retiro el bloque visible "Contexto de la operacion". La seleccion de operaciones, el contador y la accion "Generar propuesta" se mantienen; `setupOperationContext()` no falla cuando el panel no existe.
- Autorizacion: se retiraron el strip de KPIs y el buscador. Las bandejas conservan el filtro por rol, los contadores por seccion y la paginacion; no se cambio el controlador ni la consulta.
- Tablas de autorizacion: se retiro la columna Estado de promesas y CNA. El estado sigue disponible en el modal de detalle mediante `data-estado`; el panel documental CNA se conserva en el modal. Los `colspan` de las filas vacias quedaron en 7 y 6.
- Validacion: `npm run build` y `php artisan view:cache` correctos; Playwright sin errores de consola ni `pageerror`; modales de promesa y CNA abren y conservan estado/documento.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7B\ola7b-fix-*`.
- Revision final de Autorizaciones: administrador 4 promesas y 3 CNA en `preaprobada`; supervisor 2 promesas y 1 CNA en `pendiente`; acciones `Aprobar`/`Rechazar` y `Preaprobar`/`Rechazar` sin cruces; modales con estado completo y panel documental; sin columna Estado ni badges recortados; 1440 y 390 sin overflow de pagina. **80 comprobaciones, 0 fallos.** Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-final-validation.json`. Los datos temporales de supervisor/CNA se eliminaron y `asesor.supervisor_id` se restauro a `NULL`.
- Refuerzo de permisos: `PreapprovePromesaAction`, `ApprovePromesaAction` y `RejectPromesaAction` (y sus equivalentes CNA) ejecutan `Gate::forUser(...)->authorize(...)`. `PromesaPolicy`/`CnaPolicy` limitan `preapprove` y `reject-as-supervisor` a supervisor, y `approve`/`reject-as-administrator` a administrador. El middleware amplio de ruta no permite saltarse la policy; no hay contradiccion.

#### 7B.3 Correccion puntual - Bloque Contexto de la operacion

Fecha: 2026-10-28.

- Hallazgo: el bloque ya no estaba incluido en `clientes/show.blade.php` ni se renderizaba en `localhost:8091`; quedaba el componente huerfano `components/clientes/operation-context.blade.php`, sin referencias en vistas.
- Cambio: se elimino el componente huerfano. No se modificaron la tabla, las columnas, los checkboxes, los botones ni la logica de seleccion, promesas o CNA. El hook `setupOperationContext()` conserva su guarda y queda inerte.
- Cache: `view:clear`, `view:cache` y `optimize:clear` como `www-data`; sin coincidencias del bloque en las vistas compiladas.
- Validacion: `npm run build`; 21 comprobaciones Playwright, 0 fallos. Seleccion individual y total, contador, `Generar propuesta`, modal de propuesta, apertura y cierre del modal CNA, navegacion `Ver operaciones` -> `#cuentas`, 1440x900 y 390x844, scroll interno de tabla, sin overflow de pagina ni errores de consola/red.
- Capturas: `OLA7B/ola7b-fix-contexto-cliente-1440x900.png` y `OLA7B/ola7b-fix-contexto-cliente-390x844.png`.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-contexto-cliente-validation.json` y `C:\KP-CRM-Meridian-ui-preview\ola7b-contexto-cliente-nav-validation.json`.

#### 7B.4 Correccion puntual - Asesores en el encabezado del cliente

Fecha: 2026-10-28.

- Auditoria: el asesor del encabezado se calculaba solo en `components/clientes/header.blade.php` desde `$cuentas->pluck('asesor')`; el `match` generaba un asesor unico, `N asesores activos`, `Sin asesor asignado` o el resumen de `CANCELADOS`. Era presentacion, no alimentaba seleccion, promesas, CNA ni persistencia.
- Cambio: se retiraron del header el calculo de asignaciones y el bloque visual del asesor. Se conservaron DNI, cuentas, operaciones, pagos y las tarjetas de deuda/pagos/promesas. La tabla, sus `data-asesor`, la columna `Asesor` y la logica de seleccion quedaron intactas.
- Validacion: 29 comprobaciones Playwright, 0 fallos, con el DNI `00000100` y cuatro operaciones asignadas a `Asesor Uno`, `Asesor Dos`, `CANCELADOS` y `Asesor Uno`. Encabezado sin asesor; tabla con las cuatro asignaciones; `CANCELADOS` conserva `S/ 5,600.00` y la deuda total `S/ 45,000.00`; seleccion individual/multiple, propuesta, CNA, 1440x900 y 390x844 sin overflow de pagina, tabla con scroll horizontal interno y sin errores de consola/red.
- Capturas: `OLA7B/ola7b-fix-asesor-cliente-1440x900.png` y `OLA7B/ola7b-fix-asesor-cliente-390x844.png`.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-asesor-cliente-validation.json`.
- `MANIFEST.csv`: 221 filas; 220 PNG + 1 JSON; 32,189,753 bytes; OLA7B con 58 PNG.

#### 7B.5 Correccion puntual - Descargas CNA compactas

Fecha: 2026-10-28.

- Objetivo: sustituir los botones PDF/DOCX de la columna CNA de la ficha por un unico icono. PDF tiene prioridad; si no existe PDF se ofrece DOCX; si no existe ninguno se muestra un estado neutro sin enlace.
- Auditoria: `clientes/show.blade.php` toma la CNA mas reciente por operacion (`sortByDesc('created_at')`) y muestra `xN` si hay varias. La celda usa `x-cna.document-state` en modo `compact`; el componente comprueba `pdf_path`/`docx_path` con `Storage::exists()`. Las rutas `cna.pdf`/`cna.docx` llaman a `CnaDocumentService`, que exige `workflow_estado = aprobada` y conserva el fallback interno. No se modificaron controladores, servicios, modelos, policies, rutas ni migraciones.
- Cambio: solo la rama `compact` de `components/cna/document-state.blade.php`. Un enlace de 32x32 px con SVG rojo para PDF o azul para DOCX, `aria-label`, `title`, foco visible y teclado. Sin archivo se muestra un icono neutro no interactivo. La rama `panel` de Autorizacion queda intacta. Pendiente/preaprobada conserva `Al aprobar`; rechazada conserva `Solicitar nueva` y no se convierte en descargable por tener una ruta.
- Validacion: `npm run build` y `view:cache` correctos. Playwright **46/46, 0 fallos** sobre DNI sintetico `00000999` con 7 operaciones: PDF+DOCX, solo DOCX, sin archivo, pendiente, preaprobada, rechazada, operacion con dos CNA y operaciones distintas. Descargas reales: PDF con `%PDF`; DOCX valido con `[Content_Types].xml` y `word/document.xml`; 403 en pendiente/preaprobada/rechazada aun con archivos; 404 en aprobada sin archivo. `V3CnaModuleTest`: 2 tests, 27 assertions, sin fallos (2 warnings de bootstrap por `.env` ausente).
- Ambiguedad: se conserva la CNA mas reciente por operacion y el badge `xN`; no hay selector de CNA historica. El icono descarga la CNA mas reciente. La UI comprueba las rutas de la base, mientras el servicio resuelve `CNA {nro_carta} - {dni}`; los registros generados cumplen esa convencion. No se modifico el servicio.
- Capturas: `OLA7B/ola7b-fix-descarga-cna-1440x900.png`, `OLA7B/ola7b-fix-descarga-cna-390x844.png`, `OLA7B/ola7b-fix-descarga-cna-pdf.png` y `OLA7B/ola7b-fix-descarga-cna-word.png`.
- Evidencia: `C:\KP-CRM-Meridian-ui-preview\ola7b-cna-download-validation.json` y `C:\KP-CRM-Meridian-ui-preview\validate-ola7b-cna-downloads.js`.
- `MANIFEST.csv`: 225 filas; 224 PNG + 1 JSON; 32,805,871 bytes; OLA7B con 62 PNG. Datos sinteticos eliminados al terminar.

Evidencia visual:

- `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7B\ola7b-antes-*`
- `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7B\ola7b-despues-*`
- `C:\KP-CRM-Meridian-ui-preview\screenshots\OLA7B\ola7b-valid-*`
- `ola7b-valid-cliente-docx-only-1440x900.png`
- `ola7b-smoke-cleanup-1440x900.png`
- `OLA7B/ola7b-fix-descarga-cna-*`
- Scripts reproducibles: `C:\KP-CRM-Meridian-ui-preview\validate-ola7b.js` y `C:\KP-CRM-Meridian-ui-preview\validate-ola7b-cna-downloads.js`

Limpieza:

- Se eliminaron los usuarios sinteticos `910001` y `910002`, las CNA `910001` a `910006`, la cuenta/cliente sintetico `00000101` con sus 4 operaciones y 3 pagos, los cuatro archivos CNA/template y la base descartable `kp_crm_ui_test`.
- El preview quedo con los datos demo originales: 3 CNA y 2 usuarios; el smoke no encontro texto `7B-` ni el cliente sintetico.
- No se hicieron commits, push, despliegues, recreacion de contenedores ni eliminacion de volumenes.

Limites:

- La tabla de autorizacion ya no muestra la columna Estado; el estado completo y el panel documental CNA estan en el modal de detalle.
- La validacion visual de CNA con moneda distinta de PEN sigue limitada porque el preview solo contiene operaciones PEN.
- OLA 8 a OLA 10 permanecen detenidas hasta autorizacion explicita; OLA 8 solo cuenta con inventario.

### OLA 8 - Administracion de usuarios (antes 7C)

Complejidad: media-baja. Prioridad: media.

Archivos previstos:

- `resources/views/placeholders/administracion.blade.php`
- `resources/js/modules/admin/users.js`

Criterios de aceptacion:

- Usa `page-shell`, `page-header`, metricas y una unica barra de filtros.
- La tabla usa `x-tables.table`, `x-tables.th`, `x-tables.td`, `x-tables.empty-row` y `x-tables.pagination`.
- Los modales de alta y password conservan su comportamiento.
- Las confirmaciones usan un dialogo Meridian, no `confirm()` nativo.
- No cambian reglas de roles, permisos ni validaciones.

Riesgo: la vista se llama `placeholders.administracion`. Mantener el nombre evita tocar el controlador. Si se desea renombrar a `admin.usuarios`, el unico cambio backend seria la ruta de la vista y debe aprobarse explicitamente.

### OLA 9 - Integraciones y carga de archivos (antes 7D)

Complejidad: alta. Prioridad: media.

Dependencia: conviene hacerla despues de estabilizar formularios y estados en 7A-OLA 8.

Archivos previstos:

- `resources/views/placeholders/integracion-data.blade.php`
- `resources/views/placeholders/integracion-asignacion.blade.php`
- `resources/views/placeholders/integracion-ccd.blade.php`
- `resources/views/placeholders/integracion-pagos.blade.php`
- `resources/js/modules/integracion/imports.js`
- Nuevos componentes de dominio, por ejemplo `components/integracion/dropzone`, `import-summary` y `recent-batches`.

Criterios de aceptacion:

- Las cuatro pantallas comparten estructura, dropzone, estados y resumen de resultado.
- Data, Asignacion y CCD incorporan precheck de encabezados y tipos, equivalente al de Pagos.
- Pagos conserva su precheck y muestra el ultimo lote con el patron Meridian.
- Los errores de archivo, encoding, tamano y filas se muestran de forma consistente.
- No se modifican servicios de importacion, validaciones backend ni estructura de datos.
- Las pruebas se hacen con CSV sinteticos, nunca con archivos reales.

Riesgo: es el modulo con mayor superficie de error por carga de archivos. La validacion visual no debe alterar el procesamiento.

### OLA 10 - Notificaciones y estados operativos (antes 7E)

Complejidad: baja-media. Prioridad: media-baja.

Dependencia: se ejecuta al final para aplicar la tabla canonica de estados a todos los modulos.

Archivos previstos:

- `resources/views/components/layout/notifications-bell.blade.php`
- `resources/js/modules/layout/notifications.js`
- `resources/views/components/clientes/status-badge.blade.php` o un alias visual aprobado
- Ajustes puntuales de etiquetas en las vistas de Ola 7.

Criterios de aceptacion:

- La campanita conserva loading, vacio, lectura individual, lectura masiva y realtime.
- Se agregan estados de error/reintento sin cambiar el contrato JSON.
- Las etiquetas y tonos de estado son consistentes en Promesas, CNA, Autorizaciones, Admin e Integraciones.
- No se cambian los valores persistidos de estado.
- Si se decide crear una pagina completa de notificaciones, se define primero la ruta y el alcance; no se asume dentro de esta ola.

Riesgo: cualquier cambio en el mapeo de estados puede afectar varias pantallas aprobadas. Se hace al final y con revision visual de las pantallas de Ola 6.

## 4. Criterios transversales de aceptacion

- Capturas de 1440x900, 1280x800, 768x1024 y 390x844 por pantalla o modal relevante.
- Sin scroll horizontal de pagina; las tablas pueden desplazarse dentro de su contenedor.
- Sin errores de consola, errores JavaScript, requests fallidos ni respuestas 4xx/5xx.
- Sin cambios en `app/`, `routes/`, `database/`, `config/` ni `resources/lang/`.
- Sin cambios en payloads, formulas, permisos, validaciones backend ni transiciones de estado.
- Uso preferente de `x-ui.*`, `x-tables.*` y `x-layout.*`.
- No introducir Bootstrap, CSS en `public/css` ni JS en `public/js`.
- Mantener accesibilidad: labels asociados, foco visible, `aria-live` en validaciones y modales con cierre por teclado.
- Las pantallas aprobadas de Olas 1 a 6 se usan como regresion visual.

## 5. Riesgos y decisiones pendientes

Riesgos:

1. Cambios globales en `x-ui.modal`, `x-ui.button`, `x-ui.card` o `app.css` pueden alterar pantallas ya aprobadas.
2. El mapeo de estados por texto puede generar etiquetas inconsistentes si no se centraliza.
3. Las importaciones tienen validaciones y codificaciones sensibles; el rediseno no debe tocar el procesamiento.
4. Renombrar vistas `placeholders.*` obliga a cambiar la ruta de vista en controladores.
5. La pagina de notificaciones no existe hoy; crearla implica una decision de producto y posiblemente una ruta nueva.
6. Los documentos CNA dependen de generacion PDF/DOCX ya existente; el alcance visual no incluye esos archivos.

Decisiones que se requieren antes de implementar:

1. Aprobar el orden 7A, 7B, OLA 8, OLA 9 y OLA 10 o indicar prioridad distinta.
2. Confirmar si "notificaciones" incluye una pagina completa o solo la campanita y la consistencia de estados.
3. Confirmar si se permite renombrar las vistas `placeholders.*` con el ajuste de una linea en el controlador.
4. Confirmar si "documentos CNA" incluye visor/carga o solo acciones de descarga y estado.
5. Confirmar si se autoriza tocar componentes globales; si se tocan, se revalida Ola 6.
6. Confirmar la convencion de capturas para OLA 8, OLA 9 y OLA 10 (`ola8-*`, `ola9-*`, `ola10-*`).
7. Confirmar si OLA 8 incluye edicion de usuarios; hoy no existe ruta ni metodo de edicion.
8. Confirmar si OLA 8 incluye una matriz editable de permisos o solo la presentacion de roles fijos.

## 6. Siguiente paso

La Ola 7A quedo aprobada. La Ola 7B fue autorizada, implementada, validada y aprobada el 2026-10-27. La OLA 8 tiene unicamente el inventario preparado en `docs/06_meridian/ola8_inventario_administracion.md`; no se implemento ningun cambio y se detiene para aprobacion. Las OLA 9 y OLA 10 permanecen reservadas. No se inicia ninguna implementacion hasta recibir autorizacion explicita. La implementacion se hara por olas con capturas, validacion y detencion para revision, manteniendo el trabajo en `feature/meridian-ui-v3` y sin commits, push ni despliegues.
