@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Registro de Ventas - {{ $branch->name }}</h1>

    <form method="GET" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-4">
        <input type="month" name="month" value="{{ $month }}" class="rounded border px-3 py-2">
        <select name="tab" class="rounded border px-3 py-2">
            <option value="natural" @selected($tab === 'natural')>Natural</option>
            <option value="corporativo" @selected($tab === 'corporativo')>Corporativo</option>
        </select>
        <button class="rounded bg-slate-700 px-3 py-2 text-white">Filtrar</button>
        <a class="rounded bg-emerald-700 px-3 py-2 text-white text-center" href="{{ route('workshop.reports.export.sales', ['month' => $month, 'customer_type' => $tab]) }}">Exportar Excel</a>
    </form>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
        <div class="rounded border p-3"><strong>Subtotal:</strong> {{ number_format($subtotal, 2) }}</div>
        <div class="rounded border p-3"><strong>IGV:</strong> {{ number_format($tax, 2) }}</div>
        <div class="rounded border p-3"><strong>Total:</strong> {{ number_format($total, 2) }}</div>
    </div>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Documento</th>
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-left">Tipo</th>
                    <th class="p-2 text-left">Subtotal</th>
                    <th class="p-2 text-left">IGV</th>
                    <th class="p-2 text-left">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr class="border-t">
                        <td class="p-2">{{ optional($sale->movement?->moved_at)->format('Y-m-d H:i') }}</td>
                        <td class="p-2">{{ $sale->movement?->number }}</td>
                        <td class="p-2">{{ $sale->movement?->person_name }}</td>
                        <td class="p-2">{{ $sale->movement?->person?->person_type }}</td>
                        <td class="p-2">{{ number_format((float)$sale->subtotal, 2) }}</td>
                        <td class="p-2">{{ number_format((float)$sale->tax, 2) }}</td>
                        <td class="p-2">{{ number_format((float)$sale->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-2 text-gray-500">Sin ventas para el filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sales->links() }}
</div>
@endsection

