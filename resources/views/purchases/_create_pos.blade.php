<input type="hidden" name="person_id" :value="selectedProviderId" required>
<input type="hidden" name="affects_cash" :value="paymentType === 'CONTADO' ? 'S' : 'N'">
<div class="hidden">
    <template x-for="(item, idx) in items" :key="`hidden-item-${idx}`">
        <div>
            <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id || ''">
            <input type="hidden" :name="`items[${idx}][unit_id]`" :value="item.unit_id || ''">
            <input type="hidden" :name="`items[${idx}][description]`" :value="item.description || ''">
            <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity || 1">
            <input type="hidden" :name="`items[${idx}][amount]`" :value="item.amount || 0">
            <input type="hidden" :name="`items[${idx}][comment]`" :value="item.comment || ''">
        </div>
    </template>
</div>

<div class="flex items-start gap-6" style="display:flex;align-items:flex-start;gap:1.5rem;">
    <section class="min-w-0 space-y-5" style="flex:0 0 68%;max-width:68%;width:68%;">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" x-show="detailType === 'DETALLADO'">
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-12">
                <div class="xl:col-span-4">
                    <x-form.date-picker
                        name="moved_at"
                        label="Fecha de compra"
                        placeholder="dd/mm/yyyy hh:mm"
                        :defaultDate="old('moved_at', $purchaseCreateConfig['initialMovedAt'])"
                        dateFormat="Y-m-d H:i"
                        :enableTime="true"
                        :time24hr="true"
                        :altInput="true"
                        altFormat="d/m/Y H:i"
                        locale="es"
                        :compact="true"
                    />
                </div>
                <div class="xl:col-span-3">
                    <label class="mb-2 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Documento</label>
                    <select name="document_type_id" x-model.number="documentTypeId" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700" required>
                        <template x-for="documentType in documentTypes" :key="documentType.id"><option :value="documentType.id" x-text="documentType.name"></option></template>
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label class="mb-2 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Serie</label>
                    <input type="text" name="series" value="{{ old('series', '001') }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700" placeholder="001">
                </div>
                <div class="xl:col-span-3">
                    <label class="mb-2 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Numero</label>
                    <input type="text" name="number" value="{{ old('number', $purchaseCreateConfig['purchaseNumberPreview']) }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700" placeholder="00000001" required>
                </div>
            </div>
        </div>

        <div class="rounded-[30px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Catálogo</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Productos</h3>
                </div>
                <div class="flex flex-wrap gap-3">
                    <template x-for="category in catalogCategories" :key="`purchase-category-${category}`">
                        <button
                            type="button"
                            @click="selectedCategory = category"
                            class="inline-flex h-12 items-center justify-center rounded-[22px] border px-6 text-sm font-bold transition"
                            :class="selectedCategory === category ? 'border-transparent text-white shadow-theme-xs' : 'border-slate-200 bg-white text-slate-900 hover:border-orange-200 hover:text-orange-600'"
                            :style="selectedCategory === category ? 'background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.22);' : ''"
                            x-text="category"
                        ></button>
                    </template>
                </div>
            </div>

            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-800">
                    <i class="ri-search-line text-[22px]"></i>
                </span>
                <input
                    type="text"
                    x-model="productSearch"
                    placeholder="Buscar por nombre o categoría"
                    class="h-14 w-full rounded-[22px] border border-slate-200 bg-slate-50 pl-14 pr-4 text-sm font-medium text-slate-700"
                >
            </div>

            <div id="purchase-products-grid" class="mt-5 grid gap-4">
                <template x-for="product in filteredCatalogProducts" :key="`catalog-${product.id}`">
                    <button
                        type="button"
                        @click="addProductCard(product)"
                        class="group relative overflow-hidden border bg-white text-center transition-all duration-200"
                        style="border-radius:30px;border-color:#e4e9f1;border-width:1px;border-style:solid;background-color:#ffffff;box-shadow:0 10px 24px rgba(15,23,42,.05);height:190px;min-height:190px;"
                        @mouseenter="$el.style.transform='translateY(-4px)';$el.style.borderColor='#ffd1a4';$el.style.boxShadow='0 18px 34px rgba(249,115,22,.12)';$el.style.backgroundColor='#fffdfb';const orb=$el.querySelector('[data-role=product-orb]'); if(orb){orb.style.transform='translateY(-1px) scale(1.03)';orb.style.boxShadow='0 18px 30px rgba(249,115,22,.12), 0 8px 16px rgba(15,23,42,.06)';}"
                        @mouseleave="$el.style.transform='';$el.style.borderColor='#e4e9f1';$el.style.boxShadow='0 10px 24px rgba(15,23,42,.05)';$el.style.backgroundColor='#ffffff';const orb=$el.querySelector('[data-role=product-orb]'); if(orb){orb.style.transform='';orb.style.boxShadow='0 12px 24px rgba(249,115,22,.08), 0 6px 14px rgba(15,23,42,.04)';}"
                    >
                        <div class="relative flex h-full w-full flex-col items-center px-3 pb-4 pt-4">
                            <div class="absolute right-3 top-4 z-20 inline-flex min-w-[62px] items-center justify-center rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-center text-[10px] font-bold leading-none text-orange-600" style="box-shadow:0 6px 14px rgba(15,23,42,.08);">
                                Stock: <span x-text="Number(product.stock || 0).toFixed(0)"></span>
                            </div>
                            <div class="flex h-[102px] w-full items-center justify-center pt-2">
                                <div
                                    data-role="product-orb"
                                    class="mx-auto flex h-[92px] w-[92px] items-center justify-center overflow-hidden rounded-full bg-white transition-transform duration-200"
                                    style="box-shadow:0 12px 24px rgba(249,115,22,.08), 0 6px 14px rgba(15,23,42,.04);"
                                >
                                    <template x-if="product.img">
                                        <img :src="product.img" :alt="productLabel(product)" class="h-16 w-16 object-contain" x-on:error="$el.style.display='none'">
                                    </template>
                                    <template x-if="!product.img">
                                        <i class="ri-shopping-bag-3-line text-[30px] text-orange-500"></i>
                                    </template>
                                </div>
                            </div>
                            <div class="mt-2 flex h-[50px] w-full items-start justify-center px-1">
                                <h4 class="line-clamp-2 block w-full text-center text-[12px] font-black leading-[1.28] text-slate-900" x-text="productLabel(product)"></h4>
                            </div>
                            <div class="mt-1 flex h-[24px] w-full items-center justify-center">
                                <p class="text-[0.95rem] font-black leading-none tracking-tight transition-colors duration-200 group-hover:text-orange-600" style="color:#f97316;" x-text="money(productAmount(product))"></p>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
            <div x-show="!filteredCatalogProducts.length" class="mt-1 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
                No se encontraron productos para el filtro actual.
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" x-show="detailType === 'GLOSA'">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Detalle</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Compra por glosa</h3>
                    <p class="mt-1 text-sm text-slate-500">Registra conceptos manuales sin obligar un producto del catálogo.</p>
                </div>
                <button
                    type="button"
                    @click="addGlosaItem()"
                    class="inline-flex h-11 items-center gap-2 rounded-2xl px-4 text-sm font-bold text-white shadow-theme-xs"
                    style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.24);"
                >
                    <i class="ri-add-line"></i>
                    <span>Agregar glosa</span>
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, idx) in items" :key="`glosa-${idx}`">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 md:grid-cols-12">
                            <div class="md:col-span-5">
                                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Descripcion</label>
                                <input
                                    type="text"
                                    x-model="item.description"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700"
                                    placeholder="Ej. Compra administrativa, traslado, ajuste, repuesto externo"
                                >
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Unidad</label>
                                <select x-model.number="item.unit_id" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                    <template x-for="unit in units" :key="`glosa-unit-${idx}-${unit.id}`">
                                        <option :value="unit.id" x-text="unit.description"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Cantidad</label>
                                <input type="number" min="0.0001" step="0.0001" x-model.number="item.quantity" @input="syncPaymentAmounts()" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Monto</label>
                                <input type="number" min="0" step="0.01" x-model.number="item.amount" @input="syncPaymentAmounts()" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#f97316;">
                            </div>
                            <div class="md:col-span-1 flex items-end">
                                <button type="button" @click="removeItem(idx)" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                            <div class="md:col-span-12">
                                <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Comentario</label>
                                <input
                                    type="text"
                                    x-model="item.comment"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700"
                                    placeholder="Observacion opcional del concepto"
                                >
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <aside class="min-w-0" style="flex:0 0 32%;max-width:32%;width:32%;">
        <div class="sticky top-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-800 bg-slate-900 px-4 py-3 text-white" style="background-color:#334155;">
                <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-slate-800/90 p-1">
                    <button type="button" @click="setAsideTab('summary')" class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold" :style="asideTab==='summary' ? activeTabStyle() : ''" :class="asideTab==='summary' ? 'text-white shadow-theme-xs' : 'text-slate-300 hover:text-white'">Resumen</button>
                    <button type="button" @click="setAsideTab('payment')" class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold" :style="asideTab==='payment' ? activeTabStyle() : ''" :class="asideTab==='payment' ? 'text-white shadow-theme-xs' : 'text-slate-300 hover:text-white'">Pago</button>
                </div>
            </div>

            <div x-show="asideTab==='summary'" class="bg-slate-50 p-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tipo detalle</label><select name="detail_type" x-model="detailType" @change="changeDetailType($event.target.value)" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><option value="DETALLADO">DETALLADO</option><option value="GLOSA">GLOSA</option></select></div>
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Afecta kardex</label><select name="affects_kardex" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><option value="S" selected>Si</option><option value="N">No</option></select></div>
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Incluye IGV</label><select name="includes_tax" x-model="includesTax" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><option value="S">Si</option><option value="N">No</option></select></div>
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">IGV %</label><input type="number" step="0.01" min="0" max="100" name="tax_rate_percent" x-model.number="taxRate" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#f97316;" required></div>
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Moneda</label><select name="currency" x-model="currency" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><option value="PEN">PEN</option><option value="USD">USD</option></select></div>
                        <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tipo cambio</label><input type="number" step="0.001" min="0.001" name="exchange_rate" x-model.number="exchangeRate" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#f97316;" required></div>
                    </div>
                </div>
                <div class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                    <template x-if="!items.length"><div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-xs font-medium text-slate-500">Agrega items para ver el resumen.</div></template>
                    <template x-for="(item, idx) in items" :key="`summary-${idx}`">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center gap-3 p-2.5">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                    <template x-if="productImage(item)">
                                        <img :src="productImage(item)" :alt="item.description || 'Producto'" class="h-12 w-12 object-cover">
                                    </template>
                                    <template x-if="!productImage(item) && detailType !== 'GLOSA'">
                                        <i class="ri-shopping-bag-3-line text-xl text-slate-400"></i>
                                    </template>
                                    <template x-if="!productImage(item) && detailType === 'GLOSA'">
                                        <i class="ri-file-text-line text-xl text-slate-400"></i>
                                    </template>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <h5 class="truncate text-sm font-bold text-slate-900" x-text="item.description || 'Producto'"></h5>
                                                <span class="shrink-0 text-sm font-black" style="color:#f97316;" x-text="money((item.quantity || 0) * (item.amount || 0))"></span>
                                            </div>
                                            <p class="mt-0.5 text-xs font-semibold" style="color:#fb923c;" x-text="`${money(item.amount || 0)} c/u`"></p>
                                        </div>
                                        <div class="inline-flex shrink-0 items-center rounded-xl border border-slate-200 bg-slate-50">
                                            <button type="button" @click="updateItemQuantity(idx, -1)" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-rose-600">
                                                <i class="ri-subtract-line"></i>
                                            </button>
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                :value="Math.max(1, Math.floor(Number(item.quantity) || 1))"
                                                @change="setItemQuantity(idx, $event.target.value)"
                                                class="h-8 w-12 border-x border-slate-200 bg-white text-center text-sm font-bold text-slate-900 outline-none"
                                            >
                                            <button type="button" @click="updateItemQuantity(idx, 1)" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-orange-600">
                                                <i class="ri-add-line"></i>
                                            </button>
                                        </div>
                                        <button type="button" @click="removeItem(idx)" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex justify-between text-sm text-slate-500"><span>Subtotal</span><span class="font-semibold" x-text="money(summary.subtotal)"></span></div>
                    <div class="flex justify-between text-sm text-slate-500"><span>IGV</span><span class="font-semibold" x-text="money(summary.tax)"></span></div>
                    <div class="border-t border-dashed border-slate-200 pt-2"></div>
                    <div class="flex items-center justify-between"><span class="text-base font-bold text-slate-900">Total a pagar</span><span class="text-3xl font-black" style="color:#f97316;" x-text="money(summary.total)"></span></div>
                </div>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Notas</label>
                    <textarea name="comment" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700" placeholder="Detalle adicional de la compra">{{ old('comment') }}</textarea>
                </div>
            </div>

            <div x-show="asideTab==='payment'" class="bg-slate-50 p-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="space-y-4">
                            <div class="space-y-2" @click.outside="providerOpen=false">
                                <label class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Proveedor</label>
                                <div class="flex items-start gap-2">
                                    <div class="relative flex-1">
                                        <input type="text" x-model="providerQuery" @focus="providerOpen=true" @input="providerOpen=true" @keydown.arrow-down.prevent="moveProviderCursor(1)" @keydown.arrow-up.prevent="moveProviderCursor(-1)" @keydown.enter.prevent="selectProviderByCursor()" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 pr-10 text-sm font-medium text-slate-700" placeholder="Buscar proveedor por nombre o documento" autocomplete="off">
                                        <button type="button" x-show="selectedProviderId" @click="clearProvider()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"><i class="ri-close-line"></i></button>
                                        <div x-show="providerOpen" x-cloak class="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
                                            <template x-if="filteredProviders.length===0"><p class="px-4 py-3 text-xs text-slate-500">Sin resultados</p></template>
                                            <template x-for="(provider,pIndex) in filteredProviders" :key="provider.id">
                                                <button type="button" @click="selectProvider(provider)" @mouseenter="providerCursor=pIndex" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-slate-50" :class="providerCursor===pIndex ? 'bg-slate-100' : ''">
                                                    <span class="font-medium text-slate-800" x-text="provider.label || 'SIN NOMBRE'"></span>
                                                    <span class="text-xs text-slate-500" x-text="provider.document"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="resetQuickProvider(); $dispatch('open-provider-modal')"
                                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-theme-xs"
                                        style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.24);"
                                        title="Agregar proveedor"
                                    >
                                        <i class="ri-add-line text-lg"></i>
                                    </button>
                                </div>
                            </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tipo pago</label><select name="payment_type" x-model="paymentType" @change="onPaymentTypeChange()" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><option value="CONTADO">CONTADO</option><option value="CREDITO">CREDITO / DEUDA</option></select></div>
                            <div><label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Caja</label><select name="cash_register_id" x-model.number="cashRegisterId" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"><template x-for="cashRegister in cashRegisters" :key="cashRegister.id"><option :value="cashRegister.id" x-text="cashRegister.status==='A' ? `${cashRegister.number} (Activa)` : cashRegister.number"></option></template></select></div>
                        </div>
                        <div x-show="paymentType==='CREDITO'" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                            Esta compra se registrara como deuda y se enviara a cuentas por pagar.
                        </div>
                    </div>
                </div>

                <div x-show="paymentType==='CONTADO'" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between"><p class="text-sm font-bold text-slate-900">Métodos de pago</p><button type="button" @click="addPaymentRow()" class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-xs font-bold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 10px 20px rgba(249,115,22,.18);"><i class="ri-add-line"></i><span>Agregar</span></button></div>
                    <div class="space-y-3">
                        <template x-for="(row, idx) in paymentRows" :key="`payment-${idx}`">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <input type="hidden" :name="`payment_methods[${idx}][payment_method_id]`" :value="row.payment_method_id || ''">
                                <input type="hidden" :name="`payment_methods[${idx}][card_id]`" :value="row.card_id || ''">
                                <input type="hidden" :name="`payment_methods[${idx}][digital_wallet_id]`" :value="row.digital_wallet_id || ''">
                                <div class="grid gap-3" :style="row.kind==='card' ? 'grid-template-columns:minmax(0,3.2fr) minmax(0,1.5fr) minmax(0,2.2fr) 52px;' : 'grid-template-columns:minmax(0,3.4fr) minmax(0,1.8fr) 52px;'">
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Metodo</label>
                                        <select x-model="row.method_variant_key" @change="applyPaymentVariant(idx)" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <template x-for="variant in paymentMethodVariants" :key="variant.key"><option :value="variant.key" x-text="variant.label"></option></template>
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Monto</label>
                                        <input :name="`payment_methods[${idx}][amount]`" type="number" min="0" step="0.01" x-model.number="row.amount" @input="syncPaymentAmounts()" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#f97316;">
                                    </div>
                                    <div class="min-w-0" x-show="row.kind==='card'">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Pasarela</label>
                                        <select :name="`payment_methods[${idx}][payment_gateway_id]`" x-model="row.payment_gateway_id" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <option value="">Seleccionar</option>
                                            <template x-for="gateway in paymentGateways" :key="gateway.id"><option :value="gateway.id" x-text="gateway.description"></option></template>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="button" @click="removePaymentRow(idx)" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <div class="flex items-center justify-between"><span class="font-semibold text-slate-500">Total pagado</span><span class="font-black" style="color:#f97316;" x-text="money(totalPaid)"></span></div>
                        <div class="mt-2 flex items-center justify-between border-t border-dashed border-slate-200 pt-2" x-show="Math.abs(paymentDifference) > .009"><span class="font-semibold" :style="paymentDifference >= 0 ? 'color:#ea580c;' : 'color:#059669;'" x-text="paymentDifference >= 0 ? 'Falta pagar' : 'Exceso'"></span><span class="font-black" :style="paymentDifference >= 0 ? 'color:#ea580c;' : 'color:#059669;'" x-text="money(Math.abs(paymentDifference))"></span></div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <a href="{{ $purchaseIndexUrl }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-100"><i class="ri-close-line"></i><span>Cancelar</span></a>
                    <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.24);"><i class="ri-save-line"></i><span>Guardar compra</span></button>
                </div>
            </div>
        </div>
    </aside>
