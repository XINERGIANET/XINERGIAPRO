<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>OC - OS {{ $order->movement?->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 22px 26px;
            font-family: "Courier New", Courier, monospace;
            color: #2d3748;
            font-size: 13px;
            line-height: 1.3;
        }
        .header { margin-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; }
        .doc-title { text-align: right; font-size: 20px; font-weight: bold; margin-top: -30px; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 4px; vertical-align: top; }
        .label { font-weight: bold; width: 120px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { border: 1px solid #718096; padding: 8px; text-align: left; }
        .items-table th { background: #f7fafc; font-size: 12px; text-transform: uppercase; }
        .num { text-align: right !important; }
        
        .footer { margin-top: 50px; }
        .signature-line { border-top: 1px solid #000; width: 200px; margin: 0 auto; text-align: center; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $order->branch?->name ?? 'XINERGIA PRO' }}</div>
        <div class="doc-title">ORDEN DE COMPRA</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Fecha:</td>
            <td>{{ now()->format('d/m/Y') }}</td>
            <td class="label">Referencia OS:</td>
            <td>{{ $order->movement?->number ?? ('#' . $order->id) }}</td>
        </tr>
        <tr>
            <td class="label">Vehículo:</td>
            <td>{{ ($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '') }}</td>
            <td class="label">Placa:</td>
            <td>{{ $order->vehicle?->plate ?? '' }}</td>
        </tr>
    </table>

    <div style="margin-bottom: 10px; font-weight: bold;">Solicitud de Repuestos para Proveedor:</div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Código/SKU</th>
                <th>Descripción del Repuesto</th>
                <th class="num">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @php
                $parts = $order->details->where('line_type', 'PART');
            @endphp
            @forelse($parts as $part)
                <tr>
                    <td>{{ $part->product?->code ?? '-' }}</td>
                    <td>{{ $part->description }}</td>
                    <td class="num">{{ number_format((float) $part->qty, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center;">No hay repuestos registrados en esta orden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <p><strong>Observaciones:</strong></p>
        <div style="border: 1px solid #718096; padding: 10px; min-height: 60px;">
            {{ $order->corrective_observations ?? 'N/A' }}
        </div>
    </div>

    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td style="text-align: center;">
                    <div style="height: 80px;"></div>
                    <div class="signature-line">Autorizado por</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
