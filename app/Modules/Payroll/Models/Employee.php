<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'document_type',
        'identification_number',
        'dv',
        'first_name',
        'middle_name',
        'last_name',
        'second_lastname',
        'email',
        'phone',
        'address',
        'city',
        'department',
        'birthdate',
        'blood_type',
        'gender',
        'marital_status_id',
        'emergency_contact',
        'emergency_phone',
        'state',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'state'     => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name} {$this->second_lastname}");
    }

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function activeContract()
    {
        // No se usa latestOfMany() porque Postgres no tiene MAX(uuid) nativo,
        // y esa relación termina agregando por employee_id (uuid) en vez de start_date.
        return $this->hasOne(EmployeeContract::class)->where('state', true)->orderByDesc('start_date');
    }

    public function novelties()
    {
        return $this->hasMany(PayrollNovelty::class);
    }

    public function socialBenefits()
    {
        return $this->hasMany(PayrollSocialBenefit::class);
    }

    public function scopeActive($query)
    {
        return $query->where('state', true);
    }
}
