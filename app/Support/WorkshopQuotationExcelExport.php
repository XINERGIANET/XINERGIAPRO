<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\WorkshopMovement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        $branchLine = trim((string) ($branch?->legal_name ?? ''));
        $client = $order->client;
        $vehicle = $order->vehicle;

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);

        $r = 1;
        $sheet->setCellValue("A{$r}", 'MOTOLAB GROUP SAC');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
        $sheet->setCellValue("A{$r}", $companyName);
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r += 2;

        $sheet->setCellValue("A{$r}", 'COTIZACION N°');
        $sheet->setCellValue("B{$r}", $order->quotation_correlative ?: ($order->movement?->number ?? ('OS-' . $order->id)));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $sheet->setCellValue("A{$r}", 'Fecha');
        $sheet->setCellValue("B{$r}", optional($order->intake_date)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
        $r++;
        $sheet->setCellValue("A{$r}", 'Tipo');
        $sheet->setCellValue("B{$r}", $order->quotation_source === 'external' ? 'Externa' : 'Interna (OS)');
        $r += 2;

        $sheet->setCellValue("A{$r}", 'DATOS DEL CLIENTE');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
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
        $sheet->setCellValue("A{$r}", 'Sucursal');
        $sheet->setCellValue("B{$r}", $branchLine);
        $r += 2;

        $sheet->setCellValue("A{$r}", 'VEHICULO');
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
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
            $r++;
            $sheet->setCellValue("A{$r}", (string) $order->diagnosis_text);
            $sheet->mergeCells("A{$r}:E" . ($r + 2));
            $sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true);
            $r += 4;
        }

        $r = self::writeCommercialTermsBlock($sheet, $r, $order);

        $parts = $order->details->where('line_type', 'PART')->values();
        $labor = $order->details->whereIn('line_type', ['LABOR', 'SERVICE'])->values();

        $r = self::writeDetailTable($sheet, $r, 'REPUESTOS', ['#', 'Descripcion', 'Cant.', 'P. unit.', 'Total'], $parts);
        $r += 2;
        $r = self::writeDetailTable($sheet, $r, 'MANO DE OBRA / SERVICIOS', ['#', 'Descripcion', 'Cant.', 'P. unit.', 'Total'], $labor);

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

        $sheet->getStyle('A1:E' . $r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range(1, $r) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }

        return $spreadsheet;
    }

    private static function writeCommercialTermsBlock(Worksheet $sheet, int $startRow, WorkshopMovement $order): int
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
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $r++;

        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $value);
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
        $rows
    ): int {
        $r = $startRow;
        $sheet->setCellValue("A{$r}", $title);
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CBD5E1');
        $r++;

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$r}", $h);
            $col++;
        }
        $sheet->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $r++;

        $i = 1;
        foreach ($rows as $d) {
            $sheet->setCellValue("A{$r}", $i);
            $sheet->setCellValue("B{$r}", (string) $d->description);
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
}
