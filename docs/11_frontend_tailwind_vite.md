# 11 - Frontend con Tailwind CSS v4 y Vite

## Decision frontend V3

La V3 migro progresivamente desde Bootstrap hacia Tailwind CSS v4 usando Vite.

El objetivo final era eliminar Bootstrap del proyecto de forma controlada. En el estado actual V3, Bootstrap ya fue retirado del layout global:

- Sin cambiar flujos funcionales.
- Sin redisenar todo de golpe.
- Sin romper pantallas criticas.
- Migrando primero layout y componentes base.

## Estado actual de implementacion

- [+] `tailwindcss` esta instalado.
- [+] `@tailwindcss/vite` esta instalado.
- [+] `vite.config.js` esta configurado con Tailwind CSS v4.
- [+] `resources/css/app.css` carga `@import "tailwindcss";`.
- [+] `resources/js/app.js` carga modulos base.
- [+] El layout principal usa `@vite(['resources/css/app.css', 'resources/js/app.js'])`.
- [+] Sidebar y topbar base existen como componentes Blade con Tailwind.
- [+] Componentes UI base existen con Tailwind.
- [+] Administracion de usuarios fue seleccionada y migrada como pantalla piloto.
- [+] Dashboard fue migrado como segunda pantalla piloto.
- [+] Reportes fueron migrados como primer modulo bajo arquitectura frontend V3.
- [+] Se creo `resources/js/core` para utilidades compartidas.
- [+] Se crearon componentes `forms`, `tables`, `feedback` y `reportes`.
- [+] `chart.js` se gestiona por npm y Vite para Dashboard.
- [+] Cliente, Promesas y CNA dentro de Cliente fueron migrados a Tailwind/Vite.
- [+] Bootstrap CSS, Bootstrap Icons y Bootstrap JS fueron retirados del layout global.
- [+] `npm run dev` se ejecuto y respondio correctamente.
- [+] `npm run build` se ejecuto correctamente.
- [+] `public/build/manifest.json` confirmado.
- [!] `npm audit` reporta vulnerabilidades moderadas en Vite/esbuild; `npm audit fix --force` implica salto mayor a Vite 8.
- [!] Validacion manual visual pendiente en las pantallas criticas migradas.

## Fase 7 - Arquitectura frontend V3

La Fase 7 amplia el enfoque: no se trata solo de reemplazar Bootstrap por Tailwind, sino de ordenar donde vive cada pieza del frontend.

Documentos nuevos:

- `docs/19_arquitectura_frontend_v3.md`.
- `docs/20_inventario_frontend_legacy.md`.

Reglas confirmadas:

- No agregar CSS nuevo en `public/css`.
- No agregar JS nuevo en `public/js`.
- No agregar scripts grandes embebidos en Blade.
- Toda pantalla migrada usa `@section('tailwind_only', true)`.
- Toda logica JS nueva vive en `resources/js/modules`.
- JS compartido vive en `resources/js/core`.
- Todo componente repetible debe convertirse en Blade Component.

Estructura creada:

```text
resources/views/components/forms/
resources/views/components/tables/
resources/views/components/feedback/
resources/views/components/reportes/
resources/views/components/clientes/
resources/views/components/cna/
resources/views/components/promesas/

resources/js/core/
resources/js/modules/reportes/
resources/js/modules/integracion/
resources/js/modules/clientes/
resources/js/modules/autorizacion/
resources/js/modules/cna/
resources/js/modules/promesas/
```

Reportes migrados:

- `resources/views/reportes/pagos.blade.php`.
- `resources/views/reportes/pdp.blade.php`.
- `resources/views/reportes/cna.blade.php`.
- `resources/js/modules/reportes/pagos.js`.
- `resources/js/modules/reportes/promesas.js`.
- `resources/js/modules/reportes/cna.js`.
- `resources/js/modules/reportes/filters.js`.

Los archivos `public/css/reportes/*` y `public/js/reportes/*` fueron eliminados despues de confirmar que ya no tenian referencias activas.

