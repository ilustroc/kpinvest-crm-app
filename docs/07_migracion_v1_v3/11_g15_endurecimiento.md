# 11. G1.5: endurecimiento y pruebas sintéticas

> **Estado: G1.5 ejecutado y verificado con datos sintéticos. Etapa 2 NO autorizada.**
>
> Fecha: 2026-10-21 (UTC). Entorno: proyecto Compose `kpcrm-staging`.
>
> No se restauró, enmascaró, transformó ni consultó `u480021566_kpinvest_bd`,
> `kpinvest_v3_migrate_test`, `kp_crm_local` ni ningún dato histórico de
> IMPULSE. Todas las pruebas usaron el fixture
> `tests/fixtures/staging/synthetic_v1.sql`. No se hicieron commits, pushes ni
> despliegues. No se eliminaron volúmenes ni se modificó el CRM operativo de
> `localhost:8088`.

## 1. Alcance y límites

G1.5 deja staging listo para una futura Etapa 2, pero **no la inicia**. El
objetivo fue demostrar, con pruebas positivas y negativas, que:

- la app V3 no tiene ruta de red ni credencial hacia la referencia sin
  enmascarar;
- la app no puede leer `.env`, `.env.staging`, la clave HMAC, los respaldos SQL
  originales, `/import` ni los reportes de staging;
- app, bases y herramientas no tienen egreso a Internet;
- cada rol de base tiene solo los privilegios de su función;
- una restauración fallida o un dump peligroso no dejan una ventana de
  escritura abierta;
- el enmascaramiento es determinista y la verificación automática no encuentra
  PII residual.

La restauración de datos históricos y el cutover siguen bloqueados.

## 2. Topología final

### 2.1 Redes

| Red | `internal` | Miembros | Función |
|---|---|---|---|
| `kpcrm_staging_ref` | sí | `kpcrm-staging-ref-db`; `staging-tools` efímero | Referencia sin enmascarar, aislada |
| `kpcrm_staging_tools_db` | sí | `kpcrm-staging-db`; `staging-tools` efímero | Herramientas contra la base de proceso |
| `kpcrm_staging_private` | sí | `kpcrm-staging-db`, `kpcrm-staging-app` | App contra `kp_crm_v3_validation` |
| `kpcrm_staging_app_net` | sí | `kpcrm-staging-app`, `kpcrm-staging-web` | Proxy hacia la app |
| `kpcrm_staging_web` | no | `kpcrm-staging-web` | Único puerto publicado: `127.0.0.1:8090` |

La app **no comparte ninguna red** con `staging-ref-db` ni con
`staging-tools`; el proxy no comparte red con las bases. La separación no
depende de que la app “recuerde” no consultar la referencia: no existe ruta
L2/L3 hacia ella.

### 2.2 Servicios

| Servicio | Contenedor | Puerto host | Redes | Volumen |
|---|---|---|---|---|
| `staging-ref-db` | `kpcrm-staging-ref-db` | ninguno | `staging_ref` | `kpcrm_staging_ref_data` |
| `staging-db` | `kpcrm-staging-db` | ninguno | `staging_private`, `staging_tools_db` | `kpcrm_staging_mariadb_data` |
| `staging-app` | `kpcrm-staging-app` | ninguno | `staging_private`, `staging_app_net` | storage aislado |
| `staging-web` | `kpcrm-staging-web` | `127.0.0.1:8090` | `staging_app_net`, `staging_web` | solo `default.conf:ro` |
| `staging-tools` | efímero | ninguno | `staging_tools_db`, `staging_ref` | `tools:ro`, `/import:ro`, `/reports`, HMAC |

`staging-tools` se ejecuta siempre con `docker compose run --rm --no-deps`
para que Compose no recree las bases por `depends_on`.

## 3. Evidencia de red

Reporte reproducible: `storage/staging/reports/g15_isolation.json`.
Resultado: **82 pruebas, 0 fallos**.

