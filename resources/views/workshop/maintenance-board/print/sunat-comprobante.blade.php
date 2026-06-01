<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $isPreview ?? false ? 'Vista previa' : 'Comprobante' }} {{ $documentCode }}</title>
    @php
        $isTicket = ($format ?? 'a4') === 'ticket';
    @endphp
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #fff;
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
        @media print {
            .preview-banner { display: none; }
        }

        /* ——— A4 ——— */
        body.format-a4 {
            margin: 12mm 14mm;
            font-size: 12px;
        }
        body.format-a4 table { width: 100%; border-collapse: collapse; }
        body.format-a4 .header td { vertical-align: top; }
        body.format-a4 .header-left { width: 58%; padding-right: 12px; }
        body.format-a4 .header-right { width: 42%; }
        body.format-a4 .logo { max-width: 130px; max-height: 72px; object-fit: contain; display: block; margin-bottom: 6px; }
        body.format-a4 .company-name { margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase; line-height: 1.15; }
        body.format-a4 .company-address { margin: 4px 0 0; font-size: 10px; line-height: 1.3; text-transform: uppercase; }
        body.format-a4 .doc-box {
            border: 1.5px solid #334155;
            text-align: center;
            padding: 14px 10px;
            min-height: 108px;
        }
        body.format-a4 .doc-title { margin: 0; font-size: 20px; font-weight: 800; line-height: 1.1; }
        body.format-a4 .doc-ruc { margin: 6px 0 0; font-size: 14px; font-weight: 700; }
        body.format-a4 .doc-code { margin: 2px 0 0; font-size: 16px; font-weight: 700; }
        body.format-a4 .gap { height: 12px; }
        body.format-a4 .info td { padding: 2px 0; vertical-align: top; font-size: 12px; }
        body.format-a4 .info-label { width: 130px; font-weight: 700; white-space: nowrap; }
        body.format-a4 .items { margin-top: 10px; }
        body.format-a4 .items th, body.format-a4 .items td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            font-size: 11px;
        }
        body.format-a4 .items th { background: #f8fafc; font-weight: 700; text-align: center; }
        body.format-a4 .items td.num { text-align: right; white-space: nowrap; }
        body.format-a4 .items td.center { text-align: center; }
        body.format-a4 .bottom { margin-top: 12px; width: 100%; }
        body.format-a4 .bottom td { vertical-align: top; }
        body.format-a4 .bottom-left { width: 55%; padding-right: 10px; }
        body.format-a4 .bottom-right { width: 45%; }
        body.format-a4 .totals td { padding: 3px 6px; font-size: 11px; border-bottom: 1px solid #e2e8f0; }
        body.format-a4 .totals .label { text-align: left; font-weight: 600; }
        body.format-a4 .totals .value { text-align: right; white-space: nowrap; }
        body.format-a4 .totals .grand td { font-weight: 800; font-size: 13px; border-top: 2px solid #334155; }
        body.format-a4 .words { margin-top: 8px; font-size: 12px; font-weight: 700; }
        body.format-a4 .plate-legend { margin-top: 6px; font-size: 11px; font-weight: 700; }
        body.format-a4 .ref-line { margin-top: 4px; font-size: 11px; font-weight: 700; }
        body.format-a4 .obs { margin-top: 8px; font-size: 11px; }
        body.format-a4 .credit-box { margin-top: 14px; border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 11px; }
        body.format-a4 .credit-box h4 { margin: 0 0 6px; font-size: 12px; font-weight: 800; }
        body.format-a4 .credit-table { width: 100%; margin-top: 6px; border-collapse: collapse; }
        body.format-a4 .credit-table th, body.format-a4 .credit-table td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: center; font-size: 11px; }
        body.format-a4 .credit-table th { background: #f8fafc; font-weight: 700; }
        body.format-a4 .sunat-footer {
            margin-top: 14px;
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            font-size: 10px;
            line-height: 1.35;
            text-align: center;
        }
        @media print {
            body.format-a4 { margin: 8mm; }
        }

        /* ——— Ticket 80mm ——— */
        body.format-ticket {
            font-size: 11px;
            line-height: 1.3;
        }
        .ticket-wrap {
            width: 100%;
            max-width: 80mm;
            margin: 0 auto;
            padding: 2mm 2.5mm 3mm;
        }
        .ticket-center { text-align: center; }
        .ticket-logo {
            display: block;
            max-width: 52mm;
            max-height: 22mm;
            margin: 0 auto 4px;
            object-fit: contain;
        }
        .ticket-company {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .ticket-address {
            margin: 2px 0 0;
            font-size: 9px;
            line-height: 1.25;
            text-transform: uppercase;
            color: #334155;
        }
        .ticket-doc-box {
            margin: 6px 0 0;
            border: 1.5px solid #0f172a;
            padding: 6px 4px;
            text-align: center;
        }
        .ticket-doc-title {
            margin: 0;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.15;
        }
        .ticket-doc-ruc {
            margin: 3px 0 0;
            font-size: 11px;
            font-weight: 700;
        }
        .ticket-doc-code {
            margin: 2px 0 0;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .ticket-sep {
            border: none;
            border-top: 1px dashed #94a3b8;
            margin: 6px 0;
        }
        .ticket-row {
            display: flex;
            gap: 4px;
            margin-bottom: 3px;
            font-size: 10px;
            align-items: flex-start;
        }
        .ticket-row .lbl {
            flex: 0 0 28mm;
            font-weight: 700;
            line-height: 1.25;
        }
        .ticket-row .val {
            flex: 1;
            min-width: 0;
            word-break: break-word;
            line-height: 1.25;
        }
        .ticket-row-full .val { flex: 1; }
        .ticket-payment {
            margin: 2px 0 4px;
            padding: 4px 5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            line-height: 1.3;
        }
        .ticket-items-head {
            display: grid;
            grid-template-columns: 9mm 10mm 1fr 14mm 11mm;
            gap: 1px;
            font-size: 8px;
            font-weight: 700;
            text-align: center;
            padding: 3px 0;
            border-bottom: 1px solid #0f172a;
            margin-bottom: 2px;
        }
        .ticket-item {
            padding: 4px 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        .ticket-item:last-child { border-bottom: none; }
        .ticket-item-grid {
            display: grid;
            grid-template-columns: 9mm 10mm 1fr 14mm 11mm;
            gap: 1px;
            font-size: 9px;
            text-align: center;
            align-items: start;
        }
        .ticket-item-grid .num { text-align: right; padding-right: 1px; }
        .ticket-item-grid .desc-cell {
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            word-break: break-word;
            line-height: 1.2;
            padding: 0 1px;
        }
        .ticket-total-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin: 2px 0;
            font-size: 10px;
        }
        .ticket-total-line .lbl { font-weight: 600; }
        .ticket-total-line .val { font-weight: 600; white-space: nowrap; }
        .ticket-grand {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 2px solid #0f172a;
            font-size: 13px;
            font-weight: 800;
        }
        .ticket-words {
            margin: 6px 0 0;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.35;
            text-align: center;
        }
        .ticket-extra {
            margin-top: 5px;
            font-size: 9px;
            line-height: 1.35;
        }
        .ticket-extra p { margin: 2px 0; }
        .ticket-extra strong { font-weight: 700; }
        .ticket-credit {
            margin-top: 6px;
            padding: 5px 6px;
            border: 1px dashed #94a3b8;
            font-size: 9px;
        }
        .ticket-credit h4 {
            margin: 0 0 4px;
            font-size: 10px;
            font-weight: 800;
            text-align: center;
        }
        .ticket-credit p { margin: 2px 0; }
        .ticket-credit table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 9px;
        }
        .ticket-credit th, .ticket-credit td {
            padding: 3px 2px;
            text-align: center;
            border-bottom: 1px dashed #cbd5e1;
        }
        .ticket-credit th {
            font-weight: 700;
            border-bottom: 1px solid #94a3b8;
        }
        .ticket-sunat {
            margin-top: 8px;
            padding: 5px 4px;
            border: 1px dashed #94a3b8;
            font-size: 8px;
            line-height: 1.35;
            text-align: center;
            color: #475569;
        }
        @media print {
            @page {
                size: 80mm 220mm;
                margin: 0;
            }
            body.format-ticket { margin: 0; }
        }
    </style>
</head>
<body class="{{ $isTicket ? 'format-ticket' : 'format-a4' }}">
    @if($isPreview ?? false)
        <div class="preview-banner">VISTA PREVIA — No tiene validez tributaria hasta confirmar y emitir el comprobante</div>
    @endif

    @if($isTicket)
        <div class="ticket-wrap">
            <div class="ticket-center">
                @if(!empty($logoDataUri) || !empty($logoFileUrl) || !empty($logoUrl))
                    <img src="{{ $logoDataUri ?: ($logoFileUrl ?: $logoUrl) }}" alt="Logo" class="ticket-logo">
                @endif
                <p class="ticket-company">{{ $branchName }}</p>
                @if($branchAddress !== '')
                    <p class="ticket-address">{{ $branchAddress }}</p>
                @endif
                <div class="ticket-doc-box">
                    <p class="ticket-doc-title">{{ $documentTitle }}</p>
                    <p class="ticket-doc-ruc">RUC {{ $branchRuc ?: '-' }}</p>
                    <p class="ticket-doc-code">{{ $documentCode }}</p>
                </div>
            </div>

            <hr class="ticket-sep">

            <div class="ticket-row">
                <span class="lbl">Fecha emisión:</span>
                <span class="val">{{ $issueDate }}</span>
            </div>
            @if($paymentLabel !== '')
                <div class="ticket-payment">{{ $paymentLabel }}</div>
            @endif

            <div class="ticket-row ticket-row-full">
                <span class="lbl">Cliente:</span>
                <span class="val">{{ $customerName }}</span>
            </div>
            <div class="ticket-row">
                <span class="lbl">{{ strlen(trim($customerDocument)) === 11 ? 'RUC' : 'Doc.' }}:</span>
                <span class="val">{{ $customerDocument !== '' ? $customerDocument : '-' }}</span>
            </div>
            @if($customerAddress !== '')
                <div class="ticket-row ticket-row-full">
                    <span class="lbl">Dirección:</span>
                    <span class="val">{{ $customerAddress }}</span>
                </div>
            @endif
            <div class="ticket-row">
                <span class="lbl">Moneda:</span>
                <span class="val">{{ $currencyLabel }}</span>
            </div>
            @if($isCredit ?? false)
                <div class="ticket-row">
                    <span class="lbl">Crédito:</span>
                    <span class="val">{{ (int) ($creditDays ?? 0) }} día(s)@if(!empty($creditDueDate)) — Vence {{ $creditDueDate }}@endif</span>
                </div>
            @endif

            <hr class="ticket-sep">

            <div class="ticket-items-head">
                <span>Cant.</span>
                <span>U.M.</span>
                <span>Descripción</span>
                <span>V.Unit</span>
                <span>ICBPER</span>
            </div>
            @forelse($lines as $line)
                <div class="ticket-item">
                    <div class="ticket-item-grid">
                        <span>{{ number_format((float) $line['qty'], 2) }}</span>
                        <span>{{ $line['unit'] }}</span>
                        <span class="desc-cell">{{ $line['description'] }}</span>
                        <span class="num">{{ number_format((float) $line['unit_value'], 2) }}</span>
                        <span class="num">{{ number_format((float) $line['icbper'], 2) }}</span>
                    </div>
                </div>
            @empty
                <p style="text-align:center;font-size:10px;margin:6px 0;">Sin líneas</p>
            @endforelse

            <hr class="ticket-sep">

            @php
                $t = $totals ?? [];
                $ticketTotalRows = [
                    ['label' => 'Sub Total Ventas', 'key' => 'subtotal_sales', 'always' => true],
                    ['label' => 'Anticipos', 'key' => 'advances'],
                    ['label' => 'Descuentos', 'key' => 'discounts'],
                    ['label' => 'Valor Venta', 'key' => 'sale_value', 'always' => true],
                    ['label' => 'ISC', 'key' => 'isc'],
                    ['label' => 'IGV', 'key' => 'igv', 'always' => true],
                    ['label' => 'ICBPER', 'key' => 'icbper'],
                ];
                if ($t['apply_detraccion'] ?? false) {
                    $ticketTotalRows[] = ['label' => 'Detracción ('.($t['detraccion_percent'] ?? 0).'%)', 'key' => 'detraccion'];
                }
                if ($t['apply_retencion'] ?? false) {
                    $ticketTotalRows[] = ['label' => 'Retención ('.($t['retencion_percent'] ?? 0).'%)', 'key' => 'retencion'];
                }
                $ticketTotalRows[] = ['label' => 'Otros Cargos', 'key' => 'other_charges'];
                $ticketTotalRows[] = ['label' => 'Redondeo', 'key' => 'rounding'];
            @endphp
            @foreach($ticketTotalRows as $row)
                @php $amount = (float) ($t[$row['key']] ?? 0); @endphp
                @if(($row['always'] ?? false) || abs($amount) >= 0.005)
                    <div class="ticket-total-line">
                        <span class="lbl">{{ $row['label'] }}</span>
                        <span class="val">S/ {{ number_format($amount, 2) }}</span>
                    </div>
                @endif
            @endforeach
            <div class="ticket-grand">
                <span>IMPORTE TOTAL</span>
                <span>S/ {{ number_format((float) ($t['total'] ?? 0), 2) }}</span>
            </div>

            <p class="ticket-words">{{ $totalInWords }}</p>

            <div class="ticket-extra">
                @if(($serviceOrderNumber ?? '-') !== '-')
                    <p><strong>O.S.:</strong> {{ $serviceOrderNumber }}</p>
                @endif
                @if(!empty($purchaseOrderNumber))
                    <p><strong>O.C.:</strong> {{ $purchaseOrderNumber }}</p>
                @endif
                @if(!empty($vehiclePlateLegend))
                    <p>{{ $vehiclePlateLegend }}</p>
                @endif
                @if(($observation ?? '-') !== '-')
                    <p><strong>Obs.:</strong> {{ $observation }}</p>
                @endif
                @if(($totals['apply_detraccion'] ?? false))
                    <p>Operación sujeta a detracción ({{ $totals['detraccion_percent'] ?? 0 }}%) — Tipo {{ $totals['detraccion_type'] ?? '020' }}</p>
                @endif
            </div>

            @if(($isCredit ?? false) && count($creditInstallments ?? []) > 0)
                <div class="ticket-credit">
                    <h4>INFORMACIÓN DEL CRÉDITO</h4>
                    <p>Monto neto pendiente: S/ {{ number_format((float) ($totals['total'] ?? 0), 2) }}</p>
                    <p>Cuotas: {{ count($creditInstallments) }}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Venc.</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($creditInstallments as $installment)
                                <tr>
                                    <td>{{ $installment['number'] ?? '001' }}</td>
                                    <td>{{ $installment['due_date'] ?? '-' }}</td>
                                    <td>{{ number_format((float) ($installment['amount'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($showSunatFooter ?? false)
                <div class="ticket-sunat">
                    Representación impresa de la factura electrónica. Puede verificarla en el sistema de la SUNAT con su clave SOL.
                </div>
            @endif
        </div>
    @else
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
    @endif
</body>
</html>
