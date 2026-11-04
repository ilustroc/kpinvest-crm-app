# 06. Evaluación MySQL 8.0.42 / MariaDB 11.8.9

> **Estado: propuesta. No se cambia el motor de producción.**
>
> La copia local `u480021566_kpinvest_bd` corre en MySQL 8.0.42. El staging
> propuesto y la producción usan MariaDB 11.8.x. Esta diferencia de motor es
> parte del riesgo a validar.

## 1. Versiones y entornos

| Entorno | Motor | Uso |
|---|---|---|
| Copia local V1 | MySQL 8.0.42 | Referencia histórica; `lower_case_table_names=1` |
| Test de esquema | MySQL 8.0.42 | Historial V3 de 8 migraciones; no se reutiliza |
| Staging V3 | MariaDB 11.8.9 | Validación principal |
| Producción IMPULSE | MariaDB 11.8.6 (según dump) | No se toca en esta fase |
| App local actual | MariaDB 11.8.9 en Docker | No se reutiliza |

Conclusión inicial: el staging debe ejecutarse en **MariaDB 11.8.9**, no en
MySQL 8, porque el destino real es MariaDB. MySQL 8 solo sirve para reproducir
la copia local y comparar comportamiento.

## 2. Resumen de compatibilidad

| Área | Resultado | Riesgo |
|---|---|---|
| Tablas/columnas/tipos | Equivalentes | Bajo |
| Índices/unique/FKs | Equivalentes | Bajo |
| `utf8mb4_unicode_ci` | Disponible en ambos | Bajo |
| `utf8mb4_bin` en `operaciones` | Disponible en ambos | Bajo |
| `JSON` como `LONGTEXT` + check | Portable | Bajo |
| `CHECK json_valid` | Ambos lo aplican | Bajo |
| `ENUM` | Ambos | Bajo; cuidado con datos |
| `TIMESTAMP` default/on update | Ambos | Bajo; representación distinta |
| `DATE` extremas | MariaDB acepta 0026/0005 en estricto | Bajo; probar |
| FK `NO ACTION` vs `RESTRICT` | Equivalente en InnoDB | Bajo |
| `lower_case_table_names` | 1 vs 0 | Medio |
| `sql_mode` | Diferente por defecto | Medio |
| `ONLY_FULL_GROUP_BY` | MariaDB lo soporta, no está activo por defecto | Medio |
| `REGEXP` | ICU vs PCRE | Medio |
| Dump/restore | Usar `mariadb-dump`, no `mysqldump` 8 | Medio |
| Collations `utf8mb4_0900_*` | No existen en MariaDB | Alto si aparecen |
| `GTID_PURGED` / `DEFINER` | Específicos de MySQL/producción | Alto si aparecen |

## 3. Diferencias de servidor

### 3.1 `sql_mode`

Copia local MySQL:

