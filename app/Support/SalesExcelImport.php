<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class SalesExcelImport
{
    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns sheet names that match NATURAL or CORPORATIVO patterns.
     */
    public static function getMatchingSheets(string $absolutePath): array
    {
        $spreadsheet = self::loadSpreadsheet($absolutePath);
        $matching    = [];
        foreach ($spreadsheet->getSheetNames() as $name) {
            $upper = mb_strtoupper(trim($name), 'UTF-8');
            if (str_contains($upper, 'NATURAL') || str_contains($upper, 'CORPORATIVO')) {
                $matching[] = $name;
            }
        }
        return $matching;
    }

    /**
     * Returns 'NATURAL', 'CORPORATIVO', or 'DESCONOCIDO'.
     */
    public static function detectSheetType(string $sheetName): string
    {
        $upper = mb_strtoupper(trim($sheetName), 'UTF-8');
        if (str_contains($upper, 'NATURAL'))      return 'NATURAL';
        if (str_contains($upper, 'CORPORATIVO'))  return 'CORPORATIVO';
        return 'DESCONOCIDO';
    }

    /**
     * Extracts rows from one or more sheets in a single spreadsheet load.
     *
     * @param  string[] $sheetNames  Sheet names to process (must already be validated).
     * @return array{rows: list<array>, period_dates: array<string,string>}
     */
    public static function extractFromSheets(string $absolutePath, array $sheetNames): array
    {
        $spreadsheet = self::loadSpreadsheet($absolutePath);

        $allRows     = [];
        $periodDates = [];

        foreach ($sheetNames as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            if (!$sheet) {
                Log::warning("SalesExcelImport: hoja no encontrada: {$name}");
                continue;
            }

            $periodDate  = self::findPeriodDateFromSheet($sheet);
            $detected    = self::detectColumns($sheet);

            if ($detected === null) {
                Log::warning("SalesExcelImport: columnas no detectadas en hoja {$name}");
                continue;
            }

            $sheetType  = self::detectSheetType($name);
            $headerRow  = (int) $detected['header_row'];
            $maxRow     = (int) $sheet->getHighestDataRow();
            $periodDates[$name] = $periodDate;

            for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
                $client      = self::cellAt($sheet, $detected['client'],      $row);
                $description = self::cellAt($sheet, $detected['description'], $row);

                if ($client === '' && $description === '') {
                    continue;
                }

                // Date: per-row value or fallback to PERIODO
                $dateCell = $detected['date'] !== null
                    ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['date']) . $row)
                    : null;
                $rowDate  = ($dateCell !== null) ? self::parseDate($dateCell) : '';
                $date     = $rowDate !== '' ? $rowDate : $periodDate;

                // Total (formula cell)
                $totalRaw = $detected['total'] !== null
                    ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['total']) . $row)->getCalculatedValue()
                    : null;
                $total = self::parseNumber($totalRaw);

                // Quantity
                $qtyRaw   = $detected['quantity'] !== null
                    ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['quantity']) . $row)->getCalculatedValue()
                    : null;
                $quantity = self::parseNumber($qtyRaw);
                if ($quantity <= 0) {
                    $quantity = 1.0;
                }

                // Unit price
                $priceRaw  = $detected['unit_price'] !== null
                    ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['unit_price']) . $row)->getCalculatedValue()
                    : null;
                $unitPrice = self::parseNumber($priceRaw);

                if ($total <= 0 && $unitPrice > 0) {
                    $total = round($quantity * $unitPrice, 2);
                }
                if ($unitPrice <= 0 && $total > 0) {
                    $unitPrice = round($total / $quantity, 4);
                }

                $paymentForm = self::cellAt($sheet, $detected['payment_form'], $row);

                // For CORPORATIVO sheets, FORMA DE PAGO contains invoice numbers
                // (e.g. "F001-00000022" or "X FACTURAR"), not a payment method.
                $invoiceNumber = '';
                $actualPaymentForm = $paymentForm;
                if ($sheetType === 'CORPORATIVO') {
                    $invoiceNumber     = $paymentForm;
                    $actualPaymentForm = '';
                }

                $allRows[] = [
                    'date'           => $date,
                    'client'         => $client,
                    'description'    => $description,
                    'quantity'       => $quantity,
                    'unit_price'     => $unitPrice,
                    'total'          => $total,
                    'payment_type'   => self::cellAt($sheet, $detected['payment_type'],   $row),
                    'operation_type' => self::cellAt($sheet, $detected['operation_type'], $row),
                    'payment_form'   => $actualPaymentForm,
                    'invoice_number' => $invoiceNumber,
                    'sheet_type'     => $sheetType,
                    'sheet_name'     => $name,
                ];
            }
        }

        if (empty($allRows)) {
            throw new \InvalidArgumentException(
                'No se encontraron filas con datos en las hojas seleccionadas.'
            );
        }

        return [
            'rows'         => $allRows,
            'period_dates' => $periodDates,
        ];
    }

    /**
     * Legacy single-sheet entry point (reads the active sheet by default).
     * Kept for backward compatibility; prefer extractFromSheets().
     *
     * @return array{period_date: string, rows: list<array>}
     */
    public static function extractRows(string $absolutePath, ?string $sheetName = null): array
    {
        $spreadsheet = self::loadSpreadsheet($absolutePath);
        $sheet       = $sheetName
            ? ($spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet())
            : $spreadsheet->getActiveSheet();

        $periodDate = self::findPeriodDateFromSheet($sheet);
        $detected   = self::detectColumns($sheet);

        if ($detected === null) {
            throw new \InvalidArgumentException(
                'No se encontraron las columnas requeridas (Cliente y Descripción). '
                . 'El encabezado debe estar en las primeras 12 filas.'
            );
        }

        $headerRow = (int) $detected['header_row'];
        $out       = [];
        $maxRow    = (int) $sheet->getHighestDataRow();
        $sheetType = self::detectSheetType($sheet->getTitle());

        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $client      = self::cellAt($sheet, $detected['client'],      $row);
            $description = self::cellAt($sheet, $detected['description'], $row);

            if ($client === '' && $description === '') {
                continue;
            }

            $dateCell = $detected['date'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['date']) . $row)
                : null;
            $rowDate  = ($dateCell !== null) ? self::parseDate($dateCell) : '';
            $date     = $rowDate !== '' ? $rowDate : $periodDate;

            $totalRaw = $detected['total'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['total']) . $row)->getCalculatedValue()
                : null;
            $total    = self::parseNumber($totalRaw);

            $qtyRaw   = $detected['quantity'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['quantity']) . $row)->getCalculatedValue()
                : null;
            $quantity = self::parseNumber($qtyRaw);
            if ($quantity <= 0) {
                $quantity = 1.0;
            }

            $priceRaw  = $detected['unit_price'] !== null
                ? $sheet->getCell(Coordinate::stringFromColumnIndex($detected['unit_price']) . $row)->getCalculatedValue()
                : null;
            $unitPrice = self::parseNumber($priceRaw);

            if ($total <= 0 && $unitPrice > 0) {
                $total = round($quantity * $unitPrice, 2);
            }
            if ($unitPrice <= 0 && $total > 0) {
                $unitPrice = round($total / $quantity, 4);
            }

            $paymentForm   = self::cellAt($sheet, $detected['payment_form'], $row);
            $invoiceNumber = '';
            $actualPaymentForm = $paymentForm;
            if ($sheetType === 'CORPORATIVO') {
                $invoiceNumber     = $paymentForm;
                $actualPaymentForm = '';
            }

            $out[] = [
                'date'           => $date,
                'client'         => $client,
                'description'    => $description,
                'quantity'       => $quantity,
                'unit_price'     => $unitPrice,
                'total'          => $total,
                'payment_type'   => self::cellAt($sheet, $detected['payment_type'],   $row),
                'operation_type' => self::cellAt($sheet, $detected['operation_type'], $row),
                'payment_form'   => $actualPaymentForm,
                'invoice_number' => $invoiceNumber,
                'sheet_type'     => $sheetType,
                'sheet_name'     => $sheet->getTitle(),
            ];
        }

        if (empty($out)) {
            throw new \InvalidArgumentException(
                'No hay filas con datos debajo del encabezado.'
            );
        }

        return [
            'period_date' => $periodDate,
            'rows'        => $out,
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private static function loadSpreadsheet(string $absolutePath): Spreadsheet
    {
        try {
            return IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            $detail = trim($e->getMessage());
            Log::warning('SalesExcelImport: fallo al cargar archivo', ['path' => $absolutePath, 'error' => $detail]);
            throw new \InvalidArgumentException(
                'No se pudo leer el archivo. Verifica que sea .xlsx, .xls o .csv válido.'
                . ($detail !== '' ? ' Detalle: ' . $detail : '')
            );
        }
    }

    /**
     * Scans the first 8 rows for a cell whose numeric value falls in the Excel
     * date serial range 40000–60000 (years 2009–2064).
     */
    private static function findPeriodDateFromSheet(Worksheet $sheet): string
    {
        $maxScanRows = min(8, (int) $sheet->getHighestDataRow());
        $maxCol      = min(Coordinate::columnIndexFromString($sheet->getHighestColumn()), 15);

        for ($r = 1; $r <= $maxScanRows; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $val = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue();
                if (is_numeric($val)) {
                    $serial = (float) $val;
                    if ($serial >= 40000 && $serial <= 60000) {
                        try {
                            $dt = ExcelDate::excelToDateTimeObject($serial);
                            return $dt->format('Y-m-d');
                        } catch (Throwable) {
                        }
                    }
                }
            }
        }

        return now()->format('Y-m-d');
    }

    /**
     * @return array<string, int|null>|null
     */
    private static function detectColumns(Worksheet $sheet): ?array
    {
        $maxScanRows      = min(12, max(1, (int) $sheet->getHighestDataRow()));
        $highestColLetter = $sheet->getHighestColumn();
        $highestColIdx    = max(
            $highestColLetter !== '' ? Coordinate::columnIndexFromString($highestColLetter) : 1,
            15
        );

        for ($r = 1; $r <= $maxScanRows; $r++) {
            $cols = [
                'date'           => null,
                'client'         => null,
                'description'    => null,
                'quantity'       => null,
                'unit_price'     => null,
                'total'          => null,
                'payment_type'   => null,
                'operation_type' => null,
                'payment_form'   => null,
            ];

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $norm = self::normalizeHeader(
                    $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue()
                );
                if ($norm === '' || $norm === 'n') {
                    continue;
                }

                if (str_contains($norm, 'fecha')) {
                    $cols['date'] = $c;
                } elseif (str_contains($norm, 'cliente') || $norm === 'client') {
                    $cols['client'] = $c;
                } elseif (str_contains($norm, 'descrip')) {
                    $cols['description'] = $c;
                } elseif (str_contains($norm, 'cantidad') || $norm === 'cant') {
                    $cols['quantity'] = $c;
                } elseif (str_contains($norm, 'precio') && str_contains($norm, 'unit')) {
                    $cols['unit_price'] = $c;
                } elseif (str_contains($norm, 'total') && str_contains($norm, 'venta')) {
                    $cols['total'] = $c;
                } elseif ($norm === 'total') {
                    $cols['total'] ??= $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'pago')) {
                    $cols['payment_type'] = $c;
                } elseif (str_contains($norm, 'tipo') && str_contains($norm, 'operac')) {
                    $cols['operation_type'] = $c;
                } elseif (str_contains($norm, 'forma') && str_contains($norm, 'pago')) {
                    $cols['payment_form'] = $c;
                }
            }

            if ($cols['client'] !== null && $cols['description'] !== null) {
                $cols['header_row'] = $r;
                return $cols;
            }
        }

        return null;
    }

    private static function parseDate(Cell $cell): string
    {
        $raw = $cell->getValue();

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial >= 40000 && $serial <= 60000) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($serial);
                    return $dt->format('Y-m-d');
                } catch (Throwable) {
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
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        try {
            return (new \DateTime($s))->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

    private static function cellAt(Worksheet $sheet, ?int $col, int $row): string
    {
        if ($col === null) {
            return '';
        }
        $v = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue();
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
        $s = str_replace(['*', '°'], '', $s);
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
        $s = preg_replace('/[^\d,.\-]/', '', (string) $raw) ?? '';
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        $lastComma = strrpos($s, ',');
        $lastDot   = strrpos($s, '.');
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