| Prueba | Resultado observado |
|---|---|
| App resuelve `staging-db` | sí |
| App resuelve `staging-ref-db` | no (`getent` falla) |
| App TCP a `staging-db:3306` | abierto |
| App TCP a `staging-ref-db:3306` | cerrado |
| App TCP a la IP real de `ref-db:3306` | sin ruta |
| App PDO/DML en `kp_crm_v3_validation` | permitido |
| App SELECT en `kp_crm_staging_masked` | denegado (`1142`) |
| App egreso a `1.1.1.1:443` | bloqueado |
| Proxy resuelve `staging-db` / `staging-ref-db` | no |
| Proxy TCP a la IP de `db` / `ref` | sin ruta |
| Proxy HTTP a `staging-app/login` | `200` |
| Egreso desde `staging-db` y `staging-ref-db` | bloqueado |
| `db` TCP a la IP de `ref` y viceversa | sin ruta |
| `127.0.0.1:8090/healthz` | `200`, cuerpo `ok` |
| `127.0.0.1:8090/login` | `200` |
| Puerto publicado | solo `80/tcp -> 127.0.0.1:8090` |

El proxy conserva egreso a Internet porque es el único servicio con puerto
publicado sobre una red bridge. No recibe secretos, datos ni acceso a las
bases; se registra como riesgo residual en la sección 9.

Comandos de reproducción:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingIsolation.ps1

docker network inspect kpcrm_staging_ref --format '{{json .Containers}}'
docker network inspect kpcrm_staging_private --format '{{json .Containers}}'
docker inspect kpcrm-staging-app --format '{{json .NetworkSettings.Networks}}'
```

## 4. Montajes y secretos

La app monta 14 bind mounts explícitos. No monta la raíz del repositorio, por
lo que no ve `.env`, `.env.staging`, `tests/`, `scripts/`, `docs/`,
`storage/staging/reports`, `/import` ni la clave HMAC.

| Ruta dentro de la app | Estado |
|---|---|
| `/var/www/html/.env`, `.env.staging` | ausentes |
| `/run/secrets/mask_hmac_key` | ausente |
| `/import` | ausente |
| `/reports` | ausente |
| `/var/www/html/tests` | ausente |
| `storage/staging/reports` | ausente |
| Archivos `.sql` bajo `/var/www/html` | 0 |
| Respaldo `u480021566*` | 0 coincidencias |
| `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `vendor/`, `artisan`, `composer.json` | montados `:ro` |
| `storage/app`, `storage/framework`, `storage/logs`, `bootstrap/cache` | montados RW y aislados |

`staging-tools` ve solo `tools/`, `/import:ro`, `/reports` y
`/run/secrets/mask_hmac_key`; no ve `app/` ni `.env*`. El proxy solo monta
`docker/staging/web/default.conf:ro`.

### 4.1 ACL y rotación

- `.env.staging` y la clave HMAC viven fuera del repositorio, en
  `C:\KP-CRM-Staging`.
- La ACL de `C:\KP-CRM-Staging` quedó limitada a `ILUSTROC\PC`, `SYSTEM` y
  `Administradores`; no quedan `Usuarios`, `Authenticated Users` ni `Everyone`.
- `g15_secrets_acl.json`: `ok=true`, `acl_forbidden_trustees=[]`,
  `acl_empty_paths=0`, `suspicious_paths=0`.
- Se corrigió un defecto del script de ACL: `icacls /T /grant:r` con banderas
  `(OI)(CI)` sobre archivos podía dejar una DACL vacía (archivo ilegible). Ahora
  se recorren directorios y archivos por separado y se verifica que ninguna
  ruta quede con DACL vacía.
- `STAGING_DB_RESTORE_PASSWORD` fue rotada después de detectar que un
  `STAGING_IMPORT_DIR` mal formado había absorbido la línea siguiente del
  `.env.staging` y creado un directorio con la asignación en el nombre. El
  artefacto se retiró y el valor nuevo no se imprimió.
