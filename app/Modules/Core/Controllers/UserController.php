<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'created_at']);

        $roles = Role::orderBy('name')->pluck('name');

        return Inertia::render('Config/Users', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name'                  => $data['name'],
            'email'                 => $data['email'],
            'password'              => $data['password'],
            'is_active'             => true,
            'onboarding_completed'  => true, // el wizard es solo para el primer admin
        ]);

        $user->assignRole($data['role']);

        return back()->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role'     => ['required', 'string', Rule::exists('roles', 'name')],
            'is_active'=> 'boolean',
        ]);

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'is_active' => $data['is_active'] ?? $user->is_active,
            ...(!empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', "Usuario {$user->name} actualizado correctamente.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'No puedes desactivar tu propio usuario.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('success', "Usuario {$user->name} desactivado.");
    }
}
