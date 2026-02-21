@extends('layouts.app')

@section('content')
<div x-data="{
    vehicles: @js($vehicles->map(fn($v) => ['id' => $v->id, 'client_person_id' => $v->client_person_id, 'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) , 'km' => (int) ($v->current_mileage ?? 0)])),
    vehicleTypes: @js($vehicleTypes->map(fn($type) => ['id' => $type->id, 'name' => $type->name])),
    servicesCatalog: @js($services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'base_price' => (float) $s->base_price, 'type' => $s->type])),
    selectedVehicleId: '',
    selectedClientId: '',
    mileageIn: '',
    creatingVehicle: false,
    creatingVehicleLoading: false,
    quickVehicleError: '',
    quickVehicle: {
        client_person_id: '',
        vehicle_type_id: @js(optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id ?? optional($vehicleTypes->first())->id ?? ''),
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
            vehicle_type_id: @js(optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id ?? optional($vehicleTypes->first())->id ?? ''),
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

        <div class="mb-6 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.maintenance-board.create') }}" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                    <i class="ri-add-circle-line"></i><span>Agregar Vehiculo e Iniciar</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}">
                    <i class="ri-file-list-3-line"></i><span>Ir a Ordenes de Servicio</span>
                </x-ui.link-button>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <label for="status" class="text-sm font-medium text-slate-700">Filtrar por estado:</label>
                <select
                    id="status"
                    name="status"
                    class="h-10 min-w-[220px] rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none"
                    onchange="this.form.submit()"
                >
                    <option value="all" @selected(($selectedStatus ?? 'in_progress') === 'all')>Todos</option>
                    <option value="draft" @selected(($selectedStatus ?? 'in_progress') === 'draft')>Borrador</option>
                    <option value="diagnosis" @selected(($selectedStatus ?? 'in_progress') === 'diagnosis')>Diagnostico</option>
                    <option value="awaiting_approval" @selected(($selectedStatus ?? 'in_progress') === 'awaiting_approval')>Esperando aprobacion</option>
                    <option value="approved" @selected(($selectedStatus ?? 'in_progress') === 'approved')>Aprobado</option>
                    <option value="in_progress" @selected(($selectedStatus ?? 'in_progress') === 'in_progress')>En reparacion</option>
                    <option value="finished" @selected(($selectedStatus ?? 'in_progress') === 'finished')>Terminado</option>
                    <option value="delivered" @selected(($selectedStatus ?? 'in_progress') === 'delivered')>Entregado</option>
                    <option value="cancelled" @selected(($selectedStatus ?? 'in_progress') === 'cancelled')>Anulado</option>
                </select>
                <x-ui.link-button size="sm" variant="outline" href="{{ route('workshop.maintenance-board.index', ['status' => 'in_progress']) }}">
                    <i class="ri-refresh-line"></i><span>Limpiar</span>
                </x-ui.link-button>
            </form>
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
                        'delivered' => ['Entregado', 'bg-green-100 text-green-700 border-green-200'],
                        'cancelled' => ['Anulado', 'bg-rose-100 text-rose-700 border-rose-200'],
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

    @php
        $defaultDocumentTypeId = optional(
            $documentTypes->first(function ($documentType) {
                return str_contains(mb_strtolower((string) $documentType->name), 'ticket');
            }) ?? $documentTypes->first()
        )->id;
        $defaultCashRegisterId = optional($cashRegisters->first())->id;
        $defaultPaymentMethodId = optional($paymentMethods->first())->id;
    @endphp

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
        class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600">Cierre Comercial</p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Venta y cobro rapido</h3>
                    <p class="text-sm text-gray-500" x-text="order_label"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total OS</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-800" x-text="`S/ ${total.toFixed(2)}`"></p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Pagado</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-700" x-text="`S/ ${paid_total.toFixed(2)}`"></p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Pendiente</p>
                    <p class="mt-1 text-xl font-extrabold text-amber-700" x-text="`S/ ${debt.toFixed(2)}`"></p>
                </div>
            </div>

            <form method="POST" :action="action" class="space-y-4">
                @csrf

                <input type="hidden" name="generate_sale" :value="pending_billing_count > 0 ? 1 : 0">

                <div x-show="pending_billing_count > 0" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-indigo-700">Documento de venta</label>
                    <select name="document_type_id" class="h-11 w-full rounded-lg border border-indigo-200 bg-white px-3 text-sm font-medium text-slate-800" :required="pending_billing_count > 0">
                        @foreach($documentTypes as $documentType)
                            <option value="{{ $documentType->id }}" @selected((int) $defaultDocumentTypeId === (int) $documentType->id)>
                                {{ $documentType->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-indigo-700" x-text="`Se facturaran ${pending_billing_count} linea(s) pendiente(s), total aprox: S/ ${pending_billing_total.toFixed(2)}.`"></p>
                </div>

                <div x-show="pending_billing_count <= 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                    Esta OS ya no tiene lineas pendientes por facturar. Solo se registrara el cobro.
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Comentario venta (opcional)</label>
                    <input name="sale_comment" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Venta desde tablero de mantenimiento">
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Caja</label>
                        <select name="cash_register_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-medium text-slate-800" required>
                        @foreach($cashRegisters as $cashRegister)
                            <option value="{{ $cashRegister->id }}" @selected((int) $defaultCashRegisterId === (int) $cashRegister->id)>
                                {{ $cashRegister->number }} {{ $cashRegister->status ? '(Activa)' : '' }}
                            </option>
                        @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Metodo de pago</label>
                        <select name="payment_methods[0][payment_method_id]" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-medium text-slate-800" required>
                            @foreach($paymentMethods as $paymentMethod)
                                <option value="{{ $paymentMethod->id }}" @selected((int) $defaultPaymentMethodId === (int) $paymentMethod->id)>
                                    {{ $paymentMethod->description }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
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
                    <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#0ea5e9,#2563eb);color:#fff">
                        <i class="ri-cash-line"></i><span>Confirmar venta y cobro</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i><span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

</div>
@endsection
