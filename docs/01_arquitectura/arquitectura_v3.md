# Arquitectura V3

## Decision oficial

La arquitectura V3 es:

```text
MVC modular por dominios + Services + Actions + ViewModels + Policies/Gates + Blade Components
```

No se adopto Clean Architecture completa porque seria demasiado pesada para un CRM interno ya productivo. V3 toma las partes utiles: casos de uso claros, capas testeables y dependencias ordenadas.

## Flujo recomendado

```text
Route -> Controller -> Service/Action -> Model -> ViewModel -> Blade View
```

Ejemplo Clientes:

```text
clientes.show
  -> ClienteController@show
  -> ClienteProfileService
  -> ClienteAccountService / ClientePaymentService
  -> ClienteShowViewModel
  -> clientes/show.blade.php
```

Ejemplo Autorizacion:

```text
autorizacion
  -> AutorizacionController@index
  -> AutorizacionIndexService
  -> PromesaQueryService / CnaQueryService
  -> AutorizacionIndexViewModel
  -> autorizacion/index.blade.php
```

## Rutas

`routes/web.php` es un agregador. Las rutas reales estan por dominio:

- `routes/web/dashboard.php`
- `routes/web/clientes.php`
- `routes/web/promesas.php`
- `routes/web/cna.php`
- `routes/web/reportes.php`
- `routes/web/integracion.php`
- `routes/web/admin.php`

## Capas

### Controllers

Reciben request, validan entrada, llaman servicios o acciones y retornan view, JSON, descarga o redirect.

### Services

Contienen logica de dominio, consultas, calculos, armado de datos y coordinacion reutilizable.

### Actions

Ejecutan operaciones puntuales como crear promesa, aprobar CNA o eliminar pagos.

### ViewModels

Preparan datos para Blade sin cargar el controlador de arrays grandes.

### Policies/Gates

Centralizan permisos y reducen validaciones de rol repetidas.

### Blade Components

Concentran UI reutilizable sin Bootstrap.

## Estado actual

- [+] Rutas separadas por dominio.
- [+] Frontend migrado a Tailwind/Vite.
- [+] Cliente, Promesa, CNA y Autorizacion refactorizados.
- [+] Policies de Cliente, Promesa, CNA y Usuario creadas.
- [+] ViewModels de Cliente, Admin y Autorizacion creados.
- [+] Tests Feature de modulos criticos creados.

## Pendientes arquitectonicos

- [!] Los controladores siguen en `app/Http/Controllers` raiz. Moverlos a carpetas por dominio implicaria actualizar namespaces y rutas; queda pendiente hasta tener una ventana segura.
- [!] `app/Services/Promesas/PromesaCreator.php` se mantiene como wrapper legacy de compatibilidad. La implementacion V3 vive en `app/Services/Promesa`.
- [!] Algunos servicios historicos de reportes siguen en `app/Services` raiz. Moverlos a `app/Services/Reporte` es una mejora posterior.
- [+] Se eliminaron placeholders `.gitkeep` y carpetas futuras sin uso real para no dejar estructura fantasma en el repo.

## Regla final

Cada cambio debe indicar modulo, comportamiento preservado, riesgo, prueba y forma de rollback.
