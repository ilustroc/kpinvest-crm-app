# KP INVEST — Fase 2: reconocimiento automático de CCD

Fecha: 2026-11-14
Base de trabajo: `739a7c7` (`main`)
Ámbito de validación: Docker local y base `kp_crm_testing` exclusivamente.

## Resultado

Se implementó una auditoría de solo lectura que obtiene texto de los PDF CCD,
extrae DNI, cuenta, operación y entidad cuando están presentes, y los compara
contra las cuentas candidatas del mismo DNI y cosecha normalizada. La auditoría
no crea ni modifica enlaces en `ccd_cliente_cuenta`, no mueve PDFs y no cambia
el controlador de descarga ni el lector ZIP V12 existente.

El nuevo comando es:

```bash
php artisan ccd:extract-audit --after-id=0 --limit=100
```

Por cada lote genera un CSV privado en el disco configurado por
`CCD_PRIVATE_DISK`, bajo `audits/`. No se expone por rutas web y el comando no
imprime DNI, operaciones ni contenido documental en la consola.

## Arquitectura comprobada

- `ccd_clientes.ccd_archivo_id` apunta a `ccd_archivos` cuando existe una copia
  privada catalogada.
- La copia privada se valida contra el `disk`, la ruta derivada del SHA-256 y
  la disponibilidad local antes de leerse.
- `CcdDocumentController` y `CcdZipArchiveV12` no fueron modificados.
- Esta copia del repositorio no contiene una clase `CcdZipArchiveV12`; por ello
  se añadió un contrato de solo lectura para conectar la adaptación existente
  de Hostinger, sin asumir su API ni reemplazarla.

## Criterio de clasificación

| Clasificación | Regla |
| --- | --- |
| `coincidencia_exacta` | La operación extraída identifica exactamente una sola cuenta candidata del mismo DNI y cosecha. |
| `coincidencia_cuenta` | La cuenta extraída coincide, pero el PDF no identifica una operación única. Nunca se confirma con este resultado. |
| `ambigua` | La operación documental coincide con más de una cuenta candidata. |
| `sin_coincidencia` | No hay cuenta compatible, DNI documental incompatible o identificador sin correspondencia. |
| `sin_texto` | El PDF se puede abrir pero no contiene texto extraíble. |
| `error` | El archivo no está disponible, el PDF es ilegible o ocurre un problema técnico. |

Los números se manejan como texto: no se convierten a enteros y conservan
ceros iniciales. Una cuenta compartida por varias operaciones se informa como
`coincidencia_cuenta`, aunque sea la única cuenta encontrada; no se promueve a
asociación confirmada.

## Identificadores extraídos en pruebas documentales

Las pruebas generan PDFs locales controlados y comprobaron que el extractor
recupera, sin modificar los documentos:

| Caso | DNI | Cuenta / operación | Entidad | Resultado |
| --- | --- | --- | --- | --- |
| Referencia CCD 4/5 | `05378290` | operación `0002744794` | `CAJAAQP` | `coincidencia_exacta` |
| Referencia CCD 23303 | `80201928` | cuenta `CTA-COMPARTIDA`, dos operaciones posibles | `CAJAAQP` | `coincidencia_cuenta` |
| Solo ZIP V12 | `05378290` | operación `0001382` | `CAJAAQP` | `coincidencia_exacta` |
| PDF sin capa de texto | — | — | — | `sin_texto` |

Estos valores provienen de PDFs de fixture creados por las pruebas; no se
presentan como evidencia de los PDFs históricos reales. En `kp_crm_testing`
no estaban las CCD reales `4`, `5` ni `23303`, por lo que no se afirmaron
identificadores documentales reales para ellas.

## Piloto solicitado

Se ejecutó en Docker con `APP_ENV=testing` y `DB_DATABASE=kp_crm_testing`:

```bash
php artisan ccd:extract-audit --after-id=0 --limit=100 \
  --output=ccd-extract-pilot-100.csv
```

Resultados:

