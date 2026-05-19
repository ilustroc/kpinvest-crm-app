# Modulo Integracion

## Alcance

Importaciones CSV operativas:

- Data maestra.
- Asignaciones.
- CCD.
- Pagos.

## Rutas

Prefijos bajo `integracion`:

- `data`
- `asignacion`
- `ccd`
- `pagos`

## Frontend

Las pantallas de integracion ya usan Tailwind/Vite. El precheck y comportamiento JS viven en:

```text
resources/js/modules/integracion/imports.js
```

## Backend actual

Los controladores de integracion mantienen la logica actual de importacion. No fueron parte del refactor backend critico.

## Pendientes

- Crear servicios/actions de importacion por dominio.
- Agregar validaciones de CSV mas trazables si se requiere.
- Confirmar mensajes de error de importaciones en pruebas manuales.
