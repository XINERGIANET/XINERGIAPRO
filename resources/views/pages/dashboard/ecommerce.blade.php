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

        // Cálculo de Crecimiento de Ingresos (Real)
        $todayIncome = (float) ($d['todayInvoiced'] ?? 0);
        $yesterdayIncome = $incomeByDay->count() >= 2 ? (float) $incomeByDay->reverse()->values()[1]['amount'] : 0;
        $incomeGrowth = $yesterdayIncome > 0 ? (($todayIncome - $yesterdayIncome) / $yesterdayIncome) * 100 : ($todayIncome > 0 ? 100 : 0);

        // Cálculo de Órdenes Nuevas Hoy (Real)
        $newOrdersToday = $recentOrders->filter(function($order) {
            return \Carbon\Carbon::parse($order->created_at)->isToday();
        })->count();

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
    @endphp

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Dashboard Taller</h1>
            <p class="text-xs text-slate-500 font-medium">Vista general de operaciones del taller</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-slate-50 transition-all">
                <i class="ri-download-2-line"></i> Exportar
            </button>
            <button class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm flex items-center gap-2 hover:bg-blue-700 transition-all">
                <i class="ri-calendar-check-fill"></i> Hoy
            </button>
        </div>
    </div>

    <div class="space-y-6 pb-10" id="workshop-dashboard">
        
        <!-- HERO CARDS: VENTAS, GASTOS, INGRESOS, EGRESOS -->
        <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Ingreso Hoy</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">S/ {{ number_format((float) ($d['todayInvoiced'] ?? 0), 2) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">Taller Directo</p>
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
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Egreso Hoy</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mb-1">S/ {{ number_format((float) ($d['expensesToday'] ?? 0), 2) }}</p>
                    <p class="text-[10px] font-bold text-slate-400">Caja y Compras</p>
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
                        <span>+{{ $newOrdersToday }} hoy</span>
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
        </section>

        <!-- MAIN GRID: TRENDS & BIRTHDAYS -->
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <article class="bg-white rounded-[1.5rem] border border-slate-100 p-8 xl:col-span-8 flex flex-col relative overflow-hidden transition-all hover:shadow-md">
                <!-- Header del gráfico -->
                <div class="flex items-start justify-between mb-8 px-8 pt-6 relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-[#F4F6FA] border border-slate-100 flex items-center justify-center shadow-sm">
                            <i class="ri-line-chart-line text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 leading-tight">Tendencia de Ingresos</h3>
                            <p class="text-sm text-slate-500">Últimos 7 días</p>
                        </div>
                    </div>
                    <div class="text-right bg-[#F4F6FA] px-4 py-3 rounded-xl border border-slate-100">
                        <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-1">Total Semana</p>
                        <p class="text-2xl font-bold text-blue-700 leading-none">S/ {{ number_format((float) ($incomeByDay->sum('amount')), 2) }}</p>
                    </div>
                </div>
                <!-- Contenedor del Gráfico -->
                <div class="flex-1 flex flex-col min-h-[320px] px-8 pb-6">
                    <div class="flex flex-1 items-stretch">
                        @php
                            $count = count($incomeByDay);
                            $maxIncomeLocal = max(1, $incomeByDay->max('amount'));
                            $yMax = ceil($maxIncomeLocal / 15) * 15;
                            if ($yMax == 0) $yMax = 60;
                            
                            $ticks = [];
                            for ($i = 4; $i >= 0; $i--) {
                                $ticks[] = ($yMax / 4) * $i;
                            }

                            $points = [];
                            foreach($incomeByDay as $index => $row) {
                                $x = ($index / (max(1, $count - 1))) * 1000;
                                $y = 100 - (($row['amount'] / $yMax) * 100);
                                $points[] = ['x' => $x, 'y' => $y];
                            }
                            
                            $path = "M " . $points[0]['x'] . " " . $points[0]['y'];
                            for ($i = 0; $i < count($points) - 1; $i++) {
                                $curr = $points[$i];
                                $next = $points[$i+1];
                                $cp1x = $curr['x'] + ($next['x'] - $curr['x']) / 2;
                                $path .= " C $cp1x " . $curr['y'] . ", $cp1x " . $next['y'] . ", " . $next['x'] . " " . $next['y'];
                            }
                            $areaPath = $path . " L 1000 100 L 0 100 Z";
                        @endphp

                        <!-- Eje Y (Labels) -->
                        <div class="flex flex-col justify-between text-[11px] text-slate-400 font-medium pr-6 mb-8 mt-1">
                            @foreach($ticks as $tick)
                                <span>{{ number_format($tick, 0) }}</span>
                            @endforeach
                        </div>

                        <!-- Área del Gráfico -->
                        <div class="relative flex-1 mb-8">
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
                                        <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stop-color="#3b82f6" stop-opacity="0.4"/>
                                            <stop offset="95%" stop-color="#3b82f6" stop-opacity="0.05"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="{{ $areaPath }}" fill="url(#colorValue)" />
                                    <path d="{{ $path }}" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    
                                    @foreach($points as $p)
                                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="#3b82f6" stroke="white" stroke-width="2" />
                                    @endforeach
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Eje X (Dates) - Ahora fuera del área absoluta para asegurar que siempre esté abajo -->
                    <div class="flex justify-between pl-[40px] border-t border-slate-50 pt-4">
                        @foreach($incomeByDay as $row)
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">{{ $row['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 xl:col-span-4 h-full flex flex-col overflow-hidden">
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

        <!-- SECONDARY GRID: PRODUCTIVITY & FREQUENT CLIENTS -->
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- PRODUCTIVITY -->
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 flex flex-col h-full overflow-hidden">
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
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Tiempo promedio: {{ number_format($tech->avg_minutes ?? 0, 0) }} min</p>
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
                            <button class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-all shrink-0 shadow-lg">
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
            <article class="rounded-[1.5rem] border border-slate-100 bg-white p-6 xl:col-span-4 h-full flex flex-col overflow-hidden">
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
                $statusLabelsMap = @js($statusLabels);
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
                orders: @js($ordersData),
                totalCount: {{ $dashboardData['totalOrdersCount'] ?? 0 }},
                get filteredOrders() {
                    if (!this.search.trim()) return this.orders;
                    const q = this.search.toLowerCase();
                    return this.orders.filter(o => o.searchText.includes(q));
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
                    
                    <div class="flex items-center gap-2">
                        <div class="relative group">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm group-focus-within:text-[#465fff] transition-colors"></i>
                            <input type="text" x-model="search" placeholder="Buscar..." class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-[#465fff] focus:bg-white transition-all w-full md:w-56 placeholder:text-slate-300">
                        </div>
                        <button class="p-2.5 bg-slate-50 border border-slate-200 text-slate-500 rounded-xl hover:bg-slate-100 transition-all">
                            <i class="ri-filter-3-line"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6">
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
                    <div class="mt-6 flex justify-center border-t border-slate-100 pt-6">
                        <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.orders.index') }}" class="group/btn">
                            <span>Ver todas las órdenes</span>
                            <i class="ri-arrow-right-line ml-1 group-hover/btn:translate-x-1 transition-transform"></i>
                        </x-ui.link-button>
                    </div>
                @endif
            </article>
        </section>

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
    </style>
@endsection
