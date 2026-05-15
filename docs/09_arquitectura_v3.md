# 09 - Arquitectura V3

## Arquitectura actual detectada

El proyecto usa una arquitectura Laravel MVC tradicional:

- Rutas en `routes/web.php`.
- Controladores en `app/Http/Controllers`.
- Modelos Eloquent en `app/Models`.
- Vistas Blade en `resources/views`.
- Servicios para algunas areas especificas.
- Imports/exports separados en `app/Imports` y `app/Exports`.

El patron actual funciona, pero algunas pantallas crecieron demasiado. La logica de negocio, acceso a datos, formateo para vistas, permisos y generacion de documentos estan mezclados en varios controladores y vistas grandes.

## Problemas de la arquitectura actual

- `routes/web.php` concentra todos los modulos.
- Controladores grandes mezclan responsabilidades.
- Algunas vistas tienen demasiado HTML, CSS y JavaScript.
- Hay logica de permisos repetida.
- Los servicios existen, pero no siguen una convencion uniforme.
- El frontend esta mezclado entre CDN, CSS en layout, `public/js`, scripts embebidos y Vite.
- La estructura de base de datos no esta representada por migraciones completas.

## Opciones evaluadas

### MVC tradicional de Laravel

Consiste en mantener rutas, controladores, modelos y vistas siguiendo la estructura basica de Laravel.

Ventajas:

- Es simple.
- El equipo Laravel lo entiende facilmente.
- No requiere crear muchas capas.
- Encaja con Blade y Eloquent.

Desventajas:

- Si no se controla, los controladores crecen demasiado.
- Las vistas pueden concentrar mucha logica.
- Los modulos quedan mezclados.

Uso recomendado:

- Mantenerlo como base, pero ordenarlo por modulos y servicios.

### MVC modular por dominios

Agrupa controladores, vistas, servicios y rutas segun modulos funcionales: clientes, promesas, CNA, reportes, integracion, admin.

Ventajas:

- Encaja muy bien con un CRM interno.
- Permite avanzar por partes.
- Reduce mezcla entre modulos.
- No exige una reescritura total.

Desventajas:

- Requiere convenciones claras.
- Puede duplicar patrones si no se documenta.

Uso recomendado:

- Es la base mas conveniente para V3.

### Arquitectura por capas

Separa responsabilidades por capas:

- HTTP/controllers.
- Servicios/casos de uso.
- Modelos/repositorios.
- Vistas/ViewModels.

Ventajas:

- Ordena dependencias.
- Facilita pruebas.
- Reduce controladores pesados.

Desventajas:

- Puede volverse burocratica si se aplica con rigidez.
- No todo necesita una clase nueva.

Uso recomendado:

- Aplicarla de forma ligera, especialmente en flujos criticos.

### Clean Architecture ligera

Busca separar dominio, casos de uso, infraestructura y presentacion.

Ventajas:

- Muy testeable.
- Independiza reglas de negocio.
- Buena para sistemas grandes y con mucho cambio.

Desventajas:

- Puede ser demasiado compleja para este CRM si se aplica completa.
- Puede alejarse de las convenciones naturales de Laravel.
- Aumenta cantidad de archivos y decisiones.

Uso recomendado:

- No aplicar Clean Architecture completa.
- Tomar ideas utiles: casos de uso, DTOs simples, servicios, dependencias claras.

### Arquitectura basada en servicios

Extrae logica de negocio desde controladores hacia clases en `app/Services`.

Ventajas:

- Encaja con el codigo actual.
- Permite refactor gradual.
- Reduce riesgo.
- Facilita pruebas unitarias.

Desventajas:

- Si todo se llama "Service", puede crecer sin orden.
- Requiere separar servicios por modulo.

Uso recomendado:

- Usarla como herramienta principal, pero organizada por dominio.

### Arquitectura modular por modulos funcionales

Organiza el sistema por areas reales del negocio:

- Cliente.
- Promesa.
- CNA.
- Reporte.
- Integracion.
- Admin.
- Documento.

Ventajas:

- Es facil de entender para negocio y desarrollo.
- Permite trabajar modulo por modulo.
- Evita una reescritura total.
- Es compatible con Laravel, Blade, Tailwind y Vite.

Desventajas:

- Requiere disciplina para no mezclar modulos.
- Algunos modelos seguiran siendo compartidos.

Uso recomendado:

- Es la arquitectura recomendada para V3.

## Arquitectura recomendada para V3

Recomendacion:

```text
MVC modular por dominios + servicios + acciones puntuales + componentes Blade.
```

No se recomienda una Clean Architecture completa. El proyecto es un CRM interno ya productivo, por lo que conviene una arquitectura realista, gradual y cercana a Laravel.

La idea es:

