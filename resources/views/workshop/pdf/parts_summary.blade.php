<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Repuestos Usados</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <h2>Resumen de Repuestos Usados</h2>
    <p><strong>OS:</strong> {{ $order->movement?->number }}</p>
    <p><strong>Cliente:</strong> {{ $order->client?->first_name }} {{ $order->client?->last_name }}</p>
    <p><strong>Vehículo:</strong> {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Estado</th>
                <th>Fecha consumo</th>
                <th>Mov. Almacén</th>
            </tr>
        </thead>
        <tbody>
            @php($lines = $order->details->where('line_type', 'PART'))
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line->product?->code }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format((float)$line->qty, 6) }}</td>
                    <td>{{ $line->stock_status }}</td>
                    <td>{{ optional($line->consumed_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $line->warehouseMovement?->movement?->number }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin repuestos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

