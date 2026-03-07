<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Blade template raíz que contiene @inertia.
     * Inertia inyectará aquí el componente Svelte correspondiente.
     */
    protected $rootView = 'app';

    /**
     * Versión del asset para cache busting en deploys.
     * Inertia fuerza un full-reload cuando cambia esta versión.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props globales compartidas con TODOS los componentes Svelte.
     * Accesibles via `$page.props` en cualquier componente.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Usuario autenticado — lazy closure para que se evalúe DESPUÉS de InitializeTenancyByDomain.
            // Try/catch por si el dominio central no tiene tabla users (schema public no tiene users).
            'auth' => [
                'user' => function () use ($request) {
                    try {
                        $user = $request->user();
                        return $user ? [
                            'id'    => $user->id,
                            'name'  => $user->name,
                            'email' => $user->email,
                        ] : null;
                    } catch (\Throwable) {
                        return null;
                    }
                },
            ],

            // Mensajes flash (toasts, notificaciones)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],

            // Nombre de la app
            'appName' => config('app.name'),
        ]);
    }
}
