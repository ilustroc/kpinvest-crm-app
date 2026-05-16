# 12 - Checklist Deploy V3

## Formato del checklist

- [+] Confirmado.
- [ ] Pendiente.
- [!] Riesgo o requiere revision.

## Mapa de fases V3

- [+] Fase 1 - Entorno local seguro.
- [+] Fase 2 - Arquitectura MVC modular por dominios.
- [+] Fase 3 - Tailwind CSS v4 con Vite.
- [+] Fase 4 - Migracion inicial Bootstrap -> Tailwind.
- [+] Fase 5 - Baseline de migraciones + limpieza de roles.
- [+] Fase 6 - Pruebas funcionales reales con cobertura inicial completada.
- [+] Fase 7 - Arquitectura frontend V3 + migracion Tailwind completada a nivel tecnico.
- [+] Fase 8 - Refactor backend por modulos criticos iniciado.
- [ ] Fase 9 - Deploy controlado.

## 1. Rama y codigo

- [+] Confirmar que el trabajo esta en rama V3.
- [+] Confirmar que `main` no fue modificado directamente.
- [+] Confirmar que el PR o merge contiene solo cambios esperados.
- [+] Revisar que no haya archivos temporales, logs o dumps sensibles versionados.
- [+] Revisar que `.env` no este incluido en commits.
- [+] Revisar `git diff` completo antes de merge.

## 2. Base de datos local

- [+] Dump descargado desde produccion.
- [+] Base de datos importada en local.
- [+] Proyecto funcionando en localhost.
- [+] `.env` local apunta a base local.
- [+] Produccion no sera modificada en esta fase.
- [+] Validar flujos actuales contra la base local.
- [+] Errores locales documentados.
- [+] Documentar diferencias detectadas entre SQL, modelos y migraciones.

## 3. Arquitectura

- [+] Arquitectura V3 definida como MVC modular por dominios.
- [+] Se mantendra Laravel.
- [+] Se mantendra Blade.
- [+] Se usaran Services.
- [+] Se usaran Actions donde aplique.
- [+] Se usaran ViewModels para vistas complejas.
- [+] Se usaran componentes Blade.
- [+] Separar rutas por modulo.
- [+] Crear estructura de carpetas por dominios.
- [+] Crear servicio piloto de bajo riesgo.
- [+] Crear ViewModel piloto.
- [+] Definir Policies/Gates por modulo critico.
- [+] Revisar permisos actuales.
- [+] Crear documentacion inicial de permisos.
- [+] Refactor backend de Clientes iniciado.
- [+] Crear servicios reales del modulo Cliente.
- [+] Crear `ClienteShowViewModel`.
- [+] Crear `DeleteClientePaymentAction`.
- [+] Crear `ClientePolicy`.
- [+] Delegar `ClienteController` a Services/Action sin cambiar rutas.
- [+] Delegar `ClienteLookupController` a `ClienteLookupService`.
- [+] Refactor backend de Promesas iniciado.
- [+] Crear servicios reales del modulo Promesas.
- [+] Crear Actions de Promesas.
- [+] Crear `PromesaPolicy`.
- [+] Delegar `PromesaController` a `CreatePromesaAction`.
- [+] Delegar workflow de Promesas a Actions/Service.
- [+] Delegar acuerdo de Promesa a `PromesaDocumentService`.
- [+] Refactor backend de CNA iniciado.
- [+] Crear servicios reales del modulo CNA.
- [+] Crear Actions de CNA.
- [+] Crear `CnaPolicy`.
- [+] Delegar `CnaController` a Actions/Services.
- [+] Delegar consulta CNA de Autorizacion a `CnaQueryService`.
- [!] No mover controladores criticos sin pruebas o validacion funcional.

## 3.1 Fase 8.1 - Refactor backend Clientes

