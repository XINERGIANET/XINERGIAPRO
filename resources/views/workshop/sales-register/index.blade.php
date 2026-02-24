@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Ventas Taller" />

    <x-common.component-card title="Registro de ventas" desc="Ventas de taller por tipo de cliente.">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.export.sales', ['month' => $month, 'customer_type' => $tab]) }}" style="background-color:#166534;color:#fff">
                <i class="ri-file-excel-2-line"></i><span>Exportar Excel</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.index') }}" style="background-color:#1d4ed8;color:#fff">
                <i class="ri-bar-chart-2-line"></i><span>Reportes</span>
            </x-ui.link-button>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Sucursal: {{ $branch->legal_name }}</h2>
            <a class="h-11 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white inline-flex items-center"
               href="{{ route('workshop.reports.export.sales', ['month' => $month, 'customer_type' => $tab]) }}">Exportar Excel</a>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <select name="tab" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <option value="natural" @selected($tab === 'natural')>Natural</option>
                <option value="corporativo" @selected($tab === 'corporativo')>Corporativo</option>
            </select>
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.sales-register.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-2 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>Subtotal:</strong> {{ number_format($subtotal, 2) }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>IGV:</strong> {{ number_format($tax, 2) }}</div>
            <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800"><strong>Total:</strong> {{ number_format($total, 2) }}</div>
        </div>

        <div class="overflow-x-auto overflow-y-hidden mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1000px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Fecha</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Documento</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Subtotal</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">IGV</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ optional($sale->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $sale->movement?->number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $sale->movement?->person_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $sale->movement?->person?->person_type }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$sale->subtotal, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$sale->tax, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$sale->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    @if($sale->movement_id)
                                        <div class="relative group">
                                            <x-ui.link-button
                                                size="icon"
                                                variant="primary"
                                                href="{{ route('admin.sales.index') }}"
                                                className="rounded-xl"
                                                style="background-color: #4F46E5; color: #FFFFFF;"
                                                aria-label="Ver"
                                            >
                                                <i class="ri-eye-line"></i>
                                            </x-ui.link-button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Ver
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>
                                    @else
                                        <div class="relative group">
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="button"
                                                className="rounded-xl opacity-60 cursor-not-allowed"
                                                style="background-color: #4F46E5; color: #FFFFFF;"
                                                aria-label="Ver"
                                            >
                                                <i class="ri-eye-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                No disponible
                                                <span class="absolute bottom-full left-1/2 -ml-1 border-4 border-transparent border-b-gray-900"></span>
                                            </span>
                                        </div>
                                    @endif

                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="edit"
                                            type="button"
                                            className="rounded-xl"
                                            style="background-color: #FBBF24; color: #111827;"
                                            onclick="alert('Operacion Editar disponible en el modulo de Ventas.');"
                                            aria-label="Editar"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Editar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="eliminate"
                                            type="button"
                                            className="rounded-xl"
                                            style="background-color: #EF4444; color: #FFFFFF;"
                                            onclick="alert('Operacion Eliminar disponible en el modulo de Ventas.');"
                                            aria-label="Eliminar"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Eliminar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500">Sin ventas para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $sales->links() }}</div>
    </x-common.component-card>
</div>
@endsection
