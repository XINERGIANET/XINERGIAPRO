@extends('layouts.app')

@section('content')
<div x-data="{
    vehicles: @js($vehicles->map(fn($v) => ['id' => $v->id, 'client_person_id' => $v->client_person_id, 'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) , 'km' => (int) ($v->current_mileage ?? 0)])),
    vehicleTypes: @js(['moto lineal', 'moto deportiva', 'scooter', 'trimoto', 'mototaxi', 'cuatrimoto', 'bicimoto', 'auto', 'camioneta', 'furgon', 'camion', 'bus', 'minivan', 'otro']),
    servicesCatalog: @js($services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'base_price' => (float) $s->base_price, 'type' => $s->type])),
    selectedVehicleId: '',
    selectedClientId: '',
    mileageIn: '',
    creatingVehicle: false,
    creatingVehicleLoading: false,
    quickVehicleError: '',
    quickVehicle: {
        client_person_id: '',
        type: 'moto lineal',
        brand: '',
        model: '',
        year: '',
        color: '',
        plate: '',
        vin: '',
        engine_number: '',
        chassis_number: '',
        serial_number: '',
        current_mileage: ''
    },
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
    },
    resetQuickVehicle() {
        this.quickVehicle = {
            client_person_id: this.selectedClientId || '',
            type: 'moto lineal',
            brand: '',
            model: '',
            year: '',
            color: '',
            plate: '',
            vin: '',
            engine_number: '',
            chassis_number: '',
            serial_number: '',
            current_mileage: this.mileageIn || ''
        };
        this.quickVehicleError = '';
    },
    toggleQuickVehicle() {
        this.creatingVehicle = !this.creatingVehicle;
        if (this.creatingVehicle) {
            this.resetQuickVehicle();
        }
    },
    async saveQuickVehicle() {
        this.quickVehicleError = '';
        this.creatingVehicleLoading = true;
        try {
            const response = await fetch(@js(route('workshop.maintenance-board.vehicles.store')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @js(csrf_token()),
                },
                body: JSON.stringify(this.quickVehicle),
            });
            const payload = await response.json();
            if (!response.ok) {
                const message = payload?.message || 'No se pudo registrar el vehiculo.';
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || message);
            }
            this.vehicles.unshift(payload);
            this.selectedVehicleId = String(payload.id);
            this.selectedClientId = payload.client_person_id ? String(payload.client_person_id) : this.selectedClientId;
            this.mileageIn = payload.km ? String(payload.km) : this.mileageIn;
            this.creatingVehicle = false;
            this.resetQuickVehicle();
        } catch (error) {
            this.quickVehicleError = error?.message || 'Error registrando vehiculo.';
        } finally {
            this.creatingVehicleLoading = false;
        }
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
                    $pendingDebtCard = max(0, (float) $card->total - (float) $card->paid_total);
                    $pendingBillingCountCard = (int) ($card->pending_billing_count ?? 0);
                    $canCheckoutCard = in_array($card->status, ['in_progress', 'finished'], true)
                        && ($pendingDebtCard > 0 || $pendingBillingCountCard > 0);
                    $checkoutPayload = [
                        'action' => route('workshop.maintenance-board.checkout', $card),
                        'order_label' => 'OS ' . ($card->movement?->number ?? ('#' . $card->id)) . ' - ' . trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')),
                        'total' => (float) ($card->total ?? 0),
                        'paid_total' => (float) ($card->paid_total ?? 0),
                        'debt' => $pendingDebtCard,
                        'pending_billing_count' => $pendingBillingCountCard,
                        'pending_billing_total' => (float) ($card->pending_billing_total ?? 0),
                    ];
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
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <div class="absolute -right-14 -top-14 h-40 w-40 rounded-full bg-gradient-to-br from-amber-300/20 to-indigo-300/20 blur-xl"></div>
                    <div class="absolute -left-8 bottom-0 h-24 w-24 rounded-full bg-gradient-to-tr from-orange-300/20 to-transparent blur-lg"></div>

                    <div class="relative z-10 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Orden de servicio</p>
                            <p class="text-sm font-bold text-slate-800">OS {{ $card->movement?->number ?? ('#' . $card->id) }}</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="relative z-10 mt-3 rounded-2xl border border-slate-200 px-3 py-2.5 text-white"
                         style="background: linear-gradient(120deg, #0f172a 0%, #1e293b 52%, #334155 100%);">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10">
                                <svg viewBox="0 0 24 24" class="h-6 w-6 text-orange-300" fill="currentColor" aria-hidden="true">
                                    <path d="M5.5 16.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm13 0a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM14 6l-1 3h3.3a1.5 1.5 0 0 1 1.33.81l1.72 3.44a3.5 3.5 0 0 1 2.12 3.25h-1.99a2.5 2.5 0 0 0-5 0H8a2.5 2.5 0 0 0-5 0H1a3.5 3.5 0 0 1 3.5-3.5h2.1l1.37-4.12A2 2 0 0 1 9.87 7.5H12l.6-1.8A1 1 0 0 1 13.55 5h2.95v1h-2.5Z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-lg font-bold leading-tight">{{ trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')) ?: 'Vehiculo en mantenimiento' }}</p>
                                <p class="truncate text-xs tracking-wide text-slate-200">Placa {{ $card->vehicle?->plate ?: 'S/PLACA' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 mt-3 grid grid-cols-1 gap-2 text-sm text-slate-700 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cliente</p>
                            <p class="font-semibold text-slate-800">{{ trim(($card->client?->first_name ?? '') . ' ' . ($card->client?->last_name ?? '')) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ingreso</p>
                            <p class="font-semibold text-slate-800">{{ optional($card->intake_date)->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-2.5 grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Total</p>
                            <p class="text-sm font-bold text-slate-800">S/ {{ number_format((float) $card->total, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-emerald-700">Pagado</p>
                            <p class="text-sm font-bold text-emerald-700">S/ {{ number_format((float) $card->paid_total, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-amber-700">Pendiente</p>
                            <p class="text-sm font-bold text-amber-700">S/ {{ number_format(max(0, (float) $card->total - (float) $card->paid_total), 2) }}</p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-3.5 flex flex-wrap gap-2">
                        @if($card->status === 'approved')
                            <form method="POST" action="{{ route('workshop.maintenance-board.start', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-700">Iniciar servicio</button>
                            </form>
                        @endif
                        @if($card->status === 'in_progress')
                            <form method="POST" action="{{ route('workshop.maintenance-board.finish', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">Finalizar servicio</button>
                            </form>
                        @endif
                        @if($canCheckoutCard)
                            <button type="button"
                                    class="rounded-xl bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-800"
                                    @click="$dispatch('open-board-checkout-modal', @js($checkoutPayload))">
                                Venta y cobro
                            </button>
                        @endif
                        <a href="{{ route('workshop.orders.show', $card) }}" class="rounded-xl bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">Ver detalle</a>
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

    <x-ui.modal
        x-data="{
            open: false,
            action: '',
            order_label: '',
            total: 0,
            paid_total: 0,
            debt: 0,
            pending_billing_count: 0,
            pending_billing_total: 0
        }"
        x-on:open-board-checkout-modal.window="
            open = true;
            action = $event.detail.action;
            order_label = $event.detail.order_label;
            total = Number($event.detail.total || 0);
            paid_total = Number($event.detail.paid_total || 0);
            debt = Number($event.detail.debt || 0);
            pending_billing_count = Number($event.detail.pending_billing_count || 0);
            pending_billing_total = Number($event.detail.pending_billing_total || 0);
        "
        :isOpen="false"
        :showCloseButton="false"
        class="max-w-3xl">
        <div class="p-6 sm:p-8">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Venta y cobro rapido</h3>
                    <p class="text-sm text-gray-500" x-text="order_label"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm md:grid-cols-3">
                <p>Total OS: <strong x-text="`S/ ${total.toFixed(2)}`"></strong></p>
                <p>Pagado: <strong x-text="`S/ ${paid_total.toFixed(2)}`"></strong></p>
                <p>Pendiente: <strong x-text="`S/ ${debt.toFixed(2)}`"></strong></p>
            </div>

            <form method="POST" :action="action" class="space-y-4">
                @csrf

                <input type="hidden" name="generate_sale" :value="pending_billing_count > 0 ? 1 : 0">

                <div x-show="pending_billing_count > 0">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Documento de venta</label>
                    <select name="document_type_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" :required="pending_billing_count > 0">
                        <option value="">Selecciona documento</option>
                        @foreach($documentTypes as $documentType)
                            <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-text="`Se facturaran ${pending_billing_count} linea(s) pendiente(s), total aprox: S/ ${pending_billing_total.toFixed(2)}.`"></p>
                </div>

                <div x-show="pending_billing_count <= 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                    Esta OS ya no tiene lineas pendientes por facturar. Solo se registrara el cobro.
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Comentario venta (opcional)</label>
                    <input name="sale_comment" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Venta desde tablero de mantenimiento">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Caja</label>
                    <select name="cash_register_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Selecciona caja</option>
                        @foreach($cashRegisters as $cashRegister)
                            <option value="{{ $cashRegister->id }}">{{ $cashRegister->number }} {{ $cashRegister->status ? '(Activa)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Metodo de pago</label>
                        <select name="payment_methods[0][payment_method_id]" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="">Selecciona metodo</option>
                            @foreach($paymentMethods as $paymentMethod)
                                <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Monto</label>
                        <input type="number" step="0.01" min="0.01" name="payment_methods[0][amount]" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" :value="debt.toFixed(2)" required>
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Referencia</label>
                        <input name="payment_methods[0][reference]" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Operacion / voucher">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Comentario cobro (opcional)</label>
                    <input name="payment_comment" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cobro registrado desde tablero">
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#1d4ed8,#4338ca);color:#fff">
                        <i class="ri-cash-line"></i><span>Confirmar venta y cobro</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i><span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

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
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2">
                        <select name="vehicle_id" x-model="selectedVehicleId" @change="syncVehicle()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="">Selecciona vehiculo</option>
                            <template x-for="vehicle in vehicles" :key="`v-${vehicle.id}`">
                                <option :value="vehicle.id" x-text="vehicle.label || `Vehiculo #${vehicle.id}`"></option>
                            </template>
                        </select>
                        <button type="button"
                                @click="toggleQuickVehicle()"
                                class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                            <i class="ri-add-line"></i>
                            <span class="ml-1 hidden sm:inline">Nuevo</span>
                        </button>
                    </div>
                </div>

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

                <div x-show="creatingVehicle" x-cloak class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 md:col-span-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-indigo-800">Registrar vehiculo rapido</h4>
                        <button type="button" @click="creatingVehicle = false" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">Cerrar</button>
                    </div>

                    <div x-show="quickVehicleError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickVehicleError"></div>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                        <select x-model="quickVehicle.client_person_id" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                            @endforeach
                        </select>
                        <select x-model="quickVehicle.type" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                            <template x-for="type in vehicleTypes" :key="`type-${type}`">
                                <option :value="type" x-text="type"></option>
                            </template>
                        </select>
                        <input x-model="quickVehicle.brand" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca">
                        <input x-model="quickVehicle.model" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo">
                        <input x-model="quickVehicle.year" type="number" min="1900" max="2100" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Anio">
                        <input x-model="quickVehicle.color" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                        <input x-model="quickVehicle.plate" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Placa">
                        <input x-model="quickVehicle.vin" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                        <input x-model="quickVehicle.engine_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro motor">
                        <input x-model="quickVehicle.chassis_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro chasis">
                        <input x-model="quickVehicle.serial_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                        <input x-model="quickVehicle.current_mileage" type="number" min="0" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM actual">
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button"
                                @click="saveQuickVehicle()"
                                :disabled="creatingVehicleLoading"
                                class="inline-flex h-10 items-center rounded-lg bg-indigo-700 px-4 text-xs font-semibold text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="ri-save-line"></i>
                            <span class="ml-1" x-text="creatingVehicleLoading ? 'Guardando...' : 'Guardar vehiculo'"></span>
                        </button>
                        <span class="text-xs text-gray-600">Se agregara y seleccionara automaticamente.</span>
                    </div>
                </div>

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
