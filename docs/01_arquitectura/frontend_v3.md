# Frontend V3

## Decision

El frontend V3 usa:

- Blade para vistas.
- Blade Components para UI reutilizable.
- Tailwind CSS v4 para estilos.
- Vite para compilar CSS y JS.
- JavaScript modular por dominio o pantalla.

## Estado final

- [+] Bootstrap CSS eliminado.
- [+] Bootstrap JS eliminado.
- [+] Bootstrap Icons eliminado.
- [+] Layout global usa solo `@vite(['resources/css/app.css', 'resources/js/app.js'])`.
- [+] `public/css` y `public/js` sin uso activo.
- [+] Administracion, Dashboard, Reportes, Integraciones, Login, Panel, Autorizacion y Cliente migrados a Tailwind/Vite.
- [+] Promesas y CNA quedaron separados en componentes/JS dentro de Cliente.

## Estructura

```text
resources/
  css/app.css
  js/app.js
  js/core/
  js/modules/
  views/layouts/
  views/components/
```

## Componentes

- `components/ui`: botones, cards, badges, modales, dropdowns.
- `components/forms`: inputs, selects, textarea, date, label, error.
- `components/tables`: tabla, th, td, paginacion, empty-row.
- `components/feedback`: alert, empty-state, loading, confirm-dialog.
- `components/layout`: sidebar, topbar, page-shell, page-header.
- Componentes de dominio: `clientes`, `promesas`, `cna`, `autorizacion`, `reportes`.

## JavaScript

- `resources/js/core`: utilidades compartidas como DOM, HTTP, modal, dropdown, forms y toast.
- `resources/js/modules`: modulos por pantalla o dominio.
- Cada modulo debe inicializarse con selector seguro, normalmente `data-module`.
- No se deben agregar scripts grandes dentro de Blade.

## CSS

`resources/css/app.css` contiene Tailwind, tokens globales y utilidades compartidas. No debe convertirse en un archivo de estilos por pantalla.

## Reglas

- No agregar CSS nuevo en `public/css`.
- No agregar JS nuevo en `public/js`.
- No agregar Bootstrap en vistas nuevas.
- Todo asset nuevo pasa por Vite.
- Todo comportamiento repetible debe ir a `resources/js/core`.
- Todo visual repetible debe ir a Blade Components.

## Riesgos pendientes

- Validacion visual manual en navegador real.
- Confirmar desktop/mobile con usuarios clave.
- Vulnerabilidades moderadas de Vite/esbuild se mantienen pendientes si requieren `npm audit fix --force`.
