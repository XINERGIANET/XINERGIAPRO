@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Detalle por servicio" />

    <x-common.component-card title="Detalle por servicio" desc="Registra una lista simple de descripciones asociadas al servicio.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('workshop.services.details.update', $service) }}" class="space-y-4">
            @csrf

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3">
                    <p class="text-sm font-bold text-slate-900">Servicio: {{ $service->name }}</p>
                    <p class="text-xs text-slate-500">Agregue las descripciones que necesita tener disponibles para este servicio.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3">
                    <p class="text-sm font-bold text-slate-900">Agregar nuevo detalle</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Descripcion</label>
                        <input
                            type="text"
                            name="new_description"
                            maxlength="255"
                            class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm"
                            placeholder="Ej: Cambio de aceite, limpieza, regulacion..."
                        >
                    </div>
                    <div class="flex items-end">
                        <x-ui.button type="submit" size="md" variant="primary" className="h-11 px-6 rounded-xl font-bold" style="background-color:#22C55E;color:#fff">
                            <i class="ri-save-line"></i><span>Guardar cambios</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                <table class="w-full">
                    <thead class="bg-[#334155] text-white">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Descripcion</th>
                            <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($details as $detail)
                            <tr>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        name="descriptions[{{ $detail->id }}]"
                                        value="{{ $detail->description }}"
                                        maxlength="255"
                                        class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm"
                                        required
                                    >
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="submit" name="delete_ids[]" value="{{ $detail->id }}" class="inline-flex items-center justify-center rounded-lg bg-error-500 px-3 py-2 text-white hover:bg-error-600">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-3 py-8 text-center text-sm text-gray-400 italic">No hay detalles configurados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </x-common.component-card>
@endsection
