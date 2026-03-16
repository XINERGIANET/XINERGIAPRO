@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $warehouseMovement = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    // Normalizar guiones a guiones bajos para coincidir con nombres de rutas Laravel
                    $normalizedAction = str_replace('-', '_', $action);
                    
                    $routeCandidates = [$action, $normalizedAction];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                        $routeCandidates[] = 'admin.' . $normalizedAction;
                    }
                    // Agregar variantes con .index solo si no tiene ya un método específico
                    if (!str_contains($action, '.') || str_ends_with($action, '.index')) {
                        $routeCandidates = array_merge(
                            $routeCandidates,
                            array_map(fn ($name) => $name . '.index', array_filter($routeCandidates, fn($n) => !str_contains($n, '.')))
                        );
                    }

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $warehouseMovement ? route($routeName, $warehouseMovement) : route($routeName);
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
                if ($action === 'warehouse-movements.create') {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="{{ $title ?? 'Movimientos de Almacén' }}" />

        <x-common.component-card title="Listado de movimientos de almacén" desc="Gestiona los movimientos de almacén registrados en el sistema.">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between mb-6">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end min-w-0">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-36 flex-none">
                        <x-form.select-autocomplete
                            name="per_page"
                            :value="$perPage"
                            :options="collect([10, 20, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                            placeholder="Por página"
                            label="Por página"
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="flex-none min-w-[160px]">
                        <x-form.date-picker
                            id="wm-date-from"
                            name="date_from"
                            label="Fecha inicio"
                            :defaultDate="$dateFrom ?? null"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div class="flex-none min-w-[160px]">
                        <x-form.date-picker
                            id="wm-date-to"
                            name="date_to"
                            label="Fecha fin"
                            :defaultDate="$dateTo ?? null"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Buscar</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="ri-search-line"></i>
                            </span>
                            <input type="text" name="search" value="{{ $search }}"
                                placeholder="Número, persona, usuario..."
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                    </div>
                    <div class="flex items-end gap-2 flex-none shrink-0">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 min-h-[2.75rem] px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('warehouse_movements.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 min-h-[2.75rem] px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

            </div>
            <div class="flex items-center gap-3 border-t border-gray-100 pt-4 lg:border-0 lg:pt-0 flex-none ml-auto">
                @foreach ($topOperations as $operation)
                    @php
                        $topTextColor = $resolveTextColor($operation);
                        $topColor = $operation->color ?: '#3B82F6';
                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                    @endphp
                    <x-ui.link-button size="md" variant="primary"
                        class="w-full sm:w-auto h-11 px-6 shadow-sm"
                        style="{{ $topStyle }}"
                        href="{{ $topActionUrl }}">
                        <i class="{{ $operation->icon }} text-lg"></i>
                        <span>{{ $operation->name }}</span>
                    </x-ui.link-button>
                @endforeach
                @if($topOperations->isEmpty())
                    <x-ui.link-button size="md" variant="primary" 
                        href="{{ route('warehouse_movements.input', $viewId ? ['view_id' => $viewId] : []) }}"
                        class="w-full sm:w-auto h-11 px-6 shadow-sm" 
                        style="background-color: #00A389; color: #FFFFFF;">
                        <i class="ri-archive-line text-lg"></i>
                        <span>Entrada</span>
                    </x-ui.link-button>
                    <x-ui.link-button size="md" variant="primary" 
                        href="{{ route('warehouse_movements.output', $viewId ? ['view_id' => $viewId] : []) }}"
                        class="w-full sm:w-auto h-11 px-6 shadow-sm" 
                        style="background-color: #EF4444; color: #FFFFFF;">
                        <i class="ri-archive-line text-lg"></i>
                        <span>Salida</span>
                    </x-ui.link-button>
                @endif
            </div>
            <div x-data="{ openRow: null }" class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <table class="w-full">
                        <thead style="background-color: #334155; color: #FFFFFF;">
                            <tr>
                                <th style="background-color: #334155; color: #FFFFFF;" class="w-10 px-3 py-3 text-center first:rounded-tl-xl"></th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-center">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Número</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Tipo</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Persona</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Motivo / Destino</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Comentario</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Estado</p>
                                </th>
                                <th class="px-3 py-3 text-center align-middle">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Fecha</p>
                                </th>
                                <th class="px-3 py-3 text-center last:rounded-tr-xl">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Acciones</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($warehouseMovements as $warehouseMovement)
                                @php
                                    $movement = $warehouseMovement->movement;
                                    $statusColors = [
                                        'PENDING' => 'warning',
                                        'SENT' => 'info',
                                        'FINALIZED' => 'success',
                                        'REJECTED' => 'error',
                                    ];
                                    $statusColor = $statusColors[$warehouseMovement->status] ?? 'info';
                                @endphp
                                <tr class="group/row transition hover:bg-gray-50/80 dark:hover:bg-white/5 relative hover:z-[60]">
                                    <td class="px-3 py-3 align-middle text-center">
                                        <button type="button"
                                            @click="openRow === {{ $warehouseMovement->id }} ? openRow = null : openRow = {{ $warehouseMovement->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $warehouseMovement->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $warehouseMovement->id }}"></i>
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/10 shrink-0">
                                                <i class="ri-archive-line text-xs"></i>
                                            </div>
                                            <p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90 truncate" title="{{ $movement->number ?? '-' }}">
                                                {{ $movement->number ?? '-' }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle">
                                        @php
                                            $stockType = $movement->documentType->stock ?? 'none';
                                            $typeLabel = $stockType === 'add' ? 'Entrada' : ($stockType === 'subtract' ? 'Salida' : 'Otros');
                                            $typeColor = $stockType === 'add' ? 'success' : ($stockType === 'subtract' ? 'error' : 'info');
                                        @endphp
                                        <x-ui.badge variant="light" color="{{ $typeColor }}">
                                            {{ $typeLabel }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        <p class="text-gray-600 text-theme-sm dark:text-gray-400 truncate max-w-[150px] mx-auto" title="{{ $movement->person_name ?? $movement->user_name ?? '-' }}">
                                            {{ $movement->person_name ?? $movement->user_name ?? '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        @php
                                            $motivoDestino = '-';
                                            $displayComment = $movement->comment ?? '-';

                                            if ($stockType === 'add') {
                                                if (!empty($movement->reason)) {
                                                    $motivoDestino = $movement->reason;
                                                } else {
                                                    // Manejo para datos históricos donde reason era null
                                                    $defaultReasons = [
                                                        'AJUSTE DE ENTRADA',
                                                        'DEVOLUCION INTERNA',
                                                        'REGULARIZACION DE STOCK',
                                                        'TRASLADO INTERNO',
                                                        'OTRO'
                                                    ];
                                                    $upperComment = strtoupper(trim((string) $movement->comment));
                                                    
                                                    if (in_array($upperComment, $defaultReasons)) {
                                                        $motivoDestino = $movement->comment;
                                                        $displayComment = '-'; // Si era de los predefinidos, no es un comentario real
                                                    } else {
                                                        // Si ingresaron un comentario personalizado en el pasado, el motivo se perdía
                                                        $motivoDestino = 'AJUSTE DE ENTRADA';
                                                    }
                                                }
                                            } elseif ($stockType === 'subtract') {
                                                // Sucursal destino si es transferencia
                                                $childMovement = \App\Models\Movement::with('branch')->where('parent_movement_id', $movement->id)->first();
                                                if ($childMovement && $childMovement->branch) {
                                                    $motivoDestino = $childMovement->branch->legal_name;
                                                } else {
                                                    $motivoDestino = 'Salida General';
                                                }
                                            }
                                        @endphp
                                        <p class="text-gray-600 text-theme-sm dark:text-gray-400 truncate max-w-[150px] mx-auto font-medium" title="{{ $motivoDestino }}">
                                            {{ $motivoDestino }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        <p class="text-gray-600 text-theme-sm dark:text-gray-400 truncate max-w-[150px] mx-auto" title="{{ $displayComment }}">
                                            {{ $displayComment }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle">
                                        <x-ui.badge variant="light" color="{{ $statusColor }}">
                                            {{ $warehouseMovement->status ?? 'FINALIZED' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle whitespace-nowrap">
                                        <div class="flex flex-col items-center">
                                            <p class="text-gray-800 text-[11px] font-medium dark:text-white/90">{{ $movement->moved_at ? $movement->moved_at->format('j/m/Y') : '-' }}</p>
                                            <p class="text-gray-500 text-[10px] dark:text-gray-400">{{ $movement->moved_at ? $movement->moved_at->format('h:i A') : '' }}</p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-middle">
                                        <div class="flex items-center justify-center gap-2">
                                            @if ($rowOperations->isNotEmpty())
                                                @foreach ($rowOperations as $operation)
                                                    @php
                                                        $action = $operation->action ?? '';
                                                        $isDelete = str_contains($action, 'destroy');
                                                        $actionUrl = $resolveActionUrl($action, $warehouseMovement, $operation);
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
                                                            data-swal-title="Eliminar movimiento?"
                                                            data-swal-text="Se eliminara el movimiento {{ $movement->number ?? '-' }}. Esta accion no se puede deshacer."
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
                                                                variant="{{ $variant }}"
                                                                type="submit"
                                                                className="rounded-xl"
                                                                style="{{ $buttonStyle }}"
                                                                aria-label="{{ $operation->name }}"
                                                            >
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.button>
                                                            <span class="pointer-events-none absolute bottom-full right-0 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                                {{ $operation->name }}
                                                                <span class="absolute top-full right-3 border-4 border-transparent border-t-gray-900"></span>
                                                            </span>
                                                        </form>
                                                    @else
                                                        <div class="relative group">
                                                            <x-ui.link-button
                                                                size="icon"
                                                                variant="{{ $variant }}"
                                                                href="{{ $actionUrl }}"
                                                                className="rounded-xl"
                                                                style="{{ $buttonStyle }}"
                                                                aria-label="{{ $operation->name }}"
                                                            >
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.link-button>
                                                            <span class="pointer-events-none absolute bottom-full right-0 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                                {{ $operation->name }}
                                                                <span class="absolute top-full right-3 border-4 border-transparent border-t-gray-900"></span>
                                                            </span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="primary"
                                                        href="{{ route('warehouse_movements.show', array_merge(['warehouseMovement' => $warehouseMovement->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="rounded-xl w-8 h-8"
                                                        style="background-color: #334155; color: #FFFFFF;"
                                                        aria-label="Ver Registro"
                                                    >
                                                        <i class="ri-eye-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full right-0 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        Ver Registro
                                                        <span class="absolute top-full right-3 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>

                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="edit"
                                                        href="{{ route('warehouse_movements.edit', array_merge(['warehouseMovement' => $warehouseMovement->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="rounded-xl w-8 h-8"
                                                        style="background-color: #FBBF24; color: #111827;"
                                                        aria-label="Editar Registro"
                                                    >
                                                        <i class="ri-pencil-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full right-0 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                        Editar Registro
                                                        <span class="absolute top-full right-3 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="openRow === {{ $warehouseMovement->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40">
                                    <td colspan="9" class="px-6 py-4">
                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 w-full">
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usuario</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $movement->user_name ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $movement->responsible_name ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo mov.</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $movement->movementType?->description ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Doc.</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $movement->documentType?->name ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sucursal</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $warehouseMovement->branch?->legal_name ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado mov.</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $movement->status ?? '-' }}</p>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-xl border border-gray-200 bg-white p-0 shadow-sm dark:border-gray-800 dark:bg-white/5 overflow-hidden">
                                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">Detalle del movimiento</p>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="w-full">
                                                    <thead>
                                                        <tr style="background-color: #334155;">
                                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Código</th>
                                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Descripción</th>
                                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Unidad</th>
                                                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-white">Cantidad</th>
                                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Comentario</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($warehouseMovement->details ?? [] as $detail)
                                                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800 hover:bg-gray-50/50 transition-colors">
                                                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200 font-medium">{{ $detail->product_snapshot['code'] ?? $detail->product?->code ?? '-' }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $detail->product_snapshot['description'] ?? $detail->product?->description ?? '-' }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $detail->unit?->description ?? '-' }}</td>
                                                                <td class="px-4 py-3 text-sm text-right text-gray-800 dark:text-gray-200 font-medium">{{ number_format((float) $detail->quantity, 2) }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $detail->comment ?? '' }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Sin detalle.</td></tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-16">
                                        <div class="flex flex-col items-center gap-4 text-center">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-gray-800/50 dark:text-gray-600">
                                                <i class="ri-archive-line text-3xl"></i>
                                            </div>
                                            <div class="space-y-1">
                                                <p class="text-base font-semibold text-gray-800 dark:text-white/90">No hay movimientos de almacén registrados</p>
                                                <p class="text-sm text-gray-500">Comienza registrando tu primer movimiento de almacén.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($warehouseMovements->count() > 0)
@endif
                    </table>
            </div>
        </x-common.component-card>

        <div class="mt-5 mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $warehouseMovements->links('vendor.pagination.forced') }}
            </div>
        </div>
    </div>
@endsection


