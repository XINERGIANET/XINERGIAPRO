@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 dark:text-gray-400"><i class="ri-restaurant-fill"></i></span>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                Salones de Pedidos
            </h2>
        </div>
        <nav>
            <ol class="flex items-center gap-1.5">
                <li>
                    <a class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        href="{{ url('/') }}">
                        Home
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        href="{{ route('admin.orders.index') }}">
                        crear orden
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                <li class="text-sm text-gray-800 dark:text-white/90">
                    Mesa {{ str_pad($table->name ?? $table->id, 2, '0', STR_PAD_LEFT) }} | Crear pedido
                </li>
            </ol>
        </nav>
    </div>
    {{-- 
        CAMBIO 1: Contenedor Principal
        - Quitamos 'h-[calc...]' y 'overflow-hidden'.
        - Usamos 'min-h-screen' y 'items-start' para permitir scroll natural.
    --}}
    <div class="flex items-stretch w-full bg-slate-100 fade-in h-full pb-10" style="--brand:#3B82F6;">

        {{-- ================= SECCIÓN IZQUIERDA: MENÚ (FLUJO NATURAL) ================= --}}
        {{-- CAMBIO 2: Quitamos 'h-full', 'overflow-y-auto' y 'relative' para que crezca con el contenido --}}
        <main class="flex-1 flex flex-col min-w-0">

            {{-- Header (Se mueve con la página) --}}
            <header class="h-20 px-6 flex items-center justify-between bg-white border-b border-gray-200 shadow-sm z-10">
                <div class="flex items-center gap-4">
                    <button onclick="goBack()"
                        class="h-10 w-10 rounded-lg bg-gray-50 border border-gray-200 text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-slate-800">
                                Mesa <span id="pos-table-name" class="text-blue-600">{{ $table->name ?? $table->id }}</span>
                            </h2>
                            <span id="pos-table-area"
                                class="text-[10px] font-bold px-2 py-0.5 bg-gray-100 text-gray-500 rounded uppercase tracking-wider border border-gray-200">--</span>
                        </div>
                        <div class="flex flex-col text-xs mt-0.5 text-gray-500 font-medium">
                            <div class="flex items-center">
                                <span class="inline-block w-14 text-gray-400">Mozo:</span>
                                <span id="pos-waiter-name"
                                    class="text-slate-700 font-semibold truncate max-w-[150px]">{{ $user?->name ?? 'Sin asignar' }}</span>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-block w-14 text-gray-400">Cliente:</span>
                                <span id="pos-client-name"
                                    class="text-slate-700 font-semibold truncate max-w-[150px]">{{ $person?->name ?? 'Sin cliente' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w-64 hidden md:block relative">
                    <input type="text" placeholder="Buscar..."
                        class="w-full pl-9 pr-4 py-2 bg-gray-100 border-transparent focus:bg-white focus:border-blue-500 focus:ring-0 rounded-lg text-sm transition-all">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                </div>
            </header>

            {{-- Grid de Productos (Crece hacia abajo infinitamente) --}}
            {{-- CAMBIO 3: Quitamos 'flex-1 overflow-y-auto' --}}
            <div class="p-6 bg-[#F3F4F6]">
                <h3 class="font-bold text-slate-700 mb-4 text-base">Categoría: General</h3>
                <div id="products-grid"
                    style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; width: 100%;">
                    {{-- JS llenará esto --}}
                </div>
            </div>
        </main>

        {{-- ================= SECCIÓN DERECHA: CARRITO (STICKY / PEGAJOSO) ================= --}}
        {{-- 
            CAMBIO 4: Configuración Sticky
            - 'sticky top-0': Hace que la barra se pegue al techo cuando haces scroll.
            - 'h-screen': Ocupa todo el alto de la ventana visible.
            - 'overflow-hidden': Para manejar su propio scroll interno si la lista de items es muy larga.
        --}}
        <aside
            class="w-[400px] max-w-[600px]
            flex-none
            h-full
            bg-white border-l border-gray-300
            flex flex-col shadow-2xl
            overflow-hidden">

            {{-- Header Carrito --}}
            <div class="h-16 px-6 border-b border-gray-200 bg-white flex justify-between items-center shrink-0 gap-2">
                <h3 class="text-xl font-bold text-slate-800">Orden Actual</h3>
                <button onclick="cancelOrder()"
                    class="py-2.5 px-3 rounded-lg border border-gray-300 bg-red-500 text-white font-semibold text-xs hover:bg-red-600 shadow-sm transition-all">
                    <i class="ri-close-line"></i>
                    Cancelar
                </button>
            </div>

            {{-- Lista Items (Scroll interno solo para el carrito) --}}
            <div id="cart-container" class="flex-1 overflow-y-auto p-5 space-y-3 bg-white"></div>

            {{-- Footer Totales --}}
            <div class="p-6 bg-slate-100 border-t border-gray-300 shadow-[0_-5px_25px_rgba(0,0,0,0.05)] shrink-0">
                <div class="space-y-3 mb-5 text-sm">
                    <div class="flex justify-between text-gray-500 font-medium">
                        <span>Subtotal</span>
                        <span class="text-slate-700" id="ticket-subtotal">$0.00</span>
                    </div>
                    <div class="flex justify-between text-gray-500 font-medium">
                        <span>Impuestos</span>
                        <span class="text-slate-700" id="ticket-tax">$0.00</span>
                    </div>
                    <div class="border-t border-dashed border-gray-300 my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-slate-800">Total a Pagar</span>
                        <span class="text-3xl font-black text-blue-600" id="ticket-total">$0.00</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="processOrder()"
                        class="py-1.5 rounded-xl border border-gray-300 bg-white text-gray-700 font-bold hover:bg-gray-50 shadow-sm transition-all">
                        Guardar
                    </button>
                    <button onclick="processOrderPayment()"
                        class="py-1.5 px-2 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 active:scale-95 transition-all flex justify-center items-center gap-2">
                        <span>Cobrar</span> <i class="fas fa-check-circle"></i>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    {{-- Notificación (éxito, error, etc.) --}}
    <div id="notification" class="fixed top-24 right-8 z-50 max-w-sm opacity-0 pointer-events-none transition-opacity duration-300" aria-live="polite"></div>

    {{-- SCRIPTS (envueltos en IIFE para evitar redeclaración al re-render Livewire) --}}
    <script>
        (function() {
            @php
                $serverTableData = [
                    'id' => $table->id,
                    'table_id' => $table->id,
                    'area_id' => $table->area_id ?? ($area->id ?? null),
                    'name' => $table->name ?? $table->id,
                    'waiter' => $user?->name ?? 'Sin asignar',
                    'clientName' => $person?->name ?? 'Sin cliente',
                    'status' => $table->situation ?? 'libre',
                    'items' => [],
                ];
            @endphp
            const serverTable = @json($serverTableData);

            let db = JSON.parse(localStorage.getItem('restaurantDB'));
            if (!db) db = {};
            let activeKey = `table-{{ $table->id }}`;
            // Si la mesa está libre en el servidor, no restaurar borrador local: así no se muestran
            // productos en una mesa que aún no se abrió (no se guardó pedido).
            const tableIsFree = (serverTable.status || '').toLowerCase() === 'libre';
            let currentTable = (!tableIsFree && db[activeKey]) ? db[activeKey] : serverTable;
            if (tableIsFree && db[activeKey]) {
                delete db[activeKey];
                localStorage.setItem('restaurantDB', JSON.stringify(db));
            }

            function init() {
                // Inicializar datos de la mesa
                if (document.getElementById('pos-table-name')) {
                    document.getElementById('pos-table-name').innerText = currentTable.name ||
                        "{{ str_pad($table->name ?? $table->id, 2, '0', STR_PAD_LEFT) }}";
                }
                if (document.getElementById('pos-table-area')) {
                    document.getElementById('pos-table-area').innerText = currentTable.area ||
                        "{{ $table->area?->name ?? ($area?->name ?? 'Sin área') }}";
                }
                if (document.getElementById('pos-waiter-name')) {
                    document.getElementById('pos-waiter-name').innerText = currentTable.waiter ||
                        "{{ $user?->name ?? 'Sin asignar' }}";
                }
                if (document.getElementById('pos-client-name')) {
                    document.getElementById('pos-client-name').innerText = currentTable.clientName ||
                        "{{ $person?->name ?? 'Sin cliente' }}";
                }
                refreshCartPricesFromServer();
                renderProducts();
                renderTicket();
            }

            // Función para escapar HTML y prevenir XSS
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Función para obtener la URL de la imagen
            function getImageUrl(imagePath) {
                if (!imagePath || imagePath === 'null' || imagePath === null || imagePath === '') {
                    // Retornar una imagen placeholder o una imagen por defecto
                    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23e5e7eb" width="200" height="200"/%3E%3Ctext fill="%239ca3af" font-family="sans-serif" font-size="14" dy="10.5" font-weight="bold" x="50%25" y="50%25" text-anchor="middle"%3ESin imagen%3C/text%3E%3C/svg%3E';
                }
                // Si ya es una URL completa, retornarla
                if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith(
                    'data:')) {
                    return imagePath;
                }
                // Si es una ruta relativa que empieza con /, retornarla tal cual
                if (imagePath.startsWith('/')) {
                    return imagePath;
                }
                // Si es una ruta de storage, ya viene con asset() desde el servidor
                return imagePath;
            }

            // Datos de productos y productBranches desde el servidor
            const serverProducts = @json($products ?? []);
            const serverProductBranches = @json($productBranches ?? []);

            function getItemTaxRatePercent(item) {
                const rate = parseFloat(item?.tax_rate);
                return !isNaN(rate) && rate >= 0 ? rate : 10;
            }

            // Los precios del POS incluyen IGV.
            function calculateTotalsFromItems(items) {
                let subtotal = 0;
                let tax = 0;
                let total = 0;

                (items || []).forEach(item => {
                    const qty = parseFloat(item.qty) || 0;
                    const price = parseFloat(item.price) || 0;
                    const lineTotal = qty * price;
                    const rate = getItemTaxRatePercent(item) / 100;
                    const lineSubtotal = rate > 0 ? (lineTotal / (1 + rate)) : lineTotal;
                    const lineTax = lineTotal - lineSubtotal;

                    subtotal += lineSubtotal;
                    tax += lineTax;
                    total += lineTotal;
                });

                return {
                    subtotal: Math.round(subtotal * 100) / 100,
                    tax: Math.round(tax * 100) / 100,
                    total: Math.round(total * 100) / 100,
                };
            }

            // Actualizar precios del carrito con los precios actuales del servidor
            function refreshCartPricesFromServer() {
                if (!currentTable?.items || !serverProductBranches?.length) return;
                let updated = false;
                currentTable.items.forEach(item => {
                    const pId = parseInt(item.pId || item.product_id, 10);
                    const pb = serverProductBranches.find(p => p.product_id === pId || parseInt(p.product_id, 10) === pId);
                    if (pb) {
                        const newPrice = parseFloat(pb.price);
                        if (!isNaN(newPrice) && newPrice >= 0 && newPrice !== parseFloat(item.price)) {
                            item.price = newPrice;
                            updated = true;
                        }
                        const newTaxRate = parseFloat(pb.tax_rate);
                        if (!isNaN(newTaxRate) && newTaxRate >= 0 && newTaxRate !== parseFloat(item.tax_rate)) {
                            item.tax_rate = newTaxRate;
                            updated = true;
                        }
                    }
                });
                if (updated) saveDB();
            }

            function renderProducts() {
                const grid = document.getElementById('products-grid');
                grid.innerHTML = '';

                if (!serverProducts || serverProducts.length === 0) {
                    grid.innerHTML =
                        '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles</div>';
                    return;
                }

                let productsRendered = 0;
                serverProducts.forEach(prod => {
                    const productBranch = serverProductBranches.find(p => p.product_id === prod.id || p.id === prod.id);
                    if (!productBranch) return;

                    const el = document.createElement('div');
                    el.className = "group cursor-pointer transition-transform duration-200 hover:scale-105";

                    // Prevenir múltiples clics rápidos
                    let isAdding = false;
                    el.onclick = function(e) {
                        e.preventDefault();

                        isAdding = true;
                        addToCart(prod, productBranch);

                        // Permitir agregar de nuevo después de un breve delay
                        setTimeout(() => {
                            isAdding = false;
                        }, 500);
                    };

                    const productName = escapeHtml(prod.name || 'Sin nombre');
                    const productCategory = escapeHtml(prod.category || 'Sin categoría');
                    const imageUrl = getImageUrl(prod.img);

                    el.innerHTML = `
            <div class="rounded-lg overflow-hidden p-3  dark:bg-slate-800/40 shadow-md hover:shadow-xl border border-gray-300 dark:border-slate-700/50 hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-blue-500/10 transition-all duration-200 hover:-translate-y-1 backdrop-blur-sm">
                <div class="relative aspect-square overflow-hidden  dark:bg-slate-700/30 rounded-lg border border-gray-300 dark:border-slate-600/30 shadow-sm">
                    <img src="${imageUrl}" 
                        alt="${productName}" 
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                        loading="lazy"
                        onerror="this.onerror=null; this.src=getImageUrl(null)">
                    
                    <span class="absolute top-3 right-3 z-10">
                        <span class="px-2.5 py-1 bg-blue-600 dark:bg-blue-500 rounded-lg text-sm font-bold shadow-lg shadow-blue-500/40 dark:shadow-blue-500/20 backdrop-blur-sm border border-blue-400/50 dark:border-blue-400/30 text-white">
                            $${parseFloat(productBranch.price).toFixed(2)}
                        </span>
                    </span>
                </div>
                
                <div class="mt-3 flex flex-col gap-1">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm line-clamp-2 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                        ${productName}
                    </h4>
                    <h6 class="text-xs text-gray-600 dark:text-gray-400">
                        ${productCategory}
                    </h6>
                </div>
            </div>
        `;
                    grid.appendChild(el);
                    productsRendered++;
                });

                if (productsRendered === 0) {
                    grid.innerHTML =
                        '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles para esta sucursal</div>';
                }

            }

            function addToCart(prod, productBranch) {
                if (!currentTable.items) currentTable.items = [];

                if (!productBranch || !productBranch.price) {

                    return;
                }

                const price = parseFloat(productBranch.price);
                if (isNaN(price) || price <= 0) {
                    return;
                }

                const stock = parseFloat(productBranch.stock ?? 0) || 0;

                // Asegurar que el ID del producto sea un número entero para la comparación
                const productId = parseInt(prod.id, 10);
                if (isNaN(productId) || productId <= 0) {

                    return;
                }

                // Limpiar items inválidos antes de buscar
                currentTable.items = currentTable.items.filter(i => {
                    const itemPId = parseInt(i.pId, 10);
                    return !isNaN(itemPId) && itemPId > 0;
                });

                // Buscar si el producto ya existe en el carrito
                const existing = currentTable.items.find(i => {
                    const itemPId = parseInt(i.pId, 10);
                    return !isNaN(itemPId) && itemPId === productId;
                });

                const qtyToAdd = existing ? existing.qty + 1 : 1;
                if (qtyToAdd > stock) {
                    showNotification('Stock insuficiente', (prod.name || 'Producto') + ': solo hay ' + stock + ' disponible(s).', 'error');
                    return;
                }

                if (existing) {
                    // Si existe, solo aumentar la cantidad
                    existing.qty++;
                    if (existing.tax_rate === undefined || existing.tax_rate === null) {
                        existing.tax_rate = parseFloat(productBranch.tax_rate ?? 10);
                    }
                } else {
                    // Si no existe, agregarlo como nuevo item

                    currentTable.items.push({
                        pId: productId, // Guardar como número entero
                        name: prod.name || 'Sin nombre',
                        qty: 1,
                        price: price,
                        tax_rate: parseFloat(productBranch.tax_rate ?? 10),
                        note: ""
                    });
                }
                saveDB();
                renderTicket();
            }

            function updateQty(index, change) {
                currentTable.items[index].qty += change;
                if (currentTable.items[index].qty <= 0) currentTable.items.splice(index, 1);
                saveDB();
                renderTicket();
            }

            function toggleNoteInput(index) {
                document.getElementById(`note-box-${index}`).classList.toggle('hidden');
            }

            function saveNote(index, val) {
                currentTable.items[index].note = val;
                saveDB();
            }

            function renderTicket() {
                const container = document.getElementById('cart-container');
                if (!container) {
                    return;
                }
                container.innerHTML = '';
                let subtotal = 0;

                if (!currentTable.items || currentTable.items.length === 0) {
                    container.innerHTML = `
                    <div class="flex flex-col items-center justify-center  text-gray-300 opacity-60">
                        <i class="fas fa-utensils text-3xl mb-2"></i>
                        <p class="font-medium text-sm">Sin productos</p>
                    </div>`;
                } else {
                    currentTable.items.forEach((item, index) => {
                        const prod = serverProducts.find(p => p.id === item.pId);
                        if (!prod) {
                            return;
                        }

                        const itemPrice = parseFloat(item.price) || 0;
                        const itemQty = parseInt(item.qty) || 0;
                        subtotal += itemPrice * itemQty;
                        const hasNote = item.note && item.note.trim() !== "";

                        const row = document.createElement('div');
                        row.className =
                            "bg-white border border-gray-200 rounded-lg p-2.5 shadow-sm relative overflow-hidden group mb-2";

                        const productName = escapeHtml(prod.name || 'Sin nombre');
                        const productImage = getImageUrl(prod.img || null);
                        const itemNote = escapeHtml(item.note || '');

                        row.innerHTML = `
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
                        <div class="flex gap-3 pl-2">
                            <img src="${productImage}" class="h-10 w-10 rounded-md object-cover bg-gray-100" alt="${productName}">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <span class="font-bold text-slate-700 text-xs truncate pr-1">${productName}</span>
                                    <span class="font-bold text-slate-800 text-xs">$${(item.price * item.qty).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <button onclick="toggleNoteInput(${index})" class="text-[10px] flex items-center gap-1 transition-colors ${hasNote ? 'text-blue-600 bg-blue-50 px-1.5 rounded' : 'text-gray-400 hover:text-blue-500'}">
                                        <i class="fas fa-comment-alt"></i> ${hasNote ? 'Nota' : 'Nota'}
                                    </button>
                                    <div class="flex items-center gap-2 bg-gray-50 rounded border border-gray-100">
                                        <button onclick="updateQty(${index}, -1)" class="w-6 h-5 flex items-center justify-center text-gray-500 hover:text-red-500"><i class="ri-subtract-line"></i></button>
                                        <span class="text-xs font-bold text-slate-700 w-4 text-center">${item.qty}</span>
                                        <button onclick="updateQty(${index}, 1)" class="w-6 h-5 flex items-center justify-center text-gray-500 hover:text-blue-600"><i class="ri-add-line"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="note-box-${index}" class="${hasNote ? '' : 'hidden'} mt-2 animate-fadeIn pl-2">
                            <input type="text" value="${itemNote}" oninput="saveNote(${index}, this.value)" placeholder="Nota..." class="w-full text-[10px] bg-yellow-50 border border-yellow-200 rounded p-1.5 text-slate-700 focus:outline-none focus:border-yellow-400">
                        </div>
                    `;
                        container.appendChild(row);
                    });
                }
                const totals = calculateTotalsFromItems(currentTable.items || []);
                const tax = totals.tax;
                const total = totals.total;
                subtotal = totals.subtotal;

                const subtotalEl = document.getElementById('ticket-subtotal');
                const taxEl = document.getElementById('ticket-tax');
                const totalEl = document.getElementById('ticket-total');

                if (subtotalEl) subtotalEl.innerText = `$${subtotal.toFixed(2)}`;
                if (taxEl) taxEl.innerText = `$${tax.toFixed(2)}`;
                if (totalEl) totalEl.innerText = `$${total.toFixed(2)}`;

            }

            function saveDB() {
                if (db && currentTable) {
                    // Agregar timestamp para saber cuándo se guardó
                    currentTable.lastUpdated = new Date().toISOString();
                    currentTable.isActive = true;
                    db[activeKey] = currentTable;
                    localStorage.setItem('restaurantDB', JSON.stringify(db));
                }
            }

            function processOrder() {
                const items = currentTable.items || [];
                const totals = calculateTotalsFromItems(items);
                const subtotal = totals.subtotal;
                const tax = totals.tax;
                const total = totals.total;

                const order = {
                    items: items,
                    table_id: currentTable.table_id ?? currentTable.id,
                    area_id: currentTable.area_id ?? null,
                    subtotal: subtotal,
                    tax: tax,
                    total: total,
                    people_count: currentTable.people_count ?? 0,
                    contact_phone: currentTable.contact_phone ?? null,
                    delivery_address: currentTable.delivery_address ?? null,
                    delivery_time: currentTable.delivery_time ?? null,
                    delivery_amount: currentTable.delivery_amount ?? 0,
                    order_movement_id: currentTable.order_movement_id ?? null,
                };
                fetch('{{ route('admin.orders.process') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                    'content') ||
                                '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(order)
                    })
                    .then(async (response) => {
                        const ct = response.headers.get('content-type');
                        if (ct && ct.includes('application/json')) {
                            return response.json();
                        }
                        throw new Error(response.status === 419 ? 'Sesión expirada. Recarga la página.' : (response.status === 401 ? 'Debes iniciar sesión.' : 'Error del servidor. Intenta de nuevo.'));
                    })
                    .then(data => {
                        if (data && data.success) {
                            sessionStorage.setItem('flash_success_message', data.message);
                            window.location.href = "{{ route('admin.orders.index') }}";
                        } else {
                            console.error('Error al guardar:', data);
                            sessionStorage.setItem('flash_error_message', data?.message );
                        }
                    })
                            .catch(error => {
                                console.error('Error:', error);
                                sessionStorage.setItem('flash_error_message', 'Error al guardar el pedido. Revisa la consola.');
                            });

            }

            function processOrderPayment() {
                const items = currentTable.items || [];
                if (items.length === 0) {
                    if (typeof showNotification === 'function') {
                        showNotification('Error', 'Agrega productos a la orden antes de cobrar.', 'error');
                    } else {
                        sessionStorage.setItem('flash_error_message', 'Agrega productos a la orden antes de cobrar.');
                    }
                    return;
                }
                const totals = calculateTotalsFromItems(items);
                const subtotal = totals.subtotal;
                const tax = totals.tax;
                const total = totals.total;
                const payload = {
                    items: items,
                    table_id: currentTable.table_id ?? currentTable.id,
                    area_id: currentTable.area_id ?? null,
                    subtotal: subtotal,
                    tax: tax,
                    total: total,
                    people_count: currentTable.people_count ?? 0,
                    contact_phone: currentTable.contact_phone ?? null,
                    delivery_address: currentTable.delivery_address ?? null,
                    delivery_time: currentTable.delivery_time ?? null,
                    delivery_amount: currentTable.delivery_amount ?? 0,
                };
                fetch('{{ route('admin.orders.processOrderPayment') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                'content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(async (response) => {
                        const ct = response.headers.get('content-type');
                        if (ct && ct.includes('application/json')) {
                            return response.json();
                        }
                        throw new Error(response.status === 419 ? 'Sesión expirada. Recarga la página.' : (response.status === 401 ? 'Debes iniciar sesión.' : 'Error del servidor. Intenta de nuevo.'));
                    })
                    .then(data => {
                        if (data && data.success && data.movement_id) {
                            const url = new URL("{{ route('admin.orders.charge') }}", window.location.origin);
                            url.searchParams.set('movement_id', data.movement_id);
                            sessionStorage.setItem('flash_success_message', data.message || 'Pedido cobrado correctamente');
                            window.location.href = url.toString();
                        } else {
                            if (typeof showNotification === 'function') {
                                showNotification('Error', data?.message || 'No se pudo procesar. Intenta de nuevo.',
                                    'error');
                            } else {
                                alert(data?.message || 'No se pudo procesar. Intenta de nuevo.');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (typeof showNotification === 'function') {
                            showNotification('Error', 'Error al procesar el pago.', 'error');
                        } else {
                            alert('Error al procesar el pago.');
                        }
                    });
            }

            function releaseTableAndGoBack() {
                const tableId = currentTable?.table_id ?? currentTable?.id ?? {{ $table->id }};
                const url = "{{ route('admin.orders.cancelOrder') }}";
                const indexUrl = "{{ route('admin.orders.index') }}";
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            table_id: tableId
                        }),
                    })
                    .then(async (r) => {
                        if (r.headers.get('content-type')?.includes('application/json')) {
                            return r.json();
                        }
                    })
                    .then(() => {
                        /* mesa liberada o ya estaba libre */ })
                    .catch(() => {
                        /* seguir igual, redirigir */ })
                    .finally(() => {
                        if (window.Turbo && typeof window.Turbo.visit === 'function') {
                            window.Turbo.visit(indexUrl, {
                                action: 'replace'
                            });
                        } else {
                            window.location.href = indexUrl;
                        }
                    });
            }

            function goBack() {
                releaseTableAndGoBack();
            }

            function cancelOrder() {
                releaseTableAndGoBack();
            }

            // Inicializar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

            function showNotification(title, message, type = 'info') {
                const notification = document.getElementById('notification');
                if (!notification) return;
                const isError = type === 'error';
                notification.innerHTML = `
                    <div class="rounded-xl border p-4 shadow-lg ${isError ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' : 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800'}">
                        <div class="flex items-start gap-3">
                            <div class="${isError ? 'text-red-500' : 'text-green-500'}"><i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'} text-xl"></i></div>
                            <div>
                                <h3 class="font-semibold ${isError ? 'text-red-800 dark:text-red-200' : 'text-green-800 dark:text-green-200'}">${title}</h3>
                                <p class="text-sm mt-1 ${isError ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'}">${message}</p>
                            </div>
                        </div>
                    </div>
                `;
                notification.classList.remove('opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    notification.classList.add('opacity-0', 'pointer-events-none');
                }, 3500);
            }

            // Exponer funciones usadas desde onclick en el HTML (mismo ámbito tras re-render)
            window.toggleNoteInput = toggleNoteInput;
            window.updateQty = updateQty;
            window.saveNote = saveNote;
            window.getImageUrl = getImageUrl;
            window.cancelOrder = cancelOrder;
            window.goBack = goBack;
            window.processOrder = processOrder;
            window.processOrderPayment = processOrderPayment;
        })();
    </script>
@endsection
