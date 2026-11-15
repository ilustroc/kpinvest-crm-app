# KP INVEST — Fase 4: procesamiento masivo de CCD

## Resultado

Se implementó `ccd:associate-bulk` como orquestador acotado y reanudable del
servicio `CcdVerifiedAssociationService`. La simulación continúa siendo el modo
predeterminado. Esta fase no ejecutó `--apply`, no creó asociaciones reales y
no modificó staging ni producción.

## Uso

Ejecución nueva en simulación:

```bash
php artisan ccd:associate-bulk --batch-size=100 --max-documents=1000
```

Filtros opcionales:

```bash
php artisan ccd:associate-bulk \
  --batch-size=100 \
  --max-documents=1000 \
  --from-id=1 \
  --to-id=50000 \
  --cosecha=BBVA1
```

Cada ejecución muestra un UUID. Si termina por límite de documentos, tiempo o
memoria, se continúa desde el checkpoint persistido:

```bash
php artisan ccd:associate-bulk \
  --resume=UUID_DE_EJECUCION \
  --batch-size=100 \
  --max-documents=1000
```

Los filtros y el modo se recuperan del registro original; no pueden cambiarse
al reanudar. El tamaño de lote y los límites de la nueva invocación sí pueden
ajustarse.

La reauditoría documental de CCD ya confirmadas es explícita:

```bash
php artisan ccd:associate-bulk --audit-confirmed --max-documents=1000
```

Sin esa opción, las confirmaciones se precargan por lote y sus PDF no vuelven a
leerse. Esto evita trabajo innecesario. Con `--audit-confirmed`, el servicio
documental completo puede detectar contradicciones sin modificar la asociación
existente.

## Modo aplicación

El modo aplicación reutiliza exactamente el servicio verificado y conserva:

- base permitida exclusivamente `u480021566_prueba`;
- `CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true`;
- `--apply`;
- `--authorization=CONFIRMAR_ASOCIACION_AUTOMATICA`;
- relectura y verificación del PDF al procesar cada documento;
- transacción, bloqueo de CCD, idempotencia y protección de confirmaciones
  manuales.

La simulación masiva no es utilizada como fuente de una posterior escritura:
una ejecución de aplicación vuelve a comprobar cada documento. La variable de
entorno debe volver a `false` al finalizar la ventana autorizada.

## Progreso y recuperación

La nueva tabla `ccd_association_runs` conserva:

- UUID y modo;
- filtros;
- último ID confirmado por checkpoint;
- procesados, elegibles, confirmados, ya confirmados, no elegibles, revisiones,
  conflictos y errores;
- tiempo activo acumulado y velocidad media;
- consultas SQL del último tramo y memoria pico;
- último CSV, latido, estado y motivo de parada.

El recorrido usa `id > ultimo_id ORDER BY id LIMIT n`; no utiliza `OFFSET` ni
carga el catálogo completo. El checkpoint se actualiza al cerrar cada lote y
al finalizar el tramo. Si el proceso muere dentro de un lote, éste puede
repetirse desde el último checkpoint, pero las confirmaciones ya persistidas se
detectan e ignoran, por lo que no se duplican.

Un bloqueo de sistema de archivos no bloqueante impide dos
`ccd:associate-bulk` simultáneos. Los comandos manual y masivo siguen
protegidos entre sí por las transacciones y bloqueos de registro existentes.

## Límites para hosting compartido

Valores predeterminados:

- lote SQL: 100;
- máximo por invocación: 1,000;
- tiempo aproximado: 240 segundos;
- memoria observada: límite de 192 MB;
- errores técnicos antes de detener: 25.

Todos son configurables dentro de cotas validadas. Las revisiones y casos no
elegibles no detienen el trabajo; los errores técnicos sí lo hacen al alcanzar
el umbral. No se requiere Supervisor, Redis ni un worker permanente.

Para cron, primero debe existir una ejecución y conocerse su UUID. Un cron
futuro podría invocar el comando con `--resume=UUID`; no se creó ni activó
ningún cron en esta fase. Una ejecución ya completada retorna sin reprocesar.

## Informes

Cada invocación genera un CSV privado independiente en `audits/`, con UUID,
CCD, cuenta, operación, fuente, resultado, motivo, evidencias, modo y tiempo por
documento. Los nombres son únicos y los CSV existentes nunca se sobrescriben.

## Optimizaciones aplicadas

1. Precarga por lote de asociaciones confirmadas.
2. Precarga Eloquent de `ccd_archivos`, evitando una búsqueda por PDF privado.
3. El comparador devuelve la cuenta exacta ya determinada, eliminando una
   segunda consulta de candidatas.
4. Caché FIFO limitada a 250 DNI para metadatos de cuentas candidatas.
5. Reutilización del proveedor ZIP durante la invocación.
6. Reutilización de hasta 11 manejadores ZIP V12, cerrados al terminar el
   proceso; cada PDF sigue extrayéndose individualmente a un temporal privado.
7. Liberación del temporal en `finally` y recolección de ciclos por lote.
8. Índice `ccd_clientes(cosecha, id)` para filtros de cosecha con keyset.

No se cambió `CcdDocumentController.php` ni el lector HTTP
`CcdZipArchiveV12.php`.

## Métricas de validación

Los tiempos siguientes son de pruebas automatizadas locales dentro de Docker;
no son una proyección del tiempo total en Hostinger:

- 10 documentos sintéticos: aproximadamente 0.4 segundos por caso completo.
- 100 documentos sintéticos: aproximadamente 0.2 segundos.
- 1,000 documentos sintéticos, lotes de 100: aproximadamente 0.3 segundos y
  menos de 100 consultas SQL; el extractor fue simulado para aislar el
  orquestador.
- integración con un PDF privado y un PDF ZIP V12 válidos generados en prueba:
  aproximadamente 0.2 segundos, con limpieza del temporal ZIP.

El costo dominante real seguirá siendo `smalot/pdfparser` y el tamaño de los
PDF. El comando registra milisegundos por documento, memoria pico y velocidad
media para obtener cifras reales en la primera simulación de staging.

## Pruebas incorporadas

- 10, 100 y 1,000 CCD en simulación;
- keyset y límites de consultas;
- interrupción por límite y reanudación en tres tramos;
- confirmada omitida y reauditoría explícita;
- umbral de errores;
- rechazo de un segundo proceso;
- rechazo de `--apply` fuera de staging;
- PDF privado y ZIP V12;
- preservación de asociaciones y limpieza de temporales.

Regresión completa:

```text
186 passed, 2 skipped, 1353 assertions
```

Las trazas de error mostradas durante la suite corresponden a escenarios
deliberados de extracción ilegible y agotamiento del mutex de caché. PHPUnit
finalizó con código 0.

## Reversión

Antes de usar el comando en un entorno, las dos migraciones de esta fase pueden
revertirse en orden inverso. Esto elimina únicamente el índice nuevo y los
checkpoints; no elimina asociaciones de `ccd_cliente_cuenta`. Si ya existen
ejecuciones, primero deben conservarse sus CSV y el contenido de
`ccd_association_runs`, porque al revertir se pierde la capacidad de reanudar
esos UUID.
