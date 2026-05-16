# 14 - Migracion Bootstrap a Tailwind

## Objetivo

Eliminar Bootstrap progresivamente y reemplazarlo por Tailwind CSS v4, componentes Blade y JavaScript modular con Vite.

## Estado Fase 4

- [+] Tailwind CSS v4 configurado.
- [+] Vite configurado.
- [+] Layout principal usa `@vite`.
- [+] Sidebar/topbar migrados a Tailwind.
- [+] Administracion de usuarios migrada a Tailwind.
- [+] Dashboard migrado a Tailwind.
- [+] Reportes migrados a Tailwind/Vite en Fase 7.
- [+] Componentes Blade base creados.
- [+] Arquitectura frontend V3 creada.
- [+] Modal reutilizable creado.
- [+] JS modular creado para Administracion y Dashboard.
- [+] JS modular creado para Reportes.
- [+] Bootstrap legacy eliminado de vistas operativas.
- [+] Bootstrap eliminado globalmente.

## Estrategia actual

El layout principal ya no carga Bootstrap. Vite es el unico canal de CSS/JS de la aplicacion.

Las vistas migradas declaran:

```blade
@section('tailwind_only', true)
```

Con eso:

- No se carga Bootstrap CSS.
- No se carga Bootstrap JS.
- La vista depende de Tailwind/Vite.

## Pantallas migradas

### Administracion de usuarios

Archivos:

- `resources/views/placeholders/administracion.blade.php`.
- `resources/js/modules/admin/users.js`.
- `app/Services/Admin/UserStatusService.php`.
- `app/ViewModels/Admin/UserIndexViewModel.php`.

Estado:

- [+] Filtros migrados.
- [+] Tabla migrada.
- [+] Badges migrados.
- [+] Botones migrados.
- [+] Modales migrados a `x-ui.modal`.
- [+] JS movido a Vite.
- [!] Acciones POST requieren validacion manual completa.

### Dashboard

Archivos:

- `resources/views/dashboard/index.blade.php`.
- `resources/js/modules/dashboard/stats.js`.

Estado:

- [+] Filtros migrados.
- [+] KPIs migrados.
- [+] Tablas migradas.
- [+] Estados vacios migrados.
- [+] Grafico movido a Vite con `chart.js`.
- [+] CSS legacy de dashboard ya no se carga en esa vista.
- [+] JS legacy de dashboard ya no se carga en esa vista.
- [!] Requiere revision visual manual del grafico y responsive.

### Reportes

Archivos:

- `resources/views/reportes/pagos.blade.php`.
- `resources/views/reportes/pagos_table.blade.php`.
- `resources/views/reportes/pdp.blade.php`.
- `resources/views/reportes/pdp_table.blade.php`.
- `resources/views/reportes/cna.blade.php`.
- `resources/views/reportes/cna_table.blade.php`.
- `resources/js/modules/reportes/filters.js`.
- `resources/js/modules/reportes/pagos.js`.
- `resources/js/modules/reportes/promesas.js`.
- `resources/js/modules/reportes/cna.js`.

Estado:

- [+] Reporte de pagos migrado a Tailwind.
- [+] Reporte de promesas migrado a Tailwind.
- [+] Reporte CNA migrado a Tailwind.
- [+] Filtros multiselect migrados a componente `x-reportes.multiselect`.
- [+] Tablas migradas a componentes `x-tables.*`.
- [+] JS movido desde `public/js/reportes` hacia Vite.
- [+] CSS especifico de reportes dejo de cargarse desde `public/css/reportes`.
- [+] Reportes usan `@section('tailwind_only', true)`.
- [+] Archivos legacy de reportes en `public/` eliminados tras confirmar que no tenian referencias activas.

## Componentes disponibles

Componentes UI:

- `x-ui.button`.
- `x-ui.card`.
- `x-ui.badge`.
- `x-ui.alert`.
- `x-ui.input`.
- `x-ui.select`.
- `x-ui.table`.
- `x-ui.textarea`.
- `x-ui.date`.
- `x-ui.modal`.
- `x-ui.dropdown`.
- `x-ui.empty-state`.
- `x-ui.confirm-dialog`.

