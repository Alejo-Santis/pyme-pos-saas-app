<?php

namespace App\Modules\Invoice\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Documento transaccional: factura, nota crédito, nota débito, documento soporte.
 * Vive en el schema del tenant — sin company_id global (intra-tenant FK hacia companies).
 */
class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'uuid',
        'internal_code',
        'company_id',
        'user_id',
        'third_party_id',
        'seller_id',
        'type_document_id',
        'type_document_operation_id',
        'resolution_id',
        'prefix',
        'number',
        'currency_id',
        'subtotal',
        'total_discount',
        'total_tax',
        'total',
        'balance',
        'payment_forms',
        'taxes',
        'issue_date',
        'paid',
        'electronic',
        'accounted',
        'annulled',
        'cufe',
        'qr_code',
        'cashier_shift',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:4',
        'total_discount' => 'decimal:4',
        'total_tax'      => 'decimal:4',
        'total'          => 'decimal:4',
        'balance'        => 'decimal:4',
        'payment_forms'  => 'array',
        'taxes'          => 'array',
        'issue_date'     => 'date',
        'paid'           => 'boolean',
        'electronic'     => 'boolean',
        'accounted'      => 'boolean',
        'annulled'       => 'boolean',
    ];

    // ─── Boot: UUID secundario y código interno ───────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Document $doc) {
            if (empty($doc->uuid)) {
                $doc->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relaciones ───────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_id');
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'resolution_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class, 'document_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentHistory::class, 'document_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(DocumentPaymentMethod::class, 'document_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    /**
     * Documentos pendientes de envío DIAN.
     */
    public function scopePending($query)
    {
        return $query->where('electronic', false)->where('annulled', false);
    }

    /**
     * Documentos ya enviados y validados por DIAN.
     */
    public function scopeSent($query)
    {
        return $query->where('electronic', true);
    }

    /**
     * Filtrar por tipo de documento (type_document_operation_id).
     */
    public function scopeByType($query, int $typeDocumentOperationId)
    {
        return $query->where('type_document_operation_id', $typeDocumentOperationId);
    }

    /**
     * Solo documentos no anulados.
     */
    public function scopeActive($query)
    {
        return $query->where('annulled', false);
    }
}
