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

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-vehicle-modal')">
                <i class="ri-add-line"></i><span>Nuevo vehiculo</span>
            </x-ui.button>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input name="search" value="{{ $search }}" class="h-11 min-w-[280px] flex-1 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Buscar por placa, marca, modelo, VIN">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Buscar</button>
            <a href="{{ route('workshop.vehicles.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center">Limpiar</a>
        </form>

        <div class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">ID</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Vehiculo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Placa</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">KM</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $vehicle->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $vehicle->client?->first_name }} {{ $vehicle->client?->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->type }})</td>
                            <td class="px-4 py-3 text-sm">{{ $vehicle->plate }}</td>
                            <td class="px-4 py-3 text-sm">{{ $vehicle->current_mileage }}</td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" action="{{ route('workshop.vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Eliminar vehiculo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-medium text-white">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-4 text-sm text-gray-500">Sin vehiculos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $vehicles->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" @open-vehicle-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar vehiculo</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.vehicles.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-4">
                @csrf
                <select name="client_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="">Cliente</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                    @endforeach
                </select>
                <input name="type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="moto" required>
                <input name="brand" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca" required>
                <input name="model" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo" required>
                <input name="year" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Anio">
                <input name="color" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                <input name="plate" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Placa">
                <input name="vin" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                <input name="engine_number" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro motor">
                <input name="chassis_number" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro chasis">
                <input name="serial_number" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                <input name="current_mileage" type="number" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Kilometraje">
                <div class="md:col-span-4 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
