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
            // Usuario autenticado (null si no hay sesión)
            'auth' => [
                'user' => $request->user() ? [
                    'id'    => $request->user()->id,
                    'name'  => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
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
