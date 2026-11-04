<?php

/**
 * Mide latencia de metadatos (stat) y lectura de archivos PHP por directorio.
 * Uso local en Docker: php docker/crm/bench/fs-latency.php
 * Limpia la realpath/stat cache de PHP antes de cada directorio para medir
 * el costo real del sistema de archivos (bind mount vs volumen nativo).
 */

$base = realpath(__DIR__.'/../../..');
$dirs = ['vendor', 'app', 'bootstrap', 'config', 'routes', 'resources/views'];
$out = [];

foreach ($dirs as $d) {
    $path = $base.'/'.$d;
    if (! is_dir($path)) {
        $out[$d] = 'missing';
        continue;
    }

    clearstatcache(true);
    $files = [];
    $t = hrtime(true);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            $files[] = $f->getPathname();
        }
    }
    $walkMs = (hrtime(true) - $t) / 1e6;

    $sample = array_slice($files, 0, 1500);

    clearstatcache(true);
    $t = hrtime(true);
    foreach ($sample as $f) {
        stat($f);
    }
    $statMs = (hrtime(true) - $t) / 1e6;

    $t = hrtime(true);
    $bytes = 0;
    foreach ($sample as $f) {
        $bytes += strlen((string) file_get_contents($f));
    }
    $readMs = (hrtime(true) - $t) / 1e6;

    $n = max(count($sample), 1);
    $out[$d] = [
        'php_files' => count($files),
        'walk_ms' => round($walkMs, 1),
        'sample' => count($sample),
        'stat_us_per_file' => round($statMs * 1000 / $n, 1),
        'read_us_per_file' => round($readMs * 1000 / $n, 1),
        'read_mb' => round($bytes / 1048576, 2),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT), PHP_EOL;
