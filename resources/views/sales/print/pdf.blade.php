<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $sale->salesDocumentCode() }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 24px 28px;
            font-family: Arial, sans-serif;
            color: #111827;
            font-size: 14px;
        }
        .page {
            width: 100%;
        }
        .header-table,
        .customer-table,
        .items-table,
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left {
            width: 58%;
            padding-right: 20px;
        }
        .header-right {
            width: 42%;
        }
        .logo-wrap {
            min-height: 86px;
            margin-bottom: 10px;
        }
        .logo {
            display: block;
            max-width: 150px;
            max-height: 90px;
            object-fit: contain;
        }
        .company-name {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .company-ruc {
            margin: 4px 0 0;
            font-size: 13px;
            font-weight: 700;
        }
        .company-address {
            margin: 5px 0 0;
            font-size: 11px;
            line-height: 1.35;
            text-transform: uppercase;
        }
        .doc-box {
            border: 1.6px solid #475569;
            text-align: center;
            padding: 18px 16px;
            min-height: 132px;
        }
        .doc-title {
            margin: 0;
            font-size: 23px;
            font-weight: 800;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .doc-ruc {
            margin: 8px 0 0;
            font-size: 16px;
            line-height: 1.2;
        }
        .doc-code {
            margin: 2px 0 0;
            font-size: 18px;
            line-height: 1.2;
        }
        .section-gap {
            height: 18px;
        }
        .customer-table td {
            padding: 2px 4px 2px 0;
            vertical-align: top;
            font-size: 13px;
        }
        .customer-label {
            width: 122px;
            font-weight: 800;
            white-space: nowrap;
        }
        .customer-value {
            word-break: break-word;
        }
        .items-table {
            margin-top: 14px;
            table-layout: fixed;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            font-size: 13px;
        }
        .items-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
        }
        .items-table .num {
            text-align: right;
            white-space: nowrap;
        }
        .items-table .center {
            text-align: center;
        }
        .items-table .um-col {
            white-space: nowrap;
            font-size: 12px;
        }
        .items-table tbody tr {
            page-break-inside: avoid;
        }
        .totals-wrap {
            width: 370px;
            margin-left: auto;
            margin-top: 16px;
        }
        .totals-table td {
            padding: 2px 0;
            font-size: 13px;
        }
        .totals-label {
            font-weight: 800;
            padding-right: 14px;
        }
        .totals-value {
            text-align: right;
            white-space: nowrap;
        }
        .grand-total td {
            border-top: 2px solid #1f2937;
            padding-top: 7px;
            font-size: 18px;
            font-weight: 800;
        }
        .notes {
            margin-top: 28px;
        }
        .notes-title {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 800;
        }
        .notes-text {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
        }
        .printed-at {
            margin-top: 18px;
            color: #475569;
            font-size: 12px;
        }
        @media print {
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'COMPROBANTE');
    $docCode = $sale->salesDocumentCode();
    $branchName = strtoupper((string) ($branchForLogo->legal_name ?? 'SUCURSAL'));
    $branchAddress = trim((string) ($branchForLogo->address ?? ''));
    $customerDocument = trim((string) ($sale->person?->document_number ?? '-'));
    $customerAddress = trim((string) ($sale->person?->address ?? '-'));
    $currencyCode = strtoupper((string) ($sale->salesMovement?->currency ?? 'PEN'));
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="logo-wrap">
                    @if(!empty($logoDataUri) || !empty($logoFileUrl) || !empty($logoUrl))
                        <img src="{{ $logoDataUri ?: ($logoFileUrl ?: $logoUrl) }}" alt="Logo sucursal" class="logo">
                    @endif
                </div>
                <p class="company-name">{{ $branchName }}</p>
                @if($branchAddress !== '')
                    <p class="company-address">{{ $branchAddress }}</p>
                @endif
            </td>
            <td class="header-right">
                <div class="doc-box">
                    <p class="doc-title">{{ $docName }}</p>
                    <p class="doc-ruc">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
                    <p class="doc-code">{{ $docCode }}</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-gap"></div>

    <table class="customer-table">
        <tr>
            <td class="customer-label">Fecha de emision:</td>
            <td class="customer-value">{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="customer-label">Senor(es):</td>
            <td class="customer-value">{{ $sale->person_name ?? 'CLIENTES VARIOS' }}</td>
        </tr>
        <tr>
            <td class="customer-label">RUC/DNI:</td>
            <td class="customer-value">{{ $customerDocument !== '' ? $customerDocument : '-' }}</td>
        </tr>
        <tr>
            <td class="customer-label">Direccion:</td>
            <td class="customer-value">{{ $customerAddress !== '' ? $customerAddress : '-' }}</td>
        </tr>
        <tr>
            <td class="customer-label">Moneda:</td>
            <td class="customer-value">{{ $currencyCode }}</td>
        </tr>
        <tr>
            <td class="customer-label">Forma de pago:</td>
            <td class="customer-value">{{ $paymentLabel }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 7%;">Item</th>
                <th style="width: 27%;">Descripcion</th>
                <th style="width: 11%;" class="center">U.M.</th>
                <th style="width: 10%;" class="num">Cantidad</th>
                <th style="width: 9%;" class="num">V.U.</th>
                <th style="width: 9%;" class="num">P.U.</th>
                <th style="width: 10%;" class="num">Dcto.</th>
                <th style="width: 17%;" class="num">Valor de venta</th>
            </tr>
        </thead>
        <tbody>
        @forelse($details as $i => $detail)
            @php
                $qty = (float) $detail->quantity;
                $grossLineTotal = (float) $detail->amount;
                $netLineTotal = (float) ($detail->original_amount ?? 0);
                $taxRatePct = (float) data_get($detail->tax_rate_snapshot, 'tax_rate', 0);
                $taxRateFactor = $taxRatePct > 0 ? ($taxRatePct / 100) : 0;

                if ($netLineTotal <= 0 && $grossLineTotal > 0) {
                    $netLineTotal = $taxRateFactor > 0
                        ? ($grossLineTotal / (1 + $taxRateFactor))
                        : $grossLineTotal;
                }

                $discountPct = (float) ($detail->discount_percentage ?? 0);
                $discountAmount = 0.0;
                if ($discountPct > 0) {
                    $discountAmount = round($netLineTotal * ($discountPct / 100), 6);
                }

                $netLineTotalAfterDiscount = $netLineTotal - $discountAmount;
                $unitValue = $qty > 0 ? ($netLineTotalAfterDiscount / $qty) : 0;
                $unitPrice = $qty > 0 ? ($grossLineTotal / $qty) : 0;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $detail->description ?? $detail->product?->description ?? '-' }}</td>
                <td class="center um-col">{{ $detail->unit?->code ?? $detail->unit?->description ?? '-' }}</td>
                <td class="num">{{ number_format($qty, 2) }}</td>
                <td class="num">S/ {{ number_format($unitValue, 3) }}</td>
                <td class="num">S/ {{ number_format($unitPrice, 3) }}</td>
                <td class="num">S/ {{ number_format($discountAmount, 2) }}</td>
                <td class="num">S/ {{ number_format($netLineTotalAfterDiscount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">Sin detalle</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals-table">
            <tr>
                <td class="totals-label">Op. gravada:</td>
                <td class="totals-value">S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">I.G.V.:</td>
                <td class="totals-value">S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td class="totals-label">Importe total:</td>
                <td class="totals-value">S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="notes">
        <p class="notes-title">Observacion:</p>
        <p class="notes-text">{{ $sale->comment ?: '-' }}</p>
        <p class="printed-at">Impreso el {{ $printedAt->format('d/m/Y H:i:s') }}</p>
    </div>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
