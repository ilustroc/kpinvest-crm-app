# 09. Checklist de validación funcional

> **Estado: propuesta. No ejecutada.**
>
> Se ejecuta solo sobre `kp_crm_v3_validation` con datos enmascarados y
> credenciales de staging. No se usan datos reales ni se conecta a producción.

## 1. Objetivo

Confirmar que V3 conserva los flujos críticos de V1/IMPULSE antes de autorizar
un cutover y antes de reanudar el rediseño Meridian.

## 2. Precondiciones de entorno

- [ ] `APP_ENV=staging`.
- [ ] `APP_DEBUG=false`.
- [ ] `DB_DATABASE=kp_crm_v3_validation`.
- [ ] La app no puede conectarse a `u480021566_kpinvest_bd`,
      `kpinvest_v3_migrate_test`, `kp_crm_local` ni producción.
- [ ] `MAIL_MAILER=log`; no hay SMTP real.
- [ ] `QUEUE_CONNECTION=sync`; no hay workers externos.
- [ ] `BROADCAST_CONNECTION=log`; no hay WebSocket externo.
- [ ] Storage de staging montado; no hay documentos reales.
- [ ] Plantillas placeholder disponibles:
      `cna_kpinvest.docx`, `cna_fondo_acreencia_arequipa.docx`,
      `cna_fondo_acreencia_arequipa_2.docx` y
      `Acuerdo_de_Pago_DNI_{dni}.docx`.
- [ ] `ILOVEPDF_PUBLIC` e `ILOVEPDF_SECRET` vacíos.
- [ ] Assets compilados (`npm run build`) y `public/build/manifest.json`.
- [ ] Cuatro credenciales de staging, una por rol, fuera del repositorio.
- [ ] Base enmascarada validada: 0 PII residual.
- [ ] Logs sin payload sensible.
- [ ] Navegador con consola limpia.

## 3. Roles

Roles válidos: `administrador`, `supervisor`, `asesor`, `soporte`.
Roles antiguos `sistemas` y `usuario` no son válidos.

| Prueba | Administrador | Supervisor | Asesor | Soporte |
|---|---|---|---|---|
| Login | Sí | Sí | Sí | Sí |
| Dashboard | Sí | Sí | Sí | Sí |
| Clientes | Sí | Sí | Sí | Sí |
| Crear promesa | Según regla | Según regla | Según regla | Según regla |
| Preaprobar/rechazar | Sí | Sí | No | No |
| Aprobar/rechazar final | Sí | No | No | No |
| CNA | Sí | Sí | Sí | Según regla |
| Autorización | Sí | Sí | No | No |
| Reportes | Sí | Según regla | Según regla | Según regla |
| Integraciones | Sí | No | No | No |
| Admin usuarios | Sí | No | No | No |
| Usuario inactivo | Bloqueado | Bloqueado | Bloqueado | Bloqueado |

- [ ] Un usuario con rol antiguo no puede iniciar sesión ni aparecer en selects.
- [ ] Los 5 casos `active <> is_active` se comportan como en V1; no se
      "corrigen" en la UI.
- [ ] Un asesor no puede aprobar su propia promesa/CNA.
- [ ] Un supervisor solo ve su equipo.
- [ ] Soporte no aprueba workflow.

## 4. Autenticación y sesión

- [ ] Login válido con cada rol.
- [ ] Login inválido muestra error sin revelar si el usuario existe.
- [ ] Logout invalida la sesión.
- [ ] Sesión expirada redirige al login.
- [ ] CSRF activo en formularios POST.
- [ ] Usuario inactivo no accede.
- [ ] Recuperación de contraseña usa log, no correo real.
- [ ] No se muestran contraseñas ni tokens en pantalla.

## 5. Dashboard y navegación

- [ ] Dashboard carga sin errores.
- [ ] Tarjetas/contadores no fallan con datos enmascarados.
- [ ] Menú muestra solo opciones permitidas por rol.
- [ ] Enlaces principales no dan 404.
- [ ] Layout desktop y móvil sin desbordes.
- [ ] Consola del navegador sin errores.

