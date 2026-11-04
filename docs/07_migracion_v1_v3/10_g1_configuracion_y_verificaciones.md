# 10. G1: configuración y verificaciones locales

> **Nota de vigencia:** este informe describe la Etapa 1 de G1. El
> endurecimiento G1.5, la topología final de cinco redes y dos servidores
> MariaDB, los roles separados, las pruebas sintéticas y el estado actual están
> en [11. G1.5: endurecimiento y pruebas sintéticas](11_g15_endurecimiento.md).
> Las secciones de red, permisos y montajes de este documento quedaron
> superadas por G1.5; los valores históricos se conservan como evidencia de esa
> etapa.
>
> **Estado: Etapa 1 de G1 ejecutada; Etapa 2 no autorizada.**
>
> Fecha: 2026-10-20.
>
> Se generó el archivo de entorno externo `.env.staging` (fuera del
> repositorio, ignorado por Git), la clave HMAC fuera del repositorio y se
> inició únicamente `kpcrm-staging-db`; las cinco bases están vacías y
> `kpcrm-staging-app` no se inició. No se copiaron, importaron,
> restauraron, transformaron ni enmascararon datos reales. Las bases existentes
> y `compose.crm.yml` permanecen intactos. La Etapa 2 requiere autorización
> explícita.

## 1. Alcance autorizado

G1 autoriza únicamente:

- configuración Docker aislada;
- redes, volúmenes, bases y usuarios de staging;
- scripts de restauración, enmascaramiento y validación;
- verificaciones automáticas de seguridad e integridad;
- comprobaciones de compatibilidad y documentación de comandos/reversión.

G1 **no** autoriza todavía la ejecución con datos reales. Para eso hace falta
una aprobación explícita posterior a la revisión de este informe.

## 2. Entregables

| Componente | Archivo |
|---|---|
| Topología aislada | `compose.crm.staging.yml` |
| Plantilla de entorno | `.env.staging.example` |
| Inicialización de bases/usuarios | `docker/staging/initdb/01-init.sql.sh` |
| ETL y verificador | `tools/migration/{bootstrap,Masker,Verifier,cli}.php` |
| Scripts operativos | `scripts/migration/*.ps1` |
| Documentación de uso | `docker/staging/README.md`, `tools/migration/README.md`, `scripts/migration/README.md` |
| Este informe | `docs/06_migracion_v1_v3/10_g1_configuracion_y_verificaciones.md` |

## 3. Aislamiento y seguridad

- Proyecto Docker nuevo: `kpcrm-staging`.
- Contenedores: `kpcrm-staging-db`, `kpcrm-staging-app`,
  `kpcrm-staging-tools` (one-off, perfil `tools`).
- Red dedicada: `kpcrm_staging_net`.
- Volumen de base: `kpcrm_staging_mariadb_data`.
- La base no publica puerto al host. La app solo escucha en
  `127.0.0.1:8090`.
- `staging-tools` monta el directorio de importación en `/import` en solo
  lectura y recibe la clave HMAC como secreto de Docker.
- `staging-app` no recibe la clave HMAC ni el usuario ETL.
- El archivo de entorno real `.env.staging` está fuera del repositorio (por
  ejemplo, `C:\KP-CRM-Staging\.env.staging`) y no se versiona. La clave HMAC y
  el directorio de importación también quedan fuera del repositorio.
- El ETL solo acepta `MASK_ENV=staging`, `DB_HOST=staging-db` y bases
  `kp_crm_*`. Rechaza las bases existentes y las del sistema.
- El ETL no acepta `root`; verifica que no tenga privilegios globales.
- La restauración escanea el dump antes de copiarlo y rechaza `USE`,
  `CREATE/DROP DATABASE`, `GRANT/REVOKE`, `SET GLOBAL`, `LOAD DATA`,
  `INTO OUTFILE`, definiciones de usuarios/triggers/rutinas/vistas y cualquier
  intento de salir de la base destino.
- Los tokens personales no se copian: `personal_access_tokens` queda vacía.
- Los mapas de pseudonimización se mantienen solo en memoria; no se escriben
  en la base ni en el repositorio.
