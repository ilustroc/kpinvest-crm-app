# 08 - Plan V3

## Estado confirmado

La planificacion V3 parte de estas decisiones y condiciones ya confirmadas:

- [+] El proyecto ya esta corriendo en localhost.
- [+] La base de datos productiva ya fue descargada e importada en local.
- [+] El archivo `.env` local apunta a `127.0.0.1` y a la base local.
- [+] La base local puede usarse para pruebas y validaciones.
- [+] Produccion no sera modificada en esta fase.
- [+] `main` no debe tocarse directamente.
- [+] La arquitectura elegida para V3 sera MVC modular por dominios.
- [+] El frontend migrara progresivamente a Tailwind CSS v4 usando Vite.
- [+] Bootstrap sera eliminado progresivamente, no de golpe.

## Objetivo de V3

La version 3 debe ordenar el proyecto sin poner en riesgo el sistema productivo. El objetivo principal no es reescribir todo, sino mejorar mantenibilidad, claridad, pruebas, frontend y seguridad de despliegue.

Objetivos concretos:

- Trabajar primero sobre localhost y base local importada desde produccion.
- Mantener produccion congelada hasta nuevo aviso.
- Mantener `main` protegido.
- Adoptar MVC modular por dominios.
- Separar logica de negocio en servicios y actions.
- Usar ViewModels para vistas complejas.
- Mantener Blade por ahora.
- Migrar progresivamente de Bootstrap a Tailwind CSS v4 con Vite.
- Crear migraciones ordenadas desde un baseline de la base real.
- Probar todo localmente antes de cualquier intento de deploy.

## Rama de trabajo

Toda planificacion y futuro desarrollo V3 debe hacerse en:

```text
v3/analisis-documentacion
```

`main` debe permanecer intacta mientras el sistema actual siga en produccion.

## Que se puede tocar ahora

En esta etapa se puede tocar:

- Documentacion.
- Configuracion local de frontend Tailwind/Vite.
- Nuevos archivos de estructura V3.
- Pantallas piloto de bajo riesgo.
- Componentes Blade reutilizables.
- Servicios y ViewModels piloto.
- Tests locales.
- Migraciones nuevas solo sobre base local.

Los cambios deben seguir siendo pequenos, revisables y reversibles.

## Que no se debe tocar todavia

No se debe:

- Modificar `main`.
- Ejecutar migraciones en produccion.
- Ejecutar seeders en produccion.
- Ejecutar `migrate:fresh` contra ninguna base que contenga informacion valiosa.
- Cambiar datos reales.
- Eliminar tablas, columnas o relaciones.
- Refactorizar flujos criticos de clientes, promesas, CNA, pagos o reportes sin pruebas.
- Cambiar la logica productiva de aprobaciones.
- Redisenar de golpe pantallas criticas.
- Eliminar Bootstrap en todo el sistema de una sola vez.

## Fases actualizadas

### Fase 1 - Entorno local seguro

Estado:

- [+] Proyecto corriendo en localhost.
- [+] Base de datos local importada.
- [+] `.env` apuntando a localhost.
- [+] Produccion no sera modificada.
- [+] Flujos actuales validados contra base local mediante smoke tests.
- [+] Errores locales documentados.

Objetivo:

- Usar la copia local como ambiente seguro de validacion.
- Confirmar que los flujos actuales funcionan localmente antes de cambios.

Flujos validados:

- Login/redireccion de invitado a `/login`.
- Dashboard.
- Administracion de usuarios.
- Busqueda de cliente.
- Vista de cliente.
- Autorizacion de promesas/CNA.
- Reportes de pagos, promesas y CNA.
- Importaciones principales: pagos, data, asignacion y CCD.

Alcance de la validacion:

- Se validaron rutas GET y renderizado contra datos locales.
- No se ejecutaron POST destructivos, cargas CSV reales, aprobaciones ni cambios masivos de datos.

### Fase 2 - Arquitectura MVC modular por dominios

Estado:

