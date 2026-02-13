@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $companyViewId = request('company_view_id');
            $branchViewId = request('branch_view_id') ?? session('branch_view_id');
            $requestIcon = request('icon');
            $pageIconHtml = null;
            if (is_string($requestIcon) && preg_match('/^ri-[a-z0-9-]+$/', $requestIcon)) {
                $pageIconHtml = '<i class="' . $requestIcon . '"></i>';
            }
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, array $routeParams = [], $operation = null) use ($viewId, $companyViewId, $branchViewId, $requestIcon) {
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
                    if (str_starts_with($action, 'branches.')) {
                        $routeCandidates[] = 'admin.companies.' . $action;
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
                        $attempts = [];
                        if (!empty($routeParams)) {
                            $attempts[] = $routeParams;
                        }
                        if (count($routeParams) > 1) {
                            $attempts[] = array_slice($routeParams, 0, 2);
                        }
                        if (count($routeParams) > 0) {
                            $attempts[] = array_slice($routeParams, 0, 1);
                        }
                        $attempts[] = [];

                        $url = '#';
                        foreach ($attempts as $params) {
                            try {
                                $url = empty($params) ? route($routeName) : route($routeName, $params);
                                break;
                            } catch (\Exception $e) {
                                $url = '#';
                            }
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

                if ($branchViewId && $url !== '#' && !str_contains($url, 'branch_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'branch_view_id=' . urlencode($branchViewId);
                }

                if ($viewId && $url !== '#' && $targetViewId && $targetViewId !== $viewId && !str_contains($url, 'profile_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'profile_view_id=' . urlencode($viewId);
                }

                if ($companyViewId && $url !== '#' && !str_contains($url, 'company_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'company_view_id=' . urlencode($companyViewId);
                }

                if ($requestIcon && $url !== '#' && !str_contains($url, 'icon=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'icon=' . urlencode($requestIcon);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'profiles.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
        <x-common.page-breadcrumb
            pageTitle="Perfiles"
            :iconHtml="$pageIconHtml"
            :crumbs="[
                ['label' => 'Empresas', 'url' => route('admin.companies.index', $companyViewId ? ['view_id' => $companyViewId] : [])],
                ['label' =>  $company->legal_name . ' | Sucursales', 'url' => route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon])))],
                ['label' =>  $branch->legal_name . ' | Perfiles' ]
            ]"
        />

        <x-common.component-card
            title="Perfiles de {{ $branch->legal_name }}"
            desc="Lista los perfiles asignados a esta sucursal."
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    @if ($companyViewId)
                        <input type="hidden" name="company_view_id" value="{{ $companyViewId }}">
                    @endif
                    @if ($branchViewId)
                        <input type="hidden" name="branch_view_id" value="{{ $branchViewId }}">
                    @endif
                    @if ($requestIcon)
                        <input type="hidden" name="icon" value="{{ $requestIcon }}">
                    @endif
                    <div class="w-29">
                        <select
                            name="per_page"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            onchange="this.form.submit()"
                        >
                            @foreach ([10, 20, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / pagina</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por nombre"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #244BB3; border-color: #244BB3;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.companies.branches.profiles.index', array_merge([$company, $branch], array_filter(['view_id' => $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'icon' => $requestIcon]))) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', [$company, $branch], $operation);
                            $isCreate = str_contains($operation->action ?? '', 'profiles.create');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button size="md" variant="primary" type="button"
                                style="{{ $topStyle }}" @click="$dispatch('open-profile-modal')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button size="md" variant="primary"
                                style="{{ $topStyle }}"
                                href="{{ $topActionUrl }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon]))) }}"
                    >
                        <i class="ri-arrow-left-line"></i>
                        <span>Volver a sucursales</span>
                    </x-ui.link-button>
                </div>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #63B7EC;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Nombre</p>
                            </th>
                            <th style="background-color: #63B7EC;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Estado</p>
                            </th>
                            <th style="background-color: #63B7EC;" class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-5 py-4 sm:px-6 text-center sticky-left">
                                    <div class="space-y-1">
                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $profile->name }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <x-ui.badge variant="light" color="{{ $profile->status ? 'success' : 'error' }}">
                                        {{ $profile->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $actionUrl = $resolveActionUrl($action, [$company, $branch, $profile], $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isDelete)
                                                <form method="POST" action="{{ $actionUrl }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="¿Eliminar perfil?"
                                                    data-swal-text="Se eliminara {{ $profile->name }}. Esta accion no se puede deshacer."
                                                    data-swal-confirm="Sí, eliminar"
                                                    data-swal-cancel="Cancelar"
                                                    data-swal-confirm-color="#ef4444"
                                                    data-swal-cancel-color="#6b7280">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($viewId)
                                                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                    @endif
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="{{ $variant }}"
                                                        type="submit"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                    >
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.button>
                                                    <span
                                                        class="invisible group-hover:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-[11px] font-medium text-white shadow-xl z-50">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </form>
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                    >
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="invisible group-hover:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-[11px] font-medium text-white shadow-xl z-50">
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
                                <td colspan="3" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-user-settings-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay perfiles asignados.</p>
                                        <p class="text-gray-500">Todos los perfiles creados se asignan automaticamente.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->total() }}</span>
                </div>
                <div>
                    {{ $profiles->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection
