@extends('layouts.app')

@php
    use Illuminate\Support\Carbon;
    $viewId = request('view_id');
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Relación Caja - Turno" />

    <x-common.component-card title="Aperturas y cierres de caja" desc="Historial de turnos de caja por sucursal.">
        <form method="GET" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            @if ($viewId)
                <input type="hidden" name="view_id" value="{{ $viewId }}">
            @endif
            <div class="w-full lg:w-28">
                <select
                    name="per_page"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:outline-hidden"
                    onchange="this.form.submit()"
                >
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }} / página</option>
                    @endforeach
                </select>
            </div>

            <div class="relative flex-1">
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Buscar por caja, serie, turno o ID"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:outline-hidden"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5">
                    <i class="ri-search-line"></i>
                    <span>Buscar</span>
                </x-ui.button>
                <x-ui.link-button
                    size="md"
                    variant="outline"
                    href="{{ route('admin.cash-shift-relations.index', $viewId ? ['view_id' => $viewId] : []) }}"
                    class="h-11 px-5"
                >
                    <i class="ri-refresh-line"></i>
                    <span>Limpiar</span>
                </x-ui.link-button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="w-full min-w-[1400px]">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">F. inicio</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">F. fin</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">Nro. de apertura</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">Caja</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">Turno</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">Detalle de turno</th>
                        <th class="bg-slate-700 px-4 py-3 text-left text-xs font-semibold text-white">Situación</th>
                        <th class="bg-slate-700 px-4 py-3 text-center text-xs font-semibold text-white">Operaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($relations as $relation)
                        @php
                            $startMovement = $relation->cashMovementStart;
                            $endMovement = $relation->cashMovementEnd;
                            $cashRegister = $startMovement?->cashRegister;
                            $shift = $startMovement?->shift;
                            $startDoc = $relation->started_at ? Carbon::parse($relation->started_at) : null;
                            $endDoc = $relation->ended_at ? Carbon::parse($relation->ended_at) : null;
                            $openingNumber = $startMovement?->movement?->number ?? str_pad((string) $relation->id, 8, '0', STR_PAD_LEFT);
                            $showUrl = ($startMovement?->movement_id && $startMovement?->cash_register_id)
                                ? route('admin.petty-cash.show', ['cash_register_id' => $startMovement->cash_register_id, 'movement' => $startMovement->movement_id] + ($viewId ? ['view_id' => $viewId] : []))
                                : '#';
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                <div class="flex items-start gap-2">
                                    <i class="ri-arrow-right-s-fill mt-0.5 text-gray-400"></i>
                                    <div>
                                        <div>{{ $startDoc?->format('Y-m-d') ?? '-' }}</div>
                                        <div class="text-gray-500">{{ $startDoc?->format('h:i:s A') ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                @if ($endDoc)
                                    <div>{{ $endDoc->format('Y-m-d') }}</div>
                                    <div class="text-gray-500">{{ $endDoc->format('h:i:s A') }}</div>
                                @else
                                    <x-ui.badge variant="light" color="success">En curso</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm font-semibold text-gray-700 align-top">{{ $openingNumber }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $cashRegister?->number ?? '-' }}
                                @if (!empty($cashRegister?->series))
                                    <span class="text-gray-400">({{ $cashRegister->series }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $shift?->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <div class="space-y-2">
                                    @foreach (($relation->turn_summary ?? collect()) as $entry)
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <i class="{{ $entry['icon'] ?? 'ri-file-list-line' }} mr-1"></i>
                                                {{ $entry['label'] }} (PEN {{ number_format((float) ($entry['total'] ?? 0), 1) }}):
                                            </p>
                                            @foreach (($entry['details'] ?? []) as $detail)
                                                <p class="ml-6 text-gray-700">
                                                    {{ $detail['method'] }}: {{ number_format((float) ($detail['amount'] ?? 0), 1) }}
                                                    @if (!empty($detail['suffix']))
                                                        <span class="text-gray-500">({{ $detail['suffix'] }})</span>
                                                    @endif
                                                </p>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm align-top">
                                @if ((string) $relation->status === '1')
                                    <x-ui.badge variant="light" color="success">Activado</x-ui.badge>
                                @else
                                    <x-ui.badge variant="light" color="gray">Cerrado</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ $showUrl }}" target="_blank" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600" title="PDF">
                                        <i class="ri-file-pdf-2-line"></i>
                                    </a>
                                    <a href="{{ $showUrl }}" target="_blank" onclick="setTimeout(() => window.print(), 250);" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-purple-600 text-white hover:bg-purple-700" title="Imprimir">
                                        <i class="ri-printer-line"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">
                                No hay relaciones de caja-turno registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700">{{ $relations->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700">{{ $relations->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700">{{ $relations->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $relations->links('vendor.pagination.forced') }}
            </div>
        </div>
    </x-common.component-card>
@endsection
