<div
    class="grid grid-cols-1 gap-6 md:grid-cols-2"
    x-data="{
        taxId: @js(old('tax_id', $company->tax_id ?? '')),
        legalName: @js(old('legal_name', $company->legal_name ?? '')),
        address: @js(old('address', $company->address ?? '')),
        rucLoading: false,
        rucError: '',
        async fetchRuc() {
            this.rucError = '';
            const ruc = String(this.taxId || '').trim();
            if (!/^\d{11}$/.test(ruc)) {
                this.rucError = 'Ingrese un RUC valido de 11 digitos.';
                return;
            }

            this.rucLoading = true;
            try {
                const response = await fetch(`/api/ruc?ruc=${encodeURIComponent(ruc)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const payload = await response.json();
                if (!response.ok || !payload?.status) {
                    throw new Error(payload?.message || 'No se encontro informacion para el RUC ingresado.');
                }

                this.taxId = payload.ruc || ruc;
                this.legalName = payload.legal_name || this.legalName;
                this.address = payload.address || this.address;
            } catch (error) {
                this.rucError = error?.message || 'Error consultando RUC.';
            } finally {
                this.rucLoading = false;
            }
        }
    }"
>
    <div class="md:col-span-1">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">RUC</label>
        <div class="flex items-center gap-2">
            <div class="relative group flex-1">
                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                    <i class="ri-id-card-line text-lg"></i>
                </span>
                <input
                    type="text"
                    name="tax_id"
                    x-model="taxId"
                    required
                    placeholder="Ingrese RUC"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                />
            </div>
            <button
                type="button"
                @click="fetchRuc()"
                :disabled="rucLoading"
                class="inline-flex h-12 shrink-0 items-center justify-center rounded-xl bg-[#244BB3] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
            >
                <i class="ri-search-line"></i>
                <span class="ml-1" x-text="rucLoading ? 'Buscando...' : 'Buscar'"></span>
            </button>
        </div>
        <p x-show="rucError" x-cloak class="mt-1.5 text-xs font-medium text-error-500" x-text="rucError"></p>
        @error('tax_id')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-1">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">Razon social</label>
        <div class="relative group">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                <i class="ri-building-line text-lg"></i>
            </span>
            <input
                type="text"
                name="legal_name"
                x-model="legalName"
                required
                placeholder="Ingrese la razon social"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('legal_name')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">Direccion completa</label>
        <div class="relative group">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-4 py-2 text-gray-400 transition-colors group-focus-within:text-brand-500 dark:border-gray-800">
                <i class="ri-map-pin-line text-lg"></i>
            </span>
            <input
                type="text"
                name="address"
                x-model="address"
                required
                placeholder="Ingrese la direccion"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-2.5 pl-[68px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-4 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('address')
            <p class="mt-1.5 text-xs font-medium text-error-500">{{ $message }}</p>
        @enderror
    </div>
</div>
