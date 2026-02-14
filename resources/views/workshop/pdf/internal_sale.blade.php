<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante Interno Venta</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .head { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f1f5f9; }
        .totals { margin-top: 12px; width: 320px; margin-left: auto; }
    </style>
</head>
<body>
    <div class="head">
        <h2>Comprobante Interno de Venta</h2>
        <p><strong>OS:</strong> {{ $order->movement?->number }}</p>
        <p><strong>Venta:</strong> {{ $order->sale?->movement?->number }}</p>
        <p><strong>Cliente:</strong> {{ $order->client?->first_name }} {{ $order->client?->last_name }}</p>
        <p><strong>Vehículo:</strong> {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->sale->details as $detail)
                <tr>
                    <td>{{ $detail->code }}</td>
                    <td>{{ $detail->description }}</td>
                    <td>{{ $detail->unit?->description }}</td>
                    <td>{{ number_format((float)$detail->quantity, 6) }}</td>
                    <td>{{ number_format((float)$detail->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>Subtotal</th><td>{{ number_format((float)$order->sale->subtotal, 2) }}</td></tr>
        <tr><th>IGV</th><td>{{ number_format((float)$order->sale->tax, 2) }}</td></tr>
        <tr><th>Total</th><td><strong>{{ number_format((float)$order->sale->total, 2) }}</strong></td></tr>
    </table>
</body>
</html>