## Situacion actual

El frontend actual esta en transicion. Todavia existen partes legacy que usan:

- Blade.
- Bootstrap 5 via CDN.
- Bootstrap Icons via CDN.
- Google Fonts via CDN.
- Chart.js instalado por npm y usado desde Vite en Dashboard y Panel.
- CSS embebido en `resources/views/layouts/app.blade.php`.
- No quedan CSS activos en `public/css`.
- No quedan JS activos en `public/js`.
- JS embebido en vistas grandes.
- Vite instalado, pero no como unico canal de assets.

Administracion y Dashboard ya usan Tailwind y no cargan Bootstrap desde el layout.

## Problemas actuales

- Dependencia fuerte de Bootstrap.
- CSS global dentro del layout.
- JS embebido dificil de mantener.
- Vistas demasiado grandes.
- Componentes visuales repetidos.
- Assets repartidos entre CDN, `public/` y Vite.
- Dificultad para redisenar de forma consistente.

## Objetivo de Tailwind CSS v4

Tailwind CSS v4 sera la base visual objetivo.

Se usara para:

- Layout principal.
- Sidebar/topbar.
- Botones.
- Badges.
- Formularios.
- Tablas.
- Cards.
- Modales.
- Estados de workflow.
- Componentes reutilizables.

## Objetivo de Vite

Vite sera el canal principal para:

- Compilar Tailwind.
- Cargar `resources/css/app.css`.
- Cargar `resources/js/app.js`.
- Separar JS por modulos.
- Generar assets versionados en `public/build`.

## Archivos que se deben revisar

Antes de instalar o cambiar assets:

- `package.json`
- `package-lock.json`
- `vite.config.js`
- `resources/views/layouts/app.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- Vistas que cargan Bootstrap por CDN.
- Vistas que tienen scripts embebidos.
- Archivos actuales en `public/css`.
- Archivos actuales en `public/js`.

## Archivos que se deben modificar primero

En la fase inicial Tailwind/Vite:

- `package.json`, mediante instalacion npm.
- `package-lock.json`, generado por npm.
- `vite.config.js`.
- `resources/css/app.css`.
- `resources/js/app.js`, solo si hace falta organizar imports base.
- Layout principal, para usar `@vite`.

No modificar todavia vistas criticas como `clientes/show.blade.php`, `autorizacion/index.blade.php`, CNA o Promesas.

## Instalacion sugerida Tailwind CSS v4

Comando sugerido:

```bash
npm install tailwindcss @tailwindcss/vite
```

Luego revisar `package.json` y `package-lock.json`.

## Configuracion esperada de Vite

Archivo a revisar/modificar:

```text
vite.config.js
```

Ejemplo esperado:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

## Configuracion esperada de CSS

Archivo:

```text
resources/css/app.css
```

Contenido base aplicado:

```css
@import "tailwindcss";
```

Ademas se agregaron tokens iniciales del CRM y una utilidad compartida de foco.

## Configuracion esperada de JS

Archivo:

```text
resources/js/app.js
```

Uso recomendado:

- Importar bootstrap propio del proyecto si aplica.
- Importar modulos compartidos.
- Evitar meter logica especifica de pantallas dentro de `app.js`.

Estado actual:

```js
import './modules/layout/sidebar';
import './modules/admin/users';
import './modules/dashboard/stats';
```

Los scripts especificos deben vivir en:

```text
resources/js/modules/
```

## Uso de `@vite`

El layout principal debe cargar assets usando:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Esto debe reemplazar progresivamente links manuales a CSS/JS propios.

Estado actual:

- [+] `@vite` esta configurado en `resources/views/layouts/app.blade.php`.
- [+] Las vistas migradas conservan `@section('tailwind_only', true)` como marca documental de migracion.
- [+] Administracion, Dashboard, Reportes, Integraciones, Login, Panel, Autorizacion y Clientes cargan CSS/JS por Vite.
- [+] Bootstrap ya no se carga desde el layout.

## Retirar Bootstrap de forma controlada

Bootstrap debe retirarse progresivamente desde:

- Layouts principales.
- CDN en Blade.
- Imports JS si existen.
- Clases Bootstrap en vistas.
- Modales.
- Botones.
- Cards.
- Tablas.
- Formularios.
- Badges.
- Alertas.

Este retiro ya se completo pantalla por pantalla hasta Cliente.

Estado actual:

- [+] Bootstrap fue retirado para la pantalla piloto de administracion.
- [+] Bootstrap fue retirado para Dashboard.
- [+] Bootstrap fue retirado para Reportes, Integraciones, Login, Panel, Autorizacion y Clientes.
- [+] Bootstrap fue retirado globalmente del layout.

## Retirar Bootstrap Icons

Bootstrap Icons tambien debe revisarse.

Opciones:

- Mantener temporalmente mientras se migra UI.
- Reemplazar por iconos SVG propios.
- Reemplazar por una libreria compatible con Vite.
- Usar componentes Blade de iconos.

Bootstrap Icons ya fue retirado del layout global. Las pantallas nuevas o migradas no deben depender de esa libreria.

## Componentes Blade + Tailwind

Los componentes Bootstrap deben reemplazarse por componentes Blade con Tailwind.

Propuesta:

```text
resources/views/components/
  ui/
    button.blade.php
    badge.blade.php
    card.blade.php
    confirm-dialog.blade.php
    date.blade.php
    dropdown.blade.php
    empty-state.blade.php
    alert.blade.php
    input.blade.php
    modal.blade.php
    select.blade.php
    table.blade.php
    textarea.blade.php
  layout/
    sidebar.blade.php
    topbar.blade.php
