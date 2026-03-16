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
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                    <x-form.select-autocomplete
                        name="product_id"
                        :value="$productId"
                        :options="collect($products)->map(fn($p) => ['value' => $p->id, 'label' => $p->code . ' - ' . $p->description])->prepend(['value' => 'all', 'label' => 'Todos los productos'])->values()->all()"
                        placeholder="Todos los productos"
                        label="Producto"
                        inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                </div>
                <div>
                    <x-form.select-autocomplete
                        name="category_id"
                        :value="$categoryId"
                        :options="collect($categories)->map(fn($c) => ['value' => $c->id, 'label' => $c->description])->prepend(['value' => 'all', 'label' => 'Todas'])->values()->all()"
                        placeholder="Todas"
                        label="Categoría"
                        inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                </div>
                <div>
                    <x-form.select-autocomplete
                        name="product_type_id"
                        :value="$productTypeId"
                        :options="collect($productTypes)->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->prepend(['value' => 'all', 'label' => 'Todos'])->values()->all()"
                        placeholder="Todos"
                        label="Tipo"
                        inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <x-form.date-picker
                        id="kardex-date-from"
                        name="date_from"
                        label="Fecha inicio"
                        :defaultDate="$dateFrom"
                        dateFormat="Y-m-d H:i"
                        :enableTime="true"
                        :time24hr="true"
                        :altInput="true"
                        altFormat="d/m/Y H:i"
                        locale="es"
                        placeholder="dd/mm/yyyy hh:mm"
                    />
                </div>
                <div>
                    <x-form.date-picker
                        id="kardex-date-to"
                        name="date_to"
                        label="Fecha fin"
                        :defaultDate="$dateTo"
                        dateFormat="Y-m-d H:i"
                        :enableTime="true"
                        :time24hr="true"
                        :altInput="true"
                        altFormat="d/m/Y H:i"
                        locale="es"
                        placeholder="dd/mm/yyyy hh:mm"
                    />
                </div>
                <div>
                    <x-form.select-autocomplete
                        name="situation"
                        :value="$situation"
                        :options="[['value' => 'all', 'label' => 'Todos'], ['value' => 'E', 'label' => 'Activado'], ['value' => 'I', 'label' => 'Inactivo'], ['value' => 'A', 'label' => 'Anulado']]"
                        placeholder="Todos"
                        label="Situación"
                        inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                </div>
                <div class="flex flex-wrap items-end justify-end gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" class="h-11 px-6" style="background-color: #334155; border-color: #334155;">
                        <i class="ri-search-line"></i>
                    </x-ui.button>
                    <button
                        type="submit"
                        formaction="{{ route('kardex.pdf') }}"
                        formtarget="_blank"
                        class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-500 px-6 text-sm font-semibold text-white shadow-theme-xs hover:bg-red-600"
                    >
                        <i class="ri-file-pdf-line"></i>
                        <span>PDF</span>
                    </button>
                    <button type="button" id="kardex-excel-button" class="inline-flex h-11 items-center gap-2 rounded-lg bg-green-500 px-6 text-sm font-semibold text-white shadow-theme-xs hover:bg-green-600">
                        <i class="ri-file-excel-line"></i>
                        <span>Excel</span>
                    </button>
                    {{-- <a href="#kardex-summary" class="inline-flex h-11 items-center gap-2 rounded-lg px-6 text-sm font-semibold text-white shadow-theme-xs hover:opacity-95" style="background-color: #9333ea;">
                        <i class="ri-printer-line"></i>
                       
                    </a> --}}
                </div>
            </div>
        </form>

   


        {{-- Tabla de movimientos --}}
        <div id="kardex-summary" class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ openRow: null }">
            <table id="kardex-table" class="w-full" style="table-layout: fixed; min-width: 960px;">
                    <thead>
                        <tr class="text-white text-center" style="background-color: #334155;">
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-9 first:rounded-tl-xl"></th>
                            <th class="px-2 py-2 text-xs font-semibold uppercase text-left" style="width: 120px;">Producto</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-24">Tipo</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-16">Stock ant.</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-14">Cantidad</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-16">Stock actual</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-20">P. unitario</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-24">Fecha</th>
                            <th class="px-2 py-2 text-xs font-semibold uppercase w-28">Origen</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-20">Situación</th>
                            <th class="px-1.5 py-2 text-xs font-semibold uppercase w-14 last:rounded-tr-xl">Oper.</th>
                        </tr>
                </thead>
                    <tbody>
                        @forelse ($movements as $idx => $m)
                            @php
                                $rowId = $m['id'] ?? ('kardex-' . $idx);
                                $tipo = $m['type'] ?? '-';
                                $dateValue = !empty($m['date']) ? \Carbon\Carbon::parse($m['date']) : null;
                                $productCode = $m['product_code'] ?? $product?->code ?? '-';
                                $productDesc = (string) ($m['product_description'] ?? $product?->description ?? '');
                                $productLine1 = $productCode;
                                $productLine2 = '';
                                if ($productDesc !== '') {
                                    $productLine1 = $productCode . ' - ';
                                    $len = strlen($productDesc);
                                    if ($len > 12) {
                                        $spacePos = strpos($productDesc, ' ', 5);
                                        if ($spacePos !== false && $spacePos < 20) {
                                            $productLine1 .= trim(substr($productDesc, 0, $spacePos));
                                            $productLine2 = trim(substr($productDesc, $spacePos));
                                        } else {
                                            $productLine1 .= trim(substr($productDesc, 0, 14));
                                            $productLine2 = trim(substr($productDesc, 14));
                                        }
                                    } else {
                                        $productLine1 .= $productDesc;
                                    }
                                }
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
                                $operationUrl = $m['operation_url'] ?? null;
                                $operationLabel = $m['operation_label'] ?? null;
                            @endphp
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 {{ $idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-gray-50/50 dark:bg-white/[0.02]' }}">
                                <td class="px-1.5 py-2 text-center align-middle">
                                    <button type="button"
                                        @click="openRow === {{ (int) $rowId }} ? openRow = null : openRow = {{ (int) $rowId }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600"
                                        aria-label="Expandir">
                                        <i class="ri-add-line text-sm" x-show="openRow !== {{ (int) $rowId }}"></i>
                                        <i class="ri-subtract-line text-sm" x-show="openRow === {{ (int) $rowId }}" x-cloak></i>
                                    </button>
                                </td>
                                <td class="px-2 py-2 text-sm text-left text-gray-800 dark:text-white/90 align-top overflow-hidden" style="width: 120px; max-width: 120px;">
                                    <div class="min-w-0 leading-tight break-words" style="max-width: 116px;">
                                        <div class="font-medium truncate" title="{{ $productLine1 }}{{ $productLine2 !== '' ? ' ' . $productLine2 : '' }}">{{ $productLine1 }}</div>
                                        @if ($productLine2 !== '')
                                        <div class="text-gray-600 dark:text-gray-400 mt-0.5 truncate" title="{{ $productLine2 }}">{{ $productLine2 }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-1.5 py-2 text-center overflow-hidden">
                                    <span
                                        class="inline-flex items-center max-w-full px-1.5 py-0.5 rounded-full text-xs font-medium leading-tight truncate"
                                        style="{{ $tipoStyle }}"
                                        title="{{ $tipo }}"
                                    >{{ $tipo }}</span>
                                </td>
                                <td class="px-1.5 py-2 text-base text-center text-gray-800 dark:text-gray-200 font-bold">
                                    {{ $m['type'] === 'Saldo inicial' ? '-' : number_format($m['previous_stock'] ?? 0, 0) }}
                                </td>
                                <td class="px-1.5 py-2 text-base text-center text-gray-800 dark:text-gray-200 font-bold">
                                    {{ ($m['quantity'] ?? 0) > 0 ? number_format($m['quantity'], 0) : '-' }}
                                </td>
                                <td class="px-1.5 py-2 text-base text-center text-gray-800 dark:text-white font-bold">
                                    {{ number_format($m['balance'] ?? 0, 0) }}
                                </td>
                                <td class="px-1.5 py-2 text-base text-center text-gray-800 dark:text-gray-200 font-bold">
                                    {{ isset($m['unit_price']) && $m['unit_price'] !== null ? 'S/ ' . number_format($m['unit_price'], 2) : '-' }}
                                </td>
                                <td class="px-1.5 py-2 text-sm text-center text-gray-800 dark:text-white/90">
                                    @if ($dateValue)
                                        <div class="leading-tight text-xs">
                                            <div>{{ $dateValue->format('d/m/y') }}</div>
                                            <div class="text-gray-600 dark:text-gray-400">{{ $dateValue->format('H:i') }}</div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-sm text-center text-gray-600 dark:text-gray-400 text-xs overflow-hidden">
                                    <span class="truncate block" title="{{ $m['origin'] ?? '-' }}">{{ $m['origin'] ?? '-' }}</span>
                                </td>
                                <td class="px-1.5 py-2 text-center overflow-hidden">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white truncate max-w-full" style="{{ $situationStyle }}" title="{{ $situationLabel }}">
                                        {{ $situationLabel }}
                                    </span>
                                </td>
                                <td class="px-1.5 py-2 text-center">
                                    @if ($operationUrl && $operationLabel)
                                        <a href="{{ $operationUrl }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white transition hover:opacity-90" style="background-color: #E91E63;" title="{{ $operationLabel }}" aria-label="{{ $operationLabel }}">
                                            <i class="{{ $operationIcon }} text-base"></i>
                                        </a>
                                    @else
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white" style="background-color: #E91E63;">
                                            <i class="{{ $operationIcon }} text-base"></i>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ (int) $rowId }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40">
                                <td colspan="11" class="px-6 py-4">
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 w-full">
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Unidad</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $m['unit'] ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $m['currency'] ?? 'PEN' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Número</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $m['number'] ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Origen</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $m['origin'] ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Situación</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $situationLabel }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ isset($m['total']) && $m['total'] != 0 ? 'S/ ' . number_format($m['total'], 2) : '-' }}</p>
                                        </div>
                                        @if ($operationUrl && $operationLabel)
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 sm:col-span-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Acción</p>
                                            <p class="mt-0.5">
                                                <a href="{{ $operationUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                                    <i class="{{ $operationIcon }}"></i>
                                                    {{ $operationLabel }}
                                                </a>
                                            </p>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-12 text-center">
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
        const excelButton = document.getElementById('kardex-excel-button');

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
                    ].map((value) => `"${String(value).replace(/"/g, '""')}"`);

                    csvRows.push(values.join(','));
                });

                const blob = new Blob(["\uFEFF" + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `kardex_${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            });
        }
    })();
</script>

@endsection
