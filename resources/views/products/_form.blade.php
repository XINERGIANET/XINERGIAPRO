<div x-data="{
        // Variable que controla si se muestran los campos (True si NO es ingrediente)
        showComplements: '{{ old('type', $product->type ?? 'PRODUCT') }}'.trim() !== 'INGREDENT',
        complementValue: '{{ old('complement', $product->complement ?? 'NO') }}',
        complementMode: '{{ old('complement_mode', $product->complement_mode ?? '') }}',
        classificationValue: '{{ old('classification', $product->classification ?? 'GOOD') }}',
        complements: @json(old('complements', [])),

        addComplement() { this.complements.push({ product: '', qty: 1 }); },
        removeComplement(i) { this.complements.splice(i, 1); },

        handleTypeChange(e) {
            const isIngredient = e.target.value.trim() === 'INGREDENT';
            this.showComplements = !isIngredient;
            if (isIngredient) {
                this.complementValue = 'NO';
                this.complementMode = '';
                this.classificationValue = 'GOOD';
            }
        }
     }">

    <!-- INFORMACIÓN GENERAL -->
    <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Información General</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Codigo <span class="text-red-500">*</span></label>
        <input
            type="text"
            name="code"
            value="{{ old('code', $product->code ?? ($defaultCode ?? '')) }}"
            required
            placeholder="Ingrese el codigo"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre <span class="text-red-500">*</span></label>
        <input
            type="text"
            name="description"
            value="{{ old('description', $product->description ?? '') }}"
            required
            placeholder="Ingrese la descripcion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Abreviatura <span class="text-red-500">*</span></label>
        <input
            type="text"
            name="abbreviation"
            value="{{ old('abbreviation', $product->abbreviation ?? '') }}"
            required
            placeholder="Ingrese la abreviatura"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                <select
                    name="type"
                    required
                    @change="handleTypeChange($event)"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="PRODUCT" @selected(old('type', $product->type ?? 'PRODUCT') === 'PRODUCT')>Producto final</option>
                    <option value="INGREDENT" @selected(old('type', $product->type ?? 'PRODUCT') === 'INGREDENT')>Ingrediente</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Categoría</label>
                <select
                    name="category_id"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>
                            {{ $category->description }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Unidad base</label>
                <select
                    name="base_unit_id"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione unidad</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('base_unit_id', $product->base_unit_id ?? '') == $unit->id)>
                            {{ $unit->description }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
                <select
                    name="status"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="A" @selected(old('status', $product->status ?? 'A') === 'A')>Activo</option>
                    <option value="I" @selected(old('status', $product->status ?? 'A') === 'I')>Inactivo</option>
                </select>
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN DEL PRODUCTO -->
    <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Configuración</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Receta</label>
                <select
                    name="recipe"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="0" @selected(old('recipe', $product?->recipe ?? 0) == 0)>No</option>
                    <option value="1" @selected(old('recipe', $product?->recipe ?? 0) == 1)>Sí</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kardex</label>
                <select
                    name="kardex"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="N" @selected(old('kardex', $product->kardex ?? 'N') === 'N')>No</option>
                    <option value="S" @selected(old('kardex', $product->kardex ?? 'N') === 'S')>Sí</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">¿Es favorito?</label>
                <select
                    name="favorite"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="N" @selected(old('favorite', $productBranch->favorite ?? 'N') === 'N')>No</option>
                    <option value="S" @selected(old('favorite', $productBranch->favorite ?? 'N') === 'S')>Sí</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Duración (minutos)</label>
                <input
                    type="number"
                    name="duration_minutes"
                    value="{{ old('duration_minutes', $productBranch->duration_minutes ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="Ej: 30"
                />
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DE PRECIOS Y STOCK (DETALLE POR SEDE) -->
    <div class="mb-8 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">💰 Información Detalle por Sede</h3>
        <p class="mb-4 text-xs text-gray-600 dark:text-gray-400">Estos campos se configuran por cada sucursal</p>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Precio</label>
                <input
                    type="number"
                    name="price"
                    step="0.01"
                    value="{{ old('price', $productBranch->price ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock actual</label>
                <input
                    type="number"
                    name="stock"
                    step="0.01"
                    value="{{ old('stock', $productBranch->stock ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo</label>
                <input
                    type="number"
                    name="stock_minimum"
                    step="0.01"
                    value="{{ old('stock_minimum', $productBranch->stock_minimum ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock máximo</label>
                <input
                    type="number"
                    name="stock_maximum"
                    step="0.01"
                    value="{{ old('stock_maximum', $productBranch->stock_maximum ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Venta mínima</label>
                <input
                    type="number"
                    name="minimum_sell"
                    step="0.01"
                    value="{{ old('minimum_sell', $productBranch->minimum_sell ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Compra mínima</label>
                <input
                    type="number"
                    name="minimum_purchase"
                    step="0.01"
                    value="{{ old('minimum_purchase', $productBranch->minimum_purchase ?? '') }}"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Unidad de venta</label>
                <input
                    type="text"
                    name="unit_sale"
                    value="{{ old('unit_sale', $productBranch->unit_sale ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="Ej: Unidad, Kg, Lt"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Fecha de expiración</label>
                <input
                    type="date"
                    name="expiration_date"
                    value="{{ old('expiration_date', $productBranch->expiration_date ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                />
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN AVANZADA -->
    <div class="mb-8 p-6 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">⚙️ Configuración Avanzada</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tasa impositiva</label>
                <select
                    name="tax_rate_id"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione tasa impositiva</option>
                    @if(isset($taxRates))
                        @foreach ($taxRates as $rate)
                            <option value="{{ $rate->id }}" @selected(old('tax_rate_id', $productBranch->tax_rate_id ?? '') == $rate->id)>
                                {{ $rate->description }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Proveedor</label>
                <select
                    name="supplier_id"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione proveedor</option>
                    @if(isset($suppliers))
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $productBranch->supplier_id ?? '') == $supplier->id)>
                                {{ $supplier->description }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>
    </div>

    <!-- COMPLEMENTOS -->
    <div x-show="showComplements" x-transition class="mb-8 p-6 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">🎁 Complementos</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Complemento</label>
                <select
                    name="complement"
                    x-model="complementValue"
                    x-bind:required="showComplements"
                    x-ref="complementSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="NO" @selected(old('complement', $product->complement ?? 'NO') === 'NO')>No</option>
                    <option value="HAS" @selected(old('complement', $product->complement ?? 'NO') === 'HAS')>Tiene complementos</option>
                    <option value="IS" @selected(old('complement', $product->complement ?? 'NO') === 'IS')>Es complemento</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Modo complemento</label>
                <select
                    name="complement_mode"
                    x-model="complementMode"
                    x-ref="modeSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="" @selected(old('complement_mode', $product->complement_mode ?? '') === '')>Sin modo</option>
                    <option value="ALL" @selected(old('complement_mode', $product->complement_mode ?? '') === 'ALL')>Todo gratis</option>
                    <option value="QUANTITY" @selected(old('complement_mode', $product->complement_mode ?? '') === 'QUANTITY')>Cantidad gratis</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Clasificación</label>
                <select
                    name="classification"
                    x-model="classificationValue"
                    x-bind:required="showComplements"
                    x-ref="classificationSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="GOOD" x-bind:selected="classificationValue === 'GOOD'">Bien</option>
                    <option value="SERVICE" x-bind:selected="classificationValue === 'SERVICE'">Servicio</option>
                </select>
            </div>
        </div>
    </div>

    <!-- MULTIMEDIA E INFORMACIÓN ADICIONAL -->
    <div class="mb-8 p-6 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">📸 Multimedia e Información Adicional</h3>
        <div class="grid gap-5 lg:grid-cols-2" x-data="{ 
                imagePreview: '{{ isset($product) && $product->image ? asset('storage/' . $product->image) : '' }}',
                fileName: '{{ isset($product) && $product->image ? basename($product->image) : '' }}',
                defaultPlaceholder: 'https://placehold.co/100x100?text=Sin+Imagen',
                
                showPreview(event) {
                    const file = event.target.files[0];
                    if (!file) {
                        this.imagePreview = '{{ isset($product) && $product->image ? asset('storage/' . $product->image) : '' }}';
                        this.fileName = '{{ isset($product) && $product->image ? basename($product->image) : '' }}';
                        return;
                    }

                    if (file.size > 2048 * 1024) {
                        alert('El archivo es demasiado grande. Máximo 2MB.');
                        event.target.value = '';
                        return;
                    }

                    this.fileName = file.name;
                    const reader = new FileReader();
                    reader.onload = (e) => { 
                        this.imagePreview = e.target.result; 
                    };
                    reader.readAsDataURL(file);
                },

                removeImage() {
                    this.imagePreview = '';
                    this.fileName = '';
                    document.getElementById('image-input').value = '';
                }
            }">
        
            <!-- Imagen -->
            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Imagen del producto (opcional)
                </label>
                
                <div class="mb-3 flex items-center gap-4 p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                    
                    <img :src="imagePreview || defaultPlaceholder" alt="Vista previa" 
                        class="h-20 w-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-gray-200 dark:bg-gray-700">
                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" 
                        x-text="fileName || 'Sin archivo seleccionado'">
                        </p>
                        
                        <template x-if="imagePreview">
                            <button type="button" @click="removeImage()" 
                                class="mt-2 inline-block text-xs text-red-600 hover:text-red-800 font-semibold">
                                ✕ Quitar archivo
                            </button>
                        </template>
                        
                        <template x-if="!imagePreview">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Esperando imagen...</span>
                        </template>
                    </div>
                </div>

                <input
                    type="file"
                    name="image"
                    id="image-input"
                    accept="image/*"
                    @change="showPreview($event)"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300"
                />

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    📁 JPG, PNG, GIF, WEBP • Máximo 2MB
                </p>

                @error('image')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Características -->
            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">Características</label>
                <textarea
                    name="features"
                    rows="6"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="Describa las características principales del producto..."
                >{{ old('features', $product->features ?? '') }}</textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    💡 Ingrese las características separadas por saltos de línea
                </p>
            </div>
        </div>
    </div>
</div>
