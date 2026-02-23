@extends('layouts.app')

@section('content')
<div x-data="{
    costs: @js($costTable),
    selectedBrand: '',
    selectedType: '',
    unitCost: 0,
    selectedAssemblies: [],
    filteredTypes() {
        if (!this.selectedBrand) return [];
        return this.costs
            .filter(c => c.brand_company === this.selectedBrand)
            .map(c => c.vehicle_type);
    },
    updateUnitCost() {
        const found = this.costs.find(c => c.brand_company === this.selectedBrand && c.vehicle_type === this.selectedType);
        if (found) {
            this.unitCost = parseFloat(found.unit_cost).toFixed(2);
        }
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
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#244BB3;color:#fff" @click="$dispatch('open-costs-table-modal')">
                <i class="ri-settings-4-line"></i><span>Configuración de Costos</span>
            </x-ui.button>
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#63B7EC;color:#fff" @click="$dispatch('open-summary-modal')">
                <i class="ri-bar-chart-2-line"></i><span>Resumen por Tipo</span>
            </x-ui.button>
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
            <h2 class="mb-5 text-lg font-bold text-gray-800 dark:text-white/90 px-1">Detalle de armados</h2>
            
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($assemblies as $assembly)
                    @php
                        $statusText = 'PENDIENTE';
                        $statusClass = 'bg-slate-100 text-slate-500';
                        if($assembly->exit_at) {
                            $statusText = 'COMPLETADO';
                            $statusClass = 'bg-green-100 text-green-700';
                        } elseif($assembly->finished_at) {
                            $statusText = 'FINALIZADO';
                            $statusClass = 'bg-blue-100 text-blue-700';
                        } elseif($assembly->started_at) {
                            $statusText = 'EN PROCESO';
                            $statusClass = 'bg-orange-100 text-orange-700';
                        }
                    @endphp
                    <div class="relative flex flex-col rounded-[1.6rem] border border-slate-200 bg-[#F1F5F9] p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-800 dark:bg-white/[0.02]">
                        <div class="absolute -top-px left-0 right-0 h-1 rounded-t-[1.6rem] bg-gradient-to-r from-[#1E293B] to-[#334155]"></div>
                        
                        <!-- Checkbox -->
                        <div class="absolute right-4 top-4 z-10">
                            <input type="checkbox" :value="{{ $assembly->id }}" x-model="selectedAssemblies" class="h-5 w-5 rounded-lg border-2 border-gray-300 text-[#244BB3] focus:ring-[#244BB3] bg-white cursor-pointer transition-all hover:border-[#244BB3]">
                        </div>

                        <!-- Header -->
                        <div class="mb-3 mt-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Orden de Armado</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-base font-black text-[#1E293B] dark:text-white">#{{ str_pad($assembly->id, 6, '0', STR_PAD_LEFT) }}</h3>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-tight {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                    @if($assembly->sales_movement_id)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-tight text-white shadow-sm" style="background-color: #10B981;">
                                            <i class="ri-checkbox-circle-line text-[10px]"></i> PAGADO
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-tight border" style="background-color: #FFFBEB; color: #D97706; border-color: #FDE68A;">
                                            <i class="ri-time-line text-[10px]"></i> NO PAGADO
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Main Vehicle Info -->
                        <div class="mb-3 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm dark:bg-gray-800 border border-slate-200/50">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10">
                                <i class="ri-motorbike-line text-xl"></i>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <h4 class="truncate text-sm font-black text-[#1E293B] dark:text-white uppercase">{{ $assembly->model ?: 'MODELO NO REGISTRADO' }}</h4>
                                <p class="truncate font-mono text-[10px] text-slate-400 font-bold italic">{{ $assembly->vin ?: 'SIN VIN ASIGNADO' }}</p>
                            </div>
                        </div>

                        <!-- Info Grid -->
                        <div class="mb-3 grid grid-cols-2 gap-2.5">
                            <div class="rounded-xl bg-white/70 p-2.5 dark:bg-white/5 border border-white">
                                <span class="block text-[9px] font-bold uppercase text-slate-400 mb-0.5">Marca</span>
                                <span class="block truncate text-xs font-bold text-slate-700 dark:text-slate-300">{{ $assembly->brand_company }}</span>
                            </div>
                            <div class="rounded-xl bg-white/70 p-2.5 dark:bg-white/5 border border-white">
                                <span class="block text-[9px] font-bold uppercase text-slate-400 mb-0.5">Ingreso</span>
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ optional($assembly->entry_at)->format('d/m/Y') ?: '--' }}</span>
                            </div>
                        </div>

                        <!-- Values -->
                        <div class="mb-4 grid grid-cols-3 gap-2">
                            <div class="flex flex-col items-center justify-center rounded-xl bg-white py-2 shadow-xs border border-gray-100 dark:bg-gray-800">
                                <span class="text-[8px] font-bold uppercase text-slate-400 mb-0.5">Cant</span>
                                <span class="text-sm font-black text-slate-800 dark:text-white">{{ $assembly->quantity }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center rounded-xl bg-emerald-50/50 py-2 border border-emerald-100 dark:bg-emerald-900/5">
                                <span class="text-[8px] font-bold uppercase text-emerald-600/70 mb-0.5">Uni</span>
                                <span class="text-sm font-black text-emerald-600">S/{{ number_format($assembly->unit_cost, 0) }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center rounded-xl bg-orange-50/50 py-2 border border-orange-100 dark:bg-orange-900/5">
                                <span class="text-[8px] font-bold uppercase text-orange-600/70 mb-0.5">Tot</span>
                                <span class="text-sm font-black text-orange-600">S/{{ number_format($assembly->total_cost, 0) }}</span>
                            </div>
                        </div>

                        <!-- Bottom Specs -->
                        <div class="flex items-center gap-2 mb-4 px-1">
                            @if($assembly->displacement)
                                <span class="text-[10px] font-bold text-slate-500 bg-white/80 dark:bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-200/50 shadow-xs">{{ $assembly->displacement }}cc</span>
                            @endif
                            @if($assembly->color)
                                <span class="text-[10px] font-bold text-slate-500 bg-white/80 dark:bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-200/50 shadow-xs uppercase">{{ $assembly->color }}</span>
                            @endif
                        </div>

                        <!-- Action Bar -->
                        <div class="mt-auto flex items-center gap-2">
                            @if(!$assembly->started_at)
                                <form method="POST" action="{{ route('workshop.armados.start', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold text-white shadow-md transition-all hover:opacity-90 active:scale-95" style="background-color: #1e293b;">
                                        <i class="ri-play-fill text-sm"></i>
                                        <span>Iniciar Armado</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->finished_at)
                                <form method="POST" action="{{ route('workshop.armados.finish', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold text-white shadow-md transition-all hover:opacity-90 active:scale-95" style="background-color: #10b981;">
                                        <i class="ri-check-line text-sm"></i>
                                        <span>Finalizar Tarea</span>
                                    </button>
                                </form>
                            @elseif(!$assembly->exit_at)
                                <form method="POST" action="{{ route('workshop.armados.exit', $assembly) }}" class="flex-1">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold text-white shadow-md transition-all hover:opacity-90 active:scale-95" style="background-color: #f59e0b;">
                                        <i class="ri-external-link-line text-sm"></i>
                                        <span>Registrar Salida</span>
                                    </button>
                                </form>
                            @else
                                <div class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-100 py-2.5 text-xs font-bold text-slate-400 dark:bg-slate-700/50 border border-slate-200">
                                    <i class="ri-checkbox-circle-fill text-sm"></i>
                                    <span>Tarea Completada</span>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('¿Eliminar este registro?')" class="flex items-center">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm transition-all hover:bg-red-50 hover:text-red-500 hover:border-red-100 dark:bg-gray-800 dark:border-gray-700">
                                    <i class="ri-delete-bin-6-line text-lg"></i>
                                </button>
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
            
            <div class="mt-8">
                {{ $assemblies->links() }}
            </div>
        </div>
    </x-common.component-card>

    {{-- Modal para añadir/editar costos --}}
    <x-ui.modal x-data="{ 
        open: false, 
        editMode: false,
        action: '{{ route('workshop.assemblies.costs.store') }}',
        formData: {
            brand_company: '',
            vehicle_type: '',
            unit_cost: ''
        },
        editCost(cost) {
            this.editMode = true;
            this.action = '{{ url('/admin/taller/armados/costos') }}/' + cost.id;
            this.formData.brand_company = cost.brand_company;
            this.formData.vehicle_type = cost.vehicle_type;
            this.formData.unit_cost = parseFloat(cost.unit_cost).toFixed(2);
            this.open = true;
        },
        resetForm() {
            this.editMode = false;
            this.action = '{{ route('workshop.assemblies.costs.store') }}';
            this.formData = { brand_company: '', vehicle_type: '', unit_cost: '' };
        }
    }" 
    @open-cost-modal.window="resetForm(); open = true" 
    @edit-cost.window="editCost($event.detail)"
    :isOpen="false" :showCloseButton="false" class="max-w-md">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="editMode ? 'Editar costo' : 'Configurar nuevo costo'"></h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" :action="action" class="grid grid-cols-1 gap-4">
                @csrf
                <template x-if="editMode">
                    @method('PUT')
                </template>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Empresa/Marca</label>
                    <input name="brand_company" x-model="formData.brand_company" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: GP MOTOS, MAVILA, HONDA" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de Vehículo</label>
                    <input name="vehicle_type" x-model="formData.vehicle_type" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: MOTOCICLETA, TRIMOTO" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Costo Unitario</label>
                    <input type="number" min="0" step="0.01" name="unit_cost" x-model="formData.unit_cost" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="0.00" required>
                </div>
                <div class="flex items-center gap-2" x-show="!editMode">
                    <input type="checkbox" name="apply_to_all_branches" id="apply_to_all" value="1" class="rounded border-gray-300">
                    <label for="apply_to_all" class="text-xs text-gray-600 italic">Aplicable a todas las sucursales</label>
                </div>
                <div class="mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" class="w-full"><i class="ri-save-line"></i><span x-text="editMode ? 'Actualizar Costo' : 'Guardar Costo'"></span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    {{-- Modal para nuevo armado --}}
    <x-ui.modal x-data="{ open: false }" @open-assembly-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nuevo registro de armado</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.assemblies.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Empresa/Marca</label>
                    <select name="brand_company" x-model="selectedBrand" @change="selectedType = ''; unitCost = 0" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione marca...</option>
                        @foreach($costTable->pluck('brand_company')->unique() as $brand)
                            <option value="{{ $brand }}">{{ $brand }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de Vehículo</label>
                    <select name="vehicle_type" x-model="selectedType" @change="updateUnitCost()" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione tipo...</option>
                        <template x-for="type in filteredTypes()" :key="type">
                            <option :value="type" x-text="type"></option>
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
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de Ingreso</label>
                    <input type="date" name="entry_at" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de Armado (Planificado)</label>
                    <input type="date" name="assembled_at" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ now()->toDateString() }}" required>
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

    {{-- Modal para Tabla de Costos --}}
    <x-ui.modal x-data="{ open: false }" @open-costs-table-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tabla de costos por tipo</h3>
                <div class="flex items-center gap-3">
                    <x-ui.button size="xs" variant="primary" type="button" style="background-color:#244BB3;color:#fff" @click="$dispatch('open-cost-modal'); open = false">
                        <i class="ri-add-line"></i><span> añadir costos</span>
                    </x-ui.button>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full min-w-[500px]">
                    <thead>
                        <tr>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Empresa/Marca</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Costo Unitario</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costTable as $cost)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3 text-sm">{{ $cost->brand_company }}</td>
                                <td class="px-4 py-3 text-sm">{{ $cost->vehicle_type }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-blue-600">S/{{ number_format((float) $cost->unit_cost, 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="$dispatch('edit-cost', {{ json_encode($cost) }}); open = false" class="text-blue-500 hover:text-blue-700" title="Editar">
                                            <i class="ri-pencil-line"></i>
                                        </button>
                                        <form method="POST" action="{{ route('workshop.assemblies.costs.destroy', $cost) }}" onsubmit="return confirm('Eliminar este costo configurado?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-4 text-sm text-gray-500 text-center">Sin costos configurados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-ui.modal>

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
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Empresa/Marca</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cantidad</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Costo Total</th>
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
</div>
@endsection