- [+] Arquitectura definida: MVC modular por dominios.
- [+] Se mantendra Laravel.
- [+] Se mantendra Blade.
- [+] Se usaran servicios.
- [+] Se usaran actions donde aporten claridad.
- [+] Se usaran ViewModels para vistas complejas.
- [+] Se usaran componentes Blade.
- [+] Rutas separadas por modulo manteniendo URLs, nombres y middlewares actuales.
- [+] Estructura backend por dominios creada.
- [+] Servicio piloto creado en Admin.
- [+] ViewModel piloto creado en Admin.
- [+] Policies/Gates definidos para modulos criticos.
- [+] Permisos actuales revisados.
- [+] Centralizacion basica de permisos creada.
- [ ] Controladores agrupados fisicamente por dominio.
- [ ] Actions reales creadas para operaciones puntuales.
- [!] Controladores criticos pendientes de mover.

Objetivo:

- Ordenar el backend por modulos funcionales.
- Separar rutas por modulo. Estado: completado en `routes/web/`.
- Reducir controladores grandes sin cambiar comportamiento.
- Preparar el refactor de controladores sin mover logica critica todavia.

Modulos base:

- Clientes.
- Promesas.
- CNA.
- Reportes.
- Integracion.
- Admin.
- Dashboard.

Resultado actual:

- `routes/web.php` funciona como agregador.
- `routes/web/dashboard.php` contiene panel y dashboard.
- `routes/web/clientes.php` contiene busqueda, ficha de cliente y acciones desde cliente.
- `routes/web/reportes.php` contiene reportes de CNA, pagos y promesas.
- `routes/web/promesas.php` contiene rutas de autorizacion de promesas y acuerdo.
- `routes/web/cna.php` contiene workflow y descargas CNA.
- `routes/web/integracion.php` contiene importaciones.
- `routes/web/admin.php` contiene administracion de usuarios.
- `app/Services/*`, `app/Actions/*`, `app/ViewModels/*`, `app/DTOs/*` y `app/Support/*` ya tienen estructura base por dominio.
- `app/Services/Admin/UserStatusService.php` separa una logica de administracion de bajo riesgo.
- `app/ViewModels/Admin/UserIndexViewModel.php` prepara datos de la pantalla piloto de usuarios.
- `app/Support/Authorization/Roles.php` centraliza reglas basicas de roles.
- `app/Policies/UserPolicy.php` centraliza permisos de usuarios.
- `AuthServiceProvider` define Gates iniciales para dashboard, administracion, reportes, integracion, promesas, CNA y pagos de cliente.
- `routes/web/admin.php`, `routes/web/integracion.php` y `routes/web/reportes.php` ya usan Gates.

### Fase 3 - Tailwind CSS v4 con Vite

Objetivo:

- Instalar y configurar Tailwind CSS v4 usando Vite.
- Usar `resources/css/app.css` como entrada principal de estilos.
- Usar `resources/js/app.js` como entrada principal de JavaScript.
- Verificar que `@vite` cargue correctamente los assets.

Checklist:

- [+] Revisar `package.json`.
- [+] Instalar `tailwindcss`.
- [+] Instalar `@tailwindcss/vite`.
- [+] Configurar `vite.config.js`.
- [+] Configurar `resources/css/app.css`.
- [+] Revisar `resources/js/app.js`.
- [+] Ejecutar `npm run dev`.
- [+] Ejecutar `npm run build`.
- [+] Confirmar `public/build/manifest.json`.
- [!] Vulnerabilidades moderadas de Vite/esbuild pendientes porque `npm audit fix --force` implica cambio mayor.

Resultado actual:

- `vite.config.js` usa `@tailwindcss/vite`.
- `resources/css/app.css` importa Tailwind CSS v4.
- `resources/js/app.js` importa modulos base de layout, administracion y dashboard.
- `@vite(['resources/css/app.css', 'resources/js/app.js'])` esta configurado en el layout principal.
- `npm run dev` levanto correctamente en `http://127.0.0.1:5178`.
- `npm run build` se ejecuto correctamente.
- `public/build/manifest.json` contiene entradas para CSS y JS.

### Fase 4 - Migracion progresiva de Bootstrap a Tailwind

Objetivo:

- Eliminar Bootstrap de forma controlada.
- Reemplazar componentes Bootstrap por componentes Blade con Tailwind.
- Evitar reescrituras masivas.

Orden recomendado:

