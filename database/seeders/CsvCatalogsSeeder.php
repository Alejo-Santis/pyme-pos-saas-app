<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga catálogos masivos desde archivos CSV (tab-separated, sin cabecera).
 * Archivos en database/csv/ — datos oficiales DANE / DIAN.
 *
 * Orden obligatorio: countries → departments → municipalities
 * (relaciones FK entre ellas)
 */
class CsvCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        // Geográficos (orden importa por FKs) — se omiten si ya tienen datos
        if (DB::table('countries')->count() === 0) {
            $this->seedCountries();
        }
        if (DB::table('departments')->count() === 0) {
            $this->seedDepartments();
        }
        if (DB::table('municipalities')->count() === 0) {
            $this->seedMunicipalities();
        }

        // Catálogos DIAN masivos — se omiten si ya tienen datos
        if (DB::table('unit_measures')->count() === 0) {
            $this->seedSimple('unit_measures', ['id', 'name', 'code']);
        }
        if (DB::table('payment_methods')->count() === 0) {
            $this->seedSimple('payment_methods', ['id', 'name', 'code']);
        }
        if (DB::table('type_currencies')->count() === 0) {
            $this->seedSimple('type_currencies', ['id', 'name', 'code']);
        }
        if (DB::table('taxes')->count() === 0) {
            $this->seedTaxes();
        }

        // Reset de secuencias PostgreSQL (IDs fueron insertados explícitamente)
        $this->resetSequences([
            'countries', 'departments', 'municipalities',
            'unit_measures', 'payment_methods', 'type_currencies', 'taxes',
        ]);
    }

    // ------------------------------------------------------------------ //
    // GEOGRÁFICOS
    // ------------------------------------------------------------------ //

    private function seedCountries(): void
    {
        // Formato CSV: id \t name \t code
        $rows = $this->parseCsv('countries', function (array $parts): ?array {
            if (count($parts) < 3) return null;
            return [
                'id'   => (int) $parts[0],
                'name' => $parts[1],
                'code' => $parts[2],
            ];
        });

        $this->insertChunked('countries', $rows);
    }

    private function seedDepartments(): void
    {
        // Formato CSV: id \t country_id \t name \t code
        $rows = $this->parseCsv('departments', function (array $parts): ?array {
            if (count($parts) < 4) return null;
            return [
                'id'         => (int) $parts[0],
                'country_id' => (int) $parts[1],
                'name'       => $parts[2],
                'code'       => $parts[3],
            ];
        });

        $this->insertChunked('departments', $rows);
    }

    private function seedMunicipalities(): void
    {
        // Formato CSV: id \t department_id \t name \t code \t codefacturador
        $rows = $this->parseCsv('municipalities', function (array $parts): ?array {
            if (count($parts) < 4) return null;
            return [
                'id'              => (int) $parts[0],
                'department_id'   => (int) $parts[1],
                'name'            => $parts[2],
                'code'            => $parts[3],
                'codefacturador'  => isset($parts[4]) && $parts[4] !== '' ? $parts[4] : null,
            ];
        });

        $this->insertChunked('municipalities', $rows, chunkSize: 1000);
    }

    // ------------------------------------------------------------------ //
    // CATÁLOGOS DIAN SIMPLES (id, name, code)
    // ------------------------------------------------------------------ //

    private function seedSimple(string $table, array $columns): void
    {
        $rows = $this->parseCsv($table, function (array $parts) use ($columns): ?array {
            if (count($parts) < count($columns)) return null;
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = $parts[$i];
            }
            if (isset($row['id'])) $row['id'] = (int) $row['id'];
            return $row;
        });

        $this->insertChunked($table, $rows);
    }

    private function seedTaxes(): void
    {
        // Formato CSV variable:
        //   id \t name \t description \t code  (4 columnas)
        //   id \t name \t code                 (3 columnas — sin description)
        $rows = $this->parseCsv('taxes', function (array $parts): ?array {
            if (count($parts) === 4) {
                return [
                    'id'          => (int) $parts[0],
                    'name'        => $parts[1],
                    'description' => $parts[2],
                    'code'        => $parts[3],
                ];
            } elseif (count($parts) === 3) {
                return [
                    'id'          => (int) $parts[0],
                    'name'        => $parts[1],
                    'description' => null,
                    'code'        => $parts[2],
                ];
            }
            return null;
        });

        $this->insertChunked('taxes', $rows);
    }

    // ------------------------------------------------------------------ //
    // HELPERS
    // ------------------------------------------------------------------ //

    /**
     * Lee un CSV tab-separated y aplica una función transformadora por línea.
     *
     * @param  callable(array): ?array  $transform
     * @return array<int, array>
     */
    private function parseCsv(string $filename, callable $transform): array
    {
        $path = database_path("csv/{$filename}.csv");

        if (! file_exists($path)) {
            $this->command->warn("CSV no encontrado: {$path}");
            return [];
        }

        $handle = fopen($path, 'r');
        $rows   = [];
        $now    = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $parts = array_map('trim', explode("\t", $line));
            $row   = $transform($parts);

            if ($row !== null) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Inserta en lotes para no saturar memoria con tablas grandes (municipios ~1120 registros).
     *
     * @param array<int, array> $rows
     */
    private function insertChunked(string $table, array $rows, int $chunkSize = 500): void
    {
        if (empty($rows)) return;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Resetea las secuencias de PostgreSQL después de insertar con IDs explícitos.
     *
     * @param string[] $tables
     */
    private function resetSequences(array $tables): void
    {
        foreach ($tables as $table) {
            DB::statement(
                "SELECT setval('{$table}_id_seq', (SELECT COALESCE(MAX(id), 1) FROM \"{$table}\"))"
            );
        }
    }
}
