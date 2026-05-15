# 08 - Plan V3

## Estado confirmado

La planificacion V3 parte de estas decisiones y condiciones ya confirmadas:

- [+] El proyecto ya esta corriendo en localhost.
- [+] La base de datos productiva ya fue descargada e importada en local.
- [+] El archivo `.env` local apunta a `127.0.0.1` y a la base local.
- [+] La base local puede usarse para pruebas y validaciones.
- [+] Produccion no sera modificada en esta fase.
- [+] `main` no debe tocarse directamente.
- [+] La arquitectura elegida para V3 sera MVC modular por dominios.
- [+] El frontend migrara progresivamente a Tailwind CSS v4 usando Vite.
- [+] Bootstrap sera eliminado progresivamente, no de golpe.

## Objetivo de V3

La version 3 debe ordenar el proyecto sin poner en riesgo el sistema productivo. El objetivo principal no es reescribir todo, sino mejorar mantenibilidad, claridad, pruebas, frontend y seguridad de despliegue.

Objetivos concretos:

- Trabajar primero sobre localhost y base local importada desde produccion.
- Mantener produccion congelada hasta nuevo aviso.
- Mantener `main` protegido.
- Adoptar MVC modular por dominios.
- Separar logica de negocio en servicios y actions.
- Usar ViewModels para vistas complejas.
- Mantener Blade por ahora.
- Migrar progresivamente de Bootstrap a Tailwind CSS v4 con Vite.
- Crear migraciones ordenadas desde un baseline de la base real.
- Probar todo localmente antes de cualquier intento de deploy.

## Rama de trabajo

Toda planificacion y futuro desarrollo V3 debe hacerse en:

```text
v3/analisis-documentacion
```

`main` debe permanecer intacta mientras el sistema actual siga en produccion.

## Que se puede tocar ahora

En esta etapa se puede tocar:

- Documentacion.
- Configuracion local de frontend cuando se inicie la fase Tailwind/Vite.
- Nuevos archivos de estructura V3, cuando se apruebe iniciar implementacion.
- Tests locales.
- Migraciones nuevas solo sobre base local.

Cuando empiece implementacion, los cambios deben ser pequenos y revisables.

## Que no se debe tocar todavia

No se debe:

- Modificar `main`.
- Ejecutar migraciones en produccion.
- Ejecutar seeders en produccion.
- Ejecutar `migrate:fresh` contra ninguna base que contenga informacion valiosa.
- Cambiar datos reales.
- Eliminar tablas, columnas o relaciones.
- Refactorizar flujos criticos de clientes, promesas, CNA, pagos o reportes sin pruebas.
- Cambiar la logica productiva de aprobaciones.
- Redisenar de golpe pantallas criticas.
- Eliminar Bootstrap en todo el sistema de una sola vez.

## Fases actualizadas

### Fase 1 - Entorno local seguro

Estado:

- [+] Proyecto corriendo en localhost.
- [+] Base de datos local importada.
- [+] `.env` apuntando a localhost.
- [+] Produccion no sera modificada.

Objetivo:

- Usar la copia local como ambiente seguro de validacion.
- Confirmar que los flujos actuales funcionan localmente antes de cambios.

### Fase 2 - Arquitectura MVC modular por dominios

Estado:

- [+] Arquitectura definida: MVC modular por dominios.
- [+] Se mantendra Laravel.
- [+] Se mantendra Blade.
- [+] Se usaran servicios.
- [+] Se usaran actions donde aporten claridad.
- [+] Se usaran ViewModels para vistas complejas.
- [+] Se usaran componentes Blade.
- [+] Rutas separadas por modulo manteniendo URLs, nombres y middlewares actuales.
- [ ] Controladores agrupados fisicamente por dominio.
- [ ] Servicios organizados por dominio.
- [ ] Actions creadas para operaciones puntuales.
- [ ] ViewModels creados para vistas complejas.

Objetivo:

- Ordenar el backend por modulos funcionales.
- Separar rutas por modulo. Estado: completado en `routes/web/`.
- Reducir controladores grandes sin cambiar comportamiento.
- Preparar el refactor de controladores sin mover logica critica todavia.

Modulos base:

- Clientes.
- Promesas.
- CNA.
- Reportes.
- Integracion.
- Admin.
- Dashboard.

Resultado actual:

- `routes/web.php` funciona como agregador.
- `routes/web/dashboard.php` contiene panel y dashboard.
- `routes/web/clientes.php` contiene busqueda, ficha de cliente y acciones desde cliente.
- `routes/web/reportes.php` contiene reportes de CNA, pagos y promesas.
- `routes/web/promesas.php` contiene rutas de autorizacion de promesas y acuerdo.
- `routes/web/cna.php` contiene workflow y descargas CNA.
- `routes/web/integracion.php` contiene importaciones.
- `routes/web/admin.php` contiene administracion de usuarios.

### Fase 3 - Tailwind CSS v4 con Vite

Objetivo:

- Instalar y configurar Tailwind CSS v4 usando Vite.
- Usar `resources/css/app.css` como entrada principal de estilos.
- Usar `resources/js/app.js` como entrada principal de JavaScript.
- Verificar que `@vite` cargue correctamente los assets.

Checklist:

- [ ] Revisar `package.json`.
- [ ] Instalar `tailwindcss`.
- [ ] Instalar `@tailwindcss/vite`.
- [ ] Configurar `vite.config.js`.
- [ ] Configurar `resources/css/app.css`.
- [ ] Revisar `resources/js/app.js`.
- [ ] Ejecutar `npm run dev`.
- [ ] Ejecutar `npm run build`.

### Fase 4 - Migracion progresiva de Bootstrap a Tailwind

Objetivo:

- Eliminar Bootstrap de forma controlada.
- Reemplazar componentes Bootstrap por componentes Blade con Tailwind.
- Evitar reescrituras masivas.

Orden recomendado:

1. Layout principal.
2. Sidebar/topbar.
3. Botones y badges.
4. Formularios simples.
5. Tablas simples.
6. Administracion de usuarios.
7. Dashboard.
8. Reportes.
9. Clientes.
10. CNA y Promesas al final.

### Fase 5 - Refactor backend por modulos

Objetivo:

- Separar controladores por dominio.
- Extraer servicios y actions.
- Crear ViewModels.
- Centralizar permisos.

Regla:

- No cambiar comportamiento visible mientras se refactoriza.

### Fase 6 - Pruebas funcionales

Objetivo:

- Validar localmente todos los flujos criticos.

Flujos minimos:

- Login.
- Clientes.
- Promesas.
- CNA.
- Pagos.
- Reportes.
- Importaciones.
- Administracion.

### Fase 7 - Deploy controlado

Objetivo:

- Preparar despliegue solo cuando V3 este probada localmente.

Antes de produccion:

- Descargar dump productivo actualizado.
- Importarlo en local o staging.
- Ejecutar migraciones sobre esa copia reciente.
- Ejecutar pruebas funcionales.
- Ejecutar build de Vite.
- Revisar checklist de deploy.
- Definir rollback.

## Orden recomendado de implementacion

1. Validar sistema actual sobre base local.
2. Configurar Tailwind CSS v4 con Vite.
3. Crear componentes Blade base.
4. Migrar layout principal sin cambiar flujos.
5. Migrar pantalla piloto de bajo riesgo.
6. Separar rutas por modulo.
7. Extraer servicios de modulos criticos.
8. Crear pruebas funcionales.
9. Ordenar migraciones desde baseline.
10. Preparar deploy controlado.

## Pantalla piloto recomendada

Primera candidata:

- Administracion de usuarios.

Motivo:

- Permite probar layout, tabla, botones, formularios y modales.
- Es menos riesgosa que clientes, promesas o CNA.

Pantallas que deben esperar:

- Clientes.
- Autorizacion.
- CNA.
- Promesas.