- `Ingrese un nombre de usuario` y los demás secretos no se versionan ni se
  imprimen. `docker inspect` puede mostrar variables de entorno del contenedor:
  el host y el acceso al daemon Docker siguen siendo parte de la frontera de
  confianza.

## 5. Matriz efectiva de privilegios

La referencia y la base de proceso son **servidores MariaDB distintos**. Los
usuarios se recrean con `Apply-StagingHardening.ps1`; no se recrean
contenedores ni se tocan volúmenes.

### 5.1 Servidor de referencia (`kpcrm-staging-ref-db`)

| Usuario | Privilegios | Comprobación real |
|---|---|---|
| `kp_stage_etl` | `USAGE`; cuenta bloqueada | login rechazado (`4151`) |
| `kp_stage_restore` | `USAGE` fuera de ventana | SELECT/CREATE en referencia: `1142` |
| `kp_stage_ref_ro` | `SELECT` en `kp_crm_v1_reference` | SELECT permitido; CREATE `1142` |
| `kp_stage_mask` | `SELECT` en `kp_crm_v1_reference` | SELECT permitido; CREATE `1142` |
| `kp_stage_ro` | `SELECT` en `kp_crm_v1_reference` | SELECT permitido; CREATE `1142` |
| `kp_stage_app` | **no existe** | login rechazado (`1045`) |

### 5.2 Servidor de proceso (`kpcrm-staging-db`)

| Usuario | Privilegios | Comprobación real |
|---|---|---|
| `kp_stage_app` | SELECT/INSERT/UPDATE/DELETE + temporales y locks en `kp_crm_v3_validation` | DML/temporal permitido; masked SELECT `1142`; masked write `1044` |
| `kp_stage_mask` | DDL/DML en `kp_crm_staging_masked` y `kp_crm_mask_work` | escritura masked permitida; validación `1044` |
| `kp_stage_ro` | `SELECT` en masked, validación y rehearsal | SELECT permitido; escritura denegada |
| `kp_stage_restore` | `USAGE` fuera de ventana | masked SELECT `1142`; referencia no aplica |
| `kp_stage_etl` | `USAGE`; cuenta bloqueada | login rechazado (`4151`) |
| `kp_stage_ref_ro` | **no existe** | — |

Ningún usuario `kp_stage_*` tiene privilegios globales distintos de `USAGE`.
No hay `GRANT` de la app sobre la referencia ni grants legacy de la referencia
en el servidor de proceso. Las ventanas de restauración terminan en estado
`locked` (`g15_privileges_apply.json`: `reference_write_window=locked`,
`rehearsal_write_window=locked`).

## 6. Pruebas sintéticas end-to-end

### 6.1 Restauración de referencia

- Dump: `synthetic_v1.sql`, SHA-256
  `EDCAFD06BA6815D5D75C838EAA7F962EB1D550467A810F924C816C156FF0AE04`.
- Destino: `kp_crm_v1_reference` en `kpcrm-staging-ref-db`.
- Resultado: **14 tablas**, **9 migraciones**, 153 columnas, todo InnoDB y
  `utf8mb4_unicode_ci`.
- `restore_kp_crm_v1_reference.json`: `ok=true`,
  `write_window_closed=true`, `reference_restore_user_write=revoked`.
- La restauración se rechazó sobre un destino no vacío sin sobrescribir:
  14 tablas antes y 14 después.

### 6.2 Enmascaramiento y verificación

- `mask` copió/enmascaró **61 filas**.
- `masking_validation.json`: `ok=true`, `failures=[]`.
- 8 FKs sin huérfanos; 6 restricciones únicas sin violaciones.
- Sumas de deuda, pagos, promesas, cuotas y CNA idénticas entre referencia y
  copia; roles y banderas preservados.
