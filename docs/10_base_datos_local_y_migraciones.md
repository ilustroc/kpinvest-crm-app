# 10 - Base de Datos Local y Migraciones

## Estado confirmado

La estrategia de base de datos V3 ya parte de un entorno local listo:

- [+] Dump descargado desde produccion.
- [+] Base importada en local.
- [+] Proyecto apuntando a localhost.
- [+] `.env` local apunta a la base local.
- [+] Produccion no se toca.
- [+] Base local usada para pruebas de Fase 1 a Fase 4.
- [+] `php artisan migrate:status` revisado.
- [+] Migraciones antiguas revisadas.
- [+] Migraciones antiguas eliminadas.
- [+] Baseline V3 creado desde `u480021566_kpinvest_bd.sql`.
- [+] Migraciones V3 probadas en base local limpia `kpinvest_v3_migrate_test`.
- [+] Rollback/migrate de migraciones V3 probado en `kpinvest_v3_migrate_test`.
- [+] Estructura generada exportada en `estructura_generada_v3.sql`.
- [!] Diferencia intencional detectada: `users.role` ya no incluye `sistemas` ni `usuario`.
- [!] Produccion no debe ejecutar estas migraciones todavia.

La base local sera el ambiente seguro para pruebas, validaciones y migraciones V3.

## Estado revisado antes del baseline V3

Comando ejecutado:

```bash
php artisan migrate:status
```

Resultado:

```text
2019_12_14_000001_create_personal_access_tokens_table ........ Ran
2025_11_02_220020_update_users_drop_team_supervisor_add_active  Ran
2025_11_05_172046_add_supervisor_id_to_users .................. Ran
2025_11_13_155533_create_asignar_clientes_table ............... Ran
2026_01_12_221108_create_cliente_bloqueos_table ............... Ran
2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago . Ran
```

Migraciones presentes en `database/migrations`:

```text
2025_11_02_220020_update_users_drop_team_supervisor_add_active.php
2025_11_05_172046_add_supervisor_id_to_users.php
2025_11_13_155533_create_asignar_clientes_table.php
2026_01_12_221108_create_cliente_bloqueos_table.php
2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago.php
```

Hallazgos previos:

- [!] La tabla `migrations` local registra `2019_12_14_000001_create_personal_access_tokens_table`, pero ese archivo no esta presente en `database/migrations`.
- [!] La base local tiene tablas core que no nacen de migraciones disponibles: `users`, `clientes_cuentas`, `pagos_propia`, `promesas_pago`, `promesa_cuotas`, `cna_solicitudes`, `ccd_clientes`, `pagos_lotes` y `promesa_operaciones`.
- [+] Las tablas principales necesarias para las pruebas locales existen en la base local.
- [+] Esta inconsistencia justifico eliminar las migraciones historicas incompletas y crear baseline V3.

## Migraciones eliminadas en Fase 5

Se eliminaron los archivos antiguos de `database/migrations`:

```text
2025_11_02_220020_update_users_drop_team_supervisor_add_active.php
2025_11_05_172046_add_supervisor_id_to_users.php
2025_11_13_155533_create_asignar_clientes_table.php
2026_01_12_221108_create_cliente_bloqueos_table.php
2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago.php
```

Motivo:

- No representaban el esquema real completo de produccion.
- Dejaban tablas core sin migracion real.
- Mezclaban parches historicos con una base que ya existia.
- V3 necesita un baseline claro para reconstruir una base desde cero.

## Migraciones V3 creadas

```text
2026_05_15_000001_create_users_table.php
2026_05_15_000002_create_clientes_tables.php
2026_05_15_000003_create_pagos_tables.php
2026_05_15_000004_create_promesas_tables.php
2026_05_15_000005_create_cna_solicitudes_table.php
2026_05_15_000006_create_integracion_tables.php
2026_05_15_000007_normalize_user_roles_v3.php
```

Tabla Laravel estandar incluida por migracion vendor:

```text
2019_12_14_000001_create_personal_access_tokens_table
```