- Las contraseñas de los cuatro usuarios de prueba son ficticias y se toman de
  `STAGING_TEST_PASSWORD`; no se imprimen en logs ni reportes.

## 4. Bases y permisos

| Base | Uso | App | ETL |
|---|---|---|---|
| `kp_crm_v1_reference` | Copia V1 restaurada | No | Sí |
| `kp_crm_staging_masked` | Copia enmascarada | No | Sí |
| `kp_crm_mask_work` | Reservada para evidencias; el ETL actual no persiste mapas | No | Sí |
| `kp_crm_v3_validation` | Validación V3 | DML + temporales | Sí |
| `kp_crm_restore_rehearsal` | Ensayo de rollback | No | Sí |

Usuarios:

- `kp_stage_etl`: `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX,
  REFERENCES, LOCK TABLES` en las cinco bases de staging. Sin privilegios
  globales ni de rutinas, vistas, triggers, eventos o tablas temporales.
- `kp_stage_app`: DML y tablas temporales solo en `kp_crm_v3_validation`.
- `kp_stage_ro`: `SELECT` en la copia enmascarada y en la validación.
- `root` solo dentro de `kpcrm-staging-db`; no se usa para la app ni para el
  ETL.

## 5. Compatibilidad observada

- Origen local de referencia: MySQL 8.0.42 con
  `lower_case_table_names=1` y modo estricto.
- Destino: MariaDB 11.8.9 en Docker, con
  `lower_case_table_names=0`, `utf8mb4`, `utf8mb4_unicode_ci` y el perfil
  estricto de Compose.
- El esquema V1 tiene 14 tablas y **153 columnas**, no 86:
  - 66 columnas `utf8mb4_unicode_ci`;
  - 1 columna `utf8mb4_bin` (`cna_solicitudes.operaciones`);
  - 86 columnas sin charset (numéricas/fechas).
- El dato anterior de “86 columnas” correspondía solo a las columnas sin
  charset. El código y la documentación ya esperan 153.
- 8 FKs, 6 restricciones únicas y 1 check `json_valid(operaciones)`.
- MariaDB aceptó fechas extremas (`0026-01-23`, `0005-12-30`) en modo estricto.
- No se observaron collations `utf8mb4_0900_*` en la copia local.
- `preflight` comprobará automáticamente versión, charset, collation,
  `sql_mode`, `max_allowed_packet`, grants, esquema y bases vacías al iniciar
  staging.

## 6. Conciliación de lotes

Resultado de la copia local, solo conteos:

- 216 lotes en total.
- 187 exactos.
- 29 inconsistentes, todos por sobredeclaración:
  - 23 con pagos vinculados;
  - 6 sin pagos vinculados.
- 0 subdeclarados.
- 175,346 registros declarados vs 40,780 pagos reales.

La copia enmascarada debe preservar exactamente estos agregados. El verificador
falla si cambian.

## 7. Verificaciones automáticas implementadas

`verify` comprueba:

- conteos de las 14 tablas;
- 8 FKs y 0 huérfanos;
- claves únicas de usuarios, asignaciones, bloqueos, promesas, CNA y tokens;
- sumas de deuda, pagos, promesas, cuotas y CNA;
- roles y banderas `active`/`is_active`;
- conciliación de lotes y casos conocidos de promesas/pagos;
- coincidencia exacta de PII original por columna;
- conteos distintos de identificadores para detectar fusiones;
- formatos sintéticos de nombres, correos, direcciones, rutas y notas;
- JSON válido en CNA y notificaciones;
- rutas relativas, sin `..`, `C:`, `/` absoluto ni URLs externas;
- `personal_access_tokens` vacía;
- hashes de contraseña en formato bcrypt.

Los reportes no contienen PII ni valores enmascarados.

## 8. Evidencia de validación

### 8.1 Validación estática previa (sin datos)

- `php -l` sobre `bootstrap.php`, `Masker.php`, `Verifier.php` y `cli.php`:
  sin errores.
- `bash -n` sobre `docker/staging/initdb/01-init.sql.sh`: sin errores.
- Parser de PowerShell sobre los siete scripts: sin errores.
- Escaneo de dump probado con archivos sintéticos: un dump con solo
  `CREATE TABLE`/`INSERT` se permite; `USE` y `GRANT` se rechazan antes de
  copiar el archivo al contenedor.
