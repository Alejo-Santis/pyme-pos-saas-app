<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOnboardingCompleted
{
    /**
     * Redirige al wizard de onboarding si el usuario aún no completó la configuración inicial.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario fue desactivado mientras tenía sesión abierta, se cierra de
        // inmediato — no basta con bloquear el login, ver LoginController::store().
        if ($user && ! $user->is_active) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu usuario fue desactivado. Contacta al administrador.',
            ]);
        }

        if ($user && ! $user->onboarding_completed) {
            // Permitir acceso a las rutas de onboarding para evitar loop infinito
            if ($request->routeIs('onboarding.*')) {
                return $next($request);
            }

            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
