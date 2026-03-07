@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
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
                            $url = $model ? route($routeName, $model) : route($routeName);
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
                if (str_contains($action, 'roles.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Roles" />

        <x-common.component-card title="Gestión de Roles" desc="Administra los roles del sistema para definir permisos.">
            
            {{-- BARRA DE BÚSQUEDA Y ACCIONES --}}
            <div class="flex flex-col gap-4 mb-6 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-wrap gap-2 sm:flex-nowrap sm:items-center min-w-0">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-full sm:w-auto flex-none">
                         <select name="per_page"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            onchange="this.form.submit()">
                            @foreach ([10, 20, 50, 100] as $size)
                                <option value="{{ $size }}" @selected(request('per_page', 10) == $size)>{{ $size }} / página</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="relative flex-1 min-w-0">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                             <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder=" Buscar rol..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex items-center gap-2 flex-none">
                        <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.roles.index', $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>

                        @foreach ($topOperations as $operation)
                            @php
                                $topTextColor = $resolveTextColor($operation);
                                $topColor = $operation->color ?: '#3B82F6';
                                $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                                $isCreate = str_contains($operation->action ?? '', 'roles.create');
                            @endphp
                            @if ($isCreate)
                                <x-ui.button
                                    size="md"
                                    variant="primary"
                                    type="button"
                                    class="h-11 px-6 shadow-sm whitespace-nowrap"
                                    style="{{ $topStyle }}"
                                    @click="$dispatch('open-role-modal')"
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
                <table class="w-full min-w-[880px]">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Nombre</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Descripción</p>
                            </th>
                            <th style="background-color: #334155;" class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $role->name }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $role->description }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $actionUrl = $resolveActionUrl($action, $role, $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isDelete)
                                                <form method="POST" action="{{ $actionUrl }}"
                                                    class="relative group js-delete-role" data-role-name="{{ $role->name }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($viewId)
                                                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                    @endif
                                                    <x-ui.button
                                                        size="icon" variant="{{ $variant }}" style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                        type="submit"
                                                    >
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </form>
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
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
                                <td colspan="3" class="px-6 py-12 text-center text-gray-500">
                                    No hay roles registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $roles->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $roles->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $roles->total() }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $roles->links('vendor.pagination.forced') }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-role-modal.window="open = true" @close-role-modal.window="open = false" :isOpen="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-gray-400">Seguridad</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">Registrar Rol</h3>
                        <p class="mt-1 text-sm text-gray-500">Define los permisos generales para este rol.</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                        <i class="ri-shield-user-line text-2xl"></i>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mb-5">
                        <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('roles._form', ['role' => null])

                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" size="md" variant="primary">
                            <i class="ri-save-line"></i>
                            <span>Guardar Rol</span>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const bindDeleteSweetAlert = () => {
        document.querySelectorAll('.js-delete-role').forEach((form) => {
            if (form.dataset.swalBound === 'true') return;
            form.dataset.swalBound = 'true';

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const name = form.dataset.roleName || 'este rol';

                if (!window.Swal) {
                    form.submit();
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar rol?',
                    text: `Se eliminará "${name}". Esta acción no se puede deshacer.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    focusCancel: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    };
    document.addEventListener('DOMContentLoaded', bindDeleteSweetAlert);
    document.addEventListener('turbo:load', bindDeleteSweetAlert);
</script>
@endpush


