<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea el usuario super-admin inicial del panel landlord.
 * Ejecutar: php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@pymepossaas.test');
        $name = env('SUPER_ADMIN_NAME', 'Super Admin');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $password && app()->environment(['local', 'testing'])) {
            $password = 'AdminLocal-' . Str::random(16);
            $this->command?->warn('SUPER_ADMIN_PASSWORD no está definido. Se generó una contraseña temporal para entorno local/testing.');
            $this->command?->warn("Contraseña temporal: {$password}");
        }

        if (! $password) {
            throw new \RuntimeException('Define SUPER_ADMIN_PASSWORD en .env antes de ejecutar SuperAdminSeeder.');
        }

        $admin = AdminUser::firstOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make($password),
                'is_active' => true,
            ]
        );

        if (! $admin->wasRecentlyCreated) {
            $this->command?->info("Super-admin ya existe: {$email}");
            return;
        }

        $this->command?->info("Super-admin creado: {$email}");
    }
}
