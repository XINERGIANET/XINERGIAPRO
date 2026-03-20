<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class WorkshopServiceSpreadsheetImport
{
    /**
     * @return list<array{name: string, price: float}>
     */
    public static function extractRows(string $absolutePath): array
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            throw new \InvalidArgumentException('No se pudo leer el archivo. Usa .xlsx, .xls o .csv valido.');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $detected = self::detectColumns($sheet);
        if ($detected === null) {
            throw new \InvalidArgumentException('No se encontraron columnas SERVICIO y PRECIO en las primeras filas del archivo.');
        }

        [, $colServicio, $colPrecio] = $detected;
        [$headerRow] = $detected;

        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $coordName = Coordinate::stringFromColumnIndex($colServicio) . $row;
            $coordPrice = Coordinate::stringFromColumnIndex($colPrecio) . $row;
            $name = self::cellToString($sheet->getCell($coordName));
            $price = self::parsePrice($sheet->getCell($coordPrice)->getCalculatedValue());

            if ($name === '') {
                continue;
            }

            $out[] = [
                'name' => mb_substr($name, 0, 255),
                'price' => $price ?? 0.0,
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No hay filas de datos con nombre de servicio debajo del encabezado.');
        }

        return $out;
    }

    /**
     * @return array{0:int,1:int,2:int}|null [headerRow, colServicio1Based, colPrecio1Based]
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
            $colServicio = null;
            $colPrecio = null;
            for ($c = 1; $c <= $highestColIdx; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $norm = self::normalizeHeader($sheet->getCell($coord)->getValue());
                if ($norm !== '' && (str_contains($norm, 'servicio') || $norm === 'servicio')) {
                    $colServicio = $c;
                }
                if ($norm !== '' && str_contains($norm, 'precio')) {
                    $colPrecio = $c;
                }
            }
            if ($colServicio !== null && $colPrecio !== null && $colServicio !== $colPrecio) {
                return [$r, $colServicio, $colPrecio];
            }
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
