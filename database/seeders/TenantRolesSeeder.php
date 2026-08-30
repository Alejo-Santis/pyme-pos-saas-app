<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles y permisos base dentro del schema de cada tenant.
 * Se ejecuta dentro del contexto del tenant (tenants:seed).
 */
class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos (importante con multi-tenancy)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Roles base ────────────────────────────────────────────────────
        $roles = [
            ['name' => 'admin',        'guard_name' => 'web'],
            ['name' => 'contador',     'guard_name' => 'web'],
            ['name' => 'vendedor',     'guard_name' => 'web'],
            ['name' => 'almacenista',  'guard_name' => 'web'],
            ['name' => 'cajero',       'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name'], 'guard_name' => $role['guard_name']]);
        }

        // ── Permisos base (ampliar por módulo en fases siguientes) ────────
        $permissions = [
            // Facturación
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.send',
            // Inventario
            'inventory.view', 'inventory.create', 'inventory.edit',
            // POS
            'pos.view', 'pos.operate',
            // Caja y bancos
            'cash.view', 'cash.edit',
            // Compras
            'purchases.view', 'purchases.create',
            // Buzón tributario
            'tax-mailbox.view',
            // Reportes
            'reports.view',
            // Configuración
            'config.view', 'config.edit',
            // Usuarios
            'users.view', 'users.create', 'users.edit', 'users.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Asignar todos los permisos al admin ───────────────────────────
        $admin = Role::findByName('admin');
        $admin->syncPermissions(Permission::all());

        // ── Permisos del contador ─────────────────────────────────────────
        $contador = Role::findByName('contador');
        $contador->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.send',
            'purchases.view', 'purchases.create',
            'tax-mailbox.view',
            'cash.view', 'cash.edit',
            'reports.view',
        ]);

        // ── Permisos del vendedor ─────────────────────────────────────────
        $vendedor = Role::findByName('vendedor');
        $vendedor->syncPermissions([
            'invoices.view', 'invoices.create',
            'inventory.view',
            'pos.view', 'pos.operate',
            'reports.view',
        ]);

        // ── Permisos del cajero ───────────────────────────────────────────
        $cajero = Role::findByName('cajero');
        $cajero->syncPermissions([
            'pos.view', 'pos.operate',
            'cash.view', 'cash.edit',
        ]);

        // ── Permisos del almacenista ──────────────────────────────────────
        $almacenista = Role::findByName('almacenista');
        $almacenista->syncPermissions([
            'inventory.view', 'inventory.create', 'inventory.edit',
            'purchases.view',
        ]);
    }
}
