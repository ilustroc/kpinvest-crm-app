# 09 - Arquitectura V3

## Decision oficial de arquitectura

La arquitectura oficial para V3 sera:

```text
MVC modular por dominios + Services + Actions + ViewModels + componentes Blade.
```

Esta decision queda confirmada para guiar el resto del trabajo.

## Estado de implementacion de Fase 2

Primer avance completado:

- [+] `routes/web.php` quedo como agregador.
- [+] Se crearon archivos de rutas por dominio en `routes/web/`.
- [+] Se mantuvieron los mismos controladores actuales.
- [+] Se mantuvieron las mismas URLs.
- [+] Se mantuvieron los mismos nombres de rutas.
- [+] Se mantuvieron los mismos middlewares.
- [+] `php artisan route:list --except-vendor` lista correctamente las rutas.

Pendiente:

- [ ] Mover controladores a carpetas por dominio.
- [ ] Crear servicios por dominio donde falten.
- [ ] Crear actions para operaciones puntuales.
- [ ] Crear ViewModels para vistas complejas.
- [ ] Centralizar Policies/Gates.

Nota: los controladores grandes no se movieron todavia para evitar cambios de namespace y riesgo innecesario en esta primera separacion.

## Por que esta arquitectura

El proyecto es un CRM interno de cobranzas que ya esta funcionando en produccion. Por eso la arquitectura debe mejorar el orden sin convertir el sistema en una reescritura riesgosa.

La opcion elegida permite:

- Mantener Laravel como base natural del proyecto.
- Mantener Blade como sistema de vistas.
- Ordenar el codigo por dominios funcionales.
- Reducir controladores grandes.
- Separar logica de negocio.
- Preparar pruebas.
- Migrar frontend por etapas.
- Evitar complejidad innecesaria.

## Por que no se usara Clean Architecture completa

Clean Architecture completa seria demasiado pesada para este caso porque:

- Aumentaria mucho la cantidad de clases.
- Exigiria separar infraestructura, dominio y casos de uso con mucha rigidez.
- Podria alejar el proyecto de convenciones Laravel.
- Haria mas lento el refactor inicial.
- Aumentaria el riesgo de romper flujos productivos.

V3 tomara ideas utiles de Clean Architecture, pero de forma ligera:

- Casos de uso claros.
- Servicios testeables.
- Actions puntuales.
- Dependencias ordenadas.
- ViewModels para vistas complejas.

## Arquitectura actual detectada

Actualmente el sistema se parece a un MVC tradicional:

- `routes/web.php` concentra las rutas.
- `app/Http/Controllers` concentra controladores de todos los modulos.
- `app/Models` contiene modelos Eloquent.
- `app/Services` ya existe, pero no esta organizado completamente por dominio.
- `resources/views` contiene vistas Blade.
- El frontend mezcla Bootstrap, CSS embebido, JS embebido, archivos en `public/` y Vite.

El sistema funciona, pero necesita orden modular.

## Problemas actuales que V3 debe resolver

- Controladores grandes con muchas responsabilidades.
- Vistas muy extensas con HTML, JS y reglas visuales mezcladas.
- Rutas en un solo archivo.
- Permisos repartidos en rutas, controladores y vistas.
- Frontend repartido entre Bootstrap CDN, CSS en layout, `public/js` y Vite.
- Poca cobertura de pruebas.
- Migraciones no alineadas con la base real.

## Estructura recomendada

### Backend

```text
app/
  Http/
    Controllers/
      Cliente/
      Promesa/
      Cna/
      Reporte/
      Integracion/
      Admin/
      Dashboard/

  Services/
    Cliente/
    Promesa/
    Cna/
    Reporte/
    Integracion/
    Documento/

  Actions/
    Cliente/
    Promesa/
    Cna/
    Importacion/

  ViewModels/
    Cliente/
    Promesa/
    Cna/
    Reporte/
    Dashboard/

  Models/
  Policies/
```

### Rutas

```text
routes/
  web.php
  web/
    clientes.php
    promesas.php
    cna.php
    reportes.php
    integracion.php
    admin.php
    dashboard.php
```

`routes/web.php` debe quedar como archivo agregador:

```php
require __DIR__.'/web/dashboard.php';
require __DIR__.'/web/clientes.php';
require __DIR__.'/web/promesas.php';
require __DIR__.'/web/cna.php';
require __DIR__.'/web/reportes.php';
require __DIR__.'/web/integracion.php';
require __DIR__.'/web/admin.php';
```

