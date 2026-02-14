@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Compras Taller</h1>
        <a href="{{ route('warehouse_movements.input') }}" class="rounded bg-blue-700 px-3 py-2 text-white">Registrar compra</a>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-6">
        <input type="month" name="month" value="{{ $month }}" class="rounded border px-3 py-2">
        <select name="supplier_id" class="rounded border px-3 py-2">
            <option value="">Proveedor</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected($supplierId === (int)$supplier->id)>
                    {{ trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: ('#'.$supplier->id) }}
                </option>
            @endforeach
        </select>
        <select name="document_kind" class="rounded border px-3 py-2">
            <option value="">Tipo doc</option>
            @foreach(['FACTURA','BOLETA','RECIBO'] as $kind)
                <option value="{{ $kind }}" @selected($documentKind === $kind)>{{ $kind }}</option>
            @endforeach
        </select>
        <select name="branch_id" class="rounded border px-3 py-2" @disabled(!$isAdmin)>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected($scopeBranchId === (int)$branch->id)>
                    {{ $branch->code }} - {{ $branch->name }}
                </option>
            @endforeach
        </select>
        <button class="rounded bg-slate-700 px-3 py-2 text-white">Filtrar</button>
        <a
            class="rounded bg-emerald-700 px-3 py-2 text-white text-center"
            href="{{ route('workshop.reports.export.purchases', ['month' => $month, 'supplier_id' => $supplierId ?: null, 'document_kind' => $documentKind ?: null, 'branch_id' => $scopeBranchId]) }}"
        >Exportar</a>
    </form>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Tipo</th>
                    <th class="p-2 text-left">Serie-Número</th>
                    <th class="p-2 text-left">Proveedor</th>
                    <th class="p-2 text-left">Moneda</th>
                    <th class="p-2 text-left">IGV %</th>
                    <th class="p-2 text-left">Subtotal</th>
                    <th class="p-2 text-left">IGV</th>
                    <th class="p-2 text-left">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr class="border-t">
                        <td class="p-2">{{ optional($record->issued_at)->format('Y-m-d') }}</td>
                        <td class="p-2">{{ $record->document_kind }}</td>
                        <td class="p-2">{{ ($record->series ? $record->series.'-' : '') . $record->document_number }}</td>
                        <td class="p-2">{{ $record->supplier?->first_name }} {{ $record->supplier?->last_name }}</td>
                        <td class="p-2">{{ $record->currency }}</td>
                        <td class="p-2">{{ number_format((float)$record->igv_rate, 4) }}</td>
                        <td class="p-2">{{ number_format((float)$record->subtotal, 2) }}</td>
                        <td class="p-2">{{ number_format((float)$record->igv, 2) }}</td>
                        <td class="p-2">{{ number_format((float)$record->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-2 text-gray-500">Sin compras para el filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $records->links() }}
</div>
@endsection

