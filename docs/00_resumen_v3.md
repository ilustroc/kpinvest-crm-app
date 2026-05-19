# 00 - Resumen V3

## Que es V3

V3 es la reorganizacion tecnica del CRM de cobranzas KP Invest sin reescritura total. El objetivo es mantener el comportamiento productivo, pero dejar una base mas mantenible para frontend, backend, base de datos, pruebas y deploy.

## Que cambio

- Se definio MVC modular por dominios como arquitectura oficial.
- Las rutas quedaron separadas por modulo en `routes/web/*.php`.
- El frontend paso a Tailwind CSS v4 con Vite.
- Bootstrap fue eliminado globalmente.
- Los assets frontend nuevos viven en `resources/css` y `resources/js`.
- Clientes, Promesas, CNA y Autorizacion fueron refactorizados hacia Services, Actions, ViewModels y Policies.
- Roles validos quedaron reducidos a `administrador`, `supervisor`, `asesor` y `soporte`.
- Las migraciones V3 fueron reconstruidas desde `u480021566_kpinvest_bd.sql`.
- Se amplio la cobertura con tests Feature para flujos criticos.

## Cerrado tecnicamente

- [+] Frontend Tailwind/Vite.
- [+] Eliminacion tecnica de Bootstrap.
- [+] Backend critico de Clientes.
- [+] Backend critico de Promesas.
- [+] Backend critico de CNA.
- [+] Backend critico de Autorizacion.
- [+] Baseline de migraciones probado en base limpia.
- [+] Roles finales centralizados.
- [+] Tests automatizados locales.
- [+] Placeholders `.gitkeep` y carpetas futuras sin uso real eliminados del repo.

## Pendiente

- [ ] Pruebas manuales en navegador real.
- [ ] Confirmar responsive/desktop visual.
- [ ] Revisar logs luego de pruebas completas.
- [ ] Confirmar plantillas DOCX reales en entorno objetivo.
- [ ] Confirmar credenciales iLovePDF/SMTP si aplican.
- [ ] Probar deploy sobre dump productivo reciente.
- [ ] Definir ventana, responsable y rollback.

## Estado de frontend

El frontend final usa Blade, Blade Components, Tailwind CSS v4 y Vite. `public/css` y `public/js` no deben recibir nuevos assets. La logica JavaScript vive en `resources/js/core` y `resources/js/modules`.

## Estado de backend

Los controladores criticos ahora actuan principalmente como orquestadores HTTP. La logica pesada se separo en servicios de dominio y acciones puntuales. No se movieron namespaces de controladores a carpetas por dominio para evitar riesgo sobre rutas y resolucion de clases.

## Estado de migraciones

Las migraciones historicas incorrectas fueron reemplazadas por un baseline V3 basado en el SQL real. El baseline fue probado en la base desechable `kpinvest_v3_migrate_test`. En produccion no debe ejecutarse nada sin estrategia de baseline y copia reciente.

## Estado de pruebas

La suite local cubre login, roles, clientes, promesas, CNA, autorizacion, reportes e importaciones principales. Las pruebas automatizadas no reemplazan la validacion manual de navegador antes de deploy.

## Riesgos antes de produccion

- Ejecutar migraciones V3 sobre una base existente sin marcar baseline.
- Plantillas DOCX faltantes o rutas de storage con permisos incorrectos.
- Credenciales externas de conversion PDF no configuradas.
- Diferencias entre dump local y produccion al momento de deploy.
- Validacion visual pendiente en navegador real.

## Riesgos historicos absorbidos

Estos puntos venian del diagnostico tecnico inicial y siguen siendo referencia para no repetir problemas:

- Las migraciones historicas no representaban la base real y fueron reemplazadas por baseline V3.
- Algunos imports/reportes pueden depender de indices reales; revisar rendimiento antes de cargas grandes.
- `users.active` e `users.is_active` deben tratarse con cuidado porque el proyecto historicamente convivio con ambos campos.
- `promesas_pago.tipo` conserva valores legacy; la cuota balon se representa con `promesa_cuotas.es_balon`.
- Las plantillas DOCX y conversion PDF dependen de archivos en storage y configuracion externa.
- Reportes e integraciones todavia tienen deuda de ordenamiento backend, aunque funcionan y tienen cobertura inicial.
- Los permisos deben seguir pasando por `Roles`, Policies y Gates para evitar validaciones duplicadas.
