# 04. Procedimiento de restauración segura

> **Estado: preparado para G1. No ejecutar todavía.**
>
> Requiere G0 (aprobado condicionalmente) y G1-B (respaldo fresco autorizado),
> además de la autorización explícita posterior a G1 para ejecutar la
> restauración local. No se restaura sobre `u480021566_kpinvest_bd`,
> `kpinvest_v3_migrate_test` ni `kp_crm_local`.

## 1. Objetivo

Restaurar una copia de V1/IMPULSE en una base nueva y aislada, verificarla y
dejarla lista para el enmascaramiento, sin riesgo para las bases existentes ni
para producción.

## 2. Precondiciones

- [ ] Procedimiento aprobado (G0).
- [ ] Respaldo fresco, completo (estructura + datos) y autorizado (G1).
- [ ] Dump generado con cliente MariaDB compatible con 11.8.x.
- [ ] Checksum SHA-256 entregado por el responsable del respaldo.
- [ ] Archivo cifrado en almacenamiento local protegido, fuera del repositorio.
- [ ] Espacio libre suficiente (mínimo 5 GB; recomendado 10 GB).
- [ ] Bases nuevas aprobadas y vacías.
- [ ] Ventana de restauración acordada.
- [ ] Responsable técnico y responsable de datos presentes.

## 3. Bases destino

| Base | Contenido | App |
|---|---|---|
| `kp_crm_v1_reference` | Dump fresco sin enmascarar | Sin acceso |
| `kp_crm_staging_masked` | Copia enmascarada | Sin acceso |
| `kp_crm_v3_validation` | V3 sobre copia enmascarada | Acceso de app |
| `kp_crm_mask_work` | Mapa y evidencias | Sin acceso |
| `kp_crm_restore_rehearsal` | Ensayo de rollback | Sin acceso |

Si una base ya existe, **abortar**. No usar `DROP DATABASE` para "reiniciar".
Se crea otra base con sufijo aprobado o se investiga el estado existente.

## 4. Requisitos del dump

El dump debe:

- incluir estructura y datos;
- corresponder a una sola base;
- estar creado con `utf8mb4`;
- usar InnoDB;
- no incluir `CREATE DATABASE` ni `USE`;
- no incluir `DROP DATABASE`;
- no incluir `DEFINER` de producción;
- no incluir collations `utf8mb4_0900_*`;
- no incluir nombres de tabla en mayúsculas;
- incluir `notifications` si existe en producción;
- tener checksum verificable.

`u480021566_kpinvest_bd.sql` (17 KB, sin `INSERT`) es una referencia de
estructura, **no un respaldo**. No se usa para restaurar datos.

## 5. Preflight del archivo

### 5.1 Integridad

En Windows, antes de mover el archivo:

```powershell
Get-FileHash -Algorithm SHA256 -LiteralPath '<archivo-dump>' |
  Format-List Algorithm, Hash, Path
```

Comparar contra el checksum entregado. Si no coincide, abortar. No continuar
"porque el tamaño parece correcto".

### 5.2 Contenido

Revisar sin exponer datos:

- buscar `CREATE DATABASE`, `DROP DATABASE`, `USE `;
- buscar `DEFINER=`;
- buscar `utf8mb4_0900`;
- buscar `GTID_PURGED`;
- buscar nombres de tabla en mayúsculas;
- confirmar que hay `INSERT INTO` (si no, es structure-only);
- confirmar que no hay rutas o credenciales embebidas.

Si aparece cualquiera de los primeros cuatro, abortar y pedir un dump limpio.
No "limpiar" el dump con expresiones regulares sin revisión.

### 5.3 Decodificación

- Si está comprimido, verificar el archivo comprimido antes de extraer.
- Extraer a un volumen temporal protegido, no al repositorio.
- Verificar nuevamente el checksum del archivo descomprimido.
- No dejar el dump en una carpeta sincronizada con nube.

## 6. Creación de bases

Usar el cliente MariaDB dentro del contenedor de staging, con un archivo de
credenciales montado en solo lectura. No pasar contraseñas por argumentos.

