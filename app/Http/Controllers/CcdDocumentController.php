<?php

namespace App\Http\Controllers;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CcdDocumentController extends Controller
{
    public function show(Request $request, string $dni, CcdCliente $ccdCliente)
    {
        Gate::forUser($request->user())->authorize('view-cliente');

        abort_unless(hash_equals((string) $ccdCliente->numdoc, $dni), 404);

        $archivoId = $ccdCliente->getAttribute('ccd_archivo_id');

        if ($archivoId !== null) {
            return $this->privateResponse((int) $archivoId);
        }

        return $this->legacyResponse((string) $ccdCliente->link);
    }

    private function privateResponse(int $archivoId)
    {
        $archivo = CcdArchivo::query()->find($archivoId);

        abort_if($archivo === null, 404, 'El documento CCD privado no está disponible.');

        $privateDisk = (string) config('ccd.private_disk');
        $expectedPath = CcdArchivo::pathForHash((string) $archivo->sha256);

        abort_unless(
            hash_equals($privateDisk, (string) $archivo->disk)
                && hash_equals($expectedPath, (string) $archivo->path),
            404,
            'El documento CCD privado no está disponible.'
        );

        $storage = Storage::disk($privateDisk);

        abort_unless(
            $storage->exists($archivo->path),
            404,
            'El documento CCD privado no está disponible.'
        );

        return $storage->response(
            $archivo->path,
            basename((string) $archivo->nombre_original),
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    private function legacyResponse(string $link)
    {
        $parts = parse_url(trim($link));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        abort_unless(
            $scheme === 'https' && in_array($host, config('ccd.legacy_hosts', []), true),
            404,
            'El documento CCD no está disponible.'
        );

        return redirect()->away($link);
    }
}