- 0 coincidencias exactas de PII original en las columnas verificadas.
- 0 patrones sintéticos inesperados, 0 JSON inválido, 0 rutas no relativas.
- `personal_access_tokens` quedó vacía; 0 hashes de contraseña con formato
  inválido.
- `preflight.json`: `ok=true`; referencia `ready=true`; masked, work,
  validación y rehearsal vacías antes de enmascarar; clave HMAC de longitud
  suficiente.

### 6.3 Determinismo

`Test-StagingDeterminism.ps1` reinició solo `kp_crm_staging_masked`, ejecutó
dos veces el mismo `preflight + mask` y comparó un dump canónico (sin
comentarios, sin fecha, orden por PK, inserts de una fila):

```text
Hash 1: f3f335f351dd6450b68e01fe3ebaed632a82a19b3462c05ec8aa16e809c0bb87
Hash 2: f3f335f351dd6450b68e01fe3ebaed632a82a19b3462c05ec8aa16e809c0bb87
```

`g15_determinism.json`: `ok=true`, `hash_equal=true`, 61 filas en ambas
ejecuciones, verificación `ok=true` en ambas.

### 6.4 Escáner de dumps

`Test-StagingRestoreGuards.ps1` ejecutó **19 casos** de escáner. Se rechazaron:

- `USE`, `CREATE/DROP/ALTER DATABASE` y `DROP SCHEMA`;
- `CREATE/ALTER/DROP USER`, `GRANT`, `REVOKE`, `FLUSH`;
- `SET GLOBAL`, `LOAD DATA`, `INTO OUTFILE`;
- `DEFINER`, `DELIMITER`, `HANDLER`;
- una sentencia peligrosa en la misma línea después de un `INSERT`;
- `/*!50000 ... */` y `/*M!100100 ... */` (comentarios ejecutables, incluida
  la versión numérica).

Se aceptaron el fixture sintético, un `CREATE TABLE`/`INSERT` normal y un dump
con sentencias peligrosas **solo** dentro de comentarios no ejecutables.

### 6.5 Fallos de restauración y recuperación

| Caso | Resultado |
|---|---|
| Restaurar sin ventana abierta | rechazado antes de copiar; 0 tablas |
| Dump con SQL inválido tras crear una tabla | falla; deja 1 tabla parcial; la ventana se cierra |
| Escritura de `kp_stage_restore` tras el fallo | denegada |
| Lectura de auditoría con `kp_stage_ro` sobre la tabla parcial | permitida |
| Recreación de la base de ensayo | vuelve a 0 tablas |
| Restauración sobre referencia poblada | rechazada; 14 tablas intactas; ventana cerrada |
| Reporte de éxito previo | conservado (`ok=true`, 14 tablas) |

`g15_restore_guards.json`: **33 pruebas, 0 fallos**. Las pruebas usaron una
copia temporal del fixture fuera del repositorio, como exige el procedimiento
real.

## 7. Estado final de contenedores, redes y volúmenes

`g15_final_state.json` es el resumen legible por máquina.

| Contenedor | ID | Estado | Reinicios | Redes |
|---|---|---|---|---|
| `kpcrm-staging-ref-db` | `863544073b8d6295ac71e9bef5ca5f6b1559d8f1deb3815552b57fcbf6e2fac3` | running, healthy | 0 | `staging_ref` |
| `kpcrm-staging-db` | `5b6516c36ff86f129820bdaaa4e30e63bd11638d3c8423fcbe7f10dc2a5e5195` | running, healthy | 0 | `staging_private`, `staging_tools_db` |
| `kpcrm-staging-app` | `7145174ec2feb9d79693d2d55513078726f32cdbc0edfa6f45ac5070deb1d14b` | running | 0 | `staging_private`, `staging_app_net` |
| `kpcrm-staging-web` | `0dd6788c94cc6f24f3c5911d5f53c63383a0f45600f71a366c5638c3235e7545` | running, healthy | 0 | `staging_app_net`, `staging_web` |

