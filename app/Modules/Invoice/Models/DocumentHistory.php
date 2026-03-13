<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de eventos de un documento (creación, envío DIAN, anulación, etc.)
 * Tabla: document_histories — id es bigIncrements (auto-increment), NO uuid
 */
class DocumentHistory extends Model
{

    protected $table = 'document_histories';

    protected $fillable = [
        'document_id',
        'user_id',
        'history_issue_date',
        'notes',
        'history',
    ];

    protected $casts = [
        'history_issue_date' => 'datetime',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
