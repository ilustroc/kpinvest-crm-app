# 08. Plan de reversión

> **Estado: propuesta. No ejecutada.**
>
> La reversión se ensaya en staging. No se prueba en producción ni se ejecuta
> sin una ventana aprobada.

## 1. Objetivo

Volver a un estado operativo conocido si el cutover V3 falla, sin perder
información de forma silenciosa y sin dejar la base en un estado intermedio.

## 2. Principios

1. **Un solo escritor.** Antes de revertir, se detienen las escrituras V3.
2. **Backup verificado.** No se restaura un backup no verificado.
3. **Preferir base nueva.** Restaurar en una base nueva y cambiar la app es más
   seguro que sobrescribir producción.
4. **No confiar en `down()`.** Las migraciones `down()` no restauran datos.
5. **Preservar evidencia.** La base V3 y los logs se conservan para análisis.
6. **Decisión humana.** La reversión la autoriza el responsable de incidente.
7. **Cero PII en la comunicación.** No se comparten filas ni capturas.

## 3. Disparadores

Se evalúa reversión si ocurre cualquiera de estos casos:

- login o permisos fallan de forma general;
- búsqueda de cliente o perfil no funcionan;
- promesas o CNA no se pueden crear/aprobar;
- documentos no se generan/descargan y el negocio no puede operar;
- reportes o importaciones críticas fallan;
- conciliación de conteos/sumas no cuadra y no se explica;
- la app escribe en la base equivocada;
- se detecta PII en un lugar no autorizado;
- el rendimiento impide operar;
- IMPULSE y V3 escriben a la vez;
- un incremento de esquema deja datos inaccesibles.

## 4. Precondiciones

- [ ] Backup completo pre-cutover, verificado.
- [ ] Backup del estado V3 inmediatamente antes de revertir.
- [ ] Artefacto de código V1/IMPULSE identificado.
- [ ] Build de assets anterior disponible.
- [ ] Variables de entorno anteriores conocidas.
- [ ] Responsable de incidente designado.
- [ ] Ventana y comunicación aprobadas.
- [ ] Ensayo de restauración completado (G6).

## 5. Niveles de reversión

### 5.1 Nivel 1: solo código

Aplica si el esquema es retrocompatible y el fallo es de aplicación.

1. Detener escrituras o poner mantenimiento.
2. Desplegar el artefacto anterior.
3. Restaurar assets anteriores.
4. Limpiar `config:cache`, `route:cache`, `view:cache`.
5. Reiniciar PHP/opcache.
6. Ejecutar smoke tests.
7. Reabrir.

Riesgo: si el enum de roles cambió o V1 no entiende un estado nuevo, el código
viejo puede fallar. Probar en staging antes de confiar en este nivel.

### 5.2 Nivel 2: esquema incremental

Aplica si el único cambio fue `notifications` o un cambio reversible.

- `notifications` puede quedarse: el código V1 probablemente la ignora. No se
  elimina si ya tiene datos.
- Si se debe eliminar, primero se exporta su contenido y se aprueba.
- No usar `migrate:rollback` a ciegas; puede revertir más de lo esperado.

### 5.3 Nivel 3: restauración completa de base

Aplica si el esquema o los datos quedaron en un estado no confiable.

Opciones:

**A. Base nueva y cambio de configuración (recomendada)**

1. Crear `kp_crm_prod_rollback_<timestamp>`.
2. Restaurar el backup pre-cutover.
3. Verificar conteos, FKs y checks.
4. Cambiar `DB_DATABASE` de la app a la base nueva.
5. Reiniciar la app y limpiar cachés.
6. Validar.

Ventaja: la base V3 queda intacta para análisis.
Riesgo: requiere que la app pueda apuntar a otra base sin cambiar código.

**B. Restauración en la base productiva**

1. Detener todas las escrituras.
2. Tomar un dump del estado V3 actual.
3. Restaurar el backup verificado sobre la base productiva.
4. Validar.
5. Reabrir.

Ventaja: no cambia configuración.
Riesgo: destructivo; si el backup falla, se pierde el estado actual. Solo con
aprobación y backup del estado V3.

**C. Recuperación a un punto en el tiempo**

Solo si el motor tiene binlogs habilitados y el equipo puede aplicarlos. No se
asume que estén disponibles. Si se usa, validar el punto exacto y la
reconciliación.

### 5.4 Nivel 4: integración IMPULSE

- Desactivar schedulers/jobs de V3.
- Reactivar los de V1/IMPULSE.
- Restaurar credenciales/endpoints anteriores.
- Revisar colas y archivos en vuelo.
- Confirmar que no hay doble escritura.
- Reconciliar los datos creados durante el corte.

### 5.5 Nivel 5: storage y documentos

- Restaurar el storage/templates anteriores.
- Invalidar enlaces/cachés de documentos.
- Confirmar que no se mezclan documentos V1 y V3.
- No restaurar documentos reales en el repositorio.

## 6. Datos creados en V3 después del cutover

Antes de revertir:

1. Detener escrituras.
2. Exportar un delta de V3:
   - IDs y fechas de creación;
   - tablas afectadas;
   - conteos por estado;
   - sin PII en el reporte.
3. Decidir con negocio:
   - replay en V1 si el esquema es compatible;
   - conservar el delta en una base de consulta;
   - o comunicar la pérdida acordada.
4. No borrar la base V3 sin antes preservar evidencia.

