<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class WorkshopOrdersExcelImport
{
    /**
     * @return list<array{row_index: int, plate: string, document: string, observations: string, service_descriptions: list<string>, intake_date: ?string, mileage_in: ?int}>
     */
    public static function extractRows(string $absolutePath): array
    {
        $lowerPath = strtolower($absolutePath);
        if (str_ends_with($lowerPath, '.xlsx') && !class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException(
                'Para importar .xlsx PHP necesita la extensión zip (ZipArchive). Activa php_zip o importa un .csv.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            $detail = trim($e->getMessage());
            Log::warning('WorkshopOrdersExcelImport: fallo al cargar', ['path' => $absolutePath, 'error' => $detail]);
            throw new \InvalidArgumentException(
                $detail !== '' ? 'No se pudo leer el archivo: ' . $detail : 'No se pudo leer el archivo.'
            );
        }

        $sheet = $spreadsheet->getActiveSheet();
        $detected = self::detectColumns($sheet);
        if ($detected === null) {
            throw new \InvalidArgumentException(
                'No se encontraron columnas obligatorias: PLACA (o PATENTE) y OBSERVACIONES. Revisa la fila de encabezados.'
            );
        }

        [$headerRow, $colPlate, $colObs, $colDoc, $colFecha, $colKm] = $detected;

        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $plate = self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colPlate) . $row));
            $obsRaw = self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colObs) . $row));
            if (trim($plate) === '' && trim($obsRaw) === '') {
                continue;
            }
            if (trim($plate) === '') {
                throw new \InvalidArgumentException("Fila {$row}: falta PLACA.");
            }
            if (trim($obsRaw) === '') {
                throw new \InvalidArgumentException("Fila {$row}: falta OBSERVACIONES.");
            }

            $document = $colDoc !== null
                ? self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colDoc) . $row))
                : '';
            $intakeDate = $colFecha !== null
                ? self::parseDateCell($sheet, $colFecha, $row)
                : null;
            $mileage = $colKm !== null
                ? self::parseIntCell($sheet->getCell(Coordinate::stringFromColumnIndex($colKm) . $row)->getCalculatedValue())
                : null;

            $serviceDescriptions = self::splitObservations($obsRaw);

            $out[] = [
                'row_index' => $row,
                'plate' => trim($plate),
                'document' => trim($document),
                'observations' => mb_substr(trim($obsRaw), 0, 2000),
                'service_descriptions' => $serviceDescriptions,
                'intake_date' => $intakeDate,
                'mileage_in' => $mileage,
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No hay filas de datos debajo del encabezado.');
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function splitObservations(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $out[] = mb_substr($t, 0, 255);
            }
        }

        if ($out === []) {
            return [mb_substr($raw, 0, 255)];
        }

        return $out;
    }

    /**
     * @return array{0:int,1:int,2:int,3:int|null,4:int|null,5:int|null}|null
     */
    private static function detectColumns(Worksheet $sheet): ?array
    {
        $maxScanRows = min(25, max(1, (int) $sheet->getHighestDataRow()));
        $highestColLetter = (string) $sheet->getHighestDataColumn();
        if ($highestColLetter === '') {
            $highestColLetter = 'A';
        }
        $highestColIdx = Coordinate::columnIndexFromString($highestColLetter);

        for ($r = 1; $r <= $maxScanRows; $r++) {
            $colPlate = null;
            $colObs = null;
            $colDoc = null;
            $colFecha = null;
            $colKm = null;

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $norm = self::normalizeHeader($sheet->getCell($coord)->getValue());
                if ($norm === '') {
                    continue;
                }

                if (str_contains($norm, 'observa')) {
                    $colObs = $c;
                } elseif ($norm === 'placa' || str_contains($norm, 'patente') || str_contains($norm, 'placa')) {
                    $colPlate = $c;
                } elseif (
                    str_contains($norm, 'document')
                    || $norm === 'dni'
                    || $norm === 'ruc'
                    || str_contains($norm, 'nro doc')
                    || str_contains($norm, 'numero doc')
                ) {
                    $colDoc = $c;
                } elseif (str_contains($norm, 'fecha')) {
                    if (
                        str_contains($norm, 'ingreso')
                        || str_contains($norm, 'entrada')
                        || str_contains($norm, 'intake')
                        || str_contains($norm, 'ingres')
                    ) {
                        $colFecha = $c;
                    } elseif ($colFecha === null) {
                        $colFecha = $c;
                    }
                } elseif (str_contains($norm, 'kilomet') || str_contains($norm, 'kilometraje') || $norm === 'km' || str_starts_with($norm, 'km ')) {
                    $colKm = $c;
                }
            }

            if ($colPlate !== null && $colObs !== null) {
                return [$r, $colPlate, $colObs, $colDoc, $colFecha, $colKm];
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

    private static function parseDateCell(Worksheet $sheet, int $col, int $row): ?string
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row);
        $val = $cell->getValue();

        if ($val === null || $val === '') {
            return null;
        }

        if (is_numeric($val)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $val);
                $y = (int) $dt->format('Y');
                if ($y >= 1980 && $y <= 2100) {
                    return $dt->format('Y-m-d');
                }
            } catch (Throwable) {
                // No es serial de fecha de Excel; seguir con texto
            }
        }

        $s = trim((string) $val);
        if ($s === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private static function parseIntCell(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return max(0, (int) round((float) $raw));
        }
        $s = preg_replace('/[^\d]/', '', (string) $raw) ?? '';

        return $s !== '' ? (int) $s : null;
    }
}
