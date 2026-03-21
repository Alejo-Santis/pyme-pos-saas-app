<?php

namespace App\Modules\Audit\Models;

use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasUuids;

    protected $table = 'api_logs';

    public $timestamps = false;
    public $updatedAt  = null;

    protected $fillable = [
        'document_id',
        'provider',
        'operation',
        'endpoint',
        'method',
        'request_payload',
        'http_status',
        'response_body',
        'success',
        'error_message',
        'attempt',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_body'   => 'array',
        'success'         => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
