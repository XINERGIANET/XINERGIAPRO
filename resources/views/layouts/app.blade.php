<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-x-hidden">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="turbo-cache-control" content="{{ session('status') || session('error') ? 'no-cache' : 'no-preview' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @yield('meta')
    <title>{{ $title ?? 'Dashboard' }} | Xinergia PRO</title>

    <!-- Scripts -->
    <script>
        window.crudModal = function (el) {
            const parseJson = (value, fallback) => {
                if (value === undefined || value === null || value === '') {
                    return fallback;
                }
                try {
                    return JSON.parse(value);
                } catch (error) {
                    return fallback;
                }
            };

            const initialForm = parseJson(el?.dataset?.form, {
                id: null,
                tax_id: '',
                legal_name: '',
                address: '',
            });

            return {
                open: parseJson(el?.dataset?.open, false),
                mode: parseJson(el?.dataset?.mode, 'create'),
                form: initialForm,
                createUrl: parseJson(el?.dataset?.createUrl, ''),
                updateBaseUrl: parseJson(el?.dataset?.updateBaseUrl, ''),
                get formAction() {
                    return this.mode === 'create' ? this.createUrl : `${this.updateBaseUrl}/${this.form.id}`;
                },
                openCreate() {
                    this.mode = 'create';
                    this.form = { id: null, tax_id: '', legal_name: '', address: '' };
                    this.open = true;
                },
                openEdit(company) {
                    this.mode = 'edit';
                    this.form = {
                        id: company.id,
                        tax_id: company.tax_id || '',
                        legal_name: company.legal_name || '',
                        address: company.address || '',
                    };
                    this.open = true;
                },
            };
        };

        document.addEventListener('alpine:init', () => {
            if (window.Alpine && typeof window.crudModal === 'function') {
                Alpine.data('crudModal', (el) => window.crudModal(el));
            }
        });
    </script>
    @php
        $viteEntries = ['resources/js/app.js'];
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            if (isset($manifest['resources/css/app.css'])) {
                $viteEntries = ['resources/css/app.css', 'resources/js/app.js'];
            }
        }
    @endphp
    @vite($viteEntries)
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    this.theme = 'light';
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    html.classList.remove('dark');
                    body.classList.remove('dark', 'bg-gray-900');
                }
            });

            Alpine.store('sidebar', {
                isExpanded: (window.innerWidth >= 1280) ? (localStorage.getItem('sidebarExpanded') !== 'false') : false,
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    if (window.innerWidth >= 1280) {
                        localStorage.setItem('sidebarExpanded', this.isExpanded);
                    }
                    this.isMobileOpen = false;
                    document.body.setAttribute('data-sidebar-expanded', this.isExpanded);
                },
                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },
                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },
                setHovered(val) {
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                        document.body.setAttribute('data-sidebar-hovered', val);
                    }
                }
            });
            document.body.setAttribute('data-sidebar-expanded', Alpine.store('sidebar').isExpanded);
        });
    </script>

    <script>
        (function() {
            const root = document.documentElement;
            root.classList.remove('dark');
            const applyBody = () => {
                const body = document.body;
                if (body) {
                    body.classList.remove('dark', 'bg-gray-900');
                }
            };
            if (document.body) {
                applyBody();
            } else {
                document.addEventListener('DOMContentLoaded', applyBody, { once: true });
            }
        })();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .swal2-container { 
            z-index: 9999999 !important; 
        }
        
        .swal2-container.swal2-bottom-start {
            display: block !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .swal2-popup.swal2-toast {
            position: fixed !important;
            left: 24px !important;
            bottom: 24px !important;
            width: fit-content !important;
            min-width: 320px !important;
            max-width: 500px !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            padding: 1.15rem 1.85rem !important;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.08) !important;
            border-radius: 14px !important;
            border: 1px solid rgba(0,0,0,0.08) !important;
            background: #ffffff !important;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            visibility: visible !important;
            opacity: 1 !important;
            overflow: hidden !important; /* Prevent scrollbars */
        }

        /* Removed rules that pushed toast away from sidebar */

        @media (max-width: 1280px) {
            .swal2-popup.swal2-toast {
                left: 1rem !important;
                width: calc(100vw - 2rem) !important;
                min-width: auto !important;
                max-width: none !important;
                bottom: 1rem !important;
            }
        }

        .swal2-popup.swal2-toast .swal2-icon {
            margin: 0 16px 0 0 !important;
            min-width: 28px !important;
            height: 28px !important;
            flex-shrink: 0 !important;
            border-width: 2px !important;
        }

        .swal2-popup.swal2-toast .swal2-title {
            margin: 0 !important;
            padding: 0 !important;
            color: #1f2937 !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            white-space: normal !important;
            overflow: visible !important;
            display: block !important;
            line-height: 1.4 !important;
            text-align: left !important;
        }

        .swal2-popup.swal2-toast .swal2-timer-progress-bar {
            background: #10B880 !important; /* Elegant green for progress */
            height: 3px !important;
            opacity: 0.6 !important;
        }

        .swal2-container.swal2-backdrop-show .swal-backdrop-blur {
            background-color: rgba(156, 163, 175, 0.4) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
        }

    </style>