```

Estado actual:

- [+] `resources/views/components/ui/button.blade.php`.
- [+] `resources/views/components/ui/card.blade.php`.
- [+] `resources/views/components/ui/badge.blade.php`.
- [+] `resources/views/components/ui/alert.blade.php`.
- [+] `resources/views/components/ui/input.blade.php`.
- [+] `resources/views/components/ui/select.blade.php`.
- [+] `resources/views/components/ui/table.blade.php`.
- [+] `resources/views/components/ui/textarea.blade.php`.
- [+] `resources/views/components/ui/date.blade.php`.
- [+] `resources/views/components/ui/modal.blade.php`.
- [+] `resources/views/components/ui/dropdown.blade.php`.
- [+] `resources/views/components/ui/empty-state.blade.php`.
- [+] `resources/views/components/ui/confirm-dialog.blade.php`.
- [+] `resources/views/components/layout/sidebar.blade.php`.
- [+] `resources/views/components/layout/topbar.blade.php`.
- [+] Administracion usa el componente modal reutilizable.

## Organizacion JS por modulos

```text
resources/js/
  app.js
  core/
    dom.js
    http.js
    forms.js
    modal.js
    dropdown.js
    toast.js
  modules/
    clientes/
      show.js
      promesa-form.js
      cna-form.js
    promesas/
      workflow.js
    cna/
      workflow.js
      documentos.js
    reportes/
      filters.js
      pagos.js
      promesas.js
      cna.js
    integracion/
      imports.js
    admin/
      users.js
    dashboard/
      stats.js