- Mantener Laravel como backend principal.
- Mantener Blade como sistema de vistas.
- Separar rutas por modulo.
- Agrupar controladores por dominio.
- Extraer logica de negocio a servicios.
- Usar Actions para operaciones concretas.
- Usar ViewModels para pantallas complejas.
- Usar Policies/Gates para permisos.
- Usar componentes Blade para UI repetida.
- Gestionar CSS/JS con Tailwind y Vite.

## Separacion de responsabilidades

### Controladores

Responsabilidad:

- Recibir request.
- Validar o usar Form Requests.
- Llamar servicios/actions.
- Retornar vistas, redirects o JSON.

No deberian:

- Generar documentos directamente.
- Armar consultas complejas extensas.
- Tener reglas de negocio largas.
- Formatear toda la data de una vista compleja.

### Servicios

Responsabilidad:

- Reglas de negocio.
- Orquestacion de consultas.
- Calculos.
- Procesos reutilizables.

Ejemplos:

- `ClienteProfileService`
- `PromesaWorkflowService`
- `CnaDocumentService`
- `PaymentReportService`

### Actions

Responsabilidad:

- Ejecutar una accion puntual con nombre claro.

Ejemplos:

- `CreatePromesaAction`
- `ApproveCnaAction`
- `RejectPromesaAction`
- `ImportPagosAction`

### ViewModels

Responsabilidad:

- Preparar datos para vistas complejas sin llenar el controlador.

Ejemplos:

- `ClienteShowViewModel`
- `AutorizacionIndexViewModel`
- `DashboardViewModel`

### Models

Responsabilidad:

- Representar tablas.
- Relaciones Eloquent.
- Casts.
- Scopes simples.
- Accessors realmente propios del modelo.

No deberian:

- Contener logica extensa de workflow.
- Hacer consultas de reportes complejas.

### Policies/Gates

Responsabilidad:

- Autorizar acciones.
- Centralizar permisos por rol.

Ejemplos:

- `PromesaPolicy`
- `CnaPolicy`
- `ClientePolicy`
- `UserPolicy`

## Propuesta de carpetas

```text
app/
  Http/
    Controllers/
      Cliente/
        ClienteController.php
        ClienteLookupController.php
      Promesa/
        PromesaController.php
        PromesaDocumentoController.php
      Cna/
        CnaController.php
        CnaDocumentoController.php
      Reporte/
        ReportePagosController.php
        ReportePromesasController.php
        ReporteCnaController.php
      Integracion/
        IntegracionDataController.php
        IntegracionPagosController.php
        IntegracionCcdController.php
        IntegracionAsignacionController.php
      Admin/
        AdminUsersController.php
      Dashboard/
        PanelController.php
        DashboardController.php
  Services/
    Cliente/
      ClienteProfileService.php
      ClienteLookupService.php
    Promesa/
      PromesaCreator.php
      PromesaWorkflowService.php
      PromesaDocumentService.php
    Cna/
      CnaCreatorService.php
      CnaWorkflowService.php
      CnaNumberingService.php
      CnaDocumentService.php
    Reporte/
      PaymentReportService.php
      PromiseReportService.php
      CnaReportService.php
    Importacion/
      DataImportService.php
      PagosImportService.php
      CcdImportService.php
      AsignacionImportService.php
    Documento/
      DocxTemplateService.php
      PdfConversionService.php
  Actions/
    Promesa/
    Cna/
    Importacion/
  ViewModels/
    Cliente/
    Autorizacion/
    Dashboard/
  Models/
  Policies/
```

Rutas:

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

Vistas:

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
```

Assets:

```text
resources/
  css/
    app.css
  js/
    app.js
    modules/
      clientes/
      promesas/
      cna/
      reportes/
      integracion/
      admin/
```

Documentacion:

```text
docs/
  arquitectura/
  base_datos/
  frontend/
  backend/
  despliegue/
```

## Ejemplo de flujo recomendado

Pantalla cliente:

```text
Route /clientes/{dni}
  -> ClienteController@show
    -> ClienteProfileService::build($dni, $user)
      -> ClienteCuenta, PagoPropia, CcdCliente, PromesaPago, CnaSolicitud
    -> ClienteShowViewModel
    -> resources/views/clientes/show.blade.php
      -> componentes Blade
      -> JS de resources/js/modules/clientes/show.js
```

El controlador queda pequeno:

```php
public function show(string $dni, ClienteProfileService $service)
{
    $profile = $service->build($dni, auth()->user());

    return view('clientes.show', [
        'vm' => new ClienteShowViewModel($profile),
    ]);
}
```

## Principio guia

Cada cambio V3 debe responder:

- Que modulo toca.
- Que riesgo productivo tiene.
- Que prueba lo cubre.
- Que rollback existe.

Si una nueva capa no reduce riesgo ni mejora claridad, no debe agregarse.

