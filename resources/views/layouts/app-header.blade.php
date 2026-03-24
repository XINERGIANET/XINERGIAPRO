<header
    style="height: 55px; background: linear-gradient(105deg, #0f172a 0%, #1e293b 46%, #b45309 100%) !important;"
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
                    
                // Safe query handling in case table is not migrated yet
                try {
                    $expiringVehicles = collect($expiringVehiclesQuery->get());
                } catch (\Throwable $e) {
                    $expiringVehicles = collect([]);
                }
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
            class="items-center justify-between w-full xl:w-auto gap-4 px-5 py-4 xl:flex shadow-theme-md xl:justify-end xl:px-0 xl:shadow-none border-t border-white/5 xl:border-0 min-w-0">
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
                        class="relative flex items-center justify-center text-white transition-all bg-white/10 border border-white/10 rounded-full hover:text-white h-11 w-11 hover:bg-white/20"
                        aria-label="Notificaciones"
                    >
                        <i class="ri-notification-3-line text-lg"></i>
                        @if($expiringVehicles->count() > 0)
                            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-md">{{ $expiringVehicles->count() }}</span>
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
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-800 dark:text-gray-100">Próximos a Vencer</h3>
                            <span class="text-[10px] font-bold text-blue-800 bg-blue-100 px-2 py-0.5 rounded-full ring-1 ring-inset ring-blue-600/20">{{ $expiringVehicles->count() }} Rev. Técnica</span>
                        </div>
                        
                        <div class="max-h-[320px] overflow-y-auto overscroll-contain">
                            @if($expiringVehicles->count() > 0)
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($expiringVehicles as $veh)
                                        @php
                                            $days = (int) now()->startOfDay()->diffInDays($veh->revision_tecnica_vencimiento->copy()->startOfDay(), false);
                                            $isExpired = $days < 0;
                                        @endphp
                                        <a href="{{ route('workshop.maintenance-board.create', ['search' => $veh->plate]) }}" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">
                                                        {{ $veh->plate }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                                        {{ $veh->client?->first_name }} {{ $veh->client?->last_name }}
                                                    </p>
                                                </div>
                                                <div class="text-right whitespace-nowrap shrink-0">
                                                    @if($isExpired)
                                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700 ring-1 ring-inset ring-red-600/10">VENCIDO</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-[10px] font-bold text-yellow-800 ring-1 ring-inset ring-yellow-600/20">En {{ $days }} días</span>
                                                    @endif
                                                    <p class="text-[10px] font-medium text-gray-400 mt-1">{{ $veh->revision_tecnica_vencimiento->format('d/m/Y') }}</p>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="px-4 py-10 text-center flex flex-col items-center justify-center">
                                    <div class="h-10 w-10 rounded-full bg-green-50 flex items-center justify-center mb-3 ring-4 ring-green-50/50">
                                        <i class="ri-check-double-line text-xl text-green-500"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800">¡Todo al día!</h4>
                                    <p class="text-xs text-gray-500 mt-1">No hay revisiones próximas a vencer.</p>
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
