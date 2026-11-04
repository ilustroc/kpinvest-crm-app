# 03. Matriz definitiva V1/V3

> **Estado: propuesta. Basada en lectura de metadatos y agregados, sin
> modificaciones.**
>
> Fuente V1: `u480021566_kpinvest_bd` (MySQL 8.0.42 local, copia histórica de
> mayo de 2026). Fuente V3: migraciones del repositorio y bases
> `kpinvest_v3_migrate_test` / `kp_crm_local`. Producción no verificada.

## 1. Alcance

Se comparan:

- 13 tablas V3 y la tabla `notifications`;
- nombres, tipos, longitudes, nulabilidad, defaults y `extra`;
- índices, claves únicas, FKs y checks;
- charset/collation;
- triggers, vistas, rutinas y eventos;
- historial de migraciones;
- roles y estados de usuarios;
- relaciones huérfanas;
- referencias documentales.

No se comparan valores personales. Los conteos son agregados.

## 2. Inventario de tablas

| Tabla | Filas V1 | PK | Claves/índices relevantes | FKs | Estado V3 | Acción |
|---|---:|---|---|---|---|---|
| `users` | 49 | `id` | unique `email`; index `supervisor_id` | 0 | Existe | Baseline; enmascarar PII; preservar roles |
| `clientes_cuentas` | 229,389 | `id` | index `(numdoc, entidad, moneda, deuda_total, deuda_capital)` | 0 | Existe | Copiar/enmascarar |
| `cliente_bloqueos` | 11 | `id` | unique `(user_id, dni)`; index `dni` | `user_id -> users.id` | Existe | Copiar/enmascarar |
| `pagos_lotes` | 216 | `id` | Ninguno adicional | 0 | Existe | Copiar; enmascarar `archivo` |
| `pagos_propia` | 40,780 | `id` | index `lote_id` | `lote_id -> pagos_lotes.id` | Existe | Copiar/enmascarar |
| `promesas_pago` | 2,580 | `id` | indexes `dni`, `operacion`, `workflow_estado`, `user_id`, aprobadores | 4 FKs a `users` | Existe | Copiar/enmascarar |
| `promesa_cuotas` | 2,818 | `id` | index `(promesa_id, nro)` | `promesa_id -> promesas_pago.id` | Existe | Copiar |
| `promesa_operaciones` | 2,739 | `id` | unique `(promesa_id, operacion)`; indexes `cartera`, `operacion` | `promesa_id -> promesas_pago.id` | Existe | Copiar; mapear operación |
| `cna_solicitudes` | 1,457 | `id` | unique `nro_carta`; index `dni`; check JSON | 0 | Existe | Copiar/enmascarar |
| `asignar_clientes` | 222,926 | `id` | unique `(numdoc, operacion)`; indexes `numdoc`, `operacion` | 0 | Existe | Copiar/enmascarar |
| `ccd_clientes` | 222,781 | `id` | Ninguno adicional | 0 | Existe | Copiar/enmascarar |
| `notifications` | 2 | `id` (char 36) | index `(notifiable_type, notifiable_id)` | 0 | Existe en V1/local; **falta en test** | Copiar; reemplazar `data` |
| `personal_access_tokens` | 0 | `id` | unique `token`; index morph | 0 | Existe | No copiar; tabla vacía |
| `migrations` | 18 | `id` | — | 0 | Existe | Copiar solo a V1 enmascarada; reconstruir en validación |

## 3. Columnas, tipos y defaults

La comparación de las 14 tablas dio **0 diferencias sustantivas** de nombre,
tipo, longitud, nulabilidad o default en las columnas V3. Las diferencias
observadas son de representación del motor:

| Diferencia | V1 MySQL 8.0.42 | V3 MariaDB 11.8.9 / test MySQL 8.0.42 | Impacto |
|---|---|---|---|
| Ancho de enteros | `bigint(20)`, `int(10) unsigned` | `bigint`, `int unsigned` | Cosmético |
| Default timestamp | `DEFAULT_GENERATED`, `CURRENT_TIMESTAMP` | `current_timestamp()` | Semánticamente igual |
| `ON UPDATE` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | `on update current_timestamp()` | Semánticamente igual |
| Regla FK de update | `NO ACTION` | `RESTRICT` | Equivalente en InnoDB |
| Nombre de check | `cna_solicitudes_chk_1` | `cna_solicitudes_operaciones_json_check` | Cosmético; mismo `json_valid` |
| `lower_case_table_names` | 1 (Windows MySQL) | 0 (Linux MariaDB) | Requiere prueba; tablas en minúsculas |
| `sql_mode` | Estricto + `ONLY_FULL_GROUP_BY` | Por defecto sin varios modos | Requiere prueba; ver documento 06 |

