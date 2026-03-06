<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Datos de la empresa
            'company_name'     => ['required', 'string', 'min:3', 'max:150'],
            'company_nit'      => ['required', 'string', 'regex:/^\d{6,15}(-\d)?$/', 'max:20'],
            'company_slug'     => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:domains,domain'],

            // Datos del administrador inicial
            'admin_name'       => ['required', 'string', 'max:100'],
            'admin_email'      => ['required', 'email', 'max:150'],
            'admin_password'   => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Plan seleccionado
            'plan_slug'        => ['required', 'string', 'exists:plans,slug'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required'   => 'El nombre de la empresa es obligatorio.',
            'company_name.min'        => 'El nombre debe tener al menos 3 caracteres.',
            'company_nit.required'    => 'El NIT es obligatorio.',
            'company_nit.regex'       => 'El NIT debe tener formato válido (ej: 900123456 o 900123456-1).',
            'company_slug.required'   => 'El identificador de empresa es obligatorio.',
            'company_slug.alpha_dash' => 'El identificador solo puede contener letras, números y guiones.',
            'company_slug.unique'     => 'Este identificador ya está en uso. Elige otro.',
            'admin_name.required'     => 'El nombre del administrador es obligatorio.',
            'admin_email.required'    => 'El correo del administrador es obligatorio.',
            'admin_email.email'       => 'Ingresa un correo electrónico válido.',
            'admin_password.required' => 'La contraseña es obligatoria.',
            'admin_password.confirmed'=> 'Las contraseñas no coinciden.',
            'admin_password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'plan_slug.required'      => 'Selecciona un plan.',
            'plan_slug.exists'        => 'El plan seleccionado no existe.',
        ];
    }
}
