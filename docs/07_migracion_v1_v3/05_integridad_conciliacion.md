# 05. Verificaciones de integridad y conciliación

> **Estado: propuesta. No ejecutada.**
>
> Todas las consultas de este documento devuelven conteos, sumas o
> distribuciones. No se seleccionan nombres, DNI, correos, teléfonos,
> direcciones ni contraseñas.

## 1. Objetivo

Demostrar que la copia enmascarada y la base V3:

- conservan las filas, relaciones, importes, fechas, estados y roles;
- no introducen huérfanos, duplicados nuevos ni cambios de esquema;
- mantienen los defectos de datos conocidos, sin "corregirlos" de forma
  silenciosa;
- no contienen PII real.

## 2. Principios

1. **No corregir durante la migración.** Un duplicado o un total de lote
   inconsistente se reporta; no se limpia sin una decisión funcional.
2. **Comparar agregados, no filas.** Los reportes no muestran valores
   personales.
3. **Congelar las claves lógicas.** Antes de ejecutar, el responsable de datos
   aprueba las claves de duplicado de pagos, promesas y cuentas.
4. **La fuente manda.** Si la copia local y el dump fresco difieren, el dump
   fresco reemplaza los conteos de referencia.
5. **Evidencia reproducible.** Cada reporte lleva fecha, base, versión de
   motor y checksum del script.

## 3. Comprobaciones estructurales

### 3.1 Tablas y columnas

```sql
SELECT COUNT(*) AS tablas
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '<base>';

SELECT COUNT(*) AS columnas
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '<base>';

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '<base>'
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

Esperado en V1/V3: 14 tablas, 153 columnas. En el test de 22 tablas, 213
columnas: 145 de las 13 tablas V3 presentes (falta `notifications`, 8
columnas) y 68 de las 9 tablas prototipo.

### 3.2 Índices, unique y checks

```sql
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '<base>'
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;

SELECT COUNT(*) AS checks
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = '<base>';
```

Esperado: 6 restricciones únicas y 1 check `json_valid(operaciones)`.

### 3.3 FKs y huérfanos

```sql
SELECT COUNT(*) AS huerfanos_bloqueos
FROM cliente_bloqueos c
LEFT JOIN users u ON u.id = c.user_id
WHERE u.id IS NULL;

SELECT COUNT(*) AS huerfanos_pagos
FROM pagos_propia p
LEFT JOIN pagos_lotes l ON l.id = p.lote_id
WHERE p.lote_id IS NOT NULL AND l.id IS NULL;

SELECT COUNT(*) AS huerfanos_cuotas
FROM promesa_cuotas c
LEFT JOIN promesas_pago p ON p.id = c.promesa_id
WHERE p.id IS NULL;

SELECT COUNT(*) AS huerfanos_operaciones
FROM promesa_operaciones o
LEFT JOIN promesas_pago p ON p.id = o.promesa_id
WHERE p.id IS NULL;

SELECT COUNT(*) AS huerfanos_promesas
FROM promesas_pago p
LEFT JOIN users u ON u.id = p.user_id
WHERE p.user_id IS NOT NULL AND u.id IS NULL;

