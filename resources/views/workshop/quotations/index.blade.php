@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Gestión de Cotizaciones" />

    <x-common.component-card title="Listado de Cotizaciones" desc="Seguimiento y aprobación de cotizaciones de taller.">
        <div class="mb-5 px-1">
            <form action="{{ route('admin.sales.quotations.index') }}" method="GET" class="flex flex-row items-center gap-2">
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                
                <!-- Per Page Select -->
                <div class="w-36 shrink-0 relative">
                    <select name="per_page" class="w-full h-11 pl-4 pr-10 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none focus:border-[#465fff] transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                        <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 / página</option>
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 / página</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 / página</option>
                    </select>
                    <i class="ri-arrow-down-s-line absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>

                <!-- Search Input -->
                <div class="relative flex-1 group">
                    <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-[#465fff] transition-colors"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar vista..." 
                        class="w-full h-11 pl-11 pr-4 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-[#465fff] transition-all placeholder:text-slate-300">
                </div>

                <!-- Client Filter -->
                <div class="w-64 shrink-0 relative">
                    <i class="ri-user-3-line absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <select name="client_id" class="w-full h-11 pl-11 pr-10 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-[#465fff] transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                        <option value="">Todos los clientes</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ (int)$clientId === (int)$client->id ? 'selected' : '' }}>
                                {{ $client->first_name }} {{ $client->last_name }}
                            </option>
                        @endforeach
                    </select>
                    <i class="ri-arrow-down-s-line absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit" class="h-11 bg-[#1e293b] text-white px-6 rounded-xl text-xs font-black hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                        <i class="ri-search-line"></i> Buscar
                    </button>
                    <a href="{{ route('admin.sales.quotations.index', ['view_id' => request('view_id')]) }}" 
                       class="h-11 bg-white border border-slate-200 text-slate-500 px-6 rounded-xl text-xs font-black hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                        <i class="ri-refresh-line"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 shadow-sm bg-white">
            <div class="overflow-x-auto overflow-y-hidden custom-scrollbar">
                <table class="w-full text-left min-w-[1000px] border-collapse">
                    <thead>
                        <tr class="bg-[#1e293b] text-white text-[11px] uppercase font-black tracking-[0.15em]">
                            <th class="px-6 py-5 rounded-tl-xl">OS / Número</th>
                            <th class="px-6 py-5">Vehículo</th>
                            <th class="px-6 py-5">Cliente</th>
                            <th class="px-6 py-5 text-center">Estado / Aprobación</th>
                            <th class="px-6 py-5 text-center">Rechazos</th>
                            <th class="px-6 py-5">Total</th>
                            <th class="px-6 py-5 text-right rounded-tr-xl">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 italic-rows">
                        @forelse($quotations as $quotation)
                            <tr class="group hover:bg-slate-50/80 transition-all duration-200">
                                <td class="px-6 py-5">
                                    <h5 class="font-black text-slate-800 tracking-tight leading-none mb-1.5">{{ $quotation->movement?->number ?? sprintf("%08d", $quotation->id) }}</h5>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $quotation->intake_date?->format('d/m/Y H:i') }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-[13px] font-black text-slate-700 uppercase leading-none mb-1.5">{{ $quotation->vehicle?->plate ?? '-' }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $quotation->vehicle?->brand }} {{ $quotation->vehicle?->model }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-[13px] font-black text-slate-800 uppercase leading-none">{{ $quotation->client?->first_name }} {{ $quotation->client?->last_name }}</p>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border
                                        {{ $quotation->status === 'approved' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : ($quotation->status === 'diagnosis' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-orange-50 text-orange-600 border-orange-100') }}">
                                        @switch($quotation->status)
                                            @case('awaiting_approval') <i class="ri-time-line mr-1"></i> Esperando aprobación @break
                                            @case('approved') <i class="ri-check-double-line mr-1"></i> Aprobada @break
                                            @case('diagnosis') <i class="ri-microscope-line mr-1"></i> En diagnóstico @break
                                            @default {{ $quotation->status }}
                                        @endswitch
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    @if($quotation->deletedDetails && $quotation->deletedDetails->count() > 0)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-red-50 text-red-600 border border-red-100 text-[10px] font-black animate-pulse" title="Existen items eliminados/rechazados">
                                            <i class="ri-error-warning-line mr-1"></i> {{ $quotation->deletedDetails->count() }} RECHAZOS
                                        </span>
                                    @else
                                        <span class="text-slate-300 text-[10px] font-bold">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-sm font-black text-slate-900 leading-none">S/ {{ number_format($quotation->total, 2) }}</p>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('workshop.orders.show', $quotation->id) }}" 
                                           class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/20 hover:scale-105 transition-all" 
                                           title="Ver Detalle OS">
                                            <i class="ri-eye-line text-lg"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-20 text-center">
                                    <div class="flex flex-col items-center justify-center opacity-30">
                                        <i class="ri-inbox-line text-5xl mb-4 text-slate-300"></i>
                                        <p class="text-sm font-bold italic text-slate-500">No se encontraron cotizaciones registradas.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Premium Pagination Footer -->
        <div class="mt-8 flex flex-col md:flex-row items-center justify-between gap-6 pb-6 px-1">
            <!-- Showing Results Info -->
            <div class="text-[#64748B] text-[13px]">
                @if($quotations->total() > 0)
                    Mostrando <span class="font-bold text-[#334155]">{{ $quotations->firstItem() }}</span> - 
                    <span class="font-bold text-[#334155]">{{ $quotations->lastItem() }}</span> de 
                    <span class="font-bold text-[#334155]">{{ $quotations->total() }}</span>
                @else
                    Mostrando <span class="font-bold text-[#334155]">0</span>
                @endif
            </div>

            <!-- Pagination Buttons -->
            <div class="flex items-center">
                {{ $quotations->links('vendor.pagination.premium') }}
            </div>
        </div>
    </x-common.component-card>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
@endsection