- `php tools/migration/cli.php help` y `plan`: salida correcta.
- `docker compose ... config --quiet` con entorno ficticio: correcto.
- Configuración resuelta:
  - servicios `staging-app`, `staging-db`, `staging-tools`;
  - app en `127.0.0.1:8090`;
  - base y herramientas sin puerto publicado;
  - red `kpcrm_staging_net`;
  - volumen `kpcrm_staging_mariadb_data`;
  - secreto HMAC solo en `staging-tools`;
  - importación en solo lectura.
- Prueba efímera de `01-init.sql.sh` con `mariadb:11.8.9`, los mismos
  argumentos de servidor de Compose, credenciales ficticias y sin volumen de
  staging:
  - 5 bases creadas, todas `utf8mb4_unicode_ci`;
  - 3 usuarios `kp_stage_*` creados;
  - 0 privilegios globales distintos de `USAGE`;
  - `@@version=11.8.9-MariaDB`, `@@character_set_server=utf8mb4`,
    `@@collation_server=utf8mb4_unicode_ci`,
    `@@lower_case_table_names=0`, `@@max_allowed_packet=67108864` y
    `sql_mode` completo;
  - contenedor y volúmenes anónimos del test eliminados; no se usó ningún
    volumen existente.
- La primera comprobación con `-p` desde PowerShell falló por el paso de
  argumentos de PowerShell, no por el script SQL. La repetición con
  `MYSQL_PWD` confirmó la creación efectiva de las cinco bases y los tres
  usuarios.
- Antes de la Etapa 1, `.env.staging` no existía en el repositorio y la prueba
  de `staging-tools` se ejecutó sin base y sin datos.
- No se encontraron claves privadas, URIs MySQL con credenciales ni
  contraseñas literales en los archivos versionables. La única coincidencia
  previa del escaneo fue una asignación con variable de PowerShell en el
  generador de secretos, no un valor real.
- Los archivos nuevos no tienen espacios finales; los bloques de código de la
  documentación están balanceados.

### 8.2 Etapa 1 ejecutada (2026-10-20)

- Archivo de entorno externo `.env.staging` generado fuera del repositorio,
  verificado como ignorado y no versionado; ACL restringida al usuario actual.
  No se imprimieron valores.
- Clave HMAC externa de 64 bytes, fuera del repositorio y legible solo por el
  usuario actual; no se imprimió ni se copió al repositorio.
- Se detectó que el CRM actual monta el repositorio completo y podía leer un
  `.env.staging` colocado allí. El archivo se trasladó a
  `C:\KP-CRM-Staging\.env.staging` y se verificó que ya no existe dentro de
  `kpcrm-meridian-app-1`. No se inició `staging-app`.
- `kpcrm-staging-db` iniciado con `mariadb:11.8.9` y healthcheck `healthy`.
  `kpcrm-staging-app` no se creó ni se inició; no quedan contenedores
  persistentes de `staging-tools`.
- Red `kpcrm_staging_net` (`bridge`, `internal=false`) con solo la base
  conectada; sin puertos publicados. Volumen `kpcrm_staging_mariadb_data`
  (`local`) montado en `/var/lib/mysql`; initdb en solo lectura.
- Cinco bases vacías y con 0 tablas: `kp_crm_v1_reference`,
  `kp_crm_staging_masked`, `kp_crm_mask_work`, `kp_crm_v3_validation` y
  `kp_crm_restore_rehearsal`; todas `utf8mb4_unicode_ci`.
- Usuarios `kp_stage_etl`, `kp_stage_app` y `kp_stage_ro` con host `%`; sin
  privilegios globales distintos de `USAGE`. El ETL tiene exactamente los
  permisos de la sección 4; app y RO tienen el alcance indicado.
- Servidor: `11.8.9-MariaDB-ubu2404`, `utf8mb4`, `utf8mb4_unicode_ci`,
  `lower_case_table_names=0`, `max_allowed_packet=67108864` y `sql_mode`
  estricto completo.
