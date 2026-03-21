@props([
    'fieldKey' => 'sa',
    'fieldKeyExpr' => null,
    'name' => '',
    'valueVar' => '',
    'optionsListExpr' => '[]',
    'optionLabel' => 'name',
    'optionValue' => 'id',
    'emptyText' => 'Seleccionar...',
    'placeholderSearch' => 'Buscar...',
    'required' => false,
    'inputClass' => '',
    'afterPick' => '',
    'displayExpr' => null,
    'pickExpr' => null,
    'numeric' => false,
])

@php
    $fk = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $fieldKey) ?: 'sa';
    $sacKeyForAlpine = ($fieldKeyExpr !== null && $fieldKeyExpr !== '') ? $fieldKeyExpr : "'" . $fk . "'";
    $pickClick = $pickExpr
        ? 'sacClose(' . $sacKeyForAlpine . '); ' . $pickExpr
        : 'sacClose(' . $sacKeyForAlpine . '); ' . $valueVar . ' = ' . ($numeric ? 'Number(opt.' . $optionValue . ')' : 'opt.' . $optionValue) . ($afterPick !== '' ? '; ' . $afterPick : '');
@endphp

<div class="relative" @click.outside="sacClose({!! $sacKeyForAlpine !!})">
    @if($name !== '')
        <input type="hidden" name="{{ $name }}" :value="{{ $valueVar }}" @if($required) required @endif>
    @endif
    <div
        @click="sacToggle({!! $sacKeyForAlpine !!})"
        class="flex h-11 min-h-[2.75rem] w-full cursor-pointer items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 shadow-sm transition dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 {{ $inputClass }}"
    >
        @if($displayExpr)
            <span class="min-w-0 flex-1 truncate" x-text="{{ $displayExpr }}"></span>
        @else
            <span
                class="min-w-0 flex-1 truncate"
                x-text="sacLabel({!! $sacKeyForAlpine !!}, {{ $optionsListExpr }}, {{ $valueVar }}, '{{ $optionLabel }}', '{{ $optionValue }}') || @js($emptyText)"
                :class="!sacLabel({!! $sacKeyForAlpine !!}, {{ $optionsListExpr }}, {{ $valueVar }}, '{{ $optionLabel }}', '{{ $optionValue }}') && 'text-gray-400 dark:text-gray-500'"
            ></span>
        @endif
        <span class="shrink-0 text-gray-400 dark:text-gray-500 transition" :class="sacEnsure({!! $sacKeyForAlpine !!}).open && 'rotate-180'">
            <i class="ri-arrow-down-s-line text-lg"></i>
        </span>
    </div>
    <div
        x-show="sacEnsure({!! $sacKeyForAlpine !!}).open"
        x-cloak
        x-transition
        class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        style="display: none;"
    >
        <div class="border-b border-gray-100 p-2 dark:border-gray-700">
            <input
                x-ref="sacSearchInput"
                type="text"
                x-model="sacEnsure({!! $sacKeyForAlpine !!}).q"
                @click.stop
                placeholder="{{ $placeholderSearch }}"
                class="h-9 w-full rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500"
            />
        </div>
        <div class="max-h-60 overflow-auto py-1">
            <template x-for="opt in sacFiltered({!! $sacKeyForAlpine !!}, {{ $optionsListExpr }}, '{{ $optionLabel }}')" :key="String(opt['{{ $optionValue }}'])">
                <button
                    type="button"
                    @click="{{ $pickClick }}"
                    class="flex w-full items-center px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-indigo-900/30"
                    :class="String(opt['{{ $optionValue }}']) === String({{ $valueVar }}) && 'bg-indigo-50 dark:bg-indigo-900/30'"
                    x-text="opt['{{ $optionLabel }}']"
                ></button>
            </template>
            <template x-if="sacFiltered({!! $sacKeyForAlpine !!}, {{ $optionsListExpr }}, '{{ $optionLabel }}').length === 0">
                <p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Sin coincidencias.</p>
            </template>
        </div>
    </div>
</div>
