@extends('layouts.app')

@php
    use Illuminate\Support\Js;

    $routeParams = ['cash_register_id' => $cashRegisterId];
    if (!empty($viewId)) {
        $routeParams['view_id'] = $viewId;
    }

    $backUrl = route('admin.petty-cash.index', $routeParams);
    $storeUrl = route('admin.petty-cash.close.store', $routeParams);
    $breadcrumbs = [
        ['label' => 'Caja chica', 'url' => $backUrl],
        ['label' => 'Caja chica | Cerrar caja'],
    ];

    $money = static fn ($value) => number_format((float) $value, 2);
@endphp

@section('content')
    <style>
        [x-cloak] {
            display: none !important;
        }

        .close-page-card {
            border-radius: 28px;
            border: 1px solid #d7dde7;
            background: #fff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
        }

        .close-field {
            border-bottom: 1px solid #c9d0db;
            padding-bottom: 8px;
        }

        .close-field-label {
            margin-bottom: 8px;
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #99a1b3;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .close-field-value {
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            color: #0f172a;
        }

        .close-amount-card {
            border-bottom: 1px solid #c9d0db;
            padding-bottom: 10px;
        }

        .close-amount-card span {
            display: block;
        }

        .close-amount-card .label {
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #99a1b3;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .close-amount-card .value {
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
            color: #111827;
        }

        .close-radio {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            color: #7b8497;
        }

        .close-radio input {
            accent-color: #14b8a6;
            width: 20px;
            height: 20px;
        }

        .close-note {
            width: 100%;
            min-height: 42px;
            resize: vertical;
            border: 0;
            border-bottom: 1px solid #c9d0db;
            background: transparent;
            padding: 0 0 8px;
            font-size: 18px;
            color: #0f172a;
            outline: none;
        }

        .close-summary-card {
            border-bottom: 1px solid #c9d0db;
            padding-bottom: 10px;
        }

        .close-summary-card .label {
            margin-bottom: 6px;
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #99a1b3;
        }

        .close-summary-card .value {
            font-size: 31px;
            line-height: 1;
            font-weight: 800;
            color: #000;
        }

        .close-section-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-radius: 999px;
            background: #353b44;
            color: #fff;
            padding: 10px 18px;
            font-size: 21px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .close-money-table {
            width: 100%;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid #d7dde7;
            background: #fff;
        }

        .close-money-table thead th {
            background: #353b44;
            color: #fff;
            padding: 18px 16px;
            font-size: 15px;
            font-weight: 700;
        }

        .close-money-table tbody td {
            border-bottom: 1px solid #edf1f7;
            padding: 18px 16px;
            vertical-align: middle;
        }

        .close-money-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .close-count-input,
        .close-note-input {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #c9d0db;
            background: transparent;
            padding: 0 0 6px;
            outline: none;
        }

        .close-count-input {
            text-align: center;
            font-size: 23px;
            font-weight: 800;
            color: #111827;
        }

        .close-note-input {
            font-size: 15px;
            color: #4b5563;
        }

        .close-detail-table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 22px;
        }

        .close-detail-table thead th {
            background: #353b44;
            color: #fff;
            padding: 16px 14px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .close-detail-table tbody td {
            border-bottom: 1px solid #edf1f7;
            padding: 14px;
            vertical-align: middle;
            color: #111827;
        }

        .close-detail-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .close-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .close-eye-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 0;
            background: #2090ff;
            color: #fff;
            transition: transform .18s ease, opacity .18s ease;
        }

        .close-eye-btn:hover {
            opacity: .92;
            transform: translateY(-1px);
        }

        .close-footer-total {
            border-top: 1px solid #d7dde7;
            padding-top: 16px;
        }

        .close-footer-total .label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #99a1b3;
            margin-bottom: 8px;
        }

        .close-footer-total .value {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .close-action-link,
        .close-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 56px;
            border-radius: 999px;
            padding: 0 28px;
            font-size: 17px;
            font-weight: 700;
            transition: transform .18s ease, opacity .18s ease, box-shadow .18s ease;
        }

        .close-action-link:hover,
        .close-action-btn:hover {
            opacity: .95;
            transform: translateY(-1px);
        }

        .close-action-link {
            border: 1px solid #d7dde7;
            color: #334155;
            background: #fff;
        }

        .close-action-btn {
            border: 0;
            color: #fff;
            background: #ff623d;
            box-shadow: 0 12px 24px rgba(255, 98, 61, 0.24);
        }

        .close-modal-shell {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .close-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(16px);
        }

        .close-modal-card {
            position: relative;
            width: min(1450px, calc(100vw - 48px));
            max-height: calc(100vh - 48px);
            overflow: hidden;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.2);
        }

        .close-modal-body {
            max-height: calc(100vh - 48px);
            overflow: auto;
        }
    </style>

    <x-common.page-breadcrumb
        pageTitle="Caja chica | Cerrar caja"
        :crumbs="$breadcrumbs"
        iconHtml='<i class="ri-add-line"></i>'
    />

    @if (session('error'))
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <p class="mb-2 font-semibold">No se pudo registrar el cierre.</p>
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        x-data="pettyCashClosePage()"
        x-effect="document.body.style.overflow = detailModalOpen ? 'hidden' : ''"
    >
        <form action="{{ $storeUrl }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="cash_register_id" value="{{ $cashRegisterId }}">
            <input type="hidden" name="shift_id" value="{{ $shiftId }}">

            <div class="close-page-card p-5 sm:p-8">
                <div class="grid gap-8 xl:grid-cols-2">
                    <div class="space-y-8">
                        <div class="grid gap-6 lg:grid-cols-3">
                            <div class="close-field">
                                <span class="close-field-label">Persona</span>
                                <div class="close-field-value">{{ $personLabel }}</div>
                            </div>
                            <div class="close-field">
                                <span class="close-field-label">Responsable</span>
                                <div class="close-field-value">
                                    <i class="ri-user-star-line text-2xl text-slate-700"></i>
                                    <span>{{ $responsibleLabel }}</span>
                                </div>
                            </div>
                            <div class="close-field">
                                <span class="close-field-label">Turno</span>
                                <div class="close-field-value">
                                    <i class="ri-time-line text-2xl text-slate-700"></i>
                                    <span>{{ $shiftName }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <div class="close-field">
                                <span class="close-field-label">Caja</span>
                                <div class="close-field-value">
                                    <i class="ri-safe-2-line text-2xl text-slate-700"></i>
                                    <span>{{ $cashRegister->number }}</span>
                                </div>
                            </div>
                            <div class="close-amount-card">
                                <span class="label">Total</span>
                                <span class="value">S/ {{ $money($systemCash) }}</span>
                            </div>
                            <div class="close-amount-card">
                                <span class="label">Pagado</span>
                                <span class="value">S/ {{ $money($systemCash) }}</span>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <div>
                                <span class="close-field-label">Moneda</span>
                                <div class="flex items-center gap-6 pt-3">
                                    <label class="close-radio">
                                        <input type="radio" checked disabled>
                                        <span>S/.</span>
                                    </label>
                                    <label class="close-radio">
                                        <input type="radio" disabled>
                                        <span>$</span>
                                    </label>
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <label for="comment" class="close-field-label">Notas al movimiento de caja</label>
                                <textarea id="comment" name="comment" class="close-note" placeholder="Cierre de caja">{{ $movementComment }}</textarea>
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-4">
                                <div class="close-section-pill">
                                    <span>Monedas</span>
                                    <i class="ri-coins-line"></i>
                                </div>

                                <div class="close-money-table">
                                    <table class="w-full">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Dinero</th>
                                                <th class="w-28 text-center">Cant.</th>
                                                <th class="text-left">Notas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, index) in coins" :key="row.key">
                                                <tr>
                                                    <td class="text-base font-medium text-slate-800">
                                                        <input type="hidden" :name="`counting[coins][${index}][key]`" :value="row.key">
                                                        <input type="hidden" :name="`counting[coins][${index}][label]`" :value="row.label">
                                                        <input type="hidden" :name="`counting[coins][${index}][value]`" :value="row.value">
                                                        <span x-text="row.label"></span>
                                                    </td>
                                                    <td>
                                                        <input type="number" min="0" step="1" x-model.number="row.quantity" :name="`counting[coins][${index}][quantity]`" class="close-count-input">
                                                    </td>
                                                    <td>
                                                        <input type="text" x-model="row.note" :name="`counting[coins][${index}][note]`" class="close-note-input" placeholder="Sin nota">
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="close-section-pill">
                                    <span>Billetes</span>
                                    <i class="ri-money-dollar-box-line"></i>
                                </div>

                                <div class="close-money-table">
                                    <table class="w-full">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Dinero</th>
                                                <th class="w-28 text-center">Cant.</th>
                                                <th class="text-left">Notas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, index) in bills" :key="row.key">
                                                <tr>
                                                    <td class="text-base font-medium text-slate-800">
                                                        <input type="hidden" :name="`counting[bills][${index}][key]`" :value="row.key">
                                                        <input type="hidden" :name="`counting[bills][${index}][label]`" :value="row.label">
                                                        <input type="hidden" :name="`counting[bills][${index}][value]`" :value="row.value">
                                                        <span x-text="row.label"></span>
                                                    </td>
                                                    <td>
                                                        <input type="number" min="0" step="1" x-model.number="row.quantity" :name="`counting[bills][${index}][quantity]`" class="close-count-input">
                                                    </td>
                                                    <td>
                                                        <input type="text" x-model="row.note" :name="`counting[bills][${index}][note]`" class="close-note-input" placeholder="Sin nota">
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <div>
                            <h3 class="mb-6 text-center text-4xl font-bold text-slate-900">Detalle de cierre</h3>

                            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="close-summary-card">
                                    <span class="label">Ventas en efectivo</span>
                                    <span class="value">S/ {{ $money($cashSales) }}</span>
                                </div>
                                <div class="close-summary-card">
                                    <span class="label">Apertura en efectivo</span>
                                    <span class="value">S/ {{ $money($openingCash) }}</span>
                                </div>
                                <div class="close-summary-card">
                                    <span class="label">Ingresos en efectivo</span>
                                    <span class="value">S/ {{ $money($otherCashIncome) }}</span>
                                </div>
                                <div class="close-summary-card">
                                    <span class="label">Egresos en efectivo</span>
                                    <span class="value">S/ {{ $money($cashExpenses) }}</span>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-6 md:grid-cols-3">
                                <div class="close-summary-card">
                                    <span class="label">Efectivo real</span>
                                    <span class="value" x-text="'S/ ' + formatMoney(realCashTotal)"></span>
                                </div>
                                <div class="close-summary-card">
                                    <span class="label">Efectivo en caja</span>
                                    <span class="value">S/ {{ $money($systemCash) }}</span>
                                </div>
                                <div class="close-summary-card">
                                    <span class="label">Efectivo sin apertura</span>
                                    <span class="value">S/ {{ $money($cashWithoutOpening) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden border border-slate-200" style="border-radius: 24px;">
                            <div class="overflow-x-auto">
                                <table class="close-detail-table min-w-full">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Tipo</th>
                                            <th class="text-left">Monto</th>
                                            <th class="text-left">Medio</th>
                                            <th class="text-left">Detalles</th>
                                            <th class="text-left">Notas</th>
                                            <th class="text-center">Operaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($detailGroups as $index => $group)
                                            @php
                                                $typeStyle = match ($group['type_label']) {
                                                    'Deuda' => 'background:#fde68a;color:#92400e;',
                                                    default => 'background:#4CAF50;color:#ffffff;',
                                                };
                                                $noteStyle = match ($group['category']) {
                                                    'opening' => 'background:#fde68a;color:#92400e;',
                                                    'sale' => 'background:#c084fc;color:#ffffff;',
                                                    'income' => 'background:#38bdf8;color:#ffffff;',
                                                    'expense' => 'background:#fca5a5;color:#991b1b;',
                                                    default => 'background:#e5e7eb;color:#475569;',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    <span class="close-pill" style="{{ $typeStyle }}">{{ $group['type_label'] }}</span>
                                                </td>
                                                <td class="text-3xl font-extrabold">S/ {{ $money($group['amount']) }}</td>
                                                <td class="text-lg font-medium">{{ $group['method'] }}</td>
                                                <td class="text-base text-slate-700">{{ $group['detail_label'] }}</td>
                                                <td>
                                                    <span class="close-pill" style="{{ $noteStyle }}">{{ $group['note'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="close-eye-btn" @click="openDetail({{ $index }})" title="Ver detalle">
                                                        <i class="ri-eye-line text-xl"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">
                                                    No hay movimientos acumulados para este cierre.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid gap-6 px-4 py-5 md:grid-cols-3">
                                <div class="close-footer-total">
                                    <span class="label">Total de ventas</span>
                                    <span class="value">S/ {{ $money($totalSales) }}</span>
                                </div>
                                <div class="close-footer-total">
                                    <span class="label">Total de ingresos</span>
                                    <span class="value">S/ {{ $money($totalOtherIncome) }}</span>
                                </div>
                                <div class="close-footer-total">
                                    <span class="label">Total de egresos</span>
                                    <span class="value">S/ {{ $money($totalExpenses) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap items-center justify-end gap-4 border-t border-slate-200 pt-6">
                    <a href="{{ $backUrl }}" class="close-action-link">
                        <i class="ri-close-line text-xl"></i>
                        <span>Cancelar</span>
                    </a>
                    <button type="submit" class="close-action-btn">
                        <span>Guardar cierre</span>
                        <i class="ri-save-line text-xl"></i>
                    </button>
                </div>
            </div>
        </form>

        <div x-show="detailModalOpen" x-cloak class="close-modal-shell">
            <div class="close-modal-backdrop" @click="closeDetail()"></div>

            <div class="close-modal-card" @click.stop>
                <div class="close-modal-body">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 class="text-4xl font-bold text-slate-800" x-text="selectedGroup ? selectedGroup.modal_title : 'Detalle de movimientos'"></h3>
                            <p class="mt-2 text-base text-slate-500">Detalle agrupado por concepto y medio de pago.</p>
                        </div>
                        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-500" @click="closeDetail()">
                            <i class="ri-close-line text-2xl"></i>
                        </button>
                    </div>

                    <div class="overflow-x-auto px-2 py-2">
                        <table class="close-detail-table" style="min-width: 1280px;">
                            <thead>
                                <tr>
                                    <th class="text-left">Numero</th>
                                    <th class="text-left">Tipo</th>
                                    <th class="text-left">Concepto</th>
                                    <th class="text-left">Total</th>
                                    <th class="text-left">Total para el medio de pago</th>
                                    <th class="text-left">Fecha</th>
                                    <th class="text-left">Usuario</th>
                                    <th class="text-left">Caja</th>
                                    <th class="text-left">Turno</th>
                                    <th class="text-left">Persona</th>
                                    <th class="text-left">Metodos de pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="!selectedGroup || !selectedGroup.records.length">
                                    <tr>
                                        <td colspan="11" class="px-6 py-8 text-center text-sm text-slate-500">No hay registros para este detalle.</td>
                                    </tr>
                                </template>
                                <template x-for="record in (selectedGroup ? selectedGroup.records : [])" :key="record.number + record.moved_at">
                                    <tr>
                                        <td class="text-lg font-medium" x-text="record.number"></td>
                                        <td>
                                            <span class="close-pill" style="background:#14b8a6;color:#ffffff;" x-text="record.type_label"></span>
                                        </td>
                                        <td>
                                            <span class="close-pill" style="background:#f59e0b;color:#ffffff;" x-text="record.concept"></span>
                                        </td>
                                        <td class="text-2xl font-extrabold" x-text="'S/ ' + formatMoney(record.movement_total)"></td>
                                        <td class="text-2xl font-extrabold" x-text="'S/ ' + formatMoney(record.method_total)"></td>
                                        <td class="text-base text-slate-700" x-text="record.moved_at"></td>
                                        <td class="text-base text-slate-700" x-text="record.user_name"></td>
                                        <td class="text-base text-slate-700" x-text="record.cash_register"></td>
                                        <td class="text-base text-slate-700" x-text="record.shift"></td>
                                        <td class="text-base text-slate-700" x-text="record.person_name"></td>
                                        <td class="text-base text-slate-700" x-text="record.payment_label"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end px-6 py-5">
                        <button type="button" class="text-sm font-medium uppercase tracking-wide text-slate-600" @click="closeDetail()">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function pettyCashClosePage() {
            return {
                coins: {{ Js::from($coins) }},
                bills: {{ Js::from($bills) }},
                detailGroups: {{ Js::from($detailGroups) }},
                detailModalOpen: false,
                selectedGroup: null,
                get realCashTotal() {
                    return [...this.coins, ...this.bills].reduce((total, row) => {
                        const quantity = Number(row.quantity || 0);
                        const value = Number(row.value || 0);
                        return total + (quantity * value);
                    }, 0);
                },
                formatMoney(value) {
                    return new Intl.NumberFormat('es-PE', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(Number(value || 0));
                },
                openDetail(index) {
                    this.selectedGroup = this.detailGroups[index] || null;
                    this.detailModalOpen = !!this.selectedGroup;
                },
                closeDetail() {
                    this.detailModalOpen = false;
                    this.selectedGroup = null;
                },
            };
        }
    </script>
@endsection
