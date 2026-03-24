{{-- Modales: selector de tipo + formulario rápido de producto (reutilizable en compras, etc.) --}}
@php
    $viewIdModal = $viewId ?? request('view_id');
    $afterCreate = $afterCreate ?? null;
@endphp

<x-ui.modal
    x-data="{ open: false }"
    @open-product-type-selector.window="open = true"
    @close-product-type-selector.window="open = false"
    :isOpen="false"
    :showCloseButton="false"
    class="w-full max-w-4xl"
>
    <div class="p-6 sm:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                    <i class="ri-box-3-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Seleccionar tipo de producto</h3>
                    <p class="mt-1 text-sm text-gray-500">Elige el tipo de producto que deseas registrar.</p>
                </div>
            </div>
            <button
                type="button"
                @click="open = false"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                aria-label="Cerrar"
            >
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse (($productTypes ?? collect()) as $productType)
                @php
                    $isSupply = $productType->behavior === 'SUPPLY';
                    $cardStyle = $isSupply
                        ? 'border-color: #FACC15; background-color: #FEFCE8;'
                        : 'border-color: #BFDBFE; background-color: #EFF6FF;';
                    $iconStyle = $isSupply
                        ? 'background-color: #EAB308; color: #FFFFFF; box-shadow: 0 10px 20px rgba(234, 179, 8, 0.30);'
                        : 'background-color: #3B82F6; color: #FFFFFF; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.30);';
                @endphp
                <button
                    type="button"
                    class="rounded-3xl border p-6 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                    style="{{ $cardStyle }}"
                    @click="$dispatch('open-product-modal', { productTypeId: '{{ $productType->id }}' }); open = false"
                >
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full" style="{{ $iconStyle }}">
                        <i class="{{ $productType->icon ?: 'ri-box-3-line' }} text-4xl text-white"></i>
                    </div>
                    <h4 class="text-center text-2xl font-bold text-gray-900 dark:text-white">{{ $productType->name }}</h4>
                    <p class="mx-auto mt-3 max-w-sm text-center text-base text-gray-600 dark:text-gray-300">
                        {{ $productType->description ?: ($isSupply ? 'Materia prima o insumo usado para la operacion diaria.' : 'Producto elaborado listo para la venta o consumo.') }}
                    </p>
                </button>
            @empty
                <div class="md:col-span-2 rounded-2xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-800">
                    No hay tipos de producto activos para esta sucursal.
                </div>
            @endforelse
        </div>

        <div class="mt-6 flex justify-end">
            <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                <i class="ri-close-line"></i>
                <span>Cancelar</span>
            </x-ui.button>
        </div>
    </div>
</x-ui.modal>

<x-ui.modal
    @open-product-modal.window="
        open = true;
        $nextTick(() => {
            const field = $el.querySelector(`#product-type-select`);
            if (!field) return;

            const targetValue = $event.detail?.productTypeId ? String($event.detail.productTypeId) : '';
            if (!targetValue) return;

            field.value = targetValue;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    "
    @close-product-modal.window="open = false"
    :isOpen="false"
    :showCloseButton="false"
    class="w-full max-w-5xl sm:max-w-6xl lg:max-w-7xl"
>
    <div class="flex w-full flex-col min-h-0 p-6 sm:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                    <i class="ri-restaurant-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar producto</h3>
                    <p class="mt-1 text-sm text-gray-500">Ingresa la informacion del producto.</p>
                </div>
            </div>
            <button type="button" @click="open = false"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                aria-label="Cerrar">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>

        @if ($errors->any())
            <div class="mb-5">
                <x-ui.alert variant="error" title="Revisa los campos"
                    message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
            </div>
        @endif

        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="flex w-full flex-col min-h-0 space-y-6">
            @csrf
            @if (!empty($viewIdModal))
                <input type="hidden" name="view_id" value="{{ $viewIdModal }}">
            @endif
            @if ($afterCreate === 'purchase_create')
                <input type="hidden" name="after_create" value="purchase_create">
            @endif

            @include('products._form', [
                'product' => null,
                'defaultCode' => $nextProductCode ?? '1',
                'productTypes' => $productTypes ?? collect(),
                'lockProductType' => true,
                'currentBranch' => $currentBranch ?? null,
                'taxRates' => $taxRates ?? collect(),
                'productBranch' => null,
                'suppliers' => $suppliers ?? collect(),
                'categories' => $categories ?? collect(),
                'units' => $units ?? collect(),
            ])

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" size="md" variant="primary">
                    <i class="ri-save-line"></i>
                    <span>Guardar</span>
                </x-ui.button>
                <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                    <i class="ri-close-line"></i>
                    <span>Cancelar</span>
                </x-ui.button>
            </div>
        </form>
    </div>
</x-ui.modal>
