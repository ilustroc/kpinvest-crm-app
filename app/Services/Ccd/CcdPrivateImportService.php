<?php

namespace App\Services\Ccd;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CcdPrivateImportService
{
    private ?bool $schemaReady = null;

    public function schemaReady(): bool
    {
        return $this->schemaReady ??= Schema::hasTable('ccd_archivos')
            && Schema::hasColumn('ccd_clientes', 'ccd_archivo_id');
    }

    public function process(string $sourcePath, string $cosecha, bool $apply): array
    {
        $metadata = $this->inspectPdf($sourcePath);
        $metadata['cosecha'] = $cosecha;
        $ccd = $this->findExactCcd($metadata['name'], $cosecha);
        $schemaReady = $this->schemaReady();

        if ($apply && ! $schemaReady) {
            throw new DomainException('La estructura privada CCD todavía no está migrada.');
        }

        if (! $schemaReady) {
            return [
                'status' => 'would_import',
                'schema_ready' => false,
                'sha256' => $metadata['sha256'],
            ];
        }

        $currentArchivoId = $ccd->getAttribute('ccd_archivo_id');
        $matchingArchivo = CcdArchivo::query()->where('sha256', $metadata['sha256'])->first();

        if (! $apply) {
            return [
                'status' => $this->dryRunStatus($currentArchivoId, $matchingArchivo, $metadata),
                'schema_ready' => true,
                'sha256' => $metadata['sha256'],
            ];
        }

        $status = DB::transaction(function () use ($ccd, $metadata) {
            $lockedCcd = CcdCliente::query()->lockForUpdate()->findOrFail($ccd->id);

            abort_unless(
                (string) $lockedCcd->pdf === $metadata['name']
                    && (string) $lockedCcd->cosecha === $metadata['cosecha'],
                409,
                'La asociación CCD cambió durante la importación.'
            );

            $currentArchivoId = $lockedCcd->getAttribute('ccd_archivo_id');

            if ($currentArchivoId !== null) {
                $archivo = CcdArchivo::query()->lockForUpdate()->find($currentArchivoId);

                if ($archivo === null || ! hash_equals((string) $archivo->sha256, $metadata['sha256'])) {
                    throw new DomainException('El CCD ya está asociado a otro contenido privado.');
                }

                $this->assertArchivoMetadata($archivo, $metadata);
                $restored = $this->ensureStoredFile($metadata['source'], $archivo->path, $metadata['sha256'], $metadata['size']);

                return $restored ? 'repaired' : 'already_imported';
            }

            $archivo = CcdArchivo::query()
                ->where('sha256', $metadata['sha256'])
                ->lockForUpdate()
                ->first();

            $targetPath = CcdArchivo::pathForHash($metadata['sha256']);
            $stored = $this->ensureStoredFile($metadata['source'], $targetPath, $metadata['sha256'], $metadata['size']);

            if ($archivo === null) {
                $archivo = CcdArchivo::query()->create([
                    'disk' => config('ccd.private_disk'),
                    'path' => $targetPath,
                    'sha256' => $metadata['sha256'],
                    'size_bytes' => $metadata['size'],
                    'mime_type' => $metadata['mime'],
                    'nombre_original' => $metadata['name'],
                    'verified_at' => now(),
                ]);
            } else {
                $this->assertArchivoMetadata($archivo, $metadata);
            }

            $lockedCcd->forceFill(['ccd_archivo_id' => $archivo->id])->save();

            if (! $stored) {
                return 'reused';
            }

            return $archivo->wasRecentlyCreated ? 'imported' : 'repaired';
        }, 3);

        return [
            'status' => $status,
            'schema_ready' => true,
            'sha256' => $metadata['sha256'],
        ];
    }

    private function inspectPdf(string $sourcePath): array
    {
        if (! is_file($sourcePath) || is_link($sourcePath) || ! is_readable($sourcePath)) {
            throw new DomainException('El origen no es un archivo regular legible.');
        }

        $name = basename($sourcePath);

        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new DomainException('El archivo no tiene extensión PDF.');
        }

        $size = filesize($sourcePath);
        $maxBytes = (int) config('ccd.max_file_bytes');

        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw new DomainException('El tamaño del PDF está fuera del límite permitido.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        $header = file_get_contents($sourcePath, false, null, 0, 5);

        if ($mime !== 'application/pdf' || $header !== '%PDF-') {
            throw new DomainException('El contenido no es un PDF válido.');
        }

        $sha256 = hash_file('sha256', $sourcePath);

        if ($sha256 === false) {
            throw new DomainException('No se pudo calcular SHA-256.');
        }

        return [
            'source' => $sourcePath,
            'name' => $name,
            'size' => (int) $size,
            'mime' => $mime,
            'sha256' => strtolower($sha256),
        ];
    }

