# Scripts de staging

Ejecutar desde la raíz del repositorio. Los scripts que modifican datos exigen
`-Execute`; sin ese modificador solo muestran el plan.

> **G1.5:** hay **dos servidores MariaDB** y cinco redes externas:
>
> | Red | `internal` | Miembros | Uso |
> | --- | --- | --- | --- |
> | `kpcrm_staging_ref` | sí | `kpcrm-staging-ref-db`, `kpcrm-staging-tools` | referencia sin enmascarar |
> | `kpcrm_staging_tools_db` | sí | `kpcrm-staging-db`, `kpcrm-staging-tools` | restauración/enmascaramiento |
> | `kpcrm_staging_private` | sí | `kpcrm-staging-db`, `kpcrm-staging-app` | validación V3 |
> | `kpcrm_staging_app_net` | sí | `kpcrm-staging-app`, `kpcrm-staging-web` | app ↔ proxy |
> | `kpcrm_staging_web` | no (bridge) | `kpcrm-staging-web` | publica `127.0.0.1:8090` |
>
> `kpcrm-staging-ref-db` guarda `kp_crm_v1_reference` y **no comparte red con la
> app**: aunque la app se comprometa, no existe ruta hacia los datos sin
> enmascarar. `kpcrm-staging-db` guarda `kp_crm_staging_masked`,
> `kp_crm_mask_work`, `kp_crm_v3_validation` y `kp_crm_restore_rehearsal`.
> Los roles están separados (`restore`, `ref_ro`, `mask`, `app`, `ro`);
> `kp_stage_etl` queda bloqueado. Las ventanas de restauración están cerradas
> por defecto.
>
> Solo el proxy tiene egreso. Es un riesgo residual conocido: el contenedor web
> no recibe secretos ni datos.

## 1. Generar secretos locales

La clave HMAC, el archivo de entorno y el directorio de importación deben quedar
**fuera del repositorio**. Ejemplo:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/New-StagingSecrets.ps1 `
  -MaskKeyPath C:\KP-CRM-Staging\secrets\mask_hmac_key `
  -ImportDir C:\KP-CRM-Staging\import
```

Crea `C:\KP-CRM-Staging\.env.staging` (fuera del repositorio, ignorado por Git)
y la clave externa. El CRM actual monta el repositorio completo; por eso las
credenciales no se dejan dentro. No imprime valores. No usar credenciales de
IMPULSE ni de producción.

Restrinja las ACL y verifique que ningún trustee prohibido (Usuarios,
Everyone, códigos de servicio, etc.) tenga acceso:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Protect-StagingSecrets.ps1 -Execute
```

## 2. Crear redes e iniciar staging

Las redes son externas: las crea este script para que Compose no las recree ni
intente recrear los contenedores existentes. Es idempotente y solo
crea/conecta/desconecta endpoints; no recrea contenedores ni volúmenes.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Set-StagingNetwork.ps1 -Execute
```

Después:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Start-Staging.ps1 -Execute
```

Inicia `kpcrm-staging-ref-db` y `kpcrm-staging-db` por defecto, con
`--no-recreate`. Ninguna base publica puerto. `-IncludeApp` añade
`kpcrm-staging-app` y el proxy `kpcrm-staging-web`; solo el proxy publica
`127.0.0.1:8090`. Úselo después de enmascarar y verificar la copia.

Para recrear cuentas y grants sin recrear contenedores:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Apply-StagingHardening.ps1 -Execute
```

No agregue `-EnableReferenceRestore` ni `-EnableRehearsalRestore` salvo durante
una ventana explícita de restauración. El script cierra la ventana al terminar,
incluso si falla.

Las herramientas se ejecutan siempre con `--no-deps`; de otro modo Compose
puede recrear `staging-db` o `staging-ref-db` por su `depends_on`.

## 3. Restaurar la referencia

La referencia se restaura **solo** en `kpcrm-staging-ref-db`. Abra la ventana y
ejecute la restauración; el script la cierra al finalizar:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Apply-StagingHardening.ps1 -Execute -EnableReferenceRestore

powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Restore-Reference.ps1 `
  -DumpPath C:\KP-CRM-Staging\import\impulse_autorizado.sql -Execute