- [+] Analizar responsabilidades actuales de `ClienteController`.
- [+] Crear `app/Services/Cliente/ClienteLookupService.php`.
- [+] Crear `app/Services/Cliente/ClienteProfileService.php`.
- [+] Crear `app/Services/Cliente/ClienteAccountService.php`.
- [+] Crear `app/Services/Cliente/ClientePaymentService.php`.
- [+] Crear `app/ViewModels/Cliente/ClienteShowViewModel.php`.
- [+] Crear `app/Actions/Cliente/DeleteClientePaymentAction.php`.
- [+] Crear `app/Policies/ClientePolicy.php`.
- [+] Registrar Gates de Cliente en `AuthServiceProvider`.
- [+] Refactorizar `ClienteController` para orquestar Services/Action.
- [+] Refactorizar `ClienteLookupController` para usar `ClienteLookupService`.
- [+] Mantener rutas y nombres de rutas actuales.
- [+] Mantener variables esperadas por `clientes/show.blade.php`.
- [+] Mantener regla de eliminacion de pagos: administrador, supervisor y soporte.
- [+] Crear `tests/Feature/V3ClienteModuleTest.php`.
- [+] Documentar refactor en `docs/22_refactor_backend_clientes_v3.md`.
- [!] Busqueda por telefono queda pendiente hasta definir fuente estable sin cambiar comportamiento.
- [+] CNA refactorizado en Fase 8.3.

## 3.2 Fase 8.2 - Refactor backend Promesas

- [+] Analizar `PromesaController`.
- [+] Analizar `AutorizacionController` para workflow de promesas.
- [+] Analizar `PromesaPdfController`.
- [+] Analizar `PromesaCreator`.
- [+] Crear `app/Services/Promesa/PromesaCreationService.php`.
- [+] Crear `app/Services/Promesa/PromesaScheduleService.php`.
- [+] Crear `app/Services/Promesa/PromesaWorkflowService.php`.
- [+] Crear `app/Services/Promesa/PromesaDocumentService.php`.
- [+] Crear `app/Services/Promesa/PromesaQueryService.php`.
- [+] Crear `app/Actions/Promesa/CreatePromesaAction.php`.
- [+] Crear `app/Actions/Promesa/PreapprovePromesaAction.php`.
- [+] Crear `app/Actions/Promesa/ApprovePromesaAction.php`.
- [+] Crear `app/Actions/Promesa/RejectPromesaAction.php`.
- [+] Crear `app/Actions/Promesa/GeneratePromesaAgreementAction.php`.
- [+] Crear `app/Policies/PromesaPolicy.php`.
- [+] Registrar Gates de Promesas en `AuthServiceProvider`.
- [+] Refactorizar `PromesaController`.
- [+] Refactorizar workflow de Promesas en `AutorizacionController`.
- [+] Refactorizar `PromesaPdfController`.
- [+] Mantener `PromesaCreator` como wrapper de compatibilidad.
- [+] Eliminar servicio legacy duplicado `app/Services/PromesaWorkflowService.php`.
- [+] Crear `tests/Feature/V3PromesaModuleTest.php`.
- [+] Documentar refactor en `docs/23_refactor_backend_promesas_v3.md`.
- [+] Documentar cuota balon como variante de flujo persistida por `promesa_cuotas.es_balon`.
- [+] CNA dentro de `AutorizacionController@index` refactorizado a `CnaQueryService` en Fase 8.3.
- [!] No cambiar persistencia de `convenio_balon` sin migracion y validacion productiva.

## 3.3 Fase 8.3 - Refactor backend CNA

- [+] Analizar `CnaController`.
- [+] Analizar `AutorizacionController` para bandeja CNA.
- [+] Analizar `CnaSolicitud`.
- [+] Crear `app/Services/Cna/CnaCreationService.php`.
- [+] Crear `app/Services/Cna/CnaNumberingService.php`.
- [+] Crear `app/Services/Cna/CnaWorkflowService.php`.
- [+] Crear `app/Services/Cna/CnaDocumentService.php`.
- [+] Crear `app/Services/Cna/CnaQueryService.php`.
- [+] Crear `app/Actions/Cna/CreateCnaAction.php`.
- [+] Crear `app/Actions/Cna/PreapproveCnaAction.php`.
- [+] Crear `app/Actions/Cna/ApproveCnaAction.php`.
- [+] Crear `app/Actions/Cna/RejectCnaAction.php`.
- [+] Crear `app/Actions/Cna/DownloadCnaDocumentAction.php`.
- [+] Crear `app/Policies/CnaPolicy.php`.
- [+] Registrar Gates de CNA en `AuthServiceProvider`.
- [+] Refactorizar `CnaController`.
- [+] Refactorizar consulta CNA en `AutorizacionController`.
- [+] Mantener rutas y nombres de rutas actuales.
- [+] Mantener estados, redirects, mensajes y fallback documental.
- [+] Crear `tests/Feature/V3CnaModuleTest.php`.
- [+] Documentar refactor en `docs/24_refactor_backend_cna_v3.md`.
- [!] Validacion manual visual de workflow CNA en navegador queda pendiente.
- [!] Separar Autorizacion en ViewModel/modulo propio queda como mejora posterior.