## 6. Clientes

### 6.1 Búsqueda

- [ ] Búsqueda por DNI enmascarado de 8 dígitos.
- [ ] Búsqueda por DNI enmascarado de 9 y 11 dígitos.
- [ ] Búsqueda por operación enmascarada.
- [ ] Búsqueda por nombre token (`CLIENTE_...`).
- [ ] Búsqueda inexistente muestra "Cliente no ubicado".
- [ ] Búsqueda vacía muestra el mensaje esperado.
- [ ] No se puede buscar por DNI real (no existe en la base).

### 6.2 Perfil

- [ ] Perfil carga con nombre token, sin PII.
- [ ] Cuentas ordenadas y paginadas.
- [ ] Deudas, intereses y totales coinciden con la base.
- [ ] Pagos listados y paginados.
- [ ] Promesas asociadas.
- [ ] CNA asociadas.
- [ ] CCD asociado.
- [ ] Bloqueos asociados.
- [ ] Enlaces de descarga no apuntan a archivos reales.
- [ ] Cliente con múltiples cuentas no duplica filas.
- [ ] Cliente con 5 claves duplicadas de auditoría no rompe la vista.

### 6.3 Pagos

- [ ] Eliminar un pago de staging pide confirmación.
- [ ] El pago se elimina y el total se recalcula.
- [ ] El registro eliminado no reaparece.
- [ ] Un usuario sin permiso no puede eliminar.
- [ ] La acción queda en log sin PII.

## 7. Promesas

Tipos válidos: `convenio`, `convenio_balon`, `cancelacion`.

### 7.1 Creación

- [ ] Crear `cancelacion` con fecha y monto.
- [ ] Crear `convenio` con cuotas.
- [ ] Crear `convenio_balon` con cuota balón.
- [ ] La suma del cronograma debe igualar `monto_convenio`.
- [ ] La cantidad de cuotas debe igualar `nro_cuotas`.
- [ ] La cuota balón debe estar en rango.
- [ ] Operaciones deben existir y no estar vacías.
- [ ] Teléfono enmascarado se guarda sin caracteres inválidos.
- [ ] Monto cero o negativo se rechaza.
- [ ] Fecha inválida se rechaza sin corromper datos.
- [ ] No se puede crear una promesa duplicada por doble clic.

### 7.2 Workflow

Estados: `pendiente`, `preaprobada`, `aprobada`, `rechazada_sup`,
`rechazada`.

- [ ] Asesor crea -> `pendiente`.
- [ ] Supervisor preaprueba -> `preaprobada`.
- [ ] Administrador aprueba -> `aprobada`.
- [ ] Supervisor rechaza -> `rechazada_sup`.
- [ ] Administrador rechaza -> `rechazada`.
- [ ] No se puede preaprobar una promesa que no está pendiente.
- [ ] No se puede aprobar una que no está preaprobada.
- [ ] Los aprobadores y fechas se registran.
- [ ] Las notas se guardan sin PII.
- [ ] La bandeja de autorización muestra solo lo que corresponde al rol.
- [ ] El correo se escribe en log, no se envía.
- [ ] La notificación interna se crea.
- [ ] El cronograma se conserva tras aprobar/rechazar.

### 7.3 Casos de datos

- [ ] Promesa con `monto=0` se muestra sin crash (708 casos).
- [ ] Promesa con fecha anterior a 2000 se muestra sin crash.
- [ ] Promesa duplicada de auditoría no se borra automáticamente.

## 8. CNA

### 8.1 Creación

- [ ] Crear CNA con operaciones válidas.
- [ ] `operaciones` se guarda como JSON de array.
- [ ] No se puede crear con operaciones vacías.
- [ ] Monto y fecha son obligatorios.
- [ ] Titular token, DNI enmascarado y notas sintéticas.
- [ ] `nro_carta` se genera con el formato esperado.
- [ ] Series `KPI`, `F` y `F2` se generan según cosecha/entidad.
- [ ] Dos creaciones concurrentes no repiten número.
- [ ] La solicitud queda `pendiente`.

### 8.2 Workflow

