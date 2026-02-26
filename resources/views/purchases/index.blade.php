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
                            placeholder="Buscar por número, proveedor o usuario"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #244BB3; border-color: #244BB3;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.link-button size="md" variant="primary" style="background-color: #12f00e; color: #111827;" href="{{ route('admin.purchases.create', $viewId ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-add-line"></i>
                        <span>Nueva compra</span>
                    </x-ui.link-button>
                </div>
            </div>

            <div x-data="{ openRow: null }" class="overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">ID</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Moneda</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Fecha</p>
                            </th>
                            <th style="background-color: #63B7EC; color: #FFFFFF;" class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl">
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
                            @endphp
                            <tr class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]">
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $purchase->id }} ? openRow = null : openRow = {{ $purchase->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $purchase->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $purchase->id }}"></i>
                                        </button>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">#{{ $purchase->id }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">{{ $purchaseDoc }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">{{ $purchase->documentType?->name ?? '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm">S/ {{ number_format((float) ($purchase->purchaseMovement?->subtotal ?? 0), 2) }}</td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm">S/ {{ number_format((float) ($purchase->purchaseMovement?->tax ?? 0), 2) }}</td>
                                <td class="px-5 py-4 sm:px-6"><p class="font-bold text-brand-600 text-theme-sm">S/ {{ number_format((float) ($purchase->purchaseMovement?->total ?? 0), 2) }}</p></td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm truncate max-w-[150px]" title="{{ $purchase->person_name ?: '-' }}">{{ $purchase->person_name ?: '-' }}</td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm">{{ $purchase->purchaseMovement?->currency ?? 'PEN' }}</td>
                                <td class="px-5 py-4 sm:px-6 text-gray-800 text-theme-sm">{{ $purchase->moved_at ? $purchase->moved_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.purchases.edit', array_merge([$purchase], $viewId ? ['view_id' => $viewId] : [])) }}"
                                           class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[#FBBF24] text-gray-900">
                                            <i class="ri-pencil-line"></i>
                                        </a>
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
                                            <button type="submit" class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[#EF4444] text-white">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <tr x-show="openRow === {{ $purchase->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                <td colspan="9" class="px-6 py-4">
                                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                        <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Detalle de compra</p>
                                        <div class="overflow-x-auto">
                                            <table class="w-full min-w-[700px]">
                                                <thead>
                                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                                        <th class="px-2 py-2 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                                                        <th class="px-2 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                                                        <th class="px-2 py-2 text-left text-xs font-semibold uppercase text-gray-500">Unidad</th>
                                                        <th class="px-2 py-2 text-right text-xs font-semibold uppercase text-gray-500">Cantidad</th>
                                                        <th class="px-2 py-2 text-right text-xs font-semibold uppercase text-gray-500">Costo</th>
                                                        <th class="px-2 py-2 text-right text-xs font-semibold uppercase text-gray-500">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($purchase->purchaseMovement?->details ?? [] as $detail)
                                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                                            <td class="px-2 py-2 text-sm">{{ $detail->code }}</td>
                                                            <td class="px-2 py-2 text-sm">{{ $detail->description }}</td>
                                                            <td class="px-2 py-2 text-sm">{{ $detail->unit?->description ?? '-' }}</td>
                                                            <td class="px-2 py-2 text-sm text-right">{{ number_format((float) $detail->quantity, 2) }}</td>
                                                            <td class="px-2 py-2 text-sm text-right">{{ number_format((float) $detail->amount, 2) }}</td>
                                                            <td class="px-2 py-2 text-sm text-right">{{ number_format((float) $detail->quantity * (float) $detail->amount, 2) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="6" class="px-2 py-2 text-sm text-gray-500">Sin detalle.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
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
                        <tfoot>
                            <tr>
                                <td colspan="9" class="h-12"></td>
                            </tr>
                        </tfoot>
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
                <div>
                    {{ $purchases->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>
@endsection