</div>

<style>
    #purchases-create-view input:focus,#purchases-create-view select:focus,#purchases-create-view textarea:focus{outline:none!important;box-shadow:0 0 0 3px rgba(249,115,22,.16)!important;border-color:#f97316!important}
    #purchases-create-view input:focus-visible,#purchases-create-view select:focus-visible,#purchases-create-view textarea:focus-visible{outline:none!important}
    #purchases-create-view #purchase-products-grid{grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:.95rem!important}
    @media (max-width:1199px){
        #purchases-create-view #purchase-products-grid{grid-template-columns:repeat(4,minmax(0,1fr))!important}
    }
    @media (max-width:991px){
        #purchases-create-view #purchase-products-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}
    }
    @media (max-width:1279px){
        #purchases-create-view .flex.items-start.gap-6[style*="display:flex"]{flex-direction:column!important;gap:1rem!important}
        #purchases-create-view .flex.items-start.gap-6[style*="display:flex"]>section,
        #purchases-create-view .flex.items-start.gap-6[style*="display:flex"]>aside{flex:0 0 100%!important;max-width:100%!important;width:100%!important}
        #purchases-create-view aside .sticky{position:static!important;top:auto!important}
    }
    @media (max-width:767px){
        #purchases-create-view #purchase-products-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:.65rem!important}
    }
</style>

@once
    @push('scripts')
        @include('purchases._create_pos_script')
    @endpush
@endonce
