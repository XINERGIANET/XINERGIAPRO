<?php

namespace App\Services\Workshop;

use App\Models\Branch;
use App\Models\BranchElectronicBillingConfig;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\WorkshopMovement;
use App\Services\ApisunatService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkshopCheckoutDocumentPreviewService
{
    public function __construct(
        private readonly ApisunatService $apisunatService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, WorkshopMovement $order, int $branchId): array
    {
        $order->loadMissing(['movement', 'vehicle', 'client', 'branch.company', 'details' => fn ($q) => $q
            ->whereNull('sales_movement_id')
            ->whereNull('deleted_at')
            ->orderBy('id')]);

        $branch = $order->branch instanceof Branch
            ? $order->branch
            : Branch::query()->with('company')->find($branchId);

        $documentTypeId = (int) $request->input('document_type_id', 0);
        $documentType = $documentTypeId > 0
            ? DocumentType::query()->find($documentTypeId)
            : null;

        $cashRegisterId = (int) $request->input('cash_register_id', 0);
        $cashRegister = $cashRegisterId > 0
            ? CashRegister::query()->where('branch_id', $branchId)->find($cashRegisterId)
            : null;

        $isInvoice = $documentType && str_contains(mb_strtolower((string) $documentType->name), 'factura');
        $isBoleta = $documentType && str_contains(mb_strtolower((string) $documentType->name), 'boleta');
        $isTicket = $documentType && str_contains(mb_strtolower((string) $documentType->name), 'ticket');

        $isSunatActive = $this->apisunatService->isConfiguredForBranch($branch);
        [$series, $number] = $this->resolvePreviewSeriesNumber(
            $branch,
            $documentType,
            $cashRegister,
            $isInvoice,
            $isSunatActive,
            $request
        );

        $documentTitle = $this->resolveDocumentTitle($documentType, $isSunatActive, $isInvoice, $isBoleta, $isTicket);
        $documentCode = trim($series . ($number !== '' ? '-' . $number : ''));

        $lines = $this->buildPreviewLines($request, $order, $branchId);
        $totals = $this->calculateTotals($lines);

        $paymentType = strtoupper((string) $request->input('payment_type', 'CONTADO'));
        $isCredit = $paymentType === 'DEUDA';
        $creditDays = max(0, (int) $request->input('credit_days', 0));
        $debtDueDate = trim((string) $request->input('debt_due_date', ''));
        if ($isCredit && $debtDueDate === '' && $creditDays > 0) {
            $debtDueDate = now()->startOfDay()->addDays($creditDays)->format('Y-m-d');
        }

        $applyDetraccion = $isSunatActive && $isInvoice && $isCredit && $request->boolean('sunat_apply_detraccion');
        $applyRetencion = $isSunatActive && $isInvoice && $isCredit && $request->boolean('sunat_apply_retencion');
        $detraccionPercent = round((float) $request->input('sunat_detraccion_percent', 12), 2);
        $retencionPercent = round((float) $request->input('sunat_retencion_percent', 3), 2);
        $detraccionAmount = $applyDetraccion ? round($totals['total'] * ($detraccionPercent / 100), 2) : 0.0;
        $retencionAmount = $applyRetencion ? round($totals['total'] * ($retencionPercent / 100), 2) : 0.0;

        $vehiclePlate = trim((string) ($order->vehicle?->plate ?? ''));
        $serviceOrderNumber = trim((string) ($order->movement?->number ?? ''));
        if ($serviceOrderNumber === '') {
            $serviceOrderNumber = '#' . $order->id;
        }
        $purchaseOrderNumber = trim((string) $request->input('purchase_order_number', ''));
        $observation = trim((string) $request->input('sale_comment', ''));
        if ($observation === '') {
            $observation = 'Venta generada desde tablero de mantenimiento OS #' . ($order->movement?->number ?? $order->id);
        }

        $paymentLabel = $this->buildPaymentLabel($isCredit, $creditDays, $debtDueDate, $request);
        [$logoUrl, $logoFileUrl, $logoDataUri] = $this->resolveBranchLogoSources((string) ($branch?->logo ?? ''));

        return [
            'isPreview' => true,
            'format' => in_array($request->input('format'), ['ticket', 'a4'], true) ? $request->input('format') : 'a4',
            'documentTitle' => $documentTitle,
            'documentCode' => $documentCode,
            'branch' => $branch,
            'branchName' => strtoupper(trim((string) ($branch?->legal_name ?? config('app.name')))),
            'branchRuc' => trim((string) ($branch?->ruc ?? '')),
            'branchAddress' => trim((string) ($branch?->address ?? '')),
            'customerName' => trim((string) (($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? ''))) ?: 'CLIENTES VARIOS',
            'customerDocument' => trim((string) ($order->client?->document_number ?? '')),
            'customerAddress' => trim((string) ($order->client?->address ?? '')),
            'issueDate' => now()->format('d/m/Y'),
            'currencyLabel' => 'SOLES',
            'paymentLabel' => $paymentLabel,
            'isCredit' => $isCredit,
            'creditDays' => $creditDays,
            'creditDueDate' => $debtDueDate !== '' ? Carbon::createFromFormat('Y-m-d', $debtDueDate)->format('d/m/Y') : null,
            'observation' => $observation,
            'vehiclePlate' => $vehiclePlate,
            'serviceOrderNumber' => $serviceOrderNumber,
            'purchaseOrderNumber' => $purchaseOrderNumber,
            'vehiclePlateLegend' => $vehiclePlate !== ''
                ? 'Combustible y/o gastos mantenimiento-Placa Vehicular: ' . $vehiclePlate
                : null,
            'creditInstallments' => $isCredit ? [[
                'number' => 'Cuota001',
                'due_date' => $this->formatPreviewDueDate($debtDueDate),
                'amount' => round((float) $totals['total'], 2),
            ]] : [],
            'lines' => $lines,
            'totals' => array_merge($totals, [
                'advances' => abs((float) collect($lines)->where('is_advance', true)->sum('line_total')),
                'detraccion' => $detraccionAmount,
                'retencion' => $retencionAmount,
                'detraccion_percent' => $detraccionPercent,
                'retencion_percent' => $retencionPercent,
                'apply_detraccion' => $applyDetraccion,
                'apply_retencion' => $applyRetencion,
                'detraccion_type' => trim((string) $request->input('sunat_detraccion_type', '020')) ?: '020',
            ]),
            'totalInWords' => $this->amountToSpanishWords((float) $totals['total']),
            'showSunatFooter' => $isSunatActive && ($isInvoice || $isBoleta),
            'logoUrl' => $logoUrl,
            'logoFileUrl' => $logoFileUrl,
            'logoDataUri' => $logoDataUri,
            'printedAt' => now(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePreviewSeriesNumber(
        ?Branch $branch,
        ?DocumentType $documentType,
        ?CashRegister $cashRegister,
        bool $isInvoice,
        bool $isSunatActive,
        Request $request
    ): array {
        if ($isSunatActive && $isInvoice && $branch) {
            $config = BranchElectronicBillingConfig::query()
                ->where('branch_id', $branch->id)
                ->where('enabled', true)
                ->first();
            $series = trim((string) ($config?->series_factura ?: config('apisunat.series.factura', 'F001')));
            $next = $this->guessNextCorrelative($branch->id, $documentType?->id, $series);

            return [$series, $next];
        }

        $billingStatus = strtoupper((string) $request->input('billing_status', 'PENDING'));
        if ($isInvoice && $billingStatus === 'INVOICED') {
            $series = trim((string) $request->input('invoice_series', '')) ?: '001';
            $number = trim((string) $request->input('invoice_number', ''));

            return [$series, $number !== '' ? $number : '00000001'];
        }

        $series = trim((string) ($cashRegister?->series ?? '001')) ?: '001';
        $number = trim((string) $request->input('preview_number', ''));
        if ($number === '' && $documentType) {
            $number = $this->guessNextCorrelative((int) $branch?->id, (int) $documentType->id, $series);
        }

        return [$series, $number !== '' ? $number : '00000001'];
    }

    private function guessNextCorrelative(int $branchId, ?int $documentTypeId, string $series): string
    {
        if ($branchId <= 0 || ! $documentTypeId) {
            return '00000001';
        }

        $last = DB::table('movements as m')
            ->join('sales_movements as sm', 'sm.movement_id', '=', 'm.id')
            ->where('m.branch_id', $branchId)
            ->where('m.document_type_id', $documentTypeId)
            ->where('sm.series', $series)
            ->whereNull('m.deleted_at')
            ->whereNull('sm.deleted_at')
            ->orderByDesc('m.id')
            ->value('sm.billing_number');

        if (! $last) {
            $last = DB::table('movements')
                ->where('branch_id', $branchId)
                ->where('document_type_id', $documentTypeId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->value('number');
        }

        $digits = preg_replace('/\D+/', '', (string) $last) ?: '0';
        $next = (int) $digits + 1;

        return str_pad((string) max(1, $next), 8, '0', STR_PAD_LEFT);
    }

    private function resolveDocumentTitle(
        ?DocumentType $documentType,
        bool $isSunatActive,
        bool $isInvoice,
        bool $isBoleta,
        bool $isTicket
    ): string {
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

        return strtoupper(trim((string) ($documentType?->name ?? 'COMPROBANTE')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPreviewLines(Request $request, WorkshopMovement $order, int $branchId): array
    {
        $lines = [];
        $defaultUnit = Unit::query()->where('abbreviation', 'NIU')->first()
            ?: Unit::query()->orderBy('id')->first();

        foreach ($order->details as $detail) {
            $qty = (float) $detail->qty;
            $lineTotal = (float) $detail->total;
            if ($qty <= 0 && abs($lineTotal) < 0.0001) {
                continue;
            }

            $taxRatePct = 0.0;
            if ($detail->tax_rate_id) {
                $taxRatePct = (float) (TaxRate::query()->find($detail->tax_rate_id)?->tax_rate ?? 0);
            }
            if ($taxRatePct <= 0 && $lineTotal > 0 && (float) $detail->subtotal > 0) {
                $taxRatePct = round((((float) $detail->total / (float) $detail->subtotal) - 1) * 100, 2);
            }
            if ($taxRatePct <= 0) {
                $taxRatePct = 18.0;
            }

            $netTotal = (float) ($detail->subtotal > 0 ? $detail->subtotal : ($lineTotal / (1 + ($taxRatePct / 100))));
            $unitValue = $qty > 0 ? ($netTotal / $qty) : $netTotal;

            $lines[] = [
                'qty' => $qty,
                'unit' => $defaultUnit?->abbreviation ?: 'NIU',
                'description' => (string) ($detail->description ?? 'Detalle'),
                'unit_value' => round($unitValue, 2),
                'icbper' => 0.0,
                'line_total' => round($netTotal, 2),
                'is_advance' => strtoupper((string) $detail->line_type) === 'ANTICIPO' || $lineTotal < 0,
            ];
        }

        $productLines = collect($request->input('product_lines', []))
            ->filter(fn ($row) => ! empty($row['product_id']) && (float) ($row['qty'] ?? 0) > 0)
            ->values();

        if ($productLines->isNotEmpty()) {
            $productIds = $productLines->pluck('product_id')->map(fn ($v) => (int) $v)->all();
            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
            $branches = ProductBranch::query()
                ->where('branch_id', $branchId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            foreach ($productLines as $row) {
                $productId = (int) $row['product_id'];
                $product = $products->get($productId);
                if (! $product) {
                    continue;
                }

                $qty = (float) $row['qty'];
                $unitPrice = (float) ($row['unit_price'] ?? 0);
                $gross = round($qty * $unitPrice, 2);
                $taxRatePct = 18.0;
                $pb = $branches->get($productId);
                if ($pb?->tax_rate_id) {
                    $taxRatePct = (float) (TaxRate::query()->find($pb->tax_rate_id)?->tax_rate ?? 18);
                }
                $netTotal = $taxRatePct > 0 ? round($gross / (1 + ($taxRatePct / 100)), 2) : $gross;
                $unitValue = $qty > 0 ? round($netTotal / $qty, 2) : 0;

                $lines[] = [
                    'qty' => $qty,
                    'unit' => $defaultUnit?->abbreviation ?: 'NIU',
                    'description' => trim((string) ($product->description ?? $product->code ?? 'Producto')),
                    'unit_value' => $unitValue,
                    'icbper' => 0.0,
                    'line_total' => $netTotal,
                    'is_advance' => false,
                ];
            }
        }

        if ($lines === []) {
            $payTotal = collect($request->input('payment_methods', []))
                ->sum(fn ($row) => (float) ($row['amount'] ?? 0));
            if ($payTotal > 0) {
                $gross = round($payTotal, 2);
                $netTotal = round($gross / 1.18, 2);
                $lines[] = [
                    'qty' => 1,
                    'unit' => $defaultUnit?->abbreviation ?: 'NIU',
                    'description' => 'PAGO ANTICIPADO OS #' . ($order->movement?->number ?? $order->id),
                    'unit_value' => $netTotal,
                    'icbper' => 0.0,
                    'line_total' => $netTotal,
                    'is_advance' => false,
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, float>
     */
    private function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) ($line['line_total'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * 0.18, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'subtotal_sales' => $subtotal,
            'discounts' => 0.0,
            'sale_value' => $subtotal,
            'isc' => 0.0,
            'igv' => $tax,
            'icbper' => 0.0,
            'other_charges' => 0.0,
            'rounding' => 0.0,
            'total' => $total,
        ];
    }

    private function buildPaymentLabel(bool $isCredit, int $creditDays, string $debtDueDate, Request $request): string
    {
        if (! $isCredit) {
            return 'Contado';
        }

        $parts = ['Credito'];
        if ($creditDays > 0) {
            $parts[] = $creditDays . ' dias';
        }
        if ($debtDueDate !== '') {
            try {
                $parts[] = 'Vence: ' . Carbon::createFromFormat('Y-m-d', $debtDueDate)->format('d/m/Y');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($request->boolean('sunat_apply_detraccion')) {
            $parts[] = 'Detraccion ' . round((float) $request->input('sunat_detraccion_percent', 12), 2) . '%';
        }
        if ($request->boolean('sunat_apply_retencion')) {
            $parts[] = 'Retencion ' . round((float) $request->input('sunat_retencion_percent', 3), 2) . '%';
        }

        return implode(' · ', $parts);
    }

    private function formatPreviewDueDate(string $debtDueDate): string
    {
        if ($debtDueDate === '') {
            return now()->format('d/m/Y');
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $debtDueDate)->format('d/m/Y');
        } catch (\Throwable $e) {
            return now()->format('d/m/Y');
        }
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
