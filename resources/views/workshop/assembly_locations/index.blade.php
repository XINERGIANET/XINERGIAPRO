@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Ubicaciones de Armado" />

    <div class="space-y-6">
        <x-common.component-card title="Ubicaciones" desc="Administra los puntos de trabajo para armados por sucursal o a nivel general.">
            <form method="GET" action="{{ route('workshop.assembly-locations.index') }}" class="mb-5 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-0 sm:min-w-[240px]">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                        <i class="ri-search-line text-lg"></i>
                    </div>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Buscar por nombre o direccion"
                        class="h-12 w-full rounded-2xl border border-slate-200 bg-white pl-12 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                    >
                </div>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" class="h-12 px-6" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;">
                        <i class="ri-search-line"></i>
                        <span>Buscar</span>
                    </x-ui.button>

                    <x-ui.link-button href="{{ route('workshop.assembly-locations.index') }}" size="md" variant="outline" class="h-12 px-6">
                        <i class="ri-refresh-line"></i>
                        <span>Limpiar</span>
                    </x-ui.link-button>
                </div>

                <div class="ml-auto flex gap-2">
                    <x-ui.button type="button" size="md" variant="primary" class="h-12 px-6" style="background:linear-gradient(90deg,#7c3aed,#6d28d9);color:#fff;" @click="$dispatch('open-create-location')">
                        <i class="ri-map-pin-add-line"></i>
                        <span>Nueva ubicacion</span>
                    </x-ui.button>
                </div>
            </form>

            <div class="table-responsive overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                            <th class="px-4 py-3 text-left font-semibold">Direccion</th>
                            <th class="px-4 py-3 text-left font-semibold">Alcance</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @if ($locations->count())
                            @foreach ($locations as $location)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-800">{{ $location->name }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ $location->address ?: 'Sin direccion' }}</td>
                                    <td class="px-4 py-4">
                                        @if ($location->branch_id)
                                            <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Solo sucursal actual</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Todas las sucursales</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($location->active)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Activa</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">Inactiva</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-600 transition hover:bg-amber-100" @click="$dispatch('open-edit-location', { id: {{ $location->id }} })">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                            <form method="POST" action="{{ route('workshop.assembly-locations.destroy', $location) }}" onsubmit="return confirm('Eliminar esta ubicacion?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>

                                            <x-ui.modal
                                                x-data="{ open: false }"
                                                x-on:open-edit-location.window="if ($event.detail && Number($event.detail.id) === {{ $location->id }}) open = true"
                                                :isOpen="false"
                                                :showCloseButton="false"
                                                class="max-w-2xl"
                                            >
                                                <div class="p-6 sm:p-8">
                                                    <div class="mb-6 flex items-center justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-slate-900">Editar ubicacion</h3>
                                                            <p class="mt-1 text-sm text-slate-500">Actualiza nombre, direccion y estado.</p>
                                                        </div>
                                                        <button type="button" @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                                                            <i class="ri-close-line text-xl"></i>
                                                        </button>
                                                    </div>

                                                    <form method="POST" action="{{ route('workshop.assembly-locations.update', $location) }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <div>
                                                            <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                                                            <input type="text" name="name" value="{{ $location->name }}" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" required>
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                                                            <select name="active" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700">
                                                                <option value="1" @selected($location->active)>Activa</option>
                                                                <option value="0" @selected(!$location->active)>Inactiva</option>
                                                            </select>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <label class="mb-1 block text-sm font-medium text-slate-700">Direccion</label>
                                                            <input type="text" name="address" value="{{ $location->address }}" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" placeholder="Direccion opcional">
                                                        </div>
                                                        <div class="md:col-span-2 flex gap-3 pt-2">
                                                            <x-ui.button type="submit" size="md" variant="primary" class="flex-1" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;">
                                                                <i class="ri-save-line"></i>
                                                                <span>Guardar cambios</span>
                                                            </x-ui.button>
                                                            <x-ui.button type="button" size="md" variant="outline" class="flex-1" @click="open = false">
                                                                <i class="ri-close-line"></i>
                                                                <span>Cancelar</span>
                                                            </x-ui.button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </x-ui.modal>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="px-4 py-14 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <i class="ri-map-pin-line text-2xl"></i>
                                    </div>
                                    <p class="mt-4 text-base font-semibold text-slate-700">No hay ubicaciones registradas.</p>
                                    <p class="mt-1 text-sm text-slate-500">Crea tu primera ubicacion para organizar los armados del taller.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $locations->links() }}
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" x-on:open-create-location.window="open = true" x-on:close-create-location.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-2xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Nueva ubicacion de armado</h3>
                        <p class="mt-1 text-sm text-slate-500">Define el lugar y si aplica a la sucursal actual o a todas.</p>
                    </div>
                    <button type="button" @click="$dispatch('close-create-location')" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.assembly-locations.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                        <input type="text" name="name" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" placeholder="Ej: Area electrica" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                        <select name="active" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700">
                            <option value="1" selected>Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Direccion</label>
                        <input type="text" name="address" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-700" placeholder="Direccion opcional">
                    </div>
                    <label class="md:col-span-2 inline-flex items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-medium text-violet-700">
                        <input type="checkbox" name="apply_to_all_branches" value="1" class="h-4 w-4 rounded border-violet-300 text-violet-600">
                        <span>Aplicar a todas las sucursales de la empresa</span>
                    </label>
                    <div class="md:col-span-2 flex gap-3 pt-2">
                        <x-ui.button type="submit" size="md" variant="primary" class="flex-1" style="background:linear-gradient(90deg,#7c3aed,#6d28d9);color:#fff;">
                            <i class="ri-save-line"></i>
                            <span>Guardar ubicacion</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" class="flex-1" @click="$dispatch('close-create-location')">
                            <i class="ri-close-line"></i>
                            <span>Cancelar</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    </div>
@endsection