## 4. Frontend V3

- [+] Se define Tailwind CSS v4 como frontend objetivo.
- [+] Se usara Vite.
- [+] Bootstrap sera eliminado progresivamente.
- [+] Instalar Tailwind CSS v4.
- [+] Instalar `@tailwindcss/vite`.
- [+] Configurar `vite.config.js`.
- [+] Configurar `resources/css/app.css`.
- [+] Revisar `resources/js/app.js`.
- [+] Ejecutar `npm run build`.
- [+] Eliminar Bootstrap del layout principal.
- [+] Reemplazar componentes Bootstrap por Tailwind en pantalla piloto.
- [+] Migrar segunda pantalla piloto a Tailwind.
- [+] Usar JS modular con Vite en pantalla migrada.
- [!] Vulnerabilidades moderadas de Vite/esbuild pendientes por posible breaking change.
- [+] Bootstrap global eliminado despues de migrar modales/dropdowns dependientes.

## 5. Checklist de instalacion Tailwind CSS v4

- [+] Revisar `package.json`.
- [+] Instalar Tailwind CSS v4.
- [+] Instalar `@tailwindcss/vite`.
- [+] Configurar `vite.config.js`.
- [+] Actualizar `resources/css/app.css`.
- [+] Confirmar `@vite` en layout.
- [+] Ejecutar `npm run dev`.
- [+] Ejecutar `npm run build`.
- [+] Confirmar `public/build/manifest.json`.
- [+] Confirmar que el layout carga CSS por Vite.
- [+] Confirmar que el layout carga JS por Vite.
- [+] Retirar Bootstrap CDN del layout global.
- [!] Revisar errores en consola: pendiente validacion manual de navegador.
- [+] Migrar primera pantalla piloto.

## 6. Bootstrap -> Tailwind

- [+] Migrar layout principal: Vite activo sin Bootstrap global.
- [+] Migrar sidebar/topbar base.
- [+] Migrar botones base.
- [+] Migrar badges base.
- [+] Migrar formularios simples base.
- [+] Migrar tablas simples base.
- [+] Migrar administracion de usuarios.
- [+] Crear modal Tailwind reutilizable.
- [+] Crear componentes adicionales: textarea, date, dropdown, empty-state, confirm-dialog.
- [+] Migrar dashboard.
- [+] JS modular usado en Dashboard.
- [+] Migrar reportes.
- [+] Migrar integraciones.
- [+] Migrar Login.
- [+] Eliminar assets legacy confirmados como no usados de Administracion, Dashboard y Reportes.
- [+] Eliminar assets legacy de Login.
- [+] Migrar clientes.
- [+] Migrar CNA dentro de Cliente.
- [+] Migrar promesas dentro de Cliente.
- [+] Bootstrap eliminado globalmente.
- [!] Validacion manual visual pendiente en Cliente, CNA y Promesas.

## 6.1 Arquitectura frontend V3

- [+] Crear `docs/19_arquitectura_frontend_v3.md`.
- [+] Crear `docs/20_inventario_frontend_legacy.md`.
- [+] Crear estructura `resources/views/components/forms`.
- [+] Crear estructura `resources/views/components/tables`.
- [+] Crear estructura `resources/views/components/feedback`.
- [+] Crear estructura `resources/views/components/reportes`.
- [+] Crear estructura futura `resources/views/pages/*`.
- [+] Crear `resources/js/core`.
- [+] Crear modulos `resources/js/modules/reportes`.
- [+] Migrar JS de reportes desde `public/js/reportes` hacia Vite.
- [+] Migrar CSS de reportes desde `public/css/reportes` hacia Tailwind/componentes.
- [+] Reporte de pagos sin Bootstrap en layout.
- [+] Reporte de promesas sin Bootstrap en layout.
- [+] Reporte CNA sin Bootstrap en layout.
- [+] Archivos legacy de reportes en `public/` eliminados.
- [+] Integraciones sin Bootstrap en layout.
- [+] Login sin Bootstrap ni assets `public`.
- [+] Panel principal sin Bootstrap en layout.
- [+] Autorizacion sin Bootstrap en layout.
- [+] Clientes sin Bootstrap en layout.
- [+] Promesas/CNA dentro de Cliente migradas a componentes y JS Vite.

