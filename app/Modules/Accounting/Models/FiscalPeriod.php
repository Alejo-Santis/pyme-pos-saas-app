<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Período fiscal contable.
 *
 * Cuando un período está cerrado ('closed'), el motor contable rechaza
 * cualquier intento de registrar asientos con fecha dentro de ese período.
 *
 * Cada empresa maneja sus propios períodos dentro de su schema de tenant.
 */
class FiscalPeriod extends Model
{
    use HasUuids;

    protected $table = 'fiscal_periods';

    protected $fillable = [
        'year',
        'month',
        'name',
        'status',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'year'      => 'integer',
        'month'     => 'integer',
        'closed_at' => 'datetime',
    ];

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Verifica si una fecha dada está dentro de un período cerrado.
     * Usa caché de 5 minutos para no consultar la DB en cada asiento.
     */
    public static function isDateInClosedPeriod(string $date): bool
    {
        $parsed = \Carbon\Carbon::parse($date);
        $year   = $parsed->year;
        $month  = $parsed->month;

        $cacheKey = "fiscal_period_closed_{$year}_{$month}";

        return Cache::remember($cacheKey, 300, function () use ($year, $month) {
            return self::where('year', $year)
                ->where('month', $month)
                ->where('status', self::STATUS_CLOSED)
                ->exists();
        });
    }

    /**
     * Invalida la caché de un período al abrirlo o cerrarlo.
     */
    public static function clearCache(int $year, int $month): void
    {
        Cache::forget("fiscal_period_closed_{$year}_{$month}");
    }

    /**
     * Nombre del mes en español para UI.
     */
    public static function monthName(int $month): string
    {
        return match ($month) {
            1  => 'Enero',   2  => 'Febrero', 3  => 'Marzo',
            4  => 'Abril',   5  => 'Mayo',    6  => 'Junio',
            7  => 'Julio',   8  => 'Agosto',  9  => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            default => "Mes {$month}",
        };
    }
}
