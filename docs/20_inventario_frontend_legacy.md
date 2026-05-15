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

Bootstrap sigue activo de forma condicional para vistas legacy que no declaran:

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
- `public/js/app.js`.
- `public/js/bootstrap.js`.
- `public/js/layout/app.js`.
- `public/js/admin/admin.js`.
- `public/js/dashboard-stats.js`.
- `public/js/reportes/pagos.js`.
- `public/js/reportes/promesas.js`.
- `public/js/reportes/cna.js`.
- `resources/views/welcome.blade.php`.
- `error_log`.
- `estructura_generada_v3.sql`.

Carpetas eliminadas al quedar vacias:

- `public/css/admin`.
- `public/css/layout`.
- `public/css/reportes`.
- `public/js/admin`.
- `public/js/layout`.
- `public/js/reportes`.

## CDN usados todavia

Desde `resources/views/layouts/app.blade.php`, solo para vistas legacy:

- Bootstrap CSS.
- Bootstrap Icons.
- Bootstrap JS bundle.
- Google Fonts.

Desde vistas legacy:

- `resources/views/panel/resumen.blade.php`: Chart.js por CDN.
- `resources/views/auth/login.blade.php`: Bootstrap CSS/JS y Bootstrap Icons por CDN.

## Archivos activos en public/css

Activos por referencia directa desde Blade:

- `public/css/auth/login.css`.

Motivo:

- `resources/views/auth/login.blade.php` todavia no fue migrada a Tailwind/Vite.

## Archivos activos en public/js

Activos por referencia directa desde Blade:

- `public/js/auth/login.js`.

Motivo:

- `resources/views/auth/login.blade.php` todavia no fue migrada a Tailwind/Vite.

## Reportes

Estado:

- [+] `resources/views/reportes/pagos.blade.php` migrada a Tailwind.
- [+] `resources/views/reportes/pdp.blade.php` migrada a Tailwind.
- [+] `resources/views/reportes/cna.blade.php` migrada a Tailwind.
- [+] Tablas parciales migradas a componentes `x-tables.*`.
- [+] Filtros multiselect migrados a `x-reportes.multiselect`.
- [+] JS migrado a `resources/js/modules/reportes`.
- [+] Sin Bootstrap CDN al declarar `tailwind_only`.
- [+] Archivos antiguos de reportes en `public/css/reportes` y `public/js/reportes` eliminados.

## Integraciones

Vistas:

- `resources/views/placeholders/integracion-pagos.blade.php`.
- `resources/views/placeholders/integracion-data.blade.php`.
- `resources/views/placeholders/integracion-asignacion.blade.php`.
- `resources/views/placeholders/integracion-ccd.blade.php`.

Legacy detectado:

- Bootstrap classes: `card`, `btn`, `form-control`, `table-responsive`.
- Bootstrap Icons.
- CSS embebido en integracion de pagos.
- JS embebido en integracion de pagos.

Pendiente:

- Migrar a `x-forms.*`, `x-tables.*`, `x-feedback.*`.
- Mover prevalidacion CSV a `resources/js/modules/integracion`.

## Clientes

Vista:

- `resources/views/clientes/show.blade.php`.

Legacy detectado:

- CSS embebido extenso.
- JS embebido extenso.
- Bootstrap modals.
- Bootstrap dropdowns/collapse/tooltips.
- Bootstrap Icons.
- Formularios y tablas con clases Bootstrap.

Pendiente:

- Dividir vista en partials/componentes.
- Mover JS a `resources/js/modules/clientes`.
- Migrar modales de Promesa y CNA con componentes V3.

## Autorizacion

Vista:

- `resources/views/autorizacion/index.blade.php`.

Legacy detectado:

- CSS embebido.
- JS embebido.
- Bootstrap modals.
- Bootstrap pagination.
- Bootstrap Icons.
- Tablas con clases Bootstrap.

Pendiente:

- Crear componentes de workflow.
- Mover JS a `resources/js/modules/autorizacion`.
- Migrar tablas y modales.

## CNA

Modulo visual:

- Parte de CNA vive en `clientes/show.blade.php`.
- Parte de CNA vive en `autorizacion/index.blade.php`.
- Descargas DOCX/PDF no tienen vista propia principal.

Legacy detectado:

- Formularios CNA dentro de modales Bootstrap.
- Workflow CNA dentro de autorizacion legacy.

Pendiente:

- Crear componentes `components/cna`.
- Crear JS `resources/js/modules/cna`.

## Promesas

Modulo visual:

- Creacion de promesas en `clientes/show.blade.php`.
- Workflow de promesas en `autorizacion/index.blade.php`.

Legacy detectado:

- Formulario grande embebido.
- Cronograma con JS embebido.
- Modales Bootstrap.
- Bootstrap Icons.

Pendiente:

- Crear componentes `components/promesas`.
- Crear JS `resources/js/modules/promesas`.
- Revisar comportamiento de cuota balon.

## Otros

### Login

Vista:

- `resources/views/auth/login.blade.php`.

Legacy activo:

- Bootstrap CSS/JS por CDN.
- Bootstrap Icons.
- `public/css/auth/login.css`.
- `public/js/auth/login.js`.

Pendiente:

- Migrar login a Tailwind/Vite en fase separada.

### Panel principal `/`

Vista:

- `resources/views/panel/resumen.blade.php`.

Legacy:

- CSS embebido.
- JS embebido.
- Chart.js por CDN.
- Bootstrap classes.
- Bootstrap Icons.

Pendiente:

- Migrar o consolidar con Dashboard.

### Welcome

Estado:

- [+] `resources/views/welcome.blade.php` fue eliminado porque no tenia ruta activa.

## Estado actual de public

Despues de la limpieza, solo deben quedar activos:

- `public/css/auth/login.css`.
- `public/js/auth/login.js`.

Regla:

- No agregar nuevos archivos a `public/css` ni `public/js` salvo excepcion temporal documentada.
- Todo asset nuevo debe vivir en `resources/css`, `resources/js` y cargarse por Vite.

## Orden recomendado pendiente

1. Migrar Integraciones.
2. Migrar Login.
3. Migrar Panel principal.
4. Migrar Autorizacion.
5. Migrar Clientes.
6. Migrar CNA/Promesas finos.
7. Retirar Bootstrap CDN del layout.
8. Eliminar `public/css/auth/login.css` y `public/js/auth/login.js` cuando Login migre a Vite.

## Regla

No borrar archivos legacy dudosos. Primero confirmar referencias activas y tener reemplazo funcional por Vite/Tailwind.
