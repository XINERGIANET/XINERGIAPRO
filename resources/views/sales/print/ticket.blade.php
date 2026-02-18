<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $sale->number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; color: #0f172a; }
        .ticket { width: 78mm; padding: 10px 8px; margin: 0 auto; }
        .center { text-align: center; }
        .logo { max-width: 56mm; max-height: 42mm; object-fit: contain; margin: 0 auto 6px; display: block; }
        .title { font-weight: 700; font-size: 17px; margin: 2px 0; }
        .small { font-size: 12px; }
        .line { border-top: 1px dashed #64748b; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; }
        .row b { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px; }
        th, td { padding: 3px 0; }
        th { text-align: left; border-bottom: 1px solid #94a3b8; }
        td.num, th.num { text-align: right; }
        .total { font-size: 21px; font-weight: 700; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'TICKET');
    $docCode = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)) . ($sale->salesMovement?->series ?? '001') . '-' . $sale->number;
@endphp

<div class="ticket">
    <div class="center">
        @if(!empty($logoFileUrl) || !empty($logoUrl))
            <img src="{{ $logoFileUrl ?: $logoUrl }}" alt="Logo sucursal" class="logo">
        @endif
        <div class="title">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</div>
        <div class="small">RUC: {{ $branchForLogo->ruc ?? '-' }}</div>
        <div class="small">{{ $docName }}</div>
        <div><b>{{ $docCode }}</b></div>
    </div>

    <div class="line"></div>

    <div class="row"><span>Fecha:</span><span>{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</span></div>
    <div class="row"><span>Cliente:</span><span style="text-align:right">{{ $sale->person_name ?? 'CLIENTES VARIOS' }}</span></div>
    <div class="row"><span>RUC/DNI:</span><span>{{ $sale->person?->document_number ?? '-' }}</span></div>
    <div class="row"><span>Pago:</span><span>{{ $paymentLabel }}</span></div>

    <div class="line"></div>

    <table>
        <thead>
        <tr>
            <th>Prod.</th>
            <th class="num">Cant</th>
            <th class="num">P.Unit</th>
            <th class="num">Subt.</th>
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
                <td>{{ \Illuminate\Support\Str::limit($detail->description ?? $detail->product?->description ?? '-', 24) }}</td>
                <td class="num">{{ number_format($qty, 2) }}</td>
                <td class="num">{{ number_format($unitPrice, 2) }}</td>
                <td class="num">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    <div class="row"><b>Op. gravada:</b><span>S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</span></div>
    <div class="row"><b>IGV:</b><span>S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</span></div>
    <div class="row total"><span>TOTAL:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</span></div>

    @if($sale->comment)
        <div class="line"></div>
        <div class="small"><b>Notas:</b> {{ $sale->comment }}</div>
    @endif

    <div class="line"></div>
    <div class="center small">
        Impreso: {{ $printedAt->format('d/m/Y H:i:s') }}<br>
        Gracias por su preferencia
    </div>
</div>

<script>
    window.addEventListener('load', function () { window.print(); });
</script>
</body>
</html>
