# Modulo Autorizacion

## Alcance

Bandeja operativa para revisar Promesas y CNA. Permite filtrar, consultar pagos, preaprobar, aprobar y rechazar segun rol.

## Backend V3

- `AutorizacionController` quedo como orquestador HTTP.
- `AutorizacionIndexService` coordina `PromesaQueryService` y `CnaQueryService`.
- `AutorizacionPaymentLookupService` resuelve pagos por DNI.
- `AutorizacionIndexViewModel` prepara variables para la vista.

## Vista

`resources/views/autorizacion/index.blade.php` no cambio durante el refactor backend 8.4. Ya habia sido migrada a Tailwind/Vite en Fase 7.

## Permisos

No se creo `AutorizacionPolicy`; la pantalla reutiliza:

- Gates de Promesas.
- Gates de CNA.
- `review-promesas`.
- `review-cna`.

## Comportamiento preservado

- Filtros.
- Paginacion.
- Visibilidad por rol/equipo.
- Variables de vista.
- Workflow de Promesas y CNA.
- Mensajes y redirects.

## Tests

`tests/Feature/V3AutorizacionModuleTest.php` cubre index, filtros, bandejas, visibilidad y rutas de workflow.

## Pendientes

- Validacion manual visual de modales y acciones.
- Separar aun mas la vista si crece en futuras mejoras.