Componentes V3 por categoria:

- `x-forms.field`.
- `x-forms.label`.
- `x-forms.error`.
- `x-forms.input`.
- `x-forms.select`.
- `x-forms.textarea`.
- `x-forms.date`.
- `x-tables.table`.
- `x-tables.th`.
- `x-tables.td`.
- `x-tables.empty-row`.
- `x-tables.pagination`.
- `x-feedback.alert`.
- `x-feedback.empty-state`.
- `x-feedback.confirm-dialog`.
- `x-feedback.loading`.
- `x-layout.page-header`.
- `x-layout.page-shell`.
- `x-reportes.multiselect`.

Componentes layout:

- `x-layout.sidebar`.
- `x-layout.topbar`.

## JS modular

Entradas actuales:

```js
import './modules/layout/sidebar';
import './modules/admin/users';
import './modules/dashboard/stats';
import './modules/reportes/pagos';
import './modules/reportes/promesas';
import './modules/reportes/cna';
import './modules/integracion/imports';
import './modules/auth/login';
import './modules/panel/resumen';
import './modules/autorizacion/index';
```

Regla:

- `resources/js/app.js` solo importa modulos.
- La logica de pantalla debe vivir en `resources/js/modules/{modulo}`.
- No agregar JS nuevo en `public/js` salvo excepcion temporal documentada.

## Orden recomendado pendiente

1. Reporte de pagos. Estado: migrado.
2. Reporte de promesas. Estado: migrado.
3. Reporte CNA. Estado: migrado.
4. Integraciones. Estado: migrado.
5. Login. Estado: migrado.
6. Panel principal `/`. Estado: migrado.
7. Autorizacion. Estado: migrado.
8. Clientes. Estado: migrado.
9. CNA dentro de Cliente. Estado: migrado.
10. Promesas dentro de Cliente. Estado: migrado.

Clientes, Autorizacion, CNA y Promesas se migraron al final porque concentran flujo operativo critico.

## Riesgos

- [+] No quedan vistas operativas con Bootstrap JS detectado.
- [+] Bootstrap Icons fue retirado del layout global.
- [+] Clientes ya no conserva Bootstrap JS para modales/workflow.
- [!] El salto de dependencias por `npm audit fix --force` podria romper Vite.

## Limpieza ejecutada

Archivos eliminados:

- `public/css/reportes/pagos.css`.
- `public/css/reportes/promesas.css`.
- `public/css/reportes/cna.css`.
- `public/js/reportes/pagos.js`.
- `public/js/reportes/promesas.js`.
- `public/js/reportes/cna.js`.
- `public/css/dashboard-stats.css`.
- `public/js/dashboard-stats.js`.
- `public/css/admin/admin.css`.
- `public/js/admin/admin.js`.
- `public/css/app.css`.
- `public/js/app.js`.
- `public/js/bootstrap.js`.
- `public/css/layout/app.css`.
- `public/js/layout/app.js`.
- `public/css/auth/login.css`.
- `public/js/auth/login.js`.

Estado actual:

- [+] No quedan archivos activos en `public/css`.
- [+] No quedan archivos activos en `public/js`.

## Fase 7.2 - Integraciones

Archivos migrados:

- `resources/views/placeholders/integracion-pagos.blade.php`.
- `resources/views/placeholders/integracion-data.blade.php`.
- `resources/views/placeholders/integracion-asignacion.blade.php`.
- `resources/views/placeholders/integracion-ccd.blade.php`.
- `resources/js/modules/integracion/imports.js`.

Estado:

- [+] Vistas sin Bootstrap en layout.
- [+] Formularios con Tailwind y componentes Blade.
- [+] Alertas con `x-feedback.alert`.
- [+] Precheck CSV movido desde Blade a Vite.

## Fase 7.3 - Login

Archivos migrados:

- `resources/views/auth/login.blade.php`.
- `resources/js/modules/auth/login.js`.

Estado:

