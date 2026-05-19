# Modulo Reportes

## Alcance

Reportes de pagos, promesas y CNA con filtros, facets, paginacion AJAX y exportaciones.

## Rutas

Prefijo `reportes`:

- `reportes.pagos`
- `reportes.pagos.facets`
- `reportes.pagos.export`
- `reportes.pdp`
- `reportes.pdp.facets`
- `reportes.pdp.export`
- `reportes.cna`
- `reportes.cna.facets`
- `reportes.cna.export`

## Backend actual

Controladores:

- `ReportePagosController`
- `ReportePromesasController`
- `ReporteCnaController`

Servicios historicos:

- `PaymentReportService`
- `PromiseReportService`
- `CnaReportService`

## Frontend

Reportes ya fue migrado a Tailwind/Vite. JS modular:

- `resources/js/modules/reportes/pagos.js`
- `resources/js/modules/reportes/promesas.js`
- `resources/js/modules/reportes/cna.js`
- `resources/js/modules/reportes/filters.js`

## Pendientes

- Reubicar servicios historicos a `app/Services/Reporte` en una fase segura.
- Revisar consultas complejas si se necesita optimizacion.
