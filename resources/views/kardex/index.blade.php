@extends('layouts.app')

@section('content')
<div>
    <x-common.page-breadcrumb pageTitle="Kardex" />

    <x-common.component-card title="Kardex" :desc="'Consulta el historial de movimientos de inventario por producto.' . ($branch ? ' Sucursal: ' . e($branch->legal_name) . '.' : '')">
        {{-- Filtros --}}
        <form method="GET" action="{{ route('kardex.index') }}" class="mb-6 space-y-5">
            @if ($viewId)
                <input type="hidden" name="view_id" value="{{ $viewId }}">
            @endif
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripción</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Buscar por código o nombre"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    >
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Producto</label>
                    <select name="product_id"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="all" @selected($productId == 'all')>Todos los productos</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected($productId == $p->id)>
                                {{ $p->code }} - {{ $p->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Categoría</label>
                    <select name="category_id"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="all" @selected($categoryId == 'all')>Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>
                                {{ $category->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-form.date-picker
                        name="date_from"
                        label="Desde"
                        placeholder="dd/mm/yyyy"
                        :defaultDate="$dateFrom"
                        dateFormat="Y-m-d"
                    />
                </div>
                <div>
                    <x-form.date-picker
                        name="date_to"
                        label="Hasta"
                        placeholder="dd/mm/yyyy"
                        :defaultDate="$dateTo"
                        dateFormat="Y-m-d"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                    <select name="document_type_id"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="all" @selected($documentTypeId == 'all')>Todos</option>
                        @foreach ($typeOptions as $typeOption)
                            <option value="{{ $typeOption->id }}" @selected((string) $documentTypeId === (string) $typeOption->id)>
                                {{ $typeOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <x-form.date-picker
                        name="date_to"
                        label="Hasta"
                        placeholder="dd/mm/yyyy"
                        :defaultDate="$dateTo"
                        dateFormat="Y-m-d"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Situación</label>
                    <select name="situation"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="all" @selected($situation === 'all')>Todos</option>
                        <option value="E" @selected($situation === 'E')>Activado</option>
                        <option value="I" @selected($situation === 'I')>Inactivo</option>
                        <option value="A" @selected($situation === 'A')>Anulado</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-3 flex-wrap">
                    <x-ui.button type="submit" size="md" variant="primary" class="h-11 px-6" style="background-color: #334155; border-color: #334155;">
                        <i class="ri-search-line"></i>
                        <span>Consultar</span>
                    </x-ui.button>
                    <button type="button" id="kardex-pdf-button" class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-500 px-6 text-sm font-semibold text-white shadow-theme-xs hover:bg-red-600">
                        <i class="ri-file-pdf-line"></i>
                        <span>PDF</span>
                    </button>
                    <button type="button" id="kardex-excel-button" class="inline-flex h-11 items-center gap-2 rounded-lg bg-green-500 px-6 text-sm font-semibold text-white shadow-theme-xs hover:bg-green-600">
                        <i class="ri-file-excel-line"></i>
                        <span>Excel</span>
                    </button>
                    <a href="#kardex-summary" class="inline-flex h-11 items-center gap-2 rounded-lg bg-fuchsia-600 px-6 text-sm font-semibold text-white shadow-theme-xs hover:bg-fuchsia-700">
                        <i class="ri-printer-line"></i>
                        <span>Resumen</span>
                    </a>
                    <x-ui.link-button href="{{ route('kardex.index', $viewId ? ['view_id' => $viewId] : []) }}" size="md" variant="outline" class="h-11 px-6">
                        <i class="ri-refresh-line"></i>
                        <span>Limpiar</span>
                    </x-ui.link-button>
                </div>
            </div>
        </form>

        <div id="kardex-summary" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Registros</p>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ number_format($summary['records']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Entradas</p>
                <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($summary['entries'], 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Salidas</p>
                <p class="mt-2 text-2xl font-black text-rose-600">{{ number_format($summary['exits'], 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Valorizado</p>
                <p class="mt-2 text-2xl font-black text-amber-600">S/ {{ number_format($summary['valuation'], 2) }}</p>
            </div>
        </div>


        {{-- Tabla de movimientos --}}
        <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-x-auto">
            <table id="kardex-table" class="w-full min-w-[1500px]">
                    <thead>
                        <tr class="text-white text-center" style="background-color: #334155;">
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4 first:rounded-tl-xl">Producto</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Tipo</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Unidad</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Stock ant.</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Cantidad</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Stock actual</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">P. unitario</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Moneda</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Fecha</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Origen</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Situación</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4 last:rounded-tr-xl">Operaciones</th>
                        </tr>
                </thead>
                    <tbody>
                        @forelse ($movements as $idx => $m)
                            @php
                                $tipo = $m['type'] ?? '-';
                                $dateValue = !empty($m['date']) ? \Carbon\Carbon::parse($m['date']) : null;
                                $productLabel = ($m['product_code'] ?? $product?->code ?? '-')
                                    . ' - '
                                    . ($m['product_description'] ?? $product?->description ?? '-');
                                $tipoStyle = match(true) {
                                    str_starts_with($tipo, 'Entrada') => 'background-color: #d1fae5; color: #065f46; border: 1px solid #10b981;',
                                    str_starts_with($tipo, 'Salida') => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444;',
                                    $tipo === 'Saldo inicial' => 'background-color: #f1f5f9; color: #334155; border: 1px solid #94a3b8;',
                                    default => 'background-color: #FFA770; color: #ffffff; border: 1px solid #FF6F2E;',
                                };
                                $operationIcon = match (true) {
                                    str_contains(strtolower($tipo), 'venta') => 'ri-shopping-cart-2-fill',
                                    str_contains(strtolower($tipo), 'compra') => 'ri-shopping-bag-3-fill',
                                    str_starts_with($tipo, 'Entrada'), str_starts_with($tipo, 'Salida') => 'ri-briefcase-4-fill',
                                    default => 'ri-file-list-3-fill',
                                };
                                $situationCode = (string) ($m['situation'] ?? 'E');
                                $situationLabel = match ($situationCode) {
                                    'A' => 'Anulado',
                                    'I' => 'Inactivo',
                                    default => 'Activado',
                                };
                                $situationStyle = match ($situationCode) {
                                    'A' => 'background-color: #ef4444;',
                                    'I' => 'background-color: #f59e0b;',
                                    default => 'background-color: #4CAF50;',
                                };
                            @endphp
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 {{ $idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-gray-50/50 dark:bg-white/[0.02]' }}">
                                <td class="px-3 py-2.5 text-sm text-center text-gray-800 dark:text-white/90 sm:px-4">
                                    {{ $productLabel }}
                                </td>
                                <td class="px-3 py-2.5 text-center sm:px-4">
                                    <span
                                        class="inline-flex items-center whitespace-nowrap px-3 py-1 rounded-full text-xs font-medium leading-none"
                                        style="{{ $tipoStyle }} min-width: max-content;"
                                    >{{ $tipo }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4">
                                    {{ $m['unit'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-700 dark:text-gray-300 sm:px-4 font-medium">
                                    {{ $m['type'] === 'Saldo inicial' ? '-' : number_format($m['previous_stock'] ?? 0, 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-700 dark:text-gray-300 sm:px-4 font-medium">
                                    {{ ($m['quantity'] ?? 0) > 0 ? number_format($m['quantity'], 0) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center font-semibold text-gray-800 dark:text-white sm:px-4">
                                    {{ number_format($m['balance'] ?? 0, 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4">
                                    {{ isset($m['unit_price']) && $m['unit_price'] !== null ? 'S/ ' . number_format($m['unit_price'], 2) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4">
                                    {{ $m['currency'] ?? 'PEN' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-800 dark:text-white/90 sm:px-4">
                                    @if ($dateValue)
                                        <div class="leading-tight">
                                            <div>{{ $dateValue->format('Y-m-d') }}</div>
                                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $dateValue->format('h:i:s A') }}</div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4 text-xs">
                                    {{ $m['origin'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-center sm:px-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white" style="{{ $situationStyle }}">
                                        {{ $situationLabel }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center sm:px-4">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white" style="background-color: #E91E63;">
                                        <i class="{{ $operationIcon }} text-base"></i>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-gray-500 dark:text-gray-400">
                                        <div class="rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                                            <i class="ri-file-list-3-line text-3xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">
                                            @if ($productId === 'all')
                                                No hay movimientos de productos en el período seleccionado.
                                            @elseif ($productId)
                                                No hay movimientos en el período seleccionado.
                                            @else
                                                Seleccione un producto y haga clic en Consultar para ver el kardex.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </x-common.component-card>
</div>

<script>
    (function () {
        const rows = @json($movements);
        const pdfButton = document.getElementById('kardex-pdf-button');
        const excelButton = document.getElementById('kardex-excel-button');

        if (pdfButton) {
            pdfButton.addEventListener('click', function () {
                window.print();
            });
        }

        if (excelButton) {
            excelButton.addEventListener('click', function () {
                const headers = ['Producto', 'Categoria', 'Tipo', 'Unidad', 'Stock anterior', 'Cantidad', 'Stock actual', 'P. unitario', 'Moneda', 'Fecha', 'Origen', 'Situacion'];
                const csvRows = [headers.join(',')];

                rows.forEach((row) => {
                    const values = [
                        `${row.product_code || '-'} - ${row.product_description || '-'}`,
                        row.category || 'Sin categoria',
                        row.type || '-',
                        row.unit || '-',
                        Number(row.previous_stock || 0),
                        Number(row.quantity || 0),
                        Number(row.balance || 0),
                        row.unit_price != null ? Number(row.unit_price).toFixed(2) : '',
                        row.currency || 'PEN',
                        row.date || '',
                        row.origin || '-',
                        row.situation === 'A' ? 'Anulado' : (row.situation === 'I' ? 'Inactivo' : 'Activado'),
                    ].map((value) => `"${String(value).replaceAll('"', '""')}"`);

                    csvRows.push(values.join(','));
                });

                const blob = new Blob(["\uFEFF" + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'kardex.csv';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            });
        }
    })();
</script>

@endsection
