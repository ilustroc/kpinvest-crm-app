# 12 - Checklist Deploy V3

## Formato del checklist

- [+] Confirmado.
- [ ] Pendiente.
- [!] Riesgo o requiere revision.

## Mapa de fases V3

- [+] Fase 1 - Entorno local seguro.
- [+] Fase 2 - Arquitectura MVC modular por dominios.
- [+] Fase 3 - Tailwind CSS v4 con Vite.
- [+] Fase 4 - Migracion inicial Bootstrap -> Tailwind.
- [+] Fase 5 - Baseline de migraciones + limpieza de roles.
- [+] Fase 6 - Pruebas funcionales reales con cobertura inicial completada.
- [+] Fase 7 - Arquitectura frontend V3 + migracion Tailwind iniciada.
- [ ] Fase 8 - Deploy controlado.

## 1. Rama y codigo

- [+] Confirmar que el trabajo esta en rama V3.
- [+] Confirmar que `main` no fue modificado directamente.
- [+] Confirmar que el PR o merge contiene solo cambios esperados.
- [+] Revisar que no haya archivos temporales, logs o dumps sensibles versionados.
- [+] Revisar que `.env` no este incluido en commits.
- [+] Revisar `git diff` completo antes de merge.

## 2. Base de datos local

- [+] Dump descargado desde produccion.
- [+] Base de datos importada en local.
- [+] Proyecto funcionando en localhost.
- [+] `.env` local apunta a base local.
- [+] Produccion no sera modificada en esta fase.
- [+] Validar flujos actuales contra la base local.
- [+] Errores locales documentados.
- [+] Documentar diferencias detectadas entre SQL, modelos y migraciones.

## 3. Arquitectura

- [+] Arquitectura V3 definida como MVC modular por dominios.
- [+] Se mantendra Laravel.
- [+] Se mantendra Blade.
- [+] Se usaran Services.
- [+] Se usaran Actions donde aplique.
- [+] Se usaran ViewModels para vistas complejas.
- [+] Se usaran componentes Blade.
- [+] Separar rutas por modulo.
- [+] Crear estructura de carpetas por dominios.
- [+] Crear servicio piloto de bajo riesgo.
- [+] Crear ViewModel piloto.
- [+] Definir Policies/Gates por modulo critico.
- [+] Revisar permisos actuales.
- [+] Crear documentacion inicial de permisos.
- [!] No mover controladores criticos sin pruebas o validacion funcional.

## 4. Frontend V3

- [+] Se define Tailwind CSS v4 como frontend objetivo.
- [+] Se usara Vite.
- [+] Bootstrap sera eliminado progresivamente.
- [+] Instalar Tailwind CSS v4.
- [+] Instalar `@tailwindcss/vite`.
- [+] Configurar `vite.config.js`.
- [+] Configurar `resources/css/app.css`.
- [+] Revisar `resources/js/app.js`.
- [+] Ejecutar `npm run build`.
- [!] Eliminar Bootstrap del layout principal: parcial, se mantiene condicional para vistas legacy.
- [+] Reemplazar componentes Bootstrap por Tailwind en pantalla piloto.
- [+] Migrar segunda pantalla piloto a Tailwind.
- [+] Usar JS modular con Vite en pantalla migrada.
- [!] Vulnerabilidades moderadas de Vite/esbuild pendientes por posible breaking change.
- [!] No eliminar Bootstrap globalmente antes de migrar modales/dropdowns dependientes.

## 5. Checklist de instalacion Tailwind CSS v4

- [+] Revisar `package.json`.
- [+] Instalar Tailwind CSS v4.
- [+] Instalar `@tailwindcss/vite`.
- [+] Configurar `vite.config.js`.
- [+] Actualizar `resources/css/app.css`.
- [+] Confirmar `@vite` en layout.
- [+] Ejecutar `npm run dev`.
- [+] Ejecutar `npm run build`.
- [+] Confirmar `public/build/manifest.json`.
- [+] Confirmar que el layout carga CSS por Vite.
- [+] Confirmar que el layout carga JS por Vite.
- [!] Retirar Bootstrap CDN: retirado solo para vistas con `tailwind_only`.
- [!] Revisar errores en consola: pendiente validacion manual de navegador.
- [+] Migrar primera pantalla piloto.

## 6. Bootstrap -> Tailwind

- [!] Migrar layout principal: Vite activo y Bootstrap condicional para legacy.
- [+] Migrar sidebar/topbar base.
- [+] Migrar botones base.
- [+] Migrar badges base.
- [+] Migrar formularios simples base.
- [+] Migrar tablas simples base.
- [+] Migrar administracion de usuarios.
- [+] Crear modal Tailwind reutilizable.
- [+] Crear componentes adicionales: textarea, date, dropdown, empty-state, confirm-dialog.
- [+] Migrar dashboard.
- [+] JS modular usado en Dashboard.
- [+] Migrar reportes.
- [ ] Migrar clientes.
- [ ] Migrar CNA.
- [ ] Migrar promesas.
- [!] CNA y Promesas deben quedar al final por criticidad.
- [!] Clientes debe migrarse solo cuando exista validacion funcional suficiente.

