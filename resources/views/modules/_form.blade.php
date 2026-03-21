@php
    use Illuminate\Support\HtmlString;
@endphp

<div class="grid gap-6">
    {{-- Nombre del Módulo --}}
    <div class="space-y-2">
        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
            <i class="ri-box-3-line text-lg text-brand-500"></i>
            Nombre del Módulo
        </label>
        <div class="relative group">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-brand-500 transition-colors duration-200">
                <i class="ri-edit-box-line text-xl"></i>
            </span>
            <input
                type="text"
                name="name"
                required
                placeholder="Ej: Gestión de Ventas, Inventario..."
                value="{{ old('name', $module->name ?? '') }}"
                class="block w-full h-12 pl-12 pr-4 text-sm text-gray-800 bg-white border border-gray-200 rounded-2xl shadow-sm focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all duration-200 dark:bg-gray-900 dark:border-gray-700 dark:text-white dark:placeholder-white/20"
            />
        </div>
        @error('name')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Icono --}}
    <div class="space-y-2">
        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
            <i class="ri-palette-line text-lg text-brand-500"></i>
            Icono del Menú
            <span class="text-[10px] font-normal px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-full uppercase tracking-wider">RemixIcon Class</span>
        </label>
        <div class="relative group">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-brand-500 transition-colors duration-200">
                <i class="ri-compass-3-line text-xl"></i>
            </span>
            <input
                type="text"
                name="icon"
                required
                placeholder="Ej: ri-dashboard-3-line, ri-settings-4-fill..."
                value="{{ old('icon', $module->icon ?? '') }}"
                class="block w-full h-12 pl-12 pr-4 text-sm text-gray-800 bg-white border border-gray-200 rounded-2xl shadow-sm focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all duration-200 dark:bg-gray-900 dark:border-gray-700 dark:text-white dark:placeholder-white/20"
            />
        </div>
        @error('icon')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Orden --}}
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                <i class="ri-list-ordered text-lg text-brand-500"></i>
                Orden de Visualización
            </label>
            <div class="relative group">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-brand-500 transition-colors duration-200">
                    <i class="ri-sort-number-asc text-xl"></i>
                </span>
                <input
                    type="number"
                    name="order_num"
                    required
                    value="{{ old('order_num', $module->order_num ?? '0') }}"
                    class="block w-full h-12 pl-12 pr-4 text-sm text-gray-800 bg-white border border-gray-200 rounded-2xl shadow-sm focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all duration-200 dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                />
            </div>
        </div>

        {{-- Estado --}}
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                <i class="ri-checkbox-circle-line text-lg text-brand-500"></i>
                Estado del Módulo
            </label>
            <div class="relative group">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-brand-500 transition-colors duration-200 pointer-events-none">
                    <i class="ri-toggle-line text-xl"></i>
                </span>
                <x-form.select-autocomplete
                    name="status"
                    class="[&_.flex]:pl-12"
                    inputClass="block w-full h-12 pl-12 pr-10 text-sm text-gray-800 bg-white border border-gray-200 rounded-2xl shadow-sm focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all duration-200 dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                    :value="(string) old('status', $module->status ?? 1)"
                    :options="[
                        ['value' => '1', 'label' => 'Activo'],
                        ['value' => '0', 'label' => 'Inactivo'],
                    ]"
                />
            </div>
        </div>
    </div>
</div>
