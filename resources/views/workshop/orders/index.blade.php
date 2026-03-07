@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Ordenes Taller" />

    <x-common.component-card title="Ordenes de servicio" desc="Consulta y gestiona ordenes del taller.">
        {{-- Barra de Herramientas Premium --}}
        <form method="GET" action="{{ route('workshop.orders.index') }}" class="mb-5 flex flex-wrap items-center gap-3">
            @if (request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif

            {{-- Selector de Registros --}}
            <div class="w-32 flex-none">
                <select name="per_page" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all" onchange="this.form.submit()">
                    <option value="10" @selected(($perPage ?? 10) == 10)>10 / página</option>
                    <option value="25" @selected(($perPage ?? 10) == 25)>25 / página</option>
                    <option value="50" @selected(($perPage ?? 10) == 50)>50 / página</option>
                    <option value="100" @selected(($perPage ?? 10) == 100)>100 / página</option>
                </select>
            </div>

            {{-- Buscador Principal --}}
            <div class="relative flex-1 min-w-[200px] sm:min-w-[300px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-gray-400"></i>
                </div>
                <input 
                    name="search" 
                    value="{{ $search }}" 
                    class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none placeholder:text-gray-400" 
                    placeholder="Buscar OS, placa o cliente..."
                >
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2">
                <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5 shadow-sm active:scale-95 transition-all" style="background-color: #334155; border-color: #334155;">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 transition-all active:scale-95">
                    <i class="ri-refresh-line"></i>
                    <span>Limpiar</span>
                </x-ui.link-button>
            </div>

            {{-- Botones de Acción (Reportes y Nueva OS) --}}
            <div class="ml-auto flex gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.index') }}" class="h-11 rounded-xl px-5 font-bold shadow-sm transition-all hover:brightness-105 active:scale-95" style="background-color: #4F46E5; color: #FFFFFF; border-color: #4F46E5;">
                    <i class="ri-bar-chart-2-line"></i>
                    <span>Reportes</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.create') }}" class="h-11 rounded-xl px-5 font-bold shadow-sm transition-all hover:brightness-105 active:scale-95" style="background-color: #00A389; color: #FFFFFF; border-color: #00A389;">
                    <i class="ri-add-line"></i>
                    <span>Nueva OS</span>
                </x-ui.link-button>
            </div>
        </form>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full">
                <thead style="background-color: #334155; color: #FFFFFF;">
                    <tr>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider first:rounded-tl-xl text-white">OS</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Ingreso</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Vehículo</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Pagado</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider last:rounded-tr-xl text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800 transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-3 text-sm text-center align-middle font-medium text-gray-800 dark:text-white/90">{{ $order->movement?->number }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle whitespace-nowrap">{{ $order->intake_date?->format('j/m/Y H:i A') }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $order->client?->full_name }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} <span class="text-xs text-gray-500">({{ $order->vehicle?->plate }})</span></td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                @php
                                    $statusColor = match($order->status) {
                                        'open' => 'info',
                                        'finished' => 'success',
                                        'cancelled' => 'error',
                                        default => 'warning'
                                    };
                                    $statusLabel = match($order->status) {
                                        'open' => 'Abierta',
                                        'finished' => 'Finalizada',
                                        'cancelled' => 'Anulada',
                                        default => $order->status
                                    };
                                @endphp
                                <x-ui.badge variant="light" color="{{ $statusColor }}">
                                    {{ $statusLabel }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-3 text-sm text-center align-middle font-bold text-gray-800 dark:text-white/90">S/ {{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle font-bold text-emerald-600">S/ {{ number_format((float) $order->paid_total, 2) }}</td>
                            <td class="px-3 py-3 text-sm">
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


