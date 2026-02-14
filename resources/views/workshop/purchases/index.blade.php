@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Compras Taller" />

    <x-common.component-card title="Registro de compras" desc="Consulta compras de taller y exporta registros mensuales.">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.link-button size="md" variant="primary" href="{{ route('warehouse_movements.input') }}" style="background-color:#00A389;color:#fff">
                <i class="ri-add-line"></i><span>Registrar compra</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.reports.export.purchases', ['month' => $month, 'supplier_id' => $supplierId ?: null, 'document_kind' => $documentKind ?: null, 'branch_id' => $scopeBranchId]) }}" style="background-color:#166534;color:#fff">
                <i class="ri-file-excel-2-line"></i><span>Exportar</span>
            </x-ui.link-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <form method="GET" class="grid flex-1 grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-6 dark:border-gray-800 dark:bg-white/[0.02]">
                <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <select name="supplier_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Proveedor</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId === (int)$supplier->id)>
                            {{ trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: ('#'.$supplier->id) }}
                        </option>
                    @endforeach
                </select>
                <select name="document_kind" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Tipo doc</option>
                    @foreach(['FACTURA','BOLETA','RECIBO'] as $kind)
                        <option value="{{ $kind }}" @selected($documentKind === $kind)>{{ $kind }}</option>
                    @endforeach
                </select>
                <select name="branch_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" @disabled(!$isAdmin)>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($scopeBranchId === (int)$branch->id)>
                            {{ $branch->code }} - {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                <button class="h-11 rounded-lg bg-[#244BB3] px-3 text-sm font-medium text-white">Filtrar</button>
                <a href="{{ route('workshop.purchases.index') }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
            </form>
            <a href="{{ route('warehouse_movements.input') }}" class="h-11 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white inline-flex items-center">Registrar compra</a>
            <a class="h-11 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white inline-flex items-center"
               href="{{ route('workshop.reports.export.purchases', ['month' => $month, 'supplier_id' => $supplierId ?: null, 'document_kind' => $documentKind ?: null, 'branch_id' => $scopeBranchId]) }}">Exportar</a>
        </div>

        <div class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Fecha</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Serie-Numero</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Proveedor</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Moneda</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">IGV %</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Subtotal</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">IGV</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ optional($record->issued_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $record->document_kind }}</td>
                            <td class="px-4 py-3 text-sm">{{ ($record->series ? $record->series.'-' : '') . $record->document_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $record->supplier?->first_name }} {{ $record->supplier?->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $record->currency }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$record->igv_rate, 4) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$record->subtotal, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$record->igv, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$record->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-4 text-sm text-gray-500">Sin compras para el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-common.component-card>
</div>
@endsection