Esa migracion viene de Sanctum y crea `personal_access_tokens`, por eso no se duplico en el baseline propio.

## Prueba en base limpia

Base desechable creada:

```text
kpinvest_v3_migrate_test
```

Comando ejecutado contra esa base:

```bash
php artisan migrate
```

Resultado:

- [+] Migracion completada correctamente.
- [+] `php artisan migrate:status` muestra todas las migraciones V3 como `Ran`.
- [+] `php artisan migrate:rollback` bajo todas las migraciones en base desechable.
- [+] `php artisan migrate` volvio a subir todas las migraciones despues del rollback.
- [+] Indices coinciden con la base real.
- [+] FKs coinciden con la base real.
- [+] Tablas coinciden con la base real.
- [!] Diferencia intencional: `users.role` queda como `enum('administrador','supervisor','asesor','soporte')` con default `soporte`.

## Diferencias SQL real vs baseline V3

Diferencia documentada:

```text
users.role
SQL real: enum('administrador','supervisor','asesor','sistemas','soporte','usuario') default 'usuario'
V3:       enum('administrador','supervisor','asesor','soporte') default 'soporte'
```

Motivo:

- `sistemas` y `usuario` fueron eliminados como roles validos de V3.
- La migracion `2026_05_15_000007_normalize_user_roles_v3.php` convierte esos roles a `soporte` antes de cerrar el enum.

Diferencias aceptadas:

- La tabla `migrations` no se define manualmente porque Laravel la administra.
- `personal_access_tokens` se crea con la migracion vendor de Sanctum, equivalente al SQL real.
- No se crearon `password_reset_tokens`, `sessions`, `cache` ni `jobs` porque no existen en el SQL real actual.

## Regla principal

Produccion queda congelada hasta nuevo aviso.

No se debe:

- Ejecutar `php artisan migrate` en produccion.
- Ejecutar seeders en produccion.
- Ejecutar `php artisan migrate:fresh`.
- Borrar tablas.
- Borrar columnas.
- Cambiar tipos de columnas sin prueba local.
- Asumir que las migraciones historicas actuales representan produccion.

## Uso de la base local

La base local permite:

- Probar el sistema con datos equivalentes a produccion.
- Validar cambios de frontend sin tocar datos reales.
- Probar migraciones nuevas.
- Comparar estructuras.
- Revisar performance de reportes.
- Probar imports y exports en ambiente controlado.

Antes de cualquier cambio funcional, se debe confirmar que el flujo actual funciona en local.

Estado Fase 4:

- [+] Smoke tests locales ejecutados contra la base importada.
- [+] Login/redireccion, Dashboard, Administracion, busqueda/vista de cliente, autorizacion, reportes e importaciones renderizan.
- [!] No se ejecutaron cambios destructivos ni POST reales de aprobacion/importacion durante esta validacion.

## Configuracion local

