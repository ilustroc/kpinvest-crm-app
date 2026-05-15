# 07 - Recomendaciones V3

## Objetivo de V3

Construir una version mas mantenible sin romper produccion: documentar primero, crear una base tecnica confiable, aislar modulos criticos y luego refactorizar con pruebas.

## Recomendacion 1: crear baseline de base de datos

Antes de cualquier migracion nueva:

1. Confirmar que `u480021566_kpinvest_bd.sql` corresponde a produccion actual.
2. Crear un backup de estructura y datos.
3. Definir una migracion baseline o un mecanismo formal para marcar el esquema actual como punto de partida.
4. Crear documentacion tabla por tabla con columnas, indices, FKs y uso en codigo.
5. Preparar migraciones futuras solo desde ese baseline.

No ejecutar `php artisan migrate` en produccion hasta completar esta etapa.

## Recomendacion 2: alinear modelos con SQL real

Revisar:

- `CcdCliente::$fillable` vs columna `codigo`.
- `users.active` vs `is_active`.
- `promesas_pago.tipo` y valores legacy.
- `ClienteCuenta` con fillable explicito.
- Relaciones faltantes en `PagoLote`, `PagoPropia`, `AsignarCliente` y `CnaSolicitud`.

## Recomendacion 3: agregar pruebas antes de refactorizar

Priorizar pruebas de regresion para:

- Login y usuario inactivo.
- Busqueda de cliente.
- Creacion de promesa de cancelacion.
- Creacion de promesa con cronograma.
- Workflow de promesas.
- Creacion y aprobacion CNA.
- Importacion de pagos.
- Importacion de data maestra.
- Reportes principales.

Estas pruebas pueden usar una base SQLite o MySQL de prueba, pero primero se debe definir un esquema confiable.

## Recomendacion 4: extraer servicios de dominio

Sugerencia de servicios:

- `CnaCreationService`
- `CnaWorkflowService`
- `CnaDocumentService`
- `CnaNumberingService`
- `PromesaDocumentService`
- `ClienteProfileService`
- `ImportResult`
- `ReportFilterParser`

La meta es que los controladores solo validen request, llamen servicios y retornen respuestas.

## Recomendacion 5: modularizar rutas

Dividir rutas por archivos:

- `routes/web/auth.php`
- `routes/web/clientes.php`
- `routes/web/promesas.php`
- `routes/web/cna.php`
- `routes/web/reportes.php`
- `routes/web/integracion.php`
- `routes/web/admin.php`

Mantener `routes/web.php` como agregador.

## Recomendacion 6: centralizar permisos

Opciones:

- Policies de Laravel por recurso.
- Gates por accion.
- Un servicio `PermissionService`.
- Un enum o clase de roles permitidos.

Evitar strings repetidos de roles en controladores, vistas y rutas.

## Recomendacion 7: sanear imports

Mejoras sugeridas:

- Validar encabezados con objetos de resultado.
- Registrar auditoria de importacion.
- Guardar errores completos en archivo o tabla, no solo en flash message.
- Confirmar indices unicos requeridos.
- Mover procesos pesados a Jobs cuando exista infraestructura de colas.
- Separar parsing CSV de persistencia.

## Recomendacion 8: mejorar reportes

Acciones:

- Crear indices segun filtros reales.
- Medir tiempos de consultas.
- Evitar subconsultas escalares repetidas si el volumen crece.
- Cachear facets con invalidacion controlada.
- Evaluar tablas/materializaciones para reportes pesados.
- Unificar JS de multiselect y paginacion AJAX.

## Recomendacion 9: ordenar frontend

Acciones:

- Extraer JS embebido de vistas grandes hacia `resources/js/modules` y cargarlo por Vite.
- Reducir CSS embebido en `layouts/app.blade.php`.
- Crear componentes Blade reutilizables para filtros, tablas, badges y modales.
- Revisar dependencia de CDNs externos en produccion.

## Recomendacion 10: documentos y plantillas

Acciones:

- Versionar o documentar deployment de plantillas DOCX.
- Validar plantillas al iniciar o con comando de diagnostico.
- Mover conversion PDF a servicio.
- Definir fallback formal si iLovePDF falla.
- Registrar auditoria de generacion de documentos.

## Recomendacion 11: seguridad

Acciones:

- Confirmar `APP_DEBUG=false` en produccion.
- Agregar rate limiting al login.
- Auditar permisos de borrado de pagos.
- Auditar acceso a documentos CNA y acuerdos.
- Evitar exponer errores crudos al usuario final.
- Revisar logs para que no contengan datos sensibles innecesarios.
- Evitar valores reales de `.env` en repositorio.

## Recomendacion 12: plan de trabajo sugerido

### Fase 1 - Base segura

- Mantener rama V3 separada.
- Congelar SQL real como baseline documentado.
- Crear suite minima de pruebas.
- Corregir documentacion de despliegue y plantillas.

### Fase 2 - Refactor sin cambio funcional

- Extraer servicios desde controladores grandes.
- Extraer componentes Blade.
- Unificar JS repetido.
- Centralizar permisos.

### Fase 3 - Migraciones confiables

- Crear baseline/migraciones completas.
- Validar en ambiente de staging.
- Comparar estructura generada vs SQL real.
- Preparar estrategia rollback.

### Fase 4 - Mejoras funcionales

- Solo despues de pruebas y baseline.
- Priorizar mejoras pequenas por modulo.
- Mantener compatibilidad con datos legacy hasta migracion validada.

## Proximos pasos inmediatos

1. Revisar esta documentacion con el equipo.
2. Confirmar si `u480021566_kpinvest_bd.sql` es el ultimo dump productivo.
3. Crear un ambiente staging con copia sanitizada.
4. Definir estrategia de baseline de migraciones.
5. Elegir el primer modulo para pruebas de regresion: recomendacion inicial, promesas y CNA.
