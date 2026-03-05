@extends('layouts.app')
@section('meta')
    <meta name="turbo-cache-control" content="no-cache">
@endsection

@section('content')
    @php
        $d = $dashboardData ?? [];
        $incomeByDay = collect($d['incomeByDay'] ?? []);
        $maxIncome = (float) max(1, (float) $incomeByDay->max('amount'));
        $statusBreakdown = collect($d['statusBreakdown'] ?? []);
        $totalStatus = (int) $statusBreakdown->sum();
        $recentOrders = collect($d['recentOrders'] ?? []);
        $topServices = collect($d['topServices'] ?? []);
    @endphp

    <x-common.page-breadcrumb pageTitle="Dashboard Taller" />

    <div class="space-y-6" id="workshop-dashboard">
     

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="wk-kpi-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Órdenes Activas</p>
                <p class="mt-2 text-4xl font-black text-slate-900">{{ number_format((int) ($d['ordersActive'] ?? 0)) }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">En flujo de atención</p>
            </article>
            <article class="wk-kpi-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Cerradas Hoy</p>
                <p class="mt-2 text-4xl font-black text-emerald-600">{{ number_format((int) ($d['ordersClosedToday'] ?? 0)) }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">Finalizadas/entregadas</p>
            </article>
            <article class="wk-kpi-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Pendiente Aprobación</p>
                <p class="mt-2 text-4xl font-black text-amber-600">{{ number_format((int) ($d['ordersPendingApproval'] ?? 0)) }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">Esperando confirmación</p>
            </article>
            <article class="wk-kpi-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Vehículos En Taller</p>
                <p class="mt-2 text-4xl font-black text-slate-900">{{ number_format((int) ($d['vehiclesInWorkshop'] ?? 0)) }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">Actualmente atendidos</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-8">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Ingresos Taller</p>
                        <h3 class="mt-1 text-lg font-black text-slate-900">Últimos 7 días</h3>
                    </div>
                    <span class="rounded-xl bg-slate-100 px-3 py-1 text-sm font-bold text-slate-600">
                        S/ {{ number_format((float) ($incomeByDay->sum('amount')), 2) }}
                    </span>
                </div>
                @if ($incomeByDay->sum('amount') <= 0)
                    <div class="mb-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-500">
                        Aún no hay ingresos registrados en esta ventana de 7 días.
                    </div>
                @endif
                <div class="wk-income-grid">
                    @foreach ($incomeByDay as $row)
                        @php
                            $amount = (float) $row['amount'];
                            $height = max(4, (int) round(($amount / $maxIncome) * 150));
                            $isZero = $amount <= 0;
                        @endphp
                        <div class="wk-income-day">
                            <div class="wk-income-track">
                                <div class="wk-income-bar {{ $isZero ? 'is-zero' : '' }}" style="height: {{ $height }}px;"></div>
                            </div>
                            <p class="text-[11px] font-bold text-slate-500">{{ $row['label'] }}</p>
                            <p class="text-xs font-black {{ $isZero ? 'text-slate-400' : 'text-slate-700' }}">S/{{ number_format($amount, 0) }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-4">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Balance rápido</p>
                <h3 class="mt-1 text-lg font-black text-slate-900">Caja y Citas</h3>
                <div class="mt-5 space-y-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-600">Facturado hoy</p>
                        <p class="mt-1 text-3xl font-black text-emerald-700">S/ {{ number_format((float) ($d['todayInvoiced'] ?? 0), 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-600">Por cobrar</p>
                        <p class="mt-1 text-3xl font-black text-amber-700">S/ {{ number_format((float) ($d['pendingCollection'] ?? 0), 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Citas de hoy</p>
                        <p class="mt-1 text-3xl font-black text-slate-800">{{ number_format((int) ($d['appointmentsToday'] ?? 0)) }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-5">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Distribución</p>
                <h3 class="mt-1 text-lg font-black text-slate-900">Estado de órdenes</h3>
                <div class="mt-5 space-y-3">
                    @forelse ($statusBreakdown as $status => $qty)
                        @php
                            $pct = $totalStatus > 0 ? round(((int) $qty * 100) / $totalStatus, 1) : 0;
                            $label = str_replace('_', ' ', strtoupper((string) $status));
                        @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm font-semibold text-slate-700">
                                <span>{{ $label }}</span>
                                <span>{{ $qty }} ({{ $pct }}%)</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100">
                                <div class="h-2.5 rounded-full" style="width: {{ $pct }}%; background: linear-gradient(90deg,#fb923c,#ea580c);"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">Sin datos de estados aún.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-7">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Productividad</p>
                <h3 class="mt-1 text-lg font-black text-slate-900">Top servicios del mes</h3>
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-3 py-2">Servicio</th>
                                <th class="px-3 py-2 text-right">Atenciones</th>
                                <th class="px-3 py-2 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topServices as $service)
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-3 text-sm font-bold text-slate-800">{{ $service->name }}</td>
                                    <td class="px-3 py-3 text-right text-sm font-semibold text-slate-700">{{ number_format((int) $service->qty) }}</td>
                                    <td class="px-3 py-3 text-right text-sm font-black text-orange-600">S/ {{ number_format((float) $service->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-10 text-center text-sm text-slate-500">Aún no hay servicios registrados este mes.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Seguimiento</p>
            <h3 class="mt-1 text-lg font-black text-slate-900">Órdenes recientes</h3>
            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[860px] text-left">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-3 py-2">Orden</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Vehículo</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-right">Pagado</th>
                            <th class="px-3 py-2 text-right">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $row)
                            @php
                                $client = trim(($row->client->first_name ?? '') . ' ' . ($row->client->last_name ?? ''));
                                if ($client === '') {
                                    $client = $row->client->document_number ?? 'Sin cliente';
                                }
                                $vehicle = trim(($row->vehicle->brand ?? '') . ' ' . ($row->vehicle->model ?? ''));
                                if (($row->vehicle->plate ?? '') !== '') {
                                    $vehicle .= ' - ' . $row->vehicle->plate;
                                }
                                $pending = max(0, (float) $row->total - (float) $row->paid_total);
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-3 text-sm font-bold text-slate-800">{{ $row->movement->number ?? ('OS-' . str_pad((string) $row->id, 8, '0', STR_PAD_LEFT)) }}</td>
                                <td class="px-3 py-3 text-sm font-semibold text-slate-700">{{ $client }}</td>
                                <td class="px-3 py-3 text-sm font-semibold text-slate-700">{{ $vehicle ?: 'Sin vehículo' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ strtoupper(str_replace('_', ' ', (string) $row->status)) }}</span>
                                </td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-700">S/ {{ number_format((float) $row->total, 2) }}</td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-emerald-700">S/ {{ number_format((float) $row->paid_total, 2) }}</td>
                                <td class="px-3 py-3 text-right text-sm font-black text-amber-600">S/ {{ number_format($pending, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-12 text-center text-sm text-slate-500">No hay órdenes de taller registradas para esta sucursal.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <style>
        #workshop-dashboard {
            --panel-bg: #ffffff;
        }

        #workshop-dashboard .wk-kpi-card {
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }

        #workshop-dashboard .wk-kpi-card:hover {
            transform: translateY(-2px);
            border-color: #fed7aa;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        }

        #workshop-dashboard .wk-income-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 12px;
        }

        #workshop-dashboard .wk-income-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        #workshop-dashboard .wk-income-track {
            position: relative;
            width: 100%;
            height: 190px;
            border-radius: 18px;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #e5e7eb;
            padding: 8px;
            display: flex;
            align-items: flex-end;
        }

        #workshop-dashboard .wk-income-bar {
            width: 100%;
            border-radius: 12px;
            background: linear-gradient(180deg, #fb923c 0%, #ea580c 100%);
            box-shadow: 0 10px 20px rgba(234, 88, 12, 0.25);
            transition: all .2s ease;
        }

        #workshop-dashboard .wk-income-bar.is-zero {
            opacity: .28;
            box-shadow: none;
        }

        @media (max-width: 1280px) {
            #workshop-dashboard .wk-income-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            #workshop-dashboard .wk-income-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
@endsection
