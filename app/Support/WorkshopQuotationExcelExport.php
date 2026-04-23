<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\WorkshopMovement;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WorkshopQuotationExcelExport
{
    /**
     * Genera un .xlsx alineado al formato Motolab (estructura por secciones).
     * Si existe storage/app/motolab-cotizacion.xlsx se intenta cargar como base (solo se conserva la 1ª hoja vacía si no hay celdas mapeadas).
     */
    public static function buildPath(WorkshopMovement $order): string
    {
        $order->loadMissing(['movement', 'vehicle', 'client', 'branch.company', 'details.product', 'details.service']);

        $spreadsheet = self::buildProgrammaticSpreadsheet($order);

        return self::writeSpreadsheet($spreadsheet, $order);
    }

    private static function writeSpreadsheet(Spreadsheet $spreadsheet, WorkshopMovement $order): string
    {
        $dir = storage_path('app/tmp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeCorrelative = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($order->quotation_correlative ?: 'COT')) ?? 'COT';
        $path = $dir . DIRECTORY_SEPARATOR . 'cotizacion_' . $safeCorrelative . '_' . $order->id . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private static function buildProgrammaticSpreadsheet(WorkshopMovement $order): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cotizacion');

        $branch = $order->branch instanceof Branch ? $order->branch : Branch::query()->find($order->branch_id);
        $companyName = $branch?->company?->legal_name ?? $branch?->legal_name ?? 'Empresa';
        $client = $order->client;
        $vehicle = $order->vehicle;
        $terms = is_array($order->quotation_commercial_terms ?? null) ? $order->quotation_commercial_terms : [];
        $headerFillColor = '1F4E78';

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);

        $r = 1;
        $logoPath = self::resolveBranchLogoPath($branch);
        $r = self::writeBranchHeaderBlock($sheet, $r, $branch, $companyName, $terms, $logoPath);

        $sheet->setCellValue("A{$r}", 'COTIZACION N°');
        $sheet->setCellValue("B{$r}", $order->quotation_correlative ?: ($order->movement?->number ?? ('OS-' . $order->id)));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $sheet->setCellValue("A{$r}", 'Fecha');
        $sheet->setCellValue("B{$r}", optional($order->intake_date)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
        $r++;
        $r++;

        $sheet->setCellValue("A{$r}", 'DATOS DEL CLIENTE');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
        $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB('FFFFFF');
        $r++;
        $sheet->setCellValue("A{$r}", 'Cliente');
        $sheet->setCellValue("B{$r}", trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')));
        $r++;
        $sheet->setCellValue("A{$r}", 'Documento');
        $sheet->setCellValue("B{$r}", (string) ($client->document_number ?? ''));
        $r++;
        $sheet->setCellValue("A{$r}", 'Correo');
        $sheet->setCellValue("B{$r}", (string) ($order->quotation_client_email ?: ($client->email ?? '')));
        $r++;
        $sheet->setCellValue("A{$r}", 'Telefono');
        $sheet->setCellValue("B{$r}", (string) ($client->phone ?? ''));
        $r++;
        $r++;

        $sheet->setCellValue("A{$r}", 'VEHICULO');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
        $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB('FFFFFF');
        $r++;
        if ($vehicle) {
            $sheet->setCellValue("A{$r}", 'Placa / modelo');
            $sheet->setCellValue(
                "B{$r}",
                trim(($vehicle->plate ?? '') . ' — ' . trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')))
            );
        } else {
            $sheet->setCellValue("A{$r}", 'Descripcion');
            $sheet->setCellValue("B{$r}", (string) ($order->quotation_vehicle_note ?: 'Sin vehiculo registrado'));
        }
        $r += 2;

        if (trim((string) ($order->diagnosis_text ?? '')) !== '') {
            $sheet->setCellValue("A{$r}", 'Diagnostico / alcance');
            $sheet->mergeCells("A{$r}:E{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
            $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB('FFFFFF');
            $r++;
            $sheet->setCellValue("A{$r}", (string) $order->diagnosis_text);
            $sheet->mergeCells("A{$r}:E" . ($r + 2));
            $sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true);
            $r += 4;
        }

        $sheet->setCellValue("A{$r}", 'Estimados Señores:');
        $sheet->mergeCells("A{$r}:E{$r}");
        $r++;
        $sheet->setCellValue("A{$r}", 'Por medio del presente, tenemos el agrado de cotizarles a ustedes, lo siguiente:');
        $sheet->mergeCells("A{$r}:E{$r}");
        $r += 2;

        $allDetails = $order->details->values();
        $r = self::writeDetailTable(
            $sheet,
            $r,
            'DETALLE DE COTIZACION',
            ['#', 'Descripcion', 'Cant.', 'P. unit.', 'Total'],
            $allDetails,
            false,
            $headerFillColor
        );

        $r += 2;
        $sheet->setCellValue("D{$r}", 'Subtotal');
        $sheet->setCellValue("E{$r}", (float) $order->subtotal);
        $r++;
        $sheet->setCellValue("D{$r}", 'IGV');
        $sheet->setCellValue("E{$r}", (float) $order->tax);
        $r++;
        $sheet->setCellValue("D{$r}", 'TOTAL');
        $sheet->setCellValue("E{$r}", (float) $order->total);
        $sheet->getStyle("D{$r}:E{$r}")->getFont()->setBold(true);
        $sheet->getStyle("E" . ($r - 2) . ":E{$r}")->getNumberFormat()->setFormatCode('#,##0.00');

        $r += 2;
        $r = self::writeCommercialTermsBlock($sheet, $r, $order, $headerFillColor);

        $sheet->getStyle('A1:E' . $r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range(1, $r) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }

        return $spreadsheet;
    }

    private static function writeCommercialTermsBlock(Worksheet $sheet, int $startRow, WorkshopMovement $order, string $headerFillColor = '1F4E78'): int
    {
        $terms = is_array($order->quotation_commercial_terms ?? null) ? $order->quotation_commercial_terms : [];
        if ($terms === []) {
            return $startRow;
        }

        $rows = [
            ['Tiempo de entrega', (string) ($terms['delivery_time'] ?? '')],
            ['Validez de oferta', (string) ($terms['offer_validity'] ?? '')],
            ['Garantía servicio', (string) ($terms['service_warranty'] ?? '')],
            ['Lugar de entrega', (string) ($terms['delivery_place'] ?? '')],
            ['Precios', (string) ($terms['prices_note'] ?? '')],
            ['Condición de pago', (string) ($terms['payment_condition'] ?? '')],
            ['Cta. Ah. S/. BCP', (string) ($terms['bank_account_bcp'] ?? '')],
            ['CCI', (string) ($terms['bank_cci'] ?? '')],
        ];

        $hasAny = false;
        foreach ($rows as [, $v]) {
            if (trim((string) $v) !== '') {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            return $startRow;
        }

        $r = $startRow;
        $sheet->setCellValue("A{$r}", 'CONDICIONES COMERCIALES');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
        $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB('FFFFFF');
        $r++;

        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            if (in_array($label, ['Cta. Ah. S/. BCP', 'CCI'], true)) {
                $sheet->setCellValueExplicit("B{$r}", (string) $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue("B{$r}", $value);
            }
            $sheet->mergeCells("B{$r}:E{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle("B{$r}")->getAlignment()->setWrapText(true);
            $r++;
        }

        return $r + 1;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\WorkshopMovementDetail>  $rows
     */
    private static function writeDetailTable(
        Worksheet $sheet,
        int $startRow,
        string $title,
        array $headers,
        $rows,
        bool $prefixTypeOnDescription = false,
        string $headerFillColor = '1F4E78'
    ): int {
        $r = $startRow;
        $sheet->setCellValue("A{$r}", $title);
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
        $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB('FFFFFF');
        $r++;

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$r}", $h);
            $col++;
        }
        $sheet->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFillColor);
        $sheet->getStyle("A{$r}:E{$r}")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$r}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $r++;

        $i = 1;
        foreach ($rows as $d) {
            $description = (string) $d->description;
            if ($prefixTypeOnDescription) {
                $typeLabel = self::resolveDetailTypeLabel((string) ($d->line_type ?? ''));
                if ($typeLabel !== '') {
                    $description = "[{$typeLabel}] {$description}";
                }
            }

            $sheet->setCellValue("A{$r}", $i);
            $sheet->setCellValue("B{$r}", $description);
            $sheet->setCellValue("C{$r}", (float) $d->qty);
            $sheet->setCellValue("D{$r}", (float) $d->unit_price);
            $sheet->setCellValue("E{$r}", (float) $d->total);
            $sheet->getStyle("C{$r}:E{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$r}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $i++;
            $r++;
        }

        if ($rows->isEmpty()) {
            $sheet->setCellValue("A{$r}", '—');
            $sheet->setCellValue("B{$r}", 'Sin lineas en esta seccion');
            $sheet->mergeCells("B{$r}:E{$r}");
            $r++;
        }

        return $r;
    }

    public static function resolveBranchLogoPath(?Branch $branch): ?string
    {
        $logo = trim((string) ($branch?->logo ?? ''));
        if ($logo === '') {
            return null;
        }

        $candidatePaths = [
            $logo,
            public_path($logo),
            public_path('storage/' . ltrim($logo, '/\\')),
            storage_path('app/public/' . ltrim($logo, '/\\')),
            storage_path('app/' . ltrim($logo, '/\\')),
        ];

        foreach ($candidatePaths as $path) {
            if (is_string($path) && $path !== '' && file_exists($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function writeBranchHeaderBlock(
        Worksheet $sheet,
        int $startRow,
        ?Branch $branch,
        string $companyName,
        array $terms,
        ?string $logoPath
    ): int {
        $rowStart = $startRow;
        $rowEnd = $rowStart + 4;

        $ruc = trim((string) ($branch?->ruc ?? $branch?->company?->tax_id ?? ''));
        $branchDireccion = trim((string) data_get($branch, 'direccion', ''));
        $branchAddress = trim((string) data_get($branch, 'address', ''));
        $address = $branchDireccion !== '' ? $branchDireccion : $branchAddress;
        $phone = self::resolveBranchContact($terms, ['branch_phone', 'phone', 'telefono', 'tel']);
        $email = self::resolveBranchContact($terms, ['branch_email', 'email', 'correo']);

        if ($logoPath !== null) {
            $drawing = new Drawing();
            $drawing->setPath($logoPath);
            $drawing->setWorksheet($sheet);
            $drawing->setCoordinates("A{$rowStart}");
            $drawing->setHeight(76);
            $drawing->setOffsetX(8);
            $drawing->setOffsetY(4);
        }

        $sheet->mergeCells("A{$rowStart}:A{$rowEnd}");
        $sheet->mergeCells("B{$rowStart}:E{$rowStart}");
        $sheet->mergeCells('B' . ($rowStart + 1) . ':E' . ($rowStart + 1));
        $sheet->mergeCells('B' . ($rowStart + 2) . ':E' . ($rowStart + 2));
        $sheet->mergeCells('B' . ($rowStart + 3) . ':E' . ($rowStart + 3));
        $sheet->mergeCells('B' . ($rowStart + 4) . ':E' . ($rowStart + 4));

        $sheet->setCellValue("B{$rowStart}", 'EMPRESA ' . trim((string) $companyName));
        $sheet->setCellValue('B' . ($rowStart + 1), 'RUC: ' . ($ruc !== '' ? $ruc : '-'));
        $sheet->setCellValue('B' . ($rowStart + 2), $address !== '' ? $address : '-');
        $sheet->setCellValue('B' . ($rowStart + 3), 'TELEF: ' . ($phone !== '' ? $phone : '-'));
        $sheet->setCellValue('B' . ($rowStart + 4), 'E-MAIL: ' . ($email !== '' ? $email : '-'));

        $sheet->getStyle("B{$rowStart}:E{$rowEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowStart}:A{$rowEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$rowStart}")->getFont()->setBold(true)->setSize(12);

        foreach (range($rowStart, $rowEnd) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(24);
        }

        return $rowEnd + 2;
    }

    private static function resolveBranchContact(array $terms, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $terms)) {
                continue;
            }
            $value = trim((string) ($terms[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function resolveDetailTypeLabel(string $lineType): string
    {
        $normalized = strtoupper(trim($lineType));

        if ($normalized === 'PART') {
            return 'REPUESTO';
        }
        if ($normalized === 'LABOR') {
            return 'MANO DE OBRA';
        }
        if ($normalized === 'SERVICE') {
            return 'SERVICIO';
        }

        return '';
    }
}
