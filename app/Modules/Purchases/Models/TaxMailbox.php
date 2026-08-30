<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxMailbox extends Model
{
    use HasUuids;

    protected $table = 'tax_mailboxes';

    protected $fillable = [
        'type_document_id',
        'document_id',
        'identification_number_provider',
        'business_name_provider',
        'subject',
        'xml_file_name',
        'pdf_file_name',
        'date',
        'cufe',
        'tax_inclusive_amount',
        'base64_attacheddocument',
        'events',
        'has_order_reference',
        'order_reference',
        'payment_form',
    ];

    protected $casts = [
        'date'                     => 'datetime',
        'tax_inclusive_amount'     => 'decimal:4',
        'base64_attacheddocument'  => 'array',
        'events'                   => 'array',
        'has_order_reference'      => 'boolean',
        'order_reference'          => 'array',
        'payment_form'             => 'array',
    ];

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('document_id');
    }

    public function scopeProcessed($query)
    {
        return $query->whereNotNull('document_id');
    }
}
