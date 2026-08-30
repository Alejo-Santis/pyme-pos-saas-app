<?php

namespace App\Providers;

use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Observers\DocumentCreateObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Observer que dispara el pipeline DIAN al crear un documento
        Document::observe(DocumentCreateObserver::class);

        // ── Rate limiting ────────────────────────────────────────────────────
        // Login tenant: máximo 5 intentos/minuto por IP + email
        RateLimiter::for('tenant-login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email', '') . '|' . $request->ip())
                ->response(fn () => back()
                    ->withErrors(['email' => 'Demasiados intentos. Espera 1 minuto e intenta de nuevo.'])
                    ->withInput()
                );
        });

        // Login admin: máximo 3 intentos/minuto por IP
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip())
                ->response(fn () => back()
                    ->withErrors(['email' => 'Demasiados intentos. Espera 1 minuto.'])
                    ->withInput()
                );
        });

        // Reintento DIAN: máximo 3 por minuto por documento
        RateLimiter::for('dian-retry', function (Request $request) {
            return Limit::perMinute(3)
                ->by('dian|' . ($request->route('document') ?? '') . '|' . $request->ip());
        });

        // Imports masivos (Excel: terceros, items, empleados): pesados en
        // CPU/IO — sin límite, un tenant podía tumbar el servidor sin querer.
        RateLimiter::for('imports', function (Request $request) {
            return Limit::perMinute(5)
                ->by('import|' . ($request->user()?->id ?? $request->ip()))
                ->response(fn () => back()->withErrors([
                    'file' => 'Demasiadas importaciones seguidas. Espera un minuto e intenta de nuevo.',
                ]));
        });

        // Exportaciones/reportes (PDF, Excel): generación pesada bajo demanda.
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(15)
                ->by('export|' . ($request->user()?->id ?? $request->ip()));
        });
    }
}
