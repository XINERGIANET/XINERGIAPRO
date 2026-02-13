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
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sucursal</label>
        <select
            name="branch_id"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione sucursal</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $sale->branch_id ?? '') == $branch->id)>
                    {{ $branch->legal_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo movimiento</label>
        <select
            name="movement_type_id"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione tipo</option>
            @foreach ($movementTypes as $movementType)
                <option value="{{ $movementType->id }}" @selected(old('movement_type_id', $sale->movement_type_id ?? '') == $movementType->id)>
                    {{ $movementType->description }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo documento</label>
        <select
            name="document_type_id"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione documento</option>
            @foreach ($documentTypes as $documentType)
                <option value="{{ $documentType->id }}" @selected(old('document_type_id', $sale->document_type_id ?? '') == $documentType->id)>
                    {{ $documentType->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Persona (opcional)</label>
        <select
            name="person_id"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione persona</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected(old('person_id', $sale->person_id ?? '') == $person->id)>
                    {{ $person->first_name }} {{ $person->last_name }}
                </option>
            @endforeach
        </select>
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
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <select
            name="status"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="A" @selected(old('status', $sale->status ?? 'A') === 'A')>Activo</option>
            <option value="I" @selected(old('status', $sale->status ?? 'A') === 'I')>Inactivo</option>
        </select>
    </div>
</div>
