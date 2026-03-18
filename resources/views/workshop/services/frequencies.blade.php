@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Frecuencia por servicio" />

    <x-common.component-card title="Frecuencia por servicio" desc="Configura cada cuántos km y el multiplicador (frecuencia) según el kilometraje del vehículo.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('workshop.services.frequencies.update', $service) }}" class="space-y-4">
            @csrf

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3 flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Servicio: {{ $service->name }}</p>
                        <p class="text-xs text-slate-500">Si no esta habilitada la frecuencia, no se usa el multiplicador.</p>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            name="frequency_enabled"
                            value="1"
                            @checked((bool) $service->frequency_enabled)
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        Frecuencia activa
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cada cuántos km (referencia)</label>
                        <input
                            type="number"
                            min="1"
                            name="frequency_each_km"
                            value="{{ $service->frequency_each_km }}"
                            class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm"
                            placeholder="Ej: 5000"
                        >
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3">
                    <p class="text-sm font-bold text-slate-900">Agregar nueva frecuencia</p>
                    <p class="text-xs text-slate-500">Ej: Km 5000 -> multiplicador 1.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Km</label>
                        <input type="number" min="1" name="new_km" class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm" placeholder="Ej: 10000">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Frecuencia (multiplicador)</label>
                        <input type="number" min="0" step="0.01" name="new_multiplier" class="h-11 w-full rounded-xl border border-gray-200 px-4 text-sm" placeholder="Ej: 1.5">
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
                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Km</th>
                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider">Frecuencia (multiplicador)</th>
                            <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($frequencies as $freq)
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="number" min="1" name="kms[{{ $freq->id }}]" value="{{ $freq->km }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm" required>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" step="0.01" name="multipliers[{{ $freq->id }}]" value="{{ (float) $freq->multiplier }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm" required>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="submit" name="delete_ids[]" value="{{ $freq->id }}" class="inline-flex items-center justify-center rounded-lg bg-error-500 px-3 py-2 text-white hover:bg-error-600">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-sm text-gray-400 italic">No hay frecuencias configuradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            
        </form>
    </x-common.component-card>
@endsection

