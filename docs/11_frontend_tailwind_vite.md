# 11 - Frontend con Tailwind CSS v4 y Vite

## Decision frontend V3

La V3 migrara progresivamente desde Bootstrap hacia Tailwind CSS v4 usando Vite.

El objetivo final es eliminar Bootstrap del proyecto, pero de forma controlada:

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
- [+] `npm run build` se ejecuto correctamente.
- [!] Bootstrap sigue cargando de forma condicional para pantallas legacy no migradas.

## Situacion actual

El frontend actual esta en transicion. Todavia existen partes legacy que usan:

- Blade.
- Bootstrap 5 via CDN.
- Bootstrap Icons via CDN.
- Google Fonts via CDN.
- Chart.js via CDN en algunas vistas.
- CSS embebido en `resources/views/layouts/app.blade.php`.
- CSS en `public/css`.
- JS en `public/js`.
- JS embebido en vistas grandes.
- Vite instalado, pero no como unico canal de assets.

La pantalla piloto de administracion ya usa Tailwind y no carga Bootstrap desde el layout.

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
- [+] Las vistas migradas pueden usar `@section('tailwind_only', true)` para no cargar Bootstrap.
- [!] Las vistas legacy siguen usando Bootstrap temporalmente para evitar quiebres.

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

No hacer todo de golpe.

Estado actual:

- [+] Bootstrap fue retirado para la pantalla piloto de administracion mediante `tailwind_only`.
- [!] Bootstrap sigue disponible para vistas legacy.
- [ ] Retirar Bootstrap globalmente cuando las pantallas dependientes hayan sido migradas.

## Retirar Bootstrap Icons

Bootstrap Icons tambien debe revisarse.

Opciones:

- Mantener temporalmente mientras se migra UI.
- Reemplazar por iconos SVG propios.
- Reemplazar por una libreria compatible con Vite.
- Usar componentes Blade de iconos.

No retirar Bootstrap Icons hasta confirmar todas las pantallas que dependen de ellos.

## Componentes Blade + Tailwind

Los componentes Bootstrap deben reemplazarse por componentes Blade con Tailwind.

Propuesta:

```text
resources/views/components/
  ui/
    button.blade.php
    badge.blade.php
    card.blade.php
    alert.blade.php
    input.blade.php
    select.blade.php
    table.blade.php
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
- [+] `resources/views/components/layout/sidebar.blade.php`.
- [+] `resources/views/components/layout/topbar.blade.php`.
- [ ] Crear componente modal reutilizable si mas pantallas lo necesitan.
- [ ] Crear componentes textarea/date cuando se migren formularios mas grandes.

## Organizacion JS por modulos

```text
resources/js/
  app.js
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
8. Reportes.
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
- [+] Modales Bootstrap fueron reemplazados por modales simples con Blade y JS del modulo `resources/js/modules/admin/users.js`.
- [+] Funcionalidad de crear usuario, cambiar contrasena y activar/desactivar se mantiene por las mismas rutas.
- [!] Requiere validacion manual en navegador con usuarios reales de prueba.

## Riesgos

### Riesgos de eliminar Bootstrap

- Muchas vistas dependen de clases Bootstrap.
- Modales y dropdowns dependen de JS Bootstrap.
- Algunos comportamientos pueden dejar de funcionar si se retira el CDN antes de migrarlos.
- Las tablas pueden perder estilos rapidamente.

Mitigacion:

- Migrar pantalla por pantalla.
- Crear componentes Blade antes de reemplazar clases.
- Mantener Bootstrap temporalmente mientras una pantalla no este migrada.
- No retirar CDN global hasta que el layout y las pantallas criticas esten listas.

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

Tailwind y Vite entran primero como infraestructura. La eliminacion de Bootstrap se hace despues, por partes, con pruebas visuales y funcionales en localhost.