```

El dump debe ser `.sql` sin comprimir, debe estar fuera del repositorio y la
base `kp_crm_v1_reference` debe estar vacía. El escáner rechaza sentencias
`USE`, `CREATE/DROP DATABASE`, `GRANT/REVOKE`, `SET GLOBAL`, `LOAD DATA`,
`INTO OUTFILE`, definiciones de usuarios/triggers/rutinas/vistas, comentarios
ejecutables de versión (`/*!...`) con contenido peligroso y cualquier intento
de salir de la base destino. Después verifica 14 tablas, cuenta las
migraciones y revoca la escritura de `kp_stage_restore` sobre la referencia.
Si la restauración falla antes o después de empezar, cierra la ventana y deja
un reporte `restore_<base>_failed.json` con `write_window_closed=true`.

Para el ensayo de rollback (base de proceso, no la referencia):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Apply-StagingHardening.ps1 -Execute -EnableRehearsalRestore

powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Restore-Reference.ps1 `
  -DumpPath C:\KP-CRM-Staging\import\impulse_autorizado.sql `
  -TargetDatabase kp_crm_restore_rehearsal -Execute
```

## 4. Enmascarar y verificar

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Invoke-Masking.ps1 -Execute
```

Ejecuta `preflight` contra `kpcrm-staging-ref-db`, copia/enmascara en
`kpcrm-staging-db` y corre la verificación automática. Si la verificación falla,
el comando termina con error. Los reportes quedan en
`storage/staging/reports/`.

La verificación también se puede repetir sin modificar datos:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingIntegrity.ps1
```

## 5. Pruebas de G1.5

Todas son de solo lectura salvo donde se indique; escriben JSON en
`storage/staging/reports/`.

```powershell
# 82 pruebas: redes, DNS, rutas por IP real, egreso, montajes, secretos,
# privilegios efectivos y salud.
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingIsolation.ps1

# 33 pruebas negativas: escáner de dumps, ventana cerrada, SQL inválido,
# destino no vacío y cierre de ventana tras fallo.
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingRestoreGuards.ps1

# Dos pasadas de enmascaramiento sobre el mismo fixture; exige hash idéntico.
# Reinicia solo kp_crm_staging_masked (base desechable).
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Test-StagingDeterminism.ps1

# Estado final consolidado (contenedores, redes, volúmenes, bases, grants).
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Write-StagingFinalState.ps1
```

`Write-StagingFinalState.ps1` genera `g15_final_state.json` y no modifica
datos. Úselo como evidencia antes de solicitar aprobación para la etapa
siguiente.

## 6. Detener staging

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migration/Stop-Staging.ps1 -Execute
```

Detiene los contenedores sin `-v`: los volúmenes `kpcrm_staging_mariadb_data`
y `kpcrm_staging_ref_data` se conservan. No ejecutar `docker compose down -v`
salvo que se quiera destruir la copia temporal y se haya autorizado.

## Orden obligatorio

1. Revisar y aprobar los resultados de G1.
2. Generar secretos y restringir ACL.
3. Crear redes e iniciar staging.
4. Abrir la ventana de referencia y restaurar.
5. Cerrar la ventana, enmascarar y verificar.
6. Correr las pruebas de aislamiento, guardas y determinismo.
7. Construir/validar V3 sobre `kp_crm_v3_validation`.
8. Ejecutar checklist funcional.
9. Ensayar rollback en `kp_crm_restore_rehearsal`.
10. Obtener y validar el dump fresco de IMPULSE (G1-B) antes de cualquier
    cutover.

Nada de esto autoriza todavía un cutover productivo ni la conexión de V3 a
producción. La etapa 2 no comienza hasta que se apruebe la evidencia de G1.5.
