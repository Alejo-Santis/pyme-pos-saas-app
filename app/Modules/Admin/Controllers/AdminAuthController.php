<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Modules\Admin\Services\LandlordAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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

    // Procesar login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = AdminUser::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Auth::guard('admin')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas o cuenta inactiva.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $request->session()->regenerate();

        $this->audit->record('login', 'auth', $user, [], [
            'email' => $user->email,
        ]);

        return redirect()->intended(route('admin.dashboard'));
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