1. Layout principal. Estado: completado, con Vite activo y sin Bootstrap global.
2. Sidebar/topbar. Estado: version Tailwind creada como componentes Blade.
3. Botones y badges. Estado: componentes base creados.
4. Formularios simples. Estado: componentes base creados.
5. Tablas simples. Estado: componente base creado.
6. Administracion de usuarios. Estado: pantalla piloto migrada a Tailwind.
7. Dashboard. Estado: segunda pantalla piloto migrada a Tailwind.
8. Reportes. Estado: migrado.
9. Clientes. Estado: migrado.
10. CNA y Promesas dentro de Cliente. Estado: migrado a componentes/JS Vite.

Regla actual:

- Las vistas migradas conservan `@section('tailwind_only', true)` como marca documental de migracion.
- El layout principal ya no carga Bootstrap CSS, Bootstrap Icons ni Bootstrap JS.
- Administracion, Dashboard, Reportes, Integraciones, Login, Panel, Autorizacion y Clientes usan Vite/Tailwind.
- Cualquier nueva pantalla debe usar Tailwind, componentes Blade y JS modular.

### Fase 5 - Baseline de migraciones y limpieza de roles

Estado:

- [+] Migraciones historicas incompletas revisadas.
- [+] Migraciones historicas incompletas eliminadas.
- [+] Migraciones V3 generadas desde `u480021566_kpinvest_bd.sql`.
- [+] Baseline V3 probado en base limpia desechable `kpinvest_v3_migrate_test`.
- [+] `php artisan migrate` ejecutado correctamente en base limpia.
- [+] `php artisan migrate:status` validado en base limpia.
- [+] Rollback de migraciones probado en base desechable.
- [+] Migraciones bajaron y volvieron a subir correctamente.
- [+] Roles finales definidos: `administrador`, `supervisor`, `asesor`, `soporte`.
- [+] Roles `sistemas` y `usuario` eliminados como roles validos de V3.
- [+] Selects, validaciones, Gates y Policies actualizados.
- [+] Tests y build pasaron despues de la limpieza.
- [!] En la base local principal las migraciones V3 aparecen pendientes porque el baseline no debe ejecutarse sobre tablas existentes.
- [!] Produccion no debe ejecutar el baseline sin una estrategia especifica.

Objetivo:

- Reconstruir migraciones Laravel desde la estructura real conocida.
- Dejar una base limpia para nuevos entornos V3.
- Normalizar roles y cerrar la lista de roles permitidos.
- Documentar diferencias entre SQL real historico y V3.

Regla:

- El baseline se prueba en base limpia o desechable.
- No se ejecuta sobre la base local importada ni sobre produccion sin estrategia de baseline.

### Fase 6 - Pruebas funcionales reales

Estado:

- [+] Fase actual.
- [+] `php artisan route:list --except-vendor`.
- [+] `php artisan migrate:status`.
- [+] Rollback/migrate validado en `kpinvest_v3_migrate_test`.
- [+] `php -l` en PHP nuevo/modificado.
- [+] `php artisan test`.
- [+] `npm run build`.
- [+] Tests de acceso por rol creados.
- [+] Tests de flujos V3 creados.

Objetivo:

- Validar localmente los flujos criticos antes de avanzar con refactors de Fase 7 o deploy.

Flujos validados por pruebas automatizadas:

- [+] Login/redireccion.
- [+] Login bloqueado para usuario inactivo.
- [+] Dashboard.
- [+] Administracion de usuarios.
- [+] Crear usuario.
- [+] Activar/desactivar usuario.
- [+] Cambiar contrasena.
- [+] Clientes: busqueda y vista.
- [+] Promesas: crear cancelacion.
- [+] Promesas: crear convenio.
- [+] Promesas: crear convenio con cuota balon.
- [+] Promesas: preaprobar, aprobar y rechazar.
- [+] Generar acuerdo de promesa con fallback DOCX local.
- [+] CNA: crear solicitud.
- [+] CNA: preaprobar, aprobar y rechazar.
- [+] CNA: descargar DOCX.
- [+] CNA: validar fallback PDF a DOCX cuando iLovePDF no tiene claves.
- [+] Reportes: pagos, promesas y CNA renderizan.
- [+] Exports de reportes: pagos, promesas y CNA.
- [+] Imports CSV: pagos, data maestra, asignaciones y CCD.

