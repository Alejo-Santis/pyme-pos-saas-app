<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\TenantNotificationMail;
use App\Models\User;
use App\Modules\Admin\Models\LandlordImpersonationToken;
use App\Modules\Admin\Services\LandlordAuditService;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminTenantController extends Controller
{
    public function __construct(private LandlordAuditService $audit)
    {
    }

    // Lista de tenants con filtros y paginación
    public function index(Request $request): Response
    {
        $tenants = Tenant::with(['plan', 'activeSubscription', 'domains'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn ($q, $p) => $q->where('plan_id', $p))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(function ($t) {
                $domain = $t->domains->first()?->domain;

                return [
                    'id'            => $t->id,
                    'name'          => $t->name,
                    'email'         => $t->email,
                    'status'        => $t->status,
                    'plan'          => $t->plan?->name ?? '—',
                    'plan_id'       => $t->plan_id,
                    'domain'        => $domain ?? '—',
                    'login_url'     => $domain ? $this->appScheme() . '://' . $domain . '/login' : null,
                    'trial_ends_at' => $t->trial_ends_at
                        ? \Carbon\Carbon::parse($t->trial_ends_at)->format('d/m/Y')
                        : null,
                    'created_at'    => $t->created_at?->format('d/m/Y'),
                ];
            });

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants'  => $tenants,
            'plans'    => $plans,
            'filters'  => $request->only(['search', 'status', 'plan_id']),
        ]);
    }

    // Detalle de un tenant
    public function show(string $id): Response
    {
        $tenant = Tenant::with(['plan', 'subscriptions.plan', 'domains'])->findOrFail($id);
        $domain = $tenant->domains->first()?->domain;
        $editableSubscription = $tenant->activeSubscription()->with('plan')->first()
            ?? $tenant->subscriptions()->with('plan')->latest()->first();
        $supportSummary = $this->supportSummary($tenant);
        $tenantUsers = $this->tenantUsers($tenant);

        $subscriptions = $tenant->subscriptions()
            ->with('plan')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'plan'       => $s->plan?->name ?? '—',
                'status'     => $s->status,
                'billing'    => $s->billing_period,
                'price'      => $s->price,
                'starts_at'  => $s->starts_at?->format('d/m/Y'),
                'ends_at'    => $s->ends_at?->format('d/m/Y'),
                'cancelled_at' => $s->cancelled_at?->format('d/m/Y'),
                'trial_ends' => $s->trial_ends_at
                    ? \Carbon\Carbon::parse($s->trial_ends_at)->format('d/m/Y')
                    : null,
            ]);

        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price_monthly', 'price_yearly']);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'email'         => $tenant->email,
                'status'        => $tenant->status,
                'plan'          => $tenant->plan?->name ?? '—',
                'plan_id'       => $tenant->plan_id,
                'domain'        => $domain ?? '—',
                'domain_input'  => $domain,
                'login_url'     => $domain ? $this->appScheme() . '://' . $domain . '/login' : null,
                'trial_ends_at' => $tenant->trial_ends_at
                    ? \Carbon\Carbon::parse($tenant->trial_ends_at)->format('d/m/Y')
                    : null,
                'trial_ends_at_input' => $tenant->trial_ends_at?->toDateString(),
                'created_at'    => $tenant->created_at?->format('d/m/Y H:i'),
                'updated_at'    => $tenant->updated_at?->format('d/m/Y H:i'),
            ],
            'subscriptions' => $subscriptions,
            'activeSubscription' => $editableSubscription ? [
                'id'                  => $editableSubscription->id,
                'plan_id'             => $editableSubscription->plan_id,
                'plan'                => $editableSubscription->plan?->name ?? '—',
                'status'              => $editableSubscription->status,
                'billing_period'      => $editableSubscription->billing_period,
                'price'               => $editableSubscription->price,
                'trial_ends_at'       => $editableSubscription->trial_ends_at?->toDateString(),
                'starts_at'           => $editableSubscription->starts_at?->toDateString(),
                'ends_at'             => $editableSubscription->ends_at?->toDateString(),
                'cancelled_at'        => $editableSubscription->cancelled_at?->toDateString(),
                'gateway'             => $editableSubscription->gateway,
                'gateway_subscription_id' => $editableSubscription->gateway_subscription_id,
            ] : null,
            'supportSummary' => $supportSummary,
            'tenantUsers' => $tenantUsers,
            'plans'         => $plans,
        ]);
    }

    // Cambiar estado del tenant
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:active,trial,suspended,cancelled',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldValues = $tenant->only(['status']);
        $tenant->update(['status' => $request->status]);

        $this->audit->record('tenant_status_updated', 'tenants', $tenant, $oldValues, $tenant->only(['status']));

        return back()->with('success', "Estado del tenant actualizado a '{$request->status}'.");
    }

    // Cambiar plan del tenant
    public function updatePlan(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $tenant = Tenant::findOrFail($id);
        $plan = Plan::findOrFail($request->plan_id);
        $oldValues = [
            'plan_id' => $tenant->plan_id,
            'plan' => $tenant->plan?->name,
        ];

        DB::transaction(function () use ($tenant, $plan) {
            $tenant->update(['plan_id' => $plan->id]);

            $subscription = $tenant->activeSubscription()->first();

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'price'   => $subscription->billing_period === 'yearly'
                        ? $plan->price_yearly
                        : $plan->price_monthly,
                ]);
            } else {
                $tenant->subscriptions()->create([
                    'plan_id'        => $plan->id,
                    'billing_period' => 'monthly',
                    'price'          => $plan->price_monthly,
                    'status'         => $tenant->status === 'trial' ? 'trial' : 'active',
                    'trial_ends_at'  => $tenant->status === 'trial' ? $tenant->trial_ends_at : null,
                    'starts_at'      => now(),
                    'ends_at'        => null,
                ]);
            }
        });

        $tenant->refresh();
        $this->audit->record('plan_updated', 'tenants', $tenant, $oldValues, [
            'plan_id' => $tenant->plan_id,
            'plan' => $plan->name,
        ]);

        return back()->with('success', 'Plan del tenant actualizado correctamente.');
    }

    public function updateDomain(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domainModel = $tenant->domains->first();

        $data = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/',
                Rule::unique('domains', 'domain')->ignore($domainModel?->id),
            ],
        ]);

        $oldValues = ['domain' => $domainModel?->domain];

        if ($domainModel) {
            $domainModel->update(['domain' => $data['domain']]);
        } else {
            $tenant->domains()->create(['domain' => $data['domain']]);
        }

        $this->audit->record('domain_updated', 'tenants', $tenant, $oldValues, [
            'domain' => $data['domain'],
        ]);

        return back()->with('success', 'Dominio del tenant actualizado correctamente.');
    }

    public function updateSubscription(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'plan_id'         => 'required|exists:plans,id',
            'status'          => 'required|in:trial,active,past_due,cancelled,expired',
            'billing_period'  => 'required|in:monthly,yearly',
            'price'           => 'required|numeric|min:0',
            'trial_ends_at'   => 'nullable|date',
            'starts_at'       => 'required|date',
            'ends_at'         => 'nullable|date|after_or_equal:starts_at',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldValues = [
            'tenant' => $tenant->only(['plan_id', 'status', 'trial_ends_at']),
        ];

        DB::transaction(function () use ($tenant, $data) {
            $subscription = ! empty($data['subscription_id'])
                ? Subscription::where('tenant_id', $tenant->id)->findOrFail($data['subscription_id'])
                : null;

            if (! $subscription) {
                $subscription = $tenant->subscriptions()->make();
            }

            $subscription->fill([
                'plan_id'        => $data['plan_id'],
                'status'         => $data['status'],
                'billing_period' => $data['billing_period'],
                'price'          => $data['price'],
                'trial_ends_at'  => $data['trial_ends_at'] ?? null,
                'starts_at'      => $data['starts_at'],
                'ends_at'        => $data['ends_at'] ?? null,
                'cancelled_at'   => in_array($data['status'], ['cancelled', 'expired'], true) ? now() : null,
            ]);

            $subscription->tenant_id = $tenant->id;
            $subscription->save();

            $tenant->update([
                'plan_id'       => $data['plan_id'],
                'status'        => $this->tenantStatusForSubscription($data['status']),
                'trial_ends_at' => $data['status'] === 'trial' ? ($data['trial_ends_at'] ?? null) : $tenant->trial_ends_at,
            ]);
        });

        $tenant->refresh();
        $this->audit->record('subscription_updated', 'subscriptions', $tenant, $oldValues, [
            'tenant' => $tenant->only(['plan_id', 'status', 'trial_ends_at']),
            'subscription' => $data,
        ]);

        return back()->with('success', 'Suscripción actualizada correctamente.');
    }

    public function extendTrial(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldValues = $tenant->only(['status', 'trial_ends_at']);
        $baseDate = $tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()
            ? $tenant->trial_ends_at
            : now();
        $trialEndsAt = $baseDate->copy()->addDays((int) $data['days']);

        DB::transaction(function () use ($tenant, $trialEndsAt) {
            $tenant->update([
                'status'        => 'trial',
                'trial_ends_at' => $trialEndsAt,
            ]);

            $subscription = $tenant->activeSubscription()->first()
                ?? $tenant->subscriptions()->latest()->first();

            if ($subscription) {
                $subscription->update([
                    'status'        => 'trial',
                    'trial_ends_at' => $trialEndsAt,
                    'ends_at'       => $trialEndsAt,
                    'cancelled_at'  => null,
                ]);
            }
        });

        $tenant->refresh();
        $this->audit->record('trial_extended', 'tenants', $tenant, $oldValues, [
            'status' => $tenant->status,
            'trial_ends_at' => $tenant->trial_ends_at,
            'days' => (int) $data['days'],
        ]);

        return back()->with('success', 'Trial extendido correctamente.');
    }

    public function sendNotification(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        $data = $request->validate([
            'subject' => 'required|string|max:160',
            'message' => 'required|string|max:3000',
            'action_label' => 'nullable|string|max:80',
            'action_url' => 'nullable|url|max:500',
        ]);

        Mail::to($tenant->email)->send(new TenantNotificationMail(
            tenant: $tenant,
            subjectText: $data['subject'],
            messageText: $data['message'],
            actionUrl: $data['action_url'] ?? null,
            actionLabel: $data['action_label'] ?? null,
        ));

        $this->audit->record('notification_sent', 'notifications', $tenant, [], [
            'to' => $tenant->email,
            'subject' => $data['subject'],
        ]);

        return back()->with('success', 'Notificación enviada correctamente.');
    }

    public function runTechnicalAction(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'action' => 'required|in:migrate,seed_defaults',
        ]);

        $exitCode = null;

        if ($data['action'] === 'migrate') {
            $exitCode = Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);
        }

        if ($data['action'] === 'seed_defaults') {
            $exitCode = Artisan::call('tenants:seed-defaults', [
                '--tenant' => $tenant->id,
            ]);
        }

        $output = trim(Artisan::output());

        $this->audit->record('technical_action_run', 'technical', $tenant, [], [
            'action' => $data['action'],
            'exit_code' => $exitCode,
            'output' => mb_substr($output, 0, 4000),
        ]);

        if ($exitCode !== 0) {
            return back()->with('error', "La acción técnica terminó con código {$exitCode}.");
        }

        return back()->with('success', 'Acción técnica ejecutada correctamente.');
    }

    public function impersonate(Request $request, string $id): SymfonyResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domain = $tenant->domains->first()?->domain;

        if (! $domain) {
            return back()->with('error', 'Este tenant no tiene dominio configurado.');
        }

        $data = $request->validate([
            'tenant_user_id' => 'required|uuid',
            'reason' => 'nullable|string|max:500',
        ]);

        $tenantUser = $tenant->run(function () use ($data) {
            return User::whereKey($data['tenant_user_id'])
                ->where('is_active', true)
                ->firstOrFail(['id', 'name', 'email']);
        });

        $admin = Auth::guard('admin')->user();
        $plainToken = Str::random(80);

        $record = LandlordImpersonationToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'tenant_id' => $tenant->id,
            'tenant_domain' => $domain,
            'admin_user_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'admin_email' => $admin?->email,
            'tenant_user_id' => $tenantUser->id,
            'tenant_user_name' => $tenantUser->name,
            'tenant_user_email' => $tenantUser->email,
            'expires_at' => now()->addMinutes(5),
            'created_ip' => $request->ip(),
            'metadata' => [
                'reason' => $data['reason'] ?? null,
            ],
        ]);

        $this->audit->record('impersonation_created', 'impersonation', $record, [], [
            'tenant_id' => $tenant->id,
            'tenant_domain' => $domain,
            'tenant_user_id' => $tenantUser->id,
            'tenant_user_email' => $tenantUser->email,
            'expires_at' => $record->expires_at,
            'reason' => $data['reason'] ?? null,
        ]);

        return Inertia::location($this->appScheme() . '://' . $domain . '/impersonate/' . $plainToken);
    }

    private function tenantStatusForSubscription(string $subscriptionStatus): string
    {
        return match ($subscriptionStatus) {
            'trial' => 'trial',
            'active' => 'active',
            'past_due' => 'suspended',
            default => 'cancelled',
        };
    }

    private function appScheme(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return $scheme ?: (request()->isSecure() ? 'https' : 'http');
    }

    private function supportSummary(Tenant $tenant): array
    {
        try {
            return $tenant->run(function () {
                $schema = DB::getSchemaBuilder();

                return [
                    'users_count' => DB::table('users')->count(),
                    'active_users_count' => DB::table('users')->where('is_active', true)->count(),
                    'documents_count' => DB::table('documents')->count(),
                    'companies_count' => DB::table('companies')->count(),
                    'api_errors_count' => $schema->hasTable('api_logs')
                        ? DB::table('api_logs')->where('success', false)->count()
                        : 0,
                    'last_user_login' => $schema->hasColumn('users', 'last_login_at')
                        ? DB::table('users')->whereNotNull('last_login_at')->max('last_login_at')
                        : null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'users_count' => null,
                'active_users_count' => null,
                'documents_count' => null,
                'companies_count' => null,
                'api_errors_count' => null,
                'last_user_login' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function tenantUsers(Tenant $tenant): array
    {
        try {
            return $tenant->run(function () {
                return User::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->limit(50)
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ])
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
