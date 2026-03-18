@extends('layouts.app')

@section('content')
<div x-data="{}">
<script>
    window.allAssemblyCosts = @js($allCosts);
</script>
    <x-common.page-breadcrumb pageTitle="Tipos de Vehiculo" />

    <x-common.component-card title="Tipos de Vehiculo" desc="Gestiona tipos de vehiculo para el taller.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        {{-- Top Bar: Filtros y Botonera --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            {{-- Formulario de Búsqueda y Paginado --}}
            <form method="GET" action="{{ route('workshop.vehicle-types.index') }}" class="flex flex-1 flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <select name="per_page" onchange="this.form.submit()" class="h-11 rounded-xl border-gray-200 bg-white px-4 text-sm font-medium shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500">
                        <option value="10" @selected($perPage == 10)>10 / página</option>
                        <option value="20" @selected($perPage == 20)>20 / página</option>
                        <option value="50" @selected($perPage == 50)>50 / página</option>
                        <option value="100" @selected($perPage == 100)>100 / página</option>
                    </select>
                </div>

                <div class="relative flex flex-1 min-w-[300px]">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="ri-search-line"></i>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" 
                        class="h-11 w-full rounded-xl border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500" 
                        placeholder="Buscar por nombre...">
                </div>

                <x-ui.button type="submit" variant="primary" style="background-color: #1E293B; color: #fff" class="h-11 px-6 rounded-xl">
                    <i class="ri-search-line mr-2"></i>Buscar
                </x-ui.button>

                <a href="{{ route('workshop.vehicle-types.index') }}" 
                    class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-6 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    <i class="ri-refresh-line mr-2"></i>Limpiar
                </a>
            </form>

            {{-- Botón Nuevo --}}
            <x-ui.button size="md" variant="primary" type="button" 
                style="background-color: #22C55E; color: #fff" 
                class="h-11 px-6 rounded-xl font-bold shadow-lg shadow-green-100 hover:shadow-xl active:scale-95"
                @click="$dispatch('open-vehicle-type-modal')">
                <i class="ri-add-line mr-1 text-lg"></i><span>Nuevo tipo</span>
            </x-ui.button>
        </div>

        {{-- Tabla de Resultados --}}
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[800px] border-collapse">
                <thead>
                    <tr class="bg-[#334155] text-white">
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider first:rounded-tl-2xl">ID</th>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Tipo de Vehículo</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Orden</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Estado</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Origen</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider last:rounded-tr-2xl">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($types as $type)
                        <tr class="group transition-colors hover:bg-gray-50/50 dark:hover:bg-white/5">
                            <td class="px-3 py-3 text-center text-sm font-medium text-gray-400">#{{ $type->id }}</td>
                            <td class="px-3 py-3">
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase">{{ $type->name }}</span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500">
                                    {{ $type->order_num }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if($type->active)
                                    <x-ui.badge variant="light" color="success" class="text-[10px] uppercase font-bold">Activo</x-ui.badge>
                                @else
                                    <x-ui.badge variant="light" color="danger" class="text-[10px] uppercase font-bold">Inactivo</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if($type->company_id)
                                    <x-ui.badge variant="light" color="info" class="text-[10px] uppercase font-bold">Sucursal</x-ui.badge>
                                @else
                                    <x-ui.badge variant="light" color="warning" class="text-[10px] uppercase font-bold">Global</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- Botón Costos --}}
                                    <div class="relative group/tooltip">
                                        <x-ui.button
                                            size="icon"
                                            variant="primary"
                                            type="button"
                                            @click="$dispatch('open-vehicle-type-costs-modal', { id: {{ $type->id }}, name: '{{ $type->name }}' })"
                                            className="rounded-xl"
                                            style="background-color: #7C3AED; color: #FFFFFF;"
                                            aria-label="Costos de armado"
                                        >
                                            <i class="ri-briefcase-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover/tooltip:opacity-100 z-[100] shadow-xl">
                                            Costos de Armado
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    @if($type->company_id)
                                        {{-- Botón Inventario --}}
                                        <div class="relative group/tooltip">
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="button"
                                                onclick="window.location='{{ route('workshop.vehicle-types.inventory.edit', $type) }}'"
                                                className="rounded-xl"
                                                style="background-color: #10B981; color: #FFFFFF;"
                                                aria-label="Inventario"
                                            >
                                                <i class="ri-list-check-2"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover/tooltip:opacity-100 z-[100] shadow-xl">
                                                Configurar inventario
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>
                                    @endif

                                    @if($type->company_id)
                                        {{-- Botón Editar --}}
                                        <div class="relative group/tooltip">
                                            <x-ui.button
                                                size="icon"
                                                variant="edit"
                                                type="button"
                                                @click="$dispatch('open-edit-vehicle-type-modal', {{ $type->id }})"
                                                className="rounded-xl"
                                                style="background-color: #FBBF24; color: #111827;"
                                                aria-label="Editar"
                                            >
                                                <i class="ri-pencil-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover/tooltip:opacity-100 z-[100] shadow-xl">
                                                Editar registro
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>

                                        {{-- Botón Eliminar --}}
                                        <form method="POST" action="{{ route('workshop.vehicle-types.destroy', $type) }}" 
                                            class="js-swal-delete relative group/tooltip"
                                            data-swal-title="¿Eliminar Tipo?"
                                            data-swal-text="Esta acción no se puede deshacer."
                                            data-swal-confirm="Sí, eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button
                                                size="icon"
                                                variant="eliminate"
                                                type="submit"
                                                className="rounded-xl"
                                                style="background-color: #EF4444; color: #FFFFFF;"
                                                aria-label="Eliminar"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover/tooltip:opacity-100 z-[100] shadow-xl">
                                                Eliminar definitivo
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </form>
                                    @else
                                        <span class="inline-flex h-9 items-center rounded-lg bg-gray-50 px-4 text-[10px] font-bold uppercase tracking-wider text-gray-400 border border-gray-100">
                                            <i class="ri-lock-line mr-1"></i> Protegido
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center text-gray-400 italic">No se encontraron tipos de vehículos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bottom Bar: Paginación y Resumen --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-t border-gray-100 pt-6">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $types->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $types->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $types->total() }}</span>
            </div>
            <div class="flex-none">
                {{ $types->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-vehicle-type-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-2xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar tipo de vehiculo</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('workshop.vehicle-types.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @csrf
                <input name="name" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre tipo" required>
                <input name="order_num" type="number" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Orden" value="0">
                <label class="md:col-span-2 inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-gray-300">
                    Activo
                </label>
                <div class="md:col-span-2 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($types as $type)
        @if($type->company_id)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-vehicle-type-modal.window="if ($event.detail === {{ $type->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-2xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de vehiculo</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('workshop.vehicle-types.update', $type) }}" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <input name="name" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ $type->name }}" placeholder="Nombre tipo" required>
                    <input name="order_num" type="number" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ $type->order_num }}" placeholder="Orden">
                    <label class="md:col-span-2 inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="active" value="1" @checked($type->active) class="h-4 w-4 rounded border-gray-300">
                        Activo
                    </label>
                    <div class="md:col-span-2 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
        @endif
    @endforeach
    {{-- Modal para Gestionar Costos por Tipo --}}
    <x-ui.modal x-data="{ 
        open: false,
        allCosts: window.allAssemblyCosts || [],
        typeId: null,
        typeName: '',
        loading: false,
        editMode: false,
        editingCostId: null,
        form: {
            brand_company: '',
            unit_cost: '',
            apply_to_all_branches: false
        },
        get filteredCosts() {
            if (!this.typeName) return [];
            return this.allCosts.filter(c => c.vehicle_type.toLowerCase() === this.typeName.toLowerCase());
        },
        initModal(data) {
            this.typeId = data.id;
            this.typeName = data.name;
            this.resetForm();
            this.open = true;
        },
        resetForm() {
            this.editMode = false;
            this.editingCostId = null;
            this.form.brand_company = '';
            this.form.unit_cost = '';
            this.form.apply_to_all_branches = false;
        },
        editCost(cost) {
            this.editMode = true;
            this.editingCostId = cost.id;
            this.form.brand_company = cost.brand_company;
            this.form.unit_cost = parseFloat(cost.unit_cost).toFixed(2);
            this.form.apply_to_all_branches = cost.branch_id === null;
        },
        async submitForm() {
            if (this.loading) return;
            this.loading = true;
            
            const url = this.editMode 
                ? `/admin/taller/armados/costos/${this.editingCostId}` 
                : '/admin/taller/armados/costos';
            
            const payload = {
                brand_company: this.form.brand_company,
                unit_cost: this.form.unit_cost,
                apply_to_all_branches: this.form.apply_to_all_branches,
                vehicle_type: this.typeName
            };
            
            if (this.editMode) payload._method = 'PUT';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    // Buscar si ya existe para actualizar o añadir
                    const existingIdx = this.allCosts.findIndex(c => 
                        c.id === result.cost.id || 
                        (c.brand_company.toLowerCase() === result.cost.brand_company.toLowerCase() && 
                         c.vehicle_type.toLowerCase() === result.cost.vehicle_type.toLowerCase() &&
                         c.branch_id === result.cost.branch_id)
                    );

                    if (existingIdx !== -1) {
                        this.allCosts[existingIdx] = result.cost;
                    } else {
                        this.allCosts.push(result.cost);
                    }
                    
                    // Forzar reactividad reemplazando la referencia
                    this.allCosts = [...this.allCosts];
                    this.resetForm();
                } else {
                    alert(result.message || 'Error al guardar');
                }
            } catch (e) {
                console.error(e);
                alert('Ocurrio un error en el servidor');
            } finally {
                this.loading = false;
            }
        },
        async deleteCost(id) {
            if (!confirm('¿Eliminar este costo?')) return;
            try {
                const response = await fetch(`/admin/taller/armados/costos/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    // Usamos != para evitar problemas de tipos (string vs number)
                    this.allCosts = [...this.allCosts.filter(c => c.id != id)];
                }
            } catch (e) {
                console.error(e);
            }
        }
    }" 
    @open-vehicle-type-costs-modal.window="initModal($event.detail)"
    :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Costos de Armado: <span class="text-brand-500 uppercase" x-text="typeName"></span></h3>
                    <p class="text-sm text-gray-500">Configura los costos base por empresa/marca para este tipo.</p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Formulario rapido -->
                <div class="lg:col-span-1">
                    <form @submit.prevent="submitForm()" class="space-y-4 rounded-2xl border border-gray-100 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400" x-text="editMode ? 'MODO EDICIÓN' : 'NUEVO COSTO'"></label>
                            <button type="button" x-show="editMode" @click="resetForm()" class="text-[10px] text-red-500 hover:underline">Cancelar</button>
                        </div>
                        
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-400">Empresa / Marca</label>
                            <input x-model="form.brand_company" class="w-full h-11 rounded-xl border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Ej: HONDA, MAVILA" required>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-400">Costo Unitario (S/)</label>
                            <input type="number" step="0.01" min="0" x-model="form.unit_cost" class="w-full h-11 rounded-xl border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="0.00" required>
                        </div>

                        <div class="flex items-center gap-2" x-show="!editMode">
                            <input type="checkbox" x-model="form.apply_to_all_branches" id="apply_all" class="rounded border-gray-300 text-brand-500">
                            <label for="apply_all" class="text-[10px] text-gray-500 italic">Aplicar a todas las sucursales</label>
                        </div>

                        <x-ui.button type="submit" size="md" variant="primary" class="w-full" ::disabled="loading">
                            <template x-if="!loading">
                                <span>
                                    <i :class="editMode ? 'ri-save-line mr-1' : 'ri-add-line mr-1'"></i>
                                    <span x-text="editMode ? 'Guardar Cambios' : 'Añadir Costo'"></span>
                                </span>
                            </template>
                            <template x-if="loading">
                                <span>Procesando...</span>
                            </template>
                        </x-ui.button>
                    </form>
                </div>

                <!-- Tabla de costos existentes -->
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800 text-gray-400 font-bold uppercase text-[10px]">
                                    <th class="px-4 py-3 text-left">Empresa/Marca</th>
                                    <th class="px-4 py-3 text-right">Costo</th>
                                    <th class="px-4 py-3 text-center w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-if="filteredCosts.length === 0">
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-400 italic">No hay costos configurados para este tipo.</td>
                                    </tr>
                                </template>
                                <template x-for="cost in filteredCosts" :key="cost.id">
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-700 dark:text-gray-300" x-text="cost.brand_company"></div>
                                            <div x-show="cost.branch_id === null" class="text-[9px] text-blue-500 font-bold">GLOBAL</div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white" x-text="'S/' + parseFloat(cost.unit_cost).toFixed(2)"></td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-center gap-1">
                                                <button type="button" @click="editCost(cost)" class="flex h-8 w-8 items-center justify-center rounded-lg text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button type="button" @click="deleteCost(cost.id)" class="flex h-8 w-8 items-center justify-center rounded-lg text-red-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-ui.modal>
</div>
@endsection
