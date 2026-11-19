<?php

namespace App\Services\Ccd;

use App\Contracts\CcdTemporaryPdfSourceProvider;
use App\Contracts\CcdZipV12PdfSourceDiagnostics;
use App\Models\CcdCliente;
use ZipArchive;

/**
 * Fuente de lectura para los ZIP V12. No depende del lector HTTP existente:
 * abre sólo una entrada, la copia a un temporal privado y nunca extrae el ZIP.
 */
class CcdZipV12FileSourceProvider implements CcdTemporaryPdfSourceProvider, CcdZipV12PdfSourceDiagnostics
{
    private ?string $failure = null;

    /** @var array<string,ZipArchive> */
    private array $openArchives = [];

    public function sourcePath(CcdCliente $ccd): ?string
    {
        $this->failure = null;

        if (! class_exists(ZipArchive::class)) {
            return $this->fail('zip_extension_no_disponible');
        }

        $cosecha = $this->safeCosecha((string) $ccd->cosecha);
        $pdf = $this->safePdfName((string) $ccd->pdf);

        if ($cosecha === null || $pdf === null) {
            return null;
        }

        $root = $this->root('ccd.zip_v12_root');
        $temporaryRoot = $this->root('ccd.zip_v12_temp_root');

        if ($root === null || ! is_dir($root) || ! is_readable($root)) {
            return $this->fail('zip_v12_root_no_disponible');
        }

        if ($temporaryRoot === null || ! $this->ensureTemporaryDirectory($temporaryRoot)) {
            return $this->fail('zip_v12_temporal_no_disponible');
        }

        $zipPath = $root.DIRECTORY_SEPARATOR.$cosecha.'.zip';

        if (! is_file($zipPath) || ! is_readable($zipPath)) {
            return $this->fail('zip_v12_no_disponible');
        }

        $zip = $this->archive($zipPath);

        if ($zip === null) {
            return null;
        }

        $stat = $zip->statName($pdf);

        if ($stat === false) {
            return $this->fail('pdf_zip_v12_no_encontrado');
        }

        $size = (int) ($stat['size'] ?? -1);
        $maximum = (int) config('ccd.max_file_bytes');

        if ($size < 5 || $maximum <= 0 || $size > $maximum) {
            return $this->fail('pdf_zip_v12_tamano_invalido');
        }

        $stream = $zip->getStream($pdf);

        if (! is_resource($stream)) {
            return $this->fail('pdf_zip_v12_no_legible');
        }

        $temporary = tempnam($temporaryRoot, 'ccd-zip-');

        if ($temporary === false) {
            fclose($stream);

            return $this->fail('pdf_zip_v12_temporal_no_creado');
        }

        $output = fopen($temporary, 'wb');

        if ($output === false) {
            fclose($stream);
            @unlink($temporary);

            return $this->fail('pdf_zip_v12_temporal_no_escribible');
        }

        try {
            $signature = fread($stream, 5);

            if ($signature !== '%PDF-') {
                return $this->removeAndFail($temporary, 'pdf_zip_v12_firma_invalida');
            }

            if (fwrite($output, $signature) !== 5) {
                return $this->removeAndFail($temporary, 'pdf_zip_v12_temporal_no_escribible');
            }

            $written = 5;

            while (! feof($stream)) {
                $chunk = fread($stream, min(8192, $maximum - $written + 1));

                if ($chunk === false) {
                    return $this->removeAndFail($temporary, 'pdf_zip_v12_error_lectura');
                }

                if ($chunk === '' && ! feof($stream)) {
                    return $this->removeAndFail($temporary, 'pdf_zip_v12_error_lectura');
                }

                $written += strlen($chunk);

                if ($written > $maximum || fwrite($output, $chunk) === false) {
                    return $this->removeAndFail($temporary, 'pdf_zip_v12_tamano_invalido');
                }
            }
        } finally {
            fclose($output);
            fclose($stream);
        }

        return $temporary;
    }

    public function releaseTemporarySource(string $path): void
    {
        $root = $this->root('ccd.zip_v12_temp_root');
        $baseName = basename($path);
        $hasValidPrefix = str_starts_with($baseName, 'ccd-zip-')
            || (DIRECTORY_SEPARATOR === '\\' && str_starts_with($baseName, 'ccd'));

        if ($root === null || ! is_file($path) || ! $hasValidPrefix) {
            return;
        }

        $canonicalRoot = realpath($root);
        $canonicalPath = realpath($path);

        if ($canonicalRoot === false || $canonicalPath === false) {
            return;
        }

        $prefix = rtrim($canonicalRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($canonicalPath, $prefix)) {
            @unlink($canonicalPath);
        }
    }

    public function lastFailure(): ?string
    {
        return $this->failure;
    }

    public function __destruct()
    {
        foreach ($this->openArchives as $archive) {
            $archive->close();
        }

        $this->openArchives = [];
    }

    private function archive(string $path): ?ZipArchive
    {
        if (isset($this->openArchives[$path])) {
            return $this->openArchives[$path];
        }

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::RDONLY) !== true) {
            return $this->fail('zip_v12_danado');
        }

        return $this->openArchives[$path] = $archive;
    }

    private function safeCosecha(string $value): ?string
    {
        $cosecha = strtoupper(trim($value));
        $allowed = array_map(
            static fn (mixed $item): string => strtoupper(trim((string) $item)),
            (array) config('ccd.zip_v12_cosechas', []),
        );

        if (! preg_match('/^[A-Z0-9]+$/', $cosecha) || ! in_array($cosecha, $allowed, true)) {
            return $this->fail('cosecha_zip_v12_no_permitida');
        }

        return $cosecha;
    }

    private function safePdfName(string $value): ?string
    {
        $name = trim($value);

        if ($name === '' || str_contains($name, "\0") || basename($name) !== $name || ! preg_match('/^[^\\\\\/]+\.pdf$/iD', $name)) {
            return $this->fail('nombre_pdf_zip_v12_invalido');
        }

        return $name;
    }

    private function root(string $key): ?string
    {
        $value = trim((string) config($key));

        return $value === '' ? null : $value;
    }

    private function ensureTemporaryDirectory(string $root): bool
    {
        return (is_dir($root) || mkdir($root, 0700, true) || is_dir($root)) && is_writable($root);
    }

    private function removeAndFail(string $temporary, string $reason): ?string
    {
        @unlink($temporary);

        return $this->fail($reason);
    }

    private function fail(string $reason): ?string
    {
        $this->failure = $reason;

        return null;
    }
}
