<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el usuario super-admin inicial del panel landlord.
 * Ejecutar: php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::firstOrCreate(
            ['email' => 'admin@nextpossaas.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('Admin@2026!'),
                'is_active' => true,
            ]
        );

        $this->command->info('Super-admin creado: admin@nextpossaas.com / Admin@2026!');
    }
}
