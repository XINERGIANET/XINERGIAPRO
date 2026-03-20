@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $salesIndexUrl = route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []);
        $posMode = $posMode ?? 'create';
        $invoiceMode = (bool) ($invoiceMode ?? false);
        $isEditMode = $posMode === 'edit';
        $pageTitle = $invoiceMode && $isEditMode ? 'Facturar venta' : ($isEditMode ? 'Editar venta' : 'Nueva venta');
        $cardDescription = $isEditMode
            ? 'Actualiza la venta con la misma interfaz del punto de venta.'
            : 'Interfaz de venta rapida. Puedes seguir agregando productos aunque el stock mostrado sea 0.';
        $secondaryActionLabel = $isEditMode ? 'Cancelar' : 'Guardar';
        $secondaryActionIcon = $isEditMode ? 'ri-close-line' : 'ri-save-line';
        $primaryActionLabel = $invoiceMode && $isEditMode ? 'Guardar factura' : ($isEditMode ? 'Guardar cambios' : 'Cobrar');
        $primaryActionIcon = $isEditMode ? 'ri-save-line' : 'ri-cash-line';
    @endphp

    <div id="sales-create-view">
        <x-common.page-breadcrumb :pageTitle="$pageTitle" />

        <x-common.component-card title="Punto de Venta"
            desc="Interfaz de venta rápida. Puedes seguir agregando productos aunque el stock mostrado sea 0.">
            <div class="flex items-start gap-6" style="display:flex; align-items:flex-start; gap:1.5rem;">
                <section class="min-w-0 space-y-5" style="flex: 0 0 60%; max-width: 60%; width: 60%;">

                    <div class="hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="relative flex-1">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="ri-search-line text-lg"></i>
                                </span>
                                <input id="product-search-legacy" type="text" placeholder="Buscar por nombre o categoría"
                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $salesIndexUrl }}"
                                    class="inline-flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="ri-arrow-left-line"></i>
                                    <span>Volver</span>
                                </a>
                                <button type="button" id="clear-sale-button-legacy"
                                    class="inline-flex h-12 items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    <i class="ri-delete-bin-6-line"></i>
                                    <span>Limpiar orden</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a href="{{ $salesIndexUrl }}"
                            class="inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="ri-arrow-left-line"></i>
                            <span>Volver</span>
                        </a>
                        <button type="button" id="clear-sale-button"
                            class="inline-flex h-10 items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                            <i class="ri-delete-bin-6-line"></i>
                            <span>Limpiar orden</span>
                        </button>
                    </div>

                    <div class="rounded-[30px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Catálogo</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">Productos</h3>
                            </div>
                            <div id="category-filters" class="flex flex-wrap gap-3"></div>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-800">
                                <i class="ri-search-line text-[22px]"></i>
                            </span>
                            <input id="product-search" type="text"
                                placeholder="Buscar por codigo de barras, nombre o categoria"
                                class="h-14 w-full rounded-[22px] border border-slate-200 bg-slate-50 pl-14 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                        </div>
                        <div id="products-grid" class="mt-5 grid gap-4"></div>
                    </div>
                </section>

                <aside class="min-w-0 xl:pr-5" style="flex: 0 0 40%; max-width: 40%; width: 40%;">
                    <div class="sticky top-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-800 bg-slate-900 px-4 py-3 text-white"
                            style="background-color: #334155">
                            <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-slate-800/90 p-1">
                                <button type="button" id="summary-tab-button"
                                    class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-white shadow-theme-xs"
                                    style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 8px 18px rgba(249,115,22,0.24);">
                                    Resumen
                                </button>
                                <button type="button" id="payment-tab-button"
                                    class="inline-flex h-9 items-center justify-center rounded-lg text-sm font-bold text-slate-300 transition-colors hover:text-white">
                                    Cobro
                                </button>
                            </div>
                        </div>



                        <div id="summary-tab-panel">

                            <div id="cart-container" class="max-h-[52vh] overflow-y-auto p-4"></div>

                            <div class="border-t border-slate-200 bg-slate-50 p-5">
                                <div class="mb-3 flex items-center justify-end">
                                    <button type="button" id="open-discount-modal-button"
                                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-3 text-xs font-bold text-orange-700 transition hover:bg-orange-100">
                                        <i class="ri-percent-line"></i>
                                        <span>Aplicar descuento</span>
                                    </button>
                                </div>
                                <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex justify-between text-sm text-slate-500"><span>Subtotal</span><span
                                            id="ticket-subtotal" class="font-semibold">$0.00</span></div>
                                    <div class="flex justify-between text-sm text-slate-500"><span>IGV</span><span
                                            id="ticket-tax" class="font-semibold">$0.00</span></div>
                                    <div class="flex justify-between text-sm text-rose-500"><span>Descuento</span><span
                                            id="ticket-discount" class="font-semibold">S/ 0.00</span></div>
                                    <div class="border-t border-dashed border-slate-200 pt-2"></div>
                                    <div class="flex items-center justify-between"><span
                                            class="text-base font-bold text-slate-900">Total a pagar</span><span
                                            id="ticket-total" class="text-3xl font-black"
                                            style="color:#f97316;">$0.00</span></div>
                                </div>
                                <div id="payment-methods-section"
                                    class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                    <label for="sale-notes"
                                        class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Notas</label>
                                    <textarea id="sale-notes" rows="2" placeholder="Detalle adicional de la venta"
                                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"></textarea>
                                </div>
                            </div>
                        </div>

                        <div id="payment-tab-panel" class="hidden bg-slate-50 p-5">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <label
                                            class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Cliente</label>
                                        <div class="flex items-start gap-2">
                                            <div class="relative flex-1" id="client-selector">
                                                <input id="client-autocomplete" type="text"
                                                    placeholder="Buscar cliente por nombre o documento" autocomplete="off"
                                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 pr-10 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                                <button type="button" id="client-clear-button"
                                                    class="absolute right-3 top-1/2 hidden -translate-y-1/2 text-slate-400 hover:text-slate-700"
                                                    title="Limpiar cliente">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                                <div id="client-options"
                                                    class="absolute z-50 mt-1 hidden max-h-56 w-full overflow-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
                                                </div>
                                            </div>
                                            <button type="button" id="open-quick-client-modal-button"
                                                class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-theme-xs"
                                                style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 12px 24px rgba(249,115,22,0.24);"
                                                title="Agregar cliente">
                                                <i class="ri-add-line text-lg"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div class="space-y-2">
                                            <label for="document-type-select"
                                                class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Documento</label>
                                            <select id="document-type-select"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                                @foreach ($documentTypes ?? [] as $documentType)
                                                    <option value="{{ $documentType->id }}" @selected((int) ($defaultDocumentTypeId ?? 0) === (int) $documentType->id)>
                                                        {{ $documentType->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="payment-type-select"
                                                class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Condicion</label>
                                            <select id="payment-type-select"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                                <option value="CONTADO">Contado</option>
                                                <option value="DEUDA">Deuda</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="cash-register-select"
                                                class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Caja</label>
                                            <select id="cash-register-select"
                                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                                @foreach ($cashRegisters ?? [] as $cashRegister)
                                                    <option value="{{ $cashRegister->id }}" @selected((int) ($defaultCashRegisterId ?? 0) === (int) $cashRegister->id)>
                                                        {{ $cashRegister->number }}{{ $cashRegister->status === 'A' ? ' (Activa)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div id="sale-debt-notice"
                                        class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                                        Esta venta se registrara como deuda y se enviara a cuentas por cobrar.
                                    </div>

                                    <div id="invoice-billing-block"
                                        class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div class="space-y-2">
                                                <label for="billing-status-select"
                                                    class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Factura</label>
                                                <select id="billing-status-select"
                                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                                    <option value="INVOICED">Facturado</option>
                                                    <option value="PENDING">Por facturar</option>
                                                </select>
                                            </div>
                                            <div id="invoice-series-group" class="space-y-2 sm:col-span-1">
                                                <label for="invoice-series-input"
                                                    class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Serie</label>
                                                <input id="invoice-series-input" type="text" maxlength="20"
                                                    placeholder="001"
                                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                            </div>
                                            <div id="invoice-number-group" class="space-y-2 sm:col-span-1">
                                                <label for="invoice-number-input"
                                                    class="block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Correlativo</label>
                                                <input id="invoice-number-input" type="text" maxlength="50"
                                                    placeholder="00000001"
                                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <p class="mt-1 text-sm font-bold text-slate-900">Métodos de pago</p>
                                    </div>
                                    <button type="button" id="add-payment-row-button"
                                        class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-xs font-bold text-white shadow-theme-xs"
                                        style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 10px 20px rgba(249,115,22,0.18);">
                                        <i class="ri-add-line"></i>
                                        <span>Agregar</span>
                                    </button>
                                </div>
                                <div id="payment-rows" class="space-y-3"></div>
                                <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-slate-500">Total pagado</span>
                                        <span id="payment-total" class="font-black">$0.00</span>
                                    </div>
                                    <div id="payment-difference-wrap"
                                        class="mt-2 hidden flex items-center justify-between border-t border-dashed border-slate-200 pt-2">
                                        <span id="payment-difference-label" class="font-semibold">Falta pagar</span>
                                        <span id="payment-difference" class="font-black" style="color:#ea580c;">$0.00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <button type="button" onclick="{{ $isEditMode ? 'cancelEditSale()' : 'goBack()' }}"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                    <i class="{{ $secondaryActionIcon }}"></i><span>{{ $secondaryActionLabel }}</span>
                                </button>
                                <button type="button" onclick="processSaleNow()"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white shadow-theme-xs"
                                    style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 12px 24px rgba(249,115,22,0.24);">
                                    <i class="{{ $primaryActionIcon }}"></i><span>{{ $primaryActionLabel }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </x-common.component-card>
    </div>

    <div id="quick-client-modal" class="fixed inset-0 z-[100000] hidden overflow-hidden p-3 sm:p-6">
        <div id="quick-client-modal-backdrop" class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]"></div>
        <div class="relative flex min-h-full items-center justify-center">
            <div class="w-full max-w-4xl rounded-[28px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8">
                    <h3 class="text-lg font-semibold text-gray-800">Registrar cliente</h3>
                    <button type="button" id="quick-client-close-button"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="p-6 sm:p-8">
                    <div id="quick-client-error"
                        class="mb-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700"></div>

                    <form id="quick-client-form" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                            <select id="quick-client-person-type"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CARNET DE EXTRANGERIA">CARNET DE EXTRANGERIA</option>
                                <option value="PASAPORTE">PASAPORTE</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                            <div class="flex items-center gap-2">
                                <input id="quick-client-document-number"
                                    class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                    placeholder="Documento" required>
                                <button type="button" id="quick-client-search-button"
                                    class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60">
                                    <i class="ri-search-line"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label id="quick-client-first-name-label"
                                class="mb-1.5 block text-sm font-medium text-gray-700">Nombres</label>
                            <input id="quick-client-first-name"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Nombres / Razon social" required>
                        </div>
                        <div id="quick-client-last-name-wrap">
                            <label id="quick-client-last-name-label"
                                class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                            <input id="quick-client-last-name"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Apellidos">
                        </div>

                        <div>
                            <label id="quick-client-date-label" class="mb-1.5 block text-sm font-medium text-gray-700">Fecha
                                de nacimiento</label>
                            <input id="quick-client-date" type="date" onclick="this.showPicker && this.showPicker()"
                                onfocus="this.showPicker && this.showPicker()"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        </div>
                        <div id="quick-client-gender-wrap">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                            <select id="quick-client-gender"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione genero</option>
                                <option value="MASCULINO">MASCULINO</option>
                                <option value="FEMENINO">FEMENINO</option>
                                <option value="OTRO">OTRO</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                            <input id="quick-client-phone"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Ingrese el telefono">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <input id="quick-client-email" type="email"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Ingrese el email">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                            <input id="quick-client-address"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Direccion (opcional)">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                            <select id="quick-client-department"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione departamento</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                            <select id="quick-client-province"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione provincia</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                            <select id="quick-client-district"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione distrito</option>
                            </select>
                        </div>

                        <div class="md:col-span-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                            <div
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox" checked disabled
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500">
                                <span>Cliente</span>
                            </div>
                        </div>

                        <div class="md:col-span-4 mt-2 flex gap-2">
                            <button id="quick-client-save-button" type="submit"
                                class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-white"
                                style="background-color:#00A389;color:#fff;">
                                <i class="ri-save-line"></i>
                                <span id="quick-client-save-label">Guardar cliente</span>
                            </button>
                            <button id="quick-client-cancel-button" type="button"
                                class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i class="ri-close-line"></i>
                                <span>Cancelar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="sale-discount-modal" class="fixed inset-0 z-[100000] hidden overflow-hidden p-3 sm:p-6">
        <div id="sale-discount-backdrop" class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]"></div>
        <div class="relative flex min-h-full items-center justify-center">
            <div class="w-full max-w-3xl rounded-[28px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8">
                    <h3 class="text-lg font-semibold text-gray-800">Aplicar descuento total</h3>
                    <button type="button" id="sale-discount-close-button"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-6 sm:p-8">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Total actual</label>
                            <input id="sale-discount-current-total" type="text" readonly
                                class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm font-semibold text-slate-700">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Dcto. (%)</label>
                            <input id="sale-discount-percentage" type="number" min="0" max="100" step="0.01"
                                placeholder="0.00" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Dcto. (por monto)</label>
                            <input id="sale-discount-amount" type="number" min="0" step="0.01" placeholder="0.00"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button id="sale-discount-cancel-button" type="button"
                            class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="ri-close-line"></i>
                            <span>Cancelar</span>
                        </button>
                        <button id="sale-discount-save-button" type="button"
                            class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-white"
                            style="background:linear-gradient(90deg,#ff7a00,#ff4d00); color:#fff; box-shadow:0 12px 24px rgba(249,115,22,0.24);">
                            <i class="ri-save-line"></i>
                            <span>Guardar descuento</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="stock-error-notification"
        class="pointer-events-none fixed right-6 top-24 z-50 translate-x-[140%] opacity-0 transition-all duration-300">
        <div
            class="flex min-w-[320px] items-start gap-3 rounded-2xl border border-orange-200 bg-white px-4 py-4 shadow-2xl">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-orange-100 text-orange-700"><i
                    class="ri-alert-line text-lg"></i></div>
            <div class="flex-1">
                <p class="text-sm font-bold text-slate-900">Aviso</p>
                <p id="stock-error-message" class="mt-0.5 text-xs text-slate-500">Mensaje</p>
            </div>
            <button type="button" onclick="hideStockError()" class="text-slate-400 hover:text-slate-700"><i
                    class="ri-close-line"></i></button>
        </div>
    </div>

    <div id="add-to-cart-notification"
        class="pointer-events-none fixed right-6 top-24 z-50 translate-x-[140%] opacity-0 transition-all duration-300">
        <div
            class="flex min-w-[320px] items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-4 shadow-2xl">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><i
                    class="ri-check-line text-lg"></i></div>
            <div class="flex-1">
                <p class="text-sm font-bold text-slate-900">Producto agregado</p>
                <p id="notification-product-name" class="mt-0.5 text-xs text-slate-500">Producto</p>
            </div>
            <button type="button" onclick="hideNotification()" class="text-slate-400 hover:text-slate-700"><i
                    class="ri-close-line"></i></button>
        </div>
    </div>

    <style>
        .notification-show {
            transform: translateX(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

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

        #sales-create-view #products-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 0.95rem !important;
        }

        @media (max-width: 1199px) {
            #sales-create-view #products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 991px) {
            #sales-create-view #products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 1279px) {
            #sales-create-view .flex.items-start.gap-6[style*="display:flex"] {
                flex-direction: column !important;
                gap: 1rem !important;
            }

            #sales-create-view .flex.items-start.gap-6[style*="display:flex"]>section,
            #sales-create-view .flex.items-start.gap-6[style*="display:flex"]>aside {
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
            let people = Array.isArray(@json(($people ?? collect())->map(function ($person) {
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
            let productSearchTimer = null;
            let clientQuery = '';
            let clientCursor = 0;
            let clientOpen = false;
            let paymentRows = [];
            let currentAsideTab = 'summary';

            const posMode = @json($posMode ?? 'create');
            const invoiceMode = Boolean(@json($invoiceMode ?? false));
            const isEditMode = posMode === 'edit';
            const initialSaleData = @json($initialSaleData ?? null);
            const defaultDocumentTypeId = Number(@json($defaultDocumentTypeId ?? 0)) || null;
            const defaultCashRegisterId = Number(@json($defaultCashRegisterId ?? 0)) || null;
            const invoiceDocumentIds = new Set(
                documentTypes
                    .filter((documentType) => String(documentType.name || '').toLowerCase().includes('factura'))
                    .map((documentType) => Number(documentType.id))
                    .filter((id) => !Number.isNaN(id))
            );
            const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
            let db = {};
            let activeKey = null;

            if (!isEditMode) {
                db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);

                if (!activeKey || !db[activeKey] || db[activeKey]?.status === 'completed') {
                    activeKey = `sale-${Date.now()}`;
                    localStorage.setItem(ACTIVE_SALE_KEY_STORAGE, activeKey);
                }
            }

            const defaultClient = people.find((person) => Number(person.id) === Number(defaultClientId)) || null;
            let currentSale = isEditMode && initialSaleData
                ? {
                    id: Number(initialSaleData.id || 0) || null,
                    clientId: initialSaleData.clientId ? Number(initialSaleData.clientId) : (defaultClient ? defaultClient.id : null),
                    clientName: initialSaleData.clientName || (defaultClient ? defaultClient.label : 'Publico General'),
                    status: 'editing',
                    notes: String(initialSaleData.notes || ''),
                    items: Array.isArray(initialSaleData.items)
                        ? initialSaleData.items.map((item) => ({
                            pId: Number(item.pId || 0),
                            name: String(item.name || ''),
                            qty: Number(item.qty || 0),
                            price: Number(item.price || 0),
                            note: String(item.note || ''),
                        }))
                        : [],
                    payment_methods: Array.isArray(initialSaleData.payment_methods) ? initialSaleData.payment_methods : [],
                    payment_type: String(initialSaleData.payment_type || 'CONTADO'),
                    document_type_id: Number(initialSaleData.document_type_id || defaultDocumentTypeId || 0) || null,
                    cash_register_id: Number(initialSaleData.cash_register_id || defaultCashRegisterId || 0) || null,
                    billing_status: String(initialSaleData.billing_status || 'NOT_APPLICABLE'),
                    invoice_series: String(initialSaleData.invoice_series || '001'),
                    invoice_number: String(initialSaleData.invoice_number || ''),
                }
                : (db[activeKey] || {
                    id: Date.now(),
                    clientId: defaultClient ? defaultClient.id : null,
                    clientName: defaultClient ? defaultClient.label : 'Publico General',
                    status: 'in_progress',
                    notes: '',
                    items: [],
                    payment_methods: [],
                    payment_type: 'CONTADO',
                    document_type_id: defaultDocumentTypeId,
                    cash_register_id: defaultCashRegisterId,
                    billing_status: 'NOT_APPLICABLE',
                    invoice_series: '001',
                    invoice_number: '',
                });
            currentSale.document_type_id = Number(currentSale.document_type_id || defaultDocumentTypeId || 0) || null;
            currentSale.cash_register_id = Number(currentSale.cash_register_id || defaultCashRegisterId || 0) || null;
            currentSale.payment_type = String(currentSale.payment_type || 'CONTADO').toUpperCase() === 'DEUDA' ? 'DEUDA' : 'CONTADO';
            currentSale.billing_status = String(currentSale.billing_status || 'NOT_APPLICABLE');
            currentSale.invoice_series = String(currentSale.invoice_series || '001');
            currentSale.invoice_number = String(currentSale.invoice_number || '');
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
            if (!isEditMode) {
                db[activeKey] = currentSale;
                localStorage.setItem('restaurantDB', JSON.stringify(db));
            }

            if (invoiceMode && invoiceDocumentIds.has(Number(currentSale.document_type_id || 0)) && currentSale.billing_status === 'PENDING') {
                currentSale.billing_status = 'INVOICED';
            }

            const getImageUrl = (imgUrl) => imgUrl && String(imgUrl).trim() !== ''
                ? imgUrl
                : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iMzAwIj48cmVjdCBmaWxsPSIjZTJlOGYwIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiM2NDc0OGIiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
            const formatMoney = (value) => `S/ ${Number(value || 0).toFixed(2)}`;
            const quickClientStoreUrl = @json($quickClientStoreUrl ?? route('admin.sales.clients.store'));
            const reniecApiUrl = @json(route('api.reniec'));
            const rucApiUrl = @json(route('api.ruc'));
            const departments = Array.isArray(@json($departments ?? [])) ? @json($departments ?? []) : [];
            const provinces = Array.isArray(@json($provinces ?? [])) ? @json($provinces ?? []) : [];
            const districts = Array.isArray(@json($districts ?? [])) ? @json($districts ?? []) : [];
            const branchDepartmentId = String(@json($branchDepartmentId ?? ''));
            const branchProvinceId = String(@json($branchProvinceId ?? ''));
            const branchDistrictId = String(@json($branchDistrictId ?? ''));
            let quickClientLoading = false;

            const normalizeApiDate = (value) => {
                const raw = String(value || '').trim();
                if (!raw) return '';
                const matchIso = raw.match(/^(\d{4}-\d{2}-\d{2})/);
                if (matchIso) return matchIso[1];
                const matchSlash = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                if (matchSlash) return `${matchSlash[3]}-${matchSlash[2]}-${matchSlash[1]}`;
                return '';
            };

            const parseFullName = (fullName) => {
                const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
                if (!parts.length) {
                    return { first_name: '', last_name: '' };
                }
                if (parts.length === 1) {
                    return { first_name: parts[0], last_name: '' };
                }
                return {
                    first_name: parts.slice(0, -2).join(' ') || parts[0],
                    last_name: parts.slice(-2).join(' '),
                };
            };

            const getQuickClientElements = () => ({
                modal: document.getElementById('quick-client-modal'),
                error: document.getElementById('quick-client-error'),
                personType: document.getElementById('quick-client-person-type'),
                documentNumber: document.getElementById('quick-client-document-number'),
                firstName: document.getElementById('quick-client-first-name'),
                lastName: document.getElementById('quick-client-last-name'),
                lastNameWrap: document.getElementById('quick-client-last-name-wrap'),
                firstNameLabel: document.getElementById('quick-client-first-name-label'),
                lastNameLabel: document.getElementById('quick-client-last-name-label'),
                dateLabel: document.getElementById('quick-client-date-label'),
                date: document.getElementById('quick-client-date'),
                gender: document.getElementById('quick-client-gender'),
                genderWrap: document.getElementById('quick-client-gender-wrap'),
                phone: document.getElementById('quick-client-phone'),
                email: document.getElementById('quick-client-email'),
                address: document.getElementById('quick-client-address'),
                department: document.getElementById('quick-client-department'),
                province: document.getElementById('quick-client-province'),
                district: document.getElementById('quick-client-district'),
                saveButton: document.getElementById('quick-client-save-button'),
                saveLabel: document.getElementById('quick-client-save-label'),
                searchButton: document.getElementById('quick-client-search-button'),
            });

            const normalizeLocationText = (value) => String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();

            const renderSelectOptions = (select, items, selectedValue, placeholder) => {
                if (!select) return;
                select.innerHTML = `<option value="">${placeholder}</option>`;
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = String(item.id);
                    option.textContent = String(item.name || item.description || '');
                    if (String(option.value) === String(selectedValue || '')) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const renderQuickClientLocationOptions = () => {
                const { department, province, district } = getQuickClientElements();
                const selectedDepartmentId = String(department?.value || '');
                const selectedProvinceId = String(province?.value || '');
                const selectedDistrictId = String(district?.value || '');

                renderSelectOptions(department, departments, selectedDepartmentId, 'Seleccione departamento');
                renderSelectOptions(
                    province,
                    provinces.filter((item) => String(item.parent_location_id || '') === selectedDepartmentId),
                    selectedProvinceId,
                    'Seleccione provincia'
                );
                renderSelectOptions(
                    district,
                    districts.filter((item) => String(item.parent_location_id || '') === selectedProvinceId),
                    selectedDistrictId,
                    'Seleccione distrito'
                );
            };

            const setQuickClientLocation = (departmentId, provinceId, districtId) => {
                const { department, province, district } = getQuickClientElements();
                if (department) department.value = String(departmentId || '');
                renderQuickClientLocationOptions();
                if (province) province.value = String(provinceId || '');
                renderQuickClientLocationOptions();
                if (district) district.value = String(districtId || '');
                renderQuickClientLocationOptions();
            };

            const onQuickClientDepartmentChange = () => {
                const { province, district } = getQuickClientElements();
                if (province) province.value = '';
                if (district) district.value = '';
                renderQuickClientLocationOptions();
            };

            const onQuickClientProvinceChange = () => {
                const { district } = getQuickClientElements();
                if (district) district.value = '';
                renderQuickClientLocationOptions();
            };

            const findDepartmentByName = (name) => {
                const target = normalizeLocationText(name);
                return departments.find((item) => normalizeLocationText(item.name) === target) || null;
            };

            const findProvinceByName = (name, departmentId) => {
                const target = normalizeLocationText(name);
                return provinces.find((item) => (
                    String(item.parent_location_id || '') === String(departmentId || '')
                    && normalizeLocationText(item.name) === target
                )) || null;
            };

            const findDistrictByName = (name, provinceId) => {
                const target = normalizeLocationText(name);
                return districts.find((item) => (
                    String(item.parent_location_id || '') === String(provinceId || '')
                    && normalizeLocationText(item.name) === target
                )) || null;
            };

            const applyQuickClientLocationFromLookup = (payload) => {
                const department = findDepartmentByName(payload?.department || '');
                if (!department) return;

                const province = findProvinceByName(payload?.province || '', department.id);
                const district = province ? findDistrictByName(payload?.district || '', province.id) : null;

                setQuickClientLocation(
                    department.id,
                    province ? province.id : '',
                    district ? district.id : ''
                );
            };

            const clearQuickClientError = () => {
                const { error } = getQuickClientElements();
                if (!error) return;
                error.textContent = '';
                error.classList.add('hidden');
            };

            const showQuickClientError = (message) => {
                const { error } = getQuickClientElements();
                if (!error) return;
                error.textContent = message;
                error.classList.remove('hidden');
            };

            const toggleQuickClientLoading = (loading) => {
                quickClientLoading = loading;
                const { saveButton, saveLabel, searchButton } = getQuickClientElements();
                if (saveButton) saveButton.disabled = loading;
                if (searchButton) searchButton.disabled = loading;
                if (saveLabel) saveLabel.textContent = loading ? 'Guardando...' : 'Guardar cliente';
            };

            const syncQuickClientPersonTypeUI = () => {
                const {
                    personType,
                    firstNameLabel,
                    lastName,
                    lastNameWrap,
                    lastNameLabel,
                    dateLabel,
                    gender,
                    genderWrap,
                    firstName,
                } = getQuickClientElements();
                const type = String(personType?.value || 'DNI').toUpperCase();
                const isRuc = type === 'RUC';

                if (firstNameLabel) {
                    firstNameLabel.textContent = isRuc ? 'Razon social' : 'Nombres';
                }
                if (firstName) {
                    firstName.placeholder = isRuc ? 'Razon social' : 'Nombres / Razon social';
                }
                if (lastNameLabel) {
                    lastNameLabel.textContent = 'Apellidos';
                }
                if (lastNameWrap) {
                    lastNameWrap.classList.toggle('hidden', isRuc);
                }
                if (lastName) {
                    lastName.required = !isRuc;
                    if (isRuc) {
                        lastName.value = '';
                    }
                }
                if (dateLabel) {
                    dateLabel.textContent = isRuc ? 'Fecha de inscripcion' : 'Fecha de nacimiento';
                }
                if (genderWrap) {
                    genderWrap.classList.toggle('hidden', isRuc);
                }
                if (gender && isRuc) {
                    gender.value = '';
                }
            };

            const resetQuickClientForm = () => {
                const {
                    personType,
                    documentNumber,
                    firstName,
                    lastName,
                    date,
                    gender,
                    phone,
                    email,
                    address,
                    department,
                    province,
                    district,
                } = getQuickClientElements();

                if (personType) personType.value = 'DNI';
                if (documentNumber) documentNumber.value = '';
                if (firstName) firstName.value = '';
                if (lastName) lastName.value = '';
                if (date) date.value = '';
                if (gender) gender.value = '';
                if (phone) phone.value = '';
                if (email) email.value = '';
                if (address) address.value = '';
                clearQuickClientError();
                syncQuickClientPersonTypeUI();
                setQuickClientLocation(branchDepartmentId, branchProvinceId, branchDistrictId);
            };

            const openQuickClientModal = () => {
                const { modal, documentNumber } = getQuickClientElements();
                resetQuickClientForm();
                if (!modal) return;
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                window.setTimeout(() => documentNumber?.focus(), 40);
            };

            const closeQuickClientModal = () => {
                const { modal } = getQuickClientElements();
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                clearQuickClientError();
                toggleQuickClientLoading(false);
            };

            const getQuickClientPayload = () => {
                const {
                    personType,
                    documentNumber,
                    firstName,
                    lastName,
                    date,
                    gender,
                    phone,
                    email,
                    address,
                    district,
                } = getQuickClientElements();

                return {
                    person_type: String(personType?.value || 'DNI').trim(),
                    document_number: String(documentNumber?.value || '').trim(),
                    first_name: String(firstName?.value || '').trim(),
                    last_name: String(lastName?.value || '').trim(),
                    fecha_nacimiento: String(date?.value || '').trim(),
                    genero: String(gender?.value || '').trim(),
                    phone: String(phone?.value || '').trim(),
                    email: String(email?.value || '').trim(),
                    address: String(address?.value || '').trim(),
                    location_id: String(district?.value || '').trim(),
                };
            };

            const upsertClientInList = (payload) => {
                const client = {
                    id: Number(payload.id || 0),
                    label: String(payload.label || payload.name || `${payload.first_name || ''} ${payload.last_name || ''}`).trim() || String(payload.document || 'Sin nombre'),
                    document: String(payload.document || payload.document_number || '').trim(),
                };

                const existingIndex = people.findIndex((person) => Number(person.id) === client.id);
                if (existingIndex >= 0) {
                    people[existingIndex] = client;
                } else {
                    people.unshift(client);
                }

                return client;
            };

            const fetchQuickClientDocumentData = async () => {
                clearQuickClientError();
                const {
                    personType,
                    documentNumber,
                    firstName,
                    lastName,
                    date,
                    gender,
                    address,
                } = getQuickClientElements();
                const type = String(personType?.value || 'DNI').toUpperCase();
                const documentValue = String(documentNumber?.value || '').trim();

                if (type === 'DNI') {
                    if (!/^\d{8}$/.test(documentValue)) {
                        showQuickClientError('Ingrese un DNI valido de 8 digitos.');
                        return;
                    }

                    try {
                        toggleQuickClientLoading(true);
                        const response = await fetch(`${reniecApiUrl}?dni=${encodeURIComponent(documentValue)}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const payload = await response.json();
                        if (!response.ok || payload?.status === false) {
                            throw new Error(payload?.message || 'Error consultando RENIEC.');
                        }

                        const fnVal = String(payload?.first_name ?? payload?.nombres ?? '').trim();
                        const apPat = String(payload?.apellido_paterno ?? '').trim();
                        const apMat = String(payload?.apellido_materno ?? '').trim();
                        const lnUnified = String(payload?.last_name ?? '').trim() || [apPat, apMat].filter(Boolean).join(' ');
                        const parsed = (!fnVal && !lnUnified)
                            ? parseFullName(payload?.name || payload?.nombre_completo || '')
                            : { first_name: fnVal, last_name: lnUnified };
                        if (firstName) firstName.value = String(fnVal || parsed.first_name || '').trim();
                        if (lastName) lastName.value = String(lnUnified || parsed.last_name || '').trim();
                        if (date) date.value = normalizeApiDate(payload?.fecha_nacimiento || '');
                        if (gender) gender.value = String(payload?.genero || '').trim();
                    } catch (error) {
                        showQuickClientError(error?.message || 'Error consultando RENIEC.');
                    } finally {
                        toggleQuickClientLoading(false);
                    }
                    return;
                }

                if (type === 'RUC') {
                    if (!/^\d{11}$/.test(documentValue)) {
                        showQuickClientError('Ingrese un RUC valido de 11 digitos.');
                        return;
                    }

                    try {
                        toggleQuickClientLoading(true);
                        const response = await fetch(`${rucApiUrl}?ruc=${encodeURIComponent(documentValue)}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const payload = await response.json();
                        if (!response.ok || payload?.status === false) {
                            throw new Error(payload?.message || 'Error consultando RUC.');
                        }

                        if (firstName) firstName.value = String(payload?.legal_name || '').trim();
                        if (lastName) lastName.value = '';
                        if (address) address.value = String(payload?.address || '').trim();
                        if (date) date.value = normalizeApiDate(payload?.raw?.fecha_inscripcion || payload?.raw?.fechaInscripcion || '');
                        applyQuickClientLocationFromLookup(payload);
                    } catch (error) {
                        showQuickClientError(error?.message || 'Error consultando RUC.');
                    } finally {
                        toggleQuickClientLoading(false);
                    }
                    return;
                }

                showQuickClientError('La busqueda automatica solo aplica para DNI o RUC.');
            };

            const saveQuickClient = async () => {
                clearQuickClientError();
                const payload = getQuickClientPayload();

                if (!payload.document_number || !payload.first_name || (!payload.last_name && payload.person_type !== 'RUC')) {
                    showQuickClientError('Completa los campos obligatorios del cliente.');
                    return;
                }

                try {
                    toggleQuickClientLoading(true);
                    const response = await fetch(String(quickClientStoreUrl || ''), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify(payload),
                    });

                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(result?.message || 'Error registrando cliente.');
                    }

                    const client = upsertClientInList(result);
                    selectClient(client);
                    closeQuickClientModal();
                } catch (error) {
                    showQuickClientError(error?.message || 'Error registrando cliente.');
                } finally {
                    toggleQuickClientLoading(false);
                }
            };

            const saveDB = () => {
                if (isEditMode) return;
                db[activeKey] = currentSale;
                localStorage.setItem('restaurantDB', JSON.stringify(db));
            };
            const isInvoiceDocumentSelected = () => invoiceDocumentIds.has(Number(currentSale.document_type_id || 0));
            const isDebtSaleSelected = () => String(currentSale.payment_type || 'CONTADO') === 'DEUDA';
            const normalizeBillingState = () => {
                if (!isInvoiceDocumentSelected()) {
                    currentSale.billing_status = 'NOT_APPLICABLE';
                    currentSale.invoice_number = '';
                    if (!currentSale.invoice_series) {
                        currentSale.invoice_series = '001';
                    }
                    return;
                }

                if (!['INVOICED', 'PENDING'].includes(String(currentSale.billing_status || ''))) {
                    currentSale.billing_status = invoiceMode ? 'INVOICED' : 'PENDING';
                }

                if (!currentSale.invoice_series) {
                    currentSale.invoice_series = '001';
                }

                if (currentSale.billing_status === 'PENDING') {
                    currentSale.invoice_number = '';
                }
            };
            const syncInvoiceBillingFields = () => {
                normalizeBillingState();

                const block = document.getElementById('invoice-billing-block');
                const billingStatusSelect = document.getElementById('billing-status-select');
                const invoiceSeriesGroup = document.getElementById('invoice-series-group');
                const invoiceNumberGroup = document.getElementById('invoice-number-group');
                const invoiceSeriesInput = document.getElementById('invoice-series-input');
                const invoiceNumberInput = document.getElementById('invoice-number-input');
                const isInvoice = isInvoiceDocumentSelected();
                const isInvoiced = isInvoice && currentSale.billing_status === 'INVOICED';

                if (block) {
                    block.classList.toggle('hidden', !isInvoice);
                }

                if (billingStatusSelect) {
                    billingStatusSelect.value = isInvoice ? currentSale.billing_status : 'PENDING';
                }

                if (invoiceSeriesInput) {
                    invoiceSeriesInput.value = currentSale.invoice_series || '001';
                }

                if (invoiceNumberInput) {
                    invoiceNumberInput.value = currentSale.invoice_number || '';
                }

                if (invoiceSeriesGroup) {
                    invoiceSeriesGroup.classList.toggle('hidden', !isInvoiced);
                }

                if (invoiceNumberGroup) {
                    invoiceNumberGroup.classList.toggle('hidden', !isInvoiced);
                }
            };
            const syncPaymentTypeUI = () => {
                const paymentTypeSelect = document.getElementById('payment-type-select');
                const paymentMethodsSection = document.getElementById('payment-methods-section');
                const debtNotice = document.getElementById('sale-debt-notice');
                const isDebtSale = isDebtSaleSelected();

                if (paymentTypeSelect) {
                    paymentTypeSelect.value = isDebtSale ? 'DEUDA' : 'CONTADO';
                }

                if (paymentMethodsSection) {
                    paymentMethodsSection.classList.toggle('hidden', isDebtSale);
                }

                if (debtNotice) {
                    debtNotice.classList.toggle('hidden', !isDebtSale);
                }
            };
            const handlePaymentTypeChange = (nextValue) => {
                currentSale.payment_type = String(nextValue || 'CONTADO').toUpperCase() === 'DEUDA' ? 'DEUDA' : 'CONTADO';
                if (isDebtSaleSelected()) {
                    paymentRows = [];
                    syncPaymentRows();
                } else if (!paymentRows.length) {
                    addPaymentRow(Number(getTotalFromSale().toFixed(2)));
                }
                syncPaymentTypeUI();
                renderPaymentRows();
                saveDB();
            };
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
            const getTotalFromSale = () => {

    const itemsTotal = currentSale.items.reduce((sum, item) => {
        return sum + ((Number(item.price) || 0) * (Number(item.qty) || 0));
    }, 0);

    const discount = currentSale.discount?.amount || 0;

    return itemsTotal - discount;

};
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

            function normalizeProductCode(value) {
                return String(value || '').trim().toLowerCase();
            }

            function clearProductSearchField() {
                productSearch = '';
                const searchInput = document.getElementById('product-search');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
            }

            function findUniqueProductByCode(searchTerm) {
                const needle = normalizeProductCode(searchTerm);
                if (!needle) return null;

                const matches = products.filter((prod) => {
                    const productId = Number(prod.id);
                    return normalizeProductCode(prod.code) === needle
                        && typeof priceByProductId.get(productId) !== 'undefined';
                });

                return matches.length === 1 ? matches[0] : null;
            }

            function tryAutoAddProductByCode(searchTerm) {
                const matchedProduct = findUniqueProductByCode(searchTerm);
                if (!matchedProduct) return false;

                const price = priceByProductId.get(Number(matchedProduct.id));
                if (typeof price === 'undefined') return false;

                addToCart(matchedProduct, price);
                clearProductSearchField();
                renderProducts();
                return true;
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

                if (isDebtSaleSelected()) {
                    updatePaymentSummary();
                    return;
                }

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
                const paid = isDebtSaleSelected()
                    ? 0
                    : paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                const diff = isDebtSaleSelected() ? total : (total - paid);
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
                    button.className = 'inline-flex h-12 items-center justify-center rounded-[22px] border px-6 text-sm font-bold transition';
                    const isActive = category === selectedCategory;
                    button.className += isActive
                        ? ' border-transparent text-white shadow-theme-xs'
                        : ' border-slate-200 bg-white text-slate-800 hover:border-orange-300 hover:text-orange-700';
                    button.style.background = isActive ? 'linear-gradient(90deg,#ff7a00,#ff4d00)' : '';
                    button.style.color = isActive ? '#fff' : '';
                    button.style.boxShadow = isActive ? '0 12px 24px rgba(249,115,22,0.22)' : '';
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
                    const searchNeedle = `${prod.code || ''} ${prod.name || ''} ${category}`.toLowerCase();

                    if (typeof price === 'undefined') return;
                    if (selectedCategory !== 'General' && category !== selectedCategory) return;
                    if (productSearch && !searchNeedle.includes(productSearch)) return;

                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'group relative overflow-hidden border bg-white text-center transition-all duration-200';
                    card.style.borderRadius = '30px';
                    card.style.borderColor = '#e4e9f1';
                    card.style.borderWidth = '1px';
                    card.style.borderStyle = 'solid';
                    card.style.backgroundColor = '#ffffff';
                    card.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.05)';
                    card.style.height = '190px';
                    card.style.minHeight = '190px';
                    card.addEventListener('click', () => addToCart(prod, price));
                    card.addEventListener('mouseenter', () => {
                        const orb = card.querySelector('[data-role="product-orb"]');
                        card.style.transform = 'translateY(-4px)';
                        card.style.borderColor = '#ffd1a4';
                        card.style.boxShadow = '0 18px 34px rgba(249, 115, 22, 0.12)';
                        card.style.backgroundColor = '#fffdfb';
                        if (orb) {
                            orb.style.transform = 'translateY(-1px) scale(1.03)';
                            orb.style.boxShadow = '0 18px 30px rgba(249, 115, 22, 0.12), 0 8px 16px rgba(15, 23, 42, 0.06)';
                        }
                    });
                    card.addEventListener('mouseleave', () => {
                        const orb = card.querySelector('[data-role="product-orb"]');
                        card.style.transform = '';
                        card.style.borderColor = '#e4e9f1';
                        card.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.05)';
                        card.style.backgroundColor = '#ffffff';
                        if (orb) {
                            orb.style.transform = '';
                            orb.style.boxShadow = '0 12px 24px rgba(249, 115, 22, 0.08), 0 6px 14px rgba(15, 23, 42, 0.04)';
                        }
                    });

                    card.innerHTML = `
                            <div class="relative flex h-full w-full flex-col items-center px-3 pb-4 pt-4">
                                <div class="absolute right-3 top-4 z-20 inline-flex min-w-[78px] items-center justify-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-center text-[12px] font-bold leading-none text-orange-600" style="box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);">
                                    Stock: ${Number(stock).toFixed(0)}
                                </div>
                                <div class="flex h-[102px] w-full items-center justify-center pt-2">
                                    <div data-role="product-orb" class="mx-auto flex h-[92px] w-[92px] items-center justify-center overflow-hidden rounded-full bg-white transition-transform duration-200" style="box-shadow: 0 12px 24px rgba(249, 115, 22, 0.08), 0 6px 14px rgba(15, 23, 42, 0.04);">
                                        ${hasImage
                            ? `<img src="${getImageUrl(prod.img)}" alt="${prod.name || 'Producto'}" class="h-16 w-16 object-contain" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">`
                            : `<i class="ri-shopping-bag-3-line text-[30px] text-orange-500"></i>`}
                                    </div>
                                </div>
                                <div class="mt-2 flex h-[50px] w-full items-start justify-center px-1">
                                    <h4 class="line-clamp-2 block w-full text-center text-[12px] font-black leading-[1.28] text-slate-900">${prod.name || 'Sin nombre'}</h4>
                                </div>
                                <div class="mt-1 flex h-[24px] w-full items-center justify-center">
                                    <p class="text-[0.95rem] font-black leading-none tracking-tight transition-colors duration-200 group-hover:text-orange-600" style="color:#f97316;">${formatMoney(price)}</p>
                                </div>
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

            function sanitizeLineMoney(value) {
                const parsed = Number(value);
                return Number.isFinite(parsed) && parsed >= 0 ? Number(parsed.toFixed(6)) : 0;
            }

            function setItemUnitPrice(index, value) {
                if (!currentSale.items[index]) return;
                currentSale.items[index].price = sanitizeLineMoney(value);
                saveDB();
                renderTicket();
            }

            function setItemLineTotal(index, value) {
                if (!currentSale.items[index]) return;
                const qty = Number(currentSale.items[index].qty) || 0;
                const total = sanitizeLineMoney(value);
                currentSale.items[index].price = qty > 0 ? Number((total / qty).toFixed(6)) : 0;
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
                        const prod = products.find((p) => Number(p.id) === Number(item.pId)) || {
                            id: Number(item.pId) || 0,
                            name: item.name || 'Producto',
                            img: null,
                        };

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
                                <div class="p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <h5 class="truncate text-sm font-bold text-slate-900">${prod.name || 'Producto'}</h5>
                                            <p class="mt-1 text-[11px] font-medium text-slate-500">Cantidad x precio de venta</p>
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
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">C/U</span>
                                            <input
                                                data-role="unit-price"
                                                data-index="${index}"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value="${(Number(item.price) || 0).toFixed(2)}"
                                                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                            >
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Total</span>
                                            <input
                                                data-role="line-total"
                                                data-index="${index}"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value="${itemTotal.toFixed(2)}"
                                                class="h-10 w-full rounded-xl border border-orange-200 bg-orange-50 px-3 text-sm font-black outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                                style="color:#f97316;"
                                            >
                                        </label>
                                    </div>
                                </div>
                            `;
                        row.querySelector('[data-role="unit-price"]')?.addEventListener('change', (event) => {
                            setItemUnitPrice(index, event.currentTarget.value);
                        });
                        row.querySelector('[data-role="line-total"]')?.addEventListener('change', (event) => {
                            setItemLineTotal(index, event.currentTarget.value);
                        });
                        container.appendChild(row);
                    });
                }

                const discount = currentSale.discount?.amount || 0;
const total = subtotalBase + tax - discount;
                document.getElementById('ticket-subtotal').innerText = formatMoney(subtotalBase);
                document.getElementById('ticket-tax').innerText = formatMoney(tax);
                document.getElementById('ticket-discount').innerText = formatMoney(discount);
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
                currentSale.payment_type = 'CONTADO';
                paymentRows = [];
                saveDB();
                syncPaymentRows();
                const notesInput = document.getElementById('sale-notes');
                if (notesInput) notesInput.value = '';
                syncPaymentTypeUI();
                renderPaymentRows();
                renderTicket();
                showNotice('La orden actual fue limpiada.');
            }

            function processSaleNow() {
                if (!currentSale.items.length) {
                    showNotice('Agrega al menos un producto antes de cobrar.');
                    return;
                }

                if (!isDebtSaleSelected() && !paymentRows.length) {
                    showNotice('Agrega al menos un metodo de pago.');
                    return;
                }

                const total = getTotalFromSale();
                const totalPaid = paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                if (!isDebtSaleSelected() && Math.abs(totalPaid - total) > 0.01) {
                    showNotice('La suma de los pagos debe coincidir con el total.');
                    return;
                }

                normalizeBillingState();
                if (isInvoiceDocumentSelected() && currentSale.billing_status === 'INVOICED') {
                    if (!String(currentSale.invoice_series || '').trim()) {
                        showNotice('Ingresa la serie de la factura.');
                        setAsideTab('payment');
                        document.getElementById('invoice-series-input')?.focus();
                        return;
                    }

                    if (!String(currentSale.invoice_number || '').trim()) {
                        showNotice('Ingresa el correlativo de la factura.');
                        setAsideTab('payment');
                        document.getElementById('invoice-number-input')?.focus();
                        return;
                    }
                }

                const invalidCardRow = isDebtSaleSelected() ? null : paymentRows.find((row) => {
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

    discount_type: currentSale.discount?.percent ? 'PERCENTAGE' : 'AMOUNT',
    discount_value: currentSale.discount?.percent || currentSale.discount?.amount || 0,

    payment_type: currentSale.payment_type || 'CONTADO',
    document_type_id: Number(document.getElementById('document-type-select')?.value || 0),
    cash_register_id: Number(document.getElementById('cash-register-select')?.value || 0),
    person_id: currentSale.clientId ? Number(currentSale.clientId) : null,

    payment_methods: isDebtSaleSelected() ? [] : paymentRows.map((row) => ({
        payment_method_id: Number(row.payment_method_id),
        amount: Number(row.amount),
        payment_gateway_id: row.payment_gateway_id ? Number(row.payment_gateway_id) : null,
        card_id: row.card_id ? Number(row.card_id) : null,
        digital_wallet_id: row.digital_wallet_id ? Number(row.digital_wallet_id) : null,
    })),

    notes: document.getElementById('sale-notes')?.value || '',
};
                if (isEditMode && currentSale.id) {
                    payload.movement_id = Number(currentSale.id);
                }

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
                        if (isEditMode) {
                            showNotice('Venta actualizada correctamente.');
                            setTimeout(() => {
                                window.location.href = @json($salesIndexUrl);
                            }, 350);
                            return;
                        }
                        currentSale = {
                            id: Date.now(),
                            clientId: defaultClient ? defaultClient.id : null,
                            clientName: defaultClient ? defaultClient.label : 'Publico General',
                            status: 'in_progress',
                            notes: '',
                            items: [],
                            payment_methods: [],
                            payment_type: 'CONTADO',
                            document_type_id: defaultDocumentTypeId,
                            cash_register_id: defaultCashRegisterId,
                            billing_status: 'NOT_APPLICABLE',
                            invoice_series: '001',
                            invoice_number: '',
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
                        syncPaymentTypeUI();
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
                        payment_type: currentSale.payment_type || 'CONTADO',
                        document_type_id: Number(document.getElementById('document-type-select')?.value || 0) || null,
                        billing_status: isInvoiceDocumentSelected() ? currentSale.billing_status : 'NOT_APPLICABLE',
                        invoice_series: isInvoiceDocumentSelected() ? (currentSale.invoice_series || '') : '',
                        invoice_number: isInvoiceDocumentSelected() && currentSale.billing_status === 'INVOICED' ? (currentSale.invoice_number || '') : '',
                        notes: document.getElementById('sale-notes')?.value || 'Venta guardada como borrador - pendiente de pago'
                    })
                }).finally(() => {
                    window.location.href = @json($salesIndexUrl);
                });
            }

            function cancelEditSale() {
                window.location.href = @json($salesIndexUrl);
            }

            document.getElementById('product-search')?.addEventListener('input', (event) => {
                const rawValue = String(event.target.value || '');
                productSearch = rawValue.trim().toLowerCase();
                renderProducts();
                window.clearTimeout(productSearchTimer);
                if (productSearch === '') return;
                productSearchTimer = window.setTimeout(() => {
                    tryAutoAddProductByCode(rawValue);
                }, 180);
            });
            document.getElementById('product-search')?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                window.clearTimeout(productSearchTimer);
                tryAutoAddProductByCode(event.target.value || '');
            });
            document.getElementById('clear-sale-button')?.addEventListener('click', clearSale);
            document.getElementById('add-payment-row-button')?.addEventListener('click', () => addPaymentRow());
            document.getElementById('summary-tab-button')?.addEventListener('click', () => setAsideTab('summary'));
            document.getElementById('payment-tab-button')?.addEventListener('click', () => setAsideTab('payment'));
            document.getElementById('sale-notes')?.addEventListener('input', (event) => {
                currentSale.notes = String(event.target.value || '');
                saveDB();
            });
            document.getElementById('document-type-select')?.addEventListener('change', (event) => {
                currentSale.document_type_id = Number(event.target.value || 0) || null;
                normalizeBillingState();
                syncInvoiceBillingFields();
                saveDB();
            });
            document.getElementById('payment-type-select')?.addEventListener('change', (event) => {
                handlePaymentTypeChange(event.target.value);
            });
            document.getElementById('cash-register-select')?.addEventListener('change', (event) => {
                currentSale.cash_register_id = Number(event.target.value || 0) || null;
                saveDB();
            });
            document.getElementById('billing-status-select')?.addEventListener('change', (event) => {
                currentSale.billing_status = String(event.target.value || 'PENDING');
                normalizeBillingState();
                syncInvoiceBillingFields();
                saveDB();
            });
            document.getElementById('invoice-series-input')?.addEventListener('input', (event) => {
                currentSale.invoice_series = String(event.target.value || '');
                saveDB();
            });
            document.getElementById('invoice-number-input')?.addEventListener('input', (event) => {
                currentSale.invoice_number = String(event.target.value || '');
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
            document.getElementById('open-quick-client-modal-button')?.addEventListener('click', openQuickClientModal);
            document.getElementById('quick-client-close-button')?.addEventListener('click', closeQuickClientModal);
            document.getElementById('quick-client-cancel-button')?.addEventListener('click', closeQuickClientModal);
            document.getElementById('quick-client-modal-backdrop')?.addEventListener('click', closeQuickClientModal);
            document.getElementById('quick-client-search-button')?.addEventListener('click', fetchQuickClientDocumentData);
            document.getElementById('quick-client-person-type')?.addEventListener('change', syncQuickClientPersonTypeUI);
            document.getElementById('quick-client-department')?.addEventListener('change', onQuickClientDepartmentChange);
            document.getElementById('quick-client-province')?.addEventListener('change', onQuickClientProvinceChange);
            document.getElementById('quick-client-form')?.addEventListener('submit', (event) => {
                event.preventDefault();
                saveQuickClient();
            });
            // abrir modal descuento
            document.getElementById('open-discount-modal-button')?.addEventListener('click', () => {

                const total = getTotalFromSale();

                document.getElementById('sale-discount-current-total').value = formatMoney(total);

                document.getElementById('sale-discount-percentage').value = '';
                document.getElementById('sale-discount-amount').value = '';

                document.getElementById('sale-discount-modal')?.classList.remove('hidden');
            });
            // sincronizar descuento porcentaje <-> monto
const discountPercentInput = document.getElementById('sale-discount-percentage');
const discountAmountInput = document.getElementById('sale-discount-amount');
const discountTotalInput = document.getElementById('sale-discount-current-total');

function getDiscountBaseTotal() {
    const raw = discountTotalInput.value.replace('S/', '').trim();
    return Number(raw) || 0;
}

// si cambia porcentaje
discountPercentInput?.addEventListener('input', () => {

    const percent = Number(discountPercentInput.value) || 0;
    const total = getDiscountBaseTotal();

    const amount = (total * percent) / 100;

    discountAmountInput.value = amount.toFixed(2);

});

// si cambia monto
discountAmountInput?.addEventListener('input', () => {

    const amount = Number(discountAmountInput.value) || 0;
    const total = getDiscountBaseTotal();

    if (total === 0) return;

    const percent = (amount / total) * 100;

    discountPercentInput.value = percent.toFixed(2);

});

            // cerrar modal
            document.getElementById('sale-discount-close-button')?.addEventListener('click', () => {
                document.getElementById('sale-discount-modal')?.classList.add('hidden');
            });

            document.getElementById('sale-discount-cancel-button')?.addEventListener('click', () => {
                document.getElementById('sale-discount-modal')?.classList.add('hidden');
            });

            document.getElementById('sale-discount-backdrop')?.addEventListener('click', () => {
                document.getElementById('sale-discount-modal')?.classList.add('hidden');
            });
            // guardar descuento
document.getElementById('sale-discount-save-button')?.addEventListener('click', () => {

    const percent = Number(document.getElementById('sale-discount-percentage').value) || 0;
    const amount = Number(document.getElementById('sale-discount-amount').value) || 0;

    // guardar descuento en la venta
    currentSale.discount = {
        percent: percent,
        amount: amount
    };

    saveDB();

    renderTicket();
    updatePaymentSummary(); // 🔴 IMPORTANTE

    document.getElementById('sale-discount-modal')?.classList.add('hidden');

});
            document.addEventListener('click', (event) => {
                const wrapper = document.getElementById('client-selector');
                if (!wrapper) return;
                if (!wrapper.contains(event.target)) {
                    closeClientDropdown();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeQuickClientModal();
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
            const documentTypeSelect = document.getElementById('document-type-select');
            if (documentTypeSelect && currentSale.document_type_id) {
                documentTypeSelect.value = String(currentSale.document_type_id);
            }
            const cashRegisterSelect = document.getElementById('cash-register-select');
            if (cashRegisterSelect && currentSale.cash_register_id) {
                cashRegisterSelect.value = String(currentSale.cash_register_id);
            }
            const paymentTypeSelect = document.getElementById('payment-type-select');
            if (paymentTypeSelect) {
                paymentTypeSelect.value = currentSale.payment_type || 'CONTADO';
            }
            const billingStatusSelect = document.getElementById('billing-status-select');
            if (billingStatusSelect) {
                billingStatusSelect.value = ['INVOICED', 'PENDING'].includes(currentSale.billing_status) ? currentSale.billing_status : 'PENDING';
            }
            const invoiceSeriesInput = document.getElementById('invoice-series-input');
            if (invoiceSeriesInput) {
                invoiceSeriesInput.value = currentSale.invoice_series || '001';
            }
            const invoiceNumberInput = document.getElementById('invoice-number-input');
            if (invoiceNumberInput) {
                invoiceNumberInput.value = currentSale.invoice_number || '';
            }

            syncQuickClientPersonTypeUI();
            setQuickClientLocation(branchDepartmentId, branchProvinceId, branchDistrictId);
            renderCategoryFilters();
            renderProducts();
            renderTicket();
            syncPaymentTypeUI();
            syncInvoiceBillingFields();
            renderPaymentRows();
            setAsideTab(invoiceMode ? 'payment' : 'summary');

            window.goBack = goBack;
            window.cancelEditSale = cancelEditSale;
            window.processSaleNow = processSaleNow;
            window.updateQty = updateQty;
            window.setQty = setQty;
            window.hideNotification = hideNotification;
            window.hideStockError = hideStockError;
        })();
    </script>
@endsection