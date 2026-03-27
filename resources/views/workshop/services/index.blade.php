@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Servicios Taller" />

    <x-common.component-card title="Catalogo de servicios" desc="Gestiona servicios preventivos y correctivos del taller.">
        {{-- Barra de Herramientas Premium --}}
        <form method="GET" action="{{ route('workshop.services.index') }}" class="mb-5 flex flex-wrap items-center gap-3">
            @if (request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif

            {{-- Selector de Registros --}}
            <div class="w-32 flex-none">
                <x-form.select-autocomplete
                    name="per_page"
                    :value="$perPage ?? 10"
                    :options="collect([10, 25, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                    placeholder="Por página"
                    :submit-on-change="true"
                    inputClass="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all"
                />
            </div>

            {{-- Buscador Principal --}}
            <div class="relative flex-1 min-w-[200px] sm:min-w-[300px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-gray-400"></i>
                </div>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none placeholder:text-gray-400" 
                    placeholder="Buscar servicio..."
                >
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2">
                <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5 shadow-sm active:scale-95 transition-all" style="background-color: #334155; border-color: #334155;">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.services.index') }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 transition-all active:scale-95">
                    <i class="ri-refresh-line"></i>
                    <span>Limpiar</span>
                </x-ui.link-button>
            </div>

            {{-- Botón Nuevo --}}
            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                <x-ui.button
                    size="md"
                    variant="outline"
                    type="button"
                    class="h-11 rounded-xl border-2 border-emerald-500/40 bg-gradient-to-r from-emerald-50 to-teal-50 px-5 font-bold text-emerald-800 shadow-sm transition-all hover:border-emerald-500 hover:from-emerald-100 hover:to-teal-100 active:scale-95 dark:from-emerald-950/40 dark:to-teal-950/40 dark:text-emerald-200"
                    @click="$dispatch('open-import-services-modal')"
                >
                    <i class="ri-file-excel-2-line text-lg"></i>
                    <span>Importar Excel</span>
                </x-ui.button>
                <x-ui.button 
                    size="md" 
                    variant="primary" 
                    type="button" 
                    class="h-11 rounded-xl px-6 font-bold shadow-sm transition-all hover:brightness-105 active:scale-95" 
                    style="background-color: #00A389; color: #FFFFFF;" 
                    @click="$dispatch('open-service-modal')"
                >
                    <i class="ri-add-line text-lg"></i>
                    <span>Nuevo servicio</span>
                </x-ui.button>
            </div>
        </form>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full">
                <thead style="background-color: #334155; color: #FFFFFF;">
                    <tr>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider first:rounded-tl-xl text-white">Nombre</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tarifas</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tiempo Est.</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Estado</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider last:rounded-tr-xl text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr class="relative hover:z-[60] border-t border-gray-100 dark:border-gray-800 transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-3 text-sm text-center align-middle font-medium text-gray-800 dark:text-white/90">{{ $service->name }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                <x-ui.badge variant="light" color="{{ $service->type === 'preventivo' ? 'success' : 'info' }}">
                                    {{ ucfirst($service->type) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-3 text-sm align-middle">
                                @if ($service->priceTiers->isNotEmpty())
                                    <div class="space-y-1">
                                        @foreach($service->priceTiers as $tier)
                                            <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-1.5 text-xs">
                                                <span class="font-semibold text-gray-600">Hasta {{ number_format((int) $tier->max_cc) }}cc</span>
                                                <span class="font-black text-emerald-600">S/ {{ number_format((float) $tier->price, 2) }}</span>
                                            </div>
                                        @endforeach
                                        @if ((float) $service->base_price > 0)
                                            <div class="pt-1 text-[11px] font-semibold text-gray-500 text-center">
                                                Base / sin cilindrada: S/ {{ number_format((float) $service->base_price, 2) }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-center font-bold text-emerald-600">
                                        S/ {{ number_format((float) $service->base_price, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $service->estimated_minutes }} min</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                <x-ui.badge variant="light" color="{{ $service->active ? 'success' : 'error' }}">
                                    {{ $service->active ? 'Activo' : 'Inactivo' }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-3 text-sm">
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

                                    @if((bool) ($service->frequency_enabled ?? false))
                                        {{-- Botón Frecuencia --}}
                                        <div class="relative group">
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="button"
                                                onclick="window.location='{{ route('workshop.services.frequencies.edit', $service) }}'"
                                                className="rounded-xl"
                                                style="background-color: #10B981; color: #FFFFFF;"
                                                aria-label="Configurar frecuencia"
                                            >
                                                <i class="ri-repeat-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                Configurar frecuencia
                                                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                            </span>
                                        </div>
                                    @endif

                                    <div class="relative group">
                                        <x-ui.button
                                            size="icon"
                                            variant="primary"
                                            type="button"
                                            onclick="window.location='{{ route('workshop.services.details.edit', $service) }}'"
                                            className="rounded-xl"
                                            style="background-color: #0EA5E9; color: #FFFFFF;"
                                            aria-label="Detalle de servicio"
                                        >
                                            <i class="ri-list-check-2-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                            Detalle de servicio
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

    <x-ui.modal x-data="{ open: false, fileName: '', dragging: false }" x-on:open-import-services-modal.window="open = true; fileName = ''; dragging = false; $nextTick(() => { const el = $refs.importFile; if (el) el.value = '' })" :isOpen="false" :showCloseButton="false" class="max-w-lg">
        <div class="overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
            <div class="relative bg-gradient-to-br from-emerald-600 via-teal-600 to-slate-800 px-6 pb-10 pt-8 text-white">
                <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute -bottom-8 left-4 h-24 w-24 rounded-full bg-emerald-300/20 blur-xl"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-emerald-50">
                            <i class="ri-upload-cloud-2-line text-sm"></i>
                            Importación masiva
                        </div>
                        <h3 class="mt-3 text-xl font-bold leading-tight sm:text-2xl">Lista de precios → servicios</h3>
                        <p class="mt-2 max-w-md text-sm text-emerald-50/90">Sube un Excel con columnas <span class="font-bold">SERVICIO</span> y <span class="font-bold">PRECIO</span> (ej. S/ 15.00). Se crearán registros en esta sucursal.</p>
                    </div>
                    <button type="button" @click="open = false" class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-white/10 text-white transition hover:bg-white/20">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="-mt-6 px-6 pb-8 pt-0">
                <form method="POST" action="{{ route('workshop.services.import') }}" enctype="multipart/form-data" data-turbo="false" class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    @csrf
                    @if(request('view_id'))
                        <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                    @endif
                    <label x-ref="dropZone"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="
                            dragging = false;
                            if ($event.dataTransfer.files.length) {
                                const dt = new DataTransfer();
                                dt.items.add($event.dataTransfer.files[0]);
                                $refs.importFile.files = dt.files;
                                fileName = $event.dataTransfer.files[0].name;
                            }
                        "
                        :class="dragging ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-400/50' : 'border-gray-200 bg-gray-50/50 hover:border-emerald-300'"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-10 transition-all dark:border-gray-600 dark:bg-gray-800/50">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/30">
                            <i class="ri-file-excel-2-line text-2xl"></i>
                        </div>
                        <p class="text-center text-sm font-semibold text-gray-800 dark:text-gray-100">Arrastra tu archivo aquí o haz clic para elegir</p>
                        <p class="mt-1 text-center text-xs text-gray-500 dark:text-gray-400">.xlsx, .xls o .csv &middot; max. 12 MB</p>
                        <p class="mt-3 text-center text-xs font-medium text-emerald-700 dark:text-emerald-400" x-show="fileName !== ''" x-text="fileName"></p>
                        <input type="file" name="import_file" x-ref="importFile" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" required class="sr-only" @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                    </label>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo para todos</label>
                            <select name="import_type" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="correctivo" selected>Correctivo</option>
                                <option value="preventivo">Preventivo</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tiempo estimado (min)</label>
                            <input type="number" name="import_estimated_minutes" value="0" min="0" max="14400" required class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="0">
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
                        <button type="button" @click="open = false" class="h-11 rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-6 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition hover:brightness-110 active:scale-[0.98]">
                            <i class="ri-upload-2-line text-lg"></i>
                            Importar ahora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal x-data="{ open: false }" x-on:open-service-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar servicio</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST"
                action="{{ route('workshop.services.store') }}"
                class="grid grid-cols-1 gap-5 md:grid-cols-3"
                x-data="workshopServicePriceTierForm(@js(old('price_tiers', [['max_cc' => '', 'price' => '']])))">
                @csrf
                <div class="md:col-span-3">
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Nombre del Servicio</label>
                    <input name="name" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: Mantenimiento Preventivo 5K" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Tipo de Servicio</label>
                    <select name="type" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" required>
                        <option value="preventivo">Preventivo</option>
                        <option value="correctivo">Correctivo</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Precio Base / sin cilindrada (S/)</label>
                    <input type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price') }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Opcional">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Tiempo Estimado (Minutos)</label>
                    <input type="number" min="0" name="estimated_minutes" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: 60" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Cada cuántos km</label>
                    <input type="number" min="1" name="frequency_each_km" value="{{ old('frequency_each_km') }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: 5000">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Frecuencia activa</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="frequency_enabled" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <span>Usar multiplicador por km</span>
                    </label>
                </div>
                <div x-data="{ hasValidity: {{ old('has_validity') ? 'true' : 'false' }} }" class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-5 rounded-2xl border border-gray-200 bg-emerald-50/30 p-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">¿Tiene vigencia?</label>
                        <p class="mb-2 text-xs text-gray-500">Permite registrar si el próximo servicio es en 6 meses o 1 año.</p>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-1">
                            <input type="checkbox" name="has_validity" value="1" x-model="hasValidity" class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Solicitar vigencia en tablero</span>
                        </label>
                    </div>
                    <div x-show="hasValidity" x-cloak x-transition>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Actualizar fecha de:</label>
                        <p class="mb-2 text-xs text-gray-500">Elige qué campo del vehículo se actualizará.</p>
                        <select name="validity_type" class="h-11 w-full rounded-xl border border-emerald-200 bg-white px-4 text-sm transition-all focus:border-emerald-500 focus:ring-emerald-500/10 focus:outline-none" :required="hasValidity">
                            <option value="">Selecciona fecha vinculada</option>
                            <option value="soat_vencimiento" @selected(old('validity_type') === 'soat_vencimiento')>SOAT Vencimiento</option>
                            <option value="revision_tecnica_vencimiento" @selected(old('validity_type') === 'revision_tecnica_vencimiento')>Rev. Técnica Vencimiento</option>
                        </select>
                    </div>
                </div>
                <div class="md:col-span-3 rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Precios por cilindrada</label>
                            <p class="text-xs text-gray-500">Configura tramos como: Hasta 250cc S/ 25, Hasta 500cc S/ 50.</p>
                        </div>
                        <button type="button" @click="addRow()" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                            <i class="ri-add-line"></i>
                            <span>Agregar tramo</span>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(tier, index) in rows" :key="tier.key">
                            <div class="rounded-2xl border border-gray-200 bg-white p-3" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) 44px;gap:0.75rem;align-items:end;">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta (cc)</label>
                                    <input type="number" min="1" step="1" :name="`price_tiers[${index}][max_cc]`" x-model="tier.max_cc" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: 250">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Precio (S/)</label>
                                    <input type="number" min="0" step="0.01" :name="`price_tiers[${index}][price]`" x-model="tier.price" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="0.00">
                                </div>
                                <div class="flex items-end justify-end">
                                    <button type="button" @click="removeRow(index)" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="md:col-span-3 mt-4 flex items-center justify-end gap-3">
                    <x-ui.button type="button" size="md" variant="outline" class="rounded-xl px-6" @click="open = false">
                        <span>Cancelar</span>
                    </x-ui.button>
                    <x-ui.button type="submit" size="md" variant="primary" class="rounded-xl px-8" style="background-color: #00A389; border-color: #00A389;">
                        <span>Guardar Servicio</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($services as $service)
        <x-ui.modal x-data="{ open: false }" x-on:open-edit-service-modal.window="if ($event.detail === {{ $service->id }}) { open = true }" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar servicio</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST"
                    action="{{ route('workshop.services.update', $service) }}"
                    class="grid grid-cols-1 gap-5 md:grid-cols-3"
                    x-data="workshopServicePriceTierForm(@js($service->priceTiers->map(fn($tier) => ['max_cc' => (int) $tier->max_cc, 'price' => (float) $tier->price])->values()->all()))">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-3">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Nombre del Servicio</label>
                        <input name="name" value="{{ $service->name }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Tipo de Servicio</label>
                        <x-form.select-autocomplete
                            name="type"
                            :value="$service->type"
                            :options="[['value' => 'preventivo', 'label' => 'Preventivo'], ['value' => 'correctivo', 'label' => 'Correctivo']]"
                            placeholder="Tipo"
                            :required="true"
                            inputClass="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Precio Base / sin cilindrada (S/)</label>
                        <input type="number" step="0.01" min="0" name="base_price" value="{{ (float) $service->base_price }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Opcional">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Tiempo Estimado (Minutos)</label>
                        <input type="number" min="0" name="estimated_minutes" value="{{ (int) $service->estimated_minutes }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Cada cuántos km</label>
                        <input type="number" min="1" name="frequency_each_km" value="{{ $service->frequency_each_km }}" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: 5000">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Frecuencia activa</label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="frequency_enabled" value="1" @checked((bool) $service->frequency_enabled) class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <span>Usar multiplicador por km</span>
                        </label>
                    </div>
                    <div x-data="{ hasValidity: {{ $service->has_validity ? 'true' : 'false' }} }" class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-5 rounded-2xl border border-gray-200 bg-emerald-50/30 p-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">¿Tiene vigencia?</label>
                            <p class="mb-2 text-xs text-gray-500">Permite registrar si el próximo servicio es en 6 meses o 1 año.</p>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-1">
                                <input type="checkbox" name="has_validity" value="1" x-model="hasValidity" class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                <span>Solicitar vigencia en tablero</span>
                            </label>
                        </div>
                        <div x-show="hasValidity" x-cloak x-transition>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Actualizar fecha de:</label>
                            <p class="mb-2 text-xs text-gray-500">Elige qué campo del vehículo se actualizará.</p>
                            <select name="validity_type" class="h-11 w-full rounded-xl border border-emerald-200 bg-white px-4 text-sm transition-all focus:border-emerald-500 focus:ring-emerald-500/10 focus:outline-none" :required="hasValidity">
                                <option value="">Selecciona fecha vinculada</option>
                                <option value="soat_vencimiento" @selected($service->validity_type === 'soat_vencimiento')>SOAT Vencimiento</option>
                                <option value="revision_tecnica_vencimiento" @selected($service->validity_type === 'revision_tecnica_vencimiento')>Rev. Técnica Vencimiento</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Estado</label>
                        <x-form.select-autocomplete
                            name="active"
                            :value="(int)$service->active"
                            :options="[['value' => 1, 'label' => 'Activo'], ['value' => 0, 'label' => 'Inactivo']]"
                            placeholder="Estado"
                            :required="true"
                            inputClass="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none"
                        />
                    </div>
                    <div class="md:col-span-3 rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Precios por cilindrada</label>
                                <p class="text-xs text-gray-500">Ordena los tramos de menor a mayor cilindrada maxima.</p>
                            </div>
                            <button type="button" @click="addRow()" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                <i class="ri-add-line"></i>
                                <span>Agregar tramo</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(tier, index) in rows" :key="tier.key">
                                <div class="rounded-2xl border border-gray-200 bg-white p-3" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) 44px;gap:0.75rem;align-items:end;">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta (cc)</label>
                                        <input type="number" min="1" step="1" :name="`price_tiers[${index}][max_cc]`" x-model="tier.max_cc" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="Ej: 250">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Precio (S/)</label>
                                        <input type="number" min="0" step="0.01" :name="`price_tiers[${index}][price]`" x-model="tier.price" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" placeholder="0.00">
                                    </div>
                                    <div class="flex items-end justify-end">
                                        <button type="button" @click="removeRow(index)" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="md:col-span-3 mt-4 flex items-center justify-end gap-3">
                        <x-ui.button type="button" size="md" variant="outline" class="rounded-xl px-6" @click="open = false">
                            <span>Cancelar</span>
                        </x-ui.button>
                        <x-ui.button type="submit" size="md" variant="primary" class="rounded-xl px-8" style="background-color: #00A389; border-color: #00A389;">
                            <span>Actualizar Servicio</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>

<script>
    function workshopServicePriceTierForm(initialRows = []) {
        return {
            rows: [],
            init() {
                const seededRows = Array.isArray(initialRows) ? initialRows : [];
                this.rows = seededRows.length
                    ? seededRows.map((row, index) => this.normalizeRow(row, index))
                    : [this.emptyRow()];
            },
            emptyRow() {
                return {
                    key: `${Date.now()}-${Math.random()}`,
                    max_cc: '',
                    price: '',
                };
            },
            normalizeRow(row, index) {
                return {
                    key: `${Date.now()}-${index}-${Math.random()}`,
                    max_cc: row?.max_cc ?? '',
                    price: row?.price ?? '',
                };
            },
            addRow() {
                this.rows.push(this.emptyRow());
            },
            removeRow(index) {
                if (this.rows.length === 1) {
                    this.rows = [this.emptyRow()];
                    return;
                }
                this.rows.splice(index, 1);
            },
        };
    }
</script>
@endsection
