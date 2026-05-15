# 05 - Rutas y Controladores

## Rutas publicas

Definidas en `routes/web.php`:

- `GET /login` -> `AuthController@form`
- `POST /login` -> `AuthController@login`
- `POST /logout` -> `AuthController@logout`, con middleware `auth`

## Rutas protegidas

Grupo principal:

```php
Route::middleware(['auth', 'active'])->group(...)
```

Dentro de este grupo estan las rutas funcionales del sistema.

## Panel y dashboard

- `GET /` -> `PanelController@index`, nombre `panel`
- `GET /dashboard` -> `DashboardController@index`, nombre `dashboard`
- `GET /panel` redirige a `/`

## Clientes

Grupo:

```text
/clientes
```

Middleware:

- `auth`
- `active`
- `block.cliente`

Rutas:

- `GET /clientes/suggest` -> `ClienteLookupController@suggest`
- `GET /clientes/lookup` -> `ClienteLookupController@quickLookup`
- `GET /clientes/{dni}` -> `ClienteController@show`
- `POST /clientes/{dni}/promesas` -> `PromesaController@store`
- `POST /clientes/{dni}/cnas` -> `CnaController@store`
- `POST /clientes/{dni}/pagos/delete` -> `ClienteController@deletePagos`

## Reportes

Grupo:

```text
/reportes
```

### CNA

- `GET /reportes/cna` -> `ReporteCnaController@index`
- `GET /reportes/cna/facets` -> `ReporteCnaController@facets`
- `GET /reportes/cna/export` -> `ReporteCnaController@export`

### Pagos

- `GET /reportes/pagos` -> `ReportePagosController@index`
- `GET /reportes/pagos/facets` -> `ReportePagosController@facets`
- `GET /reportes/pagos/export` -> `ReportePagosController@export`

### Promesas

- `GET /reportes/promesas` -> `ReportePromesasController@index`
- `GET /reportes/promesas/facets` -> `ReportePromesasController@facets`
- `GET /reportes/promesas/export` -> `ReportePromesasController@export`

## Autorizaciones

Middleware:

```text
role:administrador,supervisor
```

Rutas:

- `GET /autorizacion` -> `AutorizacionController@index`
- `GET /autorizacion/pagos/{dni}` -> `AutorizacionController@pagosDni`
- `POST /autorizacion/{promesa}/preaprobar` -> `AutorizacionController@preaprobar`
- `POST /autorizacion/{promesa}/rechazar-sup` -> `AutorizacionController@rechazarSup`
- `POST /autorizacion/{promesa}/aprobar` -> `AutorizacionController@aprobar`
- `POST /autorizacion/{promesa}/rechazar-admin` -> `AutorizacionController@rechazarAdmin`
- `POST /cna/{cna}/preaprobar` -> `CnaController@preaprobar`
- `POST /cna/{cna}/rechazar-sup` -> `CnaController@rechazarSup`
- `POST /cna/{cna}/aprobar` -> `CnaController@aprobar`
- `POST /cna/{cna}/rechazar-admin` -> `CnaController@rechazarAdmin`

## Documentos

- `GET /promesas/{promesa}/acuerdo` -> `PromesaPdfController@acuerdo`
- `GET /cna/{id}/pdf` -> `CnaController@pdf`
- `GET /cna/{id}/docx` -> `CnaController@docx`

Las descargas CNA estan bajo roles `administrador`, `supervisor`, `asesor`, `soporte`.

## Integraciones

Grupo:

```text
/integracion
```

Middleware:

```text
role:administrador,supervisor,soporte
```

### Data maestra

- `GET /integracion/data`
- `GET /integracion/data/template`
- `POST /integracion/data/import`

### Asignacion

- `GET /integracion/asignacion`
- `GET /integracion/asignacion/template`
- `POST /integracion/asignacion/import`

### CCD

- `GET /integracion/ccd`
- `GET /integracion/ccd/template`
- `POST /integracion/ccd/import`

### Pagos

- `GET /integracion/pagos`
- `GET /integracion/pagos/template`
- `POST /integracion/pagos/import`

## Administracion

Grupo:

```text
/administracion
```

Middleware:

```text
role:administrador,supervisor,soporte
```

Rutas:

- `GET /administracion` -> `AdminUsersController@index`
- `POST /administracion/usuarios` -> `AdminUsersController@store`
- `PATCH /administracion/usuarios/{user}/toggle` -> `AdminUsersController@toggle`
- `PATCH /administracion/usuarios/{user}/password` -> `AdminUsersController@updatePassword`

## Redirecciones de compatibilidad

- `ANY /index.php` redirige a `/`
- `GET /home` redirige a `/`

## API

`routes/api.php` solo conserva:

- `GET /api/user`, protegido por `auth:sanctum`

No se detecto una API publica propia para integraciones externas.

## Controladores mas grandes

Segun conteo aproximado de lineas:

- `CnaController`: 421 lineas.
- `AutorizacionController`: 361 lineas.
- `PromesaPdfController`: 345 lineas.
- `ClienteController`: 206 lineas.
- `PanelController`: 147 lineas.

Estos archivos deben revisarse con prioridad en V3 porque concentran logica de negocio, acceso a datos, transformaciones y presentacion indirecta.

## Observacion de middleware

En `app/Http/Kernel.php` aparece una definicion duplicada de `role` dentro de `$routeMiddleware`, una apuntando al middleware local y otra a `Spatie\Permission\Middlewares\RoleMiddleware`. Composer no muestra `spatie/laravel-permission` como dependencia.

En Laravel 10 la propiedad usada para alias es `$middlewareAliases`, donde `role` apunta correctamente al middleware local. Aun asi, la duplicidad en `$routeMiddleware` es ruido tecnico y debe limpiarse con cuidado en V3.

