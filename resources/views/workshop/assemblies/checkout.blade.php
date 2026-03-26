@extends('layouts.app')

@section('content')
<div x-data="{
    ...(typeof formAutocompleteHelpers === 'function' ? formAutocompleteHelpers() : {}),
    openQuickClientModal: false,
    isSavingClient: false,
    isSearchingDocument: false,
    clientError: '',
    newClient: { 
        person_type: 'DNI', document_number: '', first_name: '', last_name: '', 
        phone: '', email: '', address: '', genero: '', fecha_nacimiento: '', 
        location_id: '' 
    },
    rucLookupMeta: null,
    
    searchDocument() {
        if (!this.newClient.document_number) return;
        this.isSearchingDocument = true;
        
        let url = '';
        if (this.newClient.person_type === 'DNI') {
            url = `{{ route('api.reniec') }}?dni=${this.newClient.document_number}`;
        } else if (this.newClient.person_type === 'RUC') {
            url = `{{ route('api.ruc') }}?ruc=${this.newClient.document_number}`;
        } else {
            this.isSearchingDocument = false;
            return;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                this.isSearchingDocument = false;
                if (data.success === false || data.status === false) {
                    this.clientError = data.message || 'Error al buscar documento.';
                    return;
                }
                this.clientError = '';
                if (this.newClient.person_type === 'DNI') {
                    this.newClient.first_name = data.nombres || data.first_name || '';
                    if (data.apellido_paterno || data.apellido_materno) {
                        this.newClient.last_name = (data.apellido_paterno ? data.apellido_paterno + ' ' : '') + (data.apellido_materno || '');
                    } else {
                        this.newClient.last_name = data.last_name || '';
                    }
                    if (data.fecha_nacimiento && !this.newClient.fecha_nacimiento) this.newClient.fecha_nacimiento = data.fecha_nacimiento;
                    if (data.genero) this.newClient.genero = data.genero;
                    this.rucLookupMeta = null;
                } else if (this.newClient.person_type === 'RUC') {
                    this.newClient.first_name = data.legal_name || data.razon_social || '';
                    this.newClient.last_name = '';
                    this.newClient.address = data.address || data.direccion || '';
                    if (data.raw && data.raw.fecha_inscripcion) {
                        const match = String(data.raw.fecha_inscripcion).match(/^(\d{4}-\d{2}-\d{2})/);
                        if (match) this.newClient.fecha_nacimiento = match[1];
                    }
                    this.rucLookupMeta = {
                        trade_name: data.trade_name || '',
                        condition: data.condition || '',
                        taxpayer_status: data.taxpayer_status || '',
                    };
                }
            })
            .catch(() => {
                this.isSearchingDocument = false;
                this.clientError = 'Error de conexión con RENIEC/SUNAT.';
            });
    },

    saveQuickClient() {
        if (!this.newClient.document_number || (!this.newClient.first_name && this.newClient.person_type !== 'RUC')) {
            this.clientError = 'Complete los campos requeridos (Documento y Nombres).';
            return;
        }
        this.clientError = '';
        this.isSavingClient = true;
        
        fetch('{{ route('workshop.assemblies.massive_sale.clients.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(this.newClient)
        })
        .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, data })))
        .then(res => {
            this.isSavingClient = false;
            if (!res.ok) {
                this.clientError = res.data.message || 'Error al guardar el cliente.';
                return;
            }
            // Agrega cliente a las opciones y lo selecciona
            this.clientsOptions.push({ id: res.data.client.id, name: res.data.client.name });
            this.selectedClientId = String(res.data.client.id);
            
            this.openQuickClientModal = false;
            this.newClient = { person_type: 'DNI', document_number: '', first_name: '', last_name: '', phone: '', email: '', address: '', genero: '', fecha_nacimiento: '', location_id: '' };
        })
        .catch(err => {
            this.isSavingClient = false;
            this.clientError = 'Error de conexión. Intente de nuevo.';
        });
    },

    clientsOptions: @js($clients->map(fn($c) => ['id' => $c->id, 'name' => trim($c->first_name . ' ' . $c->last_name)])->values()->all()),
    selectedClientId: '',

    allAssembliesData: @js($assemblies->map(fn($a) => ['id' => $a->id, 'total_cost' => $a->total_cost])->values()->all()),
    // Payment Logic
    documentTypeOptions: @js(collect($documentTypes ?? collect())->map(fn ($doc) => ['id' => (int) $doc->id, 'name' => (string) ($doc->name ?? '')])->values()->all()),
    selectedDocumentTypeId: @js((string) old('document_type_id', optional(($documentTypes ?? collect())->first())->id)),
    selectedCashRegisterId: @js((string) old('cash_register_id', $defaultCashRegisterId ?? optional(($cashRegisters ?? collect())->first())->id)),
    standardCashRegisterId: @js((string) ($standardCashRegisterId ?? $defaultCashRegisterId ?? '')),
    invoiceCashRegisterId: @js((string) ($invoiceCashRegisterId ?? $defaultCashRegisterId ?? '')),
    billingStatus: @js((string) old('billing_status', 'PENDING')),
    invoiceSeries: @js((string) old('invoice_series', '001')),
    invoiceNumber: @js((string) old('invoice_number', '')),
    paymentType: @js((string) old('payment_type', 'CONTADO')),
    paymentMethodOptions: @js(($paymentMethodOptions ?? collect())->values()->all()),
    creditDays: @js((int) old('credit_days', 0)),
    debtDueDate: @js((string) old('debt_due_date', '')),
    cardOptions: @js(($cardOptions ?? collect())->values()->all()),
    digitalWalletOptions: @js(($digitalWalletOptions ?? collect())->values()->all()),
    paymentGatewayOptionsByMethod: @js($paymentGatewayOptionsByMethod ?? []),
    paymentRows: @js(array_values(old('payment_methods', []))),
    
    // Lifecycle
    init() {
        this.paymentType = String(this.paymentType || 'CONTADO').toUpperCase() === 'DEUDA' ? 'DEUDA' : 'CONTADO';
        this.creditDays = Math.max(0, parseInt(this.creditDays, 10) || 0);
        this.debtDueDate = String(this.debtDueDate || '').trim();
        if (!Array.isArray(this.paymentRows)) this.paymentRows = [];
        this.paymentRows = this.paymentRows.map((row) => this.normalizePaymentRow(row));
        if (this.isDebtPaymentSelected()) this.paymentRows = [];
        if (this.paymentRows.length === 0 && !this.isDebtPaymentSelected()) this.addPaymentRow(true);
        this.syncInvoiceBillingFields();
        this.applyCashRegisterByDocumentType();
        this.syncAmount();
        
        this.$watch('newClient.person_type', (value) => {
            if (value === 'RUC') {
                this.newClient.last_name = '';
                this.newClient.genero = '';
            }
        });
    },
    
    // --- PAYMENT METHODS LOGIC ---
    currentDocumentType() {
        return this.documentTypeOptions.find((item) => String(item.id) === String(this.selectedDocumentTypeId || '')) || null;
    },
    isInvoiceDocumentSelected() {
        const name = String(this.currentDocumentType()?.name || '').toLowerCase();
        return name.includes('factura');
    },
    isDebtPaymentSelected() {
        return String(this.paymentType || 'CONTADO').toUpperCase() === 'DEUDA';
    },
    preferredCashRegisterId() {
        return String(this.isInvoiceDocumentSelected() ? (this.invoiceCashRegisterId || '') : (this.standardCashRegisterId || '')).trim();
    },
    applyCashRegisterByDocumentType() {
        const preferredId = this.preferredCashRegisterId();
        if (!preferredId) return;
        this.selectedCashRegisterId = preferredId;
    },
    parseBaseDebtDate() {
        return new Date();
    },
    formatDebtIsoDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },
    syncDebtDueFromCreditDays() {
        if (!this.isDebtPaymentSelected()) return;
        const base = this.parseBaseDebtDate();
        const next = new Date(base.getTime());
        next.setDate(next.getDate() + Math.max(0, parseInt(this.creditDays, 10) || 0));
        this.debtDueDate = this.formatDebtIsoDate(next);
    },
    onCreditDaysChange() {
        this.creditDays = Math.max(0, parseInt(this.creditDays, 10) || 0);
        this.syncDebtDueFromCreditDays();
    },
    onDebtDueDateChange() {
        const iso = String(this.debtDueDate || '').trim();
        if (!iso) {
            this.syncDebtDueFromCreditDays();
            return;
        }
        const due = new Date(`${iso}T12:00:00`);
        if (Number.isNaN(due.getTime())) return;
        const base = this.parseBaseDebtDate();
        const baseDay = new Date(base.getFullYear(), base.getMonth(), base.getDate());
        const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate());
        const diffMs = dueDay.getTime() - baseDay.getTime();
        this.creditDays = Math.max(0, Math.round(diffMs / 86400000));
    },
    syncInvoiceBillingFields() {
        if (!this.isInvoiceDocumentSelected()) {
            this.billingStatus = 'NOT_APPLICABLE';
            this.invoiceSeries = this.invoiceSeries || '001';
            this.invoiceNumber = '';
            return;
        }
        if (!['INVOICED', 'PENDING'].includes(String(this.billingStatus || ''))) {
            this.billingStatus = 'PENDING';
        }
        if (!this.invoiceSeries) {
            this.invoiceSeries = '001';
        }
        if (this.billingStatus === 'PENDING') {
            this.invoiceNumber = '';
        }
    },
    cardTypeLabel(type) {
        const c = String(type || '').trim().toUpperCase();
        if (c === 'C') return 'Crédito';
        if (c === 'D') return 'Débito';
        return '';
    },
    get paymentMethodVariants() {
        return this.paymentMethodOptions.flatMap(m => {
            const id = Number(m.id), d = String(m.description || ''), k = m.kind || 'other';
            if (k === 'wallet' && this.digitalWalletOptions.length) {
                return this.digitalWalletOptions.map(w => ({
                    key: `wallet:${id}:${Number(w.id)}`, 
                    payment_method_id: id, 
                    digital_wallet_id: Number(w.id), 
                    card_id: null, 
                    label: `${d} - ${w.description}`, 
                    kind: k
                }));
            }
            if (k === 'card' && this.cardOptions.length) {
                return this.cardOptions.map(card => {
                    const typePart = this.cardTypeLabel(card.type);
                    const base = `${d} - ${card.description}`;
                    const label = typePart ? `${base} (${typePart})` : base;
                    return {
                        key: `card:${id}:${Number(card.id)}`, 
                        payment_method_id: id, 
                        digital_wallet_id: null, 
                        card_id: Number(card.id), 
                        label, 
                        kind: k
                    };
                });
            }
            return [{
                key: `plain:${id}`, 
                payment_method_id: id, 
                digital_wallet_id: null, 
                card_id: null, 
                label: d, 
                kind: k
            }];
        });
    },
    getPaymentVariantByKey(k) {
        return this.paymentMethodVariants.find(v => v.key === k) || null;
    },
    getDefaultPaymentVariant() {
        return this.paymentMethodVariants[0] || null;
    },
    normalizePaymentRow(row) {
        const methodId = String(row.payment_method_id ?? '');
        const ck = row.card_id ? `card:${Number(methodId)}:${Number(row.card_id)}` : null;
        const wk = row.digital_wallet_id ? `wallet:${Number(methodId)}:${Number(row.digital_wallet_id)}` : null;
        const pk = methodId ? `plain:${Number(methodId)}` : null;
        const v = this.getPaymentVariantByKey(ck) || this.getPaymentVariantByKey(wk) || this.getPaymentVariantByKey(pk) || this.getDefaultPaymentVariant();
        
        return {
            method_variant_key: v?.key || '',
            payment_method_id: String(v?.payment_method_id || methodId),
            card_id: v?.card_id ? String(v.card_id) : '',
            digital_wallet_id: v?.digital_wallet_id ? String(v.digital_wallet_id) : '',
            payment_gateway_id: row.payment_gateway_id ? String(row.payment_gateway_id) : '',
            amount: row.amount === 0 ? '0.00' : String(row.amount ?? ''),
            reference: String(row.reference ?? ''),
            kind: v?.kind || 'other',
        };
    },
    defaultPaymentRow(useRemaining = false) {
        const v = this.getDefaultPaymentVariant();
        const remaining = Math.max(this.remainingAmount(), 0);
        const amount = useRemaining ? remaining : 0;
        return {
            method_variant_key: v?.key || '',
            payment_method_id: v ? String(v.payment_method_id) : '',
            amount: amount > 0 ? amount.toFixed(2) : '',
            reference: '',
            card_id: v?.card_id ? String(v.card_id) : '',
            digital_wallet_id: v?.digital_wallet_id ? String(v.digital_wallet_id) : '',
            payment_gateway_id: '',
            kind: v?.kind || 'other',
        };
    },
    addPaymentRow(useRemaining = false) {
        this.paymentRows.push(this.defaultPaymentRow(useRemaining));
        this.syncAmount();
    },
    onPaymentTypeChange() {
        this.paymentType = this.isDebtPaymentSelected() ? 'DEUDA' : 'CONTADO';
        if (this.isDebtPaymentSelected()) {
            this.paymentRows = [];
            this.syncDebtDueFromCreditDays();
        } else if (this.paymentRows.length === 0) {
            this.addPaymentRow(true);
            return;
        }
        this.syncAmount();
    },
    removePaymentRow(index) {
        if (this.paymentRows.length === 1) {
            this.paymentRows[0].amount = this.chargeTotal().toFixed(2);
            return;
        }
        this.paymentRows.splice(index, 1);
        this.syncAmount();
    },
    applyPaymentVariant(index) {
        const v = this.getPaymentVariantByKey(this.paymentRows[index].method_variant_key);
        if (!v) return;
        Object.assign(this.paymentRows[index], {
            payment_method_id: String(v.payment_method_id),
            card_id: v.card_id ? String(v.card_id) : '',
            digital_wallet_id: v.digital_wallet_id ? String(v.digital_wallet_id) : '',
            kind: v.kind
        });
        if (v.kind !== 'card') this.paymentRows[index].payment_gateway_id = '';
    },
    availableGateways(methodId) {
        return this.paymentGatewayOptionsByMethod[String(methodId)] || [];
    },
    chargeTotal() {
        return this.massTotal;
    },
    get massTotal() {
         return this.allAssembliesData.reduce((sum, a) => {
             return sum + parseFloat(a.total_cost || 0);
         }, 0);
    },
    getMassTotalCalc() {
         return this.massTotal;
    },
    paymentTotal() {
        if (this.isDebtPaymentSelected()) return 0;
        return this.paymentRows.reduce((sum, row) => sum + Number(row.amount || 0), 0);
    },
    remainingAmount() {
        if (this.isDebtPaymentSelected()) return this.massTotal;
        return this.massTotal - this.paymentTotal();
    },
    syncAmount() {
        if (this.isDebtPaymentSelected()) return;
        if (this.paymentRows.length === 1) {
            this.paymentRows[0].amount = this.massTotal.toFixed(2);
        }
    }
}">
    <x-common.page-breadcrumb pageTitle="Venta y Cobro de Armados" />

    <x-common.component-card title="Venta Masiva de Armados" desc="Factura los armados seleccionados y registra sus ingresos a caja.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4">
            <x-ui.link-button size="sm" variant="outline" href="{{ route('workshop.assemblies.index') }}">
                <i class="ri-arrow-left-line"></i><span>Volver a Armados</span>
            </x-ui.link-button>
        </div>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 overflow-x-auto">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 mb-3">Armados Seleccionados</p>
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 text-slate-500">
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">Empresa/Marca</th>
                        <th class="px-3 py-2 font-medium">Tipo</th>
                        <th class="px-3 py-2 font-medium">Modelo</th>
                        <th class="px-3 py-2 font-medium text-right">Costo Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($assemblies as $assembly)
                    <tr>
                        <td class="px-3 py-2">ARM-{{ $assembly->id }}</td>
                        <td class="px-3 py-2">{{ $assembly->brand_company }}</td>
                        <td class="px-3 py-2">{{ $assembly->vehicle_type }}</td>
                        <td class="px-3 py-2">{{ $assembly->model ?? '-' }}</td>
                        <td class="px-3 py-2 text-right font-bold">S/ {{ number_format($assembly->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="px-3 py-3 text-right text-slate-500">Total a Vender:</th>
                        <th class="px-3 py-3 text-right text-lg font-black text-slate-800" x-text="`S/ ${massTotal.toFixed(2)}`"></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <form method="POST" action="{{ route('workshop.assemblies.massive_sale') }}" class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @csrf
            {{-- Campos ocultos para IDs --}}
            @foreach($assemblies as $assembly)
                <input type="hidden" name="assembly_ids[]" value="{{ $assembly->id }}">
            @endforeach

            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Datos del Cliente *</label>
                    <div class="flex items-start gap-2">
                        <div class="flex-1">
                            <x-form.select-autocomplete-inline
                                fieldKey="ms_client"
                                name="client_person_id"
                                valueVar="selectedClientId"
                                optionsListExpr="clientsOptions"
                                optionLabel="name"
                                optionValue="id"
                                emptyText="Seleccione un cliente..."
                                placeholderSearch="Buscar cliente por nombre..."
                                :required="true"
                                inputClass="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500"
                            />
                        </div>
                        <button type="button" @click="openQuickClientModal = true" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition hover:scale-105" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);" title="Nuevo Cliente">
                            <i class="ri-user-add-line text-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Documento de Venta</label>
                            <select x-model="selectedDocumentTypeId" @change="applyCashRegisterByDocumentType(); syncInvoiceBillingFields()" name="document_type_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                                <template x-for="doc in documentTypeOptions" :key="doc.id">
                                    <option :value="doc.id" x-text="doc.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Condición</label>
                            <select x-model="paymentType" @change="onPaymentTypeChange()" name="payment_type" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                <option value="CONTADO">Contado</option>
                                <option value="DEUDA">Deuda / Crédito</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div x-show="isInvoiceDocumentSelected()" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Estado Fra.</label>
                            <select x-model="billingStatus" @change="syncInvoiceBillingFields()" name="billing_status" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="INVOICED">Facturado</option>
                                <option value="PENDING">Por facturar</option>
                            </select>
                        </div>
                        <div x-show="billingStatus === 'INVOICED'" x-cloak>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Serie</label>
                            <input type="text" name="invoice_series" x-model="invoiceSeries" x-bind:required="isInvoiceDocumentSelected() && billingStatus === 'INVOICED'" x-bind:disabled="!isInvoiceDocumentSelected() || billingStatus !== 'INVOICED'" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="001">
                        </div>
                        <div x-show="billingStatus === 'INVOICED'" x-cloak>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Correlativo</label>
                            <input type="text" name="invoice_number" x-model="invoiceNumber" x-bind:required="isInvoiceDocumentSelected() && billingStatus === 'INVOICED'" x-bind:disabled="!isInvoiceDocumentSelected() || billingStatus !== 'INVOICED'" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="00000001">
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5" x-show="!isDebtPaymentSelected()" x-cloak>
                    <div class="flex items-center justify-between mb-3 border-b border-gray-200 pb-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400">Registro de Pagos</label>
                        <button type="button" @click="addPaymentRow(true)" class="rounded-lg bg-gray-200 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-300">
                            <i class="ri-add-line"></i> Dividir pago
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Caja Destino</label>
                        <select x-model="selectedCashRegisterId" name="cash_register_id" class="w-full h-11 rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            @foreach($cashRegisters as $cr)
                                <option value="{{ $cr->id }}">Caja #{{ $cr->number }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, index) in paymentRows" :key="`payment-row-${index}`">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-500">Método <span x-text="index + 1"></span></span>
                                    <button type="button" @click="removePaymentRow(index)" class="text-red-500 hover:text-red-700" :disabled="paymentRows.length === 1" :class="{ 'opacity-50 cursor-not-allowed': paymentRows.length === 1 }">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                                <input type="hidden" :name="`payment_methods[${index}][payment_method_id]`" :value="row.payment_method_id || ''">
                                <input type="hidden" :name="`payment_methods[${index}][card_id]`" :value="row.card_id || ''">
                                <input type="hidden" :name="`payment_methods[${index}][digital_wallet_id]`" :value="row.digital_wallet_id || ''">
                                
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                    <div :class="row.kind === 'card' ? 'md:col-span-4' : 'md:col-span-5'">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Método</label>
                                        <select x-model="row.method_variant_key" @change="applyPaymentVariant(index)" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <template x-for="variant in paymentMethodVariants" :key="variant.key"><option :value="variant.key" x-text="variant.label"></option></template>
                                        </select>
                                    </div>
                                    
                                    <div class="md:col-span-3" x-show="row.kind === 'card'">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Pasarela</label>
                                        <select :name="`payment_methods[${index}][payment_gateway_id]`" x-model="row.payment_gateway_id" x-bind:disabled="row.kind !== 'card'" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <option value="">Seleccionar</option>
                                            <template x-for="gateway in availableGateways(row.payment_method_id)">
                                                <option :value="String(gateway.id)" x-text="gateway.description"></option>
                                            </template>
                                        </select>
                                    </div>
                                    
                                    <div :class="row.kind === 'card' ? 'md:col-span-2' : 'md:col-span-3'">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Monto</label>
                                        <input type="number" step="0.01" min="0.01" x-model.number="row.amount" :name="`payment_methods[${index}][amount]`" @input="syncAmount()" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#059669;">
                                    </div>
                                    
                                    <div :class="row.kind === 'card' ? 'md:col-span-3' : 'md:col-span-4'">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Ref.</label>
                                        <input type="text" x-model="row.reference" :name="`payment_methods[${index}][reference]`" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700" placeholder="# Ref/Voucher">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-800 p-3 text-white">
                        <div>
                            <p class="text-[10px] font-bold uppercase text-slate-400">Total a Pagar</p>
                            <p class="text-sm font-black" x-text="`S/ ${massTotal.toFixed(2)}`"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold uppercase text-slate-400">Total Distribuido</p>
                            <p class="text-sm font-black" :class="remainingAmount() === 0 ? 'text-emerald-400' : 'text-amber-400'" x-text="`S/ ${paymentTotal().toFixed(2)}`"></p>
                        </div>
                    </div>
                </div>

                <div x-show="isDebtPaymentSelected()" x-cloak class="rounded-2xl border border-rose-200 bg-rose-50/80 p-5 shadow-inner">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-rose-200 text-rose-600">
                        <i class="ri-file-list-3-fill text-2xl"></i>
                    </div>
                    <h4 class="text-center text-sm font-bold text-rose-800">Venta al Credito</h4>
                    <p class="mt-1 text-center text-xs text-rose-600">Esta venta se enviara a Cuentas por Cobrar. No se registrara ingreso de dinero a tu caja.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-rose-800">Dias de credito</label>
                            <input type="number" min="0" step="1" x-model="creditDays" @input="onCreditDaysChange()" name="credit_days" class="h-11 w-full rounded-xl border border-rose-200 bg-white px-3 text-sm font-bold text-slate-700 focus:border-rose-400 focus:ring-2 focus:ring-rose-200">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-rose-800">Fecha vencimiento</label>
                            <input type="date" x-model="debtDueDate" @input="onDebtDueDateChange()" name="debt_due_date" class="h-11 w-full rounded-xl border border-rose-200 bg-white px-3 text-sm font-bold text-slate-700 focus:border-rose-400 focus:ring-2 focus:ring-rose-200">
                        </div>
                    </div>
                    <p class="mt-3 text-center text-lg font-black text-rose-900" x-text="`S/ ${massTotal.toFixed(2)}`"></p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/5">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Observaciones Venta</label>
                    <textarea name="comment" rows="2" class="w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Ej: Venta masiva corporativa..."></textarea>
                </div>
            </div>

            <div class="md:col-span-2 mt-4">
                <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 py-4 text-base font-bold text-white shadow-lg shadow-purple-200 transition-all hover:scale-[1.02] hover:shadow-xl active:scale-95 dark:shadow-none">
                    <i class="ri-shopping-cart-fill text-xl"></i>
                    <span>Confirmar y Procesar Venta</span>
                </button>
                <p class="mt-3 text-center text-xs text-gray-400">
                    <i class="ri-information-line"></i> Esta acción actualizará los registros de armado y creará un nuevo movimiento de venta en el registro mensual.
                </p>
            </div>
        </form>
    </x-common.component-card>

    <div x-show="openQuickClientModal" class="fixed inset-0 z-[100000] overflow-hidden p-3 sm:p-6" style="display: none;">
        <div class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]" @click="openQuickClientModal = false"></div>
        <div class="relative flex min-h-full items-center justify-center">
            <div class="w-full max-w-4xl rounded-[28px] bg-white shadow-2xl" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8">
                    <h3 class="text-lg font-semibold text-gray-800">Registrar cliente</h3>
                    <button type="button" @click="openQuickClientModal = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="p-6 sm:p-8">
                    <div x-show="clientError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="clientError" style="display: none;"></div>

                    <form @submit.prevent="saveQuickClient" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                            <select x-model="newClient.person_type" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CARNET DE EXTRANGERIA">CARNET DE EXTRANGERIA</option>
                                <option value="PASAPORTE">PASAPORTE</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                            <div class="flex items-center gap-2">
                                <input x-model="newClient.document_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Documento" required>
                                <button type="button" @click="searchDocument()" :disabled="isSearchingDocument" class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60 text-lg">
                                    <i class="ri-search-line" x-show="!isSearchingDocument"></i>
                                    <i class="ri-loader-4-line animate-spin" x-show="isSearchingDocument" style="display: none;"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">
                                <span x-text="newClient.person_type === 'RUC' ? 'Razon social' : 'Nombres'"></span>
                            </label>
                            <input x-model="newClient.first_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="newClient.person_type === 'RUC' ? 'Ingrese razon social' : 'Nombres'" required>
                        </div>
                        <div x-show="newClient.person_type !== 'RUC'" x-cloak>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                            <input x-model="newClient.last_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Apellidos" :required="newClient.person_type !== 'RUC'">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" x-text="newClient.person_type === 'RUC' ? 'Fecha de inscripcion' : 'Fecha de nacimiento'"></label>
                            <input x-model="newClient.fecha_nacimiento" type="date" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div x-show="newClient.person_type !== 'RUC'" x-cloak>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                            <select x-model="newClient.genero" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Seleccione genero</option>
                                <option value="MASCULINO">MASCULINO</option>
                                <option value="FEMENINO">FEMENINO</option>
                                <option value="OTRO">OTRO</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                            <input x-model="newClient.phone" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Ingrese el telefono">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <input x-model="newClient.email" type="email" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Ingrese el email">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                            <input x-model="newClient.address" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Direccion (opcional)">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                            <select class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500 text-gray-600" disabled>
                                <option value="">Por defecto de sucursal</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                            <select class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500 text-gray-600" disabled>
                                <option value="">Por defecto de sucursal</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                            <select class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-brand-500 focus:ring-brand-500 text-gray-600" disabled>
                                <option value="">Por defecto de sucursal</option>
                            </select>
                        </div>

                        <template x-if="newClient.person_type === 'RUC' && rucLookupMeta">
                            <div class="col-span-1 md:col-span-4 grid gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Nombre comercial</p>
                                    <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.trade_name || '-'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Condicion</p>
                                    <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.condition || '-'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Estado</p>
                                    <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.taxpayer_status || '-'"></p>
                                </div>
                            </div>
                        </template>

                        <div class="md:col-span-4 mt-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                            <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox" checked disabled class="h-4 w-4 rounded border-gray-300 text-brand-500">
                                <span>Cliente</span>
                            </div>
                        </div>

                        <div class="md:col-span-4 mt-2 flex gap-2">
                            <button type="submit" :disabled="isSavingClient" class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-white" style="background-color:#00A389;color:#fff;" :class="isSavingClient && 'opacity-70 cursor-wait'">
                                <i class="ri-save-line" x-show="!isSavingClient"></i>
                                <i class="ri-loader-4-line animate-spin" x-show="isSavingClient" style="display: none;"></i>
                                <span x-text="isSavingClient ? 'Guardando...' : 'Guardar cliente'"></span>
                            </button>
                            <button type="button" @click="openQuickClientModal = false" :disabled="isSavingClient" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i class="ri-close-line"></i>
                                <span>Cancelar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
