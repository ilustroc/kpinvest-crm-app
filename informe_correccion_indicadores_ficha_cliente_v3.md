# Corrección de indicadores de Ficha 360° — CRM V3

Fecha: 2026-11-04  
Alcance: entorno local Docker y base de pruebas `kp_crm_testing`. No se realizaron escrituras en `kp_crm_revision_v3`, Hostinger ni producción.

## Causa identificada

La cabecera de la Ficha 360° sumaba directamente todas las cuentas entregadas a la vista. Por ello no distinguía una operación cuya asignación vigente estuviese a nombre de `CANCELADO` o `CANCELADOS`.

La columna **Monto negociación** tomaba `promesas_pago.monto` y, cuando era cero, sólo la primera cuota del cronograma. En consecuencia, un convenio de dos o más cuotas se mostraba incompleto.

## Corrección aplicada

- `ClienteProfileService` resuelve primero las asignaciones vigentes mediante `ClienteAssignmentService`, como ya lo hacía la ficha, y delega los indicadores a `ClienteAccountService`.
- `ClienteAccountService::isOperationallyCancelled()` normaliza espacios y mayúsculas, y acepta únicamente las etiquetas exactas `CANCELADO` y `CANCELADOS`. No se emplean coincidencias parciales.
- La fórmula ahora es:

  - `DEUDA CAPITAL = SUM(deuda_capital o saldo_capital de cuentas no canceladas)`
  - `DEUDA TOTAL = SUM(deuda_total de cuentas no canceladas)`

  La identidad de cada cuenta sigue siendo la cuenta/operación resuelta por el servicio central; una pendiente o rechazada no cambia el resultado. Las operaciones permanecen visibles en la tabla.
- `PromesaPago::montoNegociado()` suma cada `promesa_cuotas.monto` con `bcadd` cuando hay cronograma. Incluye por tanto cuotas balón y conserva precisión decimal de dos posiciones.
- Si una promesa histórica no tiene cuotas, el fallback seguro es, en este orden, `monto_convenio`, `monto` y `monto_cuota`, sólo si es positivo; de lo contrario se muestra `0.00`.
- El modal de cronograma recibe el mismo fallback histórico que la columna; no se crean ni persisten cuotas nuevas.
- Se incluyó la relación de usuario en la carga de promesas para evitar una consulta por fila.

## Archivos modificados

- `app/Services/Cliente/ClienteAccountService.php`
- `app/Services/Cliente/ClienteProfileService.php`
- `app/ViewModels/Cliente/ClienteShowViewModel.php`
- `app/Models/PromesaPago.php`
- `resources/views/clientes/show.blade.php`
- `resources/views/components/promesas/promise-history.blade.php`
- `tests/Feature/FichaFinancialIndicatorsTest.php`

## Validación con datos locales reales

Para el cliente `06891625`, la operación `8190917` tiene una asignación vigente aprobada a **David Reyes**, no a una etiqueta de cancelación. Por ello sus indicadores permanecen correctamente en:

- DEUDA TOTAL: **S/ 15,098.97**
- DEUDA CAPITAL: **S/ 11,015.67**

La promesa/convenio `4475` tiene dos cuotas de S/ 2,000.00 y S/ 1,000.00. Antes la tabla podía presentar la primera cuota; después presenta **S/ 3,000.00**. Se verificó en el navegador autenticado que el modal del cronograma también muestra total S/ 3,000.00 y las dos cuotas originales.

## Pruebas y controles

- Nueva prueba de ficha: cuentas activas, `CANCELADO`, `CANCELADOS` con espacios, etiqueta similar `CANCELADO EXTRA`, pendiente posterior, reasignación aprobada, cancelación posterior, todas canceladas, ninguna cancelada y operación repetida en dos entidades.
- Nueva prueba de promesas: dos cuotas, varias cuotas con balón, decimales, cancelación, historial sin cronograma y aislamiento frente a cuotas de otra promesa.
- Grupo focalizado: **23 aprobadas, 296 aserciones** (`FichaFinancialIndicatorsTest`, asignaciones, cliente y promesas).
- Flujos V3 restantes: **17 aprobadas, 203 aserciones; 2 omitidas** por no existir un DNI local que los tests heredados requieren. No son fallos.
- La suite se ejecutó en bloques para evitar el límite de salida del terminal; todos los archivos de prueba se completaron sin fallos nuevos.
- `npm run build`: correcto (Vite 5.4.21).
- `git diff --check` y `php -l` de los PHP modificados: correctos.

## Caché y seguridad

No había una caché propia de estos indicadores: la ficha resuelve las cuentas y su asignación vigente por solicitud. Por ello una nueva aprobación ya se refleja en el cálculo siguiente, sin invalidación adicional ni datos obsoletos. No se cambiaron reglas de pagos, cobranzas, aprobaciones, acuerdos Word/PDF, importes almacenados ni permisos.

## Publicación

- Commit funcional: `e3928c12dde1acef7db0036114d6652b01690cb4` — `fix(ficha): corrige deuda vigente y monto negociado`.
- La sincronización de `feature/meridian-ui-v3` y `main` se confirma tras publicar este informe.
