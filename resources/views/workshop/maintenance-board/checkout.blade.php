@extends('layouts.app')

@section('content')
<div
    x-data="{
        pendingLines: @js($pendingLines->values()->all()),
        productsCatalog: @js(($products ?? collect())->values()->all()),
        productLines: @js(array_values(old('product_lines', []))),
        pendingOs: Number(@json($pendingOs)),
        init() {
            if (!Array.isArray(this.productLines)) this.productLines = [];
            if (this.productLines.length === 0) this.syncAmount();
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
        syncAmount() {
            const amountInput = this.$refs.amountInput;
            if (amountInput) amountInput.value = this.chargeTotal().toFixed(2);
        }
    }"
>
    <x-common.page-breadcrumb pageTitle="Venta y Cobro" />

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
                    Servicios y lineas pendientes a cobrar
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
                                <td colspan="4" class="px-3 py-5 text-center text-sm text-slate-500">No hay lineas pendientes.</td>
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

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Documento de venta</label>
                    <select name="document_type_id" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(($documentTypes ?? collect()) as $doc)
                            <option value="{{ $doc->id }}" @selected(old('document_type_id', optional(($documentTypes ?? collect())->first())->id) == $doc->id)>
                                {{ $doc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Caja</label>
                    <select name="cash_register_id" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(($cashRegisters ?? collect()) as $cash)
                            <option value="{{ $cash->id }}" @selected(old('cash_register_id', optional(($cashRegisters ?? collect())->first())->id) == $cash->id)>
                                Caja {{ $cash->number }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Metodo de pago</label>
                    <select name="payment_methods[0][payment_method_id]" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(($paymentMethods ?? collect()) as $method)
                            <option value="{{ $method->id }}" @selected(old('payment_methods.0.payment_method_id', optional(($paymentMethods ?? collect())->first())->id) == $method->id)>
                                {{ $method->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Monto</label>
                    <input
                        x-ref="amountInput"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="payment_methods[0][amount]"
                        value="{{ old('payment_methods.0.amount', number_format($pendingOs, 2, '.', '')) }}"
                        required
                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Referencia (opcional)</label>
                    <input type="text" name="payment_methods[0][reference]" value="{{ old('payment_methods.0.reference') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Operacion / voucher">
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
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Total a cobrar ahora</p>
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
