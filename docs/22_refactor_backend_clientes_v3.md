# 22 - Refactor backend Clientes V3

## Objetivo

Ordenar el backend del modulo Clientes sin cambiar rutas, nombres de rutas, permisos funcionales, estructura de base de datos ni comportamiento observable.

Esta fase corresponde a:

```text
Fase 8.1 - Refactor backend del modulo Clientes
```

## Responsabilidades detectadas antes del refactor

`app/Http/Controllers/ClienteController.php` concentraba:

- Carga de cuentas del cliente.
- Validacion de existencia de cliente.
- Busqueda de asesor asignado por operacion.
- Carga de pagos.
- Calculo de totales de pagos.
- Agrupacion de pagos por operacion.
- Mapeo de cuenta por operacion.
- Carga y agrupacion de CCD.
- Normalizacion de cosechas Cliente -> CCD.
- Carga de promesas con operaciones y cuotas.
- Carga y agrupacion de CNA por cuenta y operacion.
- Calculo del siguiente numero de carta CNA.
- Armado completo de variables para `clientes/show.blade.php`.
- Validacion de rol para eliminar pagos.
- Eliminacion de pagos.
- Manejo de errores y logging.

`app/Http/Controllers/ClienteLookupController.php` concentraba:

- Busqueda rapida por DNI, operacion o nombre.
- Redireccion directa cuando habia un unico cliente.
- Lista de coincidencias cuando habia varios candidatos.
- Respuesta JSON para autocomplete.

## Servicios creados

### `app/Services/Cliente/ClienteProfileService.php`

Responsabilidad:

- Orquestar la ficha completa del cliente.
- Coordinar cuentas, pagos, promesas, CNA y CCD.
- Construir `ClienteShowViewModel`.

### `app/Services/Cliente/ClienteAccountService.php`

Responsabilidad:

- Consultar cuentas por DNI.
- Consultar asesores asignados por operacion.
- Agregar datos de pagos y asesor a cada cuenta.
- Mantener el mapeo de cosecha de Cliente hacia CCD.

### `app/Services/Cliente/ClientePaymentService.php`

Responsabilidad:

- Consultar pagos del cliente.
- Calcular total de pagos.
- Agrupar pagos por operacion.
- Detectar cuenta de recaudo frecuente por operacion.
- Normalizar IDs recibidos para eliminacion.
- Eliminar pagos pertenecientes al DNI indicado.

### `app/Services/Cliente/ClienteLookupService.php`

Responsabilidad:

- Mantener la busqueda rapida usada por `/clientes/lookup`.
- Mantener la respuesta JSON usada por `/clientes/suggest`.
- Evitar que `ClienteLookupController` contenga consultas largas.

Nota:

- No se amplio la busqueda a telefono en esta fase para no cambiar comportamiento funcional. Queda como posible mejora posterior si se define una fuente de telefono confiable.

## ViewModel creado

### `app/ViewModels/Cliente/ClienteShowViewModel.php`

Responsabilidad:

- Transportar hacia Blade los mismos datos que ya esperaba `clientes/show.blade.php`.
- Evitar que el controlador arme un `compact()` grande.
- Mantener nombres compatibles:
  - `dni`.
  - `titular`.
  - `cuentas`.
  - `pagos`.
  - `promesas`.
  - `ccdDocs`.
  - `ccdByDni`.
  - `ccdByCodigo`.
  - `ccdByCosecha`.
  - `ccdCosechaKeys`.
  - `cnasByCuenta`.
  - `cnasByOperacion`.
  - `pagosGrouped`.
  - `nextNroCarta`.
  - `totPagos`.

## Action creada

### `app/Actions/Cliente/DeleteClientePaymentAction.php`

Responsabilidad:

- Ejecutar la eliminacion puntual de pagos.
- Validar permiso mediante Gate `delete-client-payments`.
- Mantener el mismo mensaje de error si no hay IDs seleccionados.
- Mantener el borrado restringido al DNI actual.

No se creo `SearchClienteAction` porque `ClienteLookupService` cubre el caso de busqueda sin agregar una clase innecesaria.

## Policy creada

### `app/Policies/ClientePolicy.php`

Permisos definidos:

- `view`.
- `search`.
- `deletePayment`.
- `createPromesa`.
- `createCna`.

Gates registrados en `app/Providers/AuthServiceProvider.php`:

- `view-cliente`.
- `search-clientes`.
- `delete-client-payments`.
- `create-cliente-promesa`.
- `create-cliente-cna`.

La regla funcional para eliminar pagos se mantiene igual:

- Permitido para `administrador`, `supervisor` y `soporte`.
- Bloqueado para `asesor`.

## Cambios en controladores

### `ClienteController`

Antes:

- Consultaba modelos directamente.
- Armaba la vista completa.
- Contenia helpers de mapeo.
- Validaba rol para eliminar pagos.

Ahora:

- `show()` llama a `ClienteProfileService`.
- `deletePagos()` llama a `DeleteClientePaymentAction`.
- Mantiene manejo de errores y logging.
- Mantiene mismas rutas, respuestas y redirects.

### `ClienteLookupController`

Antes:

- Contenia toda la consulta de busqueda.

Ahora:

- Delega a `ClienteLookupService`.
- Mantiene el contrato de:
  - redirect a `clientes.show`.
  - `quick_error`.
  - `quick_list`.
  - JSON con `dni`, `nombre`, `operacion`, `cosecha`, `url`.

## Vista tocada

`resources/views/clientes/show.blade.php` tuvo un ajuste minimo:

- `canDeletePagos` ahora usa `Gate::allows('delete-client-payments')`.

No se cambio layout, estructura visual ni flujo de formularios.

## Comportamiento mantenido

- Misma URL `clientes/{dni}`.
- Mismo nombre de ruta `clientes.show`.
- Mismas rutas de busqueda `clientes.quick` y `clientes.suggest`.
- Misma ruta `clientes.pagos.delete`.
- Mismos middlewares.
- Mismas variables esperadas por Blade.
- Misma agrupacion de pagos por operacion.
- Mismo mapeo de cuentas de recaudo.
- Mismo agrupamiento CNA por cuenta y operacion.
- Mismo fallback de error del controlador.
- Misma regla de eliminacion de pagos.

## Tests creados

Archivo:

- `tests/Feature/V3ClienteModuleTest.php`.

Cobertura:

- Busqueda/autocomplete de cliente.
- Redireccion de busqueda rapida.
- Render de vista de cliente.
- Carga de cuentas.
- Carga de pagos.
- Carga de promesas.
- Carga de CNA.
- Permiso de eliminacion de pagos bloqueado para asesor.
- Eliminacion de pagos permitida para administrador.

## Validaciones realizadas

- [+] `php -l` en archivos PHP nuevos/modificados.
- [+] `php artisan test --filter=V3ClienteModuleTest`.

## Pendientes

- Refactor backend de Promesas. Estado: completado en `docs/23_refactor_backend_promesas_v3.md`.
- Refactor backend de CNA.
- Extraer permisos internos de Promesas/CNA hacia Policies.
- Evaluar busqueda por telefono solo si se define una fuente de datos estable.
- Mover controladores a namespaces por dominio solo cuando exista una ventana de refactor mas amplia.
