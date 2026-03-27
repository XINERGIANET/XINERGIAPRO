@extends('layouts.app')

@section('content')
<div
    x-data="{
        pendingLines: @js($pendingLines->values()->all()),
        productsCatalog: @js(($products ?? collect())->values()->all()),
        productLines: @js(array_values(old('product_lines', []))),
        documentTypeOptions: @js(collect($documentTypes ?? collect())->map(fn ($doc) => ['id' => (int) $doc->id, 'name' => (string) ($doc->name ?? '')])->values()->all()),
        selectedDocumentTypeId: @js((string) old('document_type_id', $defaultDocumentTypeId ?? optional(($documentTypes ?? collect())->first())->id)),
        selectedCashRegisterId: @js((string) old('cash_register_id', $defaultCashRegisterId ?? optional(($cashRegisters ?? collect())->first())->id)),
        standardCashRegisterId: @js((string) ($standardCashRegisterId ?? $defaultCashRegisterId ?? '')),
        invoiceCashRegisterId: @js((string) ($invoiceCashRegisterId ?? $defaultCashRegisterId ?? '')),
        saleHeaderSeries: @js('001'),
        saleHeaderNumber: @js('00000001'),
        billingStatus: @js((string) old('billing_status', 'PENDING')),
        invoiceSeries: @js((string) old('invoice_series', '001')),
        invoiceNumber: @js((string) old('invoice_number', '')),
        paymentType: @js((string) old('payment_type', 'CONTADO')),
        paymentMethodOptions: @js(($paymentMethodOptions ?? collect())->values()->all()),
        cardOptions: @js(($cardOptions ?? collect())->values()->all()),
        digitalWalletOptions: @js(($digitalWalletOptions ?? collect())->values()->all()),
        paymentGatewayOptionsByMethod: @js($paymentGatewayOptionsByMethod ?? []),
        paymentRows: @js(array_values(old('payment_methods', []))),
        pendingOs: Number(@json($pendingOs)),
        nothingToCollect() {
            return this.chargeTotal() <= 0.000009;
        },
        init() {
            this.paymentType = String(this.paymentType || 'CONTADO').toUpperCase() === 'DEUDA' ? 'DEUDA' : 'CONTADO';
            if (this.chargeTotal() <= 0.000009) {
                this.paymentType = 'CONTADO';
            }
            if (!Array.isArray(this.productLines)) this.productLines = [];
            if (!Array.isArray(this.paymentRows)) this.paymentRows = [];
            this.paymentRows = this.paymentRows.map((row) => this.normalizePaymentRow(row));
            if (this.isDebtPaymentSelected()) {
                this.paymentRows = [];
            } else if (this.nothingToCollect()) {
                this.paymentRows = [];
            } else if (this.paymentRows.length === 0) {
                this.addPaymentRow(true);
            }
            this.syncInvoiceBillingFields();
            this.applyCashRegisterByDocumentType();
            this.syncAmount();
            this.refreshSaleHeaderPreview();
        },
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
        async refreshSaleHeaderPreview() {
            const docId = Number(this.selectedDocumentTypeId || 0);
            const cashId = Number(this.selectedCashRegisterId || this.$refs.cashRegisterSelect?.value || 0);
            if (!docId || !cashId) return;

            const url = new URL(@js(route('admin.sales.preview.header')), window.location.origin);
            url.searchParams.set('document_type_id', String(docId));
            url.searchParams.set('cash_register_id', String(cashId));

            try {
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data?.series != null) {
                    this.saleHeaderSeries = String(data.series);
                }
                if (data?.number != null) {
                    this.saleHeaderNumber = String(data.number);
                }
                if (this.isInvoiceDocumentSelected() && this.billingStatus === 'INVOICED') {
                    this.invoiceSeries = this.saleHeaderSeries || '001';
                    this.invoiceNumber = this.saleHeaderNumber || '';
                }
            } catch (e) {
                /* ignorar */
            }
        },
        lineSubtotal(line) {
            return Number(line.qty || 0) * Number(line.unit_price || 0);
        },
        pendingLinesTotal() {
            return this.pendingLines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
        },
        productLinesTotal() {
            return this.productLines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
        },
        chargeTotal() {
            return this.pendingLinesTotal() + this.productLinesTotal();
        },
        addProductLine() {
            const first = this.productsCatalog[0] || null;
            this.productLines.push({
                product_id: first ? String(first.id) : '',
                qty: 1,
                unit_price: first ? Number(first.price || 0) : 0
            });
            this.syncAmount();
        },
        removeProductLine(index) {
            this.productLines.splice(index, 1);
            this.syncAmount();
        },
        onProductChange(index) {
            const line = this.productLines[index];
            const product = this.productsCatalog.find(p => String(p.id) === String(line.product_id));
            if (product) line.unit_price = Number(product.price || 0);
            this.syncAmount();
        },
        inferMethodKind(methodId) {
            const method = this.paymentMethodOptions.find((item) => String(item.id) === String(methodId));
            return method?.kind || 'other';
        },
        normalizePaymentRow(row) {
            const methodId = String(row.payment_method_id ?? '');
            return {
                payment_method_id: methodId,
                amount: row.amount === 0 ? '0.00' : String(row.amount ?? ''),
                reference: String(row.reference ?? ''),
                card_id: row.card_id ? String(row.card_id) : '',
                digital_wallet_id: row.digital_wallet_id ? String(row.digital_wallet_id) : '',
                payment_gateway_id: row.payment_gateway_id ? String(row.payment_gateway_id) : '',
                kind: this.inferMethodKind(methodId),
            };
        },
        defaultPaymentRow(useRemaining = false) {
            const first = this.paymentMethodOptions[0] || null;
            const methodId = first ? String(first.id) : '';
            const remaining = Math.max(this.remainingAmount(), 0);
            const amount = useRemaining ? remaining : 0;
            return {
                payment_method_id: methodId,
                amount: amount > 0 ? amount.toFixed(2) : '',
                reference: '',
                card_id: '',
                digital_wallet_id: '',
                payment_gateway_id: '',
                kind: this.inferMethodKind(methodId),
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
            } else if (this.nothingToCollect()) {
                this.paymentRows = [];
            } else if (this.paymentRows.length === 0) {
                this.addPaymentRow(true);
                return;
            }
            this.syncAmount();
        },
        removePaymentRow(index) {
            if (this.nothingToCollect()) {
                this.paymentRows.splice(index, 1);
                return;
            }
            if (this.paymentRows.length === 1) {
                this.paymentRows[0].amount = this.chargeTotal().toFixed(2);
                return;
            }
            this.paymentRows.splice(index, 1);
            this.syncAmount();
        },
        onPaymentMethodChange(index) {
            const row = this.paymentRows[index];
            row.kind = this.inferMethodKind(row.payment_method_id);
            row.card_id = '';
            row.digital_wallet_id = '';
            row.payment_gateway_id = '';
        },
        availableGateways(methodId) {
            return this.paymentGatewayOptionsByMethod[String(methodId)] || [];
        },
        paymentTotal() {
            if (this.isDebtPaymentSelected()) {
                return 0;
            }
            return this.paymentRows.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        },
        remainingAmount() {
            if (this.isDebtPaymentSelected()) {
                return this.chargeTotal();
            }
            return this.chargeTotal() - this.paymentTotal();
        },
        syncAmount() {
            if (this.isDebtPaymentSelected()) {
                return;
            }
            if (this.nothingToCollect()) {
                this.paymentRows = [];
                return;
            }
            if (this.paymentRows.length === 0) {
                this.addPaymentRow(true);
                return;
            }
            if (this.paymentRows.length === 1) {
                this.paymentRows[0].amount = this.chargeTotal().toFixed(2);
            }
        }
    }"
