# KP INVEST — Fase 3.1: seguridad previa a la asociación automática

Fecha: 2026-11-14
Base: `3665856`
Ámbito: Docker local y `kp_crm_testing`; no hubo escrituras en staging ni producción.

## Bloqueo de aplicación

El modo `ccd:associate-verified --apply` ahora requiere cuatro condiciones simultáneas:

1. Argumento `--apply`.
2. `--authorization=CONFIRMAR_ASOCIACION_AUTOMATICA`.
3. Conexión a la base exacta `u480021566_prueba`.
4. `CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true`.

La configuración predeterminada es `false`. Una conexión de producción, cualquier otra base o una variable deshabilitada rechaza la aplicación antes de leer o escribir asociaciones. Las simulaciones no requieren habilitación y siguen siendo de solo lectura.

## Identidad documental

La corroboración de DNI ya no acepta una secuencia numérica aislada en cualquier parte del PDF. Sólo reconoce:

- Etiquetas explícitas: DNI, documento de identidad o equivalente, con límites numéricos y ceros iniciales preservados.
- Encabezado histórico BBVA de destinatario en una única línea: `SEÑOR(A)`, `SR(A)`, destinatario, cliente o titular, seguido por la identidad.

Una referencia accidental, parcial o contradictoria produce `revision` con `dni_documental_no_corrobable`. El contenido completo del PDF no se registra en logs.

## Conflictos y concurrencia

El servicio automático bloquea la CCD, sus enlaces y la cuenta objetivo dentro de transacción antes de reconfirmar el documento. El servicio manual existente ahora bloquea la misma CCD y enlaces antes de confirmar, y rechaza una segunda confirmación para otra cuenta. Esto evita que los comandos manual y automático finalicen con dos asociaciones confirmadas distintas.

Las confirmaciones manuales no se modifican. Los conflictos se devuelven como `conflicto`; los registros `en_revision` se preservan para revisión humana.

## Trazabilidad

El CSV privado ahora incluye CCD ID, cuenta ID, operación, fuente (`privado` o `zip_v12`), resultado, motivo, evidencia, método y modo. El método automático es siempre `operacion_pdf_automatica`; no se inventa un usuario de revisión visual. Los fallos técnicos se registran sólo con la clase de excepción, sin PDF, DNI, operación ni rutas documentales.

## Archivos modificados

- `app/Services/Ccd/CcdAutoAssociationApplyGuard.php`
- `config/ccd.php`
- `app/Console/Commands/AssociateVerifiedCcd.php`
- `app/Services/Ccd/CcdVerifiedAssociationService.php`
- `app/Services/Ccd/CcdCuentaAssociationService.php`
- `app/Console/Commands/AuditCcdCuentaAssociations.php`
- `tests/Feature/CcdExtractAuditTest.php`
- `tests/Feature/CcdCuentaAssociationTest.php`

No se modificaron controlador CCD, ZIP V12, documentos, pagos, CNA, promesas, CANCELADOS, rutas, migraciones ni producción.

## Pruebas

| Control | Resultado |
| --- | --- |
| `--apply` sin autorización | rechazado |
| `--apply` fuera de `u480021566_prueba` | rechazado |
| Variable de habilitación falsa | rechazada por el guard |
| Guard con staging exacto + habilitación verdadera | permitido para una futura ejecución controlada |
| Simulación | sin escrituras |
| DNI accidental | revisión |
| Encabezado BBVA `SEÑOR(A)` | identidad aceptada |
| Confirmación manual preexistente | conflicto preservado |
| Reintento automático | idempotente |
| Operaciones con ceros, BBVA, CONFIANZA y Caja Arequipa | regresión cubierta |

Resultados: 40 pruebas CCD/asociaciones aprobadas, 171 aserciones. Suite completa: 174 aprobadas, 2 omitidas, 1271 aserciones.

## Limitaciones y siguiente paso

La detección del encabezado BBVA se limita deliberadamente a formatos conocidos; formatos nuevos deben pasar a revisión, no relajarse automáticamente. Los bloqueos cubren comandos manuales y automáticos que usan estos servicios, pero el primer lote real debe permanecer en simulación hasta revisar el CSV. Si se autoriza una aplicación futura, usar un lote mínimo en `u480021566_prueba`, con `CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true`, y deshabilitarlo inmediatamente tras completar el lote.

No se hizo push ni despliegue.
