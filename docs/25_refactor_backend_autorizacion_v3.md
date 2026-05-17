# 25 - Refactor backend Autorizacion V3

## Objetivo

Reducir y ordenar `AutorizacionController` sin cambiar rutas, nombres de rutas, estructura de base de datos, filtros, paginacion, variables de vista, mensajes ni reglas funcionales.

Esta fase corresponde a:

```text
Fase 8.4 - Refactor backend del modulo Autorizacion
```

## Archivos revisados

- `app/Http/Controllers/AutorizacionController.php`.
- `app/Services/Promesa/PromesaQueryService.php`.
- `app/Services/Cna/CnaQueryService.php`.
- `resources/views/autorizacion/index.blade.php`.
- `routes/web/promesas.php`.
- `routes/web/cna.php`.
- `tests/Feature/V3PromesaModuleTest.php`.
- `tests/Feature/V3CnaModuleTest.php`.
- `tests/Feature/V3WorkflowSmokeTest.php`.

## Responsabilidades que quedaban en el controlador

Despues de Fase 8.2 y Fase 8.3, `AutorizacionController` ya no tenia consultas largas de Promesas ni CNA, pero aun conservaba:

- Lectura de filtros `q` y `status`.
- Coordinacion manual entre `PromesaQueryService` y `CnaQueryService`.
- Armado del array final para `resources/views/autorizacion/index.blade.php`.
- Calculo de `isSupervisor`.
- Consulta directa de pagos por DNI para el modal CNA.
- Orquestacion de workflow de Promesas mediante Actions.

## ViewModel creado

### `app/ViewModels/Autorizacion/AutorizacionIndexViewModel.php`

Responsabilidad:

- Exponer la data final que consume `autorizacion.index`.
- Mantener nombres compatibles con la vista:
  - `rows`.
  - `cnaRows`.
  - `prodByOp`.
  - `q`.
  - `status`.
  - `isSupervisor`.

No se cambio el formato principal de variables esperado por Blade.

## Servicios creados

### `app/Services/Autorizacion/AutorizacionIndexService.php`

Responsabilidad:

- Leer filtros desde el request.
- Coordinar `PromesaQueryService`.
- Coordinar `CnaQueryService`.
- Construir `AutorizacionIndexViewModel`.
- Mantener `AutorizacionController@index` liviano.

### `app/Services/Autorizacion/AutorizacionPaymentLookupService.php`

Responsabilidad:

- Consultar pagos por DNI para la ficha CNA.
- Mantener el contrato JSON actual de `autorizacion.pagos`.
- Sacar la consulta directa de pagos desde el controlador.

## Controlador modificado

### `AutorizacionController`

Ahora:

- `index()` retorna la vista usando `AutorizacionIndexService`.
- Los metodos de workflow de Promesas siguen delegando a Actions.
- `pagosDni()` delega la consulta a `AutorizacionPaymentLookupService`.
- Ya no coordina manualmente Promesas/CNA ni arma arrays grandes de vista.

## Vista

No se modifico `resources/views/autorizacion/index.blade.php`.

La vista conserva las mismas variables principales:

- `$rows`.
- `$cnaRows`.
- `$prodByOp`.
- `$q`.
- `$isSupervisor`.

Tambien queda disponible `$status` como variable explicita para futuras mejoras, sin cambiar comportamiento visual actual.

## Policies y Gates

No se creo `AutorizacionPolicy` porque el modulo Autorizacion funciona como bandeja/orquestador de flujos ya cubiertos por:

- `review-promesas`.
- `review-cna`.
- `preapprove-promesa`.
- `approve-promesa`.
- `reject-promesa-supervisor`.
- `reject-promesa-admin`.
- `preapprove-cna`.
- `approve-cna`.
- `reject-cna-supervisor`.
- `reject-cna-admin`.

Decision:

- Mantener las Policies propias de Promesas y CNA.
- No duplicar reglas en una Policy adicional de Autorizacion.

## Tests creados

Archivo:

- `tests/Feature/V3AutorizacionModuleTest.php`.

Cobertura:

- Render de `autorizacion.index`.
- Bandeja de Promesas visible para administrador.
- Bandeja CNA visible para administrador.
- Filtros `q` y `status`.
- Visibilidad de supervisor sobre pendientes de su equipo.
- Bloqueo de asesor por middleware actual.
- Rutas de workflow de Promesas siguen respondiendo.
- Endpoint JSON `autorizacion.pagos` conserva contrato.

## Comportamiento mantenido

- Misma ruta `autorizacion`.
- Misma ruta `autorizacion.pagos`.
- Mismas rutas de workflow de Promesas.
- Mismos middlewares.
- Mismos filtros `q` y `status`.
- Misma paginacion CNA `page_cna`.
- Mismas variables principales de Blade.
- Mismos redirects y mensajes de workflow.
- Misma visibilidad por rol/equipo.

## Pendientes

- Validacion manual en navegador real de la bandeja y modales.
- Si la pantalla crece mas, se puede separar en componentes/partials de backend o agregar contadores al ViewModel.

## Estado de Fase 8

Con Clientes, Promesas, CNA y Autorizacion refactorizados con tests, la Fase 8 de backend critico queda cerrada a nivel tecnico.

Quedan pendientes fuera de Fase 8:

- Pruebas manuales visuales.
- Preparacion de deploy controlado.
- Revalidacion sobre dump reciente antes de tocar produccion.
