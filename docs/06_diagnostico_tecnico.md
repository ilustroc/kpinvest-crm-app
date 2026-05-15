# 06 - Diagnostico Tecnico

## Resumen del diagnostico

El sistema cumple funciones productivas importantes, pero crecio con deuda tecnica: base de datos sin migraciones historicas completas, controladores grandes, vistas con mucha logica embebida, dependencias externas mezcladas en controladores y varios puntos donde el codigo no esta completamente alineado con el SQL real.

No se hicieron cambios funcionales durante este analisis.

## Errores o riesgos detectados

### 1. Migraciones incompletas frente a produccion

El SQL real contiene todas las tablas productivas, pero las migraciones actuales solo cubren cambios puntuales. Esto impide reconstruir un ambiente confiable desde cero usando `php artisan migrate`.

Riesgo:

- Ambientes nuevos inconsistentes.
- Perdida de confianza en migraciones.
- Cambios futuros peligrosos por no tener baseline.

### 2. Riesgo de `upsert` sin indice unico real

`DataImport` usa:

```php
DB::table('clientes_cuentas')->upsert($batch, ['numdoc', 'operacion'], $updateCols);
```

Pero el SQL real de `clientes_cuentas` no muestra una unica `numdoc` + `operacion`.

Riesgo:

- Duplicacion de registros.
- Actualizaciones que no se comporten como se espera.
- Reportes y vistas con datos repetidos.

### 3. Modelo `CcdCliente` desalineado con SQL

El modelo incluye `codigo` en `$fillable`, pero la tabla real `ccd_clientes` no tiene esa columna.

Riesgo:

- Confusion de mantenimiento.
- Errores si algun flujo intenta escribir `codigo`.
- Logica condicional en `ClienteController` que detecta si existe la columna.

### 4. `users.active` e `users.is_active`

La tabla tiene ambas columnas, pero el codigo usa `active`.

Riesgo:

- Estados divergentes.
- Usuarios activos en una columna e inactivos en otra.
- Confusion para soporte o scripts externos.

### 5. `promesas_pago.tipo` con valores legacy

SQL define default `parcial`. El codigo actual usa `convenio`, `convenio_balon` y `cancelacion`; el modelo normaliza valores no reconocidos a `convenio`.

Riesgo:

- Datos legacy interpretados de forma distinta.
- Reportes con tipos inconsistentes.
- Dificultad para auditar historico.

### 6. Controladores demasiado grandes

Archivos con alta responsabilidad:

- `CnaController`
- `AutorizacionController`
- `PromesaPdfController`
- `ClienteController`

Riesgo:

- Dificil probar.
- Dificil modificar sin romper produccion.
- Mezcla de validacion, negocio, persistencia, documentos y respuestas HTTP.

### 7. Vistas grandes con JavaScript embebido

`resources/views/clientes/show.blade.php` supera las 1200 lineas y contiene mucho comportamiento de interfaz.

Riesgo:

- Cambios visuales pueden romper flujos de negocio.
- Dificil aislar errores frontend.
- Duplicacion de logica entre Blade y JS.

### 8. Consultas SQL complejas en servicios de reportes

`PromiseReportService` y `CnaReportService` usan subconsultas, `selectRaw`, `whereRaw` y expresiones JSON.

Riesgo:

- Performance variable en volumen alto.
- Dificil portar o testear.
- Cambios de nombres de columnas pueden romper reportes.

### 9. Dependencia externa iLovePDF dentro de controladores

`CnaController` y `PromesaPdfController` llaman iLovePDF directamente.

Riesgo:

- Caidas externas impactan experiencia de usuario.
- Dificil reintentar o auditar.
- Controlador queda acoplado a proveedor externo.

### 10. Generacion de documentos depende de archivos en storage

Las plantillas DOCX se buscan en `storage/app/templates`.

Riesgo:

- Ambientes nuevos sin plantillas rompen CNA/acuerdos.
- No hay validacion automatica de existencia de plantillas.
- No esta claro si las plantillas estan versionadas o desplegadas manualmente.

### 11. Middleware `role` duplicado en Kernel

`app/Http/Kernel.php` contiene dos entradas `role` en `$routeMiddleware`, una local y una de Spatie. La dependencia Spatie Permission no esta en `composer.json`.

Riesgo:

- Confusion al mantener.
- Potenciales errores si cambia la version/framework o si se usa la propiedad equivocada.

### 12. Falta de pruebas funcionales

Solo existen tests base de Laravel.

Riesgo:

