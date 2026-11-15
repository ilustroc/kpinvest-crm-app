# KP INVEST — Fase 3.2: verificación documental del DNI

## Causa

`CcdVerifiedAssociationService::documentContainsDni()` exigía que ciertos
encabezados estuvieran al inicio de una línea. El texto de varios PDF se
extrae sin conservar esos saltos, por lo que formas válidas de destinatario
quedaban como `dni_documental_no_corrobable` aunque la operación fuera exacta.

## Formatos aceptados

La comprobación se sustituyó por una función que devuelve evidencia explícita:

- `dni_documental_etiquetado`: `DNI`, `Documento de identidad` o variante
  equivalente, con límites numéricos.
- `dni_destinatario_bbva_estimado_sr`: encabezado BBVA
  `Estimado(a) Sr.(a): <destinatario> - <DNI>`.
- `dni_destinatario_confianza_referencia_senor`: encabezado CONFIANZA
  `Referencia: <DNI> Señor(a): <destinatario>`.

Los dos últimos se validan como una única estructura: la referencia numérica
no se acepta por sí sola, los dígitos no pueden ser una subcadena de otro
número y se preservan ceros iniciales. No se admiten rótulos amplios como
`cliente` o `titular` sin el patrón completo.

## Confirmaciones existentes y trazabilidad

- Una confirmación manual de la misma operación se conserva y se informa como
  `ya_confirmada`, aunque el PDF no pueda volver a corroborar el formato del
  DNI.
- Si el PDF contiene un DNI documental explícito distinto y existe una
  confirmación previa, la simulación informa
  `dni_documental_contradice_confirmacion_existente` como `conflicto`; no
  modifica el vínculo.
- El CSV privado de `ccd:associate-verified` incorpora la columna
  `evidencia_dni`.
- El modo `--apply`, su bloqueo de producción y
  `CCD_AUTO_ASSOCIATION_APPLY_ENABLED=false` permanecen sin cambios.

## Evidencia local de formatos

Se inspeccionaron muestras locales con el extractor PDF existente, sin copiar,
alterar ni exponer el contenido de los documentos. El formato BBVA de
destinatario coincidió en la muestra revisada y el patrón estructurado de
CONFIANZA coincidió en 10 de 10 muestras de `CONFIANZA1`.

## Pruebas

Prueba específica en Docker contra `kp_crm_testing`:

```text
docker compose exec -T app php artisan test tests/Feature/CcdExtractAuditTest.php
40 passed (163 assertions)
```

Los casos cubren el destinatario BBVA, la estructura CONFIANZA, número aislado
accidental, DNI contradictorio, operaciones múltiples, PDF sin identidad y la
preservación de confirmaciones manuales. La línea de log esperada de una prueba
de error de extracción controlado no representa un fallo de la suite.

Regresión completa, también en Docker y con una sola ejecución secuencial:

```text
177 passed, 2 skipped, 1282 assertions
```

## Riesgo residual

Documentos cuyo texto no contenga una etiqueta de identidad ni uno de los dos
formatos estructurados permanecen en `revision`. No se realiza OCR ni se
habilita ninguna confirmación automática por DNI, cosecha, cuenta o referencia
numérica aislada.
