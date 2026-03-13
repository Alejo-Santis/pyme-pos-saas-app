<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro de intentos de envío electrónico a la DIAN.
 * Tabla: sending_electronic_documents
 */
class SendingElectronicDocument extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sending_electronic_documents';

    protected $fillable = [
        'document_id',
        'event_id',
        'error_message',
        'is_valid',
        'is_invoice',
        'is_event',
        'is_payroll',
        'is_nc',
        'is_nd',
        'is_ds',
        'is_nds',
        'is_eqdocs',
        'status_code',
        'status_description',
        'status_message',
        'xml_document_key',
        'qr_str',
        'response_api',
        'dian_validation_date_time',
        'attempts',
        'status',
    ];

    protected $casts = [
        'error_message'             => 'array',
        'response_api'              => 'array',
        'dian_validation_date_time' => 'array',
        'is_valid'                  => 'boolean',
        'is_invoice'                => 'boolean',
        'is_event'                  => 'boolean',
        'is_payroll'                => 'boolean',
        'is_nc'                     => 'boolean',
        'is_nd'                     => 'boolean',
        'is_ds'                     => 'boolean',
        'is_nds'                    => 'boolean',
        'is_eqdocs'                 => 'boolean',
        'status'                    => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
