<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Modules\Admin\Services\LandlordAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private LandlordAuditService $audit)
    {
    }

    public function index(Request $request): Response
    {
        $admins = AdminUser::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
            )
            ->when($request->status !== null && $request->status !== '', fn ($q) =>
                $q->where('is_active', request()->boolean('status'))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AdminUser $admin) => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'is_active' => $admin->is_active,
                'last_login_at' => $admin->last_login_at?->format('d/m/Y H:i'),
                'created_at' => $admin->created_at?->format('d/m/Y H:i'),
                'is_current' => $admin->id === Auth::guard('admin')->id(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'admins' => $admins,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:admin_users,email',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        $admin = AdminUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->record('created', 'admin_users', $admin, [], [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => $admin->is_active,
        ]);

        return back()->with('success', 'Administrador creado correctamente.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $admin = AdminUser::findOrFail($id);
        $oldValues = $admin->only(['name', 'email', 'is_active']);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => [
                'required',
                'email',
                'max:160',
                Rule::unique('admin_users', 'email')->ignore($admin->id),
            ],
            'is_active' => 'boolean',
        ]);

        if ($admin->id === Auth::guard('admin')->id() && ! $request->boolean('is_active')) {
            return back()->with('error', 'No puedes desactivar tu propio usuario.');
        }

        $admin->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->record('updated', 'admin_users', $admin, $oldValues, $admin->only(['name', 'email', 'is_active']));

        return back()->with('success', 'Administrador actualizado correctamente.');
    }

    public function updatePassword(Request $request, string $id): RedirectResponse
    {
        $admin = AdminUser::findOrFail($id);

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin->update(['password' => Hash::make($data['password'])]);

        $this->audit->record('password_updated', 'admin_users', $admin, [], [
            'email' => $admin->email,
        ]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    public function toggle(Request $request, string $id): RedirectResponse
    {
        $admin = AdminUser::findOrFail($id);

        if ($admin->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'No puedes desactivar tu propio usuario.');
        }

        $oldValues = $admin->only(['is_active']);
        $admin->update(['is_active' => ! $admin->is_active]);

        $this->audit->record('status_toggled', 'admin_users', $admin, $oldValues, [
            'is_active' => $admin->is_active,
        ]);

        return back()->with('success', 'Estado del administrador actualizado.');
    }
}