</head>

<body class="min-h-screen flex flex-col overflow-x-hidden"
    x-data="{ 'loaded': true}"
    x-init="
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            const saved = localStorage.getItem('sidebarExpanded');
            $store.sidebar.isExpanded = (saved !== 'false');
        }
        document.body.setAttribute('data-sidebar-expanded', $store.sidebar.isExpanded);
    };
    if (window.__sidebarResizeHandler) {
        window.removeEventListener('resize', window.__sidebarResizeHandler);
    }
    window.__sidebarResizeHandler = checkMobile;
    window.addEventListener('resize', window.__sidebarResizeHandler);
    checkMobile();">

    <x-common.preloader/>
    <x-common.loading-overlay/>

    <div class="min-h-screen">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="min-w-0 min-h-screen transition-all duration-300 ease-in-out overflow-x-hidden flex flex-col"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            @include('layouts.app-header')
            
            <main class="flex-1 p-4 md:p-6 flex flex-col min-w-0 w-full">
                <div class="flex-1">
                    @yield('content')
                </div>
            </main>

            <footer class="border-t border-gray-100 bg-white px-6 py-2 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition-colors duration-300">
                <div class="flex items-center justify-center gap-2">
                    <div class="flex items-center justify-center gap-2 text-center text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium">Derechos de autor &copy; {{ date('Y') }}</span>
                        <span class="font-bold tracking-tight text-[#63B7EC]">Xinergia</span>
                        <span class="hidden sm:inline opacity-40">&bull;</span>
                        <span class="hidden sm:inline">Todos los derechos reservados.</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

{{-- Script de Notificaciones --}}
@if (session('status'))
<script>
    (function() {
        if (window.Swal && !document.documentElement.hasAttribute('data-turbo-preview')) {
            Swal.fire({
                toast: true,
                position: 'bottom-start',
                icon: 'success',
                title: @json(session('status')),
                showConfirmButton: false,
                timer: 4500,
                timerProgressBar: true
            });
        }
    })();
</script>
@endif

@if (session('error'))
<script>
    (function() {
        if (window.Swal && !document.documentElement.hasAttribute('data-turbo-preview')) {
            Swal.fire({
                toast: true,
                position: 'bottom-start',
                icon: 'error',
                title: @json(session('error')),
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
        }
    })();
</script>
@endif

<script>
    document.addEventListener('turbo:before-visit', () => {
        if (window.Swal && Swal.getPopup()) Swal.close();
    });
    document.addEventListener('turbo:before-cache', () => {
        if (window.Swal && Swal.getPopup()) Swal.close();
    });

    if (!window.__globalSwalDeleteHandler) {
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('.js-swal-delete');
            if (!form || form.dataset.swalBound === 'true') return;
            event.preventDefault();
            if (!window.Swal) { form.submit(); return; }
            
            const isDark = document.documentElement.classList.contains('dark');
            Swal.fire({
                title: form.dataset.swalTitle || '¿Eliminar registro?',
                text: form.dataset.swalText || 'Esta acción no se puede deshacer.',
                icon: form.dataset.swalIcon || 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.swalConfirm || 'Sí, eliminar',
                cancelButtonText: form.dataset.swalCancel || 'Cancelar',
                confirmButtonColor: form.dataset.swalConfirmColor || '#ef4444',
                cancelButtonColor: form.dataset.swalCancelColor || '#6b7280',
                reverseButtons: true,
                allowOutsideClick: false,
                background: isDark ? '#111827' : '#ffffff',
                color: isDark ? '#e5e7eb' : '#111827',
                customClass: { backdrop: 'swal-backdrop-blur' }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (window.showLoadingModal) window.showLoadingModal();
                    form.dataset.swalBound = 'true';
                    form.submit();
                }
            });
        });
        window.__globalSwalDeleteHandler = true;
    }
</script>
    @stack('scripts')
    
    <!-- Indicadores de Campos Obligatorios (*) -->
    <style>
        label::after, 
        label::before {
            content: none !important;
        }

        label:has(+ :required)::after,
        label:has(+ * :required)::after,
        label:has(+ * * :required)::after,
        label:has(~ :required)::after,
        label:has(~ * :required)::after,
        label:has(~ * * :required)::after,
        label:has(:required)::after,
        div:has(> label):has(:required) > label::after,
        div:has(> label):has([required]) > label::after {
            content: " *" !important;
            color: #ef4444 !important;
            font-weight: bold !important;
            display: inline !important;
        }
    </style>
</body> 
</html>
