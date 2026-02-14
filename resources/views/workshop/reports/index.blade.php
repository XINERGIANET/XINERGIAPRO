@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Taller - Reportes</h1>

    <form method="GET" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-4">
        <select name="status" class="rounded border px-3 py-2">
            <option value="">Todos</option>
            @foreach(['draft','diagnosis','awaiting_approval','approved','in_progress','finished','delivered','cancelled'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded border px-3 py-2">
        <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded border px-3 py-2">
        <button class="rounded bg-blue-600 px-3 py-2 text-white">Filtrar</button>
    </form>

    <div class="grid grid-cols-1 gap-3 rounded border p-4 md:grid-cols-2">
        <div>
            <h2 class="mb-2 font-semibold">Exportaciones contables (CSV)</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                <a class="rounded bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.purchases', ['month' => now()->format('Y-m')]) }}">Compras mes</a>
                <a class="rounded bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.sales', ['month' => now()->format('Y-m'), 'customer_type' => 'natural']) }}">Ventas Natural mes</a>
                <a class="rounded bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.sales', ['month' => now()->format('Y-m'), 'customer_type' => 'corporativo']) }}">Ventas Corporativo mes</a>
                <a class="rounded bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.orders', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}">OS filtradas</a>
                <a class="rounded bg-emerald-700 px-3 py-2 text-white" href="{{ route('workshop.reports.export.productivity', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}">Productividad técnicos</a>
            </div>
        </div>
        <div>
            <h2 class="mb-2 font-semibold">Exportar kardex por producto</h2>
            <form method="GET" action="{{ route('workshop.reports.export.kardex') }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <select name="product_id" class="rounded border px-3 py-2 md:col-span-3" required>
                    <option value="">Producto</option>
                    @foreach($kardexProducts as $product)
                        <option value="{{ $product->id }}">{{ $product->description }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded border px-3 py-2">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded border px-3 py-2">
                <button class="rounded bg-slate-800 px-3 py-2 text-white">Descargar</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
        <div class="rounded border p-3"><strong>OS:</strong> {{ $orders->total() }}</div>
        <div class="rounded border p-3"><strong>Subtotal:</strong> {{ number_format((float) $totals['subtotal'], 2) }}</div>
        <div class="rounded border p-3"><strong>IGV:</strong> {{ number_format((float) $totals['tax'], 2) }}</div>
        <div class="rounded border p-3"><strong>Total:</strong> {{ number_format((float) $totals['total'], 2) }}</div>
        <div class="rounded border p-3"><strong>Pagado:</strong> {{ number_format((float) $totals['paid_total'], 2) }}</div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">OS por estado</h2>
            @forelse($byStatus as $state => $qty)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $state }}</span>
                    <strong>{{ $qty }}</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin datos.</p>
            @endforelse
        </div>

        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">Servicios frecuentes</h2>
            @forelse($topServices as $service)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $service->description }}</span>
                    <strong>{{ $service->qty }}</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin datos.</p>
            @endforelse
        </div>

        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">Repuestos usados</h2>
            @forelse($topParts as $part)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $part->description }}</span>
                    <strong>{{ number_format((float) $part->qty, 2) }}</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin datos.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded border p-3">
        <h2 class="mb-2 font-semibold">Ingresos 30 días</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Total OS</th>
                        <th class="p-2 text-left">Pagado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomeByDay as $row)
                        <tr class="border-t">
                            <td class="p-2">{{ $row->day }}</td>
                            <td class="p-2">{{ number_format((float) $row->total, 2) }}</td>
                            <td class="p-2">{{ number_format((float) $row->paid, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-2 text-gray-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">Clientes con deuda</h2>
            @forelse($clientsWithDebt as $row)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $row->client?->first_name }} {{ $row->client?->last_name }}</span>
                    <strong>{{ number_format((float) $row->debt, 2) }}</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin deudas pendientes.</p>
            @endforelse
        </div>

        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">Productividad técnico</h2>
            @forelse($productivityByTechnician as $row)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $row->technician }}</span>
                    <span>{{ $row->lines_done }} líneas / {{ number_format((float) $row->billed_total, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin datos.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">OS por técnico</h2>
            @forelse($ordersByTechnician as $row)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $row->technician }}</span>
                    <strong>{{ $row->orders }} OS</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin datos.</p>
            @endforelse
        </div>

        <div class="rounded border p-3">
            <h2 class="mb-2 font-semibold">Stock mínimo</h2>
            @forelse($stockMinimum as $row)
                <div class="flex justify-between border-b py-1 text-sm">
                    <span>{{ $row->description }}</span>
                    <strong>{{ number_format((float)$row->stock,2) }} / min {{ number_format((float)$row->stock_minimum,2) }}</strong>
                </div>
            @empty
                <p class="text-sm text-gray-600">Sin alertas.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded border p-3">
        <h2 class="mb-2 font-semibold">Margen por OS (venta - costo repuestos)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">OS</th>
                        <th class="p-2 text-left">Total venta</th>
                        <th class="p-2 text-left">Costo repuestos</th>
                        <th class="p-2 text-left">Margen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($marginByOrder as $row)
                        <tr class="border-t">
                            <td class="p-2">{{ $row['order']->movement?->number }}</td>
                            <td class="p-2">{{ number_format((float)$row['order']->total, 2) }}</td>
                            <td class="p-2">{{ number_format((float)$row['part_cost'], 2) }}</td>
                            <td class="p-2">{{ number_format((float)$row['margin'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-2 text-gray-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded border p-3">
        <h2 class="mb-2 font-semibold">Productividad: horas estimadas vs reales</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">OS</th>
                        <th class="p-2 text-left">Estimado (min)</th>
                        <th class="p-2 text-left">Real (min)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hoursReport as $row)
                        <tr class="border-t">
                            <td class="p-2">{{ $row['order']->movement?->number }}</td>
                            <td class="p-2">{{ $row['estimated_minutes'] }}</td>
                            <td class="p-2">{{ $row['real_minutes'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-2 text-gray-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">OS</th>
                    <th class="p-2 text-left">Ingreso</th>
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-left">Vehiculo</th>
                    <th class="p-2 text-left">Estado</th>
                    <th class="p-2 text-left">Total</th>
                    <th class="p-2 text-left">Pagado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr class="border-t">
                        <td class="p-2">{{ $order->movement?->number }}</td>
                        <td class="p-2">{{ $order->intake_date?->format('Y-m-d H:i') }}</td>
                        <td class="p-2">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</td>
                        <td class="p-2">{{ $order->vehicle?->plate }}</td>
                        <td class="p-2">{{ $order->status }}</td>
                        <td class="p-2">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="p-2">{{ number_format((float) $order->paid_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $orders->links() }}
</div>
@endsection

