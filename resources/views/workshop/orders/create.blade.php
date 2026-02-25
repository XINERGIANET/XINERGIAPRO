@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Nueva Orden de Servicio</h1>

    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('workshop.orders.store') }}" class="grid grid-cols-1 gap-4 rounded border p-4 md:grid-cols-2">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo <span class="text-red-500">*</span></label>
            <select name="vehicle_id" class="w-full rounded border px-3 py-2" required>
                <option value="">Seleccione vehiculo</option>
                @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Cliente <span class="text-red-500">*</span></label>
            <select name="client_person_id" class="w-full rounded border px-3 py-2" required>
                <option value="">Seleccione cliente</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Cita relacionada (opcional)</label>
            <select name="appointment_id" class="w-full rounded border px-3 py-2">
                <option value="">Seleccione cita</option>
                @foreach($appointments as $appointment)
                    <option value="{{ $appointment->id }}">#{{ $appointment->id }} {{ $appointment->start_at?->format('Y-m-d H:i') }} - {{ $appointment->reason }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">OS previa (garantia/post-servicio)</label>
            <select name="previous_workshop_movement_id" class="w-full rounded border px-3 py-2">
                <option value="">Seleccione OS previa</option>
                @foreach($previousOrders as $prev)
                    <option value="{{ $prev->id }}">OS {{ $prev->movement?->number }} - {{ $prev->status }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de ingreso <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="intake_date" class="w-full rounded border px-3 py-2" value="{{ now()->format('Y-m-d\TH:i') }}" required>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Kilometraje ingreso</label>
            <input type="number" name="mileage_in" class="w-full rounded border px-3 py-2" min="0" placeholder="0">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">Diagnostico inicial</label>
            <input name="diagnosis_text" class="w-full rounded border px-3 py-2" placeholder="Describa el diagnostico">
        </div>

        <div class="md:col-span-2 flex items-center gap-2">
            <input type="checkbox" id="tow_in" name="tow_in" value="1" class="h-4 w-4 rounded border-gray-300">
            <label for="tow_in" class="text-sm font-medium text-gray-700">Ingreso en grua</label>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">Estado inicial</label>
            <select name="status" class="w-full rounded border px-3 py-2">
                <option value="draft">Borrador</option>
                <option value="diagnosis">En Diagnóstico</option>
                <option value="awaiting_approval">Esperando Aprobación</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">Observaciones</label>
            <textarea name="observations" class="w-full rounded border px-3 py-2" rows="3" placeholder="Notas adicionales..."></textarea>
        </div>

        <button class="rounded bg-[#244BB3] px-3 py-3 text-white font-semibold transition-colors hover:bg-[#1f3f98] md:col-span-2">Crear Orden de Servicio</button>
    </form>
</div>
@endsection

