# Backend V3

## Objetivo

Ordenar el backend sin cambiar comportamiento productivo. La prioridad fue reducir controladores grandes, aislar logica critica y crear cobertura de pruebas antes de deploy.

## Modulos refactorizados

### Clientes

- `ClienteController` delega perfil en `ClienteProfileService`.
- Eliminacion de pagos delegada a `DeleteClientePaymentAction`.
- Busqueda rapida delegada a `ClienteLookupService`.
- Vista compleja preparada por `ClienteShowViewModel`.

### Promesas

- `PromesaController` delega creacion en `CreatePromesaAction`.
- Cronogramas en `PromesaScheduleService`.
- Workflow en `PromesaWorkflowService`.
- Acuerdo DOCX/PDF en `PromesaDocumentService`.
- Bandeja de autorizacion en `PromesaQueryService`.

### CNA

- `CnaController` delega creacion, workflow y descargas en Actions/Services.
- Numeracion en `CnaNumberingService`.
- Documentos y fallback en `CnaDocumentService`.
- Bandeja de autorizacion en `CnaQueryService`.

### Autorizacion

- `AutorizacionController` quedo como orquestador HTTP.
- `index()` usa `AutorizacionIndexService`.
- `pagosDni()` usa `AutorizacionPaymentLookupService`.
- Datos finales para Blade salen de `AutorizacionIndexViewModel`.

## Controladores revisados

- `ClienteController`
- `ClienteLookupController`
- `PromesaController`
- `PromesaPdfController`
- `CnaController`
- `AutorizacionController`

Todos conservan rutas, nombres de rutas, redirects, mensajes y contratos de vista.

## Clases legacy mantenidas

`app/Services/Promesas/PromesaCreator.php` se mantiene como wrapper legacy. No tiene logica propia de V3; delega en `PromesaCreationService`. Se conserva para evitar romper referencias historicas o resolucion externa.

## Limpieza realizada

- Se removio `.gitkeep` de `app/Services/Dashboard` porque ya contiene `DashboardStatsService`.
- Se eliminaron `.gitkeep` restantes de carpetas futuras sin clases reales.
- Se dejaron fuera del repo carpetas vacias de DTOs, Actions, Services, ViewModels y pages que todavia no tienen uso funcional.
- Se elimino un servicio legacy duplicado de workflow de promesas.
- Se revisaron namespaces, imports y duplicados visibles.

## Pendientes

- Mover controladores a carpetas por dominio cuando exista una ventana segura.
- Reubicar servicios historicos de reportes bajo `app/Services/Reporte`.
- Crear servicios reales para integraciones/importaciones si se refactoriza ese modulo.
- Crear DTOs o carpetas futuras solo cuando haya clases reales que las justifiquen.
- Ejecutar validacion manual completa en navegador.