- [+] Login sin Bootstrap CSS/JS.
- [+] Login sin Bootstrap Icons.
- [+] Login cargado por Vite.
- [+] Assets antiguos de Login eliminados desde `public`.

## Fase 7.4 - Panel principal

Archivos migrados:

- `resources/views/panel/resumen.blade.php`.
- `resources/js/modules/panel/resumen.js`.

Estado:

- [+] Panel principal sin Bootstrap en layout.
- [+] CSS embebido retirado.
- [+] JS embebido movido a Vite.
- [+] Buscador rapido y sugerencias movidos a modulo JS.
- [+] Chart.js por CDN retirado.
- [+] Chart.js usado desde npm/Vite.

## Fase 7.5 - Autorizacion

Archivos migrados:

- `resources/views/autorizacion/index.blade.php`.
- `resources/js/modules/autorizacion/index.js`.
- `resources/views/components/autorizacion/decision-modal.blade.php`.
- `resources/views/components/autorizacion/detail-field.blade.php`.

Estado:

- [+] Autorizacion sin Bootstrap en layout.
- [+] CSS embebido retirado.
- [+] JS embebido movido a Vite.
- [+] Modales Bootstrap reemplazados por modales Tailwind.
- [+] Tabla de promesas y tabla CNA migradas a componentes Tailwind.
- [+] Paginacion CNA migrada fuera de Bootstrap.

## Fase 7.6 - Clientes

Archivos migrados:

- `resources/views/clientes/show.blade.php`.
- `resources/js/modules/clientes/show.js`.
- `resources/js/modules/promesas/form.js`.
- `resources/js/modules/promesas/schedule.js`.
- `resources/js/modules/cna/form.js`.
- `resources/views/components/clientes/*`.
- `resources/views/components/promesas/*`.
- `resources/views/components/cna/*`.

Estado:

- [+] Clientes sin Bootstrap en layout.
- [+] CSS embebido retirado.
- [+] JS embebido movido a Vite.
- [+] Modales Bootstrap reemplazados por `x-ui.modal`.
- [+] Dropdown/collapse/tooltips Bootstrap reemplazados por `details`, `title` y JS modular.
- [+] Formulario de Promesas separado en componentes y JS Vite.
- [+] Formulario CNA separado en componentes y JS Vite.
- [+] Bootstrap global retirado de `resources/views/layouts/app.blade.php`.

## Cierre frontend

Estado:

- [+] Bootstrap CSS/JS/Icon CDN eliminado del layout global.
- [+] No quedan assets activos en `public/css` ni `public/js`.
- [+] No quedan clases/atributos Bootstrap en vistas o JS activos.
- [+] `resources/js/app.js` solo importa modulos reales.
- [+] `.gitkeep` innecesarios retirados.
- [+] Componentes placeholder no usados eliminados.
- [!] Quedan menciones a Bootstrap en documentacion historica y archivos propios del framework, no como dependencia frontend activa.

## Revision post-diseno manual

Estado:

- [+] Se revisaron los cambios manuales del commit `b49c286 Refactor UI components and layout`.
- [+] Se mantuvo el diseno visual nuevo de componentes, Cliente, Autorizacion, Panel y Reportes.
- [+] `x-ui.alert` ya no contiene scripts ni eventos inline.
- [+] Toast/alertas se manejan desde `resources/js/core/toast.js`.
- [+] Promesas usa `notify()` para avisos de UI.
- [+] Sidebar ya no expone `window.toggleRail`.
- [+] Se eliminaron plantillas Bootstrap de paginacion no usadas publicadas en `resources/views/vendor/pagination`.
- [!] Las menciones a Bootstrap restantes deben ser documentales, historicas o propias del framework.

## Validaciones realizadas

- [+] `npm run dev`.
- [+] `npm run build`.
- [+] `public/build/manifest.json`.
- [+] `php artisan view:cache`.
- [+] `php artisan test`.
- [+] Smoke tests de Dashboard y Administracion.

## Regla final

Bootstrap ya fue eliminado globalmente. Cualquier nueva pantalla debe usar Tailwind, Blade Components y assets por Vite.
