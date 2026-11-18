# KP INVEST — Validación documental Caja Arequipa AQP4/AQP5

## Resultado

Se incorporó el reconocimiento controlado del encabezado histórico:

```text
CÓDIGO DEL CLIENTE: 12345678
SEÑOR (A): NOMBRE DEL DESTINATARIO
```

La regla queda limitada a `AQP4` y `AQP5`. Se reconoce con y sin tilde en
`CÓDIGO`, conserva ceros iniciales y admite espacios, separadores numéricos y
saltos de línea controlados.

La evidencia se registra como `dni_codigo_cliente_aqp_verificado`, pero aún no
autoriza una confirmación automática. El flujo normal de
`ccd:associate-verified` y el método `apply()` devuelven:

```text
resultado: revision
motivo: dni_codigo_cliente_aqp_pendiente_validacion_ampliada
```

Sólo `ccd:recheck-dni`, que no escribe asociaciones, utiliza esta evidencia
para contabilizar el caso como `ahora_elegible` durante la validación ampliada.

## Reglas de seguridad

Antes de considerar la evidencia AQP siguen siendo obligatorias:

1. lectura correcta del PDF;
2. operación documental exacta;
3. una única cuenta candidata;
4. coincidencia entre DNI de cuenta y CCD;
5. código de cliente dentro del encabezado de destinatario;
6. ausencia de DNI explícitos contradictorios;
7. ausencia de confirmaciones incompatibles.

El patrón exige que `SEÑOR (A):` aparezca después del código, dentro de un
contexto acotado y sin números intermedios. Un texto arbitrario con
`CÓDIGO DEL CLIENTE` no se acepta.

Clasificaciones adicionales:

- `dni_codigo_cliente_aqp_verificado`;
- `dni_codigo_cliente_aqp_no_coincide`;
- `dni_codigo_cliente_aqp_contradictorio`;
- `dni_codigo_cliente_aqp_contexto_insuficiente`.

Un código incorrecto, parcial, incrustado, múltiple o sin contexto permanece
en revisión. Un DNI explícito contradictorio prevalece sobre un código AQP
correcto. Las evidencias de DNI explícitas que ya estaban autorizadas conservan
su comportamiento anterior.

## Simulación privada

`ccd:recheck-dni` acepta ahora filtros repetibles por cosecha y añade la cosecha
al CSV de salida. Lee los CSV históricos de `audits/`, vuelve a analizar los
PDF y genera otro CSV privado. No inserta, actualiza ni elimina asociaciones.

Muestra AQP4:

```bash
php artisan ccd:recheck-dni \
  --input=REPORTE_MASIVO_1.csv \
  --input=REPORTE_MASIVO_2.csv \
  --cosecha=AQP4 \
  --limit=200 \
  --after-id=0 \
  --output=ccd-recheck-aqp4-muestra-200.csv
```

Muestra AQP5:

```bash
php artisan ccd:recheck-dni \
  --input=REPORTE_MASIVO_1.csv \
  --input=REPORTE_MASIVO_2.csv \
  --cosecha=AQP5 \
  --limit=200 \
  --after-id=0 \
  --output=ccd-recheck-aqp5-muestra-200.csv
```

Muestra conjunta, hasta el límite seguro de 1,000 documentos:

```bash
php artisan ccd:recheck-dni \
  --input=REPORTE_MASIVO_1.csv \
  --input=REPORTE_MASIVO_2.csv \
  --cosecha=AQP4 \
  --cosecha=AQP5 \
  --limit=1000 \
  --after-id=0 \
  --output=ccd-recheck-aqp45-muestra-1000.csv
```

Cada `--input` es el nombre, sin directorio, de un CSV real generado por
`ccd:associate-bulk` dentro del disco privado `audits/`. Para la siguiente
página se usa como `--after-id` el mayor `ccd_id` del CSV anterior y un nombre
de salida nuevo.

El resumen separa:

- ahora elegibles;
- siguen en revisión;
- contradicciones;
- validación manual;
- errores.

## Pruebas

Pruebas focalizadas de CCD:

```text
90 passed, 409 assertions
```

Regresión completa de Laravel:

```text
227 passed, 2 skipped, 1528 assertions
```

Se probaron AQP4, AQP5, ceros iniciales, tilde y ausencia de tilde, espacios,
saltos de línea, código incorrecto, parcial, incrustado, códigos múltiples,
DNI explícito contradictorio, falta de contexto, otra cosecha, operaciones
ambiguas, bloqueo de `apply()` y filtrado del comando sin escrituras.

Los dos casos omitidos corresponden a condiciones opcionales del entorno de
pruebas y no a fallos del módulo CCD.

## Archivos modificados

- `app/Services/Ccd/CcdDocumentDniRecognizer.php`.
- `app/Services/Ccd/CcdVerifiedAssociationService.php`.
- `app/Console/Commands/RecheckCcdDniEvidence.php`.
- `tests/Unit/CcdDocumentDniRecognizerTest.php`.
- `tests/Feature/CcdExtractAuditTest.php`.

`CcdPdfIdentifierExtractor.php` y `CcdExtractionMatcher.php` fueron revisados,
pero no necesitaron cambios: la operación y los DNI explícitos continúan
validándose con las reglas existentes.

## Riesgos y límites

- La evidencia se basa en texto extraíble; no se agregó OCR.
- El límite contextual de 240 caracteres se eligió para tolerar el aplanado de
  texto de `smalot/pdfparser` sin buscar códigos en todo el documento.
- No se dispone localmente de los PDF y CSV privados de los 10,224 casos AQP4
  y AQP5. Por ello no se proyecta todavía cuántos serán recuperables.
- La regla debe permanecer no autorizada para `apply()` hasta revisar una
  muestra real ampliada y sus contradicciones.

No se ejecutó `--apply`, no se modificaron asociaciones reales y no se hizo
despliegue ni merge a `main`.
