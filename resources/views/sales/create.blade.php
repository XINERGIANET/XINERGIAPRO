@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $salesIndexUrl = route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []);
        $salesChargeUrl = route('admin.sales.charge', $viewId ? ['view_id' => $viewId] : []);
    @endphp
    {{-- Breadcrumb --}}
    <div class=" flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 dark:text-gray-400"><i class="ri-restaurant-fill"></i></span>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                Punto de Venta
            </h2>
        </div>
        <nav>
            <ol class="flex items-center gap-1.5">
                <li>
                    <a class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                        href="{{ $salesIndexUrl }}">
                        Ventas
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                <li class="text-sm text-gray-800 dark:text-white/90">
                    Nueva Venta
                </li>
            </ol>
        </nav>
    </div>

    {{-- Contenedor Principal - Full Width con fondo --}}
    <div class="-mx-4 md:-mx-6 -mb-4 md:-mb-6">
        <div class="flex items-start w-full dark:bg-slate-950 fade-in min-h-[calc(100vh-180px)]" style="--brand:#3B82F6;">

            {{-- ================= SECCIÓN IZQUIERDA: MENÚ ================= --}}
            <main class="flex-1 p-4 flex flex-col min-w-0">

             

                {{-- Grid de Productos --}}
                <div class="p-6  dark:bg-slate-900 min-h-[calc(100vh-260px)]">
                   
                    <div id="category-filters" class="mb-5 flex flex-wrap gap-2"></div>
                    <div id="products-grid"
                        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-5 gap-3 w-full">
                        {{-- JS llenará esto --}}
                    </div>
                </div>
            </main>

            {{-- ================= SECCIÓN DERECHA: CARRITO (STICKY) ================= --}}
            <aside
                class="w-[420px] dark:bg-slate-900 backdrop-blur-sm border-l border-gray-300 dark:border-slate-800 flex flex-col shadow-2xl z-20 shrink-0 sticky top-0 h-screen">

                {{-- Header Carrito --}}
                <div
                    class="h-14 px-4 border-b border-gray-200 dark:border-slate-800 dark:bg-slate-900/80 backdrop-blur-sm flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Orden Actual</h3>
                        <span id="cart-count-badge"
                            class="inline-block px-2 py-0.5 bg-blue-600 dark:bg-blue-500 text-white rounded-full text-[10px] font-bold shadow-lg shadow-blue-500/30">
                            0
                        </span>
                    </div>
                    <span
                        class="px-2.5 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 rounded-full text-[10px] font-bold border border-blue-100 dark:border-blue-800">En
                        curso</span>
                </div>

                {{-- Lista Items --}}
                <div id="cart-container" class="flex-1 overflow-y-auto p-3 dark:bg-slate-900"></div>

                {{-- Footer Totales --}}
                <div
                    class="p-4 dark:bg-slate-950/90 backdrop-blur-sm border-t border-gray-300 dark:border-slate-800 shadow-[0_-5px_25px_rgba(0,0,0,0.05)] dark:shadow-[0_-5px_25px_rgba(0,0,0,0.3)] shrink-0 z-30">
                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex justify-between text-gray-500 dark:text-gray-400 font-medium text-xs">
                            <span>Subtotal</span>
                            <span class="text-slate-800 dark:text-white" id="ticket-subtotal">$0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-500 dark:text-gray-400 font-medium text-xs">
                            <span>IGV</span>
                            <span class="text-slate-800 dark:text-white" id="ticket-tax">$0.00</span>
                        </div>
                        <div class="border-t border-dashed border-gray-300 dark:border-slate-700 my-1.5"></div>
                        <div class="flex justify-between items-center">
                            <span class="text-base font-bold text-slate-800 dark:text-white">Total a Pagar</span>
                            <span class="text-2xl font-black text-blue-600 dark:text-blue-400"
                                id="ticket-total">$0.00</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="goBack()"
                            class="hidden py-2.5 rounded-lg border border-gray-300 dark:border-slate-700 dark:bg-slate-800 text-gray-700 dark:text-white text-sm font-bold hover:bg-gray-50 dark:hover:bg-slate-700 shadow-sm transition-all">
                            Guardar
                        </button>
                        <button type="button" id="checkout-button" onclick="goToChargeView()"
                            class="py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold shadow-lg shadow-blue-500/30 dark:shadow-blue-500/20 hover:bg-blue-700 dark:hover:bg-blue-600 active:scale-95 transition-all flex justify-center items-center gap-2">
                            <span>Cobrar</span> <i class="fas fa-cash-register text-xs"></i>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Notificación de stock insuficiente --}}
    <div id="stock-error-notification"
        class="fixed top-24 right-8 z-50 transform transition-all duration-500 translate-x-[150%] opacity-0 pointer-events-none">
        <div class="bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-4 rounded-xl shadow-2xl border border-red-400/30 backdrop-blur-sm flex items-center gap-4 min-w-[320px]">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-sm">Stock insuficiente</p>
                <p id="stock-error-message" class="text-xs text-red-50 mt-0.5">Solo hay X disponible(s)</p>
            </div>
            <button onclick="hideStockError()" class="text-white/80 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    {{-- Notificación de producto agregado --}}
    <div id="add-to-cart-notification"
        class="fixed top-24 right-8 z-50 transform transition-all duration-500 translate-x-[150%] opacity-0">
        <div
            class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-4 rounded-xl shadow-2xl border border-green-400/30 backdrop-blur-sm flex items-center gap-4 min-w-[320px]">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center animate-bounce">
                <i class="fas fa-check text-2xl"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-sm">¡Producto agregado!</p>
                <p id="notification-product-name" class="text-xs text-green-50 mt-0.5">Producto</p>
            </div>
            <button onclick="hideNotification()" class="text-white/80 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <style>
        @keyframes slideInFromLeft {
            from {
                transform: translateX(-30px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes pulse-subtle {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }
        }

        .cart-item-enter {
            animation: slideInFromLeft 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .product-click-effect {
            animation: pulse-subtle 0.3s ease-out;
        }

        .shake-animation {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px) rotate(-2deg);
            }

            75% {
                transform: translateX(5px) rotate(2deg);
            }
        }

        .notification-show {
            transform: translateX(0) !important;
            opacity: 1 !important;
        }

        .qty-badge-pop {
            animation: popScale 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes popScale {
            0% {
                transform: scale(0.8);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .pulse-button {
            animation: pulse-glow 2s infinite;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            }

            50% {
                box-shadow: 0 0 30px rgba(59, 130, 246, 0.6);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>

    <script>
    (function () {
        const productsRaw = @json($products ?? []);
        const productBranchesRaw = @json($productBranches ?? $productsBranches ?? []);

        const products = Array.isArray(productsRaw) ? productsRaw : Object.values(productsRaw || {});
        const productBranches = Array.isArray(productBranchesRaw) ? productBranchesRaw : Object.values(productBranchesRaw || {});

        const priceByProductId = new Map();
        const taxRateByProductId = new Map();
        const stockByProductId = new Map();
        const defaultTaxPct = 18;
        productBranches.forEach((pb) => {
            const pid = Number(pb.product_id ?? pb.id);
            if (!Number.isNaN(pid)) {
                priceByProductId.set(pid, Number(pb.price ?? 0));
                taxRateByProductId.set(pid, pb.tax_rate != null ? Number(pb.tax_rate) : defaultTaxPct);
                stockByProductId.set(pid, Number(pb.stock ?? 0) || 0);
            }
        });
        let selectedCategory = 'General';

        function getProductCategory(prod) {
            const value = (prod && prod.category) ? String(prod.category).trim() : '';
            return value !== '' ? value : 'Sin categoria';
        }

        function getCategories() {
            const unique = new Set();
            products.forEach((prod) => unique.add(getProductCategory(prod)));
            return ['General', ...Array.from(unique).sort((a, b) => a.localeCompare(b))];
        }

        const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
        let db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
        let activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);

        if (!activeKey || !db[activeKey] || db[activeKey]?.status === 'completed') {
            activeKey = `sale-${Date.now()}`;
            localStorage.setItem(ACTIVE_SALE_KEY_STORAGE, activeKey);
        }

        let currentSale = db[activeKey] || {
            id: Date.now(),
            clientName: 'Publico General',
            status: 'in_progress',
            items: [],
        };

        db[activeKey] = currentSale;
        localStorage.setItem('restaurantDB', JSON.stringify(db));

        function getImageUrl(imgUrl) {
            if (imgUrl && String(imgUrl).trim() !== '') return imgUrl;
            return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cmVjdCBmaWxsPSIjZTdlOWViIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5Y2EzYWYiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
        }

        function saveDB() {
            db[activeKey] = currentSale;
            localStorage.setItem('restaurantDB', JSON.stringify(db));
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            if (!grid) return;
            grid.innerHTML = '';

            let rendered = 0;

            products.forEach((prod) => {
                const productId = Number(prod.id);
                const price = priceByProductId.get(productId);
                const category = getProductCategory(prod);

                // Mostrar solo productos asignados a la sucursal actual
                if (typeof price === 'undefined') return;
                if (selectedCategory !== 'General' && category !== selectedCategory) return;

                const el = document.createElement('div');
                el.className = 'group cursor-pointer transition-transform duration-200 hover:scale-105';
                el.addEventListener('click', function () {
                    addToCart(prod, price);
                });

                const safeName = prod.name || 'Sin nombre';
                const safeCategory = category;

                el.innerHTML = `
                    <div class="rounded-lg overflow-hidden p-3 dark:bg-slate-800/40 shadow-md hover:shadow-xl border border-gray-300 dark:border-slate-700/50 hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-blue-500/10 transition-all duration-200 hover:-translate-y-1 backdrop-blur-sm">
                        <div class="relative aspect-square overflow-hidden dark:bg-slate-700/30 rounded-lg border border-gray-300 dark:border-slate-600/30 shadow-sm">
                            <img src="${getImageUrl(prod.img)}" alt="${safeName}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" loading="lazy" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">
                            <span class="absolute top-3 right-3 z-10">
                                <span class="px-2.5 py-1 bg-blue-600 dark:bg-blue-500 rounded-lg text-sm font-bold shadow-lg shadow-blue-500/40 dark:shadow-blue-500/20 backdrop-blur-sm border border-blue-400/50 dark:border-blue-400/30 text-white">
                                    $${Number(price).toFixed(2)}
                                </span>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-col gap-1">
                            <h4 class="font-semibold text-gray-900 dark:text-white text-sm line-clamp-2 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">${safeName}</h4>
                            <h6 class="text-xs text-gray-600 dark:text-gray-400">${safeCategory}</h6>
                        </div>
                    </div>
                `;

                grid.appendChild(el);
                rendered++;
            });

            if (rendered === 0) {
                grid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles para esta sucursal.</div>';
            }
        }

        function renderCategoryFilters() {
            const container = document.getElementById('category-filters');
            const label = document.getElementById('selected-category-label');
            if (!container) return;

            container.innerHTML = '';
            const categories = getCategories();

            categories.forEach((category) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'px-3 py-1.5 rounded-lg border text-xs font-semibold transition';
                const isActive = category === selectedCategory;

                if (isActive) {
                    button.className += ' bg-blue-600 text-white border-blue-600';
                } else {
                    button.className += ' bg-white text-slate-700 border-gray-300 hover:border-blue-400 hover:text-blue-600';
                }

                button.textContent = category;
                button.addEventListener('click', () => {
                    selectedCategory = category;
                    if (label) label.textContent = selectedCategory;
                    renderCategoryFilters();
                    renderProducts();
                });

                container.appendChild(button);
            });

            if (label) label.textContent = selectedCategory;
        }

        function addToCart(prod, price) {
            const productId = Number(prod.id);
            if (Number.isNaN(productId)) return;

            if (!Array.isArray(currentSale.items)) currentSale.items = [];

            const stock = stockByProductId.get(productId) ?? 0;
            const existing = currentSale.items.find((i) => Number(i.pId) === productId);
            const qtyToAdd = existing ? existing.qty + 1 : 1;

            if (qtyToAdd > stock) {
                showStockError(prod.name || 'Producto', stock);
                return;
            }

            if (existing) {
                existing.qty += 1;
            } else {
                currentSale.items.push({
                    pId: productId,
                    name: prod.name || '',
                    qty: 1,
                    price: Number(price) || 0,
                    note: '',
                });
            }

            saveDB();
            renderTicket();
            showNotification(prod.name || 'Producto');
        }

        function updateQty(index, delta) {
            if (!currentSale.items[index]) return;
            currentSale.items[index].qty += delta;
            if (currentSale.items[index].qty <= 0) currentSale.items.splice(index, 1);
            saveDB();
            renderTicket();
        }

        function toggleNoteInput(index) {
            const box = document.getElementById(`note-box-${index}`);
            if (box) box.classList.toggle('hidden');
        }

        function saveNote(index, value) {
            if (!currentSale.items[index]) return;
            currentSale.items[index].note = value;
            saveDB();
        }

        function renderTicket() {
            const container = document.getElementById('cart-container');
            if (!container) return;

            container.innerHTML = '';
            let subtotal = 0;

            if (!currentSale.items || currentSale.items.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-48 text-gray-300 dark:text-gray-600 opacity-70">
                        <div class="w-16 h-16 rounded-full dark:bg-slate-800 flex items-center justify-center mb-3">
                            <i class="fas fa-shopping-cart text-2xl dark:text-gray-600"></i>
                        </div>
                        <p class="font-semibold text-sm text-gray-500 dark:text-gray-500">Sin productos</p>
                        <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Selecciona productos del menu</p>
                    </div>
                `;
            } else {
                currentSale.items.forEach((item, index) => {
                    const prod = products.find((p) => Number(p.id) === Number(item.pId));
                    if (!prod) return;

                    const itemPrice = Number(item.price) || 0;
                    const itemQty = Number(item.qty) || 0;
                    const itemTotal = itemPrice * itemQty;
                    subtotal += itemTotal;

                    const hasNote = !!(item.note && String(item.note).trim() !== '');

                    const row = document.createElement('div');
                    row.className = 'py-2 px-2 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700/50 rounded-lg p-1.5 shadow-sm mb-2';
                    row.innerHTML = `
                        <div class="flex gap-2 pl-2">
                            <div class="relative shrink-0">
                                <img src="${getImageUrl(prod.img)}" alt="${prod.name}" class="h-12 w-12 rounded-lg object-cover bg-gray-100 dark:bg-slate-700 ring-1 ring-gray-200/50 dark:ring-slate-600/50 shadow-sm">
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <h5 class="font-semibold text-slate-900 dark:text-white text-xs truncate leading-tight">${prod.name}</h5>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">$${itemPrice.toFixed(2)} c/u</p>
                                    </div>
                                    <div class="shrink-0">
                                        <span class="font-bold text-blue-600 dark:text-blue-400 text-sm">$${itemTotal.toFixed(2)}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <button onclick="toggleNoteInput(${index})" class="text-[10px] flex items-center gap-1 ${hasNote ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'}">
                                        <i class="fas fa-sticky-note text-[8px]"></i> ${hasNote ? 'Nota' : '+ Nota'}
                                    </button>
                                    <div class="flex items-center gap-0.5 dark:bg-slate-700 rounded border border-gray-200 dark:border-slate-600 shadow-sm">
                                        <button onclick="updateQty(${index}, -1)" class="w-6 h-6 flex items-center justify-center text-gray-700 dark:text-white hover:text-red-600"><i class="ri-subtract-line text-xs"></i></button>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white w-6 text-center">${itemQty}</span>
                                        <button onclick="updateQty(${index}, 1)" class="w-6 h-6 flex items-center justify-center text-gray-700 dark:text-white hover:text-blue-600"><i class="ri-add-line text-xs"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="note-box-${index}" class="${hasNote ? '' : 'hidden'} mt-1.5 ml-2 mr-2">
                            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20 border-l-2 border-amber-400 dark:border-amber-500 rounded p-1.5">
                                <input type="text" value="${(item.note || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}" oninput="saveNote(${index}, this.value)" placeholder="Ej: Sin cebolla, extra queso..." class="w-full text-[10px] bg-transparent border-none text-slate-700 dark:text-gray-200 focus:outline-none placeholder-gray-400 dark:placeholder-gray-500 font-medium">
                            </div>
                        </div>
                    `;
                    container.appendChild(row);
                });
            }

            // Los precios ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa (del sistema).
            let subtotalBase = 0;
            let tax = 0;
            (currentSale.items || []).forEach((item) => {
                const itemTotal = (Number(item.price) || 0) * (Number(item.qty) || 0);
                const taxPct = taxRateByProductId.get(Number(item.pId)) ?? defaultTaxPct;
                const taxVal = taxPct / 100;
                const itemSubtotal = taxVal > 0 ? itemTotal / (1 + taxVal) : itemTotal;
                subtotalBase += itemSubtotal;
                tax += itemTotal - itemSubtotal;
            });
            const total = subtotalBase + tax;
            document.getElementById('ticket-subtotal').innerText = `$${subtotalBase.toFixed(2)}`;
            document.getElementById('ticket-tax').innerText = `$${tax.toFixed(2)}`;
            document.getElementById('ticket-total').innerText = `$${total.toFixed(2)}`;

            const cartCountBadge = document.getElementById('cart-count-badge');
            const totalItems = (currentSale.items || []).reduce((sum, item) => sum + (Number(item.qty) || 0), 0);
            if (cartCountBadge) cartCountBadge.textContent = String(totalItems);
        }

        function goToChargeView() {
            if (!currentSale.items || currentSale.items.length === 0) {
                showEmptyCartNotification();
                return;
            }
            saveDB();
            window.location.href = @json($salesChargeUrl);
        }

        function goBack() {
            if (!currentSale.items || currentSale.items.length === 0) {
                currentSale.items = [];
                currentSale.total = 0;
                saveDB();
                localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
                window.location.href = @json($salesIndexUrl);
                return;
            }

            const saleData = {
                items: currentSale.items.map(item => ({
                    pId: item.pId,
                    qty: Number(item.qty),
                    price: Number(item.price),
                    note: item.note || ''
                })),
                notes: 'Venta guardada como borrador - pendiente de pago'
            };

            fetch(@json(route('admin.sales.draft')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(saleData)
            })
            .finally(() => {
                window.location.href = @json($salesIndexUrl);
            });
        }

        function showStockError(productName, stock) {
            const notification = document.getElementById('stock-error-notification');
            const msgEl = document.getElementById('stock-error-message');
            if (!notification || !msgEl) return;
            msgEl.textContent = (productName || 'Producto') + ': solo hay ' + stock + ' disponible(s).';
            notification.classList.add('notification-show');
            notification.classList.remove('pointer-events-none');
            setTimeout(hideStockError, 3500);
        }

        function hideStockError() {
            const notification = document.getElementById('stock-error-notification');
            if (notification) {
                notification.classList.remove('notification-show');
                notification.classList.add('pointer-events-none');
            }
        }

        function showNotification(productName) {
            const notification = document.getElementById('add-to-cart-notification');
            const productNameEl = document.getElementById('notification-product-name');
            if (!notification || !productNameEl) return;
            productNameEl.textContent = productName;
            notification.classList.add('notification-show');
            setTimeout(hideNotification, 1600);
        }

        function hideNotification() {
            const notification = document.getElementById('add-to-cart-notification');
            if (!notification) return;
            notification.classList.remove('notification-show');
        }

        init();

        function init() {
            const clientNameEl = document.getElementById('pos-client-name');
            if (clientNameEl) clientNameEl.innerText = currentSale.clientName || 'Publico General';
            renderCategoryFilters();
            renderProducts();
            renderTicket();
        }

        window.goBack = goBack;
        window.getImageUrl = getImageUrl;
        window.updateQty = updateQty;
        window.toggleNoteInput = toggleNoteInput;
        window.saveNote = saveNote;
        window.goToChargeView = goToChargeView;
        window.hideNotification = hideNotification;
    })();
</script>
@endsection
