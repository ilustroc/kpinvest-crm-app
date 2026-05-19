# Pruebas funcionales V3

## Cobertura automatizada

La suite Feature cubre:

- Login y usuario inactivo.
- Accesos por rol.
- Busqueda y vista de cliente.
- Cuentas, pagos, promesas y CNA en cliente.
- Creacion y workflow de promesas.
- Creacion y workflow de CNA.
- Generacion de acuerdos y documentos con fallback.
- Reportes y exports.
- Importaciones CSV principales.
- Administracion de usuarios.
- Bandeja de autorizacion.

## Tests principales

- `ExampleTest`
- `V3LocalFlowTest`
- `V3RoleAccessTest`
- `V3WorkflowSmokeTest`
- `V3ClienteModuleTest`
- `V3PromesaModuleTest`
- `V3CnaModuleTest`
- `V3AutorizacionModuleTest`

## Comandos

```bash
php artisan route:list --except-vendor
php artisan view:clear
php artisan cache:clear
php artisan view:cache
php artisan test
npm run build
git diff --check
```

## Pendientes manuales

- Validar navegador real.
- Confirmar consola sin errores.
- Confirmar modales y acciones de workflow.
- Confirmar responsive/desktop.
- Confirmar plantillas DOCX reales.
- Confirmar logs luego de pruebas.
