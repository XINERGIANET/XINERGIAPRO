@extends('layouts.app')

@php
    $viewId = request('view_id');
    $statusOptions = [
        'all' => 'Todos los estados',
        'awaiting_approval' => 'Esperando aprobacion',
        'approved' => 'Aprobado',
        'in_progress' => 'En reparacion',
        'finished' => 'Termianda',
        'delivered' => 'Entregada',
        'cancelled' => 'Anulada',
    ];
@endphp

@section('content')
<div x-data="{ openRow: null }">
    <x-common.page-breadcrumb pageTitle="Ordenes Taller" />

    <x-common.component-card title="Ordenes de servicio" desc="Consulta y gestiona ordenes del taller.">
        {{-- Barra de herramientas: el import NO puede ir dentro del form GET de filtros (HTML no permite formularios anidados). --}}
        <div class="mb-5 flex flex-col gap-3">
            <form method="GET" action="{{ route('workshop.orders.index') }}" class="flex flex-col gap-3">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                    <div class="relative w-full xl:flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <i class="ri-search-line text-gray-400"></i>
                        </div>
                        <input
                            name="search"
                            value="{{ $search }}"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none placeholder:text-gray-400"
                            placeholder="Buscar por OS, DNI, nombre completo, placa, marca o modelo..."
                        >
                    </div>
                </div>

                <div class="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-center">
                    <div class="w-full xl:w-32 xl:flex-none">
                        <select name="per_page" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all" onchange="this.form.submit()">
                            <option value="10" @selected(($perPage ?? 10) == 10)>10 / página</option>
                            <option value="25" @selected(($perPage ?? 10) == 25)>25 / página</option>
                            <option value="50" @selected(($perPage ?? 10) == 50)>50 / página</option>
                            <option value="100" @selected(($perPage ?? 10) == 100)>100 / página</option>
                        </select>
                    </div>

                    <div class="w-full xl:w-[240px] xl:flex-none">
                        <select name="status" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all">
                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(($selectedStatus ?? 'all') === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-[180px] xl:flex-none">
                        <input
                            type="date"
                            name="date_from"
                            value="{{ $dateFrom ?? '' }}"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none"
                        >
                    </div>

                    <div class="w-full sm:w-[180px] xl:flex-none">
                        <input
                            type="date"
                            name="date_to"
                            value="{{ $dateTo ?? '' }}"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none"
                        >
                    </div>

                    <div class="flex w-full flex-wrap items-center gap-2 xl:ml-auto xl:w-auto xl:flex-none">
                        <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5 shadow-sm active:scale-95 transition-all" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line"></i>
                            <span>Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index', $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 transition-all active:scale-95">
                            <i class="ri-refresh-line"></i>
                            <span>Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </div>
            </form>

            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                <form method="POST" action="{{ route('workshop.orders.import-excel') }}" enctype="multipart/form-data" class="inline" data-turbo="false">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="file" name="file" id="workshop-orders-excel-import-input" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" class="hidden" onchange="if (this.files.length) this.form.submit();">
                    <x-ui.button size="md" variant="outline" type="button" class="h-11 rounded-xl border-gray-300 px-4 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5"
                        onclick="document.getElementById('workshop-orders-excel-import-input').click();">
                        <i class="ri-file-excel-2-line"></i>
                        <span>Importar Excel</span>
                    </x-ui.button>
                </form>
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.maintenance-board.create', $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 rounded-xl px-5 font-bold shadow-sm transition-all hover:brightness-105 active:scale-95" style="background-color: #00A389; color: #FFFFFF; border-color: #00A389;">
                    <i class="ri-add-line"></i>
                    <span>Nueva OS</span>
                </x-ui.link-button>
            </div>
        </div>
        @if ($errors->has('file'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/30 dark:text-red-200">
                <strong>Importación OS:</strong> {{ $errors->first('file') }}
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full">
                <thead style="background-color: #334155; color: #FFFFFF;">
                    <tr>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider first:rounded-tl-xl text-white"></th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">OS</th>
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
                        @php
                            $clientName = trim(((string) ($order->client?->first_name ?? '')) . ' ' . ((string) ($order->client?->last_name ?? '')));
                            $vehicleLabel = trim(((string) ($order->vehicle?->brand ?? '')) . ' ' . ((string) ($order->vehicle?->model ?? '')));
                        @endphp
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800 transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-3 text-center align-middle">
                                <button
                                    type="button"
                                    @click="openRow === {{ $order->id }} ? openRow = null : openRow = {{ $order->id }}"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600"
                                >
                                    <i class="ri-add-line" x-show="openRow !== {{ $order->id }}"></i>
                                    <i class="ri-subtract-line" x-show="openRow === {{ $order->id }}"></i>
                                </button>
                            </td>
                            <td class="px-3 py-3 text-sm text-center align-middle font-medium text-gray-800 dark:text-white/90">{{ $order->movement?->number }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle whitespace-nowrap">{{ $order->intake_date?->format('j/m/Y H:i A') }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                {{ $clientName ?: ((string) ($order->client?->document_number ?? '-') ) }}
                            </td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} <span class="text-xs text-gray-500">({{ $order->vehicle?->plate }})</span></td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                @php
                                    $statusColor = match($order->status) {
                                        'registered' => 'info',
                                        'draft' => 'gray',
                                        'diagnosis' => 'info',
                                        'awaiting_approval' => 'warning',
                                        'approved' => 'success',
                                        'in_progress' => 'warning',
                                        'open' => 'info',
                                        'finished' => 'success',
                                        'delivered' => 'success',
                                        'cancelled' => 'error',
                                        default => 'warning'
                                    };
                                    $statusLabel = match($order->status) {
                                        'registered' => 'Registrada',
                                        'draft' => 'Borrador',
                                        'diagnosis' => 'Diagnostico',
                                        'awaiting_approval' => 'Esperando aprobacion',
                                        'approved' => 'Aprobado',
                                        'in_progress' => 'En reparacion',
                                        'open' => 'Abierta',
                                        'finished' => 'Finalizada',
                                        'delivered' => 'Entregada',
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
                                            href="{{ route('workshop.maintenance-board.edit', array_merge([$order], $viewId ? ['view_id' => $viewId] : [])) }}"
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
                        <tr x-show="openRow === {{ $order->id }}" x-cloak class="border-t border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40">
                            <td colspan="9" class="px-6 py-4">
                                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Documento</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $order->client?->document_number ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Teléfono</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $order->client?->phone ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vehículo</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $vehicleLabel ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Placa</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $order->vehicle?->plate ?: '-' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kilometraje</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $order->mileage_in !== null ? number_format((float) $order->mileage_in, 0) : '-' }}</p>
                                    </div>
                                </div>

                                <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Observaciones</p>
                                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $order->observations ?: 'Sin observaciones.' }}</p>
                                </div>

                                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Detalle de la orden</p>
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $order->details->count() }} línea(s)</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full">
                                            <thead class="bg-gray-50 dark:bg-gray-800/70">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Tipo</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Descripción</th>
                                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Cant.</th>
                                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Técnico</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($order->details as $detail)
                                                    @php
                                                        $detailDescription = $detail->description
                                                            ?: $detail->service?->name
                                                            ?: $detail->product?->description
                                                            ?: '-';
                                                        $technicianName = trim(((string) ($detail->technician?->first_name ?? '')) . ' ' . ((string) ($detail->technician?->last_name ?? '')));
                                                    @endphp
                                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $detail->line_type === 'product' ? 'Producto' : 'Servicio' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $detailDescription }}</td>
                                                        <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $detail->qty, 2) }}</td>
                                                        <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ $technicianName ?: '-' }}</td>
                                                        <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800 dark:text-gray-200">S/ {{ number_format((float) $detail->total, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Sin detalle.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-4 text-sm text-gray-500 text-center">Sin ordenes para el filtro.</td></tr>
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

