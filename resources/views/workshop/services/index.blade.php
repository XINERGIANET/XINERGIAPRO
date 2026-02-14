@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">Taller - Catalogo de servicios</h1>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    <form method="GET" class="flex gap-2">
        <input name="search" value="{{ $search }}" class="rounded border px-3 py-2" placeholder="Buscar servicio">
        <button class="rounded bg-blue-600 px-3 py-2 text-white">Buscar</button>
    </form>

    <form method="POST" action="{{ route('workshop.services.store') }}" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-5">
        @csrf
        <input name="name" class="rounded border px-3 py-2" placeholder="Nombre" required>
        <select name="type" class="rounded border px-3 py-2" required>
            <option value="preventivo">preventivo</option>
            <option value="correctivo">correctivo</option>
        </select>
        <input type="number" step="0.01" min="0" name="base_price" class="rounded border px-3 py-2" placeholder="Precio base" required>
        <input type="number" min="0" name="estimated_minutes" class="rounded border px-3 py-2" placeholder="Minutos" required>
        <button class="rounded bg-emerald-600 px-3 py-2 text-white">Agregar</button>
    </form>

    <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Nombre</th>
                    <th class="p-2 text-left">Tipo</th>
                    <th class="p-2 text-left">Precio base</th>
                    <th class="p-2 text-left">Minutos</th>
                    <th class="p-2 text-left">Activo</th>
                    <th class="p-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $service)
                    <tr class="border-t">
                        <td class="p-2">{{ $service->name }}</td>
                        <td class="p-2">{{ $service->type }}</td>
                        <td class="p-2">{{ number_format((float)$service->base_price, 2) }}</td>
                        <td class="p-2">{{ $service->estimated_minutes }}</td>
                        <td class="p-2">{{ $service->active ? 'SI' : 'NO' }}</td>
                        <td class="p-2">
                            <form method="POST" action="{{ route('workshop.services.destroy', $service) }}" onsubmit="return confirm('Eliminar servicio?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded bg-red-600 px-2 py-1 text-white">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $services->links() }}
</div>
@endsection

