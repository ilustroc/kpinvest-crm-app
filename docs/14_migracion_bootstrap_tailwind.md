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
- [!] Bootstrap legacy se mantiene condicional para vistas no migradas.
- [ ] Bootstrap eliminado globalmente.

## Estrategia actual

El layout principal conserva Bootstrap solo para vistas legacy.

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
- [!] Archivos legacy de reportes en `public/` quedan pendientes de eliminacion tras validacion visual.

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
```

Regla:

- `resources/js/app.js` solo importa modulos.
- La logica de pantalla debe vivir en `resources/js/modules/{modulo}`.
- No agregar JS nuevo en `public/js` salvo excepcion temporal documentada.

## Orden recomendado pendiente

1. Reporte de pagos. Estado: migrado.
2. Reporte de promesas. Estado: migrado.
3. Reporte CNA. Estado: migrado.
4. Integraciones.
5. Panel principal `/`.
6. Clientes.
7. Autorizacion.
8. CNA.
9. Promesas.

Clientes, Autorizacion, CNA y Promesas deben quedar al final porque concentran flujo operativo critico.

## Riesgos

- [!] Algunas vistas legacy dependen de Bootstrap JS para modales, dropdowns o spinners.
- [!] Bootstrap Icons sigue siendo usado en vistas legacy.
- [!] Hay CSS antiguo en `public/css` que todavia puede estar activo en vistas no migradas.
- [!] Hay JS antiguo en `public/js` que todavia puede estar activo en vistas no migradas.
- [!] El salto de dependencias por `npm audit fix --force` podria romper Vite.

## Validaciones realizadas

- [+] `npm run dev`.
- [+] `npm run build`.
- [+] `public/build/manifest.json`.
- [+] `php artisan view:cache`.
- [+] `php artisan test`.
- [+] Smoke tests de Dashboard y Administracion.

## Regla final

No eliminar Bootstrap globalmente hasta que las vistas legacy criticas hayan sido migradas o verificadas con reemplazos Tailwind/JS equivalentes.