## 6.2 Limpieza de archivos legacy

- [+] Revisar referencias antes de borrar archivos.
- [+] Eliminar `public/css/reportes/*.css`.
- [+] Eliminar `public/js/reportes/*.js`.
- [+] Eliminar `public/css/dashboard-stats.css`.
- [+] Eliminar `public/js/dashboard-stats.js`.
- [+] Eliminar `public/css/admin/admin.css`.
- [+] Eliminar `public/js/admin/admin.js`.
- [+] Eliminar `public/css/app.css`.
- [+] Eliminar `public/js/app.js`.
- [+] Eliminar `public/js/bootstrap.js`.
- [+] Eliminar `public/css/layout/app.css`.
- [+] Eliminar `public/js/layout/app.js`.
- [+] Eliminar `resources/views/welcome.blade.php` por no tener ruta activa.
- [+] Eliminar `error_log`.
- [+] Eliminar `estructura_generada_v3.sql`.
- [+] Eliminar `public/css/auth/login.css` despues de migrar Login.
- [+] Eliminar `public/js/auth/login.js` despues de migrar Login.
- [+] Eliminar carpetas vacias `public/css`, `public/js`, `public/css/auth` y `public/js/auth`.
- [+] Confirmar que no quedan referencias activas a `public/css` o `public/js`.
- [+] Confirmar migraciones sin duplicados obsoletos.
- [+] Actualizar `.gitignore` para logs, temporales y estructura generada.
- [+] Bootstrap CDN retirado del layout.
- [+] Clientes migrado a Tailwind/Vite.

## 6.3 Fase 7.2 y 7.3 - Integraciones y Login

- [+] Migrar `integracion-pagos` a Tailwind/Vite.
- [+] Migrar `integracion-data` a Tailwind/Vite.
- [+] Migrar `integracion-asignacion` a Tailwind/Vite.
- [+] Migrar `integracion-ccd` a Tailwind/Vite.
- [+] Mover precheck CSV de pagos a `resources/js/modules/integracion/imports.js`.
- [+] Importar modulo de Integraciones desde `resources/js/app.js`.
- [+] Migrar `resources/views/auth/login.blade.php` a Tailwind/Vite.
- [+] Mover JS de Login a `resources/js/modules/auth/login.js`.
- [+] Importar modulo de Login desde `resources/js/app.js`.
- [+] Eliminar Bootstrap directo de Login.
- [+] Eliminar Bootstrap Icons directo de Login.
- [+] Eliminar `public/css/auth/login.css`.
- [+] Eliminar `public/js/auth/login.js`.
- [+] Bootstrap no carga en Integraciones/Login.

## 6.4 Fase 7.4 - Panel principal

- [+] Migrar `resources/views/panel/resumen.blade.php` a Tailwind/Vite.
- [+] Agregar `@section('tailwind_only', true)` al Panel principal.
- [+] Reemplazar cards Bootstrap por componentes Blade/Tailwind.
- [+] Reemplazar tabla de coincidencias por `x-tables.*`.
- [+] Reemplazar botones por `x-ui.button`.
- [+] Quitar Bootstrap Icons del Panel principal.
- [+] Quitar CSS embebido del Panel principal.
- [+] Quitar JS embebido del Panel principal.
- [+] Crear `resources/js/modules/panel/resumen.js`.
- [+] Importar modulo de Panel desde `resources/js/app.js`.
- [+] Quitar Chart.js por CDN.
- [+] Usar Chart.js desde npm/Vite.
- [+] Bootstrap no carga en Panel principal.

## 6.5 Fase 7.5 - Autorizacion

- [+] Migrar `resources/views/autorizacion/index.blade.php` a Tailwind/Vite.
- [+] Agregar `@section('tailwind_only', true)` a Autorizacion.
- [+] Reemplazar cards Bootstrap por componentes Blade/Tailwind.
- [+] Reemplazar botones por `x-ui.button`.
- [+] Reemplazar tablas por `x-tables.*`.
- [+] Reemplazar alertas por `x-feedback.alert`.
- [+] Reemplazar paginacion Bootstrap de CNA.
- [+] Reemplazar modales Bootstrap por modales Tailwind.
- [+] Crear componentes `resources/views/components/autorizacion/*`.
- [+] Crear `resources/js/modules/autorizacion/index.js`.
- [+] Importar modulo de Autorizacion desde `resources/js/app.js`.
- [+] Mantener formularios POST, CSRF y rutas actuales.
- [+] Clientes deja de ser vista legacy Bootstrap.
- [!] Validacion manual visual pendiente para acciones de workflow.

