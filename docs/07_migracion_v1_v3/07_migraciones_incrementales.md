# 07. Estrategia de migraciones incrementales

> **Estado: propuesta. No ejecutada.**
>
> La migración de producción no usa la base enmascarada. El staging
> enmascarado sirve para validar el procedimiento; producción conserva sus
> datos reales y solo recibe los incrementos de esquema aprobados.

## 1. Objetivo

Pasar de V1/IMPULSE a V3 con el mínimo cambio de esquema posible, sin
`migrate:fresh`, sin transformar datos de negocio y sin normalizar roles sin
aprobación.

## 2. Principios

1. **Baseline mixto:** conservar las tablas V1 existentes y marcar como
   aplicado lo que ya coincide con V3.
2. **Solo incrementales:** aplicar únicamente lo que falta.
3. **Sin transformación silenciosa:** los datos se copian, no se "limpian".
4. **Roles con compuerta:** cualquier mapeo de roles requiere decisión
   explícita.
5. **Una sola escritura:** V1 o V3, no ambos, durante el corte.
6. **Rollback probado:** cada incremento tiene reversión o plan manual.
7. **Producción no se enmascara:** el enmascaramiento es solo para staging.

## 3. Inventario de migraciones

Migraciones propias del repositorio:

| Migración | Qué hace | Estado esperado |
|---|---|---|
| `2026_05_15_000001_create_users_table` | Crea `users` | Baseline si ya existe |
| `2026_05_15_000002_create_clientes_tables` | Crea clientes, bloqueos, asignaciones, CCD | Baseline si ya existen |
| `2026_05_15_000003_create_pagos_tables` | Crea lotes y pagos | Baseline si ya existen |
| `2026_05_15_000004_create_promesas_tables` | Crea promesas, cuotas, operaciones | Baseline si ya existen |
| `2026_05_15_000005_create_cna_solicitudes_table` | Crea CNA | Baseline si ya existe |
| `2026_05_15_000006_create_integracion_tables` | Crea tablas de integración | Baseline si ya existen |
| `2026_05_15_000007_normalize_user_roles_v3` | Mapea roles antiguos y cambia enum | **Compuerta de roles** |
| `2026_05_21_122013_create_notifications_table` | Crea `notifications` si falta | Incremental idempotente |

Además, Laravel/Sanctum aporta:

- `2019_12_14_000001_create_personal_access_tokens_table`.

Conteos observados:

- `u480`: 18 filas híbridas (V1 + V3).
- `kpinvest_v3_migrate_test`: 8 filas (PAT + 7 V3, sin notifications).
- `kp_crm_local`: 9 filas (PAT + 7 V3 + notifications).

No se copia el historial de `u480` a V3.

## 4. Baseline recomendado

### 4.1 Opción A: baseline mixto (recomendada)

1. Restaurar la copia V1 en una base nueva.
2. Comparar el esquema contra V3.
3. Si las 14 tablas y 153 columnas coinciden:
   - marcar `000001` a `000006` como aplicadas;
   - decidir `000007` según roles;
   - crear `notifications` si falta.
4. No copiar datos a otra base; V3 usa la misma base, ya existente.
5. Validar la app contra esa base.

Ventaja: conserva datos e historial operativo, con cambios mínimos.
Riesgo: una marca de baseline mal hecha oculta drift. Se exige comparación
previa y evidencia.

### 4.2 Opción B: esquema V3 limpio + importación de datos

1. Crear una base V3 desde cero con todas las migraciones.
2. Importar los datos de V1 tabla por tabla.
3. Validar.

Ventaja: historial limpio.
Riesgo: hay que ordenar FKs, IDs y secuencias; es más fácil perder datos o
romper relaciones. No se recomienda para producción con historial.

### 4.3 Opción C: solo incrementales, sin marcar baseline

No es viable si Laravel intenta crear tablas existentes. Se necesita una marca
de baseline o una migración de baseline revisada.