```

## Orden de migracion Bootstrap -> Tailwind

Orden recomendado:

1. Layout principal.
2. Sidebar/topbar.
3. Botones y badges.
4. Formularios simples.
5. Tablas simples.
6. Administracion de usuarios.
7. Dashboard.
8. Reportes. Estado: migrado en Fase 7.
9. Clientes.
10. CNA y Promesas al final.

## Pantalla piloto

Pantalla seleccionada:

- Administracion de usuarios.

Por que:

- Usa tabla, acciones, formulario y modales.
- Es buena para probar componentes.
- Es menos critica que cliente, CNA o promesas.

Estado:

- [+] Vista migrada a Tailwind.
- [+] Tabla, filtros, botones, badges y formularios usan componentes Tailwind.
- [+] Modales Bootstrap fueron reemplazados por `x-ui.modal` y JS del modulo `resources/js/modules/admin/users.js`.
- [+] Funcionalidad de crear usuario, cambiar contrasena y activar/desactivar se mantiene por las mismas rutas.
- [+] Smoke test renderiza la pantalla contra base local.
- [!] Requiere validacion manual completa de acciones POST con usuarios reales de prueba.

## Segunda pantalla piloto

Pantalla seleccionada:

- Dashboard.

Estado:

- [+] Vista migrada a Tailwind.
- [+] Filtros, KPIs, tablas y estados vacios usan componentes Blade.
- [+] Bootstrap y Bootstrap Icons no cargan en esta vista.
- [+] CSS legacy `public/css/dashboard-stats.css` eliminado.
- [+] JS legacy `public/js/dashboard-stats.js` eliminado.
- [+] Grafico de pagos usa `resources/js/modules/dashboard/stats.js` con `chart.js` importado desde npm.
- [+] Smoke test renderiza la pantalla contra base local.
- [!] Falta validacion visual manual de grafico y responsive en navegador.

## Limpieza de assets legacy

Estado:

- [+] Eliminados assets antiguos de Reportes en `public/css/reportes` y `public/js/reportes`.
- [+] Eliminados assets antiguos de Dashboard: `public/css/dashboard-stats.css` y `public/js/dashboard-stats.js`.
- [+] Eliminados assets antiguos de Administracion: `public/css/admin/admin.css` y `public/js/admin/admin.js`.
- [+] Eliminados assets genericos no referenciados: `public/css/app.css`, `public/js/app.js`, `public/js/bootstrap.js`, `public/css/layout/app.css` y `public/js/layout/app.js`.
- [+] Login migrado a Tailwind/Vite.
- [+] Eliminados assets antiguos de Login: `public/css/auth/login.css` y `public/js/auth/login.js`.
- [+] `public/css` y `public/js` quedan sin archivos activos.

Regla:

- No volver a crear archivos nuevos en `public/css` o `public/js` salvo excepcion temporal documentada.

## Fase 7.2 - Integraciones

Estado:

- [+] `resources/views/placeholders/integracion-pagos.blade.php` migrada a Tailwind/Vite.
- [+] `resources/views/placeholders/integracion-data.blade.php` migrada a Tailwind/Vite.
- [+] `resources/views/placeholders/integracion-asignacion.blade.php` migrada a Tailwind/Vite.
- [+] `resources/views/placeholders/integracion-ccd.blade.php` migrada a Tailwind/Vite.
- [+] Precheck CSV de pagos movido a `resources/js/modules/integracion/imports.js`.
- [+] Modulo importado desde `resources/js/app.js`.
- [+] Sin Bootstrap classes, Bootstrap Icons, CSS embebido ni scripts embebidos.

Pendiente:

- [!] Validacion manual en navegador con CSV reales grandes.

## Fase 7.3 - Login

Estado:

- [+] `resources/views/auth/login.blade.php` migrada a Tailwind/Vite.
- [+] Bootstrap CSS/JS directo retirado.
- [+] Bootstrap Icons retirado.
- [+] JS movido a `resources/js/modules/auth/login.js`.
- [+] Modulo importado desde `resources/js/app.js`.
- [+] `public/css/auth/login.css` eliminado.
- [+] `public/js/auth/login.js` eliminado.

Pendiente:

- [!] Validacion manual responsive y comportamiento de Caps Lock/password.

## Fase 7.4 - Panel principal

Estado:

- [+] `resources/views/panel/resumen.blade.php` migrada a Tailwind/Vite.
- [+] `@section('tailwind_only', true)` agregado.
- [+] CSS embebido retirado.
- [+] JS embebido movido a `resources/js/modules/panel/resumen.js`.
- [+] Chart.js por CDN retirado.
- [+] Chart.js importado desde npm en el modulo Vite.
- [+] Buscador rapido y sugerencias movidos al modulo JS del Panel.
- [+] Panel principal ya no carga Bootstrap ni Bootstrap Icons.

Pendiente:

- [!] Validacion visual manual del grafico, sugerencias del buscador y responsive.

## Fase 7.5 - Autorizacion

Estado:

- [+] `resources/views/autorizacion/index.blade.php` migrada a Tailwind/Vite.
- [+] `@section('tailwind_only', true)` agregado.
- [+] CSS embebido retirado.
- [+] JS embebido movido a `resources/js/modules/autorizacion/index.js`.
- [+] Modulo importado desde `resources/js/app.js`.
- [+] Modales Bootstrap reemplazados por modales Tailwind.
- [+] Tablas migradas a `x-tables.*`.
- [+] Botones migrados a `x-ui.button`.
- [+] Alertas migradas a `x-feedback.alert`.
- [+] Paginacion CNA migrada fuera de `pagination::bootstrap-5`.
- [+] Autorizacion ya no carga Bootstrap ni Bootstrap Icons.

Pendiente:

- [!] Validacion manual visual de modales, aprobaciones/rechazos y detalle CNA.

## Fase 7.6 - Clientes y cierre Bootstrap

Estado:

- [+] `resources/views/clientes/show.blade.php` migrada a Tailwind/Vite.
- [+] `@section('tailwind_only', true)` agregado.
- [+] CSS embebido retirado.
- [+] JS embebido movido a modulos Vite.
- [+] Modulo `resources/js/modules/clientes/show.js` creado.
- [+] Modulos `resources/js/modules/promesas/form.js` y `resources/js/modules/promesas/schedule.js` creados.
- [+] Modulo `resources/js/modules/cna/form.js` creado.
- [+] Componentes `resources/views/components/clientes/*` creados.
- [+] Componentes `resources/views/components/promesas/*` creados.
- [+] Componentes `resources/views/components/cna/*` creados.
- [+] Modales Bootstrap de Cliente, Promesas y CNA reemplazados por modales Tailwind.
- [+] Bootstrap CSS, Bootstrap Icons y Bootstrap JS retirados del layout global.
- [+] `resources/js/app.js` revisado: solo importa modulos reales.
- [+] `resources/views/layouts/app.blade.php` revisado: solo carga assets por `@vite`.
- [+] `public/css` y `public/js` sin uso activo.
- [+] `.gitkeep` innecesarios retirados de modulos/carpetas con archivos reales.
- [+] Componentes placeholder no usados eliminados.

Pendiente:

- [!] Validacion manual visual de Cliente, seleccion de operaciones, cronograma de promesas y generacion CNA.

## Riesgos

### Riesgos de eliminar Bootstrap

- Muchas vistas dependen de clases Bootstrap.
- Modales y dropdowns dependen de JS Bootstrap.
- Algunos comportamientos pueden dejar de funcionar si se retira el CDN antes de migrarlos.
- Las tablas pueden perder estilos rapidamente.

Mitigacion:

- Migrar pantalla por pantalla.
- Crear componentes Blade antes de reemplazar clases.
- Mantener validaciones manuales sobre las pantallas migradas.
- No reintroducir CDN Bootstrap en vistas nuevas.

### Riesgos de Tailwind CSS v4

- Curva de adaptacion.
- Clases largas en Blade.
- Necesidad de convenciones visuales.
- Posible mezcla visual durante transicion.

Mitigacion:

- Usar componentes Blade.
- Definir tokens de diseno.
- Revisar pantalla piloto.
- Evitar duplicacion.

### Riesgos de Vite

- Produccion necesita `npm run build`.
- Debe existir `public/build/manifest.json`.
- El deploy debe incluir assets compilados.
- Si `@vite` queda mal configurado, el layout puede cargar sin CSS/JS.

Mitigacion:

- Probar `npm run dev`.
- Probar `npm run build`.
- Confirmar manifest.
- Revisar consola del navegador.

## Regla final

Tailwind y Vite quedan como infraestructura frontend V3. Bootstrap ya fue retirado del layout global y no debe reintroducirse en vistas nuevas o migradas.
