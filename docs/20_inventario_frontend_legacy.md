# 20 - Inventario Frontend Legacy

## Objetivo

Registrar que partes del frontend siguen dependiendo de Bootstrap, CDN, CSS/JS en `public` o scripts embebidos.

Este inventario tambien deja constancia de los archivos legacy ya eliminados para evitar que vuelvan a agregarse sin necesidad.

## Estado global

Pantallas ya migradas a Tailwind/Vite:

- [+] Administracion de usuarios.
- [+] Dashboard.
- [+] Reporte de pagos.
- [+] Reporte de promesas.
- [+] Reporte CNA.
- [+] Integracion de pagos.
- [+] Integracion de data maestra.
- [+] Integracion de asignaciones.
- [+] Integracion de CCD.
- [+] Login.
- [+] Panel principal `/`.
- [+] Autorizacion.
- [+] Clientes.

Bootstrap ya no se carga desde el layout. Las pantallas migradas conservan esta marca documental:

```blade
@section('tailwind_only', true)
```

## Limpieza ejecutada

Archivos eliminados por no tener referencias activas desde Blade, Vite, PHP, rutas o tests:

- `public/css/app.css`.
- `public/css/layout/app.css`.
- `public/css/admin/admin.css`.
- `public/css/dashboard-stats.css`.
- `public/css/reportes/pagos.css`.
- `public/css/reportes/promesas.css`.
- `public/css/reportes/cna.css`.
- `public/css/auth/login.css`.
- `public/js/app.js`.
- `public/js/bootstrap.js`.
- `public/js/layout/app.js`.
- `public/js/admin/admin.js`.
- `public/js/dashboard-stats.js`.
- `public/js/reportes/pagos.js`.
- `public/js/reportes/promesas.js`.
- `public/js/reportes/cna.js`.
- `public/js/auth/login.js`.
- `resources/views/welcome.blade.php`.
- `error_log`.
- `estructura_generada_v3.sql`.

Carpetas eliminadas al quedar vacias:

- `public/css/admin`.
- `public/css/layout`.
- `public/css/reportes`.
- `public/css/auth`.
- `public/css`.
- `public/js/admin`.
- `public/js/layout`.
- `public/js/reportes`.
- `public/js/auth`.
- `public/js`.

## CDN usados todavia

Desde `resources/views/layouts/app.blade.php`:

- Google Fonts.
- Vite para `resources/css/app.css` y `resources/js/app.js`.

Desde vistas legacy:

- No quedan CDN adicionales fuera del layout.
- No quedan vistas legacy Bootstrap detectadas.

Login ya no carga Bootstrap, Bootstrap Icons ni assets desde `public`.

## Archivos activos en public/css

Estado:

- [+] No quedan archivos activos en `public/css`.

Regla:

- No agregar CSS nuevo en `public/css`.

## Archivos activos en public/js

Estado:

- [+] No quedan archivos activos en `public/js`.

Regla:

- No agregar JS nuevo en `public/js`.

## Reportes

Estado:

- [+] `resources/views/reportes/pagos.blade.php` migrada a Tailwind.
- [+] `resources/views/reportes/pdp.blade.php` migrada a Tailwind.
- [+] `resources/views/reportes/cna.blade.php` migrada a Tailwind.
- [+] Tablas parciales migradas a componentes `x-tables.*`.
- [+] Filtros multiselect migrados a `x-reportes.multiselect`.
- [+] JS migrado a `resources/js/modules/reportes`.
- [+] Sin Bootstrap CDN global.
- [+] Archivos antiguos de reportes en `public/css/reportes` y `public/js/reportes` eliminados.

## Integraciones

Vistas:

- `resources/views/placeholders/integracion-pagos.blade.php`.
- `resources/views/placeholders/integracion-data.blade.php`.
- `resources/views/placeholders/integracion-asignacion.blade.php`.
- `resources/views/placeholders/integracion-ccd.blade.php`.

Estado:

- [+] Vistas migradas a Tailwind.
- [+] Usan `@section('tailwind_only', true)`.
- [+] Formularios migrados a `x-forms.*` y `x-ui.button`.
- [+] Avisos migrados a `x-feedback.alert`.
- [+] Tabla de ultimo lote de pagos migrada a `x-tables.*`.
- [+] Precheck CSV de pagos movido a `resources/js/modules/integracion/imports.js`.
- [+] Sin Bootstrap classes, Bootstrap Icons, CSS embebido ni scripts embebidos.

Pendiente:

- [!] Validar en navegador real los imports con CSV de negocio grandes.

## Login

Vista:

- `resources/views/auth/login.blade.php`.

Estado:

- [+] Migrada a Tailwind/Vite.
- [+] Bootstrap CSS/JS eliminado.
- [+] Bootstrap Icons eliminado.
- [+] CSS `public/css/auth/login.css` eliminado.
- [+] JS `public/js/auth/login.js` movido a `resources/js/modules/auth/login.js`.
- [+] `public/css/auth` y `public/js/auth` eliminadas al quedar vacias.

