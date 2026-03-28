@extends('layouts.app')

@section('content')
<div x-data="{}">
    @if (session('open_initial_report_url'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const url = @js(session('open_initial_report_url'));
                if (!url) return;
                const reportWindow = window.open('', 'workshop-initial-report');
                if (reportWindow) {
                    reportWindow.location = url;
                    reportWindow.focus();
                }
            });
        </script>
    @endif
    <x-common.page-breadcrumb pageTitle="Tablero de Mantenimiento" />

    <x-common.component-card title="Tablero Circular de Servicios" desc="Inicia y finaliza mantenimientos con visual de moto y cliente en tiempo real.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-6 flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="flex flex-wrap items-center gap-2 whitespace-nowrap xl:shrink-0">
                <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.maintenance-board.create') }}" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                    <i class="ri-add-circle-line"></i><span>Agregar Vehiculo</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}">
                    <i class="ri-file-list-3-line"></i><span>Ir a OS</span>
                </x-ui.link-button>
            </div>

            <form method="GET" class="flex min-w-0 flex-1 flex-wrap items-center gap-2 lg:flex-nowrap">
                @if (request()->filled('view_id'))
                    <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                @endif
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    class="h-11 w-full flex-1 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none sm:min-w-[340px]"
                    placeholder="Buscar por OS, DNI, RUC, cliente, placa, marca, modelo o vehículo"
                >
                <select
                    id="status"
                    name="status"
                    onchange="this.form.submit()"
                    class="h-11 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none sm:w-[250px]"
                >
                    <option value="all" @selected(($selectedStatus ?? 'in_progress') === 'all')>Todos</option>
                    <option value="awaiting_approval" @selected(($selectedStatus ?? 'in_progress') === 'awaiting_approval')>Esperando aprobación</option>
                    <option value="approved" @selected(($selectedStatus ?? 'in_progress') === 'approved')>Aprobado</option>
                    <option value="in_progress" @selected(($selectedStatus ?? 'in_progress') === 'in_progress')>En reparación / Pausa</option>
                    <option value="paused" @selected(($selectedStatus ?? 'in_progress') === 'paused')>Pausados (Solo)</option>
                    <option value="finished" @selected(($selectedStatus ?? 'in_progress') === 'finished')>Terminado</option>
                    <option value="delivered" @selected(($selectedStatus ?? 'in_progress') === 'delivered')>Entregado</option>
                    <option value="cancelled" @selected(($selectedStatus ?? 'in_progress') === 'cancelled')>Anulado</option>
                </select>
                <x-ui.button size="sm" variant="primary" type="submit" className="h-11 whitespace-nowrap px-5" style="background-color:#334155;border-color:#334155;color:#fff" onmouseover="this.style.backgroundColor='#1e293b';this.style.borderColor='#1e293b'" onmouseout="this.style.backgroundColor='#334155';this.style.borderColor='#334155'">
                    <i class="ri-search-line"></i><span>Filtrar</span>
                </x-ui.button>
                <x-ui.link-button size="sm" variant="outline" href="{{ route('workshop.maintenance-board.index', array_filter(['status' => 'in_progress', 'view_id' => request('view_id')])) }}" className="h-11 whitespace-nowrap px-5">
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
                    $canCheckoutCard = ((string) $card->status === 'finished'
                        && ($pendingDebtCard > 0.00001 || (float) $card->total <= 0.00001));
                    $canEditBoardCard = !in_array((string) $card->status, ['cancelled', 'delivered'], true) && !$card->sales_movement_id;
                    $quotationPayload = [
                        'action' => route('workshop.maintenance-board.quotation', $card),
                        'order_label' => 'OS ' . ($card->movement?->number ?? ('#' . $card->id)) . ' - ' . trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')),
                        'status' => (string) $card->status,
                        'quote_lines' => $card->details
                            ->filter(fn ($detail) => in_array((string) $detail->line_type, ['SERVICE', 'PART'], true) && $detail->sales_movement_id === null)
                            ->values()
                            ->map(function ($detail) {
                                $label = trim((string) ($detail->description ?? ''));
                                if ($label === '') {
                                    if ((string) $detail->line_type === 'PART') {
                                        $code = trim((string) ($detail->product?->code ?? ''));
                                        $name = trim((string) ($detail->product?->description ?? ''));
                                        $label = $code !== '' ? ($code . ($name !== '' ? ' - ' . $name : '')) : ($name !== '' ? $name : 'Repuesto');
                                    } else {
                                        $label = (string) ($detail->service?->name ?? 'Servicio');
                                    }
                                }

                                return [
                                    'detail_id' => (int) $detail->id,
                                    'line_type' => (string) $detail->line_type,
                                    'description' => $label,
                                    'qty' => (float) $detail->qty,
                                    'unit_price' => (float) $detail->unit_price,
                                    'subtotal' => (float) $detail->total,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                    $statusMap = [
                        'draft' => ['Borrador', 'bg-slate-100 text-slate-700 border-slate-200'],
                        'diagnosis' => ['Diagnóstico', 'bg-indigo-100 text-indigo-700 border-indigo-200'],
                        'awaiting_approval' => ['Esperando aprobación', 'bg-amber-100 text-amber-700 border-amber-200'],
                        'approved' => ['Aprobado', 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                        'in_progress' => ['En reparación', 'bg-orange-100 text-orange-700 border-orange-200'],
                        'paused' => ['Pausado', 'bg-slate-200 text-slate-800 border-slate-300'],
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-xs tracking-wide text-slate-200">Placa {{ $card->vehicle?->plate ?: 'S/PLACA' }}</p>
                                    @if($card->vehicle)
                                        @php
                                            $soat = $card->vehicle->getDocumentStatus($card->vehicle->soat_vencimiento);
                                            $rev = $card->vehicle->getDocumentStatus($card->vehicle->revision_tecnica_vencimiento);
                                            $colorMapIndex = [
                                                'success' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                                'warning' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                                'danger' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                            ];
                                        @endphp
                                        <span class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $colorMapIndex[$soat['color']] }}" title="SOAT: {{ $card->vehicle->soat_vencimiento }}">
                                            <i class="ri-shield-check-line"></i> SOAT
                                        </span>
                                        <span class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $colorMapIndex[$rev['color']] }}" title="Rev. Técnica: {{ $card->vehicle->revision_tecnica_vencimiento }}">
                                            <i class="ri-rest-time-line"></i> REV
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 mt-3 grid grid-cols-1 gap-2 text-sm text-slate-700 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cliente</p>
                            <p class="font-semibold text-slate-800">{{ trim(($card->client?->first_name ?? '') . ' ' . ($card->client?->last_name ?? '')) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                {{ $card->status === 'in_progress' ? 'Inicio del servicio' : 'Ingreso' }}
                            </p>
                            <p class="font-semibold text-slate-800">
                                {{ optional($card->status === 'in_progress' ? $card->started_at : $card->intake_date)->format('Y-m-d H:i') }}
                            </p>
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
                            <button type="button"
                                    class="rounded-xl bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-700"
                                    @click="$dispatch('open-start-service-modal', {
                                        action: @js(route('workshop.maintenance-board.start', $card)),
                                        order_label: @js('OS ' . ($card->movement?->number ?? ('#' . $card->id)) . ' - ' . trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')))
                                    })">
                                Iniciar servicio
                            </button>
                        @endif
                        @if($card->status === 'in_progress')
                            <button type="button"
                                    class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-900"
                                    @click="$dispatch('open-pause-service-modal', {
                                        action: @js(route('workshop.maintenance-board.pause', $card)),
                                        order_label: @js('OS ' . ($card->movement?->number ?? ('#' . $card->id)) . ' - ' . trim(($card->vehicle?->brand ?? '') . ' ' . ($card->vehicle?->model ?? '')))
                                    })">
                                <i class="ri-pause-circle-line"></i> Pausar
                            </button>
                            <form method="POST" action="{{ route('workshop.maintenance-board.finish', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">Finalizar servicio</button>
                            </form>
                        @endif
                        @if($card->status === 'paused')
                            <form method="POST" action="{{ route('workshop.maintenance-board.resume', $card) }}">
                                @csrf
                                <button class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                    <i class="ri-play-circle-line"></i> Reanudar
                                </button>
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
                        @if($canEditBoardCard)
                            <a href="{{ route('workshop.maintenance-board.edit', $card) }}"
                               class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700">
                                Editar
                            </a>
                        @endif
                        <a href="{{ route('workshop.pdf.order', $card) }}" target="_blank" rel="noopener" class="rounded-xl bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">I. inicial</a>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <i class="ri-motorbike-line text-5xl text-gray-300"></i>
                    <p class="mt-3 text-sm text-gray-600">No hay servicios activos. Agrega un vehículo para iniciar mantenimiento.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-5 flex justify-end">
            <div class="flex-none pagination-simple">
                {{ $cards->links('vendor.pagination.forced') }}
            </div>
        </div>
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
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Servicios y repuestos</h3>
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
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">Lineas</span>
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
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Concepto</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Cantidad</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Precio</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide">Subtotal</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Quitar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="quote_lines.length === 0">
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No hay servicios ni repuestos para cotizar en esta OS.</td>
                                </tr>
                            </template>
                            <template x-for="(line, index) in quote_lines" :key="`quote-line-${line.detail_id}-${line.line_type || 'x'}`">
                                <tr>
                                    <td class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">
                                        <span x-text="line.line_type === 'PART' ? 'Repuesto' : 'Servicio'"></span>
                                    </td>
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

    <x-ui.modal
        x-data="{
            open: false,
            action: '',
            order_label: '',
            technician_id: '',
        }"
        x-on:open-start-service-modal.window="
            open = true;
            action = $event.detail.action;
            order_label = $event.detail.order_label;
            technician_id = '';
        "
        :isOpen="false"
        class="max-w-md">
        <div class="p-6">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Comenzar Trabajo</p>
                    <h3 class="text-xl font-extrabold text-slate-800">Asignar Técnico</h3>
                    <p class="text-sm text-slate-500" x-text="order_label"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form method="POST" :action="action" class="space-y-6">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">¿Quién se encargará de este servicio?</label>
                    <div class="relative">
                        <select name="technician_person_id" x-model="technician_id" required
                                class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 py-3.5 pl-4 pr-10 text-sm font-medium text-slate-700 transition-all focus:border-orange-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-orange-500/10">
                            <option value="">Seleccione un técnico...</option>
                            @foreach($technicians ?? [] as $tech)
                                <option value="{{ $tech['id'] }}">{{ $tech['name'] }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <i class="ri-arrow-down-s-line text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-2">
                    <button type="submit" :disabled="!technician_id"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-orange-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-600/20 transition-all hover:bg-orange-700 hover:shadow-orange-600/30 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                        <i class="ri-play-circle-line text-lg"></i>
                        <span>Iniciar Servicio Ahora</span>
                    </button>
                    <button type="button" @click="open = false"
                            class="w-full rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-700">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    <x-ui.modal
        x-data="{
            open: false,
            action: '',
            order_label: '',
            comment: ''
        }"
        x-on:open-pause-service-modal.window="
            open = true;
            action = $event.detail.action;
            order_label = $event.detail.order_label;
            comment = '';
        "
        :isOpen="false"
        class="max-w-md">
        <div class="p-6">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Pausar Trabajo</p>
                    <h3 class="text-xl font-extrabold text-slate-800">Motivo de Pausa</h3>
                    <p class="text-sm text-slate-500" x-text="order_label"></p>
                </div>
                <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form method="POST" :action="action" class="space-y-6">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">¿Por qué se pausa este servicio? (Requerido)</label>
                    <textarea name="pause_comment" x-model="comment" required
                              class="w-full rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-medium text-slate-700 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10"
                              placeholder="Ej: Técnico asignado a otra tarea, falta repuesto, etc."
                              rows="3"></textarea>
                </div>

                <div class="flex flex-col gap-3 pt-2">
                    <button type="submit" :disabled="!comment.trim()"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-800 py-3.5 text-sm font-bold text-white shadow-lg shadow-slate-800/20 transition-all hover:bg-slate-900 hover:shadow-slate-800/30 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                        <i class="ri-pause-circle-line text-lg"></i>
                        <span>Confirmar Pausa</span>
                    </button>
                    <button type="button" @click="open = false"
                            class="w-full rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-700">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </x-ui.modal>

</div>
@endsection
