# Diagramas PlantUML V3

Estos diagramas explican la arquitectura oficial V3 y los flujos criticos.

## Archivos

- `plantuml/arquitectura_general.puml`: vision general del sistema.
- `plantuml/flujo_cliente.puml`: carga de perfil de cliente.
- `plantuml/flujo_promesa.puml`: creacion, workflow y documento de promesa.
- `plantuml/flujo_cna.puml`: creacion, workflow y documentos CNA.
- `plantuml/flujo_autorizacion.puml`: bandeja de aprobaciones.
- `plantuml/flujo_correos_workflow.puml`: correos y botones por rol creador.
- `plantuml/frontend_vite_tailwind.puml`: arquitectura frontend.
- `plantuml/permisos_gates_policies.puml`: roles, gates y policies.
- `plantuml/base_datos_resumen.puml`: entidades principales.

## Cual usar

- Para explicar el proyecto al equipo: `arquitectura_general.puml`.
- Para explicar el frontend: `frontend_vite_tailwind.puml`.
- Para explicar permisos: `permisos_gates_policies.puml`.
- Para explicar flujos operativos: `flujo_cliente`, `flujo_promesa`, `flujo_cna`, `flujo_autorizacion` y `flujo_correos_workflow`.
- Para explicar la base: `base_datos_resumen.puml`.

## Convertir a imagen

Con PlantUML instalado:

```bash
plantuml docs/05_diagramas/plantuml/*.puml
```

Con Docker:

```bash
docker run --rm -v "%cd%:/work" plantuml/plantuml docs/05_diagramas/plantuml/*.puml
```

Tambien pueden abrirse en extensiones de VS Code compatibles con PlantUML.