- Refactors sin red de seguridad.
- Cambios en V3 pueden romper flujos productivos sin deteccion temprana.

### 13. Autorizacion distribuida

Las reglas de roles estan repartidas entre rutas, middleware, servicios y controladores.

Riesgo:

- Inconsistencias de permisos.
- Cambios de rol dificiles de auditar.
- Posibles accesos no previstos.

### 14. Logica de equipo por nombre en algunos reportes

El dashboard y reportes usan `gestor` como texto en pagos y `user_id` en promesas/CNA.

Riesgo:

- Si cambia el nombre de un asesor, los reportes historicos pueden quedar desalineados.
- Es dificil garantizar equivalencia entre `users.name` y `pagos_propia.gestor`.

### 15. Archivos no esperados en raiz

Se observa un archivo `aprobada,` en la raiz del proyecto y `error_log`.

Riesgo:

- Ruido en repositorio.
- Posible inclusion de logs o artefactos no deseados.

## Fallos de estructura

- No hay separacion clara entre capa HTTP y casos de uso.
- La generacion de documentos no esta aislada en servicios.
- Importaciones CSV mezclan validacion, parsing y persistencia.
- Vistas concentran UI, reglas de habilitacion y JS.
- No hay documentacion previa del dominio ni de la base.

## Posibles malas practicas

- Uso de `Schema::hasTable` y `getColumnListing` en runtime para tolerar esquemas variables.
- Uso de `set_time_limit` e `ini_set` dentro de procesos web.
- Uso amplio de `DB::table` y SQL raw sin objetos de consulta tipados.
- Dependencia de nombres textuales para relacionar asesores y pagos.
- Correos enviados de forma sincrona o best-effort sin trazabilidad de cola clara.
- Assets mezclados entre Vite, `public/`, CDN y estilos embebidos.

## Codigo repetido

Se repiten patrones en:

- Controladores de reportes: filtros, facets, export.
- JS de reportes: multiselect, AJAX, paginacion, export URL.
- Controladores de integracion: plantilla, upload, import, mensajes.
- Workflows de promesas y CNA: preaprobar, aprobar, rechazar por supervisor/admin.

## Modelos y relaciones incompletas

- `ClienteCuenta` no declara fillable especifico ni relaciones.
- `PagoLote` no relaciona `usuario_id` con `User`.
- `PagoPropia` no relaciona cliente por DNI/operacion ni usuario por gestor.
- `CnaSolicitud` declara relaciones a usuarios, pero el SQL no muestra FKs.
- `CcdCliente` incluye `codigo` sin columna real.
- `AsignarCliente` no relaciona con `User`; guarda `name` como texto.

## Rutas

La estructura de `routes/web.php` esta agrupada y legible, pero:

- Hay mucha funcionalidad en una sola ruta web.
- No hay versionado ni separacion por archivos de rutas de modulo.
- La API no esta desarrollada mas alla de `/api/user`.
- Los permisos dependen de strings de rol en muchos lugares.

## Riesgos de seguridad

- Valores de `.env` no deben exponerse en documentacion ni commits.
- Login no tiene rate limiting explicito propio en ruta web.
- No se observa politica centralizada de permisos por accion.
- Importaciones permiten archivos grandes y escriben directamente en tablas criticas.
- Descargas/generacion de documentos dependen de rutas y archivos locales; deben validarse path traversal y existencia de plantillas.
- Uso de CDNs externos implica dependencia de terceros y superficie de seguridad en frontend.
- Si `APP_DEBUG` estuviera activo en produccion, las rutas de Ignition serian altamente sensibles.

## Riesgos de mantenimiento

- Cualquier cambio en la base puede romper flujos por falta de migraciones completas.
- La vista del cliente y controladores grandes son dificiles de probar.
- Los reportes tienen SQL acoplado a nombres exactos de columnas.
- No hay suite de regresion para flujos criticos.
- No hay documentacion de despliegue ni de plantillas requeridas.

## Partes criticas que no deben tocarse sin revision

- `u480021566_kpinvest_bd.sql`
- `clientes_cuentas`
- `promesas_pago`
- `promesa_operaciones`
- `promesa_cuotas`
- `cna_solicitudes`
- `pagos_propia`
- `pagos_lotes`
- `users`
- `ClienteController`
- `CnaController`
- `AutorizacionController`
- `PromesaCreator`
- `PromesaWorkflowService`
- `WorkflowMailer`
- `resources/views/clientes/show.blade.php`
- `resources/views/autorizacion/index.blade.php`
- plantillas en `storage/app/templates`

