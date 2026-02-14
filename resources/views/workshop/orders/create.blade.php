@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Nueva Orden de Servicio</h1>

    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('workshop.orders.store') }}" class="grid grid-cols-1 gap-3 rounded border p-4 md:grid-cols-2">
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

        <select name="appointment_id" class="rounded border px-3 py-2">
            <option value="">Cita relacionada (opcional)</option>
            @foreach($appointments as $appointment)
                <option value="{{ $appointment->id }}">#{{ $appointment->id }} {{ $appointment->start_at?->format('Y-m-d H:i') }} - {{ $appointment->reason }}</option>
            @endforeach
        </select>

        <select name="previous_workshop_movement_id" class="rounded border px-3 py-2">
            <option value="">OS previa (garantia/post-servicio)</option>
            @foreach($previousOrders as $prev)
                <option value="{{ $prev->id }}">OS {{ $prev->movement?->number }} - {{ $prev->status }}</option>
            @endforeach
        </select>

        <input type="datetime-local" name="intake_date" class="rounded border px-3 py-2" value="{{ now()->format('Y-m-d\TH:i') }}" required>
        <input type="number" name="mileage_in" class="rounded border px-3 py-2" min="0" placeholder="Kilometraje ingreso">
        <input name="diagnosis_text" class="rounded border px-3 py-2" placeholder="Diagnostico inicial">

        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="tow_in" value="1">
            <span>Ingreso en grua</span>
        </label>

        <select name="status" class="rounded border px-3 py-2">
            <option value="draft">draft</option>
            <option value="diagnosis">diagnosis</option>
            <option value="awaiting_approval">awaiting_approval</option>
        </select>

        <textarea name="observations" class="rounded border px-3 py-2 md:col-span-2" rows="3" placeholder="Observaciones"></textarea>

        <button class="rounded bg-emerald-600 px-3 py-2 text-white md:col-span-2">Crear OS</button>
    </form>
</div>
@endsection