No se encontraron columnas faltantes, sobrantes, con otro tipo o con otro
default en el núcleo V3.

### 3.1 Columnas clave por tabla

| Tabla | Columnas principales |
|---|---|
| `users` | `id`, `name`, `email`, `role`, `supervisor_id`, `password`, `active`, `is_active`, timestamps |
| `clientes_cuentas` | `id`, `numdoc`, `cuenta`, `nombre`, `dpto`, `operacion`, `entidad`, `producto`, `cosecha`, `moneda`, `fecha_compra`, `fecha_castigo`, `deuda_capital`, `interes`, `deuda_total`, `direccion`, `provincia`, `distrito`, timestamps |
| `cliente_bloqueos` | `id`, `user_id`, `dni`, `motivo`, timestamps |
| `pagos_lotes` | `id`, `tipo`, `archivo`, `usuario_id`, `total_registros`, timestamps |
| `pagos_propia` | `id`, `lote_id`, `dni`, `operacion`, `entidad`, `nombre_cliente`, `monto_pagado`, `fecha`, `gestor`, `cosecha`, `cuenta_recaudo`, timestamps |
| `promesas_pago` | `id`, `dni`, `telefono`, `operacion`, `fecha_promesa`, `fecha_pago`, `monto`, `workflow_estado`, `tipo`, `nro_cuotas`, `monto_convenio`, `monto_cuota`, `cuota_dia`, `estado`, `nota`, `user_id`, aprobadores, timestamps |
| `promesa_cuotas` | `id`, `promesa_id`, `nro`, `fecha`, `monto`, `es_balon`, timestamps |
| `promesa_operaciones` | `id`, `promesa_id`, `operacion`, `cartera`, timestamps |
| `cna_solicitudes` | `id`, `correlativo`, `nro_carta`, `fecha_pago_realizado`, `monto_pagado`, `observacion`, `dni`, `titular`, `producto`, `operaciones`, `nota`, `workflow_estado`, `user_id`, aprobadores, `docx_path`, `pdf_path`, timestamps |
| `asignar_clientes` | `id`, `numdoc`, `operacion`, `name` |
| `ccd_clientes` | `id`, `numdoc`, `pdf`, `cosecha`, `link` |
| `notifications` | `id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, timestamps |
| `personal_access_tokens` | `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, timestamps |
| `migrations` | `id`, `migration`, `batch` |

## 4. Índices y restricciones únicas

| Tabla | Restricción | Columnas |
|---|---|---|
| `users` | `users_email_unique` | `email` |
| `users` | `users_supervisor_id_idx` | `supervisor_id` |
| `cliente_bloqueos` | `cliente_bloqueos_user_id_dni_unique` | `user_id`, `dni` |
| `cna_solicitudes` | `cna_solicitudes_nro_carta_unique` | `nro_carta` |
| `asignar_clientes` | `unique_asignacion_cliente` | `numdoc`, `operacion` |
| `promesa_operaciones` | `promesa_operaciones_promesa_id_operacion_unique` | `promesa_id`, `operacion` |
| `personal_access_tokens` | `personal_access_tokens_token_unique` | `token` |

Índices no únicos relevantes:

- `clientes_cuentas.idx_cc_numdoc_search`
- `clientes_cuentas` no tiene unique de cliente/cuenta; por eso se observaron
  5 grupos de claves duplicadas.
- `pagos_propia.fk_pagos_lote`
- `promesas_pago` índices de `dni`, `operacion`, `workflow_estado` y
  aprobadores.
- `promesa_cuotas (promesa_id, nro)`
- `promesa_operaciones` índices de `cartera` y `operacion`
- `asignar_clientes` índices de `numdoc` y `operacion`
- `notifications` índice morph
- `personal_access_tokens` índice morph

## 5. Claves foráneas

| Tabla | FK | Referencia | Delete | Update |
|---|---|---|---|---|
| `cliente_bloqueos` | `cliente_bloqueos_user_id_foreign` | `users.id` | `CASCADE` | `NO ACTION` / `RESTRICT` |
| `pagos_propia` | `fk_pagos_propia_lote` | `pagos_lotes.id` | `SET NULL` | `NO ACTION` / `RESTRICT` |
| `promesa_cuotas` | `promesa_cuotas_promesa_id_foreign` | `promesas_pago.id` | `CASCADE` | `NO ACTION` / `RESTRICT` |
| `promesa_operaciones` | `promesa_operaciones_promesa_id_foreign` | `promesas_pago.id` | `CASCADE` | `NO ACTION` / `RESTRICT` |
| `promesas_pago` | `promesas_pago_user_id_foreign` | `users.id` | `SET NULL` | `NO ACTION` / `RESTRICT` |
| `promesas_pago` | `promesas_pago_pre_aprobado_por_foreign` | `users.id` | `SET NULL` | `NO ACTION` / `RESTRICT` |
| `promesas_pago` | `promesas_pago_aprobado_por_foreign` | `users.id` | `SET NULL` | `NO ACTION` / `RESTRICT` |
| `promesas_pago` | `promesas_pago_rechazado_por_foreign` | `users.id` | `SET NULL` | `NO ACTION` / `RESTRICT` |

