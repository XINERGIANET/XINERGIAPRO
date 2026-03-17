@extends('layouts.app')

@section('content')
    <div>
        @php
            $viewId = request('view_id');
        @endphp

        <x-common.page-breadcrumb pageTitle="Compras" />

        <x-common.component-card title="Listado de compras" desc="Gestiona las compras registradas.">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @php
                $currentPageItems = $purchases->getCollection();
                $pageTotal = (float) $currentPageItems->sum(fn ($purchase) => (float) ($purchase->purchaseMovement?->total ?? 0));
                $pageCreditPending = (float) $currentPageItems->sum(function ($purchase) {
                    $paymentType = strtoupper((string) ($purchase->purchaseMovement?->payment_type ?? 'CONTADO'));
                    return $paymentType === 'CREDITO'
                        ? (float) ($purchase->purchaseMovement?->total ?? 0)
                        : 0;
                });
            @endphp

            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <form method="GET" class="flex flex-1 flex-wrap gap-3 items-center">
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
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por número, proveedor o usuario"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="w-full sm:w-44 xl:w-40 flex-none">
                        <x-form.date-picker
                            id="purchases-date-from"
                            name="date_from"
                            placeholder="dd/mm/aaaa"
                            :defaultDate="$dateFrom"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                        />
                    </div>
                    <div class="w-full sm:w-44 xl:w-40 flex-none">
                        <x-form.date-picker
                            id="purchases-date-to"
                            name="date_to"
                            placeholder="dd/mm/aaaa"
                            :defaultDate="$dateTo"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                        />
                    </div>
                    <div class="w-full sm:w-48 xl:w-44 flex-none">
                        <x-form.select-autocomplete
                            name="payment_type"
                            :value="$paymentType ?? ''"
                            :options="[['value' => '', 'label' => 'Tipo pago: Todos'], ['value' => 'CONTADO', 'label' => 'Contado'], ['value' => 'CREDITO', 'label' => 'Credito']]"
                            placeholder="Tipo pago: Todos"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-start gap-2">
                    <x-ui.link-button size="md" variant="primary" style="background-color: #12f00e; color: #111827;" href="{{ route('admin.purchases.create', $viewId ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-add-line"></i>
                        <span>Nueva compra</span>
                    </x-ui.link-button>
                </div>
            </div>

            <div x-data="{ openRow: null }" class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">ID</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>

                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Fecha</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchases as $purchase)
                            @php
                                $purchaseDoc = strtoupper(substr($purchase->documentType?->name ?? 'C', 0, 1))
                                    . ($purchase->purchaseMovement?->series ?? '001')
                                    . '-'
                                    . $purchase->number;
                                $paymentType = strtoupper((string) ($purchase->purchaseMovement?->payment_type ?? 'CONTADO'));
                                $pendingAmount = $paymentType === 'CREDITO'
                                    ? (float) ($purchase->purchaseMovement?->total ?? 0)
                                    : 0;
                            @endphp
                            <tr class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $purchase->id }} ? openRow = null : openRow = {{ $purchase->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $purchase->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $purchase->id }}"></i>
                                        </button>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">#{{ $purchase->id }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">{{ $purchaseDoc }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">{{ $purchase->documentType?->name ?? '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm text-center">S/ {{ number_format((float) ($purchase->purchaseMovement?->subtotal ?? 0), 2) }}</td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm text-center">S/ {{ number_format((float) ($purchase->purchaseMovement?->tax ?? 0), 2) }}</td>
                                <td class="px-5 py-4 sm:px-6 text-center"><p class="font-bold text-brand-600 text-theme-sm">S/ {{ number_format((float) ($purchase->purchaseMovement?->total ?? 0), 2) }}</p></td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm text-center truncate max-w-[150px]" title="{{ $purchase->person_name ?: '-' }}">{{ $purchase->person_name ?: '-' }}</td>

                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm text-center">{{ $purchase->moved_at ? $purchase->moved_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="relative group">
                                            <a href="{{ route('admin.purchases.edit', array_merge([$purchase], $viewId ? ['view_id' => $viewId] : [])) }}"
                                               class="inline-flex h-12 w-12 items-center justify-center rounded-2xl shadow-sm transition hover:brightness-95"
                                               style="background-color:#fbbf24; color:#111827; border:1px solid #fbbf24;">
                                                <i class="ri-pencil-line text-base" style="color:#111827 !important;"></i>
                                            </a>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Editar
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>
                                        <form method="POST" action="{{ route('admin.purchases.destroy', array_merge([$purchase], $viewId ? ['view_id' => $viewId] : [])) }}"
                                              class="relative group js-swal-delete"
                                              data-swal-title="Eliminar compra?"
                                              data-swal-text="Se eliminará la compra {{ $purchase->number }} y se revertirá stock."
                                              data-swal-confirm="Sí, eliminar"
                                              data-swal-cancel="Cancelar"
                                              data-swal-confirm-color="#ef4444"
                                              data-swal-cancel-color="#6b7280">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex h-12 w-12 items-center justify-center rounded-2xl shadow-sm transition hover:brightness-95"
                                                style="background-color:#ef4444; color:#ffffff; border:1px solid #ef4444;">
                                                <i class="ri-delete-bin-line text-base" style="color:#ffffff !important;"></i>
                                            </button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Eliminar
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <tr x-show="openRow === {{ $purchase->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">

                                <td colspan="10" class="px-6 py-4">
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 mb-4">

                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Persona</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $purchase->person_name ?: '-' }}">{{ $purchase->person_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $purchase->moved_at ? $purchase->moved_at->format('d/m/Y H:i') : '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usuario</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $purchase->user_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $purchase->responsible_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $purchase->purchaseMovement?->currency ?? 'PEN' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">T. cambio</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($purchase->purchaseMovement?->exchange_rate ?? 1), 3) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Condición</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ strtoupper((string) ($purchase->purchaseMovement?->payment_type ?? 'CONTADO')) === 'CREDITO' ? 'CREDITO' : 'CONTADO' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Afecta Caja</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($purchase->purchaseMovement?->affects_cash ?? 'N') === 'S' ? 'Sí' : 'No' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Afecta Kardex</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($purchase->purchaseMovement?->affects_kardex ?? 'N') === 'S' ? 'Sí' : 'No' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 col-span-2 lg:col-span-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Comentario</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $purchase->comment ?? '-' }}">{{ $purchase->comment ?? '-' }}</p>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-0 shadow-sm dark:border-gray-800 dark:bg-white/5 overflow-hidden">
                                        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                                            <p class="text-sm font-bold text-gray-700 dark:text-gray-200">Detalle de compra</p>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full">
                                                <thead>
                                                    <tr style="background-color: #334155;">
                                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Código</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Descripción</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-white">Unidad</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-white">Cantidad</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-white">Costo</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-white">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($purchase->purchaseMovement?->details ?? [] as $detail)
                                                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800 hover:bg-gray-50/50 transition-colors">
                                                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200 font-medium">{{ $detail->code }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $detail->description }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $detail->unit?->description ?? '-' }}</td>
                                                            <td class="px-4 py-3 text-sm text-right text-gray-800 dark:text-gray-200 font-medium">{{ number_format((float) $detail->quantity, 2) }}</td>
                                                            <td class="px-4 py-3 text-sm text-right text-gray-800 dark:text-gray-200">S/ {{ number_format((float) $detail->amount, 2) }}</td>
                                                            <td class="px-4 py-3 text-sm text-right text-brand-600 dark:text-brand-400 font-bold">S/ {{ number_format((float) $detail->quantity * (float) $detail->amount, 2) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Sin detalle.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-shopping-bag-3-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay compras registradas.</p>
                                        <p class="text-gray-500">Crea la primera compra para comenzar.</p>
                                        <x-ui.link-button size="sm" variant="primary" href="{{ route('admin.purchases.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar compra</span>
                                        </x-ui.link-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($purchases->count() > 0)
@endif
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->total() }}</span>
                </div>
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    Total filtrado:
                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">S/ {{ number_format((float) ($purchasesTotalAmount ?? 0), 2) }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $purchases->links('vendor.pagination.forced') }}
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection
