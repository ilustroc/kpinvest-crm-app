# KP INVEST — Fase 3: vinculación automática controlada de CCD

Fecha: 2026-11-14
Base: `87bbb21`
Ámbito: Docker local y `kp_crm_testing`. No se ejecutó `--apply` en Hostinger ni producción.

## Implementación

Nuevo comando: `php artisan ccd:associate-verified --after-id=0 --limit=100`.
La simulación es el modo predeterminado: relee el PDF, usa resolver privado/ZIP V12, extractor y comparador, y genera un CSV privado sin escribir tablas.

El modo aplicación exige autorización explícita: `--apply --authorization=CONFIRMAR_ASOCIACION_AUTOMATICA`. No fue usado sobre documentos reales en esta fase.

## Condiciones de elegibilidad

1. El comparador debe devolver `coincidencia_exacta`.
2. La operación debe identificar una sola cuenta candidata.
3. DNI y operación se validan de nuevo contra la cuenta.
4. El DNI numérico debe aparecer directamente en el texto PDF con límites numéricos seguros.
5. No puede existir otra confirmación ni una asociación `en_revision` para la CCD.

La homologación de Financiera Confianza conserva las restricciones de Fase 2.3. DNI, cosecha o cuenta por sí solos nunca confirman.

## Persistencia y concurrencia

`CcdVerifiedAssociationService` vuelve a leer el PDF dentro de una transacción con reintentos. Bloquea CCD, enlaces y cuenta objetivo antes de persistir. La confirmación automática usa `estado=confirmada`, `metodo_verificacion=operacion_pdf_automatica`, `confirmado_por_user_id=NULL`, marca temporal y evidencia del proceso. Una confirmación manual en otra cuenta devuelve `conflicto`; una reejecución devuelve `ya_confirmada` sin duplicar filas.

## Ejemplo de simulación

```text
ccd_id,cliente_cuenta_id,operacion,fuente,resultado,motivo,evidencia,modo
..., ...,00110301995001707769,privado,elegible,operacion_y_dni_documentales_verificados,operacion_candidata_en_texto_pdf,simulacion
```

Los CSV van a `CCD_PRIVATE_DISK/audits/`, no se sobrescriben y protegen contra fórmulas. La consola no muestra DNI ni contenido PDF.

## Archivos

- `app/Console/Commands/AssociateVerifiedCcd.php`
- `app/Services/Ccd/CcdVerifiedAssociationService.php`
- `tests/Feature/CcdExtractAuditTest.php`

No se modificaron controlador CCD, ZIP V12, documentos, asociaciones manuales existentes, CNA, promesas, pagos, CANCELADOS, rutas ni migraciones.

## Pruebas

| Caso | Resultado |
| --- | --- |
| Simulación BBVA / PDF privado | elegible sin escritura |
| `--apply` sin autorización | rechazado |
| `--apply` en fixture de prueba | confirma una vez con método automático |
| Reejecución | `ya_confirmada`, una sola fila |
| Confirmación manual a otra cuenta | conflicto preservado |
| DNI no corroborable | revisión, sin escritura |
| CONFIANZA1 desde ZIP V12 | elegible sólo por operación documental |
| Varias operaciones o cuentas duplicadas | revisión |
| Operación de otro DNI | no elegible |
| PDF sin texto | no elegible |

Resultados: 35 pruebas CCD/asociaciones aprobadas, 159 aserciones. Suite completa: 169 aprobadas, 2 omitidas y 1259 aserciones. Lint PHP y `git diff --check` correctos.

## Riesgos y piloto

El riesgo residual es la presencia casual de DNI y operación en un mismo PDF. Se mitiga con cosecha, entidad cuando aplica, cuenta única, límites numéricos, relectura transaccional y prohibición de sobrescribir manuales. El piloto real debe ser: simulaciones de 100 en `u480021566_prueba`, revisión de CSV privados, muestra humana por cosecha/evidencia y autorización explícita antes de cualquier lote `--apply`.

No se hizo push ni despliegue.