`NO ACTION` y `RESTRICT` son equivalentes para estas FK inmediatas en InnoDB.
No se deben "corregir" solo por el nombre.

## 6. Checks

| Tabla | Check | V1 | V3 | Estado |
|---|---|---|---|---|
| `cna_solicitudes` | `json_valid(operaciones)` | `cna_solicitudes_chk_1` | `cna_solicitudes_operaciones_json_check` | Equivalente |
| Prototipo `pago` | `monto >= 0` | No aplica | Solo en test | Ignorar; no es V1 |
| Prototipo `promesa` | `monto_compromiso >= 0` | No aplica | Solo en test | Ignorar; no es V1 |

No hay otros checks en las tablas V3.

## 7. Charset y collation

- 14/14 tablas V1: InnoDB, `utf8mb4_unicode_ci`.
- 153 columnas V1: 66 con `utf8mb4_unicode_ci`, 1 con `utf8mb4_bin`
  (`cna_solicitudes.operaciones`), 86 sin charset (numéricas/fechas).
- Test: 22 tablas InnoDB `utf8mb4_unicode_ci`; 213 columnas: 80 unicode,
  1 bin, 132 sin charset (incluye prototipo).
- Local: 14 tablas InnoDB `utf8mb4_unicode_ci`; 66 unicode, 1 bin, 19 sin
  charset.
- No se observaron columnas `utf8mb4_0900_*` en V1. Si un dump fresco las
  trae, MariaDB no las acepta; debe abortarse el preflight.
- `utf8mb4_bin` en `operaciones` es intencional; no cambiar a `unicode_ci`
  porque el check y las comparaciones JSON podrían cambiar.

## 8. Objetos de base

| Objeto | V1 | Test | Local |
|---|---:|---:|---:|
| Triggers | 0 | 0 | 0 |
| Vistas | 0 | 0 | 0 |
| Rutinas | 0 | 0 | 0 |
| Eventos | 0 | 0 | 0 |

No hay lógica oculta en la base. Si el dump fresco trae triggers, vistas,
rutinas o eventos, se debe revisar y decidir antes de importar; no se copian
`DEFINER` de producción.

## 9. Historial de migraciones

| Base | Filas | Estado |
|---|---:|---|
| `u480021566_kpinvest_bd` | 18 | Híbrido V1+V3; todas las V3 marcadas |
| `kpinvest_v3_migrate_test` | 8 | V3 limpio, sin `notifications` |
| `kp_crm_local` | 9 | V3 limpio, con `notifications` |

Historial de `u480`:

```text
2019_12_14_000001_create_personal_access_tokens_table
2025_08_15_164655_create_pagos_lotes_table
2025_08_15_164655_create_pagos_propia_table
2025_08_15_192105_create_pagos_caja_cusco_castigada_table
2025_11_02_220020_update_users_drop_team_supervisor_add_active
2025_11_05_172046_add_supervisor_id_to_users
2025_11_07_224706_agregar_link_descarga
2025_11_13_155533_create_asignar_clientes_table
2026_01_12_221108_create_cliente_bloqueos_table
2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago
2026_05_15_000001_create_users_table
2026_05_15_000002_create_clientes_tables
2026_05_15_000003_create_pagos_tables
2026_05_15_000004_create_promesas_tables
2026_05_15_000005_create_cna_solicitudes_table
2026_05_15_000006_create_integracion_tables
2026_05_15_000007_normalize_user_roles_v3
2026_05_21_122013_create_notifications_table
```

Conclusión: el historial de `u480` no es evidencia de un baseline V3 limpio.
Para la validación V3 se reconstruye el historial desde las migraciones, no se
copia el historial híbrido. Para producción se decide con el dump fresco y la
comparación de esquema, no con la tabla `migrations` local.

## 10. Roles y estados

| Rol | Filas |
|---|---:|
| `administrador` | 1 |
| `supervisor` | 3 |
| `asesor` | 44 |
| `soporte` | 1 |

