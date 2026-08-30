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

    // Siglas que la plantilla le pide al usuario → código numérico DIAN real.
    // La tabla type_document_identifications guarda el código DIAN ('13', '31'...)
    // en su columna `code`, no la sigla ('CC', 'NIT'...), así que hay que traducir.
    private const DOC_TYPE_DIAN_CODES = [
        'RC' => '11', 'TI' => '12', 'CC' => '13', 'TE' => '21', 'CE' => '22',
        'NIT' => '31', 'PASAPORTE' => '41', 'PEP' => '47', 'PPT' => '48', 'NUIP' => '91',
    ];

    // Catálogo en memoria para no hacer N queries por fila
    private array $docTypes = [];
    private array $orgTypes = [];

    public function __construct()
    {
        $dianCodeToId = DB::table('type_document_identifications')
            ->pluck('id', 'code')
            ->toArray();

        foreach (self::DOC_TYPE_DIAN_CODES as $abbr => $dianCode) {
            if (isset($dianCodeToId[$dianCode])) {
                $this->docTypes[$abbr] = $dianCodeToId[$dianCode];
            }
        }

        // Igual que arriba: la plantilla pide N/J, pero type_organizations
        // guarda '1'/'2' en `code` — se busca por nombre en vez de por código.
        $this->orgTypes = [
            'J' => DB::table('type_organizations')->where('name', 'Persona Jurídica')->value('id'),
            'N' => DB::table('type_organizations')->where('name', 'Persona Natural')->value('id'),
        ];
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

        // type_third_id es NOT NULL: se infiere de es_cliente/es_proveedor
        // (catálogo type_thirds: 1=Cliente, 2=Proveedor, 5=Otro)
        $typeThirdId = $customer ? 1 : ($provider ? 2 : 5);

        // "ciudad" en la plantilla es un nombre libre — se resuelve contra el
        // catálogo de municipios; si no hay match, queda sin bodega asociada
        $municipalityId = $city
            ? DB::table('municipalities')->where('name', 'ilike', "%{$city}%")->value('id')
            : null;

        DB::transaction(function () use (
            $idNumber, $dv, $docTypeId, $orgTypeId, $regimeId, $liabilityId, $typeThirdId,
            $name, $surname, $email, $phone, $address, $municipalityId, $customer, $provider
        ) {
            $third = ThirdParty::create([
                'identification_number'          => $idNumber,
                'dv'                              => $dv ?: null,
                'type_document_identification_id'=> $docTypeId,
                'type_organization_id'           => $orgTypeId,
                'type_regime_id'                 => $regimeId,
                'type_liability_id'              => $liabilityId,
                'type_third_id'                  => $typeThirdId,
                'name'                           => $name,
                'surname'                        => $surname ?: null,
                'email'                          => $email    ?: null,
                'phone'                          => $phone    ?: null,
                'address'                        => $address  ?: null,
                'municipality_id'                => $municipalityId,
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