## 6.6 Fase 7.6 - Clientes y cierre Bootstrap

- [+] Migrar `resources/views/clientes/show.blade.php` a Tailwind/Vite.
- [+] Agregar `@section('tailwind_only', true)` a Clientes.
- [+] Reemplazar cards Bootstrap por componentes Blade/Tailwind.
- [+] Reemplazar botones por `x-ui.button`.
- [+] Reemplazar tablas por `x-tables.*`.
- [+] Reemplazar alertas por `x-feedback.alert`.
- [+] Reemplazar modales Bootstrap por `x-ui.modal`.
- [+] Crear componentes `resources/views/components/clientes/*`.
- [+] Crear componentes `resources/views/components/promesas/*`.
- [+] Crear componentes `resources/views/components/cna/*`.
- [+] Crear `resources/js/modules/clientes/show.js`.
- [+] Crear `resources/js/modules/promesas/form.js`.
- [+] Crear `resources/js/modules/promesas/schedule.js`.
- [+] Crear `resources/js/modules/cna/form.js`.
- [+] Importar modulo de Clientes desde `resources/js/app.js`.
- [+] Mantener formularios POST, CSRF y rutas actuales.
- [+] Eliminar Bootstrap CSS, Bootstrap Icons y Bootstrap JS del layout global.
- [!] Validacion manual visual pendiente para Cliente, Promesas y CNA.

## 6.7 Cierre frontend Tailwind/Vite

- [+] Confirmar que `resources/views/promesas_placeholder.txt` no existe.
- [+] Buscar referencias a `promesas_placeholder` y `placeholder.txt`.
- [+] Confirmar Bootstrap CSS eliminado.
- [+] Confirmar Bootstrap JS eliminado.
- [+] Confirmar Bootstrap Icons eliminado.
- [+] Confirmar ausencia de `data-bs`, `form-control`, `table-responsive` y `modal fade` en vistas/JS activos.
- [+] Confirmar que `public/css` no tiene archivos activos.
- [+] Confirmar que `public/js` no tiene archivos activos.
- [+] Eliminar `.gitkeep` innecesarios en modulos con archivos reales.
- [+] Revisar componentes duplicados.
- [+] Mantener wrappers `x-feedback.*` y componentes `x-ui.*` por compatibilidad con pantallas migradas.
- [+] Eliminar componentes placeholder no usados.
- [+] Revisar `resources/js/app.js`.
- [+] Revisar `resources/views/layouts/app.blade.php`.
- [!] Referencias restantes a Bootstrap son historicas/documentales o propias del framework (`bootstrap/app.php`, `bootstrap/cache`, PHPUnit).
- [!] Validacion manual de navegador pendiente.

## 6.8 Revision frontend post-diseno manual

- [+] Revisar cambios recientes con `git status`, `git log`, `git diff` y `git diff HEAD~1..HEAD`.
- [+] Crear `docs/21_revision_frontend_post_diseno_manual.md`.
- [+] Revisar layouts, componentes, vistas grandes, JS modular, CSS global, Vite y `package.json`.
- [+] Mantener cambios manuales de diseno que respetan la arquitectura V3.
- [+] Retirar `<script>` y `onclick` de `resources/views/components/ui/alert.blade.php`.
- [+] Centralizar toast/alertas en `resources/js/core/toast.js`.
- [+] Importar `resources/js/core/toast.js` desde `resources/js/app.js`.
- [+] Reemplazar `window.alert` de Promesas por `notify()`.
- [+] Retirar `window.toggleRail` de Sidebar.
- [+] Eliminar plantillas Bootstrap de paginacion no usadas.
- [+] Confirmar que no quedan scripts grandes embebidos en Blade/componentes.
- [+] Confirmar que `resources/js/app.js` importa solo modulos reales.
- [+] Confirmar que `resources/css/app.css` no recibio CSS especifico de pantalla.
- [!] Validacion manual de navegador pendiente.