## 6.1 Arquitectura frontend V3

- [+] Crear `docs/19_arquitectura_frontend_v3.md`.
- [+] Crear `docs/20_inventario_frontend_legacy.md`.
- [+] Crear estructura `resources/views/components/forms`.
- [+] Crear estructura `resources/views/components/tables`.
- [+] Crear estructura `resources/views/components/feedback`.
- [+] Crear estructura `resources/views/components/reportes`.
- [+] Crear estructura futura `resources/views/pages/*`.
- [+] Crear `resources/js/core`.
- [+] Crear modulos `resources/js/modules/reportes`.
- [+] Migrar JS de reportes desde `public/js/reportes` hacia Vite.
- [+] Migrar CSS de reportes desde `public/css/reportes` hacia Tailwind/componentes.
- [+] Reporte de pagos sin Bootstrap en layout.
- [+] Reporte de promesas sin Bootstrap en layout.
- [+] Reporte CNA sin Bootstrap en layout.
- [!] Archivos legacy de reportes en `public/` quedan pendientes de eliminacion tras validacion visual.
- [!] Integraciones, Clientes y Autorizacion siguen como legacy.

## 7. Migraciones

- [+] Migraciones antiguas revisadas.
- [+] Migraciones antiguas eliminadas.
- [+] Nuevas migraciones generadas desde `u480021566_kpinvest_bd.sql`.
- [+] Revisar migraciones nuevas una por una.
- [+] Confirmar que no eliminan datos sin respaldo en base limpia.
- [+] Confirmar que no eliminan columnas usadas por codigo actual en baseline limpio.
- [+] Ejecutar migraciones solo sobre base local limpia desechable.
- [+] `php artisan migrate` ejecutado en base local limpia `kpinvest_v3_migrate_test`.
- [+] Ejecutar `php artisan migrate:status` en local.
- [+] Comparar conceptualmente SQL/base local vs migraciones disponibles.
- [+] Confirmar indices y claves foraneas.
- [+] Probar rollback si aplica.
- [+] Confirmar si se requieren seeders: no se requirieron en esta fase.
- [ ] Si se requieren seeders, confirmar que son idempotentes.
- [+] Baseline V3 creado.
- [+] Migracion de limpieza de roles creada.
- [!] En la base local principal las migraciones V3 aparecen pendientes porque el baseline no debe correrse sobre tablas existentes.
- [!] Diferencia SQL vs migraciones documentada: `users.role` elimina `sistemas` y `usuario`.
- [!] Migraciones historicas incompletas frente a la base real fueron reemplazadas, pero produccion requiere estrategia de baseline antes de ejecutar cualquier cambio.
- [!] No correr `php artisan migrate` en produccion todavia.
- [!] No correr `php artisan migrate:fresh` sobre bases con informacion.
- [!] No correr seeders en produccion sin revision.

## 8. Pruebas funcionales

- [+] `php artisan route:list --except-vendor`.
- [+] `php artisan view:clear`.
- [+] `php artisan cache:clear`.
- [+] `php artisan view:cache`.
- [+] `php -l` en archivos PHP nuevos/modificados.
- [+] `php artisan test`.
- [+] Login correcto: validada redireccion de invitado y pagina login.
- [+] Login bloqueado para usuario inactivo.
- [+] Busqueda rapida de cliente.
- [+] Vista de cliente carga cuentas.
- [+] Vista de cliente carga pagos.
- [+] Vista de cliente carga promesas.
- [+] Vista de cliente carga CNA.
- [+] Crear promesa de cancelacion.
- [+] Crear promesa de convenio.
- [+] Crear promesa con cuota balon.
- [+] Preaprobar promesa como supervisor.
- [+] Aprobar promesa como administrador.
- [+] Rechazar promesa como supervisor.
- [+] Rechazar promesa como administrador.
- [+] Crear CNA.
- [+] Preaprobar CNA.
- [+] Aprobar CNA.
- [+] Generar/descargar DOCX CNA.
- [+] Generar/descargar PDF CNA o validar fallback.
- [+] Generar acuerdo de promesa.
- [+] Importar pagos CSV.
- [+] Importar data maestra CSV.
- [+] Importar asignaciones CSV.
- [+] Importar CCD CSV.
- [+] Reporte de pagos.
- [+] Export reporte de pagos.
- [+] Reporte de promesas.
- [+] Export reporte de promesas.
- [+] Reporte CNA.
- [+] Export reporte CNA.
- [+] Administracion de usuarios.
- [+] Activar/desactivar usuarios.
- [+] Cambiar contrasena.
- [+] Dashboard.
- [+] Pantallas de importacion principales renderizan.
- [+] POST principales, exports e imports CSV validados con tests Feature y datos locales transaccionales.
- [!] Pruebas manuales en navegador real aun pendientes.

## 9. Assets y build

