@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Taller - Ordenes de servicio</h1>
        <a href="{{ route('workshop.orders.create') }}" class="rounded bg-emerald-600 px-3 py-2 text-white">Nueva OS</a>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" class="rounded border px-3 py-2" placeholder="Buscar OS / placa / cliente">
        <button class="rounded bg-blue-600 px-3 py-2 text-white">Buscar</button>
    </form>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">OS</th>
                    <th class="p-2 text-left">Fecha ingreso</th>
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-left">Vehiculo</th>
                    <th class="p-2 text-left">Estado</th>
                    <th class="p-2 text-left">Total</th>
                    <th class="p-2 text-left">Pagado</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr class="border-t">
                        <td class="p-2">{{ $order->movement?->number }}</td>
                        <td class="p-2">{{ $order->intake_date?->format('Y-m-d H:i') }}</td>
                        <td class="p-2">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</td>
                        <td class="p-2">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</td>
                        <td class="p-2">{{ $order->status }}</td>
                        <td class="p-2">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="p-2">{{ number_format((float) $order->paid_total, 2) }}</td>
                        <td class="p-2">
                            <a href="{{ route('workshop.orders.show', $order) }}" class="rounded bg-indigo-600 px-2 py-1 text-white">Abrir</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $orders->links() }}
</div>
@endsection

