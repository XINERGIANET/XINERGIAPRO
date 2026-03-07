@extends('layouts.app')

@section('content')
    <div class="{{ !empty($isModal) ? 'p-4 sm:p-5' : '' }}">
        @unless (!empty($isModal))
            <x-common.page-breadcrumb pageTitle="Historial de Cliente" />
        @endunless

        <x-common.component-card title="{{ trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: 'Cliente' }}" desc="Historial completo del cliente en taller.">
            <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Vehículos</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ $vehicles->count() }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Citas</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ $appointments->count() }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">OS / Deuda</p>
                    <p class="mt-2 text-lg font-black text-slate-900">S/ {{ number_format((float) $totalOrders, 2) }}</p>
                    <p class="mt-1 text-sm font-semibold text-orange-600">Deuda: S/ {{ number_format((float) $debtOrders, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Ventas / Pagos</p>
                    <p class="mt-2 text-lg font-black text-slate-900">S/ {{ number_format((float) $totalSales, 2) }}</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-600">Pagos: S/ {{ number_format((float) $totalPayments, 2) }}</p>
                </div>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Vehículos</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($vehicles as $vehicle)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <p class="font-semibold text-slate-900">{{ trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: 'Vehículo sin nombre' }}</p>
                                <p class="text-xs text-slate-500">{{ $vehicle->plate ?: 'Sin placa' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Sin vehículos registrados.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Citas</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($appointments as $appointment)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <p class="font-semibold text-slate-900">{{ optional($appointment->start_at)->format('d/m/Y H:i') ?: '--' }}</p>
                                <p class="text-xs text-slate-500">{{ $appointment->reason ?: 'Sin motivo' }} · {{ $appointment->status ?: 'Sin estado' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Sin citas registradas.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Compras</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($purchases as $purchase)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <p class="font-semibold text-slate-900">{{ $purchase->movement?->number ?: 'Sin comprobante' }}</p>
                                <p class="text-xs text-slate-500">{{ optional($purchase->movement?->moved_at)->format('d/m/Y H:i') ?: '--' }} · S/ {{ number_format((float) $purchase->total, 2) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Sin compras registradas.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Mantenimientos realizados</h3>
                        <p class="mt-1 text-sm text-slate-500">Incluye fecha del servicio, técnico responsable, observaciones y vehículo.</p>
                    </div>
                    <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-600">{{ $orders->count() }} registros</span>
                </div>

                <div class="table-responsive overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">OS</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Vehículo</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Mantenimiento / servicio</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Técnico responsable</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Observaciones</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($orders as $order)
                                @php
                                    $serviceNames = $order->details
                                        ->whereIn('line_type', ['SERVICE', 'SERVCE'])
                                        ->map(fn ($detail) => $detail->service?->name ?: $detail->description)
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    $technicians = $order->technicians
                                        ->map(fn ($record) => trim(($record->technician->first_name ?? '') . ' ' . ($record->technician->last_name ?? '')))
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    if ($technicians->isEmpty()) {
                                        $technicians = $order->details
                                            ->map(fn ($detail) => trim(($detail->technician->first_name ?? '') . ' ' . ($detail->technician->last_name ?? '')))
                                            ->filter()
                                            ->unique()
                                            ->values();
                                    }

                                    $vehicleLabel = trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? ''));
                                    if (($order->vehicle?->plate ?? '') !== '') {
                                        $vehicleLabel .= ' - ' . $order->vehicle->plate;
                                    }
                                @endphp

                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-bold text-slate-900">{{ $order->movement?->number ?: 'Sin OS' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ optional($order->intake_date)->format('d/m/Y H:i') ?: '--' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $vehicleLabel !== '' ? $vehicleLabel : 'Sin vehículo' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $serviceNames->isNotEmpty() ? $serviceNames->implode(', ') : 'Sin detalle' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $technicians->isNotEmpty() ? $technicians->implode(', ') : 'No asignado' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $order->observations ?: ($order->diagnosis_text ?: 'Sin observaciones') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ mb_strtoupper(str_replace('_', ' ', (string) $order->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Sin historial de mantenimientos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Ventas</h3>
                    <div class="mt-3 table-responsive overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Comprobante</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.14em]">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($sales as $sale)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-600">{{ optional($sale->movement?->moved_at)->format('d/m/Y H:i') ?: '--' }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $sale->movement?->number ?: 'Sin comprobante' }}</td>
                                        <td class="px-4 py-3 text-right font-black text-orange-600">S/ {{ number_format((float) $sale->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">Sin ventas registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-700">Pagos</h3>
                    <div class="mt-3 table-responsive overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.14em]">Comprobante</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.14em]">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-600">{{ optional($payment->movement?->moved_at)->format('d/m/Y H:i') ?: '--' }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $payment->movement?->number ?: 'Sin comprobante' }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-600">S/ {{ number_format((float) $payment->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">Sin pagos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection
