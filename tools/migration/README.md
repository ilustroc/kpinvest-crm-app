# Herramientas de migración V1 → V3

Código PHP del ETL de staging. Se ejecuta dentro de `staging-tools`, nunca en
producción.

## Comandos

```text
php tools/migration/cli.php preflight
php tools/migration/cli.php mask --execute --confirm=staging-mask
php tools/migration/cli.php verify
php tools/migration/cli.php plan
php tools/migration/cli.php help
```

- `preflight` valida aislamiento, versión de MariaDB, charset, collation,
  `sql_mode`, grants, secreto HMAC, esquema de referencia y bases vacías.
- `mask` copia y enmascara `kp_crm_v1_reference` hacia
  `kp_crm_staging_masked`. Sin `--execute --confirm=staging-mask` solo muestra
  el plan.
- `verify` es de solo lectura: compara conteos, FKs, claves únicas, sumas,
  roles, casos conocidos, PII residual exacta, formatos sintéticos, JSON y
  rutas.

## Controles de seguridad

- Se ejecuta con `docker compose run --rm --no-deps`; sin `--no-deps` Compose
  puede recrear `staging-db` por el `depends_on`.
- El rol se inyecta por ejecución (`ro`, `mask` o `restore`); no hay
  credenciales persistentes en el servicio.
- Solo acepta `MASK_ENV=staging`, `DB_HOST=staging-db` y bases `kp_crm_*`.
- Rechaza `u480021566_kpinvest_bd`, `kpinvest_v3_migrate_test`, `kp_crm_local`
  y las bases del sistema.
- Exige que la base enmascarada esté vacía antes de empezar. Si el proceso
  falla, se elimina solo esa base nueva de staging y se recrea; no se reintenta
  a ciegas sobre una copia parcial.
- No normaliza roles. Si aparece un valor distinto de `administrador`,
  `supervisor`, `asesor` o `soporte`, el proceso se detiene.
- No copia `personal_access_tokens`; la tabla queda vacía.
- Mantiene los mapas de pseudonimización solo en memoria. No los escribe en la
  base ni en el repositorio.
- No imprime valores originales, contraseñas ni la clave HMAC.

## Transformaciones

- Conserva IDs, importes, fechas, estados, cantidades, roles y relaciones.
- Genera DNI, nombres, teléfonos, operaciones, cuentas, correos, direcciones,
  rutas y notas ficticias.
- Usa tokens deterministas por valor de origen y evita colisiones con valores
  originales y entre tokens generados.
- Conserva el grupo de cada valor: dos filas con el mismo DNI original siguen
  apuntando al mismo cliente ficticio.
- Sustituye `notifications.data` por JSON sintético y valida
  `cna_solicitudes.operaciones` como JSON.
- Crea cuatro usuarios de prueba, uno por rol, con correos
  `usuario{id}@example.invalid` y la contraseña `STAGING_TEST_PASSWORD` del
  archivo de entorno externo `.env.staging` (no se muestra en logs).
- Conserva el historial de `migrations` para que la baseline V3 sea explícita.

## Reportes

Se escriben en `storage/staging/reports/` (ignorado por Git):

- `preflight.json`
- `mask_manifest.json`
- `masking_validation.json`

No contienen PII, secretos ni valores enmascarados; solo nombres de
tablas/columnas y conteos.
