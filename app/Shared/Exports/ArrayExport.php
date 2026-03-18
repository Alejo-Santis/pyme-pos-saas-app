<?php

namespace App\Shared\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación genérica a Excel.
 *
 * Uso:
 *   Excel::download(new ArrayExport($rows, $headers, 'Hoja1'), 'archivo.xlsx')
 *
 * $rows: array de arrays (cada fila es un array de valores en el mismo orden que $headers)
 * $headers: array de strings con los títulos de columna
 * $meta: array opcional con líneas de encabezado antes de la tabla (empresa, período, etc.)
 */
class ArrayExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private array  $rows,
        private array  $headers,
        private string $title = 'Reporte',
        private array  $meta  = [],   // ej: ['Empresa: ACME', 'Período: Enero 2025']
    ) {}

    public function array(): array
    {
        $data = [];

        // Líneas de meta-información (empresa, fecha, etc.)
        foreach ($this->meta as $line) {
            $data[] = [$line];
        }

        if (!empty($this->meta)) {
            $data[] = []; // fila vacía separadora
        }

        // Encabezados
        $data[] = $this->headers;

        // Datos
        foreach ($this->rows as $row) {
            $data[] = array_values((array) $row);
        }

        return $data;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        $metaLines  = count($this->meta) + (!empty($this->meta) ? 1 : 0);
        $headerRow  = $metaLines + 1;
        $totalCols  = count($this->headers);
        $lastCol    = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
        $lastRow    = $headerRow + count($this->rows);

        // Fila de encabezado — azul con texto blanco
        $sheet->getStyle("{$lastCol}1")->applyFromArray([]); // touch para inicializar
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF3A5AB5']]],
        ];

        // Filas de datos — alternadas
        $dataStyles = [];
        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $bg = ($r % 2 === 0) ? 'FFF8F9FF' : 'FFFFFFFF';
            $dataStyles["A{$r}:{$lastCol}{$r}"] = [
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFD1D5DB']]],
                'font'    => ['size' => 9],
            ];
        }

        // Meta-info en cursiva
        $metaStyleRange = $metaLines > 0 ? ["A1:A{$metaLines}" => ['font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6B7280']]]] : [];

        return array_merge(
            ["A{$headerRow}:{$lastCol}{$headerRow}" => $headerStyle],
            $dataStyles,
            $metaStyleRange
        );
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (range(1, count($this->headers)) as $i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $widths[$col] = 20;
        }
        // Primera columna más ancha (suele ser nombre/descripción)
        $widths['A'] = 35;
        return $widths;
    }
}