    private function findExactCcd(string $name, string $cosecha): CcdCliente
    {
        $columns = ['id', 'pdf', 'cosecha'];

        if (Schema::hasColumn('ccd_clientes', 'ccd_archivo_id')) {
            $columns[] = 'ccd_archivo_id';
        }

        $matches = CcdCliente::query()
            ->where('cosecha', $cosecha)
            ->where('pdf', $name)
            ->get($columns)
            ->filter(fn (CcdCliente $ccd) => (string) $ccd->cosecha === $cosecha && (string) $ccd->pdf === $name)
            ->values();

        if ($matches->count() !== 1) {
            throw new DomainException('La coincidencia exacta por nombre y cosecha no es inequívoca.');
        }

        $ccd = $matches->first();
        $ccd->setAttribute('cosecha', $cosecha);

        return $ccd;
    }

    private function dryRunStatus(mixed $currentArchivoId, ?CcdArchivo $matchingArchivo, array $metadata): string
    {
        if ($currentArchivoId !== null) {
            $current = CcdArchivo::query()->find($currentArchivoId);

            if (
                $current === null
                || ! hash_equals((string) $current->sha256, $metadata['sha256'])
                || ! $this->archivoMetadataMatches($current, $metadata)
            ) {
                return 'conflict';
            }

            $storage = Storage::disk($current->disk);

            if (! $storage->exists($current->path)) {
                return 'would_repair';
            }

            return $this->storedFileMatches($storage->path($current->path), $metadata['sha256'], $metadata['size'])
                ? 'already_imported'
                : 'conflict';
        }

        if ($matchingArchivo !== null) {
            if (! $this->archivoMetadataMatches($matchingArchivo, $metadata)) {
                return 'conflict';
            }

            $storage = Storage::disk($matchingArchivo->disk);

            if (! $storage->exists($matchingArchivo->path)) {
                return 'would_repair';
            }

            if (! $this->storedFileMatches($storage->path($matchingArchivo->path), $metadata['sha256'], $metadata['size'])) {
                return 'conflict';
            }
        }

        return $matchingArchivo === null ? 'would_import' : 'would_reuse';
    }

    private function assertArchivoMetadata(CcdArchivo $archivo, array $metadata): void
    {
        if (! $this->archivoMetadataMatches($archivo, $metadata)) {
            throw new DomainException('Los metadatos privados existentes no coinciden con el contenido validado.');
        }
    }

    private function archivoMetadataMatches(CcdArchivo $archivo, array $metadata): bool
    {
        $disk = (string) config('ccd.private_disk');
        $path = CcdArchivo::pathForHash($metadata['sha256']);

        return hash_equals($disk, (string) $archivo->disk)
            && hash_equals($path, (string) $archivo->path)
            && (int) $archivo->size_bytes === $metadata['size']
            && (string) $archivo->mime_type === $metadata['mime'];
    }

    private function ensureStoredFile(string $sourcePath, string $targetPath, string $sha256, int $size): bool
    {
        $storage = Storage::disk(config('ccd.private_disk'));

        if ($storage->exists($targetPath)) {
            $this->assertStoredFile($storage->path($targetPath), $sha256, $size);

            return false;
        }

        $stagingPath = '.staging/'.Str::uuid().'.part';
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new DomainException('No se pudo abrir el PDF de origen.');
        }

        try {
            if (! $storage->writeStream($stagingPath, $stream)) {
                throw new DomainException('No se pudo copiar el PDF al área temporal privada.');
            }
        } finally {
            fclose($stream);
        }

        try {
            $this->assertStoredFile($storage->path($stagingPath), $sha256, $size);

            if ($storage->exists($targetPath)) {
                $this->assertStoredFile($storage->path($targetPath), $sha256, $size);

                return false;
            }

            if (! $storage->move($stagingPath, $targetPath)) {
                throw new DomainException('No se pudo consolidar el PDF privado.');
            }

            return true;
        } finally {
            if ($storage->exists($stagingPath)) {
                $storage->delete($stagingPath);
            }
        }
    }

    private function assertStoredFile(string $path, string $sha256, int $size): void
    {
        $storedSize = filesize($path);
        $storedHash = hash_file('sha256', $path);

        if ($storedSize !== $size || $storedHash === false || ! hash_equals($sha256, strtolower($storedHash))) {
            throw new DomainException('La copia privada no supera la verificación de integridad.');
        }
    }

    private function storedFileMatches(string $path, string $sha256, int $size): bool
    {
        $storedSize = filesize($path);
        $storedHash = hash_file('sha256', $path);

        return $storedSize === $size
            && $storedHash !== false
            && hash_equals($sha256, strtolower($storedHash));
    }
}
