<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Models\LandlordAuditLog;
use App\Modules\Admin\Models\LandlordImpersonationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function consume(Request $request, string $token): RedirectResponse
    {
        $tokenHash = hash('sha256', $token);
        $tenant = tenancy()->tenant;

        $record = tenancy()->central(function () use ($tokenHash) {
            return LandlordImpersonationToken::where('token_hash', $tokenHash)->first();
        });

        if (! $record || ! $record->isUsable() || $record->tenant_id !== $tenant?->id) {
            return redirect()->route('login')->withErrors([
                'email' => 'El enlace de soporte no es válido o ya expiró.',
            ]);
        }

        $user = User::whereKey($record->tenant_user_id)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'El usuario de soporte no existe o está inactivo.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonated_by_admin', [
            'admin_user_id' => $record->admin_user_id,
            'admin_name' => $record->admin_name,
            'admin_email' => $record->admin_email,
            'started_at' => now()->toDateTimeString(),
        ]);

        tenancy()->central(function () use ($record, $request) {
            $record->update([
                'consumed_at' => now(),
                'consumed_ip' => $request->ip(),
            ]);

            LandlordAuditLog::create([
                'admin_user_id' => $record->admin_user_id,
                'admin_name' => $record->admin_name,
                'admin_email' => $record->admin_email,
                'event' => 'impersonation_consumed',
                'module' => 'impersonation',
                'auditable_type' => User::class,
                'auditable_id' => $record->tenant_user_id,
                'new_values' => [
                    'tenant_id' => $record->tenant_id,
                    'tenant_domain' => $record->tenant_domain,
                    'tenant_user_email' => $record->tenant_user_email,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()->intended(route($user->onboarding_completed ? 'dashboard' : 'onboarding.show'));
    }
}
