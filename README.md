# KP Invest CRM App

CRM interno de cobranzas desarrollado en Laravel para gestionar clientes, cuentas, promesas de pago, solicitudes CNA, importaciones CSV, reportes y administracion de usuarios.

La version 3 se trabaja en la rama:

```bash
v3/analisis-documentacion
```

El sistema productivo ya existe. No se debe tocar `main`, produccion ni una base real sin backup, pruebas sobre copia reciente y plan de rollback.

## Estado V3

- Frontend cerrado tecnicamente con Tailwind CSS v4 y Vite.
- Bootstrap CSS, Bootstrap JS y Bootstrap Icons eliminados del layout global.
- `public/css` y `public/js` sin uso activo para assets frontend.
- Backend critico refactorizado por modulos: Clientes, Promesas, CNA y Autorizacion.
- Migraciones V3 reconstruidas desde `u480021566_kpinvest_bd.sql` y probadas en base local desechable.
- Roles finales: `administrador`, `supervisor`, `asesor`, `soporte`.
- Pruebas automatizadas y build pasan en local.
- Deploy todavia pendiente.

## Stack actual

- Laravel 10.
- PHP 8.1+ con plataforma Composer fijada a PHP 8.3.0.
- MySQL/MariaDB.
- Blade.
- Tailwind CSS v4.
- Vite.
- PhpSpreadsheet para exportaciones.
- PhpWord, DomPDF/mPDF e iLovePDF para documentos DOCX/PDF y fallback.

## Arquitectura

V3 usa MVC modular por dominios:

- `routes/web.php` como agregador de rutas por modulo.
- Controllers como orquestadores HTTP.
- Services para logica de dominio.
- Actions para operaciones puntuales.
- ViewModels para vistas complejas.
- Policies/Gates para permisos.
- Blade Components para UI reutilizable.
- Vite como unico canal de assets frontend nuevos.

## Comandos utiles

```bash
composer install
npm install
npm run dev
npm run build
php artisan test
php artisan route:list --except-vendor
php artisan view:clear
php artisan cache:clear
php artisan view:cache
```

## Documentacion

La documentacion oficial esta en:

- [Indice de documentacion](docs/README.md)
- [Resumen V3](docs/00_resumen_v3.md)
- [Arquitectura V3](docs/01_arquitectura/arquitectura_v3.md)
- [Checklist deploy](docs/04_deploy/checklist_deploy.md)
- [Diagramas PlantUML](docs/05_diagramas/README.md)

## Reglas de seguridad

- No modificar `main` directamente.
- No ejecutar migraciones contra produccion en esta fase.
- No ejecutar `migrate:fresh` sobre una base con datos importantes.
- No versionar `.env`, dumps sensibles, logs ni archivos temporales.
- Antes de produccion: dump reciente, pruebas sobre copia, build, checklist funcional, backup y rollback.
