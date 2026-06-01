<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista previa {{ $documentCode }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: {{ ($format ?? 'a4') === 'ticket' ? '4mm 3mm' : '12mm 14mm' }};
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: {{ ($format ?? 'a4') === 'ticket' ? '11px' : '12px' }};
        }
        .preview-banner {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .header-left { width: 58%; padding-right: 12px; }
        .header-right { width: 42%; }
        .logo { max-width: 130px; max-height: 72px; object-fit: contain; display: block; margin-bottom: 6px; }
        .company-name { margin: 0; font-size: {{ ($format ?? 'a4') === 'ticket' ? '13px' : '16px' }}; font-weight: 800; text-transform: uppercase; line-height: 1.15; }
        .company-address { margin: 4px 0 0; font-size: 10px; line-height: 1.3; text-transform: uppercase; }
        .doc-box {
            border: 1.5px solid #334155;
            text-align: center;
            padding: 14px 10px;
            min-height: 108px;
        }
        .doc-title { margin: 0; font-size: {{ ($format ?? 'a4') === 'ticket' ? '14px' : '20px' }}; font-weight: 800; line-height: 1.1; }
        .doc-ruc { margin: 6px 0 0; font-size: 14px; font-weight: 700; }
        .doc-code { margin: 2px 0 0; font-size: 16px; font-weight: 700; }
        .gap { height: 12px; }
        .info td { padding: 2px 0; vertical-align: top; font-size: 12px; }
        .info-label { width: 130px; font-weight: 700; white-space: nowrap; }
        .items { margin-top: 10px; }
        .items th, .items td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            font-size: {{ ($format ?? 'a4') === 'ticket' ? '10px' : '11px' }};
        }
        .items th { background: #f8fafc; font-weight: 700; text-align: center; }
        .items td.num { text-align: right; white-space: nowrap; }
        .items td.center { text-align: center; }
        .bottom { margin-top: 12px; width: 100%; }
        .bottom td { vertical-align: top; }
        .bottom-left { width: 55%; padding-right: 10px; }
        .bottom-right { width: 45%; }
        .totals td { padding: 3px 6px; font-size: 11px; border-bottom: 1px solid #e2e8f0; }
        .totals .label { text-align: left; font-weight: 600; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand td { font-weight: 800; font-size: 13px; border-top: 2px solid #334155; }
        .words { margin-top: 8px; font-size: 12px; font-weight: 700; }
        .plate-legend { margin-top: 6px; font-size: 11px; font-weight: 700; }
        .ref-line { margin-top: 4px; font-size: 11px; font-weight: 700; }
        .obs { margin-top: 8px; font-size: 11px; }
        .credit-box { margin-top: 14px; border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 11px; }
        .credit-box h4 { margin: 0 0 6px; font-size: 12px; font-weight: 800; }
        .credit-table { width: 100%; margin-top: 6px; border-collapse: collapse; }
        .credit-table th, .credit-table td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: center; font-size: 11px; }
        .credit-table th { background: #f8fafc; font-weight: 700; }
        .sunat-footer {
            margin-top: 14px;
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            font-size: 10px;
            line-height: 1.35;
            text-align: center;
        }
        @media print {
            .preview-banner { display: none; }
            body { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="preview-banner">VISTA PREVIA — No tiene validez tributaria hasta confirmar y emitir el comprobante</div>

    <table class="header">
        <tr>
            <td class="header-left">
                @if(!empty($logoDataUri) || !empty($logoFileUrl) || !empty($logoUrl))
                    <img src="{{ $logoDataUri ?: ($logoFileUrl ?: $logoUrl) }}" alt="Logo" class="logo">
                @endif
                <p class="company-name">{{ $branchName }}</p>
                @if($branchAddress !== '')
                    <p class="company-address">{{ $branchAddress }}</p>
                @endif
            </td>
            <td class="header-right">
                <div class="doc-box">
                    <p class="doc-title">{{ $documentTitle }}</p>
                    <p class="doc-ruc">RUC {{ $branchRuc ?: '-' }}</p>
                    <p class="doc-code">{{ $documentCode }}</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    <table class="info">
        <tr>
            <td class="info-label">Fecha de Emisión:</td>
            <td>{{ $issueDate }}</td>
            <td class="info-label" style="width:110px;padding-left:8px;">Forma de Pago:</td>
            <td>{{ $paymentLabel }}</td>
        </tr>
        <tr>
            <td class="info-label">Señor(es):</td>
            <td colspan="3">{{ $customerName }}</td>
        </tr>
        <tr>
            <td class="info-label">RUC:</td>
            <td>{{ $customerDocument !== '' ? $customerDocument : '-' }}</td>
            <td class="info-label" style="padding-left:8px;">Tipo de Moneda:</td>
            <td>{{ $currencyLabel }}</td>
        </tr>
        <tr>
            <td class="info-label">Dirección del Cliente:</td>
            <td colspan="3">{{ $customerAddress !== '' ? $customerAddress : '-' }}</td>
        </tr>
        @if($isCredit ?? false)
            <tr>
                <td class="info-label">Días de crédito:</td>
                <td>{{ (int) ($creditDays ?? 0) }}</td>
                <td class="info-label" style="padding-left:8px;">Fecha vencimiento:</td>
                <td>{{ $creditDueDate ?? '-' }}</td>
            </tr>
        @endif
        <tr>
            <td class="info-label">Observación:</td>
            <td colspan="3">{{ $observation }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:9%;">Cantidad</th>
                <th style="width:11%;">Unidad<br>Medida</th>
                <th>Descripción</th>
                <th style="width:13%;">Valor<br>Unitario</th>
                <th style="width:9%;">ICBPER</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="center">{{ number_format((float) $line['qty'], 2) }}</td>
                    <td class="center">{{ $line['unit'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ number_format((float) $line['unit_value'], 2) }}</td>
                    <td class="num">{{ number_format((float) $line['icbper'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center">Sin líneas para facturar</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="bottom">
        <tr>
            <td class="bottom-left">
                <p class="words">{{ $totalInWords }}</p>
                <p class="ref-line">Orden de Servicio: {{ $serviceOrderNumber ?? '-' }}</p>
                @if(!empty($purchaseOrderNumber))
                    <p class="ref-line">Orden de Compra: {{ $purchaseOrderNumber }}</p>
                @endif
                @if(!empty($vehiclePlateLegend))
                    <p class="plate-legend">{{ $vehiclePlateLegend }}</p>
                @endif
                <p class="obs"><strong>Observación:</strong> {{ $observation }}</p>
                @if(($totals['apply_detraccion'] ?? false))
                    <p class="obs">Operación sujeta a detracción ({{ $totals['detraccion_percent'] ?? 0 }}%) — Tipo {{ $totals['detraccion_type'] ?? '020' }}</p>
                @endif
            </td>
            <td class="bottom-right">
                <table class="totals">
                    <tr>
                        <td class="label">Sub Total Ventas</td>
                        <td class="value">S/ {{ number_format((float) ($totals['subtotal_sales'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Anticipos</td>
                        <td class="value">S/ {{ number_format((float) ($totals['advances'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Descuentos</td>
                        <td class="value">S/ {{ number_format((float) ($totals['discounts'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Valor Venta</td>
                        <td class="value">S/ {{ number_format((float) ($totals['sale_value'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">ISC</td>
                        <td class="value">S/ {{ number_format((float) ($totals['isc'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">IGV</td>
                        <td class="value">S/ {{ number_format((float) ($totals['igv'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">ICBPER</td>
                        <td class="value">S/ {{ number_format((float) ($totals['icbper'] ?? 0), 2) }}</td>
                    </tr>
                    @if(($totals['apply_detraccion'] ?? false))
                        <tr>
                            <td class="label">Detracción ({{ $totals['detraccion_percent'] ?? 0 }}%)</td>
                            <td class="value">S/ {{ number_format((float) ($totals['detraccion'] ?? 0), 2) }}</td>
                        </tr>
                    @endif
                    @if(($totals['apply_retencion'] ?? false))
                        <tr>
                            <td class="label">Retención ({{ $totals['retencion_percent'] ?? 0 }}%)</td>
                            <td class="value">S/ {{ number_format((float) ($totals['retencion'] ?? 0), 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">Otros Cargos</td>
                        <td class="value">S/ {{ number_format((float) ($totals['other_charges'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Monto de redondeo</td>
                        <td class="value">S/ {{ number_format((float) ($totals['rounding'] ?? 0), 2) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="label">Importe Total</td>
                        <td class="value">S/ {{ number_format((float) ($totals['total'] ?? 0), 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(($isCredit ?? false) && count($creditInstallments ?? []) > 0)
        <div class="credit-box">
            <h4>Información del crédito</h4>
            <p>Monto neto pendiente de pago: S/ {{ number_format((float) ($totals['total'] ?? 0), 2) }}</p>
            <p>Total de cuotas: {{ count($creditInstallments) }}</p>
            <table class="credit-table">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Fec. Venc.</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creditInstallments as $installment)
                        <tr>
                            <td>{{ $installment['number'] ?? 'Cuota001' }}</td>
                            <td>{{ $installment['due_date'] ?? '-' }}</td>
                            <td>S/ {{ number_format((float) ($installment['amount'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($showSunatFooter ?? false)
        <div class="sunat-footer">
            Esta es una representación impresa de la factura electrónica, generada en el Sistema de la SUNAT. Puede verificarla utilizando su clave SOL.
        </div>
    @endif
</body>
</html>
