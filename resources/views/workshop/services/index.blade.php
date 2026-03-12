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
                <select name="per_page" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all" onchange="this.form.submit()">
                    <option value="10" @selected(($perPage ?? 10) == 10)>10 / página</option>
                    <option value="25" @selected(($perPage ?? 10) == 25)>25 / página</option>
                    <option value="50" @selected(($perPage ?? 10) == 50)>50 / página</option>
                    <option value="100" @selected(($perPage ?? 10) == 100)>100 / página</option>
                </select>
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
            <div class="ml-auto">
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
                        <select name="type" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" required>
                            <option value="preventivo" @selected($service->type === 'preventivo')>Preventivo</option>
                            <option value="correctivo" @selected($service->type === 'correctivo')>Correctivo</option>
                        </select>
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
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">Estado</label>
                        <select name="active" class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 text-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none" required>
                            <option value="1" @selected((int)$service->active === 1)>Activo</option>
                            <option value="0" @selected((int)$service->active === 0)>Inactivo</option>
                        </select>
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