Los IDs de `staging-db`, `staging-app` y `staging-web` coinciden con la línea
base previa a G1.5: **no se recrearon**. `staging-ref-db` es el servidor nuevo
creado para aislar la referencia.

| Volumen | Driver | En uso por |
|---|---|---|
| `kpcrm_staging_mariadb_data` | local | `kpcrm-staging-db` |
| `kpcrm_staging_ref_data` | local | `kpcrm-staging-ref-db` |

No se eliminó ningún volumen, no se ejecutó `docker compose down -v` y no se
modificó `compose.crm.yml`. El CRM existente sigue en `127.0.0.1:8088`
(`200`); sus contenedores no fueron recreados.

| Base | Servidor | Tablas | Filas |
|---|---|---|---|
| `kp_crm_v1_reference` | ref-db | 14 | 62 |
| `kp_crm_staging_masked` | db | 14 | 61 |
| `kp_crm_mask_work` | db | 0 | 0 |
| `kp_crm_v3_validation` | db | 0 | 0 |
| `kp_crm_restore_rehearsal` | db | 0 | 0 |

Ventanas de restauración: **referencia cerrada** y **ensayo cerrado**.
`/healthz` responde `ok` y `/login` responde `200` a través de
`127.0.0.1:8090`.

## 8. Incidentes corregidos durante G1.5

1. **Ventana de escritura tras un fallo de preflight.** La primera versión de
   `Restore-Reference.ps1` abría el `try/catch` después de validar ruta y dump.
   Un fallo temprano (por ejemplo, un dump dentro del repositorio) podía dejar
   la ventana abierta. La prueba negativa lo detectó, se movió toda la
   validación dentro del `try` y ahora **cualquier** fallo ejecuta el bloqueo.
   La prueba también creó una tabla de sonda en la referencia; se retiró de
   inmediato y la referencia volvió a 14 tablas. El caso quedó cubierto por
   `restore:non-empty-closes-write`.
2. **DACL vacía en archivos.** `Protect-StagingSecrets.ps1` usaba
   `icacls /T /grant:r` con banderas de herencia también sobre archivos, lo que
   podía dejarlos sin ACEs. Se corrigió con recorrido separado de directorios y
   archivos y una comprobación de DACL vacía.
3. **Comentarios ejecutables no detectados.** El escáner quitaba `/*! ... */`
   antes de ver la sentencia y no descartaba la versión numérica. Ahora
   `/*!50000 DROP DATABASE ... */` y `/*M!100100 ... */` se rechazan.
4. **Antecedente de recreación de servicio.** En una sesión anterior, un
   `docker compose run` sin `--no-deps` recreó `kpcrm-staging-db`; el volumen y
   los datos sintéticos se conservaron. Desde entonces se usa `--no-deps` y
   `--no-recreate`, y en esta ejecución los IDs no cambiaron. Cualquier nueva
   recreación requiere autorización explícita.

## 9. Riesgos residuales

- **Etapa 2 no autorizada.** No se procesó ningún dato histórico; no existe un
  G1-B autorizado.
- **Sin rol DDL para V3.** `kp_stage_app` solo tiene DML. Construir
  `kp_crm_v3_validation` requiere decidir un rol/ventana DDL separado; no se
  debe dar DDL a la app.
- **Proxy con egreso.** Es deliberado para publicar `127.0.0.1:8090`; no tiene
  secretos ni acceso a bases. Si se exige egreso cero también en el proxy, hay
  que cambiar el diseño de publicación.
- **Acceso al host.** Quien pueda usar el daemon Docker o leer el entorno del
  contenedor puede ver variables como `APP_KEY` y contraseñas de base. La ACL
  de Windows y el acceso al host son parte de la frontera de confianza.
- **HMAC.** La clave está fuera del repositorio; no se probó su rotación ni la
  recuperación de una copia enmascarada con una clave distinta.
