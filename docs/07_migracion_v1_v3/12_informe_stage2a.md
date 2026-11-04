# Stage 2A — Restauración, enmascaramiento y validación

**Fecha:** 2026-10-21  
**Estado:** Stage 2A completado; Stage 2B **no iniciado y no autorizado**  
**Fuente:** copia histórica local `u480021566_kpinvest_bd` únicamente. No se conectó IMPULSE producción ni se obtuvo data nueva.

## 1. Resumen ejecutivo

- `kp_crm_v1_reference` fue restaurada en el servidor de referencia aislado con **14 tablas, 153 columnas, 18 migraciones y 725,766 filas**.
- `kp_crm_staging_masked` fue generada con las herramientas de enmascaramiento y contiene **725,766 filas**, con los mismos conteos por tabla.
- Conteos, sumas, IDs, fechas, roles, JSON, lotes, promesas y relaciones lógicas coinciden entre origen, referencia y copia enmascarada.
- La validación de privacidad reporta **0 coincidencias exactas de PII en 43 columnas sensibles**, **0 violaciones de patrones sintéticos**, **0 correos no sintéticos**, **0 rutas filtradas** y **0 tokens personales**.
- La validación final de enmascaramiento es `ok=true`. La validación inicial falló por una comparación incorrecta de claves; el verificador se corrigió sin debilitar las comprobaciones de PII. La evidencia inicial se conserva.
- El dump plano temporal fue eliminado. Se conserva únicamente el respaldo cifrado y su clave fuera del repositorio.
- No se hizo commit, push, despliegue ni acción de Stage 2B.

## 2. Alcance y autorización

Autorizado: restaurar la copia histórica local únicamente en `kp_crm_v1_reference`, generar `kp_crm_staging_masked` con las herramientas preparadas, validar privacidad e integridad y entregar este informe agregado.

No autorizado / no ejecutado: Stage 2B, conexión de CRM V3 a staging, uso de producción, recreación de contenedores, borrado de volúmenes, `migrate:fresh`, `db:wipe`, commit, push o despliegue.

## 3. Estado de las bases de staging

| Base | Servidor | Tablas | Filas | Estado |
|---|---|---:|---:|---|
| `kp_crm_v1_reference` | `kpcrm-staging-ref-db` | 14 | 725,766 | Referencia restaurada; ventana de escritura cerrada |
| `kp_crm_staging_masked` | `kpcrm-staging-db` | 14 | 725,766 | Copia enmascarada validada |
| `kp_crm_mask_work` | `kpcrm-staging-db` | 0 | 0 | Vacía |
| `kp_crm_v3_validation` | `kpcrm-staging-db` | 0 | 0 | Vacía; app aislada |
| `kp_crm_restore_rehearsal` | `kpcrm-staging-db` | 0 | 0 | Vacía |

**Hallazgo de higiene:** en el servidor de proceso existe otra base llamada `kp_crm_v1_reference` con **14 tablas, 62 filas y 9 migraciones**. Es un fixture sintético G1.5 (`example.test`), **no** es la referencia restaurada de Stage 2A, no tiene grants para los roles de staging y no tiene acceso desde la app. Se dejó intacta; archivarla/eliminarla requiere autorización explícita.

`personal_access_tokens` contiene **0 filas** en referencia y enmascarada.

## 4. Respaldo y restauración

- Dump plano temporal: `120,161,175` bytes, SHA-256 `6E22F52245ADD072C4695C87133C103D1EBAB6CE7D93E77CA0E0FA71FEE20D29`.
- Respaldo cifrado conservado: `120,161,200` bytes, SHA-256 `A2E108E498B6F8397D4473FBAEF3F2898DDB221FA19989C60D86554B4822009E`; AES-256-CBC, PBKDF2 200,000 iteraciones, SHA-256.
- El descifrado fue verificado contra el hash del dump plano.
- El dump plano y el archivo `.err` temporal fueron eliminados; el manifiesto y el reporte de limpieza registran la eliminación.
- La clave del respaldo y la clave HMAC permanecen fuera del repositorio, con ACL restringida.
- Restauración de referencia: `ok=true`, 14 tablas, 18 migraciones, `write_window_closed=true`, permiso de escritura del usuario de restauración revocado.

