@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Taller - Agenda / Citas</h1>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="flex flex-wrap gap-2">
        <input type="date" name="from" value="{{ $from }}" class="rounded border px-3 py-2">
        <input type="date" name="to" value="{{ $to }}" class="rounded border px-3 py-2">
        <button class="rounded bg-blue-600 px-3 py-2 text-white">Filtrar</button>
    </form>

    <form method="POST" action="{{ route('workshop.appointments.store') }}" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-3">
        @csrf
        <select name="vehicle_id" class="rounded border px-3 py-2" required>
            <option value="">Vehiculo</option>
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
            @endforeach
        </select>
        <select name="client_person_id" class="rounded border px-3 py-2" required>
            <option value="">Cliente</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
            @endforeach
        </select>
        <select name="technician_person_id" class="rounded border px-3 py-2">
            <option value="">Tecnico</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
            @endforeach
        </select>
        <input type="datetime-local" name="start_at" class="rounded border px-3 py-2" required>
        <input type="datetime-local" name="end_at" class="rounded border px-3 py-2" required>
        <input name="reason" class="rounded border px-3 py-2" placeholder="Motivo" required>
        <input name="notes" class="rounded border px-3 py-2 md:col-span-2" placeholder="Notas">
        <select name="status" class="rounded border px-3 py-2">
            <option value="pending">pending</option>
            <option value="confirmed">confirmed</option>
            <option value="arrived">arrived</option>
            <option value="cancelled">cancelled</option>
            <option value="no_show">no_show</option>
        </select>
        <button class="rounded bg-emerald-600 px-3 py-2 text-white md:col-span-3">Registrar cita</button>
    </form>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Inicio</th>
                    <th class="p-2 text-left">Fin</th>
                    <th class="p-2 text-left">Cliente</th>
                    <th class="p-2 text-left">Vehiculo</th>
                    <th class="p-2 text-left">Estado</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($appointments as $appointment)
                    <tr class="border-t">
                        <td class="p-2">{{ $appointment->start_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-2">{{ $appointment->end_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-2">{{ $appointment->client?->first_name }} {{ $appointment->client?->last_name }}</td>
                        <td class="p-2">{{ $appointment->vehicle?->brand }} {{ $appointment->vehicle?->model }} {{ $appointment->vehicle?->plate }}</td>
                        <td class="p-2">{{ $appointment->status }}</td>
                        <td class="p-2 flex gap-2">
                            @if(!$appointment->movement_id)
                                <form method="POST" action="{{ route('workshop.appointments.convert', $appointment) }}">
                                    @csrf
                                    <button class="rounded bg-indigo-600 px-2 py-1 text-white">Convertir a OS</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('workshop.appointments.destroy', $appointment) }}" onsubmit="return confirm('Eliminar cita?')">
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

    {{ $appointments->links() }}
</div>
@endsection

