<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Numero</label>
        <input
            type="text"
            name="number"
            value="{{ old('number', $sale->number ?? '') }}"
            required
            placeholder="Ingrese el numero"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Fecha</label>
        <input
            type="datetime-local"
            name="moved_at"
            value="{{ old('moved_at', isset($sale?->moved_at) ? $sale->moved_at->format('Y-m-d\\TH:i') : '') }}"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="branch_id"
            :value="old('branch_id', $sale->branch_id ?? '')"
            :options="collect($branches ?? [])->map(fn($b) => ['value' => $b->id, 'label' => $b->legal_name])->prepend(['value' => '', 'label' => 'Seleccione sucursal'])->values()->all()"
            placeholder="Seleccione sucursal"
            label="Sucursal"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="movement_type_id"
            :value="old('movement_type_id', $sale->movement_type_id ?? '')"
            :options="collect($movementTypes ?? [])->map(fn($m) => ['value' => $m->id, 'label' => $m->description])->prepend(['value' => '', 'label' => 'Seleccione tipo'])->values()->all()"
            placeholder="Seleccione tipo"
            label="Tipo movimiento"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="document_type_id"
            :value="old('document_type_id', $sale->document_type_id ?? '')"
            :options="collect($documentTypes ?? [])->map(fn($d) => ['value' => $d->id, 'label' => $d->name])->prepend(['value' => '', 'label' => 'Seleccione documento'])->values()->all()"
            placeholder="Seleccione documento"
            label="Tipo documento"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <x-form.select-autocomplete
            name="person_id"
            :value="old('person_id', $sale->person_id ?? '')"
            :options="collect($people ?? [])->map(fn($p) => ['value' => $p->id, 'label' => trim($p->first_name . ' ' . $p->last_name)])->prepend(['value' => '', 'label' => 'Seleccione persona'])->values()->all()"
            placeholder="Seleccione persona"
            label="Persona (opcional)"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div class="sm:col-span-2 lg:col-span-3">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Comentario</label>
        <textarea
            name="comment"
            rows="3"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            placeholder="Ingrese comentario"
        >{{ old('comment', $sale->comment ?? '') }}</textarea>
    </div>

    <div>
        <x-form.select-autocomplete
            name="status"
            :value="old('status', $sale->status ?? 'A')"
            :options="[['value' => 'A', 'label' => 'Activo'], ['value' => 'I', 'label' => 'Inactivo']]"
            placeholder="Estado"
            label="Estado"
            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>
</div>
