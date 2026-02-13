@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $indexUrl = route('admin.modules.index', $viewId ? ['view_id' => $viewId] : []);
    @endphp
    <x-common.page-breadcrumb pageTitle="Módulos" />

    <x-ui.modal
        x-data="{
            open: true,
            close() {
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit('{{ $indexUrl }}', { action: 'replace' });
                } else {
                    window.location.href = '{{ $indexUrl }}';
                }
            }
        }"
        :isOpen="true"
        :showCloseButton="false"
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            {{-- ENCABEZADO --}}
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                        <i class="ri-dashboard-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">Editar módulo</h3>
                        <p class="mt-1 text-sm text-gray-500">Actualiza la información principal del módulo.</p>
                    </div>
                </div>
                
                {{-- BOTÓN CERRAR SUPERIOR (X) --}}
                <button
                    type="button"
                    @click="close()"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            {{-- ALERTAS DE ERROR --}}
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            {{-- FORMULARIO --}}
            <form method="POST" action="{{ route('admin.modules.update', $module->id) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <div class="grid gap-5">
                    
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre del Módulo</label>
                        <div class="relative">
                            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                {{-- SVG o Icono --}}
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7V17C4 18.1046 4.89543 19 6 19H18C19.1046 19 20 18.1046 20 17V7M4 7L12 12L20 7M4 7L12 2L20 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input
                                type="text"
                                name="name"
                                required
                                value="{{ old('name', $module->name) }}"
                                placeholder="Ej: Ventas"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    </div>

                    {{-- CAMPO: ICONO --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Icono <span class="text-xs text-gray-400 font-normal">(Clase de RemixIcon)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 12L11 15L16 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <input
                                type="text"
                                name="icon"
                                required
                                value="{{ old('icon', $module->icon) }}"
                                placeholder="Ej: ri-home-line"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        {{-- CAMPO: ORDEN --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Orden</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 3H14V21H10V3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 8H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <input
                                    type="number"
                                    name="order_num"
                                    required
                                    value="{{ old('order_num', $module->order_num) }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- BOTONES DE ACCIÓN --}}
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    
                    <x-ui.button
                        type="button"
                        size="md"
                        variant="outline"
                        @click="close()"
                    >
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection