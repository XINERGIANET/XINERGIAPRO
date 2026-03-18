@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventario por tipo de vehiculo" />

    <x-common.component-card title="Inventario por tipo de vehiculo" desc="Gestiona los items de inventario por tipo de vehiculo. Puedes agregar, editar y activar/desactivar.">
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
                    <p class="text-xs text-slate-500">Los items activos seran los que se mostraran al iniciar un servicio.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                    <table class="w-full">
                        <thead class="bg-[#334155] text-white">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Activo</th>
                                <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Nombre del item</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Orden</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Clave</th>
                                <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($items as $item)
                                <tr>
                                    <td class="px-3 py-2">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" name="active_keys[]" value="{{ $item->item_key }}" @checked(!$item->trashed()) class="h-4 w-4 rounded border-gray-300">
                                        </label>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            name="labels[{{ $item->item_key }}]"
                                            value="{{ $item->label }}"
                                            class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm"
                                            required
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            type="number"
                                            min="0"
                                            name="orders[{{ $item->item_key }}]"
                                            value="{{ $item->order_num }}"
                                            class="h-10 w-24 rounded-lg border border-gray-200 px-3 text-sm text-center"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex rounded-lg bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-500">
                                            {{ $item->item_key }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="submit"
                                            name="delete_keys[]"
                                            value="{{ $item->item_key }}"
                                            class="inline-flex items-center justify-center rounded-lg bg-error-500 px-3 py-2 text-white hover:bg-error-600"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-400 italic">No hay items configurados para este tipo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nuevo item</label>
                        <input type="text" name="new_item_label" class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm" placeholder="Ej: Direccionales (LED)">
                    </div>
                    <div class="flex items-end">
                        <p class="text-xs text-gray-500">Si lo agregas, se creara como item activo.</p>
                    </div>
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

