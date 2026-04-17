@extends('layouts.app')
@section('meta')
    <meta name="turbo-cache-control" content="no-cache">
@endsection


@section('content')
    <style>
        .birthday-hover-row {
            transition: all 0.3s ease-in-out;
            cursor: pointer;
        }
        .birthday-hover-row:hover {
            background-color: #fff7ed !important;
            border-color: #fed7aa !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(251, 146, 60, 0.08);
        }
        .client-hover-row {
            transition: all 0.3s ease-in-out;
            cursor: pointer;
        }
        .client-hover-row:hover {
            background-color: #e0e7ff !important;
            border-color: #c7d2fe !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08);
        }
    </style>
    @php
        $d = $dashboardData ?? [];
        $incomeByDay = collect($d['incomeByDay'] ?? []);
        $maxIncome = (float) max(1, (float) $incomeByDay->max('amount'));
        $statusBreakdown = collect($d['statusBreakdown'] ?? []);
        $totalStatus = (int) $statusBreakdown->sum();
        $recentOrders = collect($d['recentOrders'] ?? []);
        $topServices = collect($d['topServices'] ?? []);
        $birthdays = collect($d['birthdays'] ?? []);
        $frequentClients = collect($d['frequentClients'] ?? []);
        $techProductivity = collect($d['techProductivity'] ?? []);
        $rangeStart = \Carbon\Carbon::parse($d['dateFrom'] ?? now()->toDateString());
        $rangeEnd = \Carbon\Carbon::parse($d['dateTo'] ?? now()->toDateString());
        $isSingleDay = $rangeStart->isSameDay($rangeEnd);
        $periodLabel = $isSingleDay ? $rangeStart->format('d/m/Y') : ($rangeStart->format('d/m/Y') . ' - ' . $rangeEnd->format('d/m/Y'));
        $chartLabel = $isSingleDay ? 'Dia seleccionado' : 'Rango seleccionado';

        // Cálculo de Crecimiento de Ingresos (Real)
        $todayIncome = (float) ($d['todayInvoiced'] ?? 0);
        $yesterdayIncome = $incomeByDay->count() >= 2 ? (float) $incomeByDay->reverse()->values()[1]['amount'] : 0;
        $incomeGrowth = $yesterdayIncome > 0 ? (($todayIncome - $yesterdayIncome) / $yesterdayIncome) * 100 : ($todayIncome > 0 ? 100 : 0);

        // Cálculo de Órdenes Nuevas Hoy (Real)
        $newOrdersToday = (int) ($d['newOrdersInRange'] ?? 0);

        $statusLabels = [
            'DRAFT' => 'BORRADOR',
            'DIAGNOSIS' => 'DIAGNÓSTICO',
            'AWAITING_APPROVAL' => 'ESPERANDO APROBACIÓN',
            'APPROVED' => 'APROBADO',
            'IN PROGRESS' => 'EN PROGRESO',
            'FINISHED' => 'FINALIZADO',
            'DELIVERED' => 'ENTREGADO',
            'CANCELLED' => 'CANCELADO',
        ];

        $isMonday = now()->isMonday();

        // --- CÁLCULO DE DATOS PARA EL GRÁFICO (CENTRALIZADO) ---
        $count = count($incomeByDay);
        $maxIncomeLocal = max(1, $incomeByDay->max('amount'));
        $yMax = ceil($maxIncomeLocal / 15) * 15;
        if ($yMax == 0) $yMax = 60;
        
        $ticks = [];
        for ($i = 4; $i >= 0; $i--) {
            $ticks[] = ($yMax / 4) * $i;
        }

        $points = [];
        $chartPoints = [];
        foreach($incomeByDay as $index => $row) {
            $x = ($index / (max(1, $count - 1))) * 1000;
            $y = 100 - (($row['amount'] / $yMax) * 100);
            $points[] = ['x' => $x, 'y' => $y];
            $chartPoints[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => (string) ($row['label'] ?? ''),
                'amount' => (float) ($row['amount'] ?? 0),
            ];
        }
        
        $path = "M " . $points[0]['x'] . " " . $points[0]['y'];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $curr = $points[$i];
            $next = $points[$i+1];
            $cp1x = $curr['x'] + ($next['x'] - $curr['x']) / 2;
            $path .= " C $cp1x " . $curr['y'] . ", $cp1x " . $next['y'] . ", " . $next['x'] . " " . $next['y'];
        }
        $areaPath = $path . " L 1000 100 L 0 100 Z";
        // -----------------------------------------------------
    @endphp

    <div class="dashboard-toolbar flex items-center justify-between mb-6 print:hidden">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Dashboard Taller</h1>
            <p class="text-xs text-slate-500 font-medium">Vista general de operaciones del taller</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="dashboard-filter-form flex flex-wrap items-center gap-3 print:hidden">
            @if (request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif
            <input type="date" name="date_from" value="{{ $d['dateFrom'] ?? now()->toDateString() }}" class="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm">
            <input type="date" name="date_to" value="{{ $d['dateTo'] ?? now()->toDateString() }}" class="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm">
            <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-slate-900 transition-all h-11">
                <i class="ri-search-line"></i> Filtrar
            </button>
            <a href="{{ route('dashboard', request('view_id') ? ['view_id' => request('view_id')] : []) }}" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-slate-50 transition-all h-11">
                <i class="ri-refresh-line"></i> Limpiar
            </a>
            <a href="{{ route('dashboard', array_filter(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString(), 'view_id' => request('view_id')])) }}" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-blue-700 transition-all h-11">
                <i class="ri-calendar-check-fill"></i> Hoy
            </a>
            <button type="button" onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-slate-50 transition-all h-11">
                <i class="ri-download-2-line"></i> Exportar
            </button>
        </form>
    </div>

    <div class="space-y-6 pb-10" id="workshop-dashboard">
        <!-- CABECERA FORMAL DE REPORTE (SOLO IMPRESIÓN) -->
        <header class="hidden print:flex flex-col gap-3 mb-6 pb-4 border-b-2 border-slate-900 w-full">
            <div class="flex flex-row justify-between items-start w-full">
                <div class="flex flex-col gap-2">
                    <img src="/images/logo/Xinergia.png" alt="Logo" class="h-12 w-auto mb-2 self-start" />
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight leading-none uppercase">Reporte Operativo de Taller</h1>
                    <div class="flex items-center gap-4 mt-1">
                        <p class="text-sm font-bold text-slate-500 uppercase tracking-widest border-r border-slate-300 pr-4">
                            Sede: <span class="text-slate-900">{{ $dashboardData['branchName'] ?? 'Principal' }}</span>
                        </p>
                        <p class="text-[10px] font-black text-white bg-slate-900 px-2 py-0.5 rounded uppercase tracking-tighter">
                            Documento Confidencial
                        </p>
                    </div>
                </div>
                <div class="text-right flex flex-col items-end gap-1">
                    <div class="px-4 py-1.5 bg-slate-100 border border-slate-200 text-slate-800 text-[10px] font-black rounded-lg uppercase tracking-widest mb-2 shadow-sm">
                        Resumen Diario
                    </div>
                    <p class="text-xs font-bold text-slate-600">Fecha: <span class="text-slate-900 font-black">{{ now()->format('d/m/Y') }}</span></p>
                    <p class="text-xs font-bold text-slate-600">Hora: <span class="text-slate-900 font-black">{{ now()->format('H:i') }}</span></p>
                    <p class="text-[10px] text-slate-400 mt-3 italic font-medium">Generado por: {{ auth()->user()->name ?? 'Administrador' }}</p>
                </div>
            </div>

            <!-- RESUMEN EJECUTIVO (KPIs) -->
            <div class="grid grid-cols-5 gap-6 mt-4 w-full">
                <div class="p-5 bg-white border-2 border-emerald-600 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-1">Ingresos Hoy</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">S/ {{ number_format($dashboardData['todayInvoiced'] ?? 0, 2) }}</p>
                </div>
                <div class="p-5 bg-white border-2 border-red-600 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-black text-red-600 uppercase tracking-widest mb-1">Egresos Hoy</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">S/ {{ number_format($dashboardData['expensesToday'] ?? 0, 2) }}</p>
                </div>
                <div class="p-5 bg-white border-2 border-blue-600 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-1">Órdenes Activas</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ number_format($dashboardData['ordersActive'] ?? 0) }}</p>
                </div>
                <div class="p-5 bg-white border-2 border-purple-600 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-black text-purple-600 uppercase tracking-widest mb-1">Servicios Semana</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ number_format($dashboardData['maintenancesWeek'] ?? 0) }}</p>
                </div>
                <div class="p-5 bg-white border-2 border-amber-500 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">Producción</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">S/ {{ number_format($dashboardData['productionAmount'] ?? 0, 2) }}</p>
                </div>
            </div>
        </header>
        
        <!-- KPI CARDS Y ANEXOS DETALLES (AHORA AL PRINCIPIO) -->
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6 mb-8 hero-kpi-grid print:grid-cols-5 print:gap-3 print:mb-4">
            <!-- Ingreso Hoy -->
            <article class="bg-white p-6 rounded-[1.5rem] border border-slate-100 transition-all hover:bg-slate-50/50">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-10 h-10 rounded-xl text-emerald-500 flex items-center justify-center bg-[#F4F6FA] border border-slate-100">
                        <i class="ri-line-chart-line text-xl"></i>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] font-black {{ $incomeGrowth >= 0 ? 'text-emerald-500' : 'text-red-500' }}">
                        <i class="{{ $incomeGrowth >= 0 ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line' }}"></i>
                        <span>{{ $incomeGrowth >= 0 ? '+' : '' }}{{ number_format($incomeGrowth, 1) }}%</span>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Ingreso periodo</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">S/ {{ number_format((float) ($d['todayInvoiced'] ?? 0), 2) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">{{ $periodLabel }}</p>
                </div>
            </article>

            <!-- Egreso Hoy -->
            <article class="bg-white p-6 rounded-[1.5rem] border border-slate-100 transition-all hover:bg-slate-50/50">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-10 h-10 rounded-xl text-red-600 flex items-center justify-center bg-[#F4F6FA] border border-slate-100">
                        <i class="ri-arrow-right-down-line text-xl"></i>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] font-black text-slate-400">
                        <i class="ri-subtract-line"></i>
                        <span>0.0%</span>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Egreso periodo</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">S/ {{ number_format((float) ($d['expensesToday'] ?? 0), 2) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">{{ $periodLabel }}</p>
                </div>
            </article>

            <!-- En Reparación -->
            <article class="bg-white p-6 rounded-[1.5rem] border border-slate-100 transition-all hover:bg-slate-50/50">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-10 h-10 rounded-xl text-blue-500 flex items-center justify-center bg-[#F4F6FA] border border-slate-100">
                        <i class="ri-tools-fill text-xl"></i>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] font-black text-blue-500">
                        <i class="ri-add-line"></i>
                        <span>+{{ $newOrdersToday }} en rango</span>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">En Reparación</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">{{ number_format((int) ($d['ordersActive'] ?? 0)) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">Órdenes abiertas</p>
                </div>
            </article>

            <!-- Mantenimientos -->
            <article class="bg-white p-6 rounded-[1.5rem] border border-slate-100 transition-all hover:bg-slate-50/50">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-10 h-10 rounded-xl text-purple-500 flex items-center justify-center bg-[#F4F6FA] border border-slate-100">
                        <i class="ri-calendar-event-fill text-xl"></i>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Mantenimientos</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">{{ number_format((int) ($d['maintenancesWeek'] ?? 0)) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">Esta semana</p>
                </div>
            </article>

            <!-- Produccion -->
            <article class="bg-white p-6 rounded-[1.5rem] border border-slate-100 transition-all hover:bg-slate-50/50">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-10 h-10 rounded-xl text-amber-500 flex items-center justify-center bg-[#FFF7ED] border border-amber-100">
                        <i class="ri-hammer-fill text-xl"></i>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] font-black text-amber-600">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>{{ number_format((int) ($d['ordersClosedToday'] ?? 0)) }} cerradas</span>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Produccion del periodo</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">S/ {{ number_format((float) ($d['productionAmount'] ?? 0), 2) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">{{ $periodLabel }}</p>
                </div>
            </article>
        </section>

        <!-- MAIN GRID: TRENDS & BIRTHDAYS -->
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12 mb-6 main-trend-grid print:!block">
            <article class="bg-white rounded-[1.5rem] border border-slate-100 p-4 sm:p-6 xl:col-span-8 flex flex-col relative overflow-hidden transition-all hover:shadow-md trend-chart-article"
                x-data="{
                    points: @js($chartPoints),
                    activeIndex: {{ max(0, count($chartPoints) - 1) }},
                    selectPoint(index) { this.activeIndex = index; },
                    get activePoint() { return this.points[this.activeIndex] ?? null; },
                    formatAmount(value) { return Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
                }">
                <!-- Header del gráfico -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5 sm:mb-6 relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-[#F4F6FA] border border-slate-100 flex items-center justify-center shadow-sm">
                            <i class="ri-line-chart-line text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 leading-tight">Tendencia de Ingresos</h3>
                            <p class="text-sm text-slate-500">{{ $chartLabel }}</p>
                        </div>
                    </div>
                    <div class="text-right bg-[#F4F6FA] px-4 py-3 rounded-xl border border-slate-100 w-full sm:w-auto">
                        <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-1">Total periodo</p>
                        <p class="text-2xl font-bold text-blue-700 leading-none">S/ {{ number_format((float) ($incomeByDay->sum('amount')), 2) }}</p>
                        <template x-if="activePoint">
                            <p class="mt-2 text-[11px] font-bold text-slate-500">
                                <span x-text="activePoint.label"></span>:
                                <span class="text-slate-700" x-text="'S/ ' + formatAmount(activePoint.amount)"></span>
                            </p>
                        </template>
                    </div>
                </div>
                <!-- Contenedor del Gráfico -->
                <div class="flex-1 flex flex-col min-h-[240px] sm:min-h-[300px]">
                    <div class="flex flex-1 items-stretch gap-2 sm:gap-4">

                        <!-- Eje Y (Labels) -->
                        <div class="hidden sm:flex flex-col justify-between text-[11px] text-slate-400 font-medium pr-2 mb-6 mt-1">
                            @foreach($ticks as $tick)
                                <span>{{ number_format($tick, 0) }}</span>
                            @endforeach
                        </div>

                        <!-- Área del Gráfico -->
                        <div class="relative flex-1 h-[220px] sm:h-[260px] mb-3 sm:mb-4">
                            <!-- Grid Lines -->
                            <div class="absolute inset-0 flex flex-col justify-between h-full pointer-events-none">
                                @foreach($ticks as $tick)
                                    <div class="w-full h-[1px] border-t border-dashed border-slate-100"></div>
                                @endforeach
                            </div>

                            <!-- SVG del Gráfico -->
                            <div class="absolute inset-0 h-full">
                                <svg viewBox="0 0 1000 100" preserveAspectRatio="none" class="w-full h-full overflow-visible">
                                    <defs>
                                        <linearGradient id="colorValueMain" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stop-color="#3b82f6" stop-opacity="0.4"/>
                                            <stop offset="95%" stop-color="#3b82f6" stop-opacity="0.05"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="{{ $areaPath }}" fill="url(#colorValueMain)" />
                                    <path d="{{ $path }}" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <template x-if="activePoint">
                                        <line :x1="activePoint.x" :x2="activePoint.x" y1="0" y2="100" stroke="#93c5fd" stroke-width="1" stroke-dasharray="3 3"></line>
                                    </template>
                                    <template x-for="(point, index) in points" :key="index">
                                        <circle
                                            :cx="point.x"
                                            :cy="point.y"
                                            :r="activeIndex === index ? 4.5 : 3"
                                            :fill="activeIndex === index ? '#1d4ed8' : '#2563eb'"
                                            stroke="white"
                                            stroke-width="2"
                                            class="cursor-pointer transition-all duration-150"
                                            @mouseenter="selectPoint(index)"
                                            @click="selectPoint(index)">
                                        </circle>
                                    </template>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Eje X (Dates) -->
                    <div class="border-t border-slate-50 pt-3 sm:pt-4">
                        <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1 sm:justify-between">
                            <template x-for="(point, index) in points" :key="'label-' + index">
                                <button type="button"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-black tracking-tight whitespace-nowrap border transition-all"
                                    :class="activeIndex === index
                                        ? 'bg-blue-50 text-blue-700 border-blue-200'
                                        : 'bg-white text-slate-400 border-transparent hover:border-slate-200 hover:text-slate-600'"
                                    @click="selectPoint(index)"
                                    x-text="point.label">
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </article>

            <!-- CUMPLEAÑOS -->
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 xl:col-span-4 h-full flex flex-col overflow-hidden print:!hidden">
                <div class="mb-8 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-600 text-white flex items-center justify-center shadow-lg shadow-orange-600/20">
                        <i class="ri-cake-2-fill text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 leading-tight">Cumpleaños</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Esta semana</p>
                    </div>
                </div>
                
                <div class="space-y-4 overflow-y-auto pr-1 custom-scrollbar flex-1">
                    @forelse($birthdays as $bday)
                        <div class="flex items-center justify-between p-4 rounded-2xl border border-transparent birthday-hover-row cursor-pointer transition-all group/item">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full text-white flex items-center justify-center font-black text-sm shadow-lg shadow-blue-500/20" style="background-color: #2563eb !important;">
                                    {{ substr($bday->first_name, 0, 1) }}{{ substr($bday->last_name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-black text-slate-800 uppercase text-sm leading-tight mb-1 whitespace-normal break-words">{{ $bday->first_name }} {{ $bday->last_name }}</h4>
                                    <p class="text-[10px] font-black text-slate-400 flex items-center gap-1 uppercase">
                                        <i class="ri-cake-2-line text-orange-400"></i>
                                        {{ \Carbon\Carbon::parse($bday->fecha_nacimiento)->translatedFormat('d \d\e M') }}
                                    </p>
                                </div>
                            </div>
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $bday->phone) }}" target="_blank" class="w-11 h-11 rounded-[1.2rem] text-white flex items-center justify-center hover:scale-110 transition-all shrink-0 shadow-lg shadow-emerald-500/20" style="background-color: #25D366 !important;">
                                <i class="ri-whatsapp-line text-xl"></i>
                            </a>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center py-10 opacity-30">
                            <i class="ri-cake-2-line text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs font-bold italic">Sin cumpleaños</p>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <!-- SECCIÓN DE DETALLES (SOLO PDF) - MOVEMOS ESTO AQUÍ PARA QUE APAREZCA JUNTO A LAS TARJETAS -->
        <div class="hidden print:block space-y-12">
            <!-- Detalle Ingresos -->
            @php $salesTodayDetails = $salesTodayDetails ?? collect([]) @endphp
            <section class="break-inside-avoid">
                <div class="mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-emerald-600 bg-emerald-50 flex items-center justify-center border border-emerald-100">
                        <i class="ri-wallet-3-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest leading-none mb-1">DETALLE OPERATIVO</p>
                        <h3 class="text-xl font-black text-slate-800 leading-none">Movimientos de Ingresos (Hoy)</h3>
                    </div>
                </div>
                <div class="rounded-2xl border-2 border-slate-200 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Hora</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Comprobante</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Cliente</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black uppercase text-slate-400">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($salesTodayDetails as $sale)
                                <tr>
                                    <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }}</td>
                                    <td class="px-6 py-4 text-xs font-black text-slate-900">{{ $sale->number }}</td>
                                    <td class="px-6 py-4 text-xs font-bold text-slate-700 uppercase">{{ $sale->client->first_name ?? '' }} {{ $sale->client->last_name ?? '' }}</td>
                                    <td class="px-6 py-4 text-right text-xs font-black text-slate-900">S/ {{ number_format($sale->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-xs text-slate-400 italic font-medium">Sin movimientos hoy</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-emerald-50/50">
                            <tr class="font-black text-emerald-700">
                                <td colspan="3" class="px-6 py-4 text-xs text-right uppercase tracking-widest">Total Ingresado:</td>
                                <td class="px-6 py-4 text-right text-sm">S/ {{ number_format($salesTodayDetails->sum('total'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- Detalle Gastos -->
            @php $expensesTodayDetails = $expensesTodayDetails ?? collect([]) @endphp
            <section class="break-inside-avoid">
                <div class="mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-red-600 bg-red-50 flex items-center justify-center border border-red-100">
                        <i class="ri-money-dollar-circle-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-red-600 uppercase tracking-widest leading-none mb-1">DETALLE OPERATIVO</p>
                        <h3 class="text-xl font-black text-slate-800 leading-none">Movimientos de Egresos (Hoy)</h3>
                    </div>
                </div>
                <div class="rounded-2xl border-2 border-slate-200 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Tipo</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Ref.</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Descripción / Proveedor</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black uppercase text-slate-400">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($expensesTodayDetails as $exp)
                                <tr>
                                    <td class="px-6 py-4 text-[10px] font-black uppercase"><span class="bg-gray-100 px-2 py-0.5 rounded">{{ $exp->type }}</span></td>
                                    <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ $exp->reference ?: '-' }}</td>
                                    <td class="px-6 py-4 text-xs font-bold text-slate-700 uppercase">{{ $exp->description ?: ($exp->provider->legal_name ?? 'S/P') }}</td>
                                    <td class="px-6 py-4 text-right text-xs font-black text-red-600">S/ {{ number_format($exp->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-10 text-center text-xs text-slate-400 italic font-medium">Sin egresos registrados hoy</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-red-50/50">
                            <tr class="font-black text-red-700">
                                <td colspan="3" class="px-6 py-4 text-xs text-right uppercase tracking-widest">Total Egresado:</td>
                                <td class="px-6 py-4 text-right text-sm">S/ {{ number_format($expensesTodayDetails->sum('total'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- Detalle Activas -->
            @php $activeOrdersDetails = $activeOrdersDetails ?? collect([]) @endphp
            <section class="break-inside-avoid">
                <div class="mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-blue-600 bg-blue-50 flex items-center justify-center border border-blue-100">
                        <i class="ri-folder-open-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest leading-none mb-1">DATOS DE TALLER</p>
                        <h3 class="text-xl font-black text-slate-800 leading-none">Órdenes Activas</h3>
                    </div>
                </div>
                <div class="rounded-2xl border-2 border-slate-200 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 text-center">Nº</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Cliente / Vehículo</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Estado</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black uppercase text-slate-400">Ingresó</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($activeOrdersDetails as $order)
                                <tr>
                                    <td class="px-6 py-4 text-xs font-black text-slate-900 text-center">{{ $order->movement->number ?? sprintf("%08d", $order->id) }}</td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs font-black text-slate-800 uppercase leading-none mb-1">{{ ($order->client->first_name ?? '') }} {{ ($order->client->last_name ?? '') }}</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ ($order->vehicle->plate ?? '') }} - {{ ($order->vehicle->brand ?? '') }} {{ ($order->vehicle->model ?? '') }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-100">
                                            {{ strtoupper($order->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-[11px] font-bold text-slate-500 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($order->created_at)->diffForHumans(now(), true) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-10 text-center text-xs text-slate-400 italic">No hay órdenes activas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Detalle Mant. Semana -->
            @php $maintenanceWeekDetails = $maintenanceWeekDetails ?? collect([]) @endphp
            <section class="break-inside-avoid">
                <div class="mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-purple-600 bg-purple-50 flex items-center justify-center border border-purple-100">
                        <i class="ri-service-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-purple-600 uppercase tracking-widest leading-none mb-1">DATOS DE TALLER</p>
                        <h3 class="text-xl font-black text-slate-800 leading-none">Mantenimientos (Semana)</h3>
                    </div>
                </div>
                <div class="rounded-2xl border-2 border-slate-200 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Fecha</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400">Placa / Cliente</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 text-center">Servicios</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black uppercase text-slate-400">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($maintenanceWeekDetails as $m)
                                <tr>
                                    <td class="px-6 py-4 text-[11px] font-bold text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($m->finished_at ?? $m->delivery_date)->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs font-black text-slate-900 uppercase tracking-tighter">{{ $m->vehicle->plate ?? '-' }}</p>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase truncate max-w-[150px]">{{ $m->client->first_name ?? '' }} {{ $m->client->last_name ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black">{{ $m->details_count ?? 0 }} ítems</span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs font-black text-slate-900">S/ {{ number_format($m->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-10 text-center text-xs text-slate-400 italic">Sin mantenimientos esta semana</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- SECCIÓN DE GRÁFICO YA FUE MOVIDA ARRIBA -->

        <!-- SECONDARY GRID: PRODUCTIVITY & FREQUENT CLIENTS -->
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2" 
                 x-data="{ 
                    showTechModal: false, 
                    loadingTech: false, 
                    techName: '', 
                    techServices: [],
                    dateFrom: @js($dashboardData['dateFrom'] ?? ''),
                    dateTo: @js($dashboardData['dateTo'] ?? ''),
                    openTechDetail(id, name) {
                        this.techName = name;
                        this.showTechModal = true;
                        this.loadingTech = true;
                        this.techServices = [];
                        
                        fetch(`{{ route('dashboard.tech-detail', ['technicianId' => '__ID__']) }}`.replace('__ID__', id) + `?date_from=${this.dateFrom}&date_to=${this.dateTo}`)
                            .then(res => res.json())
                            .then(data => {
                                this.techServices = data;
                                this.loadingTech = false;
                            })
                            .catch(err => {
                                console.error(err);
                                this.loadingTech = false;
                            });
                    }
                 }">
            <!-- PRODUCTIVITY -->
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 flex flex-col h-full overflow-hidden relative">
                <div class="mb-8 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shadow-lg" style="background-color: #9333ea !important;">
                        <i class="ri-time-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-purple-600 uppercase tracking-widest leading-none mb-1">RENDIMIENTO OPERATIVO</p>
                        <h3 class="text-lg font-black text-slate-800 leading-none">Productividad Técnicos</h3>
                    </div>
                </div>
                
                <div class="space-y-3 flex-1 pr-1 custom-scrollbar overflow-y-auto">
                    @forelse($techProductivity as $tech)
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-100 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full text-white flex items-center justify-center font-black text-xs shadow-md" style="background-color: #9333ea !important;">
                                    {{ substr($tech->technician, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-black text-slate-800 whitespace-normal break-words">{{ $tech->technician }}</p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">Tiempo promedio: {{ number_format($tech->avg_minutes ?? 0, 0) }} min</p>
                                        <button type="button" 
                                                @click="openTechDetail('{{ $tech->technician_id }}', '{{ $tech->technician }}')"
                                                class="text-purple-600 hover:text-purple-800 transform hover:scale-110 transition-all"
                                                title="Ver detalle de servicios">
                                            <i class="ri-information-fill text-sm"></i>
                                            <span class="text-[9px] font-black underline uppercase">Ver detalle</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-black text-slate-900 leading-tight">{{ $tech->orders }}</p>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">órdenes</p>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center py-10 opacity-30">
                            <i class="ri-user-forbid-line text-3xl mb-2"></i>
                            <p class="text-xs font-bold italic">Sin datos</p>
                        </div>
                    @endforelse
                </div>

                <!-- MODAL DE DETALLES (PREMIUM) -->
                <div x-show="showTechModal" 
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-cloak>
                    <div @click.away="showTechModal = false" 
                         class="bg-white rounded-[2rem] shadow-2xl w-full max-w-4xl overflow-hidden border border-slate-100"
                         x-transition:enter="transition ease-out duration-300 transform"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                        
                        <!-- Header -->
                        <div class="p-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center shadow-lg">
                                    <i class="ri-user-settings-line text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-black text-slate-800 leading-none mb-1" x-text="techName"></h4>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Historial de Productividad</p>
                                </div>
                            </div>
                            <button @click="showTechModal = false" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center transition-colors">
                                <i class="ri-close-line text-xl text-slate-500"></i>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-6 max-h-[60vh] overflow-y-auto custom-scrollbar">
                            <template x-if="loadingTech">
                                <div class="py-20 flex flex-col items-center justify-center opacity-40">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mb-4"></div>
                                    <p class="text-xs font-bold italic">Cargando detalles...</p>
                                </div>
                            </template>

                            <template x-if="!loadingTech && techServices.length === 0">
                                <div class="py-20 flex flex-col items-center justify-center opacity-30">
                                    <i class="ri-inbox-line text-4xl mb-2"></i>
                                    <p class="text-xs font-bold italic">No hay registros para este periodo</p>
                                </div>
                            </template>

                            <template x-if="!loadingTech && techServices.length > 0">
                                <div class="overflow-x-auto overflow-y-hidden rounded-2xl border border-slate-100 shadow-sm custom-scrollbar">
                                    <table class="w-full min-w-[640px] text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-100">
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider">Orden</th>
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider">Vehículo</th>
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Inicio</th>
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Fin</th>
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Tiempo</th>
                                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Pausa</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <template x-for="service in techServices" :key="service.os">
                                                <tr class="hover:bg-slate-50/50 transition-colors">
                                                    <td class="px-4 py-3 text-xs font-black text-slate-900" x-text="service.os"></td>
                                                    <td class="px-4 py-3">
                                                        <p class="text-xs font-bold text-slate-700 truncate max-w-[180px]" x-text="service.vehicle"></p>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-[10px] font-bold text-slate-500" x-text="service.started_at"></td>
                                                    <td class="px-4 py-3 text-center text-[10px] font-bold text-slate-500" x-text="service.finished_at"></td>
                                                    <td class="px-4 py-3 text-center">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black" x-text="service.net_minutes + ' min'"></span>
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black" x-text="service.paused_minutes + ' min'"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="p-6 bg-slate-50/30 border-t border-slate-50 flex justify-end">
                            <button @click="showTechModal = false" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-black hover:bg-slate-900 transition-all shadow-lg">
                                Cerrar Detalle
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- FREQUENT CLIENTS -->
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 flex flex-col h-full overflow-hidden">
                <div class="mb-8 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shadow-lg" style="background-color: #2563eb !important;">
                        <i class="ri-group-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-blue-600 uppercase tracking-widest leading-none mb-1">INTELIGENCIA DE NEGOCIO</p>
                        <h3 class="text-lg font-black text-slate-800 leading-none">Clientes Frecuentes</h3>
                    </div>
                </div>

                <div class="space-y-3 flex-1 pr-1 custom-scrollbar overflow-y-auto">
                    @forelse($frequentClients as $client)
                        <div class="group flex items-center gap-4 p-4 rounded-2xl bg-blue-50 border border-transparent client-hover-row transition-all">
                            <div class="w-10 h-10 rounded-full text-white flex items-center justify-center font-black text-xs shrink-0 shadow-md" style="background-color: #2563eb !important;">
                                {{ $loop->iteration }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-black text-slate-800 whitespace-normal break-words uppercase tracking-tight">{{ $client->client }}</p>
                                <p class="text-[10px] text-slate-500 font-bold mt-0.5 uppercase">
                                    {{ $client->visits }} ÓRDENES • S/ {{ number_format($client->total_spent ?? 0, 2) }}
                                </p>
                            </div>
                            <button class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-all shrink-0 shadow-lg print:hidden">
                                <i class="ri-user-add-fill text-lg"></i>
                            </button>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center py-10 opacity-30">
                            <i class="ri-group-line text-3xl mb-2"></i>
                            <p class="text-xs font-bold italic">Sin clientes recurrentes</p>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>



        <!-- BOTTOM GRID: TOP SERVICES & RECENT ORDERS -->
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <!-- TOP SERVICES -->
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 xl:col-span-4 h-full flex flex-col overflow-hidden print-hidden">
                <div class="mb-8 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shadow-lg" style="background-color: #10b981 !important;">
                        <i class="ri-medal-fill text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest leading-none mb-1">LO MÁS SOLICITADO</p>
                        <h3 class="text-lg font-black text-slate-800 leading-none">Servicios Top</h3>
                    </div>
                </div>
                
                <div class="space-y-6 flex-1 pr-1 overflow-y-auto custom-scrollbar">
                    @forelse($topServices as $service)
                        <div class="block">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center font-black text-xs shadow-md" style="background-color: #10b981 !important;">
                                        {{ $loop->iteration }}
                                    </div>
                                    <div>
                                        <p class="text-[13px] font-black text-slate-800 leading-none mb-1">{{ $service->name }}</p>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">{{ number_format($service->qty, 0) }} ATENCIONES</p>
                                    </div>
                                </div>
                                <p class="text-sm font-black text-slate-900">S/ {{ number_format($service->amount, 0) }}</p>
                            </div>
                            <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full shadow-sm" style="width: {{ min(100, (int)($service->qty * 5)) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="flex-1 flex flex-col items-center justify-center py-10 opacity-30">
                            <i class="ri-service-line text-3xl mb-2"></i>
                            <p class="text-xs font-bold italic">Sin datos</p>
                        </div>
                    @endforelse
                </div>
            </article>

            @php
                $ordersData = $recentOrders->map(function($row) use ($statusLabels) {
                    $cliente = trim(($row->client->first_name ?? '') . ' ' . ($row->client->last_name ?? '')) ?: 'S/C';
                    $vehicle = trim(($row->vehicle->brand ?? '') . ' ' . ($row->vehicle->model ?? '') . ' (' . ($row->vehicle->plate ?? '-') . ')');
                    $pending = max(0, (float) $row->total - (float) $row->paid_total);
                    $rawStatus = strtoupper(str_replace('_', ' ', (string) $row->status));
                    $displayStatus = $statusLabels[$rawStatus] ?? $rawStatus;
                    $orderNumber = $row->movement->number ?? sprintf("%08d", $row->id);
                    
                    return [
                        'id' => $row->id,
                        'number' => $orderNumber,
                        'client' => $cliente,
                        'vehicle' => $vehicle,
                        'pending' => $pending,
                        'status' => $row->status,
                        'displayStatus' => $displayStatus,
                        'date' => \Carbon\Carbon::parse($row->created_at)->format('d/m/Y'),
                        'searchText' => strtolower($orderNumber . ' ' . $cliente . ' ' . $vehicle)
                    ];
                });
            @endphp

            <article x-data="{
                search: '',
                selectedStatus: 'all',
                showStatusMenu: false,
                orders: {{ \Illuminate\Support\Js::from($ordersData) }},
                totalCount: {{ $dashboardData['totalOrdersCount'] ?? 0 }},
                get statusOptions() {
                    const map = new Map();
                    this.orders.forEach(order => {
                        if (!map.has(order.status)) {
                            map.set(order.status, order.displayStatus);
                        }
                    });
                    return [{ value: 'all', label: 'Todos los estados' }, ...Array.from(map, ([value, label]) => ({ value, label }))];
                },
                get selectedStatusLabel() {
                    const selected = this.statusOptions.find(option => option.value === this.selectedStatus);
                    return selected ? selected.label : 'Todos los estados';
                },
                setStatus(value) {
                    this.selectedStatus = value;
                    this.showStatusMenu = false;
                },
                get filteredOrders() {
                    const q = this.search.trim().toLowerCase();
                    return this.orders.filter(order => {
                        const matchesSearch = q === '' || order.searchText.includes(q);
                        const matchesStatus = this.selectedStatus === 'all' || order.status === this.selectedStatus;
                        return matchesSearch && matchesStatus;
                    });
                }
            }" class="rounded-[1.5rem] border border-slate-200 bg-white p-6 xl:col-span-8 flex flex-col h-full overflow-hidden">
                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shadow-lg" style="background-color: #1e293b !important;">
                            <i class="ri-stack-fill text-xl"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">SEGUIMIENTO</p>
                            <h3 class="text-lg font-black text-slate-800 leading-none">Órdenes Recientes</h3>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 print:hidden">
                        <div class="relative group">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm group-focus-within:text-[#465fff] transition-colors"></i>
                            <input type="text" x-model="search" placeholder="Buscar..." class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-[#465fff] focus:bg-white transition-all w-full md:w-56 placeholder:text-slate-300">
                        </div>
                        <div class="relative" @click.away="showStatusMenu = false">
                            <button type="button" @click="showStatusMenu = !showStatusMenu" class="p-2.5 border rounded-xl transition-all"
                                :class="showStatusMenu || selectedStatus !== 'all'
                                    ? 'bg-blue-50 border-blue-200 text-blue-600'
                                    : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100'">
                                <i class="ri-filter-3-line"></i>
                            </button>
                            <div x-show="showStatusMenu" x-transition class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg z-20 overflow-hidden" x-cloak>
                                <div class="px-3 py-2 border-b border-slate-100">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filtrar por estado</p>
                                </div>
                                <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                    <template x-for="option in statusOptions" :key="option.value">
                                        <button type="button" @click="setStatus(option.value)" class="w-full px-3 py-2.5 text-left text-xs font-bold transition-colors flex items-center justify-between"
                                            :class="selectedStatus === option.value ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50'">
                                            <span x-text="option.label"></span>
                                            <i class="ri-check-line text-sm" x-show="selectedStatus === option.value"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-4 print:hidden" x-show="selectedStatus !== 'all'">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 text-blue-700 text-[10px] font-black uppercase tracking-wider border border-blue-100">
                        <i class="ri-filter-3-line"></i>
                        <span x-text="selectedStatusLabel"></span>
                        <button type="button" @click="setStatus('all')" class="hover:text-blue-900">
                            <i class="ri-close-line text-sm"></i>
                        </button>
                    </span>
                </div>

                <div class="overflow-x-auto -mx-6 px-6 print:mx-0 print:px-0">
                    <table class="w-full text-left min-w-[700px]">
                        <thead>
                            <tr class="bg-[#1e293b] text-white text-[10px] uppercase font-black tracking-[0.15em]">
                                <th class="px-6 py-4 rounded-tl-2xl">Nº Orden</th>
                                <th class="px-6 py-4">Cliente / Vehículo</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4 text-center">Fecha</th>
                                <th class="px-6 py-4 text-right rounded-tr-2xl">Saldo Pendiente</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="order in filteredOrders" :key="order.id">
                                <tr class="group hover:bg-slate-50 transition-all duration-200">
                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-slate-400 group-hover:text-slate-900 transition-colors" x-text="order.number"></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-[13px] font-black text-slate-800 uppercase leading-tight" x-text="order.client"></p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight mt-0.5" x-text="order.vehicle"></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border"
                                            :class="{
                                                'bg-emerald-50 text-emerald-600 border-emerald-100': ['delivered', 'finished'].includes(order.status),
                                                'bg-red-50 text-red-600 border-red-100': order.status === 'cancelled',
                                                'bg-blue-50 text-blue-600 border-blue-100': !['delivered', 'finished', 'cancelled'].includes(order.status)
                                            }" 
                                            x-text="order.displayStatus">
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-[11px] font-bold text-slate-500" x-text="order.date"></td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-[13px] font-black" :class="order.pending > 0 ? 'text-slate-900' : 'text-slate-300'" x-text="'S/ ' + parseFloat(order.pending).toFixed(2)"></span>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredOrders.length === 0">
                                <tr>
                                    <td colspan="5" class="py-16 text-center text-sm text-slate-300 italic">No hay órdenes que coincidan con la búsqueda.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                @if(($dashboardData['totalOrdersCount'] ?? 0) > $recentOrders->count())
                    <div class="mt-6 flex justify-center border-t border-slate-100 pt-6 print:hidden">
                        <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}" class="group/btn">
                            <span>Ver todas las órdenes</span>
                            <i class="ri-arrow-right-line ml-1 group-hover/btn:translate-x-1 transition-transform"></i>
                        </x-ui.link-button>
                    </div>
                @endif
            </article>
        </section>

        <!-- SECCIÓN DE ANEXOS YA FUE MOVIDA ARRIBA CERCA DE LAS TARJETAS (SOLO PDF) -->

    </div>

    <style>
        #workshop-dashboard {
            background-color: #F4F6FA;
            margin: -24px;
            padding: 24px;
        }

        .wk-premium-card {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .wk-premium-card:hover {
            transform: translateY(-4px);
            border-color: #e2e8f0;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(70, 95, 255, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(70, 95, 255, 0.2);
        }
        
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            #workshop-dashboard {
                margin: -12px;
                padding: 12px;
            }

            .dashboard-toolbar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }

            .dashboard-toolbar h1 {
                font-size: 1.75rem;
                line-height: 1.1;
            }

            .dashboard-filter-form {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 0.6rem;
                width: 100%;
            }

            .dashboard-filter-form > input,
            .dashboard-filter-form > button,
            .dashboard-filter-form > a {
                width: 100%;
                min-height: 44px;
                justify-content: center;
            }

            .hero-kpi-grid {
                gap: 0.9rem !important;
                margin-bottom: 1rem !important;
            }

            .hero-kpi-grid > article {
                padding: 1rem;
            }
        }

        @media print {
            /* 1. Forzar colores de fondo y gráficos (muy importante) */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page {
                size: portrait;
                margin: 15mm;
            }

            /* 2. Ocultar menús, navs, cargadores y ventanas emergentes */
            header, aside, nav, footer, 
            [id*="sidebar"], [class*="sidebar"],
            [id*="navbar"], [class*="navbar"],
            [class*="preloader"], [class*="loading-overlay"],
            .fixed.z-999999, .fixed.z-\[999999\], 
            .fixed.z-\[9999\], .fixed.z-50, 
            .swal2-container,
            button, input, select, .print\:hidden {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }

            /* No ocultar mi nueva cabecera de reporte ni el buscador de Alpine si es necesario, 
               pero sí los inputs y botones generales */
            #workshop-dashboard header.hidden.print\:flex {
                display: flex !important;
            }

            /* Forzar tablas a ocupar el ancho exacto sin scroll */
            .overflow-x-auto {
                overflow: visible !important;
            }
            table {
                width: 100% !important;
                min-width: 0 !important;
                table-layout: auto !important;
            }

            /* 2. Expandir contenedores principales para permitir paginación */
            html, body, #app, main, .main-content {
                display: block !important;
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                position: static !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            /* 3. El dashboard mismo debe fluir de forma natural */
            #workshop-dashboard {
                position: static !important;
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* 4. Solucionar el corte de listas largas: transformamos las grillas grandes en bloques apilados */
            #workshop-dashboard section.grid:not(.hero-kpi-grid):not(.main-trend-grid) {
                display: block !important;
            }
            #workshop-dashboard section.grid:not(.hero-kpi-grid):not(.main-trend-grid) > article {
                display: block !important;
                width: 100% !important;
                margin-bottom: 2rem !important;
                height: auto !important;
            }

            /* Forzar el grid de KPIs a 4 columnas compacto en la primera página */
            .hero-kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 0.75rem !important;
                margin-bottom: 0.75rem !important;
            }
            .hero-kpi-grid > article {
                display: flex !important;
                flex-direction: column !important;
                width: auto !important;
                margin-bottom: 0 !important;
                padding: 0.75rem !important;
                border-width: 1px !important;
            }
            .hero-kpi-grid > article .text-3xl {
                font-size: 1.25rem !important;
            }
            .hero-kpi-grid > article .mb-6 {
                margin-bottom: 0.5rem !important;
            }

            /* Sección de tendencia + cumpleaños: mostrar gráfico al 100%, ocultar cumpleaños */
            .main-trend-grid {
                display: block !important;
                margin-bottom: 0.75rem !important;
            }
            .main-trend-grid > article.print\:\!hidden {
                display: none !important;
            }
            .trend-chart-article {
                width: 100% !important;
                display: block !important;
                min-height: 200px !important;
                page-break-inside: avoid !important;
                margin-bottom: 0.5rem !important;
                padding: 1rem !important;
            }
            .trend-chart-article .mb-8 {
                margin-bottom: 0.5rem !important;
            }
            .trend-chart-article .min-h-\[320px\] {
                min-height: 180px !important;
            }
            .trend-chart-article div[style*="height: 250px"] {
                height: 160px !important;
            }
            /* El dashboard fluye naturalmente: KPIs primero, luego tendencia */
            .main-trend-grid, .hero-kpi-grid { display: block !important; }

            /* 5. Solucionar el corte de contenido interno (los scrollbars) */
            #workshop-dashboard .overflow-y-auto, 
            #workshop-dashboard .overflow-x-auto, 
            #workshop-dashboard .overflow-hidden, 
            #workshop-dashboard .h-full, 
            #workshop-dashboard .custom-scrollbar {
                display: block !important;
                overflow: visible !important;
                height: auto !important;
                max-height: none !important;
            }

            /* 6. Evitar que las tarjetas se corten a la mitad entre hojas */
            article, .bg-white, .wk-premium-card, section.grid > article {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                margin-bottom: 1rem !important;
                display: block !important;
                position: relative !important;
                border: 2px solid #cbd5e1 !important;
                padding: 1rem !important;
                border-radius: 0.75rem !important;
                background-color: #ffffff !important;
            }
            /* Ensure trend chart stays on same page */
            .trend-chart-article {
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }
            /* Hide birthday section in print */
            .print-hidden {
                display: none !important;
            }

            /* Los anexos grandes SÍ pueden cortar, pero entre filas */
            section.break-before-auto article {
                break-inside: auto !important;
                page-break-inside: auto !important;
                border-width: 1px !important;
            }
            tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
                border: 1px solid #94a3b8 !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                font-size: 9px !important;
            }
            td {
                border-bottom: 1px solid #e2e8f0 !important;
                color: #1e293b !important;
            }

            /* 7. Mejorar visibilidad de textos e íconos en el PDF */
            .text-slate-400, .text-slate-500 {
                color: #475569 !important; /* Más oscuro aún para impresión */
            }
            .text-slate-800, .text-slate-900 {
                color: #000000 !important; /* Negro puro para máximo contraste */
            }
            .bg-slate-50 {
                background-color: #f8fafc !important;
            }
            .wk-premium-card, article {
                box-shadow: none !important;
            }
        }
    </style>
@endsection
