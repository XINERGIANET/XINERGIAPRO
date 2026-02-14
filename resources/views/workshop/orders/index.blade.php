@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Ordenes Taller" />

    <x-common.component-card title="Ordenes de servicio" desc="Consulta y gestiona ordenes del taller.">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.create') }}" style="background-color:#00A389;color:#fff">
                <i class="ri-add-line"></i><span>Nueva OS</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.index') }}" style="background-color:#1d4ed8;color:#fff">
                <i class="ri-bar-chart-2-line"></i><span>Reportes</span>
            </x-ui.link-button>
        </div>

        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <form method="GET" class="flex flex-1 gap-2">
                <input name="search" value="{{ $search }}" class="h-11 flex-1 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Buscar OS / placa / cliente">
                <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Buscar</button>
                <a href="{{ route('workshop.orders.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center">Limpiar</a>
            </form>
            <a href="{{ route('workshop.orders.create') }}" class="h-11 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white inline-flex items-center">Nueva OS</a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        <div class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">OS</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Ingreso</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Vehiculo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Pagado</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $order->movement?->number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->intake_date?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->status }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $order->paid_total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('workshop.orders.show', $order) }}" class="rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-medium text-white">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500">Sin ordenes para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </x-common.component-card>
</div>
@endsection
