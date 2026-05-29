<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IncomeAndExpense extends Model
{
    use HasUuids;

    protected $table = 'income_and_expenses';

    protected $fillable = [
        'uuid',
        'internal_code',
        'name',
        'description',
        'type_document_operation_id',
        'accountable_id',
        'accountable_type',
        'account_nature_id',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
