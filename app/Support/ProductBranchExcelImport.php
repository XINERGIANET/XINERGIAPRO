<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class ProductBranchExcelImport
{
    /**
     * @return list<array{category: string, description: string, marca: string, stock: float}>
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

        $sheet = $spreadsheet->getActiveSheet();
        $detected = self::detectColumns($sheet);
        if ($detected === null) {
            throw new \InvalidArgumentException(
                'No se encontraron las columnas requeridas (categoría, descripción, stock). Incluye encabezados como CATEGORÍA/CATERGORIA, DESCRIPCION y STOCK ACTUAL.'
            );
        }

        [$headerRow, $colCategory, $colDescription, $colMarca, $colStock] = $detected;

        $out = [];
        $maxRow = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $desc = self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colDescription) . $row));
            if ($desc === '') {
                continue;
            }

            $category = self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colCategory) . $row));
            $marca = $colMarca !== null
                ? self::cellToString($sheet->getCell(Coordinate::stringFromColumnIndex($colMarca) . $row))
                : '';
            $stockRaw = $sheet->getCell(Coordinate::stringFromColumnIndex($colStock) . $row)->getCalculatedValue();
            $stock = self::parseStock($stockRaw);

            $out[] = [
                'category' => mb_substr(trim($category), 0, 255),
                'description' => mb_substr(trim($desc), 0, 255),
                'marca' => mb_substr(trim($marca), 0, 255),
                'stock' => max(0.0, $stock),
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No hay filas con descripción debajo del encabezado.');
        }

        return $out;
    }

    /**
     * @return array{0:int,1:int,2:int,3:int|null,4:int}|null [headerRow, colCategory, colDescription, colMarca|null, colStock]
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
            $colCategory = null;
            $colDescription = null;
            $colMarca = null;
            $colStock = null;

            for ($c = 1; $c <= $highestColIdx; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $norm = self::normalizeHeader($sheet->getCell($coord)->getValue());
                if ($norm === '') {
                    continue;
                }

                if (str_contains($norm, 'descrip')) {
                    $colDescription = $c;
                } elseif (self::headerLooksLikeCategory($norm)) {
                    $colCategory = $c;
                } elseif ($norm === 'marca' || str_starts_with($norm, 'marca ')) {
                    $colMarca = $c;
                } elseif (str_contains($norm, 'stock')) {
                    $colStock = $c;
                }
            }

            if ($colCategory !== null && $colDescription !== null && $colStock !== null) {
                return [$r, $colCategory, $colDescription, $colMarca, $colStock];
            }
        }

        return null;
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

    private static function parseStock(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_numeric($raw)) {
            return round((float) $raw, 4);
        }
        $s = (string) $raw;
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
