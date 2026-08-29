<?php

namespace App\Modules\Inventory\Imports;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\ItemTax;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemImport implements ToCollection, WithHeadingRow
{
    public int   $imported = 0;
    public array $errors   = [];

    // Catálogos en memoria
    private array $unitMeasures = [];
    private array $taxes        = [];

    public function __construct()
    {
        $this->unitMeasures = DB::table('unit_measures')
            ->pluck('id', 'code')
            ->toArray();

        // Índice por porcentaje: '0' => id, '5' => id, '19' => id.
        // `percent` es numeric(x,4) en BD y llega como string "19.0000" — se
        // normaliza a entero ("19") para que matchee con $ivaPct en processRow().
        $rawTaxes = DB::table('taxes')
            ->where('tax_type', 'IVA')
            ->pluck('id', 'percent');

        foreach ($rawTaxes as $percent => $id) {
            $this->taxes[(string) (int) round((float) $percent)] = $id;
        }
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
        $row = array_map('trim', $row);

        $name         = $row['nombre']          ?? '';
        $shortName    = $row['nombre_corto']     ?? '';
        $internalCode = $row['codigo_interno']   ?? '';
        $type         = strtolower($row['tipo']  ?? 'product'); // product|service|combo
        $categoryName = $row['categoria']        ?? '';
        $unitCode     = strtoupper($row['unidad_medida'] ?? 'UN');
        $salePrice    = $this->parseNum($row['precio_venta']    ?? 0);
        $cost         = $this->parseNum($row['costo_promedio']  ?? 0);
        $minStock     = $this->parseNum($row['stock_minimo']    ?? 0);
        $ivaPct       = (string) ((int) $this->parseNum($row['porcentaje_iva'] ?? 0));
        $managesStock = !($type === 'service');

        if (empty($name)) {
            $this->errors[] = "Fila {$rowNum}: El campo 'nombre' es obligatorio.";
            return;
        }

        $validTypes = ['product', 'producto', 'service', 'servicio', 'combo'];
        if (!in_array($type, $validTypes)) {
            $this->errors[] = "Fila {$rowNum}: Tipo '{$type}' inválido. Use: producto, servicio o combo.";
            return;
        }

        // Normalizar tipo
        $type = match($type) {
            'producto' => 'product',
            'servicio' => 'service',
            default    => $type,
        };

        // Resolver categoría
        $categoryId = null;
        if (!empty($categoryName)) {
            $category = ItemCategory::firstOrCreate(
                ['name' => $categoryName],
                ['is_active' => true]
            );
            $categoryId = $category->id;
        }

        // Resolver unidad de medida
        $unitMeasureId = $this->unitMeasures[$unitCode]
            ?? ($this->unitMeasures['UN'] ?? null);

        // Código interno: auto-generado si no viene
        if (empty($internalCode)) {
            $last = Item::withTrashed()
                ->where('internal_code', 'like', 'ART-%')
                ->orderByDesc('internal_code')
                ->value('internal_code');
            $next = $last ? ((int) substr($last, 4)) + 1 : 1;
            $internalCode = 'ART-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        } elseif (Item::where('internal_code', $internalCode)->exists()) {
            $this->errors[] = "Fila {$rowNum}: El código '{$internalCode}' ya existe.";
            return;
        }

        DB::transaction(function () use (
            $name, $shortName, $internalCode, $type, $categoryId,
            $unitMeasureId, $salePrice, $cost, $minStock, $ivaPct, $managesStock
        ) {
            $item = Item::create([
                'name'               => $name,
                'short_name'         => $shortName  ?: null,
                'internal_code'      => $internalCode,
                'type'               => $type,
                'item_category_id'   => $categoryId,
                'unit_measure_id'    => $unitMeasureId,
                'default_sale_price' => $salePrice,
                'average_cost'       => $cost,
                'last_purchase_price'=> $cost,
                'minimum_existence'  => $minStock,
                'manages_stock'      => $managesStock,
                'is_service'         => $type === 'service',
                'is_active'          => true,
            ]);

            // Impuesto IVA si corresponde
            $taxId = $this->taxes[$ivaPct] ?? ($this->taxes['0'] ?? null);
            if ($taxId) {
                ItemTax::create([
                    'item_id'     => $item->id,
                    'tax_id'      => $taxId,
                    'application' => 3, // 1=venta, 2=compra, 3=ambos — sin más contexto se aplica a ambos
                ]);
            }
        });

        $this->imported++;
    }

    private function parseNum(mixed $val): float
    {
        // Limpiar separadores de miles colombianos (puntos) y decimales (comas)
        $val = str_replace(['.', ',', '$', ' '], ['', '.', '', ''], (string) $val);
        return (float) $val;
    }
}
