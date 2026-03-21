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
        [x-cloak] { display: none !important; }

        /* Dashboard Layout */
        .pc-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .pc-container { grid-template-columns: 1fr; }
        }

        /* Generic Card */
        .pc-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Sidebar Elements */
        .pc-sidebar-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 24px;
        }

        .pc-sidebar-field {
            margin-bottom: 12px;
        }

        .pc-sidebar-label {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            display: block;
        }

        .pc-sidebar-value {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Calculation Tiles (Sidebar) */
        .pc-tile {
            border-radius: 16px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .pc-tile.green { background: #ecfdf5; color: #059669; }
        .pc-tile.gray { background: #f1f5f9; color: #475569; }

        .pc-tile-label { font-size: 13px; font-weight: 700; }
        .pc-tile-value { font-size: 20px; font-weight: 800; }

        /* Currency Selector */
        .pc-currency-box {
            background: #eff6ff;
            border-radius: 16px;
            padding: 16px;
            margin-top: 24px;
        }

        .pc-currency-label {
            font-size: 11px;
            font-weight: 800;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
        }

        .pc-currency-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .pc-currency-btn {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-align: center;
            transition: all 0.2s;
        }

        .pc-currency-btn.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        /* Top Summary Cards */
        .pc-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .pc-summary-grid { grid-template-columns: 1fr 1fr; }
        }

        .pc-summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
        }

        .pc-summary-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .pc-summary-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
            display: block;
        }

        .pc-summary-value {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
        }

        /* Big Impact Cards */
        .pc-impact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .pc-impact-card {
            border-radius: 16px;
            padding: 24px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .pc-impact-card::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .pc-impact-card.purple { background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%); }
        .pc-impact-card.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }

        .pc-impact-label {
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 12px;
            display: block;
        }

        .pc-impact-value {
            font-size: 32px;
            font-weight: 900;
        }

        /* Table Section */
        .pc-section-header {
            margin-bottom: 20px;
        }

        .pc-section-title {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
        }

        .pc-section-subtitle {
            font-size: 12px;
            color: #94a3b8;
        }

        .pc-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .pc-table thead th {
            background: #1e293b;
            color: #fff;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pc-table thead th:first-child { border-top-left-radius: 12px; }
        .pc-table thead th:last-child { border-top-right-radius: 12px; }

        .pc-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        /* Status Pills */
        .pc-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .pc-status.income { background: #dcfce7; color: #166534; }
        .pc-status.expense { background: #fee2e2; color: #991b1b; }
        .pc-status.paid { background: #e0f2fe; color: #075985; }
        .pc-status.debt { background: #ffedd5; color: #9a3412; }

        /* Action Buttons */
        .pc-actions-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .pc-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .pc-btn-secondary { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .pc-btn-primary { background: #059669; color: #fff; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.2); }

        .pc-eye-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #3b82f6;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
        }

        .pc-eye-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 6px 10px -1px rgba(59, 130, 246, 0.3);
        }

        /* Modal Overrides */
        .close-modal-shell {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .close-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
        }

        .close-modal-card {
            position: relative;
            width: 1000px;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>

    <x-common.page-breadcrumb
        pageTitle="Caja chica | Cerrar caja"
        :crumbs="$breadcrumbs"
        iconHtml='<i class="ri-history-line"></i>'
    />

    @if (session('error'))
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div
        x-data="pettyCashClosePage()"
        x-effect="document.body.style.overflow = detailModalOpen ? 'hidden' : ''"
        class="mt-6"
    >
        <form action="{{ $storeUrl }}" method="POST">
            @csrf
            <input type="hidden" name="cash_register_id" value="{{ $cashRegisterId }}">
            <input type="hidden" name="shift_id" value="{{ $shiftId }}">
            <input type="hidden" name="real_closing_amount" :value="montoRealManual !== '' && montoRealManual !== null ? parseFloat(montoRealManual) : ''">
            <input type="hidden" name="closing_discrepancy" :value="montoRealManual !== '' && montoRealManual !== null ? descuadre : ''">

            <div class="pc-container">
                <!-- Sidebar (Left) -->
                <div class="pc-card p-6">
                    <div class="pc-sidebar-title">
                        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white">
                            <i class="ri-safe-2-line"></i>
                        </div>
                        <span>INFORMACIÓN DE CAJA</span>
                    </div>

                    <div class="space-y-4">
                        <div class="pc-sidebar-field">
                            <span class="pc-sidebar-label">Persona</span>
                            <div class="pc-sidebar-value">
                                <i class="ri-user-follow-line text-blue-500"></i>
                                {{ $personLabel }}
                            </div>
                        </div>

                        <div class="pc-sidebar-field">
                            <span class="pc-sidebar-label">Responsable</span>
                            <div class="pc-sidebar-value">
                                <i class="ri-user-star-line text-blue-500"></i>
                                {{ $responsibleLabel }}
                            </div>
                        </div>

                        <div class="pc-sidebar-field">
                            <span class="pc-sidebar-label">Turno</span>
                            <div class="pc-sidebar-value">
                                <i class="ri-history-line text-blue-500"></i>
                                {{ $shiftName }}
                            </div>
                        </div>

                        <div class="pc-sidebar-field">
                            <span class="pc-sidebar-label">Caja</span>
                            <div class="pc-sidebar-value">
                                <i class="ri-instance-line text-blue-500"></i>
                                {{ $cashRegister->number }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 space-y-3">
                        <div class="pc-tile gray">
                            <span class="pc-tile-label">Total según sistema</span>
                            <span class="pc-tile-value">S/ {{ $money($systemCash) }}</span>
                        </div>
                        <div class="pc-sidebar-field">
                            <span class="pc-sidebar-label">Monto real de cierre (manual)</span>
                            <input type="number"
                                step="0.01"
                                min="0"
                                x-model="montoRealManual"
                                placeholder="0.00"
                                class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-semibold text-slate-800 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                            >
                        </div>
                        <div class="pc-sidebar-field" x-show="String(montoRealManual || '').trim() !== ''">
                            <span class="pc-sidebar-label">Descuadre</span>
                            <div class="mt-1 flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"
                                :class="descuadre > 0 ? 'border-rose-200 bg-rose-50 text-rose-700' : (descuadre < 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600')">
                                <span x-text="'S/ ' + formatMoney(Math.abs(descuadre))"></span>
                                <span x-text="descuadre > 0 ? '(Faltante)' : (descuadre < 0 ? '(Sobrante)' : '(Cuadrado)')"></span>
                            </div>
                        </div>
                        <div class="pc-tile green">
                            <span class="pc-tile-label">Total Contado (billetes/monedas)</span>
                            <span class="pc-tile-value" x-text="'S/ ' + formatMoney(realCashTotal)"></span>
                        </div>
                    </div>

                    <div class="pc-currency-box">
                        <span class="pc-currency-label text-[10px]">TIPO DE MONEDA</span>
                        <div class="pc-currency-btns">
                            <button type="button" class="pc-currency-btn">Dólar $</button>
                            <button type="button" class="pc-currency-btn active">Soles S/</button>
                        </div>
                    </div>
                </div>

                <!-- Main Content (Right) -->
                <div class="space-y-6">
                    <!-- Top Summary Grid -->
                    <div class="pc-summary-grid">
                        <div class="pc-summary-card">
                            <div class="pc-summary-icon bg-emerald-50 text-emerald-600">
                                <i class="ri-arrow-right-up-line"></i>
                            </div>
                            <span class="pc-summary-label">Ventas en efectivo</span>
                            <span class="pc-summary-value">S/ {{ $money($cashSales) }}</span>
                        </div>
                        <div class="pc-summary-card">
                            <div class="pc-summary-icon bg-blue-50 text-blue-600">
                                <i class="ri-money-dollar-circle-line"></i>
                            </div>
                            <span class="pc-summary-label">Apertura en efectivo</span>
                            <span class="pc-summary-value">S/ {{ $money($openingCash) }}</span>
                        </div>
                        <div class="pc-summary-card">
                            <div class="pc-summary-icon bg-cyan-50 text-cyan-600">
                                <i class="ri-line-chart-line"></i>
                            </div>
                            <span class="pc-summary-label">Ingresos en efectivo</span>
                            <span class="pc-summary-value">S/ {{ $money($otherCashIncome) }}</span>
                        </div>
                        <div class="pc-summary-card">
                            <div class="pc-summary-icon bg-rose-50 text-rose-600">
                                <i class="ri-arrow-right-down-line"></i>
                            </div>
                            <span class="pc-summary-label">Egresos en efectivo</span>
                            <span class="pc-summary-value">S/ {{ $money($cashExpenses) }}</span>
                        </div>
                    </div>

                    <!-- Impact Cards -->
                    <div class="pc-impact-grid">
                        <div class="pc-impact-card purple">
                            <span class="pc-impact-label">Total en caja</span>
                            <span class="pc-impact-value">S/ {{ $money($cashWithoutOpening + $openingCash) }}</span>
                        </div>
                        <div class="pc-impact-card green">
                            <span class="pc-impact-label">Total en caja cierre</span>
                            <span class="pc-impact-value">S/ {{ $money($systemCash) }}</span>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div class="pc-card overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                            <div class="pc-section-header mb-0">
                                <h3 class="pc-section-title">Detalle de cierre</h3>
                                <p class="pc-section-subtitle">Registro de movimientos de caja</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="pc-table">
                                <thead>
                                    <tr>
                                        <th class="text-left">FLUJO</th>
                                        <th class="text-left">TIPO</th>
                                        <th class="text-left">MONTO</th>
                                        <th class="text-left">MEDIO</th>
                                        <th class="text-left">DETALLES</th>
                                        <th class="text-left">NOTAS</th>
                                        <th class="text-center">OPERACIONES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($detailGroups as $index => $group)
                                        @php
                                            $flowLabel = $group['flow_label'] ?? (($group['category'] ?? '') === 'expense' ? 'Egreso' : 'Ingreso');
                                            $isIncome = $flowLabel !== 'Egreso';
                                            $statusClass = $isIncome ? 'pc-status income' : 'pc-status expense';
                                            $icon = $isIncome ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line';
                                            $paymentTypeLabel = $group['type_label'] ?? 'Pagado';
                                            $paymentTypeClass = $paymentTypeLabel === 'Deuda' ? 'pc-status debt' : 'pc-status paid';
                                            
                                            $noteStyle = match ($group['category']) {
                                                'opening' => 'background:#fde68a;color:#78350f;',
                                                'sale' => 'background:#c084fc;color:#ffffff;',
                                                'income' => 'background:#38bdf8;color:#ffffff;',
                                                'expense' => 'background:#ef4444;color:#ffffff;',
                                                default => 'background:#e2e8f0;color:#1e293b;',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="{{ $statusClass }}">
                                                    <i class="{{ $icon }}"></i>
                                                    {{ $flowLabel }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="{{ $paymentTypeClass }}">
                                                    {{ $paymentTypeLabel }}
                                                </span>
                                            </td>
                                            <td class="font-bold text-slate-700">S/ {{ $money($group['amount']) }}</td>
                                            <td class="text-slate-500">{{ $group['method'] }}</td>
                                            <td class="text-slate-500">{{ $group['detail_label'] }}</td>
                                            <td>
                                                <span class="px-4 py-1.5 rounded-full text-[12px] font-black leading-none" style="{{ $noteStyle }}">
                                                    {{ $group['note'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" @click="openDetail({{ $index }})" class="pc-eye-btn">
                                                    <i class="ri-eye-line text-sm"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center text-slate-400 font-medium">
                                                No hay movimientos acumulados para este cierre.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Table Footer Totals -->
                        <div class="p-6 bg-slate-50/50 flex flex-wrap justify-between gap-8 border-t border-slate-100">
                            <div class="text-center">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">TOTAL DE VENTAS</p>
                                <p class="text-lg font-black text-emerald-600">S/ {{ $money($totalSales) }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">TOTAL DE INGRESOS</p>
                                <p class="text-lg font-black text-blue-600">S/ {{ $money($totalOtherIncome) }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">TOTAL DE EGRESOS</p>
                                <p class="text-lg font-black text-rose-600">S/ {{ $money($totalExpenses) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Money Counting Inputs (Preserved for backend if necessary) -->
                    <div class="hidden">
                        <template x-for="(row, index) in coins" :key="row.key">
                            <div>
                                <input type="hidden" :name="`counting[coins][${index}][quantity]`" x-model.number="row.quantity">
                                <input type="hidden" :name="`counting[coins][${index}][key]`" :value="row.key">
                                <input type="hidden" :name="`counting[coins][${index}][label]`" :value="row.label">
                                <input type="hidden" :name="`counting[coins][${index}][value]`" :value="row.value">
                            </div>
                        </template>
                        <template x-for="(row, index) in bills" :key="row.key">
                            <div>
                                <input type="hidden" :name="`counting[bills][${index}][quantity]`" x-model.number="row.quantity">
                                <input type="hidden" :name="`counting[bills][${index}][key]`" :value="row.key">
                                <input type="hidden" :name="`counting[bills][${index}][label]`" :value="row.label">
                                <input type="hidden" :name="`counting[bills][${index}][value]`" :value="row.value">
                            </div>
                        </template>
                    </div>

                    <!-- Actions Bar -->
                    <div class="pc-actions-bar">
                        <a href="{{ $backUrl }}" class="pc-btn pc-btn-secondary">
                            <i class="ri-close-line"></i> Cancelar
                        </a>
                        <button type="submit" class="pc-btn pc-btn-primary">
                            <i class="ri-save-line"></i> Guardar cierre
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Detail Modal -->
        <template x-teleport="body">
            <div x-show="detailModalOpen" x-cloak class="close-modal-shell">
            <div class="close-modal-backdrop" @click="closeDetail()"></div>

            <div class="close-modal-card" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-black text-slate-800" x-text="selectedGroup ? selectedGroup.modal_title : 'Detalle de movimientos'"></h3>
                        <p class="text-xs text-slate-400 font-medium">Desglose completo del grupo de movimientos seleccionado</p>
                    </div>
                    <button @click="closeDetail()" class="w-10 h-10 rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all flex items-center justify-center">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="p-0 max-h-[70vh] overflow-auto rounded-b-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">N°</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Flujo</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Tipo</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Concepto</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Total Mov.</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Fecha</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Usuario</th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-xs uppercase tracking-wider font-bold">Persona</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="record in (selectedGroup ? selectedGroup.records : [])" :key="record.number + record.moved_at">
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-3 py-3 font-bold text-slate-900 text-sm" x-text="record.number || '-'"></td>
                                    <td class="px-3 py-3">
                                        <span :class="record.type_label === 'Ingreso' ? 'pc-status income' : 'pc-status expense'" x-text="record.type_label"></span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span :class="record.payment_type_label === 'Deuda' ? 'pc-status debt' : 'pc-status paid'" x-text="record.payment_type_label || 'Pagado'"></span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[10px] font-bold uppercase whitespace-nowrap" x-text="record.concept"></span>
                                    </td>
                                    <td class="px-3 py-3 font-black text-slate-800 text-sm whitespace-nowrap" x-text="'S/ ' + formatMoney(record.movement_total)"></td>
                                    <td class="px-3 py-3 text-xs text-slate-500 whitespace-nowrap" x-text="record.moved_at"></td>
                                    <td class="px-3 py-3 text-xs text-slate-500" x-text="record.user_name || '-'"></td>
                                    <td class="px-3 py-3 text-xs text-slate-500" x-text="record.person_name || '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 bg-slate-50 flex justify-end">
                    <button type="button" class="pc-btn pc-btn-secondary" @click="closeDetail()">
                        Cerrar Detalle
                    </button>
                </div>
            </div>
        </div>
        </template>
    </div>

    <script>
        function pettyCashClosePage() {
            return {
                coins: {{ Js::from($coins) }},
                bills: {{ Js::from($bills) }},
                detailGroups: {{ Js::from($detailGroups) }},
                detailModalOpen: false,
                selectedGroup: null,
                systemCash: {{ Js::from((float) ($systemCash ?? 0)) }},
                montoRealManual: '',
                get realCashTotal() {
                    return [...this.coins, ...this.bills].reduce((total, row) => {
                        const quantity = Number(row.quantity || 0);
                        const value = Number(row.value || 0);
                        return total + (quantity * value);
                    }, 0);
                },
                get descuadre() {
                    const real = parseFloat(this.montoRealManual) || 0;
                    return Math.round((this.systemCash - real) * 100) / 100;
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
