# 01 - Descripcion General

## Proposito

KP Invest CRM App es un sistema interno para gestionar informacion de clientes con deuda, cuentas asociadas, pagos, promesas de pago, solicitudes CNA, reportes operativos y carga de informacion desde archivos CSV.

El sistema esta orientado a roles operativos:

- `asesor`: consulta clientes, registra promesas y solicitudes CNA.
- `supervisor`: revisa y preaprueba solicitudes de su equipo.
- `administrador`: aprueba o rechaza solicitudes preaprobadas y gestiona usuarios.
- `soporte`: rol operativo con acceso a integraciones, reportes o gestion limitada segun el modulo.

## Flujo funcional principal

1. Un usuario inicia sesion.
2. El panel muestra KPIs, busqueda rapida de clientes y pendientes segun el rol.
3. Desde el cliente se revisan cuentas, pagos, documentos CCD, promesas y CNA.
4. El asesor puede registrar promesas de pago o solicitudes CNA sobre operaciones del cliente.
5. Supervisor y administrador procesan aprobaciones desde la bandeja de autorizacion.
6. El sistema envia correos de workflow cuando corresponde.
7. Los modulos de reportes permiten consultar y exportar pagos, promesas y CNA.
8. Los modulos de integracion permiten cargar data maestra, asignaciones, CCD y pagos desde CSV.

## Tecnologias

- Laravel 10 como framework principal.
- Eloquent y Query Builder para acceso a datos.
- MySQL/MariaDB como motor de base de datos.
- Sesiones web para autenticacion.
- Blade para vistas.
- Tailwind CSS v4 y Vite para frontend.
- JavaScript vanilla para interacciones, filtros y tablas AJAX.
- PhpSpreadsheet para generar XLSX.
- PhpWord e iLovePDF para generar DOCX/PDF.

## Integraciones externas

- SMTP configurado por variables `MAIL_*`.
- iLovePDF configurado por `ILOVEPDF_PUBLIC_KEY` e `ILOVEPDF_SECRET_KEY`.
- Google Fonts desde CDN.
- Chart.js gestionado por npm/Vite en las pantallas migradas.

## Estado actual del proyecto

El sistema esta en produccion y tiene una base de datos real documentada en `u480021566_kpinvest_bd.sql`. Las migraciones actuales son parciales y no reconstruyen todo el esquema productivo.

La version 3 debe partir de este diagnostico, no de ejecutar migraciones existentes.