-- Repetir para pre_aprobado_por, aprobado_por y rechazado_por.
```

Esperado: 0 en todas. La copia local ya dio 0; debe repetirse en el dump
fresco.

### 3.4 Unicidad

```sql
SELECT COUNT(*) AS dup_users_email
FROM (
  SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS dup_cna_nro
FROM (
  SELECT nro_carta FROM cna_solicitudes GROUP BY nro_carta HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS dup_asignaciones
FROM (
  SELECT numdoc, operacion FROM asignar_clientes
  GROUP BY numdoc, operacion HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS dup_promesa_operacion
FROM (
  SELECT promesa_id, operacion FROM promesa_operaciones
  GROUP BY promesa_id, operacion HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS dup_bloqueo
FROM (
  SELECT user_id, dni FROM cliente_bloqueos
  GROUP BY user_id, dni HAVING COUNT(*) > 1
) d;
```

Esperado: 0 en todas.

## 4. Comprobaciones de negocio

### 4.1 Conteos base

```sql
SELECT 'users' t, COUNT(*) n FROM users
UNION ALL SELECT 'clientes_cuentas', COUNT(*) FROM clientes_cuentas
UNION ALL SELECT 'cliente_bloqueos', COUNT(*) FROM cliente_bloqueos
UNION ALL SELECT 'pagos_lotes', COUNT(*) FROM pagos_lotes
UNION ALL SELECT 'pagos_propia', COUNT(*) FROM pagos_propia
UNION ALL SELECT 'promesas_pago', COUNT(*) FROM promesas_pago
UNION ALL SELECT 'promesa_cuotas', COUNT(*) FROM promesa_cuotas
UNION ALL SELECT 'promesa_operaciones', COUNT(*) FROM promesa_operaciones
UNION ALL SELECT 'cna_solicitudes', COUNT(*) FROM cna_solicitudes
UNION ALL SELECT 'asignar_clientes', COUNT(*) FROM asignar_clientes
UNION ALL SELECT 'ccd_clientes', COUNT(*) FROM ccd_clientes
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'personal_access_tokens', COUNT(*) FROM personal_access_tokens
UNION ALL SELECT 'migrations', COUNT(*) FROM migrations;
```

### 4.2 Sumas monetarias

```sql
SELECT 'clientes_cuentas' t,
       SUM(deuda_capital) capital,
       SUM(interes) interes,
       SUM(deuda_total) total
FROM clientes_cuentas
UNION ALL
SELECT 'pagos_propia', NULL, NULL, SUM(monto_pagado) FROM pagos_propia
UNION ALL
SELECT 'promesas_pago', NULL, NULL, SUM(monto) FROM promesas_pago
UNION ALL
SELECT 'promesa_cuotas', NULL, NULL, SUM(monto) FROM promesa_cuotas
UNION ALL
SELECT 'cna_solicitudes', NULL, NULL, SUM(monto_pagado) FROM cna_solicitudes;
```

Las sumas deben coincidir entre V1 enmascarada y V3 validación. No deben
coincidir necesariamente con `pagos_lotes.total_registros`; ver 4.3.

### 4.3 Conciliación de lotes

```sql
SELECT COUNT(*) AS lotes,
       SUM(total_registros) AS declarado,
       (SELECT COUNT(*) FROM pagos_propia) AS pagos_reales
FROM pagos_lotes;

SELECT
  SUM(actual = declarado) AS exactos,
  SUM(actual <> declarado) AS inconsistentes,
  SUM(actual = 0) AS sin_pagos,
  SUM(declarado > actual) AS declara_mas,
  SUM(declarado < actual) AS declara_menos,
  SUM(actual = 0 AND declarado > 0) AS sobre_sin_pagos,
  SUM(actual > 0 AND declarado > actual) AS sobre_con_pagos
FROM (
  SELECT l.id, l.total_registros AS declarado, COUNT(p.id) AS actual
  FROM pagos_lotes l
  LEFT JOIN pagos_propia p ON p.lote_id = l.id
  GROUP BY l.id, l.total_registros
) x;
```

Hallazgo de la copia local:

- 216 lotes en total.
- 187 con conteo exacto.
- 29 inconsistentes, todos por sobredeclaración:
  - 23 con pagos vinculados y total declarado mayor.
  - 6 sin pagos vinculados y total declarado mayor.
- 0 subdeclarados.
- 175,346 registros declarados vs 40,780 pagos reales.
- "216" es el total de lotes; "29" es el subconjunto inconsistente. No sumar
  187 + 29 + 6: el total se divide en 187 exactos + 29 inconsistentes.

**No corregir estos totales en la migración.** Si V3 cambia el número, es un
defecto de migración. La limpieza de datos es un trabajo separado.

### 4.4 Duplicados de negocio

La auditoría previa detectó:

| Caso | Grupos | Filas extra |
|---|---:|---:|
| Claves de cuenta de cliente (según `idx_cc_numdoc_search`) | 5 | — |
| Claves de pago | 729 | 1,040 |
| Claves de promesa | 26 | 31 |
| Grupos de `numdoc` en CCD | 13,824 | Esperado: varios documentos por cliente |

Antes de ejecutar, el responsable de datos debe congelar la clave lógica
exacta usada en cada conteo. Ejemplo de consulta, sin mostrar claves:

```sql
SELECT COUNT(*) AS grupos_dup,
       SUM(n - 1) AS filas_extra
FROM (
  SELECT <clave_logica>, COUNT(*) AS n
  FROM <tabla>
  GROUP BY <clave_logica>
  HAVING COUNT(*) > 1
) d;
```

Los 13,824 grupos de CCD no son un error: un cliente puede tener varios
documentos. No se deduplican.

### 4.5 Fechas inválidas

```sql
SELECT COUNT(*) AS promesas_antes_2000
FROM promesas_pago
WHERE (fecha_promesa IS NOT NULL AND fecha_promesa < '2000-01-01')
   OR (fecha_pago IS NOT NULL AND fecha_pago < '2000-01-01');

SELECT COUNT(*) AS pagos_antes_2000
FROM pagos_propia
WHERE fecha IS NOT NULL AND fecha < '2000-01-01';

SELECT MIN(fecha_promesa) AS min_promesa,
       MIN(fecha_pago) AS min_pago
FROM promesas_pago;

SELECT MIN(fecha) AS min_pago FROM pagos_propia;
```

Hallazgo local: 3 promesas y 5 pagos anteriores al año 2000; fechas mínimas
`0026-01-23` y `0005-12-30`. MariaDB 11.8.9 acepta esas fechas en modo
estricto, pero debe confirmarse en el dump fresco.

**No corregir fechas** durante la migración. Si una fecha impide la
importación, se documenta y se decide con el responsable funcional.

### 4.6 Promesas con monto cero

```sql
SELECT COUNT(*) AS promesas_monto_cero
FROM promesas_pago
WHERE monto = 0;
```

Hallazgo local: 708. Se preservan.

### 4.7 Usuarios y roles

```sql
SELECT role, COUNT(*) n FROM users GROUP BY role ORDER BY role;

SELECT active, is_active, COUNT(*) n
FROM users
GROUP BY active, is_active
ORDER BY active, is_active;

SELECT COUNT(*) AS inconsistentes
FROM users
WHERE active <> is_active;
```

Hallazgo local:

| Rol | Filas |
|---|---:|
| administrador | 1 |
| supervisor | 3 |
| asesor | 44 |
| soporte | 1 |

44 activos, 5 inactivos, 5 con `active <> is_active`, 0 roles antiguos.

**No normalizar roles ni `active`/`is_active`.** Cualquier cambio requiere
aprobación.

### 4.8 CNA y JSON

```sql
SELECT COUNT(*) AS total,
       SUM(JSON_VALID(operaciones) = 0) AS invalidos,
       SUM(JSON_TYPE(operaciones) = 'ARRAY') AS arrays,
       SUM(JSON_TYPE(operaciones) = 'OBJECT') AS objects,
       MIN(JSON_LENGTH(operaciones)) AS min_ops,
       MAX(JSON_LENGTH(operaciones)) AS max_ops
FROM cna_solicitudes;
```

Hallazgo local: 1,457 filas, 0 inválidas, 1,457 arrays, longitud 1–3.

Verificar además que cada operación del JSON exista en
`clientes_cuentas.operacion` enmascarada y que no queden operaciones sin token.

### 4.9 Referencias documentales

```sql
SELECT COUNT(*) AS cna_total,
       SUM(docx_path IS NOT NULL AND docx_path <> '') AS docx,
       SUM(pdf_path IS NOT NULL AND pdf_path <> '') AS pdf,
       SUM(docx_path LIKE 'http%') AS docx_url,
       SUM(pdf_path LIKE 'http%') AS pdf_url,
       SUM(docx_path LIKE '/%') AS docx_abs,
       SUM(pdf_path LIKE '/%') AS pdf_abs
FROM cna_solicitudes;

SELECT COUNT(*) AS ccd_total,
       SUM(pdf IS NOT NULL AND pdf <> '') AS pdf,
       SUM(link IS NOT NULL AND link <> '') AS link,
       SUM(pdf LIKE 'http%') AS pdf_url,
       SUM(link LIKE 'http%') AS link_url,
       SUM(pdf LIKE '/%') AS pdf_abs,
       SUM(link LIKE '/%') AS link_abs
FROM ccd_clientes;
```

Hallazgo local: CNA 1,415 DOCX y 1,330 PDF; CCD 222,781 PDF y 222,781 link;
sin URLs ni rutas absolutas. No hay archivos DOCX/PDF en el repositorio.

## 5. Conciliación V1 enmascarada → V3

### 5.1 Filas por tabla

Para cada tabla copiada:

```sql
SELECT 'users' t,
  (SELECT COUNT(*) FROM kp_crm_staging_masked.users) v1,
  (SELECT COUNT(*) FROM kp_crm_v3_validation.users) v3
UNION ALL SELECT 'clientes_cuentas',
  (SELECT COUNT(*) FROM kp_crm_staging_masked.clientes_cuentas),
  (SELECT COUNT(*) FROM kp_crm_v3_validation.clientes_cuentas)
-- repetir para las demás tablas copiadas
;
```

Esperado: V1 = V3 en todas las tablas copiadas.

### 5.2 PK sets

```sql
SELECT COUNT(*) AS faltan_en_v3
FROM kp_crm_staging_masked.clientes_cuentas a
LEFT JOIN kp_crm_v3_validation.clientes_cuentas b ON b.id = a.id
WHERE b.id IS NULL;

SELECT COUNT(*) AS sobran_en_v3
FROM kp_crm_v3_validation.clientes_cuentas b
LEFT JOIN kp_crm_staging_masked.clientes_cuentas a ON a.id = b.id
WHERE a.id IS NULL;
```

Esperado: 0 y 0. Repetir para cada tabla con PK `id`. Para `notifications`,
comparar `id`, `type`, `read_at` y fechas; `data` es sintético y no se compara
valor a valor.

### 5.3 Agregados de negocio

Deben coincidir:

- suma de deudas, pagos, promesas y cuotas;
- distribución de roles y estados;
- cantidad de CNA y de operaciones por CNA;
- cantidad de promesas con monto cero;
- fechas mínimas/máximas y casos inválidos;
- cantidad de referencias documentales (aunque las rutas cambien).

### 5.4 PII residual

La comparación se hace con hashes, no con valores:

1. Calcular `source_hash` de cada identificador de la fuente.
2. Buscar ese hash en el mapa y en la base enmascarada.
3. Reportar `COUNT(*)` de coincidencias exactas del mismo dominio.
4. Reportar conteos de patrones de email/teléfono/DNI en texto libre.
5. Revisar una muestra agregada de textos y notificaciones, sin imprimir
   valores.

El ETL rechaza cualquier token que coincida con un identificador real del
mismo dominio, por lo que el criterio es 0 coincidencias exactas de PII de
origen.

## 6. Casos conocidos que no se corrigen

| Caso | Valor local | Tratamiento |
|---|---:|---|
| Lotes con total inconsistente | 216 total; 187 exactos; 29 sobredeclarados (23 con pagos, 6 sin pagos); 0 subdeclarados; 175,346 declarado vs 40,780 real | Reportar; no corregir |
| Duplicados de cuenta | 5 grupos | Reportar; no deduplicar |
| Duplicados de pago | 729 grupos, 1,040 extra | Reportar; no deduplicar |
| Duplicados de promesa | 26 grupos, 31 extra | Reportar; no deduplicar |
| CCD con múltiples `numdoc` | 13,824 grupos | Esperado; no deduplicar |
| Fechas anteriores a 2000 | 3 promesas, 5 pagos | Preservar; no corregir |
| Promesas con monto cero | 708 | Preservar |
| Usuarios con `active <> is_active` | 5 | Preservar; no normalizar |
| Roles antiguos | 0 en copia local | Si aparecen en dump fresco, detener |
| Documentos ausentes | 0 archivos en repo | Placeholders de staging |

## 7. Umbrales de aceptación

### 7.1 Fuente → enmascarada

- [ ] 100% de filas copiadas, salvo `personal_access_tokens`.
- [ ] 0 huérfanos.
- [ ] 0 violaciones de PK/unique.
- [ ] 100% JSON válido.
- [ ] Sumas y distribuciones exactas.
- [ ] 0 coincidencias exactas de PII.
- [ ] Rutas y textos sintéticos.

### 7.2 Enmascarada → V3

- [ ] 100% de tablas y filas copiadas.
- [ ] 0 PK faltantes/sobrantes.
- [ ] 0 huérfanos.
- [ ] 0 nuevas violaciones de unique.
- [ ] Sumas y estados exactos.
- [ ] Casos conocidos con los mismos conteos.
- [ ] `notifications` con el mismo número de filas y tipos.
- [ ] `personal_access_tokens` vacía.

### 7.3 Cierre

- [ ] Informe firmado por técnico y funcional.
- [ ] Desviaciones documentadas.
- [ ] Ninguna corrección de datos sin aprobación.
- [ ] Evidencias sin PII archivadas.

## 8. Formato de evidencia

Guardar un reporte agregado, por ejemplo JSON/CSV, con:

- fecha y hora;
- base y motor;
- versión del script;
- checksum del dump;
- nombres de bases;
- conteos, sumas, mínimos/máximos y distribuciones;
- resultado de cada check;
- desviaciones y aprobaciones.

No incluir filas, claves de negocio, nombres, DNI, correos, teléfonos,
direcciones, rutas reales ni capturas de pantalla con datos.

## 9. Conciliación posterior al cutover

En la ventana de cutover, después de congelar escrituras:

1. Ejecutar los mismos conteos y sumas.
2. Comparar contra el respaldo pre-cutover.
3. Verificar `MAX(id)` y cantidad de filas nuevas.
4. Repetir la conciliación de lotes y roles.
5. Confirmar que no hay PII en logs ni reportes.
6. Archivar el reporte y firmar el cierre.

Si hay diferencias no explicadas, se aborta el cutover y se aplica el plan de
reversión. Ver [08_rollback.md](08_rollback.md).
