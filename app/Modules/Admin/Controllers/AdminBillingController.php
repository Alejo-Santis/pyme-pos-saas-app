<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\LandlordPayment;
use App\Modules\Admin\Services\LandlordAuditService;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBillingController extends Controller
{
    public function __construct(
        private readonly LandlordAuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $subscriptions = Subscription::with(['tenant.domains', 'plan'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->plan_id, fn ($q, $planId) => $q->where('plan_id', $planId))
            ->when($request->search, fn ($q, $search) =>
                $q->whereHas('tenant', fn ($tenantQuery) =>
                    $tenantQuery->where('name', 'ilike', '%' . $search . '%')
                        ->orWhere('email', 'ilike', '%' . $search . '%')
                )
            )
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'tenant_name' => $subscription->tenant?->name ?? '—',
                'tenant_email' => $subscription->tenant?->email ?? '—',
                'tenant_domain' => $subscription->tenant?->domains?->first()?->domain ?? '—',
                'plan' => $subscription->plan?->name ?? '—',
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'billing_period' => $subscription->billing_period,
                'price' => $subscription->price,
                'trial_ends_at' => $subscription->trial_ends_at?->format('d/m/Y'),
                'starts_at' => $subscription->starts_at?->format('d/m/Y'),
                'ends_at' => $subscription->ends_at?->format('d/m/Y'),
                'cancelled_at' => $subscription->cancelled_at?->format('d/m/Y'),
            ]);

        $payments = LandlordPayment::with(['tenant.domains', 'subscription.plan'])
            ->latest('paid_at')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (LandlordPayment $payment) => [
                'id' => $payment->id,
                'tenant_name' => $payment->tenant?->name ?? '—',
                'tenant_domain' => $payment->tenant?->domains?->first()?->domain ?? '—',
                'subscription_id' => $payment->subscription_id,
                'plan' => $payment->subscription?->plan?->name ?? '—',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->format('d/m/Y H:i'),
                'notes' => $payment->notes,
            ]);

        $summary = [
            'active' => Subscription::where('status', 'active')->count(),
            'trial' => Subscription::where('status', 'trial')->count(),
            'past_due' => Subscription::where('status', 'past_due')->count(),
            'cancelled' => Subscription::whereIn('status', ['cancelled', 'expired'])->count(),
            'paid_month' => LandlordPayment::where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'mrr' => Subscription::where('status', 'active')
                ->selectRaw("COALESCE(SUM(CASE WHEN billing_period = 'yearly' THEN price / 12 ELSE price END), 0) as total")
                ->value('total'),
        ];

        return Inertia::render('Admin/Billing/Index', [
            'subscriptions' => $subscriptions,
            'summary' => $summary,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'plan_id']),
            'recentPayments' => $payments,
        ]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|uuid|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:150',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $subscription = Subscription::with(['tenant', 'plan'])->findOrFail($data['subscription_id']);
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

        $payment = LandlordPayment::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? 'COP'),
            'status' => 'paid',
            'payment_method' => $data['payment_method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $paidAt,
            'notes' => $data['notes'] ?? null,
            'metadata' => [
                'source' => 'super_admin_manual',
            ],
        ]);

        $oldSubscription = $subscription->only(['status', 'starts_at', 'ends_at', 'cancelled_at']);
        $oldTenant = $subscription->tenant?->only(['status', 'plan_id']) ?? [];

        $subscription->update([
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?? $paidAt,
            'ends_at' => $subscription->billing_period === 'yearly'
                ? $paidAt->copy()->addYear()
                : $paidAt->copy()->addMonth(),
            'cancelled_at' => null,
            'grace_ends_at' => null,
        ]);

        $tenant = Tenant::find($subscription->tenant_id);
        $tenant?->update([
            'status' => 'active',
            'plan_id' => $subscription->plan_id,
        ]);

        $this->audit->record('manual_payment_recorded', 'billing', $payment, [
            'subscription' => $oldSubscription,
            'tenant' => $oldTenant,
        ], [
            'payment' => $payment->only(['id', 'amount', 'currency', 'status', 'payment_method', 'reference', 'paid_at']),
            'subscription' => $subscription->fresh()->only(['status', 'starts_at', 'ends_at', 'cancelled_at']),
            'tenant' => $tenant?->fresh()?->only(['status', 'plan_id']),
        ]);

        return back()->with('success', 'Pago manual registrado y suscripcion activada.');
    }
}