## 5. Compuerta de roles

La migración `000007` hace dos cosas:

```php
UPDATE users SET role = 'soporte'
WHERE role IN ('sistemas', 'usuario');

ALTER TABLE users
MODIFY role ENUM('administrador','supervisor','asesor','soporte')
NOT NULL DEFAULT 'soporte';
```

Reglas:

1. Si el dump fresco tiene roles `sistemas` o `usuario`, **detener**.
2. El responsable funcional define el mapeo exacto, por ejemplo:
   - `sistemas` -> `soporte` o `administrador` (decisión, no default);
   - `usuario` -> `asesor` o `soporte` (decisión, no default).
3. El mapeo se prueba en staging y se documenta fila por fila por rol, sin
   mostrar datos personales.
4. Solo después se aprueba ejecutar el cambio de enum.
5. Si no hay roles antiguos y el enum ya es de 4 valores, se puede marcar
   `000007` como aplicada, con aprobación explícita.
6. No se usa el `down()` como rollback de datos: solo revierte el enum, no
   restaura los roles originales.

La copia local no tiene roles antiguos, pero el dump fresco puede diferir.

## 6. Incremento de `notifications`

Es el único incremento de esquema esperado en el escenario actual:

- si `notifications` existe, no hace nada;
- si falta, la crea;
- no transforma datos;
- su `down()` elimina la tabla, pero no se usa en producción sin backup.

No se debe confundir con las notificaciones de la app: la tabla puede existir
con 0 filas y la app seguir funcionando.

## 7. Procedimiento de baseline en staging

1. Crear `kp_crm_v3_validation` desde la copia enmascarada V1.
2. Ejecutar la comparación de esquema de
   [03_matriz_v1_v3.md](03_matriz_v1_v3.md).
3. Generar un SQL de baseline revisado, por ejemplo:

```sql
INSERT INTO migrations (migration, batch) VALUES
('2026_05_15_000001_create_users_table', 100),
('2026_05_15_000002_create_clientes_tables', 100),
('2026_05_15_000003_create_pagos_tables', 100),
('2026_05_15_000004_create_promesas_tables', 100),
('2026_05_15_000005_create_cna_solicitudes_table', 100),
('2026_05_15_000006_create_integracion_tables', 100);
-- 000007 solo si la compuerta de roles lo aprueba
-- 2019_12_14_000001 PAT solo si la tabla existe y coincide
```

4. Ejecutar `php artisan migrate:status` y confirmar que no quedan pendientes
   inesperadas.
5. Aplicar `notifications` con ruta explícita si falta.
6. Ejecutar la app y las pruebas.
7. Guardar evidencia del SQL, la comparación y el resultado.

No se usa `php artisan migrate` a ciegas sobre una base con tablas existentes.

## 8. Validación en staging

| Base | Construcción | Propósito |
|---|---|---|
| `kp_crm_staging_masked` | V1 enmascarada | Fuente de datos y comparación |
| `kp_crm_v3_validation` | V1 enmascarada + baseline/incrementales | Simular producción |
| `kp_crm_v3_schema_check` (opcional) | Migraciones V3 desde cero | Comparar DDL limpio |
| `kp_crm_restore_rehearsal` | Restauración del backup | Ensayo de rollback |

La app de staging solo apunta a `kp_crm_v3_validation`.

## 9. Cutover productivo

### 9.1 Antes de la ventana

- [ ] G1-B: dump fresco autorizado y verificado.
- [ ] G5: roles y baseline decididos.
- [ ] G6: ensayo de cutover y rollback completado.
- [ ] Código V3 congelado y artefacto identificado.
- [ ] Build de assets listo.
- [ ] Variables de entorno y credenciales revisadas.
- [ ] Responsables y ventana confirmados.
- [ ] Comunicación a usuarios.

### 9.2 Durante la ventana

