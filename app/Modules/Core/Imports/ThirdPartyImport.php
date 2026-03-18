<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Models\PartyLinkage;
use App\Modules\Core\Models\ThirdParty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ThirdPartyImport implements ToCollection, WithHeadingRow
{
    public int   $imported = 0;
    public array $errors   = [];

    // Catálogo en memoria para no hacer N queries por fila
    private array $docTypes = [];
    private array $orgTypes = [];

    public function __construct()
    {
        $this->docTypes = DB::table('type_document_identifications')
            ->pluck('id', 'code')
            ->toArray();

        $this->orgTypes = DB::table('type_organizations')
            ->pluck('id', 'code')
            ->toArray();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            try {
                $this->processRow($row->toArray(), $rowNum);
            } catch (\Throwable $e) {
                $this->errors[] = "Fila {$rowNum}: " . $e->getMessage();
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        // Normalizar claves (quitar espacios, lowercase)
        $row = array_map('trim', $row);

        $docCode   = strtoupper($row['tipo_documento'] ?? '');
        $idNumber  = $row['numero_documento']   ?? '';
        $orgCode   = strtoupper($row['tipo_persona'] ?? 'N'); // N=Natural, J=Juridica
        $name      = $row['nombre_razon_social'] ?? '';
        $surname   = $row['apellidos']           ?? '';
        $email     = $row['email']               ?? '';
        $phone     = $row['telefono']            ?? '';
        $address   = $row['direccion']           ?? '';
        $city      = $row['ciudad']              ?? '';
        $customer  = $this->parseBool($row['es_cliente']   ?? '');
        $provider  = $this->parseBool($row['es_proveedor'] ?? '');
        $dv        = $row['digito_verificacion'] ?? null;

        if (empty($idNumber) || empty($name)) {
            $this->errors[] = "Fila {$rowNum}: numero_documento y nombre_razon_social son obligatorios.";
            return;
        }

        if (ThirdParty::where('identification_number', $idNumber)->exists()) {
            $this->errors[] = "Fila {$rowNum}: El tercero con documento {$idNumber} ya existe.";
            return;
        }

        $docTypeId = $this->docTypes[$docCode] ?? null;
        $orgTypeId = $this->orgTypes[$orgCode] ?? ($this->orgTypes['N'] ?? null);

        // Régimen y responsabilidad por defecto
        $regimeId    = DB::table('type_regimes')->value('id');
        $liabilityId = DB::table('type_liabilities')->value('id');

        DB::transaction(function () use (
            $idNumber, $dv, $docTypeId, $orgTypeId, $regimeId, $liabilityId,
            $name, $surname, $email, $phone, $address, $city, $customer, $provider
        ) {
            $third = ThirdParty::create([
                'identification_number'          => $idNumber,
                'check_digit'                    => $dv,
                'type_document_identification_id'=> $docTypeId,
                'type_organization_id'           => $orgTypeId,
                'type_regime_id'                 => $regimeId,
                'type_liability_id'              => $liabilityId,
                'name'                           => $name,
                'surname'                        => $surname ?: null,
                'email'                          => $email    ?: null,
                'phone'                          => $phone    ?: null,
                'address'                        => $address  ?: null,
                'city'                           => $city     ?: null,
                'is_active'                      => true,
            ]);

            PartyLinkage::create([
                'third_party_id' => $third->id,
                'customer'       => $customer,
                'provider'       => $provider,
                'other'          => !$customer && !$provider,
            ]);
        });

        $this->imported++;
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtoupper(trim($value)), ['SI', 'SÍ', 'S', 'YES', 'Y', '1', 'TRUE']);
    }
}
