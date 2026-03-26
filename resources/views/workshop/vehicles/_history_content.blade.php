@php
    $client = $vehicle->client;
    $clientName = trim(((string) ($client->first_name ?? '')) . ' ' . ((string) ($client->last_name ?? '')));
    $vehicleName = trim(((string) ($vehicle->brand ?? '')) . ' ' . ((string) ($vehicle->model ?? '')));
@endphp

<div class="{{ !empty($isModal) ? 'p-5 sm:p-6' : '' }}">
    <div class="mx-auto max-w-[1600px] space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                            <i class="ri-bike-line text-[28px]"></i>
                        </span>
                        <div>
                            <h2 class="text-2xl font-black tracking-tight text-slate-900">
                                {{ $vehicleName !== '' ? $vehicleName : 'Vehiculo' }}
                            </h2>
                            <p class="text-sm font-medium text-slate-500">
                                Placa: {{ $vehicle->plate ?: 'Sin placa' }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Cliente</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $clientName !== '' ? $clientName : 'Clientes varios' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Cilindrada</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $vehicle->engine_displacement_cc ? $vehicle->engine_displacement_cc . ' cc' : 'No registrada' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Color</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $vehicle->color ?: 'No registrado' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Kilometraje</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format((float) ($vehicle->current_mileage ?? 0), 0) }} km</p>
                        </div>
                    </div>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:max-w-[540px] xl:grid-cols-2">
                    <div class="rounded-3xl border border-blue-200 bg-blue-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-500">Ordenes</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">{{ $totalOrdersCount }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Servicios del vehiculo</p>
                    </div>
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-500">Pagadas</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">S/ {{ number_format($totalPaidOrders, 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Monto abonado</p>
                    </div>
                    <div class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-500">Pendiente</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">S/ {{ number_format($debtOrders, 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Saldo del vehiculo</p>
                    </div>
                    <div class="rounded-3xl border border-violet-200 bg-violet-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-violet-500">Citas</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">{{ $appointments->count() }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Agenda registrada</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Mantenimientos</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Historial tecnico del vehiculo</h3>
                    <p class="mt-1 text-sm text-slate-500">Incluye fecha del servicio, tecnico responsable, observaciones y detalle realizado.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Ordenes</p>
                        <p class="mt-2 text-xl font-black text-slate-900">{{ $orders->count() }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Ventas</p>
                        <p class="mt-2 text-xl font-black text-slate-900">S/ {{ number_format($totalSales, 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Pagos</p>
                        <p class="mt-2 text-xl font-black text-slate-900">S/ {{ number_format($totalPayments, 2) }}</p>
                    </div>
                </div>
            </div>

            @include('workshop.clients._history_technical')
        </section>
    </div>
</div>
