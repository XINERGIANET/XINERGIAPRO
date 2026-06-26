<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class PurchaseExcelImport
{
    /**
     * @return list<array{
     *     date: string,
     *     doc_type_name: string,
     *     series: string,
     *     number: string,
     *     provider_doc_type: string,
     *     provider_doc_number: string,
     *     provider_name: string,
     *     subtotal: float,
     *     tax: float,
     *     total: float,
     *     currency: string,
     *     exchange_rate: float,
     *     purchase_type: string,
     *     category: string,
     *     use_description: string,
     *     vehicle_type: string,
     *     area: string,
     *     payment_method: string,
     *     fiscal_credit: string,
     *     observations: string,
     * }>
     */
    public static function extractRows(string $absolutePath): array
    {
        $lowerPath = strtolower($absolutePath);
        if (str_ends_with($lowerPath, '.xlsx') && !class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException(
                'Para importar .xlsx PHP necesita la extensión zip. Actívala en php.ini y reinicia el servidor.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            $detail = trim($e->getMessage());
            Log::warning('PurchaseExcelImport: fallo al cargar archivo', [
                'path' => $absolutePath,
                'error' => $detail,
            ]);
            throw new \InvalidArgumentException(
                'No se pudo leer el archivo. Verifica que sea .xlsx, .xls o .csv válido.'
                . ($detail !== '' ? ' Detalle: ' . $detail : '')
            );
        }

        $sheet = $spreadsheet->getActiveSheet();
        $detected = self::detectColumns($sheet);

        if ($detected === null) {
            throw new \InvalidArgumentException(
                'No se encontraron las columnas requeridas (Fecha y Número). '
                . 'El encabezado debe estar en las primeras 15 filas con esos nombres.'
            );
        }

        $headerRow = (int) $detected['header_row'];
        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $number = self::cellAt($sheet, $detected['number'], $row);

            // Every real purchase record has an invoice number.
            // Skip empty rows, footer/total rows, and any row without one.
            if ($number === '') {
                continue;
            }

            $subtotalRaw = $detected['subtotal'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['subtotal']) . $row)->getCalculatedValue()
                : null;
            $subtotal = self::parseNumber($subtotalRaw);

            $dateCell = $detected['date'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['date']) . $row)
                : null;

            $taxRaw = $detected['tax'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['tax']) . $row)->getCalculatedValue()
                : null;
            $totalRaw = $detected['total'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['total']) . $row)->getCalculatedValue()
                : null;
            $exchangeRaw = $detected['exchange_rate'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['exchange_rate']) . $row)->getCalculatedValue()
                : null;

            $exchangeRate = self::parseNumber($exchangeRaw);
            if ($exchangeRate <= 0) {
                $exchangeRate = 1.0;
            }

            $out[] = [
                'date'                => $dateCell !== null ? self::parseDate($dateCell) : '',
                'doc_type_name'       => self::cellAt($sheet, $detected['doc_type_name'], $row),
                'series'              => self::cellAt($sheet, $detected['series'], $row),
                'number'              => $number,
                'provider_doc_type'   => self::cellAt($sheet, $detected['provider_doc_type'], $row),
                'provider_doc_number' => self::cellAt($sheet, $detected['provider_doc_number'], $row),
                'provider_name'       => self::cellAt($sheet, $detected['provider_name'], $row),
                'subtotal'            => $subtotal,
                'tax'                 => self::parseNumber($taxRaw),
                'total'               => self::parseNumber($totalRaw),
                'currency'            => strtoupper(self::cellAt($sheet, $detected['currency'], $row)) ?: 'PEN',
                'exchange_rate'       => $exchangeRate,
                'purchase_type'       => self::cellAt($sheet, $detected['purchase_type'], $row),
                'category'            => self::cellAt($sheet, $detected['category'], $row),
                'use_description'     => self::cellAt($sheet, $detected['use_description'], $row),
                'vehicle_type'        => self::cellAt($sheet, $detected['vehicle_type'], $row),
                'area'                => self::cellAt($sheet, $detected['area'], $row),
                'payment_method'      => self::cellAt($sheet, $detected['payment_method'], $row),
                'fiscal_credit'       => self::cellAt($sheet, $detected['fiscal_credit'], $row),
                'observations'        => self::cellAt($sheet, $detected['observations'], $row),
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException(
                'No hay filas con datos debajo del encabezado. Agrega compras al archivo.'
            );
        }

        return $out;
    }

    /**
     * Scans first 15 rows to find the header row and map each column.
     * Returns null if required columns (date + number) are not found.
     *
     * @return array<string, int|null>|null
     */
    private static function detectColumns(Worksheet $sheet): ?array
    {
        $maxScanRows = min(15, max(1, (int) $sheet->getHighestDataRow()));

        // getHighestDataColumn() only counts cells with non-empty values, so if the
        // optional columns (Moneda, Tipo Cambio, Área, etc.) have no data rows filled
        // it stops at the last filled column (e.g. 'L') and misses the rest.
        // We use getHighestColumn() which counts ALL cells (including the header labels)
        // and cap at a minimum of 30 to be safe against edge cases.
        $highestColLetter = $sheet->getHighestColumn();
        $highestColIdx = max(
            $highestColLetter !== '' ? Coordinate::columnIndexFromString($highestColLetter) : 1,
            30
        );

        for ($r = 1; $r <= $maxScanRows; $r++) {
            $cols = [
                'date'                => null,
                'doc_type_name'       => null,
                'series'              => null,
                'number'              => null,
                'provider_doc_type'   => null,
                'provider_doc_number' => null,
                'provider_name'       => null,
                'subtotal'            => null,
                'tax'                 => null,
                'total'               => null,
                'currency'            => null,
                'exchange_rate'       => null,
                'purchase_type'       => null,
                'category'            => null,
                'use_description'     => null,
                'vehicle_type'        => null,
                'area'                => null,
                'payment_method'      => null,
                'fiscal_credit'       => null,
                'observations'        => null,
            ];

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $norm = self::normalizeHeader(
                    $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue()
                );
                if ($norm === '' || $norm === 'n') {
                    continue; // skip empty and N° row-counter column
                }

                // Priority order: more specific conditions first
                if (str_contains($norm, 'fecha') && str_contains($norm, 'pago')) {
                    $cols['date'] = $c;
                } elseif (str_contains($norm, 'fecha')) {
                    $cols['date'] ??= $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'comprobante')) {
                    $cols['doc_type_name'] = $c;
                } elseif ($norm === 'serie' || $norm === 'series') {
                    $cols['series'] = $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'doc') && str_contains($norm, 'proveedor')) {
                    $cols['provider_doc_type'] = $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'cambio')) {
                    $cols['exchange_rate'] = $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'compra')) {
                    $cols['purchase_type'] = $c;
                } elseif (str_contains($norm, 'tipo') && (str_contains($norm, 'vehiculo') || str_contains($norm, 'vehic'))) {
                    $cols['vehicle_type'] = $c;
                } elseif (str_contains($norm, 'razon') && str_contains($norm, 'social')) {
                    $cols['provider_name'] = $c;
                } elseif (str_contains($norm, 'doc') && str_contains($norm, 'proveedor')) {
                    $cols['provider_doc_number'] = $c;
                } elseif (str_contains($norm, 'proveedor')) {
                    $cols['provider_name'] ??= $c;
                } elseif (str_contains($norm, 'base') && str_contains($norm, 'imponible')) {
                    $cols['subtotal'] = $c;
                } elseif ($norm === 'igv' || (str_contains($norm, 'igv') && !str_contains($norm, 'credito'))) {
                    $cols['tax'] = $c;
                } elseif ($norm === 'total') {
                    $cols['total'] = $c;
                } elseif ($norm === 'moneda') {
                    $cols['currency'] = $c;
                } elseif ($norm === 'area') {
                    $cols['area'] = $c;
                } elseif (str_contains($norm, 'credito') && str_contains($norm, 'fiscal')) {
                    $cols['fiscal_credit'] = $c;
                } elseif (str_contains($norm, 'uso') && str_contains($norm, 'compra')) {
                    $cols['use_description'] = $c;
                } elseif (str_contains($norm, 'categor')) {
                    $cols['category'] = $c;
                } elseif (str_contains($norm, 'vehiculo') || str_contains($norm, 'vehic')) {
                    $cols['vehicle_type'] ??= $c;
                } elseif (str_contains($norm, 'medio') && str_contains($norm, 'pago')) {
                    $cols['payment_method'] = $c;
                } elseif (str_contains($norm, 'observ')) {
                    $cols['observations'] = $c;
                } elseif (str_contains($norm, 'numero') && !str_contains($norm, 'doc') && !str_contains($norm, 'proveedor')) {
                    $cols['number'] = $c;
                }
            }

            if ($cols['date'] !== null && $cols['number'] !== null) {
                $cols['header_row'] = $r;

                return $cols;
            }
        }

        return null;
    }

    private static function parseDate(Cell $cell): string
    {
        $raw = $cell->getValue();

        // Excel date serials are numeric values typically in range 40000–60000
        // (40000 ≈ year 2009, 60000 ≈ year 2064).
        // Never call isDateTime() or getFormattedValue() — both access worksheet
        // styles and throw "Worksheet no longer exists" after PhpSpreadsheet
        // frees internal references during iteration.
        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial >= 40000 && $serial <= 60000) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($serial);
                    return self::clampDate((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
                } catch (\Throwable) {
                    // fall through to string parsing
                }
            }
        }

        if ($raw instanceof RichText) {
            $raw = $raw->getPlainText();
        }
        $s = trim((string) $raw);

        if ($s === '') {
            return '';
        }

        // "30 de marzo del 2026" / "30 de marzo de 2026" / "30 de marzo 2026"
        static $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3,
            'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
            'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        if (preg_match('/^(\d{1,2})\s+de\s+(\w+)(?:\s+(?:de(?:l)?))?\s+(\d{4})$/iu', $s, $m)) {
            $month = $meses[mb_strtolower(trim($m[2]), 'UTF-8')] ?? null;
            if ($month !== null) {
                return self::clampDate((int) $m[3], $month, (int) $m[1]);
            }
        }

        // dd/mm/yyyy  or  d/m/yyyy  (separadores / o -)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
            return self::clampDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // yyyy-mm-dd
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return self::clampDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        try {
            $dt = new \DateTime($s);
            return self::clampDate((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Devuelve 'YYYY-MM-DD'. Si el día supera el máximo del mes (ej. 30/02),
     * lo recorta al último día válido en lugar de retornar vacío.
     * Retorna '' solo si mes o año son completamente inválidos.
     */
    private static function clampDate(int $year, int $month, int $day): string
    {
        if ($month < 1 || $month > 12 || $year < 1900 || $year > 2100) {
            return '';
        }
        // Último día del mes (respeta años bisiestos)
        $maxDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $day = max(1, min($day, $maxDay));
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function cellAt(Worksheet $sheet, ?int $col, int $row): string
    {
        if ($col === null) {
            return '';
        }
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row);
        $v = $cell->getValue();
        if ($v instanceof RichText) {
            $v = $v->getPlainText();
        }

        return trim((string) $v);
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
        $s = str_replace(['*', '°', '(opcional)', '(requerido)'], '', $s);
        $s = preg_replace('/\(\s*opcional\s*\)/u', '', $s) ?? $s;

        return trim($s);
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
