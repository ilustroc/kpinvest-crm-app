<?php

namespace App\Services\Ccd;

use RuntimeException;

class CcdBulkProcessLock
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(): bool
    {
        $path = storage_path('framework/ccd-associate-bulk.lock');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio del bloqueo CCD masivo.');
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el bloqueo CCD masivo.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);
        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }

        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
