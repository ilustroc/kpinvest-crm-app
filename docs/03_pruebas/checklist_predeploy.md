# Checklist predeploy

## Codigo

- [ ] Confirmar rama `v3/analisis-documentacion`.
- [ ] Revisar `git status`.
- [ ] Revisar `git diff`.
- [ ] Confirmar que no hay `.env`, logs ni dumps sensibles versionados.
- [ ] Confirmar que no hay cambios directos en `main`.

## Base de datos

- [ ] Descargar dump productivo reciente.
- [ ] Importar dump en base local o staging desechable.
- [ ] Marcar o validar baseline sin destruir datos.
- [ ] Probar migraciones necesarias sobre esa copia.
- [ ] Probar rollback o plan manual.

## Funcional

- [ ] Login y logout.
- [ ] Dashboard y panel.
- [ ] Cliente real de prueba.
- [ ] Promesas.
- [ ] CNA.
- [ ] Autorizacion.
- [ ] Reportes.
- [ ] Integraciones CSV.
- [ ] Admin usuarios.

## Assets

- [ ] `npm run build`.
- [ ] `public/build/manifest.json`.
- [ ] Layout carga CSS/JS por Vite.
- [ ] Navegador sin errores de consola.

## Configuracion

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` correcto.
- [ ] DB productiva correcta.
- [ ] SMTP.
- [ ] Storage y cache con permisos.
- [ ] Plantillas DOCX.
- [ ] Credenciales externas si aplican.
