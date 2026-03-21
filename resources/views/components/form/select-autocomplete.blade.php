@props([
    'name' => '',
    'value' => '',
    'options' => [],
    'placeholder' => 'Seleccionar...',
    'label' => null,
    'class' => '',
    'inputClass' => '',
    'submitOnChange' => false,
    'required' => false,
    'disabled' => false,
    'selectId' => null,
])

@php
    $options = collect($options)->map(fn ($o) => is_array($o) ? $o : ['value' => $o['value'] ?? $o, 'label' => $o['label'] ?? (string) $o])->values()->all();
    $value = (string) $value;
@endphp

<div
    class="relative {{ $class }} @if($disabled) pointer-events-none opacity-60 @endif"
    {{ $attributes->except(['class']) }}
    x-data="{
        open: false,
        query: '',
        value: @js($value),
        options: @js($options),
        get selectedLabel() {
            const opt = this.options.find(o => String(o.value) === String(this.value));
            return opt ? opt.label : '';
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter(o => String(o.label || '').toLowerCase().includes(q));
        },
        submitOnChange: @js((bool) $submitOnChange),
        select(opt) {
            this.value = opt.value;
            this.query = '';
            this.open = false;
            this.$refs.hiddenSelect.value = opt.value;
            this.$refs.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (this.submitOnChange && this.$refs.hiddenSelect.form) this.$refs.hiddenSelect.form.submit();
        },
        init() {
            this.$nextTick(() => {
                const el = this.$refs.hiddenSelect;
                if (!el) return;
                el.value = this.value;
                el.addEventListener('change', () => {
                    this.value = String(el.value);
                });
                el.addEventListener('sync-autocomplete-display', () => {
                    this.value = String(el.value);
                });
            });
        }
    }"
    x-init="init()"
    @click.outside="open = false"
>
    @if($label)
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</label>
    @endif
    <select x-ref="hiddenSelect" name="{{ $name }}" class="sr-only" aria-hidden="true" tabindex="-1" @if($selectId) id="{{ $selectId }}" @endif @if($required) required @endif @if($disabled) disabled @endif>
        @foreach($options as $opt)
            <option value="{{ $opt['value'] }}" @selected($value === (string) $opt['value'])>{{ $opt['label'] }}</option>
        @endforeach
    </select>
    <div
        @click="open = !open; if (open) $nextTick(() => $refs.searchInput?.focus())"
        class="flex h-11 min-h-[2.75rem] cursor-pointer items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 shadow-sm transition dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 {{ $inputClass }}"
    >
        <span class="min-w-0 flex-1 truncate" x-text="selectedLabel || '{{ addslashes($placeholder) }}'" :class="!selectedLabel && 'text-gray-400 dark:text-gray-500'"></span>
        <span class="shrink-0 text-gray-400 dark:text-gray-500" :class="open && 'rotate-180'">
            <i class="ri-arrow-down-s-line text-lg"></i>
        </span>
    </div>
    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        style="display: none;"
    >
        <div class="border-b border-gray-100 p-2 dark:border-gray-700">
            <input
                x-ref="searchInput"
                type="text"
                x-model="query"
                @click.stop
                placeholder="Buscar..."
                class="h-9 w-full rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500"
            />
        </div>
        <div class="max-h-60 overflow-auto py-1">
            <template x-for="(opt, idx) in filtered" :key="idx">
                <button
                    type="button"
                    @click="select(opt)"
                    class="flex w-full items-center px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-indigo-900/30"
                    :class="String(opt.value) === String(value) && 'bg-indigo-50 dark:bg-indigo-900/30'"
                    x-text="opt.label"
                ></button>
            </template>
            <template x-if="filtered.length === 0">
                <p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Sin coincidencias.</p>
            </template>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>[x-cloak]{display:none!important}</style>
    @endpush
@endonce
