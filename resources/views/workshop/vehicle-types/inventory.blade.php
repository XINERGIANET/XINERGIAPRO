@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventario por tipo de vehiculo" />

    <x-common.component-card title="Inventario por tipo de vehiculo" desc="Selecciona que items de inventario se mostraran al iniciar un servicio para este tipo.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('workshop.vehicle-types.inventory.update', $vehicleType) }}" class="space-y-4">
            @csrf

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3">
                    <p class="text-sm font-bold text-slate-900">Tipo: {{ $vehicleType->name }}</p>
                    <p class="text-xs text-slate-500">Marca/desmarca los items que aplican para este tipo.</p>
                </div>

                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    @foreach ($inventoryDefinitions as $itemKey => $label)
                        <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="items[{{ $itemKey }}]"
                                value="1"
                                @checked(in_array($itemKey, $enabledItemKeys, true))
                                class="h-4 w-4 rounded border-gray-300"
                            >
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <a href="{{ route('workshop.vehicle-types.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i class="ri-arrow-left-line mr-2"></i>Volver
                </a>

                <div class="flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" style="background-color:#22C55E;color:#fff" className="h-11 px-5 rounded-xl font-bold">
                        <i class="ri-save-line"></i><span>Guardar</span>
                    </x-ui.button>
                </div>
            </div>
        </form>
    </x-common.component-card>
@endsection

