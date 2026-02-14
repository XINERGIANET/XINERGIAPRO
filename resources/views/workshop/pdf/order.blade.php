<!doctype html>
<html>
<head><meta charset="utf-8"><title>OS {{ $order->movement?->number }}</title></head>
<body>
<h2>Orden de Servicio {{ $order->movement?->number }}</h2>
<p>Cliente: {{ $order->client?->first_name }} {{ $order->client?->last_name }}</p>
<p>Vehiculo: {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</p>
<p>Ingreso: {{ $order->intake_date }}</p>
<h3>Inventario recibido</h3>
<ul>@foreach($order->intakeInventory as $i)<li>{{ $i->item_key }}: {{ $i->present ? 'SI' : 'NO' }}</li>@endforeach</ul>
<h3>Daños preexistentes</h3>
<ul>@foreach($order->damages as $d)<li>{{ $d->side }} - {{ $d->description }} ({{ $d->severity }})</li>@endforeach</ul>
<h3>Trabajos / Detalle</h3>
<table border="1" cellspacing="0" cellpadding="4"><tr><th>Tipo</th><th>Descripcion</th><th>Cant</th><th>Total</th></tr>@foreach($order->details as $line)<tr><td>{{ $line->line_type }}</td><td>{{ $line->description }}</td><td>{{ $line->qty }}</td><td>{{ number_format((float)$line->total,2) }}</td></tr>@endforeach</table>
<p>Subtotal: {{ number_format((float)$order->subtotal,2) }} | Impuesto: {{ number_format((float)$order->tax,2) }} | Total: {{ number_format((float)$order->total,2) }}</p>
</body>
</html>

