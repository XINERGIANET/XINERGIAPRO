@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $productType = null, $operation = null) use ($viewId) {
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
                    $routeCandidates = array_merge($routeCandidates, array_map(fn ($name) => $name . '.index', $routeCandidates));

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $productType ? route($routeName, $productType) : route($routeName);
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

            $resolveTextColor = fn ($operation) => (str_contains($operation->action ?? '', 'product-types.create') || str_contains($operation->action ?? '', 'product_types.create')) ? '#111827' : '#FFFFFF';
        @endphp

        <x-common.page-breadcrumb pageTitle="Tipos de producto" />

        <x-common.component-card title="Tipos de producto" desc="Configura los tipos de producto por sucursal.">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
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
                    <div class="relative flex-1">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre, descripcion o comportamiento" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('product-types.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $action = (string) ($operation->action ?? '');
                            $isCreate = str_contains($action, 'product-types.create') || str_contains($action, 'product_types.create') || str_contains($action, 'create');
                            $createStyle = 'background-color: #12f00e; color: #111827;';
                        @endphp
                        @if ($isCreate)
                            <x-ui.button size="md" variant="primary" type="button" style="{{ $createStyle }}" @click="$dispatch('open-product-type-modal')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            @php
                                $topTextColor = $resolveTextColor($operation);
                                $topColor = $operation->color ?: '#3B82F6';
                                $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            @endphp
                            <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $resolveActionUrl($operation->action ?? '', null, $operation) }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                    @if ($topOperations->isEmpty())
                        <x-ui.button size="md" variant="primary" type="button" style="background-color: #12f00e; color: #111827;" @click="$dispatch('open-product-type-modal')">
                            <i class="ri-add-line"></i>
                            <span>Nuevo tipo</span>
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[900px]">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl w-1/3"><p class="font-semibold text-white text-theme-xs uppercase">Nombre</p></th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Comportamiento</p></th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Estado</p></th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl"><p class="font-semibold text-white text-theme-xs uppercase">Acciones</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productTypes as $productType)
                            <tr class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 text-left sm:px-6 w-1/3">
                                    <div class="flex items-center justify-start gap-3">
                                        @if ($productType->icon)
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                <i class="{{ $productType->icon }}"></i>
                                            </span>
                                        @endif
                                        <div class="text-left">
                                            <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $productType->name }}</p>
                                            @if ($productType->description)
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $productType->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <x-ui.badge variant="light" color="{{ $productType->behavior === 'SUPPLY' ? 'warning' : 'success' }}">
                                        {{ $productType->behavior === 'SUPPLY' ? 'Suministro' : 'Vendible' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <x-ui.badge variant="light" color="{{ $productType->status ? 'success' : 'error' }}">
                                        {{ $productType->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = (string) ($operation->action ?? '');
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isEdit = str_contains($action, 'edit') || str_contains($action, 'product-types.edit') || str_contains($action, 'product_types.edit');
                                                    $updateUrl = route('product-types.update', $productType);
                                                    $actionUrl = $isEdit ? $updateUrl : $resolveActionUrl($action, $productType, $operation);
                                                    $editStyle = 'background-color: #FBBF24; color: #111827;';
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : ($isEdit ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}" class="relative group js-swal-delete" data-swal-title="Eliminar tipo?" data-swal-text="Se eliminara {{ $productType->name }}. Esta accion no se puede deshacer." data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar" data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            {{ $operation->name }}
                                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        @if ($isEdit)
                                                            <x-ui.button size="icon" variant="edit" type="button" className="rounded-xl" style="{{ $editStyle }}" aria-label="{{ $operation->name }}"
                                                                @click="$dispatch('open-product-type-edit-modal', {{ \Illuminate\Support\Js::from(['updateUrl' => $updateUrl, 'productType' => $productType->only(['id','name','description','behavior','status','icon'])]) }})">
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.button>
                                                        @else
                                                            <x-ui.link-button size="icon" variant="{{ $variant }}" href="{{ $actionUrl }}" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.link-button>
                                                        @endif
                                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            {{ $operation->name }}
                                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="relative group">
                                                <x-ui.button size="icon" variant="edit" type="button" className="rounded-xl" style="background-color: #FBBF24; color: #111827;" aria-label="Editar"
                                                    @click="$dispatch('open-product-type-edit-modal', {{ \Illuminate\Support\Js::from(['updateUrl' => route('product-types.update', $productType), 'productType' => $productType->only(['id','name','description','behavior','status','icon'])]) }})">
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">Editar</span>
                                            </div>
                                            <form method="POST" action="{{ route('product-types.destroy', array_merge([$productType], $viewId ? ['view_id' => $viewId] : [])) }}" class="relative group js-swal-delete" data-swal-title="Eliminar tipo?" data-swal-text="Se eliminara {{ $productType->name }}. Esta accion no se puede deshacer." data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar" data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button size="icon" variant="eliminate" type="submit" className="rounded-xl" style="background-color: #EF4444; color: #FFFFFF;" aria-label="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">Eliminar</span>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No hay tipos de producto configurados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->total() }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $productTypes->links('vendor.pagination.forced') }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-product-type-modal.window="open = true" @close-product-type-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="w-full max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar tipo de producto</h3>
                        <p class="mt-1 text-sm text-gray-500">Configura un tipo de producto para la sucursal actual.</p>
                    </div>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white" aria-label="Cerrar">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-sm text-error-700 dark:border-error-500/50 dark:bg-error-500/20 dark:text-error-300">
                        <p class="mb-2 font-semibold">Revisa los campos</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('product-types.store') }}" class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('product-types._form', ['productType' => null])

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

        <x-ui.modal
            x-data="{ open: false, editPayload: null }"
            @open-product-type-edit-modal.window="editPayload = $event.detail; open = true"
            @close-product-type-edit-modal.window="open = false"
            :isOpen="false"
            :showCloseButton="true"
            class="w-full max-w-3xl"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de producto</h3>
                    <p class="mt-1 text-sm text-gray-500">Actualiza la configuración del tipo.</p>
                </div>
                <template x-if="editPayload">
                    <form :action="editPayload.updateUrl" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @if (!empty($viewId))
                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                        @endif
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
                                <input type="text" name="name" x-model="editPayload.productType.name" required placeholder="Ingrese el nombre"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                            <div x-data="iconPicker()" x-init="init()" :data-initial-icon="editPayload?.productType?.icon ?? ''" class="relative">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Icono</label>
                                <div class="relative">
                                    <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <i class="ri-grid-line text-lg"></i>
                                    </span>
                                    <input
                                        type="text"
                                        name="icon"
                                        x-ref="iconInput"
                                        x-model="search"
                                        placeholder="Busca o escribe un icono..."
                                        autocomplete="off"
                                        spellcheck="false"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        @focus="openDropdown()"
                                        @input="openDropdown()"
                                        @keydown.escape.stop="closeDropdown()"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                        @click="toggleDropdown()"
                                        aria-label="Abrir selector de iconos"
                                    >
                                        <i class="ri-arrow-down-s-line text-lg transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                    </button>
                                </div>
                                <div
                                    x-show="open"
                                    class="absolute left-0 top-full z-50 mt-2 w-full rounded-xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                                    @click.outside="closeDropdown()"
                                >
                                    <div class="mb-2 flex items-center justify-between text-xs text-gray-500">
                                        <span x-text="loading ? 'Cargando iconos...' : `${filteredIcons.length} iconos`"></span>
                                        <button type="button" class="text-brand-500 hover:text-brand-600" @click="clear()">Limpiar</button>
                                    </div>
                                    <template x-if="loading">
                                        <div class="flex items-center gap-3 text-sm text-gray-500">
                                            <div class="h-5 w-5 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                                            <span>Cargando iconos...</span>
                                        </div>
                                    </template>
                                    <template x-if="!loading && error">
                                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                                            No se pudieron cargar los iconos.
                                        </div>
                                    </template>
                                    <template x-if="!loading && !error && displayedIcons.length === 0">
                                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                                            No se encontraron iconos.
                                        </div>
                                    </template>
                                    <div class="max-h-64 overflow-y-auto custom-scrollbar" x-show="!loading && !error && displayedIcons.length">
                                        <div class="grid gap-2 grid-cols-4 sm:grid-cols-6">
                                            <template x-for="icon in displayedIcons" :key="icon">
                                                <button
                                                    type="button"
                                                    class="flex items-center justify-center rounded-lg border border-gray-200 bg-white px-2 py-3 text-gray-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                                    @click="select(icon)"
                                                >
                                                    <span class="text-xl"><i :class="icon"></i></span>
                                                </button>
                                            </template>
                                        </div>
                                        <div class="mt-2 text-xs text-gray-400 text-center" x-show="filteredIcons.length > displayedIcons.length">
                                            Escribe para filtrar mas resultados.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Comportamiento</label>
                                <select name="behavior" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                    x-model="editPayload.productType.behavior">
                                    <option value="SELLABLE">Vendible</option>
                                    <option value="SUPPLY">Suministro</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
                                <select name="status" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                    x-model="editPayload.productType.status">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripción</label>
                                <textarea name="description" rows="3" x-model="editPayload.productType.description" placeholder="Descripción opcional"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <x-ui.button type="submit" size="md" variant="primary">
                                <i class="ri-save-line"></i>
                                <span>Actualizar</span>
                            </x-ui.button>
                            <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                                <i class="ri-close-line"></i>
                                <span>Cancelar</span>
                            </x-ui.button>
                        </div>
                    </form>
                </template>
            </div>
        </x-ui.modal>
    </div>
@endsection