- 44 activos, 5 inactivos.
- 5 filas con `active <> is_active`.
- 0 filas con roles antiguos `sistemas` o `usuario`.
- `role` es `ENUM('administrador','supervisor','asesor','soporte') NOT NULL DEFAULT 'soporte'`.
- `active` e `is_active` son `TINYINT(1) NOT NULL DEFAULT 1`.

**Regla:** no se normaliza ningún rol automáticamente. Si el dump fresco trae
roles antiguos, se detiene la migración y se aplica un mapeo aprobado.

## 11. Integridad referencial observada

Se verificaron las 8 FKs y no se encontraron filas huérfanas. Esto aplica a la
copia local; debe repetirse sobre el dump fresco.

No se debe asumir que los datos de negocio son consistentes solo porque las
FK están bien. Los defectos de conciliación se documentan en
[05_integridad_conciliacion.md](05_integridad_conciliacion.md).

## 12. Tablas prototipo que no se migran

`kpinvest_v3_migrate_test` contiene 9 tablas normalizadas de un prototipo:

- `asignacion`
- `cliente`
- `cuenta`
- `gestion`
- `pago`
- `promesa`
- `tipificacion_n1`
- `tipificacion_n2`
- `usuario`

Están vacías, sin migraciones, creadas el 2026-09-09, y no corresponden al
esquema V1. **No se incluyen en la matriz de migración ni se copian a V3.**

## 13. Referencias documentales

| Origen | Referencias | Formato observado | Archivos en repo |
|---|---:|---|---|
| `cna_solicitudes.docx_path` | 1,415 no vacías, 1,415 distintas | Relativas, con directorio, `.docx`, sin URL ni ruta absoluta | No |
| `cna_solicitudes.pdf_path` | 1,330 no vacías, 1,330 distintas | Relativas, con directorio, `.pdf`, sin URL ni ruta absoluta | No |
| `ccd_clientes.pdf` | 222,781 no vacías, 222,781 distintas | Sin directorio, `.pdf`, sin URL | No |
| `ccd_clientes.link` | 222,781 no vacías, 222,781 distintas | Con directorio, sin URL | No |

`storage/app/cna/docx`, `storage/app/cna/pdf` y `storage/app/templates` no
contienen archivos DOCX/PDF en el repositorio. La generación y descarga
documental no se puede validar sin placeholders o sin los assets autorizados
de staging. No se copian documentos reales al repositorio.

## 14. Árbol de decisión de baseline

```text
Dump fresco autorizado
        |
        v
¿Existen las 14 tablas y coinciden 153 columnas?
   | no                         | sí
   v                            v
Crear/corregir solo lo       ¿Roles son los 4 de V3?
faltante con migración          | no              | sí
revisada                        v                 v
                           DETENER: mapeo     ¿notifications existe?
                           de roles aprobado       | no          | sí
                                                   v             v
                                            crear tabla     marcar baseline
                                            notifications   000001-000007
                                                   |             |
                                                   +------+------+
                                                          v
                                              validar 0 drift y datos
                                                          |
                                                          v
                                              aplicar solo incrementales
```

Reglas:

1. No ejecutar `migrate:fresh`.
2. No editar migraciones ya aplicadas.
3. No copiar el historial de `u480` a V3.
4. No marcar `000007` si hay roles antiguos sin aprobación.
5. No crear `notifications` si ya existe; la migración es idempotente.
6. Toda marca de baseline debe quedar en un SQL revisado y auditado.

## 15. Criterios de aceptación

- [ ] 14/14 tablas presentes y 0 drift sustantivo.
- [ ] 153 columnas V3 verificadas (incluida `notifications`).
- [ ] 6 restricciones únicas y los índices no únicos verificados.
- [ ] 8 FKs verificadas, 0 huérfanos.
- [ ] Check JSON válido en 1,457 CNA.
- [ ] Collations iguales, salvo `operaciones` bin.
- [ ] 0 triggers/vistas/rutinas/eventos, o revisión explícita.
- [ ] Roles y estados contabilizados sin normalizar.
- [ ] `notifications` presente o creada de forma idempotente.
- [ ] 9 tablas prototipo excluidas.
- [ ] Rutas documentales inventariadas y placeholders definidos.

## 16. Decisiones abiertas

1. Mapeo de roles si el dump fresco trae `sistemas`/`usuario`.
2. Tratamiento de `nro_carta`/`correlativo` en el enmascaramiento.
3. Copia completa enmascarada o subconjunto de UI.
4. Assets documentales: placeholders o storage autorizado.
5. Perfil de `sql_mode` para la validación.
