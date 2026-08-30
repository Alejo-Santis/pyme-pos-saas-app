<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Modules\Admin\Services\LandlordAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class AdminAuthController extends Controller
{
    public function __construct(private LandlordAuditService $audit)
    {
    }

    // Mostrar login del panel admin
    public function showLogin(): Response|\Illuminate\Http\RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Login');
    }

    // Procesar login (primer factor: email + contraseña)
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = AdminUser::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas o cuenta inactiva.',
            ]);
        }

        // Con 2FA activo no se inicia sesión todavía — se guarda el usuario
        // pendiente en sesión y se exige el código antes de autenticar de verdad.
        if ($user->two_factor_enabled) {
            $request->session()->put('admin_2fa_user_id', $user->id);
            $request->session()->put('admin_2fa_remember', $request->boolean('remember'));

            return redirect()->route('admin.two-factor');
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        $this->audit->record('login', 'auth', $user, [], [
            'email' => $user->email,
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    // Pantalla de desafío del segundo factor
    public function showTwoFactor(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        if (! $request->session()->has('admin_2fa_user_id')) {
            return redirect()->route('admin.login');
        }

        return Inertia::render('Admin/TwoFactorChallenge');
    }

    // Verifica el código TOTP (o un código de recuperación) y completa el login
    public function verifyTwoFactor(Request $request)
    {
        $userId = $request->session()->get('admin_2fa_user_id');

        if (! $userId) {
            return redirect()->route('admin.login');
        }

        $user = AdminUser::findOrFail($userId);

        $data = $request->validate([
            'code'          => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $verified = false;

        if (! empty($data['code'])) {
            $verified = Google2FA::verifyKey($user->two_factor_secret, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $verified = $this->consumeRecoveryCode($user, $data['recovery_code']);
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'El código ingresado no es válido.',
            ]);
        }

        $remember = $request->session()->pull('admin_2fa_remember', false);
        $request->session()->forget('admin_2fa_user_id');

        Auth::guard('admin')->login($user, $remember);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function consumeRecoveryCode(AdminUser $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);

                return true;
            }
        }

        return false;
    }

    // Cerrar sesión
    public function logout(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            $this->audit->record('logout', 'auth', $admin, [], [
                'email' => $admin->email,
            ]);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