- One-off `staging-tools`: `pdo_mysql`, `mysqli`, `mbstring`, `json` y
  `bcmath` disponibles; clave HMAC legible de 64 bytes; conexión PDO como
  `kp_stage_etl@%`; cinco bases y cero tablas.
- CRM V3 actual: `http://localhost:8088/` responde `302` hacia `/login`;
  `/login` responde `200`. `kpcrm-meridian-app-1` y
  `kpcrm-meridian-db-1` siguen arriba y no se modificaron.
- Comprobaciones finales tras los cambios: `php -l`, parser de PowerShell,
  `bash -n`, `docker compose config --quiet`, `cli.php help/plan`, enlaces y
  bloques de documentación, espacios finales y `git diff --check`: sin errores.
- No se restauró, enmascaró ni transformó ningún dato real.

## 9. Limitaciones y riesgos abiertos

- La copia local `u480021566_kpinvest_bd` es histórica (mayo de 2026); **no es
  un respaldo fresco de producción**. G1-B sigue pendiente.
- `preflight`, `mask` y `verify` no se ejecutaron con datos históricos; la
  restauración y el enmascaramiento siguen bloqueados. `preflight` se ejecutará
  al iniciar la Etapa 2.
- La red de staging es un `bridge` dedicado con `internal=false`; el bloqueo de
  egreso a Internet, APIs operativas, correo y mensajería no está implementado
  ni verificado. Es requisito previo de la Etapa 2.
- La app de staging no se inició; la validación funcional V3 y el ensayo de
  rollback todavía no se ejecutaron.
- `staging-app` monta el repositorio completo (`./:/var/www/html`), por lo que
  el `.env` preexistente del proyecto sigue visible para ese contenedor. Antes
  de iniciar la app se debe aislar el montaje o cubrir los archivos `.env*` con
  un placeholder; no se hará mientras la Etapa 2 no esté autorizada.
- La clave HMAC está fuera del repositorio y restringida al usuario actual; no
  se ha probado su rotación ni un procedimiento de recuperación.
- El dump autorizado debe ser de una sola base. Si incluye `USE`,
  `CREATE DATABASE` o sentencias de privilegios, la restauración lo rechaza;
  se debe regenerar sin `--databases`.
- No existen plantillas DOCX/PDF ficticias; la validación de generación de
  documentos queda bloqueada hasta crearlas.
- Si el dump fresco trae roles `sistemas` o `usuario`, el ETL se detendrá. No
  hay normalización automática; se requiere decisión explícita.
- Si la máscara se interrumpe, la base `kp_crm_staging_masked` puede quedar
  parcial. La recuperación es eliminar solo esa base nueva de staging y
  recrearla; no se reintenta sobre una copia parcial.

## 10. Reversión y limpieza

- `Stop-Staging.ps1 -Execute` ejecuta `docker compose down` sin `-v`; conserva
  el volumen.
- Para destruir la copia temporal se requiere autorización explícita y eliminar
  solo el proyecto/volumen `kpcrm-staging`; nunca las bases o volúmenes
  existentes.
- `Restore-Reference.ps1 -TargetDatabase kp_crm_restore_rehearsal -Execute`
  permite ensayar la restauración sin tocar `kp_crm_v1_reference`.
- Antes de un cutover se necesita un dump fresco autorizado (G1-B), ventana,
  RTO/RPO y responsables de reversión.

## 11. Decisión solicitada

Se solicita revisar la Etapa 1 y autorizar explícitamente la Etapa 2:

1. restaurar el dump local autorizado en `kp_crm_v1_reference`;
2. enmascarar y verificar la copia en `kp_crm_staging_masked`;
3. construir y validar V3 en `kp_crm_v3_validation`;
4. ejecutar el checklist funcional y el ensayo de rollback en
   `kp_crm_restore_rehearsal`.

Antes de ejecutar la Etapa 2 deben confirmarse el aislamiento de egreso, la
copia de referencia protegida, la ausencia de PII en logs/artefactos y la
vigencia del dump (G1-B). Esta autorización no habilita el cutover productivo
ni la conexión de V3 a producción. El cutover seguirá bloqueado hasta G1-B, G5
y G6.
