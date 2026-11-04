# KP INVEST CRM V3 — Optimización Docker + Laravel

Fecha de validación: 2026-11-04 (America/Lima)
Repositorio: `ilustroc/kpinvest-crm-app`
Base analizada: `eee305bd8be4324e17bb0734525117769fd87ff3`
Runtime validado: Laravel 10.50.0, PHP 8.3.36 en Apache, MariaDB 11.8.9.

## 1. Causa raíz demostrada

Se confirmaron dos causas independientes:

1. `vendor/` estaba dentro del bind mount de Windows (`C:/GitHub/KP-CRM-Meridian-ui:/var/www/html`). Laravel incluye 674 archivos durante el bootstrap y Composer/Laravel pagaban el coste Windows↔Docker en cada `stat`/lectura. Antes del cambio, `autoload` tardó 1.18–1.51 s y el bootstrap de proveedores 2.55–2.94 s.
2. OPcache estaba deshabilitado para todos los SAPIs (`opcache.enable=0`), incluido Apache. No era sólo una configuración de CLI.

Además se halló una condición de disponibilidad tras reiniciar Docker Desktop: `db` no tenía política de reinicio, quedó detenido (exit 255) mientras Apache seguía operativo y Laravel fallaba al resolver el host `db`. Los logs registraron `getaddrinfo for db failed`. No se tomaron como benchmark válido las peticiones hechas mientras la base estaba fuera de línea.

MariaDB no fue el cuello de botella del arranque sano: antes de la corrección, abrir la conexión tardó 150–170 ms y `SELECT 1`, alrededor de 20 ms; el mayor coste estaba en sistema de archivos, autoload y bootstrap.

## 2. Estado Docker inicial

- Código, `vendor/` y `bootstrap/` estaban dentro de un bind mount Windows↔Linux.
- `storage` ya usaba el volumen `kpcrm_meridian_ui_revision_storage`.
- CCD permanece como bind mount de solo lectura en `/mnt/ccd-import/BBVA_5`; no fue leído, movido ni modificado.
- MariaDB usa el volumen existente `kpcrm_meridian_ui_mariadb_data`; nunca se eliminó ni se recreó su contenido.
- Apache usa `mpm_prefork`, que es el MPM compatible con `mod_php` de esta imagen.
- No se encontró Xdebug cargado.

## 3. Métricas iniciales

Las cinco mediciones proporcionadas para `GET /login` desde Windows fueron 5.047, 8.340, 12.657, 5.796 y 5.758 s de TTFB: promedio **7.520 s**, mediana **5.796 s**, máximo **12.657 s**.

Con MariaDB restaurada pero antes de la corrección, se obtuvieron cinco mediciones internas de bootstrap:

| Métrica | Promedio | Mediana | Máximo |
| --- | ---: | ---: | ---: |
| Autoload Composer | 1,318 ms | 1,258 ms | 1,515 ms |
| Bootstrap / providers | 2,716 ms | 2,700 ms | 2,936 ms |
| Laravel total | 4,494 ms | 4,560 ms | 4,777 ms |
| Conexión MariaDB | 156 ms | 153 ms | 170 ms |
| `SELECT 1` MariaDB | 20 ms | 20 ms | 21 ms |

Una repetición previa a la corrección, ya con BD disponible, midió `/login` dentro del contenedor entre 2.602 y 5.438 s. Esto aisló el problema de red/port-forward de Docker Desktop.

## 4. Cambios aplicados

- `compose.yml`
  - Se añadió el volumen Docker `kpcrm_meridian_ui_vendor` en `/var/www/html/vendor`.
  - Se añadió `restart: unless-stopped` a MariaDB para que vuelva a iniciar junto con Docker Desktop.
- `docker/crm/Dockerfile`
  - Instala dependencias desde `composer.lock` en la imagen mediante `composer install --prefer-dist --optimize-autoloader --no-scripts`.
  - Guarda el SHA-256 del lockfile dentro del árbol de dependencias para verificar su vigencia.
- `docker/crm/entrypoint.sh`
  - Comprueba ese SHA-256 al iniciar y ejecuta `composer install` sólo si `composer.lock` cambió o falta `vendor/autoload.php`; no reutiliza dependencias obsoletas.
  - Reestablece propiedad `www-data` en `storage` y `bootstrap/cache` antes de iniciar Apache, evitando que mantenimiento ejecutado como root rompa sesiones, vistas y cachés del navegador.
- `docker/crm/php-dev.ini`
  - Habilita OPcache para Apache, no para CLI.
  - Usa `validate_timestamps=1`, `revalidate_freq=0` y `file_update_protection=0`, por lo que los cambios PHP se detectan en la siguiente petición sin reinicio manual.
  - Configura 20,000 archivos acelerados y caché de rutas realista para Laravel.
- `docker/crm/bench/`
  - Añade herramientas no HTTP para fases de arranque, latencia de filesystem, HTTP interno y medición de kernel/consultas. No exponen `phpinfo()` ni endpoints de diagnóstico.

No se modificó Laravel 10, PHP, reglas de negocio, interfaz Meridian, datos, CCD ni producción.

## 5. Métricas posteriores

El nuevo volumen quedó montado y el SHA-256 de `composer.lock` coincidió con el sello de `vendor`. OPcache quedó verificado como `enable=On`, `enable_cli=Off`, `validate_timestamps=On`, `revalidate_freq=0`.

