# KP INVEST — Corrección de validación documental de DNI en CCD

## Resumen

La validación del DNI estaba duplicada entre el extractor y el servicio de
asociación. Ambos componentes aceptaban conjuntos de etiquetas distintos, de
modo que una operación podía identificarse correctamente mientras el DNI
terminaba como `dni_documental_no_corrobable`.

Se creó `CcdDocumentDniRecognizer` como única fuente de reglas para extracción
y corroboración. No se añadió OCR, ejecutables externos ni dependencias.

## Formatos documentales aceptados

- `DNI: 12345678` y compatibilidad histórica con `Documento: 12345678`.
- `D.N.I.: 12345678`.
- `DNI N° 12345678`.
- `DNI Nro. 12345678`.
- `Nro. de documento: 12345678`.
- `Número de documento: 12345678`.
- `Documento de identidad: 12345678`.
- `Documento Nacional de Identidad: 12345678`.
- Separadores controlados entre dígitos: espacio, punto, tabulación o guion.
- Saltos de línea entre etiqueta, separador y valor.
- Encabezados históricos BBVA y CONFIANZA ya soportados.

Los ceros iniciales se conservan. Todos los formatos exigen límites numéricos,
por lo que un DNI parcial o incrustado en otro número no corrobora la identidad.
Un número aislado tampoco es evidencia suficiente.

## Seguridad preservada

Antes de declarar `elegible` siguen siendo obligatorias:

1. disponibilidad y lectura del PDF;
2. operación documental exacta;
3. cuenta candidata única;
4. coincidencia del DNI de la cuenta con el CCD;
5. evidencia documental del DNI;
6. ausencia de confirmaciones contradictorias.

Si el PDF contiene el DNI esperado y otro DNI explícito, el resultado es
`dni_documental_contradictorio`. Las confirmaciones manuales o automáticas
existentes no se modifican.

## Diagnóstico compatible

El motivo principal continúa siendo `dni_documental_no_corrobable`, para no
romper reportes existentes. La columna `evidencia_dni` ahora puede distinguir:

- `dni_ausente_texto_extraido`;
- `dni_presente_sin_etiqueta_verificable`;
- `dni_etiqueta_documental_no_reconocida`;
- `dni_documental_no_coincide`;
- `dni_documental_contradictorio`;
- `dni_no_evaluable_sin_texto`;
- `dni_no_evaluable_error_extraccion`.

Los logs técnicos sólo incluyen clase de excepción y CCD ID; no almacenan DNI
ni texto extraído.

## Simulación posterior sin escritura

Se añadió el comando de sólo lectura `ccd:recheck-dni`. Consume uno o varios
CSV privados generados por la asociación masiva y selecciona únicamente filas
con resultado `revision` y motivo `dni_documental_no_corrobable`.

Ejemplo para una muestra de 100:

```bash
php artisan ccd:recheck-dni \
  --input=ccd-associate-bulk-TRAMO-1.csv \
  --limit=100 \
  --after-id=0 \
  --output=ccd-recheck-dni-muestra-100.csv
```

Con varios tramos:

```bash
php artisan ccd:recheck-dni \
  --input=ccd-associate-bulk-TRAMO-1.csv \
  --input=ccd-associate-bulk-TRAMO-2.csv \
  --limit=1000 \
  --output=ccd-recheck-dni-muestra-1000.csv
```

El resumen y CSV comparan:

- `ahora_elegible`;
- `sigue_revision`;
- `contradiccion`;
- `requiere_validacion_manual`;
- `error`.

El comando invoca únicamente `analyze()`: no inserta, actualiza ni elimina
asociaciones. Los CSV de entrada y salida permanecen en el disco privado bajo
`audits/`.

## Pruebas

Se agregaron pruebas unitarias y de integración para todos los formatos,
ceros iniciales, separadores, saltos de línea, DNI incorrecto, parcial,
incrustado, aislado, contradictorio, operación correcta sin DNI corroborable,
múltiples operaciones y formatos históricos BBVA/CONFIANZA.

Pruebas focalizadas ejecutadas en Docker contra `kp_crm_testing`:

```text
74 passed, 334 assertions
```

También se verificó que `ccd:recheck-dni` convierta un formato nuevo en
`ahora_elegible`, mantenga un número aislado en revisión y no escriba en
`ccd_cliente_cuenta`.

Regresión completa de Laravel:

```text
211 passed, 2 skipped, 1453 assertions
```

Los dos casos omitidos dependen de condiciones opcionales del entorno de
pruebas; no corresponden al módulo CCD ni a fallos introducidos por este cambio.

## Limitaciones

- Los PDF sin capa de texto continúan sin poder resolverse automáticamente.
- No se realizó OCR.
- No se dispone localmente de los CSV privados de los 10,242 casos reales, por
  lo que no se proyecta cuántos serán recuperables.
- La primera medición real debe realizarse en staging con una muestra pequeña
  mediante `ccd:recheck-dni`, antes de considerar cualquier reprocesamiento.

## Archivos

- `app/Services/Ccd/CcdDocumentDniRecognizer.php`.
- `app/Services/Ccd/CcdPdfIdentifierExtractor.php`.
- `app/Services/Ccd/CcdExtractionMatcher.php`.
- `app/Services/Ccd/CcdVerifiedAssociationService.php`.
- `app/Console/Commands/RecheckCcdDniEvidence.php`.
- `tests/Unit/CcdDocumentDniRecognizerTest.php`.
- `tests/Feature/CcdExtractAuditTest.php`.
