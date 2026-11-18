# KP INVEST — CCD AQP V4: asociación controlada

## Estado

V4 quedó preparada para diagnóstico, muestreo humano y una futura aplicación
controlada de las 10,224 CCD auditadas en V3:

- AQP4: 7,024.
- AQP5: 3,200.
- CSV V3 esperados: 12.
- Duplicados permitidos: 0.

No se ejecutó ninguna asociación real. El modo predeterminado de
`ccd:associate-aqp-audited` es `dry-run`.

## Controles previos

Antes de procesar un lote, el comando lee todos los CSV indicados y exige:

1. esquema exacto producido por `ccd:recheck-dni` V3;
2. filas con clasificación anterior `revision` y motivo
   `dni_documental_no_corrobable`;
3. clasificación nueva `elegible` y resultado `ahora_elegible`;
4. evidencia `dni_codigo_cliente_aqp_verificado`;
5. fuente `privado` y cosecha AQP4/AQP5;
6. 10,224 CCD únicas, 12 CSV, 7,024 AQP4 y 3,200 AQP5;
7. ausencia total de CCD duplicadas.

Se calcula SHA-256 de cada CSV. Por cada CCD seleccionada se vuelve a comprobar:

- existencia del registro y cosecha sin cambios;
- disponibilidad del PDF privado;
- SHA-256 físico igual al catálogo `ccd_archivos` antes y después del análisis;
- operación documental exacta y cuenta candidata única;
- DNI de CCD igual al DNI de la cuenta;
- código de cliente AQP igual al documento esperado;
- ausencia de DNI o códigos contradictorios;
- ausencia de confirmaciones incompatibles.

El primer fallo de integridad detiene el lote. El CSV privado de salida no
incluye DNI, nombre ni operación.

## Separación de V3 y V4

`ccd:associate-verified` sigue bloqueando el código AQP como evidencia nueva.
V4 utiliza una entrada específica `applyAqpAudited()` que sólo admite AQP4/AQP5,
la evidencia V3 exacta y una huella SHA-256 del reporte.

La persistencia reutiliza las transacciones, `lockForUpdate`, comprobación de
conflictos e idempotencia existentes. El método almacenado será:

```text
operacion_pdf_aqp_auditada_v3
```

Las asociaciones confirmadas previamente nunca se sobrescriben.

## Barreras de aplicación

Una futura aplicación requiere simultáneamente:

1. base exacta `u480021566_prueba`;
2. `CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true`;
3. `CCD_AQP_ASSOCIATION_APPLY_ENABLED=true`;
4. `CCD_AQP_HUMAN_REVIEW_APPROVED=true`;
5. `--apply`;
6. `--authorization=CONFIRMAR_ASOCIACION_AQP_AUDITADA`;
7. `--confirmation=REVALIDAR_PDF_Y_CONFIRMAR_AQP`;
8. totales oficiales completos de la auditoría V3.

Los tres indicadores de entorno permanecen en `false` por defecto. Durante V4
no deben habilitarse.

## Comandos para Hostinger

Los doce nombres reales se agregan repitiendo `--input`. Ejemplo abreviado:

```bash
cd /home/u480021566/apps/kpinvest-crm-pruebas

php artisan ccd:associate-aqp-audited \
  --input=CCD_RECHECK_V3_01.csv \
  --input=CCD_RECHECK_V3_02.csv \
  --input=CCD_RECHECK_V3_03.csv \
  --input=CCD_RECHECK_V3_04.csv \
  --input=CCD_RECHECK_V3_05.csv \
  --input=CCD_RECHECK_V3_06.csv \
  --input=CCD_RECHECK_V3_07.csv \
  --input=CCD_RECHECK_V3_08.csv \
  --input=CCD_RECHECK_V3_09.csv \
  --input=CCD_RECHECK_V3_10.csv \
  --input=CCD_RECHECK_V3_11.csv \
  --input=CCD_RECHECK_V3_12.csv \
  --limit=25 \
  --after-id=0 \
  --output=ccd-aqp-v4-dry-run-001.csv
```

Este comando es seguro para la primera prueba: no usa `--apply`. Para el tramo
siguiente se usa como `--after-id` el mayor `ccd_id` del CSV anterior y un
nombre de salida nuevo. Se puede añadir `--cosecha=AQP4` o `--cosecha=AQP5`.

