@extends('layouts.app')

@section('content')
<div x-data="{
    costs: @js($costTable),
    allAssembliesData: @js($assemblies->map(fn($a) => ['id' => $a->id, 'total_cost' => $a->total_cost])->values()->all()),
    allVehicleTypes: @js($vehicleTypes),
    selectedBrand: '',
    selectedType: '',
    unitCost: 0,
    selectedAssemblies: [],
    viewMode: 'cards', // 'cards' o 'table'
    // Lifecycle
    init() {
    },
    // Indica si el tipo seleccionado no tiene costos configurados para NINGUNA marca
    get typeHasNoCosts() {
        if (!this.selectedType) return false;
        return !this.costs.some(c => c.vehicle_type === this.selectedType);
    },

    // Devuelve las marcas disponibles filtradas por el tipo de vehículo
    get availableBrands() {
        if (!this.selectedType) {
            // Si no hay tipo, mostrar todas las marcas únicas
            return this.costs.map(c => c.brand_company).filter((v, i, a) => a.indexOf(v) === i);
        }
        // Mostrar solo las marcas que tienen configurado el tipo seleccionado
        return this.costs
            .filter(c => c.vehicle_type === this.selectedType)
            .map(c => c.brand_company)
            .filter((v, i, a) => a.indexOf(v) === i);
    },

    filteredTypes() {
        return this.allVehicleTypes.map(t => t.name);
    },

    updateUnitCost() {
        if (!this.selectedBrand || !this.selectedType) {
            this.unitCost = 0;
            return;
        }
        const found = this.costs.find(c => c.brand_company === this.selectedBrand && c.vehicle_type === this.selectedType);
        this.unitCost = found ? parseFloat(found.unit_cost).toFixed(2) : 0;
    },

    // --- QUICK COST CONFIG ---
    showCostConfigModal: false,
    configLoading: false,
    configData: {
        brand_company: '',
        unit_cost: ''
    },

    openQuickConfig() {
        this.configData.brand_company = this.selectedBrand;
        this.configData.unit_cost = '';
        this.$dispatch('open-quick-cost-modal');
    },

    async submitQuickConfig() {
        if (!this.configData.brand_company || !this.configData.unit_cost) return;
        this.configLoading = true;
        
        try {
            const response = await fetch('{{ route('workshop.assemblies.costs.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    brand_company: this.configData.brand_company,
                    vehicle_type: this.selectedType,
                    unit_cost: this.configData.unit_cost,
                    apply_to_all_branches: 0
                })
            });

            const result = await response.json();
            if (result.status === 'success') {
                // Actualizar lista local de costos
                this.costs.push(result.cost);
                this.costs = [...this.costs];
                
                // Si la marca coincide, actualizar el costo unitario en el formulario principal
                if (this.configData.brand_company === this.selectedBrand) {
                    this.updateUnitCost();
                }
                
                this.$dispatch('close-quick-cost-modal');
                // Notificar éxito (opcional, el cambio en el select es visualmente claro)
            }
        } catch (e) {
            console.error('Error configurando costo:', e);
        }
        this.configLoading = false;
    },

    // --- QUICK LOCATION CONFIG ---
    locationsData: @js($assemblyLocations),
    locationLoading: false,
    locationData: { name: '', address: '', active: '1', apply_to_all_branches: false },

    openQuickLocation() {
        this.locationData = { name: '', address: '', active: '1', apply_to_all_branches: false };
        this.$dispatch('open-quick-location-modal');
    },

    async submitQuickLocation() {
        if (!this.locationData.name) return;
        this.locationLoading = true;
        
        try {
            const response = await fetch('{{ route('workshop.assembly-locations.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.locationData)
            });

            const result = await response.json();
            if (result.status === 'success') {
                this.locationsData.push(result.location);
                this.locationsData = [...this.locationsData];
                
                const selectEl = document.getElementById('workshop_assembly_location_id');
                if (selectEl) {
                    setTimeout(() => { selectEl.value = result.location.id; }, 100);
                }
                
                this.$dispatch('close-quick-location-modal');
            }
        } catch (e) {
            console.error('Error configurando ubicacion:', e);
        }
        this.locationLoading = false;
    }
}">
    <x-common.page-breadcrumb pageTitle="Armados Taller" />

    <x-common.component-card title="Armado / Ensamblaje" desc="Registro mensual de armados y costos por tipo de vehiculo.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-assembly-modal')">
                <i class="ri-add-line"></i><span>Nuevo armado</span>
            </x-ui.button>
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#334155;color:#fff" @click="$dispatch('open-summary-modal')">
                <i class="ri-bar-chart-2-line"></i><span>Resumen por Tipo</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.assembly-locations.index') }}" style="background-color:#7C3AED;color:#fff">
                <i class="ri-map-pin-line"></i><span>Ubicaciones</span>
            </x-ui.link-button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.assemblies.export', ['month' => $month]) }}" style="background-color:#166534;color:#fff">
                <i class="ri-file-excel-2-line"></i><span>Exportar CSV</span>
            </x-ui.link-button>

            {{-- Botón para Generar Venta Masiva --}}
            <template x-if="selectedAssemblies.length > 0">
                <form method="GET" action="{{ route('workshop.assemblies.checkout.page') }}" class="inline-block">
                    <template x-for="id in selectedAssemblies" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <x-ui.button size="md" variant="primary" type="submit" style="background-color:#7C3AED;color:#fff">
                        <i class="ri-shopping-cart-2-line"></i><span>Generar Venta (<span x-text="selectedAssemblies.length"></span>)</span>
                    </x-ui.button>
                </form>
            </template>
        </div>

        <form method="GET" action="{{ route('workshop.assemblies.index') }}" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-7 dark:border-gray-800 dark:bg-white/[0.02]">
            @if(request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif
            <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="brand_company" value="{{ $brandCompany }}" placeholder="Empresa/Marca" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="vehicle_type" value="{{ $vehicleType }}" placeholder="Tipo vehiculo" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="guia_remision" value="{{ $guiaRemision }}" placeholder="Guía de remisión" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <option value="all" @selected(($status ?? 'all') === 'all')>Todos los estados</option>
                <option value="pending" @selected(($status ?? 'all') === 'pending')>Pendiente</option>
                <option value="in_progress" @selected(($status ?? 'all') === 'in_progress')>En proceso</option>
                <option value="finished" @selected(($status ?? 'all') === 'finished')>Finalizado</option>
                <option value="delivered" @selected(($status ?? 'all') === 'delivered')>Entregado</option>
            </select>
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.assemblies.index', request('view_id') ? ['view_id' => request('view_id')] : []) }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>


        <div class="mt-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4 px-1">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white/90">Detalle de armados</h2>
                
                <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
                    <button type="button" 
                        @click="viewMode = 'cards'"
                        :class="viewMode === 'cards' ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-all">
                        <i class="ri-grid-fill text-lg"></i>
                    </button>
                    <button type="button" 
                        @click="viewMode = 'table'"
                        :class="viewMode === 'table' ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-all">
                        <i class="ri-table-line text-lg"></i>
                    </button>
                </div>
            </div>
            
            <div x-show="viewMode === 'cards'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($assemblies as $assembly)
                    @php
                        $statusText = 'En proceso';
                        $statusClass = 'bg-orange-500/20 text-orange-300 border border-orange-500/30';
                        if($assembly->exit_at) {
                            $statusText = 'Completado';
                            $statusClass = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                        } elseif($assembly->finished_at) {
                            $statusText = 'Finalizado';
                            $statusClass = 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                        } elseif(!$assembly->started_at) {
                            $statusText = 'Pendiente';
                            $statusClass = 'bg-white/10 text-white border border-white/20';
                        }
                    @endphp
                    <div class="relative flex h-full flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white p-4 shadow-[0_16px_38px_rgba(15,23,42,0.08)] transition-all hover:-translate-y-1 hover:shadow-[0_22px_50px_rgba(15,23,42,0.14)]">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">Orden de armado</span>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <h3 class="text-[22px] font-black leading-none tracking-tight text-slate-900">#{{ str_pad($assembly->id, 8, '0', STR_PAD_LEFT) }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                    @if($assembly->sales_movement_id)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide text-emerald-700">
                                            <i class="ri-checkbox-circle-fill"></i> Pagado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide text-amber-700">
                                            <i class="ri-error-warning-fill"></i> Deuda
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if(!$assembly->sales_movement_id)
                                <input type="checkbox" :value="{{ $assembly->id }}" x-model="selectedAssemblies" class="mt-1 h-5 w-5 rounded border-slate-300 text-[#244BB3] focus:ring-[#244BB3]">
                            @endif
                        </div>

                        <div class="mb-3 flex items-center gap-3 rounded-[24px] bg-slate-800 px-3.5 py-3 text-white shadow-lg">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                                <i class="ri-motorbike-line text-[28px] text-[#ffb15c]"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[18px] font-black uppercase leading-none tracking-tight">{{ $assembly->model ?: 'Sin modelo' }}</div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-slate-200">
                                    <span class="font-semibold uppercase">{{ $assembly->brand_company ?: 'Sin marca' }}</span>
                                    <span class="text-slate-400">{{ $assembly->vehicle_type ?: 'Sin tipo' }}</span>
                                    <span class="truncate font-mono text-slate-300">{{ $assembly->vin ?: 'No asignado' }}</span>
                                    @if($assembly->guia_remision)
                                        <span class="truncate text-slate-100" title="Guía de remisión">GR: {{ $assembly->guia_remision }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 grid grid-cols-2 gap-2.5">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Ingreso</div>
                                <div class="mt-1 text-[15px] font-black text-slate-900">{{ optional($assembly->entry_at)->format('d/m/y H:i') ?: '--' }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Marca</div>
                                <div class="mt-1 truncate text-[15px] font-black uppercase text-slate-900">{{ $assembly->brand_company ?: '--' }}</div>
                            </div>
                            <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Ubicacion / tecnico</div>
                                <div class="mt-1 truncate text-[15px] font-black leading-tight text-slate-900">
                                    {{ $assembly->location?->name ?: 'Sin ubicacion' }}
                                    @if($assembly->responsibleTechnician)
                                         -  {{ trim(($assembly->responsibleTechnician->first_name ?? '') . ' ' . ($assembly->responsibleTechnician->last_name ?? '')) }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mb-2.5 grid grid-cols-3 gap-2.5">
                            <div class="rounded-2xl border border-slate-200 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Cant</div>
                                <div class="mt-1 text-xl font-black text-slate-900">{{ $assembly->quantity }}</div>
                            </div>
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-600">Unit</div>
                                <div class="mt-1 text-xl font-black text-emerald-700">S/{{ number_format($assembly->unit_cost, 0) }}</div>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-amber-600">Total</div>
                                <div class="mt-1 text-xl font-black text-amber-700">S/{{ number_format($assembly->total_cost, 0) }}</div>
                            </div>
                        </div>

                        <div class="mb-4 grid grid-cols-3 gap-2.5">
                            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-blue-600">Estimado</div>
                                <div class="mt-1 text-sm font-black text-slate-900">{{ (int) $assembly->estimated_minutes }} min</div>
                            </div>
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-600">Real</div>
                                <div class="mt-1 text-sm font-black text-slate-900">{{ $assembly->actual_repair_minutes !== null ? $assembly->actual_repair_minutes . ' min' : '--' }}</div>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-2.5 py-2.5 text-center">
                                <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-amber-600">Variacion</div>
                                <div class="mt-1 text-sm font-black text-slate-900">{{ $assembly->estimated_vs_real_minutes !== null ? ($assembly->estimated_vs_real_minutes > 0 ? '+' : '') . $assembly->estimated_vs_real_minutes . ' min' : '--' }}</div>
                            </div>
                        </div>

                        <div class="mt-auto flex flex-wrap items-center gap-2">
                            @if(!$assembly->started_at)
                                <form method="POST" action="{{ route('workshop.armados.start', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                        <i class="ri-play-fill"></i>
                                        <span>Iniciar</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->finished_at)
                                <form method="POST" action="{{ route('workshop.armados.finish', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);">
                                        <i class="ri-check-line"></i>
                                        <span>Finalizar</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->exit_at)
                                <form method="POST" action="{{ route('workshop.armados.exit', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                        <i class="ri-external-link-line"></i>
                                        <span>Salida</span>
                                    </button>
                                </form>
                            @endif

                            @if(!$assembly->sales_movement_id)
                                <button type="button" class="flex-1 rounded-xl py-2 text-[11px] font-bold text-white transition-all hover:brightness-110 shadow-lg" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);" @click="$dispatch('open-massive-sale-modal'); selectedAssemblies = [{{ $assembly->id }}]">
                                    <i class="ri-shopping-cart-line mr-1"></i>Venta
                                </button>
                            @endif

                            @if(!$assembly->sales_movement_id)
                                <a href="{{ route('workshop.assemblies.edit', request('view_id') ? ['assembly' => $assembly, 'view_id' => request('view_id')] : ['assembly' => $assembly]) }}" class="flex h-9 w-9 items-center justify-center rounded-xl border transition-all hover:bg-amber-500 hover:text-white hover:border-amber-500" style="background-color: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #94A3B8;" title="Editar">
                                    <i class="ri-pencil-line text-lg"></i>
                                </a>
                            @endif

                            <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('Eliminar este registro?')" class="flex items-center">
                                @csrf
                                @method('DELETE')
                                <div class="relative group">
                                    <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-xl border transition-all hover:bg-red-600 hover:text-white hover:border-red-600" style="background-color: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #94A3B8;">
                                        <i class="ri-delete-bin-line text-lg"></i>
                                    </button>
                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                        Eliminar
                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-gray-200 bg-gray-50/50 py-16 dark:border-gray-800 dark:bg-transparent">
                        <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-white text-gray-200 shadow-sm dark:bg-gray-800">
                            <i class="ri-car-line text-3xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-gray-400">Sin registros de armado</h3>
                    </div>
                @endforelse
            </div>

            {{-- Vista de Tabla --}}
            <div x-show="viewMode === 'table'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="overflow-x-auto rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-gray-800/50">
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300">Orden</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300">Guía remisión</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300">Vehículo / Marca</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300">Técnico / Ubicación</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300 text-center">Cant.</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300 text-center">Estado</th>
                            <th class="px-5 py-4 font-bold text-gray-700 dark:text-gray-300">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($assemblies as $assembly)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($assembly->sales_movement_id)
                                            <div title="Venta generada" class="flex h-4 w-4 items-center justify-center rounded bg-emerald-100 text-emerald-600">
                                                <i class="ri-check-line text-xs font-bold"></i>
                                            </div>
                                        @else
                                            <input type="checkbox" :value="{{ $assembly->id }}" x-model="selectedAssemblies" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        @endif
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 dark:text-white">#{{ str_pad($assembly->id, 6, '0', STR_PAD_LEFT) }}</span>
                                            <span class="text-[10px] text-gray-500">{{ optional($assembly->entry_at)->format('d/m/y') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-gray-700 dark:text-gray-300">
                                    {{ $assembly->guia_remision ?: '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 dark:text-gray-200 uppercase">{{ $assembly->model ?: 'Sin modelo' }}</span>
                                        <span class="text-xs text-gray-500 uppercase">{{ $assembly->brand_company }} -  {{ $assembly->vehicle_type }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ trim(($assembly->responsibleTechnician->first_name ?? '') . ' ' . ($assembly->responsibleTechnician->last_name ?? '')) ?: 'Sin técnico' }}
                                        </span>
                                        <span class="text-xs text-gray-500 italic">{{ $assembly->location?->name ?: 'Sin ubicación' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center font-bold">{{ $assembly->quantity }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-center">
                                        @php
                                            $statusLabel = 'Pendiente';
                                            $statusColor = 'bg-gray-100 text-gray-600';
                                            if($assembly->exit_at) { $statusLabel = 'Entregado'; $statusColor = 'bg-emerald-100 text-emerald-700'; }
                                            elseif($assembly->finished_at) { $statusLabel = 'Finalizado'; $statusColor = 'bg-blue-100 text-blue-700'; }
                                            elseif($assembly->started_at) { $statusLabel = 'En Proceso'; $statusColor = 'bg-orange-100 text-orange-700'; }
                                        @endphp
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $statusColor }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        @if(!$assembly->started_at)
                                            <form method="POST" action="{{ route('workshop.armados.start', $assembly) }}">@csrf
                                                <button title="Iniciar" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"><i class="ri-play-line text-lg"></i></button>
                                            </form>
                                        @elseif(!$assembly->finished_at)
                                            <form method="POST" action="{{ route('workshop.armados.finish', $assembly) }}">@csrf
                                                <button title="Finalizar" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="ri-check-line text-lg"></i></button>
                                            </form>
                                        @elseif(!$assembly->exit_at)
                                            <form method="POST" action="{{ route('workshop.armados.exit', $assembly) }}">@csrf
                                                <button title="Salida" class="p-1.5 text-orange-600 hover:bg-orange-50 rounded-lg transition-colors"><i class="ri-external-link-line text-lg"></i></button>
                                            </form>
                                        @endif
                                        
                                        @if(!$assembly->sales_movement_id)
                                            <button @click="$dispatch('open-massive-sale-modal'); selectedAssemblies = [{{ $assembly->id }}]" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Generar Venta">
                                                <i class="ri-shopping-cart-2-line text-lg"></i>
                                            </button>
                                        @endif

                                        @if(!$assembly->sales_movement_id)
                                            <a href="{{ route('workshop.assemblies.edit', request('view_id') ? ['assembly' => $assembly, 'view_id' => request('view_id')] : ['assembly' => $assembly]) }}" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Editar">
                                                <i class="ri-pencil-line text-lg"></i>
                                            </a>
                                        @endif

                                        <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('¿Eliminar?')">
                                            @csrf @method('DELETE')
                                            <button class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors"><i class="ri-delete-bin-line text-lg"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-8">
                {{ $assemblies->links() }}
            </div>
        </div>
    </x-common.component-card>


    {{-- Modal para nuevo armado --}}

    {{-- Modal para nuevo armado --}}
    <x-ui.modal x-data="{ open: false }" @open-assembly-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nuevo registro de armado</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div x-show="typeHasNoCosts && selectedType" x-cloak class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 flex items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="ri-error-warning-fill text-xl"></i>
                    <div>
                        <span class="font-bold">Aviso:</span> El tipo <span class="font-bold" x-text="selectedType"></span> no tiene costos registrados.
                        <p class="text-[11px] mt-0.5 opacity-80 italic">¿Deseas configurar uno ahora mismo?</p>
                    </div>
                </div>
                <x-ui.button type="button" size="xs" variant="primary" style="background-color:#D97706" @click="openQuickConfig()">
                    <i class="ri-settings-4-line"></i><span>Configurar</span>
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('workshop.assemblies.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                @if(request('view_id'))
                    <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                @endif
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de Vehículo</label>
                    <select name="vehicle_type" x-model="selectedType" @change="updateUnitCost()" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione tipo...</option>
                        <template x-for="type in allVehicleTypes" :key="type.id">
                            <option :value="type.name" x-text="type.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Empresa/Marca</label>
                    <select name="brand_company" x-model="selectedBrand" @change="updateUnitCost()" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione marca...</option>
                        <template x-for="brand in availableBrands" :key="brand">
                            <option :value="brand" x-text="brand"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                    <input name="model" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: ELEGANCE S">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada</label>
                    <input name="displacement" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: 110">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                    <input name="color" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: GRIS">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">VIN / Código Motor</label>
                    <input name="vin" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese VIN o código">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Guía de remisión</label>
                    <input name="guia_remision" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Número de guía">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cantidad</label>
                    <input type="number" min="1" name="quantity" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="1" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Costo Unitario</label>
                    <input type="number" min="0" step="0.01" name="unit_cost" x-model="unitCost" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm bg-gray-50" readonly placeholder="0.00">
                    <p class="mt-1 text-[10px] text-gray-500 italic">* Según marca/tipo.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Ubicación del armado</label>
                    <div class="flex items-start gap-2">
                        <select name="workshop_assembly_location_id" id="workshop_assembly_location_id" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm flex-1">
                            <option value="">Seleccione ubicación...</option>
                            <template x-for="loc in locationsData" :key="loc.id">
                                <option :value="loc.id" x-text="loc.name"></option>
                            </template>
                        </select>
                        <button type="button" @click="openQuickLocation()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" title="Nueva ubicación">
                            <i class="ri-add-line text-lg"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Técnico responsable</label>
                    <select name="responsible_technician_person_id" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione técnico...</option>
                        @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ trim(($technician->first_name ?? '') . ' ' . ($technician->last_name ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de Ingreso</label>
                    <input type="datetime-local" name="entry_at" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ now()->format('Y-m-d\\TH:i') }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de Armado (Planificado)</label>
                    <input type="date" name="assembled_at" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Entrega estimada</label>
                    <input type="datetime-local" name="estimated_delivery_at" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ now()->addHours(2)->format('Y-m-d\\TH:i') }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tiempo estimado (min)</label>
                    <input type="number" min="0" name="estimated_minutes" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="120" placeholder="120">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Notas adicionales..."></textarea>
                </div>
                <div class="md:col-span-2 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" class="flex-1"><i class="ri-save-line"></i><span>Registrar Armado</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false" class="flex-1"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>


    {{-- Modal para Resumen Mensual --}}

    {{-- Modal para Resumen Mensual --}}
    <x-ui.modal x-data="{ open: false }" @open-summary-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Resumen mensual por tipo</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full min-w-[650px]">
                    <thead>
                        <tr>
                            <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Empresa/Marca</th>
                            <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                            <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cantidad</th>
                            <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Costo Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaryByType as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3 text-sm">{{ $row->brand_company }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row->vehicle_type }}</td>
                                <td class="px-4 py-3 text-sm">{{ (int) $row->total_qty }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-800">S/{{ number_format((float) $row->total_cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-4 text-sm text-gray-500 text-center">Sin registros en el mes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-ui.modal>


    {{-- Modal rápido para configuración de costos --}}
    <x-ui.modal 
        x-data="{ open: false }" 
        @open-quick-cost-modal.window="open = true" 
        @close-quick-cost-modal.window="open = false" 
        :isOpen="false" :showCloseButton="false" class="max-w-md">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">General Costo</h3>
                    <p class="text-[11px] text-gray-500">Tipo: <span class="font-bold text-amber-600" x-text="selectedType"></span></p>
                </div>
                <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:bg-gray-100 transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 uppercase">Empresa / Marca</label>
                    <input type="text" x-model="configData.brand_company" 
                        class="h-11 w-full rounded-xl border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" 
                        placeholder="GP MOTOS, HONDA...">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 uppercase">Costo Unitario (S/)</label>
                    <input type="number" step="0.01" x-model="configData.unit_cost" 
                        class="h-11 w-full rounded-xl border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" 
                        placeholder="0.00">
                </div>

                <div class="pt-2">
                    <button type="button" @click="submitQuickConfig()" 
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-sm font-bold text-white shadow-lg shadow-amber-100 transition-all hover:bg-amber-700 active:scale-95"
                        :disabled="configLoading">
                        <i class="ri-save-line" x-show="!configLoading"></i>
                        <span x-text="configLoading ? 'Procesando...' : 'Guardar y Continuar'"></span>
                    </button>
                </div>
            </div>
        </div>
    </x-ui.modal>

    {{-- Modal rápido para configuración de ubicaciones --}}
    <x-ui.modal 
        x-data="{ open: false }" 
        @open-quick-location-modal.window="open = true" 
        @close-quick-location-modal.window="open = false" 
        :isOpen="false" :showCloseButton="false" class="max-w-xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Nueva ubicacion de armado</h3>
                    <p class="mt-1 text-sm text-gray-500">Define el lugar y si aplica a la sucursal actual o a todas.</p>
                </div>
                <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:bg-gray-100 transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" x-model="locationData.name" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" placeholder="Ej: Area electrica">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                    <select x-model="locationData.active" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Direccion</label>
                    <input type="text" x-model="locationData.address" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" placeholder="Direccion opcional">
                </div>
                <label class="md:col-span-2 inline-flex items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-medium text-violet-700">
                    <input type="checkbox" x-model="locationData.apply_to_all_branches" class="h-4 w-4 rounded border-violet-300 text-violet-600">
                    <span>Aplicar a todas las sucursales de la empresa</span>
                </label>
                <div class="md:col-span-2 flex gap-3 pt-2">
                    <x-ui.button type="button" @click="submitQuickLocation()" size="md" variant="primary" class="flex-1" style="background:linear-gradient(90deg,#7c3aed,#6d28d9);color:#fff;" x-bind:disabled="locationLoading">
                        <i class="ri-save-line" x-show="!locationLoading"></i>
                        <span x-text="locationLoading ? 'Guardando...' : 'Guardar ubicacion'"></span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" class="flex-1" @click="open = false" x-bind:disabled="locationLoading">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.modal>
</div>
@endsection
