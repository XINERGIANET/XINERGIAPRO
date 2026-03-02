<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $productType->name ?? '') }}"
            required
            placeholder="Ingrese el nombre"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div x-data="iconPicker()" x-init="init()" class="relative">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Icono</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <i class="ri-grid-line text-lg"></i>
            </span>
            <input
                type="text"
                name="icon"
                x-ref="iconInput"
                x-model="search"
                value="{{ old('icon', $productType->icon ?? '') }}"
                placeholder="Busca o escribe un icono..."
                autocomplete="off"
                spellcheck="false"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                @focus="openDropdown()"
                @input="openDropdown()"
                @keydown.escape.stop="closeDropdown()"
            />
            <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                @click="toggleDropdown()"
                aria-label="Abrir selector de iconos"
            >
                <i class="ri-arrow-down-s-line text-lg transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
        </div>
        <div
            x-show="open"
            class="absolute left-0 top-full z-50 mt-2 w-full rounded-xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
            @click.outside="closeDropdown()"
        >
            <div class="mb-2 flex items-center justify-between text-xs text-gray-500">
                <span x-text="loading ? 'Cargando iconos...' : `${filteredIcons.length} iconos`"></span>
                <button type="button" class="text-brand-500 hover:text-brand-600" @click="clear()">Limpiar</button>
            </div>
            <template x-if="loading">
                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                    <span>Cargando iconos...</span>
                </div>
            </template>

            <template x-if="!loading && error">
                <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                    No se pudieron cargar los iconos.
                </div>
            </template>

            <template x-if="!loading && !error && displayedIcons.length === 0">
                <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                    No se encontraron iconos.
                </div>
            </template>

            <div class="max-h-64 overflow-y-auto custom-scrollbar" x-show="!loading && !error && displayedIcons.length">
                <div class="grid gap-2 grid-cols-4 sm:grid-cols-6">
                    <template x-for="icon in displayedIcons" :key="icon">
                        <button
                            type="button"
                            class="flex items-center justify-center rounded-lg border border-gray-200 bg-white px-2 py-3 text-gray-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            @click="select(icon)"
                        >
                            <span class="text-xl"><i :class="icon"></i></span>
                        </button>
                    </template>
                </div>
                <div class="mt-2 text-xs text-gray-400 text-center" x-show="filteredIcons.length > displayedIcons.length">
                    Escribe para filtrar mas resultados.
                </div>
            </div>
        </div>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Comportamiento</label>
        <select
            name="behavior"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="SELLABLE" @selected(old('behavior', $productType->behavior ?? 'SELLABLE') === 'SELLABLE')>Vendible</option>
            <option value="SUPPLY" @selected(old('behavior', $productType->behavior ?? 'SELLABLE') === 'SUPPLY')>Suministro</option>
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
        <textarea
            name="description"
            rows="3"
            placeholder="Ingrese una descripcion opcional"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        >{{ old('description', $productType->description ?? '') }}</textarea>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <select
            name="status"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="1" @selected((string) old('status', isset($productType) ? (int) $productType->status : 1) === '1')>Activo</option>
            <option value="0" @selected((string) old('status', isset($productType) ? (int) $productType->status : 1) === '0')>Inactivo</option>
        </select>
    </div>
</div>
