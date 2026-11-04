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
        $numdoc = (string) ($request->route('numdoc') ?? $request->route('dni'));

        // Admin/Sistemas pueden saltarse (si quieres)
        if ($user && $user->role === 'administrador') {
            return $next($request);
        }

        if ($user && $numdoc !== '') {
            $column = \Illuminate\Support\Facades\Schema::hasColumn('cliente_bloqueos', 'numdoc') ? 'numdoc' : 'dni';
            $blocked = DB::table('cliente_bloqueos')
                ->where('user_id', $user->id)
                ->where($column, $numdoc)
                ->exists();

            if ($blocked) {
                abort(403, 'No autorizado para ver este cliente.');
            }
        }

        return $next($request);
    }
}
