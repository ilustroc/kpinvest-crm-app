# KP INVEST — Fase 2.1: integración del extractor CCD con ZIP V12

Fecha: 2026-11-14
Base: `ed2dc99`
Ámbito: checkout local y Docker local con `kp_crm_testing`. No se ejecutó
ninguna operación contra Hostinger ni producción.

## Resultado

`ccd:extract-audit` ahora puede obtener el PDF desde dos fuentes de solo
lectura:

1. La copia privada individual catalogada en `ccd_archivos` (sin cambios al
   comportamiento previo).
2. Un ZIP V12, únicamente cuando la copia privada no está disponible o el CCD
   no tiene `ccd_archivo_id`.

La fuente ZIP no depende de que `CcdZipArchiveV12.php` exista en este
checkout. No se modificó ese lector de Hostinger ni `CcdDocumentController`.

## Método de lectura ZIP V12

`CcdZipV12FileSourceProvider` implementa el contrato existente
`CcdZipV12PdfSourceProvider`.

- La raíz se configura mediante `CCD_ZIP_V12_ROOT`; no hay rutas de Hostinger
  embebidas en código.
- La cosecha se normaliza a mayúsculas y debe pertenecer a la lista cerrada
  `CCD_ZIP_V12_COSECHAS`: `CONFIANZA1`, `CONFIANZA2`, `CONFIANZA4` a
  `CONFIANZA12` (sin `CONFIANZA3`, que no fue incluida entre los ZIP V12
  verificados).
- El proveedor compone exclusivamente `{COSECHA}.zip` y consulta con
  `ZipArchive::statName()` el valor exacto de `ccd_clientes.pdf`.
- El nombre debe ser plano, terminar en `.pdf` y no puede contener rutas,
  barras ni byte nulo.
- Abre el ZIP en `ZipArchive::RDONLY`, usa `getStream()` para una sola entrada
  y nunca llama a `extractTo()`.
- Verifica tamaño descomprimido contra `CCD_MAX_FILE_BYTES`, limita también la
  copia por flujo y exige la firma `%PDF-` antes de entregar el temporal.

## Gestión de archivos temporales

La única escritura de la fuente ZIP es un archivo temporal privado limitado al
PDF solicitado, bajo `CCD_ZIP_V12_TEMP_ROOT` (por defecto
`storage/app/private/ccd-zip-tmp`). No se modifica el ZIP ni un PDF original.

El resolvedor marca como liberable únicamente una fuente que implementa
`CcdTemporaryPdfSourceProvider`. El comando ejecuta su liberación dentro de
`finally`, tanto si el análisis termina correctamente como si el extractor
lanza una excepción recuperable. Antes de borrar, el proveedor confirma que:

- el archivo existe;
- tiene prefijo `ccd-zip-`;
- su ruta canónica está dentro del directorio temporal configurado.

Por tanto, una ruta privada original nunca es candidata a borrado.

## Archivos creados o modificados

- `app/Services/Ccd/CcdZipV12FileSourceProvider.php`
- `app/Contracts/CcdTemporaryPdfSourceProvider.php`
- `app/Contracts/CcdZipV12PdfSourceDiagnostics.php`
- `app/Services/Ccd/CcdPdfSourceResolver.php`
- `app/Console/Commands/ExtractCcdAudit.php`
- `config/ccd.php`
- `tests/Feature/CcdExtractAuditTest.php`

No se modificaron `CcdDocumentController.php`, `CcdZipArchiveV12.php`, rutas
web, migraciones, asociaciones CCD existentes ni módulos financieros.

## Configuración requerida en Hostinger para una futura prueba

```dotenv
CCD_ZIP_V12_ROOT=/home/u480021566/private/ccd-zips
CCD_ZIP_V12_TEMP_ROOT=/home/u480021566/apps/kpinvest-crm-pruebas/storage/app/private/ccd-zip-tmp
CCD_ZIP_V12_COSECHAS=CONFIANZA1,CONFIANZA2,CONFIANZA4,CONFIANZA5,CONFIANZA6,CONFIANZA7,CONFIANZA8,CONFIANZA9,CONFIANZA10,CONFIANZA11,CONFIANZA12
```

No es necesario configurar `CCD_ZIP_V12_SOURCE_PROVIDER`: por defecto apunta
a `CcdZipV12FileSourceProvider`. Esa configuración se documenta para un futuro
piloto; no fue aplicada en Hostinger durante esta fase.

El servidor debe tener PHP 8.3, `ext-zip`, `mbstring`, `iconv`, `zlib` y
`fileinfo`. El entorno informado ya dispone de ellas. `composer check-platform-reqs`
fue correcto en Docker PHP 8.3.36; `smalot/pdfparser` bloqueado en
`composer.lock` (`v2.13.1`) declara compatibilidad con PHP desde 7.1 y requiere
`iconv` y `zlib`.

## Pruebas realizadas

| Comprobación | Resultado |
| --- | --- |
| PDF privado individual | Correcto; conserva el flujo existente. |
| ZIP V12 con PDF y una operación | `coincidencia_exacta`; temporal eliminado. |
| Cuenta con varias operaciones | `coincidencia_cuenta`; no se confirma. |
| Operación con ceros iniciales | Conservada como texto. |
| Entrada ZIP inexistente | Error auditable, sin temporal. |
| Nombre PDF inseguro | Rechazado antes de abrir una entrada. |
| Tamaño excesivo | Rechazado por límite. |
| Firma distinta a `%PDF-` | Rechazada y temporal eliminado. |
| ZIP dañado y ZIP ausente | Errores técnicos diferenciados. |
| Excepción recuperable del extractor | El comando falla de forma controlada y elimina el temporal en `finally`. |
| Asociación confirmada previa | Preservada sin cambios. |

Resultados ejecutados:

- `php artisan test tests/Feature/CcdExtractAuditTest.php tests/Feature/CcdCuentaAssociationTest.php`: 13 aprobadas, 60 aserciones.
- `php artisan test --compact`: 147 aprobadas, 2 omitidas, 1160 aserciones.
- `composer validate --no-check-publish`: correcto.
- `composer check-platform-reqs`: correcto en PHP 8.3.36.
- `npm run build`: correcto.
- `git diff --check`: correcto.

La prueba de excepción deliberada registra sólo la clase de excepción, sin
contenido del PDF, DNI, operación ni ruta documental.

## Riesgos y siguiente piloto

1. El proveedor debe probarse primero contra una muestra restaurada en
   `u480021566_prueba`, nunca contra producción.
2. Los nombres internos del ZIP deben coincidir exactamente con
   `ccd_clientes.pdf`; diferencias de mayúsculas, espacios o sufijos se
   clasificarán como documento no encontrado deliberadamente.
3. PDFs escaneados, cifrados o sin texto seguirán en `sin_texto` o `error`;
   esta fase no incorpora OCR.
4. Se recomienda empezar con `--limit=100`, revisar los CSV privados y no
   ejecutar ningún comando de asociación automática hasta validar una muestra
   humana por cosecha.
5. Los ZIP no se cargan completos en memoria: se procesa una entrada y en
   bloques; aun así, se debe observar la duración en hosting compartido antes
   de ampliar el lote.

## Reversión

No se crearon migraciones ni se modificaron registros. Revertir el commit de
esta fase restaura el comportamiento anterior. Los temporales se eliminan al
terminar cada intento; los ZIP, PDFs originales y asociaciones confirmadas no
son alterados.
