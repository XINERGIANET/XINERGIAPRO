@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Historial Cliente: {{ $person->first_name }} {{ $person->last_name }}</h1>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="rounded border p-3"><strong>Vehículos:</strong> {{ $vehicles->count() }}</div>
        <div class="rounded border p-3"><strong>OS:</strong> {{ $orders->count() }}</div>
        <div class="rounded border p-3"><strong>Total OS:</strong> {{ number_format($totalOrders, 2) }}</div>
        <div class="rounded border p-3"><strong>Deuda:</strong> {{ number_format($debtOrders, 2) }}</div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded border p-4">
            <h2 class="mb-2 font-semibold">Vehículos</h2>
            @forelse($vehicles as $vehicle)
                <div class="border-b py-1 text-sm">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate ?: 'S/PLACA' }}</div>
            @empty
                <p class="text-sm text-gray-500">Sin vehículos.</p>
            @endforelse
        </div>
        <div class="rounded border p-4">
            <h2 class="mb-2 font-semibold">Citas</h2>
            @forelse($appointments as $appointment)
                <div class="border-b py-1 text-sm">{{ optional($appointment->start_at)->format('Y-m-d H:i') }} - {{ $appointment->status }} - {{ $appointment->reason }}</div>
            @empty
                <p class="text-sm text-gray-500">Sin citas.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Órdenes de Servicio</h2>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">OS</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Vehículo</th>
                        <th class="p-2 text-left">Estado</th>
                        <th class="p-2 text-left">Total</th>
                        <th class="p-2 text-left">Pagado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-t">
                            <td class="p-2">{{ $order->movement?->number }}</td>
                            <td class="p-2">{{ optional($order->intake_date)->format('Y-m-d H:i') }}</td>
                            <td class="p-2">{{ $order->vehicle?->plate }}</td>
                            <td class="p-2">{{ $order->status }}</td>
                            <td class="p-2">{{ number_format((float)$order->total,2) }}</td>
                            <td class="p-2">{{ number_format((float)$order->paid_total,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-2 text-gray-500">Sin órdenes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Ventas</h2>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Doc</th>
                        <th class="p-2 text-left">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="border-t">
                            <td class="p-2">{{ optional($sale->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                            <td class="p-2">{{ $sale->movement?->number }}</td>
                            <td class="p-2">{{ number_format((float)$sale->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-2 text-gray-500">Sin ventas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

