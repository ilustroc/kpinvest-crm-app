# 12 - Checklist Deploy V3

## Objetivo

Este checklist debe revisarse antes de pasar cualquier cambio V3 a produccion. No reemplaza una estrategia de deploy formal, pero reduce el riesgo de romper el CRM productivo.

## 1. Rama y codigo

- [+] Confirmar que el trabajo esta en `v3/analisis-documentacion` o rama V3 aprobada.
- [+] Confirmar que `main` no fue modificado directamente.
- [ ] Confirmar que el PR o merge contiene solo cambios esperados.
- [ ] Revisar que no haya archivos temporales, logs o dumps sensibles versionados.
- [ ] Revisar que `.env` no este incluido en commits.
- [ ] Revisar `git diff` completo antes de merge.

## 2. Backup y dump reciente

- [ ] Confirmar backup reciente de produccion.
- [ ] Confirmar que el backup incluye datos y estructura.
- [ ] Descargar dump actualizado de produccion.
- [ ] Guardar el dump en ubicacion segura.
- [ ] Importar el dump en local o staging.
- [ ] Confirmar que local/staging apunta a base de prueba, no a produccion.

## 3. Migraciones

- [ ] Revisar migraciones nuevas una por una.
- [ ] Confirmar que no eliminan datos sin respaldo.
- [ ] Confirmar que no eliminan columnas usadas por codigo actual.
- [ ] Ejecutar migraciones sobre copia local reciente.
- [ ] Ejecutar `php artisan migrate:status` en local/staging.
- [ ] Comparar estructura antes/despues.
- [ ] Confirmar indices y claves foraneas.
- [ ] Probar rollback si aplica.
- [ ] Confirmar si se requieren seeders.
- [ ] Si se requieren seeders, confirmar que son idempotentes.

## 4. Pruebas funcionales

- [ ] Login correcto.
- [ ] Login bloqueado para usuario inactivo.
- [ ] Busqueda rapida de cliente.
- [ ] Vista de cliente carga cuentas.
- [ ] Vista de cliente carga pagos.
- [ ] Vista de cliente carga promesas.
- [ ] Vista de cliente carga CNA.
- [ ] Crear promesa de cancelacion.
- [ ] Crear promesa de convenio.
- [ ] Crear promesa con cuota balon.
- [ ] Preaprobar promesa como supervisor.
- [ ] Aprobar promesa como administrador.
- [ ] Rechazar promesa como supervisor.
- [ ] Rechazar promesa como administrador.
- [ ] Crear CNA.
- [ ] Preaprobar CNA.
- [ ] Aprobar CNA.
- [ ] Generar/descargar DOCX CNA.
- [ ] Generar/descargar PDF CNA o validar fallback.
- [ ] Generar acuerdo de promesa.
- [ ] Importar pagos CSV.
- [ ] Importar data maestra CSV.
- [ ] Importar asignaciones CSV.
- [ ] Importar CCD CSV.
- [ ] Reporte de pagos.
- [ ] Export reporte de pagos.
- [ ] Reporte de promesas.
- [ ] Export reporte de promesas.
- [ ] Reporte CNA.
- [ ] Export reporte CNA.
- [ ] Administracion de usuarios.
- [ ] Activar/desactivar usuarios.
- [ ] Cambiar contrasena.

## 5. Frontend y assets

- [ ] Ejecutar `npm install` si cambiaron dependencias.
- [ ] Ejecutar `npm run build`.
- [ ] Confirmar que existe `public/build/manifest.json`.
- [ ] Confirmar que las vistas cargan CSS.
- [ ] Confirmar que las vistas cargan JS.
- [ ] Confirmar que no hay errores en consola del navegador.
- [ ] Confirmar que modales funcionan.
- [ ] Confirmar que filtros AJAX funcionan.
- [ ] Confirmar que paginacion AJAX funciona.
- [ ] Confirmar que layout funciona en desktop.
- [ ] Confirmar que layout funciona en resolucion movil si aplica.

## 6. Configuracion de produccion

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

## 7. Plantillas y documentos

- [ ] Confirmar existencia de `storage/app/templates/Acuerdo_de_Pago_DNI_{dni}.docx`.
- [ ] Confirmar existencia de plantilla CNA KP Invest.
- [ ] Confirmar existencia de plantilla CNA Fondo Acreencia Arequipa.
- [ ] Confirmar existencia de plantilla CNA Acreencia II.
- [ ] Probar generacion DOCX.
- [ ] Probar conversion PDF.
- [ ] Validar fallback si iLovePDF falla.

## 8. Usuarios, roles y permisos

- [ ] Confirmar roles existentes: administrador, supervisor, asesor, sistemas, soporte, usuario.
- [ ] Confirmar usuarios administradores activos.
- [ ] Confirmar que al menos un administrador queda activo.
- [ ] Confirmar visibilidad de supervisor sobre su equipo.
- [ ] Confirmar restricciones para asesor.
- [ ] Confirmar restricciones para soporte.
- [ ] Confirmar acceso a integraciones.
- [ ] Confirmar acceso a reportes.
- [ ] Confirmar acceso a autorizaciones.

## 9. Logs y monitoreo

- [ ] Revisar `storage/logs` en local/staging despues de pruebas.
- [ ] Confirmar que no hay errores nuevos.
- [ ] Confirmar que errores de correo se registran correctamente.
- [ ] Confirmar que errores de iLovePDF se registran correctamente.
- [ ] Confirmar que imports reportan errores utiles.
- [ ] Preparar monitoreo post-deploy.

## 10. Rollback

- [ ] Definir plan para volver al commit anterior.
- [ ] Definir plan para restaurar backup de base de datos.
- [ ] Confirmar responsable del rollback.
- [ ] Confirmar ventana de tiempo aceptable.
- [ ] Confirmar que migraciones nuevas tienen `down` seguro o plan manual.
- [ ] Confirmar que assets anteriores pueden restaurarse.

## 11. Antes de ejecutar en produccion

- [ ] El equipo reviso y aprobo el cambio.
- [ ] Se probo sobre dump reciente.
- [ ] Se aprobo checklist funcional.
- [ ] Se aprobo checklist de assets.
- [ ] Se aprobo checklist de configuracion.
- [ ] Existe backup reciente.
- [ ] Existe rollback.
- [ ] Se definio ventana de deploy.

## 12. Despues del deploy

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

