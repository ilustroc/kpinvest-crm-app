<?php

/**
 * Benchmark in-process (Kernel HTTP de Laravel) para rutas autenticadas.
 * Uso local en Docker, NO accesible vía web:
 *   php docker/crm/bench/http-kernel-bench.php <user_id> <numdoc> [repeticiones]
 *
 * - Autentica con el guard de Laravel en memoria (sin crear sesiones persistentes
 *   ni modificar contraseñas) del mismo modo que actingAs() en las pruebas.
 * - Toda la ejecución corre dentro de una transacción que se revierte al final:
 *   ninguna escritura accidental persiste en la base consultada.
 * - Cuenta consultas SQL y su duración con DB::listen.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$userId = (int) ($argv[1] ?? 0);
$numdoc = (string) ($argv[2] ?? '');
$reps = (int) ($argv[3] ?? 5);

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$routes = ['/', '/dashboard', '/clientes/'.$numdoc, '/autorizacion', '/integracion/asignacion', '/reportes/pagos'];

$queries = 0;
$sqlMs = 0.0;
$writes = [];

$results = [];
$first = true;

foreach ($routes as $uri) {
    $samples = [];
    for ($i = 0; $i < $reps; $i++) {
        $request = Request::create($uri, 'GET');
        if ($first) {
            // Primer handle: registra el listener y abre la transacción de protección.
            $app->instance('request', $request);
        }

        $queries = 0;
        $sqlMs = 0.0;
        $t = hrtime(true);

        if ($first) {
            $app->booted(function () use (&$queries, &$sqlMs, &$writes, $userId) {
                DB::listen(function ($q) use (&$queries, &$sqlMs, &$writes) {
                    $queries++;
                    $sqlMs += $q->time;
                    if (! preg_match('/^\s*(select|show|set|\(select)/i', $q->sql)) {
                        $writes[] = substr($q->sql, 0, 120);
                    }
                });
                DB::beginTransaction();
                Auth::onceUsingId($userId);
            });
            $first = false;
        } else {
            Auth::onceUsingId($userId);
        }

        $response = $kernel->handle($request);
        $elapsed = (hrtime(true) - $t) / 1e6;
        $samples[] = [
            'ms' => round($elapsed, 1),
            'status' => $response->getStatusCode(),
            'queries' => $queries,
            'sql_ms' => round($sqlMs, 1),
        ];
    }

    $times = array_column($samples, 'ms');
    sort($times);
    $results[$uri] = [
        'status' => $samples[0]['status'],
        'cold_ms' => $samples[0]['ms'],
        'warm_avg_ms' => round(array_sum(array_slice(array_column($samples, 'ms'), 1)) / max($reps - 1, 1), 1),
        'median_ms' => $times[intdiv(count($times), 2)],
        'max_ms' => max($times),
        'queries' => $samples[count($samples) - 1]['queries'],
        'sql_ms' => $samples[count($samples) - 1]['sql_ms'],
    ];
}

DB::rollBack();

echo json_encode(['results' => $results, 'non_select_statements_rolled_back' => array_values(array_unique($writes))], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
