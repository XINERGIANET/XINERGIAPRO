<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $documentType->name ?? '') }}"
            required
            placeholder="Ingrese el nombre"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock</label>
        <x-form.select-autocomplete
            name="stock"
            :value="(string) old('stock', $documentType->stock ?? 'add')"
            :options="[
                ['value' => 'add', 'label' => 'Suma'],
                ['value' => 'subtract', 'label' => 'Resta'],
                ['value' => 'none', 'label' => 'No'],
            ]"
            :required="true"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo movimiento</label>
        <x-form.select-autocomplete
            name="movement_type_id"
            :value="(string) old('movement_type_id', $documentType->movement_type_id ?? '')"
            :options="collect($movementTypes ?? [])->map(fn ($m) => ['value' => (string) $m->id, 'label' => $m->description])->prepend(['value' => '', 'label' => 'Seleccione tipo'])->values()->all()"
            placeholder="Seleccione tipo"
            :required="true"
        />
    </div>
</div>