## 5. Enmascaramiento

- Filas copiadas: **725,766**.
- Conteos por tabla idénticos al origen: 14/14 tablas.
- Usuarios de prueba sintéticos: 4.
- `personal_access_tokens`: 0.
- Validación final: `ok=true`, sin fallos.

### 5.1 Incidente inicial y corrección

La primera validación detectó diferencias de valores distintos:

| Columna | Inicial origen → enmascarada | Causa |
|---|---|---|
| `pagos_propia.operacion` | 13,113 → 13,105 | 8 variantes de formato normalizan a la misma clave |
| `pagos_propia.cuenta_recaudo` | 5 → 7 | 7 valores binarios/ case-sensitive colapsan a 5 con collation case-insensitive |
| `promesas_pago.telefono` | 2,450 → 2,448 | 2 variantes de formato normalizan a la misma clave |

`Verifier.php` se corrigió para comparar la misma clave normalizada/case-sensitive que usa `Masker.php`. Las comprobaciones de PII exacta no se relajaron. Resultado final: operación 13,105, teléfono 2,448, cuenta binaria 7; **0 coincidencias exactas de PII**. La evidencia del fallo inicial se conserva en `masking_validation_initial_failure.json`.

## 6. Validación de conteos, IDs, montos y fechas

### 6.1 Conteos

| Tabla | Origen | Referencia | Enmascarada |
|---|---:|---:|---:|
| `asignar_clientes` | 222,926 | 222,926 | 222,926 |
| `ccd_clientes` | 222,781 | 222,781 | 222,781 |
| `cliente_bloqueos` | 11 | 11 | 11 |
| `clientes_cuentas` | 229,389 | 229,389 | 229,389 |
| `cna_solicitudes` | 1,457 | 1,457 | 1,457 |
| `migrations` | 18 | 18 | 18 |
| `notifications` | 2 | 2 | 2 |
| `pagos_lotes` | 216 | 216 | 216 |
| `pagos_propia` | 40,780 | 40,780 | 40,780 |
| `personal_access_tokens` | 0 | 0 | 0 |
| `promesa_cuotas` | 2,818 | 2,818 | 2,818 |
| `promesa_operaciones` | 2,739 | 2,739 | 2,739 |
| `promesas_pago` | 2,580 | 2,580 | 2,580 |
| `users` | 49 | 49 | 49 |
| **Total** | **725,766** | **725,766** | **725,766** |

Los checksums de IDs coinciden en las 14 tablas.

### 6.2 Montos

| Campo | Total origen = referencia = enmascarada |
|---|---:|
| `clientes_cuentas.deuda_capital` | 951,026,938.03 |
| `clientes_cuentas.interes` | 638,827,151.76 |
| `clientes_cuentas.deuda_total` | 1,578,101,325.51 |
| `pagos_propia.monto_pagado` | 29,193,546.41 |
| `promesas_pago.monto` | 2,372,789.03 |
| `promesas_pago.monto_convenio` | 2,501,404.16 |
| `promesas_pago.monto_cuota` | 610,077.50 |
| `promesa_cuotas.monto` | 2,507,027.10 |
| `cna_solicitudes.monto_pagado` | 2,157,452.98 |

### 6.3 Fechas y anomalías históricas

Los rangos de las 7 columnas de fecha comparadas coinciden exactamente entre origen, referencia y enmascarada. No se normalizaron fechas ni se corrigieron anomalías:

