<div class="grid gap-5 sm:grid-cols-2">
        @if (isset($currentBranch))
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sucursal</label>
                <input type="text" value="{{ $currentBranch->legal_name }}" disabled
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400 cursor-not-allowed" />
            </div>
        @else
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sucursal</label>
                <div
                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 px-4 py-2.5 text-sm text-red-600 dark:text-red-400">
                    No se pudo determinar la sucursal. Por favor, inicia sesión nuevamente.
                </div>
            </div>
        @endif

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Tasa de impuesto <span class="text-red-500">*</span>
            </label>
            <select name="tax_rate_id" x-model="formData.tax_rate_id" required @change="formData.tax_rate_id = $event.target.value" value="{{ old('tax_rate_id', isset($productBranch) && $productBranch ? $productBranch->tax_rate_id : '') }}"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Seleccione una tasa</option>
                @if (isset($taxRates) && $taxRates->count() > 0)
                    @foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->id }}">
                            {{ $taxRate->description }} ({{ $taxRate->tax_rate }}%)
                        </option>
                    @endforeach
                @endif
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Stock <span class="text-red-500">*</span>
            </label>
            <input type="number" name="stock" x-model="formData.stock" value="{{ old('stock', isset($productBranch) && $productBranch ? $productBranch->stock : 0) }}" required @change="formData.stock = $event.target.value"
                placeholder="Ingrese el stock"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>

        <div>
             <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Precio <span class="text-red-500">*</span>
            </label>
            <input type="number" step="0.01" name="price" x-model="formData.price" required @change="formData.price = $event.target.value"
                placeholder="Ingrese el precio"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo</label>
            <input type="number" step="0.000001" name="stock_minimum" x-model="formData.stock_minimum" @change="formData.stock_minimum = $event.target.value"
                placeholder="Ingrese el stock mínimo"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock máximo</label>
            <input type="number" step="0.000001" name="stock_maximum" x-model="formData.stock_maximum" @change="formData.stock_maximum = $event.target.value"
                placeholder="Ingrese el stock máximo"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo de
                venta</label>
            <input type="number" step="0.000001" name="minimum_sell" x-model="formData.minimum_sell" @change="formData.minimum_sell = $event.target.value"
                placeholder="Ingrese el stock mínimo de venta"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo de
                compra</label>
            <input type="number" step="0.000001" name="minimum_purchase" x-model="formData.minimum_purchase" @change="formData.minimum_purchase = $event.target.value"
                placeholder="Ingrese el stock mínimo de compra"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        </div>
    </div>
