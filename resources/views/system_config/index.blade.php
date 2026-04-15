@extends('layouts.app')

@php
    $activeTab = old('active_tab', optional($categories->first())->id);
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Configuración | Mi sucursal" />

    <x-common.component-card title="Configuración de sistema" desc="Parámetros por sucursal.">
        <form method="POST" action="{{ route('admin.system-config.update', $viewId ? ['view_id' => $viewId] : []) }}" x-data="{ activeTab: '{{ (string) $activeTab }}' }">
            @csrf
            @if ($viewId)
                <input type="hidden" name="view_id" value="{{ $viewId }}">
            @endif
            <input type="hidden" name="active_tab" :value="activeTab">

            <div class="mb-6 overflow-x-auto border-b border-gray-200">
                <div class="flex min-w-max items-center gap-1">
                    @foreach ($categories as $category)
                        <button
                            type="button"
                            @click="activeTab = '{{ $category->id }}'"
                            class="h-11 whitespace-nowrap rounded-t-lg px-5 text-sm font-semibold transition"
                            :class="activeTab === '{{ $category->id }}'
                                ? 'border-b-2 border-orange-500 bg-orange-50 text-orange-600'
                                : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                        >
                            {{ strtoupper($category->description) }}
                        </button>
                    @endforeach
                </div>
            </div>

            @foreach ($categories as $category)
                <section x-show="activeTab === '{{ $category->id }}'" x-cloak>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($category->parameters as $parameter)
                            @php
                                $currentValue = old("values.{$parameter->id}", $parameter->branch_value ?? $parameter->value);
                                $parameterDescription = strtolower(trim((string) $parameter->description));
                                $normalized = strtolower(trim((string) $currentValue));
                                $isBoolean = in_array($normalized, ['si', 'no', 'yes', 'true', 'false', '1', '0'], true);
                                $isDefaultSaleDocType = str_contains($parameterDescription, 'tipo venta por defecto');
                                $isDefaultIgvParameter = str_contains($parameterDescription, 'igv defecto')
                                    || str_contains($parameterDescription, 'igv por defecto');
                                $isCashRegisterParameter = str_contains($parameterDescription, 'caja ventas del') || str_contains($parameterDescription, 'caja factur');
                                $isMaintenanceDaysParameter = str_contains($parameterDescription, 'periodo de mantenimiento')
                                    || str_contains($parameterDescription, 'dias previos de recordatorio')
                                    || str_contains($parameterDescription, 'días previos de recordatorio')
                                    || str_contains($parameterDescription, 'notificar próximo servicio')
                                    || str_contains($parameterDescription, 'notificar citas');
                                    
                                if ($isMaintenanceDaysParameter) {
                                    $isBoolean = false;
                                }
                                $type = is_numeric($currentValue) ? 'number' : 'text';
                            @endphp
                            <div class="rounded-xl border border-gray-200 bg-white p-4">
                                <label class="mb-2 block text-sm font-semibold text-gray-700">{{ $parameter->description }}</label>

                                @if ($isDefaultSaleDocType)
                                    <select
                                        name="values[{{ $parameter->id }}]"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    >
                                        @forelse (($saleDocumentTypes ?? collect()) as $documentType)
                                            <option value="{{ $documentType->id }}" @selected((string) $currentValue === (string) $documentType->id)>
                                                {{ $documentType->name }}
                                            </option>
                                        @empty
                                            <option value="">Sin tipos de documento de venta</option>
                                        @endforelse
                                    </select>
                                @elseif ($isDefaultIgvParameter)
                                    <select
                                        name="values[{{ $parameter->id }}]"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    >
                                        @forelse (($taxRates ?? collect()) as $rate)
                                            @php
                                                $rateValue = rtrim(rtrim(number_format((float) $rate->tax_rate, 6, '.', ''), '0'), '.');
                                                $selectedRate = (float) $currentValue;
                                                $optionRate = (float) $rate->tax_rate;
                                            @endphp
                                            <option value="{{ $rateValue }}" @selected(abs($selectedRate - $optionRate) < 0.000001)>
                                                {{ $rate->description }} ({{ rtrim(rtrim(number_format((float) $rate->tax_rate, 2, '.', ''), '0'), '.') }}%)
                                            </option>
                                        @empty
                                            <option value="{{ $currentValue }}">{{ $currentValue }}</option>
                                        @endforelse
                                    </select>
                                @elseif ($isCashRegisterParameter)
                                    <select
                                        name="values[{{ $parameter->id }}]"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    >
                                        <option value="">Seleccione caja</option>
                                        @forelse (($cashRegisters ?? collect()) as $cashRegister)
                                            <option value="{{ $cashRegister->id }}" @selected((string) $currentValue === (string) $cashRegister->id)>
                                                {{ $cashRegister->number }}
                                            </option>
                                        @empty
                                            <option value="">Sin cajas registradas</option>
                                        @endforelse
                                    </select>
                                @elseif ($isBoolean)
                                    <select
                                        name="values[{{ $parameter->id }}]"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    >
                                        <option value="Si" @selected(in_array($normalized, ['si', 'yes', 'true', '1'], true))>Sí</option>
                                        <option value="No" @selected(in_array($normalized, ['no', 'false', '0'], true))>No</option>
                                    </select>
                                @elseif ($isMaintenanceDaysParameter)
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        name="values[{{ $parameter->id }}]"
                                        value="{{ $currentValue }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    />
                                @else
                                    <input
                                        type="{{ $type }}"
                                        step="any"
                                        name="values[{{ $parameter->id }}]"
                                        value="{{ $currentValue }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 focus:border-orange-400 focus:outline-none"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="mt-6 flex justify-end">
                <x-ui.button type="submit" size="md" variant="primary" class="h-11 px-6" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;">
                    <i class="ri-save-line"></i>
                    <span>Guardar configuración</span>
                </x-ui.button>
            </div>
        </form>
    </x-common.component-card>
@endsection
