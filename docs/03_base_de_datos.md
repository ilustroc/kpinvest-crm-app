# 03 - Base de Datos

## Fuente de verdad actual

La estructura real de produccion esta documentada en:

```text
u480021566_kpinvest_bd.sql
```

Ese archivo fue generado desde phpMyAdmin el 15-05-2026 y apunta a una base MariaDB/MySQL llamada `u480021566_kpinvest_bd`.

Las migraciones en `database/migrations/` no representan el esquema completo. Por ahora deben tratarse solo como referencia historica parcial.

## Conexion

La aplicacion usa la conexion `mysql` por defecto en `config/database.php`, configurada por variables de entorno:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

No se deben documentar valores reales de `.env`.

## Tablas reales segun SQL

### `users`

Usuarios del sistema. Campos principales:

- `id`
- `name`
- `email`
- `role`
- `supervisor_id`
- `password`
- `active`
- `is_active`
- timestamps

Roles definidos en SQL: `administrador`, `supervisor`, `asesor`, `sistemas`, `soporte`, `usuario`.

Riesgo: el codigo usa principalmente `active`, pero la tabla tambien tiene `is_active`. Esa duplicidad puede generar confusion.

### `clientes_cuentas`

Maestro de clientes y cuentas. Campos principales:

- `numdoc`
- `cuenta`
- `nombre`
- `dpto`
- `operacion`
- `entidad`
- `producto`
- `cosecha`
- `moneda`
- `fecha_compra`
- `fecha_castigo`
- `deuda_capital`
- `interes`
- `deuda_total`
- `direccion`
- `provincia`
- `distrito`
- timestamps

Es una tabla critica: se usa en busqueda de clientes, reportes, promesas, CNA, asignaciones y documentos.

Indice real destacado:

- `idx_cc_numdoc_search` sobre `numdoc`, `entidad`, `moneda`, `deuda_total`, `deuda_capital`.

Riesgo: `DataImport` hace `upsert` por `numdoc` + `operacion`, pero el SQL no muestra indice unico sobre ese par. En MySQL/MariaDB, `upsert` requiere una clave unica o primaria para detectar duplicados; si no existe, puede insertar duplicados.

### `asignar_clientes`

Asignacion de operaciones a asesores por documento:

- `numdoc`
- `operacion`
- `name`

Tiene unica `numdoc` + `operacion`.

### `ccd_clientes`

Referencias a documentos CCD:

- `numdoc`
- `pdf`
- `cosecha`
- `link`

Riesgo: el modelo `CcdCliente` incluye `codigo` en `$fillable`, pero el SQL real no tiene columna `codigo`.

### `pagos_lotes`

Lotes de importacion de pagos:

- `tipo`
- `archivo`
- `usuario_id`
- `total_registros`
- timestamps

### `pagos_propia`

Pagos importados:

- `lote_id`
- `dni`
- `operacion`
- `entidad`
- `nombre_cliente`
- `monto_pagado`
- `fecha`
- `gestor`
- `cosecha`
- `cuenta_recaudo`
- timestamps

Tiene FK hacia `pagos_lotes` con `ON DELETE SET NULL`.

### `promesas_pago`

Promesas y convenios registrados:

- `dni`
- `telefono`
- `operacion`
- `fecha_promesa`
- `fecha_pago`
- `monto`
- `workflow_estado`
- `tipo`
- `nro_cuotas`
- `monto_convenio`
- `monto_cuota`
- `cuota_dia`
- `estado`
- `nota`
- `user_id`
- campos de preaprobacion, aprobacion y rechazo
- timestamps

Tiene FK hacia `users` en `user_id`, `pre_aprobado_por`, `aprobado_por` y `rechazado_por`.

Riesgo: `tipo` en SQL tiene default `parcial`, pero el codigo actual espera `convenio`, `convenio_balon` o `cancelacion`; el modelo normaliza cualquier tipo no reconocido a `convenio`.

### `promesa_operaciones`

Operaciones asociadas a una promesa:

- `promesa_id`
- `operacion`
- `cartera`
- timestamps

Tiene unica `promesa_id` + `operacion` y FK hacia `promesas_pago`.

### `promesa_cuotas`

Cronograma de cuotas:

- `promesa_id`
- `nro`
- `fecha`
- `monto`
- `es_balon`
- timestamps

Tiene FK hacia `promesas_pago` con `ON DELETE CASCADE`.

### `cna_solicitudes`

Solicitudes CNA:

- `correlativo`
- `nro_carta`
- `fecha_pago_realizado`
- `monto_pagado`
- `observacion`
- `dni`
- `titular`
- `producto`
- `operaciones` como JSON validado
- `nota`
- `workflow_estado`
- `user_id`
- campos de preaprobacion, aprobacion y rechazo
- `docx_path`
- `pdf_path`
- timestamps

Tiene unica `nro_carta` e indice por `dni`.

Riesgo: no se observan FKs hacia `users` en los campos de aprobacion ni hacia clientes. El codigo asume relaciones Eloquent, pero la base no las refuerza para CNA.

### `cliente_bloqueos`

Bloqueos de acceso a clientes por usuario:

- `user_id`
- `dni`
- `motivo`
- timestamps

Tiene unica `user_id` + `dni` y FK hacia `users`.

### `personal_access_tokens`

Tabla de Laravel Sanctum.

### `migrations`

Tabla de control de migraciones.

## Migraciones actuales

Migraciones presentes:

- `2025_11_02_220020_update_users_drop_team_supervisor_add_active.php`
- `2025_11_05_172046_add_supervisor_id_to_users.php`
- `2025_11_13_155533_create_asignar_clientes_table.php`
- `2026_01_12_221108_create_cliente_bloqueos_table.php`
- `2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago.php`

Comparacion conceptual:

- Cubren cambios puntuales, no creacion completa del esquema.
- No crean `clientes_cuentas`, `promesas_pago`, `promesa_operaciones`, `promesa_cuotas`, `cna_solicitudes`, `pagos_lotes`, `pagos_propia`, `ccd_clientes` ni `personal_access_tokens`.
- Una migracion elimina `supervisor_id` y otra posterior lo reintroduce. Esto requiere cuidado si se ejecutan sobre ambientes no alineados.
- La tabla real `users` tiene `active` e `is_active`; las migraciones solo consideran `active`.
- El SQL real contiene indices y FKs que no estan representados por migraciones completas.

## Reglas para V3 sobre base de datos

- No ejecutar migraciones actuales sobre produccion.
- Crear un snapshot documentado del SQL real antes de cualquier cambio.
- Reconstruir migraciones desde cero en una rama controlada o usar una migracion baseline.
- Preparar una tabla de equivalencias entre SQL real, modelos y migraciones.
- Validar indices unicos necesarios antes de usar `upsert`.
- Confirmar columnas legacy antes de eliminarlas.

