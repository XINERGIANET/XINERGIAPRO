@extends('layouts.app')

@section('content')
<div class="space-y-4" x-data="{ selectedClientId: '', historyUrl: '', historyBase: @js(route('workshop.clients.history', ['person' => '__PERSON__'])) }">
    <h1 class="text-xl font-semibold">Nueva Orden de Servicio</h1>

    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('workshop.orders.store') }}" class="grid grid-cols-1 gap-4 rounded border p-4 md:grid-cols-2">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo</label>
            <select name="vehicle_id" class="w-full rounded border px-3 py-2" required>
                <option value="">Seleccione vehiculo</option>
                @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
            <div class="flex gap-2">
                <select
                    name="client_person_id"
                    x-model="selectedClientId"
                    @change="historyUrl = selectedClientId ? historyBase.replace('__PERSON__', selectedClientId) + '?modal=1' : ''"
                    class="w-full rounded border px-3 py-2"
                    required
                >
                    <option value="">Seleccione cliente</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                @endforeach
                </select>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded border border-violet-200 bg-violet-50 px-3 text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!selectedClientId"
                    @click="$dispatch('open-order-client-history')"
                    title="Ver historial"
                >
                    <i class="ri-history-line text-lg"></i>
                </button>
            </div>
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
            <label class="mb-1 block text-sm font-medium text-gray-700">Fecha de ingreso</label>
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

    <x-ui.modal x-data="{ open: false }" x-on:open-order-client-history.window="open = true" x-on:close-order-client-history.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-7xl">
        <div class="p-5 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Historial del cliente</h3>
                    <p class="mt-1 text-sm text-gray-500">Consulta mantenimientos, tecnico, observaciones y vehiculos asociados.</p>
                </div>
                <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-gray-200">
                <template x-if="historyUrl">
                    <iframe :src="historyUrl" class="h-[75vh] w-full bg-white"></iframe>
                </template>
                <template x-if="!historyUrl">
                    <div class="flex h-80 items-center justify-center bg-gray-50 text-sm font-medium text-gray-500">
                        Seleccione un cliente para ver su historial.
                    </div>
                </template>
            </div>
        </div>
    </x-ui.modal>
</div>
@endsection