## Muestra humana independiente

```bash
php artisan ccd:sample-aqp-audit \
  --input=CCD_RECHECK_V3_01.csv \
  --input=CCD_RECHECK_V3_02.csv \
  --input=CCD_RECHECK_V3_03.csv \
  --input=CCD_RECHECK_V3_04.csv \
  --input=CCD_RECHECK_V3_05.csv \
  --input=CCD_RECHECK_V3_06.csv \
  --input=CCD_RECHECK_V3_07.csv \
  --input=CCD_RECHECK_V3_08.csv \
  --input=CCD_RECHECK_V3_09.csv \
  --input=CCD_RECHECK_V3_10.csv \
  --input=CCD_RECHECK_V3_11.csv \
  --input=CCD_RECHECK_V3_12.csv \
  --size-per-cosecha=20 \
  --output=ccd-aqp-v4-muestra-humana.csv
```

El muestreo es aleatorio y estratificado por cuartiles de ID para cubrir el
inicio, dos tramos intermedios y el final de AQP4 y AQP5. El CSV sólo contiene
CCD ID, cosecha, tramo histórico y huella del reporte. El revisor debe abrir el
PDF mediante las rutas privadas existentes y completar:

- identidad del destinatario;
- significado de `CÓDIGO DEL CLIENTE`;
- operación o crédito;
- coincidencia con CRM;
- resultado y observación.

La revisión humana debe concluir antes de cambiar
`CCD_AQP_HUMAN_REVIEW_APPROVED`.

## Comando reservado para una autorización futura

No ejecutar durante esta fase:

```bash
php artisan ccd:associate-aqp-audited \
  --input=CCD_RECHECK_V3_01.csv \
  --input=CCD_RECHECK_V3_02.csv \
  --input=CCD_RECHECK_V3_03.csv \
  --input=CCD_RECHECK_V3_04.csv \
  --input=CCD_RECHECK_V3_05.csv \
  --input=CCD_RECHECK_V3_06.csv \
  --input=CCD_RECHECK_V3_07.csv \
  --input=CCD_RECHECK_V3_08.csv \
  --input=CCD_RECHECK_V3_09.csv \
  --input=CCD_RECHECK_V3_10.csv \
  --input=CCD_RECHECK_V3_11.csv \
  --input=CCD_RECHECK_V3_12.csv \
  --limit=10 \
  --after-id=0 \
  --apply \
  --authorization=CONFIRMAR_ASOCIACION_AQP_AUDITADA \
  --confirmation=REVALIDAR_PDF_Y_CONFIRMAR_AQP \
  --output=ccd-aqp-v4-aplicacion-001.csv
```

## Pruebas y riesgos

Las pruebas cubren dry-run, aplicación transaccional en `kp_crm_testing`, DNI y
código incorrectos, operación ambigua, confirmación incompatible, idempotencia,
duplicados, CSV inválido, PDF ausente o alterado, concurrencia, rollback y
muestreo sin datos personales.

Resultados:

```text
Pruebas focalizadas CCD: 84 aprobadas, 430 aserciones.
Suite Laravel completa: 237 aprobadas, 2 omitidas, 1573 aserciones.
```

Las dos pruebas omitidas dependen de condiciones opcionales del entorno y no
corresponden al módulo CCD.

Limitaciones:

- Los CSV V3 no fueron firmados al generarse. V4 calcula y registra sus huellas,
  valida estrictamente sus filas y vuelve a verificar cada PDF, por lo que un
  cambio del CSV no puede omitir la revalidación documental.
- El bloqueo de archivo evita dos procesos masivos cooperantes; los bloqueos de
  base protegen además frente a otros comandos que intenten confirmar la misma
  CCD simultáneamente.
- No se añadió migración ni se modificó el controlador CCD o ZIP V12.

Para desactivar inmediatamente la futura vía AQP basta mantener o restaurar:

```dotenv
CCD_AQP_ASSOCIATION_APPLY_ENABLED=false
CCD_AQP_HUMAN_REVIEW_APPROVED=false
```

No se eliminan asociaciones automáticamente como procedimiento de reversión;
cualquier reversión futura de datos requerirá una auditoría específica.