- [+] Ejecutar `npm install` si cambiaron dependencias.
- [+] Ejecutar `npm run build`.
- [+] Confirmar `public/build/manifest.json`.
- [+] Confirmar que las vistas migradas cargan CSS por Vite.
- [+] Confirmar que las vistas migradas cargan JS por Vite.
- [!] Confirmar que no hay errores en consola del navegador: pendiente manual.
- [!] Confirmar que modales funcionan: requiere prueba manual de acciones.
- [+] Confirmar que filtros AJAX funcionan en reportes mediante Feature tests.
- [+] Confirmar que paginacion AJAX funciona en reportes mediante Feature tests.
- [ ] Confirmar desktop.
- [ ] Confirmar mobile si aplica.

## 10. Configuracion de produccion

- [ ] Confirmar `APP_ENV=production`.
- [ ] Confirmar `APP_DEBUG=false`.
- [ ] Confirmar `APP_URL` correcto.
- [ ] Confirmar conexion DB productiva correcta.
- [ ] Confirmar credenciales SMTP.
- [ ] Confirmar claves iLovePDF si se usa conversion PDF.
- [ ] Confirmar `QUEUE_CONNECTION` esperado.
- [ ] Confirmar permisos de `storage/`.
- [ ] Confirmar permisos de `bootstrap/cache/`.
- [ ] Confirmar enlace storage si aplica.
- [ ] Confirmar cache config/rutas si se usa.

## 11. Plantillas y documentos

- [ ] Confirmar existencia de `storage/app/templates/Acuerdo_de_Pago_DNI_{dni}.docx`.
- [ ] Confirmar existencia de plantilla CNA KP Invest.
- [ ] Confirmar existencia de plantilla CNA Fondo Acreencia Arequipa.
- [ ] Confirmar existencia de plantilla CNA Acreencia II.
- [ ] Probar generacion DOCX.
- [ ] Probar conversion PDF.
- [ ] Validar fallback si iLovePDF falla.

## 12. Usuarios, roles y permisos

- [+] Roles finales definidos: administrador, supervisor, asesor, soporte.
- [+] Roles `sistemas` y `usuario` eliminados del codigo como roles validos.
- [+] Selects y validaciones de roles actualizados.
- [+] Gates/Policies actualizados.
- [+] Migracion `2026_05_15_000007_normalize_user_roles_v3.php` creada para convertir `sistemas`/`usuario` a `soporte`.
- [+] Confirmar roles existentes en base local: administrador, supervisor, asesor, soporte.
- [+] Confirmar usuarios administradores activos.
- [+] Confirmar que al menos un administrador queda activo en reglas de servicio.
- [+] Confirmar visibilidad de supervisor sobre su equipo en servicio/policy.
- [+] Confirmar restricciones para asesor con prueba automatizada.
- [+] Confirmar restricciones para soporte en administracion.
- [+] Confirmar acceso a integraciones por Gate.
- [+] Confirmar acceso a reportes por Gate.
- [+] Confirmar acceso a autorizaciones para administrador/supervisor.

## 13. Logs y monitoreo

- [ ] Revisar `storage/logs` en local/staging despues de pruebas.
- [ ] Confirmar que no hay errores nuevos.
- [ ] Confirmar que errores de correo se registran correctamente.
- [ ] Confirmar que errores de iLovePDF se registran correctamente.
- [ ] Confirmar que imports reportan errores utiles.
- [ ] Preparar monitoreo post-deploy.

## 14. Rollback

- [ ] Definir plan para volver al commit anterior.
- [ ] Definir plan para restaurar backup de base de datos.
- [ ] Confirmar responsable del rollback.
- [ ] Confirmar ventana de tiempo aceptable.
- [+] Confirmar que migraciones nuevas tienen `down` funcional en base desechable.
- [ ] Definir plan manual de rollback para produccion.
- [ ] Confirmar que assets anteriores pueden restaurarse.

## 15. Antes de ejecutar en produccion

- [ ] El equipo reviso y aprobo el cambio.
- [ ] Se descargo dump actualizado de produccion.
- [ ] Se probo sobre dump reciente.
- [ ] Se aprobo checklist funcional.
- [ ] Se aprobo checklist de assets.
- [ ] Se aprobo checklist de configuracion.
- [ ] Existe backup reciente.
- [ ] Existe rollback.
- [ ] Se definio ventana de deploy.
- [!] Si falla una prueba local sobre copia reciente, no desplegar.

## 16. Despues del deploy

- [ ] Limpiar caches si corresponde.
- [ ] Verificar login.
- [ ] Verificar panel.
- [ ] Verificar cliente real de prueba.
- [ ] Verificar promesas.
- [ ] Verificar CNA.
- [ ] Verificar pagos.
- [ ] Verificar reportes.
- [ ] Verificar logs.
- [ ] Confirmar con usuarios clave.

## Regla final

Si una migracion, build o prueba funcional falla sobre una copia reciente de produccion, no se debe ejecutar el cambio en produccion real.
