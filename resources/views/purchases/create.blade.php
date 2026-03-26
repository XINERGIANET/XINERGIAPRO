@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $purchaseIndexUrl = route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []);
        $isEditing = isset($purchase) && $purchase;
        $pageTitle = $isEditing ? 'Editar compra' : 'Nueva compra';
        $cardTitle = $isEditing ? 'Compras | Editar' : 'Compras | Nuevo';
        $cardDesc = $isEditing
            ? 'Actualiza la compra con la misma interfaz operativa del registro.'
            : 'Registra la compra, actualiza stock y, si corresponde, genera la salida de caja desde un solo flujo.';
        $formAction = $isEditing
            ? route('admin.purchases.update', array_merge([$purchase], $viewId ? ['view_id' => $viewId] : []))
            : route('admin.purchases.store', $viewId ? ['view_id' => $viewId] : []);
    @endphp

    <div
        id="purchases-create-view"
        x-data="purchaseCreateForm(@js($purchaseCreateConfig))"
        x-init="init()"
    >
        <x-common.page-breadcrumb :pageTitle="$pageTitle" />

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first('error') ?: $errors->first() }}
            </div>
        @endif

        <x-common.component-card
            :title="$cardTitle"
            :desc="$cardDesc"
        >
            <form
                method="POST"
                action="{{ $formAction }}"
                class="space-y-6"
            >
                @csrf
                @if($isEditing)
                    @method('PUT')
                @endif
                @include('purchases._create_pos', ['purchaseIndexUrl' => $purchaseIndexUrl])
            </form>
        </x-common.component-card>

        <x-ui.modal
            x-data="{ open: false }"
            x-on:open-provider-modal.window="open = true"
            x-on:close-provider-modal.window="open = false"
            :isOpen="false"
            :showCloseButton="false"
            class="w-full max-w-7xl"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar proveedor</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div x-show="quickProviderError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickProviderError"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                        <x-form.select-autocomplete-inline
                            fieldKey="qpp_type"
                            valueVar="quickProvider.person_type"
                            optionsListExpr="quickProviderPersonTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            emptyText="Tipo de persona"
                            pickExpr="quickProvider.person_type = opt.value"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                        <div class="flex items-center gap-2">
                            <input x-model="quickProvider.document_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" :placeholder="isQuickProviderRuc() ? 'Ingrese el RUC (11 digitos)' : 'Ingrese el DNI u otro documento'" required>
                            <button
                                type="button"
                                @click="fetchQuickProviderDocument()"
                                :disabled="creatingProviderLoading"
                                class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
                            >
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" x-text="isQuickProviderRuc() ? 'Razon social' : 'Nombres'"></label>
                        <input x-model="quickProvider.first_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" :placeholder="isQuickProviderRuc() ? 'Ingrese la razon social' : 'Ingrese los nombres'" required>
                    </div>
                    <div x-show="!isQuickProviderRuc()" x-cloak>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                        <input x-model="quickProvider.last_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese los apellidos" :required="!isQuickProviderRuc()">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" x-text="isQuickProviderRuc() ? 'Fecha de inscripcion' : 'Fecha de nacimiento'"></label>
                        <div class="flex items-center gap-2">
                            <input type="date" x-ref="quickProviderFechaInput" x-model="quickProvider.fecha_nacimiento" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-sm">
                            <button type="button" @click="$refs.quickProviderFechaInput?.showPicker?.()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100" aria-label="Abrir calendario" title="Abrir calendario">
                                <i class="ri-calendar-line text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <div x-show="!isQuickProviderRuc()" x-cloak>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                        <x-form.select-autocomplete-inline
                            fieldKey="qpp_gen"
                            valueVar="quickProvider.genero"
                            optionsListExpr="quickProviderGeneroOptions"
                            optionLabel="label"
                            optionValue="value"
                            emptyText="Seleccione genero"
                            pickExpr="quickProvider.genero = opt.value"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                        <input x-model="quickProvider.phone" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el telefono">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" x-model="quickProvider.email" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el email">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                        <input x-model="quickProvider.address" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Direccion (opcional)">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Dias de credito</label>
                        <input type="number" min="0" step="1" x-model.number="quickProvider.credit_days" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                        <x-form.select-autocomplete-inline
                            fieldKey="qpp_dept"
                            valueVar="quickProvider.department_id"
                            optionsListExpr="departments"
                            optionLabel="name"
                            optionValue="id"
                            emptyText="Seleccione departamento"
                            pickExpr="quickProvider.department_id = String(opt.id); onQuickProviderDepartmentChange()"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                        <x-form.select-autocomplete-inline
                            fieldKey="qpp_prov"
                            valueVar="quickProvider.province_id"
                            optionsListExpr="filteredQuickProviderProvinces"
                            optionLabel="name"
                            optionValue="id"
                            emptyText="Seleccione provincia"
                            pickExpr="quickProvider.province_id = String(opt.id); onQuickProviderProvinceChange()"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                        <x-form.select-autocomplete-inline
                            fieldKey="qpp_dist"
                            valueVar="quickProvider.location_id"
                            optionsListExpr="filteredQuickProviderDistricts"
                            optionLabel="name"
                            optionValue="id"
                            emptyText="Seleccione distrito"
                            pickExpr="quickProvider.location_id = String(opt.id)"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        />
                    </div>

                    <div class="md:col-span-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                        <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" checked disabled class="h-4 w-4 rounded border-gray-300 text-brand-500">
                            <span>Proveedor</span>
                        </div>
                    </div>

                    <div class="md:col-span-4 mt-2 flex gap-2">
                        <x-ui.button type="button" size="md" variant="primary" style="background-color:#00A389;color:#fff;" @click="saveQuickProvider()">
                            <i class="ri-save-line"></i><span x-text="creatingProviderLoading ? 'Guardando...' : 'Guardar proveedor'"></span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="$dispatch('close-provider-modal')"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </div>
            </div>
        </x-ui.modal>

        @include('products._modals_quick_create', $productQuickCreate ?? [])
    </div>
@endsection