- **Documentos.** No hay plantillas DOCX/PDF reales; la validación de
  documentos sigue pendiente.
- **Roles no normalizados.** Si aparece un rol fuera de
  `administrador/supervisor/asesor/soporte`, `preflight`/`mask` se detienen.
- **Cutover.** Ventana, RTO/RPO y responsables de rollback no están definidos.
- **Storage de staging.** `storage/staging/{app,framework,logs}` es escribible
  por la app y no contiene secretos; no es un almacén de evidencias.

## 10. Procedimiento exacto para la Etapa 2 (no ejecutado)

Precondiciones: autorización escrita de Etapa 2, G1-B vigente, dump de **una
sola base** fuera del repositorio, ventana y responsables definidos. Detenerse
si el dump no pasa el escáner, si el destino no está vacío o si aparece
cualquier base histórica fuera de `kp_crm_*`.

1. **Detener app y proxy sin borrar nada:**
   `docker stop kpcrm-staging-app kpcrm-staging-web`.
2. **Confirmar referencia vacía** con `kp_stage_ro`; si tiene tablas, detenerse
   y pedir instrucción. No sobrescribir.
3. **Abrir la ventana de referencia:**
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Apply-StagingHardening.ps1 -Execute -EnableReferenceRestore`
4. **Restaurar el dump autorizado:**
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Restore-Reference.ps1 -DumpPath C:\KP-CRM-Staging\import\<dump-autorizado>.sql -Execute`
   Exigir `restore_kp_crm_v1_reference.json`: 14 tablas, 9 migraciones,
   `write_window_closed=true`. Si falla, revisar `restore_*_failed.json`; la
   ventana debe quedar cerrada.
5. **Enmascarar y verificar:**
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Invoke-Masking.ps1 -Execute`
   Exigir `mask_manifest.json` y `masking_validation.json` con
   `verification_ok=true` y `failures=[]`.
6. **Verificación independiente:**
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingIntegrity.ps1`
   y `Test-StagingDeterminism.ps1`. No continuar si hay una sola coincidencia
   de PII o si el hash no es estable.
7. **Probar el aislamiento:**
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingIsolation.ps1`.
8. **Construir `kp_crm_v3_validation`** con el rol/ventana DDL que se autorice.
   La app no recibe DDL. Después, aplicar `kp_stage_app` y ejecutar el checklist
   funcional.
9. **Arrancar app y proxy** solo con la copia enmascarada/validada:
   `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Start-Staging.ps1 -IncludeApp -Execute`.
   Verificar `/healthz` y `/login` en `127.0.0.1:8090`.
10. **Ensayo de rollback** en `kp_crm_restore_rehearsal`:
    abrir con
    `Apply-StagingHardening.ps1 -Execute -EnableRehearsalRestore`, restaurar
    con `Restore-Reference.ps1 -TargetDatabase kp_crm_restore_rehearsal -Execute`
    y cerrar con `Apply-StagingHardening.ps1 -Execute` (sin banderas).
11. **Cierre:** ejecutar
    `Write-StagingFinalState.ps1`, confirmar ambas ventanas cerradas y que
    `g15_final_state.json` tenga `ok=true`. No ejecutar `docker compose down -v`
    ni tocar volúmenes ajenos.

## 11. Decisión solicitada

Se solicita revisar la evidencia y **autorizar o rechazar** la Etapa 2. En
particular:

1. autorizar el uso del respaldo G1-B cuando exista;
2. aprobar la ventana de restauración y el bloqueo posterior de la referencia;
3. decidir el rol/ventana DDL para construir `kp_crm_v3_validation`;
4. confirmar si se acepta el proxy con egreso como único punto de publicación;
5. autorizar cualquier recreación de servicio que fuese necesaria.

Sin esas decisiones, la Etapa 2 y el cutover permanecen bloqueados. G1.5 no
inicia la Etapa 2.
