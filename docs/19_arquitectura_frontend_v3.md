# 19 - Arquitectura Frontend V3

## Objetivo

Definir una arquitectura frontend estable para el CRM usando Blade, Blade Components, Tailwind CSS v4, Vite y JavaScript modular.

La meta no es solo eliminar Bootstrap. La meta es ordenar donde vive cada cosa.

## Problema actual del frontend

El frontend historico esta mezclado entre varias capas:

- Blade con clases Bootstrap.
- Bootstrap y Bootstrap Icons cargados por CDN.
- CSS embebido dentro de vistas grandes.
- CSS historico en `public/css`.
- JS historico en `public/js`.
- Scripts grandes embebidos en Blade.
- Chart.js historicamente cargado por CDN en vistas legacy; Dashboard y Panel ya lo usan desde Vite.
- Vite configurado, pero usado solo en pantallas migradas.
- Componentes visuales repetidos.
- Reportes, Clientes, Autorizacion e Integraciones con estructuras diferentes.
- Falta de criterio unico para ubicar vistas, componentes y scripts.

Riesgo:

- Cada pantalla puede romperse de una forma distinta.
- Es dificil eliminar Bootstrap globalmente.
- Los cambios visuales se vuelven lentos y peligrosos.

## Arquitectura frontend elegida

V3 usara:

- Blade para vistas.
- Blade Components para UI reutilizable.
- Tailwind CSS v4 para estilos.
- Vite para compilar CSS y JavaScript.
- JavaScript modular por dominio o pantalla.
- `public/css` y `public/js` solo como legacy temporal.
- CDN externos eliminados progresivamente.

## Reglas frontend V3

- No agregar CSS nuevo en `public/css`.
- No agregar JS nuevo en `public/js`.
- No agregar scripts grandes embebidos en Blade.
- No agregar Bootstrap en vistas nuevas.
- Toda pantalla migrada debe usar `@section('tailwind_only', true)`.
- Toda logica JS nueva debe vivir en `resources/js/modules`.
- JS compartido debe vivir en `resources/js/core`.
- Todo componente visual repetible debe convertirse en Blade Component.
- Todo asset nuevo debe cargarse por Vite.
- `resources/css/app.css` es la entrada principal de estilos.
- CSS especifico por pantalla debe evitarse salvo excepcion documentada.

## Estructura recomendada

```text
resources/views/layouts/
resources/views/components/layout/
resources/views/components/ui/
resources/views/components/forms/
resources/views/components/tables/
resources/views/components/feedback/
resources/views/components/reportes/
resources/views/components/clientes/
resources/views/components/cna/
resources/views/components/promesas/

resources/views/pages/dashboard/
resources/views/pages/admin/
resources/views/pages/reportes/
resources/views/pages/integracion/
resources/views/pages/clientes/
resources/views/pages/autorizacion/
resources/views/pages/cna/
resources/views/pages/promesas/

resources/js/app.js
resources/js/core/
resources/js/modules/dashboard/
resources/js/modules/admin/
resources/js/modules/reportes/
resources/js/modules/integracion/
resources/js/modules/clientes/
resources/js/modules/autorizacion/
resources/js/modules/cna/
resources/js/modules/promesas/

resources/css/app.css
```

## Estado implementado

- [+] `resources/views/components/forms/` creado.
- [+] `resources/views/components/tables/` creado.
- [+] `resources/views/components/feedback/` creado.
- [+] `resources/views/components/reportes/` creado.
- [+] `resources/views/components/clientes/`, `cna/` y `promesas/` creados como estructura inicial.
- [+] `resources/views/pages/*` creado como estructura futura.
- [+] `resources/js/core/` creado.
- [+] `resources/js/modules/reportes/` creado.
- [+] `resources/js/modules/integracion/` creado y usado.
- [+] `resources/js/modules/auth/` creado y usado.
- [+] Carpetas JS futuras creadas para clientes, autorizacion, CNA y promesas.
- [+] Assets legacy no referenciados eliminados de `public/css` y `public/js`.
- [+] Login migrado a Vite; no quedan assets activos en `public/css` ni `public/js`.

## Convenciones de componentes

- Componentes genericos: `resources/views/components/ui`.
- Formularios: `resources/views/components/forms`.
- Tablas: `resources/views/components/tables`.
- Feedback/estado: `resources/views/components/feedback`.
- Componentes propios de reportes: `resources/views/components/reportes`.
- Componentes propios de clientes: `resources/views/components/clientes`.
- Componentes propios de CNA: `resources/views/components/cna`.
- Componentes propios de promesas: `resources/views/components/promesas`.

