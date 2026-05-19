# Estrategia de deploy V3

## Principio

Produccion no se toca hasta validar los cambios sobre una copia reciente de produccion.

## Flujo recomendado

1. Congelar ventana de cambios.
2. Descargar dump productivo reciente.
3. Importar dump en entorno local/staging.
4. Validar baseline y migraciones necesarias.
5. Ejecutar pruebas automatizadas.
6. Ejecutar pruebas manuales.
7. Generar build Vite.
8. Revisar logs.
9. Confirmar backup y rollback.
10. Hacer deploy en ventana acordada.
11. Verificar flujos criticos post-deploy.

## Migraciones

El baseline V3 no debe ejecutarse directamente sobre una base productiva con tablas existentes sin una estrategia explicita para marcar migraciones como aplicadas o aplicar solo cambios incrementales.

## Assets

Los assets deben compilarse con:

```bash
npm run build
```

El servidor debe servir `public/build/manifest.json` generado por Vite.

## Criterio de no deploy

No desplegar si falla:

- Login.
- Busqueda/vista cliente.
- Promesas.
- CNA.
- Reportes.
- Importaciones.
- Generacion/descarga documental.
- `php artisan test`.
- `npm run build`.
