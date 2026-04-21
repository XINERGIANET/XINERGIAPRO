@extends('layouts.app')

@section('content')
    @php
        $quotation = $quotation ?? null;
        $isEdit = $quotation !== null;
        $resolvedDefaultTaxRateId = old('default_tax_rate_id', $defaultTaxRateId ?? null);
        $defaultRow = [
            'line_type' => 'LABOR',
            'description' => 'Mano de obra',
            'qty' => 1,
            'unit_price' => 0,
            'product_id' => '',
            'service_id' => '',
            'tax_rate_id' => $resolvedDefaultTaxRateId ? (string) $resolvedDefaultTaxRateId : '',
            'discount_amount' => 0,
        ];
        $existingRows = [];
        if ($isEdit) {
            $existingRows = $quotation->details
                ->map(fn ($d) => [
                    'line_type' => (string) $d->line_type,
                    'description' => (string) $d->description,
                    'qty' => (float) $d->qty,
                    'unit_price' => (float) $d->unit_price,
                    'product_id' => $d->product_id ? (int) $d->product_id : '',
                    'service_id' => $d->service_id ? (int) $d->service_id : '',
                    'tax_rate_id' => $d->tax_rate_id ? (int) $d->tax_rate_id : '',
                    'discount_amount' => (float) ($d->discount_amount ?? 0),
                ])
                ->values()
                ->all();
        }
        $initialRows = old('items', !empty($existingRows) ? $existingRows : [$defaultRow]);
        $selectedClientId = (int) old('client_person_id', $isEdit ? (int) $quotation->client_person_id : $clientId);
        $selectedVehicleId = (int) old('vehicle_id', $isEdit ? (int) ($quotation->vehicle_id ?? 0) : 0);
        $productRows = $productOptions->map(fn ($p) => [
            'product_id' => (int) $p->product_id,
            'price' => (float) ($p->price ?? 0),
            'purchase_price' => (float) ($p->purchase_price ?? 0),
            'avg_cost' => (float) ($p->avg_cost ?? 0),
            'tax_rate_id' => $p->tax_rate_id ? (int) $p->tax_rate_id : null,
            'description' => (string) $p->description,
            'label' => trim(($p->code ? $p->code . ' - ' : '') . (string) $p->description),
        ])->values();
        $serviceRows = $services->map(fn ($s) => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'base_price' => (float) ($s->base_price ?? 0),
        ])->values();
        $taxMap = $taxRates->mapWithKeys(fn ($tr) => [(string) $tr->id => (float) $tr->tax_rate])->all();
        $commercialDefaults = [
            'quotation_delivery_time' => (string) ($quotation->quotation_commercial_terms['delivery_time'] ?? ''),
            'quotation_offer_validity' => (string) ($quotation->quotation_commercial_terms['offer_validity'] ?? '5 dias habiles'),
            'quotation_service_warranty' => (string) ($quotation->quotation_commercial_terms['service_warranty'] ?? ''),
            'quotation_delivery_place' => (string) ($quotation->quotation_commercial_terms['delivery_place'] ?? 'Centro de servicio MOTOLAB GROUP SAC.'),
            'quotation_prices_note' => (string) ($quotation->quotation_commercial_terms['prices_note'] ?? 'IGV Incluido'),
            'quotation_payment_condition' => (string) ($quotation->quotation_commercial_terms['payment_condition'] ?? 'Deposito en cuenta'),
            'quotation_bank_account_bcp' => (string) ($quotation->quotation_commercial_terms['bank_account_bcp'] ?? '30509057178051'),
            'quotation_bank_cci' => (string) ($quotation->quotation_commercial_terms['bank_cci'] ?? '00230510905717805119'),
        ];
        $formAction = $isEdit
            ? route('admin.sales.quotations.update-external', $quotation)
            : route('admin.sales.quotations.store-external');
    @endphp

    <div x-data="quotationExternalForm()" class="space-y-6">
        <x-common.page-breadcrumb pageTitle="Cotización externa" />

        <x-common.component-card title="{{ $isEdit ? 'Editar cotización externa' : 'Nueva cotización externa' }}" desc="Registro manual de cotización para cliente externo con repuestos y mano de obra.">
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $formAction }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                <input type="hidden" name="default_tax_rate_id" value="{{ $resolvedDefaultTaxRateId }}">

                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-500">Datos de cabecera</h3>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 md:items-end">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Cliente <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <select name="client_person_id" id="quotation-external-client-select" class="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none">
                                    <option value="">Seleccione…</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c->id }}" @selected($selectedClientId === (int) $c->id)>
                                            {{ $c->first_name }} {{ $c->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="open-quotation-quick-client-modal" title="Nuevo cliente" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-black text-slate-700 hover:border-[#465fff] hover:text-[#465fff]">
                                    +
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Vehículo (opcional)</label>
                            <div class="flex gap-2">
                                <select name="vehicle_id" id="quotation-external-vehicle-select" class="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none">
                                    <option value="">Sin vehículo</option>
                                    @foreach ($vehicles as $v)
                                        <option value="{{ $v->id }}" @selected($selectedVehicleId === (int) $v->id)>
                                            {{ $v->plate }} - {{ $v->brand }} {{ $v->model }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="open-quotation-quick-vehicle-modal" title="Nuevo vehículo" @if ($selectedClientId <= 0 || $vehicleTypes->isEmpty()) disabled @endif class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-black text-slate-700 hover:border-[#465fff] hover:text-[#465fff] disabled:cursor-not-allowed disabled:opacity-40">
                                    +
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                    <div class="mb-4 border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-500">Condiciones comerciales y pago</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Tiempo de entrega</label>
                            <input type="text" name="quotation_delivery_time" value="{{ old('quotation_delivery_time', $commercialDefaults['quotation_delivery_time']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Ej. 3 dias habiles">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Validez de oferta</label>
                            <input type="text" name="quotation_offer_validity" value="{{ old('quotation_offer_validity', $commercialDefaults['quotation_offer_validity']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Garantía servicio</label>
                            <input type="text" name="quotation_service_warranty" value="{{ old('quotation_service_warranty', $commercialDefaults['quotation_service_warranty']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Ej. 30 dias en mano de obra">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Lugar de entrega</label>
                            <input type="text" name="quotation_delivery_place" value="{{ old('quotation_delivery_place', $commercialDefaults['quotation_delivery_place']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Precios</label>
                            <input type="text" name="quotation_prices_note" value="{{ old('quotation_prices_note', $commercialDefaults['quotation_prices_note']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Condición de pago</label>
                            <input type="text" name="quotation_payment_condition" value="{{ old('quotation_payment_condition', $commercialDefaults['quotation_payment_condition']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Cta. Ah. S/. BCP</label>
                            <input type="text" name="quotation_bank_account_bcp" value="{{ old('quotation_bank_account_bcp', $commercialDefaults['quotation_bank_account_bcp']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 font-mono text-xs font-bold text-slate-800 focus:border-[#465fff] focus:outline-none" autocomplete="off">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">CCI</label>
                            <input type="text" name="quotation_bank_cci" value="{{ old('quotation_bank_cci', $commercialDefaults['quotation_bank_cci']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 font-mono text-xs font-bold text-slate-800 focus:border-[#465fff] focus:outline-none" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-4">
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">Líneas de cotización</h3>
                            <button type="button" @click="addRow()" class="h-9 rounded-lg bg-slate-800 px-4 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-900">
                                + Línea
                            </button>
                        </div>

                        <div class="overflow-visible quotation-lines-root">
                            <table class="w-full min-w-[980px] border-collapse text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-[11px] font-black uppercase tracking-wide text-slate-400">
                                        <th class="px-3 py-3">Tipo <span class="text-red-500">*</span></th>
                                        <th class="px-3 py-3">Catálogo</th>
                                        <th class="px-3 py-3">Descripción <span class="text-red-500">*</span></th>
                                        <th class="px-3 py-3 w-24 text-right">Cant. <span class="text-red-500">*</span></th>
                                        <th class="px-3 py-3 w-28 text-right">P. Unit. <span class="text-red-500">*</span></th>
                                        <th class="px-3 py-3 w-32">IGV</th>
                                        <th class="px-3 py-3 w-24 text-right">Dto.</th>
                                        <th class="px-3 py-3 w-28 text-right">Importe</th>
                                        <th class="px-3 py-3 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr>
                                            <td class="px-3 py-2 align-top relative-dropdown-host">
                                                <select class="h-10 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold text-slate-700 quotation-front-select focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model="row.line_type" :name="`items[${index}][line_type]`" @change="onLineTypeChange(index)">
                                                    <option value="PART">Repuesto</option>
                                                    <option value="LABOR">Mano de obra</option>
                                                    <option value="SERVICE">Servicio</option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 align-top relative-dropdown-host">
                                                <template x-if="row.line_type === 'PART'">
                                                <select class="h-10 w-full rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-700 quotation-front-select focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model="row.product_id" :name="`items[${index}][product_id]`" @change="onProductChange(index)">
                                                        <option value="">Sin catalogo (solo descripcion)</option>
                                                        @foreach ($productOptions as $p)
                                                            <option value="{{ $p->product_id }}">{{ $p->code }} - {{ $p->description }}</option>
                                                        @endforeach
                                                    </select>
                                                </template>
                                                <template x-if="row.line_type === 'SERVICE'">
                                                    <select class="h-10 w-full rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-700 quotation-front-select focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model="row.service_id" :name="`items[${index}][service_id]`" @change="onServiceChange(index)">
                                                        <option value="">Seleccione servicio</option>
                                                        @foreach ($services as $s)
                                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </template>
                                                <template x-if="row.line_type === 'LABOR'">
                                                    <div class="h-10 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-2 text-xs font-bold text-slate-400 flex items-center">
                                                        Mano de obra libre
                                                    </div>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input type="text" class="h-10 w-full rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model="row.description" :name="`items[${index}][description]`">
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input type="number" step="0.000001" min="0.000001" class="h-10 w-full rounded-lg border border-slate-200 px-2 text-right text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model.number="row.qty" :name="`items[${index}][qty]`">
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input type="number" step="0.01" min="0" class="h-10 w-full rounded-lg border border-slate-200 px-2 text-right text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model.number="row.unit_price" :name="`items[${index}][unit_price]`">
                                            </td>
                                            <td class="px-3 py-2 align-top relative-dropdown-host">
                                                <select class="h-10 w-full rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-700 quotation-front-select focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model="row.tax_rate_id" :name="`items[${index}][tax_rate_id]`">
                                                    @if ($taxRates->isEmpty())
                                                        <option value="">Sin tasas de impuesto</option>
                                                    @endif
                                                    @foreach ($taxRates as $tr)
                                                        <option value="{{ $tr->id }}">{{ $tr->description }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input type="number" step="0.01" min="0" class="h-10 w-full rounded-lg border border-slate-200 px-2 text-right text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#465fff]/20" x-model.number="row.discount_amount" :name="`items[${index}][discount_amount]`">
                                            </td>
                                            <td class="px-3 py-2 align-top text-right">
                                                <div class="h-10 inline-flex items-center text-xs font-black text-slate-700" x-text="currency(lineTotal(row))"></div>
                                            </td>
                                            <td class="px-1 py-2 align-top text-center">
                                                <button type="button" class="text-red-500 hover:text-red-700" title="Quitar" @click="removeRow(index)">
                                                    <i class="ri-delete-bin-line text-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-500">Resumen</h3>
                        <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-500">Sub total</span>
                                <span class="font-black text-slate-700" x-text="currency(summary.subtotal)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-500">IGV</span>
                                <span class="font-black text-slate-700" x-text="currency(summary.tax)"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                                <span class="font-black text-slate-700">Total cotización</span>
                                <span class="text-base font-black text-indigo-700" x-text="currency(summary.total)"></span>
                            </div>
                        </div>
                        <p class="mt-3 text-[10px] font-medium text-slate-400">
                            El total se actualiza automáticamente según cantidad, precio, IGV y descuento por línea.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('admin.sales.quotations.index', array_filter(['view_id' => request('view_id')])) }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 text-xs font-black uppercase tracking-widest text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300">
                        <i class="ri-arrow-left-line text-sm"></i>
                        <span>Volver</span>
                    </a>
                    <button type="submit" id="quotation-external-submit" @if ((int) old('client_person_id', $clientId) <= 0) disabled @endif class="h-11 rounded-xl bg-[#465fff] px-8 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-40">
                        {{ $isEdit ? 'Actualizar cotización externa' : 'Guardar cotización externa' }}
                    </button>
                </div>
            </form>
        </x-common.component-card>
    </div>

    @include('sales.partials.quick-client-modal')
    @include('workshop.quotations.partials.quick-vehicle-modal')
    <script>
        window.__quotationExternalOldVehicleId = {{ (int) old('vehicle_id', $selectedVehicleId) }};
        window.__quotationExternalHasVehicleTypes = @json(!$vehicleTypes->isEmpty());
    </script>
    @include('workshop.quotations.partials.external-quick-client-script')
    @include('workshop.quotations.partials.external-quick-vehicle-script')

    <script>
        function quotationExternalForm() {
            const initialRows = {{ \Illuminate\Support\Js::from($initialRows) }};
            const products = {{ \Illuminate\Support\Js::from($productRows) }};
            const services = {{ \Illuminate\Support\Js::from($serviceRows) }};
            const taxMap = {{ \Illuminate\Support\Js::from($taxMap) }};

            return {
                rows: initialRows,
                products,
                services,
                taxMap,
                defaultRow: {
                    line_type: 'LABOR',
                    description: 'Mano de obra',
                    qty: 1,
                    unit_price: 0,
                    product_id: '',
                    service_id: '',
                    tax_rate_id: '{{ $resolvedDefaultTaxRateId ? (string) $resolvedDefaultTaxRateId : '' }}',
                    discount_amount: 0,
                },
                get summary() {
                    let subtotal = 0;
                    let tax = 0;
                    let total = 0;
                    this.rows.forEach((row) => {
                        const qty = this.toNumber(row.qty);
                        const price = this.toNumber(row.unit_price);
                        const discount = this.toNumber(row.discount_amount);
                        const lineGross = qty * price;
                        const lineNet = Math.max(0, lineGross - discount);
                        const rate = this.taxRateFor(row);
                        const lineSub = rate > 0 ? lineNet / (1 + rate) : lineNet;
                        const lineTax = lineNet - lineSub;
                        subtotal += lineSub;
                        tax += lineTax;
                        total += lineNet;
                    });
                    return { subtotal, tax, total };
                },
                addRow() {
                    this.rows.push(JSON.parse(JSON.stringify(this.defaultRow)));
                },
                removeRow(index) {
                    if (this.rows.length > 1) {
                        this.rows.splice(index, 1);
                    }
                },
                onLineTypeChange(index) {
                    const row = this.rows[index];
                    row.product_id = '';
                    row.service_id = '';
                    if (row.line_type === 'LABOR' && (!row.description || row.description.trim() === '')) {
                        row.description = 'Mano de obra';
                    }
                },
                onProductChange(index) {
                    const row = this.rows[index];
                    const pid = parseInt(row.product_id, 10);
                    if (!pid) {
                        return;
                    }
                    const p = this.products.find((x) => Number(x.product_id) === pid);
                    if (!p) {
                        return;
                    }
                    row.description = (p.description && String(p.description).trim() !== '') ? p.description : p.label;
                    const salePrice = this.toNumber(p.price);
                    const purchasePrice = this.toNumber(p.purchase_price);
                    const avgCost = this.toNumber(p.avg_cost);
                    row.unit_price = salePrice > 0 ? salePrice : (purchasePrice > 0 ? purchasePrice : avgCost);
                    if (p.tax_rate_id) {
                        row.tax_rate_id = String(p.tax_rate_id);
                    }
                },
                onServiceChange(index) {
                    const row = this.rows[index];
                    const sid = parseInt(row.service_id, 10);
                    if (!sid) {
                        return;
                    }
                    const s = this.services.find((x) => x.id === sid);
                    if (!s) {
                        return;
                    }
                    row.description = s.name;
                    row.unit_price = s.base_price;
                },
                lineTotal(row) {
                    const qty = this.toNumber(row.qty);
                    const price = this.toNumber(row.unit_price);
                    const discount = this.toNumber(row.discount_amount);
                    return Math.max(0, (qty * price) - discount);
                },
                taxRateFor(row) {
                    if (!row.tax_rate_id) {
                        return 0;
                    }
                    const pct = this.toNumber(this.taxMap[String(row.tax_rate_id)] ?? 0);
                    return pct / 100;
                },
                toNumber(value) {
                    const parsed = parseFloat(value);
                    if (Number.isNaN(parsed)) {
                        return 0;
                    }
                    return parsed;
                },
                currency(value) {
                    return 'S/ ' + this.toNumber(value).toLocaleString('es-PE', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
            };
        }
    </script>

    <style>
        .quotation-lines-root {
            overflow: visible;
        }
        .relative-dropdown-host {
            position: relative;
            z-index: 1;
        }
        .relative-dropdown-host:focus-within {
            z-index: 999;
        }
        .quotation-front-select {
            position: relative;
            z-index: 2;
        }
        .quotation-lines-root .ts-dropdown,
        .quotation-lines-root .choices__list--dropdown,
        .quotation-lines-root .select2-dropdown,
        .quotation-lines-root .vscomp-options-container,
        .quotation-lines-root .dropdown-menu {
            z-index: 9999 !important;
        }
    </style>
@endsection
