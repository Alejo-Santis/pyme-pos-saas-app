<?php

namespace App\Modules\Tenant\Services;

use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Validation\ValidationException;

class PlanLimitsService
{
    private ?Plan $plan = null;

    public function plan(): ?Plan
    {
        if ($this->plan !== null) {
            return $this->plan;
        }

        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return null;
        }

        return $this->plan = tenancy()->central(function () use ($tenant) {
            return Tenant::with('plan')->find($tenant->id)?->plan;
        });
    }

    public function featureEnabled(string $feature): bool
    {
        $plan = $this->plan();

        if (! $plan) {
            return true;
        }

        return (bool) ($plan->features[$feature] ?? false);
    }

    public function ensureFeature(string $feature, string $label): void
    {
        if ($this->featureEnabled($feature)) {
            return;
        }

        throw ValidationException::withMessages([
            'plan' => "Tu plan actual no incluye {$label}. Solicita un cambio de plan para usar esta funcion.",
        ]);
    }

    public function ensureBelowLimit(string $limitKey, int $currentUsage, string $label): void
    {
        $plan = $this->plan();

        if (! $plan) {
            return;
        }

        $limit = $plan->{$limitKey};

        if ($limit === null || (int) $limit <= 0 || $currentUsage < (int) $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'plan' => "Tu plan permite hasta {$limit} {$label}. Ya alcanzaste ese limite.",
        ]);
    }

    public function ensureCanCreateUser(): void
    {
        $this->ensureBelowLimit('max_users', User::count(), 'usuarios');
    }

    public function ensureCanCreateItem(): void
    {
        $this->ensureBelowLimit('max_products', Item::count(), 'productos o servicios');
    }

    public function ensureCanCreateInvoice(): void
    {
        $this->ensureFeature('dian_fe', 'facturacion electronica');

        $currentMonthUsage = Document::whereBetween('issue_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ])->count();

        $this->ensureBelowLimit('max_invoices_monthly', $currentMonthUsage, 'documentos al mes');
    }
}
