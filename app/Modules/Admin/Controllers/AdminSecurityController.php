<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

/**
 * Configuración de autenticación de dos factores (TOTP) del panel super-admin.
 */
class AdminSecurityController extends Controller
{
    public function show(): Response
    {
        /** @var AdminUser $user */
        $user = Auth::guard('admin')->user();

        $setup = null;

        // Secreto generado pero todavía no confirmado con un código válido:
        // se vuelve a mostrar el QR para poder terminar el proceso si la
        // página se recargó a mitad de la configuración.
        if ($user->two_factor_secret && ! $user->two_factor_enabled) {
            $setup = $this->buildSetupPayload($user);
        }

        return Inertia::render('Admin/Security', [
            'twoFactorEnabled'   => $user->two_factor_enabled,
            'twoFactorConfirmedAt' => $user->two_factor_confirmed_at,
            'setup'              => $setup,
        ]);
    }

    public function enable(): \Illuminate\Http\RedirectResponse
    {
        /** @var AdminUser $user */
        $user = Auth::guard('admin')->user();

        $user->update([
            'two_factor_secret'  => Google2FA::generateSecretKey(),
            'two_factor_enabled' => false,
        ]);

        return back();
    }

    public function confirm(Request $request): \Illuminate\Http\RedirectResponse
    {
        /** @var AdminUser $user */
        $user = Auth::guard('admin')->user();

        $data = $request->validate(['code' => 'required|string']);

        if (! $user->two_factor_secret || ! Google2FA::verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'El código ingresado no es válido.']);
        }

        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();

        $user->update([
            'two_factor_enabled'        => true,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $recoveryCodes),
        ]);

        return back()->with('recoveryCodes', $recoveryCodes);
    }

    public function disable(Request $request): \Illuminate\Http\RedirectResponse
    {
        /** @var AdminUser $user */
        $user = Auth::guard('admin')->user();

        $data = $request->validate(['password' => 'required|string']);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Contraseña incorrecta.']);
        }

        $user->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
        ]);

        return back()->with('success', 'La autenticación de dos factores fue desactivada.');
    }

    private function buildSetupPayload(AdminUser $user): array
    {
        $company = config('app.name', 'PyME POS SaaS');

        return [
            'secret' => $user->two_factor_secret,
            'qrCode' => Google2FA::getQRCodeInline($company, $user->email, $user->two_factor_secret),
        ];
    }
}
