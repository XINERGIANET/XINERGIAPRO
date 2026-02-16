@extends('layouts.app')

@section('content')
<div x-data="{
    costs: @js($costTable),
    selectedBrand: '',
    selectedType: '',
    unitCost: 0,
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
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.assemblies.export', ['month' => $month]) }}" style="background-color:#166534;color:#fff">
                <i class="ri-file-excel-2-line"></i><span>Exportar CSV</span>
            </x-ui.link-button>
        </div>

        <form method="GET" action="{{ route('workshop.assemblies.index') }}" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-5 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="brand_company" value="{{ $brandCompany }}" placeholder="Empresa/Marca" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="vehicle_type" value="{{ $vehicleType }}" placeholder="Tipo vehiculo" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.assemblies.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Tabla de costos por tipo</h2>
                    <x-ui.button size="xs" variant="primary" type="button" style="background-color:#244BB3;color:#fff" @click="$dispatch('open-cost-modal')">
                        <i class="ri-add-line"></i><span> añadir costos</span>
                    </x-ui.button>
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
                                    <td class="px-4 py-3 text-sm font-medium text-blue-600">{{ number_format((float) $cost->unit_cost, 2) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="$dispatch('edit-cost', {{ json_encode($cost) }})" class="text-blue-500 hover:text-blue-700" title="Editar">
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
                                <tr><td colspan="4" class="px-4 py-4 text-sm text-gray-500">Sin costos configurados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                <h2 class="mb-2 text-base font-semibold">Resumen mensual por tipo</h2>
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
                                    <td class="px-4 py-3 text-sm">{{ number_format((float) $row->total_cost, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-4 text-sm text-gray-500">Sin registros en el mes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <h2 class="mb-2 text-base font-semibold">Detalle de armados</h2>
            <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full min-w-[950px]">
                    <thead>
                        <tr>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">N°</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Marca</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Modelo</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cilindrada</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Color</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-center">Ingreso</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-center">Inicio</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-center">Fin</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-center">Salida</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">VIN</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assemblies as $assembly)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $loop->iteration + ($assemblies->currentPage() - 1) * $assemblies->perPage() }}</td>
                                <td class="px-4 py-3 text-xs">{{ $assembly->brand_company }}</td>
                                <td class="px-4 py-3 text-xs">{{ $assembly->vehicle_type }}</td>
                                <td class="px-4 py-3 text-xs">{{ $assembly->model }}</td>
                                <td class="px-4 py-3 text-xs">{{ $assembly->displacement }}</td>
                                <td class="px-4 py-3 text-xs">{{ $assembly->color }}</td>
                                <td class="px-4 py-3 text-xs text-center">{{ optional($assembly->entry_at)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-xs text-center">
                                    <span class="text-[10px] block">{{ optional($assembly->started_at)->format('d/m/Y H:i') }}</span>
                                    @if(!$assembly->started_at)
                                        <form method="POST" action="{{ route('workshop.armados.start', $assembly) }}">
                                            @csrf
                                            <button class="bg-[#244BB3] text-white px-2 py-1 rounded text-[10px] uppercase font-bold">Iniciar</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-center">
                                    <span class="text-[10px] block">{{ optional($assembly->finished_at)->format('d/m/Y H:i') }}</span>
                                    @if($assembly->started_at && !$assembly->finished_at)
                                        <form method="POST" action="{{ route('workshop.armados.finish', $assembly) }}">
                                            @csrf
                                            <button class="bg-green-600 text-white px-2 py-1 rounded text-[10px] uppercase font-bold">Fin</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-center">
                                    <span class="text-[10px] block">{{ optional($assembly->exit_at)->format('d/m/Y H:i') }}</span>
                                    @if($assembly->finished_at && !$assembly->exit_at)
                                        <form method="POST" action="{{ route('workshop.armados.exit', $assembly) }}">
                                            @csrf
                                            <button class="bg-orange-500 text-white px-2 py-1 rounded text-[10px] uppercase font-bold">Salida</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs font-mono">{{ $assembly->vin }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('Eliminar registro de armado?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-800" title="Eliminar">
                                                <i class="ri-delete-bin-line text-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-4 py-4 text-sm text-gray-500 text-center">Sin registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $assemblies->links() }}</div>
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
</div>
@endsection
