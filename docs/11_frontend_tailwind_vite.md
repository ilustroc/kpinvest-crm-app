# 11 - Frontend con Tailwind CSS y Vite

## Situacion actual del frontend

El frontend actual usa:

- Blade como sistema de vistas.
- Bootstrap 5 y Bootstrap Icons via CDN.
- Google Fonts via CDN.
- Chart.js via CDN en algunas vistas.
- CSS embebido en `resources/views/layouts/app.blade.php`.
- CSS separado en `public/css`.
- JS separado en `public/js`.
- JS embebido en vistas grandes como `clientes/show.blade.php` y `panel/resumen.blade.php`.
- Vite instalado, pero no usado como unica fuente de assets.

La interfaz funciona, pero la mezcla de fuentes de CSS/JS dificulta mantener una identidad visual consistente.

## Problemas detectados

- Estilos globales embebidos en el layout.
- Vistas muy grandes con HTML, JS y reglas visuales mezcladas.
- JavaScript repetido en reportes.
- Assets repartidos entre `public/`, CDN y Vite.
- Dificultad para redisenar sin afectar funcionalidad.
- Menor control sobre versionado y build de dependencias frontend.

## Por que usar Tailwind CSS

Tailwind CSS conviene para V3 porque:

- Permite construir una interfaz consistente sin escribir grandes archivos CSS manuales.
- Funciona bien con Blade.
- Facilita crear componentes reutilizables.
- Reduce estilos globales impredecibles.
- Permite migracion gradual pantalla por pantalla.
- Encaja con un CRM interno donde se necesitan tablas, filtros, paneles y formularios densos.

Tailwind no debe usarse para redisenar todo de golpe. Debe entrar de forma progresiva.

## Por que usar Vite

Vite ya esta disponible en el proyecto y debe convertirse en la herramienta principal para:

- Compilar Tailwind.
- Gestionar `resources/css/app.css`.
- Gestionar `resources/js/app.js`.
- Separar JS por modulos.
- Versionar assets con build.
- Evitar scripts embebidos innecesarios.

Beneficios:

- Builds reproducibles.
- Mejor cache busting.
- Menos dependencia de archivos sueltos en `public/js`.
- Mejor organizacion para V3.

## Organizacion propuesta de assets

```text
resources/
  css/
    app.css
    modules/
      clientes.css
      reportes.css
  js/
    app.js
    bootstrap.js
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

Build:

```text
public/build/
```

Los assets generados por Vite deben servirse con `@vite`.

## Componentes Blade reutilizables

Crear componentes para UI repetida:

```text
resources/views/components/
  layout/
    sidebar.blade.php
    topbar.blade.php
  ui/
    card.blade.php
    button.blade.php
    badge.blade.php
    modal.blade.php
    alert.blade.php
    table.blade.php
    pagination.blade.php
  forms/
    input.blade.php
    select.blade.php
    multiselect.blade.php
    date-range.blade.php
  reportes/
    filters.blade.php
    export-button.blade.php
```

Ventajas:

- Menos HTML repetido.
- Cambios visuales centralizados.
- Pantallas mas pequenas.
- Redisenio mas seguro.

## Separacion de JavaScript por modulos

Regla recomendada:

- Cada pantalla compleja debe tener su JS en un archivo propio.
- Cada comportamiento reutilizable debe tener un modulo compartido.

Ejemplo:

```text
resources/js/modules/reportes/filters.js
resources/js/modules/reportes/pagos.js
resources/js/modules/clientes/show.js
```

`resources/js/app.js` debe importar solo lo necesario:

```js
import './bootstrap';
import './modules/layout/sidebar';
```

Para modulos especificos se puede usar:

- Entrypoints por pagina en Vite.
- Imports condicionales segun elementos presentes en DOM.
- Data attributes para inicializar componentes.

## Propuesta visual para V3

Mantener una interfaz de CRM interno:

- Clara.
- Densa.
- Rapida de operar.
- Con buen contraste.
- Tablas legibles.
- Filtros visibles.
- Estados bien diferenciados.

No conviene convertir el CRM en una landing page ni usar componentes demasiado decorativos.

Elementos recomendados:

- Sidebar simple.
- Topbar ligera.
- Cards compactas para KPIs.
- Tablas con headers fijos cuando aplique.
- Badges consistentes para estados.
- Formularios con validacion visible.
- Modales ordenados y pequenos.
- Filtros reutilizables en reportes.

## Migracion gradual desde Bootstrap/CSS actual

No se recomienda reemplazar todo Bootstrap de inmediato.

Plan sugerido:

1. Configurar Tailwind y Vite sin cambiar pantallas.
2. Crear layout V3 opcional o componentes piloto.
3. Migrar una pantalla de bajo riesgo.
4. Migrar reportes o dashboard con componentes reutilizables.
5. Migrar clientes solo cuando existan pruebas.
6. Retirar CSS viejo por partes.
7. Reducir dependencias CDN gradualmente.

## Pantallas candidatas para piloto

Primera opcion:

- Administracion de usuarios.

Motivo:

- Es importante, pero menos compleja que clientes/CNA.
- Permite probar formularios, tabla, modal y permisos.

Segunda opcion:

- Reporte de pagos.

Motivo:

- Permite probar filtros, tabla, paginacion y export.

Evitar como primera pantalla:

- `clientes/show.blade.php`.
- `autorizacion/index.blade.php`.

Son pantallas muy criticas y grandes.

## Riesgos de Tailwind y Vite

### Riesgos de Tailwind

- Mezclar Tailwind con Bootstrap puede generar inconsistencias visuales.
- Clases largas en Blade pueden volver dificil leer vistas.
- Si no se crean componentes, se puede duplicar mucho markup.
- Requiere definir convenciones de diseno.

Mitigacion:

- Usar componentes Blade.
- Migrar por pantalla.
- Definir tokens visuales: colores, espaciado, estados, botones.
- No mezclar estilos sin criterio.

### Riesgos de Vite

- Produccion necesita `npm run build`.
- El servidor debe tener los assets generados.
- Si se rompe el manifest, las vistas pueden fallar.
- Hay que ajustar deploy para incluir `public/build`.

Mitigacion:

- Documentar build.
- Probar `npm run build` antes de deploy.
- Confirmar `public/build/manifest.json`.
- Mantener fallback solo durante transicion.

## Recomendaciones finales

- Blade se mantiene.
- Tailwind entra gradualmente.
- Bootstrap puede convivir temporalmente.
- Vite debe ser el canal principal de nuevos assets.
- JS embebido debe reducirse progresivamente.
- No redisenar pantallas criticas sin pruebas.

