<?php

use App\Modules\Tenant\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $plans = Plan::where('is_active', true)
        ->orderBy('sort_order')
        ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'features', 'trial_days']);

    return Inertia::render('Landing', ['plans' => $plans]);
});