| Métrica | Resultado |
| --- | ---: |
| Límite solicitado | 100 |
| CCD disponibles en `kp_crm_testing` | 3 |
| CCD procesados | 3 |
| Coincidencias exactas | 0 |
| Coincidencias por cuenta | 0 |
| Ambiguas | 0 |
| Sin coincidencia | 0 |
| Sin texto | 0 |
| Errores técnicos | 3 |
| Enlaces `ccd_cliente_cuenta` observados tras el piloto | 0 |

Los tres registros de la base de pruebas no tenían una fuente privada
disponible y tampoco había un proveedor ZIP V12 configurado. El CSV privado
quedó en `audits/ccd-extract-pilot-100.csv`; sus errores son de disponibilidad
de fuente, no de asociación. No se ejecutó ningún acceso contra producción.

## Archivos incorporados

- `app/Contracts/CcdZipV12PdfSourceProvider.php`: contrato de lectura para el
  adaptador local de ZIP V12.
- `app/Services/Ccd/CcdPdfSourceResolver.php`: resuelve copia privada y, como
  respaldo, el adaptador ZIP V12, sin escribir archivos.
- `app/Services/Ccd/CcdPdfIdentifierExtractor.php`: extracción de texto e
  identificadores mediante `smalot/pdfparser`, sin OCR.
- `app/Services/Ccd/CcdExtractionMatcher.php`: clasificación conservadora
  contra cuentas candidatas existentes.
- `app/Console/Commands/ExtractCcdAudit.php`: comando de auditoría por lotes
  y CSV privado, protegido contra sobrescritura.
- `tests/Feature/CcdExtractAuditTest.php`: cobertura de coincidencia exacta,
  cuenta compartida, PDF sin texto y ZIP V12 de solo lectura.
- `config/ccd.php`: llave opcional `CCD_ZIP_V12_SOURCE_PROVIDER`.
- `composer.json` y `composer.lock`: `smalot/pdfparser` `v2.13.1` (LGPL-3.0).

## Pruebas ejecutadas

- `php artisan test tests/Feature/CcdCuentaAssociationTest.php tests/Feature/CcdExtractAuditTest.php`:
  6 aprobadas, 28 aserciones.
- `php artisan test`: 140 aprobadas, 2 omitidas, 1128 aserciones.
- `npm run build`: correcto.
- `composer validate --no-check-publish`: correcto.
- `git diff --check`: correcto.

La prueba completa se ejecutó dentro del contenedor y contra
`kp_crm_testing`. Los mensajes controlados de recuperación de lock de caché
corresponden a su prueba de resiliencia existente.

## Integración pendiente del ZIP V12 real

En Hostinger debe configurarse una clase local que implemente
`CcdZipV12PdfSourceProvider` y delegue en su `CcdZipArchiveV12` ya adaptado.
Su único método recibe un `CcdCliente` y devuelve una ruta temporal legible o
`null`; no debe crear enlaces, mover el ZIP ni cambiar el controlador. Después
se declara esa clase en `CCD_ZIP_V12_SOURCE_PROVIDER`. Esta separación evita
adivinar la API de la adaptación que no existe en este checkout.

## Recomendación para la siguiente fase

1. Restaurar en `kp_crm_testing` una muestra privada que incluya las CCD 4, 5
   y 23303, con su fuente privada o el adaptador ZIP configurado.
2. Ejecutar pilotos sucesivos de 100 y revisar CSV privados de
   `coincidencia_exacta`; no aplicar asociaciones todavía.
3. Validar una muestra humana por entidad y formato documental antes de crear
   un comando separado de confirmación con usuario, evidencia y trazabilidad.
4. Mantener `sin_texto` fuera de automatización y reservar OCR selectivo y
   autorizado para una fase posterior.

## Reversión

No hay migraciones ni cambios de datos que revertir. Para revertir código,
retirar el commit de esta fase; los CSV privados de auditoría pueden
conservarse como evidencia o eliminarse manualmente desde almacenamiento
privado según la política de retención. Ningún PDF original ni registro de CCD
fue modificado.
