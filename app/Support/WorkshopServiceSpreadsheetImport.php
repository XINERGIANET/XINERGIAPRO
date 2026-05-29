<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class WorkshopServiceSpreadsheetImport
{
    /**
     * @return list<array{
     *     name: string,
     *     price: float,
     *     type: string|null,
     *     estimated_minutes: int|null,
     *     active: bool|null
     * }>
     */
    public static function extractRows(string $absolutePath): array
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            throw new \InvalidArgumentException('No se pudo leer el archivo. Usa .xlsx, .xls o .csv valido.');
        }

        $sheet = self::resolveDataSheet($spreadsheet, 'Servicios');
        $detected = self::detectColumns($sheet);
        if ($detected === null) {
            throw new \InvalidArgumentException('No se encontraron columnas SERVICIO y PRECIO. Descarga la plantilla o incluye esos encabezados.');
        }

        $headerRow = (int) $detected['header_row'];
        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $name = self::cellAt($sheet, $detected['service'], $row);
            if ($name === '' || self::isExampleRow($name)) {
                continue;
            }

            $priceRaw = $sheet->getCell(Coordinate::stringFromColumnIndex($detected['price']) . $row)->getCalculatedValue();
            $typeRaw = $detected['type'] !== null ? self::cellAt($sheet, $detected['type'], $row) : '';
            $minutesRaw = $detected['estimated_minutes'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['estimated_minutes']) . $row)->getCalculatedValue()
                : null;
            $activeRaw = $detected['active'] !== null ? self::cellAt($sheet, $detected['active'], $row) : '';

            $out[] = [
                'name' => mb_substr($name, 0, 255),
                'price' => self::parsePrice($priceRaw) ?? 0.0,
                'type' => self::parseType($typeRaw),
                'estimated_minutes' => self::parseMinutes($minutesRaw),
                'active' => self::parseActive($activeRaw),
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No hay filas de datos con nombre de servicio debajo del encabezado.');
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
                'service' => null,
                'price' => null,
                'type' => null,
                'estimated_minutes' => null,
                'active' => null,
            ];

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $norm = self::normalizeHeader($sheet->getCell($coord)->getValue());
                if ($norm === '') {
                    continue;
                }

                if (str_contains($norm, 'servicio')) {
                    $cols['service'] = $c;
                } elseif (str_contains($norm, 'precio')) {
                    $cols['price'] = $c;
                } elseif ($norm === 'tipo' || str_starts_with($norm, 'tipo ')) {
                    $cols['type'] = $c;
                } elseif (str_contains($norm, 'tiempo') || str_contains($norm, 'minuto')) {
                    $cols['estimated_minutes'] = $c;
                } elseif (str_contains($norm, 'activo') || str_contains($norm, 'estado')) {
                    $cols['active'] = $c;
                }
            }

            if ($cols['service'] !== null && $cols['price'] !== null && $cols['service'] !== $cols['price']) {
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

    private static function parseType(string $raw): ?string
    {
        $norm = mb_strtolower(trim($raw), 'UTF-8');
        if ($norm === '') {
            return null;
        }
        if (str_starts_with($norm, 'prev')) {
            return 'preventivo';
        }
        if (str_starts_with($norm, 'corr')) {
            return 'correctivo';
        }
        if (str_starts_with($norm, 'ext')) {
            return 'externo';
        }

        return null;
    }

    private static function parseMinutes(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            $s = preg_replace('/[^\d]/', '', (string) $raw) ?? '';
            if ($s === '') {
                return null;
            }
            $raw = $s;
        }
        $minutes = (int) $raw;

        return $minutes >= 0 ? min($minutes, 14400) : null;
    }

    private static function parseActive(string $raw): ?bool
    {
        $norm = mb_strtolower(trim($raw), 'UTF-8');
        if ($norm === '') {
            return null;
        }
        if (in_array($norm, ['1', 'si', 'sí', 's', 'true', 'activo', 'yes'], true)) {
            return true;
        }
        if (in_array($norm, ['0', 'no', 'n', 'false', 'inactivo'], true)) {
            return false;
        }

        return null;
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

    private static function parsePrice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return round((float) $raw, 6);
        }
        $s = (string) $raw;
        $s = str_ireplace(['s/', 'sol ', 'soles ', 'pen ', 's./'], '', $s);
        $s = trim($s);
        $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return null;
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

        return is_numeric($s) ? round((float) $s, 6) : null;
    }
}
