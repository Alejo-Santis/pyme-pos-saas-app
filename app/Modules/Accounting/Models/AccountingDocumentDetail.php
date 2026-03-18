<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un comprobante contable (débito o crédito a una cuenta del PUC).
 * La regla de oro: sum(debit) === sum(credit) en cada comprobante.
 */
class AccountingDocumentDetail extends Model
{
    protected $table = 'accounting_documents_details';

    protected $fillable = [
        'accounting_document_id',
        'accountable_id',
        'accountable_type',
        'third_party_id',
        'cost_center_id',
        'document_number',
        'taxable_amount',
        'debit',
        'credit',
        'issue_date',
    ];

    protected $casts = [
        'taxable_amount' => 'decimal:4',
        'debit'          => 'decimal:4',
        'credit'         => 'decimal:4',
    ];

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }
}
