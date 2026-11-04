# 01. Plan de desidentificación

> **Estado: propuesta. No ejecutada.**
>
> Este documento define cómo obtener una copia enmascarada de
> `u480021566_kpinvest_bd` sin modificar la original y sin exponer datos
> personales. La ejecución requiere aprobación G0 y un entorno aislado.

## 1. Objetivo

Construir una base de staging que conserve:

- las relaciones entre usuarios, clientes, cuentas, pagos, promesas, CNA,
  asignaciones, bloqueos y CCD;
- los importes, fechas, estados, roles, tipos y distribuciones necesarias para
  probar V3;
- los casos representativos y los defectos de datos conocidos.

Y que elimine o sustituya de forma determinística:

- nombres, DNI/RUC, correos, teléfonos, direcciones y contraseñas;
- números de cuenta y operación que permitan volver a la persona;
- rutas y nombres de archivos que contengan identificadores;
- texto libre y cargas de notificaciones que puedan contener PII.

No se usarán únicamente datos sintéticos como base principal. Los datos
sintéticos se limitan a credenciales de prueba, documentos placeholder y
fixtures de UI.

## 2. Principios

1. **La fuente no se toca.** `u480021566_kpinvest_bd` es de solo lectura.
2. **Determinismo.** La misma persona, cuenta u operación recibe el mismo
   token en todas las tablas.
3. **Formato preservado.** Un DNI de 8 dígitos sigue siendo de 8 dígitos; un
   RUC de 11 sigue siendo de 11; un teléfono conserva su clase de longitud.
4. **Sin PII en el resultado.** No se limita a "ofuscar"; se sustituye.
5. **Trazabilidad sin datos crudos.** El mapa guarda hashes HMAC y valores
   enmascarados, nunca el valor original.
6. **Aislamiento.** La base enmascarada no se conecta a la aplicación de
   desarrollo ni a producción.
7. **Reportes agregados.** La validación informa conteos, no valores.

## 3. Clasificación de campos

| Clase | Ejemplos | Tratamiento |
|---|---|---|
| Identificador directo | nombre, DNI/RUC, email, teléfono, dirección, titular | Sustituir por token |
| Cuasi-identificador | departamento, provincia, distrito, fechas, importes, entidad | Tokenizar geografía; preservar fechas/importes/entidad |
| Identificador financiero | operación, cuenta, cuenta de recaudo | Token numérico determinístico |
| Identificador documental | `nro_carta`, `correlativo`, extensión de archivo | Preservar si no contiene PII; si contiene, tokenizar formato |
| Texto libre | notas, observaciones, motivos, mensajes | Reemplazar por texto sintético |
| Secreto | password, remember_token, tokens personales | No copiar; generar credenciales de staging |
| No sensible | estados, roles, tipos, moneda, producto, cosecha, montos | Preservar |

## 4. Mecanismo determinístico

### 4.1 Secreto

- Generar un secreto aleatorio de 256 bits fuera del repositorio.
- Guardarlo en un almacén local protegido (por ejemplo, Credential Manager o
  un archivo con ACL restringida, nunca versionado).
- No imprimirlo, no incluirlo en logs, no pasarlo por argumentos de línea de
  comandos y no enviarlo a servicios externos.
- Rotarlo invalida el mapa; si se rota, se reconstruye toda la base.

### 4.2 Hash de origen

Para cada dominio:

```text
source_hash = HMAC-SHA256(secreto, dominio || ":" || valor_normalizado)
```

El `source_hash` es la clave de búsqueda en el mapa. El valor original no se
almacena. El mapa vive en `kp_crm_mask_work`, una base sin acceso desde la app.

### 4.3 Tokens numéricos

Para DNI, RUC, operación y cuenta:

1. Normalizar el valor de origen (trim, sin separadores).
2. Calcular `source_hash`.
3. Derivar un entero con HMAC y el dominio.
4. Ajustarlo a la misma longitud y clase del original.
5. Rechazar el token si ya está usado **o** si coincide con un identificador
   real del mismo dominio. Para esto se compara su hash contra el conjunto de
   hashes de origen, sin guardar valores crudos.
6. Si hay rechazo, reintentar con un contador determinístico.
7. Guardar `source_hash -> masked_value` en el mapa.

El conjunto de hashes de origen se calcula al inicio del ETL y se descarta al
terminar. Así ningún token enmascarado coincide con un DNI, RUC, operación o
cuenta real de la fuente.

Reglas:

- 8 dígitos: 8 dígitos, primer dígito distinto de cero.
- 9 dígitos: 9 dígitos.
- 11 dígitos: 11 dígitos.
- 7 dígitos: 7 dígitos.
- Otros: conservar longitud si es numérico; si no, token alfanumérico con
  prefijo `MASK_` y hash corto.
- Valores vacíos o `NULL`: se conservan como vacíos o `NULL`.

La misma persona conserva su DNI enmascarado en `clientes_cuentas`,
`pagos_propia`, `promesas_pago`, `cna_solicitudes`, `cliente_bloqueos`,
`asignar_clientes` y `ccd_clientes`.

### 4.4 Nombres

No se usan nombres reales ni listas de nombres reales. Se usan tokens legibles:

- `CLIENTE_000001`, `CLIENTE_000002`, ... por persona.
- `USUARIO_001`, `USUARIO_002`, ... por usuario.
- `ASESOR_001` para nombres de asesores en `asignar_clientes.name`.
- `GESTOR_001` para gestores de pago.
- `TITULAR_000001` se reemplaza por el mismo `CLIENTE_...` de la persona.

El número de persona se asigna una sola vez por `source_hash` de DNI. Así, todas
las cuentas y documentos de la misma persona muestran el mismo token.

### 4.5 Correos y teléfonos

- Email de usuario: `usuario<ID>@example.invalid`.
- Email alternativo si apareciera en texto libre: no se conserva.
- Teléfonos: mismo largo, prefijo ficticio y dígitos derivados del hash. Se
  conserva la diferencia entre 8, 9, 10, 11, 12 y 13 dígitos para probar
  validaciones y visualización.
- No se reutilizan teléfonos reales ni dominios reales.

### 4.6 Direcciones y geografía

- `direccion`: `DIRECCION_000001`.
- `dpto`, `provincia`, `distrito`: `DPTO_01`, `PROV_01`, `DIST_01`,
  manteniendo la jerarquía y el número de grupos.
- Si el área funcional necesita nombres reales de departamentos para reportes,
  se puede conservar solo el macro-departamento, nunca la dirección. Esa
  excepción requiere aprobación.

## 5. Tratamiento por tabla

| Tabla | Campo | Tratamiento |
|---|---|---|
| `users` | `name` | `USUARIO_###` |
| `users` | `email` | `usuario<ID>@example.invalid` |
| `users` | `password` | Hash bcrypt de staging; nunca el real |
| `users` | `remember_token` | `NULL` |
| `users` | `role`, `active`, `is_active`, `supervisor_id` | Preservar; no normalizar |
| `clientes_cuentas` | `numdoc` | Token numérico por persona |
| `clientes_cuentas` | `cuenta` | Token numérico |
| `clientes_cuentas` | `nombre` | `CLIENTE_###` |
| `clientes_cuentas` | `direccion` | `DIRECCION_###` |
| `clientes_cuentas` | `dpto`, `provincia`, `distrito` | Tokens jerárquicos |
| `clientes_cuentas` | `operacion` | Token numérico; misma tabla de operaciones |
| `clientes_cuentas` | `entidad`, `producto`, `cosecha`, `moneda` | Preservar |
| `clientes_cuentas` | `fecha_compra`, `fecha_castigo`, deudas | Preservar |
| `cliente_bloqueos` | `dni` | Token por persona |
| `cliente_bloqueos` | `motivo` | `MOTIVO_###` |
| `pagos_lotes` | `archivo` | `lote_###.<ext>`; extensión preservada |
| `pagos_lotes` | `tipo`, `total_registros`, `usuario_id` | Preservar |
| `pagos_propia` | `dni`, `operacion`, `cuenta_recaudo` | Tokens determinísticos |
| `pagos_propia` | `nombre_cliente` | `CLIENTE_###` |
| `pagos_propia` | `gestor` | `GESTOR_###` |
| `pagos_propia` | `lote_id`, `monto_pagado`, `fecha`, `entidad`, `cosecha` | Preservar |
| `promesas_pago` | `dni`, `operacion`, `telefono` | Tokens determinísticos |
| `promesas_pago` | `nota`, `nota_preaprobacion`, `nota_aprobacion`, `nota_rechazo` | Texto sintético |
| `promesas_pago` | `user_id`, fechas, montos, estados, tipo, cuotas | Preservar |
| `promesa_operaciones` | `operacion` | Mismo token de operación |
| `promesa_operaciones` | `cartera`, `promesa_id` | Preservar |
| `promesa_cuotas` | Fechas, montos, `es_balon`, `promesa_id` | Preservar |
| `cna_solicitudes` | `dni`, `titular` | Token por persona / `CLIENTE_###` |
| `cna_solicitudes` | `operaciones` (JSON) | Mapear cada operación al token |
| `cna_solicitudes` | `observacion`, `nota`, `motivo_rechazo` | Texto sintético |
| `cna_solicitudes` | `nro_carta`, `correlativo` | Preservar si no contienen PII; documentar |
| `cna_solicitudes` | `docx_path`, `pdf_path` | Ruta relativa sintética |
| `cna_solicitudes` | `workflow_estado`, fechas, montos, aprobadores | Preservar |
| `asignar_clientes` | `numdoc`, `operacion` | Tokens determinísticos |
| `asignar_clientes` | `name` | `ASESOR_###` (es el nombre del asesor, no del cliente) |
| `ccd_clientes` | `numdoc` | Token por persona |
| `ccd_clientes` | `pdf`, `link` | Nombre/ruta sintética; extensión preservada |
| `ccd_clientes` | `cosecha` | Preservar |
| `notifications` | `data` | JSON sintético con las mismas claves |
| `notifications` | `type`, `notifiable_type`, `read_at`, fechas | Preservar |
| `personal_access_tokens` | Todos | No copiar; tabla vacía |
| `migrations` | Filas | Copiar solo en staging V1; no en validación V3 |