La tabla `notifications` no existe en el flujo V1; si se revierte el código,
se puede conservar sin afectar. No se debe eliminar sin revisar.

## 7. Árbol de decisión

```text
¿Falla crítica?
   | no  -> monitorear, corregir forward
   | sí
   v
¿Se puede corregir con código sin tocar datos?
   | sí  -> rollback nivel 1
   | no
   v
¿El esquema es retrocompatible?
   | sí  -> rollback nivel 1/2
   | no
   v
¿Hay backup verificado y ventana?
   | no  -> mantener mantenimiento, escalar
   | sí
   v
¿Se puede restaurar en base nueva?
   | sí  -> nivel 3A
   | no  -> nivel 3B con aprobación
   v
Validar, reconciliar y reabrir
```

## 8. Procedimiento de reversión completa

1. **Declarar incidente.** Responsable y hora.
2. **Congelar.** Activar mantenimiento; detener escrituras, jobs y colas.
3. **Preservar.** Dump del estado V3 actual y logs; no borrar nada.
4. **Verificar backup.** Checksum y restauración de prueba si el tiempo lo
   permite.
5. **Restaurar.** Base nueva `kp_crm_prod_rollback_<timestamp>` o base
   productiva, según decisión.
6. **Validar restore.** 14 tablas, conteos, FKs, checks, roles.
7. **Cambiar app.** Código anterior + `DB_DATABASE` anterior + assets.
8. **Limpiar cachés.** `config:clear`, `cache:clear`, `route:clear`,
   `view:clear`; reiniciar PHP.
9. **Reactivar V1/IMPULSE.** Schedulers, jobs, integraciones.
10. **Smoke tests.** Login, cliente, promesa, CNA, reportes, documentos.
11. **Reconciliar.** Conteos y sumas; delta V3 documentado.
12. **Comunicar.** Estado, impacto, siguiente paso.
13. **Postmortem.** Causa, evidencia, acciones.

## 9. Reversión de roles

- El `down()` de `000007` solo cambia el enum, no devuelve `sistemas` ni
  `usuario`.
- Si se cambió el enum a 4 valores y el código V1 espera 6, el rollback de
  código puede fallar.
- Antes de revertir, verificar el enum y los valores presentes.
- Si se necesita volver a 6 valores, se requiere una migración revisada y un
  mapeo aprobado; no editar filas a mano.
- El backup pre-cutover es la vía más segura para recuperar roles.

## 10. Reversión de `notifications`

- Si se creó y V1 la ignora, se puede dejar.
- Si se debe quitar:
  1. exportar contenido;
  2. confirmar que ningún proceso V3 la usa;
  3. eliminar con una migración revisada, no con `DROP` manual;
  4. validar que V1 sigue operando.
- No eliminar si hay evidencia que se necesita para el postmortem.

## 11. Verificación posterior a la reversión

- [ ] Login y roles.
- [ ] Búsqueda y perfil de cliente.
- [ ] Promesas y CNA.
- [ ] Reportes.
- [ ] Importaciones.
- [ ] Documentos.
- [ ] Notificaciones.
- [ ] Jobs/schedulers V1 activos.
- [ ] V3 sin escrituras.
- [ ] Conteos y sumas conciliados.
- [ ] Logs sin PII.
- [ ] Comunicación enviada.

## 12. Ensayo de reversión

En staging:

1. Restaurar el backup en `kp_crm_restore_rehearsal`.
2. Cambiar la app de staging a esa base.
3. Ejecutar el checklist funcional.
4. Medir tiempo de restauración y de smoke tests.
5. Volver a `kp_crm_v3_validation`.
6. Documentar desviaciones.

No se ensaya sobre producción. No se usa la base `u480` como destino.

## 13. RTO/RPO propuestos

| Métrica | Propuesta | Estado |
|---|---|---|
| RTO | ≤ 60 minutos | A confirmar con negocio |
| RPO | ≤ 15 minutos | A confirmar; depende del dump final y binlogs |

El RTO debe medirse en el ensayo. Si la restauración tarda más, se ajusta la
ventana o se diseña una estrategia de base nueva pre-restaurada.

## 14. Responsabilidades

| Rol | Responsabilidad |
|---|---|
| Responsable de incidente | Decide revertir y comunica |
| DBA / datos | Restaura, valida, preserva evidencia |
| Líder de aplicación | Código, configuración, cachés |
| Líder funcional | Smoke tests y aceptación |
| Integración | V1/IMPULSE, jobs, colas |
| Comunicaciones | Avisos a usuarios |

## 15. Prohibiciones

- No revertir sin backup verificado.
- No usar `migrate:rollback` a ciegas.
- No usar `DROP DATABASE` sobre producción.
- No editar roles o datos a mano.
- No restaurar sobre una base con escrituras activas.
- No borrar la base V3 antes del postmortem.
- No compartir capturas o filas con PII.

## 16. Checklist de reversión

- [ ] Incidente declarado.
- [ ] Escrituras detenidas.
- [ ] Estado V3 preservado.
- [ ] Backup verificado.
- [ ] Base de rollback creada/restaurada.
- [ ] Conteos y FKs validados.
- [ ] Código y assets anteriores desplegados.
- [ ] Cachés limpias.
- [ ] V1/IMPULSE reactivado.
- [ ] Smoke tests aprobados.
- [ ] Delta V3 documentado.
- [ ] Comunicación enviada.
- [ ] Postmortem agendado.