- [ ] Asesor crea -> `pendiente`.
- [ ] Supervisor preaprueba -> `preaprobada`.
- [ ] Administrador aprueba -> `aprobada`.
- [ ] Supervisor rechaza -> `rechazada_sup`.
- [ ] Administrador rechaza -> `rechazada`.
- [ ] No se aprueba una CNA no preaprobada.
- [ ] Se registran aprobadores/fechas.
- [ ] La nota se agrega sin datos reales.
- [ ] La bandeja de autorización respeta rol y equipo.
- [ ] El correo se escribe en log.

### 8.3 Documentos

- [ ] Al aprobar, se genera DOCX/PDF placeholder.
- [ ] La ruta guardada es relativa y sintética.
- [ ] La descarga devuelve un archivo válido.
- [ ] Si falta plantilla, se conserva el fallback documentado.
- [ ] No se descarga ningún documento real.
- [ ] No se generan rutas con DNI/nombre real.

## 9. Autorización

- [ ] Bandeja de promesas pendientes/preaprobadas.
- [ ] Bandeja de CNA pendientes/preaprobadas.
- [ ] Búsqueda por DNI/nro/producto/observación enmascarados.
- [ ] Filtro por estado.
- [ ] Supervisor solo ve su equipo.
- [ ] Administrador ve lo que corresponde.
- [ ] Acciones de aprobar/rechazar funcionan.
- [ ] No hay acciones duplicadas por doble clic.
- [ ] Paginación conserva filtros.

## 10. Reportes y exports

- [ ] Reporte de pagos.
- [ ] Reporte de promesas.
- [ ] Reporte de clientes/cuentas.
- [ ] Filtros por fecha, entidad, estado y rol.
- [ ] Totales coinciden con la base.
- [ ] Export CSV/Excel descarga sin PII real.
- [ ] Nombres de archivo no contienen datos personales.
- [ ] Paginación y orden con `NULL`.
- [ ] Consultas con `GROUP BY` funcionan con `ONLY_FULL_GROUP_BY`.
- [ ] No se rompe con fechas inválidas o montos cero.

## 11. Integraciones CSV

- [ ] Importar data maestra con CSV de staging.
- [ ] Importar asignaciones.
- [ ] Importar CCD.
- [ ] Importar pagos.
- [ ] Archivo vacío muestra error controlado.
- [ ] Encabezados inválidos muestran error controlado.
- [ ] Fila inválida no corrompe el resto.
- [ ] Conteos de importación coinciden.
- [ ] No se importan datos reales.
- [ ] Logs no contienen filas ni PII.
- [ ] Un usuario sin permiso no accede a integraciones.

## 12. Administración de usuarios

- [ ] Listar usuarios.
- [ ] Crear usuario de staging con email `@example.invalid`.
- [ ] Editar nombre/email/rol/supervisor.
- [ ] Activar/desactivar.
- [ ] Roles antiguos no aparecen en el select.
- [ ] No se puede asignar un rol inválido.
- [ ] Supervisor ve solo su equipo.
- [ ] No se muestran contraseñas.
- [ ] No se envía correo real.

## 13. Notificaciones

- [ ] Generar notificación de workflow.
- [ ] Se guarda en `notifications`.
- [ ] `data` no contiene PII real.
- [ ] Marcar como leída.
- [ ] Contador/bandeja se actualiza.
- [ ] Realtime desactivado o local; no hay servicio externo.
- [ ] No se envía PII a Pusher/Reberb/otro.
- [ ] La notificación sobrevive al refresh.

## 14. Documentos y storage

- [ ] Plantillas placeholder se leen.
- [ ] DOCX generado abre.
- [ ] PDF generado abre.
- [ ] Acuerdo de promesa se genera o cae al fallback DOCX.
- [ ] Confirmar el nombre real de `Acuerdo_de_Pago_DNI_{dni}.docx` y si el
      código reemplaza `{dni}` o espera un archivo literal.
