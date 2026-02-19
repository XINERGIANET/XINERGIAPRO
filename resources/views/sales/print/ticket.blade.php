<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $sale->number }}</title>
    <style>
        * {
            font-family: Verdana, sans-serif;
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 80mm;
            background: #fff;
        }

        .ticket-wrapper {
            width: 76mm;
            margin: 0 auto;
            padding: 3mm 2mm 2mm;
        }

        table {
            width: 100%;
            font-size: small;
            border-collapse: collapse;
        }

        tr {
            page-break-inside: avoid;
        }

        thead tr td,
        thead tr th {
            font-weight: bold;
            font-size: 13px;
        }

        tbody tr td {
            padding-left: 5px;
            padding-right: 5px;
            font-size: 13px;
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
        .separator { border-top: 1px dashed #8aa0bc; margin: 6px 0; }
        .meta-row { display: grid; grid-template-columns: 18mm 1fr; gap: 1.5mm; align-items: start; margin-bottom: 1px; }
        .meta-label { font-weight: bold; font-size: 11px; line-height: 1.2; }
        .meta-value { font-size: 11px; line-height: 1.2; word-break: break-word; }
        .totals-row { display: flex; justify-content: space-between; margin: 1px 0; font-size: 13px; }
        .grand-total {
            border-top: 1px solid #8aa0bc;
            margin-top: 2px;
            padding-top: 2px;
            display: flex;
            justify-content: space-between;
            font-size: 17px;
            font-weight: bold;
        }
        .grand-total .label { letter-spacing: .2px; }
        .grand-total .value { white-space: nowrap; }
        .logo {
            display: block;
            max-width: 48mm;
            max-height: 20mm;
            margin: 0 auto 6px;
            object-fit: contain;
        }
        .prod-col {
            font-size: 11px;
            line-height: 1.15;
            padding-left: 0 !important;
        }
        .num-col {
            text-align: right;
            font-size: 11px;
            white-space: nowrap;
            padding-right: 0 !important;
        }
        .notes-wrap {
            font-size: 11px;
            line-height: 1.2;
            word-break: break-word;
        }
        .footer {
            font-size: 11px;
            line-height: 1.25;
            margin-top: 3px;
        }

        @media print {
            @page { size: 80mm 220mm; margin: 0; }
            html, body { width: 80mm; margin: 0; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'TICKET DE VENTA');
    $docCode = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)) . ($sale->salesMovement?->series ?? '001') . '-' . $sale->number;
@endphp

<div class="ticket-wrapper">
    <div class="center">
        @if(!empty($logoFileUrl) || !empty($logoUrl))
            <img src="{{ $logoFileUrl ?: $logoUrl }}" alt="Logo sucursal" class="logo">
        @endif
        <p class="without-tb bold large" style="font-size: 18px;">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</p>
        <p class="without-tb medium" style="font-size: 12px;">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
        <p class="without-tb medium" style="font-size: 12px;">{{ $docName }}</p>
        <p class="without-tb bold large" style="font-size: 15px;">{{ $docCode }}</p>
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
