<?php

namespace App\Modules\Invoice\Models;

use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de detalle de un documento (ítem facturado).
 * Tabla: documents_details (según migración existente).
 */
class DocumentLine extends Model
{
    protected $table = 'documents_details';

    // La tabla usa bigIncrements (no UUID), por lo que no se usa HasUuids aquí.
    protected $fillable = [
        'document_id',
        'item_id',
        'description',
        'item_note',
        'amount',
        'cost_value',
        'sale_price',
        'discount',
        'taxable_amount',
        'tax_amount',
        'taxes',
        'unit_measure_id',
        'warehouse_out',
        'warehouse_in',
        'movement_type',
        'state',
        'annulled',
    ];

    protected $casts = [
        'amount'         => 'decimal:4',
        'cost_value'     => 'decimal:4',
        'sale_price'     => 'decimal:4',
        'discount'       => 'decimal:4',
        'taxable_amount' => 'decimal:4',
        'tax_amount'     => 'decimal:4',
        'taxes'          => 'array',
        'annulled'       => 'boolean',
        'state'          => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
