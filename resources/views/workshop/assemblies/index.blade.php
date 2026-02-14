@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">Armado / Ensamblaje</h1>
        <form method="GET" action="{{ route('workshop.assemblies.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="month" name="month" value="{{ $month }}" class="rounded border px-3 py-2">
            <input name="brand_company" value="{{ $brandCompany }}" placeholder="Empresa/Marca" class="rounded border px-3 py-2">
            <input name="vehicle_type" value="{{ $vehicleType }}" placeholder="Tipo vehiculo" class="rounded border px-3 py-2">
            <button class="rounded bg-slate-700 px-3 py-2 text-white">Filtrar</button>
            <a href="{{ route('workshop.assemblies.export', ['month' => $month]) }}" class="rounded bg-emerald-700 px-3 py-2 text-white">Exportar CSV</a>
        </form>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('workshop.assemblies.store') }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Nuevo registro de armado</h2>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <input name="brand_company" class="rounded border px-3 py-2" placeholder="Empresa/Marca (GP MOTOS)" required>
                <input name="vehicle_type" class="rounded border px-3 py-2" placeholder="Tipo vehiculo" required>
                <input type="number" min="1" name="quantity" class="rounded border px-3 py-2" value="1" required>
                <input type="number" min="0" step="0.000001" name="unit_cost" class="rounded border px-3 py-2" placeholder="Costo unitario (opcional)">
                <input type="date" name="assembled_at" class="rounded border px-3 py-2 md:col-span-2" value="{{ now()->toDateString() }}" required>
                <textarea name="notes" rows="2" class="rounded border px-3 py-2 md:col-span-2" placeholder="Observaciones"></textarea>
            </div>
            <button class="mt-2 rounded bg-blue-700 px-3 py-2 text-white">Guardar armado</button>
        </form>

        <div class="rounded border p-4">
            <h2 class="mb-2 font-semibold">Tabla de costos por tipo</h2>
            <div class="overflow-x-auto rounded border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Empresa/Marca</th>
                            <th class="p-2 text-left">Tipo</th>
                            <th class="p-2 text-left">Costo Unitario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costTable as $cost)
                            <tr class="border-t">
                                <td class="p-2">{{ $cost->brand_company }}</td>
                                <td class="p-2">{{ $cost->vehicle_type }}</td>
                                <td class="p-2">{{ number_format((float) $cost->unit_cost, 6) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-2 text-gray-500">Sin costos configurados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Resumen mensual por tipo</h2>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Empresa/Marca</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Cantidad</th>
                        <th class="p-2 text-left">Costo Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryByType as $row)
                        <tr class="border-t">
                            <td class="p-2">{{ $row->brand_company }}</td>
                            <td class="p-2">{{ $row->vehicle_type }}</td>
                            <td class="p-2">{{ (int) $row->total_qty }}</td>
                            <td class="p-2">{{ number_format((float) $row->total_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-2 text-gray-500">Sin registros en el mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Detalle de armados</h2>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Empresa/Marca</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Cantidad</th>
                        <th class="p-2 text-left">Costo U.</th>
                        <th class="p-2 text-left">Costo T.</th>
                        <th class="p-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assemblies as $assembly)
                        <tr class="border-t">
                            <td class="p-2">{{ optional($assembly->assembled_at)->format('Y-m-d') }}</td>
                            <td class="p-2">{{ $assembly->brand_company }}</td>
                            <td class="p-2">{{ $assembly->vehicle_type }}</td>
                            <td class="p-2">{{ $assembly->quantity }}</td>
                            <td class="p-2">{{ number_format((float) $assembly->unit_cost, 6) }}</td>
                            <td class="p-2">{{ number_format((float) $assembly->total_cost, 2) }}</td>
                            <td class="p-2">
                                <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('Eliminar registro de armado?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded bg-red-700 px-2 py-1 text-white">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-2 text-gray-500">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            {{ $assemblies->links() }}
        </div>
    </div>
</div>
@endsection

