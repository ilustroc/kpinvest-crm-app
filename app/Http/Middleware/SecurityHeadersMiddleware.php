<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach baseline security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (function_exists('header_remove') && !headers_sent()) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        // Protección contra Clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Protección contra MIME Sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Política de Referer segura (evita fuga de URLs completas con DNI)
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restricción de permisos y APIs del navegador
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // CSP Progresivo compatible con la interfaz Meridian actual
        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com data:; "
            . "img-src 'self' data: https: blob:; "
            . "connect-src 'self' ws: wss:; "
            . "frame-ancestors 'self'; "
            . "form-action 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
