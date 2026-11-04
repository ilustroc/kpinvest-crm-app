# Migración V1/IMPULSE → V3: paquete de preparación

> **Estado: G0 APROBADO CONDICIONALMENTE (2026-10-20). G1 ETAPA 1 EJECUTADA;
> ETAPA 2 PENDIENTE DE AUTORIZACIÓN. DATOS REALES AÚN NO COPIADOS NI
> TRANSFORMADOS.**
>
> La aprobación autoriza configuración Docker, redes, usuarios/permisos,
> scripts de restauración/enmascaramiento/validación, verificaciones
> automáticas y documentación de reversión. **No** autoriza todavía copiar,
> importar, restaurar ni transformar datos reales, ni conectar V3 a producción.
> Antes de ejecutar esas operaciones se deben revisar los resultados de
> [G1](10_g1_configuracion_y_verificaciones.md) y autorizar la Etapa 2.

## 1. Objetivo

Preparar el reemplazo controlado del CRM V1/IMPULSE por el CRM V3, conservando
la información histórica y sin interrumpir las operaciones, mediante:

- una copia local aislada y enmascarada de la base V1;
- una base separada para validar el esquema V3 sobre esa copia;
- restauración, conciliación, reversión y checklist funcional documentados;
- una decisión explícita sobre roles y baseline antes de tocar producción.

La copia local `u480021566_kpinvest_bd` se usa únicamente como referencia
histórica de mayo de 2026. **No es un respaldo actualizado de producción.**

## 2. Decisiones ya aprobadas

| Decisión | Estado |
|---|---|
| Estrategia de baseline mixto con staging separado | Aprobada, solo para preparación y validación local |
| Usar una copia enmascarada de la base local V1, no solo datos sintéticos | Aprobada |
| Mantener `u480021566_kpinvest_bd`, `kpinvest_v3_migrate_test` y `kp_crm_local` sin cambios | Aprobada |
| No conectar V3 a producción en esta fase | Aprobada |
| No normalizar roles antiguos automáticamente | Aprobada |
| Obtener un respaldo fresco y autorizado de IMPULSE antes del despliegue | Obligatoria, pendiente |

## 3. Decisiones pendientes antes de ejecutar

| ID | Decisión | Responsable sugerido |
|---|---|---|
| D1 | Aprobar este procedimiento y sus anexos | Aprobado condicionalmente el 2026-10-20; G1 local autorizada |
| D2 | Confirmar si se usa copia completa enmascarada o un subconjunto menor para UI | Copia completa enmascarada aprobada; subconjunto solo para UI si se requiere |
| D3 | Definir el mapeo de roles si el dump fresco trae `sistemas` o `usuario` | Pendiente; el ETL se detiene ante roles incompatibles |
| D4 | Autorizar el respaldo fresco de IMPULSE y su ventana de extracción | Pendiente; obligatorio antes del cutover |
| D5 | Definir claves lógicas de pago, promesa y cuenta para conciliación | Pendiente de revisión funcional |
| D6 | Definir ventana de corte, RTO/RPO y responsables de reversión | Pendiente; antes del cutover |
| D7 | Confirmar tratamiento de plantillas y documentos DOCX/PDF | Pendiente; se usarán placeholders ficticios |
| D8 | Aprobar el perfil de `sql_mode` y el motor de staging | MariaDB 11.8.9 aprobado; perfil verificado en G1 |
| D9 | Reanudar Meridian solo después del cierre de la validación | Pendiente; después de G4–G6 |

### 3.1 Recomendaciones técnicas

| Tema | Recomendación |
|---|---|
| Base de validación | Copia completa enmascarada (173.69 MB); un subconjunto solo para UI |
| Roles | No normalizar; detenerse si el dump fresco trae `sistemas`/`usuario` |
| Respaldo | Usar la copia local solo para la primera prueba técnica; el cutover exige dump fresco |
| Motor | MariaDB 11.8.9 para staging; no cambiar el motor de producción |
| `sql_mode` | Perfil estricto y segunda pasada con el modo por defecto de MariaDB |
| Red | Puertos solo en `127.0.0.1`; DB/ETL en red interna si se valida el acceso |
| Storage | Placeholders locales; no copiar documentos reales |
| Cutover | Ventana con freeze de escrituras; evitar dual-write |
| Meridian | Reanudar solo después de G4–G6 |

## 4. Entregables

| # | Documento | Contenido |
|---|---|---|
| 1 | [Plan de desidentificación](01_plan_desidentificacion.md) | Reglas determinísticas, campos, validación y riesgos |
| 2 | [Arquitectura de staging](02_arquitectura_staging.md) | Bases nuevas, contenedores, red, storage y aislamiento |
| 3 | [Matriz V1/V3](03_matriz_v1_v3.md) | Tablas, columnas, índices, FKs, checks, roles e historial |
| 4 | [Restauración segura](04_restauracion_segura.md) | Preflight, importación, verificación y manejo de fallos |
| 5 | [Integridad y conciliación](05_integridad_conciliacion.md) | Consultas, invariantes, casos conocidos y umbrales |
| 6 | [MySQL/MariaDB](06_mysql_mariadb.md) | Diferencias reales, riesgos y plan de pruebas |
| 7 | [Migraciones incrementales](07_migraciones_incrementales.md) | Baseline, roles, notifications y cutover |
| 8 | [Plan de reversión](08_rollback.md) | Escenarios, datos creados en V3 y ensayo |
| 9 | [Checklist funcional](09_checklist_validacion_funcional.md) | Pruebas críticas y criterios de salida |
| 10 | [G1: configuración y verificaciones](10_g1_configuracion_y_verificaciones.md) | Aislamiento, scripts, seguridad, compatibilidad y estado de ejecución |

