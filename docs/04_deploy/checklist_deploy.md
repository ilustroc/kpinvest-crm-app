# Checklist deploy V3

## Regla final

Si una migracion, build o prueba funcional falla sobre una copia reciente de produccion, no se debe ejecutar el cambio en produccion real.

## Estado previo

- [+] Frontend V3 cerrado tecnicamente.
- [+] Backend critico cerrado tecnicamente.
- [+] Tests automatizados pasan en local.
- [+] Build Vite pasa en local.
- [+] Baseline de migraciones probado en base limpia.
- [!] Pruebas manuales de navegador pendientes.
- [!] Deploy pendiente.

## Antes de deploy

- [ ] Equipo revisa y aprueba cambios.
- [ ] Dump productivo reciente descargado.
- [ ] Dump probado en entorno local/staging.
- [ ] Checklist funcional aprobado.
- [ ] Checklist assets aprobado.
- [ ] Checklist configuracion aprobado.
- [ ] Backup reciente confirmado.
- [ ] Rollback definido.
- [ ] Ventana de deploy definida.

## Produccion

- [ ] Confirmar `APP_ENV=production`.
- [ ] Confirmar `APP_DEBUG=false`.
- [ ] Confirmar `APP_URL`.
- [ ] Confirmar conexion DB.
- [ ] Confirmar SMTP.
- [ ] Confirmar storage/cache.
- [ ] Confirmar plantillas DOCX.
- [ ] Confirmar Vite build.
- [ ] Confirmar roles finales sin `sistemas` ni `usuario`.
- [ ] Confirmar que existe al menos un administrador activo.
- [ ] Confirmar logs limpios antes y despues del deploy.

## Plantillas y documentos

- [ ] Confirmar plantilla de acuerdo de pago.
- [ ] Confirmar plantillas CNA usadas por el negocio.
- [ ] Probar generacion DOCX.
- [ ] Probar conversion PDF o fallback.

## Despues del deploy

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
