@extends('layouts.app')

@section('content')
    <script>
        // Datos globales de ProductBranch
        window.productBranchData = {
            @php
                $branchId = session('branch_id');
            @endphp
            @foreach ($products as $product)
                @php
                    $productBranch = $product->productBranches->where('branch_id', $branchId)->first();
                @endphp
                @if($productBranch)
                    {{ $product->id }}: {
                        stock: {{ $productBranch->stock }},
                        price: {{ $productBranch->price }},
                        tax_rate_id: {{ $productBranch->tax_rate_id }},
                        stock_minimum: {{ $productBranch->stock_minimum }},
                        stock_maximum: {{ $productBranch->stock_maximum }},
                        minimum_sell: {{ $productBranch->minimum_sell ?? 0 }},
                        minimum_purchase: {{ $productBranch->minimum_purchase ?? 0 }}
                    },
                @endif
            @endforeach
        };

        // Rutas de productos
        window.productRoutes = {
            @foreach ($products as $product)
                {{ $product->id }}: '{{ route('admin.products.product_branches.store', $product) }}',
            @endforeach
        };

        
    </script>

    <div x-data="{
        openRow: null
    }">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $product = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    $routeCandidates = [$action];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                    }
                    $routeCandidates = array_merge(
                        $routeCandidates,
                        array_map(fn ($name) => $name . '.index', $routeCandidates)
                    );

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $product ? route($routeName, $product) : route($routeName);
                        } catch (\Exception $e) {
                            $url = '#';
                        }
                    } else {
                        $url = '#';
                    }
                }

                $targetViewId = $viewId;
                if ($operation && !empty($operation->view_id_action)) {
                    $targetViewId = $operation->view_id_action;
                }

                if ($targetViewId && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'view_id=' . urlencode($targetViewId);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'products.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
        
        <x-common.page-breadcrumb pageTitle="Productos" />

        <x-common.component-card title="Productos" desc="Gestiona los productos.">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-36 flex-none">
                        <x-form.select-autocomplete
                            name="per_page"
                            :value="$perPage"
                            :options="collect([10, 20, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                            placeholder="Por página"
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="relative flex-1 min-w-[320px] w-full sm:w-auto">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                            placeholder="Buscar por codigo, descripcion o marca"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>
                    <div class="w-full sm:w-48 flex-none">
                        <x-form.select-autocomplete
                            name="category_id"
                            :value="$selectedCategoryId ?? 0"
                            :options="collect($categories)->map(fn($c) => ['value' => $c->id, 'label' => $c->description])->prepend(['value' => 0, 'label' => 'Todas las categorías'])->values()->all()"
                            placeholder="Todas las categorías"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="w-full sm:w-48 flex-none">
                        <x-form.select-autocomplete
                            name="product_type_id"
                            :value="$selectedProductTypeId ?? 0"
                            :options="collect($productTypes)->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->prepend(['value' => 0, 'label' => 'Todos los tipos'])->values()->all()"
                            placeholder="Todos los tipos"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="flex flex-1 flex-wrap items-center gap-2 min-w-fit sm:min-w-[200px]">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.products.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                            $isCreate = str_contains($operation->action ?? '', 'products.create');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button size="md" variant="primary" type="button" style="{{ $topStyle }}" @click="$dispatch('open-product-type-selector')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                    @if ($topOperations->isEmpty())
                        <x-ui.button size="md" variant="primary" type="button"
                            style=" background-color: #12f00e; color: #111827;" @click="$dispatch('open-product-type-selector')">
                            <i class="ri-add-line"></i>
                            <span>Nuevo producto</span>
                        </x-ui.button>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.products.import-template', $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5">
                            <i class="ri-download-2-line"></i>
                            <span>Descargar plantilla</span>
                        </x-ui.link-button>
                        <form method="POST" action="{{ route('admin.products.import-excel') }}" enctype="multipart/form-data" class="inline" data-turbo="false">
                            @csrf
                            @if ($viewId)
                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                            @endif
                            <input type="file" name="file" id="product-excel-import-input" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" class="hidden" onchange="if (this.files.length) this.form.submit();">
                            <x-ui.button size="md" variant="outline" type="button" class="h-11 border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5"
                                onclick="document.getElementById('product-excel-import-input').click();">
                                <i class="ri-file-excel-2-line"></i>
                                <span>Importar Excel</span>
                            </x-ui.button>
                        </form>
                    </div>
                </div>
            </div>

            @if ($errors->has('file'))
            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/30 dark:text-red-200">
                <strong>Importación:</strong> {{ $errors->first('file') }}
            </div>
            @endif

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl"></th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden md:table-cell w-32 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Código</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden lg:table-cell w-36 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Marca</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Descripción</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden sm:table-cell w-48 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Categoría</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden lg:table-cell w-40 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Unidad base</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden xl:table-cell w-40 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Tipo</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="hidden xl:table-cell w-32 px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Stock</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="w-40 px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr
                                class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <button type="button"
                                        @click="openRow === {{ $product->id }} ? openRow = null : openRow = {{ $product->id }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 dark:bg-brand-500 dark:text-white">
                                        <i class="ri-add-line" x-show="openRow !== {{ $product->id }}"></i>
                                        <i class="ri-subtract-line" x-show="openRow === {{ $product->id }}"></i>
                                    </button>
                                </td>
                                <td class="hidden md:table-cell px-5 py-4 text-center sm:px-6">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                        {{ $product->code }}</p>
                                </td>
                                <td class="hidden lg:table-cell px-5 py-4 text-center sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                        {{ $product->marca !== null && $product->marca !== '' ? $product->marca : '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $product->description }}
                                    </p>
                                </td>
                                <td class="hidden sm:table-cell px-5 py-4 text-center sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                        {{ $product->category?->description ?? '-' }}</p>
                                </td>
                                <td class="hidden lg:table-cell px-5 py-4 text-center sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                        {{ $product->baseUnit?->description ?? '-' }}</p>
                                </td>
                                <td class="hidden xl:table-cell px-5 py-4 text-center sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $product->productType?->name ?? '-' }}</p>
                                </td>
                                <td class="hidden xl:table-cell px-5 py-4 text-center sm:px-6">
                                    @php
                                        $branchId = session('branch_id');
                                        $rowProductBranch = $product->productBranches->where('branch_id', $branchId)->first();
                                    @endphp
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                        {{ $rowProductBranch ? number_format((float) $rowProductBranch->stock, 2) : '0.00' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <div class="flex items-center justify-center gap-2">

                                        {{-- <div class="relative group">
                                            <x-ui.button size="icon" type="button"
                                                @click="$dispatch('open-product-branch-modal', { productId: {{ $product->id }} })"
                                                className="bg-blue-500 text-white hover:bg-blue-600 ring-0 rounded-full"
                                                style="border-radius: 100%; background-color: #2493fb; color: #111827;"
                                                aria-label="Agregar a sucursal">
                                                <i class="ri-store-line"></i>
                                            </x-ui.button>
                                            <span
                                                class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                style="transition-delay: 0.5s;">Agregar a sucursal</span>
                                        </div> --}}
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl($action, $product, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete" data-swal-title="Eliminar producto?"
                                                        data-swal-text="Se eliminara {{ $product->description }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span
                                                            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                            style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span
                                                            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                            style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                       
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $product->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                <td colspan="9" class="px-6 py-4">
                                    @php
                                        $branchId = session('branch_id');
                                        $productBranch = $product->productBranches->where('branch_id', $branchId)->first();
                                        if ($productBranch) {
                                            $productBranch->loadMissing('taxRate');
                                        }
                                    @endphp
                                    @if($productBranch)
                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 w-full">
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Abreviatura</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->abbreviation ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kardex</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->kardex === 'Y' ? 'Sí' : 'No' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Receta</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->recipe ? 'Sí' : 'No' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Complemento</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->complement === 'Y' ? 'Sí' : 'No' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Modo complemento</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->complement_mode ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Clasificación</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->classification ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($productBranch->status ?? 'A') === 'A' ? 'Activo' : 'Inactivo' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Precio venta</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">S/ {{ number_format((float) $productBranch->price, 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Precio compra</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">S/ {{ number_format((float) ($productBranch->purchase_price ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Costo promedio</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">S/ {{ number_format((float) ($productBranch->avg_cost ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stock mínimo</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($productBranch->stock_minimum ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stock máximo</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($productBranch->stock_maximum ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Venta mínima</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($productBranch->minimum_sell ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Compra mínima</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($productBranch->minimum_purchase ?? 0), 2) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Venta unitaria</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($productBranch->unit_sale ?? 'N') === 'Y' ? 'Sí' : 'No' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Favorito</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($productBranch->favorite ?? 'N') === 'Y' ? 'Sí' : 'No' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha vencimiento</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $productBranch->expiration_date ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Duración (min)</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $productBranch->duration_minutes ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasa de impuesto</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                                    @if($productBranch->tax_rate_id && $productBranch->taxRate)
                                                        {{ number_format((float) $productBranch->taxRate->tax_rate, 2) }}%
                                                    @else
                                                        <span class="text-gray-400">Sin tasa</span>
                                                    @endif
                                                </p>
                                            </div>
                                          
                                        </div>
                                        <div class="mt-3 grid gap-3 lg:grid-cols-4">
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 lg:col-span-3">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Características</p>
                                                <p class="mt-1 text-sm font-medium leading-6 text-gray-800 dark:text-gray-200">{{ $product->features ?: 'Sin características registradas.' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Imagen</p>
                                                @if($product->image)
                                                    <div class="mt-2 flex items-center gap-3">
                                                        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->description }}" class="h-16 w-16 rounded-xl border border-gray-200 object-cover dark:border-gray-700">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $product->description ?: 'Producto' }}</p>
                                                    </div>
                                                @else
                                                    <p class="mt-1 text-sm font-medium text-gray-400">Sin imagen registrada.</p>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/30">
                                            Este producto no está registrado en la sucursal actual.
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div
                                            class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-restaurant-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay
                                            productos registrados.</p>
                                        <p class="text-gray-500">Crea el primer producto para comenzar.</p>
                                        <x-ui.button size="sm" variant="primary" type="button"
                                            @click="$dispatch('open-product-type-selector')">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar producto</span>
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->total() }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $products->links('vendor.pagination.forced') }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal
            x-data="{
                open: false,
                productId: null,
                formData: {
                    stock: 0,
                    price: 0.00,
                    tax_rate_id: '',
                    stock_minimum: 0.000000,
                    stock_maximum: 0.000000,
                    minimum_sell: 0.000000,
                    minimum_purchase: 0.000000
                },
                handleOpen(event) {
                    this.productId = event.detail?.productId || null;
                    console.log('Product ID:', this.productId);
                    console.log('Available data:', window.productBranchData);
                    
                    if (this.productId) {
                        // Cargar datos existentes si hay
                        if (window.productBranchData && window.productBranchData[this.productId]) {
                            console.log('Loading existing data:', window.productBranchData[this.productId]);
                            this.formData = {...window.productBranchData[this.productId]};
                            console.log('Form data after loading:', this.formData);
                        } else {
                            console.log('No existing data, using defaults');
                            // Resetear a valores por defecto
                            this.formData = {
                                stock: 0,
                                price: 0.00,
                                tax_rate_id: '',
                                stock_minimum: 0.000000,
                                stock_maximum: 0.000000,
                                minimum_sell: 0.000000,
                                minimum_purchase: 0.000000
                            };
                        }
                        const form = document.getElementById('product-branch-form');
                        if (form && window.productRoutes && window.productRoutes[this.productId]) {
                            form.action = window.productRoutes[this.productId];
                        }
                    }
                    this.open = true;
                }
            }"
            @open-product-branch-modal.window="handleOpen($event)"
            @close-product-branch-modal.window="open = false"
            :isOpen="false"
            :showCloseButton="false"
            class="w-full max-w-5xl sm:max-w-6xl"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                            <i class="ri-store-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Agregar producto a sucursal</h3>
                            <p class="mt-1 text-sm text-gray-500">Ingresa la informacion del producto para la sucursal.</p>
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

                @if ($errors->any())
                    <div class="mb-5">
                        <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                    </div>
                @endif

                <form id="product-branch-form" method="POST" action="{{ route('admin.product_branches.store_generic') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="product_id" x-model="productId">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('products.product_branch._form', [
                        'productBranch' => null,
                        'currentBranch' => $currentBranch,
                        'taxRates' => $taxRates,
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

        @include('products._modals_quick_create', [
            'viewId' => $viewId,
            'productTypes' => $productTypes ?? collect(),
            'nextProductCode' => $nextProductCode ?? '1',
            'currentBranch' => $currentBranch ?? null,
            'taxRates' => $taxRates ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'categories' => $categories ?? collect(),
            'units' => $units ?? collect(),
            'afterCreate' => null,
        ])
    </div>
@endsection