- [ ] iLovePDF no se invoca; no hay llaves configuradas.
- [ ] CCD PDF/link placeholder abre.
- [ ] Ruta relativa, sin `http` y sin ruta absoluta (`/...` o `C:\...`).
- [ ] No hay archivos reales en `storage/app`.
- [ ] No se mezcla storage de V1 y V3.
- [ ] El fallback funciona si falta una plantilla.
- [ ] No se registran rutas reales ni DNI reales en logs.

## 15. Casos de datos conocidos

| Caso | Esperado |
|---|---|
| 5 claves de cuenta duplicadas | Se muestran; no se deduplican |
| 729 claves de pago duplicadas | Se reportan; no se borran |
| 26 claves de promesa duplicadas | Se reportan; no se borran |
| 13,824 grupos CCD | Se muestran como varios documentos |
| 3 promesas y 5 pagos con fecha < 2000 | Se muestran; no se corrigen |
| 708 promesas con monto 0 | Se muestran; no se corrigen |
| 5 usuarios `active <> is_active` | Se comportan como V1 |
| 216 lotes totales; 29 inconsistentes (23 con pagos, 6 sin pagos) | Se reportan; no se corrigen |
| 0 roles antiguos en la copia | Si aparecen en dump fresco, se detiene |

- [ ] Ningún caso conocido se "arregla" automáticamente.
- [ ] Los conteos de la UI coinciden con la base.
- [ ] Los reportes no ocultan filas problemáticas.

## 16. Rendimiento y UX

- [ ] Búsqueda en 229k cuentas responde en tiempo aceptable.
- [ ] CCD con 222k filas pagina sin bloquear.
- [ ] Reportes con filtros no agotan memoria.
- [ ] Creación de CNA no bloquea la tabla completa de forma excesiva.
- [ ] Layout responsive en desktop y móvil.
- [ ] Formularios muestran errores claros.
- [ ] Modales de confirmación funcionan.
- [ ] No hay errores de consola.
- [ ] No hay recursos 404.

## 17. Seguridad y PII

- [ ] No hay nombres, DNI, correos, teléfonos, direcciones ni contraseñas
      reales en pantalla.
- [ ] No hay PII en logs de Laravel, Apache o navegador.
- [ ] No hay PII en exports, reportes ni nombres de archivo.
- [ ] No hay PII en capturas compartidas.
- [ ] No se enviaron datos a servicios externos.
- [ ] No se usaron agentes de IA con filas o dumps.
- [ ] La base de staging no es accesible desde la red.
- [ ] Las credenciales están fuera del repositorio.
- [ ] El storage no contiene documentos reales.

## 18. Pruebas técnicas

- [ ] `php artisan route:list --except-vendor`.
- [ ] `php artisan view:clear`.
- [ ] `php artisan cache:clear`.
- [ ] `php artisan view:cache`.
- [ ] `php artisan test` (sin `RefreshDatabase` sobre datos valiosos).
- [ ] `npm run build`.
- [ ] `git diff --check`.
- [ ] Logs revisados.
- [ ] Consola del navegador revisada.

## 19. Evidencia

Cada prueba debe registrar:

- ID y fecha;
- base y entorno;
- rol usado (sin credenciales);
- resultado;
- evidencia agregada o captura sin PII;
- defecto asociado, si aplica.

No adjuntar filas, dumps, nombres, DNI, correos, teléfonos, direcciones ni
rutas reales.

## 20. Criterios de salida

- [ ] 0 defectos P0/P1 abiertos.
- [ ] P2 conocidos con aceptación del responsable funcional.
- [ ] Todos los flujos críticos aprobados.
- [ ] Conteos y sumas conciliados.
- [ ] 0 PII residual.
- [ ] Rollback ensayado.
- [ ] Roles decididos y documentados.
- [ ] Respaldo fresco validado.
- [ ] Evidencia firmada por técnico y funcional.
- [ ] Recién entonces se autoriza el cutover y se reanuda Meridian.

## 21. Limitaciones de esta validación

- Usa datos enmascarados, no producción.
- No prueba documentos reales; usa placeholders.
- No prueba la integración real con IMPULSE hasta que se inventaríe.
- No sustituye la validación sobre el dump fresco.
- No autoriza el cutover por sí sola.
