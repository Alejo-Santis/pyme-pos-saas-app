<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de login del tenant.
     * Detecta ?registered=1 para mostrar notificación de bienvenida tras el registro.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'justRegistered' => $request->boolean('registered'),
            'centralDomain' => config('tenancy.central_domain') ?? env('CENTRAL_DOMAIN'),
        ]);
    }

    /**
     * Procesa el intento de inicio de sesión.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        // 'is_active' => true se agrega a la condición WHERE de Auth::attempt():
        // un usuario desactivado no debe poder autenticarse, aunque la contraseña sea correcta.
        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
