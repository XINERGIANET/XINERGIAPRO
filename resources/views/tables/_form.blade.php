<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $table->name ?? '') }}"
            required
            placeholder="Ingrese el nombre"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Capacidad</label>
        <input
            type="number"
            name="capacity"
            min="1"
            value="{{ old('capacity', $table->capacity ?? '') }}"
            placeholder="Ej: 4"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="area_id"
            :value="old('area_id', $table->area_id ?? '')"
            :options="collect($areas ?? [])->map(fn($a) => ['value' => $a->id, 'label' => $a->name])->prepend(['value' => '', 'label' => 'Seleccione area'])->values()->all()"
            placeholder="Seleccione area"
            label="Area"
            :required="true"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="status"
            :value="old('status', $table->status ?? 1)"
            :options="[['value' => 1, 'label' => 'Activo'], ['value' => 0, 'label' => 'Inactivo']]"
            placeholder="Estado"
            label="Estado"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="situation"
            :value="old('situation', $table->situation ?? 'libre')"
            :options="[['value' => 'libre', 'label' => 'Libre'], ['value' => 'ocupada', 'label' => 'Ocupada']]"
            placeholder="Situacion"
            label="Situacion"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Hora apertura</label>
        <input
            type="time"
            name="opened_at"
            value="{{ old('opened_at', $table->opened_at ?? '') }}"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>
</div>
