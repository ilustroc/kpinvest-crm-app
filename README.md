# KP Invest CRM App

Aplicacion CRM desarrollada en Laravel 10 para la gestion operativa de KP Invest: consulta de clientes, promesas de pago, solicitudes CNA, carga de data operativa, reportes y administracion de usuarios.

Este repositorio contiene un sistema que ya funciona en produccion. La rama de trabajo para el analisis de version 3 es:

```bash
v3/analisis-documentacion
```

## Estado del analisis

Este documento y la carpeta `docs/` fueron preparados como diagnostico tecnico inicial para planificar una version 3 sin tocar la logica productiva.

No se ejecutaron migraciones, seeders ni cambios de base de datos durante este analisis.

## Punto critico sobre la base de datos

El archivo `u480021566_kpinvest_bd.sql` es la referencia principal de la estructura real de produccion. Las migraciones actuales en `database/migrations/` no representan todo el historial ni toda la estructura real del esquema, por lo que no deben ejecutarse sin una estrategia de normalizacion y respaldo.

## Documentacion tecnica

- [01 - Descripcion general](docs/01_descripcion_general.md)
- [02 - Estructura del proyecto](docs/02_estructura_proyecto.md)
- [03 - Base de datos](docs/03_base_de_datos.md)
- [04 - Modulos funcionales](docs/04_modulos_funcionales.md)
- [05 - Rutas y controladores](docs/05_rutas_y_controladores.md)
- [06 - Diagnostico tecnico](docs/06_diagnostico_tecnico.md)
- [07 - Recomendaciones V3](docs/07_recomendaciones_v3.md)
- [08 - Plan V3](docs/08_plan_v3.md)
- [09 - Arquitectura V3](docs/09_arquitectura_v3.md)
- [10 - Base de datos local y migraciones](docs/10_base_datos_local_y_migraciones.md)
- [11 - Frontend Tailwind y Vite](docs/11_frontend_tailwind_vite.md)
- [12 - Checklist deploy V3](docs/12_checklist_deploy_v3.md)

## Stack principal

- PHP 8.1 o superior, con plataforma Composer fijada a PHP 8.3.0.
- Laravel Framework 10.x.
- MySQL/MariaDB.
- Tailwind CSS v4 y Vite como frontend V3.
- Bootstrap CSS/JS e Icons fueron retirados del layout global durante la migracion frontend V3.
- `public/css` y `public/js` ya no tienen assets activos; los assets nuevos pasan por Vite.
- PhpSpreadsheet para exportaciones Excel.
- PhpWord y DomPDF/mPDF/iLovePDF para generacion o conversion de documentos.
- Sanctum instalado, aunque el uso principal del sistema es via sesion web.

## Modulos principales

- Autenticacion y control de usuarios activos.
- Panel/resumen operativo.
- Dashboard estadistico.
- Consulta de clientes y cuentas.
- Promesas de pago y flujo de aprobacion.
- Solicitudes CNA y generacion de documentos.
- Reportes de pagos, promesas y CNA.
- Integraciones CSV para data, asignaciones, CCD y pagos.
- Administracion de usuarios por rol.

## Reglas de seguridad para trabajar en V3

- No modificar `main` directamente.
- No ejecutar `php artisan migrate` contra la base real.
- No asumir que las migraciones actuales reconstruyen produccion.
- No eliminar columnas, tablas ni relaciones sin comparacion previa contra el SQL real.
- No cambiar controladores, modelos, rutas ni vistas productivas sin pruebas y respaldo.
- No exponer valores reales de `.env` en documentacion o commits.

## Comandos utiles para inventario local

Estos comandos son seguros para inspeccion y no alteran la base de datos:

```bash
php artisan route:list
composer install
npm install
npm run build
```

Evitar por ahora:

```bash
php artisan migrate
php artisan migrate:fresh
php artisan db:seed
```