El `.env` local debe apuntar a MySQL/MariaDB local:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u480021566_kpinvest_bd
DB_USERNAME=root
DB_PASSWORD=********
```

No versionar `.env`.

No usar host, usuario o password de produccion en local.

## Rol del archivo SQL

El archivo:

```text
u480021566_kpinvest_bd.sql
```

sirve como referencia historica y tecnica de la estructura real conocida.

Se usa para:

- Revisar tablas.
- Revisar columnas.
- Revisar indices.
- Revisar claves foraneas.
- Comparar contra migraciones actuales.
- Definir baseline.

Importante:

- Aunque ya existe una base local importada, antes de produccion se debe descargar un dump productivo actualizado.
- El dump actual no debe tratarse como eterno.

## Que significa baseline

Un baseline es el punto oficial desde el cual V3 empieza a construir migraciones ordenadas.

En este proyecto, el baseline representa:

- La estructura real de produccion antes de cambios V3.
- Las tablas existentes.
- Las columnas legacy que deben conservarse.
- Los indices y FKs reales.
- El estado inicial que no debe romperse.

## Estrategia recomendada de migraciones

1. Trabajar sobre la base local importada.
2. Documentar esquema actual.
3. Crear baseline tecnico.
4. Crear migraciones nuevas solo para cambios V3.
5. Ejecutar migraciones nuevas solo en local.
6. Comparar estructura antes/despues.
7. Probar flujos funcionales.
8. Repetir sobre dump actualizado antes de produccion.
9. Solo con aprobacion, planificar deploy.

## Opciones de baseline

### Opcion A - SQL baseline

Mantener una copia de estructura SQL como referencia base.

Ventaja:

- Es fiel al estado real.
- Evita ejecutar migraciones antiguas incompletas.

Riesgo:

- No queda completamente integrado al flujo Laravel.

### Opcion B - Migracion baseline completa

Crear migracion o grupo de migraciones que representen el esquema actual.

Ventaja:

- Ambientes nuevos pueden reconstruirse desde Laravel.

Riesgo:

- No debe ejecutarse sobre una base que ya tiene esas tablas.

### Opcion C - Baseline mixto

Usar SQL como referencia y crear migraciones Laravel desde V3 en adelante.

Recomendacion:

- Usar baseline mixto en una primera etapa.
- No forzar una reconstruccion total hasta tener pruebas.

## Validar migraciones en local

Cuando se creen migraciones V3:

- [+] Revisar `php artisan migrate:status`.
- [+] Ejecutarlas solo en local cuando existan migraciones nuevas.
- [+] Revisar estructura de tablas afectadas.
- [+] Confirmar indices.
- [+] Confirmar FKs.
- [+] Confirmar que no se perdieron datos en base desechable.
- [+] Probar rollback si aplica.
- [+] Probar flujos funcionales iniciales.
- [ ] Revisar logs.

## Comparar estructura local vs esperada

Opciones recomendadas:

- Exportar estructura local con `mysqldump --no-data`.
- Revisar `SHOW CREATE TABLE`.
- Comparar dump antes/despues.
- Documentar diferencias intencionales.

Ejemplo local:

```bash
mysqldump -u root -p --no-data u480021566_kpinvest_bd > estructura_local.sql
```

Comparacion conceptual:

```bash
git diff --no-index estructura_antes.sql estructura_local.sql
```

Estos comandos deben usarse solo en local o staging.

## Antes de produccion

Antes de tocar produccion real:

- [ ] Descargar dump actualizado de produccion.
- [ ] Importarlo en local o staging.
- [ ] Ejecutar migraciones V3 sobre esa copia.
- [ ] Validar estructura.
- [ ] Ejecutar pruebas funcionales.
- [ ] Confirmar backup real.
- [ ] Confirmar rollback.
- [ ] Definir ventana de deploy.

## Riesgos de migrar sin revisar

- Ejecutar migraciones antiguas incompletas.
- Duplicar tablas.
- Eliminar columnas legacy.
- Romper relaciones.
- Perder indices.
- Cambiar tipos incompatibles con datos existentes.
- Fallar por datos reales no previstos.
- Dejar la tabla `migrations` en estado inconsistente.

## Seeders

Por ahora:

- No correr seeders en produccion.
- No crear seeders que pisen usuarios reales.
- No modificar contrasenas productivas.
- No se crearon ni ejecutaron seeders en esta fase.

## Decision Fase 5

El baseline V3 ya existe y fue probado en una base limpia. No debe ejecutarse sobre la base local importada ni sobre produccion sin una estrategia de baseline aplicada, porque esas tablas ya existen.

Antes de produccion se debe decidir si:

- Se crea una base nueva desde migraciones V3 y se importan datos.
- O se marca el baseline como aplicado y solo se ejecutan migraciones incrementales.
- O se usa un plan SQL/manual controlado para transicionar una base existente.

Cuando se necesiten seeders:

- Deben ser idempotentes.
- Deben usar `updateOrCreate` o estrategia equivalente.
- Deben estar probados en local.
- Deben documentar claramente que datos crean o modifican.

## Regla final

La base local es el laboratorio. Produccion no se toca hasta que el cambio haya sido probado contra una copia reciente y exista rollback.
