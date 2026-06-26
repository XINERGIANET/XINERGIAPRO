@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Accesorios adicionales" />

    <x-common.component-card title="Accesorios adicionales" desc="Gestiona los accesorios que se mostraran en el ingreso de mantenimiento para esta sucursal.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4">
            <form method="POST" action="{{ route('workshop.accessories.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-12">
                @csrf
                <div class="md:col-span-6">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                    <input name="name" value="{{ old('name') }}" class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm" placeholder="Ej: Botiquin">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Orden</label>
                    <input name="order_num" type="number" min="0" value="{{ old('order_num', 0) }}" class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm">
                </div>
                <div class="flex items-end md:col-span-2">
                    <label class="inline-flex h-11 items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-gray-300">
                        Activo
                    </label>
                </div>
                <div class="flex items-end md:col-span-2">
                    <x-ui.button type="submit" size="md" variant="primary" className="h-11 w-full rounded-xl font-bold">
                        <i class="ri-add-line"></i><span>Agregar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <form method="GET" action="{{ route('workshop.accessories.index') }}" class="flex gap-2">
                <input name="search" value="{{ $search }}" class="h-10 w-72 rounded-xl border border-gray-200 px-3 text-sm" placeholder="Buscar accesorio">
                <button class="inline-flex h-10 items-center rounded-xl border border-gray-200 px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i class="ri-search-line mr-1"></i>Buscar
                </button>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
            <table class="w-full">
                <thead class="bg-[#334155] text-white">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Activo</th>
                        <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Nombre</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Orden</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($accessories as $accessory)
                        <tr>
                            <td class="px-3 py-2">
                                <input form="accessory-update-{{ $accessory->id }}" type="checkbox" name="active" value="1" @checked($accessory->active) class="h-4 w-4 rounded border-gray-300">
                            </td>
                            <td class="px-3 py-2">
                                <input form="accessory-update-{{ $accessory->id }}" name="name" value="{{ $accessory->name }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input form="accessory-update-{{ $accessory->id }}" name="order_num" type="number" min="0" value="{{ $accessory->order_num }}" class="h-10 w-24 rounded-lg border border-gray-200 px-3 text-center text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-center gap-2">
                                    <form id="accessory-update-{{ $accessory->id }}" method="POST" action="{{ route('workshop.accessories.update', $accessory) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-700" title="Guardar">
                                            <i class="ri-save-line"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('workshop.accessories.destroy', $accessory) }}" onsubmit="return confirm('Eliminar este accesorio?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-error-500 text-white hover:bg-error-600" title="Eliminar">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-sm italic text-gray-400">No hay accesorios configurados para esta sucursal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $accessories->links() }}
        </div>
    </x-common.component-card>
@endsection
