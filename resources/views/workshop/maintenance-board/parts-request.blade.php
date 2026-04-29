@extends('layouts.app')

@section('content')
<div x-data="Object.assign(typeof window.formAutocompleteHelpers === 'function' ? window.formAutocompleteHelpers() : {}, partsRequestData())" class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <x-common.page-breadcrumb
        pageTitle="Solicitud de Repuestos"
        :crumbs="[
            ['label' => 'Tablero de Mantenimiento', 'url' => route('workshop.maintenance-board.index')],
            ['label' => 'Tablero Correctivo', 'url' => route('workshop.maintenance-board.corrective')],
            ['label' => 'Solicitud de Repuestos'],
        ]"
    />

    <x-common.component-card>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">
            Solicitar Repuestos
        </h1>
        <p class="text-sm text-gray-500">Seleccione los repuestos requeridos y asigne a qué proveedor se le solicitará. OS: {{ str_pad($order->id, 8, '0', STR_PAD_LEFT) }}</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('workshop.maintenance-board.parts-request.store', $order) }}" class="mt-6" data-turbo="false">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Repuestos solicitados</h4>
                        <p class="text-xs text-gray-500">Agregue repuestos del catálogo o repuestos libres (glosa) y asigne su proveedor.</p>
                    </div>
                    <button type="button" @click="addPartLine()" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                        <i class="ri-add-line"></i><span class="ml-1">Agregar repuesto</span>
                    </button>
                </div>

                <template x-if="parts.length === 0">
                    <p class="text-sm text-gray-500 text-center py-4">No hay repuestos en la solicitud. Agregue uno para continuar.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(part, pindex) in parts" :key="part.uid">
                        <div class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-gray-50/80 p-3 lg:flex-row lg:items-end">
                            <input type="hidden" :name="`parts[${pindex}][detail_id]`" :value="part.detail_id || ''">
                            
                            <div class="min-w-0 flex-1 lg:w-1/4">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-600 uppercase tracking-wide">Repuesto (Catálogo)</label>
                                <input type="hidden" :name="`parts[${pindex}][product_id]`" :value="part.product_id">
                                <x-form.select-autocomplete-inline
                                    fieldKeyExpr="'part-' + part.uid"
                                    valueVar="part.product_id"
                                    optionsListExpr="productsCatalog"
                                    optionLabel="label"
                                    optionValue="id"
                                    emptyText="Seleccionar repuesto..."
                                    pickExpr="part.product_id = String(opt.id); part.description = ''; onProductChange(part)"
                                    inputClass="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm"
                                />
                            </div>

                            <div class="min-w-0 flex-1 lg:w-1/4">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-600 uppercase tracking-wide">Descripción Libre (Glosa)</label>
                                <input type="text" x-model="part.description" :name="`parts[${pindex}][description]`" :disabled="!!part.product_id" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:bg-gray-100 disabled:text-gray-400" placeholder="Escriba si no está en catálogo">
                            </div>

                            <div class="w-full lg:w-48">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-600 uppercase tracking-wide">Proveedor</label>
                                <select x-model="part.supplier_person_id" :name="`parts[${pindex}][supplier_person_id]`" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm">
                                    <option value="">Seleccionar proveedor...</option>
                                    <template x-for="sup in suppliers" :key="sup.id">
                                        <option :value="sup.id" x-text="sup.label"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="w-full lg:w-20">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-600 uppercase tracking-wide">Cant.</label>
                                <input type="number" min="0.01" step="any" x-model="part.qty" :name="`parts[${pindex}][qty]`" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm" required>
                            </div>

                            <div class="w-full lg:w-24">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-600 uppercase tracking-wide">Costo Est.</label>
                                <input type="number" min="0" step="0.01" x-model="part.unit_price" :name="`parts[${pindex}][unit_price]`" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm">
                            </div>

                            <button type="button" @click="removePartLine(pindex)" class="h-10 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100 mt-2 lg:mt-0 shrink-0">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </template>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones de la solicitud</label>
                    <textarea name="observations" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Opcional: detalles adicionales para las compras..."></textarea>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#10b981,#059669);color:#fff">
                    <i class="ri-checkbox-circle-line mr-1"></i> Confirmar y Solicitar Repuestos
                </x-ui.button>
                <a href="{{ route('workshop.maintenance-board.corrective') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
            </div>
        </form>
    </x-common.component-card>
</div>
@endsection

@push('scripts')
<script>
function partsRequestData() {
    return {
        productsCatalog: @js($products->map(function ($p) {
            return [
                'id' => $p->id,
                'label' => $p->description . ($p->marca ? ' (' . $p->marca . ')' : '') . ' - ' . $p->code,
                'price' => $p->price
            ];
        })->values()->all()),
        suppliers: @js($suppliers->map(function ($s) {
            return [
                'id' => $s->id,
                'label' => trim($s->document_number . ' - ' . $s->first_name . ' ' . $s->last_name)
            ];
        })->values()->all()),
        parts: [],

        init() {
            console.log("Catálogo de productos cargado:", this.productsCatalog);
            // Load existing parts from the order
            const existingParts = @js($order->details->map(function($d) {
                return [
                    'uid' => 'old_' + $d->id,
                    'detail_id' => $d->id,
                    'product_id' => $d->product_id ? (string)$d->product_id : '',
                    'description' => $d->description ?? '',
                    'qty' => (float)$d->qty,
                    'unit_price' => (float)$d->unit_price,
                    'supplier_person_id' => $d->supplier_person_id ? (string)$d->supplier_person_id : ''
                ];
            }));
            
            if (existingParts.length > 0) {
                this.parts = existingParts;
            } else {
                this.addPartLine();
            }
        },

        addPartLine() {
            this.parts.push({
                uid: 'new_' + Date.now() + Math.floor(Math.random() * 1000),
                detail_id: null,
                product_id: '',
                description: '',
                qty: 1,
                unit_price: 0,
                supplier_person_id: ''
            });
        },

        removePartLine(index) {
            this.parts.splice(index, 1);
        },

        onProductChange(part) {
            if (part.product_id) {
                const p = this.productsCatalog.find(x => String(x.id) === String(part.product_id));
                if (p) {
                    part.unit_price = parseFloat(p.price) || 0;
                }
            }
        }
    }
}
</script>
@endpush
