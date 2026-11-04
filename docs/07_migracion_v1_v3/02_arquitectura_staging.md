# 02. Arquitectura de staging local

> **Nota de vigencia:** este documento es la propuesta inicial. La topología
> ejecutada y verificada es la de
> [11. G1.5: endurecimiento y pruebas sintéticas](11_g15_endurecimiento.md):
> cinco redes, `kpcrm-staging-ref-db` dedicado a la referencia y
> `kpcrm-staging-db` para las bases de proceso. Donde este documento mencione
> `kpcrm_staging_net`, la red fue retirada y sustituida por esa topología.
>
> **Estado: propuesta superada por G1.5; no usar como guía operativa.**
>
> No se modifica `compose.crm.yml`, no se reinicia el entorno actual y no se
> reutilizan `u480021566_kpinvest_bd`, `kpinvest_v3_migrate_test` ni
> `kp_crm_local`.

## 1. Objetivo

Disponer de un entorno local aislado donde:

1. se restaure una copia autorizada de V1;
2. se produzca una copia enmascarada;
3. se construya y valide el esquema V3 sobre esa copia;
4. se ejecuten pruebas funcionales, de integridad, rendimiento y reversión;

sin que la aplicación V3 pueda leer datos reales ni conectarse a producción.

## 2. Estado actual que no se toca

| Recurso | Uso actual | Acción |
|---|---|---|
| `u480021566_kpinvest_bd` | Copia histórica V1/IMPULSE en MySQL 8.0.42 local | Solo lectura; no modificar |
| `kpinvest_v3_migrate_test` | Prueba anterior de esquema en MySQL 8.0.42 | No usar ni modificar |
| `kp_crm_local` | Base V3 en Docker MariaDB 11.8.9 | No usar ni modificar |
| `kpcrm-meridian-app-1` | App V3 local actual | No tocar |
| `kpcrm-meridian-db-1` | MariaDB local actual | No tocar |
| `compose.crm.yml` | Topología actual | No modificar en esta fase |

## 3. Topología propuesta

```text
Windows host
│
├── Docker project: kpcrm-meridian (actual, intacto)
│   ├── kpcrm-meridian-app-1  -> 127.0.0.1:8088
│   └── kpcrm-meridian-db-1   -> 127.0.0.1:3311  (kp_crm_local)
│
└── Docker project: kpcrm-staging (nuevo, aislado)
    ├── kpcrm-staging-app     -> 127.0.0.1:8090
    ├── kpcrm-staging-db      -> sin puerto publicado
    └── kpcrm-staging-tools   -> sin puerto; one-off (perfil tools)
        red: kpcrm_staging_net (dedicada)
        volumen DB: kpcrm_staging_mariadb_data
        bind mounts: storage/staging/{app,framework,logs,bootstrap-cache}
        importacion: directorio externo montado en /import (solo lectura)
```

La app de staging usa el nombre de servicio `staging-db`, nunca
`127.0.0.1` dentro del contenedor. La base no publica `3312`; la
administración se hace con `docker exec` o mediante `staging-tools`.

## 4. Bases nuevas

| Base | Propósito | Acceso app | Acceso ETL | Retención |
|---|---|---|---|---|
| `kp_crm_v1_reference` | Respaldo fresco autorizado de V1, sin enmascarar | No | Sí | Hasta validar la copia enmascarada; luego archivo cifrado o borrado aprobado |
| `kp_crm_staging_masked` | Copia V1 enmascarada | No | Sí | Hasta cierre de validación |
| `kp_crm_v3_validation` | Esquema y datos V3 sobre la copia enmascarada | Sí | Sí | Hasta cierre de validación y ensayo de reversión |
| `kp_crm_mask_work` | Reservada para evidencias; el ETL actual mantiene los mapas solo en memoria | No | Sí | Hasta cierre de validación; luego borrar con el secreto |
| `kp_crm_restore_rehearsal` | Ensayo de restauración de rollback | No | Sí | Solo durante el ensayo; borrar después |

Opcional, solo si se aprueba D2:

