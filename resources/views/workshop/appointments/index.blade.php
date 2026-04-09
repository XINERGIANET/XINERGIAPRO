@extends('layouts.app')

@section('content')
<div x-data="{ 
    view: 'calendar',
    vehicles: {{ \Illuminate\Support\Js::from($vehicles->map(function ($v) {
        $clientName = trim(((string) ($v->client->first_name ?? '')) . ' ' . ((string) ($v->client->last_name ?? '')));
        return [
            'id' => (string) $v->id,
            'client_person_id' => (string) $v->client_person_id,
            'client_name' => $clientName,
            'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')),
            'display_label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) . ($clientName !== '' ? ' (Cliente: ' . $clientName . ')' : ''),
        ];
    })) }},
    vehicleClientMap: {{ $vehicles->pluck('client_person_id', 'id')->toJson() }}
}">
    <x-common.page-breadcrumb pageTitle="Agenda Taller" />

    <x-common.component-card title="Agenda / Citas" desc="Gestiona citas y conviertelas en ordenes de servicio.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-type-selection-modal')">
                <i class="ri-add-line"></i><span>Nueva cita</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.orders.index') }}" style="background-color:#4f46e5;color:#fff">
                <i class="ri-file-list-3-line"></i><span>Ordenes de servicio</span>
            </x-ui.link-button>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="date" name="from" value="{{ $from }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input type="date" name="to" value="{{ $to }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#334155] px-4 text-sm font-medium text-white shadow-sm hover:shadow-md transition-all active:scale-95">Filtrar</button>
            <a href="{{ route('workshop.appointments.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <div class="mt-6 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-4 px-1">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white/90">Gestión de Agenda</h2>
                
                <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
                    <button type="button" 
                        @click="view = 'calendar'"
                        :class="view === 'calendar' ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-all">
                        <i class="ri-calendar-fill text-lg"></i>
                    </button>
                    <button type="button" 
                        @click="view = 'table'"
                        :class="view === 'table' ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-all">
                        <i class="ri-table-line text-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <style>
            [x-cloak] { display: none !important; }
            .fc .fc-toolbar-title {
                font-size: 1.25rem !important;
                font-weight: 700 !important;
                color: #1e293b !important;
            }
            .fc .fc-button-primary {
                background-color: #334155 !important;
                border-color: transparent !important;
                color: #ffffff !important;
                text-transform: capitalize !important;
                box-shadow: none !important;
                outline: none !important;
                border-radius: 14px !important;
                padding: 8px 18px !important;
                font-weight: 700 !important;
                font-size: 0.85rem !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            /* Botones de Navegación específicos (Flechas tipo paginación) */
            .fc .fc-prev-button, .fc .fc-next-button {
                background-color: transparent !important;
                border: none !important;
                color: #94a3b8 !important; /* Color gris suave por defecto */
                padding: 4px !important;
                width: auto !important;
                height: auto !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: none !important;
            }
            .fc .fc-prev-button:hover, .fc .fc-next-button:hover {
                background-color: transparent !important;
                color: #1e293b !important; /* Más oscuro al pasar el cursor */
                transform: scale(1.1);
            }
            /* Flechas más negritas al hover */
            .fc .fc-prev-button:hover .fc-icon, .fc .fc-next-button:hover .fc-icon {
                font-weight: 800 !important;
            }
            /* Botón Hoy específico */
            .fc .fc-today-button {
                background-color: #334155 !important;
                border-color: transparent !important;
                opacity: 1 !important;
                padding-left: 18px !important;
                padding-right: 18px !important;
                border-radius: 12px !important;
                font-weight: 700 !important;
                color: #ffffff !important;
            }
            .fc .fc-today-button:hover {
                background-color: #1e293b !important;
            }
            .fc .fc-today-button:disabled {
                background-color: #94a3b8 !important;
                opacity: 0.6 !important;
            }
            .fc .fc-button-group {
                gap: 4px !important;
            }
            .fc .fc-button-group > .fc-button {
                border-radius: 14px !important;
                margin-left: 0 !important;
            }
            /* Estilo para días que no pertenecen al mes actual */
            .fc .fc-day-other {
                background-color: #f1f5f9 !important; /* Un gris un poco más visible (Slate 100) */
            }
            .fc .fc-day-other .fc-daygrid-day-number {
                color: #94a3b8 !important; /* Gris medio, perfectamente legible */
                opacity: 1 !important;
                font-weight: 500 !important;
            }
            .fc .fc-day-other .fc-daygrid-day-top {
                opacity: 1 !important;
            }
            /* Target navigation icons */
            .fc .fc-button .fc-icon {
                color: #ffffff !important;
            }
            .fc .fc-daygrid-day-number {
                font-size: 0.9rem !important;
                font-weight: 700 !important; /* Bolder for better visibility */
                color: #1e293b !important; /* Darker slate for high contrast */
                text-decoration: none !important;
                padding: 8px !important;
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
            /* Estilos para eventos más compactos */
            .fc-v-event, .fc-h-event {
                border: none !important;
            }
            .fc-daygrid-event {
                margin: 1px 2px !important;
                padding: 1px 4px !important;
                border-radius: 5px !important;
                min-height: 20px !important;
                display: flex !important;
                align-items: center !important;
            }
            .fc-event-main {
                font-size: 0.72rem !important;
                font-weight: 500 !important;
                line-height: 1.2 !important;
                padding: 0 !important;
                width: 100% !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            .fc-event-time {
                font-weight: 700 !important;
                margin-right: 3px !important;
                font-size: 0.65rem !important;
            }
            .fc-event-title {
                font-weight: 600 !important;
            }
        </style>

        <div class="grid grid-cols-1 gap-6">
            <!-- Table View -->
            <div x-show="view === 'table'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="space-y-4">
                <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-sm">
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
                                        @if($appointment->type === 'other')
                                            <div class="font-bold text-amber-700 uppercase">[Otros]</div>
                                            <div class="text-[10px] text-gray-600 font-medium">{{ $appointment->reason }}</div>
                                        @else
                                            <div class="font-bold text-gray-800 truncate max-w-[120px]">{{ $appointment->client?->first_name }} {{ $appointment->client?->last_name }}</div>
                                            <div class="text-[10px] text-gray-500 flex items-center gap-1">
                                                <i class="ri-motorbike-line"></i>
                                                <span>{{ $appointment->vehicle?->plate }}</span>
                                            </div>
                                        @endif
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
                                            @php
                                                $statusLabels = [
                                                    'pending' => 'Pendiente',
                                                    'confirmed' => 'Confirmado',
                                                    'arrived' => 'Llegó',
                                                    'cancelled' => 'Cancelado',
                                                    'no_show' => 'No se presentó'
                                                ];
                                            @endphp
                                            {{ $statusLabels[$appointment->status] ?? $appointment->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                            <div class="flex items-center justify-end gap-2">
                                                @if($appointment->type === 'service' && !$appointment->movement_id)
                                                    <a href="{{ route('workshop.maintenance-board.create', [
                                                        'vehicle_id' => $appointment->vehicle_id,
                                                        'client_person_id' => $appointment->client_person_id,
                                                        'diagnosis' => $appointment->reason,
                                                        'appointment_id' => $appointment->id
                                                    ]) }}" class="relative group">
                                                        <x-ui.button
                                                            size="icon"
                                                            variant="primary"
                                                            type="button"
                                                            className="rounded-xl w-8 h-8"
                                                            style="background-color: #8B5CF6; color: #FFFFFF;"
                                                            aria-label="Ir a Tablero"
                                                        >
                                                            <i class="ri-dashboard-fill"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            Ir a Tablero
                                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </a>
                                                @endif

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
            <div x-show="view === 'calendar'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="flex flex-col h-full">
                <div class="bg-white rounded-xl border border-gray-200 p-4 dark:bg-white/[0.03] dark:border-gray-800 shadow-sm flex-1">
                    <div id="workshop-calendar" 
                         data-events-url="{{ route('workshop.appointments.events') }}" 
                         style="min-height: 700px;"></div>
                </div>
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-type-selection-modal.window="open = true" :isOpen="false" :showCloseButton="true" class="max-w-xl">
        <div class="p-6 text-center">
            <h3 class="mb-6 text-lg font-bold text-gray-800 dark:text-white/90">Seleccionar tipo de cita</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button @click="open = false; $dispatch('open-appointment-modal', { type: 'service' })" class="flex flex-col items-center justify-center p-6 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-2xl border-2 border-blue-200 dark:border-blue-800 transition-all group">
                    <div class="w-14 h-14 bg-blue-500 rounded-full flex items-center justify-center text-white mb-3 group-hover:scale-110 transition-transform shadow-lg shadow-blue-500/30">
                        <i class="ri-service-line text-2xl"></i>
                    </div>
                    <span class="font-bold text-blue-800 dark:text-blue-300 text-base">Servicio</span>
                    <span class="text-[10px] text-blue-600 dark:text-blue-400 mt-1">Reparación o mantenimiento</span>
                </button>
                <button @click="open = false; $dispatch('open-appointment-modal', { type: 'other' })" class="flex flex-col items-center justify-center p-6 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/30 rounded-2xl border-2 border-amber-200 dark:border-amber-800 transition-all group">
                    <div class="w-14 h-14 bg-amber-500 rounded-full flex items-center justify-center text-white mb-3 group-hover:scale-110 transition-transform shadow-lg shadow-amber-500/30">
                        <i class="ri-more-line text-2xl"></i>
                    </div>
                    <span class="font-bold text-amber-800 dark:text-amber-300 text-base">Otros</span>
                    <span class="text-[10px] text-amber-600 dark:text-amber-400 mt-1">Reuniones o actividades generales</span>
                </button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal x-data="{
        open: false,
        appointmentType: 'service',
        selectedVehicleId: '',
        vehicleSearch: '',
        vehicleDropdownOpen: false,
        selectedClient: '',
        get filteredVehicles() {
            const q = String(this.vehicleSearch || '').trim().toLowerCase();
            if (!q) return this.vehicles.slice(0, 30);
            return this.vehicles
                .filter(v => {
                    const vehicleText = String(v.label || '').toLowerCase();
                    const clientText = String(v.client_name || '').toLowerCase();
                    return vehicleText.includes(q) || clientText.includes(q);
                })
                .slice(0, 30);
        },
        selectVehicle(vehicle) {
            this.selectedVehicleId = vehicle.id;
            this.vehicleSearch = vehicle.label || '';
            this.vehicleDropdownOpen = false;
            
            if (vehicle.client_person_id) {
                const clientId = String(vehicle.client_person_id);
                if (this.$refs && this.$refs.clientSelect) {
                    let exists = Array.from(this.$refs.clientSelect.options).some(opt => String(opt.value) === clientId);
                    if (!exists && vehicle.client_name) {
                        const newOpt = new Option(vehicle.client_name, clientId);
                        this.$refs.clientSelect.add(newOpt);
                    }
                }
                this.selectedClient = clientId;
                
                // Disparar evento de cambio por si hay librerías externas (Select2, etc.)
                this.$nextTick(() => {
                    if (this.$refs.clientSelect) {
                        this.$refs.clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            } else {
                this.selectedClient = '';
            }
        },
        onVehicleSearchInput() {
            this.vehicleDropdownOpen = true;
            if (!String(this.vehicleSearch || '').trim()) {
                this.selectedVehicleId = '';
                this.selectedClient = '';
            }
        }
    }" x-on:open-appointment-modal.window="open = true; appointmentType = $event.detail.type || 'service'; selectedVehicleId = ''; vehicleSearch = ''; selectedClient = '';" :isOpen="false" :showCloseButton="false" class="max-w-5xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cita</h3>
                    <p class="text-xs text-brand-500 font-medium mt-0.5" x-text="appointmentType === 'service' ? 'Tipo: Servicio Técnico' : 'Tipo: Cita General / Otra'"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.appointments.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @csrf
                <input type="hidden" name="type" x-model="appointmentType">
                
                <div x-show="appointmentType === 'service'">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo</label>
                    <div class="relative" x-on:click.outside="vehicleDropdownOpen = false">
                        <input type="hidden" name="vehicle_id" x-model="selectedVehicleId">
                        <input
                            type="text"
                            x-model="vehicleSearch"
                            @input="onVehicleSearchInput()"
                            @focus="vehicleDropdownOpen = true"
                            @keydown.escape="vehicleDropdownOpen = false"
                            @blur="setTimeout(() => { vehicleDropdownOpen = false }, 200)"
                            class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                            placeholder="Buscar vehiculo o cliente..."
                            :required="appointmentType === 'service'"
                        >
                        <div
                            x-show="vehicleDropdownOpen && filteredVehicles.length > 0"
                            x-cloak
                            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                        >
                            <div class="sticky top-0 flex items-center justify-between border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                <span>Vehiculos</span>
                                <button type="button" @click="vehicleDropdownOpen = false" class="text-indigo-600 hover:text-indigo-800">Cerrar</button>
                            </div>
                            <div class="py-1">
                                <template x-for="vehicle in filteredVehicles" :key="`vehicle-${vehicle.id}`">
                                    <button
                                        type="button"
                                        @click="selectVehicle(vehicle)"
                                        class="flex w-full items-start justify-between border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-indigo-50"
                                    >
                                        <span class="font-medium text-gray-800" x-text="vehicle.label || `Vehiculo #${vehicle.id}`"></span>
                                        <span class="ml-3 text-xs text-gray-500" x-text="vehicle.client_name ? `(${vehicle.client_name})` : ''"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div x-show="appointmentType === 'service'">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
                    <select x-ref="clientSelect" name="client_person_id" x-model="selectedClient" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm flex-1 dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" :required="appointmentType === 'service'">
                        <option value="">Seleccione cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" x-text="appointmentType === 'service' ? 'Tecnico' : 'Responsable'"></label>
                    <select name="technician_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione tecnico</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Inicio</label>
                    <input type="datetime-local" name="start_at" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Fin</label>
                    <input type="datetime-local" name="end_at" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                    <input name="reason" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                    <input name="notes" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Notas">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select name="status" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="pending">Pendiente</option>
                        <option value="confirmed">Confirmado</option>
                        <option value="arrived">Llegó</option>
                        <option value="cancelled">Cancelado</option>
                        <option value="no_show">No se presentó</option>
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
        <x-ui.modal x-data="{
            open: false,
            selectedVehicleId: '{{ $appointment->vehicle_id }}',
            appointmentType: '{{ $appointment->type ?? 'service' }}',
            vehicleSearch: '{{ $appointment->vehicle ? $appointment->vehicle->brand . ' ' . $appointment->vehicle->model . ' - ' . $appointment->vehicle->plate : '' }}',
            vehicleDropdownOpen: false,
            selectedClient: '{{ $appointment->client_person_id }}',
            get filteredVehicles() {
                const q = String(this.vehicleSearch || '').trim().toLowerCase();
                if (!q) return this.vehicles.slice(0, 30);
                return this.vehicles
                    .filter(v => {
                        const vehicleText = String(v.label || '').toLowerCase();
                        const clientText = String(v.client_name || '').toLowerCase();
                        return vehicleText.includes(q) || clientText.includes(q);
                    })
                    .slice(0, 30);
            },
            selectVehicle(vehicle) {
                this.selectedVehicleId = vehicle.id;
                this.vehicleSearch = vehicle.label || '';
                this.vehicleDropdownOpen = false;
                
                if (vehicle.client_person_id) {
                    const clientId = String(vehicle.client_person_id);
                    if (this.$refs && this.$refs.clientSelect) {
                        let exists = Array.from(this.$refs.clientSelect.options).some(opt => String(opt.value) === clientId);
                        if (!exists && vehicle.client_name) {
                            const newOpt = new Option(vehicle.client_name, clientId);
                            this.$refs.clientSelect.add(newOpt);
                        }
                    }
                    this.selectedClient = clientId;
                    
                    // Disparar evento de cambio
                    this.$nextTick(() => {
                        if (this.$refs.clientSelect) {
                            this.$refs.clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                } else {
                    this.selectedClient = '';
                }
            },
            onVehicleSearchInput() {
                this.vehicleDropdownOpen = true;
                if (!String(this.vehicleSearch || '').trim()) {
                    this.selectedVehicleId = '';
                    this.selectedClient = '';
                }
            }
        }" x-on:open-edit-appointment-modal.window="if ($event.detail === {{ $appointment->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-5xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar cita</h3>
                        <p class="text-xs text-brand-500 font-medium mt-0.5" x-text="appointmentType === 'service' ? 'Tipo: Servicio Técnico' : 'Tipo: Cita General / Otra'"></p>
                    </div>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.appointments.update', $appointment) }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="type" x-model="appointmentType">

                    <div x-show="appointmentType === 'service'">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo</label>
                        <div class="relative" x-on:click.outside="vehicleDropdownOpen = false">
                            <input type="hidden" name="vehicle_id" x-model="selectedVehicleId">
                            <input
                                type="text"
                                x-model="vehicleSearch"
                                @input="onVehicleSearchInput()"
                                @focus="vehicleDropdownOpen = true"
                                @keydown.escape="vehicleDropdownOpen = false"
                                @blur="setTimeout(() => { vehicleDropdownOpen = false }, 200)"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Buscar vehiculo o cliente..."
                                :required="appointmentType === 'service'"
                            >
                            <div
                                x-show="vehicleDropdownOpen && filteredVehicles.length > 0"
                                x-cloak
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                            >
                                <div class="sticky top-0 flex items-center justify-between border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                    <span>Vehiculos</span>
                                    <button type="button" @click="vehicleDropdownOpen = false" class="text-indigo-600 hover:text-indigo-800">Cerrar</button>
                                </div>
                                <div class="py-1">
                                    <template x-for="vehicle in filteredVehicles" :key="`edit-vehicle-${vehicle.id}`">
                                        <button
                                            type="button"
                                            @click="selectVehicle(vehicle)"
                                            class="flex w-full items-start justify-between border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-indigo-50"
                                        >
                                            <span class="font-medium text-gray-800" x-text="vehicle.label || `Vehiculo #${vehicle.id}`"></span>
                                            <span class="ml-3 text-xs text-gray-500" x-text="vehicle.client_name ? `(${vehicle.client_name})` : ''"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div x-show="appointmentType === 'service'">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
                        <select x-ref="clientSelect" name="client_person_id" x-model="selectedClient" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm flex-1 dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" :required="appointmentType === 'service'">
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" x-text="appointmentType === 'service' ? 'Tecnico' : 'Responsable'"></label>
                        <select name="technician_person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione tecnico</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" @selected((int)$appointment->technician_person_id === (int)$tech->id)>{{ $tech->first_name }} {{ $tech->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Inicio</label>
                        <input type="datetime-local" name="start_at" value="{{ optional($appointment->start_at)->format('Y-m-d\\TH:i') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Fin</label>
                        <input type="datetime-local" name="end_at" value="{{ optional($appointment->end_at)->format('Y-m-d\\TH:i') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                        <input name="reason" value="{{ $appointment->reason }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                        <input name="notes" value="{{ $appointment->notes }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Notas">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                        <select name="status" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="pending" @selected($appointment->status === 'pending')>Pendiente</option>
                            <option value="confirmed" @selected($appointment->status === 'confirmed')>Confirmado</option>
                            <option value="arrived" @selected($appointment->status === 'arrived')>Llegó</option>
                            <option value="cancelled" @selected($appointment->status === 'cancelled')>Cancelado</option>
                            <option value="no_show" @selected($appointment->status === 'no_show')>No se presentó</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 mt-2 flex flex-wrap gap-2">
                        @if($appointment->type === 'service' && !$appointment->movement_id)
                            <a href="{{ route('workshop.maintenance-board.create', [
                                'vehicle_id' => $appointment->vehicle_id,
                                'client_person_id' => $appointment->client_person_id,
                                'diagnosis' => $appointment->reason,
                                'appointment_id' => $appointment->id
                            ]) }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-purple-600 px-4 text-xs font-bold uppercase tracking-wider text-white hover:bg-purple-700 transition shadow-sm">
                                <i class="ri-dashboard-fill text-lg"></i>
                                <span>Ir a Tablero</span>
                            </a>
                        @endif
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
@endsection
