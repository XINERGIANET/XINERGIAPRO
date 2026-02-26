@php
    $purchaseMovement = optional($purchase)->purchaseMovement;
    $existingItems = collect($purchaseMovement?->details ?? [])->map(function ($detail) {
        return [
            'product_id' => (int) ($detail->product_id ?? 0),
            'unit_id' => (int) ($detail->unit_id ?? 0),
            'description' => (string) ($detail->description ?? ''),
            'quantity' => (float) ($detail->quantity ?? 1),
            'amount' => (float) ($detail->amount ?? 0),
            'comment' => (string) ($detail->comment ?? ''),
        ];
    })->values();
@endphp

<div
    x-data="purchaseForm({
        products: @js($products->map(fn($p) => [
            'id' => (int) $p->id,
            'code' => (string) ($p->code ?? ''),
            'name' => (string) ($p->description ?? ''),
            'unit_id' => (int) ($p->unit_sale ?? 0),
            'unit_name' => (string) ($p->unit_name ?? ''),
            'cost' => (float) ($p->price ?? 0),
        ])->values()),
        units: @js($units),
        initialItems: @js(old('items', $existingItems->all())),
        taxRate: @js((float) old('tax_rate_percent', $defaultTaxRate)),
        includesTax: @js((string) old('includes_tax', $purchaseMovement->includes_tax ?? 'N')),
    })"
    class="space-y-5"
