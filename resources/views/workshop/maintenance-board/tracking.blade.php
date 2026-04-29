@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-800">Seguimiento de Mantenimiento</h1>
            <p class="text-sm text-slate-500">Cronología detallada de la Orden de Servicio OS {{ $order->movement?->number ?? ('#' . $order->id) }}</p>
        </div>
        <a href="{{ route('workshop.maintenance-board.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:shadow-md">
            <i class="ri-arrow-left-line"></i> Volver al Tablero
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Columna Izquierda: Información General -->
        <div class="space-y-6">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-slate-800 p-4 text-white">
                    <h3 class="font-bold">Información del Vehículo</h3>
                </div>
                <div class="p-5">
                    <div class="mb-4 flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                            <i class="ri-motorbike-line text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-slate-800">{{ trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')) ?: 'Vehículo' }}</p>
                            <p class="text-sm font-medium text-slate-500">Placa: {{ $order->vehicle?->plate ?: 'S/PLACA' }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kilometraje Ent.</p>
                            <p class="text-sm font-semibold text-slate-700">{{ number_format($order->mileage_in) }} km</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fecha Ingreso</p>
                            <p class="text-sm font-semibold text-slate-700">{{ $order->intake_date?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-slate-800 p-4 text-white">
                    <h3 class="font-bold">Cliente</h3>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <i class="ri-user-smile-line text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-slate-800">{{ trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')) }}</p>
                            <p class="text-sm font-medium text-slate-500">{{ $order->client?->document_number }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if($order->finished_photo_path)
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-emerald-600 p-4 text-white">
                    <h3 class="font-bold">Evidencia Final</h3>
                </div>
                <div class="p-2">
                    <a href="{{ asset('storage/' . $order->finished_photo_path) }}" target="_blank" class="group relative block overflow-hidden rounded-xl">
                        <img src="{{ asset('storage/' . $order->finished_photo_path) }}" class="aspect-video w-full object-cover transition duration-500 group-hover:scale-110">

                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                            <i class="ri-zoom-in-line text-3xl text-white"></i>
                        </div>
                    </a>
                </div>
            </div>
            @endif
        </div>

        <!-- Columna Derecha: Línea de Tiempo -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-8 text-xl font-bold text-slate-800">Línea de Tiempo del Servicio</h3>

                <div class="relative space-y-8 before:absolute before:left-4 before:top-2 before:h-[calc(100%-16px)] before:w-0.5 before:bg-slate-100">
                    
                    <!-- Evento: Ingreso -->
                    <div class="relative pl-12">
                        <div class="absolute left-0 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-white ring-4 ring-white">
                            <i class="ri-login-box-line"></i>
                        </div>
                        <div>
                            <p class="text-sm font-extrabold text-slate-800">Ingreso a Taller</p>
                            <p class="text-[11px] font-bold text-slate-400">{{ $order->intake_date?->format('d/m/Y H:i:s') }}</p>
                            <p class="mt-2 text-sm text-slate-600">Se registra el ingreso del vehículo para diagnóstico inicial.</p>
                        </div>
                    </div>

                    @if($order->started_at)
                    <!-- Evento: Inicio -->
                    <div class="relative pl-12">
                        <div class="absolute left-0 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white ring-4 ring-white">
                            <i class="ri-play-circle-line"></i>
                        </div>
                        <div>
                            <p class="text-sm font-extrabold text-slate-800">Inicio de Trabajo</p>
                            <p class="text-[11px] font-bold text-slate-400">{{ $order->started_at?->format('d/m/Y H:i:s') }}</p>
                            <p class="mt-2 text-sm text-slate-600">El técnico asignado ha comenzado las labores en la unidad.</p>
                        </div>
                    </div>
                    @endif

                    @php
                        $histories = $order->statusHistories->sortBy('created_at');
                        $lastStatus = '';
                    @endphp

                    @foreach($histories as $history)
                        @php
                            $icon = 'ri-checkbox-blank-circle-line';
                            $color = 'bg-slate-400';
                            $title = 'Cambio de Estado';
                            $show = false;

                            if ($history->to_status === 'paused') {
                                $icon = 'ri-pause-circle-line';
                                $color = 'bg-amber-500';
                                $title = 'Servicio Pausado';
                                $show = true;
                            } elseif ($history->to_status === 'in_progress_external') {
                                $icon = 'ri-external-link-line';
                                $color = 'bg-purple-500';
                                $title = 'Inicio de Servicio Externo';
                                $show = true;
                            } elseif ($history->from_status === 'paused' && $history->to_status === 'in_progress') {
                                $icon = 'ri-play-line';
                                $color = 'bg-indigo-500';
                                $title = 'Servicio Reanudado';
                                $show = true;
                            } elseif ($history->to_status === 'finished') {
                                $icon = 'ri-checkbox-circle-line';
                                $color = 'bg-emerald-500';
                                $title = ($history->from_status === 'in_progress_external') ? 'Servicio Externo Finalizado' : 'Servicio Finalizado';
                                $show = true;
                            } elseif ($history->to_status === 'delivered') {

                                $icon = 'ri-truck-line';
                                $color = 'bg-blue-600';
                                $title = 'Vehículo Entregado';
                                $show = true;
                            }
                        @endphp

                        @if($show)
                        <div class="relative pl-12">
                            <div class="absolute left-0 top-1 flex h-8 w-8 items-center justify-center rounded-full {{ $color }} text-white ring-4 ring-white">
                                <i class="{{ $icon }}"></i>
                            </div>
                            <div>
                                <p class="text-sm font-extrabold text-slate-800">{{ $title }}</p>
                                <div class="flex items-center gap-2 text-[11px] font-bold text-slate-400">
                                    <span>{{ $history->created_at?->format('d/m/Y H:i:s') }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="text-slate-500"><i class="ri-user-line"></i> {{ $history->user?->name ?: 'Sistema' }}</span>
                                </div>
                                @if($history->note)
                                <div class="mt-2 rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm italic text-slate-600">
                                    "{{ $history->note }}"
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    @endforeach

                    @if($order->finished_at && !$histories->contains('to_status', 'finished'))
                    <div class="relative pl-12">
                        <div class="absolute left-0 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white ring-4 ring-white">
                            <i class="ri-checkbox-circle-line"></i>
                        </div>
                        <div>
                            <p class="text-sm font-extrabold text-slate-800">Servicio Finalizado</p>
                            <p class="text-[11px] font-bold text-slate-400">{{ $order->finished_at?->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
