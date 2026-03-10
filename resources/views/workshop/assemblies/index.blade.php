@extends('layouts.app')

@section('content')
<div x-data="{
    costs: @js($costTable),
    allVehicleTypes: @js($vehicleTypes),
    selectedBrand: '',
    selectedType: '',
    unitCost: 0,
    selectedAssemblies: [],
    viewMode: 'cards', // 'cards' o 'table'
    
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
                <x-ui.button size="md" variant="primary" type="button" style="background-color:#7C3AED;color:#fff" @click="$dispatch('open-massive-sale-modal')">
                    <i class="ri-shopping-cart-2-line"></i><span>Generar Venta (<span x-text="selectedAssemblies.length"></span>)</span>
                </x-ui.button>
            </template>
        </div>

        <form method="GET" action="{{ route('workshop.assemblies.index') }}" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-5 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="brand_company" value="{{ $brandCompany }}" placeholder="Empresa/Marca" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="vehicle_type" value="{{ $vehicleType }}" placeholder="Tipo vehiculo" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.assemblies.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
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
            
            <div x-show="viewMode === 'cards'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
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
                    <div class="relative flex flex-col p-6 shadow-2xl transition-all hover:shadow-[0_20px_50px_rgba(131,75,26,0.3)] hover:-translate-y-1 border" 
                         style="background: linear-gradient(135deg, #231B18 0%, #442B1E 50%, #834B1A 100%); border-radius: 2.5rem; border-color: #3D2B22;">
                        
                        <!-- Header & Controls -->
                        <div class="mb-5 flex items-start justify-between">
                            <div class="flex-1">
                                <span class="text-[9px] font-bold uppercase tracking-widest text-white">ORDEN DE ARMADO</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <h3 class="text-sm font-black text-white">ORD #{{ str_pad($assembly->id, 8, '0', STR_PAD_LEFT) }}</h3>
                                    <span class="rounded-full px-2.5 py-0.5 text-[8px] font-bold uppercase tracking-tight {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                @if($assembly->sales_movement_id)
                                    <span class="flex h-5 items-center gap-1 rounded-full px-2 text-[8px] font-bold border" style="background-color: rgba(16, 185, 129, 0.15); color: #6EE7B7; border-color: rgba(16, 185, 129, 0.2);">
                                        <i class="ri-checkbox-circle-fill"></i> PAGADO
                                    </span>
                                @else
                                    <span class="flex h-5 items-center gap-1 rounded-full px-2 text-[8px] font-bold border" style="background-color: rgba(245, 158, 11, 0.15); color: #FCD34D; border-color: rgba(245, 158, 11, 0.2);">
                                        <i class="ri-error-warning-fill"></i> DEUDA
                                    </span>
                                @endif
                                <input type="checkbox" :value="{{ $assembly->id }}" x-model="selectedAssemblies" class="h-5 w-5 rounded-lg border-2 border-white/20 text-[#834B1A] focus:ring-[#834B1A] bg-white/10 cursor-pointer transition-all hover:border-[#834B1A]">
                            </div>
                        </div>

                        <!-- Vehicle Identity Block (White Premium) -->
                        <div class="mb-5 flex items-center gap-4 rounded-3xl p-4 shadow-lg border" style="background-color: #FFFFFF; border-color: rgba(255, 255, 255, 0.1);">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-inner border" style="background-color: #FBF9F7; border-color: #F3EBE4;">
                                <i class="ri-motorbike-line text-2xl text-[#3D261C]"></i>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <h4 class="truncate font-black text-[#3D261C] uppercase tracking-tight text-xs">{{ $assembly->model ?: 'Sin modelo' }}</h4>
                                <div class="flex items-center gap-2">
                                     <span class="text-[9px] font-bold text-[#7D6658] uppercase tracking-wide">{{ $assembly->brand_company }}</span>
                                     <span class="h-1 w-1 rounded-full bg-[#CAA994]"></span>
                                     <p class="truncate font-mono text-[9px] text-[#8B7366]">{{ $assembly->vin ?: 'No asignado' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Info Grid -->
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl p-3 border" style="background-color: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05);">
                                <span class="block text-[8px] font-bold uppercase mb-1" style="color: #FDE6D2;">MARCA</span>
                                <span class="block truncate text-xs font-black text-white uppercase">{{ $assembly->brand_company }}</span>
                            </div>
                            <div class="rounded-2xl p-3 border" style="background-color: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05);">
                                <span class="block text-[8px] font-bold uppercase mb-1" style="color: #FDE6D2;">INGRESO</span>
                                <span class="block text-xs font-black text-white leading-tight">{{ optional($assembly->entry_at)->format('d/m/y H:i') ?: '--' }}</span>
                            </div>
                            <div class="rounded-2xl p-3 border col-span-2" style="background-color: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05);">
                                <span class="block text-[8px] font-bold uppercase mb-1" style="color: #FDE6D2;">UBICACION / TECNICO</span>
                                <span class="block text-xs font-black text-white leading-tight">
                                    {{ $assembly->location?->name ?: 'Sin ubicacion' }}
                                    @if($assembly->responsibleTechnician)
                                        · {{ trim(($assembly->responsibleTechnician->first_name ?? '') . ' ' . ($assembly->responsibleTechnician->last_name ?? '')) }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Values Row (High Contrast) -->
                        <div class="mb-5 grid grid-cols-3 gap-4">
                            <div class="flex flex-col items-center justify-center rounded-2xl py-2.5 border shadow-sm" style="background-color: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1);">
                                <span class="text-[7px] font-bold uppercase mb-0.5" style="color: #EAD7CA;">CANT</span>
                                <span class="text-xs font-black" style="color: #FFFFFF;">{{ $assembly->quantity }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center rounded-2xl py-2.5 border" style="background-color: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2);">
                                <span class="text-[7px] font-bold uppercase mb-0.5" style="color: #A7F3D0;">UNIT</span>
                                <span class="text-xs font-black" style="color: #A7F3D0;">S/{{ number_format($assembly->unit_cost, 0) }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center rounded-2xl py-2.5 border" style="background-color: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.2);">
                                <span class="text-[7px] font-bold uppercase mb-0.5" style="color: #FDE6D2;">TOTAL</span>
                                <span class="text-xs font-black" style="color: #FDE6D2;">S/{{ number_format($assembly->total_cost, 0) }}</span>
                            </div>
                        </div>
                        <div class="mb-5 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl p-3 border text-center" style="background-color: rgba(59, 130, 246, 0.12); border-color: rgba(59, 130, 246, 0.2);">
                                <span class="block text-[8px] font-bold uppercase mb-1 text-blue-200">ESTIMADO</span>
                                <span class="block text-xs font-black text-white">{{ (int) $assembly->estimated_minutes }} min</span>
                            </div>
                            <div class="rounded-2xl p-3 border text-center" style="background-color: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.2);">
                                <span class="block text-[8px] font-bold uppercase mb-1 text-emerald-200">REAL</span>
                                <span class="block text-xs font-black text-white">{{ $assembly->actual_repair_minutes !== null ? $assembly->actual_repair_minutes . ' min' : '--' }}</span>
                            </div>
                            <div class="rounded-2xl p-3 border text-center" style="background-color: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.2);">
                                <span class="block text-[8px] font-bold uppercase mb-1 text-amber-200">VARIACION</span>
                                <span class="block text-xs font-black text-white">{{ $assembly->estimated_vs_real_minutes !== null ? ($assembly->estimated_vs_real_minutes > 0 ? '+' : '') . $assembly->estimated_vs_real_minutes . ' min' : '--' }}</span>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="mt-auto flex flex-wrap items-center gap-2">
                            @if(!$assembly->started_at)
                                <form method="POST" action="{{ route('workshop.armados.start', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                        <i class="ri-play-fill"></i>
                                        <span>Iniciar</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->finished_at)
                                <form method="POST" action="{{ route('workshop.armados.finish', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);">
                                        <i class="ri-check-line"></i>
                                        <span>Finalizar</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->exit_at)
                                <form method="POST" action="{{ route('workshop.armados.exit', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-[11px] font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                        <i class="ri-external-link-line"></i>
                                        <span>Salida</span>
                                    </button>
                                </form>
                            @endif

                            @if(!$assembly->sales_movement_id)
                                <button type="button" class="flex-1 rounded-xl py-2.5 text-[11px] font-bold text-white transition-all hover:brightness-110 shadow-lg" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);" @click="$dispatch('open-massive-sale-modal'); selectedAssemblies = [{{ $assembly->id }}]">
                                    <i class="ri-shopping-cart-line mr-1"></i>Venta
                                </button>
                            @endif

                            <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('¿Eliminar este registro?')" class="flex items-center">
                                @csrf
                                @method('DELETE')
                                <div class="relative group">
                                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all hover:bg-red-600 hover:text-white hover:border-red-600" style="background-color: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #94A3B8;">
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
                                        <input type="checkbox" :value="{{ $assembly->id }}" x-model="selectedAssemblies" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 dark:text-white">#{{ str_pad($assembly->id, 6, '0', STR_PAD_LEFT) }}</span>
                                            <span class="text-[10px] text-gray-500">{{ optional($assembly->entry_at)->format('d/m/y') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 dark:text-gray-200 uppercase">{{ $assembly->model ?: 'Sin modelo' }}</span>
                                        <span class="text-xs text-gray-500 uppercase">{{ $assembly->brand_company }} · {{ $assembly->vehicle_type }}</span>
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
                    <select name="workshop_assembly_location_id" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione ubicación...</option>
                        @foreach($assemblyLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
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

    {{-- Modal para Venta Masiva --}}
    <x-ui.modal x-data="{ open: false }" @open-massive-sale-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Generar Venta de Armados</h3>
                    <p class="text-sm text-gray-500">Se procesarán <span class="font-bold text-purple-600" x-text="selectedAssemblies.length"></span> registros seleccionados.</p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.assemblies.massive_sale') }}" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @csrf
                {{-- Campos ocultos para IDs --}}
                <template x-for="id in selectedAssemblies" :key="id">
                    <input type="hidden" name="assembly_ids[]" :value="id">
                </template>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Datos del Cliente</label>
                        <select name="client_person_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                            <option value="">Seleccione un cliente...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Documento de Venta</label>
                        <select name="document_type_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Caja y Cobro (Opcional)</label>
                        <div class="grid grid-cols-1 gap-3">
                            <select name="cash_register_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                <option value="">No registrar pago (Solo venta)</option>
                                @foreach($cashRegisters as $cr)
                                    <option value="{{ $cr->id }}">Caja #{{ $cr->number }}</option>
                                @endforeach
                            </select>
                            <select name="payment_method_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->description }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Observaciones</label>
                        <textarea name="comment" rows="2" class="w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Notas sobre esta venta masiva..."></textarea>
                    </div>
                </div>

                <div class="md:col-span-2 mt-4">
                    <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 py-4 text-base font-bold text-white shadow-lg shadow-purple-200 transition-all hover:scale-[1.02] hover:shadow-xl active:scale-95 dark:shadow-none">
                        <i class="ri-shopping-cart-fill text-xl"></i>
                        <span>Confirmar y Procesar Venta</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-gray-400">
                        <i class="ri-information-line"></i> Esta acción actualizará los registros de armado y creará un nuevo movimiento de venta en el registro mensual.
                    </p>
                </div>
            </form>
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
</div>
@endsection
