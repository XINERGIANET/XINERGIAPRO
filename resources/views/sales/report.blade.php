@extends('layouts.app')

@section('content')
    <div>
        <x-common.page-breadcrumb pageTitle="Reporte de Ventas" />

        <x-common.component-card title="Reporte de Ventas" desc="Genera un reporte de las ventas registradas.">
            {{-- Barra de filtros y acción principal --}}
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-4">
                <form method="GET" action="{{ route('sales.report') }}" class="mb-6">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <x-form.date-picker name="date_from" label="Desde" placeholder="dd/mm/yyyy" :defaultDate="$dateFrom"
                                dateFormat="Y-m-d" />
                        </div>
                        <div>
                            <x-form.date-picker name="date_to" label="Hasta" placeholder="dd/mm/yyyy" :defaultDate="$dateTo"
                                dateFormat="Y-m-d" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo de documento</label>
                            <select name="document_type_id"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Todos</option>
                                @foreach ($documentTypes ?? [] as $dt)
                                    <option value="{{ $dt->id }}" @selected(($documentTypeId ?? '') == $dt->id)>{{ $dt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex items-end gap-2">
                        <x-ui.button type="submit" size="md" variant="primary" class="h-11 px-6"
                            style="background-color: #63B7EC; border-color: #63B7EC;">
                            <i class="ri-search-line"></i>
                            <span>Consultar</span>
                        </x-ui.button>
                        <x-ui.link-button href="{{ route('sales.report', $viewId ? ['view_id' => $viewId] : []) }}"
                            size="md" variant="outline" class="h-11 px-6">
                            <i class="ri-refresh-line"></i>
                            <span>Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>
                <div
                    class="shrink-0 border-t border-gray-200 pt-4 lg:border-t-0 lg:border-l lg:border-gray-200 lg:pl-6 lg:pt-0">
                    <button type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-4 text-sm font-medium text-white shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                        <i class="ri-file-pdf-line"></i> Descargar PDF
                    </button>
                </div>
            </div>

            <div x-data="{ openRow: null }"
                class="mt-6 size-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                <div class="overflow-visible">
                    <table class="min-w-full size-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/80" style="background-color: #63B7EC; color: #FFFFFF;">
                                <th class="w-12 px-4 py-4 text-center first:rounded-tl-xl"></th>

                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Comprobante</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Tipo</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Total</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Fecha</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Cliente</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white dark:text-gray-300">
                                    Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800/30">
                            @forelse ($sales as $sale)
                                <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-4 text-center">
                                        <button type="button"
                                            @click="openRow === {{ $sale->id }} ? openRow = null : openRow = {{ $sale->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 dark:bg-brand-500 dark:text-white"
                                            >
                                            <i class="ri-add-line" x-show="openRow !== {{ $sale->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $sale->id }}"></i>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $sale->number }}</span>
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-4 py-3.5 text-sm text-gray-600 dark:text-gray-400 text-center">
                                        {{ $sale->documentType?->name ?? '-' }}</td>
                                    <td
                                        class="whitespace-nowrap px-4 py-3.5 text-center text-sm font-medium tabular-nums text-gray-900 dark:text-white">
                                        {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</td>
                                    <td
                                        class="whitespace-nowrap px-4 py-3.5 text-sm text-gray-600 dark:text-gray-400 text-center">
                                        {{ $sale->moved_at ? \Carbon\Carbon::parse($sale->moved_at)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        @php
                                            $status = $sale->status ?? 'A';
                                            $badgeColor = 'success';
                                            $badgeText = 'Activo';
                                            if ($status === 'P') {
                                                $badgeColor = 'warning';
                                                $badgeText = 'Pendiente';
                                            } elseif ($status !== 'A') {
                                                $badgeColor = 'error';
                                                $badgeText = 'Inactivo';
                                            }
                                        @endphp
                                        <x-ui.badge variant="light" color="{{ $badgeColor }}"
                                            class="inline-flex text-xs font-medium">
                                            {{ $badgeText }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if (($sale->status ?? 'A') === 'P')
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="primary"
                                                        href="{{ route('admin.sales.charge', array_merge(['movement_id' => $sale->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="bg-success-500 text-white hover:bg-success-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #10B981; color: #FFFFFF;"
                                                        aria-label="Cobrar">
                                                        <i class="ri-money-dollar-circle-line"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                        style="transition-delay: 0.5s;">Cobrar</span>
                                                </div>
                                            @endif
                                            <div class="relative group">
                                                <x-ui.link-button size="icon" variant="edit"
                                                    href="{{ route('admin.sales.edit', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar">
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.link-button>
                                                <span
                                                    class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                    style="transition-delay: 0.5s;">Editar</span>
                                            </div>
                                            <form method="POST"
                                                action="{{ route('admin.sales.destroy', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="relative group js-swal-delete" data-swal-title="Eliminar venta?"
                                                data-swal-text="Se eliminara la venta {{ $sale->number }}. Esta accion no se puede deshacer."
                                                data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <x-ui.button size="icon" variant="eliminate" type="submit"
                                                    className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #EF4444; color: #FFFFFF;"
                                                    aria-label="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span
                                                    class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                    style="transition-delay: 0.5s;">Eliminar</span>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="openRow === {{ $sale->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                    <td colspan="10" class="px-6 py-4">
                                        <div class="grid grid-cols-4 gap-3 sm:grid-cols-4">
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cliente</p>
                                                <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->person_name ?: ($sale->salesMovement?->movement?->person_name ?? '-') }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Subtotal</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">IGV</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de pago</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->payment_type ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->moved_at ? $sale->moved_at->format('d/m/Y H:i') : '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usuario</p>
                                                <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->user_name ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</p>
                                                <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->responsible_name ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->currency ?? 'PEN' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado SUNAT</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->status ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Origen</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->movementType?->description ?? 'Venta' }} - {{ $sale->documentType?->name[0] ?? '' }}{{ $sale->salesMovement?->series ?? '' }} - {{ $sale->number }}</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-16">
                                        <div class="flex flex-col items-center justify-center gap-4 text-center">
                                            <div
                                                class="rounded-full bg-gray-100 p-5 text-gray-400 dark:bg-gray-700 dark:text-gray-500">
                                                <i class="ri-file-list-3-line text-4xl"></i>
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay
                                                    ventas en este reporte</p>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta los filtros
                                                    o registra una nueva venta.</p>
                                            </div>
                                            <x-ui.link-button size="sm" variant="primary"
                                                href="{{ route('admin.sales.create', $viewId ? ['view_id' => $viewId] : []) }}"
                                                class="mt-1">
                                                <i class="ri-add-line mr-1"></i>
                                                Registrar venta
                                            </x-ui.link-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="mt-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/30 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando <span
                        class="font-medium text-gray-900 dark:text-white">{{ $sales->firstItem() ?? 0 }}</span>
                    a <span class="font-medium text-gray-900 dark:text-white">{{ $sales->lastItem() ?? 0 }}</span>
                    de <span class="font-medium text-gray-900 dark:text-white">{{ $sales->total() }}</span> registros
                </p>
                <div class="flex justify-end">
                    {{ $sales->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>

    @push('scripts')
        <script>
            (function() {
                function showFlashToast() {
                    const msg = sessionStorage.getItem('flash_success_message');
                    if (!msg) return;
                    sessionStorage.removeItem('flash_success_message');
                    if (window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: 'success',
                            title: msg,
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true
                        });
                    }
                }
                showFlashToast();
                document.addEventListener('turbo:load', showFlashToast);
            })();
        </script>
    @endpush
@endsection
