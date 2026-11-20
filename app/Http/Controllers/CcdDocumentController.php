<?php

namespace App\Http\Controllers;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CcdDocumentController extends Controller
{
    /**
     * Roles autorizados para consultar información de clientes en KP INVEST CRM.
     * Compatible con arquitectura LEGACY de producción donde Roles::class no existe.
     */
    public const AUTHORIZED_ROLES = [
        'administrador',
        'admin',
        'supervisor',
        'asesor',
        'soporte',
    ];

    /**
     * Flags de simulación para pruebas automatizadas en entornos mixtos (Legacy vs Modern).
     */
    public static bool $simulateRolesClassMissing = false;

    public static bool $simulateGateMissing = false;

    public function show(Request $request, string $numdoc, CcdCliente $ccdCliente)
    {
        $user = $request->user();
        abort_unless($user !== null, 401, 'No autenticado.');

        // 1. Control de usuario activo (mismo criterio que middleware EnsureUserIsActive)
        if ((isset($user->active) && ! $user->active) || (isset($user->activo) && ! $user->activo)) {
            abort(403, 'Tu cuenta ha sido desactivada.');
        }

        // 2. Control de permisos equivalente a clientes.show (compatible con Producción Legacy y Modern)
        $hasGate = ! self::$simulateGateMissing && Gate::has('view-cliente');
        $hasRolesClass = ! self::$simulateRolesClassMissing && class_exists('App\Support\Authorization\Roles');

        $isAuthorized = false;

        if ($hasGate) {
            Gate::forUser($user)->authorize('view-cliente');
            $isAuthorized = true;
        } elseif ($hasRolesClass) {
            /** @var class-string $rolesClass */
            $rolesClass = 'App\Support\Authorization\Roles';
            $role = $rolesClass::normalize($user->role ?? '');
            $isAuthorized = in_array($role, $rolesClass::FINAL_ROLES, true);
        } else {
            // Producción LEGACY (ROLES_CLASS=NO y GATE_VIEW_CLIENTE=NO):
            // Reutiliza exactamente los roles legítimos de clientes.show
            $role = strtolower(trim((string) ($user->role ?? '')));
            $isAdmin = in_array($role, ['administrador', 'admin'], true)
                || (! empty($user->is_admin))
                || (! empty($user->es_admin));

            $isAuthorized = in_array($role, self::AUTHORIZED_ROLES, true) || $isAdmin;
        }

        abort_unless($isAuthorized, 403, 'No autorizado para consultar información de clientes.');

        // 3. Verificación estricta de DNI en URL contra DNI del registro documental
        $targetDoc = (string) ($request->route('numdoc') ?? $request->route('dni') ?? $numdoc);
        abort_unless(hash_equals((string) $ccdCliente->numdoc, $targetDoc), 404);

        // 4. Control de bloqueo de cliente (mismo criterio que BlockClienteAccess)
        $userRole = strtolower(trim((string) ($user->role ?? '')));
        $isAdminRole = in_array($userRole, ['administrador', 'admin'], true)
            || (! empty($user->is_admin))
            || (! empty($user->es_admin));

        if ($user && ! $isAdminRole && Schema::hasTable('cliente_bloqueos')) {
            $col = Schema::hasColumn('cliente_bloqueos', 'numdoc') ? 'numdoc' : 'dni';
            $blocked = DB::table('cliente_bloqueos')
                ->where('user_id', $user->id)
                ->where($col, $targetDoc)
                ->exists();
            abort_if($blocked, 403, 'No autorizado para ver este cliente.');
        }

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
