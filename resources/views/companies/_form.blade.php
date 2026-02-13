<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="md:col-span-1">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">RUC</label>
        <div class="relative group">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                <i class="ri-id-card-line text-lg"></i>
            </span>
            <input
                type="text"
                name="tax_id"
                value="{{ old('tax_id', $company->tax_id ?? '') }}"
                required
                placeholder="Ingrese RUC"
                @if(($useAlpine ?? false)) x-model="form.tax_id" @endif
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('tax_id')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-1">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">Razón social</label>
        <div class="relative group">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                <i class="ri-building-line text-lg"></i>
            </span>
            <input
                type="text"
                name="legal_name"
                value="{{ old('legal_name', $company->legal_name ?? '') }}"
                required
                placeholder="Ingrese la razón social"
                @if(($useAlpine ?? false)) x-model="form.legal_name" @endif
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('legal_name')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">Dirección completa</label>
        <div class="relative group">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                <i class="ri-map-pin-line text-lg"></i>
            </span>
            <input
                type="text"
                name="address"
                value="{{ old('address', $company->address ?? '') }}"
                required
                placeholder="Ingrese la dirección"
                @if(($useAlpine ?? false)) x-model="form.address" @endif
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('address')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>
</div>
