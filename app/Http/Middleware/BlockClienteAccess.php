<?php

// app/Http/Middleware/BlockClienteAccess.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlockClienteAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $dni  = (string) $request->route('dni');

        // Admin/Sistemas pueden saltarse (si quieres)
        if ($user && in_array($user->role, ['administrador','sistemas'], true)) {
            return $next($request);
        }

        if ($user && $dni) {
            $blocked = DB::table('cliente_bloqueos')
                ->where('user_id', $user->id)
                ->where('dni', $dni)
                ->exists();

            if ($blocked) {
                abort(403, 'No autorizado para ver este cliente.');
                // o si prefieres “ocultar” el cliente:
                // abort(404);
            }
        }

        return $next($request);
    }
}