## 7. Migraciones

- [+] Migraciones antiguas revisadas.
- [+] Migraciones antiguas eliminadas.
- [+] Nuevas migraciones generadas desde `u480021566_kpinvest_bd.sql`.
- [+] Revisar migraciones nuevas una por una.
- [+] Confirmar que no eliminan datos sin respaldo en base limpia.
- [+] Confirmar que no eliminan columnas usadas por codigo actual en baseline limpio.
- [+] Ejecutar migraciones solo sobre base local limpia desechable.
- [+] `php artisan migrate` ejecutado en base local limpia `kpinvest_v3_migrate_test`.
- [+] Ejecutar `php artisan migrate:status` en local.
- [+] Comparar conceptualmente SQL/base local vs migraciones disponibles.
- [+] Confirmar indices y claves foraneas.
- [+] Probar rollback si aplica.
- [+] Confirmar si se requieren seeders: no se requirieron en esta fase.
- [ ] Si se requieren seeders, confirmar que son idempotentes.
- [+] Baseline V3 creado.
- [+] Migracion de limpieza de roles creada.
- [!] En la base local principal las migraciones V3 aparecen pendientes porque el baseline no debe correrse sobre tablas existentes.
- [!] Diferencia SQL vs migraciones documentada: `users.role` elimina `sistemas` y `usuario`.
- [!] Migraciones historicas incompletas frente a la base real fueron reemplazadas, pero produccion requiere estrategia de baseline antes de ejecutar cualquier cambio.
- [!] No correr `php artisan migrate` en produccion todavia.
- [!] No correr `php artisan migrate:fresh` sobre bases con informacion.
- [!] No correr seeders en produccion sin revision.

## 8. Pruebas funcionales

- [+] `php artisan route:list --except-vendor`.
- [+] `php artisan view:clear`.
- [+] `php artisan cache:clear`.
- [+] `php artisan view:cache`.
- [+] `php -l` en archivos PHP nuevos/modificados.
- [+] `php artisan test`.
- [+] Login correcto: validada redireccion de invitado y pagina login.
- [+] Login bloqueado para usuario inactivo.
- [+] Busqueda rapida de cliente.
- [+] Vista de cliente carga cuentas.
- [+] Vista de cliente carga pagos.
- [+] Vista de cliente carga promesas.
- [+] Vista de cliente carga CNA.
- [+] Crear promesa de cancelacion.
- [+] Crear promesa de convenio.
- [+] Crear promesa con cuota balon.
- [+] Preaprobar promesa como supervisor.
- [+] Aprobar promesa como administrador.
- [+] Rechazar promesa como supervisor.
- [+] Rechazar promesa como administrador.
- [+] Crear CNA.
- [+] Preaprobar CNA.
- [+] Aprobar CNA.
- [+] Generar/descargar DOCX CNA.
- [+] Generar/descargar PDF CNA o validar fallback.
- [+] Generar acuerdo de promesa.
- [+] Importar pagos CSV.
- [+] Importar data maestra CSV.
- [+] Importar asignaciones CSV.
- [+] Importar CCD CSV.
- [+] Reporte de pagos.
- [+] Export reporte de pagos.
- [+] Reporte de promesas.
- [+] Export reporte de promesas.
- [+] Reporte CNA.
- [+] Export reporte CNA.
- [+] Administracion de usuarios.
- [+] Activar/desactivar usuarios.
- [+] Cambiar contrasena.
- [+] Dashboard.
- [+] Pantallas de importacion principales renderizan.
- [+] POST principales, exports e imports CSV validados con tests Feature y datos locales transaccionales.
- [!] Pruebas manuales en navegador real aun pendientes.

## 9. Assets y build

- [+] Ejecutar `npm install` si cambiaron dependencias.
- [+] Ejecutar `npm run build`.
- [+] Confirmar `public/build/manifest.json`.
- [+] Confirmar que las vistas migradas cargan CSS por Vite.
- [+] Confirmar que las vistas migradas cargan JS por Vite.
- [!] Confirmar que no hay errores en consola del navegador: pendiente manual.
- [!] Confirmar que modales funcionan: requiere prueba manual de acciones.
- [+] Confirmar que filtros AJAX funcionan en reportes mediante Feature tests.
- [+] Confirmar que paginacion AJAX funciona en reportes mediante Feature tests.
- [ ] Confirmar desktop.
- [ ] Confirmar mobile si aplica.

