@extends('layouts.app')

@section('content')
    <div>
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
        @endphp

        <x-common.page-breadcrumb pageTitle="Ventas" />

        <x-common.component-card title="Listado de ventas" desc="Gestiona las ventas registradas.">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-full sm:w-24">
                        <select
                            name="per_page"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            onchange="this.form.submit()"
                        >
                            @foreach ([10, 20, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / página</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="relative flex-1">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por numero, persona o usuario"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #244BB3; border-color: #244BB3;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($topOperations->isNotEmpty())
                        @foreach ($topOperations as $operation)
                            @php
                                $topColor = $operation->color ?: '#3B82F6';
                                $topTextColor = str_contains($operation->action ?? '', 'sales.create') ? '#111827' : '#FFFFFF';
                                $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                            @endphp
                            <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endforeach
                    @else
                        <x-ui.link-button size="md" variant="primary" style="background-color: #12f00e; color: #111827;" href="{{ route('admin.sales.create', $viewId ? ['view_id' => $viewId] : []) }}">
                            <i class="ri-add-line"></i>
                            <span>Nueva venta</span>
                        </x-ui.link-button>
                    @endif
                </div>
            </div>

            <div x-data="{ openRow: null }" class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-white text-theme-xs uppercase">ID</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class= "px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th  style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th  style="background-color: #63B7EC; color: #FFFFFF;"  class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th  style="background-color: #63B7EC; color: #FFFFFF;"  class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>
                            <th  style="background-color: #63B7EC; color: #FFFFFF;"  class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Situación</p>
                            </th>
                            <th  style="background-color: #63B7EC; color: #FFFFFF;"  class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-4 py-4 sm:px-6 sticky-left">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $sale->id }} ? openRow = null : openRow = {{ $sale->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $sale->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $sale->id }}"></i>
                                        </button>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">#{{ $sale->id }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ strtoupper(substr($sale->documentType->name, 0, 1)) }}{{ $sale->salesMovement->series }}-{{ $sale->number }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">
                                            {{ $sale->documentType?->name ?? '-' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-bold text-brand-600 text-theme-sm dark:text-brand-400">S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90 truncate max-w-[150px]" title="{{ $sale->person_name ?? 'Público General' }}">
                                        {{ $sale->person_name ?? 'Público General' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    @php
                                        $status = $sale->status ?? 'A';
                                        $badgeColor = 'success';
                                        $badgeText = 'Activo';
                                        if ($status === 'P') {
                                            $badgeColor = 'warning';
                                            $badgeText = 'Pendiente';
                                        } elseif ($status !== 'A') {
                                            $badgeColor = 'error';
                                            $badgeText = 'Inactivo';
                                        }
                                    @endphp
                                    <x-ui.badge variant="light" color="{{ $badgeColor }}">
                                        {{ $badgeText }}
                                        </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isCharge = str_contains($action, 'charge');
                                                    if ($isCharge && ($sale->status ?? 'A') !== 'P') {
                                                        continue;
                                                    }

                                                    $actionUrl = $resolveActionUrl($action, $sale, $operation);
                                                    if ($isCharge && $actionUrl !== '#') {
                                                        $separator = str_contains($actionUrl, '?') ? '&' : '?';
                                                        $actionUrl .= $separator . 'movement_id=' . urlencode($sale->id);
                                                    }

                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonTextColor = str_contains($action, 'edit') ? '#111827' : '#FFFFFF';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$buttonTextColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp

                                                @if ($isDelete)
                                                    <form
                                                        method="POST"
                                                        action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar venta?"
                                                        data-swal-text="Se eliminara la venta {{ $sale->number }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar"
                                                        data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}" href="{{ $actionUrl }}" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach

                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="outline"
                                                    href="{{ route('admin.sales.print.pdf', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                                    aria-label="Imprimir PDF" target="_blank"
                                                >
                                                    <i class="ri-file-pdf-2-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">PDF</span>
                                            </div>
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="outline"
                                                    href="{{ route('admin.sales.print.ticket', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100"
                                                    aria-label="Imprimir Ticket" target="_blank"
                                                >
                                                    <i class="ri-printer-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Ticket</span>
                                            </div>
                                        @else
                                            @if(($sale->status ?? 'A') === 'P')
                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="primary"
                                                        href="{{ route('admin.sales.charge', array_merge(['movement_id' => $sale->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="bg-success-500 text-white hover:bg-success-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #10B981; color: #FFFFFF;"
                                                        aria-label="Cobrar"
                                                    >
                                                        <i class="ri-money-dollar-circle-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Cobrar</span>
                                                </div>
                                            @endif
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="edit"
                                                    href="{{ route('admin.sales.edit', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar"
                                                >
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Editar</span>
                                            </div>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sales.destroy', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="relative group js-swal-delete"
                                                data-swal-title="Eliminar venta?"
                                                data-swal-text="Se eliminara la venta {{ $sale->number }}. Esta accion no se puede deshacer."
                                                data-swal-confirm="Si, eliminar"
                                                data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444"
                                                data-swal-cancel-color="#6b7280"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <x-ui.button
                                                    size="icon"
                                                    variant="eliminate"
                                                    type="submit"
                                                    className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #EF4444; color: #FFFFFF;"
                                                    aria-label="Eliminar"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Eliminar</span>
                                            </form>

                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="outline"
                                                    href="{{ route('admin.sales.print.pdf', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                                    aria-label="Imprimir PDF"
                                                >
                                                    <i class="ri-file-pdf-2-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">PDF</span>
                                            </div>
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="outline"
                                                    href="{{ route('admin.sales.print.ticket', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100"
                                                    aria-label="Imprimir Ticket"
                                                >
                                                    <i class="ri-printer-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Ticket</span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $sale->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                <td colspan="10" class="px-6 py-4">
                                    <div class="grid grid-cols-4 gap-3 sm:grid-cols-5">
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Persona</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->person_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->moved_at ? $sale->moved_at->format('d/m/Y H:i') : '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usuario</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->user_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->responsible_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de detalle</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->detail_type ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->currency ?? 'PEN' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">T. cambio</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($sale->salesMovement?->exchange_rate ?? 1), 3) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Por consumo</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($sale->salesMovement?->consumption ?? 'N') === 'Y' ? 'Sí' : 'No' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de pago</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->payment_type ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 sm:col-span-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Comentario</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ Str::limit($sale->comment ?? '-', 60) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado SUNAT</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->status ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Origen</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->movementType?->description ?? 'Venta' }} - {{ strtoupper(substr($sale->documentType?->name , 0, 1))?? '-' }}{{ $sale->salesMovement?->series }}-{{ $sale->number }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-shopping-bag-3-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay ventas registradas.</p>
                                        <p class="text-gray-500">Crea la primera venta para comenzar.</p>
                                        <x-ui.link-button size="sm" variant="primary" href="{{ route('admin.sales.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar venta</span>
                                        </x-ui.link-button>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->total() }}</span>
                </div>
                <div>
                    {{ $sales->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>

    @push('scripts')
    <script>
    (function() {
        function showFlashToast() {
            const msg = sessionStorage.getItem('flash_success_message');
            if (!msg) return;
            sessionStorage.removeItem('flash_success_message');
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: msg,
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            }
        }
        showFlashToast();
        document.addEventListener('turbo:load', showFlashToast);
    })();
    </script>
    @endpush
@endsection
