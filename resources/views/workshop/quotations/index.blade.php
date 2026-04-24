@extends('layouts.app')

@section('content')
    <div x-data="{
        showModal: false,
        selectedQuotation: null,
        totalQuotation: 0,
        showSendModal: false,
        sendQuotation: null,
        sendEmail: '',
        decisionPayload: { decision: 'approved', approval_note: '' },
        overflowSync() {
            if (this.showModal || this.showSendModal) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        },
        calculateTotal() {
            if (!this.selectedQuotation) return;
            this.totalQuotation = this.selectedQuotation.details.reduce((acc, d) => acc + (parseFloat(d.qty) * parseFloat(d.unit_price)), 0);
        },
        openModal(quotation) {
            this.selectedQuotation = JSON.parse(JSON.stringify(quotation));
            this.decisionPayload = {
                decision: (quotation.approval_status === 'rejected') ? 'rejected' : 'approved',
                approval_note: quotation.approval_note || '',
            };
            this.calculateTotal();
            this.showModal = true;
            this.overflowSync();
        },
        openSendModal(q) {
            this.sendQuotation = q;
            this.sendEmail = q.default_email || '';
            this.showSendModal = true;
            this.overflowSync();
        },
        closeSendModal() {
            this.showSendModal = false;
            this.overflowSync();
        },
    }" x-init="
        $watch('showModal', () => $data.overflowSync());
        $watch('showSendModal', () => $data.overflowSync());
    ">
        <x-common.page-breadcrumb pageTitle="Gestión de Cotizaciones" />

        <x-common.component-card title="Listado de Cotizaciones" desc="Seguimiento y aprobación de cotizaciones de taller.">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($showQuotationStats ?? false)
                <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 px-1">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total cotizaciones</p>
                        <p class="mt-1 text-2xl font-black text-slate-800">{{ (int) ($stats['total'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Ganadas / convertidas</p>
                        <p class="mt-1 text-2xl font-black text-emerald-800">{{ (int) ($stats['won'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-red-100 bg-red-50/60 p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-widest text-red-700">No concretadas</p>
                        <p class="mt-1 text-2xl font-black text-red-800">{{ (int) ($stats['lost'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-800">Abiertas</p>
                        <p class="mt-1 text-2xl font-black text-amber-900">{{ (int) ($stats['open'] ?? 0) }}</p>
                    </div>
                </div>
            @endif

            @php
                $colCount = ($showQuotationExtras ?? false) ? 10 : 7;
            @endphp

            <div class="mb-5 px-1">
                <form action="{{ route('admin.sales.quotations.index') }}" method="GET" class="quotation-filters flex flex-row flex-wrap items-center gap-2">
                    <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                    
                    <!-- Per Page Select -->
                    <div class="w-36 shrink-0 relative">
                        <select name="per_page" class="w-full h-11 pl-4 pr-10 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none focus:border-[#465fff] transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 / página</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 / página</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 / página</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="relative flex-1 group">
                        <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-[#465fff] transition-colors"></i>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Número OS, correlativo, placa o cliente…"
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
                    </div>

                    @if ($showQuotationExtras ?? false)
                        <div class="w-44 shrink-0 relative">
                            <i class="ri-filter-3-line absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <select name="quotation_source" class="w-full h-11 pl-11 pr-10 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-[#465fff] transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                                <option value="">Tipo: todas</option>
                                <option value="internal" {{ ($sourceFilter ?? '') === 'internal' ? 'selected' : '' }}>Internas (OS)</option>
                                <option value="external" {{ ($sourceFilter ?? '') === 'external' ? 'selected' : '' }}>Externas</option>
                            </select>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button type="submit" class="h-11 bg-[#1e293b] text-white px-6 rounded-xl text-xs font-black hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                            <i class="ri-search-line"></i> Buscar
                        </button>
                        <a href="{{ route('admin.sales.quotations.index', ['view_id' => request('view_id')]) }}" 
                           class="h-11 bg-white border border-slate-200 text-slate-500 px-6 rounded-xl text-xs font-black hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                            <i class="ri-refresh-line"></i> Limpiar
                        </a>
                        <a href="{{ route('admin.sales.quotations.create-external', array_filter(['view_id' => request('view_id')])) }}"
                           class="h-11 bg-indigo-600 text-white px-6 rounded-xl text-xs font-black hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-indigo-500/20">
                            <i class="ri-file-add-line"></i> Cotización externa
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 shadow-sm bg-white">
                <div class="overflow-x-auto overflow-y-hidden custom-scrollbar">
                    <table class="w-full text-left min-w-[1100px] border-collapse">
                        <thead>
                            <tr class="bg-[#1e293b] text-white text-[11px] uppercase font-black tracking-[0.15em]">
                                <th class="px-6 py-5 rounded-tl-xl">OS / Número</th>
                                @if ($showQuotationExtras ?? false)
                                    <th class="px-6 py-5">Correlativo</th>
                                    <th class="px-6 py-5 text-center">Tipo</th>
                                @endif
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
                                @php
                                    $isExternalQuotation = (($quotation->quotation_source ?? 'internal') === 'external');
                                    $generatedOrder = $quotation->generatedOrder;
                                    $hasVehicle = (bool) $quotation->vehicle_id;
                                    $qTerms = is_array($quotation->quotation_commercial_terms ?? null) ? $quotation->quotation_commercial_terms : [];
                                    $partsPurchaseRecorded = !empty($qTerms['parts_purchase_recorded']);
                                    $partDetails = $quotation->details->filter(fn ($d) => strtoupper((string) ($d->line_type ?? '')) === 'PART');
                                    $hasPartLine = $partDetails->isNotEmpty();
                                    $hasGlosaPart = $partDetails->contains(fn ($d) => empty($d->product_id));
                                    $canStartPartsFlow = $isExternalQuotation
                                        && $quotation->status === 'approved'
                                        && !$hasVehicle
                                        && !$quotation->sales_movement_id
                                        && (bool) $quotation->client_person_id;
                                    $showPartsRegisterPurchase = $canStartPartsFlow && $hasPartLine;
                                    $showPartsPurchaseConfirm = $canStartPartsFlow && $hasGlosaPart && !$partsPurchaseRecorded;
                                    $canGeneratePartsSale = $canStartPartsFlow
                                        && $quotation->details->isNotEmpty()
                                        && (!$hasGlosaPart || $partsPurchaseRecorded);
                                @endphp
                                <tr class="group hover:bg-slate-50/80 transition-all duration-200">
                                    <td class="px-6 py-5">
                                        @if ($isExternalQuotation && !$generatedOrder)
                                            <h5 class="font-black text-slate-500 tracking-tight leading-none mb-1.5">-</h5>
                                        @elseif ($isExternalQuotation && $generatedOrder)
                                            <h5 class="font-black text-slate-800 tracking-tight leading-none mb-1.5">{{ $generatedOrder->movement?->number ?? sprintf("%08d", $generatedOrder->id) }}</h5>
                                        @else
                                            <h5 class="font-black text-slate-800 tracking-tight leading-none mb-1.5">{{ $quotation->movement?->number ?? sprintf("%08d", $quotation->id) }}</h5>
                                        @endif
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $quotation->intake_date?->format('d/m/Y H:i') }}</p>
                                    </td>
                                    @if ($showQuotationExtras ?? false)
                                        <td class="px-6 py-5">
                                            <span class="font-mono text-xs font-black text-slate-700">{{ $quotation->quotation_correlative ?? '—' }}</span>
                                        </td>
                                        <td class="px-6 py-5 text-center">
                                            @if (($quotation->quotation_source ?? 'internal') === 'external')
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100 text-[9px] font-black uppercase tracking-widest">Externa</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-[9px] font-black uppercase tracking-widest">Interna</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-6 py-5">
                                        <p class="text-[13px] font-black text-slate-700 uppercase leading-none mb-1.5">{{ $quotation->vehicle?->plate ?? '-' }}</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $quotation->vehicle?->brand }} {{ $quotation->vehicle?->model }}</p>
                                    </td>
                                    <td class="px-6 py-5">
                                        <p class="text-[13px] font-black text-slate-800 uppercase leading-none">{{ $quotation->client?->first_name }} {{ $quotation->client?->last_name }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        @php
                                            $approvalStatus = (string) ($quotation->approval_status ?? '');
                                            $workflowStatus = (string) ($quotation->status ?? '');

                                            if ($approvalStatus === 'rejected' || $workflowStatus === 'diagnosis') {
                                                $approvalLabel = 'Rechazada';
                                                $approvalClass = 'bg-red-50 text-red-600 border-red-100';
                                                $approvalIcon = 'ri-close-circle-line';
                                            } elseif ($workflowStatus === 'awaiting_approval' || $approvalStatus === 'pending' || $approvalStatus === '') {
                                                $approvalLabel = 'Esperando aprobación';
                                                $approvalClass = 'bg-amber-50 text-amber-700 border-amber-100';
                                                $approvalIcon = 'ri-time-line';
                                            } else {
                                                $approvalLabel = 'Aprobada';
                                                $approvalClass = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                                $approvalIcon = 'ri-check-double-line';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border {{ $approvalClass }}">
                                            <i class="{{ $approvalIcon }} mr-1"></i> {{ $approvalLabel }}
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
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <div class="inline-flex flex-nowrap items-center justify-end gap-2">
                                            @if ($showQuotationExtras ?? false)
                                                @if ($isExternalQuotation && !$generatedOrder)
                                                    <a href="{{ route('admin.sales.quotations.edit-external', array_filter(['quotation' => $quotation->id, 'view_id' => request('view_id')])) }}"
                                                       class="w-10 h-10 rounded-xl bg-slate-600 text-white flex items-center justify-center shadow-lg shadow-slate-500/20 hover:scale-105 transition-all"
                                                       title="Editar cotización externa">
                                                        <i class="ri-edit-line text-lg"></i>
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.sales.quotations.destroy-external', $quotation) }}" onsubmit="return confirm('¿Eliminar cotización externa? Esta acción no se puede deshacer.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                                                        <button type="submit"
                                                            class="w-10 h-10 rounded-xl bg-red-600 text-white flex items-center justify-center shadow-lg shadow-red-500/20 hover:scale-105 transition-all cursor-pointer"
                                                            title="Eliminar cotización externa">
                                                            <i class="ri-delete-bin-line text-lg"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('admin.sales.quotations.excel', $quotation) }}"
                                                   class="w-10 h-10 rounded-xl bg-slate-700 text-white flex items-center justify-center shadow-lg shadow-slate-500/20 hover:scale-105 transition-all"
                                                   data-no-loading="true"
                                                   data-turbo="false"
                                                   title="Descargar Excel">
                                                    <i class="ri-file-excel-2-line text-lg"></i>
                                                </a>
                                                <button type="button"
                                                    @click="openSendModal({{ \Illuminate\Support\Js::from([
                                                        'send_url' => route('admin.sales.quotations.send', $quotation),
                                                        'default_email' => $quotation->quotation_client_email ?? '',
                                                    ]) }})"
                                                    class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/20 hover:scale-105 transition-all cursor-pointer"
                                                    title="Enviar por correo">
                                                    <i class="ri-mail-send-line text-lg"></i>
                                                </button>
                                            @endif
                                            <a href="{{ route('admin.sales.quotations.pdf', array_filter(['quotation' => $quotation, 'view_id' => request('view_id')])) }}"
                                               class="w-10 h-10 rounded-xl bg-rose-700 text-white flex items-center justify-center shadow-lg shadow-rose-500/20 hover:scale-105 transition-all"
                                               target="_blank" rel="noopener noreferrer"
                                               data-no-loading="true"
                                               data-turbo="false"
                                               title="Ver en PDF">
                                                <i class="ri-file-pdf-2-line text-lg"></i>
                                            </a>
                                            @if ($isExternalQuotation && $quotation->status === 'approved' && !$generatedOrder)
                                                @if ($hasVehicle)
                                                    <form method="POST" action="{{ route('admin.sales.quotations.generate-order', $quotation) }}">
                                                        @csrf
                                                        <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                                                        <button type="submit"
                                                            class="w-10 h-10 rounded-xl bg-violet-600 text-white flex items-center justify-center shadow-lg shadow-violet-500/20 hover:scale-105 transition-all cursor-pointer"
                                                            title="Generar orden de servicio">
                                                            <i class="ri-tools-line text-lg"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    @if ($showPartsRegisterPurchase)
                                                        <a href="{{ route('admin.purchases.create', array_filter(['workshop_quotation_id' => $quotation->id, 'view_id' => request('view_id')])) }}"
                                                            class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center shadow-lg shadow-amber-500/20 hover:scale-105 transition-all"
                                                            title="Registrar compra de repuestos">
                                                            <i class="ri-shopping-basket-2-line text-lg"></i>
                                                        </a>
                                                    @endif
                                                    @if ($showPartsPurchaseConfirm)
                                                        <form method="POST" action="{{ route('admin.sales.quotations.confirm-parts-purchase', $quotation) }}">
                                                            @csrf
                                                            <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                                                            <button type="submit"
                                                                class="w-10 h-10 rounded-xl bg-orange-600 text-white flex items-center justify-center shadow-lg shadow-orange-500/20 hover:scale-105 transition-all cursor-pointer"
                                                                title="Compra a glosa atendida: liberar generación de venta">
                                                                <i class="ri-checkbox-circle-line text-lg"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($canGeneratePartsSale)
                                                        <form method="POST" action="{{ route('admin.sales.quotations.generate-parts-sale', $quotation) }}">
                                                            @csrf
                                                            <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                                                            <button type="submit"
                                                                class="w-10 h-10 rounded-xl bg-fuchsia-700 text-white flex items-center justify-center shadow-lg shadow-fuchsia-500/20 hover:scale-105 transition-all cursor-pointer"
                                                                title="Generar venta con los repuestos (sin vehículo)">
                                                                <i class="ri-shopping-bag-3-line text-lg"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            @elseif ($isExternalQuotation && $generatedOrder)
                                                <a href="{{ route('workshop.orders.show', $generatedOrder) }}"
                                                    class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 border border-violet-200 flex items-center justify-center hover:scale-105 transition-all"
                                                    title="Ver orden de servicio generada">
                                                    <i class="ri-file-list-3-line text-lg"></i>
                                                </a>
                                            @endif
                                            @if ($isExternalQuotation && !$hasVehicle && $quotation->sales_movement_id && $quotation->sale?->movement_id)
                                                <a href="{{ route('admin.sales.edit', $quotation->sale->movement_id) }}"
                                                    class="w-10 h-10 rounded-xl bg-teal-600 text-white flex items-center justify-center shadow-lg shadow-teal-500/20 hover:scale-105 transition-all"
                                                    title="Ver venta generada">
                                                    <i class="ri-bill-line text-lg"></i>
                                                </a>
                                            @endif
                                            <button type="button" 
                                                @click="openModal({{ json_encode([
                                                    'id' => $quotation->id,
                                                    'number' => $isExternalQuotation
                                                        ? ($generatedOrder?->movement?->number ?? 'SIN OS')
                                                        : ($quotation->movement?->number ?? sprintf('%08d', $quotation->id)),
                                                    'vehicle' => $quotation->vehicle?->brand . ' ' . $quotation->vehicle?->model,
                                                    'status' => $quotation->status,
                                                    'status_label' => $quotation->status === 'approved' ? 'Aprobada' : 'Esperando aprobación',
                                                    'approve_url' => route('workshop.orders.approve', $quotation->id),
                                                    'approval_status' => $quotation->approval_status ?? 'pending',
                                                    'approval_note' => $quotation->approval_note ?? ($quotation->quotation_lost_reason ?? ''),
                                                    'details' => $quotation->details->whereIn('line_type', ['PART', 'SERVICE', 'LABOR'])->map(fn($d) => [
                                                        'id' => $d->id,
                                                        'line_type' => $d->line_type,
                                                        'description' => $d->description,
                                                        'qty' => (float)$d->qty,
                                                        'unit_price' => (float)$d->unit_price,
                                                        'total' => (float)$d->total,
                                                    ])->values()->toArray(),
                                                    'deleted_details' => $quotation->deletedDetails->whereIn('line_type', ['PART', 'SERVICE', 'LABOR'])->map(fn($d) => [
                                                        'line_type' => $d->line_type,
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
                                    <td colspan="{{ $colCount }}" class="py-20 text-center">
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
                                                    <th class="px-3 py-3 text-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Tipo</th>
                                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Concepto</th>
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
                                                        <td class="px-3 py-3.5 text-center">
                                                            <span class="inline-flex px-2 py-0.5 rounded-md bg-slate-100 text-[9px] font-black uppercase tracking-wider text-slate-500 border border-slate-200" x-text="(detail.line_type || 'LABOR') === 'PART' ? 'Rep.' : ((detail.line_type || 'LABOR') === 'LABOR' ? 'M.O.' : 'Serv.')"></span>
                                                        </td>
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
                                                            <template x-if="selectedQuotation?.approval_status === 'rejected'">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-red-50 text-red-600 border border-red-100 text-[9px] font-black uppercase tracking-widest">
                                                                    Rechazado
                                                                </span>
                                                            </template>
                                                            <template x-if="selectedQuotation?.approval_status !== 'rejected' && selectedQuotation?.status === 'awaiting_approval'">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 text-[9px] font-black uppercase tracking-widest">
                                                                    Esperando
                                                                </span>
                                                            </template>
                                                            <template x-if="selectedQuotation?.approval_status !== 'rejected' && selectedQuotation?.status !== 'awaiting_approval'">
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
                                                        <td class="px-3 py-3.5 text-center">
                                                            <span class="inline-flex px-2 py-0.5 rounded-md bg-red-50 text-[9px] font-black uppercase tracking-wider text-red-300 border border-red-100" x-text="(deleted.line_type || 'LABOR') === 'PART' ? 'Rep.' : ((deleted.line_type || 'LABOR') === 'LABOR' ? 'M.O.' : 'Serv.')"></span>
                                                        </td>
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
                                        <template x-if="selectedQuotation?.status === 'awaiting_approval'">
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Decisión</label>
                                                    <select name="decision" x-model="decisionPayload.decision"
                                                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 focus:border-blue-500 focus:outline-none">
                                                        <option value="approved">Aprobar cotización</option>
                                                        <option value="rejected">Rechazar cotización</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                        Motivo
                                                        <span x-show="decisionPayload.decision === 'rejected'" class="normal-case font-medium">(opcional en rechazo)</span>
                                                    </label>
                                                    <textarea name="approval_note" x-model="decisionPayload.approval_note" placeholder="Comentario o motivo..." class="mt-1 w-full min-h-[84px] p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-medium text-slate-700 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 transition-all outline-none resize-none shadow-sm"></textarea>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="selectedQuotation?.status !== 'awaiting_approval'">
                                            <textarea placeholder="Comentarios..." class="w-full h-full min-h-[80px] p-4 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-medium text-slate-500 outline-none resize-none shadow-sm" readonly></textarea>
                                        </template>
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
                                    <i class="ri-checkbox-circle-fill text-lg"></i>
                                    <span x-text="decisionPayload.decision === 'rejected' ? 'Guardar rechazo' : 'Aprobar cotización'"></span>
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

        <template x-teleport="body">
            <div x-show="showSendModal" class="relative z-[999999]" role="dialog" aria-modal="true">
                <div x-show="showSendModal"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="closeSendModal()"
                    class="fixed inset-0 bg-slate-950/60 backdrop-blur-[12px] cursor-pointer"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
                    <div x-show="showSendModal"
                        x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        @click.stop
                        class="pointer-events-auto w-full max-w-md rounded-[1.5rem] border border-slate-200 bg-white p-8 shadow-2xl">
                        <h3 class="text-lg font-black text-slate-800 tracking-tight">Enviar cotización por correo</h3>
                        <p class="mt-1 text-[11px] font-medium text-slate-400">Se adjunta el Excel generado para el cliente.</p>
                        <form method="POST" :action="sendQuotation?.send_url" class="mt-6 space-y-4">
                            @csrf
                            <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Correo electrónico</label>
                                <input type="email" name="email" x-model="sendEmail" required
                                    class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-800 focus:border-emerald-500 focus:outline-none">
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="closeSendModal()"
                                    class="h-10 rounded-xl border border-slate-200 px-5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-50">Cancelar</button>
                                <button type="submit"
                                    class="h-10 rounded-xl bg-emerald-600 px-6 text-[10px] font-black uppercase tracking-widest text-white shadow-lg shadow-emerald-500/25 hover:bg-emerald-700">Enviar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>

    </div>

    <style>
        .quotation-filters select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem 1rem;
        }
        .quotation-filters select::-ms-expand {
            display: none;
        }
        [x-cloak] {
            display: none !important;
        }
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