- 708 promesas con monto cero.
- 3 promesas con fecha histórica antigua.
- 5 fechas de pago de promesa antiguas.
- 0 fechas de pago antiguas en `pagos_propia`.
- 216 lotes; 210 con pagos; 40,780 pagos vinculados.
- Suma declarada de lotes: 175,346.
- Conciliación de lotes: 187 exactos, 29 inconsistentes, 29 sobredeclarados, 0 subdeclarados, 6 sin pagos, 23 con pagos por encima de lo declarado.

## 7. Integridad referencial y esquema

- 14 tablas, 153 columnas, 18 migraciones.
- 29 constraints declarativos: **14 PK, 6 unique, 8 FK, 1 check**.
- 8 foreign keys; 39 nombres de índice.
- 0 huérfanos lógicos en las 8 relaciones verificadas.
- 0 violaciones de unicidad lógica.
- 0 JSON inválidos; 0 diferencias de estructura JSON.
- El origen MySQL reporta `NO ACTION`; MariaDB reporta el equivalente `RESTRICT`. Nombres, tablas, tablas referenciadas y acciones de borrado coinciden tras normalizar esa diferencia de metadatos.
- Evidencia raíz: `schema_constraints.json`, `ok=true`.

## 8. Privacidad y seguridad

- 43 columnas sensibles comparadas por coincidencia exacta: **0 coincidencias**.
- 0 violaciones de patrones sintéticos en textos libres.
- 0 secuencias de 7+ dígitos en campos de texto libre.
- 0 correos no sintéticos.
- 0 rutas de archivo filtradas.
- 0 tokens personales.
- 0 hashes de contraseña con formato inválido.
- JSON de CNA y notificaciones: 0 inválidos, 0 cambios de estructura.

Grants:

- Referencia: `kp_stage_mask`, `kp_stage_ref_ro` y `kp_stage_ro` solo `SELECT`; `kp_stage_restore` sin grant; ventana cerrada.
- Proceso: `kp_stage_app` solo `SELECT/INSERT/UPDATE/DELETE/LOCK` en `kp_crm_v3_validation`; `kp_stage_mask` solo escribe en `kp_crm_staging_masked` y `kp_crm_mask_work`; `kp_stage_ro` solo `SELECT`.
- Sin privilegios `SUPER` ni `GRANT OPTION`.
- CRM V3 no fue conectado a `kp_crm_v1_reference` ni a `kp_crm_staging_masked`.

## 9. Errores y estado final

No queda ningún bloqueo de integridad o privacidad de Stage 2A. El único error relevante fue la validación inicial de valores distintos, corregida y documentada. La validación final, las comprobaciones independientes y la comparación raíz de constraints devuelven `ok=true`.

## 10. Riesgos y pendientes

1. **Fixture sintético duplicado en proceso:** `kp_crm_v1_reference` con 62 filas, 9 migraciones, emails `example.test`, sin grants. No es la referencia de Stage 2A. Debe archivarse/eliminarse solo con autorización.
2. **Usuarios:** 5 usuarios tienen `active=1` e `is_active=0`; el estado se preservó y V3 debe resolverlo explícitamente.
3. **Anomalías históricas:** 708 promesas en cero, 3 fechas antiguas de promesa, 5 fechas antiguas de pago de promesa y 29 lotes inconsistentes; se conservaron como casos de prueba.
4. **Metadatos FK:** `NO ACTION` vs `RESTRICT` es equivalente, pero el DDL de V3 debe normalizarlo de forma deliberada.
5. **Zona horaria:** el origen usa `SYSTEM`; el dump/restore usó UTC. Las fechas DATE coinciden; la semántica de timestamps debe revisarse antes del cutover.
6. **Stage 2B bloqueado:** faltan autorización, rol DDL de V3, plantillas documentales, mapeo de roles, ventana de cutover, RTO/RPO y propiedad del rollback.

## 11. Confirmación de originales intactos

