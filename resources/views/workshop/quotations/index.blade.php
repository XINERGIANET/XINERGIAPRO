@extends('layouts.app')

@section('content')
    <div x-data="{ 
        showModal: false, 
        selectedQuotation: null,
        totalQuotation: 0,
        calculateTotal() {
            if (!this.selectedQuotation) return;
            this.totalQuotation = this.selectedQuotation.details.reduce((acc, d) => acc + (parseFloat(d.qty) * parseFloat(d.unit_price)), 0);
        },
        openModal(quotation) {
            this.selectedQuotation = JSON.parse(JSON.stringify(quotation)); // Deep copy to avoid mutating original list until save
            this.calculateTotal();
            this.showModal = true;
        }
    }" x-init="$watch('showModal', value => {
        if (value) {
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    })">
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
                                            <button type="button" 
                                                @click="openModal({{ json_encode([
                                                    'id' => $quotation->id,
                                                    'number' => $quotation->movement?->number ?? sprintf('%08d', $quotation->id),
                                                    'vehicle' => $quotation->vehicle?->brand . ' ' . $quotation->vehicle?->model,
                                                    'status' => $quotation->status,
                                                    'status_label' => $quotation->status === 'approved' ? 'Aprobada' : 'Esperando aprobación',
                                                    'approve_url' => route('workshop.orders.approve', $quotation->id),
                                                    'details' => $quotation->details->whereIn('line_type', ['SERVICE', 'LABOR'])->map(fn($d) => [
                                                        'id' => $d->id,
                                                        'description' => $d->description,
                                                        'qty' => (float)$d->qty,
                                                        'unit_price' => (float)$d->unit_price,
                                                        'total' => (float)$d->total,
                                                    ])->values()->toArray(),
                                                    'deleted_details' => $quotation->deletedDetails->whereIn('line_type', ['SERVICE', 'LABOR'])->map(fn($d) => [
                                                        'description' => $d->description,
                                                        'qty' => (float)$d->qty,
                                                        'unit_price' => (float)$d->unit_price,
                                                        'total' => (float)$d->total,
                                                    ])->values()->toArray()
                                                ]) }})"
                                               class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/20 hover:scale-105 transition-all cursor-pointer" 
                                               title="Ver Detalle OS">
                                                <i class="ri-eye-line text-lg"></i>
                                            </button>
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

        <!-- OS Approval Modal (Refined & Compact) -->
        <template x-teleport="body">
            <div x-show="showModal" class="relative z-[999999]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <!-- Backdrop with strong blur and darkness -->
                <div x-show="showModal" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="showModal = false"
                     class="fixed inset-0 bg-slate-950/60 backdrop-blur-[12px] transition-opacity cursor-pointer" 
                     style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);" aria-hidden="true"></div>

                <!-- Scrollable container for the modal content -->
                <div class="fixed inset-0 overflow-hidden flex items-center justify-center pointer-events-none">
                    <div class="flex min-h-0 w-full items-center justify-center p-4 text-center sm:p-0 pointer-events-auto">
                        <div x-show="showModal"
                             x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-90"
                             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                             x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-90"
                             class="relative flex flex-col w-full bg-white rounded-[2rem] text-left shadow-[0_40px_100px_rgba(0,0,0,0.5)] transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl border border-slate-200 overflow-hidden max-h-[90vh]">
                    
                        <form :action="selectedQuotation?.approve_url" method="POST" class="flex flex-col min-h-0">
                            @csrf
                            <!-- Premium Header Section (Fixed) -->
                            <div class="px-8 py-6 flex items-center justify-between border-b border-slate-50 shrink-0">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100/50">
                                        <i class="ri-file-list-3-line text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800 tracking-tight leading-none" id="modal-title">Aprobación de Cotización</h3>
                                        <p class="text-[10px] font-medium text-slate-400 mt-1.5 flex items-center gap-1.5 uppercase tracking-[0.1em]">
                                            ORDEN <span x-text="selectedQuotation?.number" class="font-black text-slate-600"></span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="showModal = false" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all flex items-center justify-center border border-slate-100">
                                    <i class="ri-close-line text-lg"></i>
                                </button>
                            </div>

                            <!-- Scrollable Content Area -->
                            <div class="px-8 py-6 overflow-y-auto custom-scrollbar flex-1 min-h-0">
                                <!-- Table Container -->
                                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm mb-6">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="bg-slate-50/50 border-b border-slate-100">
                                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Servicio</th>
                                                    <th class="px-4 py-3 text-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Cant</th>
                                                    <th class="px-4 py-3 text-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Precio</th>
                                                    <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Subtotal</th>
                                                    <th class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100/60">
                                                <!-- Active Items -->
                                                <template x-for="(detail, index) in selectedQuotation?.details" :key="detail.id">
                                                    <tr class="group hover:bg-slate-50/50 transition-colors">
                                                        <td class="px-6 py-3.5">
                                                            <p class="text-sm font-bold text-slate-700 leading-tight" x-text="detail.description"></p>
                                                            <input type="hidden" :name="`details[${index}][id]`" :value="detail.id">
                                                            <input type="hidden" :name="`details[${index}][description]`" :value="detail.description">
                                                        </td>
                                                        <td class="px-4 py-3.5 text-center">
                                                            <input type="number" step="1" min="1" :name="`details[${index}][qty]`" 
                                                                x-model="detail.qty" @input="calculateTotal()"
                                                                class="h-8 w-14 rounded-lg border border-slate-200 bg-slate-50/50 px-2 text-center text-[11px] font-black text-slate-700 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/5 transition-all outline-none">
                                                        </td>
                                                        <td class="px-4 py-3.5 text-center">
                                                            <input type="number" step="0.01" min="0" :name="`details[${index}][unit_price]`" 
                                                                x-model="detail.unit_price" @input="calculateTotal()"
                                                                class="h-8 w-20 rounded-lg border border-slate-200 bg-slate-50/50 px-2 text-center text-[11px] font-black text-slate-700 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/5 transition-all outline-none">
                                                        </td>
                                                        <td class="px-6 py-3.5 text-right">
                                                            <span class="text-sm font-black text-slate-800" x-text="'S/ ' + (detail.qty * detail.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                        </td>
                                                        <td class="px-6 py-3.5 text-center">
                                                            <template x-if="selectedQuotation?.status === 'awaiting_approval'">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 text-[9px] font-black uppercase tracking-widest">
                                                                    Esperando
                                                                </span>
                                                            </template>
                                                            <template x-if="selectedQuotation?.status !== 'awaiting_approval'">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 text-[9px] font-black uppercase tracking-widest">
                                                                    Aprobado
                                                                </span>
                                                            </template>
                                                        </td>
                                                    </tr>
                                                </template>
    
                                                <!-- Rejected History -->
                                                <template x-for="deleted in selectedQuotation?.deleted_details" :key="'deleted-' + Math.random()">
                                                    <tr class="bg-red-50/5 opacity-60 backdrop-blur-[2px]">
                                                        <td class="px-6 py-3.5">
                                                            <p class="text-[12px] font-semibold text-red-900/40 line-through decoration-red-200/50 italic" x-text="deleted.description"></p>
                                                        </td>
                                                        <td class="px-4 py-3.5 text-center text-[10px] italic text-slate-400 font-medium" x-text="deleted.qty"></td>
                                                        <td class="px-4 py-3.5 text-center text-[10px] italic text-slate-400 font-medium" x-text="'S/ ' + deleted.unit_price.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                                        <td class="px-6 py-3.5 text-right text-[10px] opacity-40 italic line-through font-bold text-slate-900" x-text="'S/ ' + deleted.total.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                                        <td class="px-6 py-3.5 text-center">
                                                            <span class="px-2.5 py-1 rounded-lg bg-red-50 text-red-400 border border-red-100 text-[8px] font-black uppercase tracking-widest">
                                                                Rechazado
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
    
                                <!-- Integrated Footer Area -->
                                <div class="flex flex-col md:flex-row gap-5 items-stretch mb-2">
                                    <div class="flex-grow">
                                        <textarea name="notes" placeholder="Comentarios..." class="w-full h-full min-h-[80px] p-4 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-medium text-slate-700 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 transition-all outline-none resize-none shadow-sm"></textarea>
                                    </div>
                                    <div class="w-full md:w-56 p-4 rounded-xl bg-blue-600 shadow-lg shadow-blue-600/20 flex flex-col justify-center text-center relative overflow-hidden group">
                                        <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                                        <p class="text-[8px] font-black text-white/50 uppercase tracking-[0.2em] mb-1">TOTAL ESTIMADO</p>
                                        <h4 class="text-xl font-black text-white tracking-tighter" x-text="'S/ ' + totalQuotation.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons (Fixed) -->
                            <div class="px-8 py-6 flex items-center justify-end gap-3 border-t border-slate-100 bg-white shrink-0">
                                <button type="submit" x-show="selectedQuotation?.status === 'awaiting_approval'" class="h-10 px-8 bg-blue-600 text-white rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-600/20 transition-all flex items-center gap-2">
                                    <i class="ri-checkbox-circle-fill text-lg"></i> Aprobar Cotización
                                </button>
                                <button type="button" x-show="selectedQuotation?.status !== 'awaiting_approval'" @click="showModal = false" class="h-10 px-8 bg-slate-800 text-white rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all flex items-center gap-2">
                                    <i class="ri-eye-line text-lg"></i> Cerrar Vista
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>

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