```sql
CREATE DATABASE kp_crm_v1_reference
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE kp_crm_staging_masked
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE kp_crm_v3_validation
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE kp_crm_mask_work
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Sin `IF NOT EXISTS`. Si existe, el comando debe fallar y se detiene el
procedimiento.

## 7. Importación

### 7.1 Preparar el archivo dentro del contenedor

```powershell
docker cp '<archivo-dump>' kpcrm-staging-db:/tmp/v1_import.sql
```

El contenedor debe estar aislado y el archivo se elimina al terminar.
`scripts/migration/Restore-Reference.ps1` escanea el dump en el host antes de
copiarlo y rechaza `USE`, `CREATE/DROP DATABASE`, privilegios y sentencias
equivalentes que intenten salir de la base destino.

### 7.2 Importar con cliente MariaDB

Crear dentro del contenedor un archivo de sesión, sin datos:

```sql
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION time_zone = '+00:00';
```

Importar concatenando la sesión y el dump:

```bash
docker exec -i kpcrm-staging-db sh -c '
  cat /tmp/session.sql /tmp/v1_import.sql |
  mariadb \
    --defaults-extra-file=/run/secrets/staging-client.cnf \
    --default-character-set=utf8mb4 \
    kp_crm_v1_reference
'
```

Notas:

- No usar `mysql` 8.0 contra MariaDB para el dump/restore; usar
  `mariadb`/`mariadb-dump` 11.8.x.
- No usar `--force`; un error debe detener la importación.
- No desactivar FK de forma global. El dump puede traer
  `SET FOREIGN_KEY_CHECKS=0` en su sesión; se validan huérfanos después.
- No cambiar `sql_mode` global. Si el dump falla por una fecha extrema, se
  detiene y se documenta; no se relaja el modo sin aprobación.
- No usar `--databases` ni `--all-databases`.

### 7.3 Si el dump trae `DROP TABLE`

En una base nueva y vacía, un `DROP TABLE IF EXISTS` es aceptable si el
preflight confirmó que no hay `DROP DATABASE`. Si el destino no está vacío,
abortar antes de importar.

## 8. Verificación posterior

### 8.1 Objetos

```sql
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'kp_crm_v1_reference'
ORDER BY TABLE_NAME;

SELECT COUNT(*) AS tablas
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'kp_crm_v1_reference';

SELECT COUNT(*) AS triggers
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'kp_crm_v1_reference';

SELECT COUNT(*) AS vistas
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = 'kp_crm_v1_reference';

SELECT COUNT(*) AS rutinas
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'kp_crm_v1_reference';