>
    @php
        $checkoutOsLabel = 'OS ' . ($order->movement?->number ?? ('#' . $order->id));
    @endphp
    <x-common.page-breadcrumb
        :pageTitle="'Venta y cobro · ' . $checkoutOsLabel"
        :crumbs="[
            ['label' => 'Tablero de Mantenimiento', 'url' => route('workshop.maintenance-board.index')],
            ['label' => $checkoutOsLabel . ' | Venta y cobro'],
        ]"
    />

    <x-common.component-card title="OS Finalizada - Venta y cobro" desc="Factura servicios pendientes, agrega productos y registra el pago para entregar la unidad.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4">
            <x-ui.link-button size="sm" variant="outline" href="{{ route('workshop.maintenance-board.index') }}">
                <i class="ri-arrow-left-line"></i><span>Volver al tablero</span>
            </x-ui.link-button>
        </div>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Orden de servicio</p>
            <p class="text-lg font-bold text-slate-800">OS {{ $order->movement?->number ?? ('#' . $order->id) }} - {{ trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')) }}</p>
            <p class="text-sm text-slate-500">Cliente: {{ trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')) ?: 'Sin cliente' }}</p>
        </div>

        <form method="POST" action="{{ route('workshop.maintenance-board.checkout', $order) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="generate_sale" value="1">

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total OS</p>
                    <p class="mt-1 text-xl font-bold text-slate-800">S/ {{ number_format($totalOs, 2) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Pagado</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">S/ {{ number_format($paidOs, 2) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Pendiente</p>
                    <p class="mt-1 text-xl font-bold text-amber-700">S/ {{ number_format($pendingOs, 2) }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                    Servicios y líneas pendientes a cobrar
                </div>
                <table class="w-full">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Descripcion</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Cant.</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">P.Unit</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-if="pendingLines.length === 0">
                            <tr>
                                <td colspan="4" class="px-3 py-5 text-center text-sm text-slate-500">No hay líneas pendientes.</td>
                            </tr>
                        </template>
                        <template x-for="line in pendingLines" :key="`pending-${line.detail_id}`">
                            <tr>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    <span x-text="line.description"></span>
                                    <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600" x-text="line.line_type"></span>
                                </td>
                                <td class="px-3 py-2 text-center text-sm text-slate-700" x-text="Number(line.qty || 0).toFixed(2)"></td>
                                <td class="px-3 py-2 text-center text-sm text-slate-700" x-text="`S/ ${Number(line.unit_price || 0).toFixed(2)}`"></td>
                                <td class="px-3 py-2 text-right text-sm font-semibold text-slate-800" x-text="`S/ ${lineSubtotal(line).toFixed(2)}`"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-indigo-200 bg-indigo-50/40">
                <div class="flex items-center justify-between border-b border-indigo-200 px-3 py-2">
                    <p class="text-sm font-semibold text-indigo-800">Agregar productos al momento de facturar</p>
                    <button type="button" @click="addProductLine()" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                        <i class="ri-add-line"></i> Agregar producto
                    </button>
                </div>
                <div class="space-y-2 p-3">
                    <template x-if="productLines.length === 0">
                        <p class="text-xs text-slate-500">Sin productos adicionales.</p>
                    </template>
                    <template x-for="(line, index) in productLines" :key="`product-line-${index}`">
                        <div class="grid grid-cols-12 gap-2 rounded-lg border border-indigo-100 bg-white p-2">
                            <div class="col-span-12 md:col-span-6">
                                <input type="hidden" :name="`product_lines[${index}][product_id]`" :value="line.product_id">
                                <select x-model="line.product_id" @change="onProductChange(index)"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm">
                                    <template x-for="product in productsCatalog" :key="`product-option-${product.id}`">
                                        <option :value="String(product.id)" x-text="`${product.code} - ${product.description}`"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-span-6 md:col-span-2">
                                <input type="number" step="0.01" min="0.01"
                                       :name="`product_lines[${index}][qty]`"
                                       x-model="line.qty"
                                       @input="syncAmount()"
                                       class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <input type="number" step="0.01" min="0"
                                       :name="`product_lines[${index}][unit_price]`"
                                       x-model="line.unit_price"
                                       @input="syncAmount()"
                                       class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                            </div>
                            <div class="col-span-12 md:col-span-1">
                                <button type="button" @click="removeProductLine(index)" class="h-10 w-full rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Documento de venta</label>
                    <select x-model="selectedDocumentTypeId" @change="applyCashRegisterByDocumentType(); syncInvoiceBillingFields(); refreshSaleHeaderPreview()" name="document_type_id" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(($documentTypes ?? collect()) as $doc)
                            <option value="{{ $doc->id }}">
                                {{ $doc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Serie</label>
                    <input type="text" x-model="saleHeaderSeries" readonly tabindex="-1" class="h-11 w-full rounded-lg border border-gray-300 bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Correlativo</label>
                    <input type="text" x-model="saleHeaderNumber" readonly tabindex="-1" class="h-11 w-full rounded-lg border border-gray-300 bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Condición</label>
                    <select x-model="paymentType" @change="onPaymentTypeChange()" name="payment_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="CONTADO">Contado</option>
                        <option value="DEUDA" x-bind:disabled="nothingToCollect()">Deuda</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Caja</label>
                    <select x-ref="cashRegisterSelect" x-model="selectedCashRegisterId" @change="refreshSaleHeaderPreview()" name="cash_register_id" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(($cashRegisters ?? collect()) as $cash)
                            <option value="{{ $cash->id }}" @selected(old('cash_register_id', $defaultCashRegisterId ?? optional(($cashRegisters ?? collect())->first())->id) == $cash->id)>
                                Caja {{ $cash->number }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-show="isDebtPaymentSelected()" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                Esta venta se registrará como deuda y se enviará a cuentas por cobrar.
            </div>

            <div x-show="isInvoiceDocumentSelected()" x-cloak class="grid grid-cols-1 gap-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Estado de factura</label>
                    <select x-model="billingStatus" @change="syncInvoiceBillingFields(); refreshSaleHeaderPreview()" name="billing_status" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                        <option value="INVOICED">Facturado</option>
                        <option value="PENDING">Por facturar</option>
                    </select>
                </div>
                <div x-show="billingStatus === 'INVOICED'" x-cloak>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Serie</label>
                    <input
                        type="text"
                        name="invoice_series"
                        x-model="invoiceSeries"
                        x-bind:required="isInvoiceDocumentSelected() && billingStatus === 'INVOICED'"
                        x-bind:disabled="!isInvoiceDocumentSelected() || billingStatus !== 'INVOICED'"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm"
                        placeholder="001"
                    >
                </div>
                <div x-show="billingStatus === 'INVOICED'" x-cloak>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Correlativo</label>
                    <input
                        type="text"
                        name="invoice_number"
                        x-model="invoiceNumber"
                        x-bind:required="isInvoiceDocumentSelected() && billingStatus === 'INVOICED'"
                        x-bind:disabled="!isInvoiceDocumentSelected() || billingStatus !== 'INVOICED'"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm"
                        placeholder="00000001"
                    >
                </div>
            </div>

            <div x-show="!isDebtPaymentSelected() && nothingToCollect()" x-cloak class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p class="font-semibold">Total a cobrar: S/ 0.00</p>
                <p class="mt-1 text-xs text-sky-800">No se registran medios de pago. Elige el documento de venta y confirma para facturar (si aplica) y marcar la OS como entregada.</p>
            </div>

            <div x-show="!isDebtPaymentSelected() && !nothingToCollect()" x-cloak data-gsa-skip="true" class="overflow-visible rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Desglose de cobro</p>
                        <p class="text-xs text-slate-500">Puedes combinar uno o varios métodos de pago en la misma entrega.</p>
                    </div>
                    <button
                        type="button"
                        @click="addPaymentRow(true)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-700 transition hover:border-slate-400 hover:bg-slate-100"
                    >
                        <i class="ri-add-line text-base normal-case"></i>
                        Agregar método
                    </button>
                </div>

                <div class="space-y-3 p-3">
                    <template x-for="(row, index) in paymentRows" :key="`payment-row-${index}`">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                            <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white" x-text="index + 1"></div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Método de pago</p>
                                        <p class="text-xs text-slate-500" x-text="row.kind === 'card' ? 'Requiere tarjeta y puede usar pasarela.' : (row.kind === 'wallet' ? 'Requiere seleccionar la billetera digital.' : 'No requiere detalles adicionales.')"></p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removePaymentRow(index)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-500 transition hover:bg-rose-50"
                                    :disabled="paymentRows.length === 1"
                                    :class="{ 'cursor-not-allowed opacity-50': paymentRows.length === 1 }"
                                    title="Quitar método"
                                >
                                    <i class="ri-delete-bin-line text-lg"></i>
                                </button>
                            </div>

                            <div
                                class="grid items-end gap-3"
                                :style="row.kind === 'card'
                                    ? 'grid-template-columns:minmax(230px,2.5fr) minmax(140px,1.2fr) minmax(180px,1.5fr) minmax(180px,1.5fr) minmax(240px,2fr);'
                                    : 'grid-template-columns:minmax(260px,2.8fr) minmax(150px,1.3fr) minmax(220px,1.8fr) minmax(260px,2.1fr);'"
                            >
                                <div class="min-w-0">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Método</label>
                                    <select
                                        x-model="row.payment_method_id"
                                        @change="onPaymentMethodChange(index)"
                                        :name="`payment_methods[${index}][payment_method_id]`"
                                        required
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-slate-500"
                                    >
                                        <template x-for="method in paymentMethodOptions" :key="`method-${method.id}`">
                                            <option :value="String(method.id)" x-text="method.description"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Monto</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        x-model="row.amount"
                                        :name="`payment_methods[${index}][amount]`"
                                        required
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-slate-500"
                                    >
                                </div>

                                <div class="min-w-0" x-show="row.kind === 'card'">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Tarjeta</label>
                                    <select
                                        x-model="row.card_id"
                                        :name="`payment_methods[${index}][card_id]`"
                                        x-bind:required="row.kind === 'card'"
                                        x-bind:disabled="row.kind !== 'card'"
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-slate-500"
                                    >
                                        <option value="">Seleccionar tarjeta</option>
                                        <template x-for="card in cardOptions" :key="`card-${card.id}`">
                                            <option :value="String(card.id)" x-text="card.type ? `${card.description} (${card.type})` : card.description"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="min-w-0" x-show="row.kind === 'wallet'">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Billetera digital</label>
                                    <select
                                        x-model="row.digital_wallet_id"
                                        :name="`payment_methods[${index}][digital_wallet_id]`"
                                        x-bind:required="row.kind === 'wallet'"
                                        x-bind:disabled="row.kind !== 'wallet'"
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-slate-500"
                                    >
                                        <option value="">Seleccionar billetera</option>
                                        <template x-for="wallet in digitalWalletOptions" :key="`wallet-${wallet.id}`">
                                            <option :value="String(wallet.id)" x-text="wallet.description"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="min-w-0" x-show="row.kind !== 'card' && row.kind !== 'wallet'">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Detalle</label>
                                    <div class="flex h-10 items-center rounded-xl border border-dashed border-slate-300 bg-white px-3 text-sm text-slate-400">
                                        Sin selección adicional
                                    </div>
                                </div>

                                <div class="min-w-0" x-show="row.kind === 'card'">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Pasarela</label>
                                    <select
                                        x-model="row.payment_gateway_id"
                                        :name="`payment_methods[${index}][payment_gateway_id]`"
                                        x-bind:disabled="row.kind !== 'card'"
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-slate-500"
                                    >
                                        <option value="">Sin pasarela</option>
                                        <template x-for="gateway in availableGateways(row.payment_method_id)" :key="`gateway-${row.payment_method_id}-${gateway.id}`">
                                            <option :value="String(gateway.id)" x-text="gateway.description"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Referencia</label>
                                    <input
                                        type="text"
                                        x-model="row.reference"
                                        :name="`payment_methods[${index}][reference]`"
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-slate-500"
                                        placeholder="Operación, voucher, celular o nota"
                                    >
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid gap-3 border-t border-slate-200 bg-slate-50 px-3 py-3 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Total cobro</p>
                        <p class="mt-1 text-lg font-bold text-slate-800" x-text="`S/ ${paymentTotal().toFixed(2)}`"></p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700">Objetivo</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700" x-text="`S/ ${chargeTotal().toFixed(2)}`"></p>
                    </div>
                    <div
                        class="rounded-xl border px-4 py-2.5"
                        :class="Math.abs(remainingAmount()) < 0.009 ? 'border-sky-200 bg-sky-50' : (remainingAmount() > 0 ? 'border-amber-200 bg-amber-50' : 'border-rose-200 bg-rose-50')"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em]" :class="Math.abs(remainingAmount()) < 0.009 ? 'text-sky-700' : (remainingAmount() > 0 ? 'text-amber-700' : 'text-rose-700')">Diferencia</p>
                        <p class="mt-1 text-lg font-bold" :class="Math.abs(remainingAmount()) < 0.009 ? 'text-sky-700' : (remainingAmount() > 0 ? 'text-amber-700' : 'text-rose-700')" x-text="`S/ ${remainingAmount().toFixed(2)}`"></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Comentario venta (opcional)</label>
                    <input type="text" name="sale_comment" value="{{ old('sale_comment', 'Venta generada desde tablero de mantenimiento') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Comentario cobro (opcional)</label>
                    <input type="text" name="payment_comment" value="{{ old('payment_comment', 'Cobro registrado desde tablero') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-right">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700" x-text="isDebtPaymentSelected() ? 'Total a registrar como deuda' : 'Total a cobrar ahora'"></p>
                <p class="mt-1 text-2xl font-extrabold text-emerald-700" x-text="`S/ ${chargeTotal().toFixed(2)}`"></p>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#16a34a,#059669);color:#fff">
                    <i class="ri-money-dollar-circle-line"></i><span>Confirmar venta, cobro y entrega</span>
                </x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.maintenance-board.index') }}">
                    <i class="ri-close-line"></i><span>Cancelar</span>
                </x-ui.link-button>
            </div>
        </form>
    </x-common.component-card>
</div>
@endsection