### Vistas y assets

```text
resources/
  views/
    layouts/
    components/
    clientes/
    promesas/
    cna/
    reportes/
    integracion/
    admin/
    dashboard/

  js/
    app.js
    modules/
      clientes/
      promesas/
      cna/
      reportes/
      integracion/
      admin/

  css/
    app.css
```

## Flujo recomendado

El flujo general debe ser:

```text
Route -> Controller -> Service/Action -> Model -> ViewModel -> Blade View
```

Ejemplo para clientes:

```text
Route /clientes/{dni}
  -> ClienteController@show
    -> ClienteProfileService
      -> Models
    -> ClienteShowViewModel
    -> resources/views/clientes/show.blade.php
```

Ejemplo resumido:

```text
ClienteController -> ClienteProfileService -> ClienteShowViewModel -> clientes/show.blade.php
```

## Responsabilidades por capa

### Routes

Responsabilidad:

- Definir URLs.
- Agrupar middlewares.
- Apuntar a controladores.

No deben:

- Tener logica de negocio.
- Tener consultas.

### Controllers

Responsabilidad:

- Recibir request.
- Validar entrada o usar Form Requests.
- Llamar Services o Actions.
- Retornar View, Redirect o JSON.

No deben:

- Armar consultas largas.
- Generar documentos directamente.
- Resolver todo el estado de una vista compleja.
- Contener reglas extensas de negocio.

### Services

Responsabilidad:

- Orquestar logica de negocio.
- Consultar modelos.
- Calcular datos.
- Preparar resultados reutilizables.

Ejemplos:

- `ClienteProfileService`
- `PromesaWorkflowService`
- `CnaNumberingService`
- `PaymentReportService`

### Actions

Responsabilidad:

- Ejecutar operaciones puntuales.

Ejemplos:

- `CreatePromesaAction`
- `ApproveCnaAction`
- `RejectPromesaAction`
- `ImportPagosAction`

### ViewModels

Responsabilidad:

- Preparar datos para Blade.
- Formatear informacion de pantalla.
- Evitar que el controlador llene arrays enormes.

Ejemplos:

- `ClienteShowViewModel`
- `PromesaIndexViewModel`
- `CnaAuthorizationViewModel`
- `DashboardViewModel`

### Models

Responsabilidad:

- Representar tablas.
- Definir relaciones.
- Definir casts.
- Definir scopes simples.

No deben:

- Contener flujos completos de workflow.
- Generar documentos.
- Enviar correos directamente.

### Policies

Responsabilidad:

- Centralizar permisos.
- Reducir validaciones de rol repetidas.

Ejemplos:

- `ClientePolicy`
- `PromesaPolicy`
- `CnaPolicy`
- `UserPolicy`

## Modulos funcionales

### Cliente

Incluye:

- Busqueda.
- Perfil del cliente.
- Cuentas.
- Pagos relacionados.
- Promesas del cliente.
- CNA del cliente.
- CCD.

### Promesa

Incluye:

- Creacion.
- Cronograma.
- Workflow.
- Acuerdo/documento.

### CNA

Incluye:

- Creacion.
- Numeracion.
- Workflow.
- Generacion DOCX/PDF.
- Descargas.

### Reporte

Incluye:

- Pagos.
- Promesas.
- CNA.
- Exports.
- Facets/filtros.

### Integracion

Incluye:

- Data maestra.
- Pagos.
- Asignaciones.
- CCD.

### Admin

Incluye:

- Usuarios.
- Roles.
- Activacion/desactivacion.
- Cambio de contrasena.

### Dashboard

Incluye:

- Panel resumen.
- Estadisticas.
- KPIs.

## Regla de migracion arquitectonica

No mover todo al mismo tiempo.

Orden recomendado:

1. Separar rutas por modulo.
2. Crear carpetas nuevas.
3. Mover controladores solo cuando existan pruebas o validacion manual clara.
4. Extraer servicios sin cambiar comportamiento.
5. Crear ViewModels para pantallas grandes.
6. Crear componentes Blade.
7. Migrar JS por modulo.

## Principio final

Cada cambio debe responder:

- Que modulo toca.
- Que comportamiento mantiene.
- Que riesgo tiene.
- Como se prueba.
- Como se revierte.
