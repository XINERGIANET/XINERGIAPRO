@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    
    // --- ICONOS ---
    $SearchIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
@endphp

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $view = null, $operation = null) use ($viewId) {
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
                            $url = $view ? route($routeName, $view) : route($routeName);
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
                if (str_contains($action, 'views.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
    
    <x-common.page-breadcrumb pageTitle="Vistas" />

    <x-common.component-card title="Gestión de Vistas" desc="Administra las vistas del sistema para asociarlas a los menús.">
        
        {{-- BARRA DE BÚSQUEDA Y ACCIONES --}}
        <div class="flex flex-col gap-4 mb-6 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-wrap gap-2 sm:flex-nowrap sm:items-center min-w-0">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div class="w-full sm:w-auto flex-none">
                    <x-form.select-autocomplete
                        name="per_page"
                        :value="request('per_page', 10)"
                        :options="collect([10, 20, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                        placeholder="Por página"
                        :submit-on-change="true"
                        inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                </div>
                <div class="relative flex-1 min-w-0">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                        {!! $SearchIcon !!}
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder=" Buscar vista..."
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </div>
                <div class="flex items-center gap-2 flex-none">
                    <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('admin.views.index', $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.link-button>

                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                            $isCreate = str_contains($operation->action ?? '', 'views.create');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button
                                size="md"
                                variant="primary"
                                type="button"
                                class="h-11 px-6 shadow-sm whitespace-nowrap"
                                style="{{ $topStyle }}"
                                @click="$dispatch('open-view-modal')"
                            >
                                <i class="{{ $operation->icon }} text-lg"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button
                                size="md"
                                variant="primary"
                                class="h-11 px-6 shadow-sm whitespace-nowrap"
                                style="{{ $topStyle }}"
                                href="{{ $topActionUrl }}"
                            >
                                <i class="{{ $operation->icon }} text-lg"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                </div>
            </form>
        </div>

        {{-- TABLA DE DATOS --}}
        <div class="table-responsive lg:!overflow-visible rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155;" class="px-5 py-4 text-center first:rounded-tl-xl">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">ID</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-4 text-left">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Nombre</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-4 text-left">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Abreviatura</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-4 text-center">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Estado</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-4 text-center last:rounded-tr-xl">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($views as $view)
                            <tr class="group/row transition hover:bg-gray-50/80 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 text-center whitespace-nowrap">
                                    <span class="text-gray-500 font-bold text-xs">#{{ $view->id }}</span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/10 shrink-0">
                                            <i class="ri-eye-line text-xs"></i>
                                        </div>
                                        <p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ $view->name }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400 capitalize">{{ $view->abbreviation ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-center whitespace-nowrap">
                                    <x-ui.badge variant="light" color="{{ $view->status ? 'success' : 'error' }}">
                                        {{ $view->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $actionUrl = $resolveActionUrl($action, $view, $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isDelete)
                                                <form method="POST" action="{{ $actionUrl }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="Eliminar vista?"
                                                    data-swal-text="Se eliminará {{ $view->name }}. Esta acción no se puede deshacer."
                                                    data-swal-confirm="Sí, eliminar"
                                                    data-swal-cancel="Cancelar"
                                                    data-swal-confirm-color="#ef4444"
                                                    data-swal-cancel-color="#6b7280">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($viewId)
                                                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                    @endif
                                                    <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.button>
                                                    <span
                                                        class="pointer-events-none opacity-0 group-hover:opacity-100 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1 text-xs font-medium text-white shadow-xl z-50 transition">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </form>
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="pointer-events-none opacity-0 group-hover:opacity-100 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1 text-xs font-medium text-white shadow-xl z-50 transition">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-16">
                                    <div class="flex flex-col items-center gap-4 text-center">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-gray-800/50 dark:text-gray-600">
                                            <i class="ri-eye-line text-3xl"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-800 dark:text-white/90">No hay vistas registradas</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
             <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $views->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $views->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $views->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                    {{ $views->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal 
        x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" 
        @open-view-modal.window="open = true" 
        @close-view-modal.window="open = false" 
        :isOpen="false" 
        :showCloseButton="false"
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                        <i class="ri-eye-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar vista</h3>
                        <p class="mt-1 text-sm text-gray-500">Ingresa la información principal de la vista.</p>
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
                    <x-ui.alert 
                        variant="error" 
                        title="Atención" 
                        message="{{ $errors->first('error') ?: 'Existen errores de validación. Revisa los campos.' }}" 
                    />
                </div>
            @endif

            <form method="POST" action="{{ route('admin.views.store') }}" class="space-y-6">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('views._form', ['view' => null])

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
    
    </div> 
@endsection

