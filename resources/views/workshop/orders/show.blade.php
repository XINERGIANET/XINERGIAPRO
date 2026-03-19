@extends('layouts.app')

@section('content')
@php
    $statusLabels = [
        'draft' => 'Borrador',
        'diagnosis' => 'Diagnóstico',
        'awaiting_approval' => 'Esperando aprobación',
        'approved' => 'Aprobado',
        'in_progress' => 'En reparación',
        'finished' => 'Terminado',
        'delivered' => 'Entregado',
        'cancelled' => 'Anulado',
        'pending' => 'Pendiente',
        'partial' => 'Parcial',
        'paid' => 'Pagado',
        'rejected' => 'Rechazado',
    ];
    $translateStatus = function ($value) use ($statusLabels) {
        $key = strtolower(trim((string) $value));
        if ($key === '') {
            return '-';
        }
        return $statusLabels[$key] ?? str_replace('_', ' ', $key);
    };
@endphp
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Detalle Orden de Servicio" />

    <x-common.component-card title="OS {{ $order->movement?->number }}" desc="Estado actual: {{ $translateStatus($order->status) }}">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.order', $order) }}" target="_blank">PDF OS</a>

           
        </div>

        <div class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
            <div><p class="text-xs text-gray-500">Cliente</p><p class="font-semibold text-gray-900">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</p></div>
            <div><p class="text-xs text-gray-500">Vehiculo</p><p class="font-semibold text-gray-900">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</p></div>
            <div><p class="text-xs text-gray-500">KM Ingreso / Salida</p><p class="font-semibold text-gray-900">{{ $order->mileage_in ?: '-' }} / {{ $order->mileage_out ?: '-' }}</p></div>
            <div><p class="text-xs text-gray-500">Aprobación / Pago</p><p class="font-semibold text-gray-900">{{ $translateStatus($order->approval_status ?? 'pending') }} / {{ $translateStatus($order->payment_status ?? 'pending') }}</p></div>
            <div><p class="text-xs text-gray-500">Total</p><p class="font-semibold text-gray-900">S/ {{ number_format((float) $order->total, 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Pagado</p><p class="font-semibold text-gray-900">S/ {{ number_format((float) $order->paid_total, 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Deuda</p><p class="font-semibold text-gray-900">S/ {{ number_format(max(0, (float)$order->total - (float)$order->paid_total), 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Tecnicos</p><p class="font-semibold text-gray-900">{{ $order->technicians->map(fn($row) => trim(($row->technician?->first_name ?? '').' '.($row->technician?->last_name ?? '')))->filter()->join(', ') ?: 'Sin asignar' }}</p></div>
        </div>

            <!-- Simplified OS Details View (Premium) -->
            <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
                <div class="table-responsive">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-[#1e293b]">
                                <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest text-white border-r border-slate-700/50">Servicio</th>
                                <th class="px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-white border-r border-slate-700/50">Cantidad</th>
                                <th class="px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-white border-r border-slate-700/50">Precio</th>
                                <th class="px-6 py-4 text-right text-[11px] font-black uppercase tracking-widest text-white border-r border-slate-700/50">Subtotal</th>
                                <th class="px-6 py-4 text-center text-[11px] font-black uppercase tracking-widest text-white">Quitar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            {{-- Accepted Services --}}
                            @foreach($order->details->whereIn('line_type', ['SERVICE', 'LABOR']) as $detail)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-5">
                                        <p class="text-sm font-semibold text-slate-600">{{ $detail->description }}</p>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex justify-center">
                                            <form method="POST" action="{{ route('workshop.orders.details.update', [$order, $detail]) }}" class="flex items-center">
                                                @csrf @method('PUT')
                                                <input type="number" step="1" min="1" name="qty" value="{{ (int)$detail->qty }}" 
                                                    class="h-14 w-40 rounded-xl border border-blue-200 bg-white px-4 text-center text-base font-bold text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                                                    onchange="this.form.submit()">
                                                <input type="hidden" name="unit_price" value="{{ $detail->unit_price }}">
                                                <input type="hidden" name="description" value="{{ $detail->description }}">
                                                <input type="hidden" name="tax_rate_id" value="{{ $detail->tax_rate_id }}">
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex justify-center">
                                            <form method="POST" action="{{ route('workshop.orders.details.update', [$order, $detail]) }}" class="flex items-center">
                                                @csrf @method('PUT')
                                                <input type="number" step="0.01" min="0" name="unit_price" value="{{ (float)$detail->unit_price }}" 
                                                    class="h-14 w-40 rounded-xl border border-blue-200 bg-white px-4 text-center text-base font-bold text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                                                    onchange="this.form.submit()">
                                                <input type="hidden" name="qty" value="{{ $detail->qty }}">
                                                <input type="hidden" name="description" value="{{ $detail->description }}">
                                                <input type="hidden" name="tax_rate_id" value="{{ $detail->tax_rate_id }}">
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <p class="text-base font-black text-slate-800">S/ {{ number_format((float) $detail->total, 2) }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <form method="POST" action="{{ route('workshop.orders.details.destroy', [$order, $detail]) }}" onsubmit="return confirm('¿Eliminar este servicio?')">
                                            @csrf @method('DELETE')
                                            <button class="w-10 h-10 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all">
                                                <i class="ri-close-line text-xl"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Rejected/Deleted Services --}}
                            @foreach($order->deletedDetails->whereIn('line_type', ['SERVICE', 'LABOR']) as $deleted)
                                <tr class="bg-red-50/10">
                                    <td class="px-6 py-5 opacity-60">
                                        <p class="text-sm font-semibold text-red-900/50 line-through decoration-red-400 decoration-2 italic">{{ $deleted->description }}</p>
                                        <span class="text-[10px] font-black uppercase text-red-500 tracking-widest mt-1 block">Rechazado</span>
                                    </td>
                                    <td class="px-6 py-5 text-center opacity-40">
                                        <div class="h-14 w-40 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-base font-bold text-gray-400">
                                            {{ (int)$deleted->qty }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center opacity-40">
                                        <div class="h-14 w-40 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-base font-bold text-gray-400">
                                            {{ (float)$deleted->unit_price }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right opacity-40">
                                        <p class="text-base font-black text-gray-400 line-through">S/ {{ number_format((float) $deleted->total, 2) }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <i class="ri-error-warning-fill text-red-300 text-xl" title="Item retirado de la orden"></i>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Hide Other Sections (As requested: 'nada mas') -->
            <div x-data="{ showAdvanced: false }" class="mt-8 border-t border-gray-100 pt-6">
                <button @click="showAdvanced = !showAdvanced" class="text-[11px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-colors flex items-center gap-2">
                    <i :class="showAdvanced ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                    <span x-text="showAdvanced ? 'Ocultar Opciones Avanzadas' : 'Ver Opciones Avanzadas (Caja, Tecnicos, etc)'"></span>
                </button>
                
                <div x-show="showAdvanced" x-transition class="mt-6 space-y-6">
                    <!-- Advanced sections would go here if needed to preserve functionality -->
                    <div class="p-4 bg-slate-50 rounded-xl text-xs text-slate-500 italic">
                        Esta sección se ha simplificado para priorizar la vista de servicios. Utilice los módulos de facturación y taller para operaciones avanzadas.
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Bitacora del Vehiculo</h3>
                    <div class="table-responsive rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Fecha</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">KM</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($order->vehicle?->logs ?? collect())->sortByDesc('created_at')->take(20) as $log)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2">{{ $log->log_type }}</td>
                                        <td class="px-3 py-2">{{ $log->mileage }}</td>
                                        <td class="px-3 py-2">{{ $log->notes }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-3 text-gray-500">Sin registros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Historial de Estados</h3>
                    <div class="table-responsive rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Fecha</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">De</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">A</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->statusHistories->sortByDesc('id')->take(30) as $history)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2">{{ $translateStatus($history->from_status) }}</td>
                                        <td class="px-3 py-2">{{ $translateStatus($history->to_status) }}</td>
                                        <td class="px-3 py-2">{{ $history->user?->name ?: ('#'.$history->user_id) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-3 text-gray-500">Sin cambios registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

          
           

           
        </div>
    </x-common.component-card>
</div>
@endsection
