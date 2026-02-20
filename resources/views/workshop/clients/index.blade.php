@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Personas" />

    <x-common.component-card title="Personas" desc="Gestiona personas del taller (clientes y personal tecnico).">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-client-modal')">
                <i class="ri-add-line"></i><span>Nuevo cliente</span>
            </x-ui.button>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-5 dark:border-gray-800 dark:bg-white/[0.02]">
            <input name="search" value="{{ $search }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Buscar por nombre, DNI o RUC">
            <select name="type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <option value="">Todos</option>
                <option value="NATURAL" @selected($type === 'NATURAL')>Natural</option>
                <option value="CORPORATIVO" @selected($type === 'CORPORATIVO')>Corporativo</option>
            </select>
            <select name="role_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                <option value="">Rol: Todos</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((int)($roleId ?? 0) === (int)$role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button class="h-11 flex-1 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Buscar</button>
                <a href="{{ route('workshop.clients.index') }}" class="h-11 flex-1 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1050px]">
                <thead>
                    <tr>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">ID</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Documento</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cliente</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Telefono</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Correo</th>
                        <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 text-sm">{{ $client->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $client->person_type === 'RUC' ? 'CORPORATIVO' : 'NATURAL' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $client->person_type }}: {{ $client->document_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $client->first_name }} {{ $client->last_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $client->phone }}</td>
                            <td class="px-4 py-3 text-sm">{{ $client->email }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ route('workshop.clients.history', $client) }}" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-white">Historial</a>
                                    <button type="button" @click="$dispatch('open-edit-client-modal', {{ $client->id }})" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white">Editar</button>
                                    <form method="POST" action="{{ route('workshop.clients.destroy', $client) }}" onsubmit="return confirm('Eliminar cliente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-medium text-white">Eliminar</button>
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

        <div class="mt-4">{{ $clients->links() }}</div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" x-on:open-client-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cliente</h3>
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

                <form method="POST" action="{{ route('workshop.clients.update', $client) }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <select name="person_type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="DNI" @selected($client->person_type === 'DNI')>DNI</option>
                        <option value="RUC" @selected($client->person_type === 'RUC')>RUC</option>
                        <option value="CARNET DE EXTRANGERIA" @selected($client->person_type === 'CARNET DE EXTRANGERIA')>CARNET DE EXTRANGERIA</option>
                        <option value="PASAPORTE" @selected($client->person_type === 'PASAPORTE')>PASAPORTE</option>
                    </select>
                    <div class="md:col-span-2 flex items-center gap-2">
                        <input name="document_number" value="{{ $client->document_number }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Documento" required>
                        <button
                            type="button"
                            onclick="fetchReniecForClientEdit(this)"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
                        >
                            <i class="ri-search-line"></i>
                            <span class="ml-1">Buscar</span>
                        </button>
                    </div>
                    <input name="first_name" value="{{ $client->first_name }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombres / Razon social" required>
                    <input name="last_name" value="{{ $client->last_name }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Apellidos" required>
                    <p class="md:col-span-3 text-xs text-red-600 hidden js-reniec-error"></p>
                    <input name="phone" value="{{ $client->phone }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Telefono">
                    <input name="email" type="email" value="{{ $client->email }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Correo">
                    <input name="address" value="{{ $client->address }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Direccion" required>
                    <div class="md:col-span-3 mt-2 flex gap-2">
                        <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar cambios</span></x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endforeach
</div>
<script>
function splitReniecName(fullName) {
    const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length <= 1) return { first_name: parts[0] || '', last_name: '' };
    if (parts.length === 2) return { first_name: parts[0], last_name: parts[1] };
    if (parts.length === 3) return { first_name: parts[0], last_name: parts.slice(1).join(' ') };
    return { first_name: parts.slice(0, 2).join(' '), last_name: parts.slice(2).join(' ') };
}

async function fetchReniecForClientEdit(button) {
    const form = button.closest('form');
    if (!form) return;

    const personType = (form.querySelector('[name="person_type"]')?.value || '').toUpperCase();
    const documentInput = form.querySelector('[name="document_number"]');
    const firstNameInput = form.querySelector('[name="first_name"]');
    const lastNameInput = form.querySelector('[name="last_name"]');
    const errorNode = form.querySelector('.js-reniec-error');

    if (errorNode) {
        errorNode.classList.add('hidden');
        errorNode.textContent = '';
    }

    if (personType !== 'DNI') {
        if (errorNode) {
            errorNode.textContent = 'La busqueda RENIEC solo aplica para DNI.';
            errorNode.classList.remove('hidden');
        }
        return;
    }

    const dni = String(documentInput?.value || '').trim();
    if (!/^\d{8}$/.test(dni)) {
        if (errorNode) {
            errorNode.textContent = 'Ingrese un DNI valido de 8 digitos.';
            errorNode.classList.remove('hidden');
        }
        return;
    }

    const originalLabel = button.querySelector('span')?.textContent || 'Buscar';
    button.disabled = true;
    const labelNode = button.querySelector('span');
    if (labelNode) labelNode.textContent = 'Buscando...';

    try {
        const response = await fetch(`/api/reniec?dni=${encodeURIComponent(dni)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload?.status || !payload?.name) {
            throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
        }

        const parsed = splitReniecName(payload.name);
        if (firstNameInput) firstNameInput.value = parsed.first_name;
        if (lastNameInput) lastNameInput.value = parsed.last_name;
    } catch (error) {
        if (errorNode) {
            errorNode.textContent = error?.message || 'Error consultando RENIEC.';
            errorNode.classList.remove('hidden');
        }
    } finally {
        button.disabled = false;
        if (labelNode) labelNode.textContent = originalLabel;
    }
}
</script>
@endsection
