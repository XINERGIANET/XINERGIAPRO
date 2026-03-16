@extends('layouts.app')

@section('title', 'Salida de Productos')

@section('content')
    @php
        $viewId = request('view_id');
        $warehouseIndexUrl = route('warehouse_movements.index', $viewId ? ['view_id' => $viewId] : []);
    @endphp

    <div id="warehouse-output-view">
        <x-common.page-breadcrumb pageTitle="Salida de productos" />

        <x-common.component-card
            title="Almacén | Salida"
            desc="Registra salidas del inventario con el mismo flujo visual que la entrada."
        >
            <div class="flex items-start gap-6" style="display:flex;align-items:flex-start;gap:1.5rem;">
                <section class="min-w-0 space-y-5" style="flex:0 0 60%;max-width:60%;width:60%;">
                    <div class="rounded-[30px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Catálogo</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">Productos</h3>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div id="category-filters" class="flex flex-wrap gap-3"></div>
                                <a href="{{ $warehouseIndexUrl }}" class="inline-flex h-12 items-center gap-2 rounded-[22px] border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="ri-arrow-left-line"></i>
                                    <span>Volver</span>
                                </a>
                                <button type="button" id="clear-output-button" class="inline-flex h-12 items-center gap-2 rounded-[22px] border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    <i class="ri-delete-bin-6-line"></i>
                                    <span>Limpiar</span>
                                </button>
                            </div>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-800">
                                <i class="ri-search-line text-[22px]"></i>
                            </span>
                            <input
                                id="product-search"
                                type="text"
                                placeholder="Buscar por codigo de barras, nombre o categoria"
                                class="h-14 w-full rounded-[22px] border border-slate-200 bg-slate-50 pl-14 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"
                            >
                        </div>
                        <div id="products-grid" class="mt-5 grid gap-4"></div>
                    </div>
                </section>

                <aside class="min-w-0" style="flex:0 0 40%;max-width:40%;width:40%;">
                    <div class="sticky top-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-800 bg-slate-900 px-4 py-3 text-white" style="background-color:#334155;">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300">Salida</p>
                                    <h3 class="mt-0.5 text-xl font-bold">Resumen</h3>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-bold text-white" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);">
                                    En curso
                                </span>
                            </div>
                        </div>

                        <div class="max-h-[42vh] overflow-y-auto p-4" id="output-cart-container"></div>

                        <div id="stock-warning" class="hidden mx-4 mb-2 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>

                        <div class="border-t border-slate-200 bg-slate-50 p-5 space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <label class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Comentario (opcional)</label>
                                <textarea id="movement-comment" rows="2" placeholder="Ej: Merma, Vencimiento, Transferencia..." class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"></textarea>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                                    <input type="checkbox" id="is-transfer" class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                    Transferir stock a otra sucursal
                                </label>
                                <div id="transfer-target-wrapper" class="mt-3 hidden">
                                    <label for="to-branch-id" class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Sucursal destino</label>
                                    <select id="to-branch-id" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                        <option value="">Selecciona sucursal</option>
                                        @foreach(($targetBranches ?? collect()) as $targetBranch)
                                            <option value="{{ $targetBranch->id }}">{{ $targetBranch->legal_name }} (#{{ $targetBranch->id }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex justify-between text-sm text-slate-500"><span>Items</span><span id="output-total-products" class="font-semibold text-slate-700">0</span></div>
                                <div class="flex justify-between text-sm text-slate-500"><span>Cantidad</span><span id="output-total-quantity" class="font-semibold text-slate-700">0</span></div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <a href="{{ $warehouseIndexUrl }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                    <i class="ri-close-line"></i><span>Cancelar</span>
                                </a>
                                <button type="button" id="save-output-button" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.24);">
                                    <i class="ri-save-line"></i><span id="save-output-text">Guardar salida</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </x-common.component-card>
    </div>

    <script>
        (function () {
            const products = Array.isArray(@json($productsMapped ?? [])) ? @json($productsMapped ?? []) : [];
            const branchId = Number(@json($branchId ?? 0)) || 0;
            const viewId = @json($viewId ?? null);
            const editingMovement = @json($editingMovement ? ['id' => $editingMovement->id] : null);
            const editingCartRaw = Array.isArray(@json($editingCart ?? [])) ? @json($editingCart ?? []) : [];

            const state = { selectedCategory: 'GENERAL', search: '', cart: [] };
            if (editingMovement && editingCartRaw.length) {
                state.cart = editingCartRaw.map((item) => ({
                    id: Number(item.id),
                    name: String(item.name || ''),
                    code: String(item.code || ''),
                    unit: String(item.unit || 'Unidad'),
                    quantity: Number(item.quantity) || 1,
                    currentStock: Number(item.currentStock) || 0,
                }));
            }
            const categoryFilters = document.getElementById('category-filters');
            const productsGrid = document.getElementById('products-grid');
            const cartContainer = document.getElementById('output-cart-container');
            const searchInput = document.getElementById('product-search');
            let searchTimer = null;

            function getImageUrl(raw) {
                if (raw && String(raw).trim() !== '') return raw;
                return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cmVjdCBmaWxsPSIjZTdlOWViIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5Y2EzYWYiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
            }

            function normalizeCategory(cat) {
                const s = String(cat || '').trim().toUpperCase();
                return (s === '' || s === 'SIN CATEGORÍA') ? 'GENERAL' : s;
            }

            function categories() {
                const base = ['GENERAL'];
                products.forEach((product) => {
                    const name = normalizeCategory(product.category);
                    if (name && !base.includes(name)) base.push(name);
                });
                return base;
            }

            function filteredProducts() {
                const term = state.search.trim().toLowerCase();
                return products.filter((product) => {
                    const category = normalizeCategory(product.category);
                    const categoryOk = state.selectedCategory === 'GENERAL' || category === state.selectedCategory;
                    const searchOk = term === ''
                        || String(product.name || '').toLowerCase().includes(term)
                        || String(product.code || '').toLowerCase().includes(term)
                        || String(product.category || '').toLowerCase().includes(term);
                    return categoryOk && searchOk;
                });
            }

            function normalizeProductCode(value) {
                return String(value || '').trim().toLowerCase();
            }

            function clearSearchField() {
                state.search = '';
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
            }

            function findUniqueProductByCode(searchTerm) {
                const needle = normalizeProductCode(searchTerm);
                if (!needle) return null;
                const matches = products.filter((product) => normalizeProductCode(product.code) === needle);
                return matches.length === 1 ? matches[0] : null;
            }

            function tryAutoAddProductByCode(searchTerm) {
                const matchedProduct = findUniqueProductByCode(searchTerm);
                if (!matchedProduct || Number(matchedProduct.currentStock || 0) <= 0) return false;
                addToCart(matchedProduct.id);
                clearSearchField();
                renderProducts();
                return true;
            }

            function renderCategoryFilters() {
                if (!categoryFilters) return;
                categoryFilters.innerHTML = '';
                categories().forEach((category) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inline-flex h-12 items-center justify-center rounded-[22px] border px-6 text-sm font-bold transition';
                    const active = state.selectedCategory === category;
                    if (active) {
                        button.classList.add('border-transparent', 'text-white', 'shadow-theme-xs');
                        button.style.background = 'linear-gradient(90deg,#ff7a00,#ff4d00)';
                        button.style.boxShadow = '0 12px 24px rgba(249,115,22,.24)';
                    } else {
                        button.classList.add('border-slate-200', 'bg-white', 'text-slate-900', 'hover:border-orange-200', 'hover:text-orange-600');
                    }
                    button.textContent = category;
                    button.addEventListener('click', () => {
                        state.selectedCategory = category;
                        renderCategoryFilters();
                        renderProducts();
                    });
                    categoryFilters.appendChild(button);
                });
            }

            function addToCart(productId) {
                const product = products.find((item) => Number(item.id) === Number(productId));
                if (!product || Number(product.currentStock || 0) <= 0) return;
                const existing = state.cart.find((item) => Number(item.id) === Number(product.id));
                const maxQty = Number(product.currentStock || 0);
                if (existing) {
                    existing.quantity = Math.min(existing.quantity + 1, maxQty);
                } else {
                    state.cart.push({
                        id: Number(product.id),
                        name: String(product.name || ''),
                        code: String(product.code || ''),
                        unit: String(product.unit || 'Unidad'),
                        currentStock: maxQty,
                        quantity: 1,
                    });
                }
                renderCart();
            }

            function updateQuantity(productId, diff) {
                const item = state.cart.find((entry) => Number(entry.id) === Number(productId));
                if (!item) return;
                const maxQty = Number(item.currentStock || 0);
                item.quantity = Math.max(0, Math.min(maxQty, (item.quantity || 0) + diff));
                if (item.quantity <= 0) {
                    state.cart = state.cart.filter((entry) => Number(entry.id) !== Number(productId));
                }
                renderCart();
            }

            function removeItem(productId) {
                state.cart = state.cart.filter((entry) => Number(entry.id) !== Number(productId));
                renderCart();
            }

            function checkStockValidation() {
                const invalid = state.cart.filter((p) => (p.quantity || 0) > (p.currentStock || 0));
                const warnEl = document.getElementById('stock-warning');
                if (!warnEl) return invalid.length === 0;
                if (invalid.length > 0) {
                    warnEl.textContent = 'Cantidad mayor al stock disponible en: ' + invalid.map((p) => p.name).join(', ');
                    warnEl.classList.remove('hidden');
                    return false;
                }
                warnEl.classList.add('hidden');
                return true;
            }

            function renderProducts() {
                if (!productsGrid) return;
                const list = filteredProducts();
                productsGrid.innerHTML = '';

                if (!list.length) {
                    productsGrid.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">No se encontraron productos para el filtro actual.</div>';
                    return;
                }

                list.forEach((product) => {
                    const stock = Number(product.currentStock ?? product.stock ?? 0);
                    const hasStock = stock > 0;
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'group relative overflow-hidden border bg-white text-center transition-all duration-200';
                    card.style.borderRadius = '30px';
                    card.style.borderColor = '#e4e9f1';
                    card.style.borderWidth = '1px';
                    card.style.borderStyle = 'solid';
                    card.style.backgroundColor = '#ffffff';
                    card.style.boxShadow = '0 10px 24px rgba(15,23,42,.05)';
                    card.style.height = '190px';
                    card.style.minHeight = '190px';
                    if (!hasStock) {
                        card.style.opacity = '0.6';
                        card.style.cursor = 'not-allowed';
                    }
                    if (hasStock) {
                        card.addEventListener('mouseenter', () => {
                            card.style.transform = 'translateY(-4px)';
                            card.style.borderColor = '#ffd1a4';
                            card.style.boxShadow = '0 18px 34px rgba(249,115,22,.12)';
                            card.style.backgroundColor = '#fffdfb';
                            const orb = card.querySelector('[data-role=product-orb]');
                            if (orb) {
                                orb.style.transform = 'translateY(-1px) scale(1.03)';
                                orb.style.boxShadow = '0 18px 30px rgba(249,115,22,.12), 0 8px 16px rgba(15,23,42,.06)';
                            }
                        });
                        card.addEventListener('mouseleave', () => {
                            card.style.transform = '';
                            card.style.borderColor = '#e4e9f1';
                            card.style.boxShadow = '0 10px 24px rgba(15,23,42,.05)';
                            card.style.backgroundColor = '#ffffff';
                            const orb = card.querySelector('[data-role=product-orb]');
                            if (orb) {
                                orb.style.transform = '';
                                orb.style.boxShadow = '0 12px 24px rgba(249,115,22,.08), 0 6px 14px rgba(15,23,42,.04)';
                            }
                        });
                        card.addEventListener('click', () => addToCart(product.id));
                    }

                    const hasImage = !!product.img;
                    const stockBadgeClass = hasStock ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-600';

                    card.innerHTML = `
                        <div class="relative flex h-full w-full flex-col items-center px-3 pb-4 pt-4">
                            <div class="absolute right-3 top-4 z-20 inline-flex min-w-[78px] items-center justify-center rounded-full border ${stockBadgeClass} px-3 py-1.5 text-center text-[12px] font-bold leading-none" style="box-shadow:0 6px 14px rgba(15,23,42,.08);">
                                Stock: ${stock.toFixed(0)}
                            </div>
                            <div class="flex h-[102px] w-full items-center justify-center pt-2">
                                <div data-role="product-orb" class="mx-auto flex h-[92px] w-[92px] items-center justify-center overflow-hidden rounded-full bg-white transition-transform duration-200" style="box-shadow:0 12px 24px rgba(249,115,22,.08), 0 6px 14px rgba(15,23,42,.04);">
                                    ${hasImage
                                        ? `<img src="${getImageUrl(product.img)}" alt="${(product.name || 'Producto').replace(/"/g, '&quot;')}" class="h-16 w-16 object-contain" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">`
                                        : `<i class="ri-shopping-bag-3-line text-[30px] text-orange-500"></i>`}
                                </div>
                            </div>
                            <div class="mt-2 flex h-[50px] w-full items-start justify-center px-1">
                                <h4 class="line-clamp-2 block w-full text-center text-[12px] font-black leading-[1.28] text-slate-900">${(product.name || 'Sin nombre').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</h4>
                            </div>
                        </div>
                    `;

                    productsGrid.appendChild(card);
                });
            }

            function renderCartSummary() {
                const totalProducts = state.cart.length;
                const totalQuantity = state.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                const elProducts = document.getElementById('output-total-products');
                const elQuantity = document.getElementById('output-total-quantity');
                if (elProducts) elProducts.textContent = String(totalProducts);
                if (elQuantity) elQuantity.textContent = String(totalQuantity);
            }

            function renderCart() {
                if (!cartContainer) return;
                checkStockValidation();

                if (!state.cart.length) {
                    cartContainer.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center"><div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm"><i class="ri-inbox-line text-3xl"></i></div><p class="mt-4 text-base font-bold text-slate-800">Sin productos en la salida</p><p class="mt-1 text-sm text-slate-500">Agrega productos desde el catálogo (solo con stock).</p></div>';
                    renderCartSummary();
                    return;
                }

                cartContainer.innerHTML = '';
                state.cart.forEach((item) => {
                    const overStock = (item.quantity || 0) > (item.currentStock || 0);
                    const row = document.createElement('div');
                    row.className = 'mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm' + (overStock ? ' border-red-300 bg-red-50' : '');
                    row.innerHTML = `
                        <div class="p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h5 class="truncate text-sm font-bold text-slate-900">${(item.name || 'Producto').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</h5>
                                    <p class="mt-1 text-xs text-slate-500">Stock máx: ${item.currentStock || 0} ${item.unit || 'Unidad'} ${overStock ? '<span class="text-red-600">(cantidad > stock)</span>' : ''}</p>
                                </div>
                                <div class="inline-flex shrink-0 items-center rounded-xl border border-slate-200 bg-slate-50">
                                    <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-rose-600" data-role="minus"><i class="ri-subtract-line"></i></button>
                                    <span class="h-8 min-w-[2.5rem] flex items-center justify-center border-x border-slate-200 bg-white text-center text-sm font-bold text-slate-900">${Math.max(0, Math.min(item.currentStock || 0, Math.floor(Number(item.quantity) || 0)))}</span>
                                    <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-orange-600" data-role="plus"><i class="ri-add-line"></i></button>
                                </div>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar" data-role="remove">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    row.querySelector('[data-role=minus]')?.addEventListener('click', () => updateQuantity(item.id, -1));
                    row.querySelector('[data-role=plus]')?.addEventListener('click', () => updateQuantity(item.id, 1));
                    row.querySelector('[data-role=remove]')?.addEventListener('click', () => removeItem(item.id));
                    cartContainer.appendChild(row);
                });

                renderCartSummary();
            }

            function isTransferMode() {
                return !!document.getElementById('is-transfer')?.checked;
            }

            function refreshTransferUi() {
                const transferMode = isTransferMode();
                document.getElementById('transfer-target-wrapper')?.classList.toggle('hidden', !transferMode);
                const saveText = document.getElementById('save-output-text');
                if (saveText) saveText.textContent = transferMode ? 'Guardar transferencia' : 'Guardar salida';
            }

            async function saveOutput() {
                if (!state.cart.length) {
                    alert('Por favor, selecciona al menos un producto.');
                    return;
                }
                if (!checkStockValidation()) {
                    alert('Hay productos con cantidad mayor al stock disponible. Ajusta las cantidades.');
                    return;
                }

                const comment = (document.getElementById('movement-comment')?.value || '').trim();
                const payload = {
                    items: state.cart.map((p) => ({
                        product_id: Number(p.id),
                        quantity: Number(p.quantity || 0),
                        comment: '',
                    })),
                    comment: comment || 'Salida de productos del almacen',
                };
                if (!editingMovement) payload.branch_id = branchId;

                const transferMode = !editingMovement && isTransferMode();
                if (transferMode) {
                    const toBranchId = parseInt(document.getElementById('to-branch-id')?.value || '0', 10);
                    if (!toBranchId) {
                        alert('Selecciona la sucursal destino para transferir.');
                        return;
                    }
                    if (Number(branchId) === toBranchId) {
                        alert('La sucursal destino no puede ser la misma sucursal actual.');
                        return;
                    }
                    payload.to_branch_id = toBranchId;
                }

                const saveButton = document.getElementById('save-output-button');
                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.classList.add('opacity-70', 'cursor-not-allowed');
                }

                const url = editingMovement
                    ? @json(route('warehouse_movements.output.update', ['warehouseMovement' => '__ID__'])).replace('__ID__', editingMovement.id)
                    : (transferMode ? @json(route('warehouse_movements.transfer.store')) : @json(route('warehouse_movements.output.store')));
                const method = editingMovement ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (response.ok && data.success) {
                        alert(editingMovement ? 'Salida actualizada correctamente' : (transferMode ? 'Transferencia guardada correctamente' : 'Salida de productos guardada correctamente'));
                        window.location.href = viewId ? `@json(route('warehouse_movements.index'))?view_id=${encodeURIComponent(String(viewId))}` : @json(route('warehouse_movements.index'));
                    } else {
                        alert('Error: ' + (data.message || (editingMovement ? 'No se pudo actualizar la salida' : 'No se pudo guardar la salida')));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert(editingMovement ? 'Error al actualizar la salida de productos' : 'Error al guardar la salida de productos');
                } finally {
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            }

            searchInput?.addEventListener('input', (event) => {
                const rawValue = String(event.target.value || '');
                state.search = rawValue;
                renderProducts();
                window.clearTimeout(searchTimer);
                if (state.search.trim() === '') return;
                searchTimer = window.setTimeout(() => tryAutoAddProductByCode(rawValue), 180);
            });
            searchInput?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                window.clearTimeout(searchTimer);
                tryAutoAddProductByCode(event.target.value || '');
            });
            document.getElementById('save-output-button')?.addEventListener('click', saveOutput);
            document.getElementById('clear-output-button')?.addEventListener('click', () => {
                state.cart = [];
                renderCart();
            });
            document.getElementById('is-transfer')?.addEventListener('change', refreshTransferUi);

            renderCategoryFilters();
            renderProducts();
            renderCart();
            refreshTransferUi();

            if (editingMovement) {
                const commentEl = document.getElementById('movement-comment');
                if (commentEl) commentEl.value = @json($editingComment ?? '');
                const saveTextEl = document.getElementById('save-output-text');
                if (saveTextEl) saveTextEl.textContent = 'Actualizar salida';
                const transferWrap = document.querySelector('[id="is-transfer"]')?.closest('.rounded-2xl');
                if (transferWrap) transferWrap.classList.add('hidden');
            }
        })();
    </script>

    <style>
        #warehouse-output-view input:focus,
        #warehouse-output-view select:focus,
        #warehouse-output-view textarea:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.16) !important;
            border-color: #f97316 !important;
        }

        #warehouse-output-view input:focus-visible,
        #warehouse-output-view select:focus-visible,
        #warehouse-output-view textarea:focus-visible {
            outline: none !important;
        }

        #warehouse-output-view #products-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 0.95rem !important;
        }

        @media (max-width: 1199px) {
            #warehouse-output-view #products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 991px) {
            #warehouse-output-view #products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 1279px) {
            #warehouse-output-view .flex.items-start.gap-6[style*="display:flex"] {
                flex-direction: column !important;
                gap: 1rem !important;
            }

            #warehouse-output-view .flex.items-start.gap-6[style*="display:flex"] > section,
            #warehouse-output-view .flex.items-start.gap-6[style*="display:flex"] > aside {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            #warehouse-output-view aside .sticky {
                position: static !important;
                top: auto !important;
            }
        }

        @media (max-width: 767px) {
            #warehouse-output-view #products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.65rem !important;
            }

            #warehouse-output-view #output-cart-container {
                max-height: 42vh !important;
            }
        }

        @media (min-width: 1536px) {
            #warehouse-output-view #products-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }
        }
    </style>
@endsection