| Base | Propósito | Acceso app |
|---|---|---|
| `kp_crm_staging_masked_subset` | Subconjunto determinístico de ~5,000 personas para pruebas rápidas de UI | No |

La base principal recomendada es la **copia completa enmascarada** porque
173.69 MB es manejable y preserva todas las relaciones, incluida la conciliación
de `pagos_lotes`. Un subconjunto no sirve para validar totales de lotes.

Nombres prohibidos como destino de la app:

- `u480021566_kpinvest_bd`
- `kpinvest_v3_migrate_test`
- `kp_crm_local`
- cualquier base o host de producción

## 5. Contenedores y configuración

### 5.1 Archivo de topología

Se usa `compose.crm.staging.yml`, con proyecto `kpcrm-staging`; no modifica
`compose.crm.yml` ni los contenedores actuales.

### 5.2 Servicio `staging-db`

- Imagen: `mariadb:11.8.9`, alineada con la versión observada de IMPULSE.
- Variables: contrasenas de root, ETL, app y lectura desde el archivo de
  entorno externo (por ejemplo, `C:\KP-CRM-Staging\.env.staging`); `TZ=America/Lima`.
  La clave HMAC no entra a esta base.
- Comandos:
  - `--character-set-server=utf8mb4`
  - `--collation-server=utf8mb4_unicode_ci`
  - `--lower-case-table-names=0`
  - `--sql-mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`
  - `--max-allowed-packet=64M`
- `lower_case_table_names` solo se puede fijar al inicializar el volumen. El
  volumen debe ser nuevo; no se reutiliza el de `kp_crm_local`.
- Healthcheck con `healthcheck.sh --connect --innodb_initialized`.
- Sin binlog ni réplica en esta fase.

El `sql_mode` anterior replica el modo estricto de la copia local MySQL. Debe
probarse también el modo por defecto de MariaDB para detectar dependencias
ocultas. Ver [06_mysql_mariadb.md](06_mysql_mariadb.md).

### 5.3 Servicio `staging-app`

- Misma imagen PHP/Apache del proyecto, construida desde `docker/crm/Dockerfile`.
- Puerto `127.0.0.1:8090:80`.
- `APP_ENV=staging`.
- `APP_DEBUG=false` para no exponer trazas ni datos en pantalla.
- `APP_URL=http://localhost:8090`.
- `APP_KEY` nuevo, exclusivo de staging, desde `.env.staging`.
- `DB_CONNECTION=mysql`.
- `DB_HOST=staging-db`.
- `DB_PORT=3306`.
- `DB_DATABASE=kp_crm_v3_validation`.
- `DB_USERNAME=kp_stage_app`.
- `DB_PASSWORD` desde `.env.staging`; la app no recibe la clave HMAC ni el
  usuario ETL.
- `MAIL_MAILER=log`, sin SMTP real.
- `QUEUE_CONNECTION=sync`, sin workers externos.
- `SESSION_DRIVER=file`, `CACHE_STORE=file`.
- `FILESYSTEM_DISK=local`, con el disco local apuntando a
  `storage_path('app')`; ese directorio se monta desde el storage de staging.
- `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`.
- `BROADCAST_CONNECTION=log`, sin WebSocket externo.
- `ILOVEPDF_PUBLIC` e `ILOVEPDF_SECRET` vacíos; sin conversión externa.
- Sin credenciales de IMPULSE, pasarelas, correo real ni almacenamiento
  productivo.
- El montaje `./:/var/www/html` incluye los archivos `.env*` del proyecto. Por
  eso el archivo de entorno real se mantiene fuera del repositorio y la app no
  se inicia en la Etapa 1. Antes de iniciarla se debe aislar el montaje de
  código o cubrir esos archivos con un placeholder; el `.env` preexistente no
  debe quedar legible desde staging.

### 5.4 Servicio `staging-tools`

