<?php

namespace App\Modules\Invoice\DTO;

use App\Shared\Traits\ToolTrait;

/**
 * DTO del cliente (receptor) para el JSON UBL 2.1 de Nextpyme.
 */
class Customer
{
    use ToolTrait;

    protected array $data;

    public function __construct(array $data, int $typeDocumentOperationId = 1)
    {
        $this->data = $this->normalize($data, $typeDocumentOperationId);
    }

    protected function normalize(array $data, int $typeDocumentOperationId): array
    {
        $customer = [];

        if (isset($data['identification_number'])) {
            $customer['identification_number'] = $data['identification_number'];
        }

        // Nombre del cliente (business_name o nombre + apellido)
        $customer['name'] = $data['business_name']
            ?? trim(($data['name'] ?? '') . ' ' . ($data['surname'] ?? ''))
            ?: 'Consumidor Final';

        if (isset($data['email']))   $customer['email']   = $data['email'];
        if (isset($data['phone']))   $customer['phone']   = $data['phone'];
        if (isset($data['address'])) $customer['address'] = $data['address'];

        // Dígito de verificación
        if (isset($data['dv'])) {
            $customer['dv'] = $data['dv'];
        } elseif (
            isset($data['identification_number'], $data['type_document_identification_id'])
            && $data['type_document_identification_id'] == 6  // NIT
        ) {
            $customer['dv'] = $this->calculateVerificationDigit($data['identification_number']);
        }

        if (isset($data['type_document_identification_id'])) {
            $customer['type_document_identification_id'] = $data['type_document_identification_id'];
        }
        if (isset($data['municipality_id'])) {
            $customer['municipality_id'] = $data['municipality_id'];
        }
        if (isset($data['type_regime_id'])) {
            $customer['type_regime_id'] = $data['type_regime_id'];
        }
        if (isset($data['type_organization_id'])) {
            $customer['type_organization_id'] = $data['type_organization_id'];
        }
        if (isset($data['type_liability_id'])) {
            $customer['type_liability_id'] = $data['type_liability_id'];
        }

        return $customer;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
