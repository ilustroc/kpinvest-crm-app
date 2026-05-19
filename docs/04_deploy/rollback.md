# Rollback V3

## Objetivo

Definir como volver a un estado anterior si el deploy falla.

## Rollback de codigo

- Mantener commit anterior identificado.
- Tener artefactos/assets anteriores disponibles.
- Confirmar comando o proceso de revert en el hosting.
- No mezclar rollback de codigo con cambios manuales no documentados.

## Rollback de base de datos

- Tener backup reciente antes de deploy.
- Probar restauracion en entorno seguro si es posible.
- No ejecutar `migrate:fresh`.
- Si hay migraciones incrementales, cada una debe tener `down` seguro o plan manual.

## Rollback de assets

- Confirmar que `public/build` puede reemplazarse por build anterior.
- Limpiar cache de navegador/CDN si aplica.

## Responsable

Debe definirse antes de Fase 9:

- Responsable tecnico.
- Responsable funcional.
- Ventana maxima aceptable.
- Criterio de abortar deploy.
