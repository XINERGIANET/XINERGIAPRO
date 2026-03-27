<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $sale->salesDocumentCode() }}</title>
    <style>
        * {
            font-family: Verdana, sans-serif;
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #fff;
            color: #0f172a;
        }

        .ticket-wrapper {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 1.4mm 1.2mm 1.6mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        tr {
            page-break-inside: avoid;
        }

        thead tr td,
        thead tr th {
            font-weight: 700;
            font-size: 14px;
            padding: 4px 2px 6px;
            border-bottom: 1px dashed #9fb2cc;
        }

        tbody tr td {
            padding: 6px 2px;
            font-size: 14px;
            vertical-align: top;
        }

        tfoot tr td {
            font-weight: bold;
        }

        .bold { font-weight: bold; }
        .without-top { margin-top: 0; }
        .without-bottom { margin-bottom: 10px; font-size: 13.2px; margin-top: 15px; }
        .without-tb, .without-bt { margin-top: 0; margin-bottom: 0; }
        .xx-small { font-size: xx-small; }
        .x-small { font-size: x-small; }
        .small { font-size: small; }
        .medium { font-size: medium; }
        .large { font-size: large; }
        .table-bordered { border: 1px solid black; }
        .table-full-bordered tr td { border: 1px solid black; }
        .gray { background-color: lightgray; }
        .teal { background-color: #000000; }
        .bg-primary { background-color: #000000; }
        .primary-text { color: #000000; }
        .white-text { color: white; }
        .page-break { page-break-after: always; }

        header {
            position: fixed;
            top: -50px;
            left: 0px;
            right: 0px;
            height: 50px;
            text-align: center;
            line-height: 35px;
        }

        header.left { text-align: left !important; }
        header.right { text-align: right !important; }

        footer {
            position: fixed;
            bottom: -50px;
            left: 0px;
            right: 0px;
            height: 50px;
            text-align: center;
            line-height: 35px;
        }

        footer.left { text-align: left !important; }
        footer.right { text-align: right !important; }

        .table-soft-bordered {
            border: 1px solid rgba(0, 0, 0, .48);
        }

        .table-full-soft-bordered thead tr td,
        .table-full-soft-bordered thead tr th {
            padding-left: 12px;
            padding-right: 12px;
            border-left: 1px solid rgba(255, 255, 255, .24);
            border-right: 1px solid rgba(255, 255, 255, .24);
            border-bottom: 1px solid rgba(255, 255, 255, .24);
        }

        .table-full-soft-bordered tr td {
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .table-full-soft-bordered tbody tr td {
            border-bottom: 1px solid rgba(0, 0, 0, .24);
        }

        .center { text-align: center; }
        .separator { border-top: 1px dashed #8aa0bc; margin: 8px 0; }
        .meta-row { display: grid; grid-template-columns: 22mm 1fr; gap: 1mm; align-items: start; margin-bottom: 2px; }
        .meta-label { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .meta-value { font-size: 14px; line-height: 1.25; word-break: break-word; }
        .totals-row { display: flex; justify-content: space-between; margin: 3px 0; font-size: 16px; }
        .grand-total {
            border-top: 1px solid #8aa0bc;
            margin-top: 6px;
            padding-top: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 22px;
            font-weight: bold;
        }
        .grand-total .label { letter-spacing: .2px; }
        .grand-total .value { white-space: nowrap; }
        .logo {
            display: block;
            max-width: 64mm;
            max-height: 26mm;
            margin: 0 auto 8px;
            object-fit: contain;
        }
        .prod-col {
            width: 45%;
            font-size: 14px;
            line-height: 1.2;
            padding-left: 0 !important;
            word-break: break-word;
        }
        .num-col {
            text-align: right;
            font-size: 14px;
            white-space: nowrap;
            padding-right: 0 !important;
        }
        .notes-wrap {
            font-size: 14px;
            line-height: 1.3;
            word-break: break-word;
        }
        .footer {
            font-size: 13px;
            line-height: 1.35;
            margin-top: 6px;
        }

        @media print {
            @page { size: 80mm 220mm; margin: 0; }
            html, body { width: 100%; margin: 0; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'TICKET DE VENTA');
    $docCode = $sale->salesDocumentCode();
@endphp

<div class="ticket-wrapper">
    <div class="center">
        @if(!empty($logoFileUrl) || !empty($logoDataUri) || !empty($logoUrl))
            <img src="{{ $logoFileUrl ?: ($logoDataUri ?: $logoUrl) }}" alt="Logo sucursal" class="logo">
        @endif
        <p class="without-tb bold large" style="font-size: 28px; line-height: 1.1;">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</p>
        <p class="without-tb medium" style="font-size: 18px; line-height: 1.2;">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
        <p class="without-tb medium" style="font-size: 18px; line-height: 1.2;">{{ $docName }}</p>
        <p class="without-tb bold large" style="font-size: 24px; line-height: 1.15;">{{ $docCode }}</p>
    </div>

    <div class="separator"></div>

    <div class="meta-row"><div class="meta-label">Fecha:</div><div class="meta-value">{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</div></div>
    <div class="meta-row"><div class="meta-label">Cliente:</div><div class="meta-value">{{ $sale->person_name ?? 'CLIENTES VARIOS' }}</div></div>
    <div class="meta-row"><div class="meta-label">Dir.:</div><div class="meta-value">{{ $sale->person?->address ?? '-' }}</div></div>
    <div class="meta-row"><div class="meta-label">RUC/DNI:</div><div class="meta-value">{{ $sale->person?->document_number ?? '-' }}</div></div>
    <div class="meta-row"><div class="meta-label">Forma pago:</div><div class="meta-value">{{ $paymentLabel }}</div></div>

    <div class="separator"></div>

    <table>
        <thead>
        <tr>
            <th>Prod.</th>
            <th style="text-align:right;">Cant</th>
            <th style="text-align:right;">P.Unit.</th>
            <th style="text-align:right;">Subt.</th>
        </tr>
        </thead>
        <tbody>
        @foreach($details as $detail)
            @php
                $qty = (float) $detail->quantity;
                $lineTotal = (float) $detail->amount;
                $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
            @endphp
            <tr>
                <td class="prod-col">{{ \Illuminate\Support\Str::limit($detail->description ?? $detail->product?->description ?? '-', 30) }}</td>
                <td class="num-col">{{ number_format($qty, 2) }}</td>
                <td class="num-col">{{ number_format($unitPrice, 2) }}</td>
                <td class="num-col">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="separator"></div>

    <div class="totals-row"><span class="bold">Op. gravada:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</span></div>
    <div class="totals-row"><span class="bold">IGV:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</span></div>
    <div class="grand-total"><span class="label">TOTAL:</span><span class="value">S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</span></div>

    @if($sale->comment)
        <div class="separator"></div>
        <div class="notes-wrap"><span class="bold">Notas:</span> {{ $sale->comment }}</div>
    @endif

    <div class="separator"></div>
    <div class="center footer">
        Impreso: {{ $printedAt->format('d/m/Y H:i:s') }}<br>
        Gracias por su preferencia
    </div>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
