<div x-data="{
    ingredients: @json(old('ingredients', $recipe->ingredients->toArray() ?? [])),
    
    addIngredient() {
        this.ingredients.push({
            product_id: '',
            unit_id: '',
            quantity: 1,
            notes: '',
            unit_cost: 0,
            order: this.ingredients.length
        });
    },
    
    removeIngredient(index) {
        this.ingredients.splice(index, 1);
    },
    
    calculateTotalCost() {
        return this.ingredients.reduce((sum, ing) => sum + (ing.quantity * ing.unit_cost), 0).toFixed(2);
    }
}">

    <!-- INFORMACIÓN GENERAL -->
    <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">📋 Información General</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Código <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    name="code"
                    value="{{ old('code', $recipe->code ?? '') }}"
                    required
                    placeholder="REC-001"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
            </div>

            <div class="lg:col-span-3">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre del Platillo <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $recipe->name ?? '') }}"
                    required
                    placeholder="Ej. Lomo Saltado Clásico"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
            </div>

            <div class="lg:col-span-4">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripción</label>
                <textarea
                    name="description"
                    rows="2"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="Descripción detallada del platillo..."
                >{{ old('description', $recipe->description ?? '') }}</textarea>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Categoría <span class="text-red-500">*</span></label>
                <select
                    name="category_id"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $recipe->category_id ?? '') == $category->id)>
                            {{ $category->description }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tiempo de Preparación (min)</label>
                <input
                    type="number"
                    name="preparation_time"
                    value="{{ old('preparation_time', $recipe->preparation_time ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="20"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Método de Preparación</label>
                <select
                    name="preparation_method"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="">Seleccione método</option>
                    <option value="wok" @selected(old('preparation_method', $recipe->preparation_method ?? '') === 'wok')>Wok</option>
                    <option value="horno" @selected(old('preparation_method', $recipe->preparation_method ?? '') === 'horno')>Horno</option>
                    <option value="freidora" @selected(old('preparation_method', $recipe->preparation_method ?? '') === 'freidora')>Freidora</option>
                    <option value="frio" @selected(old('preparation_method', $recipe->preparation_method ?? '') === 'frio')>Frío</option>
                    <option value="manual" @selected(old('preparation_method', $recipe->preparation_method ?? '') === 'manual')>Manual</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Rendimiento <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        name="yield_quantity"
                        step="0.01"
                        value="{{ old('yield_quantity', $recipe->yield_quantity ?? 1) }}"
                        required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        placeholder="1"
                    />
                    <select
                        name="yield_unit_id"
                        required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    >
                        <option value="">Unidad</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('yield_unit_id', $recipe->yield_unit_id ?? '') == $unit->id)>
                                {{ $unit->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado <span class="text-red-500">*</span></label>
                <select
                    name="status"
                    required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                >
                    <option value="A" @selected(old('status', $recipe->status ?? 'A') === 'A')>Activo</option>
                    <option value="I" @selected(old('status', $recipe->status ?? 'A') === 'I')>Inactivo</option>
                </select>
            </div>
        </div>
    </div>

    <!-- INGREDIENTES -->
    <div class="mb-8 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🥘 Ingredientes</h3>
            <button
                type="button"
                @click="addIngredient()"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-600 transition-colors"
            >
                <i class="ri-add-line"></i> Agregar Ingrediente
            </button>
        </div>

        <div class="space-y-3 max-h-96 overflow-y-auto">
            <template x-for="(ingredient, index) in ingredients" :key="index">
                <div class="flex gap-2 p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex-1">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Producto</label>
                        <select
                            :name="`ingredients[${index}][product_id]`"
                            x-model="ingredient.product_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Seleccionar...</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-24">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Cantidad</label>
                        <input
                            type="number"
                            :name="`ingredients[${index}][quantity]`"
                            x-model="ingredient.quantity"
                            step="0.01"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <div class="w-32">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Unidad</label>
                        <select
                            :name="`ingredients[${index}][unit_id]`"
                            x-model="ingredient.unit_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Seleccionar...</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-28">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Costo Unit</label>
                        <input
                            type="number"
                            :name="`ingredients[${index}][unit_cost]`"
                            x-model="ingredient.unit_cost"
                            step="0.01"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <div class="w-24">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Subtotal</label>
                        <div class="h-10 flex items-center px-3 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            S/ <span x-text="(ingredient.quantity * ingredient.unit_cost).toFixed(2)"></span>
                        </div>
                    </div>

                    <div class="flex items-end">
                        <button
                            type="button"
                            @click="removeIngredient(index)"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                        >
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-4 flex justify-end">
            <div class="text-right">
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Costo Total de Insumos:</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">S/ <span x-text="calculateTotalCost()"></span></p>
            </div>
        </div>
    </div>

    <!-- IMAGEN Y NOTAS -->
    <div class="mb-8 p-6 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">📸 Imagen y Notas</h3>
        <div class="grid gap-5 lg:grid-cols-2">
            <div x-data="{ 
                imagePreview: '{{ isset($recipe) && $recipe->image ? asset('storage/' . $recipe->image) : '' }}',
                showPreview(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    if (file.size > 2048 * 1024) {
                        alert('El archivo es demasiado grande');
                        event.target.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (e) => { this.imagePreview = e.target.result; };
                    reader.readAsDataURL(file);
                }
            }">
                <div>
                    <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">Imagen</label>
                    <img x-show="imagePreview" :src="imagePreview" alt="Preview" class="mb-3 h-40 w-full object-cover rounded-lg">
                    <input
                        type="file"
                        name="image"
                        accept="image/*"
                        @change="showPreview($event)"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700"
                    />
                </div>
            </div>

            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">Notas Adicionales</label>
                <textarea
                    name="notes"
                    rows="5"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="Notas de preparación, variaciones, consejos..."
                >{{ old('notes', $recipe->notes ?? '') }}</textarea>
            </div>
        </div>
    </div>

</div>
