<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $normalizedEmail = Str::lower(trim((string) $data['email']));
        $ip = $request->ip() ?: '127.0.0.1';

        $user = User::where('email', $normalizedEmail)->first();

        // Evitar enumeración de usuarios: mensaje uniforme si no existe o está inactivo
        if (! $user || ! $user->active) {
            $this->logAuthAttempt($user, $ip, 'FAILED');

            return back()
                ->withErrors(['email' => 'Credenciales inválidas.'])
                ->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt([
            'email' => $normalizedEmail,
            'password' => $data['password'],
            'active' => 1,
        ], $remember)) {
            // Regenerar ID de sesión para el navegador actual protegiendo contra fijación de sesión,
            // sin invalidar las sesiones concurrentes de otros asesores compartiendo la cuenta.
            $request->session()->regenerate();

            $this->logAuthAttempt($user, $ip, 'SUCCESS');

            return redirect()->intended(route('panel'));
        }

        $this->logAuthAttempt($user, $ip, 'FAILED');

        return back()
            ->withErrors(['email' => 'Credenciales inválidas.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $userId = $user?->id;
        $ip = $request->ip() ?: '127.0.0.1';

        Auth::logout();

        // Invalida únicamente la sesión del dispositivo/navegador que ejecuta la salida.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            Log::channel('auth')->info('AUTH_LOGOUT', [
                'user_id' => $userId,
                'ip' => $ip,
                'fecha_hora' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            Log::info('AUTH_LOGOUT', [
                'user_id' => $userId,
                'ip' => $ip,
                'fecha_hora' => now()->toIso8601String(),
            ]);
        }

        return redirect()->route('login');
    }

    private function logAuthAttempt(?User $user, string $ip, string $resultado): void
    {
        $empresa = $user?->empresa ?? $user?->company ?? null;
        if (! $empresa && $user && ! str_contains($user->email, '@gmail.com') && ! str_contains($user->email, '@kpinvest.pe')) {
            $empresa = $user->name;
        }

        $payload = [
            'user_id' => $user?->id,
            'empresa' => $empresa,
            'ip' => $ip,
            'fecha_hora' => now()->toIso8601String(),
            'resultado' => $resultado,
        ];

        try {
            Log::channel('auth')->info('AUTH_ATTEMPT', $payload);
        } catch (\Throwable) {
            Log::info('AUTH_ATTEMPT', $payload);
        }
    }
}