Pendientes de validacion manual:

- [!] Consola del navegador sin errores.
- [!] Modales en navegador real.
- [!] Filtros AJAX y paginacion AJAX en navegador real.
- [!] Vista desktop y responsive/mobile.
- [!] Prueba con archivos CSV reales de negocio mas grandes.

### Fase 7 - Arquitectura frontend V3 + migracion Tailwind

Objetivo:

- Ordenar frontend completo antes de seguir migrando pantallas criticas.
- Consolidar Blade + Blade Components + Tailwind CSS v4 + Vite + JS modular.
- Reducir dependencia de Bootstrap, `public/css`, `public/js`, CDN y scripts embebidos.

Estado:

- [+] Arquitectura frontend documentada en `docs/19_arquitectura_frontend_v3.md`.
- [+] Inventario legacy creado en `docs/20_inventario_frontend_legacy.md`.
- [+] Componentes `forms`, `tables`, `feedback` y `reportes` creados.
- [+] Estructura futura `resources/views/pages/*` creada.
- [+] Utilidades JS compartidas creadas en `resources/js/core`.
- [+] Modulos JS de reportes creados en `resources/js/modules/reportes`.
- [+] Reporte de pagos migrado a Tailwind/Vite.
- [+] Reporte de promesas migrado a Tailwind/Vite.
- [+] Reporte CNA migrado a Tailwind/Vite.
- [+] Assets legacy sin referencias eliminados de `public/css` y `public/js`.
- [+] `welcome.blade.php`, `error_log` y `estructura_generada_v3.sql` eliminados del repositorio.
- [+] Integraciones migradas a Tailwind/Vite.
- [+] JS de Integraciones movido a `resources/js/modules/integracion/imports.js`.
- [+] Login migrado a Tailwind/Vite.
- [+] JS de Login movido a `resources/js/modules/auth/login.js`.
- [+] `public/css/auth/login.css` eliminado.
- [+] `public/js/auth/login.js` eliminado.
- [+] No quedan assets activos en `public/css` ni `public/js`.
- [+] Panel principal `/` migrado a Tailwind/Vite.
- [+] Chart.js del Panel principal movido a Vite.
- [+] JS del Panel principal movido a `resources/js/modules/panel/resumen.js`.
- [+] Panel principal ya no carga Bootstrap.
- [+] Autorizacion migrada a Tailwind/Vite.
- [+] JS de Autorizacion movido a `resources/js/modules/autorizacion/index.js`.
- [+] Modales Bootstrap de Autorizacion reemplazados por modales Tailwind.
- [+] Autorizacion ya no carga Bootstrap.
- [+] Clientes migrado a Tailwind/Vite.
- [+] JS de Cliente movido a `resources/js/modules/clientes/show.js`.
- [+] Promesas separadas en componentes y JS Vite.
- [+] CNA separado en componentes y JS Vite.
- [+] Bootstrap eliminado de `clientes/show.blade.php`.
- [+] Bootstrap eliminado globalmente de `resources/views/layouts/app.blade.php`.
- [+] Cierre frontend ejecutado: sin Bootstrap activo, sin assets activos en `public/css` o `public/js`.
- [+] `.gitkeep` innecesarios eliminados en carpetas con archivos reales.
- [+] Componentes de dominio no usados eliminados.
- [+] Revision frontend post-diseno manual documentada en `docs/21_revision_frontend_post_diseno_manual.md`.
- [+] Logica de toast retirada de Blade y centralizada en `resources/js/core/toast.js`.
- [+] `window.alert` de Promesas reemplazado por `notify()` modular.
- [+] Global `window.toggleRail` retirado del sidebar.
- [+] Plantillas Bootstrap de paginacion no usadas eliminadas.
- [!] Validacion manual visual pendiente para Cliente, Promesas y CNA.

Reglas:

- No agregar CSS nuevo en `public/css`.
- No agregar JS nuevo en `public/js`.
- No agregar scripts grandes embebidos en Blade.
- Toda pantalla migrada usa `@section('tailwind_only', true)` como marca documental de migracion.
- Toda logica JS nueva vive en `resources/js/modules`.

