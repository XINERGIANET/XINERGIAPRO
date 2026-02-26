@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Tipos de Vehiculo" />

    <x-common.component-card title="Tipos de Vehiculo" desc="Gestiona tipos de vehiculo para el taller.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-vehicle-type-modal')">
                <i class="ri-add-line"></i><span>Nuevo tipo</span>
            </x-ui.button>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input name="search" value="{{ $search }}" class="h-11 min-w-[280px] flex-1 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Buscar tipo de vehiculo">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Buscar</button>
            <a href="{{ route('workshop.vehicle-types.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center">Limpiar</a>
        </form>

        <div class="overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">ID</th>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Orden</th>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Origen</th>
                        <th style="background-color:#334155" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $type)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $type->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ ucfirst($type->name) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $type->order_num }}</td>
                            <td class="px-4 py-3 text-sm">{{ $type->active ? 'Activo' : 'Inactivo' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $type->company_id ? 'Sucursal' : 'Global' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($type->company_id)
                                <div class="flex items-center justify-end gap-2">
                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="edit"
                                            type="button"
                                            @click="$dispatch('open-edit-vehicle-type-modal', {{ $type->id }})"
                                            className="rounded-xl"
                                            style="background-color: #FBBF24; color: #111827;"
                                            aria-label="Editar"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Editar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('workshop.vehicle-types.destroy', $type) }}"
                                        class="relative group js-swal-delete"
                                        data-swal-title="Eliminar tipo de vehiculo?"
                                        data-swal-text="Se eliminara este tipo de vehiculo. Esta accion no se puede deshacer."
                                        data-swal-confirm="Si, eliminar"
                                        data-swal-cancel="Cancelar"
                                        data-swal-confirm-color="#ef4444"
                                        data-swal-cancel-color="#6b7280"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            size="icon"
                                            variant="eliminate"
                                            type="submit"
                                            className="rounded-xl"
                                            style="background-color: #EF4444; color: #FFFFFF;"
                                            aria-label="Eliminar"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                            Eliminar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </form>
                                </div>
                                @else
                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-4 text-sm text-gray-500">Sin tipos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $types->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-vehicle-type-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-2xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar tipo de vehiculo</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('workshop.vehicle-types.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @csrf
                <input name="name" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre tipo" required>
                <input name="order_num" type="number" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Orden" value="0">
                <label class="md:col-span-2 inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-gray-300">
                    Activo
                </label>
                <div class="md:col-span-2 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($types as $type)
        @if($type->company_id)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-vehicle-type-modal.window="if ($event.detail === {{ $type->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-2xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de vehiculo</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('workshop.vehicle-types.update', $type) }}" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <input name="name" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ $type->name }}" placeholder="Nombre tipo" required>
                    <input name="order_num" type="number" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="{{ $type->order_num }}" placeholder="Orden">
                    <label class="md:col-span-2 inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="active" value="1" @checked($type->active) class="h-4 w-4 rounded border-gray-300">
                        Activo
                    </label>
                    <div class="md:col-span-2 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
        @endif
    @endforeach
</div>
@endsection

