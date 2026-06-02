<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comprobante contable (asiento de diario).
 * Se genera automáticamente al crear facturas, compras, NC, etc.
 */
class AccountingDocument extends Model
{
    use HasUuids;

    protected $table = 'accounting_documents';

    protected $fillable = [
        'uuid',
        'internal_code',
        'user_id',
        'third_party_id',
        'document_id',
        'type_document_operation_id',
        'prefix',
        'number',
        'debit',
        'credit',
        'total',
        'notes',
        'issue_date',
        'annulled',
        'reversed_at',
        'reversed_by_accounting_document_id',
    ];

    protected $casts = [
        'debit'    => 'decimal:4',
        'credit'   => 'decimal:4',
        'total'    => 'decimal:4',
        'annulled' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingDocumentDetail::class, 'accounting_document_id');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_accounting_document_id');
    }
}