Pendiente:

- [!] Validar visualmente responsive y comportamiento de Caps Lock/password en navegador real.

## Clientes

Vista:

- `resources/views/clientes/show.blade.php`.

Estado:

- [+] CSS embebido retirado.
- [+] JS embebido movido a `resources/js/modules/clientes/show.js`.
- [+] Modales Bootstrap reemplazados por modales Tailwind.
- [+] Dropdown/collapse/tooltips Bootstrap reemplazados por `details`, `title` y JS modular.
- [+] Bootstrap Icons retirado.
- [+] Formularios y tablas migrados a componentes Tailwind.
- [+] Bootstrap ya no carga en Cliente.

Pendiente:

- [!] Validacion manual visual y funcional sobre navegador real.

## Autorizacion

Vista:

- `resources/views/autorizacion/index.blade.php`.

Estado:

- [+] Migrada a Tailwind/Vite.
- [+] Usa `@section('tailwind_only', true)`.
- [+] CSS embebido retirado.
- [+] JS embebido movido a `resources/js/modules/autorizacion/index.js`.
- [+] Modales Bootstrap reemplazados por modales Tailwind.
- [+] Paginacion Bootstrap reemplazada.
- [+] Sin Bootstrap classes ni Bootstrap Icons.

Pendiente:

- [!] Validacion manual visual de modales, aprobaciones/rechazos y ficha CNA.

## CNA

Modulo visual:

- Parte de CNA vive en `clientes/show.blade.php`.
- El workflow CNA en `autorizacion/index.blade.php` ya fue migrado a Tailwind/Vite.
- Descargas DOCX/PDF no tienen vista propia principal.

Estado:

- [+] Formulario CNA migrado a `resources/views/components/cna/create-modal.blade.php`.
- [+] Logica CNA movida a `resources/js/modules/cna/form.js`.

Pendiente:

- [!] Validar creacion CNA con datos locales controlados.

## Promesas

Modulo visual:

- Creacion de promesas en `clientes/show.blade.php`.
- Workflow de promesas en `autorizacion/index.blade.php`.

Estado:

- [+] Formulario grande separado en componentes `components/promesas`.
- [+] Cronograma movido a JS modular en `resources/js/modules/promesas`.
- [+] Modales Bootstrap reemplazados por `x-ui.modal`.
- [+] Bootstrap Icons retirado.

Pendiente:

- [!] Validar creacion de cancelacion, convenio y cuota balon en navegador real.

## Otros

### Panel principal `/`

Vista:

- `resources/views/panel/resumen.blade.php`.

Estado:

- [+] Migrado a Tailwind/Vite.
- [+] Usa `@section('tailwind_only', true)`.
- [+] CSS embebido retirado.
- [+] JS embebido movido a `resources/js/modules/panel/resumen.js`.
- [+] Chart.js por CDN eliminado.
- [+] Chart.js usado desde npm/Vite.
- [+] Sin Bootstrap classes ni Bootstrap Icons.

Pendiente:

- [!] Validacion visual manual del grafico, sugerencias del buscador y responsive.

### Welcome

Estado:

- [+] `resources/views/welcome.blade.php` fue eliminado porque no tenia ruta activa.

## Estado actual de public

Despues de la limpieza de Fase 7.2 y 7.3:

- [+] No quedan archivos activos en `public/css`.
- [+] No quedan archivos activos en `public/js`.

Regla:

- No agregar nuevos archivos a `public/css` ni `public/js`.
- Todo asset nuevo debe vivir en `resources/css`, `resources/js` y cargarse por Vite.

## Cierre frontend Tailwind/Vite

Estado:

- [+] `resources/views/promesas_placeholder.txt` no existe.
- [+] No hay referencias a `promesas_placeholder` ni `placeholder.txt`.
- [+] `.gitkeep` innecesarios eliminados en carpetas con archivos reales.
- [+] Componentes placeholder no usados eliminados: `clientes/account-card`, `cna/cna-history`, `cna/cna-summary`.
- [+] No quedan archivos activos en `public/css` ni `public/js`.
- [+] Bootstrap CSS, JS e Icons no cargan desde el layout.
- [+] No quedan `data-bs`, `form-control`, `table-responsive` ni `modal fade` en vistas/JS activos.

Falsos positivos esperados al buscar Bootstrap:

- `bootstrap/app.php`, `bootstrap/cache` y referencias de PHPUnit/Composer.
- Menciones historicas o de cierre en documentacion V3.

## Orden recomendado pendiente

1. Validar manualmente Cliente, Promesas y CNA.
2. Ejecutar pruebas funcionales completas.
3. Revisar responsive/mobile.
4. Mantener regla de no agregar assets en `public/css` o `public/js`.

## Regla

No borrar archivos legacy dudosos. Primero confirmar referencias activas y tener reemplazo funcional por Vite/Tailwind.