- Contenedor one-off, sin puerto, en la misma red, con perfil `tools`.
- Monta el directorio de importación externo en `/import` en solo lectura.
- Usa `kp_stage_etl`.
- Ejecuta restauración, enmascaramiento y validaciones.
- Recibe la clave HMAC como secreto de Docker; `staging-app` no la recibe.
- Se destruye al terminar (`docker compose run --rm`).

### 5.5 Usuarios de base

| Usuario | Permisos | Uso |
|---|---|---|
| `kp_stage_etl` | `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, LOCK TABLES` en las cinco bases | Restauración, ETL, migraciones |
| `kp_stage_app` | DML y tablas temporales solo en `kp_crm_v3_validation` | Aplicación V3 |
| `kp_stage_ro` | `SELECT` en masked y validation | Conciliación y evidencias |
| `root` | Solo dentro del contenedor | Administración excepcional |

El ETL no tiene privilegios globales ni permisos de rutinas, vistas, triggers,
eventos o tablas temporales. La app no usa `root` ni el usuario ETL. Las
contraseñas de base viven en el archivo de entorno externo (por ejemplo,
`C:\KP-CRM-Staging\.env.staging`), fuera del repositorio e ignorado por Git. La
clave HMAC también vive fuera del repositorio y se monta solo en
`staging-tools` como `/run/secrets/mask_hmac_key`. No se imprimen secretos en
logs ni en argumentos de comandos.

## 6. Red y aislamiento

- Red Docker `kpcrm_staging_net`, dedicada y separada de `crm_net`.
- Estado verificado en la Etapa 1: red `bridge` dedicada, `internal=false`, con
  solo `kpcrm-staging-db` conectado; la app no se inició. La base no publica
  puertos al host.
- El bloqueo de salida a Internet, correo, mensajería y APIs operativas **no**
  está implementado todavía. Es un requisito de la Etapa 2: evaluar
  `internal: true` para DB/ETL, una red de borde para la app y/o firewall del
  host, y validar que no se rompan los puertos locales antes de confiar en esa
  configuración.
- Solo se publica `127.0.0.1:8090` para la app; nada en `0.0.0.0`. La base no
  publica puerto: se administra con `docker exec` o `staging-tools`.
- Sin túneles, ngrok, Cloudflare Tunnel ni acceso remoto.
- Sin volumen compartido con el entorno `kpcrm-meridian`.
- Sin `DB_HOST` hacia la máquina Windows ni hacia `kpcrm-meridian-db-1`.
- Sin credenciales de producción en el archivo de entorno externo.
- Guardia de arranque propuesta: la app debe rechazar iniciar si
  `DB_DATABASE` no está en la lista permitida o si `APP_ENV` no es `staging`.
  Se implementará en una fase posterior, no ahora.

## 7. Storage y documentos

Bind mounts bajo `storage/staging/`, separados del storage actual. El código
usa `storage_path('app/...')`, por lo que el contenido de
`storage/staging/app` se monta en `/var/www/html/storage/app` **solo** en los
contenedores de staging:

```text
storage/staging/
├── app/
│   ├── cna/
│   │   ├── docx/
│   │   └── pdf/
│   ├── ccd/
│   ├── templates/
│   │   ├── cna_kpinvest.docx
│   │   ├── cna_fondo_acreencia_arequipa.docx
│   │   ├── cna_fondo_acreencia_arequipa_2.docx
│   │   └── Acuerdo_de_Pago_DNI_{dni}.docx
│   └── tmp/
├── framework/
├── logs/
└── bootstrap-cache/
```

Montajes del contenedor de staging:

- `storage/staging/app` -> `/var/www/html/storage/app`
- `storage/staging/framework` -> `/var/www/html/storage/framework`
- `storage/staging/logs` -> `/var/www/html/storage/logs`
- `storage/staging/bootstrap-cache` -> `/var/www/html/bootstrap/cache`

- No se monta `storage/app` de producción.
- No se reutiliza el `storage/app` del repositorio; se monta el bind de
  staging encima para que `storage_path('app')` resuelva a los placeholders.
- Se aíslan framework, logs y bootstrap-cache para no mezclar sesiones,
  cachés, vistas ni logs con el entorno actual.