### Fase 8 - Refactor backend por modulos criticos

Objetivo:

- Reducir controladores grandes sin cambiar comportamiento funcional.
- Extraer Services, Actions, ViewModels y Policies por modulo.
- Mantener rutas, nombres de rutas, vistas, formularios y permisos funcionales.
- Ampliar tests por modulo antes de tocar flujos mas sensibles.

Estado:

- [+] Fase 8.1 iniciada con modulo Clientes.
- [+] `ClienteController` delegado a `ClienteProfileService` y `DeleteClientePaymentAction`.
- [+] `ClienteLookupController` delegado a `ClienteLookupService`.
- [+] Servicios creados: `ClienteProfileService`, `ClienteAccountService`, `ClientePaymentService`, `ClienteLookupService`.
- [+] ViewModel creado: `ClienteShowViewModel`.
- [+] Action creada: `DeleteClientePaymentAction`.
- [+] Policy creada: `ClientePolicy`.
- [+] Tests creados: `V3ClienteModuleTest`.
- [+] Fase 8.2 iniciada con modulo Promesas.
- [+] Servicios creados: `PromesaCreationService`, `PromesaScheduleService`, `PromesaWorkflowService`, `PromesaDocumentService`, `PromesaQueryService`.
- [+] Actions creadas: `CreatePromesaAction`, `PreapprovePromesaAction`, `ApprovePromesaAction`, `RejectPromesaAction`, `GeneratePromesaAgreementAction`.
- [+] Policy creada: `PromesaPolicy`.
- [+] Tests creados: `V3PromesaModuleTest`.
- [+] Fase 8.3 iniciada con modulo CNA.
- [+] Servicios creados: `CnaCreationService`, `CnaNumberingService`, `CnaWorkflowService`, `CnaDocumentService`, `CnaQueryService`.
- [+] Actions creadas: `CreateCnaAction`, `PreapproveCnaAction`, `ApproveCnaAction`, `RejectCnaAction`, `DownloadCnaDocumentAction`.
- [+] Policy creada: `CnaPolicy`.
- [+] Tests creados: `V3CnaModuleTest`.
- [!] Autorizacion puede separarse despues en ViewModel/modulo propio si se quiere reducir mas la pantalla de bandeja.

Documento:

- `docs/22_refactor_backend_clientes_v3.md`.
- `docs/23_refactor_backend_promesas_v3.md`.
- `docs/24_refactor_backend_cna_v3.md`.

### Fase 9 - Deploy controlado

Objetivo:

- Preparar despliegue solo cuando V3 este probada localmente y sobre un dump reciente.

Antes de produccion:

- Descargar dump productivo actualizado.
- Importarlo en local o staging.
- Ejecutar migraciones sobre esa copia reciente.
- Ejecutar pruebas funcionales.
- Ejecutar build de Vite.
- Revisar checklist de deploy.
- Definir rollback.

## Orden recomendado de implementacion

1. Validar sistema actual sobre base local.
2. Configurar Tailwind CSS v4 con Vite.
3. Crear componentes Blade base.
4. Migrar layout principal sin cambiar flujos.
5. Migrar pantalla piloto de bajo riesgo.
6. Separar rutas por modulo.
7. Crear baseline de migraciones desde SQL real.
8. Limpiar roles historicos.
9. Crear pruebas funcionales reales por rol y flujo.
10. Ejecutar validaciones tecnicas completas.
11. Ordenar arquitectura frontend V3.
12. Migrar Reportes.
13. Migrar Integraciones.
14. Revisar frontend post-diseno manual y normalizar scripts/componentes.
15. Refactor backend de Clientes con Services/Actions/ViewModel/Policy.
16. Extraer servicios/actions de Promesas con cobertura.
17. Extraer servicios/actions de CNA con cobertura.
18. Preparar deploy controlado.

## Pantalla piloto recomendada

Primera candidata:

- Administracion de usuarios.

Motivo:

- Permite probar layout, tabla, botones, formularios y modales.
- Es menos riesgosa que clientes, promesas o CNA.

Pantallas que deben esperar:

- Clientes.
- Autorizacion.
- CNA.
- Promesas.
