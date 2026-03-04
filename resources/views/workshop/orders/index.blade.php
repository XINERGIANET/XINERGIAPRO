@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Ordenes Taller" />

    <x-common.component-card title="Ordenes de servicio" desc="Consulta y gestiona ordenes del taller.">
        {{-- Barra de Herramientas Premium (Estilo solicitado) --}}
        <form method="GET" action="{{ route('workshop.orders.index') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
            {{-- Selector de Registros --}}
            <div class="flex items-center gap-2">
                <select name="per_page" class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="10" @selected(($perPage ?? 10) == 10)>10 / pág</option>
                    <option value="25" @selected(($perPage ?? 10) == 25)>25 / pág</option>
                    <option value="50" @selected(($perPage ?? 10) == 50)>50 / pág</option>
                    <option value="100" @selected(($perPage ?? 10) == 100)>100 / pág</option>
                </select>
            </div>

            {{-- Buscador Principal --}}
            <div class="relative flex-1 min-w-[300px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-lg text-gray-400"></i>
                </div>
                <input 
                    name="search" 
                    value="{{ $search }}" 
                    class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-blue-500 focus:ring-blue-500 placeholder:text-gray-400" 
                    placeholder="Buscar OS, placa o cliente..."
                >
            </div>

            {{-- Acciones del Formulario --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#244BB3] px-6 text-sm font-bold text-white shadow-lg shadow-blue-100 transition-all hover:brightness-110 active:scale-95">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </button>
                <a href="{{ route('workshop.orders.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    Limpiar
                </a>
            </div>

            {{-- Botón Nuevo (Al final a la derecha) --}}
            <div class="ml-auto flex gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.index') }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#1d4ed8;color:#fff">
                    <i class="ri-bar-chart-2-line"></i><span>Reportes</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.create') }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#00A389;color:#fff">
                    <i class="ri-add-line"></i><span>Nueva OS</span>
                </x-ui.link-button>
            </div>
        </form>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        <div class="overflow-x-auto overflow-y-hidden mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">OS</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Ingreso</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Vehiculo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Pagado</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm text-center font-medium">{{ $order->movement?->number }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $order->intake_date?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $order->client?->full_name }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} ({{ $order->vehicle?->plate }})</td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="px-2 py-1 rounded-lg text-xs font-bold uppercase {{ match($order->status) {
                                    'open' => 'bg-blue-100 text-blue-700',
                                    'finished' => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-700'
                                } }}">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center font-bold">{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float) $order->paid_total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="relative group">
                                        <x-ui.link-button
                                            size="icon"
                                            variant="primary"
                                            href="{{ route('workshop.orders.show', $order) }}"
                                            className="rounded-xl"
                                            style="background-color: #4F46E5; color: #FFFFFF;"
                                            aria-label="Ver"
                                        >
                                            <i class="ri-eye-line"></i>
                                        </x-ui.link-button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                            Ver
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <div class="relative group">
                                        <x-ui.link-button
                                            size="icon"
                                            variant="edit"
                                            href="{{ route('workshop.orders.show', $order) }}"
                                            className="rounded-xl"
                                            style="background-color: #FBBF24; color: #111827;"
                                            aria-label="Editar"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </x-ui.link-button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                            Editar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('workshop.orders.destroy', $order) }}"
                                        class="relative group js-swal-delete"
                                        data-swal-title="Eliminar/anular OS?"
                                        data-swal-text="Se eliminara esta orden de servicio. Esta accion no se puede deshacer."
                                        data-swal-confirm="Si, eliminar"
                                        data-swal-cancel="Cancelar"
                                        data-swal-confirm-color="#ef4444"
                                        data-swal-cancel-color="#6b7280"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            size="icon"
                                            variant="eliminate"
                                            type="submit"
                                            className="rounded-xl"
                                            style="background-color: #EF4444; color: #FFFFFF;"
                                            aria-label="Eliminar"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                            Eliminar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500 text-center">Sin ordenes para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $orders->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>
</div>
@endsection
