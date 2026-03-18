<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan Único de Cuentas (PUC) Colombia.
 * El código determina el nivel jerárquico:
 *   1 dígito  → Clase (ej: "1" = Activo)
 *   2 dígitos → Grupo (ej: "13" = Deudores)
 *   4 dígitos → Cuenta (ej: "1305" = Clientes)
 *   6 dígitos → Subcuenta
 *   8+ dígitos → Auxiliar (único nivel con movimientos)
 */
class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code', 'name', 'class', 'level',
        'parent_code', 'nature', 'allows_movement', 'state',
    ];

    protected $casts = [
        'allows_movement' => 'boolean',
        'state'           => 'boolean',
    ];

    // Nombre de la clase según el primer dígito del código
    public static function className(int $class): string
    {
        return match ($class) {
            1 => 'Activo',
            2 => 'Pasivo',
            3 => 'Patrimonio',
            4 => 'Ingresos',
            5 => 'Gastos',
            6 => 'Costos de venta',
            7 => 'Costos de producción',
            8 => 'Cuentas de orden deudoras',
            9 => 'Cuentas de orden acreedoras',
            default => 'Desconocido',
        };
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code');
    }
}
