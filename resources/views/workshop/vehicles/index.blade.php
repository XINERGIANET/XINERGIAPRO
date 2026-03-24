@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Vehiculos Taller" />

    <x-common.component-card title="Vehiculos" desc="Administra vehiculos asociados a clientes del taller.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Barra de Herramientas Premium (Estilo solicitado) --}}
        <form method="GET" action="{{ route('workshop.vehicles.index') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
            {{-- Selector de Registros --}}
            <div class="flex items-center gap-2">
                <x-form.select-autocomplete
                    name="per_page"
                    :value="$per_page ?? 10"
                    :options="collect([10, 25, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / pág'])->values()->all()"
                    placeholder="Por página"
                    :submit-on-change="true"
                    inputClass="h-11 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
            </div>

            {{-- Buscador Principal --}}
            <div class="relative flex-1 min-w-0 sm:min-w-[300px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-lg text-gray-400"></i>
                </div>
                <input 
                    name="search" 
                    value="{{ $search ?? '' }}" 
                    class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-blue-500 focus:ring-blue-500 placeholder:text-gray-400" 
                    placeholder="Buscar por placa, marca, modelo, VIN..."
                >
            </div>

            {{-- Acciones del Formulario --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#334155] px-6 text-sm font-bold text-white shadow-lg shadow-blue-100 transition-all hover:brightness-110 active:scale-95">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </button>
                <a href="{{ route('workshop.vehicles.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    Limpiar
                </a>
            </div>

            {{-- Botón Nuevo (Al final a la derecha) --}}
            <div class="ml-auto">
                <x-ui.button 
                    size="md" 
                    variant="primary" 
                    type="button" 
                    class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" 
                    style="background-color:#00A389;color:#fff" 
                    @click="$dispatch('open-vehicle-modal')"
                >
                    <i class="ri-add-line text-lg"></i>
                    <span>Nuevo vehiculo</span>
                </x-ui.button>
            </div>
        </form>

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">ID</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Vehiculo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Placa</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Cilindrada</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">KM</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm text-center">{{ $vehicle->id }}</td>
                            <td class="px-4 py-3 text-sm text-center uppercase">{{ $vehicle->client?->first_name }} {{ $vehicle->client?->last_name }}</td>
                            <td class="px-4 py-3 text-sm text-center uppercase">{{ $vehicle->brand }} {{ $vehicle->model }} ({{ ucfirst($vehicle->vehicleType?->name ?? $vehicle->type) }})</td>
                            <td class="px-4 py-3 text-sm text-center font-bold">{{ $vehicle->plate ?: '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $vehicle->engine_displacement_cc ? number_format((int) $vehicle->engine_displacement_cc) . ' cc' : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ number_format($vehicle->current_mileage) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="edit"
                                            type="button"
                                            @click="$dispatch('open-edit-vehicle-modal', {{ $vehicle->id }})"
                                            className="rounded-xl"
                                            style="background-color: #FBBF24; color: #111827;"
                                            aria-label="Editar"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Editar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('workshop.vehicles.destroy', $vehicle) }}"
                                        class="relative group js-swal-delete"
                                        data-swal-title="Eliminar vehiculo?"
                                        data-swal-text="Se eliminara este vehiculo. Esta accion no se puede deshacer."
                                        data-swal-confirm="Si, eliminar"
                                        data-swal-cancel="Cancelar"
                                        data-swal-confirm-color="#ef4444"
                                        data-swal-cancel-color="#6b7280"
                                    >
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
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Eliminar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-4 text-sm text-gray-500 text-center">Sin vehiculos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $vehicles->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $vehicles->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $vehicles->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $vehicles->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-vehicle-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar vehiculo</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.vehicles.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
                    <x-form.select-autocomplete
                        name="client_person_id"
                        :value="old('client_person_id', '')"
                        :options="collect($clients ?? [])->map(fn($c) => ['value' => $c->id, 'label' => trim($c->first_name . ' ' . $c->last_name)])->prepend(['value' => '', 'label' => 'Seleccione cliente'])->values()->all()"
                        placeholder="Seleccione cliente"
                        :required="true"
                        inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de vehiculo</label>
                    <x-form.select-autocomplete
                        name="vehicle_type_id"
                        :value="old('vehicle_type_id', optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id ?? '')"
                        :options="collect($vehicleTypes ?? [])->map(fn($v) => ['value' => $v->id, 'label' => ucfirst($v->name)])->values()->all()"
                        placeholder="Tipo"
                        :required="true"
                        inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Marca</label>
                    <input name="brand" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                    <input name="model" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Año</label>
                    <input name="year" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Anio">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                    <input name="color" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Placa</label>
                    <input name="plate" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Placa">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">VIN</label>
                    <input name="vin" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nro motor</label>
                    <input name="engine_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro motor">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nro chasis</label>
                    <input name="chassis_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro chasis">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Serial</label>
                    <input name="serial_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Kilometraje</label>
                    <input name="current_mileage" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Kilometraje">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada (cc)</label>
                    <input name="engine_displacement_cc" type="number" min="1" max="5000" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej: 250" value="{{ old('engine_displacement_cc') }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Vencimiento SOAT</label>
                    <input type="date" name="soat_vencimiento" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700" value="{{ old('soat_vencimiento') }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Vencimiento Rev. Técnica</label>
                    <input type="date" name="revision_tecnica_vencimiento" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700" value="{{ old('revision_tecnica_vencimiento') }}">
                </div>
                <div class="md:col-span-4 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($vehicles as $vehicle)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-vehicle-modal.window="if ($event.detail === {{ $vehicle->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar vehiculo</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.vehicles.update', $vehicle) }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
                        <x-form.select-autocomplete
                            name="client_person_id"
                            :value="$vehicle->client_person_id"
                            :options="collect($clients ?? [])->map(fn($c) => ['value' => $c->id, 'label' => trim($c->first_name . ' ' . $c->last_name)])->values()->all()"
                            placeholder="Cliente"
                            :required="true"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de vehiculo</label>
                        <x-form.select-autocomplete
                            name="vehicle_type_id"
                            :value="old('vehicle_type_id', $vehicle->vehicle_type_id)"
                            :options="collect($vehicleTypes ?? [])->map(fn($v) => ['value' => $v->id, 'label' => ucfirst($v->name)])->values()->all()"
                            placeholder="Tipo"
                            :required="true"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Marca</label>
                        <input name="brand" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->brand }}" placeholder="Marca" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                        <input name="model" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->model }}" placeholder="Modelo" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Año</label>
                        <input name="year" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->year }}" placeholder="Anio">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                        <input name="color" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->color }}" placeholder="Color">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Placa</label>
                        <input name="plate" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->plate }}" placeholder="Placa">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">VIN</label>
                        <input name="vin" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->vin }}" placeholder="VIN">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro motor</label>
                        <input name="engine_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->engine_number }}" placeholder="Nro motor">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro chasis</label>
                        <input name="chassis_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->chassis_number }}" placeholder="Nro chasis">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Serial</label>
                        <input name="serial_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ $vehicle->serial_number }}" placeholder="Serial">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kilometraje</label>
                        <input name="current_mileage" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ (float) $vehicle->current_mileage }}" placeholder="Kilometraje">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada (cc)</label>
                        <input name="engine_displacement_cc" type="number" min="1" max="5000" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="{{ old('engine_displacement_cc', $vehicle->engine_displacement_cc) }}" placeholder="Ej: 250">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Vencimiento SOAT</label>
                        <input type="date" name="soat_vencimiento" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700" value="{{ old('soat_vencimiento', $vehicle->soat_vencimiento?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Vencimiento Rev. Técnica</label>
                        <input type="date" name="revision_tecnica_vencimiento" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700" value="{{ old('revision_tecnica_vencimiento', $vehicle->revision_tecnica_vencimiento?->format('Y-m-d')) }}">
                    </div>
                    <div class="md:col-span-4 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
@endsection
