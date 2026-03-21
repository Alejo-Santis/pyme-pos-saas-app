<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
            )
            ->when($request->role, fn ($q, $r) =>
                $q->whereHas('roles', fn ($rq) => $rq->where('name', $r))
            )
            ->when($request->status !== null && $request->status !== '', fn ($q) =>
                $q->where('is_active', filter_var($request->status, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($user) => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone ?? null,
                'is_active'  => $user->is_active,
                'roles'      => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'roles'   => Role::orderBy('name')->pluck('name'),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user'  => null,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role'     => 'required|string|exists:roles,name',
            'is_active'=> 'boolean',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Form', [
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'phone'     => $user->phone ?? null,
                'is_active' => $user->is_active,
                'role'      => $user->roles->first()?->name ?? '',
            ],
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => "required|email|max:255|unique:users,email,{$user->id}",
            'phone'    => 'nullable|string|max:20',
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'role'     => 'required|string|exists:roles,name',
            'is_active'=> 'boolean',
        ]);

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        // No permitir eliminar al propio usuario
        if ($user->id === auth()->id()) {
            return back()->withErrors(['general' => 'No puedes eliminar tu propio usuario.']);
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['general' => 'No puedes desactivar tu propio usuario.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.');
    }
}