SELECT COUNT(*) AS eventos
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = 'kp_crm_v1_reference';
```

Esperado: 14 tablas, InnoDB, `utf8mb4_unicode_ci`, 0 objetos ocultos (salvo
que el dump fresco traiga objetos que se revisen y aprueben).

### 8.2 Conteos

```sql
SELECT 'users' t, COUNT(*) n FROM kp_crm_v1_reference.users
UNION ALL SELECT 'clientes_cuentas', COUNT(*) FROM kp_crm_v1_reference.clientes_cuentas
UNION ALL SELECT 'cliente_bloqueos', COUNT(*) FROM kp_crm_v1_reference.cliente_bloqueos
UNION ALL SELECT 'pagos_lotes', COUNT(*) FROM kp_crm_v1_reference.pagos_lotes
UNION ALL SELECT 'pagos_propia', COUNT(*) FROM kp_crm_v1_reference.pagos_propia
UNION ALL SELECT 'promesas_pago', COUNT(*) FROM kp_crm_v1_reference.promesas_pago
UNION ALL SELECT 'promesa_cuotas', COUNT(*) FROM kp_crm_v1_reference.promesa_cuotas
UNION ALL SELECT 'promesa_operaciones', COUNT(*) FROM kp_crm_v1_reference.promesa_operaciones
UNION ALL SELECT 'cna_solicitudes', COUNT(*) FROM kp_crm_v1_reference.cna_solicitudes
UNION ALL SELECT 'asignar_clientes', COUNT(*) FROM kp_crm_v1_reference.asignar_clientes
UNION ALL SELECT 'ccd_clientes', COUNT(*) FROM kp_crm_v1_reference.ccd_clientes
UNION ALL SELECT 'notifications', COUNT(*) FROM kp_crm_v1_reference.notifications
UNION ALL SELECT 'personal_access_tokens', COUNT(*) FROM kp_crm_v1_reference.personal_access_tokens
UNION ALL SELECT 'migrations', COUNT(*) FROM kp_crm_v1_reference.migrations;
```

Comparar con el manifiesto del dump. Si no hay manifiesto, generarlo antes de
la importación y congelarlo.

### 8.3 Integridad

- 0 huérfanos en las 8 FKs.
- 0 violaciones de PK/unique.
- 100% de `cna_solicitudes.operaciones` con JSON válido.
- Roles y estados contabilizados.
- `notifications` presente si el dump la trae.
- `migrations` coherente con el dump, no con la base local.

### 8.4 Diferencias de motor

- Revisar `sql_mode`, `lower_case_table_names`, `time_zone`, charset.
- Confirmar que no hay `utf8mb4_0900_*`.
- Confirmar que las fechas extremas se conservaron (si existen en el dump).
- Confirmar que los checks `json_valid` se crearon.
- Comparar `SHOW CREATE TABLE` solo como evidencia; no usarlo para decidir
  compatibilidad por diferencias cosméticas.

## 9. Enmascaramiento y validación

1. Congelar `kp_crm_v1_reference`.
2. Ejecutar el ETL de enmascaramiento hacia `kp_crm_staging_masked`.
3. Validar PII, conteos, FKs y unicidad.
4. Construir `kp_crm_v3_validation` desde `kp_crm_staging_masked`.
5. No conectar la app a `kp_crm_v1_reference` ni a `kp_crm_staging_masked`.

Ver [01_plan_desidentificacion.md](01_plan_desidentificacion.md) y
[07_migraciones_incrementales.md](07_migraciones_incrementales.md).

## 10. Ensayo de rollback

- Restaurar el mismo dump en `kp_crm_restore_rehearsal`.
- Medir tiempo, espacio y errores.
- Verificar conteos y FKs.
- No conectar la app de staging a esta base.
- Borrar la base del ensayo después de capturar evidencias, con aprobación.

## 11. Manejo de fallos

| Fallo | Acción |
|---|---|
| Checksum no coincide | Abortar; pedir nuevo dump |
| `CREATE DATABASE`/`USE` en el dump | Abortar; pedir dump de una sola base |
| `DEFINER` o rutinas | Abortar; revisar y limpiar con aprobación |
| Collation `0900` | Abortar; incompatible con MariaDB |
| Nombre de tabla en mayúsculas | Abortar; revisar `lower_case_table_names` |
| Error de fecha/`sql_mode` | Detener; documentar fila y pedir decisión |
| Error de FK | No desactivar globalmente; revisar orden y datos |
| Disco lleno | Detener; limpiar solo temporales aprobados |
| Base destino existe | No borrar; crear otra con sufijo o investigar |

Si la importación falla, la base destino queda en cuarentena. No se reutiliza
sin revisar el error y sin aprobación.

## 12. Limpieza y retención

- Eliminar `/tmp/v1_import.sql` del contenedor al terminar.
- Mantener el dump cifrado fuera del repositorio.
- Borrar `kp_crm_v1_reference` solo después de que la copia enmascarada esté
  validada y con aprobación.
- Borrar `kp_crm_mask_work` y el secreto HMAC juntos, al cerrar la validación.
- Conservar evidencias agregadas, no filas.
- No dejar dumps en carpetas sincronizadas, correo, chat o herramientas de IA.

## 13. Prohibiciones

- No restaurar sobre bases existentes.
- No usar `migrate:fresh`, `db:wipe`, `DROP DATABASE` ni `TRUNCATE` en la
  fuente.
- No usar `--all-databases`.
- No usar `--force`.
- No desactivar FK de forma global.
- No cambiar `sql_mode` global para "hacer pasar" un dump.
- No conectar V3 a la base sin enmascarar.
- No ejecutar este procedimiento sin G0, G1-B y autorización explícita de ejecución local.

## 14. Checklist de restauración

- [ ] G0 aprobado.
- [ ] G1-B dump fresco autorizado.
- [ ] Checksum verificado.
- [ ] Preflight sin `CREATE/DROP DATABASE`, `USE`, `DEFINER`, `0900`, GTID.
- [ ] Bases nuevas creadas, ninguna existente.
- [ ] Importación con cliente MariaDB 11.8.x.
- [ ] 14 tablas y conteos verificados.
- [ ] 0 huérfanos y 0 duplicados de PK/unique.
- [ ] 0 triggers/vistas/rutinas/eventos no aprobados.
- [ ] `notifications` y `migrations` verificadas.
- [ ] Enmascaramiento validado.
- [ ] Evidencias agregadas guardadas.
- [ ] Dump temporal eliminado.
