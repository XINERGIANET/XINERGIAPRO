@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $purchaseIndexUrl = route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []);
    @endphp

    <div
        id="purchases-create-view"
        x-data="purchaseCreateForm(@js($purchaseCreateConfig))"
        x-init="init()"
    >
        <x-common.page-breadcrumb pageTitle="Nueva compra" />

        <x-common.component-card
            title="Compras | Nuevo"
            desc="Registra la compra, actualiza stock y, si corresponde, genera la salida de caja desde un solo flujo."
        >
            <form
                method="POST"
                action="{{ route('admin.purchases.store', $viewId ? ['view_id' => $viewId] : []) }}"
                class="space-y-6"
            >
                @csrf
                @include('purchases._create_pos', ['purchaseIndexUrl' => $purchaseIndexUrl])
            </form>
        </x-common.component-card>

        <x-ui.modal
            x-data="{ open: false }"
            x-on:open-provider-modal.window="open = true"
            x-on:close-provider-modal.window="open = false"
            :isOpen="false"
            :showCloseButton="false"
            class="max-w-4xl"
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
                        <select x-model="quickProvider.person_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="CARNET DE EXTRANGERIA">CARNET DE EXTRANGERIA</option>
                            <option value="PASAPORTE">PASAPORTE</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                        <div class="flex items-center gap-2">
                            <input x-model="quickProvider.document_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Documento" required>
                            <button
                                type="button"
                                @click="fetchReniecQuickProvider()"
                                :disabled="creatingProviderLoading"
                                class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
                            >
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Nombres</label>
                        <input x-model="quickProvider.first_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombres / Razon social" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                        <input x-model="quickProvider.last_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Apellidos" required>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                        <input type="date" x-model="quickProvider.fecha_nacimiento" onclick="this.showPicker && this.showPicker()" onfocus="this.showPicker && this.showPicker()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                        <select x-model="quickProvider.genero" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione genero</option>
                            <option value="MASCULINO">MASCULINO</option>
                            <option value="FEMENINO">FEMENINO</option>
                            <option value="OTRO">OTRO</option>
                        </select>
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
                        <select x-model="quickProvider.department_id" @change="onQuickProviderDepartmentChange()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione departamento</option>
                            <template x-for="department in departments" :key="`quick-provider-department-${department.id}`">
                                <option :value="String(department.id)" :selected="String(department.id) === String(quickProvider.department_id)" x-text="department.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                        <select x-model="quickProvider.province_id" @change="onQuickProviderProvinceChange()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione provincia</option>
                            <template x-for="province in filteredQuickProviderProvinces" :key="`quick-provider-province-${province.id}`">
                                <option :value="String(province.id)" :selected="String(province.id) === String(quickProvider.province_id)" x-text="province.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                        <select x-model="quickProvider.location_id" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <option value="">Seleccione distrito</option>
                            <template x-for="district in filteredQuickProviderDistricts" :key="`quick-provider-district-${district.id}`">
                                <option :value="String(district.id)" :selected="String(district.id) === String(quickProvider.location_id)" x-text="district.name"></option>
                            </template>
                        </select>
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
    </div>
@endsection
