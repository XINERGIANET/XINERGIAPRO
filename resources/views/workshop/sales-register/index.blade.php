@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Ventas Taller" />

    <x-common.component-card title="Registro de ventas" desc="Ventas de taller por tipo de cliente.">
        {{-- Barra de Herramientas Premium (Estilo solicitado) --}}
        <form method="GET" action="{{ route('workshop.sales-register.index') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
            {{-- Selector de Registros --}}
            <div class="flex items-center gap-2">
                <select name="per_page" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="10" @selected(($perPage ?? 10) == 10)>10 / pág</option>
                    <option value="25" @selected(($perPage ?? 10) == 25)>25 / pág</option>
                    <option value="50" @selected(($perPage ?? 10) == 50)>50 / pág</option>
                    <option value="100" @selected(($perPage ?? 10) == 100)>100 / pág</option>
                </select>
            </div>

            {{-- Filtros Secundarios --}}
            <div class="flex flex-wrap items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                
                <select name="tab" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="natural" @selected($tab === 'natural')>Natural</option>
                    <option value="corporativo" @selected($tab === 'corporativo')>Corporativo</option>
                </select>
            </div>

            {{-- Acciones del Formulario --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#244BB3] px-6 text-sm font-bold text-white shadow-lg shadow-blue-100 transition-all hover:brightness-110 active:scale-95">
                    <i class="ri-search-line"></i>
                    <span>Filtrar</span>
                </button>
                <a href="{{ route('workshop.sales-register.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    Limpiar
                </a>
            </div>

            {{-- Botones de Acción (Al final a la derecha) --}}
            <div class="ml-auto flex gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.export.sales', ['month' => $month, 'customer_type' => $tab]) }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#166534;color:#fff">
                    <i class="ri-file-excel-2-line"></i><span>Exportar</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.index') }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#1d4ed8;color:#fff">
                    <i class="ri-bar-chart-2-line"></i><span>Reportes</span>
                </x-ui.link-button>
            </div>
        </form>

        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-700 dark:text-white/90">Sucursal: {{ $branch->legal_name }}</h2>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-gray-100 bg-slate-50 p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Subtotal</div>
                <div class="mt-1 text-xl font-bold text-slate-700">{{ number_format($subtotal, 2) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-slate-50 p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">IGV</div>
                <div class="mt-1 text-xl font-bold text-slate-700">{{ number_format($tax, 2) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-blue-50 p-4 shadow-sm dark:border-gray-800 dark:bg-blue-500/[0.05]">
                <div class="text-xs font-bold uppercase tracking-wider text-blue-600">Total</div>
                <div class="mt-1 text-2xl font-black text-blue-700">{{ number_format($total, 2) }}</div>
            </div>
        </div>

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1000px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Fecha</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Documento</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Subtotal</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">IGV</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm text-center font-medium">{{ optional($sale->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $sale->movement?->number }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $sale->movement?->person_name }}</td>
                            <td class="px-4 py-3 text-sm text-center uppercase">{{ $sale->movement?->person?->person_type }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold">{{ number_format((float)$sale->subtotal, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float)$sale->tax, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float)$sale->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
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
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                No disponible
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
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
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500 text-center">Sin ventas para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $sales->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>
</div>
@endsection

