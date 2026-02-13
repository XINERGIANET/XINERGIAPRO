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
        @endphp

        <x-common.page-breadcrumb pageTitle="Pedidos" />

        <x-common.component-card title="Listado de pedidos" desc="Gestiona los pedidos registrados.">
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
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / pagina</option>
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
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.orders.list', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($topOperations->isNotEmpty())
                        @foreach ($topOperations as $operation)
                            @php
                                $topColor = $operation->color ?: '#22c55e';
                                $topTextColor = '#FFFFFF';
                                $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                            @endphp
                            <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}" class="flex-1 sm:flex-none shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 border-none">
                                <i class="{{ $operation->icon }}"></i>
                                <span class="font-medium">{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endforeach
                    @else
                        <x-ui.link-button size="md" variant="primary" style="background-color: #22c55e; color: #FFFFFF;" href="{{ route('admin.orders.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 border-none">
                            <i class="ri-restaurant-line"></i>
                            <span class="font-medium">Salones de pedidos</span>
                        </x-ui.link-button>
                    @endif
                </div>
            </div>

            <div x-data="{ openRow: null }" class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1480px]">
                    <thead class="bg-[#363d46]">
                        <tr class="text-white"  style="background-color: #63B7EC; color: #FFFFFF;">
                            <th class="w-12 px-4 py-3 text-center first:rounded-tl-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">#</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Comanda</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Moneda</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Total</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Fecha</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Usuario</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Persona</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Responsable</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase"># Personas</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">F. Fin</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Mesa</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Salón</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Situación</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Estado</p></th>
                            <th class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl"><p class="font-semibold text-white text-theme-xs uppercase">Operaciones</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $rowStatus = strtoupper((string) ($order->status ?? 'PENDIENTE'));
                                $situationStatus = strtoupper((string) ($order->movement?->status ?? 'A'));
                                $rowStatusColor = in_array($rowStatus, ['FINALIZADO', 'F'], true) ? 'success' : (in_array($rowStatus, ['CANCELADO', 'I'], true) ? 'error' : 'warning');
                                $rowStatusText = in_array($rowStatus, ['FINALIZADO', 'F'], true) ? 'Finalizado' : (in_array($rowStatus, ['CANCELADO', 'I'], true) ? 'Cancelado' : 'Pendiente');
                                $situationColor = in_array($situationStatus, ['A', '1'], true) ? 'success' : 'error';
                                $situationText = in_array($situationStatus, ['A', '1'], true) ? 'Activado' : 'Inactivo';
                            @endphp
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $order->id }} ? openRow = null : openRow = {{ $order->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $order->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $order->id }}"></i>
                                        </button>
                                        <span class="text-gray-700 text-theme-sm dark:text-gray-300">{{ $order->id }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6"><p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $order->movement?->number ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->currency ?? 'PEN' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90">{{ number_format((float) ($order->total ?? 0), 2) }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->movement?->moved_at?->format('Y-m-d h:i:s A') ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->movement?->user_name ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->movement?->person_name ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->movement?->responsible_name ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ (int) ($order->people_count ?? 0) }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->finished_at ? \Illuminate\Support\Carbon::parse($order->finished_at)->format('Y-m-d h:i:s A') : '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->table?->name ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order->area?->name ?? '-' }}</p></td>
                                <td class="px-5 py-4 sm:px-6"><x-ui.badge variant="light" color="{{ $situationColor }}">{{ $situationText }}</x-ui.badge></td>
                                <td class="px-5 py-4 sm:px-6"><x-ui.badge variant="light" color="{{ $rowStatusColor }}">{{ $rowStatusText }}</x-ui.badge></td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isCharge = str_contains($action, 'charge');
                                                    if ($isCharge && !in_array($rowStatus, ['P', 'PENDIENTE'], true)) {
                                                        continue;
                                                    }
                                                    $actionUrl = $resolveActionUrl($action, $order, $operation);
                                                    if ($isCharge && $actionUrl !== '#') {
                                                        $separator = str_contains($actionUrl, '?') ? '&' : '?';
                                                        $actionUrl .= $separator . 'movement_id=' . urlencode($order->movement_id);
                                                    }
                                                    if ($actionUrl === '#') {
                                                        continue;
                                                    }
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonTextColor = str_contains($action, 'edit') ? '#111827' : '#FFFFFF';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$buttonTextColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}" class="relative group js-swal-delete" data-swal-title="Eliminar pedido?" data-swal-text="Se eliminara el pedido {{ $order->movement?->number }}. Esta accion no se puede deshacer." data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar" data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
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
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $order->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                <td colspan="15" class="px-6 py-4">
                                    <div class="mx-auto w-full max-w-xl space-y-1 text-center text-gray-800 dark:text-gray-200">
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">T. cambio</span><span>{{ number_format((float) ($order->exchange_rate ?? 1), 3) }}</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Comentario</span><span>{{ $order->movement?->comment ?: '-' }}</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Repartidor</span><span>{{ $order->movement?->responsible_name ?: '-' }}</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Monto de envío</span><span>{{ number_format((float) ($order->delivery_amount ?? 0), 2) }}</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Origen</span><span>Pedido</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Celular</span><span>{{ $order->contact_phone ?: '-' }}</span></div>
                                        <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700"><span class="font-semibold">Dirección</span><span>{{ $order->delivery_address ?: '-' }}</span></div>
                                        <div class="grid grid-cols-2 py-2"><span class="font-semibold">Hora de entrega</span><span>{{ $order->delivery_time ? \Illuminate\Support\Carbon::parse($order->delivery_time)->format('Y-m-d h:i:s A') : '-' }}</span></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-restaurant-2-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay pedidos registrados.</p>
                                        <p class="text-gray-500">Crea el primer pedido desde Salones de pedidos.</p>
                                        <x-ui.link-button size="sm" variant="primary" href="{{ route('admin.orders.index', $viewId ? ['view_id' => $viewId] : []) }}">
                                            <i class="ri-add-line"></i>
                                            <span>Ir a salones</span>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->total() }}</span>
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection
