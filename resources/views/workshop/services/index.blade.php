@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Servicios Taller" />

    <x-common.component-card title="Catalogo de servicios" desc="Gestiona servicios preventivos y correctivos del taller.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-service-modal')">
                <i class="ri-add-line"></i><span>Nuevo servicio</span>
            </x-ui.button>
            <button type="button" class="h-10 rounded-lg bg-[#244BB3] px-3 text-sm font-medium text-white">Operacion: Ver</button>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <input name="search" value="{{ $search }}" class="h-11 min-w-[280px] flex-1 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Buscar servicio">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Buscar</button>
            <a href="{{ route('workshop.services.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center">Limpiar</a>
        </form>

        <div class="overflow-x-auto overflow-y-hidden mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Nombre</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Precio base</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Minutos</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Activo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $service->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $service->type }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float)$service->base_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $service->estimated_minutes }}</td>
                            <td class="px-4 py-3 text-sm">{{ $service->active ? 'SI' : 'NO' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="edit"
                                            type="button"
                                            @click="$dispatch('open-edit-service-modal', {{ $service->id }})"
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
                                        action="{{ route('workshop.services.destroy', $service) }}"
                                        class="relative group js-swal-delete"
                                        data-swal-title="Eliminar servicio?"
                                        data-swal-text="Se eliminara este servicio. Esta accion no se puede deshacer."
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
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-4 text-sm text-gray-500">Sin servicios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $services->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-service-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-3xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar servicio</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.services.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                @csrf
                <input name="name" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre" required>
                <select name="type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <option value="preventivo">preventivo</option>
                    <option value="correctivo">correctivo</option>
                </select>
                <input type="number" step="0.01" min="0" name="base_price" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio base" required>
                <input type="number" min="0" name="estimated_minutes" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Minutos" required>
                <button class="h-11 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white">Agregar</button>
                <div class="md:col-span-5 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($services as $service)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-service-modal.window="if ($event.detail === {{ $service->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar servicio</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.services.update', $service) }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $service->name }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre" required>
                    <select name="type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="preventivo" @selected($service->type === 'preventivo')>preventivo</option>
                        <option value="correctivo" @selected($service->type === 'correctivo')>correctivo</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="base_price" value="{{ (float) $service->base_price }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio base" required>
                    <input type="number" min="0" name="estimated_minutes" value="{{ (int) $service->estimated_minutes }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Minutos" required>
                    <select name="active" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="1" @selected((int)$service->active === 1)>SI</option>
                        <option value="0" @selected((int)$service->active === 0)>NO</option>
                    </select>

                    <div class="md:col-span-5 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
@endsection
