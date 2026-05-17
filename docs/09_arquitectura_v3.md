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
- [+] Se creo la estructura base de carpetas por dominio.
- [+] Se mantuvieron los mismos controladores actuales.
- [+] Se mantuvieron las mismas URLs.
- [+] Se mantuvieron los mismos nombres de rutas.
- [+] Se mantuvieron los mismos middlewares.
- [+] `php artisan route:list --except-vendor` lista correctamente las rutas.
- [+] Se creo un servicio piloto: `app/Services/Admin/UserStatusService.php`.
- [+] Se creo un ViewModel piloto: `app/ViewModels/Admin/UserIndexViewModel.php`.
- [+] La pantalla piloto de administracion usa el flujo Controller -> Service/ViewModel -> Blade.
- [+] Se creo `app/Support/Authorization/Roles.php`.
- [+] Se creo `app/Policies/UserPolicy.php`.
- [+] Se creo `app/Policies/ClientePolicy.php`.
- [+] Se creo `app/Policies/PromesaPolicy.php`.
- [+] Se definieron Gates iniciales por modulo critico.
- [+] Reportes, Integracion y Administracion usan Gates en rutas.
- [+] Sidebar/topbar usan Gates para mostrar accesos.
- [+] Dashboard fue migrado como segunda pantalla piloto Tailwind/Vite.

Pendiente:

- [ ] Mover controladores a carpetas por dominio.
- [+] Crear servicios reales para el modulo Cliente.
- [+] Crear action puntual para eliminacion de pagos de Cliente.
- [+] Crear ViewModel para la vista compleja de Cliente.
- [+] Crear servicios reales para el modulo Promesas.
- [+] Crear actions puntuales para creacion, workflow y acuerdo de Promesas.
- [+] Migrar permisos internos de Promesas hacia Policy/Gates.
- [+] Crear servicios reales para el modulo CNA.
- [+] Crear actions puntuales para creacion, workflow y descargas de CNA.
- [+] Migrar permisos internos de CNA hacia Policy/Gates.
- [+] Crear ViewModel y servicios de orquestacion para Autorizacion.
- [+] Reducir `AutorizacionController` sin cambiar variables de vista.
- [!] Mover controladores a carpetas por dominio queda pendiente hasta tener validacion manual suficiente.

Nota: los controladores grandes no se movieron todavia para evitar cambios de namespace y riesgo innecesario en esta primera separacion.
La excepcion controlada es `AdminUsersController`, que comenzo a delegar logica simple en un servicio y un ViewModel sin cambiar rutas ni comportamiento esperado.

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

Al inicio de la V3 el sistema se parecia a un MVC tradicional:

- `routes/web.php` concentra las rutas.
- `app/Http/Controllers` concentra controladores de todos los modulos.
- `app/Models` contiene modelos Eloquent.
- `app/Services` ya existe, pero no esta organizado completamente por dominio.
- `resources/views` contiene vistas Blade.
- El frontend mezclaba Bootstrap, CSS embebido, JS embebido, archivos en `public/` y Vite.

El sistema funciona, pero necesita orden modular.

## Problemas actuales que V3 debe resolver

- Controladores grandes con muchas responsabilidades.
- Vistas muy extensas con HTML, JS y reglas visuales mezcladas.
- Rutas en un solo archivo.
- Permisos repartidos en rutas, controladores y vistas.
- Frontend repartido historicamente entre Bootstrap CDN, CSS en layout, `public/js` y Vite; en Fase 7 se retiro Bootstrap globalmente y se consolido Vite.
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
    Admin/
    Dashboard/
    Documento/
    Autorizacion/

  Actions/
    Cliente/
    Promesa/
    Cna/
    Importacion/
    Admin/

  ViewModels/
    Cliente/
    Promesa/
    Cna/
    Reporte/
    Admin/
    Dashboard/
    Autorizacion/

  DTOs/
    Cliente/
    Promesa/
    Cna/
    Reporte/
    Integracion/

  Support/
    Authorization/
    Formatters/
    Helpers/

  Models/
  Policies/
```

Estado actual de carpetas:

- [+] `app/Services/Cliente`.
- [+] `app/Services/Promesa`.
- [+] `app/Services/Cna`.
- [+] `app/Services/Reporte`.
- [+] `app/Services/Integracion`.
- [+] `app/Services/Admin`.
- [+] `app/Services/Dashboard`.
- [+] `app/Services/Documento`.
- [+] `app/Services/Autorizacion`.
- [+] `app/Actions/Cliente`.
- [+] `app/Actions/Promesa`.
- [+] `app/Actions/Cna`.
- [+] `app/Actions/Importacion`.
- [+] `app/Actions/Admin`.
- [+] `app/ViewModels/Cliente`.
- [+] `app/ViewModels/Promesa`.
- [+] `app/ViewModels/Cna`.
- [+] `app/ViewModels/Reporte`.
- [+] `app/ViewModels/Admin`.
- [+] `app/ViewModels/Dashboard`.
- [+] `app/ViewModels/Autorizacion`.
- [+] `app/DTOs/Cliente`.
- [+] `app/DTOs/Promesa`.
- [+] `app/DTOs/Cna`.
- [+] `app/DTOs/Reporte`.
- [+] `app/DTOs/Integracion`.
- [+] `app/Support/Formatters`.
- [+] `app/Support/Helpers`.
- [+] `app/Support/Authorization`.
- [+] `app/Policies/UserPolicy.php`.
- [+] `app/Policies/ClientePolicy.php`.
- [+] `app/Policies/PromesaPolicy.php`.
- [+] `app/Policies/CnaPolicy.php`.

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
      dashboard/
      layout/

  css/
    app.css
```

