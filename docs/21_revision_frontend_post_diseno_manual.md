# 21 - Revision frontend post-diseno manual

## Objetivo

Revisar el frontend completo despues de los cambios manuales de diseno y confirmar que sigue alineado con la arquitectura V3: Blade, Blade Components, Tailwind CSS v4, Vite y JavaScript modular.

Esta revision no repite la limpieza general de Bootstrap. Solo corrige desviaciones nuevas o detectadas despues del ajuste visual manual.

## Comandos de revision usados

- `git status --short --untracked-files=all`.
- `git log --oneline -5`.
- `git diff`.
- `git diff HEAD~1..HEAD`.
- `rg "<script|@push\\('scripts'\\)|@section\\('scripts'\\)|onclick=|onchange=|oninput=|addEventListener|document.querySelector|window\\."`.
- `rg "bootstrap|bootstrap-icons|cdn.jsdelivr|data-bs|form-control|table-responsive|modal fade"`.
- `rg "x-ui.|x-forms.|x-tables.|x-feedback.|x-clientes.|x-promesas.|x-cna."`.

## Archivos frontend revisados

Se revisaron las areas frontend principales:

- `resources/views/layouts/`.
- `resources/views/components/`.
- `resources/views/clientes/`.
- `resources/views/promesas/`.
- `resources/views/cna/`.
- `resources/views/autorizacion/`.
- `resources/views/reportes/`.
- `resources/views/placeholders/`.
- `resources/views/panel/`.
- `resources/views/auth/`.
- `resources/views/vendor/pagination/`.
- `resources/js/app.js`.
- `resources/js/core/`.
- `resources/js/modules/`.
- `resources/css/app.css`.
- `vite.config.js`.
- `package.json`.

## Cambios manuales detectados

El ultimo commit revisado fue:

- `b49c286 Refactor UI components and layout`.

Ese commit modifico principalmente:

- Componentes visuales genericos como `x-ui.alert`, `x-ui.button`, `x-ui.modal` y `x-ui.select`.
- Sidebar y layout visual.
- Componentes de Cliente, Promesas y CNA.
- Vistas grandes migradas como Cliente, Autorizacion, Panel y Reportes.
- Plantillas de paginacion publicadas en `resources/views/vendor/pagination`.

## Cambios mantenidos

Se mantuvo el diseno visual manual cuando no rompia la arquitectura V3:

- Toast flotante con logo de KP Invest.
- Estilo visual nuevo de botones, modales y selects.
- Ajustes visuales de Cliente, Autorizacion, Panel y Reportes.
- JSON embebido en `dashboard/index.blade.php` y `panel/resumen.blade.php`, porque funciona como payload de datos y no como script de comportamiento.
- Estilos inline en `resources/views/mail/*.blade.php`, porque las plantillas de correo suelen requerir CSS inline.
- Metas `@push('head')` en Reportes para exponer URLs al JS modular.

## Cambios normalizados

Se normalizaron desviaciones detectadas:

- `resources/views/components/ui/alert.blade.php` dejo de incluir `onclick`, `<script>` y `window.closeToast`.
- La logica de cierre/timeout de toast paso a `resources/js/core/toast.js`.
- `resources/js/app.js` importa ahora `resources/js/core/toast.js`.
- `resources/js/modules/promesas/form.js` dejo de usar `window.alert` y usa `notify()` desde core.
- `resources/js/modules/layout/sidebar.js` dejo de exponer `window.toggleRail`.
- Se eliminaron plantillas Bootstrap de paginacion no usadas:
  - `resources/views/vendor/pagination/bootstrap-4.blade.php`.
  - `resources/views/vendor/pagination/bootstrap-5.blade.php`.
  - `resources/views/vendor/pagination/simple-bootstrap-4.blade.php`.
  - `resources/views/vendor/pagination/simple-bootstrap-5.blade.php`.

## Scripts movidos o ajustados

- Toast/alertas globales: `resources/js/core/toast.js`.
- Validacion visual de cronograma en Promesas: `resources/js/modules/promesas/form.js`, usando `notify()`.
- Sidebar: `resources/js/modules/layout/sidebar.js`, solo con listeners por `data-toggle-rail`.

No quedan scripts grandes embebidos en vistas o componentes. Las etiquetas `<script type="application/json">` restantes se consideran datos para modulos Vite.

## Componentes revisados

Se revisaron componentes en:

- `resources/views/components/ui`.
- `resources/views/components/forms`.
- `resources/views/components/tables`.
- `resources/views/components/feedback`.
- `resources/views/components/layout`.
- `resources/views/components/clientes`.
- `resources/views/components/promesas`.
- `resources/views/components/cna`.
- `resources/views/components/autorizacion`.
- `resources/views/components/reportes`.

Decision:

- `x-ui.*` se mantiene por compatibilidad y por componentes base de UI.
- `x-forms.*`, `x-tables.*` y `x-feedback.*` se mantienen como componentes V3 preferidos para nuevas vistas.
- No se movieron componentes de dominio hacia `ui`, ni componentes genericos hacia dominios.
- No se eliminaron componentes base no usados si forman parte de la arquitectura frontend V3.

## CSS revisado

`resources/css/app.css` se mantiene como entrada global de Tailwind:

- Conserva `@import "tailwindcss";`.
- Conserva tokens globales del CRM.
- No se agrego CSS especifico de pantalla.
- No se reintrodujeron archivos en `public/css`.

## JS revisado

`resources/js/app.js` importa modulos reales y necesarios:

- Core: `toast`.
- Layout: `sidebar`.
- Admin.
- Auth/Login.
- Autorizacion.
- Clientes.
- Dashboard.
- Integracion.
- Panel.
- Reportes.

Cada modulo revisado mantiene inicializacion segura por selector, `data-module` o `data-*` equivalente.

## Decisiones tomadas

- Mantener el diseno manual donde ya encaja con Tailwind y componentes.
- Mover comportamiento de UI fuera de Blade hacia `resources/js/core` o `resources/js/modules`.
- No reintroducir Bootstrap ni assets en `public/css` o `public/js`.
- Eliminar solo plantillas Bootstrap de paginacion confirmadas como no usadas.
- Mantener plantillas de paginacion Tailwind/default restantes para compatibilidad con Laravel.

## Pendientes antes de Fase 8 backend

- Validacion manual en navegador real de Cliente, Promesas, CNA, Autorizacion, Login, Panel y Reportes.
- Revisar consola del navegador despues de recorrer flujos criticos.
- Confirmar responsive desktop/mobile con usuarios reales de prueba.
- Mantener la regla de no agregar scripts embebidos ni estilos por pantalla.

## Verificaciones ejecutadas

- [+] `php artisan route:list --except-vendor`: correcto, 53 rutas listadas.
- [+] `php artisan view:clear`: correcto.
- [+] `php artisan cache:clear`: correcto.
- [+] `php artisan view:cache`: correcto.
- [+] `php artisan test`: correcto, 12 tests y 141 assertions.
- [+] `npm run build`: correcto, 27 modulos transformados.
- [+] `git diff --check`: correcto, sin errores de whitespace.
- [+] Busqueda de scripts en Blade: solo quedan payloads JSON en Dashboard y Panel.
- [+] Busqueda de Bootstrap en vistas/JS activos: sin referencias.
- [!] `php -l` no aplica en esta revision porque no se modificaron archivos PHP no-Blade.

## Resultado

El frontend sigue alineado con la arquitectura V3. Las desviaciones detectadas despues del diseno manual fueron normalizadas sin deshacer el estilo visual aplicado.
