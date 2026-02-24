@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Reportes Taller" />

    <x-common.component-card title="Reportes" desc="Indicadores operativos, financieros y exportaciones del taller.">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.index') }}" style="background-color:#1d4ed8;color:#fff">
                <i class="ri-file-list-3-line"></i><span>Ordenes</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.sales-register.index') }}" style="background-color:#0f766e;color:#fff">
                <i class="ri-line-chart-line"></i><span>Ventas</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.purchases.index') }}" style="background-color:#166534;color:#fff">
                <i class="ri-shopping-bag-2-line"></i><span>Compras</span>
            </x-ui.link-button>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <option value="">Todos</option>
                @foreach(['draft','diagnosis','awaiting_approval','approved','in_progress','finished','delivered','cancelled'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-2 dark:border-gray-800 dark:bg-white/[0.02]">
            <div>
                <h2 class="mb-2 text-base font-semibold">Exportaciones</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <a class="rounded-lg bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.purchases', ['month' => now()->format('Y-m')]) }}">Compras mes</a>
                    <a class="rounded-lg bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.sales', ['month' => now()->format('Y-m'), 'customer_type' => 'natural']) }}">Ventas Natural</a>
                    <a class="rounded-lg bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.sales', ['month' => now()->format('Y-m'), 'customer_type' => 'corporativo']) }}">Ventas Corporativo</a>
                    <a class="rounded-lg bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.orders', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}">OS filtradas</a>
                    <a class="rounded-lg bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.productivity', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}">Productividad</a>
                </div>
            </div>
            <div>
                <h2 class="mb-2 text-base font-semibold">Exportar kardex por producto</h2>
                <form method="GET" action="{{ route('workshop.reports.export.kardex') }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    <select name="product_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-3" required>
                        <option value="">Producto</option>
                        @foreach($kardexProducts as $product)
                            <option value="{{ $product->id }}">{{ $product->description }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <button class="h-11 rounded-lg bg-slate-800 px-4 text-sm font-medium text-white">Descargar</button>
                </form>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 md:grid-cols-5">
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>OS:</strong> {{ $orders->total() }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>Subtotal:</strong> {{ number_format((float) $totals['subtotal'], 2) }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>IGV:</strong> {{ number_format((float) $totals['tax'], 2) }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>Total:</strong> {{ number_format((float) $totals['total'], 2) }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>Pagado:</strong> {{ number_format((float) $totals['paid_total'], 2) }}</div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><h2 class="mb-2 font-semibold">OS por estado</h2>@forelse($byStatus as $state => $qty)<div class="flex justify-between border-b py-1 text-sm"><span>{{ $state }}</span><strong>{{ $qty }}</strong></div>@empty<p class="text-sm text-gray-600">Sin datos.</p>@endforelse</div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><h2 class="mb-2 font-semibold">Servicios frecuentes</h2>@forelse($topServices as $service)<div class="flex justify-between border-b py-1 text-sm"><span>{{ $service->description }}</span><strong>{{ $service->qty }}</strong></div>@empty<p class="text-sm text-gray-600">Sin datos.</p>@endforelse</div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><h2 class="mb-2 font-semibold">Repuestos usados</h2>@forelse($topParts as $part)<div class="flex justify-between border-b py-1 text-sm"><span>{{ $part->description }}</span><strong>{{ number_format((float) $part->qty, 2) }}</strong></div>@empty<p class="text-sm text-gray-600">Sin datos.</p>@endforelse</div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><h2 class="mb-2 font-semibold">Clientes con deuda</h2>@forelse($clientsWithDebt as $row)<div class="flex justify-between border-b py-1 text-sm"><span>{{ $row->client?->first_name }} {{ $row->client?->last_name }}</span><strong>{{ number_format((float) $row->debt, 2) }}</strong></div>@empty<p class="text-sm text-gray-600">Sin deudas pendientes.</p>@endforelse</div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><h2 class="mb-2 font-semibold">Productividad tecnico</h2>@forelse($productivityByTechnician as $row)<div class="flex justify-between border-b py-1 text-sm"><span>{{ $row->technician }}</span><span>{{ $row->lines_done }} lineas / {{ number_format((float) $row->billed_total, 2) }}</span></div>@empty<p class="text-sm text-gray-600">Sin datos.</p>@endforelse</div>
        </div>

        <div class="mb-4 table-responsive lg:!overflow-visible rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1000px]">
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
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $order->movement?->number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->intake_date?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->vehicle?->plate }}</td>
                            <td class="px-4 py-3 text-sm">{{ $order->status }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $order->paid_total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-end gap-2">
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
                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Ver
                                            <span class="absolute bottom-full left-1/2 -ml-1 border-4 border-transparent border-b-gray-900"></span>
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
                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Editar
                                            <span class="absolute bottom-full left-1/2 -ml-1 border-4 border-transparent border-b-gray-900"></span>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('workshop.orders.destroy', $order) }}"
                                        class="relative group js-swal-delete"
                                        data-swal-title="Eliminar/anular esta OS?"
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
                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Eliminar
                                            <span class="absolute bottom-full left-1/2 -ml-1 border-4 border-transparent border-b-gray-900"></span>
                                        </span>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </x-common.component-card>
</div>
@endsection
