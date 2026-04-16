@extends('layouts.app')

@section('content')
<div x-data="{
    costs: @js($costTable),
    allVehicleTypes: @js($vehicleTypes),
    selectedBrand: @js(old('brand_company', $assembly->brand_company)),
    selectedType: @js(old('vehicle_type', $assembly->vehicle_type)),
    unitCost: @js(number_format((float) old('unit_cost', $assembly->unit_cost), 2, '.', '')),
    get availableBrands() {
        const current = this.selectedBrand ? [this.selectedBrand] : [];
        if (!this.selectedType) {
            return current.concat(this.costs.map(c => c.brand_company)).filter((v, i, a) => a.indexOf(v) === i);
        }
        return current.concat(this.costs
            .filter(c => c.vehicle_type === this.selectedType)
            .map(c => c.brand_company)
        ).filter((v, i, a) => a.indexOf(v) === i);
    },
    updateUnitCost() {
        if (!this.selectedBrand || !this.selectedType) {
            this.unitCost = 0;
            return;
        }
        const found = this.costs.find(c => c.brand_company === this.selectedBrand && c.vehicle_type === this.selectedType);
        this.unitCost = found ? parseFloat(found.unit_cost).toFixed(2) : this.unitCost;
    }
}">
    <x-common.page-breadcrumb pageTitle="Editar armado" />

    <x-common.component-card title="Editar armado" desc="Actualiza completamente la informacion del registro de armado.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('workshop.assemblies.update', $assembly) }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @if(request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de vehiculo</label>
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
                <input name="model" value="{{ old('model', $assembly->model) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: ELEGANCE S">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada</label>
                <input name="displacement" value="{{ old('displacement', $assembly->displacement) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: 110">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                <input name="color" value="{{ old('color', $assembly->color) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: GRIS">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">VIN / Codigo Motor</label>
                <input name="vin" value="{{ old('vin', $assembly->vin) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese VIN o codigo">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Guía de remisión</label>
                <input name="guia_remision" value="{{ old('guia_remision', $assembly->guia_remision) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Número de guía">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Cantidad</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity', $assembly->quantity) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Costo unitario</label>
                <input type="number" min="0" step="0.01" name="unit_cost" x-model="unitCost" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                <p class="mt-1 text-[10px] text-gray-500 italic">Puede ajustarlo manualmente si hace falta.</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Ubicacion del armado</label>
                <select name="workshop_assembly_location_id" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Seleccione ubicacion...</option>
                    @foreach($assemblyLocations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('workshop_assembly_location_id', $assembly->workshop_assembly_location_id) === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Tecnico responsable</label>
                <select name="responsible_technician_person_id" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Seleccione tecnico...</option>
                    @foreach($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected((string) old('responsible_technician_person_id', $assembly->responsible_technician_person_id) === (string) $technician->id)>{{ trim(($technician->first_name ?? '') . ' ' . ($technician->last_name ?? '')) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de ingreso</label>
                <input type="datetime-local" name="entry_at" value="{{ old('entry_at', optional($assembly->entry_at)->format('Y-m-d\\TH:i')) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de armado</label>
                <input type="date" name="assembled_at" value="{{ old('assembled_at', optional($assembly->assembled_at)->format('Y-m-d')) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Entrega estimada</label>
                <input type="datetime-local" name="estimated_delivery_at" value="{{ old('estimated_delivery_at', optional($assembly->estimated_delivery_at)->format('Y-m-d\\TH:i')) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Tiempo estimado (min)</label>
                <input type="number" min="0" name="estimated_minutes" value="{{ old('estimated_minutes', $assembly->estimated_minutes) }}" class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="120">
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Observaciones</label>
                <textarea name="notes" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Notas adicionales...">{{ old('notes', $assembly->notes) }}</textarea>
            </div>

            <div class="md:col-span-2 mt-2 flex gap-2">
                <x-ui.button type="submit" size="md" variant="primary" class="flex-1"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.assemblies.index', request('view_id') ? ['view_id' => request('view_id')] : []) }}" class="flex-1"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.link-button>
            </div>
        </form>
    </x-common.component-card>
</div>
@endsection