- `u480021566_kpinvest_bd` revalidada: 14 tablas, 153 columnas, 725,766 filas, versión 8.0.42.
- Conteos, checksums de IDs, montos, fechas, roles, JSON, lotes y promesas coinciden con la línea base.
- `source_modified=false`; no se escribió en el origen.
- No se conectó producción ni se obtuvo data nueva.
- No se recrearon contenedores persistentes ni se borraron volúmenes; se usaron contenedores efímeros de herramientas para las verificaciones.
- El CRM existente sigue en ejecución; no fue modificado.
- No hubo commit, push ni despliegue.

## 12. Plan exacto para Stage 2B (no ejecutado)

1. Obtener autorización escrita separada para Stage 2B, ventana de congelamiento, responsable de rollback y RTO/RPO aprobados; confirmar el sign-off de Stage 2A.
2. Aprobar el build de V3, el rol DDL dedicado, la base destino de staging, las plantillas documentales y el mapeo de roles administrador/supervisor/asesor/soporte.
3. Tomar respaldo y snapshot de V3 antes de migrar; no ejecutar `migrate:fresh`, `db:wipe` ni resets destructivos.
4. Ejecutar migraciones V3 en la base de validación aislada con el usuario DDL dedicado; verificar 14 tablas, 153 columnas, 29 constraints, 8 FKs y 39 índices.
5. Implementar ETL idempotente y por lotes desde `kp_crm_staging_masked` hacia el destino V3 de staging; preservar IDs, montos, fechas, estructura JSON y anomalías; no escribir en `kp_crm_v1_reference`.
6. Mapear usuarios y roles, resolver los 5 desajustes `active`/`is_active` y emitir credenciales nuevas o resets; no reutilizar contraseñas ni hashes del origen.
7. Definir y validar plantillas CNA y reescritura de rutas usando almacenamiento sintético de staging.
8. Ejecutar la suite completa de Stage 2B: conteos, IDs, FKs, montos, fechas, JSON, roles, lotes, promesas, control de acceso y PII residual contra la línea base de Stage 2A.
9. Ensayar cutover y rollback, medir RTO/RPO, restaurar el snapshot previo de V3 y demostrar ausencia de pérdida.
10. Detenerse y entregar el informe de Stage 2B. El cutover a producción, commit, push y despliegue requieren autorización explícita separada.

## 13. Evidencia y hashes

| Evidencia | SHA-256 |
|---|---|
| `masking_validation.json` | `18327F3410914A22C4299B4640E1F04FE189CECEA6E5A7439C6610B331DE4410` |
| `mask_manifest.json` | `383837903F2D8CF991BC78A62434A1E129725AC1743D4593FD76B01381F6978A` |
| `stage2a_independent_checks.json` | `FF462D934A783C372518EE3498AE42C220865ACB8DE9D78E9F92FF8E705793D1` |
| `restore_kp_crm_v1_reference.json` | `9E03CDEC58EFE9FCB8D1112469297500628944F531A5A6230C5613899DED2A4B` |
| `schema_constraints.json` | `C2CD4E47F8A747E3A10351E31AED000EC311A3D3A7AD0462E1DCD7727FB9AA23` |
| `source_metrics.json` | `BA7C9EB3C672EF0C3AC7ED43BA372C738C8D4CF477C2D92D83E71B92755002A6` |
| `reference_metrics.json` | `105CD5EEC86910484C5E8CC34AA9626983A88E16B3350490333BCB84BDC9ED77` |
| `source_recheck.json` | `846D9E7D8E48B59DD4857E8D3CE722A6BC29EADD7D75CF703E8DC5758BA653C7` |
| `source_backup.json` | `E01E9BAC0769FE51B3D406E64486DB4FEB87A2872D5E025F4789A1E9B65B525A` |
| `cleanup_report.json` | `A4C40682B0DDA9D9B240571E33D06F6B3EAF6A4F646DF3ACA9779496B21D95AA` |
| `masking_validation_initial_failure.json` | `C704BCF22347F78AA7D7300D7917D16F854EF4030C0D3CE13218FC5B0B9CA57E` |

**Fin de Stage 2A.** Stage 2B no fue iniciado. No se hizo commit, push ni despliegue.
