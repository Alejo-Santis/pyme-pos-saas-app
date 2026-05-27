<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\Password;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Datos de la empresa
            'company_name'     => ['required', 'string', 'min:3', 'max:150'],
            'company_nit'      => ['required', 'string', 'regex:/^\d{6,15}(-\d)?$/', 'max:20'],
            'company_slug'     => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/'],

            // Datos del administrador inicial
            'admin_name'       => ['required', 'string', 'max:100'],
            'admin_email'      => ['required', 'email', 'max:150'],
            'admin_password'   => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Plan seleccionado
            'plan_slug'        => ['required', 'string', 'exists:plans,slug'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('company_slug')) {
            $this->merge([
                'company_slug' => str($this->input('company_slug'))
                    ->lower()
                    ->ascii()
                    ->replaceMatches('/[^a-z0-9-]+/', '-')
                    ->replaceMatches('/-+/', '-')
                    ->trim('-')
                    ->toString(),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $domain = $this->tenantDomain($this->input('company_slug'));

            if ($domain === trim((string) env('CENTRAL_DOMAIN', ''), '.')) {
                $validator->errors()->add('company_slug', 'Este identificador está reservado para el dominio principal.');
            }

            if ($domain && Domain::where('domain', $domain)->exists()) {
                $validator->errors()->add('company_slug', 'Este identificador ya está en uso. Elige otro.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'company_name.required'   => 'El nombre de la empresa es obligatorio.',
            'company_name.min'        => 'El nombre debe tener al menos 3 caracteres.',
            'company_nit.required'    => 'El NIT es obligatorio.',
            'company_nit.regex'       => 'El NIT debe tener formato válido (ej: 900123456 o 900123456-1).',
            'company_slug.required'   => 'El identificador de empresa es obligatorio.',
            'company_slug.regex'      => 'El identificador solo puede contener letras, números y guiones, sin guiones al inicio o al final.',
            'admin_name.required'     => 'El nombre del administrador es obligatorio.',
            'admin_email.required'    => 'El correo del administrador es obligatorio.',
            'admin_email.email'       => 'Ingresa un correo electrónico válido.',
            'admin_password.required' => 'La contraseña es obligatoria.',
            'admin_password.confirmed'=> 'Las contraseñas no coinciden.',
            'admin_password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'admin_password.letters'  => 'La contraseña debe contener al menos una letra.',
            'admin_password.numbers'  => 'La contraseña debe contener al menos un número.',
            'plan_slug.required'      => 'Selecciona un plan.',
            'plan_slug.exists'        => 'El plan seleccionado no existe.',
        ];
    }

    private function tenantDomain(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        $mode = env('TENANT_DOMAIN_MODE', 'subdomain');

        if ($mode === 'suffix') {
            $suffix = trim((string) env('TENANT_DOMAIN_SUFFIX', env('CENTRAL_DOMAIN', 'pymepossaas-app.test')), '.');

            return "{$slug}.{$suffix}";
        }

        $centralDomain = trim((string) env('CENTRAL_DOMAIN', 'pymepossaas-app.test'), '.');

        return "{$slug}.{$centralDomain}";
    }
}
