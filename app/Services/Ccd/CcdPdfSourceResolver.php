<?php

namespace App\Services\Ccd;

use App\Contracts\CcdTemporaryPdfSourceProvider;
use App\Contracts\CcdZipV12PdfSourceProvider;
use App\Contracts\CcdZipV12PdfSourceDiagnostics;
use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CcdPdfSourceResolver
{
    /**
     * @return array{status:string,source:?string,path:?string,evidence:string,cleanup:(callable():void)|null}
     */
    public function resolve(CcdCliente $ccd): array
    {
        $archivoId = $ccd->getAttribute('ccd_archivo_id');

        if ($archivoId !== null) {
            $private = $this->privateSource((int) $archivoId);

            if ($private['status'] === 'ok') {
                return $private;
            }

            // Una catalogación privada incompleta no debe impedir consultar el
            // lector V12 instalado por el hosting, siempre en modo lectura.
            $zip = $this->zipV12Source($ccd);

            if ($zip['status'] === 'ok') {
                $zip['evidence'] = 'respaldo_zip_v12:'.$private['evidence'];

                return $zip;
            }

            return $this->unavailable($private['evidence'].';'.$zip['evidence']);
        }

        return $this->zipV12Source($ccd);
    }

    /** @return array{status:string,source:?string,path:?string,evidence:string,cleanup:(callable():void)|null} */
    private function privateSource(int $archivoId): array
    {
        $archivo = CcdArchivo::query()->find($archivoId);

        if ($archivo === null) {
            return $this->unavailable('archivo_privado_no_catalogado');
        }

        $disk = (string) config('ccd.private_disk');
        $expectedPath = CcdArchivo::pathForHash((string) $archivo->sha256);

        if ($archivo->disk !== $disk || $archivo->path !== $expectedPath) {
            return $this->unavailable('archivo_privado_no_valido');
        }

        try {
            $storage = Storage::disk($disk);
            $path = $storage->path($archivo->path);
        } catch (Throwable) {
            return $this->unavailable('archivo_privado_no_accesible');
        }

        if (! $storage->exists($archivo->path) || ! is_file($path) || ! is_readable($path)) {
            return $this->unavailable('archivo_privado_no_disponible');
        }

        return [
            'status' => 'ok',
            'source' => 'privado',
            'path' => $path,
            'evidence' => 'fuente_privada_verificada',
            'cleanup' => null,
        ];
    }

    /** @return array{status:string,source:?string,path:?string,evidence:string,cleanup:(callable():void)|null} */
    private function zipV12Source(CcdCliente $ccd): array
    {
        $configured = config('ccd.zip_v12_source_provider');

        if ($configured === null || $configured === '') {
            return $this->unavailable('fuente_zip_v12_no_configurada');
        }

        try {
            $provider = is_object($configured) ? $configured : app((string) $configured);

            if (! $provider instanceof CcdZipV12PdfSourceProvider) {
                return $this->unavailable('fuente_zip_v12_incompatible');
            }

            $path = $provider->sourcePath($ccd);
        } catch (Throwable) {
            return $this->unavailable('fuente_zip_v12_no_accesible');
        }

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            $evidence = $provider instanceof CcdZipV12PdfSourceDiagnostics
                ? ($provider->lastFailure() ?? 'fuente_zip_v12_no_disponible')
                : 'fuente_zip_v12_no_disponible';

            return $this->unavailable($evidence);
        }

        return [
            'status' => 'ok',
            'source' => 'zip_v12',
            'path' => $path,
            'evidence' => 'fuente_zip_v12_lectura',
            'cleanup' => $provider instanceof CcdTemporaryPdfSourceProvider
                ? static function () use ($provider, $path): void {
                    $provider->releaseTemporarySource($path);
                }
                : null,
        ];
    }

    /** @param array{status:string,source:?string,path:?string,evidence:string,cleanup:(callable():void)|null} $source */
    public function release(array $source): void
    {
        if ($source['cleanup'] !== null) {
            ($source['cleanup'])();
        }
    }

    /** @return array{status:string,source:?string,path:?string,evidence:string,cleanup:(callable():void)|null} */
    private function unavailable(string $evidence): array
    {
        return [
            'status' => 'error',
            'source' => null,
            'path' => null,
            'evidence' => $evidence,
            'cleanup' => null,
        ];
    }
}
