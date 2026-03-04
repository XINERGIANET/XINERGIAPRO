@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Servicios Taller" />

    <x-common.component-card title="Catalogo de servicios" desc="Gestiona servicios preventivos y correctivos del taller.">
        {{-- Barra de Herramientas Premium (Estilo solicitado) --}}
        <form method="GET" action="{{ route('workshop.services.index') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
            {{-- Selector de Registros --}}
            <div class="flex items-center gap-2">
                <select name="per_page" class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="10" @selected(($perPage ?? 10) == 10)>10 / pág</option>
                    <option value="25" @selected(($perPage ?? 10) == 25)>25 / pág</option>
                    <option value="50" @selected(($perPage ?? 10) == 50)>50 / pág</option>
                    <option value="100" @selected(($perPage ?? 10) == 100)>100 / pág</option>
                </select>
            </div>

            {{-- Buscador Central --}}
            <div class="relative flex-1 min-w-[200px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-gray-400"></i>
                </div>
                <input type="text" name="search" value="{{ $search }}" class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm font-medium text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Buscar servicio...">
            </div>

            {{-- Acciones del Formulario --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#244BB3] px-6 text-sm font-bold text-white shadow-lg shadow-blue-100 transition-all hover:brightness-110 active:scale-95">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </button>
                <a href="{{ route('workshop.services.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-50 active:scale-95">
                    Limpiar
                </a>
            </div>

            {{-- Botones de Acción (Al final a la derecha) --}}
            <div class="ml-auto flex gap-2">
                <x-ui.button size="md" variant="primary" type="button" class="!h-11 rounded-2xl px-6 font-bold shadow-lg transition-all hover:scale-[1.02]" style="background-color:#00A389;color:#fff" @click="$dispatch('open-service-modal')">
                    <i class="ri-add-line"></i><span>Nuevo servicio</span>
                </x-ui.button>
            </div>
        </form>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="overflow-x-auto overflow-y-hidden mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Nombre</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Precio base</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Minutos</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Activo</th>
                        <th style="background-color:#1e293b" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm text-center font-medium">{{ $service->name }}</td>
                            <td class="px-4 py-3 text-sm text-center uppercase">{{ $service->type }}</td>
                            <td class="px-4 py-3 text-sm text-center font-bold text-emerald-600">{{ number_format((float)$service->base_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ $service->estimated_minutes }} min</td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $service->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $service->active ? 'SI' : 'NO' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
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
                        <tr><td colspan="6" class="px-4 py-4 text-sm text-gray-500 text-center">Sin servicios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $services->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $services->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $services->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $services->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-service-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-3xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar servicio</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.services.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nombre <span class="text-red-500">*</span></label>
                    <input name="name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                    <select name="type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="preventivo">preventivo</option>
                        <option value="correctivo">correctivo</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Precio base <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="base_price" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio base" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Minutos <span class="text-red-500">*</span></label>
                    <input type="number" min="0" name="estimated_minutes" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Minutos" required>
                </div>
                <div class="md:col-span-4 mt-2 flex gap-2">
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

                <form method="POST" action="{{ route('workshop.services.update', $service) }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nombre <span class="text-red-500">*</span></label>
                        <input name="name" value="{{ $service->name }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombre" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                        <select name="type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="preventivo" @selected($service->type === 'preventivo')>preventivo</option>
                            <option value="correctivo" @selected($service->type === 'correctivo')>correctivo</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Precio base <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="base_price" value="{{ (float) $service->base_price }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio base" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Minutos <span class="text-red-500">*</span></label>
                        <input type="number" min="0" name="estimated_minutes" value="{{ (int) $service->estimated_minutes }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Minutos" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Activo <span class="text-red-500">*</span></label>
                        <select name="active" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                            <option value="1" @selected((int)$service->active === 1)>SI</option>
                            <option value="0" @selected((int)$service->active === 0)>NO</option>
                        </select>
                    </div>

                    <div class="md:col-span-4 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
@endsection