## 6. Texto libre y documentos

### 6.1 Texto libre

Los campos `observacion`, `nota`, `nota_preaprobacion`, `nota_aprobacion`,
`nota_rechazo`, `motivo`, `motivo_rechazo` y `notifications.data.message` se
reemplazan por texto sintético. No se intenta "limpiar" el texto real con
regex, porque una limpieza parcial deja PII residual.

Se conserva:

- `NULL` vs cadena vacía;
- el largo máximo permitido por la columna;
- la existencia de una nota, para probar la UI.

### 6.2 Rutas CNA

- `docx_path`: `cna/docx/CLIENTE_###/CNA_###.docx`.
- `pdf_path`: `cna/pdf/CLIENTE_###/CNA_###.pdf`.
- Sin rutas absolutas, sin `http`, sin nombres reales.
- Los archivos reales no se copian al repositorio. Se generan documentos
  placeholder sin metadatos en el storage de staging.

### 6.3 Rutas CCD

- `pdf`: `CCD_###.pdf` (sin directorio, como el original).
- `link`: `ccd/CCD_###/documento.pdf` (con directorio, como el original).
- Se generan PDFs placeholder válidos y sin metadatos.
- Si un test necesita distinguir PDF de enlace, se conserva la diferencia de
  forma entre ambos campos.

### 6.4 Notificaciones

`notifications.data` contiene claves que pueden incluir DNI, cliente, nombre del
creador y URL. Se reemplaza completo por un JSON sintético con las mismas
claves, por ejemplo:

```json
{
  "dni": "CLIENTE_000001",
  "type": "workflow",
  "title": "Notificación de prueba",
  "estado": "pendiente",
  "module": "promesas",
  "cliente": "CLIENTE_000001",
  "message": "Mensaje sintético",
  "entity_id": "OP_000001",
  "action_url": "/staging/notificacion/000001",
  "created_by": 1,
  "action_label": "Abrir",
  "created_by_name": "USUARIO_001"
}
```

Se preservan `type`, `notifiable_type`, `notifiable_id`, `read_at` y fechas.

## 7. Credenciales de staging

- No se copian hashes de contraseñas reales.
- Todos los usuarios reciben un hash bcrypt aleatorio no utilizable.
- Se habilitan cuatro credenciales de prueba, una por rol
  (`administrador`, `supervisor`, `asesor`, `soporte`), generadas localmente.
- Las credenciales se guardan fuera del repositorio y no se imprimen.
- Se preservan `role`, `active`, `is_active` y `supervisor_id` para reproducir
  el comportamiento real, incluidos los 5 casos con `active <> is_active`.
- No se crean usuarios demo adicionales en esta fase.

## 8. Orden de ejecución

La ejecución se hará solo después de G0 y G2:

1. Verificar que la fuente es la copia autorizada y que está intacta.
2. Crear `kp_crm_staging_masked` y `kp_crm_mask_work` vacías.
3. Cargar la fuente en `kp_crm_v1_reference` o leerla en modo solo lectura.
4. Crear el secreto y el mapa.
5. Enmascarar en este orden:
   `users` -> `clientes_cuentas` -> `cliente_bloqueos` -> `pagos_lotes` ->
   `pagos_propia` -> `promesas_pago` -> `promesa_operaciones` ->
   `promesa_cuotas` -> `cna_solicitudes` -> `asignar_clientes` ->
   `ccd_clientes` -> `notifications`.
6. No copiar `personal_access_tokens`.
7. Copiar `migrations` solo a la base V1 enmascarada, no a la validación V3.
8. Validar conteos, FKs, unicidad y ausencia de PII.
9. Generar placeholders de documentos.
10. Congelar la base enmascarada y construir `kp_crm_v3_validation`.

## 9. Validación del enmascaramiento

### 9.1 Conteos

- Cada tabla copiada debe tener exactamente el mismo número de filas que la
  fuente, salvo `personal_access_tokens`, que se excluye.
- Cada FK verificada debe tener 0 huérfanos.
- Cada PK y restricción única debe seguir siendo única.
- Los roles y estados deben conservar sus conteos.

### 9.2 Ausencia de PII

Se comparan conjuntos de hashes, sin mostrar valores:

- `source_hash` de DNI/RUC de origen contra los tokens de destino: 0 matches
  exactos del mismo dominio (el ETL rechaza tokens que coincidan con un
  identificador real).
- `source_hash` de emails, teléfonos, nombres y direcciones: 0 matches.
- `source_hash` de rutas y nombres de archivo: 0 matches.
- Búsqueda de patrones de email, teléfono y DNI en texto libre: se reporta
  conteo; los tokens `CLIENTE_...` no deben coincidir con el formato real.
- Revisión manual de una muestra agregada de textos y notificaciones, sin
  imprimir valores.

### 9.3 Invariantes

- Sumas de deuda, pagos y cuotas iguales a la fuente.
- Distribución de estados y roles igual.
- Fechas mínimas/máximas y casos inválidos preservados.
- `cna_solicitudes.operaciones` sigue siendo JSON válido, con la misma
  cantidad de operaciones por solicitud.
- Cada operación del JSON existe en `clientes_cuentas` enmascarada.
- Cada `promesa_operaciones.operacion` existe en la tabla de operaciones.
- Cada `ccd_clientes.numdoc` y `asignar_clientes.numdoc` tiene token asignado.

### 9.4 Documentos

- 100% de las rutas CNA y CCD son relativas y sintéticas.
- No hay rutas absolutas, `http://`, `https://` ni nombres reales.
- Los placeholders existen y abren; no contienen metadatos de producción.

## 10. Riesgos residuales

| Riesgo | Control |
|---|---|
| Reidentificación por combinaciones raras de importe/fecha/entidad | Acceso limitado a staging; reportes agregados; no compartir exports |
| El mapa HMAC es sensible | Base `kp_crm_mask_work` sin acceso de app; secreto fuera del repo |
| Logs o capturas con datos | `APP_DEBUG=false`; logs sin payload; capturas solo de staging |
| Documentos con metadatos | Placeholders generados localmente; sin EXIF/docProps reales |
| Copias de seguridad de staging | Cifradas, retención corta, borrado aprobado |
| Fuga por agentes de IA | No enviar dumps, filas, capturas ni rutas reales |

El enmascaramiento reduce el riesgo, pero no convierte la copia en datos
públicos. La base enmascarada sigue siendo información confidencial.

## 11. Criterios de aceptación

- [ ] Fuente intacta y con checksum verificado.
- [ ] 100% de filas copiadas (excepto tokens personales).
- [ ] 0 huérfanos y 0 violaciones de unicidad.
- [ ] 0 coincidencias exactas de PII de origen.
- [ ] Roles, estados, importes, fechas y casos conocidos preservados.
- [ ] Rutas y documentos sintéticos.
- [ ] Credenciales de staging fuera del repositorio.
- [ ] Informe de validación aprobado por técnico y funcional.

## 12. Decisiones abiertas

1. ¿Se conserva `nro_carta`/`correlativo` como identificador documental o se
   tokeniza con el mismo formato?
2. ¿Se tokeniza la geografía o se conserva solo el macro-departamento?
3. ¿Se usa copia completa enmascarada o un subconjunto de 5,000 personas para
   pruebas de UI?
4. ¿Qué claves lógicas se congelan para conciliación de pagos y promesas?
5. ¿Quién custodia el secreto HMAC y el mapa?