## 10. Configuracion de produccion

- [ ] Confirmar `APP_ENV=production`.
- [ ] Confirmar `APP_DEBUG=false`.
- [ ] Confirmar `APP_URL` correcto.
- [ ] Confirmar conexion DB productiva correcta.
- [ ] Confirmar credenciales SMTP.
- [ ] Confirmar claves iLovePDF si se usa conversion PDF.
- [ ] Confirmar `QUEUE_CONNECTION` esperado.
- [ ] Confirmar permisos de `storage/`.
- [ ] Confirmar permisos de `bootstrap/cache/`.
- [ ] Confirmar enlace storage si aplica.
- [ ] Confirmar cache config/rutas si se usa.

## 11. Plantillas y documentos

- [ ] Confirmar existencia de `storage/app/templates/Acuerdo_de_Pago_DNI_{dni}.docx`.
- [ ] Confirmar existencia de plantilla CNA KP Invest.
- [ ] Confirmar existencia de plantilla CNA Fondo Acreencia Arequipa.
- [ ] Confirmar existencia de plantilla CNA Acreencia II.
- [ ] Probar generacion DOCX.
- [ ] Probar conversion PDF.
- [ ] Validar fallback si iLovePDF falla.

## 12. Usuarios, roles y permisos

- [+] Roles finales definidos: administrador, supervisor, asesor, soporte.
- [+] Roles `sistemas` y `usuario` eliminados del codigo como roles validos.
- [+] Selects y validaciones de roles actualizados.
- [+] Gates/Policies actualizados.
- [+] Migracion `2026_05_15_000007_normalize_user_roles_v3.php` creada para convertir `sistemas`/`usuario` a `soporte`.
- [+] Confirmar roles existentes en base local: administrador, supervisor, asesor, soporte.
- [+] Confirmar usuarios administradores activos.
- [+] Confirmar que al menos un administrador queda activo en reglas de servicio.
- [+] Confirmar visibilidad de supervisor sobre su equipo en servicio/policy.
- [+] Confirmar restricciones para asesor con prueba automatizada.
- [+] Confirmar restricciones para soporte en administracion.
- [+] Confirmar acceso a integraciones por Gate.
- [+] Confirmar acceso a reportes por Gate.
- [+] Confirmar acceso a autorizaciones para administrador/supervisor.

## 13. Logs y monitoreo

- [ ] Revisar `storage/logs` en local/staging despues de pruebas.
- [ ] Confirmar que no hay errores nuevos.
- [ ] Confirmar que errores de correo se registran correctamente.
- [ ] Confirmar que errores de iLovePDF se registran correctamente.
- [ ] Confirmar que imports reportan errores utiles.
- [ ] Preparar monitoreo post-deploy.

## 14. Rollback

- [ ] Definir plan para volver al commit anterior.
- [ ] Definir plan para restaurar backup de base de datos.
- [ ] Confirmar responsable del rollback.
- [ ] Confirmar ventana de tiempo aceptable.
- [+] Confirmar que migraciones nuevas tienen `down` funcional en base desechable.
- [ ] Definir plan manual de rollback para produccion.
- [ ] Confirmar que assets anteriores pueden restaurarse.

## 15. Antes de ejecutar en produccion

- [ ] El equipo reviso y aprobo el cambio.
- [ ] Se descargo dump actualizado de produccion.
- [ ] Se probo sobre dump reciente.
- [ ] Se aprobo checklist funcional.
- [ ] Se aprobo checklist de assets.
- [ ] Se aprobo checklist de configuracion.
- [ ] Existe backup reciente.
- [ ] Existe rollback.
- [ ] Se definio ventana de deploy.
- [!] Si falla una prueba local sobre copia reciente, no desplegar.

## 16. Despues del deploy

- [ ] Limpiar caches si corresponde.
- [ ] Verificar login.
- [ ] Verificar panel.
- [ ] Verificar cliente real de prueba.
- [ ] Verificar promesas.
- [ ] Verificar CNA.
- [ ] Verificar pagos.
- [ ] Verificar reportes.
- [ ] Verificar logs.
- [ ] Confirmar con usuarios clave.

## Regla final

Si una migracion, build o prueba funcional falla sobre una copia reciente de produccion, no se debe ejecutar el cambio en produccion real.
