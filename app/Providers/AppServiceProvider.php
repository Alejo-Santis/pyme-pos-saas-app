<?php

namespace App\Providers;

use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Observers\DocumentCreateObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observer que dispara el pipeline DIAN al crear un documento
        Document::observe(DocumentCreateObserver::class);
    }
}
