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
        $quotationDateDefault = old('quotation_date', $isEdit ? optional($quotation->intake_date)->format('Y-m-d') : now()->format('Y-m-d'));
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
            'quotation_delivery_time' => (string) ($quotation->quotation_commercial_terms['delivery_time'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Tiempo de entrega', '')),
            'quotation_offer_validity' => (string) ($quotation->quotation_commercial_terms['offer_validity'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Validez de oferta', '5 dias habiles')),
            'quotation_service_warranty' => (string) ($quotation->quotation_commercial_terms['service_warranty'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Garantía de servicio', '')),
            'quotation_delivery_place' => (string) ($quotation->quotation_commercial_terms['delivery_place'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Lugar de entrega', 'Centro de servicio')),
            'quotation_currency_note' => (string) ($quotation->quotation_commercial_terms['currency_note'] ?? 'Cotizacion expresado en soles'),
            'quotation_credit_days' => (string) ($quotation->quotation_commercial_terms['credit_days'] ?? ''),
            'quotation_prices_note' => (string) ($quotation->quotation_commercial_terms['prices_note'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Nota de precios', 'IGV Incluido')),
            'quotation_payment_condition' => (string) ($quotation->quotation_commercial_terms['payment_condition'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Condición de pago', 'Deposito en cuenta')),
            'quotation_bank_account_bcp' => (string) ($quotation->quotation_commercial_terms['bank_account_bcp'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: Cuenta BCP', '')),
            'quotation_bank_cci' => (string) ($quotation->quotation_commercial_terms['bank_cci'] ?? \App\Helpers\ParameterHelper::getBranchValue('Cotización: CCI', '')),
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

                    <div class="grid gap-4 md:grid-cols-3 md:items-end">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha</label>
                            <input type="date" name="quotation_date" value="{{ $quotationDateDefault }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Cliente <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <div class="relative min-w-0 flex-1" @click.outside="externalQuotationClientDropdownOpen = false">
                                    <input
                                        x-model="externalQuotationClientSearch"
                                        @focus="externalQuotationClientDropdownOpen = true"
                                        @click="externalQuotationClientDropdownOpen = true"
                                        @input="externalQuotationClientDropdownOpen = true"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none"
                                        placeholder="Buscar cliente..."
                                        autocomplete="off"
                                    >
                                    <input type="hidden" name="client_person_id" x-model="externalQuotationSelectedClientId">
                                    <div
                                        x-show="externalQuotationClientDropdownOpen"
                                        x-cloak
                                        class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                                    >
                                        <template x-if="externalQuotationFilteredClients().length === 0">
                                            <p class="px-3 py-2 text-xs text-slate-500">Sin resultados.</p>
                                        </template>
                                        <template x-for="client in externalQuotationFilteredClients()" :key="`ext-client-${client.id}`">
                                            <button
                                                type="button"
                                                @click="externalQuotationSelectClient(client)"
                                                class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left hover:bg-slate-50 last:border-b-0"
                                            >
                                                <span class="text-xs font-medium text-slate-800" x-text="`${client.document_number || ''} - ${((client.first_name || '') + ' ' + (client.last_name || '')).trim()}`"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <button type="button" @click="creatingClient = true" title="Nuevo cliente" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white hover:shadow-lg" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;">
                                    <i class="ri-add-line text-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Vehículo (opcional)</label>
                            <div class="flex gap-2">
                                <div class="relative min-w-0 flex-1" @click.outside="externalQuotationVehicleDropdownOpen = false">
                                    <input
                                        x-model="externalQuotationVehicleSearch"
                                        @focus="externalQuotationVehicleDropdownOpen = true"
                                        @click="externalQuotationVehicleDropdownOpen = true"
                                        @input="externalQuotationOnVehicleSearchInput()"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none"
                                        placeholder="Buscar vehículo..."
                                        autocomplete="off"
                                    >
                                    <input type="hidden" name="vehicle_id" x-model="externalQuotationSelectedVehicleId">
                                    <div
                                        x-show="externalQuotationVehicleDropdownOpen"
                                        x-cloak
                                        class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                                    >
                                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-3 py-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Vehículos</p>
                                            <button type="button" @click="externalQuotationVehicleDropdownOpen = false" class="text-xs font-semibold text-slate-500 hover:text-slate-700">
                                                Cerrar
                                            </button>
                                        </div>
                                        <template x-if="externalQuotationFilteredVehicles().length === 0">
                                            <p class="px-3 py-2 text-xs text-slate-500">Sin resultados.</p>
                                        </template>
                                        <template x-for="vehicle in externalQuotationFilteredVehicles()" :key="`ext-vehicle-${vehicle.id}`">
                                            <button
                                                type="button"
                                                @click="externalQuotationSelectVehicle(vehicle)"
                                                class="flex w-full items-start justify-between border-b border-slate-100 px-3 py-2 text-left hover:bg-slate-50"
                                            >
                                                <span class="font-medium text-slate-800" x-text="vehicle.label || `Vehículo #${vehicle.id}`"></span>
                                                <span class="ml-3 text-xs text-slate-500" x-text="vehicle.client_name ? `(${vehicle.client_name})` : ''"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <button type="button"
                                        @click="externalQuotationToggleCreatingVehicle()"
                                        :disabled="externalQuotationSelectedClientId <= 0 || vehicleTypes.length === 0"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-white hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-40" style="background:linear-gradient(90deg,#465fff,#3b47d9);color:#fff;">
                                    <i class="ri-add-line text-lg"></i>
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
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Moneda</label>
                            <input type="text" name="quotation_currency_note" value="{{ old('quotation_currency_note', $commercialDefaults['quotation_currency_note']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Credito en dias</label>
                            <input type="number" min="0" step="1" name="quotation_credit_days" value="{{ old('quotation_credit_days', $commercialDefaults['quotation_credit_days']) }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Ej. 30">
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

                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                    <div class="mb-4 border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-500">Observaciones</h3>
                    </div>
                    <textarea name="observations" rows="4" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Observaciones que aparecerán en la parte inferior de la cotización">{{ old('observations', $isEdit ? (string) ($quotation->observations ?? '') : '') }}</textarea>
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
                                                <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id || ''">
                                                <input type="hidden" :name="`items[${index}][service_id]`" :value="row.service_id || ''">
                                                <template x-if="row.line_type === 'PART'">
                                                    <div>
                                                        <x-form.select-autocomplete-inline
                                                            fieldKeyExpr="'quotation-product-' + index"
                                                            valueVar="row.product_id"
                                                            optionsListExpr="products"
                                                            optionLabel="label"
                                                            optionValue="product_id"
                                                            emptyText="Sin catalogo (solo descripcion)"
                                                            placeholderSearch="Buscar producto..."
                                                            pickExpr="row.product_id = String(opt.product_id); onProductChange(index)"
                                                            inputClass="h-10 w-full rounded-lg border-slate-200 px-2 text-xs font-semibold text-slate-700 quotation-front-select"
                                                        />
                                                    </div>
                                                </template>
                                                <template x-if="row.line_type === 'SERVICE'">
                                                    <div>
                                                        <x-form.select-autocomplete-inline
                                                            fieldKeyExpr="'quotation-service-' + index"
                                                            valueVar="row.service_id"
                                                            optionsListExpr="services"
                                                            optionLabel="name"
                                                            optionValue="id"
                                                            emptyText="Seleccione servicio"
                                                            placeholderSearch="Buscar servicio..."
                                                            pickExpr="row.service_id = String(opt.id); onServiceChange(index)"
                                                            inputClass="h-10 w-full rounded-lg border-slate-200 px-2 text-xs font-semibold text-slate-700 quotation-front-select"
                                                        />
                                                    </div>
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

    <!-- Modal Cliente -->
    <div x-show="creatingClient" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div class="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-2xl bg-white shadow-2xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cliente</h3>
                <button type="button" @click="creatingClient = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div x-show="quickClientError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickClientError"></div>

            <form @submit.prevent="saveQuickClient()" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                    <select x-model="quickClient.person_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="DNI">DNI</option>
                        <option value="RUC">RUC</option>
                        <option value="CARNET DE EXTRANGERIA">CARNET DE EXTRANGERIA</option>
                        <option value="PASAPORTE">PASAPORTE</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                    <div class="flex items-center gap-2">
                        <input
                            x-model="quickClient.document_number"
                            class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                            :placeholder="isQuickClientRuc() ? 'Ingrese el RUC (11 digitos)' : 'Documento'"
                            required
                        >
                        <button
                            type="button"
                            @click="fetchQuickClientDocumentData()"
                            :disabled="creatingClientLoading"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
                        >
                            <i class="ri-search-line"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" x-text="isQuickClientRuc() ? 'Razon social' : 'Nombres'"></label>
                    <input
                        x-model="quickClient.first_name"
                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        :placeholder="isQuickClientRuc() ? 'Razon social' : 'Nombres'"
                        required
                    >
                </div>
                <div x-show="!isQuickClientRuc()" x-cloak>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                    <input
                        x-model="quickClient.last_name"
                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        placeholder="Apellidos"
                        :required="!isQuickClientRuc()"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" x-text="isQuickClientRuc() ? 'Fecha de inscripcion' : 'Fecha de nacimiento'"></label>
                    <div class="flex items-center gap-2">
                        <input type="date" x-ref="quickClientFechaInput" x-model="quickClient.fecha_nacimiento" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-sm">
                        <button type="button" @click="$refs.quickClientFechaInput?.showPicker?.()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100" aria-label="Abrir calendario" title="Abrir calendario">
                            <i class="ri-calendar-line text-xl"></i>
                        </button>
                    </div>
                </div>
                <div x-show="!isQuickClientRuc()" x-cloak>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                    <select x-model="quickClient.genero" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione genero</option>
                        <option value="MASCULINO">MASCULINO</option>
                        <option value="FEMENINO">FEMENINO</option>
                        <option value="OTRO">OTRO</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                    <input x-model="quickClient.phone" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el telefono">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" x-model="quickClient.email" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el email">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                    <input x-model="quickClient.address" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Direccion (opcional)">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                    <select x-model="quickClient.department_id" @change="onClientDepartmentChange()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione departamento</option>
                        <template x-for="department in departments" :key="department.id">
                            <option
                                :value="String(department.id)"
                                :selected="String(department.id) === String(quickClient.department_id || '')"
                                x-text="department.name"
                            ></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                    <select x-model="quickClient.province_id" @change="onClientProvinceChange()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione provincia</option>
                        <template x-for="province in filteredProvinces" :key="province.id">
                            <option
                                :value="String(province.id)"
                                :selected="String(province.id) === String(quickClient.province_id || '')"
                                x-text="province.name"
                            ></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                    <select x-model="quickClient.location_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione distrito</option>
                        <template x-for="district in filteredDistricts" :key="district.id">
                            <option
                                :value="String(district.id)"
                                :selected="String(district.id) === String(quickClient.location_id || '')"
                                x-text="district.name"
                            ></option>
                        </template>
                    </select>
                </div>

                <div class="md:col-span-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                    <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox" checked disabled class="h-4 w-4 rounded border-gray-300 text-brand-500">
                        <span>Cliente</span>
                    </div>
                </div>

                <div class="md:col-span-4 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" style="background-color:#00A389;color:#fff;">
                        <i class="ri-save-line"></i><span x-text="creatingClientLoading ? 'Guardando...' : 'Guardar cliente'"></span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="creatingClient = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
        </div>
    </div>

    <!-- Modal Vehículo -->
    <div x-show="creatingVehicle" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-800">Registrar vehiculo rapido</h3>
                    <button type="button" @click="creatingVehicle = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>

            <div x-show="quickVehicleError" class="mx-6 mt-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickVehicleError"></div>

            <div class="p-6">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de vehículo</label>
                        <select x-model="quickVehicle.vehicle_type_id" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <template x-for="type in vehicleTypes" :key="`type-${type.id}`">
                                <option :value="type.id" x-text="String(type.name || '').toUpperCase()"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Marca</label>
                        <input x-model="quickVehicle.brand" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                        <input x-model="quickVehicle.model" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Año</label>
                        <input x-model="quickVehicle.year" type="number" min="1900" max="2100" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Año">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                        <input x-model="quickVehicle.color" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Placa <span class="text-red-600">*</span></label>
                        <div class="flex items-center gap-2">
                            <input x-model="quickVehicle.plate"
                                @blur="lookupQuickVehicleByPlate()"
                                @input="quickVehicle.plate = normalizePlateForLookup(quickVehicle.plate)"
                                class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Placa">
                            <button type="button"
                                @click="lookupQuickVehicleByPlate()"
                                :disabled="lookingUpPlate"
                                class="inline-flex h-10 shrink-0 items-center rounded-lg border border-blue-300 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="ri-search-line mr-1"></i>
                                <span x-text="lookingUpPlate ? 'Buscando...' : 'Buscar placa'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">VIN <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.vin" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro. motor <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.engine_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro. motor">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro. chasis</label>
                        <input x-model="quickVehicle.chassis_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro. chasis">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Serial</label>
                        <input x-model="quickVehicle.serial_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">KM actual <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.current_mileage" type="number" min="0" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM actual">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada (cc)</label>
                        <input x-model="quickVehicle.engine_displacement_cc" type="number" min="1" max="5000" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cilindrada (cc)">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">SOAT Vencimiento</label>
                        <input x-model="quickVehicle.soat_vencimiento" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Rev. Técnica Vencimiento</label>
                        <input x-model="quickVehicle.revision_tecnica_vencimiento" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
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
        </div>
    </div>

    </div>

    @include('sales.partials.quick-client-modal')
    <script>
        window.__quotationExternalOldVehicleId = {{ (int) old('vehicle_id', $selectedVehicleId) }};
        window.__quotationExternalHasVehicleTypes = @json(!$vehicleTypes->isEmpty());
    </script>
    @include('workshop.quotations.partials.external-quick-client-script')

    <script>
        function quotationExternalForm() {
            const initialRows = {{ \Illuminate\Support\Js::from($initialRows) }};
            const products = {{ \Illuminate\Support\Js::from($productRows) }};
            const services = {{ \Illuminate\Support\Js::from($serviceRows) }};
            const taxMap = {{ \Illuminate\Support\Js::from($taxMap) }};
            const allClients = {{ \Illuminate\Support\Js::from($clients->map(fn($c) => ['id' => $c->id, 'first_name' => $c->first_name, 'last_name' => $c->last_name, 'person_type' => $c->person_type, 'document_number' => $c->document_number])) }};
            const allVehicles = {{ \Illuminate\Support\Js::from($vehicles->map(fn($v) => ['id' => $v->id, 'client_person_id' => $v->client_person_id, 'plate' => $v->plate, 'brand' => $v->brand, 'model' => $v->model, 'engine_displacement_cc' => $v->engine_displacement_cc, 'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')), 'client_name' => trim(($clients->firstWhere('id', $v->client_person_id)?->first_name ?? '') . ' ' . ($clients->firstWhere('id', $v->client_person_id)?->last_name ?? ''))])) }};

            const autocompleteHelpers = typeof formAutocompleteHelpers === 'function' ? formAutocompleteHelpers() : {};

            return {
                ...autocompleteHelpers,
                rows: initialRows,
                products,
                services,
                taxMap,
                allClients,
                allVehicles,
                departments: {{ \Illuminate\Support\Js::from($departments ?? []) }},
                provinces: {{ \Illuminate\Support\Js::from($provinces ?? []) }},
                districts: {{ \Illuminate\Support\Js::from($districts ?? []) }},
                externalQuotationSelectedClientId: @json((string) $selectedClientId),
                externalQuotationSelectedVehicleId: @json((string) $selectedVehicleId),
                externalQuotationClientSearch: '',
                externalQuotationVehicleSearch: '',
                externalQuotationClientDropdownOpen: false,
                externalQuotationVehicleDropdownOpen: false,
                vehicleTypes: {{ \Illuminate\Support\Js::from($vehicleTypes) }},
                // Modal states
                creatingClient: false,
                creatingVehicle: false,
                creatingClientLoading: false,
                creatingVehicleLoading: false,
                quickClientError: '',
                quickVehicleError: '',
                // Quick client data
                quickClient: {
                    person_type: 'DNI',
                    document_number: '',
                    first_name: '',
                    last_name: '',
                    phone: '',
                    email: '',
                    address: '-',
                    location_id: '',
                    department_id: '',
                    province_id: '',
                    genero: '',
                    fecha_nacimiento: ''
                },
                // Quick vehicle data
                quickVehicle: {
                    client_person_id: '',
                    vehicle_type_id: '',
                    brand: '',
                    model: '',
                    year: '',
                    color: '',
                    plate: '',
                    vin: '',
                    engine_number: '',
                    chassis_number: '',
                    serial_number: '',
                    current_mileage: '',
                    engine_displacement_cc: '',
                    soat_vencimiento: '',
                    revision_tecnica_vencimiento: ''
                },
                // Additional modal variables
                lookingUpPlate: false,
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
                externalQuotationFilteredClients() {
                    const q = String(this.externalQuotationClientSearch || '').trim().toLowerCase();
                    if (!q) return this.allClients.slice(0, 30);
                    return this.allClients
                        .filter(c => {
                            const doc = String(c.document_number ?? '').toLowerCase();
                            const name = `${String(c.first_name ?? '')} ${String(c.last_name ?? '')}`.toLowerCase().trim();
                            const label = `${doc} - ${name}`.toLowerCase();
                            if (label.includes(q) || doc.includes(q) || name.includes(q)) return true;
                            return false;
                        })
                        .slice(0, 30);
                },
                externalQuotationSelectClient(client) {
                    this.externalQuotationSelectedClientId = String(client.id);
                    this.externalQuotationClientSearch = `${client.document_number || ''} - ${((client.first_name || '') + ' ' + (client.last_name || '')).trim()}`;
                    this.externalQuotationClientDropdownOpen = false;
                },
                externalQuotationFilteredVehicles() {
                    const q = String(this.externalQuotationVehicleSearch || '').trim().toLowerCase();
                    const clientId = String(this.externalQuotationSelectedClientId || '');
                    const filtered = this.allVehicles.filter(v => String(v.client_person_id || '') === clientId);
                    if (!q) return filtered.slice(0, 30);
                    return filtered
                        .filter(v => {
                            const label = (v.label || '').toLowerCase();
                            const client = (v.client_name || '').toLowerCase();
                            return label.includes(q) || client.includes(q);
                        })
                        .slice(0, 30);
                },
                externalQuotationSelectVehicle(vehicle) {
                    this.externalQuotationSelectedVehicleId = String(vehicle.id);
                    this.externalQuotationVehicleSearch = vehicle.label || '';
                    this.externalQuotationVehicleDropdownOpen = false;
                },
                externalQuotationOnVehicleSearchInput() {
                    this.externalQuotationVehicleDropdownOpen = true;
                    if (!String(this.externalQuotationVehicleSearch || '').trim()) {
                        this.externalQuotationSelectedVehicleId = '';
                    }
                },
                externalQuotationToggleCreatingVehicle() {
                    this.creatingVehicle = !this.creatingVehicle;
                    if (this.creatingVehicle) {
                        this.quickVehicle.client_person_id = this.externalQuotationSelectedClientId;
                        this.quickVehicle.vehicle_type_id = this.vehicleTypes.length > 0 ? this.vehicleTypes[0].id : '';
                    }
                },
                // Modal functions
                async saveQuickClient() {
                    this.quickClientError = '';
                    this.creatingClientLoading = true;
                    try {
                        const response = await fetch(@js(route('admin.sales.clients.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                            body: JSON.stringify(this.quickClient),
                        });
                        const payload = await response.json();
                        if (!response.ok) {
                            const message = payload?.message || 'No se pudo registrar el cliente.';
                            const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                            throw new Error(firstError || message);
                        }

                        this.allClients.unshift(payload);
                        this.externalQuotationSelectedClientId = String(payload.id);
                        this.externalQuotationClientSearch = `${payload.document_number || ''} - ${((payload.first_name || '') + ' ' + (payload.last_name || '')).trim()}`;
                        this.creatingClient = false;
                        this.resetQuickClient();
                    } catch (error) {
                        this.quickClientError = error?.message || 'Error registrando cliente.';
                    } finally {
                        this.creatingClientLoading = false;
                    }
                },
                async saveQuickVehicle() {
                    this.quickVehicleError = '';
                    const kmStr = String(this.quickVehicle.current_mileage ?? '').trim();
                    if (kmStr === '') {
                        this.quickVehicleError = 'Debes registrar KM actual.';
                        return;
                    }

                    const kmNum = Number(kmStr);
                    if (!Number.isFinite(kmNum) || kmNum < 0) {
                        this.quickVehicleError = 'KM actual debe ser un numero valido.';
                        return;
                    }

                    const plate = String(this.quickVehicle.plate ?? '').trim();
                    const vin = String(this.quickVehicle.vin ?? '').trim();
                    const engineNumber = String(this.quickVehicle.engine_number ?? '').trim();
                    if (plate === '' && vin === '' && engineNumber === '') {
                        this.quickVehicleError = 'Debe registrar placa o VIN o numero de motor.';
                        return;
                    }

                    this.creatingVehicleLoading = true;
                    try {
                        const response = await fetch(@js(route('admin.sales.vehicles.store')), {
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
                        this.allVehicles.unshift(payload);
                        this.externalQuotationSelectedVehicleId = String(payload.id);
                        this.externalQuotationVehicleSearch = payload.label || '';
                        this.creatingVehicle = false;
                        this.resetQuickVehicle();
                    } catch (error) {
                        this.quickVehicleError = error?.message || 'Error registrando vehiculo.';
                    } finally {
                        this.creatingVehicleLoading = false;
                    }
                },
                resetQuickClient() {
                    this.quickClient = {
                        person_type: 'DNI',
                        document_number: '',
                        first_name: '',
                        last_name: '',
                        phone: '',
                        email: '',
                        address: '-',
                        location_id: '',
                        department_id: '',
                        province_id: '',
                        genero: '',
                        fecha_nacimiento: ''
                    };
                },
                resetQuickVehicle() {
                    this.quickVehicle = {
                        client_person_id: this.externalQuotationSelectedClientId,
                        vehicle_type_id: this.vehicleTypes.length > 0 ? this.vehicleTypes[0].id : '',
                        brand: '',
                        model: '',
                        year: '',
                        color: '',
                        plate: '',
                        vin: '',
                        engine_number: '',
                        chassis_number: '',
                        serial_number: '',
                        current_mileage: '',
                        engine_displacement_cc: '',
                        soat_vencimiento: '',
                        revision_tecnica_vencimiento: ''
                    };
                },
                isQuickClientRuc() {
                    return String(this.quickClient.person_type || '').toUpperCase() === 'RUC';
                },
                get filteredProvinces() {
                    return (this.provinces || []).filter(p => String(p.parent_location_id) === String(this.quickClient.department_id || ''));
                },
                get filteredDistricts() {
                    return (this.districts || []).filter(d => String(d.parent_location_id) === String(this.quickClient.province_id || ''));
                },
                onClientDepartmentChange() {
                    this.quickClient.province_id = '';
                    this.quickClient.location_id = '';
                },
                onClientProvinceChange() {
                    this.quickClient.location_id = '';
                },
                async fetchQuickClientDocumentData() {
                    const doc = String(this.quickClient.document_number || '').trim();
                    if (!doc) return;

                    try {
                        const response = await fetch(`/api/apireniec/${doc}`);
                        if (!response.ok) return;

                        const data = await response.json();
                        if (data && data.success) {
                            const payload = data.data;
                            this.quickClient.first_name = payload.nombres || payload.first_name || '';
                            this.quickClient.last_name = payload.apellido_paterno && payload.apellido_materno ?
                                `${payload.apellido_paterno} ${payload.apellido_materno}` :
                                (payload.last_name || '');
                            this.quickClient.address = payload.direccion || '-';
                        }
                    } catch (error) {
                        console.error('Error fetching document data:', error);
                    }
                },
                normalizePlateForLookup(value) {
                    return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                },
                async lookupQuickVehicleByPlate() {
                    const plate = String(this.quickVehicle.plate || '').trim().toUpperCase();
                    if (!plate) return;

                    this.lookingUpPlate = true;
                    try {
                        const response = await fetch(`/api/vehicle_lookup/${plate}`);
                        if (!response.ok) return;

                        const data = await response.json();
                        if (data && data.success && data.data) {
                            const vehicle = data.data;
                            this.quickVehicle.brand = vehicle.marca || '';
                            this.quickVehicle.model = vehicle.modelo || '';
                            this.quickVehicle.year = vehicle.anio || '';
                            this.quickVehicle.color = vehicle.color || '';
                            this.quickVehicle.vin = vehicle.vin || '';
                            this.quickVehicle.engine_number = vehicle.motor || '';
                        }
                    } catch (error) {
                        console.error('Error looking up plate:', error);
                    } finally {
                        this.lookingUpPlate = false;
                    }
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