>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Fecha</label>
            <input type="datetime-local" name="moved_at" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                   value="{{ old('moved_at', optional($purchase?->moved_at ?? now())->format('Y-m-d\TH:i')) }}" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Proveedor</label>
            <select name="person_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                <option value="">Selecciona proveedor</option>
                @foreach($people as $person)
                    <option value="{{ $person->id }}" @selected((int) old('person_id', $purchase?->person_id ?? 0) === (int) $person->id)>
                        {{ trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) }}{{ $person->document_number ? ' - ' . $person->document_number : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Documento</label>
            <select name="document_type_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                <option value="">Selecciona documento</option>
                @foreach($documentTypes as $documentType)
                    <option value="{{ $documentType->id }}" @selected((int) old('document_type_id', $purchase?->document_type_id ?? 0) === (int) $documentType->id)>
                        {{ $documentType->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Serie</label>
            <input type="text" name="series" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                   value="{{ old('series', $purchaseMovement->series ?? '001') }}" placeholder="001">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Tipo detalle</label>
            <select name="detail_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                @foreach(['DETALLADO','GLOSA'] as $option)
                    <option value="{{ $option }}" @selected(old('detail_type', $purchaseMovement->detail_type ?? 'DETALLADO') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Incluye IGV</label>
            <select name="includes_tax" x-model="includesTax" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                <option value="N">N</option>
                <option value="S">S</option>
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Tipo pago</label>
            <select name="payment_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                @foreach(['CONTADO','CREDITO'] as $option)
                    <option value="{{ $option }}" @selected(old('payment_type', $purchaseMovement->payment_type ?? 'CONTADO') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Afecta caja</label>
            <select name="affects_cash" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                <option value="N" @selected(old('affects_cash', $purchaseMovement->affects_cash ?? 'N') === 'N')>N</option>
                <option value="S" @selected(old('affects_cash', $purchaseMovement->affects_cash ?? 'N') === 'S')>S</option>
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Afecta kardex</label>
            <select name="affects_kardex" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                <option value="S" @selected(old('affects_kardex', $purchaseMovement->affects_kardex ?? 'S') === 'S')>S</option>
                <option value="N" @selected(old('affects_kardex', $purchaseMovement->affects_kardex ?? 'S') === 'N')>N</option>
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Moneda</label>
            <input type="text" name="currency" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                   value="{{ old('currency', $purchaseMovement->currency ?? 'PEN') }}" required>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Tipo de cambio</label>
            <input type="number" step="0.001" min="0.001" name="exchange_rate" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                   value="{{ old('exchange_rate', $purchaseMovement->exchange_rate ?? 1) }}" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">IGV %</label>
            <input type="number" step="0.01" min="0" max="100" name="tax_rate_percent" x-model.number="taxRate" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Comentario</label>
            <input type="text" name="comment" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                   value="{{ old('comment', $purchase?->comment ?? '') }}" placeholder="Comentario compra">
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Detalle de compra</h3>
            <button type="button" @click="addItem()" class="rounded-lg bg-[#244BB3] px-3 py-2 text-xs font-semibold text-white">
                <i class="ri-add-line mr-1"></i>Agregar item
            </button>
        </div>

        <div class="space-y-2">
            <template x-for="(item, idx) in items" :key="idx">
                <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 p-3 lg:grid-cols-12">
                    <div class="lg:col-span-3">
                        <select :name="`items[${idx}][product_id]`" x-model.number="item.product_id" @change="setProductMeta(idx)"
                                class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="">Producto</option>
                            <template x-for="product in products" :key="product.id">
                                <option :value="product.id" x-text="`${product.code || 'SIN'} - ${product.name}`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <input :name="`items[${idx}][description]`" x-model="item.description" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Descripcion">
                    </div>
                    <div class="lg:col-span-2">
                        <select :name="`items[${idx}][unit_id]`" x-model.number="item.unit_id" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="">Unidad</option>
                            <template x-for="unit in units" :key="unit.id">
                                <option :value="unit.id" x-text="unit.description"></option>
                            </template>
                        </select>
                    </div>
                    <div class="lg:col-span-1">
                        <input :name="`items[${idx}][quantity]`" type="number" step="0.0001" min="0.0001" x-model.number="item.quantity"
                               class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cant." required>
                    </div>
                    <div class="lg:col-span-2">
                        <input :name="`items[${idx}][amount]`" type="number" step="0.0001" min="0" x-model.number="item.amount"
                               class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Costo unit." required>
                    </div>
                    <div class="lg:col-span-1">
                        <input :name="`items[${idx}][comment]`" x-model="item.comment" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Obs">
                    </div>
                    <div class="lg:col-span-1 flex items-center justify-end">
                        <button type="button" @click="removeItem(idx)" class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-[#EF4444] text-white">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                    <div class="lg:col-span-12 text-right text-xs text-gray-500">
                        Subtotal: <span class="font-semibold" x-text="money((item.quantity || 0) * (item.amount || 0))"></span>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-2 text-right lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs uppercase text-gray-500">Subtotal</p>
                <p class="text-lg font-bold text-gray-900" x-text="money(summary.subtotal)"></p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs uppercase text-gray-500">IGV</p>
                <p class="text-lg font-bold text-gray-900" x-text="money(summary.tax)"></p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs uppercase text-gray-500">Total</p>
                <p class="text-lg font-bold text-brand-600" x-text="money(summary.total)"></p>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function purchaseForm({ products, units, initialItems, taxRate, includesTax }) {
                return {
                    products,
                    units,
                    items: (initialItems && initialItems.length)
                        ? initialItems.map(i => ({
                            product_id: Number(i.product_id || 0),
                            unit_id: Number(i.unit_id || 0),
                            description: i.description || '',
                            quantity: Number(i.quantity || 1),
                            amount: Number(i.amount || 0),
                            comment: i.comment || '',
                        }))
                        : [{ product_id: 0, unit_id: 0, description: '', quantity: 1, amount: 0, comment: '' }],
                    taxRate: Number(taxRate || 18),
                    includesTax: includesTax || 'N',
                    get summary() {
                        const lineTotal = this.items.reduce((sum, i) => sum + ((Number(i.quantity) || 0) * (Number(i.amount) || 0)), 0);
                        const r = (Number(this.taxRate) || 0) / 100;
                        if (this.includesTax === 'S') {
                            const subtotal = r > 0 ? (lineTotal / (1 + r)) : lineTotal;
                            const tax = lineTotal - subtotal;
                            return { subtotal, tax, total: lineTotal };
                        }
                        const subtotal = lineTotal;
                        const tax = subtotal * r;
                        return { subtotal, tax, total: subtotal + tax };
                    },
                    addItem() {
                        this.items.push({ product_id: 0, unit_id: 0, description: '', quantity: 1, amount: 0, comment: '' });
                    },
                    removeItem(idx) {
                        this.items.splice(idx, 1);
                        if (!this.items.length) this.addItem();
                    },
                    setProductMeta(idx) {
                        const product = this.products.find(p => Number(p.id) === Number(this.items[idx].product_id));
                        if (!product) return;
                        if (!this.items[idx].description) this.items[idx].description = product.name || '';
                        if (!this.items[idx].unit_id && product.unit_id) this.items[idx].unit_id = Number(product.unit_id);
                        if (!this.items[idx].amount && product.cost) this.items[idx].amount = Number(product.cost);
                    },
                    money(v) {
                        return `S/ ${Number(v || 0).toFixed(2)}`;
                    }
                }
            }
        </script>
    @endpush
@endonce
