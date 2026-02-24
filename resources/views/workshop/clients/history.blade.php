@extends('layouts.app')

@section('content')
<div>
    <x-common.page-breadcrumb pageTitle="Historial de Cliente" />

    <x-common.component-card title="{{ $person->first_name }} {{ $person->last_name }}" desc="Detalle integral del cliente en taller.">
        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm"><strong>Vehiculos:</strong> {{ $vehicles->count() }}</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm"><strong>Citas:</strong> {{ $appointments->count() }}</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm"><strong>OS total/deuda:</strong> {{ number_format($totalOrders, 2) }} / {{ number_format($debtOrders, 2) }}</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm"><strong>Ventas/Pagos:</strong> {{ number_format($totalSales, 2) }} / {{ number_format($totalPayments, 2) }}</div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Vehiculos</h3>
                @forelse($vehicles as $vehicle)
                    <div class="border-b border-gray-100 py-1 text-sm">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate ?: 'S/PLACA' }}</div>
                @empty
                    <p class="text-sm text-gray-500">Sin vehiculos.</p>
                @endforelse
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Citas</h3>
                @forelse($appointments as $appointment)
                    <div class="border-b border-gray-100 py-1 text-sm">{{ optional($appointment->start_at)->format('Y-m-d H:i') }} - {{ $appointment->status }} - {{ $appointment->reason }}</div>
                @empty
                    <p class="text-sm text-gray-500">Sin citas.</p>
                @endforelse
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Ordenes de Servicio</h3>
            <div class="overflow-x-auto overflow-y-hidden rounded border border-gray-200">
                <table class="w-full min-w-[900px] text-sm">
                    <thead style="background-color:#1e293b" class="text-white">
                        <tr>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider first:rounded-tl">OS</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Fecha</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Vehiculo</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Estado</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Pagado</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider last:rounded-tr">Deuda</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr class="border-t border-gray-100">
                                <td class="p-2">{{ $order->movement?->number }}</td>
                                <td class="p-2">{{ optional($order->intake_date)->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $order->vehicle?->plate }}</td>
                                <td class="p-2">{{ $order->status }}</td>
                                <td class="p-2">{{ number_format((float)$order->total,2) }}</td>
                                <td class="p-2">{{ number_format((float)$order->paid_total,2) }}</td>
                                <td class="p-2">{{ number_format((float)$order->debt,2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-2 text-gray-500">Sin ordenes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Ventas</h3>
            <div class="overflow-x-auto overflow-y-hidden rounded border border-gray-200">
                <table class="w-full min-w-[700px] text-sm">
                    <thead style="background-color:#1e293b" class="text-white">
                        <tr>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider first:rounded-tl">Fecha</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Doc</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider last:rounded-tr">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            <tr class="border-t border-gray-100">
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

        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Compras</h3>
            <div class="overflow-x-auto overflow-y-hidden rounded border border-gray-200">
                <table class="w-full min-w-[700px] text-sm">
                    <thead style="background-color:#1e293b" class="text-white">
                        <tr>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider first:rounded-tl">Fecha</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Doc</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider last:rounded-tr">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr class="border-t border-gray-100">
                                <td class="p-2">{{ optional($purchase->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $purchase->movement?->number }}</td>
                                <td class="p-2">{{ number_format((float)$purchase->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-2 text-gray-500">Sin compras.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Pagos</h3>
            <div class="overflow-x-auto overflow-y-hidden rounded border border-gray-200">
                <table class="w-full min-w-[700px] text-sm">
                    <thead style="background-color:#1e293b" class="text-white">
                        <tr>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider first:rounded-tl">Fecha</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider">Comprobante</th>
                            <th class="p-2 text-left text-xs font-semibold uppercase tracking-wider last:rounded-tr">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr class="border-t border-gray-100">
                                <td class="p-2">{{ optional($payment->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $payment->movement?->number }}</td>
                                <td class="p-2">{{ number_format((float)$payment->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-2 text-gray-500">Sin pagos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-common.component-card>
</div>
@endsection
