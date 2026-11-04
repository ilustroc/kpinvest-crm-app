# Corrección del error 500 — Reporte de Asignaciones V3

Fecha: 2026-11-04

## Causa exacta

La aplicación web local usa la conexión MySQL `kp_crm_revision_v3`. La petición autenticada a `GET /reportes/asignaciones` llegaba a renderizar la vista, pero Laravel no pudo reescribir su archivo Blade compilado:

```text
file_put_contents(/var/www/html/storage/framework/views/fdba64955716258c39f3b73206253141.php):
Failed to open stream: Permission denied
```

La excepción quedó registrada el 2026-11-04 15:52:47 en el log del contenedor Apache. El archivo afectado era `root:root` con modo `0644`; Apache ejecuta como `www-data`. La traza sitúa el fallo en `Illuminate\Filesystem\Filesystem.php:204`, llamado por `BladeCompiler.php:194`.

No fue una migración faltante ni una excepción SQL. La consulta del controlador ya había completado y el fallo sucedía al compilar la vista. Se corrigieron la propiedad y permisos de `storage` y `bootstrap/cache` dentro del contenedor local, sin borrar archivos ni registros. Las validaciones posteriores se ejecutaron como `www-data` para no volver a crear cachés de vistas propiedad de `root`.

## Esquema y datos activos verificados (solo lectura)

- Base web activa: `kp_crm_revision_v3`.
- `asignar_clientes`: 276,162 filas.
- Las columnas usadas por el reporte existen: `cliente_cuenta_id`, `solicitante_user_id`, `asesor_destino_user_id`, `aprobado_por_user_id`, `fecha_solicitud`, `motivo`, `estado`, `origen` y `fecha_aprobacion`.
- Históricos detectados: 276,158 sin solicitante, 107,963 sin asesor destino, 276,158 sin resolutor y 276,158 sin fecha de solicitud.
- `clientes_cuentas` y `users` tienen las claves primarias requeridas para las uniones.

No se ejecutaron migraciones, DDL, escrituras ni alteraciones sobre `kp_crm_revision_v3`.

## Consulta, rendimiento y solución

La consulta utiliza `LEFT JOIN` por las llaves reales:

```sql
FROM asignar_clientes ac
LEFT JOIN clientes_cuentas cc ON cc.id = ac.cliente_cuenta_id
LEFT JOIN users solicitante ON solicitante.id = ac.solicitante_user_id
LEFT JOIN users destino ON destino.id = ac.asesor_destino_user_id
LEFT JOIN users resolvedor ON resolvedor.id = ac.aprobado_por_user_id
ORDER BY ac.fecha_solicitud DESC, ac.id DESC
```

Por ello, una operación sin `cliente_cuenta_id` no se enlaza artificialmente con otra cuenta que tenga el mismo número de operación. El plan de la consulta sin filtros analiza el histórico y usa `filesort` para cumplir el orden global: 274,314 filas estimadas. No se añadió un índice ni se modificó la base histórica. En local, la primera página real (25 de 276,162) se obtuvo en 109.81 ms; el filtro por documento/operación probado tardó 144.91 ms y devolvió tres filas.

El reporte sigue usando paginación SQL real. La exportación pasó de recorrer la consulta por lotes reordenados por ID a usar un cursor en el orden solicitado, evitando materializar el resultado de la consulta en una colección PHP completa.

## Compatibilidad histórica y Excel

`AssignmentReportService` presenta correctamente los nulos históricos:

- Cliente sin cuenta: `Histórico`.
- Solicitante nulo: `Sistema / Histórico`.
- Asesor destino: conserva `name` histórico (por ejemplo, `BOLSA` o `ESCALL`); si no existe, `Sin información`.
- Motivo, fecha de solicitud y resolución ausentes: `Histórico` o `Sin información`.
- Nunca se inventan solicitantes ni aprobadores.

`AssignmentExport` conserva los ceros iniciales de documento y operación con celdas explícitamente de texto, mantiene `ExportSanitizer` contra fórmulas y exporta las doce columnas solicitadas.

## Pruebas y evidencia

- Navegador local autenticado como administrador: `GET /reportes/asignaciones` respondió y mostró 276,162 registros, tabla, filtros y filas históricas; no hubo errores JavaScript.
- `ClienteAssignmentModuleTest`: 14 aprobadas, 225 aserciones.
- Nueva cobertura: fila histórica con todas las relaciones nulas, registro manual, carga masiva, mismo número de operación en otro cliente, filtros combinados, segunda página, reporte vacío, rol no autorizado y XLSX.
- La integración masiva verifica plantilla `NUMDOC,OPERACION,NAME`, previsualización, inexistentes, ambigüedades, idempotencia, aprobación automática, solicitante/resolutor, motivo, fechas y aparición en el reporte.
- `npm run build`: aprobado.

## Archivos corregidos

- `app/Services/AssignmentReportService.php`
- `app/Exports/AssignmentExport.php`
- `resources/views/reportes/asignaciones.blade.php`
- `tests/Feature/ClienteAssignmentModuleTest.php`

## Integridad

Las escrituras de prueba permanecen restringidas a `kp_crm_testing` mediante `phpunit.xml`. No se modificaron reglas de asignación, aprobaciones, `ClienteAssignmentService`, CNA, Propuestas, Hostinger ni producción.

Commit funcional publicado: `97c1de8 fix(reportes): corrige historial de asignaciones`.
La sincronización de ramas se verifica tras el fast-forward seguro.
