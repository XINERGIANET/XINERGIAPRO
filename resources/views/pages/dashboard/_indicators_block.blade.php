@php
    $ic = $dashboardData['indicatorCharts'] ?? [];
    $hasIndicators = is_array($ic) && empty($ic['empty']);
    $techRows = collect($dashboardData['techProductivity'] ?? []);
@endphp

@if ($hasIndicators)
    <section class="mb-8 print:hidden" x-data="{ tab: 'sales' }">
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50/90 px-4 py-4 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Indicadores</p>
                        <h2 class="text-xl font-black text-slate-900">Decisiones empresariales</h2>
                        <p class="mt-1 text-xs text-slate-500">Ventas, clientes, gastos y cotizaciones · Rango filtro: {{ $ic['range_label'] ?? '' }}</p>
                    </div>
                    <nav class="flex flex-wrap gap-2" role="tablist">
                        <button type="button" role="tab"
                            @click="tab = 'sales'"
                            :class="tab === 'sales' ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-600 border border-slate-200'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition">Facturación</button>
                        <button type="button" role="tab"
                            @click="tab = 'clients'; $nextTick(() => window.__drawIndicatorTab && window.__drawIndicatorTab('clients'))"
                            :class="tab === 'clients' ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-600 border border-slate-200'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition">Clientes</button>
                        <button type="button" role="tab"
                            @click="tab = 'trend'; $nextTick(() => window.__drawIndicatorTab && window.__drawIndicatorTab('trend'))"
                            :class="tab === 'trend' ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-600 border border-slate-200'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition">Tendencia</button>
                        <button type="button" role="tab"
                            @click="tab = 'expense'; $nextTick(() => window.__drawIndicatorTab && window.__drawIndicatorTab('expense'))"
                            :class="tab === 'expense' ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-600 border border-slate-200'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition">Gastos</button>
                        <button type="button" role="tab"
                            @click="tab = 'ops'; $nextTick(() => window.__drawIndicatorTab && window.__drawIndicatorTab('ops'))"
                            :class="tab === 'ops' ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-600 border border-slate-200'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition">Productividad</button>
                    </nav>
                </div>
            </div>

            <div class="p-4 sm:p-6 space-y-6">
                <!-- Tab: Ventas / facturacion -->
                <div x-show="tab === 'sales'" x-cloak class="space-y-6">
                    <p class="text-sm text-slate-600">Mes a mes: <strong>Facturado SUNAT</strong> frente a ventas <strong>sin factura electronica</strong> (pending u otros estados), y el total.</p>
                    <div class="h-[380px] w-full min-w-0" id="indicator-chart-monthly-sales"></div>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Año</th>
                                    <th class="px-4 py-3">Mes</th>
                                    <th class="px-4 py-3 text-right">Mont. fact.</th>
                                    <th class="px-4 py-3 text-right">No fact.</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach (($ic['month_rows'] ?? []) as $row)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-4 py-2 font-semibold text-slate-800">{{ $row['year'] }}</td>
                                        <td class="px-4 py-2 text-slate-700">{{ $row['month_label'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono">S/ {{ number_format($row['fact'], 2) }}</td>
                                        <td class="px-4 py-2 text-right font-mono">S/ {{ number_format($row['nofact'], 2) }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-black text-slate-900">S/ {{ number_format($row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Clientes -->
                <div x-show="tab === 'clients'" x-cloak class="space-y-4">
                    <p class="text-sm text-slate-600">Top clientes por monto de venta en el periodo filtrado.</p>
                    <div class="h-[400px] w-full min-w-0" id="indicator-chart-clients"></div>
                </div>

                <!-- Tab: Tendencia -->
                <div x-show="tab === 'trend'" x-cloak class="space-y-6">
                    <div>
                        <h3 class="text-base font-black uppercase tracking-wide text-slate-900">Tendencia crecimiento lineal</h3>
                        <p class="mt-1 text-xs text-slate-500">Ventas reales (T. VENTAS) por mes; desde el mes 10 se suma el escenario de nuevos clientes (Motocorp + Lifan/Hero) y la proyeccion lineal total. Montos en Soles.</p>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="min-w-[860px] w-full text-left text-xs">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-3 py-3 text-center w-12">Mes</th>
                                    <th class="px-3 py-3 min-w-[140px]">Mes (nombre)</th>
                                    <th class="px-3 py-3 text-right">T. VENTAS</th>
                                    <th class="px-3 py-3 text-right">Nuevo cliente Motocorp</th>
                                    <th class="px-3 py-3 text-right">Nuevo cliente (Lifan, Hero Senda)</th>
                                    <th class="px-3 py-3 text-right">Proy. lineal total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach (($ic['linear_growth_rows'] ?? []) as $lg)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-3 py-2 text-center font-bold text-slate-800">{{ $lg['mes_no'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-slate-700 capitalize">{{ $lg['mes_title'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-right font-mono">S/ {{ number_format((float) ($lg['tv_ventas'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700">
                                            @if (($lg['nuevo_cliente_motocorp'] ?? null) === null)
                                                —
                                            @else
                                                S/ {{ number_format((float) $lg['nuevo_cliente_motocorp'], 2) }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700">
                                            @if (($lg['nuevo_cliente_lifan'] ?? null) === null)
                                                —
                                            @else
                                                S/ {{ number_format((float) $lg['nuevo_cliente_lifan'], 2) }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-slate-900">
                                            @if (($lg['proy_lineal_total'] ?? null) === null)
                                                —
                                            @else
                                                S/ {{ number_format((float) $lg['proy_lineal_total'], 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">T. VENTAS</p>
                        <div class="h-[400px] w-full min-w-0" id="indicator-chart-trend"></div>
                    </div>
                </div>

                <!-- Tab: Gastos -->
                <div x-show="tab === 'expense'" x-cloak class="space-y-6">
                    <p class="text-sm text-slate-600">Egresos de caja por concepto (periodo). Proyectado estimado a partir del promedio de los ultimos meses.</p>
                    <div class="h-[420px] w-full min-w-0" id="indicator-chart-expense"></div>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Concepto</th>
                                    <th class="px-4 py-3 text-right">Proyectado</th>
                                    <th class="px-4 py-3 text-right">Real</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach (($ic['expense_rows'] ?? []) as $er)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-4 py-2 font-semibold text-slate-800">{{ $er['label'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono">S/ {{ number_format($er['projected'], 2) }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-bold text-slate-900">S/ {{ number_format($er['real'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Productividad + productos + cotizaciones -->
                <div x-show="tab === 'ops'" x-cloak class="space-y-8">
                    @if ($techRows->isNotEmpty())
                        <div>
                            <h4 class="text-sm font-black text-slate-800 mb-2">Ordenes cerradas por tecnico (periodo filtro)</h4>
                            <div class="h-[320px] w-full min-w-0" id="indicator-chart-tech"></div>
                        </div>
                    @endif

                    <div>
                        <h4 class="text-sm font-black text-slate-800 mb-2">Top repuestos / productos vendidos (monto)</h4>
                        <div class="h-[360px] w-full min-w-0" id="indicator-chart-products"></div>
                        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
                            <table class="min-w-full text-left text-xs">
                                <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Producto</th>
                                        <th class="px-3 py-2 text-right">Cant.</th>
                                        <th class="px-3 py-2 text-right">Importe</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach (($ic['product_qty_top'] ?? []) as $pr)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-800">{{ $pr['name'] }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ number_format($pr['qty'], 2) }}</td>
                                            <td class="px-3 py-2 text-right font-mono font-bold">S/ {{ number_format($pr['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-[10px] font-black uppercase text-slate-400">Cotiz. externas (periodo)</p>
                            <p class="text-2xl font-black text-slate-900">{{ (int) ($ic['quotes_stats']['total'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-sm">
                            <p class="text-[10px] font-black uppercase text-emerald-700">Aprobadas</p>
                            <p class="text-2xl font-black text-emerald-900">{{ (int) ($ic['quotes_stats']['approved'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                            <p class="text-[10px] font-black uppercase text-amber-800">En espera aprobacion</p>
                            <p class="text-2xl font-black text-amber-950">{{ (int) ($ic['quotes_stats']['awaiting'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
                            <p class="text-[10px] font-black uppercase text-indigo-800">Convertidas</p>
                            <p class="text-2xl font-black text-indigo-950">{{ (int) ($ic['quotes_stats']['converted'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
    (function () {
        const payload = @json($ic ?? []);
        const techProd = @json($techRows->values()->take(12)->values() ?? []);

        const chartStore = {};

        function apexCtor() {
            return typeof window !== 'undefined' ? window.ApexCharts : null;
        }

        function bootWhenApex(callback) {
            var tries = 0;
            function tick() {
                var Ctor = apexCtor();
                if (Ctor) {
                    callback();
                    return;
                }
                tries += 1;
                if (tries < 120) {
                    window.setTimeout(tick, 25);
                }
            }
            tick();
        }

        function baseOpts() {
            return {
                chart: {
                    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
                    toolbar: { show: true },
                    zoom: { enabled: false },
                },
                grid: {
                    strokeDashArray: 4,
                    borderColor: '#e2e8f0',
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontWeight: 600,
                    labels: { colors: '#475569' },
                },
                dataLabels: { enabled: false },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function (val) {
                            try {
                                return 'S/ ' + Number(val || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            } catch (_) {
                                return String(val ?? '');
                            }
                        },
                    },
                },
            };
        }

        function mountMonthlySales() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-monthly-sales');
            if (!el || chartStore.monthly) return;
            const categories = payload.month_labels || [];
            chartStore.monthly = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: { type: 'bar', height: 380, stacked: false },
                series: [
                    { name: 'Mont. fact. (INVOICED)', data: payload.series_monthly_fact || [] },
                    { name: 'No fact. (otros estados)', data: payload.series_monthly_nofact || [] },
                    { name: 'Total', data: payload.series_monthly_total || [] },
                ],
                xaxis: { categories, labels: { rotate: -35, rotateAlways: categories.length > 8 } },
                colors: ['#2563eb', '#ea580c', '#22c55e'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '62%' } },
                yaxis: { labels: { formatter: function (val) { return 'S/' + Number(val).toLocaleString('es-PE', { maximumFractionDigits: 0 }); } } },
            }));
            chartStore.monthly.render();
        }

        function mountClients() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-clients');
            if (!el || chartStore.clients) return;
            const labels = payload.clients_labels || [];
            chartStore.clients = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: { type: 'bar', height: 400 },
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                series: [{ name: 'Ventas S/', data: payload.clients_data || [] }],
                colors: ['#0f766e'],
                xaxis: { categories: labels, labels: { trim: false, maxHeight: 120 } },
            }));
            chartStore.clients.render();
        }

        function mountTrend() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-trend');
            if (!el || chartStore.trend) return;
            const lbl = payload.linear_growth_chart_categories || [];
            const tot = payload.linear_growth_tv_series || [];
            if (!lbl.length || !tot.length) return;
            chartStore.trend = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: Object.assign({}, baseOpts().chart || {}, { type: 'line', height: 400, zoom: { enabled: false } }),
                stroke: { width: 3, curve: 'smooth' },
                series: [{ name: 'T. VENTAS', data: tot }],
                colors: ['#2563eb'],
                markers: { size: 4, strokeWidth: 2, hover: { size: 6 } },
                xaxis: {
                    categories: lbl,
                    labels: { rotate: -45, rotateAlways: lbl.length > 8, maxHeight: 120, trim: false },
                },
                yaxis: {
                    labels: {
                        formatter: function (val) {
                            try {
                                return 'S/' + Number(val).toLocaleString('es-PE', { maximumFractionDigits: 0 });
                            } catch (_) {
                                return val;
                            }
                        },
                    },
                },
            }));
            chartStore.trend.render();
        }

        function mountExpense() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-expense');
            if (!el || chartStore.expense) return;
            const labels = payload.expense_labels || [];
            chartStore.expense = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: { type: 'bar', height: 420 },
                series: [
                    { name: 'Proyectado (referencia)', data: payload.expense_projected || [] },
                    { name: 'Real (periodo)', data: payload.expense_real || [] },
                ],
                xaxis: { categories: labels, labels: { rotate: -38, rotateAlways: labels.length > 10 } },
                colors: ['#94a3b8', '#ea580c'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '58%' } },
            }));
            chartStore.expense.render();
        }

        function mountTech() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-tech');
            if (!el || chartStore.tech) return;
            if (!techProd || !techProd.length) return;
            const names = techProd.map(function (r) {
                var t = (r && r.technician) ? String(r.technician) : '';
                return t.length > 22 ? (t.slice(0, 21) + '…') : t;
            });
            chartStore.tech = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: { type: 'bar', height: 320 },
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                series: [{
                    name: 'Ordenes',
                    data: techProd.map(function (r) { return Number(r && r.orders !== undefined ? r.orders : r.orders_qty || 0); }),
                }],
                colors: ['#0369a1'],
                xaxis: { categories: names },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            try {
                                return Number(val || 0).toLocaleString('es-PE');
                            } catch (_) {
                                return val;
                            }
                        },
                    },
                },
            }));
            chartStore.tech.render();
        }

        function mountProducts() {
            var ApexCharts = apexCtor();
            if (!ApexCharts) return;
            const el = document.querySelector('#indicator-chart-products');
            if (!el || chartStore.products) return;
            chartStore.products = new ApexCharts(el, Object.assign({}, baseOpts(), {
                chart: { type: 'bar', height: 360 },
                plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '52%' } },
                series: [{ name: 'Importe vendido', data: payload.product_amounts || [] }],
                colors: ['#c2410c'],
                xaxis: { categories: payload.product_labels || [], labels: { rotate: -42, rotateAlways: true } },
            }));
            chartStore.products.render();
        }

        window.__drawIndicatorTab = function (slug) {
            bootWhenApex(function () {
                if (slug === 'clients') mountClients();
                if (slug === 'trend') mountTrend();
                if (slug === 'expense') mountExpense();
                if (slug === 'ops') {
                    mountTech();
                    mountProducts();
                }
                window.setTimeout(function () {
                    var keys = ['monthly', 'clients', 'trend', 'expense', 'tech', 'products'];
                    keys.forEach(function (k) {
                        var ch = chartStore[k];
                        if (ch && typeof ch.resize === 'function') ch.resize();
                    });
                }, 120);
            });
        };

        function bootFirstSeries() {
            bootWhenApex(function () {
                mountMonthlySales();
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () {
                        if (chartStore.monthly && typeof chartStore.monthly.resize === 'function') {
                            chartStore.monthly.resize();
                        }
                        window.setTimeout(function () {
                            if (chartStore.monthly && typeof chartStore.monthly.resize === 'function') {
                                chartStore.monthly.resize();
                            }
                        }, 160);
                    });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', bootFirstSeries);
        document.addEventListener('turbo:load', bootFirstSeries);
        if (document.readyState !== 'loading') {
            bootFirstSeries();
        }
    })();
    </script>
    @endpush
@endif
