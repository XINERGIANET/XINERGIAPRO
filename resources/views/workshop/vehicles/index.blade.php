@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Taller - Vehiculos</h1>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" class="rounded border px-3 py-2" placeholder="Buscar por placa, marca, modelo">
        <button class="rounded bg-blue-600 px-3 py-2 text-white">Buscar</button>
    </form>

    <form method="POST" action="{{ route('workshop.vehicles.store') }}" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-4">
        @csrf
        <select name="client_person_id" class="rounded border px-3 py-2" required>
            <option value="">Cliente</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
            @endforeach
        </select>
        <input name="type" class="rounded border px-3 py-2" value="moto" required>
        <input name="brand" class="rounded border px-3 py-2" placeholder="Marca" required>
        <input name="model" class="rounded border px-3 py-2" placeholder="Modelo" required>
        <input name="year" class="rounded border px-3 py-2" placeholder="Año">
        <input name="color" class="rounded border px-3 py-2" placeholder="Color">
        <input name="plate" class="rounded border px-3 py-2" placeholder="Placa">
        <input name="vin" class="rounded border px-3 py-2" placeholder="VIN">
        <input name="engine_number" class="rounded border px-3 py-2" placeholder="Nro motor">
        <input name="chassis_number" class="rounded border px-3 py-2" placeholder="Nro chasis">
        <input name="serial_number" class="rounded border px-3 py-2" placeholder="Serial">
        <input name="current_mileage" type="number" min="0" class="rounded border px-3 py-2" placeholder="Kilometraje">
        <button class="rounded bg-emerald-600 px-3 py-2 text-white md:col-span-4">Registrar vehiculo</button>
    </form>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">ID</th>
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-left">Vehiculo</th>
                    <th class="p-2 text-left">Placa</th>
                    <th class="p-2 text-left">KM</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vehicles as $vehicle)
                    <tr class="border-t">
                        <td class="p-2">{{ $vehicle->id }}</td>
                        <td class="p-2">{{ $vehicle->client?->first_name }} {{ $vehicle->client?->last_name }}</td>
                        <td class="p-2">{{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->type }})</td>
                        <td class="p-2">{{ $vehicle->plate }}</td>
                        <td class="p-2">{{ $vehicle->current_mileage }}</td>
                        <td class="p-2">
                            <form method="POST" action="{{ route('workshop.vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Eliminar vehiculo?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded bg-red-600 px-2 py-1 text-white">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $vehicles->links() }}
</div>
@endsection

