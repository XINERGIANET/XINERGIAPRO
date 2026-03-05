@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $salesIndexUrl = route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []);
    @endphp

    <div id="sales-create-view">
        <x-common.page-breadcrumb pageTitle="Nueva venta" />

        <x-common.component-card
            title="Punto de Venta"
            desc="Interfaz de venta rápida. Puedes seguir agregando productos aunque el stock mostrado sea 0."
        >
            <div class="flex items-start gap-6" style="display:flex; align-items:flex-start; gap:1.5rem;">
                <section class="min-w-0 space-y-5" style="flex: 0 0 60%; max-width: 60%; width: 60%;">
                  
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="relative flex-1">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="ri-search-line text-lg"></i>
                                </span>
                                <input
                                    id="product-search"
                                    type="text"
                                    placeholder="Buscar por nombre o categoría"
                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"
                                >
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $salesIndexUrl }}" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="ri-arrow-left-line"></i>
                                    <span>Volver</span>
                                </a>
                                <button type="button" id="clear-sale-button" class="inline-flex h-12 items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    <i class="ri-delete-bin-6-line"></i>
                                    <span>Limpiar orden</span>
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

                <aside class="min-w-0" style="flex: 0 0 40%; max-width: 40%; width: 40%;">
                    <div class="sticky top-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-800 bg-slate-900 px-4 py-3 text-white" style="background-color: #334155">
                            <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-slate-800/90 p-1">
                                <button type="button" id="summary-tab-button" class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 8px 18px rgba(249,115,22,0.24);">
                                    Resumen
                                </button>
                                <button type="button" id="payment-tab-button" class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-slate-300 transition-colors hover:text-white">
                                    Cobro
                                </button>
                            </div>
                        </div>

             

                        <div id="summary-tab-panel">

                        <div id="cart-container" class="max-h-[52vh] overflow-y-auto p-4"></div>

                        <div class="border-t border-slate-200 bg-slate-50 p-5">
                            <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex justify-between text-sm text-slate-500"><span>Subtotal</span><span id="ticket-subtotal" class="font-semibold">$0.00</span></div>
                                <div class="flex justify-between text-sm text-slate-500"><span>IGV</span><span id="ticket-tax" class="font-semibold" >$0.00</span></div>
                                <div class="border-t border-dashed border-slate-200 pt-2"></div>
                                <div class="flex items-center justify-between"><span class="text-base font-bold text-slate-900">Total a pagar</span><span id="ticket-total" class="text-3xl font-black" style="color:#f97316;">$0.00</span></div>
                            </div>
                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                <label for="sale-notes" class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Notas</label>
                                <textarea
                                    id="sale-notes"
                                    rows="2"
                                    placeholder="Detalle adicional de la venta"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"
                                ></textarea>
                            </div>
                        </div>
                        </div>

                        <div id="payment-tab-panel" class="hidden bg-slate-50 p-5">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <label class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Cliente</label>
                                        <div class="relative" id="client-selector">
                                            <input
                                                id="client-autocomplete"
                                                type="text"
                                                placeholder="Buscar cliente por nombre o documento"
                                                autocomplete="off"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 pr-10 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                            >
                                            <button
                                                type="button"
                                                id="client-clear-button"
                                                class="absolute right-3 top-1/2 hidden -translate-y-1/2 text-slate-400 hover:text-slate-700"
                                                title="Limpiar cliente"
                                            >
                                                <i class="ri-close-line"></i>
                                            </button>
                                            <div
                                                id="client-options"
                                                class="absolute z-50 mt-1 hidden max-h-56 w-full overflow-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="space-y-2">
                                            <label for="document-type-select" class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Documento</label>
                                            <select
                                                id="document-type-select"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                            >
                                                @foreach ($documentTypes ?? [] as $documentType)
                                                    <option value="{{ $documentType->id }}" @selected((int) ($defaultDocumentTypeId ?? 0) === (int) $documentType->id)>{{ $documentType->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="cash-register-select" class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Caja</label>
                                            <select
                                                id="cash-register-select"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                            >
                                                @foreach ($cashRegisters ?? [] as $cashRegister)
                                                    <option value="{{ $cashRegister->id }}" @selected($cashRegister->status === 'A')>
                                                        {{ $cashRegister->number }}{{ $cashRegister->status === 'A' ? ' (Activa)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <p class="mt-1 text-sm font-bold text-slate-900">Métodos de pago</p>
                                    </div>
                                    <button type="button" id="add-payment-row-button" class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-xs font-bold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 10px 20px rgba(249,115,22,0.18);">
                                        <i class="ri-add-line"></i>
                                        <span>Agregar</span>
                                    </button>
                                </div>
                                <div id="payment-rows" class="space-y-3"></div>
                                <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-slate-500">Total pagado</span>
                                        <span id="payment-total" class="font-black" >$0.00</span>
                                    </div>
                                    <div id="payment-difference-wrap" class="mt-2 hidden flex items-center justify-between border-t border-dashed border-slate-200 pt-2">
                                        <span id="payment-difference-label" class="font-semibold" >Falta pagar</span>
                                        <span id="payment-difference" class="font-black" style="color:#ea580c;">$0.00</span>
                                    </div>
                                </div>
                            </div>
                         
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <button type="button" onclick="goBack()" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                    <i class="ri-save-line"></i><span>Guardar</span>
                                </button>
                                <button type="button" onclick="processSaleNow()" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white shadow-theme-xs" style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 12px 24px rgba(249,115,22,0.24);">
                                    <i class="ri-cash-line"></i><span>Cobrar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </x-common.component-card>
    </div>

    <div id="stock-error-notification" class="pointer-events-none fixed right-6 top-24 z-50 translate-x-[140%] opacity-0 transition-all duration-300">
        <div class="flex min-w-[320px] items-start gap-3 rounded-2xl border border-orange-200 bg-white px-4 py-4 shadow-2xl">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-orange-100 text-orange-700"><i class="ri-alert-line text-lg"></i></div>
            <div class="flex-1">
                <p class="text-sm font-bold text-slate-900">Aviso</p>
                <p id="stock-error-message" class="mt-0.5 text-xs text-slate-500">Mensaje</p>
            </div>
            <button type="button" onclick="hideStockError()" class="text-slate-400 hover:text-slate-700"><i class="ri-close-line"></i></button>
        </div>
    </div>

    <div id="add-to-cart-notification" class="pointer-events-none fixed right-6 top-24 z-50 translate-x-[140%] opacity-0 transition-all duration-300">
        <div class="flex min-w-[320px] items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-4 shadow-2xl">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><i class="ri-check-line text-lg"></i></div>
            <div class="flex-1">
                <p class="text-sm font-bold text-slate-900">Producto agregado</p>
                <p id="notification-product-name" class="mt-0.5 text-xs text-slate-500">Producto</p>
            </div>
            <button type="button" onclick="hideNotification()" class="text-slate-400 hover:text-slate-700"><i class="ri-close-line"></i></button>
        </div>
    </div>

    <style>
        .notification-show { transform: translateX(0) !important; opacity: 1 !important; pointer-events: auto !important; }

        #sales-create-view input:focus,
        #sales-create-view select:focus,
        #sales-create-view textarea:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.16) !important;
            border-color: #f97316 !important;
        }

        #sales-create-view input:focus-visible,
        #sales-create-view select:focus-visible,
        #sales-create-view textarea:focus-visible {
            outline: none !important;
        }

        @media (max-width: 1279px) {
            #sales-create-view .flex.items-start.gap-6[style*="display:flex"] {
                flex-direction: column !important;
                gap: 1rem !important;
            }

            #sales-create-view .flex.items-start.gap-6[style*="display:flex"] > section,
            #sales-create-view .flex.items-start.gap-6[style*="display:flex"] > aside {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            #sales-create-view aside .sticky {
                position: static !important;
                top: auto !important;
            }
        }

        @media (max-width: 767px) {
            #sales-create-view #products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.65rem !important;
            }

            #sales-create-view #cart-container {
                max-height: 42vh !important;
            }

            #stock-error-notification,
            #add-to-cart-notification {
                right: 0.75rem !important;
                left: 0.75rem !important;
                top: auto !important;
                bottom: 0.75rem !important;
                transform: translateY(140%) !important;
            }

            #stock-error-notification .min-w-\[320px\],
            #add-to-cart-notification .min-w-\[320px\] {
                min-width: auto !important;
                width: 100% !important;
            }

            .notification-show {
                transform: translateY(0) !important;
            }
        }
    </style>

    <script>
        (function () {
            const products = Array.isArray(@json($products ?? [])) ? @json($products ?? []) : Object.values(@json($products ?? []) || {});
            const productBranches = Array.isArray(@json($productBranches ?? $productsBranches ?? [])) ? @json($productBranches ?? $productsBranches ?? []) : Object.values(@json($productBranches ?? $productsBranches ?? []) || {});
            const people = Array.isArray(@json(($people ?? collect())->map(function ($person) {
                $fullName = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
                return [
                    'id' => (int) $person->id,
                    'label' => $fullName !== '' ? $fullName : ($person->document_number ?: 'Sin nombre'),
                    'document' => (string) ($person->document_number ?? '')
                ];
            })->values())) ? @json(($people ?? collect())->map(function ($person) {
                $fullName = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
                return [
                    'id' => (int) $person->id,
                    'label' => $fullName !== '' ? $fullName : ($person->document_number ?: 'Sin nombre'),
                    'document' => (string) ($person->document_number ?? '')
                ];
            })->values()) : [];
            const defaultClientId = Number(@json($defaultClientId ?? 0)) || null;
            const documentTypes = Array.isArray(@json($documentTypes ?? [])) ? @json($documentTypes ?? []) : [];
            const paymentMethods = Array.isArray(@json($paymentMethods ?? [])) ? @json($paymentMethods ?? []) : [];
            const paymentGateways = Array.isArray(@json($paymentGateways ?? [])) ? @json($paymentGateways ?? []) : [];
            const cards = Array.isArray(@json($cards ?? [])) ? @json($cards ?? []) : [];
            const digitalWallets = Array.isArray(@json($digitalWallets ?? [])) ? @json($digitalWallets ?? []) : [];

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
            let productSearch = '';
            let clientQuery = '';
            let clientCursor = 0;
            let clientOpen = false;
            let paymentRows = [];
            let currentAsideTab = 'summary';

            const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
            let db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
            let activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);

            if (!activeKey || !db[activeKey] || db[activeKey]?.status === 'completed') {
                activeKey = `sale-${Date.now()}`;
                localStorage.setItem(ACTIVE_SALE_KEY_STORAGE, activeKey);
            }

            const defaultClient = people.find((person) => Number(person.id) === Number(defaultClientId)) || null;
            let currentSale = db[activeKey] || {
                id: Date.now(),
                clientId: defaultClient ? defaultClient.id : null,
                clientName: defaultClient ? defaultClient.label : 'Publico General',
                status: 'in_progress',
                notes: '',
                items: []
            };
            paymentRows = Array.isArray(currentSale.payment_methods)
                ? currentSale.payment_methods.map((row) => ({
                    payment_method_id: Number(row.payment_method_id || row.methodId || paymentMethods[0]?.id || 0),
                    amount: Number(row.amount || 0),
                    payment_gateway_id: row.payment_gateway_id ? Number(row.payment_gateway_id) : null,
                    card_id: row.card_id ? Number(row.card_id) : null,
                    digital_wallet_id: row.digital_wallet_id ? Number(row.digital_wallet_id) : null,
                    method_variant_key: row.method_variant_key || null,
                }))
                : [];
            db[activeKey] = currentSale;
            localStorage.setItem('restaurantDB', JSON.stringify(db));

            const getImageUrl = (imgUrl) => imgUrl && String(imgUrl).trim() !== ''
                ? imgUrl
                : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iMzAwIj48cmVjdCBmaWxsPSIjZTJlOGYwIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiM2NDc0OGIiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
            const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`;
            const saveDB = () => { db[activeKey] = currentSale; localStorage.setItem('restaurantDB', JSON.stringify(db)); };
            const getProductCategory = (prod) => (prod && prod.category && String(prod.category).trim() !== '') ? String(prod.category).trim() : 'Sin categoria';
            const filteredClients = () => {
                const term = clientQuery.toLowerCase().trim();
                const list = term === ''
                    ? people
                    : people.filter((person) => {
                        const label = String(person.label || '').toLowerCase();
                        const doc = String(person.document || '').toLowerCase();
                        return label.includes(term) || doc.includes(term);
                    });

                if (clientCursor >= list.length) clientCursor = 0;
                return list.slice(0, 40);
            };
            const getTotalFromSale = () => currentSale.items.reduce((sum, item) => {
                return sum + ((Number(item.price) || 0) * (Number(item.qty) || 0));
            }, 0);
            const inferPaymentMethodKind = (description) => {
                const normalized = String(description || '').toLowerCase();
                if (normalized.includes('tarjeta') || normalized.includes('card')) return 'card';
                if (normalized.includes('billetera') || normalized.includes('wallet')) return 'wallet';
                return 'plain';
            };
            const buildPaymentMethodVariants = () => paymentMethods.flatMap((method) => {
                const methodId = Number(method.id);
                const description = String(method.description || '');
                const kind = inferPaymentMethodKind(description);

                if (kind === 'wallet' && digitalWallets.length) {
                    return digitalWallets.map((wallet) => ({
                        key: `wallet:${methodId}:${Number(wallet.id)}`,
                        payment_method_id: methodId,
                        digital_wallet_id: Number(wallet.id),
                        card_id: null,
                        label: `${description} - ${wallet.description}`,
                        kind,
                    }));
                }

                if (kind === 'card' && cards.length) {
                    return cards.map((card) => ({
                        key: `card:${methodId}:${Number(card.id)}`,
                        payment_method_id: methodId,
                        digital_wallet_id: null,
                        card_id: Number(card.id),
                        label: `${description} - ${card.description}`,
                        kind,
                    }));
                }

                return [{
                    key: `plain:${methodId}`,
                    payment_method_id: methodId,
                    digital_wallet_id: null,
                    card_id: null,
                    label: description,
                    kind,
                }];
            });
            const paymentMethodVariants = buildPaymentMethodVariants();
            const getPaymentVariantByKey = (key) => paymentMethodVariants.find((variant) => variant.key === key) || null;
            const getDefaultPaymentVariant = () => paymentMethodVariants[0] || null;
            const isCardMethod = (methodId) => {
                const method = paymentMethods.find((pm) => Number(pm.id) === Number(methodId));
                const description = String(method?.description || '').toLowerCase();
                return description.includes('tarjeta') || description.includes('card');
            };
            const getMethodName = (methodId) => {
                const method = paymentMethods.find((pm) => Number(pm.id) === Number(methodId));
                return method?.description || 'Metodo';
            };

            function getCategories() {
                const unique = new Set();
                products.forEach((prod) => unique.add(getProductCategory(prod)));
                return ['General', ...Array.from(unique).sort((a, b) => a.localeCompare(b))];
            }

            function showNotice(message) {
                const notification = document.getElementById('stock-error-notification');
                const msgEl = document.getElementById('stock-error-message');
                if (!notification || !msgEl) return;
                msgEl.textContent = message;
                notification.classList.add('notification-show');
                setTimeout(hideStockError, 2200);
            }

            function hideStockError() {
                document.getElementById('stock-error-notification')?.classList.remove('notification-show');
            }

            function showNotification(productName) {
                const notification = document.getElementById('add-to-cart-notification');
                const productNameEl = document.getElementById('notification-product-name');
                if (!notification || !productNameEl) return;
                productNameEl.textContent = productName;
                notification.classList.add('notification-show');
                setTimeout(hideNotification, 1400);
            }

            function closeClientDropdown() {
                clientOpen = false;
                document.getElementById('client-options')?.classList.add('hidden');
            }

            function openClientDropdown() {
                clientOpen = true;
                document.getElementById('client-options')?.classList.remove('hidden');
                renderClientOptions();
            }

            function renderClientOptions() {
                const container = document.getElementById('client-options');
                const clearButton = document.getElementById('client-clear-button');
                if (!container) return;

                const clients = filteredClients();
                container.innerHTML = '';

                if (clearButton) {
                    clearButton.classList.toggle('hidden', !currentSale.clientId && !(clientQuery || '').trim());
                }

                if (!clients.length) {
                    container.innerHTML = '<p class="px-4 py-3 text-xs text-slate-500">Sin resultados</p>';
                    return;
                }

                clients.forEach((client, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-slate-50';
                    if (clientCursor === index) {
                        button.classList.add('bg-slate-100');
                    }
                    button.innerHTML = `
                        <span class="font-medium text-slate-800">${client.label || 'SIN NOMBRE'}</span>
                        <span class="text-xs text-slate-500">${client.document || ''}</span>
                    `;
                    button.addEventListener('mouseenter', () => {
                        clientCursor = index;
                    });
                    button.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        selectClient(client);
                    });
                    container.appendChild(button);
                });
            }

            function selectClient(client) {
                currentSale.clientId = client ? client.id : null;
                currentSale.clientName = client ? client.label : (defaultClient ? defaultClient.label : 'Publico General');
                clientQuery = client ? (client.document ? `${client.label} - ${client.document}` : client.label) : '';
                saveDB();
                const clientInput = document.getElementById('client-autocomplete');
                if (clientInput) clientInput.value = clientQuery || currentSale.clientName;
                closeClientDropdown();
            }

            function clearClient() {
                currentSale.clientId = defaultClient ? defaultClient.id : null;
                currentSale.clientName = defaultClient ? defaultClient.label : 'Publico General';
                clientQuery = '';
                saveDB();
                const clientInput = document.getElementById('client-autocomplete');
                if (clientInput) clientInput.value = currentSale.clientName;
                openClientDropdown();
            }

            function setAsideTab(tab) {
                currentAsideTab = tab === 'payment' ? 'payment' : 'summary';

                const summaryButton = document.getElementById('summary-tab-button');
                const paymentButton = document.getElementById('payment-tab-button');
                const summaryPanel = document.getElementById('summary-tab-panel');
                const paymentPanel = document.getElementById('payment-tab-panel');

                if (summaryPanel) summaryPanel.classList.toggle('hidden', currentAsideTab !== 'summary');
                if (paymentPanel) paymentPanel.classList.toggle('hidden', currentAsideTab !== 'payment');

                if (summaryButton) {
                    summaryButton.className = currentAsideTab === 'summary'
                        ? 'inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-white shadow-theme-xs'
                        : 'inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-slate-300 transition-colors hover:text-white';
                    summaryButton.style.background = currentAsideTab === 'summary' ? 'linear-gradient(90deg,#ff7a00,#ff4d00)' : '';
                    summaryButton.style.color = currentAsideTab === 'summary' ? '#fff' : '';
                    summaryButton.style.boxShadow = currentAsideTab === 'summary' ? '0 8px 18px rgba(249,115,22,0.24)' : '';
                }

                if (paymentButton) {
                    paymentButton.className = currentAsideTab === 'payment'
                        ? 'inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-white shadow-theme-xs'
                        : 'inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-slate-300 transition-colors hover:text-white';
                    paymentButton.style.background = currentAsideTab === 'payment' ? 'linear-gradient(90deg,#ff7a00,#ff4d00)' : '';
                    paymentButton.style.color = currentAsideTab === 'payment' ? '#fff' : '';
                    paymentButton.style.boxShadow = currentAsideTab === 'payment' ? '0 8px 18px rgba(249,115,22,0.24)' : '';
                }
            }

            function syncPaymentRows() {
                currentSale.payment_methods = paymentRows.map((row) => ({
                    payment_method_id: Number(row.payment_method_id) || null,
                    amount: Number(row.amount) || 0,
                    payment_gateway_id: row.payment_gateway_id ? Number(row.payment_gateway_id) : null,
                    card_id: row.card_id ? Number(row.card_id) : null,
                    digital_wallet_id: row.digital_wallet_id ? Number(row.digital_wallet_id) : null,
                    method_variant_key: row.method_variant_key || null,
                }));
                saveDB();
            }

            function syncPaymentAmountsWithTotal() {
                const total = Number(getTotalFromSale().toFixed(2));

                if (!currentSale.items.length) {
                    updatePaymentSummary();
                    return;
                }

                if (!paymentRows.length) {
                    addPaymentRow(total);
                    return;
                }

                if (paymentRows.length === 1) {
                    paymentRows[0].amount = total;
                } else {
                    const fixedAmount = paymentRows
                        .slice(0, -1)
                        .reduce((sum, row) => sum + (Number(row.amount) || 0), 0);

                    paymentRows[paymentRows.length - 1].amount = Math.max(
                        0,
                        Number((total - fixedAmount).toFixed(2))
                    );
                }

                syncPaymentRows();
                renderPaymentRows();
            }

            function updatePaymentSummary() {
                const total = getTotalFromSale();
                const paid = paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                const diff = total - paid;
                const totalEl = document.getElementById('payment-total');
                const diffWrap = document.getElementById('payment-difference-wrap');
                const diffLabel = document.getElementById('payment-difference-label');
                const diffEl = document.getElementById('payment-difference');

                if (totalEl) totalEl.textContent = formatMoney(paid);

                if (!diffWrap || !diffLabel || !diffEl) return;

                if (Math.abs(diff) <= 0.009) {
                    diffWrap.classList.add('hidden');
                    return;
                }

                diffWrap.classList.remove('hidden');
                if (diff > 0) {
                    diffLabel.textContent = 'Falta pagar';
                    diffLabel.className = 'font-semibold';
                    diffEl.className = 'font-black';
                    diffLabel.style.color = '#ea580c';
                    diffEl.style.color = '#ea580c';
                    diffEl.textContent = formatMoney(diff);
                } else {
                    diffLabel.textContent = 'Vuelto';
                    diffLabel.className = 'font-semibold';
                    diffEl.className = 'font-black';
                    diffLabel.style.color = '#059669';
                    diffEl.style.color = '#059669';
                    diffEl.textContent = formatMoney(Math.abs(diff));
                }
            }

            function addPaymentRow(prefillAmount = null) {
                const fallbackVariant = getDefaultPaymentVariant();
                if (!fallbackVariant) {
                    showNotice('No hay metodos de pago disponibles.');
                    return;
                }

                const total = getTotalFromSale();
                const paid = paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                const remaining = Math.max(0, total - paid);

                paymentRows.push({
                    payment_method_id: Number(fallbackVariant.payment_method_id),
                    amount: prefillAmount != null ? Number(prefillAmount) : (paymentRows.length === 0 ? total : remaining),
                    payment_gateway_id: null,
                    card_id: fallbackVariant.card_id ? Number(fallbackVariant.card_id) : null,
                    digital_wallet_id: fallbackVariant.digital_wallet_id ? Number(fallbackVariant.digital_wallet_id) : null,
                    method_variant_key: fallbackVariant.key,
                });
                syncPaymentRows();
                renderPaymentRows();
            }

            function removePaymentRow(index) {
                paymentRows.splice(index, 1);
                syncPaymentRows();
                renderPaymentRows();
            }

            function renderPaymentRows() {
                const container = document.getElementById('payment-rows');
                if (!container) return;

                if (!paymentRows.length) {
                    container.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-center text-xs font-medium text-slate-500">Agrega al menos un metodo de pago.</div>';
                    updatePaymentSummary();
                    return;
                }

                container.innerHTML = paymentRows.map((row, index) => {
                    const selectedVariantKey = row.method_variant_key
                        || (row.card_id
                            ? `card:${Number(row.payment_method_id)}:${Number(row.card_id)}`
                            : row.digital_wallet_id
                                ? `wallet:${Number(row.payment_method_id)}:${Number(row.digital_wallet_id)}`
                                : `plain:${Number(row.payment_method_id)}`);
                    const selectedVariant = getPaymentVariantByKey(selectedVariantKey);
                    const showCardFields = selectedVariant?.kind === 'card' || isCardMethod(row.payment_method_id);
                    const layoutStyle = showCardFields
                        ? 'display:grid; gap:0.75rem; grid-template-columns:minmax(0,1.7fr) minmax(0,0.9fr) minmax(0,1fr) auto;'
                        : 'display:grid; gap:0.75rem; grid-template-columns:minmax(0,1.8fr) minmax(0,1fr) auto;';
                    const gatewayOptions = paymentGateways.map((gateway) => `
                        <option value="${gateway.id}" ${Number(row.payment_gateway_id) === Number(gateway.id) ? 'selected' : ''}>${gateway.description}</option>
                    `).join('');
                    const methodOptions = paymentMethodVariants.map((variant) => `
                        <option value="${variant.key}" ${selectedVariantKey === variant.key ? 'selected' : ''}>${variant.label}</option>
                    `).join('');

                    return `
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div style="${layoutStyle}">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Metodo</label>
                                    <select data-role="method" data-index="${index}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                        ${methodOptions}
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Monto</label>
                                    <input data-role="amount" data-index="${index}" type="number" min="0" step="0.01" value="${(Number(row.amount) || 0).toFixed(2)}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                </div>
                                ${showCardFields ? `
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Pasarela</label>
                                        <select data-role="gateway" data-index="${index}" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                            <option value="">Seleccionar</option>
                                            ${gatewayOptions}
                                        </select>
                                    </div>
                                ` : ''}
                                <div class="flex items-end">
                                    <button type="button" data-role="remove-payment" data-index="${index}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Eliminar">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                container.querySelectorAll('[data-role="method"]').forEach((element) => {
                    element.addEventListener('change', (event) => {
                        const index = Number(event.currentTarget.dataset.index);
                        const variant = getPaymentVariantByKey(event.currentTarget.value);
                        if (!variant) return;
                        paymentRows[index].method_variant_key = variant.key;
                        paymentRows[index].payment_method_id = Number(variant.payment_method_id) || null;
                        paymentRows[index].card_id = variant.card_id ? Number(variant.card_id) : null;
                        paymentRows[index].digital_wallet_id = variant.digital_wallet_id ? Number(variant.digital_wallet_id) : null;
                        if (variant.kind !== 'card') {
                            paymentRows[index].payment_gateway_id = null;
                        }
                        syncPaymentRows();
                        renderPaymentRows();
                    });
                });

                container.querySelectorAll('[data-role="amount"]').forEach((element) => {
                    element.addEventListener('input', (event) => {
                        const index = Number(event.currentTarget.dataset.index);
                        paymentRows[index].amount = Number(event.currentTarget.value) || 0;
                        syncPaymentRows();
                        updatePaymentSummary();
                    });
                });

                container.querySelectorAll('[data-role="gateway"]').forEach((element) => {
                    element.addEventListener('change', (event) => {
                        const index = Number(event.currentTarget.dataset.index);
                        paymentRows[index].payment_gateway_id = event.currentTarget.value ? Number(event.currentTarget.value) : null;
                        syncPaymentRows();
                    });
                });

                container.querySelectorAll('[data-role="remove-payment"]').forEach((element) => {
                    element.addEventListener('click', (event) => {
                        removePaymentRow(Number(event.currentTarget.dataset.index));
                    });
                });

                updatePaymentSummary();
            }

            function hideNotification() {
                document.getElementById('add-to-cart-notification')?.classList.remove('notification-show');
            }

            function renderCategoryFilters() {
                const container = document.getElementById('category-filters');
                const label = document.getElementById('selected-category-label');
                if (!container) return;
                container.innerHTML = '';

                getCategories().forEach((category) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inline-flex h-10 items-center rounded-2xl border px-4 text-xs font-bold uppercase tracking-[0.14em] transition';
                    const isActive = category === selectedCategory;
                    button.className += isActive
                        ? ' border-transparent text-white shadow-theme-xs'
                        : ' border-slate-200 bg-white text-slate-600 hover:border-orange-300 hover:text-orange-700';
                    button.style.background = isActive ? 'linear-gradient(90deg,#ff7a00,#ff4d00)' : '';
                    button.style.color = isActive ? '#fff' : '';
                    button.style.boxShadow = isActive ? '0 10px 20px rgba(249,115,22,0.18)' : '';
                    button.textContent = category;
                    button.addEventListener('click', () => {
                        selectedCategory = category;
                        if (label) label.textContent = category;
                        renderCategoryFilters();
                        renderProducts();
                    });
                    container.appendChild(button);
                });

                if (label) label.textContent = selectedCategory;
            }

            function renderProducts() {
                const grid = document.getElementById('products-grid');
                const catalogCount = document.getElementById('catalog-count');
                if (!grid) return;
                grid.innerHTML = '';
                let rendered = 0;

                products.forEach((prod) => {
                    const productId = Number(prod.id);
                    const price = priceByProductId.get(productId);
                    const category = getProductCategory(prod);
                    const stock = stockByProductId.get(productId) ?? 0;
                    const hasImage = !!(prod.img && String(prod.img).trim() !== '');
                    const searchNeedle = `${prod.name || ''} ${category}`.toLowerCase();

                    if (typeof price === 'undefined') return;
                    if (selectedCategory !== 'General' && category !== selectedCategory) return;
                    if (productSearch && !searchNeedle.includes(productSearch)) return;

                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'group overflow-hidden border bg-white text-center transition-all duration-200';
                    card.style.borderRadius = '28px';
                    card.style.borderColor = '#dbe3ef';
                    card.style.borderWidth = '1px';
                    card.style.borderStyle = 'solid';
                    card.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.06)';
                    card.addEventListener('click', () => addToCart(prod, price));
                    card.addEventListener('mouseenter', () => {
                        const orb = card.querySelector('[data-role="product-orb"]');
                        card.style.transform = 'translateY(-4px)';
                        card.style.borderColor = '#fdba74';
                        card.style.boxShadow = '0 18px 34px rgba(249, 115, 22, 0.16)';
                        card.style.backgroundColor = '#fdfefe';
                        if (orb) {
                            orb.style.transform = 'scale(1.04)';
                            orb.style.boxShadow = '0 16px 28px rgba(249, 115, 22, 0.24)';
                        }
                    });
                    card.addEventListener('mouseleave', () => {
                        const orb = card.querySelector('[data-role="product-orb"]');
                        card.style.transform = '';
                        card.style.borderColor = '#dbe3ef';
                        card.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.06)';
                        card.style.backgroundColor = '#ffffff';
                        if (orb) {
                            orb.style.transform = '';
                            orb.style.boxShadow = '0 12px 24px rgba(249, 115, 22, 0.18)';
                        }
                    });

                    card.innerHTML = `
                        <div class="relative px-3 pt-3">
                         
                            <div class="absolute right-2 top-[2.9rem] z-20 rounded-full border px-1.5 py-0.5 text-[9px] font-bold leading-none ${stock > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-700'}" style="box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);">
                                Stock: ${Number(stock).toFixed(0)}
                            </div>
                            <div data-role="product-orb" class="mx-auto mt-2 flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gradient-to-b from-orange-400 to-orange-500 transition-transform duration-200" style="box-shadow: 0 12px 24px rgba(249, 115, 22, 0.18);">
                                ${hasImage
                                    ? `<img src="${getImageUrl(prod.img)}" alt="${prod.name || 'Producto'}" class="h-14 w-14 object-cover" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">`
                                    : `<i class="ri-shopping-bag-3-line text-3xl text-white"></i>`}
                            </div>
                        </div>
                        <div class="px-3 pb-3 pt-2.5">
                            <h4 class="line-clamp-2 min-h-[40px] text-[14px] font-bold leading-5 text-slate-900">${prod.name || 'Sin nombre'}</h4>
                            <p class="mt-1 text-[1.85rem] font-black leading-none tracking-tight transition-colors duration-200 group-hover:text-orange-600" style="color:#f97316;">${formatMoney(price)}</p>
                        </div>
                    `;

                    grid.appendChild(card);
                    rendered++;
                });

                if (catalogCount) catalogCount.textContent = String(rendered);

                if (rendered === 0) {
                    grid.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">No se encontraron productos para el filtro actual.</div>';
                }
            }

            function addToCart(prod, price) {
                const productId = Number(prod.id);
                if (Number.isNaN(productId)) return;

                const existing = currentSale.items.find((item) => Number(item.pId) === productId);
                if (existing) {
                    existing.qty += 1;
                } else {
                    currentSale.items.push({ pId: productId, name: prod.name || '', qty: 1, price: Number(price) || 0, note: '' });
                }

                saveDB();
                renderTicket();
                if ((stockByProductId.get(productId) ?? 0) <= 0) {
                    showNotice((prod.name || 'Producto') + ': agregado aunque no tenga stock.');
                }
                showNotification(prod.name || 'Producto');
            }

            function updateQty(index, delta) {
                if (!currentSale.items[index]) return;
                currentSale.items[index].qty += delta;
                if (currentSale.items[index].qty <= 0) currentSale.items.splice(index, 1);
                saveDB();
                renderTicket();
            }

            function setQty(index, value) {
                if (!currentSale.items[index]) return;

                const parsed = Math.floor(Number(value));
                if (!Number.isFinite(parsed) || parsed <= 0) {
                    currentSale.items.splice(index, 1);
                } else {
                    currentSale.items[index].qty = parsed;
                }

                saveDB();
                renderTicket();
            }

            function renderTicket() {
                const container = document.getElementById('cart-container');
                if (!container) return;
                container.innerHTML = '';

                let subtotalBase = 0;
                let tax = 0;
                let totalItems = 0;

                if (!currentSale.items.length) {
                    container.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center"><div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm"><i class="ri-shopping-bag-3-line text-3xl"></i></div><p class="mt-4 text-base font-bold text-slate-800">Sin productos en la orden</p><p class="mt-1 text-sm text-slate-500">Agrega productos desde el catálogo.</p></div>';
                } else {
                    currentSale.items.forEach((item, index) => {
                        const prod = products.find((p) => Number(p.id) === Number(item.pId));
                        if (!prod) return;

                        const itemTotal = (Number(item.price) || 0) * (Number(item.qty) || 0);
                        const taxPct = taxRateByProductId.get(Number(item.pId)) ?? defaultTaxPct;
                        const taxVal = taxPct / 100;
                        const itemSubtotal = taxVal > 0 ? itemTotal / (1 + taxVal) : itemTotal;
                        subtotalBase += itemSubtotal;
                        tax += itemTotal - itemSubtotal;
                        totalItems += Number(item.qty) || 0;

                        const row = document.createElement('div');
                        row.className = 'mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';
                        row.innerHTML = `
                            <div class="flex items-center gap-3 p-2.5">
                                <img src="${getImageUrl(prod.img)}" alt="${prod.name || 'Producto'}" class="h-12 w-12 shrink-0 rounded-xl object-cover bg-slate-100" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <h5 class="truncate text-sm font-bold text-slate-900">${prod.name || 'Producto'}</h5>
                                                <span class="shrink-0 text-sm font-black" style="color:#f97316;">${formatMoney(itemTotal)}</span>
                                            </div>
                                            <p class="mt-0.5 text-xs font-semibold">${formatMoney(item.price)} c/u</p>
                                        </div>
                                        <div class="inline-flex shrink-0 items-center rounded-xl border border-slate-200 bg-slate-50">
                                            <button type="button" onclick="updateQty(${index}, -1)" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-rose-600"><i class="ri-subtract-line"></i></button>
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                value="${Math.max(1, Math.floor(Number(item.qty) || 1))}"
                                                onchange="setQty(${index}, this.value)"
                                                class="h-8 w-12 border-x border-slate-200 bg-white text-center text-sm font-bold text-slate-900 outline-none"
                                            >
                                            <button type="button" onclick="updateQty(${index}, 1)" class="flex h-8 w-8 items-center justify-center text-slate-700 hover:text-orange-600"><i class="ri-add-line"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(row);
                    });
                }

                const total = subtotalBase + tax;
                document.getElementById('ticket-subtotal').innerText = formatMoney(subtotalBase);
                document.getElementById('ticket-tax').innerText = formatMoney(tax);
                document.getElementById('ticket-total').innerText = formatMoney(total);
                const subtotalSecondary = document.getElementById('ticket-subtotal-secondary');
                const taxSecondary = document.getElementById('ticket-tax-secondary');
                const totalSecondary = document.getElementById('ticket-total-secondary');
                if (subtotalSecondary) subtotalSecondary.innerText = formatMoney(subtotalBase);
                if (taxSecondary) taxSecondary.innerText = formatMoney(tax);
                if (totalSecondary) totalSecondary.innerText = formatMoney(total);
                const cartCountBadge = document.getElementById('cart-count-badge');
                if (cartCountBadge) {
                    cartCountBadge.textContent = String(totalItems);
                }

                syncPaymentAmountsWithTotal();
            }

            function clearSale() {
                currentSale.items = [];
                currentSale.notes = '';
                paymentRows = [];
                saveDB();
                syncPaymentRows();
                const notesInput = document.getElementById('sale-notes');
                if (notesInput) notesInput.value = '';
                renderPaymentRows();
                renderTicket();
                showNotice('La orden actual fue limpiada.');
            }

            function processSaleNow() {
                if (!currentSale.items.length) {
                    showNotice('Agrega al menos un producto antes de cobrar.');
                    return;
                }

                if (!paymentRows.length) {
                    showNotice('Agrega al menos un metodo de pago.');
                    return;
                }

                const total = getTotalFromSale();
                const totalPaid = paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                if (Math.abs(totalPaid - total) > 0.01) {
                    showNotice('La suma de los pagos debe coincidir con el total.');
                    return;
                }

                const invalidCardRow = paymentRows.find((row) => {
                    return isCardMethod(row.payment_method_id) && (!row.payment_gateway_id || !row.card_id);
                });
                if (invalidCardRow) {
                    showNotice('Completa pasarela y tarjeta para los pagos con tarjeta.');
                    return;
                }

                const payload = {
                    items: currentSale.items.map((item) => ({
                        pId: Number(item.pId),
                        qty: Number(item.qty),
                        price: Number(item.price),
                        note: item.note || '',
                    })),
                    document_type_id: Number(document.getElementById('document-type-select')?.value || 0),
                    cash_register_id: Number(document.getElementById('cash-register-select')?.value || 0),
                    person_id: currentSale.clientId ? Number(currentSale.clientId) : null,
                    payment_methods: paymentRows.map((row) => ({
                        payment_method_id: Number(row.payment_method_id),
                        amount: Number(row.amount),
                        payment_gateway_id: row.payment_gateway_id ? Number(row.payment_gateway_id) : null,
                        card_id: row.card_id ? Number(row.card_id) : null,
                        digital_wallet_id: row.digital_wallet_id ? Number(row.digital_wallet_id) : null,
                    })),
                    notes: document.getElementById('sale-notes')?.value || '',
                };

                const payButton = document.querySelector('button[onclick="processSaleNow()"]');
                if (payButton) {
                    payButton.disabled = true;
                    payButton.classList.add('opacity-70', 'cursor-not-allowed');
                }

                fetch(@json(route('admin.sales.process')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(payload)
                })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'No se pudo procesar la venta.');
                        }
                        currentSale = {
                            id: Date.now(),
                            clientId: defaultClient ? defaultClient.id : null,
                            clientName: defaultClient ? defaultClient.label : 'Publico General',
                            status: 'in_progress',
                            notes: '',
                            items: []
                        };
                        paymentRows = [];
                        db[activeKey] = currentSale;
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                        const notesInput = document.getElementById('sale-notes');
                        if (notesInput) notesInput.value = '';
                        renderTicket();
                        renderPaymentRows();
                        const clientInputEl = document.getElementById('client-autocomplete');
                        if (clientInputEl) {
                            clientInputEl.value = currentSale.clientName;
                            clientQuery = currentSale.clientName;
                        }
                        showNotification('Venta procesada correctamente');
                        setTimeout(() => {
                            window.location.href = @json($salesIndexUrl);
                        }, 500);
                    })
                    .catch((error) => {
                        showNotice(error.message || 'No se pudo procesar la venta.');
                    })
                    .finally(() => {
                        if (payButton) {
                            payButton.disabled = false;
                            payButton.classList.remove('opacity-70', 'cursor-not-allowed');
                        }
                    });
            }

            function goBack() {
                if (!currentSale.items.length) {
                    currentSale.items = [];
                    saveDB();
                    localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
                    window.location.href = @json($salesIndexUrl);
                    return;
                }

                fetch(@json(route('admin.sales.draft')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        items: currentSale.items.map((item) => ({ pId: item.pId, qty: Number(item.qty), price: Number(item.price), note: item.note || '' })),
                        document_type_id: Number(document.getElementById('document-type-select')?.value || 0) || null,
                        notes: document.getElementById('sale-notes')?.value || 'Venta guardada como borrador - pendiente de pago'
                    })
                }).finally(() => {
                    window.location.href = @json($salesIndexUrl);
                });
            }

            document.getElementById('product-search')?.addEventListener('input', (event) => {
                productSearch = String(event.target.value || '').trim().toLowerCase();
                renderProducts();
            });
            document.getElementById('clear-sale-button')?.addEventListener('click', clearSale);
            document.getElementById('add-payment-row-button')?.addEventListener('click', () => addPaymentRow());
            document.getElementById('summary-tab-button')?.addEventListener('click', () => setAsideTab('summary'));
            document.getElementById('payment-tab-button')?.addEventListener('click', () => setAsideTab('payment'));
            document.getElementById('sale-notes')?.addEventListener('input', (event) => {
                currentSale.notes = String(event.target.value || '');
                saveDB();
            });
            document.getElementById('client-autocomplete')?.addEventListener('focus', () => {
                clientQuery = document.getElementById('client-autocomplete')?.value || '';
                openClientDropdown();
            });
            document.getElementById('client-autocomplete')?.addEventListener('input', (event) => {
                clientQuery = String(event.target.value || '');
                clientOpen = true;
                clientCursor = 0;
                renderClientOptions();
                document.getElementById('client-options')?.classList.remove('hidden');
            });
            document.getElementById('client-autocomplete')?.addEventListener('keydown', (event) => {
                const clients = filteredClients();
                if (!clients.length) return;
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    clientCursor = clientCursor >= clients.length - 1 ? 0 : clientCursor + 1;
                    renderClientOptions();
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    clientCursor = clientCursor <= 0 ? clients.length - 1 : clientCursor - 1;
                    renderClientOptions();
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    selectClient(clients[clientCursor] || clients[0]);
                }
                if (event.key === 'Escape') {
                    closeClientDropdown();
                }
            });
            document.getElementById('client-clear-button')?.addEventListener('click', clearClient);

            document.addEventListener('click', (event) => {
                const wrapper = document.getElementById('client-selector');
                if (!wrapper) return;
                if (!wrapper.contains(event.target)) {
                    closeClientDropdown();
                }
            });

            const clientInput = document.getElementById('client-autocomplete');
            if (clientInput) {
                clientInput.value = currentSale.clientName || (defaultClient ? defaultClient.label : 'Publico General');
                clientQuery = clientInput.value;
            }
            const notesInput = document.getElementById('sale-notes');
            if (notesInput) {
                notesInput.value = currentSale.notes || '';
            }

            renderCategoryFilters();
            renderProducts();
            renderPaymentRows();
            renderTicket();
            setAsideTab('summary');

            window.goBack = goBack;
            window.processSaleNow = processSaleNow;
            window.updateQty = updateQty;
            window.setQty = setQty;
            window.hideNotification = hideNotification;
            window.hideStockError = hideStockError;
        })();
    </script>
@endsection
