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
            'product_query' => '',
            'product_open' => false,
            'product_cursor' => 0,
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
        providers: @js($people->map(fn($person) => [
            'id' => (int) $person->id,
            'label' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
            'document' => (string) ($person->document_number ?? ''),
        ])->values()),
        initialProviderId: @js((int) old('person_id', $purchase?->person_id ?? 0)),
        initialItems: @js(old('items', $existingItems->all())),
        taxRate: @js((float) old('tax_rate_percent', $defaultTaxRate)),
        includesTax: @js((string) old('includes_tax', $purchaseMovement->includes_tax ?? 'N')),
    })"
    x-init="initProvider()"
    class="space-y-5"
>
    <div class="space-y-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-700">Cabecera de compra</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div class="md:col-span-2">
                    <x-form.date-picker
                        name="moved_at"
                        label="Fecha de compra"
                        placeholder="dd/mm/yyyy hh:mm"
                        :defaultDate="old('moved_at', optional($purchase?->moved_at ?? now())->format('Y-m-d H:i'))"
                        dateFormat="Y-m-d H:i"
                        :enableTime="true"
                        :time24hr="true"
                        :altInput="true"
                        altFormat="d/m/Y H:i"
                        locale="es"
                        :compact="true"
                    />
                </div>
                <div class="md:col-span-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Proveedor</label>
                    <input type="hidden" name="person_id" x-model="selectedProviderId" required>
                    <div class="relative" @click.outside="providerOpen = false">
                        <input
                            type="text"
                            x-model="providerQuery"
                            @focus="providerOpen = true"
                            @input="providerOpen = true"
                            @keydown.arrow-down.prevent="moveProviderCursor(1)"
                            @keydown.arrow-up.prevent="moveProviderCursor(-1)"
                            @keydown.enter.prevent="selectProviderByCursor()"
                            class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm"
                            placeholder="Buscar proveedor por nombre o documento"
                            autocomplete="off"
                        >
                        <button
                            type="button"
                            x-show="selectedProviderId"
                            @click="clearProvider()"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:text-gray-700"
                            title="Limpiar proveedor"
                        >
                            <i class="ri-close-line"></i>
                        </button>

                        <div
                            x-show="providerOpen"
                            x-cloak
                            class="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white shadow-lg"
                        >
                            <template x-if="filteredProviders.length === 0">
                                <p class="px-3 py-2 text-xs text-gray-500">Sin resultados</p>
                            </template>
                            <template x-for="(provider, pIndex) in filteredProviders" :key="provider.id">
                                <button
                                    type="button"
                                    @click="selectProvider(provider)"
                                    @mouseenter="providerCursor = pIndex"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50"
                                    :class="providerCursor === pIndex ? 'bg-gray-100' : ''"
                                >
                                    <span class="font-medium text-gray-800" x-text="provider.label || 'SIN NOMBRE'"></span>
                                    <span class="text-xs text-gray-500" x-text="provider.document"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Documento</label>
                    <select name="document_type_id" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm" required>
                        <option value="">Selecciona documento</option>
                        @foreach($documentTypes as $documentType)
                            <option value="{{ $documentType->id }}" @selected((int) old('document_type_id', $purchase?->document_type_id ?? 0) === (int) $documentType->id)>
                                {{ $documentType->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Serie</label>
                    <input type="text" name="series" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm"
                           value="{{ old('series', $purchaseMovement->series ?? '001') }}" placeholder="001">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Numero</label>
                    <input
                        type="text"
                        name="number"
                        class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm"
                        value="{{ old('number', $purchase->number ?? ($purchaseNumberPreview ?? '00000001')) }}"
                        placeholder="00000001"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-700">Detalle de compra</h3>
                <button type="button" @click="addItem()" class="inline-flex items-center rounded-xl bg-[#244BB3] px-3.5 py-2 text-xs font-semibold text-white hover:bg-[#1f3f98]">
                    <i class="ri-add-line mr-1"></i>Agregar item
                </button>
            </div>

            <div class="overflow-visible rounded-xl border border-gray-200">
                <table class="w-full table-fixed">
                    <colgroup>
                        <col style="width:40%">
                        <col style="width:16%">
                        <col style="width:6%">
                        <col style="width:6%">
                        <col style="width:25%">
                        <col style="width:4%">
                        <col style="width:3%">
                    </colgroup>
                    <thead style="background-color: #334155; color: #FFFFFF;">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase">Codigo / Producto</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase">Unidad</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold uppercase">Cant.</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase">P. Unit.</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase">Notas</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase">Importe</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold uppercase">Op.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, idx) in items" :key="idx">
                            <tr class="border-b border-gray-100 last:border-b-0">
                                <td class="relative overflow-visible px-2 py-1.5">
                                    <input type="hidden" :name="`items[${idx}][product_id]`" x-model.number="item.product_id">
                                    <input type="hidden" :name="`items[${idx}][description]`" x-model="item.description">
                                    <div class="relative z-20" @click.outside="item.product_open = false">
                                        <input
                                            type="text"
                                            x-model="item.product_query"
                                            @focus="item.product_open = true"
                                            @input="item.product_open = true"
                                            @keydown.arrow-down.prevent="moveProductCursor(idx, 1)"
                                            @keydown.arrow-up.prevent="moveProductCursor(idx, -1)"
                                            @keydown.enter.prevent="selectProductByCursor(idx)"
                                            class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                            placeholder="Selecciona producto"
                                            autocomplete="off"
                                        >
                                        <button
                                            type="button"
                                            x-show="item.product_id"
                                            @click="clearProduct(idx)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:text-gray-700"
                                            title="Limpiar producto"
                                        >
                                            <i class="ri-close-line"></i>
                                        </button>
                                        <div
                                            x-show="item.product_open"
                                            x-cloak
                                            class="absolute inset-x-0 top-full z-[999] mt-1 w-full max-h-56 overflow-y-auto overflow-x-hidden rounded-xl border border-gray-200 bg-white shadow-2xl"
                                        >
                                            <template x-if="filteredProducts(item).length === 0">
                                                <p class="px-3 py-2 text-xs text-gray-500">Sin resultados</p>
                                            </template>
                                            <template x-for="(product, pIndex) in filteredProducts(item)" :key="product.id">
                                                <button
                                                    type="button"
                                                    @click="selectProduct(idx, product)"
                                                    @mouseenter="item.product_cursor = pIndex"
                                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50"
                                                    :class="item.product_cursor === pIndex ? 'bg-gray-100' : ''"
                                                >
                                                    <span class="font-medium text-gray-800" x-text="`${product.code || 'SIN'} - ${product.name}`"></span>
                                                    <span class="text-xs text-gray-500" x-text="product.unit_name || ''"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-1.5">
                                    <select :name="`items[${idx}][unit_id]`" x-model.number="item.unit_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                                        <option value="">Unidad</option>
                                        <template x-for="unit in units" :key="unit.id">
                                            <option :value="unit.id" x-text="unit.description"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-2 py-1.5">
                                    <input :name="`items[${idx}][quantity]`" type="number" step="1" min="1" x-model.number="item.quantity"
                                           class="h-11 w-20 max-w-full rounded-lg border border-gray-300 px-2 text-center text-lg font-semibold" required>
                                </td>
                                <td class="px-2 py-1.5">
                                    <input :name="`items[${idx}][amount]`" type="number" step="0.01" min="0" x-model.number="item.amount"
                                           class="h-11 w-20 max-w-full rounded-lg border border-gray-300 px-2 text-center text-lg font-semibold" required>
                                </td>
                                <td class="px-2 py-1.5">
                                    <input :name="`items[${idx}][comment]`" x-model="item.comment" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Obs">
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <p class="text-sm font-bold text-gray-800" x-text="money((item.quantity || 0) * (item.amount || 0))"></p>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <button
                                        type="button"
                                        @click="removeItem(idx)"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg shadow-sm"
                                        style="background-color:#ef4444 !important; color:#fff !important; border:1px solid #ef4444 !important;"
                                        title="Eliminar item"
                                    >
                                        <i class="ri-delete-bin-line text-white" style="color:#fff !important; font-size:16px;"></i>
                                        <span class="sr-only">Eliminar</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="mb-4 grid grid-cols-2 overflow-hidden rounded-xl border border-[#fecaca] text-xs font-semibold uppercase">
                <button type="button" class="bg-[#fff1f2] py-2 text-[#ef4444]">Datos de compra</button>
                <button type="button" class="bg-white py-2 text-gray-500">Resumen</button>
            </div>

            
                <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Tipo detalle</label>
                        <select name="detail_type" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm">
                            @foreach(['DETALLADO','GLOSA'] as $option)
                                <option value="{{ $option }}" @selected(old('detail_type', $purchaseMovement->detail_type ?? 'DETALLADO') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Incluye IGV</label>
                        <select name="includes_tax" x-model="includesTax" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm">
                            <option value="N">No</option>
                            <option value="S">Si</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">IGV %</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_rate_percent" x-model.number="taxRate" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Tipo pago</label>
                        <select name="payment_type" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm">
                            @foreach(['CONTADO','CREDITO'] as $option)
                                <option value="{{ $option }}" @selected(old('payment_type', $purchaseMovement->payment_type ?? 'CONTADO') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Moneda</label>
                        <select name="currency" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm" required>
                            <option value="PEN" @selected(old('currency', $purchaseMovement->currency ?? 'PEN') === 'PEN')>PEN</option>
                            <option value="USD" @selected(old('currency', $purchaseMovement->currency ?? 'PEN') === 'USD')>USD</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Tipo de cambio</label>
                        <input type="number" step="0.001" min="0.001" name="exchange_rate" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm"
                               value="{{ old('exchange_rate', $purchaseMovement->exchange_rate ?? 3.5) }}" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Afecta caja</label>
                        <select name="affects_cash" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm">
                            <option value="N" @selected(old('affects_cash', $purchaseMovement->affects_cash ?? 'N') === 'N')>No</option>
                            <option value="S" @selected(old('affects_cash', $purchaseMovement->affects_cash ?? 'N') === 'S')>Si</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Afecta kardex</label>
                        <select name="affects_kardex" class="h-10 w-full rounded-xl border border-gray-300 px-3 text-sm">
                            <option value="S" @selected(old('affects_kardex', $purchaseMovement->affects_kardex ?? 'S') === 'S')>Si</option>
                            <option value="N" @selected(old('affects_kardex', $purchaseMovement->affects_kardex ?? 'S') === 'N')>No</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Notas de compra</label>
                        <textarea name="comment" rows="2" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Comentario compra">{{ old('comment', $purchase?->comment ?? '') }}</textarea>
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1.5 block text-xs font-semibold uppercase text-gray-500">Resumen</label>
                        <div class="space-y-1.5 rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Subtotal</span>
                                <span class="font-bold text-gray-900" x-text="money(summary.subtotal)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>IGV</span>
                                <span class="font-bold text-gray-900" x-text="money(summary.tax)"></span>
                            </div>
                            <div class="mt-2 flex items-center justify-between border-t border-gray-300 pt-2">
                                <span class="text-sm font-semibold uppercase text-gray-700">Total</span>
                                <span class="text-2xl font-extrabold text-[#244BB3]" x-text="money(summary.total)"></span>
                            </div>
                        </div>
                    </div>
                </div>
           
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function purchaseForm({ products, units, providers, initialProviderId, initialItems, taxRate, includesTax }) {
                return {
                    products,
                    units,
                    providers: providers || [],
                    initialProviderId: Number(initialProviderId || 0),
                    selectedProviderId: '',
                    providerQuery: '',
                    providerOpen: false,
                    providerCursor: 0,
                    items: (initialItems && initialItems.length)
                        ? initialItems.map(i => ({
                            product_id: Number(i.product_id || 0),
                            unit_id: Number(i.unit_id || 0),
                            description: i.description || '',
                            quantity: Number(i.quantity || 1),
                            amount: Number(i.amount || 0),
                            comment: i.comment || '',
                            product_query: '',
                            product_open: false,
                            product_cursor: 0,
                        }))
                        : [{ product_id: 0, unit_id: 0, description: '', quantity: 1, amount: 0, comment: '', product_query: '', product_open: false, product_cursor: 0 }],
                    taxRate: Number(taxRate || 18),
                    includesTax: includesTax || 'N',
                    initProvider() {
                        if (!this.initialProviderId) return;
                        const provider = this.providers.find(p => Number(p.id) === Number(this.initialProviderId));
                        if (!provider) return;
                        this.selectedProviderId = String(provider.id);
                        this.providerQuery = provider.document
                            ? `${provider.label} - ${provider.document}`
                            : provider.label;
                        this.items.forEach((item, idx) => {
                            if (Number(item.product_id || 0) > 0) {
                                this.setProductMeta(idx);
                            }
                        });
                    },
                    get filteredProviders() {
                        const term = (this.providerQuery || '').toLowerCase().trim();
                        const list = term === ''
                            ? this.providers
                            : this.providers.filter((p) => {
                                const label = (p.label || '').toLowerCase();
                                const doc = (p.document || '').toLowerCase();
                                return label.includes(term) || doc.includes(term);
                            });
                        if (this.providerCursor >= list.length) this.providerCursor = 0;
                        return list.slice(0, 40);
                    },
                    selectProvider(provider) {
                        this.selectedProviderId = String(provider.id);
                        this.providerQuery = provider.document
                            ? `${provider.label} - ${provider.document}`
                            : provider.label;
                        this.providerOpen = false;
                    },
                    clearProvider() {
                        this.selectedProviderId = '';
                        this.providerQuery = '';
                        this.providerOpen = true;
                        this.providerCursor = 0;
                    },
                    moveProviderCursor(step) {
                        const list = this.filteredProviders;
                        if (!list.length) return;
                        const max = list.length - 1;
                        const next = this.providerCursor + step;
                        if (next < 0) this.providerCursor = max;
                        else if (next > max) this.providerCursor = 0;
                        else this.providerCursor = next;
                    },
                    selectProviderByCursor() {
                        const list = this.filteredProviders;
                        if (!list.length) return;
                        this.selectProvider(list[this.providerCursor] || list[0]);
                    },
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
                        this.items.push({
                            product_id: 0,
                            unit_id: 0,
                            description: '',
                            quantity: 1,
                            amount: 0,
                            comment: '',
                            product_query: '',
                            product_open: false,
                            product_cursor: 0,
                        });
                    },
                    removeItem(idx) {
                        this.items.splice(idx, 1);
                        if (!this.items.length) this.addItem();
                    },
                    filteredProducts(item) {
                        const term = String(item.product_query || '').toLowerCase().trim();
                        const list = term === ''
                            ? this.products
                            : this.products.filter((p) => {
                                const code = String(p.code || '').toLowerCase();
                                const name = String(p.name || '').toLowerCase();
                                const unit = String(p.unit_name || '').toLowerCase();
                                return code.includes(term) || name.includes(term) || unit.includes(term);
                            });
                        if (item.product_cursor >= list.length) item.product_cursor = 0;
                        return list.slice(0, 40);
                    },
                    selectProduct(idx, product) {
                        this.items[idx].product_id = Number(product.id);
                        this.items[idx].product_query = `${product.code || 'SIN'} - ${product.name}`;
                        this.items[idx].description = product.name || '';
                        this.items[idx].product_open = false;
                        this.setProductMeta(idx);
                    },
                    clearProduct(idx) {
                        const current = this.items[idx];
                        current.product_id = 0;
                        current.product_query = '';
                        current.description = '';
                        current.unit_id = 0;
                        current.amount = 0;
                        current.product_open = true;
                        current.product_cursor = 0;
                    },
                    moveProductCursor(idx, step) {
                        const current = this.items[idx];
                        const list = this.filteredProducts(current);
                        if (!list.length) return;
                        const max = list.length - 1;
                        const next = current.product_cursor + step;
                        if (next < 0) current.product_cursor = max;
                        else if (next > max) current.product_cursor = 0;
                        else current.product_cursor = next;
                    },
                    selectProductByCursor(idx) {
                        const current = this.items[idx];
                        const list = this.filteredProducts(current);
                        if (!list.length) return;
                        this.selectProduct(idx, list[current.product_cursor] || list[0]);
                    },
                    setProductMeta(idx) {
                        const product = this.products.find(p => Number(p.id) === Number(this.items[idx].product_id));
                        if (!product) return;
                        if (!this.items[idx].product_query) this.items[idx].product_query = `${product.code || 'SIN'} - ${product.name}`;
                        this.items[idx].description = product.name || '';
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
