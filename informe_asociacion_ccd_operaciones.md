# Asociación CCD con operaciones

## Alcance

Se implementó una tabla puente para relacionar CCD y cuentas sin modificar `ccd_clientes`, documentos privados, el lector ZIP V12 ni `CcdDocumentController`.

La relación sólo aparece en la ficha cuando está en estado `confirmada`. Las coincidencias por DNI y cosecha normalizada se conservan como `candidata`; no son visibles como una asociación de operación.

## Archivos modificados

- `database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php`
- `app/Models/CcdClienteCuenta.php`
- `app/Models/CcdCliente.php`
- `app/Models/ClienteCuenta.php`
- `app/Services/Ccd/CcdCuentaAssociationService.php`
- `app/Console/Commands/AuditCcdCuentaAssociations.php`
- `app/Services/Cliente/ClienteProfileService.php`
- `resources/views/clientes/show.blade.php`
- `tests/Feature/CcdCuentaAssociationTest.php`

## Tabla puente

`ccd_cliente_cuenta` conserva `ccd_cliente_id`, `cliente_cuenta_id`, estado (`candidata`, `confirmada`, `en_revision`), método, evidencia, usuario confirmador y fecha. Tiene claves foráneas y una restricción única por pareja CCD/cuenta.

## Criterio de asociación

1. El DNI debe coincidir de manera exacta.
2. La cosecha de `clientes_cuentas` se normaliza mediante `ClienteAccountService::mapCosechaClientesToCcd`; después se comparan sólo letras y números en mayúsculas.
3. Una coincidencia de DNI/cosecha crea únicamente una candidata.
4. Una confirmación exige que la operación identifique una sola cuenta candidata, evidencia explícita de la revisión visual del PDF y el usuario que la confirmó.
5. Los nombres de PDF no intervienen como prueba de operación. No se ejecuta OCR masivo.
6. Si hay varias candidatas, todas permanecen candidatas hasta una revisión manual; una confirmación no degrada ni borra las demás.

## Comando de auditoría

El comando es `ccd:associate-audit` y por defecto es simulación, sin escrituras:

```powershell
php artisan ccd:associate-audit --ccd-id=23303 --dni=80201928 --confirm-operation=2744794 --evidence="Operación verificada visualmente en el PDF"
```

Para escribir una confirmación puntual revisada, se debe agregar `--apply` y el usuario que verificó:

```powershell
php artisan ccd:associate-audit --ccd-id=23303 --dni=80201928 --confirm-operation=2744794 --evidence="Operación 2744794 verificada visualmente en el PDF" --confirmed-by=ID_USUARIO --apply
```

Los lotes de candidatas se procesan por ID y tienen límite máximo de 100:

```powershell
php artisan ccd:associate-audit --after-id=0 --limit=25
php artisan ccd:associate-audit --after-id=0 --limit=25 --apply
```

El comando es idempotente: la pareja CCD/cuenta no se duplica. No se ejecutó ningún lote masivo.

## Resultado de validación

- Migración aplicada únicamente a `kp_crm_testing`.
- Clasificación preliminar de referencia (no fue persistida como asociación): 243,167 CCD con una candidata por DNI/cosecha, 26,366 con varias candidatas, 6,367 sin coincidencia de cosecha y 87 sin cuenta encontrada.
- Caso solicitado en simulación: `DNI 80201928`, `CCD 23303`, operación `2744794`.
- Resultado: el CCD `23303` no existe en `kp_crm_testing`; por tanto no se escribió ninguna asociación y el caso debe repetirse contra una copia de pruebas que contenga ese registro antes de aplicar una confirmación.
- Regresión automatizada completa: 136 aprobadas, 1,115 aserciones y 2 omisiones preexistentes por ausencia de DNI local.
- `php artisan view:cache` y limpieza de vistas: correctos.
- `npm run build`: correcto.
- `git diff --check`: correcto.

### Cobertura sintética comprobada

- Simulación de una candidata única: 1 candidata, 0 escrituras.
- Aplicación repetida de candidata: 1 fila persistida, sin duplicados.
- Confirmación visual de operación: 1 asociación confirmada y una candidata alternativa conservada.
- Interfaz: el icono CCD confirmado aparece sólo en la fila de su cuenta y usa exclusivamente `clientes.ccd.show`; los no confirmados permanecen en `CCD sin operación verificada`.

## Reversión

Antes de revertir en cualquier ambiente, exportar `ccd_cliente_cuenta` si ya contiene revisiones manuales. La reversión de esquema se limita a la tabla puente:

```powershell
php artisan migrate:rollback --path=database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php
```

No modifica `ccd_clientes`, `ccd_archivos`, los PDF, ZIP V12 ni datos financieros. Si se requiere preservar las confirmaciones, no ejecutar el rollback: marcar enlaces como `en_revision` o restaurar la tabla desde el respaldo exportado.
