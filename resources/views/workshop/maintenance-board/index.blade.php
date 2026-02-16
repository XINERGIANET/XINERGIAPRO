@extends('layouts.app')

@section('content')
<div x-data="{
    vehicles: @js($vehicles->map(fn($v) => ['id' => $v->id, 'client_person_id' => $v->client_person_id, 'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) , 'km' => (int) ($v->current_mileage ?? 0)])),
    servicesCatalog: @js($services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'base_price' => (float) $s->base_price, 'type' => $s->type])),
    selectedVehicleId: '',
    selectedClientId: '',
    mileageIn: '',
    serviceLines: [{ service_id: '', qty: 1, unit_price: 0 }],
    syncVehicle() {
        const selected = this.vehicles.find(v => String(v.id) === String(this.selectedVehicleId));
        if (!selected) return;
        this.selectedClientId = selected.client_person_id ? String(selected.client_person_id) : '';
        this.mileageIn = selected.km ? String(selected.km) : '';
    },
    addServiceLine() {
        this.serviceLines.push({ service_id: '', qty: 1, unit_price: 0 });
    },
    removeServiceLine(index) {
        if (this.serviceLines.length === 1) {
            this.serviceLines = [{ service_id: '', qty: 1, unit_price: 0 }];
            return;
        }
        this.serviceLines.splice(index, 1);
    },
    onServiceChange(index) {
        const service = this.servicesCatalog.find(s => String(s.id) === String(this.serviceLines[index].service_id));
        if (!service) return;
        this.serviceLines[index].unit_price = Number(service.base_price || 0);
    },
    lineSubtotal(line) {
        const qty = Number(line.qty || 0);
        const price = Number(line.unit_price || 0);
        return qty * price;
    },
    estimatedTotal() {
        return this.serviceLines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
    }
}">
    <x-common.page-breadcrumb pageTitle="Tablero de Mantenimiento" />

    <x-common.component-card title="Tablero Circular de Servicios" desc="Inicia y finaliza mantenimientos con visual de moto y cliente en tiempo real.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff" @click="$dispatch('open-board-create-modal')">
                <i class="ri-add-circle-line"></i><span>Agregar Vehiculo e Iniciar</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}">
                <i class="ri-file-list-3-line"></i><span>Ir a Ordenes de Servicio</span>
            </x-ui.link-button>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($cards as $card)
                @php
                    $status = (string) $card->status;
                    $statusMap = [
                        'draft' => ['Borrador', 'bg-slate-100 text-slate-700 border-slate-200'],
                        'diagnosis' => ['Diagnostico', 'bg-indigo-100 text-indigo-700 border-indigo-200'],
                        'awaiting_approval' => ['Esperando aprobacion', 'bg-amber-100 text-amber-700 border-amber-200'],
                        'approved' => ['Aprobado', 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                        'in_progress' => ['En reparacion', 'bg-orange-100 text-orange-700 border-orange-200'],
                        'finished' => ['Terminado', 'bg-cyan-100 text-cyan-700 border-cyan-200'],
                    ];
                    [$statusLabel, $statusClass] = $statusMap[$status] ?? [strtoupper($status), 'bg-gray-100 text-gray-700 border-gray-200'];
                @endphp
                <div class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-gradient-to-br from-orange-200/40 to-indigo-200/40"></div>

                    <div class="relative z-10 mb-4 flex items-center justify-between">
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="text-xs font-medium text-gray-500">OS {{ $card->movement?->number ?? ('#' . $card->id) }}</span>
                    </div>

                    <div class="relative z-10 mb-4 flex justify-center">
                        <div class="flex h-44 w-44 items-center justify-center rounded-full border-8 border-white shadow-inner"
                             style="background: radial-gradient(circle at 30% 30%, #0f172a, #1e293b 60%, #334155);">
                            <div class="text-center text-white">
                                <i class="ri-motorbike-fill text-6xl leading-none text-orange-300"></i>
                                <p class="mt-2 text-xs uppercase tracking-[0.2em] text-slate-200">Mantenimiento</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 space-y-1 text-sm">
                        <p class="font-semibold text-gray-900">{{ $card->vehicle?->brand }} {{ $card->vehicle?->model }}</p>
                        <p class="text-gray-600">Placa: <span class="font-medium">{{ $card->vehicle?->plate ?: 'S/PLACA' }}</span></p>
                        <p class="text-gray-600">Cliente: <span class="font-medium">{{ trim(($card->client?->first_name ?? '') . ' ' . ($card->client?->last_name ?? '')) }}</span></p>
                        <p class="text-gray-600">Ingreso: <span class="font-medium">{{ optional($card->intake_date)->format('Y-m-d H:i') }}</span></p>
                        <p class="text-gray-700">Total: <span class="font-semibold">S/ {{ number_format((float) $card->total, 2) }}</span></p>
                    </div>

                    <div class="relative z-10 mt-5 flex flex-wrap gap-2">
                        @if($card->status === 'approved')
                            <form method="POST" action="{{ route('workshop.maintenance-board.start', $card) }}">
                                @csrf
                                <button class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white">Iniciar servicio</button>
                            </form>
                        @endif
                        @if($card->status === 'in_progress')
                            <form method="POST" action="{{ route('workshop.maintenance-board.finish', $card) }}">
                                @csrf
                                <button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Finalizar servicio</button>
                            </form>
                        @endif
                        <a href="{{ route('workshop.orders.show', $card) }}" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white">Ver detalle</a>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <i class="ri-motorbike-line text-5xl text-gray-300"></i>
                    <p class="mt-3 text-sm text-gray-600">No hay servicios activos. Agrega un vehiculo para iniciar mantenimiento.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-5">{{ $cards->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-board-create-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nuevo ingreso a mantenimiento</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.maintenance-board.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @csrf
                <select name="vehicle_id" x-model="selectedVehicleId" @change="syncVehicle()" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="">Selecciona vehiculo</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->brand }} {{ $vehicle->model }} {{ $vehicle->plate ? ('- '.$vehicle->plate) : '(S/PLACA)' }}</option>
                    @endforeach
                </select>

                <select name="client_person_id" x-model="selectedClientId" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="">Selecciona cliente</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }} - {{ $client->person_type }} {{ $client->document_number }}</option>
                    @endforeach
                </select>

                <input name="mileage_in" type="number" min="0" x-model="mileageIn" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM ingreso">

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 md:col-span-1">
                    <input type="checkbox" name="tow_in" value="1" class="h-4 w-4 rounded border-gray-300">
                    Ingreso en grua
                </label>

                <input name="diagnosis_text" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Diagnostico inicial (opcional)">
                <textarea name="observations" rows="3" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" placeholder="Observaciones"></textarea>

                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 md:col-span-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-800">Servicios a realizar</h4>
                        <button type="button" @click="addServiceLine()" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Agregar servicio</button>
                    </div>
                    <template x-for="(line, index) in serviceLines" :key="index">
                        <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-12">
                            <div class="md:col-span-6">
                                <select :name="`service_lines[${index}][service_id]`" x-model="line.service_id" @change="onServiceChange(index)" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                    <option value="">Selecciona servicio</option>
                                    <template x-for="service in servicesCatalog" :key="service.id">
                                        <option :value="service.id" x-text="`${service.name} (${service.type})`"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <input type="number" step="0.01" min="0.01" :name="`service_lines[${index}][qty]`" x-model="line.qty" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cant.">
                            </div>
                            <div class="md:col-span-3">
                                <input type="number" step="0.01" min="0" :name="`service_lines[${index}][unit_price]`" x-model="line.unit_price" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio">
                            </div>
                            <div class="md:col-span-1">
                                <button type="button" @click="removeServiceLine(index)" class="h-11 w-full rounded-lg bg-red-600 text-white">X</button>
                            </div>
                            <div class="md:col-span-12 text-right text-xs text-gray-600">
                                Subtotal linea: S/ <span x-text="lineSubtotal(line).toFixed(2)"></span>
                            </div>
                        </div>
                    </template>
                    <div class="mt-2 border-t border-gray-200 pt-2 text-right text-sm font-semibold text-gray-800">
                        Total estimado: S/ <span x-text="estimatedTotal().toFixed(2)"></span>
                    </div>
                </div>

                <div class="md:col-span-3 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                        <i class="ri-play-circle-line"></i><span>Iniciar mantenimiento</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
