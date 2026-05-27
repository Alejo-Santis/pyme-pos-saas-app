<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupProgressService
{
    public function progress(): array
    {
        $steps = $this->steps();
        $completed = collect($steps)->where('completed', true)->count();
        $total = count($steps);

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'steps' => $steps,
            'next_step' => collect($steps)->firstWhere('completed', false),
        ];
    }

    public function steps(): array
    {
        return [
            $this->step(
                key: 'company',
                title: 'Datos de empresa',
                description: 'Completa la información tributaria, dirección y contacto de la empresa.',
                href: '/config/company',
                icon: 'mdi-domain',
                completed: $this->count('companies') > 0,
                action: 'Revisar empresa',
                group: 'Configuración'
            ),
            $this->step(
                key: 'dian_resolution',
                title: 'Resolución DIAN',
                description: 'Configura la resolución de facturación electrónica o déjala pendiente si solo estás probando.',
                href: '/config/resolutions',
                icon: 'mdi-file-certificate-outline',
                completed: $this->count('resolutions', fn ($q) => $q->where('type_document_operation_id', 1)->where('is_active', true)) > 0,
                action: 'Configurar DIAN',
                group: 'Configuración'
            ),
            $this->step(
                key: 'warehouse',
                title: 'Bodega principal',
                description: 'Crea al menos una bodega para manejar inventario y movimientos de productos.',
                href: '/config/warehouses',
                icon: 'mdi-warehouse',
                completed: $this->count('warehouses') > 0,
                action: 'Crear bodega',
                group: 'Operación'
            ),
            $this->step(
                key: 'cash_box',
                title: 'Caja principal',
                description: 'Define una caja para registrar pagos, ventas POS y cierres de turno.',
                href: '/cash',
                icon: 'mdi-cash-register',
                completed: $this->count('cash_boxes', fn ($q) => $q->where('state', true)) > 0,
                action: 'Configurar caja',
                group: 'Operación'
            ),
            $this->step(
                key: 'pos_terminal',
                title: 'Terminal POS',
                description: 'Crea una terminal para vender por mostrador, abrir turnos y emitir recibos.',
                href: '/pos',
                icon: 'mdi-point-of-sale',
                completed: $this->count('pos_terminals', fn ($q) => $q->where('state', true)) > 0,
                action: 'Configurar POS',
                group: 'Operación'
            ),
            $this->step(
                key: 'third_parties',
                title: 'Clientes y proveedores',
                description: 'Crea o importa terceros para facturar, comprar y llevar cartera.',
                href: '/third-parties',
                icon: 'mdi-account-group-outline',
                completed: $this->count('third_parties') > 0,
                action: 'Importar terceros',
                group: 'Datos maestros',
                secondary_href: '/third-parties/import/template'
            ),
            $this->step(
                key: 'items',
                title: 'Productos y servicios',
                description: 'Carga tu catálogo inicial para vender, comprar y controlar inventario.',
                href: '/inventory',
                icon: 'mdi-package-variant-closed',
                completed: $this->count('items') > 0,
                action: 'Importar productos',
                group: 'Datos maestros',
                secondary_href: '/inventory/import/template'
            ),
            $this->step(
                key: 'users',
                title: 'Usuarios y permisos',
                description: 'Invita a tu equipo y asigna roles para separar ventas, inventario, caja y administración.',
                href: '/users',
                icon: 'mdi-account-multiple-outline',
                completed: $this->count('users', fn ($q) => $q->where('is_active', true)) > 1,
                action: 'Invitar usuarios',
                group: 'Equipo'
            ),
            $this->step(
                key: 'first_document',
                title: 'Primera factura o venta POS',
                description: 'Registra la primera venta para comprobar que facturación, pagos y contabilidad trabajan juntos.',
                href: '/invoices/create',
                icon: 'mdi-file-plus-outline',
                completed: $this->count('documents', fn ($q) => $q->where('annulled', false)) > 0,
                action: 'Crear venta',
                group: 'Primer uso'
            ),
            $this->step(
                key: 'employees',
                title: 'Empleados para nómina',
                description: 'Si usarás nómina, importa empleados y contratos antes de liquidar periodos.',
                href: '/payroll/employees',
                icon: 'mdi-account-hard-hat-outline',
                completed: $this->count('employees', fn ($q) => $q->where('state', true)) > 0,
                action: 'Importar empleados',
                group: 'Nómina',
                optional: true,
                secondary_href: '/payroll/employees/import/template'
            ),
        ];
    }

    private function step(
        string $key,
        string $title,
        string $description,
        string $href,
        string $icon,
        bool $completed,
        string $action,
        string $group,
        bool $optional = false,
        ?string $secondary_href = null,
    ): array {
        return compact('key', 'title', 'description', 'href', 'icon', 'completed', 'action', 'group', 'optional', 'secondary_href');
    }

    private function count(string $table, ?callable $constraint = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if ($constraint) {
            $constraint($query);
        }

        return $query->count();
    }
}