Estado actual de frontend V3:

- [+] `resources/css/app.css` existe y carga Tailwind CSS v4.
- [+] `resources/js/app.js` existe y carga modulos base.
- [+] `resources/js/modules/layout/sidebar.js` maneja el menu lateral sin Bootstrap.
- [+] `resources/js/modules/admin/users.js` maneja la pantalla piloto de administracion.
- [+] `resources/js/modules/dashboard/stats.js` maneja el grafico de Dashboard con Vite.
- [+] `resources/views/components/ui/*` contiene componentes base Tailwind.
- [+] `resources/views/components/layout/*` contiene sidebar y topbar Tailwind.

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

## Flujo piloto implementado

La primera pantalla piloto usa este flujo:

```text
routes/web/admin.php
  -> AdminUsersController@index
    -> UserService
    -> UserIndexViewModel
    -> resources/views/placeholders/administracion.blade.php
```

Para activacion/desactivacion de usuarios:

```text
routes/web/admin.php
  -> AdminUsersController@toggle
    -> UserStatusService
    -> User model
    -> redirect con mensaje
```

Este piloto mantiene rutas, nombres y controladores actuales, pero empieza a separar responsabilidad.

La segunda pantalla piloto usa este flujo:

```text
routes/web/dashboard.php
  -> DashboardController@index
    -> DashboardStatsService
    -> resources/views/dashboard/index.blade.php
    -> resources/js/modules/dashboard/stats.js
```

Dashboard mantiene el controlador y servicio actual, pero reemplaza Bootstrap por Tailwind y mueve el grafico a Vite.

El primer refactor backend critico usa este flujo:

```text
routes/web/clientes.php
  -> ClienteController@show
    -> ClienteProfileService
      -> ClienteAccountService
      -> ClientePaymentService
      -> ClienteShowViewModel
    -> resources/views/clientes/show.blade.php

routes/web/clientes.php
  -> ClienteController@deletePagos
    -> DeleteClientePaymentAction
      -> ClientePaymentService
      -> redirect con mensaje

routes/web/clientes.php
  -> ClienteLookupController
    -> ClienteLookupService
    -> redirect/json
```

Este refactor mantiene rutas, nombres de rutas, variables de vista y permisos funcionales.

El segundo refactor backend critico usa este flujo:

```text
routes/web/clientes.php
  -> PromesaController@store
    -> CreatePromesaAction
      -> PromesaCreationService
        -> PromesaScheduleService
      -> redirect con mensaje

routes/web/promesas.php
  -> AutorizacionController@preaprobar/aprobar/rechazar*
    -> Promesa Actions
      -> PromesaWorkflowService
      -> redirect con mensaje

routes/web/promesas.php
  -> PromesaPdfController@acuerdo
    -> GeneratePromesaAgreementAction
      -> PromesaDocumentService
      -> PDF o DOCX fallback
```

La bandeja de Autorizacion usa `AutorizacionIndexService` y `AutorizacionIndexViewModel` para coordinar `PromesaQueryService` y `CnaQueryService`.

```text
routes/web/promesas.php
  -> AutorizacionController@index
    -> AutorizacionIndexService
      -> PromesaQueryService
      -> CnaQueryService
    -> AutorizacionIndexViewModel
    -> resources/views/autorizacion/index.blade.php

routes/web/promesas.php
  -> AutorizacionController@pagosDni
    -> AutorizacionPaymentLookupService
    -> JSON para ficha CNA
```

El tercer refactor backend critico usa este flujo:

```text
routes/web/clientes.php
  -> CnaController@store
    -> CreateCnaAction
      -> CnaCreationService
        -> CnaNumberingService
        -> CnaDocumentService cuando crea administrador
      -> redirect con mensaje

routes/web/cna.php
  -> CnaController@preaprobar/aprobar/rechazar*
    -> CNA Actions
      -> CnaWorkflowService
        -> CnaDocumentService al aprobar
      -> redirect con mensaje

routes/web/cna.php
  -> CnaController@docx/pdf
    -> DownloadCnaDocumentAction
      -> CnaDocumentService
      -> descarga DOCX/PDF o fallback
```

## Permisos V3

Base implementada:

- `Roles` centraliza listas de roles y reglas reutilizables.
- `UserPolicy` centraliza permisos de usuarios.
- `ClientePolicy` centraliza permisos base del modulo Cliente.
- `PromesaPolicy` centraliza permisos de creacion, workflow y acuerdo de Promesas.
- `CnaPolicy` centraliza permisos de creacion, workflow y descargas de CNA.
- `AuthServiceProvider` registra Gates de acceso por modulo.
- Rutas de `admin`, `integracion` y `reportes` ya usan middleware `can:*`.
- El sidebar usa Gates para decidir visibilidad.

Gates iniciales:

- `access-dashboard`.
- `access-admin-users`.
- `access-reportes`.
- `access-integracion`.
- `review-promesas`.
- `review-cna`.
- `delete-client-payments`.
- `view-cliente`.
- `search-clientes`.
- `create-cliente-promesa`.
- `create-cliente-cna`.
- `create-promesa`.
- `preapprove-promesa`.
- `approve-promesa`.
- `reject-promesa-supervisor`.
- `reject-promesa-admin`.
- `generate-promesa-agreement`.
- `create-cna`.
- `preapprove-cna`.
- `approve-cna`.
- `reject-cna-supervisor`.
- `reject-cna-admin`.
- `generate-cna-document`.
- `download-cna-document`.

Pendiente:

- Validar manualmente la bandeja de Autorizacion en navegador real.
- Evitar cambios grandes de permisos sin pruebas funcionales completas.

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
- `AutorizacionIndexViewModel`
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
