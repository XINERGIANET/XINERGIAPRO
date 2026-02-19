<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $sale->number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .logo { max-height: 84px; max-width: 220px; object-fit: contain; }
        .doc-box { border: 1px solid #334155; padding: 16px; min-width: 300px; text-align: center; }
        .doc-box h1 { margin: 0; font-size: 30px; }
        .doc-box p { margin: 4px 0; font-size: 18px; }
        .meta { margin-top: 18px; display: grid; grid-template-columns: 180px 1fr; row-gap: 4px; column-gap: 12px; }
        .meta b { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; font-size: 13px; }
        th { background: #0f172a; color: #fff; text-align: left; }
        .num { text-align: right; }
        .totals { margin-top: 18px; width: 360px; margin-left: auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .final { border-top: 2px solid #111827; margin-top: 6px; padding-top: 6px; font-weight: 700; font-size: 20px; }
        .notes { margin-top: 20px; }
        .notes p { margin: 6px 0 0; }
        @media print {
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'COMPROBANTE');
    $docCode = strtoupper(substr($sale->documentType?->name ?? 'X', 0, 1)) . ($sale->salesMovement?->series ?? '001') . '-' . $sale->number;
@endphp

<div class="head">
    <div>
        @if(!empty($logoFileUrl) || !empty($logoUrl))
            <img src="{{ $logoFileUrl ?: $logoUrl }}" alt="Logo sucursal" class="logo">
        @endif
        <h3 style="margin:12px 0 2px;">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</h3>
        <p style="margin:0;">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
    </div>
    <div class="doc-box">
        <h1>{{ $docName }}</h1>
        <p>{{ $docCode }}</p>
    </div>
</div>

<div class="meta">
    <b>Fecha de emision:</b><span>{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</span>
    <b>Cliente:</b><span>{{ $sale->person_name ?? 'CLIENTES VARIOS' }}</span>
    <b>RUC/DNI:</b><span>{{ $sale->person?->document_number ?? '-' }}</span>
    <b>Direccion:</b><span>{{ $sale->person?->address ?? '-' }}</span>
    <b>Moneda:</b><span>{{ $sale->salesMovement?->currency ?? 'PEN' }}</span>
    <b>Forma de pago:</b><span>{{ $paymentLabel }}</span>
</div>

<table>
    <thead>
    <tr>
        <th style="width:50px;">Item</th>
        <th>Descripcion</th>
        <th style="width:60px;">U.M.</th>
        <th style="width:80px;" class="num">Cantidad</th>
        <th style="width:90px;" class="num">P.Unit</th>
        <th style="width:100px;" class="num">Subtotal</th>
    </tr>
    </thead>
    <tbody>
    @forelse($details as $i => $detail)
        @php
            $qty = (float) $detail->quantity;
            $lineTotal = (float) $detail->amount;
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $detail->description ?? $detail->product?->description ?? '-' }}</td>
            <td>{{ $detail->unit?->code ?? $detail->unit?->description ?? '-' }}</td>
            <td class="num">{{ number_format($qty, 2) }}</td>
            <td class="num">S/ {{ number_format($unitPrice, 2) }}</td>
            <td class="num">S/ {{ number_format($lineTotal, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Sin detalle</td></tr>
    @endforelse
    </tbody>
</table>

<div class="totals">
    <div><span>Op. gravada:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</span></div>
    <div><span>I.G.V.:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</span></div>
    <div class="final"><span>Importe total:</span><span>S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</span></div>
</div>

<div class="notes">
    <b>Observacion:</b>
    <p>{{ $sale->comment ?: '-' }}</p>
    <p style="margin-top:14px; color:#475569;">Impreso el {{ $printedAt->format('d/m/Y H:i:s') }}</p>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
