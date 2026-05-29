<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class ProductBranchExcelImport
{
    /**
     * @return list<array{
     *     category: string,
     *     description: string,
     *     code: string,
     *     marca: string,
     *     abbreviation: string,
     *     stock: float,
     *     price: float,
     *     purchase_price: float,
     *     stock_minimum: float,
     *     stock_maximum: float
     * }>
     */
    public static function extractRows(string $absolutePath): array
    {
        $lowerPath = strtolower($absolutePath);
        if (str_ends_with($lowerPath, '.xlsx') && !class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException(
                'Para importar .xlsx PHP necesita la extensión zip (ZipArchive). En Laragon: Menú PHP → Extensiones → activa «zip», guarda y reinicia Apache/Nginx. Alternativa: exporta el archivo como CSV e impórtalo así.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            $detail = trim($e->getMessage());
            Log::warning('ProductBranchExcelImport: fallo al cargar hoja', [
                'path' => $absolutePath,
                'error' => $detail,
            ]);

            if (!class_exists(\ZipArchive::class) && str_ends_with($lowerPath, '.xlsx')) {
                throw new \InvalidArgumentException(
                    'No se pudo leer el .xlsx: falta la extensión PHP «zip». Actívala en php.ini (extension=zip) y reinicia el servidor. O importa un archivo .csv.'
                );
            }

            if ($detail !== '' && (stripos($detail, 'zip') !== false || stripos($detail, 'ZipArchive') !== false)) {
                throw new \InvalidArgumentException(
                    'No se pudo abrir el Excel: los .xlsx son ZIP internamente. Habilita extension=zip en PHP y reinicia. Detalle: ' . $detail
                );
            }

            if ($detail !== '') {
                throw new \InvalidArgumentException(
                    'No se pudo leer el archivo. Comprueba que sea .xlsx, .xls o .csv guardado desde Excel. Detalle: ' . $detail
                );
            }

            throw new \InvalidArgumentException('No se pudo leer el archivo. Usa .xlsx, .xls o .csv válido.');
        }

        $sheet = self::resolveDataSheet($spreadsheet, 'Productos');
        $detected = self::detectColumns($sheet);
        if ($detected === null) {
            throw new \InvalidArgumentException(
                'No se encontraron las columnas requeridas (categoría y descripción). Usa la plantilla descargable o incluye encabezados como CATEGORÍA y DESCRIPCIÓN.'
            );
        }

        $headerRow = (int) $detected['header_row'];
        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $desc = self::cellAt($sheet, $detected['description'], $row);
            if ($desc === '' || self::isExampleRow($desc)) {
                continue;
            }

            $category = self::cellAt($sheet, $detected['category'], $row);
            $marca = $detected['marca'] !== null ? self::cellAt($sheet, $detected['marca'], $row) : '';
            $code = $detected['code'] !== null ? self::cellAt($sheet, $detected['code'], $row) : '';
            $abbreviation = $detected['abbreviation'] !== null ? self::cellAt($sheet, $detected['abbreviation'], $row) : '';
            $stockRaw = $detected['stock'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['stock']) . $row)->getCalculatedValue()
                : null;
            $priceRaw = $detected['price'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['price']) . $row)->getCalculatedValue()
                : null;
            $purchaseRaw = $detected['purchase_price'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['purchase_price']) . $row)->getCalculatedValue()
                : null;
            $stockMinRaw = $detected['stock_minimum'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['stock_minimum']) . $row)->getCalculatedValue()
                : null;
            $stockMaxRaw = $detected['stock_maximum'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['stock_maximum']) . $row)->getCalculatedValue()
                : null;

            $out[] = [
                'category' => mb_substr(trim($category), 0, 255),
                'description' => mb_substr(trim($desc), 0, 255),
                'code' => mb_substr(trim($code), 0, 50),
                'marca' => mb_substr(trim($marca), 0, 255),
                'abbreviation' => mb_substr(trim($abbreviation), 0, 255),
                'stock' => max(0.0, self::parseNumber($stockRaw)),
                'price' => max(0.0, self::parseNumber($priceRaw)),
                'purchase_price' => max(0.0, self::parseNumber($purchaseRaw)),
                'stock_minimum' => max(0.0, self::parseNumber($stockMinRaw)),
                'stock_maximum' => max(0.0, self::parseNumber($stockMaxRaw)),
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No hay filas con descripción debajo del encabezado. Borra la fila de ejemplo o agrega tus productos.');
        }

        return $out;
    }

    private static function resolveDataSheet(Spreadsheet $spreadsheet, string $preferredName): Worksheet
    {
        $named = $spreadsheet->getSheetByName($preferredName);
        if ($named instanceof Worksheet) {
            return $named;
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return array<string, int|null>|null
     */
    private static function detectColumns(Worksheet $sheet): ?array
    {
        $maxScanRows = min(15, max(1, (int) $sheet->getHighestDataRow()));
        $highestColLetter = (string) $sheet->getHighestDataColumn();
        if ($highestColLetter === '') {
            $highestColLetter = 'A';
        }
        $highestColIdx = Coordinate::columnIndexFromString($highestColLetter);

        for ($r = 1; $r <= $maxScanRows; $r++) {
            $cols = [
                'category' => null,
                'description' => null,
                'marca' => null,
                'stock' => null,
                'code' => null,
                'abbreviation' => null,
                'price' => null,
                'purchase_price' => null,
                'stock_minimum' => null,
                'stock_maximum' => null,
            ];

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $norm = self::normalizeHeader($sheet->getCell($coord)->getValue());
                if ($norm === '') {
                    continue;
                }

                if (str_contains($norm, 'descrip')) {
                    $cols['description'] = $c;
                } elseif (self::headerLooksLikeCategory($norm)) {
                    $cols['category'] = $c;
                } elseif ($norm === 'marca' || str_starts_with($norm, 'marca ')) {
                    $cols['marca'] = $c;
                } elseif (str_contains($norm, 'stock') && str_contains($norm, 'min')) {
                    $cols['stock_minimum'] = $c;
                } elseif (str_contains($norm, 'stock') && str_contains($norm, 'max')) {
                    $cols['stock_maximum'] = $c;
                } elseif (str_contains($norm, 'stock')) {
                    $cols['stock'] = $c;
                } elseif (str_contains($norm, 'codigo') || $norm === 'code') {
                    $cols['code'] = $c;
                } elseif (str_contains($norm, 'abrev')) {
                    $cols['abbreviation'] = $c;
                } elseif (str_contains($norm, 'precio') && str_contains($norm, 'compra')) {
                    $cols['purchase_price'] = $c;
                } elseif (str_contains($norm, 'precio')) {
                    $cols['price'] = $c;
                }
            }

            if ($cols['category'] !== null && $cols['description'] !== null) {
                $cols['header_row'] = $r;

                return $cols;
            }
        }

        return null;
    }

    private static function cellAt(Worksheet $sheet, ?int $col, int $row): string
    {
        if ($col === null) {
            return '';
        }

        return self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row));
    }

    private static function isExampleRow(string $value): bool
    {
        $norm = mb_strtolower(trim($value), 'UTF-8');

        return str_starts_with($norm, 'ejemplo:')
            || str_starts_with($norm, 'ejemplo ')
            || $norm === 'ejemplo';
    }

    private static function headerLooksLikeCategory(string $norm): bool
    {
        if (str_contains($norm, 'categor')) {
            return true;
        }
        if (str_contains($norm, 'caterg')) {
            return true;
        }

        return false;
    }

    private static function normalizeHeader(mixed $value): string
    {
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }
        $s = mb_strtolower(trim((string) $value), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
            $s = preg_replace('/\pM/u', '', $s) ?? $s;
        }
        $s = str_replace('*', '', $s);
        $s = preg_replace('/\(\s*opcional\s*\)/u', '', $s) ?? $s;

        return trim($s);
    }

    private static function cellToString(mixed $cell): string
    {
        if (is_object($cell) && method_exists($cell, 'getValue')) {
            $value = $cell->getValue();
        } else {
            $value = $cell;
        }
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        return trim((string) $value);
    }

    private static function parseNumber(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_numeric($raw)) {
            return round((float) $raw, 4);
        }
        $s = (string) $raw;
        $s = str_ireplace(['s/', 'sol ', 'soles ', 'pen ', 's./'], '', $s);
        $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? round((float) $s, 4) : 0.0;
    }
}