## 5. Línea base auditada

Auditoría de solo lectura: 2026-10-20. Datos de la copia local
`u480021566_kpinvest_bd`, no de producción:

| Tabla | Filas |
|---|---:|
| `users` | 49 |
| `clientes_cuentas` | 229,389 |
| `asignar_clientes` | 222,926 |
| `ccd_clientes` | 222,781 |
| `cliente_bloqueos` | 11 |
| `pagos_lotes` | 216 |
| `pagos_propia` | 40,780 |
| `promesas_pago` | 2,580 |
| `promesa_cuotas` | 2,818 |
| `promesa_operaciones` | 2,739 |
| `cna_solicitudes` | 1,457 |
| `notifications` | 2 |
| `personal_access_tokens` | 0 |
| `migrations` | 18 |

- Tamaño aproximado: 173.69 MB.
- 14 tablas InnoDB, `utf8mb4_unicode_ci`, salvo
  `cna_solicitudes.operaciones` que es `utf8mb4_bin`.
- 153 columnas V1: 66 `utf8mb4_unicode_ci`, 1 `utf8mb4_bin` y 86 sin charset
  (numéricas/fechas). El dato anterior de "86 columnas" correspondía solo a
  las columnas sin charset.
- 0 filas huérfanas en las relaciones verificadas.
- 0 triggers, vistas, rutinas o eventos.
- Roles: 1 administrador, 3 supervisores, 44 asesores, 1 soporte.
- 44 usuarios activos y 5 inactivos; 5 filas tienen `active <> is_active`.
- No hay roles antiguos `sistemas` ni `usuario` en esta copia.
- `kpinvest_v3_migrate_test`: 22 tablas (13 V3 + 9 prototipo), 8 migraciones,
  sin `notifications`, sin datos de negocio.
- `kp_crm_local`: 14 tablas, 9 migraciones, 1 administrador local, sin datos
  de negocio.

## 6. Reglas de trabajo

1. No escribir en `u480021566_kpinvest_bd`, `kpinvest_v3_migrate_test` ni
   `kp_crm_local`.
2. No conectar la aplicación V3 a la base V1 ni a producción en esta fase.
3. No consultar ni mostrar nombres, DNI, correos, teléfonos, direcciones ni
   contraseñas reales. Los reportes de auditoría solo llevan conteos y
   agregados.
4. No enviar datos personales a servicios externos ni a agentes de IA.
5. No hacer `migrate:fresh`, `db:wipe`, `DROP DATABASE` ni seeders sobre bases
   con datos.
6. No hacer commits ni push.
7. Cualquier cambio de permisos o de roles requiere aprobación explícita.

## 7. Puertas de aprobación

| Puerta | Criterio | Estado |
|---|---|---|
| G0 | Procedimiento completo revisado y aprobado | Aprobado condicionalmente el 2026-10-20 |
| G1 | Preparación local de staging: config, scripts y verificaciones | Preparada; pendiente de revisión y autorización de ejecución |
| G1-B | Respaldo fresco y autorizado de IMPULSE, con checksum | Pendiente; obligatorio antes del cutover |
| G2 | Restauración local y enmascaramiento ejecutados en aislamiento | Pendiente; requiere autorización tras G1 |
| G3 | Enmascaramiento validado: 0 PII residual, conteos y FKs correctos | Pendiente |
| G4 | V3 validado sobre la copia enmascarada | Pendiente |
| G5 | Decisión de roles y baseline documentada | Pendiente |
| G6 | Ensayo de cutover y reversión completado | Pendiente |
| G7 | Cutover productivo autorizado | Fuera de esta fase |
| G8 | Meridian se reanuda | Solo después de G4–G6 |

## 8. Cómo revisar este paquete

1. Leer este índice y el plan de desidentificación.
2. Confirmar que las bases propuestas y el aislamiento son aceptables.
3. Revisar la matriz V1/V3 y la decisión de baseline.
4. Validar las claves de conciliación y los casos conocidos.
5. Aprobar o corregir el perfil MySQL/MariaDB.
6. Confirmar el plan de reversión y el checklist funcional.
7. Revisar los resultados de G1 y autorizar explícitamente la ejecución local
   (restauración, enmascaramiento y validación). Sin esa autorización no se
   copian ni transforman datos reales.

## 9. Aprobación

| Rol | Nombre | Fecha | Firma / decisión |
|---|---|---|---|
| Responsable técnico |  |  |  |
| Responsable funcional |  |  |  |
| Responsable de datos |  |  |  |
| Responsable de producción |  |  |  |

---

**Recordatorio:** la aprobación de este paquete habilita la preparación local de
staging, no la ejecución con datos reales ni el despliegue productivo. El
cutover requiere además G1-B (dump fresco), G5, G6 y una ventana autorizada.
