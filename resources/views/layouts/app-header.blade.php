<header
    style="min-height: 55px; background: linear-gradient(105deg, #0f172a 0%, #1e293b 46%, #b45309 100%) !important;"
    class="sticky top-0 flex w-full z-40 dark:border-gray-800 dark:bg-gray-900 min-w-0"
    x-data="{
        isApplicationMenuOpen: false,
        toggleApplicationMenu() {
            this.isApplicationMenuOpen = !this.isApplicationMenuOpen;
        }
    }">
    <div class="flex flex-col items-center justify-between grow xl:flex-row xl:px-6 min-w-0">
        <div
            class="flex items-center justify-between w-full xl:w-auto gap-2 px-3 py-3 dark:border-gray-800 sm:gap-4 xl:justify-normal xl:border-b-0 xl:px-0 lg:py-4 min-w-0">

            <!-- Desktop Sidebar Toggle Button -->
            <button
                class="hidden xl:flex items-center justify-center w-10 h-10 text-white border border-white/10 rounded-xl hover:bg-white/10 hover:text-white transition-all duration-200 lg:h-11 lg:w-11"
                :class="{ 'bg-white/10 text-white': !$store.sidebar.isExpanded }"
                @click="$store.sidebar.toggleExpanded()" aria-label="Toggle Sidebar">
                <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="white"></path>
                </svg>
                <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                        fill="" />
                </svg>
            </button>

            @php
                $branchName = null;
                $branchId = null;
                if (session()->has('branch_id')) {
                    $branchId = (int) session('branch_id');
                    $branchName = optional(\App\Models\Branch::find($branchId))->legal_name;
                }
                
                $expiringVehiclesQuery = \App\Models\Vehicle::query()
                    ->with('client')
                    ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                    ->whereNotNull('revision_tecnica_vencimiento')
                    ->where('revision_tecnica_vencimiento', '<=', now()->addDays(30))
                    ->orderBy('revision_tecnica_vencimiento', 'asc')
                    ->limit(10);
                    
                $notifMonths = \Illuminate\Support\Facades\DB::table('branch_parameters as bp')
                    ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
                    ->where('bp.branch_id', $branchId > 0 ? $branchId : 1)
                    ->where('p.description', 'Meses para notificar próximo servicio')
                    ->value('bp.value');
                    
                $notifDays = \Illuminate\Support\Facades\DB::table('branch_parameters as bp')
                    ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
                    ->where('bp.branch_id', $branchId > 0 ? $branchId : 1)
                    ->where('p.description', 'Días para notificar próximo servicio')
                    ->value('bp.value');
                    
                if (is_null($notifMonths) || is_null($notifDays)) {
                    try {
                        $categoryId = \Illuminate\Support\Facades\DB::table('parameter_categories')->whereRaw('UPPER(description) = ?', ['TALLER'])->value('id');
                        if (!$categoryId) {
                            $categoryId = \Illuminate\Support\Facades\DB::table('parameter_categories')->insertGetId(['description' => 'Taller', 'created_at' => now(), 'updated_at' => now()]);
                        }
                        
                        $bIdToUse = $branchId > 0 ? $branchId : 1;
                        
                        // parameter Months
                        $pId1 = \Illuminate\Support\Facades\DB::table('parameters')->where('description', 'Meses para notificar próximo servicio')->value('id');
                        if (!$pId1) {
                            $pId1 = \Illuminate\Support\Facades\DB::table('parameters')->insertGetId([
                                'description' => 'Meses para notificar próximo servicio', 'value' => '2', 'status' => 1, 'parameter_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                        $existingBp1 = \Illuminate\Support\Facades\DB::table('branch_parameters')->where('parameter_id', $pId1)->where('branch_id', $bIdToUse)->first();
                        if (!$existingBp1) {
                            \Illuminate\Support\Facades\DB::table('branch_parameters')->insert([
                                'parameter_id' => $pId1, 'branch_id' => $bIdToUse, 'value' => '2', 'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                        
                        // parameter Days
                        $pId2 = \Illuminate\Support\Facades\DB::table('parameters')->where('description', 'Días para notificar próximo servicio')->value('id');
                        if (!$pId2) {
                            $pId2 = \Illuminate\Support\Facades\DB::table('parameters')->insertGetId([
                                'description' => 'Días para notificar próximo servicio', 'value' => '0', 'status' => 1, 'parameter_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                        $existingBp2 = \Illuminate\Support\Facades\DB::table('branch_parameters')->where('parameter_id', $pId2)->where('branch_id', $bIdToUse)->first();
                        if (!$existingBp2) {
                            \Illuminate\Support\Facades\DB::table('branch_parameters')->insert([
                                'parameter_id' => $pId2, 'branch_id' => $bIdToUse, 'value' => '0', 'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                    } catch (\Throwable $e) {}
                }
                    
                $notifMonths = is_numeric($notifMonths) ? (int) $notifMonths : 2;
                $notifDays = is_numeric($notifDays) ? (int) $notifDays : 0;
                $cutoffDate = now()->subMonths($notifMonths)->subDays($notifDays);

                $notifAppDays = \Illuminate\Support\Facades\DB::table('branch_parameters as bp')
                    ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
                    ->where('bp.branch_id', $branchId > 0 ? $branchId : 1)
                    ->where('p.description', 'Días previos para notificar citas')
                    ->value('bp.value');
                $notifAppDays = is_numeric($notifAppDays) ? (int) $notifAppDays : 2;
                
                $recommendedVehiclesQuery = \App\Models\Vehicle::query()
                    ->with('client')
                    ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                    ->whereHas('workshopMovements', function($q) {
                        $q->whereIn('status', ['delivered', 'finished'])
                          ->where(function($sq) {
                              $sq->whereNotNull('delivery_date')->orWhereNotNull('finished_at');
                          });
                    })
                    ->whereDoesntHave('workshopMovements', function($q) use ($cutoffDate) {
                        $q->where(function($sq) use ($cutoffDate) {
                            $sq->where(function($ssq) use ($cutoffDate) {
                                $ssq->where('status', 'delivered')->where('delivery_date', '>=', $cutoffDate);
                            })->orWhere(function($ssq) use ($cutoffDate) {
                                $ssq->where('status', 'finished')->where('finished_at', '>=', $cutoffDate);
                            });
                        });
                    })
                    ->whereDoesntHave('workshopMovements', function($q) {
                        $q->whereNotIn('status', ['delivered', 'finished', 'cancelled']);
                    })
                    ->withMax(['workshopMovements as last_service_date' => function($q) {
                        $q->whereIn('status', ['delivered', 'finished']);
                    }], \DB::raw('COALESCE(delivery_date, finished_at)'))
                    ->orderBy('last_service_date', 'asc')
                    ->limit(15);

                $upcomingAppointmentsQuery = \App\Models\Appointment::query()
                    ->with(['vehicle', 'client'])
                    ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('start_at', '>=', now()->startOfDay())
                    ->where('start_at', '<=', now()->addDays($notifAppDays)->endOfDay())
                    ->whereNull('movement_id')
                    ->orderBy('start_at', 'asc')
                    ->limit(10);
                    
                // Safe query handling in case table is not migrated yet
                try {
                    $expiringVehicles = collect($expiringVehiclesQuery->get());
                    $recommendedVehicles = collect($recommendedVehiclesQuery->get());
                    $upcomingAppointments = collect($upcomingAppointmentsQuery->get());
                    
                    $todaysAppCount = $upcomingAppointments->filter(fn($app) => \Carbon\Carbon::parse($app->start_at)->isToday())->count();
                } catch (\Throwable $e) {
                    $expiringVehicles = collect([]);
                    $recommendedVehicles = collect([]);
                    $upcomingAppointments = collect([]);
                }
                
                $totalNotifs = $expiringVehicles->count() + $recommendedVehicles->count() + $upcomingAppointments->count();
            @endphp

            <!-- Mobile Menu Toggle Button -->
            <button
                class="flex xl:hidden items-center justify-center w-10 h-10 text-white rounded-lg hover:bg-white/10 hover:text-white transition-all duration-200 lg:h-11 lg:w-11"
                :class="{ 'bg-white/10 text-white': $store.sidebar.isMobileOpen }"
                @click="$store.sidebar.toggleMobileOpen()" aria-label="Toggle Mobile Menu">
                <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="white"></path>
                </svg>
                <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                        fill="" />
                </svg>
            </button>

            @if ($branchName)
                <h1 class="ml-2 inline-flex max-w-[140px] items-center truncate text-lg font-bold text-white tracking-tight sm:max-w-[220px]">
                    {{ $branchName }}
                </h1>
            @endif

            <!-- Logo (mobile only) -->
            <a href="/" class="xl:hidden">
                <img class="brightness-0 invert opacity-90" src="/images/logo/Xinergia.png" alt="Logo" width="130" height="35" />
            </a>

            <!-- Application Menu Toggle -->
            <button @click="toggleApplicationMenu()"
                class="flex items-center justify-center w-10 h-10 text-white rounded-lg z-40 hover:bg-white/10 hover:text-white transition-all xl:hidden">
                <!-- Dots Icon -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M5.99902 10.4951C6.82745 10.4951 7.49902 11.1667 7.49902 11.9951V12.0051C7.49902 12.8335 6.82745 13.5051 5.99902 13.5051C5.1706 13.5051 4.49902 12.8335 4.49902 12.0051V11.9951C4.49902 11.1667 5.1706 10.4951 5.99902 10.4951ZM17.999 10.4951C18.8275 10.4951 19.499 11.1667 19.499 11.9951V12.0051C19.499 12.8335 18.8275 13.5051 17.999 13.5051C17.1706 13.5051 16.499 12.8335 16.499 12.0051V11.9951C16.499 11.1667 17.1706 10.4951 17.999 10.4951ZM13.499 11.9951C13.499 11.1667 12.8275 10.4951 11.999 10.4951C11.1706 10.4951 10.499 11.1667 10.499 11.9951V12.0051C10.499 12.8335 11.1706 13.5051 11.999 13.5051C12.8275 13.5051 13.499 12.8335 13.499 12.0051V11.9951Z"
                        fill="currentColor" />
                </svg>
            </button>

        </div>

        <!-- Application Menu (mobile) and Right Side Actions (desktop) -->
        <div :class="isApplicationMenuOpen ? 'flex' : 'hidden'"
            class="items-center justify-between w-full xl:w-auto gap-4 px-5 py-4 xl:flex shadow-theme-md xl:justify-end xl:px-0 xl:shadow-none border-t border-white/5 xl:border-0 min-w-0 bg-[#1e293b]/95 backdrop-blur-md xl:bg-transparent">
            <div class="flex items-center gap-2 2xsm:gap-3">
                @if (!empty($quickOptions) && $quickOptions->count())
                    <div class="hidden xl:flex items-center gap-2">
                        @foreach ($quickOptions as $option)
                            @php
                                $quickUrl = \App\Helpers\MenuHelper::appendViewIdToPath(route($option->action), $option->view_id);
                            @endphp
                            <a
                                href="{{ $quickUrl }}"
                                class="group relative flex items-center justify-center text-white transition-all bg-white/5 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                                aria-label="{{ $option->name }}"
                            >
                                <i class="{{ $option->icon }} text-lg"></i>
                                <span class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-black/80 px-2 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                                    {{ $option->name }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <!-- Notification Button -->
                <div class="relative group" x-data="{ open: false }" @click.away="open = false">
                    <button
                        @click="open = !open"
                        class="relative flex items-center justify-center text-white transition-all bg-white/10 border border-white/20 rounded-full hover:bg-white/25 h-10 w-10 lg:h-11 lg:w-11 group/notif active:scale-95 shadow-sm"
                        :class="{ 'bg-white/25 border-white/40 ring-2 ring-white/10': open }"
                        aria-label="Notificaciones"
                    >
                        {{-- Bell Icon (SVG) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6 fill-current group-hover/notif:rotate-12 transition-all duration-300">
                            <path d="M20 17H22V19H2V17H4V10C4 5.58172 7.58172 2 12 2C16.4183 2 20 5.58172 20 10V17ZM9 21H15V23H9V21Z"></path>
                        </svg>

                        @if($totalNotifs > 0)
                            <span class="absolute top-0 right-0 -mr-1 -mt-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white shadow-lg border-2 border-[#543b2d] z-20 group-hover/notif:scale-110 transition-transform">
                                {{ $totalNotifs }}
                            </span>
                        @endif
                    </button>
                    <span x-show="!open" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-black/80 px-2 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                        Notificaciones
                    </span>
                    
                    <!-- Dropdown Menu -->
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="absolute right-0 top-full mt-3 w-80 sm:w-80 rounded-2xl bg-white shadow-2xl border border-gray-100 dark:bg-gray-800 dark:border-gray-700 z-50 overflow-hidden"
                         style="display: none;">
                         
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 dark:bg-gray-800/50 dark:border-gray-700 flex justify-between items-center">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-800 dark:text-gray-100">Notificaciones</h3>
                            <span class="text-[10px] font-bold text-blue-800 bg-blue-100 px-2 py-0.5 rounded-full ring-1 ring-inset ring-blue-600/20">{{ $totalNotifs }} Pendientes</span>
                        </div>
                        
                        <div class="max-h-[380px] overflow-y-auto overscroll-contain">
                            
                            {{-- TAB: Citas Próximas --}}
                            @if($upcomingAppointments->count() > 0)
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 dark:bg-gray-800/80 dark:border-gray-700 flex justify-between items-center sticky top-0 z-10">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">Próximas Citas</h3>
                                    <span class="text-[10px] font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">{{ $upcomingAppointments->count() }}</span>
                                </div>
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($upcomingAppointments as $app)
                                        @php
                                            $appTime = \Carbon\Carbon::parse($app->start_at);
                                            $timeLabel = '';
                                            if ($appTime->isToday()) {
                                                $timeLabel = 'Hoy ' . $appTime->format('H:i');
                                            } elseif ($appTime->isTomorrow()) {
                                                $timeLabel = 'Mañana ' . $appTime->format('H:i');
                                            } else {
                                                $timeLabel = $appTime->format('d/m H:i');
                                            }
                                        @endphp
                                        <a href="{{ route('workshop.appointments.index') }}" class="group block px-4 py-3 hover:bg-blue-50/40 dark:hover:bg-gray-700/50 transition-all duration-200">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                                                    <i class="ri-calendar-event-line text-xl"></i>
                                                </div>
                                                <div class="min-w-0 flex-grow">
                                                    <div class="flex items-center justify-between gap-2 mb-1">
                                                        <p class="text-[13px] font-bold text-gray-900 dark:text-gray-100 truncate leading-tight">
                                                            @if($app->type === 'service')
                                                                {{ $app->vehicle ? $app->vehicle->brand . ' ' . $app->vehicle->model : 'Servicio' }}
                                                            @else
                                                                {{ $app->reason }}
                                                            @endif
                                                        </p>
                                                        <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 text-[9px] font-bold text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-600/10 whitespace-nowrap">
                                                            {{ $timeLabel }}
                                                        </span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                        {{ $app->client?->first_name }} {{ $app->client?->last_name }} 
                                                        @if($app->type === 'service' && $app->vehicle?->plate)
                                                            • {{ $app->vehicle->plate }}
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            {{-- TAB: Revisiones Vencidas --}}
                            @if($expiringVehicles->count() > 0)
                                <div class="bg-gray-50 px-4 py-2 border-y border-gray-100 dark:bg-gray-800/80 dark:border-gray-700 flex justify-between items-center sticky top-0 z-10">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">Rev. Técnicas Vencidas</h3>
                                    <span class="text-[10px] font-bold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">{{ $expiringVehicles->count() }}</span>
                                </div>
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($expiringVehicles as $veh)
                                        @php
                                            $days = (int) now()->startOfDay()->diffInDays($veh->revision_tecnica_vencimiento->copy()->startOfDay(), false);
                                            $isExpired = $days < 0;
                                        @endphp
                                        <a href="{{ route('workshop.maintenance-board.create', ['search' => $veh->plate]) }}" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-[13px] font-bold text-gray-800 dark:text-gray-200 truncate">
                                                        {{ $veh->plate ?: 'S/Placa' }}
                                                    </p>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                                        {{ $veh->client?->first_name }} {{ $veh->client?->last_name }}
                                                    </p>
                                                </div>
                                                <div class="text-right whitespace-nowrap shrink-0">
                                                    @if($isExpired)
                                                        <span class="inline-flex items-center rounded bg-red-50 px-1.5 py-0.5 text-[9px] font-bold text-red-700 ring-1 ring-inset ring-red-600/10">VENCIDO</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded bg-yellow-50 px-1.5 py-0.5 text-[9px] font-bold text-yellow-800 ring-1 ring-inset ring-yellow-600/20">En {{ $days }} d</span>
                                                    @endif
                                                    <p class="text-[9px] font-medium text-gray-400 mt-1">{{ $veh->revision_tecnica_vencimiento->format('d/m/y') }}</p>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            
                            {{-- TAB: Mantenimiento Recomendado --}}
                            @if($recommendedVehicles->count() > 0)
                                <div class="bg-gray-50 px-4 py-2 border-y border-gray-100 dark:bg-gray-800/80 dark:border-gray-700 flex justify-between items-center sticky top-0 z-10">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">Mantenimiento Sugerido</h3>
                                    <span class="text-[10px] font-bold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full">{{ $recommendedVehicles->count() }}</span>
                                </div>
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($recommendedVehicles as $veh)
                                        @php
                                            $lastServiceDate = \Carbon\Carbon::parse($veh->last_service_date);
                                            $diff = $lastServiceDate->diff(now());
                                            $m = $diff->y * 12 + $diff->m;
                                            $d = $diff->d;
                                            $timeText = '';
                                            if ($m > 0) $timeText .= $m . 'm ';
                                            if ($d > 0) $timeText .= $d . 'd';
                                            if ($timeText === '') $timeText = 'Hoy';
                                            $timeText = trim($timeText);
                                        @endphp
                                        <a href="{{ route('workshop.maintenance-board.create', ['search' => $veh->plate]) }}" class="group block px-4 py-4 hover:bg-orange-50/40 dark:hover:bg-gray-700/50 transition-all duration-200">
                                            <div class="flex items-center gap-3">
                                                {{-- Icon Container --}}
                                                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-orange-50 to-orange-100 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center text-orange-600 dark:text-orange-400 group-hover:scale-110 transition-transform">
                                                    <i class="ri-tools-line text-xl"></i>
                                                </div>
                                                
                                                <div class="min-w-0 flex-grow">
                                                    <div class="flex items-center justify-between gap-2 mb-1">
                                                        <p class="text-[13px] font-bold text-gray-900 dark:text-gray-100 truncate leading-tight">
                                                            {{ $veh->brand }} {{ $veh->model }}
                                                        </p>
                                                        <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-600/10">
                                                            Hace {{ $timeText }}
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="flex items-center justify-between gap-2 mt-1">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                                {{ $veh->plate ?: 'S/Placa' }} • {{ $veh->client?->first_name }} {{ $veh->client?->last_name }}
                                                            </p>
                                                        </div>
                                                        <p class="text-[10px] font-medium text-gray-400 shrink-0">{{ $lastServiceDate->format('d/m/y') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if($totalNotifs === 0)
                                <div class="px-4 py-10 text-center flex flex-col items-center justify-center">
                                    <div class="h-10 w-10 rounded-full bg-green-50 flex items-center justify-center mb-3 ring-4 ring-green-50/50">
                                        <i class="ri-check-double-line text-xl text-green-500"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800">¡Todo al día!</h4>
                                    <p class="text-xs text-gray-500 mt-1">No hay notificaciones pendientes.</p>
                                </div>
                            @endif
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 p-2 text-center">
                            <a href="{{ route('workshop.vehicles.index') }}" class="inline-block w-full py-2 text-xs font-bold text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors dark:text-blue-400 dark:hover:bg-gray-800">
                                Ver todos los vehículos
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="relative group">
                    <button
                        onclick="window.history.back()"
                        class="relative flex items-center justify-center text-white transition-all bg-white/10 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/20"
                        aria-label="Regresar"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <span class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-black/80 px-2 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                        Regresar
                    </span>
                </div>
            </div>

            <!-- User Dropdown -->
            <x-header.user-dropdown class="text-white hover:text-white" />
        </div>
    </div>
</header>

@if($totalNotifs > 0 && request()->routeIs('dashboard') && !session()->has('service_reminder_shown'))
    @php session()->put('service_reminder_shown', true); @endphp
    {{-- Welcome Toast Card (Alert on login/load) --}}
    <div x-data="{ show: false }" 
         x-init="setTimeout(() => show = true, 800); setTimeout(() => show = false, 8000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-500"
         x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:translate-x-8"
         x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-6 right-6 z-[9999] max-w-sm w-[calc(100%-3rem)] bg-white dark:bg-gray-800 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-orange-100 dark:border-gray-700 p-4 flex items-center gap-4 group/toast"
         style="display: none;"
    >
        {{-- Accent Decoration --}}
        <div class="absolute left-0 top-3 bottom-3 w-1.5 rounded-r-full bg-orange-500 shadow-[2px_0_8px_rgba(249,115,22,0.3)]"></div>
        
        {{-- Icon Section --}}
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-950/30 flex items-center justify-center text-orange-600 dark:text-orange-400">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-7 h-7 fill-current animate-bounce-slow">
                <path d="M20 17H22V19H2V17H4V10C4 5.58172 7.58172 2 12 2C16.4183 2 20 5.58172 20 10V17ZM9 21H15V23H9V21Z"></path>
            </svg>
        </div>
        
        {{-- Content Section --}}
        <div class="flex-grow min-w-0">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Recordatorio de Servicios</h4>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                Hola, tienes <span class="font-bold text-orange-600 dark:text-orange-400 tracking-tight">{{ $totalNotifs }} vehículos</span> con mantenimiento sugerido.
            </p>
        </div>
        
        {{-- Close Button --}}
        <button @click="show = false" class="flex-shrink-0 text-gray-300 hover:text-gray-500 dark:hover:text-white transition-colors p-1">
            <i class="ri-close-line text-xl"></i>
        </button>
    </div>

    <style>
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        .animate-bounce-slow {
            animation: bounce-slow 2s infinite ease-in-out;
        }
    </style>
@endif

@if($todaysAppCount > 0 && (
    (request()->routeIs('dashboard') && !session()->has('today_apps_notified_dash')) || 
    request()->routeIs('workshop.appointments.index')
))
    @if(request()->routeIs('dashboard'))
        @php session()->put('today_apps_notified_dash', true); @endphp
    @endif
    {{-- Today's Appointments Bubble --}}
    <div x-data="{ show: false }" 
         x-init="setTimeout(() => show = true, 1000); setTimeout(() => show = false, 5000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-700"
         x-transition:enter-start="opacity-0 translate-y-20"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-800"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-24"
         class="fixed bottom-40 right-8 z-[9999] p-1"
         style="display: none;"
    >
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full shadow-[0_10px_30px_rgba(37,99,235,0.4)] px-6 py-3 flex items-center gap-3 border border-white/20 backdrop-blur-sm">
            {{-- Animated Icon --}}
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white ring-2 ring-white/30 animate-pulse">
                <i class="ri-calendar-check-line text-lg"></i>
            </div>
            
            {{-- Text --}}
            <div class="flex flex-col">
                <span class="text-xs font-black text-white/80 uppercase tracking-widest leading-none">Hoy</span>
                <p class="text-[13px] font-bold text-white whitespace-nowrap">Tienes {{ $todaysAppCount }} {{ $todaysAppCount > 1 ? 'citas programadas' : 'cita programada' }}</p>
            </div>

            {{-- Close button --}}
            <button @click="show = false" class="ml-2 text-white/60 hover:text-white transition-colors">
                <i class="ri-close-line text-lg"></i>
            </button>
        </div>
    </div>
@endif