1. Declarar inicio de mantenimiento.
2. Detener escrituras de V1/IMPULSE y procesos programados.
3. Tomar el dump final y verificar checksum.
4. Restaurar en una base de ensayo y validar conteos.
5. Aplicar el baseline aprobado y los incrementales:
   - `notifications` si falta;
   - roles solo si se aprobó el mapeo;
   - cualquier corrección de drift aprobada.
6. Ejecutar migraciones pendientes con `--force` solo después de revisar
   `migrate:status`.
7. Desplegar código V3 y assets.
8. Apuntar la app a la base de producción.
9. Ejecutar smoke tests críticos.
10. Declarar fin de mantenimiento y habilitar escrituras V3.

### 9.3 Después de la ventana

- [ ] Login y roles.
- [ ] Búsqueda de cliente.
- [ ] Promesas.
- [ ] CNA.
- [ ] Reportes.
- [ ] Importaciones.
- [ ] Documentos.
- [ ] Notificaciones.
- [ ] Logs sin PII.
- [ ] Conteos y sumas conciliados.
- [ ] V1/IMPULSE en solo lectura para rollback.

## 10. Datos creados durante la ventana

- No se permite escribir en V1 después del corte.
- Si hay escrituras en cola, se documentan y se decide:
  - replay en V3 si son compatibles;
  - o se rechazan y se comunican.
- No se hace dual-write V1/V3 sin un diseño, pruebas y reconciliación
  explícitos.
- La opción recomendada es **ventana con freeze**, no convivencia de
  escrituras.

## 11. Migraciones posteriores al cutover

- Cada cambio nuevo es una migración nueva, no una edición de las anteriores.
- Cada migración debe tener `down` seguro o plan manual documentado.
- Se prueba en staging sobre una copia reciente.
- No se usa `migrate:fresh` en bases con datos.
- No se ejecutan seeders en producción sin revisión.
- Los cambios de datos masivos van en migraciones separadas, con backup y
  conciliación.

## 12. Integración IMPULSE

El alcance de esta base de datos no cubre por sí solo la integración con
IMPULSE. Antes del cutover se debe inventariar:

- endpoints y credenciales;
- jobs y schedulers;
- archivos CSV de entrada/salida;
- correo y notificaciones externas;
- almacenamiento de documentos;
- autenticación y sesiones;
- procesos manuales del negocio.

Reglas:

1. Un solo sistema escribe en la base.
2. Al cortar, se desactivan schedulers de V1 y se activan los de V3.
3. No se comparten credenciales de producción con staging.
4. El rollback incluye reactivar V1/IMPULSE y desactivar V3.
5. El responsable de integración debe firmar el checklist.

## 13. Pruebas de la estrategia

- [ ] Baseline marcado en staging y `migrate:status` coherente.
- [ ] `notifications` creada si falta; no duplicada si existe.
- [ ] Roles sin cambios no aprobados.
- [ ] App funciona con el esquema resultante.
- [ ] Rollback restaura el estado anterior.
- [ ] Conteos y sumas conciliados.
- [ ] Cutover cronometrado en staging.
- [ ] Integración IMPULSE inventariada y probada.

## 14. Criterios de aceptación

- [ ] 0 drift de esquema antes de marcar baseline.
- [ ] 0 transformación de datos de negocio en producción.
- [ ] Roles tratados solo con aprobación.
- [ ] `notifications` idempotente.
- [ ] Migraciones posteriores con rollback probado.
- [ ] Ventana de freeze y responsables definidos.
- [ ] IMPULSE sin escrituras después del corte.
- [ ] Evidencia de cutover y conciliación firmada.

## 15. Decisiones abiertas

1. ¿Se marca `000007` como aplicada o se ejecuta un mapeo específico?
2. ¿Se usa baseline mixto (A) o esquema limpio (B)?
3. ¿La ventana permite un freeze completo o se necesita una estrategia de
   replay?
4. ¿Quién es el responsable de la integración IMPULSE?
5. ¿Se conserva V1 en solo lectura después del cutover? (recomendado: sí)
