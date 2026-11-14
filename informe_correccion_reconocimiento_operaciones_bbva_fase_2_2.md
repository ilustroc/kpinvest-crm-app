# KP INVEST — Fase 2.2: corrección de reconocimiento de operaciones BBVA

Fecha: 2026-11-14
Base: `a6e18f6`
Ámbito de validación: Docker local con `kp_crm_testing` exclusivamente.

## Causa

Los PDF BBVA reales de las CCD 4 y 5 contienen las operaciones completas, pero
no junto a una etiqueta reconocida por `CcdPdfIdentifierExtractor`. El texto se
extraía correctamente, pero el comparador sólo evaluaba operaciones etiquetadas
y terminaba como `sin_coincidencia`.

## Corrección

`CcdExtractionMatcher` conserva primero el reconocimiento etiquetado y, si no
encuentra una operación, compara el texto contra las operaciones candidatas del
mismo DNI y cosecha normalizada.

1. Sólo considera candidatas numéricas de seis o más dígitos.
2. Preserva todos los ceros iniciales.
3. Busca cada operación con límites `(?<![0-9])` y `(?![0-9])`.
4. Acepta únicamente espacios, tabulaciones, puntos y guiones entre dígitos.
5. No compacta ni elimina caracteres de todo el documento; por ello no une
   números de líneas o campos distintos.

Una candidata encontrada produce `coincidencia_exacta`; dos o más producen
`ambigua`. El DNI explícito contradictorio sigue teniendo precedencia y da
`sin_coincidencia`.

## Evidencia CSV

| Situación | Clasificación | Evidencia |
| --- | --- | --- |
| Operación etiquetada | `coincidencia_exacta` | `operacion_documental_exacta` |
| Una candidata sin etiqueta | `coincidencia_exacta` | `operacion_candidata_en_texto_pdf` |
| Varias candidatas en texto | `ambigua` | `operaciones_candidatas_en_texto_pdf` |
| DNI contradictorio | `sin_coincidencia` | `dni_documental_no_coincide` |
| Sin operación candidata | `sin_coincidencia` | `texto_sin_identificadores_de_cuenta_u_operacion` |

## Casos BBVA cubiertos

| Caso | Operación en texto sin etiqueta | Resultado |
| --- | --- | --- |
| CCD 4, DNI `05378290` | `00110301995001707769` | `coincidencia_exacta` |
| CCD 5, DNI `05378290` | `00110301925001679374`, incluida con guion | `coincidencia_exacta` |
| Ambas operaciones | ambas | `ambigua` |
| Subcadena de número mayor | `9{operación}8` | no coincide |
| Operación ausente | ninguna candidata | `sin_coincidencia` |
| CCD 23303 / Caja Arequipa | `2744794` etiquetada | conserva evidencia existente |

Las pruebas no usan ni alteran los PDF reales. También cubren ceros iniciales,
PDF sin texto, cuenta con varias operaciones, ZIP V12, PDF privado y
preservación de asociaciones confirmadas.

## Archivos modificados

- `app/Services/Ccd/CcdExtractionMatcher.php`
- `tests/Feature/CcdExtractAuditTest.php`

No se modificaron el controlador CCD, ZIP V12, rutas web, documentos,
asociaciones confirmadas, CNA, promesas, pagos ni CANCELADOS.

## Pruebas ejecutadas

- CCD y asociaciones: 20 aprobadas, 83 aserciones.
- Suite completa: 154 aprobadas, 2 omitidas, 1183 aserciones.
- Sintaxis PHP y `git diff --check`: correctos.

## Riesgos y siguiente paso

Un PDF podría contener casualmente una operación candidata como número
independiente. El riesgo se reduce al exigir DNI y cosecha candidatos,
operación completa, ceros preservados y límites numéricos estrictos. Esta fase
continúa siendo de auditoría: no escribe `ccd_cliente_cuenta` ni confirma
asociaciones.

Se recomienda ejecutar lotes de 100 en `u480021566_prueba`, revisar los CSV
privados con evidencia `operacion_candidata_en_texto_pdf` y mantener la
confirmación como una fase trazable separada. No se hizo push ni despliegue.