Compatibilidad:

- Los componentes `x-ui.*` existentes se mantienen.
- Las nuevas pantallas deben preferir `x-forms.*`, `x-tables.*`, `x-feedback.*` y componentes por dominio.

## Convenciones JavaScript

- `resources/js/app.js` solo importa core y modulos.
- `resources/js/core` contiene utilidades compartidas.
- `resources/js/modules/{dominio}` contiene logica especifica.
- Cada modulo debe inicializarse solo si existe un selector raiz.
- El selector raiz recomendado es `data-module`.
- Usar `data-*` para comportamiento, no clases visuales.
- No depender de Bootstrap JS en pantallas migradas.

Patron:

```js
const root = document.querySelector('[data-module="reportes-pagos"]');

if (root) {
    // inicializar modulo
}
```

## Core JS creado

- `resources/js/core/dom.js`.
- `resources/js/core/http.js`.
- `resources/js/core/forms.js`.
- `resources/js/core/modal.js`.
- `resources/js/core/dropdown.js`.
- `resources/js/core/toast.js`.

Responsabilidad de `toast.js`:

- Inicializar toasts renderizados por Blade con `data-toast`.
- Manejar cierre manual con `data-toast-close`.
- Manejar cierre automatico por `data-toast-timeout`.
- Exponer `notify(message, variant)` para modulos JS sin usar `window.alert`.

## Modulos JS creados en Fase 7

- `resources/js/modules/reportes/filters.js`.
- `resources/js/modules/reportes/pagos.js`.
- `resources/js/modules/reportes/promesas.js`.
- `resources/js/modules/reportes/cna.js`.
- `resources/js/modules/integracion/imports.js`.
- `resources/js/modules/auth/login.js`.
- `resources/js/modules/panel/resumen.js`.
- `resources/js/modules/autorizacion/index.js`.

## CSS

`resources/css/app.css` se mantiene como entrada principal:

```css
@import "tailwindcss";
```

Tambien contiene tokens base del CRM y utilidades compartidas minimas.

Regla:

- No meter CSS especifico de pantalla en `app.css` salvo utilidad realmente transversal.

## Pantallas migradas bajo esta arquitectura

- Administracion de usuarios.
- Dashboard.
- Reporte de pagos.
- Reporte de promesas.
- Reporte CNA.
- Integracion de pagos.
- Integracion de data maestra.
- Integracion de asignaciones.
- Integracion de CCD.
- Login.
- Panel principal `/`.
- Autorizacion.
- Clientes.
- Promesas dentro de Cliente.
- CNA dentro de Cliente.

## Legacy temporal

Bootstrap ya no se carga desde `resources/views/layouts/app.blade.php`.

Vistas legacy principales:

- No quedan vistas operativas con dependencia Bootstrap detectada.

Layout final:

- `resources/views/layouts/app.blade.php` carga solamente `@vite(['resources/css/app.css', 'resources/js/app.js'])` para assets de aplicacion.
- No existe condicion `tailwind_only` para cargar o retirar Bootstrap.

Entrada JS final:

- `resources/js/app.js` importa solo modulos reales.
- Cada modulo se inicializa por selector/data-module propio o por eventos seguros.

Archivos legacy activos en `public`:

- Ninguno.

Archivos legacy eliminados:

- Assets antiguos de Reportes.
- Assets antiguos de Dashboard.
- Assets antiguos de Administracion.
- Assets antiguos de Login.
- Assets genericos no referenciados en `public/css` y `public/js`.

Componentes de dominio creados:

- `resources/views/components/autorizacion/decision-modal.blade.php`.
- `resources/views/components/autorizacion/detail-field.blade.php`.
- `resources/views/components/clientes/*`.
- `resources/views/components/promesas/*`.
- `resources/views/components/cna/*`.

## Decision

Fase 7 establece la arquitectura frontend V3 y migra Reportes como primer modulo completo bajo esa arquitectura.

Bootstrap fue eliminado al final de la migracion de Clientes. Vite queda como canal unico de assets frontend.

## Revision post-diseno manual

Despues de los ultimos ajustes visuales manuales, se creo `docs/21_revision_frontend_post_diseno_manual.md`.

Resultado:

- [+] El diseno visual manual se mantuvo.
- [+] El comportamiento de UI quedo fuera de Blade cuando era logica reutilizable.
- [+] Los componentes siguen separados por responsabilidad.
- [+] `resources/js/app.js` importa solo modulos reales.
- [+] `resources/css/app.css` conserva solo estilos globales/tokens.
- [+] No se reintrodujeron assets en `public/css` o `public/js`.
