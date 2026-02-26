@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
        {{-- Header Compacto --}}
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Cobrar Venta</h1>
                <a href="{{ route('admin.sales.create') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Volver
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3" style="height: calc(100vh - 160px);">
            {{-- Columna Izquierda: Resumen --}}
            <div class="lg:col-span-2 flex flex-col gap-3 overflow-hidden">
                                {{-- Cliente y Caja --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 shrink-0">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label for="client-id" class="mb-2 block text-xs font-semibold text-gray-900 dark:text-white">Cliente</label>
                            <select id="client-id"
                                class="w-full rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-blue-400">
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}" @selected((int) $person->id === (int) $defaultClientId)>{{ trim(($person->document_number ?? '') . ' - ' .($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="cash-register-id" class="mb-2 block text-xs font-semibold text-gray-900 dark:text-white">Caja</label>
                            <select id="cash-register-id"
                                class="w-full rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-blue-400">
                                @foreach ($cashRegisters as $cashRegister)
                                    <option value="{{ $cashRegister->id }}" @selected($cashRegister->status === 'A')>
                                        {{ $cashRegister->number }}{{ $cashRegister->status === 'A' ? ' (Activa)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Productos --}}
                <div
                    class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 flex-1 flex flex-col min-h-0 overflow-hidden">
                    <div class="mb-2 flex items-center justify-between shrink-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Productos</h2>
                        <span
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                            id="items-count">0 items</span>
                    </div>
                    <div id="items-list" class="space-y-1.5 flex-1 overflow-y-auto pr-1 custom-scrollbar">
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
                            <i class="fas fa-shopping-cart mb-2 text-2xl text-gray-400"></i>
                            <p class="text-xs text-gray-500 dark:text-gray-400">No hay productos en la orden</p>
                        </div>
                    </div>
                </div>

                {{-- Totales --}}
                <div
                    class="rounded-lg border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-blue-100 p-3 dark:border-blue-800 dark:from-blue-900/20 dark:to-blue-800/20 shrink-0">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Resumen</h3>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                            <span class="font-semibold text-gray-900 dark:text-white" id="subtotal">S/0.00</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-400">IGV</span>
                            <span class="font-semibold text-gray-900 dark:text-white" id="tax">S/0.00</span>
                        </div>
                        <div class="border-t border-blue-300 pt-1.5 dark:border-blue-700">
                            <div class="flex justify-between">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-blue-600 dark:text-blue-400"
                                    id="total">S/0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Columna Derecha: Pago (Sticky) --}}
            <div class="flex flex-col gap-3 lg:sticky lg:top-4 h-fit max-h-[calc(100vh-80px)] overflow-y-auto custom-scrollbar">
                {{-- Tipo de Documento --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                    <label class="mb-2 block text-xs font-semibold text-gray-900 dark:text-white">Tipo de Documento</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach ($documentTypes as $index => $documentType)
                            <button type="button"
                                class="doc-type-btn {{ $index === 0 ? 'doc-active' : '' }} w-full rounded-lg border-2 {{ $index === 0 ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-gray-50' }} p-2 text-left transition hover:bg-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                data-doc-type="{{ strtolower($documentType->name) }}" data-doc-id="{{ $documentType->id }}">
                                <div class="flex items-center gap-2">
                                    <i
                                        class="fas fa-file-alt text-base {{ $index === 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400' }}"></i>
                                    <div class="flex-1">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $documentType->name }}</div>
                                    </div>
                                    <i
                                        class="fas fa-check-circle text-sm {{ $index === 0 ? 'text-blue-600 dark:text-blue-400' : 'hidden text-blue-600 dark:text-blue-400' }}"></i>
                                </div>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" id="document-type-id" name="document_type_id"
                        value="{{ $documentTypes->first()?->id ?? '' }}">
                </div>

                {{-- Métodos de Pago Múltiples --}}
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-3 flex items-center justify-between">
                        <label class="block text-sm font-semibold text-gray-900 dark:text-white">Métodos de Pago</label>
                        <button type="button" id="add-payment-method-btn"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                            <i class="fas fa-plus mr-1"></i> Agregar
                            </button>
                    </div>
                    <div id="payment-methods-list" class="space-y-3">
                        {{-- Los métodos de pago se agregarán dinámicamente aquí --}}
                            </div>
                    {{-- Resumen de pagos --}}
                    <div id="payment-summary" class="mt-3 rounded-lg border-2 border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total pagado:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white" id="total-paid">S/0.00</span>
                        </div>
                        <div id="payment-remaining" class="mt-2 hidden rounded-lg bg-orange-50 p-2 dark:bg-orange-900/20">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-orange-700 dark:text-orange-400">Falta pagar:</span>
                                <span class="text-sm font-bold text-orange-700 dark:text-orange-400" id="remaining-amount">S/0.00</span>
                            </div>
                        </div>
                        <div id="payment-excess" class="mt-2 hidden rounded-lg bg-green-50 p-2 dark:bg-green-900/20">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-green-700 dark:text-green-400">Vuelto a devolver:</span>
                                <span class="text-sm font-bold text-green-700 dark:text-green-400" id="excess-amount">S/0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notas --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                    <label for="sale-notes" class="mb-1.5 block text-xs font-semibold text-gray-900 dark:text-white">Notas
                        (Opcional)</label>
                    <textarea id="sale-notes" rows="2" placeholder="Ej: Cliente pagó con billete de 50..."
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-blue-400"></textarea>
                </div>

                {{-- Botón Confirmar (Siempre visible) --}}
                <button type="button" id="confirm-btn"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-blue-700 active:scale-95 dark:bg-blue-700 dark:hover:bg-blue-800 shrink-0">
                    <i class="fas fa-check-circle mr-1.5"></i>
                    Confirmar y Cobrar
                </button>
            </div>
        </div>

        {{-- Modal para seleccionar método de pago --}}
        <div id="payment-method-selection-modal"
            class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50">
            <div class="mx-4 w-full max-w-2xl rounded-lg bg-white shadow-xl dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Seleccionar Método de Pago</h3>
                        <button type="button" id="close-payment-method-modal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($paymentMethods as $paymentMethod)
                            @php
                                $isCard = str_contains(strtolower($paymentMethod->description), 'tarjeta') || 
                                         str_contains(strtolower($paymentMethod->description), 'card');
                                $icon = $isCard ? 'fa-credit-card' : 
                                       (str_contains(strtolower($paymentMethod->description), 'efectivo') || str_contains(strtolower($paymentMethod->description), 'cash') ? 'fa-money-bill-wave' :
                                       (str_contains(strtolower($paymentMethod->description), 'yape') || str_contains(strtolower($paymentMethod->description), 'plin') ? 'fa-mobile-alt' : 'fa-wallet'));
                            @endphp
                            <button type="button"
                                class="pm-selection-btn rounded-lg border-2 border-gray-300 bg-gray-50 p-4 text-left transition hover:bg-blue-50 hover:border-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                data-method-id="{{ $paymentMethod->id }}"
                                data-method-name="{{ $paymentMethod->description }}"
                                data-is-card="{{ $isCard ? '1' : '0' }}">
                                <div class="flex items-center gap-3">
                                    <i class="fas {{ $icon }} text-2xl text-gray-600 dark:text-gray-400"></i>
                                    <div class="flex-1">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $paymentMethod->description }}
                                        </div>
                                    </div>
                                    <i class="fas fa-check-circle text-sm hidden text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal para seleccionar pasarela de pago y tarjeta --}}
        <div id="card-selection-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50">
            <div class="mx-4 w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Seleccionar Pasarela y Tarjeta</h3>
                        <button type="button" id="close-card-modal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4">
                    {{-- Pasarelas de Pago --}}
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-semibold text-gray-900 dark:text-white">
                            Pasarela de Pago
                        </label>

                        <div class="flex gap-3 overflow-x-auto custom-scrollbar pb-2">
                            @foreach ($paymentGateways as $gateway)
                                <button type="button"
                                    class="gateway-btn min-w-[220px] rounded-lg border-2 border-gray-300 bg-gray-50 p-3 text-left transition hover:bg-blue-50
                                       dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                    data-gateway-id="{{ $gateway->id }}"
                                    data-gateway-name="{{ $gateway->description }}">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-credit-card text-base text-gray-600 dark:text-gray-400"></i>

                                        <div class="flex-1">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $gateway->description }}
                                            </div>
                                        </div>

                                        <i class="fas fa-check-circle text-sm hidden text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    {{-- Tarjetas --}}
                    <div>
                        <label class="mb-3 block text-sm font-semibold text-gray-900 dark:text-white">
                            Tipo de Tarjeta
                        </label>

                        {{-- Tarjetas de Crédito --}}
                        <div class="mb-4">
                            <label class="mb-2 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Crédito
                            </label>
                            <div class="flex gap-3 overflow-x-auto items-center custom-scrollbar pb-2">
                                @foreach ($cards as $card)
                                    @if ($card->type == 'C')
                                        <button type="button"
                                            class="card-btn min-w-[220px] rounded-lg border-2 border-gray-300 bg-gray-50 p-3 text-left transition hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                            data-card-id="{{ $card->id }}" data-card-name="{{ $card->description }}">
                                            <div class="flex items-center gap-2">
                                                @if ($card->icon)
                                                    <i class="{{ $card->icon }} text-base text-gray-600 dark:text-gray-400"></i>
                                                @else
                                                    <i class="fas fa-credit-card text-base text-gray-600 dark:text-gray-400"></i>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                        {{ $card->description }}
                                                    </div>
                                                </div>
                                                <i class="fas fa-check-circle text-sm hidden text-blue-600 dark:text-blue-400"></i>
                                            </div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Tarjetas de Débito --}}
                        <div>
                            <label class="mb-2 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Débito
                            </label>
                            <div class="flex gap-3 overflow-x-auto items-center custom-scrollbar pb-2">
                                @foreach ($cards as $card)
                                    @if ($card->type == 'D')
                                        <button type="button"
                                            class="card-btn min-w-[220px] rounded-lg border-2 border-gray-300 bg-gray-50 p-3 text-left transition hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                            data-card-id="{{ $card->id }}" data-card-name="{{ $card->description }}">
                                            <div class="flex items-center gap-2">
                                                @if ($card->icon)
                                                    <i class="{{ $card->icon }} text-base text-gray-600 dark:text-gray-400"></i>
                                                @else
                                                    <i class="fas fa-credit-card text-base text-gray-600 dark:text-gray-400"></i>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                        {{ $card->description }}
                                                    </div>
                                                </div>
                                                <i class="fas fa-check-circle text-sm hidden text-blue-600 dark:text-blue-400"></i>
                                            </div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex justify-end gap-2">
                        <button type="button" id="cancel-card-selection"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancelar
                        </button>
                        <button type="button" id="confirm-card-selection"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800"
                            disabled>
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Notificación del sistema --}}
    <div id="payment-notification" 
        class="fixed top-24 right-8 z-50 transform transition-all duration-500 translate-x-[150%] opacity-0">
        <div id="notification-content" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-xl shadow-2xl border border-blue-400/30 backdrop-blur-sm flex items-center gap-4 min-w-[320px]">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                <i id="notification-icon" class="fas fa-info-circle text-2xl"></i>
            </div>
            <div class="flex-1">
                <p id="notification-title" class="font-bold text-sm">Notificación</p>
                <p id="notification-message" class="text-xs text-blue-50 mt-0.5">Mensaje</p>
            </div>
            <button onclick="hidePaymentNotification()" class="text-white/80 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <style>
        .pm-btn.pm-active {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .dark .pm-btn.pm-active {
            background: linear-gradient(135deg, rgba(30, 58, 138, .55) 0%, rgba(15, 23, 42, 1) 100%);
        }

        .doc-type-btn.doc-active {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .dark .doc-type-btn.doc-active {
            background: linear-gradient(135deg, rgba(30, 58, 138, .55) 0%, rgba(15, 23, 42, 1) 100%);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #475569;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        #card-selection-modal {
            backdrop-filter: blur(2px);
        }

        #card-selection-modal .gateway-btn.border-blue-500,
        #card-selection-modal .card-btn.border-blue-500 {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .dark #card-selection-modal .gateway-btn.border-blue-500,
        .dark #card-selection-modal .card-btn.border-blue-500 {
            background: linear-gradient(135deg, rgba(30, 58, 138, .55) 0%, rgba(15, 23, 42, 1) 100%);
        }
        .notification-show {
            transform: translateX(0) !important;
            opacity: 1 !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Documentos disponibles (se cargarán desde el servidor)
            const documentTypes = @json($documentTypes ?? []);
            const paymentMethods = @json($paymentMethods ?? []);
            const paymentGateways = @json($paymentGateways ?? []);
            const cards = @json($cards ?? []);
            const defaultClientId = @json($defaultClientId ?? 4);
            const productsMap = @json($products ?? []);
            const productBranches = @json($productBranches ?? []);
            const taxRateByProductId = new Map();
            const defaultTaxPct = 18;
            productBranches.forEach((pb) => {
                const pid = Number(pb.product_id);
                if (!Number.isNaN(pid)) {
                    taxRateByProductId.set(pid, pb.tax_rate != null ? Number(pb.tax_rate) : defaultTaxPct);
                }
            });
            
            // Debug: verificar que los métodos de pago se carguen
            console.log('Métodos de pago cargados:', paymentMethods);

            const docButtons = document.querySelectorAll('.doc-type-btn');
            const totalElement = document.getElementById('total');
            const documentTypeInput = document.getElementById('document-type-id');
            const clientInput = document.getElementById('client-id');
            const cashRegisterInput = document.getElementById('cash-register-id');
            const paymentMethodsList = document.getElementById('payment-methods-list');
            const addPaymentMethodBtn = document.getElementById('add-payment-method-btn');
            const paymentMethodSelectionModal = document.getElementById('payment-method-selection-modal');
            const closePaymentMethodModalBtn = document.getElementById('close-payment-method-modal');
            const cardSelectionModal = document.getElementById('card-selection-modal');
            const closeCardModal = document.getElementById('close-card-modal');
            const cancelCardSelection = document.getElementById('cancel-card-selection');
            const confirmCardSelection = document.getElementById('confirm-card-selection');
            // Estos se actualizarán cuando se abra el modal
            let gatewayButtons = document.querySelectorAll('.gateway-btn');
            let cardButtons = document.querySelectorAll('.card-btn');
            const pmSelectionButtons = document.querySelectorAll('.pm-selection-btn');

            let paymentMethodsData = []; // Array para almacenar los métodos de pago agregados
            let currentEditingIndex = -1; // Índice del método de pago que se está editando
            let selectedGatewayId = null;
            let selectedCardId = null;
            let cardModalListenersSetup = false; // Bandera para evitar configurar listeners múltiples veces

            // Función para formatear dinero
            function fmtMoney(n) {
                return 'S/' + (Number(n) || 0).toFixed(2);
            }

            // Función para calcular el total pagado
            function calculateTotalPaid() {
                return paymentMethodsData.reduce((sum, pm) => sum + (parseFloat(pm.amount) || 0), 0);
            }

            // Función para actualizar el resumen de pagos
            function  updatePaymentSummary() {
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                const remaining = total - totalPaid;
                const excess = totalPaid - total;

                document.getElementById('total-paid').textContent = fmtMoney(totalPaid);
                
                const remainingDiv = document.getElementById('payment-remaining');
                const excessDiv = document.getElementById('payment-excess');
                
                if (remaining > 0.01) {
                    remainingDiv.classList.remove('hidden');
                    document.getElementById('remaining-amount').textContent = fmtMoney(remaining);
                } else {
                    remainingDiv.classList.add('hidden');
                }
                
                if (excess > 0.01) {
                    excessDiv.classList.remove('hidden');
                    document.getElementById('excess-amount').textContent = fmtMoney(excess);
                } else {
                    excessDiv.classList.add('hidden');
                }
            }

            // Función para renderizar un método de pago
            function renderPaymentMethod(index, paymentMethod) {
                const isCard = paymentMethod.isCard || false;
                const methodName = paymentMethod.methodName || '';
                const amount = paymentMethod.amount || 0;
                const methodId = paymentMethod.methodId || null;
                const gatewayId = paymentMethod.gatewayId || null;
                const cardId = paymentMethod.cardId || null;
                const gatewayName = paymentMethod.gatewayName || '';
                const cardName = paymentMethod.cardName || '';

                // Determinar el icono según el método
                const getMethodIcon = (methodDesc) => {
                    const desc = (methodDesc || '').toLowerCase();
                    if (desc.includes('tarjeta') || desc.includes('card')) return 'fa-credit-card';
                    if (desc.includes('efectivo') || desc.includes('cash')) return 'fa-money-bill-wave';
                    if (desc.includes('yape') || desc.includes('plin')) return 'fa-mobile-alt';
                    if (desc.includes('transferencia') || desc.includes('transfer')) return 'fa-exchange-alt';
                    return 'fa-wallet';
                };

                const methodIcon = getMethodIcon(methodName);
                const hasCardInfo = isCard && gatewayName && cardName;

                const cardInfo = isCard ? `
                    <div class="mb-2 rounded-lg border-2 ${hasCardInfo ? 'border-green-200 bg-green-50' : 'border-orange-200 bg-orange-50'} p-2 dark:${hasCardInfo ? 'border-green-800 bg-green-900/20' : 'border-orange-800 bg-orange-900/20'}">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Pasarela:</p>
                                <p class="text-sm font-bold ${hasCardInfo ? 'text-green-700 dark:text-green-400' : 'text-orange-700 dark:text-orange-400'} gateway-name-${index}">${gatewayName || 'No seleccionada'}</p>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tarjeta:</p>
                                <p class="text-sm font-bold ${hasCardInfo ? 'text-green-700 dark:text-green-400' : 'text-orange-700 dark:text-orange-400'} card-name-${index}">${cardName || 'No seleccionada'}</p>
                            </div>
                            <button type="button" class="select-card-btn ml-2 rounded-lg ${hasCardInfo ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'} px-3 py-1.5 text-xs font-semibold text-white transition" data-index="${index}">
                                <i class="fas fa-${hasCardInfo ? 'edit' : 'plus'}"></i>
                            </button>
                        </div>
                    </div>
                ` : '';

                return `
                    <div class="payment-method-item rounded-lg border-2 border-gray-300 bg-white p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow" data-index="${index}">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" class="payment-method-btn flex-1 rounded-lg border-2 ${isCard ? 'border-blue-500 bg-blue-50' : 'border-gray-300'} p-2.5 text-left transition hover:${isCard ? 'bg-blue-100' : 'bg-gray-100'} dark:${isCard ? 'border-blue-600 bg-blue-900/20' : 'border-gray-600'} dark:hover:${isCard ? 'bg-blue-900/30' : 'bg-gray-600'}" data-index="${index}">
                                <div class="flex items-center gap-2">
                                    <i class="fas ${methodIcon} text-lg ${isCard ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'}"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">${methodName || 'Seleccionar método'}</p>
                                        ${isCard && !hasCardInfo ? '<p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">Configurar pasarela y tarjeta</p>' : ''}
                                    </div>
                                    <i class="fas fa-chevron-down text-xs "></i>
                                </div>
                            </button>
                            <button type="button" class="remove-payment-method ml-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 transition" data-index="${index}" title="Eliminar método">
                                  <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                        ${cardInfo}
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-600 dark:text-gray-400">S/</span>
                                <input type="number" step="0.01" min="0" class="payment-amount-input w-full text-right rounded-lg border-2 border-gray-300 bg-white pl-8 pr-3 py-2.5 text-base font-bold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-blue-400" 
                                    value="${amount > 0 ? amount.toFixed(2) : '0.00'}" data-index="${index}" placeholder="0.00">
                            </div>
                            <button type="button" class="fill-remaining-btn rounded-lg bg-blue-100 px-3 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50" data-index="${index}" title="Completar con lo que falta">
                                <i class="ri-money-dollar-circle-line"></i>
                            </button>
                        </div>
                        <input type="hidden" class="payment-method-id" value="${methodId || ''}" data-index="${index}">
                        <input type="hidden" class="payment-gateway-id" value="${gatewayId || ''}" data-index="${index}">
                        <input type="hidden" class="payment-card-id" value="${cardId || ''}" data-index="${index}">
                    </div>
                `;
            }

            // Función para obtener el monto que falta pagar
            function getRemainingAmount() {
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                return Math.max(0, total - totalPaid);
            }

            // Función para autocompletar un método de pago con lo que falta
            function fillRemainingAmount(index) {
                const remaining = getRemainingAmount();
                if (remaining > 0 && paymentMethodsData[index]) {
                    paymentMethodsData[index].amount = remaining;
                    updatePaymentMethodsList();
                    updatePaymentSummary();
                }
            }

            // Función para agregar un método de pago
            function addPaymentMethod() {
                // Verificar que haya métodos de pago disponibles
                if (!paymentMethods || paymentMethods.length === 0) {
                    console.error('No hay métodos de pago disponibles');
                    if (typeof showNotification === 'function') {
                        showNotification('Error', 'No hay métodos de pago disponibles', 'error');
                    }
                    return;
                }
                
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                const remaining = total - totalPaid;
                
                // Buscar el primer método que no sea tarjeta, o usar el primero disponible
                const defaultMethod = paymentMethods.find(pm => {
                    const desc = (pm.description || '').toLowerCase();
                    return !desc.includes('tarjeta') && !desc.includes('card');
                }) || paymentMethods[0];
                
                const isCard = defaultMethod && (defaultMethod.description.toLowerCase().includes('tarjeta') || defaultMethod.description.toLowerCase().includes('card'));
                
                // Si es el primer método, usar el total completo; si no, usar lo que falta
                const initialAmount = paymentMethodsData.length === 0 ? total : (remaining > 0 ? remaining : 0);
                
                const newPaymentMethod = {
                    methodId: defaultMethod?.id || null,
                    methodName: defaultMethod?.description || 'Seleccionar método',
                    isCard: isCard,
                    amount: initialAmount,
                    gatewayId: null,
                    cardId: null,
                    gatewayName: '',
                    cardName: ''
                };
                
                paymentMethodsData.push(newPaymentMethod);
                updatePaymentMethodsList();
                updatePaymentSummary();
            }

            // Función para eliminar un método de pago
            function removePaymentMethod(index) {
                paymentMethodsData.splice(index, 1);
                updatePaymentMethodsList();
                updatePaymentSummary();
            }

            // Función para abrir modal de selección de método de pago
            function openPaymentMethodModal(index) {
                currentEditingIndex = index;
                paymentMethodSelectionModal.classList.remove('hidden');
                paymentMethodSelectionModal.classList.add('flex');
                
                // Marcar el método actual si existe
                if (paymentMethodsData[index]) {
                    const currentMethodId = paymentMethodsData[index].methodId;
                    pmSelectionButtons.forEach(btn => {
                        if (btn.dataset.methodId == currentMethodId) {
                            btn.classList.remove('border-gray-300', 'bg-gray-50');
                            btn.classList.add('border-blue-500', 'bg-blue-50');
                            const checkIcon = btn.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.remove('hidden');
                        } else {
                            btn.classList.remove('border-blue-500', 'bg-blue-50');
                            btn.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = btn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                        }
                    });
                }
            }

            // Función para cerrar modal de selección de método de pago
            function closePaymentMethodModal(resetIndex = true) {
                paymentMethodSelectionModal.classList.add('hidden');
                paymentMethodSelectionModal.classList.remove('flex');
                // Solo resetear el índice si no se va a abrir otro modal
                if (resetIndex) {
                    currentEditingIndex = -1;
                }
            }

            // Event listeners para selección de método de pago
            pmSelectionButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const methodId = parseInt(this.dataset.methodId);
                    const methodName = this.dataset.methodName;
                    const isCard = this.dataset.isCard === '1';
                    
                    if (currentEditingIndex >= 0 && paymentMethodsData[currentEditingIndex]) {
                        paymentMethodsData[currentEditingIndex].methodId = methodId;
                        paymentMethodsData[currentEditingIndex].methodName = methodName;
                        paymentMethodsData[currentEditingIndex].isCard = isCard;
                        
                        if (!isCard) {
                            // Si no es tarjeta, limpiar datos de tarjeta
                            paymentMethodsData[currentEditingIndex].gatewayId = null;
                            paymentMethodsData[currentEditingIndex].cardId = null;
                            paymentMethodsData[currentEditingIndex].gatewayName = '';
                            paymentMethodsData[currentEditingIndex].cardName = '';
                            updatePaymentMethodsList();
                            closePaymentMethodModal();
                        } else {
                            // Si es tarjeta, siempre abrir modal de pasarela/tarjeta
                            // Guardar el índice antes de cerrar el modal
                            const savedIndex = currentEditingIndex;
                            closePaymentMethodModal(false);
                            // Asegurarse de que el índice se mantenga
                            currentEditingIndex = savedIndex;
                            setTimeout(() => {
                                console.log('Abriendo modal de tarjeta con índice:', currentEditingIndex);
                                openCardModal();
                            }, 200);
                        }
                    }
                });
            });

            // Cerrar modal al hacer clic fuera
            paymentMethodSelectionModal?.addEventListener('click', function(e) {
                if (e.target === paymentMethodSelectionModal) {
                    closePaymentMethodModal();
                }
            });

            closePaymentMethodModalBtn?.addEventListener('click', closePaymentMethodModal);

            // Función para actualizar la lista de métodos de pago
            function updatePaymentMethodsList() {
                paymentMethodsList.innerHTML = paymentMethodsData.map((pm, index) => renderPaymentMethod(index, pm)).join('');
                
                // Agregar event listeners
                paymentMethodsList.querySelectorAll('.payment-method-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        openPaymentMethodModal(index);
                    });
                });
                
                paymentMethodsList.querySelectorAll('.payment-amount-input').forEach(input => {
                    input.addEventListener('input', function() {
                        const index = parseInt(this.dataset.index);
                        paymentMethodsData[index].amount = parseFloat(this.value) || 0;
                        updatePaymentSummary();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.remove-payment-method').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        removePaymentMethod(index);
                    });
                });
                
                paymentMethodsList.querySelectorAll('.select-card-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        currentEditingIndex = index;
                        openCardModal();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.fill-remaining-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        fillRemainingAmount(index);
                    });
                });
            }

            // Botón para agregar método de pago
            addPaymentMethodBtn?.addEventListener('click', addPaymentMethod);

            // Abrir modal de selección de tarjeta
            function openCardModal() {
                
                // Actualizar referencias a los botones (por si el DOM cambió)
                gatewayButtons = document.querySelectorAll('.gateway-btn');
                cardButtons = document.querySelectorAll('.card-btn');
                
                cardSelectionModal.classList.remove('hidden');
                cardSelectionModal.classList.add('flex');
                
                // Si estamos editando un método de pago existente, restaurar sus valores
                if (currentEditingIndex >= 0 && paymentMethodsData[currentEditingIndex]) {
                    const pm = paymentMethodsData[currentEditingIndex];
                    selectedGatewayId = pm.gatewayId ? String(pm.gatewayId) : null;
                    selectedCardId = pm.cardId ? String(pm.cardId) : null;
                    console.log('Restaurando valores del método:', pm, { selectedGatewayId, selectedCardId });
                } else {
                    selectedGatewayId = null;
                    selectedCardId = null;
                    console.warn('currentEditingIndex es inválido o no hay método de pago:', currentEditingIndex);
                }
                
                // Marcar botones según valores guardados
                gatewayButtons.forEach(b => {
                    if (b.dataset.gatewayId && b.dataset.gatewayId == selectedGatewayId) {
                        b.classList.remove('border-gray-300', 'bg-gray-50');
                        b.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    } else {
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    }
                });
                
                cardButtons.forEach(b => {
                    if (b.dataset.cardId && b.dataset.cardId == selectedCardId) {
                        b.classList.remove('border-gray-300', 'bg-gray-50');
                        b.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    } else {
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    }
                });
                
                // Configurar event listeners para los botones (por si no se configuraron antes)
                setupCardModalListeners();
                
                updateConfirmButton();
            }
            
            // Función para configurar los event listeners del modal de tarjeta usando event delegation
            function setupCardModalListeners() {
                // Solo configurar una vez
                if (cardModalListenersSetup) return;
                cardModalListenersSetup = true;
                
                // Usar event delegation en el modal para manejar clics en pasarelas y tarjetas
                // Esto evita problemas con listeners duplicados
                cardSelectionModal.addEventListener('click', function(e) {
                    // No procesar si se hace clic en el botón de confirmar o cancelar
                    if (e.target.closest('#confirm-card-selection') || e.target.closest('#cancel-card-selection') || e.target.closest('#close-card-modal')) {
                        return;
                    }
                    
                    // Manejar clic en pasarela
                    const gatewayBtn = e.target.closest('.gateway-btn');
                    if (gatewayBtn && gatewayBtn.dataset.gatewayId) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Actualizar referencias
                        gatewayButtons = document.querySelectorAll('.gateway-btn');
                        
                        // Deseleccionar otros
                        gatewayButtons.forEach(b => {
                            if (!b.dataset.gatewayId) return;
                            b.classList.remove('border-blue-500', 'bg-blue-50');
                            b.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = b.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.add('hidden');
                        });
                        
                        // Seleccionar este
                        gatewayBtn.classList.remove('border-gray-300', 'bg-gray-50');
                        gatewayBtn.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = gatewayBtn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                        selectedGatewayId = gatewayBtn.dataset.gatewayId;
                        console.log('Pasarela seleccionada:', selectedGatewayId, gatewayBtn.dataset.gatewayName);
                        updateConfirmButton();
                        return;
                    }
                    
                    // Manejar clic en tarjeta
                    const cardBtn = e.target.closest('.card-btn');
                    if (cardBtn && cardBtn.dataset.cardId) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Actualizar referencias
                        cardButtons = document.querySelectorAll('.card-btn');
                        
                        // Deseleccionar otros
                        cardButtons.forEach(b => {
                            if (!b.dataset.cardId) return;
                            b.classList.remove('border-blue-500', 'bg-blue-50');
                            b.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = b.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.add('hidden');
                        });
                        
                        // Seleccionar este
                        cardBtn.classList.remove('border-gray-300', 'bg-gray-50');
                        cardBtn.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = cardBtn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                        selectedCardId = cardBtn.dataset.cardId;
                        console.log('Tarjeta seleccionada:', selectedCardId, cardBtn.dataset.cardName);
                        updateConfirmButton();
                        return;
                    }
                });
            }

            // Cerrar modal
            function closeModal() {
                cardSelectionModal.classList.add('hidden');
                cardSelectionModal.classList.remove('flex');
            }

            closeCardModal?.addEventListener('click', closeModal);
            cancelCardSelection?.addEventListener('click', function() {
                closeModal();
                currentEditingIndex = -1;
            });

            // Cerrar modal al hacer clic fuera
            cardSelectionModal?.addEventListener('click', function(e) {
                if (e.target === cardSelectionModal) {
                    closeModal();
                }
            });

            // Los event listeners ahora se configuran en setupCardModalListeners()
            // que se llama cuando se abre el modal

            // Actualizar estado del botón confirmar
            function updateConfirmButton() {
                if (selectedGatewayId && selectedCardId) {
                    confirmCardSelection.disabled = false;
                    confirmCardSelection.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    confirmCardSelection.disabled = true;
                    confirmCardSelection.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }

            // Confirmar selección de tarjeta
            confirmCardSelection?.addEventListener('click', function() {
                
                if (selectedGatewayId && selectedCardId && currentEditingIndex >= 0) {
                    // Actualizar referencias a los botones antes de obtener los nombres
                    gatewayButtons = document.querySelectorAll('.gateway-btn');
                    cardButtons = document.querySelectorAll('.card-btn');
                    
                    // Actualizar el método de pago actual
                    const pm = paymentMethodsData[currentEditingIndex];
                    pm.gatewayId = selectedGatewayId;
                    pm.cardId = selectedCardId;
                    
                    // Obtener nombres usando las referencias actualizadas
                    const gatewayBtn = Array.from(gatewayButtons).find(b => b.dataset.gatewayId == selectedGatewayId);
                    const cardBtn = Array.from(cardButtons).find(b => b.dataset.cardId == selectedCardId);

                    if (gatewayBtn) {
                        pm.gatewayName = gatewayBtn.dataset.gatewayName || '';
                    }
                    if (cardBtn) {
                        pm.cardName = cardBtn.dataset.cardName || '';
                    }

                    // Actualizar la lista
                    updatePaymentMethodsList();
                    closeModal();
                    currentEditingIndex = -1;
                    
                    // Limpiar selección
                    selectedGatewayId = null;
                    selectedCardId = null;
                } else {
                }
            });

            // Tipo de documento
            docButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    docButtons.forEach(b => {
                        b.classList.remove('doc-active');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.querySelector('.fa-file-alt').classList.remove('text-blue-600',
                            'dark:text-blue-400');
                        b.querySelector('.fa-file-alt').classList.add('text-gray-600',
                            'dark:text-gray-400');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    });
                    this.classList.add('doc-active');
                    this.classList.remove('border-gray-300', 'bg-gray-50');
                    this.classList.add('border-blue-500', 'bg-blue-50');
                    this.querySelector('.fa-file-alt').classList.remove('text-gray-600',
                        'dark:text-gray-400');
                    this.querySelector('.fa-file-alt').classList.add('text-blue-600',
                        'dark:text-blue-400');
                    const checkIcon = this.querySelector('.fa-check-circle');
                    if (checkIcon) checkIcon.classList.remove('hidden');

                    // Obtener el ID directamente del atributo data-doc-id
                    const docId = this.dataset.docId;
                    if (docId && documentTypeInput) {
                        documentTypeInput.value = docId;
                    }
                });
            });


            // Cargar orden desde localStorage o desde el servidor (si es borrador)
            const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
            const draftSaleFromServer = @json($draftSale ?? null);
            
            let sale = null;
            
            // Filtrar ítems válidos: pId y qty > 0, y que el producto exista en la BD (productsMap)
            function validItems(items) {
                if (!Array.isArray(items)) return [];
                return items.filter(it => {
                    const id = it.pId ?? it.id;
                    const qty = Number(it.qty) || 0;
                    if (id == null || id === '' || Number.isNaN(Number(id)) || qty <= 0) return false;
                    // Excluir productos que no existen en la BD (evita "Producto #5" fantasma)
                    const idStr = String(id);
                    const exists = productsMap && (Object.prototype.hasOwnProperty.call(productsMap, idStr) || Object.prototype.hasOwnProperty.call(productsMap, Number(id)));
                    return !!exists;
                });
            }

            // Si hay un borrador del servidor, usarlo
            if (draftSaleFromServer && draftSaleFromServer.items && draftSaleFromServer.items.length > 0) {
                sale = {
                    id: draftSaleFromServer.id,
                    number: draftSaleFromServer.number,
                    clientName: draftSaleFromServer.clientName || 'Público General',
                    items: validItems(draftSaleFromServer.items),
                    status: 'draft',
                    notes: draftSaleFromServer.notes || '',
                    pendingAmount: draftSaleFromServer.pendingAmount || 0
                };
            } else {
                // Si no, intentar cargar desde localStorage
                const db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                const activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                const fromStorage = activeKey ? db[activeKey] : null;
                if (fromStorage && Array.isArray(fromStorage.items)) {
                    sale = { ...fromStorage, items: validItems(fromStorage.items) };
                    // Actualizar localStorage con ítems filtrados para no volver a cargar fantasmas
                    if (sale.items.length !== fromStorage.items.length && activeKey) {
                        db[activeKey] = sale;
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                    }
                } else {
                    sale = fromStorage;
                }
            }

            function fmtMoney(n) {
                return 'S/' + (Number(n) || 0).toFixed(2);
            }

            // Hacer fmtMoney disponible globalmente
            window.fmtMoney = fmtMoney;
            function hydratePaymentMethodsFromSale(total) {
                if (!sale || !Array.isArray(sale.payment_methods) || sale.payment_methods.length === 0) {
                    return false;
                }

                const normalized = sale.payment_methods
                    .map((pm) => {
                        const methodId = Number(pm.payment_method_id ?? pm.methodId);
                        if (!methodId) return null;
                        const catalogMethod = paymentMethods.find((m) => Number(m.id) === methodId);
                        if (!catalogMethod) return null;

                        const methodName = catalogMethod.description || '';
                        const isCard = methodName.toLowerCase().includes('tarjeta') || methodName.toLowerCase().includes('card');

                        return {
                            methodId,
                            methodName,
                            isCard,
                            amount: Number(pm.amount ?? 0),
                            gatewayId: pm.payment_gateway_id ? Number(pm.payment_gateway_id) : null,
                            cardId: pm.card_id ? Number(pm.card_id) : null,
                            gatewayName: '',
                            cardName: '',
                        };
                    })
                    .filter(Boolean);

                if (normalized.length === 0) {
                    return false;
                }

                const sum = normalized.reduce((acc, item) => acc + (Number(item.amount) || 0), 0);
                if (sum <= 0) {
                    normalized[0].amount = Number(total || 0);
                }

                paymentMethodsData = normalized;
                updatePaymentMethodsList();
                return true;
            }

            function renderSale() {
                if (!sale || !Array.isArray(sale.items) || sale.items.length === 0) {
                    window.location.href = "{{ route('admin.sales.create') }}";
                    return;
                }

                if (clientInput) {
                    clientInput.value = sale.clientId ? String(sale.clientId) : String(defaultClientId);
                }

                const totalItems = sale.items.reduce((sum, it) => sum + (Number(it.qty) || 0), 0);
                document.getElementById('items-count').textContent = `${totalItems} items`;

                let subtotal = 0;
                const rows = sale.items.map((it) => {
                    const qty = Number(it.qty) || 0;
                    // Buscar el nombre del producto: primero en it.name, luego en productsMap, luego usar ID
                    const description = it.name || productsMap[it.pId] || `Producto #${it.pId}`;
                    const price = Number(it.price) || 0;
                    const lineTotal = qty * price;
                    subtotal += lineTotal;
                    const safeNote = (it.note ?? it.comment ?? '') || '';
                    return `
                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-2 dark:border-gray-700 dark:bg-gray-700">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">${description}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${qty} x ${fmtMoney(price)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${safeNote}</p>
                    </div>
                    <p class="ml-2 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">${fmtMoney(lineTotal)}</p>
                </div>
            `;
                }).join('');

                document.getElementById('items-list').innerHTML = rows;

                // Calcular subtotal e IGV por producto según su tasa (del sistema o del ítem si es borrador).
                let subtotalBase = 0;
                let tax = 0;
                sale.items.forEach((it) => {
                    const itemTotal = (Number(it.qty) || 0) * (Number(it.price) || 0);
                    const taxPct = it.tax_rate != null ? Number(it.tax_rate) : (taxRateByProductId.get(Number(it.pId)) ?? defaultTaxPct);
                    const taxVal = taxPct / 100;
                    const itemSubtotal = taxVal > 0 ? itemTotal / (1 + taxVal) : itemTotal;
                    subtotalBase += itemSubtotal;
                    tax += itemTotal - itemSubtotal;
                });
                const total = subtotalBase + tax;

                document.getElementById('subtotal').textContent = fmtMoney(subtotalBase);
                document.getElementById('tax').textContent = fmtMoney(tax);
                document.getElementById('total').textContent = fmtMoney(total);

                // Inicializar el primer método de pago con el total
                const preloaded = hydratePaymentMethodsFromSale(total);
                if (!preloaded && paymentMethodsData.length === 0) {
                    addPaymentMethod();
                }
                updatePaymentSummary();
                
                // Debug: verificar que se haya agregado el método
                console.log('Métodos de pago después de inicializar:', paymentMethodsData);
                
                // Si es un borrador, establecer las notas
                if (sale.notes && document.getElementById('sale-notes')) {
                    const notesText = sale.notes.replace(' [BORRADOR]', '').trim();
                    document.getElementById('sale-notes').value = notesText;
                }
            }

            renderSale();

            // El tipo de documento ya está establecido en el HTML con el primer valor
            // No es necesario hacer clic automático

            // Funciones de notificación
            let notificationTimeout;
            
            function showNotification(title, message, type = 'info') {
                const notification = document.getElementById('payment-notification');
                const content = document.getElementById('notification-content');
                const icon = document.getElementById('notification-icon');
                const titleEl = document.getElementById('notification-title');
                const messageEl = document.getElementById('notification-message');
                
                if (!notification || !content || !icon || !titleEl || !messageEl) return;
                
                // Limpiar timeout anterior
                if (notificationTimeout) {
                    clearTimeout(notificationTimeout);
                }
                
                // Configurar colores según el tipo
                const colors = {
                    success: 'from-green-500 to-emerald-600 border-green-400/30',
                    error: 'from-red-500 to-red-600 border-red-400/30',
                    warning: 'from-amber-500 to-orange-600 border-amber-400/30',
                    info: 'from-blue-500 to-blue-600 border-blue-400/30'
                };
                
                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                
                content.className = `bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-2xl border backdrop-blur-sm flex items-center gap-4 min-w-[320px]`;
                icon.className = `fas ${icons[type]} text-2xl`;
                titleEl.textContent = title;
                messageEl.textContent = message;
                
                notification.classList.add('notification-show');
                
                notificationTimeout = setTimeout(() => {
                    hidePaymentNotification();
                }, 5000);
            }
            
            function hidePaymentNotification() {
                const notification = document.getElementById('payment-notification');
                if (notification) {
                    notification.classList.remove('notification-show');
                }
            }

            function showErrorModal(message, title = 'No se pudo completar la venta') {
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        icon: 'error',
                        title,
                        text: message,
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }
                showNotification('Error', message, 'error');
            }

            // Confirmar pago
            document.getElementById('confirm-btn')?.addEventListener('click', function() {
                const docTypeId = documentTypeInput?.value;
                if (!docTypeId) {
                    showNotification('Error', 'Selecciona un tipo de documento', 'error');
                    return;
                }
                const cashRegisterId = cashRegisterInput?.value;
                if (!cashRegisterId) {
                    showNotification('Error', 'Selecciona una caja', 'error');
                    return;
                }

                const totalText = (totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim();
                const total = parseFloat(totalText) || 0;
                const totalPaid = calculateTotalPaid();

                // Validar que haya al menos un método de pago
                if (paymentMethodsData.length === 0) {
                    showNotification('Error', 'Agrega al menos un método de pago', 'error');
                    return;
                }

                // Validar que la suma de los métodos de pago sea igual al total
                if (Math.abs(totalPaid - total) > 0.01) {
                    showNotification('Error', `La suma de los métodos de pago (${fmtMoney(totalPaid)}) debe ser igual al total (${fmtMoney(total)})`, 'error');
                        return;
                    }

                // Validar que todos los métodos de tarjeta tengan pasarela y tarjeta seleccionadas
                for (let i = 0; i < paymentMethodsData.length; i++) {
                    const pm = paymentMethodsData[i];
                    if (pm.isCard) {
                        if (!pm.gatewayId || !pm.cardId) {
                            showNotification('Error', `El método de pago "${pm.methodName}" requiere seleccionar pasarela y tarjeta`, 'error');
                            currentEditingIndex = i;
                        openCardModal();
                        return;
                        }
                    }
                }

                if (!sale || !Array.isArray(sale.items) || sale.items.length === 0) {
                    showNotification('Error', 'No hay una orden activa', 'error');
                    setTimeout(() => {
                        window.location.href = "{{ route('admin.sales.index') }}";
                    }, 2000);
                    return;
                }

                const payload = {
                    items: sale.items.map(it => ({
                        pId: it.pId ?? it.id,
                        name: it.name,
                        qty: Number(it.qty) || 0,
                        price: Number(it.price) || 0,
                        note: String(it.note ?? it.comment ?? '').trim(),
                    })),
                    document_type_id: parseInt(docTypeId),
                    cash_register_id: parseInt(cashRegisterId),
                    person_id: clientInput?.value ? parseInt(clientInput.value) : null,
                    payment_methods: paymentMethodsData.map(pm => ({
                        payment_method_id: pm.methodId,
                        amount: parseFloat(pm.amount) || 0,
                        payment_gateway_id: pm.gatewayId ? parseInt(pm.gatewayId) : null,
                        card_id: pm.cardId ? parseInt(pm.cardId) : null,
                    })),
                    notes: document.getElementById('sale-notes')?.value || '',
                };
                
                // Si es un borrador, agregar el movement_id para actualizar en lugar de crear
                if (sale.id && sale.status === 'draft') {
                    payload.movement_id = sale.id;
                }

                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Procesando...';

                fetch('{{ route('admin.sales.process') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(async r => {
                        const contentType = r.headers.get('content-type') || '';

                        if (contentType.includes('application/json')) {
                            const data = await r.json();
                            if (!r.ok) {
                                let errorMessage = data.message || data.error || 'Error al procesar la venta';
                                if (data.errors && typeof data.errors === 'object') {
                                    const validationErrors = Object.values(data.errors).flat().join(', ');
                                    errorMessage = validationErrors || errorMessage;
                                }
                                if (r.status >= 500 && (!errorMessage || errorMessage === 'Error al procesar la venta')) {
                                    errorMessage = 'Ocurrió un error interno al procesar la venta. Por favor, inténtalo nuevamente en unos minutos.';
                                }
                                throw new Error(errorMessage);
                            }
                            return data;
                        }

                        await r.text();
                        if (!r.ok) {
                            throw new Error('Ocurrió un error interno al procesar la venta. Por favor, inténtalo nuevamente en unos minutos.');
                        }
                        throw new Error('Respuesta inesperada del servidor.');
                    })
                    .then(data => {
                        if (!data.success) {
                            const errorMessage = data.message || data.error || 'Error al procesar la venta';
                            throw new Error(errorMessage);
                        }

                        // Limpiar venta activa
                        const db2 = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                        const k = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                        if (k && db2[k]) {
                            db2[k].status = 'completed';
                            db2[k].items = [];
                            localStorage.setItem('restaurantDB', JSON.stringify(db2));
                        }
                        localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);

                        sessionStorage.setItem('flash_success_message', data.message || 'Venta cobrada correctamente');
                        const viewId = new URLSearchParams(window.location.search).get('view_id');
                        let url = "{{ route('admin.sales.index') }}";
                        if (viewId) url += (url.includes('?') ? '&' : '?') + 'view_id=' + encodeURIComponent(viewId);
                        window.location.href = url;
                    })
                    .catch(err => {   
                        const errorMessage = err.message || 'Error al procesar la venta';
                        showErrorModal(errorMessage);
                        this.disabled = false;
                        this.textContent = originalText;
                    });
            });
        });
    </script>
@endsection
