<?php

/**
 * Diagnóstico CLI de fases de arranque de Laravel (sólo uso local en Docker).
 * No es accesible vía HTTP: vive fuera de public/.
 *
 * Uso: php docker/crm/bench/boot-phases.php [repeticiones]
 * Imprime JSON por fase (ms) para separar autoload, bootstrap, providers,
 * configuración, sesión/caché y conexión a MariaDB.
 */

$t0 = hrtime(true);
$ms = static fn (int $from): float => round((hrtime(true) - $from) / 1e6, 2);

$phases = [];

$t = hrtime(true);
require __DIR__.'/../../../vendor/autoload.php';
$phases['autoload'] = $ms($t);

$t = hrtime(true);
$app = require __DIR__.'/../../../bootstrap/app.php';
$phases['create_app'] = $ms($t);

$t = hrtime(true);
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap(); // env + config + facades + providers register/boot
$phases['bootstrap_providers'] = $ms($t);

$t = hrtime(true);
$cfg = $app['config']->get('app.name');
$app->make('router');
$app->make('view');
$phases['resolve_services'] = $ms($t);

$t = hrtime(true);
Illuminate\Support\Facades\Cache::get('bench_probe_key');
$phases['cache_get'] = $ms($t);

$t = hrtime(true);
$app['session']->driver()->getHandler()->read('bench_probe_session');
$phases['session_read'] = $ms($t);

$t = hrtime(true);
Illuminate\Support\Facades\DB::connection()->getPdo();
$phases['db_connect'] = $ms($t);

$t = hrtime(true);
Illuminate\Support\Facades\DB::select('SELECT 1');
$phases['db_select_1'] = $ms($t);

$phases['total'] = $ms($t0);
$phases['included_files'] = count(get_included_files());
$phases['opcache_cli'] = (bool) ini_get('opcache.enable_cli');

echo json_encode($phases), PHP_EOL;
