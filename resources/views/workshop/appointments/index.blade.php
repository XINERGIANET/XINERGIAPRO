@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Agenda Taller" />

    <x-common.component-card title="Agenda / Citas" desc="Gestiona citas y conviertelas en ordenes de servicio.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-appointment-modal')">
                <i class="ri-add-line"></i><span>Nueva cita</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.index') }}" style="background-color:#4f46e5;color:#fff">
                <i class="ri-file-list-3-line"></i><span>Ordenes de servicio</span>
            </x-ui.link-button>
            <button type="button" class="h-10 rounded-lg bg-[#244BB3] px-3 text-sm font-medium text-white">Operacion: Ver</button>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="date" name="from" value="{{ $from }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input type="date" name="to" value="{{ $to }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.appointments.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <div class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Inicio</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Fin</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Vehiculo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $appointment->start_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->end_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->client?->first_name }} {{ $appointment->client?->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->vehicle?->brand }} {{ $appointment->vehicle?->model }} {{ $appointment->vehicle?->plate }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->status }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="$dispatch('open-edit-appointment-modal', {{ $appointment->id }})" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white">Editar</button>
                                    @if(!$appointment->movement_id)
                                        <form method="POST" action="{{ route('workshop.appointments.convert', $appointment) }}">
                                            @csrf
                                            <button class="rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-medium text-white">Convertir a OS</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('workshop.appointments.destroy', $appointment) }}" onsubmit="return confirm('Eliminar cita?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-medium text-white">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-4 text-sm text-gray-500">Sin citas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $appointments->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-appointment-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-5xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cita</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.appointments.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                @csrf
                <select name="vehicle_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="">Vehiculo</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                    @endforeach
                </select>
                <select name="client_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="">Cliente</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                    @endforeach
                </select>
                <select name="technician_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Tecnico</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
                    @endforeach
                </select>
                <input type="datetime-local" name="start_at" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                <input type="datetime-local" name="end_at" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                <input name="reason" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                <input name="notes" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Notas">
                <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="pending">pending</option>
                    <option value="confirmed">confirmed</option>
                    <option value="arrived">arrived</option>
                    <option value="cancelled">cancelled</option>
                    <option value="no_show">no_show</option>
                </select>
                <div class="md:col-span-3 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($appointments as $appointment)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-appointment-modal.window="if ($event.detail === {{ $appointment->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-5xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar cita</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.appointments.update', $appointment) }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <select name="vehicle_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((int)$appointment->vehicle_id === (int)$vehicle->id)>{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                        @endforeach
                    </select>
                    <select name="client_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((int)$appointment->client_person_id === (int)$client->id)>{{ $client->first_name }} {{ $client->last_name }}</option>
                        @endforeach
                    </select>
                    <select name="technician_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Tecnico</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" @selected((int)$appointment->technician_person_id === (int)$tech->id)>{{ $tech->first_name }} {{ $tech->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="start_at" value="{{ optional($appointment->start_at)->format('Y-m-d\\TH:i') }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <input type="datetime-local" name="end_at" value="{{ optional($appointment->end_at)->format('Y-m-d\\TH:i') }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <input name="reason" value="{{ $appointment->reason }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                    <input name="notes" value="{{ $appointment->notes }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Notas">
                    <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="pending" @selected($appointment->status === 'pending')>pending</option>
                        <option value="confirmed" @selected($appointment->status === 'confirmed')>confirmed</option>
                        <option value="arrived" @selected($appointment->status === 'arrived')>arrived</option>
                        <option value="cancelled" @selected($appointment->status === 'cancelled')>cancelled</option>
                        <option value="no_show" @selected($appointment->status === 'no_show')>no_show</option>
                    </select>
                    <div class="md:col-span-3 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
@endsection
