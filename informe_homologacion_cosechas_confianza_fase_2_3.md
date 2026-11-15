# KP INVEST — Fase 2.3: homologación de cosechas CONFIANZA

Fecha: 2026-11-14
Base: `d8d177b`
Ámbito: Docker local y `kp_crm_testing`; no se modificó Hostinger ni producción.

## Regla aplicada

El caso CCD `23550` usa DNI `00003862`, cosecha `CONFIANZA1` y la referencia
`6878399DEUDAACTUAL`. Su cuenta CRM histórica usa cosecha `CONFIANZA`, entidad
`FINANCIERA CONFIANZA` y operación `6878399`.

1. El emparejamiento específico por DNI y cosecha se conserva y tiene prioridad.
2. Sólo cuando no hay candidatas específicas, una CCD `CONFIANZA1` a `CONFIANZA12` puede buscar el mismo DNI, entidad exacta `FINANCIERA CONFIANZA` y cosecha CRM exacta `CONFIANZA`.
3. DNI, entidad y cosecha no bastan: una única operación debe aparecer en el PDF.
4. Una operación genera `coincidencia_exacta` y `operacion_documental_confianza_cosecha_generica`.
5. Varias operaciones generan `ambigua` y `operaciones_documentales_confianza_cosecha_generica`.
6. Sin operación completa queda `sin_coincidencia` con `confianza_cosecha_generica_sin_operacion_documental`.

La homologación no se aplica a BBVA, Caja Arequipa ni otra entidad.

## Referencias concatenadas

El extractor reconoce una referencia numérica de seis o más dígitos unida a
una palabra en mayúsculas, como `6878399DEUDAACTUAL`, y extrae sólo `6878399`.
No une números separados ni inventa dígitos. El comparador conserva ceros
iniciales y límites numéricos estrictos.

## Archivos modificados

- `app/Services/Ccd/CcdCuentaAssociationService.php`
- `app/Services/Ccd/CcdPdfIdentifierExtractor.php`
- `app/Services/Ccd/CcdExtractionMatcher.php`
- `tests/Feature/CcdExtractAuditTest.php`

No se modificaron controlador CCD, ZIP V12, rutas web, PDFs, ZIP, asociaciones, CNA, promesas, pagos, importes ni CANCELADOS.

## Pruebas

| Escenario | Resultado |
| --- | --- |
| CCD 23550 simulado | coincidencia exacta para `6878399` |
| Referencia seguida de `DEUDAACTUAL` | identificador separado correctamente |
| Dos operaciones compatibles | ambigua |
| Operación parcial | sin coincidencia |
| Otra entidad con cosecha `CONFIANZA` | sin candidata |
| Cosecha específica `CONFIANZA1` | mantiene prioridad |
| Once cosechas ZIP V12 configuradas | cobertura de homologación documental |
| BBVA 4 y 5 | regresión conservada |
| Caja Arequipa / CCD 23303 | coincidencia etiquetada conservada |
| PDF sin texto y asociación confirmada | sin regresión |

Resultados: 26 pruebas CCD/asociaciones aprobadas (121 aserciones); suite completa con 160 aprobadas, 2 omitidas y 1221 aserciones. Lint PHP y `git diff --check` correctos.

## Riesgo y siguiente paso

Una referencia aislada podría coincidir casualmente con una operación del mismo DNI. El riesgo se limita por cosecha específica primero, homologación exclusiva de CONFIANZA, entidad exacta, operación única y límites numéricos. Esta fase sólo audita: no escribe `ccd_cliente_cuenta` ni modifica confirmaciones manuales.

Durante el piloto deben revisarse los CSV privados con evidencia `operacion_documental_confianza_cosecha_generica` antes de una fase separada de confirmación trazable. No se hizo push ni despliegue.