```text
ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,
ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

MariaDB 11.8.9 local, por defecto:

```text
STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,
NO_ENGINE_SUBSTITUTION
```

Se comprobó que MariaDB 11.8.9 acepta el perfil estricto completo, incluidos
`ONLY_FULL_GROUP_BY`, `NO_ZERO_DATE` y `NO_ZERO_IN_DATE`.

Recomendación:

- Ejecutar la validación principal con el perfil estricto, para reproducir
  MySQL y detectar consultas frágiles.
- Ejecutar una segunda pasada con el modo por defecto de MariaDB, para detectar
  dependencias del modo.
- No cambiar el `sql_mode` global de producción para "hacer pasar" una
  consulta o un dump.

### 3.2 `lower_case_table_names`

- Origen Windows MySQL: `1` (nombres de tabla insensibles a mayúsculas).
- Destino Linux MariaDB: `0` (sensibles a mayúsculas).
- Todas las tablas observadas están en minúsculas.
- `lower_case_table_names` no se puede cambiar después de inicializar el
  volumen; el staging debe usar un volumen nuevo.

Riesgo: una consulta cruda con `Clientes_Cuentas` o similar funcionaría en
Windows y fallaría en Linux. Antes de la prueba se debe escanear el código y el
dump. Si aparece un nombre mixto, se corrige el código o se aborta; no se
"arregla" con un `lower_case_table_names` improvisado.

### 3.3 Zona horaria

- Origen: `time_zone=SYSTEM`, zona de Perú.
- MariaDB local: `time_zone=SYSTEM`, `system_time_zone=-05`.
- La app usa `America/Lima`.

Recomendación:

- Exportar e importar con una zona horaria explícita y documentada.
- Si se usa UTC para el dump, registrar la decisión y verificar que las fechas
  y timestamps se muestran igual en la app.
- No comparar `TIMESTAMP` como texto sin fijar la sesión.
- Las fechas de negocio son `DATE`; no se convierten a `TIMESTAMP`.

### 3.4 Charset y collation

- 14/14 tablas usan `utf8mb4_unicode_ci`.
- 66 columnas de texto usan `utf8mb4_unicode_ci`.
- 1 columna (`operaciones`) usa `utf8mb4_bin`.
- No se observaron `utf8mb4_0900_*`.

MariaDB no soporta collations `utf8mb4_0900_*` de MySQL 8. Si el dump fresco
las trae, se debe abortar y convertir con un procedimiento revisado; no se
importa a ciegas.

## 4. Diferencias de DDL

| Elemento | MySQL 8.0.42 | MariaDB 11.8.9 | Tratamiento |
|---|---|---|---|
| `bigint(20)` | Muestra ancho | Muestra `bigint` | Cosmético |
| `int(10) unsigned` | Muestra ancho | `int unsigned` | Cosmético |
| `DEFAULT_GENERATED` | Aparece en `EXTRA` | No igual | Cosmético |
| `CURRENT_TIMESTAMP` | Texto distinto | `current_timestamp()` | Semánticamente igual |
| FK update rule | `NO ACTION` | `RESTRICT` | Equivalente |
| Check name | `cna_solicitudes_chk_1` | `cna_solicitudes_operaciones_json_check` | Cosmético |
| `JSON` | Tipo nativo | Alias `LONGTEXT` | El esquema usa `LONGTEXT` explícito; portable |
| `CHECK` | Se aplica | Se aplica | Validar con insert inválido |

No se debe comparar `SHOW CREATE TABLE` línea a línea para declarar
incompatibilidad. La comparación válida es por `information_schema`:
nombre, tipo, longitud, nulabilidad, default, índices, FKs y checks.

## 5. Tipos de datos y fechas

- `BIGINT UNSIGNED`, `INT UNSIGNED`, `TINYINT(1)`: equivalentes.
- `DECIMAL(18,2)`, `DECIMAL(15,2)`, `DECIMAL(12,2)`: equivalentes.
- `VARCHAR(n)`: longitud en caracteres en ambos; `utf8mb4` ocupa hasta 4 bytes.
- `TEXT`, `LONGTEXT`: equivalentes.
- `DATE`: MariaDB 11.8.9 aceptó `0026-01-23` y `0005-12-30` en modo estricto.
  Confirmar con el dump fresco y con las columnas exactas.
- `TIMESTAMP`: rango 1970–2038 en ambos. Las fechas de negocio son `DATE`;
  los `TIMESTAMP` se usan para auditoría. Verificar sus rangos en el dump
  fresco antes de importar.
- `ENUM`: ambos aceptan; el `ALTER TABLE ... MODIFY role ENUM(...)` funciona,
  pero el cambio de datos requiere aprobación.
- `BOOLEAN`: ambos lo tratan como `TINYINT(1)`.

## 6. JSON y checks

- `cna_solicitudes.operaciones` es `LONGTEXT` con
  `CHECK (json_valid(operaciones))`.
- MySQL y MariaDB aceptan `JSON_VALID`, `JSON_TYPE` y `JSON_LENGTH`.
- MariaDB almacena JSON como texto; Laravel lo castea a array. No se debe
  asumir que el tipo nativo `JSON` está disponible.
- El check debe probarse con un `INSERT` inválido en staging; debe fallar en
  ambos motores.
- No cambiar `utf8mb4_bin` por `utf8mb4_unicode_ci`; el check y las
  comparaciones podrían cambiar.

## 7. Consultas y comportamiento

| Tema | Riesgo | Prueba |
|---|---|---|
| `ONLY_FULL_GROUP_BY` | Consultas con columnas no agregadas fallan | Ejecutar suite y reportes con modo estricto |
| `REGEXP` | ICU (MySQL) vs PCRE (MariaDB) | Probar búsquedas y filtros que usen regex |
| `LIKE` | Collation `unicode_ci` | Probar mayúsculas/minúsculas y acentos |
| `ORDER BY` con `NULL` | Puede cambiar el orden visual | Probar listados y paginación |
| `GROUP_CONCAT` | Límite por `group_concat_max_len` | Reportes con muchos valores |
| `JSON` | Texto vs nativo | Casting de `operaciones`, filtros y guardado |
| `TIMESTAMP` | Zona horaria | Altas, edición y listados |
| Transacciones | `lockForUpdate` en CNA | Crear CNA concurrentes en staging |
| Paginación | Tablas de 200k+ filas | Búsqueda y reportes |

## 8. Dump y restore

- Usar `mariadb-dump`/`mariadb` 11.8.x para producción MariaDB.
- No usar `mysqldump` 8.0 contra MariaDB 11.8 como herramienta principal.
- Evitar `--set-gtid-purged` (MySQL) y `GTID_PURGED` en el dump.
- Evitar `DEFINER` de producción.
- Abortar si aparecen `utf8mb4_0900_*`, `CREATE DATABASE`, `USE` o
  `DROP DATABASE`.
- Importar con `utf8mb4` y `sql_mode` de sesión explícito.
- Validar 0 huérfanos después de importar, aunque el dump traiga
  `FOREIGN_KEY_CHECKS=0`.

## 9. Plan de pruebas de compatibilidad

### 9.1 DDL

- [ ] Crear `kp_crm_v3_validation` vacía en MariaDB 11.8.9.
- [ ] Aplicar baseline V3 y migraciones.
- [ ] Comparar 14 tablas/153 columnas contra V1.
- [ ] Verificar 6 unique, 8 FKs y 1 check.
- [ ] Verificar `utf8mb4_unicode_ci` y `operaciones` bin.
- [ ] Confirmar 0 triggers/vistas/rutinas/eventos no aprobados.

### 9.2 Datos

- [ ] Importar la copia enmascarada.
- [ ] Verificar conteos, sumas, fechas y estados.
- [ ] Insertar un JSON inválido en CNA: debe fallar.
- [ ] Insertar un email duplicado: debe fallar.
- [ ] Insertar una asignación duplicada: debe fallar.
- [ ] Probar FK `CASCADE` y `SET NULL` con registros de staging.
- [ ] Probar fechas extremas del dump.
- [ ] Probar `TIMESTAMP` default y `ON UPDATE`.
- [ ] Probar `ALTER TABLE` de `role` solo en staging y con aprobación.

### 9.3 Aplicación

- [ ] Login/logout con las cuatro credenciales de staging.
- [ ] Búsqueda por DNI enmascarado, operación y nombre token.
- [ ] Alta/edición de promesa y CNA.
- [ ] Workflow de aprobación/rechazo.
- [ ] Reportes con `GROUP BY`.
- [ ] Importación CSV de staging.
- [ ] Generación/descarga de documentos placeholder.
- [ ] Notificaciones en base y log.
- [ ] `php artisan test` en staging (sin `RefreshDatabase` sobre datos
      valiosos).
- [ ] `npm run build` y carga de assets.

### 9.4 Rendimiento

- [ ] Búsqueda en `clientes_cuentas` con 229k filas.
- [ ] Listado/paginación de CCD con 222k filas.
- [ ] Reporte de pagos y promesas.
- [ ] Creación concurrente de CNA (bloqueo de numeración).
- [ ] Restauración completa y tiempo de arranque.

### 9.5 Paridad opcional MySQL 8

Solo si se aprueba: crear una base nueva en MySQL 8.0.42 (no reutilizar
`kpinvest_v3_migrate_test`) y repetir DDL + datos. Sirve para distinguir un
defecto de esquema de un defecto de motor. No es la validación principal.

## 10. Recomendación

1. Validar en MariaDB 11.8.9, que es el motor destino.
2. Usar el esquema portable actual: `LONGTEXT` + check, `utf8mb4_unicode_ci`,
   `ENUM`, `TIMESTAMP` estándar.
3. No introducir tipos o collations exclusivos de MySQL 8.
4. Fijar `sql_mode` estricto en staging y probar también el modo por defecto.
5. Usar nombres de tabla en minúsculas y un volumen nuevo con
   `lower_case_table_names=0`.
6. Usar `mariadb-dump` para el respaldo fresco.
7. No cambiar el motor de producción ni el esquema durante esta fase.

## 11. Criterios de aceptación

- [ ] DDL V3 aplica sin errores en MariaDB 11.8.9.
- [ ] 14 tablas/153 columnas equivalentes.
- [ ] 0 diferencias semánticas de índices, FKs o checks.
- [ ] JSON inválido rechazado.
- [ ] Fechas extremas preservadas.
- [ ] `GROUP BY`, `REGEXP` y `LIKE` probados.
- [ ] Dump/restore con cliente MariaDB verificado.
- [ ] Sin collations `0900` ni `DEFINER`.
- [ ] App funciona con PDO MariaDB y `utf8mb4`.

## 12. Decisiones abiertas

1. ¿Se ejecuta la pasada opcional en MySQL 8.0.42?
2. ¿Se usa UTC o `America/Lima` para dump/import?
3. ¿Se activa `ONLY_FULL_GROUP_BY` en staging? (recomendado: sí)
4. ¿Se mantiene `lower_case_table_names=0`? (recomendado: sí, con código en
   minúsculas)
5. ¿Quién revisa las consultas crudas con nombres de tabla o regex?
