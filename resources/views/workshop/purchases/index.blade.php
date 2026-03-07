@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Compras Taller" />

    <x-common.component-card title="Registro de compras" desc="Consulta compras de taller y exporta registros mensuales.">
        {{-- Barra de Herramientas Premium (Estilo solicitado) --}}
        <form method="GET" action="{{ route('workshop.purchases.index') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
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
                
                <select name="supplier_id" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Proveedor (Todos)</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId === (int)$supplier->id)>
                            {{ trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: ('#'.$supplier->id) }}
                        </option>
                    @endforeach
                </select>

                <select name="document_kind" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Tipo doc (Todos)</option>
                    @foreach(['FACTURA','BOLETA','RECIBO'] as $kind)
                        <option value="{{ $kind }}" @selected($documentKind === $kind)>{{ $kind }}</option>
                    @endforeach
                </select>

                <select name="branch_id" class="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled(!$isAdmin)>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($scopeBranchId === (int)$branch->id)>
                            {{ $branch->code }} - {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Acciones del Formulario --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#244BB3] px-6 text-sm font-bold text-white shadow-lg shadow-blue-100 transition-all hover:brightness-110 active:scale-95">
                    <i class="ri-search-line"></i>
                    <span>Filtrar</span>
                </button>
                <a href="{{ route('workshop.purchases.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    Limpiar
                </a>
            </div>

            {{-- Botones de Acción (Al final a la derecha) --}}
            <div class="ml-auto flex gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.export.purchases', ['month' => $month, 'supplier_id' => $supplierId ?: null, 'document_kind' => $documentKind ?: null, 'branch_id' => $scopeBranchId]) }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#166534;color:#fff">
                    <i class="ri-file-excel-2-line"></i><span>Exportar</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="primary" href="{{ route('warehouse_movements.input') }}" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#00A389;color:#fff">
                    <i class="ri-add-line"></i><span>Registrar compra</span>
                </x-ui.link-button>
            </div>
        </form>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Documentos del mes</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ (int) ($monthSummary->total_docs ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Monto registrado</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">S/ {{ number_format((float) ($monthSummary->total_amount ?? 0), 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-amber-600">Pendiente de pago</p>
                <p class="mt-2 text-3xl font-black text-amber-700">S/ {{ number_format((float) ($pendingCreditTotal ?? 0), 2) }}</p>
            </div>
        </div>

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1260px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Fecha</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Serie-Numero</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Proveedor</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Pago</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Moneda</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Subtotal</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">IGV</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Pendiente</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        @php
                            $paymentType = strtoupper((string) ($record->movement?->purchaseMovement?->payment_type ?? 'CONTADO'));
                            $pendingAmount = $paymentType === 'CREDITO' ? (float) $record->total : 0;
                        @endphp
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm text-center font-medium">{{ optional($record->issued_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $record->document_kind }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ ($record->series ? $record->series.'-' : '') . $record->document_number }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $record->supplier?->first_name }} {{ $record->supplier?->last_name }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $paymentType === 'CREDITO' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $paymentType }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">{{ $record->currency }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold">{{ number_format((float)$record->subtotal, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float)$record->igv, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float)$record->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold {{ $pendingAmount > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                {{ $pendingAmount > 0 ? 'S/ ' . number_format($pendingAmount, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    @if($record->movement_id)
                                        <div class="relative group">
                                            <x-ui.link-button
                                                size="icon"
                                                variant="primary"
                                                href="{{ route('warehouse_movements.show', $record->movement_id) }}"
                                                className="rounded-xl"
                                                style="background-color: #4F46E5; color: #FFFFFF;"
                                                aria-label="Ver"
                                            >
                                                <i class="ri-eye-line"></i>
                                            </x-ui.link-button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Ver Detalle
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>

                                        <div class="relative group">
                                            <x-ui.link-button
                                                size="icon"
                                                variant="edit"
                                                href="{{ route('warehouse_movements.edit', $record->movement_id) }}"
                                                className="rounded-xl"
                                                style="background-color: #FBBF24; color: #111827;"
                                                aria-label="Editar"
                                            >
                                                <i class="ri-pencil-line"></i>
                                            </x-ui.link-button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Editar
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
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                No disponible
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>

                                        <div class="relative group">
                                            <x-ui.button
                                                size="icon"
                                                variant="edit"
                                                type="button"
                                                className="rounded-xl opacity-60 cursor-not-allowed"
                                                style="background-color: #FBBF24; color: #111827;"
                                                aria-label="Editar"
                                            >
                                                <i class="ri-pencil-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                No disponible
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>
                                    @endif

                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="eliminate"
                                            type="button"
                                            className="rounded-xl"
                                            style="background-color: #EF4444; color: #FFFFFF;"
                                            onclick="alert('Operacion Eliminar disponible en el modulo origen de movimientos.');"
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
                        <tr><td colspan="11" class="px-4 py-4 text-sm text-gray-500 text-center">Sin compras para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $records->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $records->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $records->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $records->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>
</div>
@endsection

