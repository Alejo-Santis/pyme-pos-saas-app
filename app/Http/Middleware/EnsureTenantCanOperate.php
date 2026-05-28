<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantCanOperate
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        if (! $tenant || $tenant->canOperate()) {
            return $next($request);
        }

        if ($request->routeIs('subscription')) {
            return $next($request);
        }

        return redirect()
            ->route('subscription')
            ->withErrors([
                'subscription' => 'La cuenta no puede operar porque la suscripcion esta suspendida, vencida o cancelada.',
            ]);
    }
}
