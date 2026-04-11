@extends('layouts.app')

@section('content')
<div>
    <x-common.page-breadcrumb pageTitle="Personas Taller" />

    <x-common.component-card title="Personas Taller" desc="Gestiona personas del taller (clientes y personal tecnico).">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        {{-- Barra de Herramientas Premium --}}
        <form method="GET" action="{{ route('workshop.clients.index') }}" class="mb-5 flex flex-wrap items-center gap-3">
            @if (request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif

            {{-- Selector de Registros --}}
            <div class="w-32 flex-none">
                <select name="per_page" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all" onchange="this.form.submit()">
                    <option value="10" @selected(($per_page ?? 10) == 10)>10 / página</option>
                    <option value="25" @selected(($per_page ?? 10) == 25)>25 / página</option>
                    <option value="50" @selected(($per_page ?? 10) == 50)>50 / página</option>
                    <option value="100" @selected(($per_page ?? 10) == 100)>100 / página</option>
                </select>
            </div>

            {{-- Buscador Principal --}}
            <div class="relative flex-1 min-w-[200px] sm:min-w-[300px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <i class="ri-search-line text-gray-400"></i>
                </div>
                <input 
                    name="search" 
                    value="{{ $search }}" 
                    class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm shadow-sm transition-all focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none placeholder:text-gray-400" 
                    placeholder="Buscar por nombre, documento..."
                >
            </div>

            {{-- Filtro de Tipo --}}
            <div class="w-40 flex-none">
                <select name="type" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all">
                    <option value="">Tipo: Todos</option>
                    <option value="NATURAL" @selected(($type ?? '') === 'NATURAL')>Natural</option>
                    <option value="CORPORATIVO" @selected(($type ?? '') === 'CORPORATIVO')>Corporativo</option>
                </select>
            </div>

            {{-- Filtro de Rol --}}
            <div class="w-44 flex-none">
                <select name="role_id" class="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 shadow-sm focus:border-brand-500 focus:ring-brand-500/10 focus:outline-none transition-all">
                    <option value="0">Rol: Todos</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(($roleId ?? 0) == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2">
                <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5 shadow-sm active:scale-95 transition-all" style="background-color: #334155; border-color: #334155;">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.clients.index') }}" class="h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 transition-all active:scale-95">
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
                    @click="$dispatch('open-client-modal')"
                >
                    <i class="ri-add-line text-lg"></i>
                    <span>Nueva Persona</span>
                </x-ui.button>
            </div>
        </form>

        <div class="table-responsive lg:!overflow-visible mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full">
                <thead style="background-color: #334155; color: #FFFFFF;">
                    <tr>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider first:rounded-tl-xl text-white">ID</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Documento</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Nombres</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Teléfono</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Correo</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-white">Rol</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider last:rounded-tr-xl text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr class="border-t border-gray-100 dark:border-gray-800 transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $client->id }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                <x-ui.badge variant="light" color="{{ $client->person_type === 'RUC' ? 'info' : 'success' }}">
                                    {{ $client->person_type === 'RUC' ? 'Corporativo' : 'Natural' }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-3 text-sm text-center align-middle whitespace-nowrap">{{ $client->person_type }}: {{ $client->document_number }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle font-medium text-gray-800 dark:text-white/90">{{ $client->first_name }} {{ $client->last_name }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $client->phone ?: '-' }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">{{ $client->email ?: '-' }}</td>
                            <td class="px-3 py-3 text-sm text-center align-middle">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @foreach($client->roles as $role)
                                        <x-ui.badge variant="light" color="secondary" class="text-[10px] py-0.5">
                                            {{ $role->name }}
                                        </x-ui.badge>
                                    @endforeach
                                    @if($client->roles->isEmpty())
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="relative group hover:z-[100]">
                                        <x-ui.button
                                            size="icon"
                                            variant="primary"
                                            type="button"
                                            @click="$dispatch('open-workshop-client-history', { url: {{ \Illuminate\Support\Js::from(route('workshop.clients.history', $client) . '?modal=1') }} })"
                                            className="rounded-xl"
                                            style="background-color: #334155; color: #FFFFFF;"
                                            aria-label="Ver historial"
                                        >
                                            <i class="ri-history-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[1000] shadow-xl">
                                            Historial
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <div class="relative group hover:z-[100]">
                                        <x-ui.button
                                            size="icon"
                                            variant="edit"
                                            type="button"
                                            @click="$dispatch('open-edit-client-modal', {{ $client->id }})"
                                            className="rounded-xl"
                                            style="background-color: #FBBF24; color: #111827;"
                                            aria-label="Editar"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </x-ui.button>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[1000] shadow-xl">
                                            Editar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('workshop.clients.destroy', $client) }}"
                                        class="relative group hover:z-[100] js-swal-delete"
                                        data-swal-title="Eliminar cliente?"
                                        data-swal-text="Se eliminara este cliente. Esta accion no se puede deshacer."
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
                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[1000] shadow-xl">
                                            Eliminar
                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                        </span>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-4 text-sm text-gray-500">Sin clientes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN INFERIOR --}}
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $clients->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $clients->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $clients->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $clients->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-client-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar Persona</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.clients.store') }}" class="space-y-6">
                @csrf
                @include('branches.people._form', ['person' => null])
                <div class="mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @foreach($clients as $client)
        <x-ui.modal
            x-data="{ open: false }"
            x-on:open-edit-client-modal.window="if ($event.detail === {{ $client->id }}) { open = true }"
            :isOpen="false"
            :showCloseButton="false"
            class="max-w-6xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar cliente</h3>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('workshop.clients.update', $client) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    @include('branches.people._form', [
                        'person' => $client,
                        'selectedRoleIds' => $client->roles->pluck('id')->toArray(),
                        'userName' => $client->user?->name,
                        'selectedProfileId' => $client->user?->profile_id
                    ])

                    <div class="mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach

    <x-ui.modal
        x-data="{ open: false, historyUrl: '' }"
        x-on:open-workshop-client-history.window="historyUrl = $event.detail.url; open = true"
        :isOpen="false"
        :showCloseButton="false"
        class="max-w-7xl">
        <div class="p-4 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Historial del cliente</h3>
                <button type="button" @click="open = false; historyUrl = ''" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <template x-if="historyUrl">
                <iframe x-bind:src="historyUrl" class="h-[75vh] w-full rounded-2xl border border-gray-200 bg-white"></iframe>
            </template>
        </div>
    </x-ui.modal>
</div>
<script>
</script>
@endsection


