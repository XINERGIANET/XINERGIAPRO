@extends('layouts.app')

@section('content')
<div x-data="{
    showAdvanceModal: false,
    selectedOrder: null,
    nextPhase: '',
    nextPhaseLabel: '',
    advanceForm: {
        date: '{{ now()->format('Y-m-d\TH:i') }}',
        technician_id: '',
        observations: '',
        approved: 'yes'
    },
    phases: [
        { key: 'recepcion', label: 'Recepción', icon: 'ri-file-list-3-line' },
        { key: 'programacion', label: 'Fecha de Programación (Espera)', icon: 'ri-calendar-event-line' },
        { key: 'eval_inicio', label: 'Inicio de Evaluación', icon: 'ri-stethoscope-line' },
        { key: 'eval_fin', label: 'Fin de Evaluación', icon: 'ri-check-double-line' },
        { key: 'cotizacion_entrega', label: 'Entrega de Cotización', icon: 'ri-file-paper-2-line' },
        { key: 'cotizacion_aprobacion', label: 'Aprobación del Cliente', icon: 'ri-thumb-up-line' },
        { key: 'repuestos_solicitud', label: 'Solicitud de Repuestos', icon: 'ri-shopping-cart-line' },
        { key: 'repuestos_entrega', label: 'Repuestos al 100% (Logística)', icon: 'ri-checkbox-multiple-line' },
        { key: 'reparacion_inicio', label: 'Iniciar Reparación y enviar a Principal', icon: 'ri-share-forward-line' }
    ],
    openAdvanceModal(order) {
        this.selectedOrder = order;
        const currentPhase = order.corrective_phase || 'recepcion';
        const currentIndex = this.phases.findIndex(p => p.key === currentPhase);
        if (currentIndex < this.phases.length - 1) {
            const next = this.phases[currentIndex + 1];
            
            // Si la siguiente fase es Entrega de Cotización, redirigimos a edición
            if (next.key === 'cotizacion_entrega') {
                const editUrl = `{{ url('admin/taller/tablero-mantenimiento') }}/${order.id}/editar?advance_corrective=1`;
                window.location.href = editUrl;
                return;
            }

            // Si la siguiente fase es Solicitud de Repuestos, redirigimos a la nueva vista
            if (next.key === 'repuestos_solicitud') {
                const partsUrl = `{{ url('admin/taller/tablero-mantenimiento') }}/${order.id}/solicitud-repuestos`;
                window.location.href = partsUrl;
                return;
            }

            this.nextPhase = next.key;
            this.nextPhaseLabel = next.label;
            this.advanceForm.date = '{{ now()->format('Y-m-d\TH:i') }}';
            this.advanceForm.technician_id = '';
            this.advanceForm.observations = '';
            this.showAdvanceModal = true;
        }
    }
}">
    <x-common.page-breadcrumb pageTitle="Gestión de Mantenimiento Correctivo" />

    <x-common.component-card title="Flujo de Trabajo Correctivo" desc="Seguimiento de fases preliminares hasta el inicio del servicio mecánico.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="flex flex-wrap items-center gap-2 whitespace-nowrap xl:shrink-0">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.maintenance-board.create', ['service_type' => 'correctivo']) }}" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;border:none">
                    <i class="ri-add-circle-line"></i><span>Nuevo Ingreso Correctivo</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.maintenance-board.index') }}">
                    <i class="ri-dashboard-2-line"></i><span>Tablero Principal</span>
                </x-ui.link-button>
            </div>

            <form method="GET" class="flex min-w-0 flex-1 flex-wrap items-center gap-2 lg:flex-nowrap">
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    class="h-11 w-full flex-1 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none"
                    placeholder="Buscar vehículo o cliente..."
                >
                <x-ui.button size="sm" variant="primary" type="submit" className="h-11 whitespace-nowrap px-5" style="background-color:#334155;border-color:#334155;color:#fff">
                    <i class="ri-search-line"></i><span>Buscar</span>
                </x-ui.button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($cards as $order)
                <div class="relative flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="mb-1 inline-block rounded-full bg-orange-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                                OS {{ $order->movement?->number ?? '#' . $order->id }}
                            </span>
                            <h3 class="text-base font-bold text-slate-800 dark:text-white">
                                {{ $order->vehicle?->plate ?? 'Sin Placa' }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }}
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-800">
                            <i class="ri-error-warning-line text-xl"></i>
                        </div>
                    </div>

                    <div class="mb-4 flex-1">
                        <div class="mb-2 flex items-center gap-2">
                            <i class="ri-user-follow-line text-slate-400"></i>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')) }}
                            </span>
                        </div>
                        
                        <div class="mt-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                            <p class="mb-1 text-[10px] font-bold uppercase text-slate-400">Fase Actual</p>
                            @php
                                $phaseInfo = collect([
                                    'recepcion' => ['label' => 'Recepción', 'color' => 'bg-blue-500'],
                                    'programacion' => ['label' => 'En espera (Programación)', 'color' => 'bg-amber-500'],
                                    'eval_inicio' => ['label' => 'Evaluación Iniciada', 'color' => 'bg-indigo-500'],
                                    'eval_fin' => ['label' => 'Evaluación Finalizada', 'color' => 'bg-purple-500'],
                                    'cotizacion_entrega' => ['label' => 'Cotización Entregada', 'color' => 'bg-cyan-500'],
                                    'cotizacion_aprobacion' => ['label' => 'Esperando Aprobación', 'color' => 'bg-emerald-500'],
                                    'repuestos_solicitud' => ['label' => 'Repuestos Solicitados', 'color' => 'bg-orange-500'],
                                    'repuestos_entrega' => ['label' => 'Repuestos al 100%', 'color' => 'bg-emerald-500'],
                                ])->get($order->corrective_phase ?? 'recepcion', ['label' => $order->corrective_phase, 'color' => 'bg-slate-500']);
                            @endphp
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-2.5 rounded-full {{ $phaseInfo['color'] }}"></div>
                                <span class="text-sm font-bold text-slate-800 dark:text-white">{{ $phaseInfo['label'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.button variant="primary" size="sm" @click="openAdvanceModal({{ $order }})" className="w-full">
                            @if($order->corrective_phase === 'repuestos_entrega')
                                <i class="ri-share-forward-line"></i><span>Iniciar Reparación</span>
                            @else
                                <i class="ri-arrow-right-circle-line"></i><span>Avanzar Fase</span>
                            @endif
                        </x-ui.button>
                        
                        @if(in_array($order->corrective_phase, ['eval_fin', 'cotizacion_entrega', 'cotizacion_aprobacion', 'repuestos_solicitud']))
                            <x-ui.link-button variant="outline" size="sm" href="{{ route('workshop.maintenance-board.edit', $order) }}" className="w-full">
                                <i class="ri-edit-line"></i><span>Editar Detalles</span>
                            </x-ui.link-button>
                        @endif

                        @if(in_array($order->corrective_phase, ['cotizacion_entrega', 'cotizacion_aprobacion', 'repuestos_solicitud', 'repuestos_entrega']))
                            <x-ui.link-button variant="outline" size="sm" href="{{ route('workshop.pdf.corrective-quote', $order) }}" target="_blank" className="w-full">
                                <i class="ri-file-pdf-line"></i><span>Descargar Cotización</span>
                            </x-ui.link-button>
                        @endif

                        @if(in_array($order->corrective_phase, ['repuestos_solicitud', 'repuestos_entrega', 'reparacion_inicio']))
                            <x-ui.link-button variant="outline" size="sm" href="{{ route('workshop.pdf.parts', $order) }}" target="_blank" className="w-full text-orange-600 border-orange-200 hover:bg-orange-50">
                                <i class="ri-file-download-line"></i><span>Descargar Solicitud Repuestos</span>
                            </x-ui.link-button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center">
                    <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <i class="ri-search-line text-4xl"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-800 dark:text-white">No hay mantenimientos correctivos</h3>
                    <p class="text-slate-500 dark:text-slate-400">Las órdenes correctivas en fase preliminar aparecerán aquí.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $cards->links() }}
        </div>
    </x-common.component-card>

    <!-- Modal para Avanzar Fase -->
    <div x-show="showAdvanceModal" class="fixed inset-0 z-[10001] flex items-center justify-center p-4" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAdvanceModal = false"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Avanzar Fase de Servicio</h3>
                <button @click="showAdvanceModal = false" class="text-slate-400 hover:text-slate-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <form x-bind:action="'/admin/taller/tablero-mantenimiento/' + selectedOrder?.id + '/avanzar-fase'" method="POST" class="p-6">
                @csrf
                <input type="hidden" name="next_phase" x-model="nextPhase">

                <div class="mb-6 rounded-xl bg-orange-50 p-4 dark:bg-orange-900/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                            <i class="ri-arrow-right-line text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-orange-600">Siguiente Fase:</p>
                            <p class="text-base font-bold text-orange-700 dark:text-orange-400" x-text="nextPhaseLabel"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">Fecha y Hora</label>
                    <input type="datetime-local" name="date" x-model="advanceForm.date" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-orange-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800" required>
                </div>

                <div x-show="nextPhase === 'cotizacion_aprobacion'" class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 p-4" x-cloak>
                    <label class="mb-2 block text-sm font-bold text-indigo-900">¿El cliente aprobó la cotización?</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="approved" value="yes" x-model="advanceForm.approved" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-indigo-800">Sí, Aprobada</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="approved" value="no" x-model="advanceForm.approved" class="h-4 w-4 text-red-600 focus:ring-red-500">
                            <span class="text-sm font-medium text-red-800">No, Rechazada (Fin)</span>
                        </label>
                    </div>
                </div>

                <div x-show="nextPhase === 'programacion'" class="mb-4" x-cloak>
                    <label class="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">Técnico Asignado (Opcional)</label>
                    <select name="technician_id" x-model="advanceForm.technician_id" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-orange-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800">
                        <option value="">-- Sin asignar --</option>
                        @foreach($technicians as $tech)
                            <option value="{{ is_array($tech) ? $tech['id'] : $tech->id }}">{{ is_array($tech) ? $tech['name'] : trim($tech->first_name . ' ' . $tech->last_name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="nextPhase !== 'programacion' && selectedOrder?.technicians?.length > 0" class="mb-4" x-cloak>
                    <label class="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">Técnico Asignado</label>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
                        <i class="ri-user-settings-line text-slate-500"></i>
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200" x-text="(selectedOrder?.technicians[0]?.technician?.first_name || '') + ' ' + (selectedOrder?.technicians[0]?.technician?.last_name || '')"></span>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">Observaciones (Opcional)</label>
                    <textarea name="observations" x-model="advanceForm.observations" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-orange-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800" placeholder="Escribe aquí cualquier observación relevante de esta fase..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button type="button" variant="outline" @click="showAdvanceModal = false">
                        Cancelar
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                        Confirmar Avance
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    @include('workshop.maintenance-board.partials.service-type-modal')
</div>
@endsection