| Métrica interna (5 ejecuciones) | Promedio | Mediana | Máximo |
| --- | ---: | ---: | ---: |
| Autoload Composer | 26 ms | 27 ms | 30 ms |
| Bootstrap / providers | 299 ms | 278 ms | 399 ms |
| Laravel total | 348 ms | 326 ms | 456 ms |
| Conexión MariaDB | 6 ms | 7 ms | 7 ms |
| `SELECT 1` MariaDB | 0.7 ms | 0.7 ms | 0.8 ms |

El recorrido de 9,901 PHP de `vendor` pasó a 55 ms; 1,500 `stat` promediaron 2.5 µs y 1,500 lecturas 10.2 µs por archivo. En contraste, los directorios de código que permanecen en el bind mount (`app`, `config`, `routes` y vistas) siguen mostrando milisegundos por lectura; ya no dominan el bootstrap porque las dependencias masivas están en volumen Linux nativo.

### HTTP posterior desde Windows (`localhost:8091`)

| Ruta | Código sin sesión | TTFB promedio | Mediana | Máximo |
| --- | ---: | ---: | ---: | ---: |
| `/favicon.ico` | 200 | 0.220 s | 0.219 s | 0.234 s |
| `/login` | 200 | **0.372 s** | **0.342 s** | **0.470 s** |
| `/` | 302 | 0.318 s | 0.316 s | 0.336 s |
| `/dashboard` | 302 | 0.315 s | 0.311 s | 0.333 s |
| `/clientes` | 404 | 0.373 s | 0.368 s | 0.427 s |
| `/clientes/22097866` | 302 | 0.402 s | 0.417 s | 0.434 s |
| `/autorizacion` | 302 | 0.325 s | 0.321 s | 0.348 s |
| `/integracion/asignacion` | 302 | 0.356 s | 0.356 s | 0.445 s |
| `/reportes/pagos` | 302 | 0.342 s | 0.331 s | 0.392 s |

Las rutas protegidas se midieron sin una cookie de usuario y, correctamente, redirigieron a login; no se presentan esos 302 como validación de dashboard autenticado. La suite funcional sí cubre flujos autenticados, roles, ficha 360°, promesas, CNA, autorizaciones, asignaciones, importación, CCD, reportes y exportaciones contra `kp_crm_testing`.

## 6. Comparativa

`/login` caliente redujo el TTFB promedio de la línea base proporcionada de 7.520 s a 0.372 s (**95.1 % menos**). El bootstrap interno promedio bajó de 4.494 s a 0.348 s (**92.3 % menos**). No se habilitó OPcache CLI para mejorar artificialmente estas cifras.

## 7. Pruebas y seguridad

- `npm run build`: correcto, Vite produjo el manifest y bundles esperados.
- `docker compose exec -T app php artisan test`: **126 passed, 971 assertions, 2 skipped**, duración 23.37 s.
- La configuración de PHPUnit fuerza `DB_DATABASE=kp_crm_testing`, `CACHE_DRIVER=array` y `SESSION_DRIVER=array`.
- `docker compose config --quiet`, `apache2ctl -t`, `php -l` de los scripts y `sh -n` del entrypoint: correctos.
- No se creó ningún endpoint público de diagnóstico; `APP_DEBUG` se conserva en `false`.
- La invalidación de OPcache mantiene visibles los cambios PHP en la siguiente petición. No se ejecutaron `config:cache`, `route:cache` ni `view:cache`, evitando configuración de desarrollo congelada.

## 8. Integridad de bases de datos

El volumen de MariaDB existente se inspeccionó antes de aplicar cambios y permaneció en `kpcrm_meridian_ui_mariadb_data`. No se ejecutaron migraciones, seeders, restauraciones, DDL, DML ni pruebas contra `kp_crm_revision_v3`; la única consulta de rendimiento fue `SELECT 1`. Las pruebas que pueden escribir usaron el esquema `kp_crm_testing` por configuración forzada de PHPUnit.

## 9. Git y sincronización

El cambio de runtime y herramientas de medición quedó registrado en:

```text
00a8adbf531e8d98d04b2380e02476145e1b044a
perf(docker): isolate vendor and enable development opcache

7231d00
docs: document Docker Laravel performance optimization
```

La sincronización de `feature/meridian-ui-v3` y el fast-forward de `main` se verifican al cierre, sin force-push.

## 10. Reconstrucción local

1. Desde `C:\GitHub\KP-CRM-Meridian-ui`, ejecutar `docker compose up -d --build`.
2. Esperar `docker compose ps` hasta que `db` sea `healthy` y `app` esté `Up`.
3. El primer arranque crea `kpcrm_meridian_ui_vendor` y lo inicializa desde la imagen. Si cambia `composer.lock`, el entrypoint detecta el hash y ejecuta `composer install` exactamente para ese lockfile.
4. Verificar con `docker compose exec -T app php artisan --version` y abrir `http://localhost:8091/login`.
5. Para medir: `docker compose exec -T app php docker/crm/bench/boot-phases.php` y `docker compose exec -T app bash docker/crm/bench/http-bench.sh 5`.

No es necesario borrar `vendor` del host ni ningún volumen. Para regenerar deliberadamente sólo las dependencias, detener el entorno y retirar exclusivamente `kpcrm_meridian_ui_vendor` después de confirmar que el lockfile y la imagen son los deseados; nunca usar ese procedimiento para MariaDB o `storage`.