- `storage/staging`, los dumps y los secretos deben quedar fuera de Git
  (`.gitignore` y ACL restringida).
- No se copian documentos reales al repositorio.
- Las plantillas se generan como placeholders locales, sin metadatos reales.
- Los PDF/DOCX de CCD, CNA y promesas se generan con nombres sintéticos y
  contenido de prueba.
- El storage de staging se borra al cerrar la validación, previa aprobación.

Las rutas de código que se deben satisfacer son las de
`CnaDocumentService`, `CnaNumberingService` y `PromesaDocumentService`; las
plantillas actuales no existen en `storage/app/templates`, por lo que la
generación documental no puede validarse sin estos placeholders.

## 8. Flujo de datos

```text
Respaldo fresco autorizado
        |
        v
kp_crm_v1_reference  (sin enmascarar, sin app)
        |
        | ETL con secreto HMAC
        v
kp_crm_staging_masked  (sin app)
        |
        | baseline V3 + migraciones
        v
kp_crm_v3_validation  (app V3, solo datos enmascarados)
        |
        +--> pruebas funcionales
        +--> conciliación
        +--> ensayo de rollback
```

Para la primera validación técnica se puede usar `u480021566_kpinvest_bd`
como fuente de solo lectura, sin crear `kp_crm_v1_reference`. Para cualquier
decisión de cutover se exige el respaldo fresco.

## 9. Respaldos y retención

- El dump fresco se guarda cifrado, fuera del repositorio y con checksum.
- La base `kp_crm_v1_reference` no se respalda a otro medio sin cifrado.
- La base enmascarada y la de validación se pueden respaldar solo si el
  respaldo queda cifrado y con acceso restringido.
- El mapa HMAC y el secreto se destruyen juntos al cerrar la validación.
- Se conservan evidencias agregadas (conteos, sumas, capturas sin PII), no
  filas.
- No se envían dumps, filas, rutas reales ni capturas a agentes de IA.

## 10. Recursos sugeridos

| Recurso | Mínimo | Recomendado |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disco libre | 5 GB | 10 GB |
| Red | localhost | localhost |

El disco debe cubrir dump comprimido, base reference, base masked, base
validation, binlogs temporales y storage de placeholders.

## 11. Fases de ejecución

| Fase | Acción | Autorización |
|---|---|---|
| A0 | Crear documentación y revisar este paquete | G0 |
| A1 | Crear contenedores y bases vacías | G0 |
| A2 | Restaurar copia local o respaldo fresco en reference | G1-B / autorización de ejecución |
| A3 | Enmascarar y validar PII | G3 |
| A4 | Construir validación V3 | G3 |
| A5 | Pruebas funcionales, integridad y rendimiento | G4 |
| A6 | Ensayo de rollback | G6 |
| A7 | Cierre y destrucción de datos temporales | Aprobación |

## 12. Criterios de aceptación

- [ ] `compose.crm.yml` y los contenedores actuales siguen intactos.
- [ ] Las tres bases existentes no tienen cambios.
- [ ] La app de staging solo puede conectarse a `kp_crm_v3_validation`.
- [ ] El ETL es el único que accede a `kp_crm_v1_reference` y
      `kp_crm_staging_masked`.
- [ ] `kp_crm_mask_work` no tiene acceso desde la app.
- [ ] Los puertos están en `127.0.0.1`.
- [ ] No hay credenciales de producción en staging.
- [ ] El storage de staging no contiene documentos reales.
- [ ] El ensayo de borrado y retención está aprobado.

## 13. Decisiones abiertas

1. Copia completa enmascarada o subconjunto de UI.
2. Perfil de `sql_mode`: estricto, por defecto de MariaDB o ambos.
3. ¿Se agrega una red `internal: true` para DB/ETL, con la app en una red
   de borde? (recomendado, validando los puertos publicados)
4. Ruta local protegida para dumps y secretos, fuera del repositorio.
5. Responsable de custodia del secreto HMAC y del mapa.
