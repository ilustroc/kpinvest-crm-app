# 10 - Base de Datos Local y Migraciones

## Objetivo

Definir una estrategia segura para trabajar con una copia local de la base de produccion y ordenar migraciones de V3 sin afectar datos reales.

La regla principal:

```text
Nunca ejecutar migraciones nuevas directamente en produccion sin probarlas antes sobre una copia reciente de produccion.
```

## Base local de trabajo

El proyecto puede ejecutarse localmente con MySQL/MariaDB. La configuracion local debe vivir solo en `.env` y no debe versionarse con credenciales reales.

Ejemplo de variables locales:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u480021566_kpinvest_bd
DB_USERNAME=root
DB_PASSWORD=********
```

Recomendacion:

- Usar una base local aislada.
- Confirmar visualmente que `DB_HOST` apunta a `127.0.0.1` o `localhost`.
- No usar credenciales ni host de produccion en local.
- No copiar `.env` productivo al repositorio.

## Uso del SQL actual

El archivo:

```text
u480021566_kpinvest_bd.sql
```

representa la estructura real conocida de produccion y sirve como referencia. Puede usarse para:

- Entender tablas reales.
- Revisar columnas, indices y claves foraneas.
- Comparar contra migraciones existentes.
- Crear un baseline.

Pero no debe asumirse que siempre esta actualizado. Antes de una fase de deploy, se debe descargar un dump nuevo de produccion.

## Importar dump SQL localmente

Proceso recomendado:

1. Confirmar que el dump corresponde a produccion actual.
2. Guardar el dump en una carpeta local segura.
3. Crear o limpiar una base local de pruebas.
4. Importar el dump solo en local.
5. Apuntar `.env` local a esa base.
6. Probar el sistema.

Ejemplo usando consola MySQL, sin poner password en el comando:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS u480021566_kpinvest_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p u480021566_kpinvest_bd < u480021566_kpinvest_bd.sql
```

Advertencia:

- Si el dump contiene `DROP TABLE`, puede borrar tablas de la base local destino.
- Antes de importar, confirmar que la base destino es local y desechable.
- No ejecutar estos comandos contra produccion.

## Que significa crear un baseline

Un baseline es un punto de partida oficial del esquema actual.

En este proyecto, el baseline debe representar la base real existente en produccion antes de empezar cambios V3.

Sirve para:

- Evitar que Laravel intente recrear todo desde migraciones historicas incompletas.
- Marcar la estructura actual como conocida.
- Crear migraciones futuras solo para cambios posteriores.
- Comparar estructura esperada vs estructura real.

Opciones de baseline:

### Opcion A - Migracion baseline documentada

Crear una migracion que represente el esquema completo actual, pero no ejecutarla sobre produccion si la estructura ya existe. Se usa para ambientes nuevos o para documentacion tecnica.

Ventaja:

- El esquema queda versionado en Laravel.

Riesgo:

- Si se ejecuta en una base con tablas existentes, puede fallar o duplicar estructura.

### Opcion B - Baseline por SQL versionado

Mantener un dump de estructura como referencia inicial y crear migraciones solo desde V3 en adelante.

Ventaja:

- Menor riesgo inmediato.

Riesgo:

- Menos integrado al flujo Laravel.

### Opcion C - Marcar migraciones como aplicadas

En una base existente, registrar migraciones baseline en la tabla `migrations` sin ejecutar cambios reales, solo si el equipo valida que la estructura coincide.

Ventaja:

- Permite continuar con migraciones futuras.

Riesgo:

- Si se marca algo incorrectamente, se pierde trazabilidad.

## Recomendacion para este proyecto

Usar una estrategia mixta:

1. Mantener el dump productivo como referencia.
2. Crear documentacion de esquema.
3. Crear una migracion baseline o set de migraciones baseline para ambientes nuevos.
4. No ejecutar baseline sobre produccion existente.
5. Crear migraciones V3 incrementales para cambios nuevos.
6. Probar todo sobre copias locales recientes.

## Ordenar migraciones sin afectar produccion

Pasos:

1. Importar dump productivo en local.
2. Confirmar que la app funciona con esa base.
3. Crear baseline de estructura.
4. Crear nueva migracion V3 para el cambio deseado.
5. Ejecutar `php artisan migrate` solo en local.
6. Revisar estructura resultante.
7. Probar funcionalidad.
8. Si algo falla, ajustar migracion localmente.
9. Repetir sobre una copia nueva antes de deploy.

## Validar migraciones en local

Validaciones minimas:

- `php artisan migrate:status`.
- Revisar tablas afectadas en MySQL.
- Comparar columnas antes/despues.
- Confirmar indices y FKs.
- Probar inserts/updates reales de la app.
- Probar rollback si la migracion tiene `down`.
- Revisar logs.

No basta con que la migracion "corra"; tambien debe comprobarse que la aplicacion sigue funcionando.

## Comparar estructura local vs esperada

Opciones:

- Exportar estructura local despues de migrar.
- Comparar contra SQL esperado.
- Usar herramientas como `mysqldump --no-data`.
- Revisar `SHOW CREATE TABLE`.
- Documentar diferencias intencionales.

Ejemplo conceptual:

```bash
mysqldump -u root -p --no-data u480021566_kpinvest_bd > estructura_local.sql
```

Luego comparar:

```bash
git diff --no-index u480021566_kpinvest_bd.sql estructura_local.sql
```

Estos comandos son para entorno local o archivos, no para produccion.

## Antes de aplicar cambios en produccion

Checklist minimo:

- Descargar dump reciente de produccion.
- Importar dump en local o staging.
- Ejecutar migraciones V3 sobre esa copia.
- Confirmar que no hay perdida de datos.
- Probar flujos criticos.
- Confirmar backup real.
- Definir rollback.
- Revisar ventana de mantenimiento si aplica.
- Confirmar que el equipo entiende el cambio.

## Riesgos de correr migrate sin revisar

- Laravel puede intentar ejecutar migraciones antiguas incompletas.
- Se pueden crear tablas duplicadas o inconsistentes.
- Se pueden eliminar columnas legacy usadas por produccion.
- Se pueden perder indices o claves foraneas.
- Un cambio de tipo de dato puede fallar por datos existentes.
- Un rollback mal definido puede borrar informacion.
- La tabla `migrations` puede no reflejar la historia real.

## Seeders

Los seeders no deben ejecutarse sobre produccion sin revision.

Pueden usarse en V3 para:

- Roles base.
- Usuarios demo locales.
- Catalogos controlados.

Pero antes deben responder:

- Son seguros si se ejecutan dos veces.
- Usan `updateOrCreate` o una estrategia idempotente.
- No pisan usuarios reales.
- No cambian contrasenas productivas.

## Reglas finales

- Primero dump local, despues migraciones.
- Primero pruebas, despues deploy.
- Primero backup, despues cambios.
- Nunca confiar en migraciones antiguas sin compararlas con SQL real.

