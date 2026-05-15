# 08 - Plan V3

## Objetivo de V3

La version 3 debe ordenar el proyecto sin poner en riesgo el sistema que ya funciona en produccion. El objetivo principal no es reescribir todo, sino mejorar mantenibilidad, claridad, pruebas y seguridad de despliegue.

Objetivos concretos:

- Trabajar siempre fuera de `main`.
- Usar una copia local reciente de la base productiva como ambiente de prueba.
- Construir migraciones ordenadas desde un baseline real.
- Refactorizar por modulos, empezando por las partes mas criticas.
- Separar logica de negocio desde controladores hacia servicios/actions.
- Ordenar frontend con Blade, componentes reutilizables, Tailwind CSS y Vite.
- Validar todo localmente antes de pensar en produccion.

## Rama de trabajo

Toda planificacion, documentacion y futuro desarrollo V3 debe hacerse en:

```text
v3/analisis-documentacion
```

`main` debe permanecer intacta mientras el sistema actual siga en produccion.

## Que se puede tocar por ahora

En esta etapa de planificacion:

- Documentacion en `README.md` y `docs/`.
- Analisis tecnico.
- Propuestas de arquitectura.
- Planes de migracion.
- Checklists.
- Inventarios de rutas, tablas, modulos y riesgos.

Cuando empiece la fase de implementacion V3, se podra tocar codigo solo con alcance controlado:

- Nuevos servicios.
- Nuevas clases de apoyo.
- Nuevos componentes Blade.
- Nuevos archivos JS/CSS gestionados por Vite.
- Nuevas migraciones, primero validadas localmente.
- Tests.

## Que no se debe tocar todavia

Por ahora no se debe:

- Modificar `main`.
- Ejecutar migraciones en produccion.
- Ejecutar seeders en produccion.
- Cambiar datos reales.
- Eliminar tablas, columnas o relaciones.
- Refactorizar controladores, modelos, vistas o rutas productivas sin pruebas.
- Cambiar el flujo funcional de promesas, CNA, pagos, clientes o reportes.
- Reemplazar de golpe todo el frontend.
- Exponer valores reales de `.env` en commits o documentacion.

## Estrategia general para no romper produccion

La V3 debe usar el flujo:

1. Descargar copia actual de produccion.
2. Importarla en una base local aislada.
3. Apuntar `.env` local a esa base.
4. Validar que el sistema local funcione igual que produccion.
5. Crear baseline de esquema.
6. Crear migraciones V3 sobre esa base local.
7. Ejecutar pruebas locales.
8. Repetir el proceso con un dump productivo mas reciente antes del despliegue.
9. Solo despues de validar, preparar deploy controlado.

## Fases de trabajo

### Fase 0 - Documentacion y diagnostico

Estado actual:

- Documentacion inicial creada.
- SQL real identificado como referencia.
- Riesgos principales registrados.
- Arquitectura V3 en planificacion.

### Fase 1 - Base local segura

Actividades:

- Descargar dump actual de produccion.
- Importar en local.
- Confirmar que `.env` local apunta a una base local, no a produccion.
- Probar login, clientes, promesas, CNA, pagos y reportes en local.
- Documentar diferencias entre SQL actual y migraciones existentes.

Resultado esperado:

- Ambiente local confiable que replique produccion.

### Fase 2 - Baseline de base de datos

Actividades:

- Definir un punto de partida para migraciones.
- Crear migracion baseline o estrategia equivalente.
- Registrar tablas, columnas, indices y claves foraneas esperadas.
- Confirmar columnas legacy que se mantienen por compatibilidad.

Resultado esperado:

- El equipo sabe desde que esquema parte V3.

### Fase 3 - Pruebas de regresion

Antes de refactorizar, crear pruebas para:

- Login y usuarios inactivos.
- Busqueda de clientes.
- Vista de cliente.
- Creacion de promesa.
- Aprobacion/rechazo de promesa.
- Creacion de CNA.
- Aprobacion/rechazo de CNA.
- Importacion de pagos.
- Reportes principales.

Resultado esperado:

- Red minima para detectar roturas.

### Fase 4 - Refactor backend sin cambio funcional

Actividades:

- Separar rutas por modulo.
- Extraer servicios desde controladores grandes.
- Crear ViewModels para pantallas complejas.
- Crear Actions para operaciones puntuales.
- Centralizar permisos con Policies o Gates.

Resultado esperado:

- Misma funcionalidad, codigo mas mantenible.

### Fase 5 - Orden frontend

Actividades:

- Instalar/configurar Tailwind CSS con Vite.
- Migrar CSS gradualmente.
- Extraer JS embebido hacia `resources/js/modules`.
- Crear componentes Blade reutilizables.
- Mantener Blade como sistema principal de vistas.

Resultado esperado:

- Interfaz mas uniforme sin reescritura brusca.

### Fase 6 - Migraciones V3 validadas

Actividades:

- Crear migraciones nuevas solo para cambios aprobados.
- Probar migraciones sobre copia local reciente de produccion.
- Revisar diferencias de estructura antes/despues.
- Definir rollback.

Resultado esperado:

- Migraciones listas para staging o deploy controlado.

### Fase 7 - Deploy controlado

Actividades:

- Descargar dump reciente.
- Probar migraciones sobre esa copia.
- Ejecutar pruebas funcionales.
- Generar assets con Vite.
- Revisar checklist de produccion.
- Definir ventana de despliegue y rollback.

Resultado esperado:

- Deploy con riesgo reducido y plan de retorno.

## Orden recomendado de implementacion

1. Confirmar dump local actualizado.
2. Crear baseline/documentacion de esquema.
3. Agregar pruebas criticas.
4. Separar rutas por modulo.
5. Extraer servicios de `ClienteController`, `CnaController`, `AutorizacionController` y `PromesaPdfController`.
6. Crear componentes Blade compartidos.
7. Configurar Tailwind/Vite.
8. Migrar una pantalla piloto.
9. Crear migraciones nuevas solo cuando el baseline este claro.
10. Preparar deploy con checklist.

## Modulos prioritarios

Prioridad alta:

- Clientes.
- Promesas.
- CNA.
- Autorizaciones.
- Base de datos/migraciones.

Prioridad media:

- Reportes.
- Importaciones.
- Administracion de usuarios.

Prioridad baja inicial:

- Redisenos visuales profundos.
- Nuevas funcionalidades.
- APIs publicas.

