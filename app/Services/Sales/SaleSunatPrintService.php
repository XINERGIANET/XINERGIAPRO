<?php

namespace App\Services\Sales;

use App\Models\Branch;
use App\Models\Movement;
use App\Models\SaleAdvance;
use App\Models\Unit;
use App\Services\ApisunatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleSunatPrintService
{
    public function __construct(
        private readonly ApisunatService $apisunatService
    ) {}

    /**
     * Datos para workshop.maintenance-board.print.sunat-comprobante (venta emitida).
     *
     * @return array<string, mixed>
     */
    public function build(Movement $sale, string $format = 'a4'): array
    {
        $sale->loadMissing([
            'branch.company',
            'person',
            'documentType',
            'salesMovement.details.unit',
        ]);

        $branch = $sale->branch instanceof Branch ? $sale->branch : null;
        $salesMovement = $sale->salesMovement;
        $meta = is_array($salesMovement?->sunat_billing_meta) ? $salesMovement->sunat_billing_meta : [];

        $docNameLower = mb_strtolower((string) ($sale->documentType?->name ?? ''), 'UTF-8');
        $isInvoice = str_contains($docNameLower, 'factura');
        $isBoleta = str_contains($docNameLower, 'boleta');
        $isTicket = str_contains($docNameLower, 'ticket');
        $isSunatActive = $this->apisunatService->isConfiguredForBranch($branch);
        $isElectronicSent = strtoupper(trim((string) ($sale->electronic_invoice_status ?? ''))) === 'SENT';

        $documentTitle = $this->resolveDocumentTitle($sale, $isSunatActive, $isElectronicSent, $isInvoice, $isBoleta, $isTicket);
        $documentCode = $sale->salesDocumentCode();

        $lines = $this->buildLinesFromSale($sale);
        $subtotal = round((float) ($salesMovement?->subtotal ?? 0), 2);
        $tax = round((float) ($salesMovement?->tax ?? 0), 2);
        $total = round((float) ($salesMovement?->total ?? 0), 2);

        if ($subtotal <= 0 && $lines !== []) {
            $subtotal = round(collect($lines)->sum('line_total'), 2);
        }
        if ($tax <= 0 && $subtotal > 0) {
            $tax = round($total - $subtotal, 2);
        }
        if ($total <= 0) {
            $total = round($subtotal + $tax, 2);
        }

        $advancesTotal = $this->resolveAppliedAdvancesTotal($sale);

        $paymentType = strtoupper((string) ($salesMovement?->payment_type ?? ''));
        $isCredit = in_array($paymentType, ['CREDIT', 'CREDITO', 'DEUDA'], true);

        $creditDays = max(0, (int) ($meta['credit_days'] ?? 0));
        $creditDueDateRaw = trim((string) ($meta['credit_due_date'] ?? ''));
        if ($isCredit && $creditDueDateRaw === '') {
            $creditDueDateRaw = $this->resolveCreditDueDateFromCashMovement($sale);
        }

        $applyDetraccion = (bool) ($meta['apply_detraccion'] ?? false);
        $applyRetencion = (bool) ($meta['apply_retencion'] ?? false);
        $detraccionPercent = round((float) ($meta['detraccion_percent'] ?? 12), 2);
        $retencionPercent = round((float) ($meta['retencion_percent'] ?? 3), 2);
        $detraccionAmount = $applyDetraccion ? round($total * ($detraccionPercent / 100), 2) : 0.0;
        $retencionAmount = $applyRetencion ? round($total * ($retencionPercent / 100), 2) : 0.0;

        $serviceOrderNumber = trim((string) ($meta['service_order_number'] ?? ''));
        $purchaseOrderNumber = trim((string) ($meta['purchase_order_number'] ?? ''));
        $vehiclePlate = trim((string) ($meta['vehicle_plate'] ?? ''));
        $observation = trim((string) ($sale->comment ?? ''));

        [$logoUrl, $logoFileUrl, $logoDataUri] = $this->resolveBranchLogoSources((string) ($branch?->logo ?? ''));

        $creditDueDateFormatted = null;
        if ($creditDueDateRaw !== '') {
            try {
                $creditDueDateFormatted = Carbon::createFromFormat('Y-m-d', $creditDueDateRaw)->format('d/m/Y');
            } catch (\Throwable $e) {
                try {
                    $creditDueDateFormatted = Carbon::parse($creditDueDateRaw)->format('d/m/Y');
                } catch (\Throwable $e2) {
                    $creditDueDateFormatted = $creditDueDateRaw;
                }
            }
        }

        return [
            'isPreview' => false,
            'format' => $format === 'ticket' ? 'ticket' : 'a4',
            'documentTitle' => $documentTitle,
            'documentCode' => $documentCode,
            'branch' => $branch,
            'branchName' => strtoupper(trim((string) ($branch?->legal_name ?? config('app.name')))),
            'branchRuc' => trim((string) ($branch?->ruc ?? '')),
            'branchAddress' => trim((string) ($branch?->address ?? '')),
            'customerName' => trim((string) ($sale->person_name ?? '')) ?: 'CLIENTES VARIOS',
            'customerDocument' => trim((string) ($sale->person?->document_number ?? '')),
            'customerAddress' => trim((string) ($sale->person?->address ?? '')),
            'issueDate' => optional($sale->moved_at)->format('d/m/Y') ?? now()->format('d/m/Y'),
            'currencyLabel' => strtoupper((string) ($salesMovement?->currency ?? 'PEN')) === 'PEN' ? 'SOLES' : strtoupper((string) ($salesMovement?->currency ?? 'PEN')),
            'paymentLabel' => $this->buildPaymentLabel($sale, $isCredit, $creditDays, $creditDueDateRaw, $meta),
            'isCredit' => $isCredit,
            'creditDays' => $creditDays,
            'creditDueDate' => $creditDueDateFormatted,
            'observation' => $observation !== '' ? $observation : '-',
            'vehiclePlate' => $vehiclePlate,
            'serviceOrderNumber' => $serviceOrderNumber !== '' ? $serviceOrderNumber : '-',
            'purchaseOrderNumber' => $purchaseOrderNumber,
            'vehiclePlateLegend' => $vehiclePlate !== ''
                ? 'Combustible y/o gastos mantenimiento-Placa Vehicular: ' . $vehiclePlate
                : null,
            'creditInstallments' => $isCredit ? [[
                'number' => 'Cuota001',
                'due_date' => $creditDueDateFormatted ?? ($creditDueDateRaw !== '' ? $creditDueDateRaw : now()->format('d/m/Y')),
                'amount' => $total,
            ]] : [],
            'lines' => $lines,
            'totals' => [
                'subtotal_sales' => $subtotal,
                'advances' => round($advancesTotal, 2),
                'discounts' => 0.0,
                'sale_value' => $subtotal,
                'isc' => 0.0,
                'igv' => $tax,
                'icbper' => 0.0,
                'other_charges' => 0.0,
                'rounding' => 0.0,
                'total' => $total,
                'detraccion' => $detraccionAmount,
                'retencion' => $retencionAmount,
                'detraccion_percent' => $detraccionPercent,
                'retencion_percent' => $retencionPercent,
                'apply_detraccion' => $applyDetraccion,
                'apply_retencion' => $applyRetencion,
                'detraccion_type' => trim((string) ($meta['detraccion_type'] ?? '020')) ?: '020',
            ],
            'totalInWords' => $this->amountToSpanishWords($total),
            'showSunatFooter' => $isElectronicSent && ($isInvoice || $isBoleta),
            'logoUrl' => $logoUrl,
            'logoFileUrl' => $logoFileUrl,
            'logoDataUri' => $logoDataUri,
            'printedAt' => now(),
        ];
    }

    private function resolveDocumentTitle(
        Movement $sale,
        bool $isSunatActive,
        bool $isElectronicSent,
        bool $isInvoice,
        bool $isBoleta,
        bool $isTicket
    ): string {
        if ($isElectronicSent && $isInvoice) {
            return 'FACTURA ELECTRONICA';
        }
        if ($isElectronicSent && $isBoleta) {
            return 'BOLETA DE VENTA ELECTRONICA';
        }
        if ($isSunatActive && $isInvoice) {
            return 'FACTURA ELECTRONICA';
        }
        if ($isSunatActive && $isBoleta) {
            return 'BOLETA DE VENTA ELECTRONICA';
        }
        if ($isTicket) {
            return 'TICKET';
        }
        if ($isBoleta) {
            return 'BOLETA DE VENTA';
        }
        if ($isInvoice) {
            return 'FACTURA';
        }

        return strtoupper(trim((string) ($sale->documentType?->name ?? 'COMPROBANTE')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLinesFromSale(Movement $sale): array
    {
        $details = $sale->salesMovement?->details?->sortBy('id') ?? collect();
        $defaultUnit = Unit::query()->where('abbreviation', 'NIU')->first()
            ?: Unit::query()->orderBy('id')->first();
        $lines = [];

        foreach ($details as $detail) {
            $qty = (float) $detail->quantity;
            $grossLineTotal = (float) $detail->amount;
            $netLineTotal = (float) ($detail->original_amount ?? 0);
            $taxRatePct = (float) data_get($detail->tax_rate_snapshot, 'tax_rate', 0);

            if ($netLineTotal <= 0 && $grossLineTotal > 0) {
                $taxRateFactor = $taxRatePct > 0 ? ($taxRatePct / 100) : 0.18;
                $netLineTotal = $taxRateFactor > 0
                    ? round($grossLineTotal / (1 + $taxRateFactor), 2)
                    : $grossLineTotal;
            }

            $discountPct = (float) ($detail->discount_percentage ?? 0);
            if ($discountPct > 0) {
                $netLineTotal = round($netLineTotal * (1 - ($discountPct / 100)), 2);
            }

            $unitValue = $qty > 0 ? round($netLineTotal / $qty, 2) : round($netLineTotal, 2);
            $description = trim((string) ($detail->description ?? data_get($detail->product_snapshot, 'description') ?? 'Detalle'));

            $lines[] = [
                'qty' => $qty,
                'unit' => $detail->unit?->abbreviation ?: ($detail->unit?->code ?? ($defaultUnit?->abbreviation ?: 'NIU')),
                'description' => $description,
                'unit_value' => $unitValue,
                'icbper' => 0.0,
                'line_total' => round($netLineTotal, 2),
                'is_advance' => (bool) ($sale->salesMovement?->is_advance)
                    || str_contains(mb_strtolower($description, 'UTF-8'), 'anticipo'),
            ];
        }

        return $lines;
    }

    private function resolveAppliedAdvancesTotal(Movement $sale): float
    {
        $total = (float) SaleAdvance::query()
            ->where('final_movement_id', $sale->id)
            ->sum('applied_amount');

        if ($total > 0) {
            return $total;
        }

        if ((int) ($sale->parent_movement_id ?? 0) <= 0) {
            return 0.0;
        }

        return (float) Movement::query()
            ->where('parent_movement_id', (int) $sale->parent_movement_id)
            ->where('id', '!=', $sale->id)
            ->whereHas('salesMovement', fn ($q) => $q->where('is_advance', true))
            ->with('salesMovement')
            ->get()
            ->sum(fn (Movement $adv) => (float) ($adv->salesMovement?->total ?? 0));
    }

    private function resolveCreditDueDateFromCashMovement(Movement $sale): string
    {
        $cashMovementId = DB::table('cash_movements')
            ->where('movement_id', $sale->id)
            ->whereNull('deleted_at')
            ->value('id');

        if (! $cashMovementId) {
            return '';
        }

        $dueAt = DB::table('cash_movement_details')
            ->where('cash_movement_id', $cashMovementId)
            ->where('status', 'A')
            ->whereNotNull('due_at')
            ->orderBy('id')
            ->value('due_at');

        if (! $dueAt) {
            return '';
        }

        try {
            return Carbon::parse($dueAt)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function buildPaymentLabel(
        Movement $sale,
        bool $isCredit,
        int $creditDays,
        string $creditDueDateRaw,
        array $meta
    ): string {
        if (! $isCredit) {
            $cashMovement = $sale->cashMovement;
            if (! $cashMovement) {
                $cashMovementId = DB::table('cash_movements')
                    ->where('movement_id', $sale->id)
                    ->whereNull('deleted_at')
                    ->value('id');
                if ($cashMovementId) {
                    $methodName = DB::table('cash_movement_details as cmd')
                        ->leftJoin('payment_methods as pm', 'pm.id', '=', 'cmd.payment_method_id')
                        ->where('cmd.cash_movement_id', $cashMovementId)
                        ->where('cmd.status', 'A')
                        ->where('cmd.type', '!=', 'DEUDA')
                        ->orderBy('cmd.id')
                        ->value('pm.description');

                    return $methodName ? (string) $methodName : 'Contado';
                }
            }

            return 'Contado';
        }

        $parts = ['Credito'];
        if ($creditDays > 0) {
            $parts[] = $creditDays . ' dias';
        }
        if ($creditDueDateRaw !== '') {
            try {
                $parts[] = 'Vence: ' . Carbon::createFromFormat('Y-m-d', $creditDueDateRaw)->format('d/m/Y');
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if ((bool) ($meta['apply_detraccion'] ?? false)) {
            $parts[] = 'Detraccion ' . round((float) ($meta['detraccion_percent'] ?? 12), 2) . '%';
        }
        if ((bool) ($meta['apply_retencion'] ?? false)) {
            $parts[] = 'Retencion ' . round((float) ($meta['retencion_percent'] ?? 3), 2) . '%';
        }

        return implode(' · ', $parts);
    }

    private function amountToSpanishWords(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        $soles = (int) floor($amount);
        $centimos = (int) round(($amount - $soles) * 100);

        return 'SON: ' . strtoupper($this->numberToWordsEs($soles)) . ' Y ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100 SOLES';
    }

    private function numberToWordsEs(int $number): string
    {
        if ($number === 0) {
            return 'cero';
        }

        $units = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $teens = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $tens = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $parts = [];

        if ($number >= 1000000) {
            $millions = intdiv($number, 1000000);
            $parts[] = $millions === 1 ? 'un millon' : $this->numberToWordsEs($millions) . ' millones';
            $number %= 1000000;
        }

        if ($number >= 1000) {
            $thousands = intdiv($number, 1000);
            $parts[] = $thousands === 1 ? 'mil' : $this->numberToWordsEs($thousands) . ' mil';
            $number %= 1000;
        }

        if ($number >= 100) {
            $h = intdiv($number, 100);
            $parts[] = $number === 100 ? 'cien' : $hundreds[$h];
            $number %= 100;
        }

        if ($number >= 20) {
            $t = intdiv($number, 10);
            $u = $number % 10;
            $parts[] = $u === 0 ? $tens[$t] : $tens[$t] . ' y ' . $units[$u];
            $number = 0;
        } elseif ($number >= 10) {
            $parts[] = $teens[$number - 10];
            $number = 0;
        }

        if ($number > 0) {
            $parts[] = $units[$number];
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function resolveBranchLogoSources(string $storedLogo): array
    {
        $storedLogo = trim($storedLogo);
        if ($storedLogo === '') {
            return [null, null, null];
        }

        if (str_starts_with($storedLogo, 'http://') || str_starts_with($storedLogo, 'https://')) {
            return [$storedLogo, null, null];
        }

        $normalized = str_replace('\\', '/', ltrim($storedLogo, '/'));
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        $logoUrl = $normalized !== '' ? asset('storage/' . $normalized) : null;
        $localLogoPath = storage_path('app/public/' . $normalized);
        if (! file_exists($localLogoPath)) {
            return [$logoUrl, null, null];
        }

        $normalizedPath = str_replace('\\', '/', $localLogoPath);
        $logoFileUrl = 'file:///' . ltrim($normalizedPath, '/');
        $content = @file_get_contents($localLogoPath);
        if ($content === false) {
            return [$logoUrl, $logoFileUrl, null];
        }

        $mimeType = @mime_content_type($localLogoPath) ?: 'image/png';

        return [$logoUrl, $logoFileUrl, 'data:' . $mimeType . ';base64,' . base64_encode($content)];
    }
}
