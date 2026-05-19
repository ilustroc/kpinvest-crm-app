# Modulo Clientes

## Alcance

Gestiona busqueda, perfil del cliente, cuentas, pagos, promesas asociadas, CNA asociadas y CCD.

## Rutas principales

- `clientes.suggest`
- `clientes.quick`
- `clientes.show`
- `clientes.promesas.store`
- `clientes.cna.store`
- `clientes.pagos.delete`

## Backend V3

- `ClienteController` orquesta perfil y eliminacion de pagos.
- `ClienteLookupController` orquesta busqueda rapida y sugerencias.
- `ClienteProfileService` prepara la vista completa.
- `ClienteAccountService` ordena cuentas/deudas.
- `ClientePaymentService` consulta y elimina pagos.
- `ClienteLookupService` resuelve busquedas por DNI, cliente u operacion.
- `ClienteShowViewModel` entrega variables compatibles con `clientes/show.blade.php`.
- `DeleteClientePaymentAction` encapsula la operacion puntual de eliminar pagos.
- `ClientePolicy` centraliza permisos del modulo.

## Frontend

La pantalla `resources/views/clientes/show.blade.php` ya usa Tailwind/Vite y componentes de:

- `components/clientes`
- `components/promesas`
- `components/cna`
- `components/tables`
- `components/forms`
- `components/ui`
- `components/feedback`

## Comportamiento preservado

- Rutas, nombres de rutas y formularios POST.
- Variables esperadas por Blade.
- Creacion de promesas y CNA desde cliente.
- Eliminacion de pagos para roles permitidos.
- Mensajes y redirects.

## Tests

`tests/Feature/V3ClienteModuleTest.php` cubre busqueda, vista, cuentas, pagos, promesas, CNA y eliminacion de pagos.

## Pendientes

- Busqueda por telefono queda pendiente hasta definir fuente estable sin cambiar comportamiento.
- Mover controller a namespace de dominio queda para una fase futura.
