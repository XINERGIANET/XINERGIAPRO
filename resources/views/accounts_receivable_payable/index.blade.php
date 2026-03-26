@extends('layouts.app')

@section('content')
    @php
        $viewId = $viewId ?? request('view_id');
        $baseRoute = $type === 'COBRAR' ? 'admin.cash-accounts.receivables' : 'admin.cash-accounts.payables';
        $settlementErrors = $errors->getBag('settlement');
        $settlementHasErrors = $settlementErrors->any();
        $defaultCashRegisterId = (string) ($defaultCashRegisterId ?? optional(($cashRegisters ?? collect())->first())->id ?? '');
        $defaultPaymentMethodId = (string) data_get(collect($paymentMethodOptions ?? collect())->first(), 'id', '');
        $modalTitle = $type === 'COBRAR' ? 'Registrar cobro' : 'Registrar pago';
        $modalButtonLabel = $type === 'COBRAR' ? 'Guardar cobro' : 'Guardar pago';
        $rowButtonLabel = $type === 'COBRAR' ? 'Cobrar' : 'Pagar';
    @endphp

    <div
        x-data="{
            open: @js($settlementHasErrors && !empty($settlementDraftRecord)),
            account: @js($settlementDraftRecord),
            formAction: @js($settlementDraftRecord['action'] ?? ''),
            settlementAccountId: @js((string) old('settlement_account_id', $settlementDraftRecord['id'] ?? '')),
            cashRegisterId: @js((string) old('cash_register_id', $settlementDraftRecord['preferred_cash_register_id'] ?? $defaultCashRegisterId)),
            paymentMethodId: @js((string) old('payment_method_id', $defaultPaymentMethodId)),
            amount: @js((string) old('amount', isset($settlementDraftRecord['pending']) ? number_format((float) $settlementDraftRecord['pending'], 2, '.', '') : '')),
            reference: @js((string) old('reference', '')),
            cardId: @js((string) old('card_id', '')),
            digitalWalletId: @js((string) old('digital_wallet_id', '')),
            paymentGatewayId: @js((string) old('payment_gateway_id', '')),
            comment: @js((string) old('comment', '')),
            paymentMethodOptions: @js(($paymentMethodOptions ?? collect())->values()->all()),
            cardOptions: @js(($cardOptions ?? collect())->values()->all()),
            digitalWalletOptions: @js(($digitalWalletOptions ?? collect())->values()->all()),
            paymentGatewayOptionsByMethod: @js($paymentGatewayOptionsByMethod ?? []),
            defaultCashRegisterId: @js($defaultCashRegisterId),
            defaultPaymentMethodId: @js($defaultPaymentMethodId),
            init() {
                this.syncMethodKind();
                if (this.account && !this.amount) {
                    this.amount = this.normalizeMoney(this.account.pending);
                }
            },
            normalizeMoney(value) {
                return Number(value || 0).toFixed(2);
            },
            currentKind() {
                const method = this.paymentMethodOptions.find((item) => String(item.id) === String(this.paymentMethodId || ''));
                return method?.kind || 'other';
            },
            syncMethodKind() {
                const kind = this.currentKind();
                if (kind !== 'card') {
                    this.cardId = '';
                    this.paymentGatewayId = '';
                }
                if (kind !== 'wallet') {
                    this.digitalWalletId = '';
                }
            },
            availableGateways() {
                return this.paymentGatewayOptionsByMethod[String(this.paymentMethodId || '')] || [];
            },
            openSettlement(payload) {
                this.account = payload;
                this.formAction = payload.action;
                this.settlementAccountId = String(payload.id || '');
                this.cashRegisterId = String(payload.preferred_cash_register_id || this.defaultCashRegisterId || '');
                this.paymentMethodId = this.defaultPaymentMethodId;
                this.amount = this.normalizeMoney(payload.pending || 0);
                this.reference = '';
                this.cardId = '';
                this.digitalWalletId = '';
                this.paymentGatewayId = '';
                this.comment = '';
                this.syncMethodKind();
                this.open = true;
            },
            closeSettlement() {
                this.open = false;
            }
        }"
    >
        <x-common.page-breadcrumb :pageTitle="$pageTitle" />

        <x-common.component-card :title="$pageTitle" :desc="$pageDescription">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mb-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">S/ {{ number_format($totalAmount, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Pagado</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">S/ {{ number_format($totalPaid, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-amber-700">Pendiente</p>
                    <p class="mt-2 text-2xl font-black text-amber-700">S/ {{ number_format($totalPending, 2) }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
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
                            inputClass="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800"
                        />
                    </div>
                    <div class="relative flex-1">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por número o persona"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400"
                        />
                    </div>
                    <div class="w-full sm:w-44 sm:flex-none">
                        <x-form.select-autocomplete
                            name="status"
                            :value="in_array($statusFilter ?? 'ALL', ['ALL', 'all']) ? 'all' : ($statusFilter ?? 'all')"
                            :options="[['value' => 'all', 'label' => 'Todos los estados'], ['value' => 'NUEVO', 'label' => 'Nuevo'], ['value' => 'PAGANDO', 'label' => 'Pagando'], ['value' => 'PAGADO', 'label' => 'Pagado']]"
                            placeholder="Todos los estados"
                            inputClass="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-6" style="background-color: #334155; border-color: #334155;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route($baseRoute, $viewId ? ['view_id' => $viewId] : []) }}" class="h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white">
                <table class="w-full">
                    <thead>
                        <tr class="text-white">
                            @foreach (['Cuenta', 'Documento', 'Persona', 'Fecha', 'Vence', 'Total', 'Pagado', 'Pendiente', 'Estado', 'Acciones'] as $heading)
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    <p class="text-theme-xs font-semibold uppercase text-white">{{ $heading }}</p>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            @php
                                $cashMovement = $record->cashMovement;
                                $cashEntryMovement = $cashMovement?->movement;
                                $sourceMovement = $cashEntryMovement?->parentMovement ?: $cashEntryMovement;
                                $total = (float) ($cashMovement?->total ?? 0);
                                $paid = (float) ($record->total_paid ?? 0);
                                $pending = max(0, $total - $paid);
                                $documentLabel = trim(($sourceMovement?->documentType?->name ?? '-') . ' ' . ($sourceMovement?->number ?? ''));
                                $personLabel = $sourceMovement?->person_name ?: ($cashEntryMovement?->person_name ?: '-');
                                $statusClasses = match (strtoupper((string) $record->status)) {
                                    'PAGADO' => 'bg-emerald-100 text-emerald-700',
                                    'PAGANDO' => 'bg-amber-100 text-amber-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                                $settlementPayload = [
                                    'id' => (int) $record->id,
                                    'action' => route('admin.cash-accounts.settle', array_filter([
                                        'account' => $record->id,
                                        'view_id' => $viewId,
                                    ], fn ($value) => $value !== null && $value !== '')),
                                    'number' => (string) ($record->number ?? ''),
                                    'person_label' => (string) $personLabel,
                                    'document_label' => (string) $documentLabel,
                                    'date_label' => optional($record->date)->format('d/m/Y H:i') ?? '-',
                                    'due_date_label' => optional($record->due_date)->format('d/m/Y H:i') ?? '-',
                                    'currency' => (string) ($record->currency ?? 'PEN'),
                                    'total' => $total,
                                    'paid' => $paid,
                                    'pending' => $pending,
                                    'preferred_cash_register_id' => str_contains(mb_strtolower(trim((string) ($sourceMovement?->documentType?->name ?? '')), 'UTF-8'), 'factura')
                                        ? ($invoiceCashRegisterId ?? $defaultCashRegisterId)
                                        : ($defaultCashRegisterId ?? ''),
                                ];
                            @endphp
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50">
                                <td class="px-5 py-4 text-center text-sm font-bold text-gray-800">{{ $record->number }}</td>
                                <td class="px-5 py-4 text-center text-sm text-gray-700">{{ $documentLabel }}</td>
                                <td class="px-5 py-4 text-center text-sm text-gray-700">{{ $personLabel }}</td>
                                <td class="px-5 py-4 text-center text-sm text-gray-700">{{ optional($record->date)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-5 py-4 text-center text-sm text-gray-700">{{ optional($record->due_date)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-5 py-4 text-center text-sm font-bold text-slate-900">S/ {{ number_format($total, 2) }}</td>
                                <td class="px-5 py-4 text-center text-sm font-medium text-emerald-700">S/ {{ number_format($paid, 2) }}</td>
                                <td class="px-5 py-4 text-center text-sm font-bold text-amber-700">S/ {{ number_format($pending, 2) }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses }}">
                                        {{ $record->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($pending > 0.009)
                                        <button
                                            type="button"
                                            @click="openSettlement(@js($settlementPayload))"
                                            class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                            style="background-color: {{ $type === 'COBRAR' ? '#0f766e' : '#dc2626' }};"
                                        >
                                            <i class="ri-money-dollar-circle-line mr-2"></i>
                                            {{ $rowButtonLabel }}
                                        </button>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                            Sin saldo
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No hay registros para este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $records->links() }}
            </div>
        </x-common.component-card>

        <template x-teleport="body">
            <div
                x-show="open"
                x-cloak
                x-effect="document.body.style.overflow = open ? 'hidden' : 'unset'; open && ($el.scrollTop = 0)"
                class="fixed inset-0 z-[100000] flex items-center justify-center overflow-hidden p-3 sm:p-6"
            >
                <div
                    @click="closeSettlement()"
                    class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                ></div>

                <div
                    @click.stop
                    class="relative flex w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-[#F4F6FA]"
                    style="max-height: min(calc(100vh - 1.5rem), calc(100dvh - 1.5rem));"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                >
                    <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-8">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Movimiento de cuenta</p>
                        <h3 class="mt-2 text-3xl font-black text-slate-800">{{ $modalTitle }}</h3>
                        <p class="mt-2 text-sm text-slate-500">
                            Registra un {{ strtolower($rowButtonLabel) }} parcial o total y actualiza el saldo pendiente de la cuenta.
                        </p>
                    </div>
                    <button type="button" @click="closeSettlement()" class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="mb-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Cuenta</p>
                        <p class="mt-2 text-lg font-extrabold text-slate-900" x-text="account?.number || '-'"></p>
                        <p class="mt-1 text-sm text-slate-500" x-text="account?.document_label || '-'"></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Persona</p>
                        <p class="mt-2 text-lg font-extrabold text-slate-900" x-text="account?.person_label || '-'"></p>
                        <p class="mt-1 text-sm text-slate-500">
                            <span x-text="account?.date_label || '-'"></span>
                            <span class="mx-1 text-slate-300">·</span>
                            <span x-text="account?.due_date_label || '-'"></span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-700">Saldo pendiente</p>
                        <p class="mt-2 text-2xl font-black text-amber-700" x-text="`${account?.currency === 'USD' ? '$' : 'S/'} ${Number(account?.pending || 0).toFixed(2)}`"></p>
                        <p class="mt-1 text-sm text-amber-800/80" x-text="`Pagado: ${account?.currency === 'USD' ? '$' : 'S/'} ${Number(account?.paid || 0).toFixed(2)}`"></p>
                    </div>
                </div>

                @if ($settlementErrors->any())
                    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">No se pudo registrar el movimiento.</p>
                        <p class="mt-1">{{ $settlementErrors->first('general') ?: $settlementErrors->first() }}</p>
                    </div>
                @endif

                <form method="POST" :action="formAction" class="space-y-5">
                    @csrf
                    <input type="hidden" name="settlement_account_id" :value="settlementAccountId">

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Caja</label>
                            <select
                                name="cash_register_id"
                                x-model="cashRegisterId"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700"
                            >
                                @foreach (($cashRegisters ?? collect()) as $cashRegister)
                                    <option value="{{ $cashRegister->id }}">
                                        Caja {{ $cashRegister->number }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($settlementErrors->has('cash_register_id'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('cash_register_id') }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Método de pago</label>
                            <select
                                name="payment_method_id"
                                x-model="paymentMethodId"
                                @change="syncMethodKind()"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700"
                            >
                                @foreach (collect($paymentMethodOptions ?? collect()) as $method)
                                    <option value="{{ $method['id'] }}">{{ $method['description'] }}</option>
                                @endforeach
                            </select>
                            @if ($settlementErrors->has('payment_method_id'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('payment_method_id') }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Monto</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="account ? Number(account.pending || 0) : null"
                                name="amount"
                                x-model="amount"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800"
                                placeholder="0.00"
                            >
                            @if ($settlementErrors->has('amount'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('amount') }}</p>
                            @endif
                        </div>
                    </div>

                    <div
                        class="grid gap-4"
                        :class="currentKind() === 'card'
                            ? 'lg:grid-cols-4'
                            : (currentKind() === 'wallet' ? 'lg:grid-cols-3' : 'lg:grid-cols-2')"
                    >
                        <div x-show="currentKind() === 'card'" x-cloak>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tarjeta</label>
                            <select
                                name="card_id"
                                x-model="cardId"
                                :required="currentKind() === 'card'"
                                :disabled="currentKind() !== 'card'"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700"
                            >
                                <option value="">Seleccionar tarjeta</option>
                                <template x-for="card in cardOptions" :key="`card-${card.id}`">
                                    <option :value="String(card.id)" x-text="card.type ? `${card.description} (${card.type})` : card.description"></option>
                                </template>
                            </select>
                            @if ($settlementErrors->has('card_id'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('card_id') }}</p>
                            @endif
                        </div>

                        <div x-show="currentKind() === 'card'" x-cloak>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pasarela</label>
                            <select
                                name="payment_gateway_id"
                                x-model="paymentGatewayId"
                                :disabled="currentKind() !== 'card'"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700"
                            >
                                <option value="">Sin pasarela</option>
                                <template x-for="gateway in availableGateways()" :key="`gateway-${gateway.id}`">
                                    <option :value="String(gateway.id)" x-text="gateway.description"></option>
                                </template>
                            </select>
                            @if ($settlementErrors->has('payment_gateway_id'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('payment_gateway_id') }}</p>
                            @endif
                        </div>

                        <div x-show="currentKind() === 'wallet'" x-cloak>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Billetera digital</label>
                            <select
                                name="digital_wallet_id"
                                x-model="digitalWalletId"
                                :required="currentKind() === 'wallet'"
                                :disabled="currentKind() !== 'wallet'"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700"
                            >
                                <option value="">Seleccionar billetera</option>
                                <template x-for="wallet in digitalWalletOptions" :key="`wallet-${wallet.id}`">
                                    <option :value="String(wallet.id)" x-text="wallet.description"></option>
                                </template>
                            </select>
                            @if ($settlementErrors->has('digital_wallet_id'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('digital_wallet_id') }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Referencia</label>
                            <input
                                type="text"
                                name="reference"
                                x-model="reference"
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700"
                                placeholder="Voucher, operación o nota"
                            >
                            @if ($settlementErrors->has('reference'))
                                <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('reference') }}</p>
                            @endif
                        </div>

                        <div x-show="currentKind() !== 'card' && currentKind() !== 'wallet'" x-cloak>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Detalle</label>
                            <div class="flex h-12 items-center rounded-xl border border-dashed border-slate-300 bg-white px-4 text-sm text-slate-400">
                                Sin detalles adicionales requeridos.
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Comentario</label>
                        <textarea
                            name="comment"
                            x-model="comment"
                            rows="3"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700"
                            placeholder="Detalle opcional del movimiento de caja"
                        ></textarea>
                        @if ($settlementErrors->has('comment'))
                            <p class="mt-1 text-xs text-rose-600">{{ $settlementErrors->first('comment') }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                        <button
                            type="button"
                            @click="closeSettlement()"
                            class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                        >
                            <i class="ri-close-line mr-2"></i>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="inline-flex h-12 items-center justify-center rounded-xl px-6 text-sm font-semibold text-white transition hover:opacity-90"
                            style="background-color: {{ $type === 'COBRAR' ? '#0f766e' : '#dc2626' }};"
                        >
                            <i class="ri-save-line mr-2"></i>
                            {{ $modalButtonLabel }}
                        </button>
                    </div>
                </form>
            </div>
                </div>
            </div>
        </template>
    </div>
@endsection
