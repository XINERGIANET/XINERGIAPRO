@extends('layouts.app')

@section('title', 'Entrada de Productos')

@section('content')
    @php
        $viewId = request('view_id');
        $warehouseIndexUrl = route('warehouse_movements.index', $viewId ? ['view_id' => $viewId] : []);
        $branchId = session('branch_id');

        $productsMapped = $products->map(function ($product) use ($productBranches) {
            $productBranch = $productBranches->get($product->id);
            $imageUrl = null;

            if ($product->image && !empty(trim($product->image))) {
                $imagePath = trim($product->image);
                if (strpos($imagePath, '\\') === false && strpos($imagePath, 'C:') === false && strpos($imagePath, 'Temp') === false && strpos($imagePath, 'Windows') === false) {
                    if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                        $imageUrl = $imagePath;
                    } elseif (str_starts_with($imagePath, 'storage/')) {
                        $imageUrl = asset($imagePath);
                    } elseif (str_starts_with($imagePath, '/storage/')) {
                        $imageUrl = asset(ltrim($imagePath, '/'));
                    } else {
                        $imageUrl = asset('storage/' . $imagePath);
                    }
                }
            }

            return [
                'id' => $product->id,
                'code' => $product->code ?? '',
                'name' => $product->description ?? 'Sin nombre',
                'img' => $imageUrl,
                'category' => $product->category ? $product->category->description : 'General',
                'unit' => $product->baseUnit ? $product->baseUnit->description : 'Unidad',
                'stock' => (float) ($productBranch->stock ?? 0),
            ];
        })->values();
    @endphp

    <div id="warehouse-entry-view">
        <x-common.page-breadcrumb pageTitle="Entrada de productos" />

        <x-common.component-card
            title="Almacén | Entrada"
            desc="Registra entradas con el mismo flujo visual del POS."
        >
            <div class="flex items-start gap-6" style="display:flex;align-items:flex-start;gap:1.5rem;">
                <section class="min-w-0 space-y-5" style="flex:0 0 60%;max-width:60%;width:60%;">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="relative flex-1">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="ri-search-line text-lg"></i>
                                </span>
                                <input
                                    id="product-search"
                                    type="text"
                                    placeholder="Buscar por nombre, código o categoría"
                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"
                                >
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $warehouseIndexUrl }}" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="ri-arrow-left-line"></i>
                                    <span>Volver</span>
                                </a>
                                <button type="button" id="clear-entry-button" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    <i class="ri-delete-bin-6-line"></i>
                                    <span>Limpiar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Catálogo</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">Productos</h3>
                            </div>
                            <div id="category-filters" class="flex flex-wrap gap-2"></div>
                        </div>
                        <div id="products-grid" class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"></div>
                    </div>
                </section>

                <aside class="min-w-0" style="flex:0 0 40%;max-width:40%;width:40%;">
                    <div class="sticky top-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-800 bg-slate-900 px-4 py-3 text-white" style="background-color:#334155;">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300">Entrada</p>
                                    <h3 class="mt-0.5 text-xl font-bold">Resumen</h3>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-bold text-white" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);">
                                    En curso
                                </span>
                            </div>
                        </div>

                        <div class="max-h-[42vh] overflow-y-auto p-4" id="entry-cart-container"></div>

                        <div class="border-t border-slate-200 bg-slate-50 p-5 space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="grid grid-cols-1 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Motivo de entrada</label>
                                        <select id="movement-reason" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <option value="AJUSTE DE ENTRADA">Ajuste de entrada</option>
                                            <option value="DEVOLUCION INTERNA">Devolucion interna</option>
                                            <option value="REGULARIZACION DE STOCK">Regularizacion de stock</option>
                                            <option value="TRASLADO INTERNO">Traslado interno</option>
                                            <option value="OTRO">Otro</option>
                                        </select>
                                    </div>

                                    {{--
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tipo doc</label>
                                        <select id="purchase-document-kind" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <option value="FACTURA">FACTURA</option>
                                            <option value="BOLETA">BOLETA</option>
                                            <option value="RECIBO">RECIBO</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Moneda</label>
                                        <input id="purchase-currency" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700" value="PEN">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Serie</label>
                                        <input id="purchase-series" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700" placeholder="001">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Número</label>
                                        <input id="purchase-number" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700" placeholder="00000001">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">IGV %</label>
                                        <input id="purchase-igv-rate" type="number" min="0" max="100" step="0.0001" value="18" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold" style="color:#f97316;">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Fecha</label>
                                        <input id="purchase-issued-at" type="date" value="{{ now()->toDateString() }}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                    </div>
                                    --}}
                                </div>
                            </div>

                            <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex justify-between text-sm text-slate-500"><span>Items</span><span id="entry-total-products" class="font-semibold text-slate-700">0</span></div>
                                <div class="flex justify-between text-sm text-slate-500"><span>Cantidad</span><span id="entry-total-quantity" class="font-semibold text-slate-700">0</span></div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <label class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Comentario</label>
                                <textarea id="movement-comment" rows="2" placeholder="Detalle adicional de la entrada" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <a href="{{ $warehouseIndexUrl }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                    <i class="ri-close-line"></i><span>Cancelar</span>
                                </a>
                                <button type="button" id="save-entry-button" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,.24);">
                                    <i class="ri-save-line"></i><span>Guardar entrada</span>
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
            const products = Array.isArray(@json($productsMapped)) ? @json($productsMapped) : [];
            const branchId = Number(@json($branchId ?? 0)) || 0;
            const viewId = @json($viewId);

            const state = { selectedCategory: 'GENERAL', search: '', cart: [] };
            const categoryFilters = document.getElementById('category-filters');
            const productsGrid = document.getElementById('products-grid');
            const cartContainer = document.getElementById('entry-cart-container');
            const searchInput = document.getElementById('product-search');

            function getImageUrl(raw) {
                if (raw && String(raw).trim() !== '') return raw;
                return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cmVjdCBmaWxsPSIjZTdlOWViIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5Y2EzYWYiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
            }

            function categories() {
                const base = ['GENERAL'];
                products.forEach((product) => {
                    const name = String(product.category || 'GENERAL').trim().toUpperCase();
                    if (name && !base.includes(name)) base.push(name);
                });
                return base;
            }

            function filteredProducts() {
                const term = state.search.trim().toLowerCase();
                return products.filter((product) => {
                    const category = String(product.category || 'GENERAL').trim().toUpperCase();
                    const categoryOk = state.selectedCategory === 'GENERAL' || category === state.selectedCategory;
                    const searchOk = term === ''
                        || String(product.name || '').toLowerCase().includes(term)
                        || String(product.code || '').toLowerCase().includes(term)
                        || String(product.category || '').toLowerCase().includes(term);
                    return categoryOk && searchOk;
                });
            }

            function renderCategoryFilters() {
                if (!categoryFilters) return;
                categoryFilters.innerHTML = '';
                categories().forEach((category) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inline-flex h-12 items-center justify-center rounded-2xl border px-5 text-sm font-bold transition-all duration-200';
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
                if (!product) return;
                const existing = state.cart.find((item) => Number(item.id) === Number(product.id));
                if (existing) {
                    existing.quantity += 1;
                } else {
                    state.cart.push({
                        id: Number(product.id),
                        name: String(product.name || ''),
                        code: String(product.code || ''),
                        unit: String(product.unit || 'Unidad'),
                        img: product.img || null,
                        quantity: 1,
                    });
                }
                renderCart();
            }

            function updateQuantity(productId, diff) {
                const item = state.cart.find((entry) => Number(entry.id) === Number(productId));
                if (!item) return;
                item.quantity = Math.max(1, Number(item.quantity || 1) + diff);
                renderCart();
            }

            function setQuantity(productId, value) {
                const item = state.cart.find((entry) => Number(entry.id) === Number(productId));
                if (!item) return;
                const parsed = Math.floor(Number(value));
                item.quantity = Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
                renderCart();
            }

            function removeItem(productId) {
                state.cart = state.cart.filter((entry) => Number(entry.id) !== Number(productId));
                renderCart();
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
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'group relative overflow-hidden border bg-white text-center transition-all duration-200 hover:-translate-y-1';
                    card.style.borderRadius = '28px';
                    card.style.borderColor = '#dbe3ef';
                    card.style.borderWidth = '1px';
                    card.style.borderStyle = 'solid';
                    card.style.boxShadow = '0 10px 24px rgba(15,23,42,.06)';
                    card.addEventListener('mouseenter', () => {
                        card.style.transform = 'translateY(-4px)';
                        card.style.borderColor = '#fdba74';
                        card.style.boxShadow = '0 18px 34px rgba(249,115,22,.16)';
                        const orb = card.querySelector('[data-role=product-orb]');
                        if (orb) orb.style.transform = 'scale(1.04)';
                    });
                    card.addEventListener('mouseleave', () => {
                        card.style.transform = '';
                        card.style.borderColor = '#dbe3ef';
                        card.style.boxShadow = '0 10px 24px rgba(15,23,42,.06)';
                        const orb = card.querySelector('[data-role=product-orb]');
                        if (orb) orb.style.transform = '';
                    });
                    card.addEventListener('click', () => addToCart(product.id));

                    const stock = Number(product.stock || 0);
                    const hasImage = !!product.img;

                    card.innerHTML = `
                        <div class="relative px-3 pt-3">
                            <div class="absolute right-2 top-[2.9rem] z-20 rounded-full border px-1.5 py-0.5 text-[9px] font-bold leading-none ${stock > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-700'}" style="box-shadow:0 6px 14px rgba(15,23,42,.08);">
                                Stock: ${stock.toFixed(0)}
                            </div>
                            <div data-role="product-orb" class="mx-auto mt-2 flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gradient-to-b from-orange-400 to-orange-500 transition-transform duration-200" style="box-shadow:0 12px 24px rgba(249,115,22,.18);">
                                ${hasImage
                                    ? `<img src="${getImageUrl(product.img)}" alt="${product.name || 'Producto'}" class="h-14 w-14 object-cover" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">`
                                    : `<i class="ri-shopping-bag-3-line text-3xl text-white"></i>`}
                            </div>
                        </div>
                        <div class="px-3 pb-3 pt-2.5">
                            <h4 class="line-clamp-2 min-h-[40px] text-[14px] font-bold leading-5 text-slate-900">${product.name || 'Sin nombre'}</h4>
                        </div>
                    `;

                    productsGrid.appendChild(card);
                });
            }

            function renderCartSummary() {
                const totalProducts = state.cart.length;
                const totalQuantity = state.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);

                document.getElementById('entry-total-products').textContent = String(totalProducts);
                document.getElementById('entry-total-quantity').textContent = String(totalQuantity);
            }

            function renderCart() {
                if (!cartContainer) return;
                if (!state.cart.length) {
                    cartContainer.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center"><div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm"><i class="ri-inbox-line text-3xl"></i></div><p class="mt-4 text-base font-bold text-slate-800">Sin productos en la entrada</p><p class="mt-1 text-sm text-slate-500">Agrega productos desde el catálogo.</p></div>';
                    renderCartSummary();
                    return;
                }

                cartContainer.innerHTML = '';
                state.cart.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';
                    row.innerHTML = `
                        <div class="flex items-center gap-3 p-2.5">
                            <img src="${getImageUrl(item.img)}" alt="${item.name || 'Producto'}" class="h-12 w-12 shrink-0 rounded-xl object-cover bg-slate-100" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <h5 class="truncate text-sm font-bold text-slate-900">${item.name || 'Producto'}</h5>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-semibold text-slate-600">${item.unit || 'Unidad'}</span>
                                        </div>
                                    </div>
                                    <div class="inline-flex shrink-0 items-center rounded-xl border border-slate-200 bg-slate-50">
                                        <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-rose-600" data-role="minus"><i class="ri-subtract-line"></i></button>
                                        <input type="number" min="1" step="1" value="${Math.max(1, Math.floor(Number(item.quantity) || 1))}" class="h-8 w-12 border-x border-slate-200 bg-white text-center text-sm font-bold text-slate-900 outline-none" data-role="qty">
                                        <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-orange-600" data-role="plus"><i class="ri-add-line"></i></button>
                                    </div>
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar" data-role="remove">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    row.querySelector('[data-role=minus]')?.addEventListener('click', () => updateQuantity(item.id, -1));
                    row.querySelector('[data-role=plus]')?.addEventListener('click', () => updateQuantity(item.id, 1));
                    row.querySelector('[data-role=remove]')?.addEventListener('click', () => removeItem(item.id));
                    row.querySelector('[data-role=qty]')?.addEventListener('change', (event) => setQuantity(item.id, event.target.value));
                    cartContainer.appendChild(row);
                });

                renderCartSummary();
            }

            async function saveEntry() {
                if (!state.cart.length) {
                    alert('Agrega al menos un producto.');
                    return;
                }

                const payload = {
                    items: state.cart.map((item) => ({
                        product_id: Number(item.id),
                        quantity: Number(item.quantity || 0),
                        unit_cost: 0,
                        comment: '',
                    })),
                    reason: (document.getElementById('movement-reason')?.value || 'AJUSTE DE ENTRADA').trim(),
                    comment: (document.getElementById('movement-comment')?.value || '').trim(),
                    branch_id: branchId,
                    movement_type: 'ENTRY',
                };

                const saveButton = document.getElementById('save-entry-button');
                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.classList.add('opacity-70', 'cursor-not-allowed');
                }

                try {
                    const response = await fetch(@json(route('warehouse_movements.store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar la entrada.');
                    sessionStorage.setItem('flash_success_message', data.message || 'Entrada guardada correctamente');
                    window.location.href = viewId
                        ? `${@json(route('warehouse_movements.index'))}?view_id=${encodeURIComponent(String(viewId))}`
                        : @json(route('warehouse_movements.index'));
                } catch (error) {
                    alert(error.message || 'No se pudo guardar la entrada.');
                } finally {
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            }

            searchInput?.addEventListener('input', (event) => {
                state.search = String(event.target.value || '');
                renderProducts();
            });
            document.getElementById('save-entry-button')?.addEventListener('click', saveEntry);
            document.getElementById('clear-entry-button')?.addEventListener('click', () => {
                state.cart = [];
                renderCart();
            });

            renderCategoryFilters();
            renderProducts();
            renderCart();
        })();
    </script>

    <style>
        #warehouse-entry-view input:focus,
        #warehouse-entry-view select:focus,
        #warehouse-entry-view textarea:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.16) !important;
            border-color: #f97316 !important;
        }

        #warehouse-entry-view input:focus-visible,
        #warehouse-entry-view select:focus-visible,
        #warehouse-entry-view textarea:focus-visible {
            outline: none !important;
        }
    </style>
@endsection
