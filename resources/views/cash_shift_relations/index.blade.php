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
            <div class="w-36 flex-none">
                <select
                    name="per_page"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    onchange="this.form.submit()"
                >
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }} / página</option>
                    @endforeach
                </select>
            </div>

            <div class="relative flex-1">
                 <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="ri-search-line"></i>
                </span>
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Buscar por caja, serie, turno o ID"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-5 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 text-gray-100" style="background-color: #334155; border-color: #334155;">
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

        <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1000px] border-collapse relative">
                <thead>
                    <tr class="text-white">
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 first:rounded-tl-xl font-semibold text-[11px] uppercase whitespace-nowrap">
                            F. inicio
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            F. fin
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            Nro. de apertura
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            Caja
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            Turno
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-left sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            Detalle de turno
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 font-semibold text-[11px] uppercase whitespace-nowrap">
                            Situación
                        </th>
                        <th style="background-color: #334155; color: #FFFFFF;" class="px-4 py-4 text-center sm:px-5 last:rounded-tr-xl font-semibold text-[11px] uppercase whitespace-nowrap">
                            Operaciones
                        </th>
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
                        <tr class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 transition">
                            <td class="px-4 py-4 sm:px-5 text-xs text-gray-700 align-middle text-center">
                                <div class="flex flex-col items-center justify-center gap-0.5">
                                    <div class="text-gray-700 font-medium">{{ $startDoc?->format('d-m-Y') ?? '-' }}</div>
                                    <div class="text-gray-500 font-medium">{{ $startDoc?->format('H:i:s') ?? '-' }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs text-gray-700 align-middle text-center">
                                @if ($endDoc)
                                    <div class="text-gray-700 font-medium">{{ $endDoc->format('d-m-Y') }}</div>
                                    <div class="text-gray-500 font-medium">{{ $endDoc->format('H:i:s') }}</div>
                                @else
                                    <x-ui.badge variant="light" color="success" class="text-[10px] px-2 py-0.5">En Curso</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs font-bold text-gray-800 dark:text-white/90 align-middle text-center">
                                {{ $openingNumber }}
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs text-gray-700 align-middle text-center dark:text-gray-300">
                                <span class="text-gray-600 font-medium">{{ $cashRegister?->number ?? '-' }}</span>
                                @if (!empty($cashRegister?->series))
                                    <span class="text-gray-400 block text-[10px]">({{ $cashRegister->series }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs text-gray-700 align-middle text-center dark:text-gray-300">
                                {{ $shift?->description ?? '-' }}
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs text-gray-700 align-middle">
                                <div class="space-y-2">
                                    @foreach (($relation->turn_summary ?? collect()) as $entry)
                                        <div class="space-y-0.5">
                                            <p class="font-bold text-gray-800 dark:text-white/90 flex items-center gap-1.5">
                                                <i class="{{ $entry['icon'] ?? 'ri-file-list-line' }} text-brand-600 text-sm"></i>
                                                {{ $entry['label'] }} (PEN {{ number_format((float) ($entry['total'] ?? 0), 1) }}):
                                            </p>
                                            @foreach (($entry['details'] ?? []) as $detail)
                                                <p class="ml-6 text-[10px] text-gray-500 flex items-center gap-2">
                                                    <span class="w-1 h-1 rounded-full bg-gray-200"></span>
                                                    {{ $detail['method'] }}: {{ number_format((float) ($detail['amount'] ?? 0), 1) }}
                                                </p>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4 sm:px-5 text-xs text-center align-middle">
                                @if ((string) $relation->status === '1')
                                    <x-ui.badge variant="light" color="success" class="text-[10px] px-2 py-0.5">Activado</x-ui.badge>
                                @else
                                    <x-ui.badge variant="light" color="gray" class="text-[10px] px-2 py-0.5">Cerrado</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-4 sm:px-5 align-middle">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ $showUrl }}" target="_blank" class="inline-flex h-9 w-9 items-center justify-center rounded-full shadow-sm transition-all duration-200 hover:opacity-90" style="background-color: #ef4444; color: #ffffff;" title="PDF">
                                        <i class="ri-file-pdf-2-line text-lg"></i>
                                    </a>
                                    <a href="{{ $showUrl }}" target="_blank" onclick="setTimeout(() => window.print(), 250);" class="inline-flex h-9 w-9 items-center justify-center rounded-full shadow-sm transition-all duration-200 hover:opacity-90" style="background-color: #8b5cf6; color: #ffffff;" title="Imprimir">
                                        <i class="ri-printer-line text-lg"></i>
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

