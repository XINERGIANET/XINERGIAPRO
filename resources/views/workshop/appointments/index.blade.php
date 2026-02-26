@extends('layouts.app')

@section('content')
<div x-data="{ view: 'calendar' }">
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
            <x-ui.button size="md" variant="primary" type="button" @click="view = 'calendar'" :class="view === 'calendar' ? 'bg-[#244BB3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="h-10 rounded-lg px-3 text-sm font-medium transition-all">
                <i class="ri-calendar-line"></i><span>Ver calendario</span>
            </x-ui.button>
            <x-ui.button size="md" variant="primary" type="button" @click="view = 'table'" :class="view === 'table' ? 'bg-[#244BB3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="h-10 rounded-lg px-3 text-sm font-medium transition-all">
                <i class="ri-table-line"></i><span>Ver tabla</span>
            </x-ui.button>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="date" name="from" value="{{ $from }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input type="date" name="to" value="{{ $to }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.appointments.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <style>
            [x-cloak] { display: none !important; }
            .fc .fc-toolbar-title {
                font-size: 1.25rem !important;
                font-weight: 700 !important;
                color: #1e293b !important;
            }
            .fc .fc-button-primary {
                background-color: #244BB3 !important;
                border-color: #244BB3 !important;
                color: #ffffff !important;
                text-transform: capitalize !important;
                box-shadow: none !important;
                outline: none !important;
            }
            .fc .fc-button-primary:hover {
                background-color: #1e3a8a !important;
                border-color: #1e3a8a !important;
                color: #ffffff !important;
            }
            .fc .fc-button-active {
                background-color: #162d6d !important;
                border-color: #162d6d !important;
                color: #ffffff !important;
            }
            /* Target navigation icons */
            .fc .fc-button .fc-icon {
                color: #ffffff !important;
            }
            .fc .fc-daygrid-day-number {
                font-size: 0.875rem !important;
                font-weight: 500 !important;
                color: #64748b !important;
                text-decoration: none !important;
            }
            .fc .fc-col-header-cell-cushion {
                font-size: 0.75rem !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                color: #94a3b8 !important;
                text-decoration: none !important;
                padding: 10px 0 !important;
            }
            .fc-event {
                cursor: pointer;
                transition: transform 0.1s ease;
            }
            .fc-event:hover {
                transform: scale(1.02);
            }
        </style>

        <div class="grid grid-cols-1 gap-6">
            <!-- Table View -->
            <div x-show="view === 'table'" x-transition x-cloak class="space-y-4">
                <div class="overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-sm">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-brand-500">
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white first:rounded-tl-xl w-1/4">Inicio</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white">Cliente / Vehículo</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white">Estado</th>
                                <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-white last:rounded-tr-xl"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($appointments as $appointment)
                                <tr class="relative hover:z-[60] hover:bg-gray-50/50 dark:hover:bg-white/[0.01] transition-colors">
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-bold text-gray-900 line-height-tight">{{ $appointment->start_at?->format('d/m/Y') }}</div>
                                        <div class="text-[10px] text-gray-500 font-medium">{{ $appointment->start_at?->format('H:i') }} - {{ $appointment->end_at?->format('H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        <div class="font-bold text-gray-800 truncate max-w-[120px]">{{ $appointment->client?->first_name }} {{ $appointment->client?->last_name }}</div>
                                        <div class="text-[10px] text-gray-500 flex items-center gap-1">
                                            <i class="ri-motorbike-line"></i>
                                            <span>{{ $appointment->vehicle?->plate }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        @php
                                            $statusColors = match($appointment->status) {
                                                'pending' => ['bg' => '#fef3c7', 'text' => '#92400e'],
                                                'confirmed' => ['bg' => '#d1fae5', 'text' => '#065f46'],
                                                'arrived' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
                                                'cancelled' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                                                'no_show' => ['bg' => '#f3f4f6', 'text' => '#374151'],
                                                default => ['bg' => '#f3f4f6', 'text' => '#374151']
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold uppercase" style="background-color: {{ $statusColors['bg'] }}; color: {{ $statusColors['text'] }}">
                                            {{ $appointment->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center justify-end gap-2">
                                            @if(!$appointment->movement_id)
                                                <form method="POST" action="{{ route('workshop.appointments.convert', $appointment) }}" class="relative group">
                                                    @csrf
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="primary"
                                                        type="submit"
                                                        className="rounded-xl w-8 h-8"
                                                        style="background-color: #3B82F6; color: #FFFFFF;"
                                                        aria-label="Convertir a OS"
                                                    >
                                                        <i class="ri-refresh-line"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        Convertir a OS
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </form>
                                            @endif

                                            <div class="relative group">
                                                <x-ui.button
                                                    size="icon"
                                                    variant="edit"
                                                    type="button"
                                                    @click="$dispatch('open-edit-appointment-modal', {{ $appointment->id }})"
                                                    className="rounded-xl w-8 h-8"
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
                                                action="{{ route('workshop.appointments.destroy', $appointment) }}"
                                                class="relative group js-swal-delete"
                                                data-swal-title="¿Eliminar cita?"
                                                data-swal-text="Se eliminara esta cita. Esta accion no se puede deshacer."
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
                                                    className="rounded-xl w-8 h-8"
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
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 font-medium italic">Sin citas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 px-2">{{ $appointments->links() }}</div>
            </div>

            <!-- Calendar View -->
            <div x-show="view === 'calendar'" x-transition x-cloak class="flex flex-col h-full">
                <div class="bg-white rounded-xl border border-gray-200 p-4 dark:bg-white/[0.03] dark:border-gray-800 shadow-sm flex-1">
                    <div id="workshop-calendar" 
                         data-events-url="{{ route('workshop.appointments.events') }}" 
                         style="min-height: 700px;"></div>
                </div>
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-appointment-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-5xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cita</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.appointments.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo <span class="text-red-500">*</span></label>
                    <select name="vehicle_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione vehiculo</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cliente <span class="text-red-500">*</span></label>
                    <select name="client_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Seleccione cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tecnico</label>
                    <select name="technician_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione tecnico</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Inicio <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="start_at" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fin <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="end_at" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Motivo <span class="text-red-500">*</span></label>
                    <input name="reason" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                    <input name="notes" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Notas">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select name="status" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="pending">pending</option>
                        <option value="confirmed">confirmed</option>
                        <option value="arrived">arrived</option>
                        <option value="cancelled">cancelled</option>
                        <option value="no_show">no_show</option>
                    </select>
                </div>
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

                <form method="POST" action="{{ route('workshop.appointments.update', $appointment) }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo <span class="text-red-500">*</span></label>
                        <select name="vehicle_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((int)$appointment->vehicle_id === (int)$vehicle->id)>{{ $vehicle->brand }} {{ $vehicle->model }} - {{ $vehicle->plate }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cliente <span class="text-red-500">*</span></label>
                        <select name="client_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((int)$appointment->client_person_id === (int)$client->id)>{{ $client->first_name }} {{ $client->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tecnico</label>
                        <select name="technician_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione tecnico</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" @selected((int)$appointment->technician_person_id === (int)$tech->id)>{{ $tech->first_name }} {{ $tech->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Inicio <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="start_at" value="{{ optional($appointment->start_at)->format('Y-m-d\\TH:i') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Fin <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="end_at" value="{{ optional($appointment->end_at)->format('Y-m-d\\TH:i') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Motivo <span class="text-red-500">*</span></label>
                        <input name="reason" value="{{ $appointment->reason }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                        <input name="notes" value="{{ $appointment->notes }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Notas">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                        <select name="status" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="pending" @selected($appointment->status === 'pending')>pending</option>
                            <option value="confirmed" @selected($appointment->status === 'confirmed')>confirmed</option>
                            <option value="arrived" @selected($appointment->status === 'arrived')>arrived</option>
                            <option value="cancelled" @selected($appointment->status === 'cancelled')>cancelled</option>
                            <option value="no_show" @selected($appointment->status === 'no_show')>no_show</option>
                        </select>
                    </div>
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
