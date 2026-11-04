# Informe — Simplificación de Asignaciones y Notificaciones V3

Fecha: 2026-11-04
Proyecto: `C:\GitHub\KP-CRM-Meridian-ui`

## Resultado

La solicitud de asignación permanece en la Ficha 360°, mientras que el historial dejó de cargarse y mostrarse allí. La resolución se concentra en una bandeja de pendientes compacta, y el historial completo quedó disponible en **Reportes → Asignaciones**.

No se modificó `ClienteAssignmentService`, las reglas de asignación vigente, las cargas masivas, CNA ni Propuestas de Pago.

## Ficha 360°

- Se eliminó el renderizado de `x-asignaciones.history-panel`.
- Se retiró la consulta exclusiva `historyForClient(..., 50)` y los datos `asignacionesHistorial` del perfil.
- Se conserva la resolución de asesor vigente, ya que es necesaria para mostrar la asignación aprobada actual.
- Se conservan botón, selección de una o más operaciones, modal, motivo obligatorio, validaciones y registro histórico.

## Autorizaciones

- **ASIGNAR CLIENTE** ahora obtiene únicamente solicitudes `pendiente`, con orden `fecha_solicitud DESC, id DESC` y paginación SQL de 15 registros.
- Se quitaron filtros de estado, documento, operación, solicitante, asesor destino, origen y fechas.
- La tabla muestra cliente/documento, operación, solicitante, fecha, motivo y acciones.
- El detalle es un modal breve con cliente, documento, operación, solicitante, asesor destino, fecha y motivo.
- Aprobar y rechazar siguen protegidos por middleware y `AsignacionPolicy`: administrador, supervisor y soporte. El asesor sigue bloqueado en backend.
- Tras resolver, el redirect vuelve a consultar sólo pendientes; la fila deja de aparecer sin navegación adicional.

## Notificaciones

Se reutilizó la infraestructura existente de `WorkflowNotifier`, `WorkflowActionRequiredNotification`, `WorkflowStatusNotification`, notificaciones de base de datos y broadcast. No se creó un segundo sistema.

- Una solicitud manual crea una notificación agrupada por cliente para usuarios activos con rol administrador, supervisor o soporte.
- La acción apunta a `/autorizacion#panel-asignaciones`.
- Una aprobación o rechazo genera una notificación de estado para el solicitante activo.
- El comportamiento de campana, contador, lectura y actualización en tiempo real es el mismo de CNA y Propuestas.
- La validación existente impide que una solicitud pendiente duplicada alcance el envío de otra notificación.

## Reportes → Asignaciones

- Nueva ruta protegida por `can:access-reportes`: `/reportes/asignaciones`.
- Tabla paginada desde SQL, sin N+1: une en una sola consulta cuentas y usuarios solicitante, destino y resolutor.
- Columnas: ID, fecha, documento, cliente, operación, solicitante, asesor destino, motivo, origen, estado, usuario y fecha de resolución.
- Filtros: fecha desde/hasta, documento, operación, asesor, estado y origen.
- Orden fijo: `fecha_solicitud DESC, id DESC`.
- Exportación XLSX reutiliza PhpSpreadsheet y `ExportSanitizer`; procesa por bloques de 1,000 filas.

## Rendimiento

- La Ficha 360° elimina su consulta histórica y las relaciones de hasta 50 solicitudes que sólo alimentaban la sección retirada.
- La bandeja deja de cargar historial adicional por cada página de pendientes.
- El reporte aplica filtros, orden y paginación en la base de datos; no materializa el historial completo en memoria.

## Validación

- `php artisan test --filter=ClienteAssignmentModuleTest`: **13 pruebas, 198 aserciones, todas aprobadas**.
- Regresión V3: **30 aprobadas, 315 aserciones; 2 omitidas** por ausencia de datos locales requeridos.
- Se ejecutaron los demás grupos de la suite (importaciones, seguridad, reportes, CNA, Propuestas, Dashboard, frontend y SQL) contra `kp_crm_testing`, sin fallos observados. Las trazas de bloqueo de Dashboard son parte de su prueba de recuperación y esa prueba aprobó.
- `npm run build`: aprobado.
- Se verificaron rutas y sintaxis PHP de los nuevos artefactos.

Las pruebas de asignaciones cubren solicitud, duplicados, roles autorizados/inactivos, aprobación, rechazo, notificación al solicitante, contador y lectura de campana, retiro del historial de Ficha 360°, permisos del reporte, filtros y exportación.

## Integridad y seguridad

- No se ejecutaron migraciones ni escrituras sobre `kp_crm_revision_v3`.
- Las pruebas usan `kp_crm_testing`, según `phpunit.xml`.
- No se eliminó ningún registro histórico, archivo CCD ni volumen Docker.
- No se modificaron Hostinger, producción, reglas financieras ni la lógica de asignación vigente.

## Git y reconstrucción

Commit funcional: `f45b4bc feat(asignaciones): simplifica autorizacion y reportes`.
La sincronización publicada de `feature/meridian-ui-v3` y `main` se verifica tras el fast-forward seguro.

Para reconstruir el entorno local:

1. Mantener este único checkout en `C:\GitHub\KP-CRM-Meridian-ui`.
2. Iniciar Docker Desktop y ejecutar `docker compose up -d`.
3. Instalar dependencias conforme a `composer.lock` y ejecutar `npm ci` si los volúmenes/dependencias no existen.
4. Ejecutar `npm run build` y `docker compose exec -T app php artisan test` (la configuración de prueba apunta a `kp_crm_testing`).
