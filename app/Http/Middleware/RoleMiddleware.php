<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Admite roles separados por coma o pipe: "administrador,supervisor".
        $allowed = [];
        foreach ($roles as $chunk) {
            foreach (preg_split('/[,\|]/', $chunk) as $r) {
                $r = trim($r);
                if ($r !== '') $allowed[] = $r;
            }
        }

        if (in_array($user->role, $allowed, true)) {
            return $next($request);
        }

        abort(403); // sin permiso (NO debe ser 500)
    }
}
