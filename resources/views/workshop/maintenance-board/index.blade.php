@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Tablero de Mantenimiento" />

    <x-common.component-card title="Tablero Circular de Servicios" desc="Inicia y finaliza mantenimientos con visual de moto y cliente en tiempo real.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.maintenance-board.create') }}" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                    <i class="ri-add-circle-line"></i><span>Agregar Vehiculo e Iniciar</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}">
                    <i class="ri-file-list-3-line"></i><span>Ir a Ordenes de Servicio</span>
                </x-ui.link-button>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    class="h-10 min-w-[260px] rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none"
                    placeholder="Buscar por cliente o vehiculo"
                >
                <select
                    id="status"
                    name="status"
                    class="h-10 min-w-[220px] rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none"
                >
                    <option value="all" @selected(($selectedStatus ?? 'in_progress') === 'all')>Todos</option>
                    <option value="draft" @selected(($selectedStatus ?? 'in_progress') === 'draft')>Borrador</option>
                    <option value="diagnosis" @selected(($selectedStatus ?? 'in_progress') === 'diagnosis')>Diagnóstico</option>
                    <option value="awaiting_approval" @selected(($selectedStatus ?? 'in_progress') === 'awaiting_approval')>Esperando aprobación</option>
                    <option value="approved" @selected(($selectedStatus ?? 'in_progress') === 'approved')>Aprobado</option>
                    <option value="in_progress" @selected(($selectedStatus ?? 'in_progress') === 'in_progress')>En reparación</option>
                    <option value="finished" @selected(($selectedStatus ?? 'in_progress') === 'finished')>Terminado</option>
                    <option value="delivered" @selected(($selectedStatus ?? 'in_progress') === 'delivered')>Entregado</option>
                    <option value="cancelled" @selected(($selectedStatus ?? 'in_progress') === 'cancelled')>Anulado</option>
                </select>
                <x-ui.button size="sm" variant="primary" type="submit" className="h-10">
                    <i class="ri-search-line"></i><span>Filtrar</span>
                </x-ui.button>
                <x-ui.link-button size="sm" variant="outline" href="{{ route('workshop.maintenance-board.index', ['status' => 'in_progress']) }}" className="h-10">
                    <i class="ri-refresh-line"></i><span>Limpiar</span>
                </x-ui.link-button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($cards as $card)
                @php
                    $status = (string) $card->status;
                    $pendingDebtCard = max(0, (float) $card->total - (float) $card->paid_total);
                    $pendingBillingCountCard = (int) ($card->pending_billing_count ?? 0);
                    $canQuoteCard = ((string) $card->status === 'awaiting_approval');
                    $canCheckoutCard = ((string) $card->status === 'finished' && $pendingDebtCard > 0);
                    $quotationPayload = [
                        'action' => route('workshop.maintenance-board.quotation', $card),
                        'order_label' => 'OS ' . ($card->movement?->number ?? ('#' . $card->id)) . ' - ' . trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')),
                        'status' => (string) $card->status,
                        'quote_lines' => $card->details
                            ->where('line_type', 'SERVICE')
                            ->whereNull('sales_movement_id')
                            ->map(fn ($detail) => [
                                'detail_id' => (int) $detail->id,
                                'description' => (string) ($detail->description ?: ($detail->service?->name ?? 'Servicio')),
                                'qty' => (float) $detail->qty,
                                'unit_price' => (float) $detail->unit_price,
                                'subtotal' => (float) $detail->total,
                            ])
                            ->values()
                            ->all(),
                    ];
                    $statusMap = [
                        'draft' => ['Borrador', 'bg-slate-100 text-slate-700 border-slate-200'],
                        'diagnosis' => ['Diagnóstico', 'bg-indigo-100 text-indigo-700 border-indigo-200'],
                        'awaiting_approval' => ['Esperando aprobación', 'bg-amber-100 text-amber-700 border-amber-200'],
                        'approved' => ['Aprobado', 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                        'in_progress' => ['En reparación', 'bg-orange-100 text-orange-700 border-orange-200'],
                        'finished' => ['Terminado', 'bg-cyan-100 text-cyan-700 border-cyan-200'],
                        'delivered' => ['Entregado', 'bg-green-100 text-green-700 border-green-200'],
                        'cancelled' => ['Anulado', 'bg-rose-100 text-rose-700 border-rose-200'],
                    ];
                    [$statusLabel, $statusClass] = $statusMap[$status] ?? [strtoupper($status), 'bg-gray-100 text-gray-700 border-gray-200'];
                @endphp
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <div class="absolute -right-14 -top-14 h-40 w-40 rounded-full bg-gradient-to-br from-amber-300/20 to-indigo-300/20 blur-xl"></div>
                    <div class="absolute -left-8 bottom-0 h-24 w-24 rounded-full bg-gradient-to-tr from-orange-300/20 to-transparent blur-lg"></div>

                    <div class="relative z-10 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Orden de servicio</p>
                            <p class="text-sm font-bold text-slate-800">OS {{ $card->movement?->number ?? ('#' . $card->id) }}</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="relative z-10 mt-3 rounded-2xl border border-slate-200 px-3 py-2.5 text-white"
                         style="background: linear-gradient(120deg, #0f172a 0%, #1e293b 52%, #334155 100%);">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10">
                                <svg viewBox="0 0 24 24" class="h-6 w-6 text-orange-300" fill="currentColor" aria-hidden="true">
                                    <path d="M5.5 16.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm13 0a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM14 6l-1 3h3.3a1.5 1.5 0 0 1 1.33.81l1.72 3.44a3.5 3.5 0 0 1 2.12 3.25h-1.99a2.5 2.5 0 0 0-5 0H8a2.5 2.5 0 0 0-5 0H1a3.5 3.5 0 0 1 3.5-3.5h2.1l1.37-4.12A2 2 0 0 1 9.87 7.5H12l.6-1.8A1 1 0 0 1 13.55 5h2.95v1h-2.5Z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-lg font-bold leading-tight">{{ trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')) ?: 'Vehiculo en mantenimiento' }}</p>
                                <p class="truncate text-xs tracking-wide text-slate-200">Placa {{ $card->vehicle?->plate ?: 'S/PLACA' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 mt-3 grid grid-cols-1 gap-2 text-sm text-slate-700 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cliente</p>
                            <p class="font-semibold text-slate-800">{{ trim(($card->client?->first_name ?? '') . ' ' . ($card->client?->last_name ?? '')) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ingreso</p>
                            <p class="font-semibold text-slate-800">{{ optional($card->intake_date)->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-2.5 grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Total</p>
                            <p class="text-sm font-bold text-slate-800">S/ {{ number_format((float) $card->total, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-emerald-700">Pagado</p>
                            <p class="text-sm font-bold text-emerald-700">S/ {{ number_format((float) $card->paid_total, 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-2 text-center">
                            <p class="text-[11px] uppercase tracking-wide text-amber-700">Pendiente</p>
                            <p class="text-sm font-bold text-amber-700">S/ {{ number_format(max(0, (float) $card->total - (float) $card->paid_total), 2) }}</p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-3.5 flex flex-wrap gap-2">
                        @if($card->status === 'approved')
                            <form method="POST" action="{{ route('workshop.maintenance-board.start', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-700">Iniciar servicio</button>
                            </form>
                        @endif
                        @if($card->status === 'in_progress')
                            <form method="POST" action="{{ route('workshop.maintenance-board.finish', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">Finalizar servicio</button>
                            </form>
                        @endif
                        @if($canQuoteCard)
                            <button type="button"
                                    class="rounded-xl bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-800"
                                    @click="$dispatch('open-board-quotation-modal', @js($quotationPayload))">
                                Cotización
                            </button>
                        @endif
                        @if($canCheckoutCard)
                            <a href="{{ route('workshop.maintenance-board.checkout.page', $card) }}"
                               class="inline-flex items-center rounded-xl bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                                Venta y cobro
                            </a>
                        @endif
                        <a href="{{ route('workshop.orders.show', $card) }}" class="rounded-xl bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">Ver detalle</a>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <i class="ri-motorbike-line text-5xl text-gray-300"></i>
                    <p class="mt-3 text-sm text-gray-600">No hay servicios activos. Agrega un vehículo para iniciar mantenimiento.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-5">{{ $cards->links() }}</div>
    </x-common.component-card>

    <x-ui.modal
        x-data="{
            open: false,
            action: '',
            order_label: '',
            status: '',
            quote_lines: [],
            lineSubtotal(line) {
                const qty = Number(line.qty || 0);
                const unitPrice = Number(line.unit_price || 0);
                return qty * unitPrice;
            },
            quoteTotal() {
                return this.quote_lines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
            }
        }"
        x-on:open-board-quotation-modal.window="
            open = true;
            action = $event.detail.action;
            order_label = $event.detail.order_label;
            status = String($event.detail.status || '');
            quote_lines = Array.isArray($event.detail.quote_lines) ? $event.detail.quote_lines : [];
        "
        :isOpen="false"
        :showCloseButton="false"
        class="max-w-5xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600">Cotización de mantenimiento</p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Servicios seleccionados</h3>
                    <p class="text-sm text-gray-500" x-text="order_label"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50/50 px-4 py-3 text-sm text-indigo-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        Estado actual:
                        <span class="font-semibold" x-text="status === 'awaiting_approval' ? 'Esperando aprobación' : (status === 'approved' ? 'Aprobado' : (status === 'in_progress' ? 'En reparación' : status))"></span>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-white px-3 py-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">Servicios</span>
                        <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white" x-text="quote_lines.length"></span>
                    </div>
                </div>
            </div>

            <form method="POST" :action="action" class="space-y-4">
                @csrf
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="w-full">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Servicio</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Cantidad</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Precio</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide">Subtotal</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Quitar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="quote_lines.length === 0">
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">No hay servicios para cotizar en esta OS.</td>
                                </tr>
                            </template>
                            <template x-for="(line, index) in quote_lines" :key="`quote-line-${line.detail_id}`">
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-slate-700" x-text="line.description"></td>
                                    <td class="px-3 py-2">
                                        <input type="hidden" :name="`quote_lines[${index}][detail_id]`" :value="line.detail_id">
                                        <input type="number" step="0.01" min="0.01" :name="`quote_lines[${index}][qty]`" x-model="line.qty"
                                               class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" :name="`quote_lines[${index}][unit_price]`" x-model="line.unit_price"
                                               class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-800" x-text="`S/ ${lineSubtotal(line).toFixed(2)}`"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @click="quote_lines.splice(index, 1)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">Nota de cotización (opcional)</label>
                        <input name="quote_note" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Detalle para cliente">
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Total cotización</p>
                        <p class="mt-1 text-2xl font-extrabold text-emerald-700" x-text="`S/ ${quoteTotal().toFixed(2)}`"></p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#0ea5e9,#2563eb);color:#fff" x-bind:disabled="quote_lines.length === 0">
                        <i class="ri-checkbox-circle-line"></i><span>Aprobar cotización</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i><span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

</div>
@endsection
