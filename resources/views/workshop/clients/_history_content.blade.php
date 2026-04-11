@if (!empty($isModal))
<div class="mx-auto max-w-[1600px] space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start gap-4">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                <i class="ri-user-3-line text-2xl"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Historial técnico</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">
                    {{ trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: 'Cliente' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Documento: {{ $person->document_number ?: 'No registrado' }}
                    @if ($vehicles->count())
                        <span class="mx-2 text-slate-300">·</span>
                        {{ $vehicles->count() }} vehículo(s)
                    @endif
                </p>
            </div>
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-1 shadow-sm sm:p-2">
        @include('workshop.clients._history_technical')
    </section>
</div>
@else
<div class="{{ !empty($isModal) ? 'p-5 sm:p-6' : '' }}">
    <div class="mx-auto max-w-[1600px] space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                            <i class="ri-user-3-line text-[28px]"></i>
                        </span>
                        <div>
                            <h2 class="text-2xl font-black tracking-tight text-slate-900">
                                {{ trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: 'Cliente' }}
                            </h2>
                            <p class="text-sm font-medium text-slate-500">
                                Documento: {{ $person->document_number ?: 'No registrado' }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Teléfono</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $person->phone ?: 'No registrado' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Email</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $person->email ?: 'No registrado' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Dirección</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $person->address ?: 'No registrada' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Tipo</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $person->person_type ?: 'No registrado' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:max-w-[540px] xl:grid-cols-2">
                    <div class="rounded-3xl border border-blue-200 bg-blue-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-500">Órdenes</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">{{ $totalOrders }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Total registradas</p>
                    </div>
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-500">Pagadas</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">S/ {{ number_format($totalPaidOrders, 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Monto abonado</p>
                    </div>
                    <div class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-500">Pendiente</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">S/ {{ number_format($debtOrders, 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Deuda de órdenes</p>
                    </div>
                    <div class="rounded-3xl border border-violet-200 bg-violet-50/80 px-5 py-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-violet-500">Vehículos</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">{{ $vehicles->count() }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Asociados al cliente</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Vehículos</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Unidades asociadas</h3>
                    </div>
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        {{ $vehicles->count() }} registro(s)
                    </span>
                </div>

                @if ($vehicles->count())
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($vehicles as $vehicle)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-base font-bold text-slate-900">
                                            {{ trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: 'Vehículo sin nombre' }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium text-slate-500">
                                            Placa: {{ $vehicle->plate ?: 'Sin placa' }}
                                        </p>
                                    </div>
                                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-500 shadow-sm">
                                        {{ $vehicle->year ?: 'Año N/D' }}
                                    </span>
                                </div>
                                <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <p><span class="font-semibold text-slate-900">Color:</span> {{ $vehicle->color ?: 'No registrado' }}</p>
                                    <p><span class="font-semibold text-slate-900">Kilometraje:</span> {{ number_format((float) ($vehicle->current_mileage ?? 0), 0) }} km</p>
                                    <p><span class="font-semibold text-slate-900">VIN:</span> {{ $vehicle->vin ?: 'No registrado' }}</p>
                                    <p><span class="font-semibold text-slate-900">Serie:</span> {{ $vehicle->serial_number ?: 'No registrada' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-900">Sin vehículos registrados.</p>
                        <p class="mt-1 text-sm text-slate-500">Este cliente aún no tiene unidades asociadas.</p>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Citas</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Agenda reciente</h3>
                    </div>
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        {{ $appointments->count() }} registro(s)
                    </span>
                </div>

                @if ($appointments->count())
                    <div class="space-y-3">
                        @foreach ($appointments as $appointment)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">
                                            {{ optional($appointment->scheduled_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $appointment->comment ?: 'Sin comentario registrado.' }}
                                        </p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ ($appointment->status ?? '') === 'A' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ ($appointment->status ?? '') === 'A' ? 'Activa' : 'Registrada' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-900">Sin citas registradas.</p>
                        <p class="mt-1 text-sm text-slate-500">No se encontraron citas para este cliente.</p>
                    </div>
                @endif
            </section>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Mantenimientos</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Historial técnico</h3>
                    <p class="mt-1 text-sm text-slate-500">Incluye fecha del servicio, técnico responsable, observaciones y vehículo.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Órdenes</p>
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

            @if ($orders->count())
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-900 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Orden</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Vehículo</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Técnico responsable</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Observaciones</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($orders as $order)
                                    <tr class="align-top hover:bg-slate-50">
                                        <td class="px-4 py-4 text-sm font-semibold text-slate-700">
                                            {{ optional($order->created_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm font-bold text-slate-900">
                                            {{ $order->number ?: 'Sin número' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ trim(($order->vehicle->brand ?? '') . ' ' . ($order->vehicle->model ?? '')) ?: 'Sin vehículo' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $order->technician->person->first_name ?? $order->technician->name ?? 'No asignado' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            {{ $order->observation ?: 'Sin observaciones.' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                                {{ strtoupper(str_replace('_', ' ', (string) ($order->status ?: 'REGISTRADO'))) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-900">Sin mantenimientos registrados.</p>
                        <p class="mt-1 text-sm text-slate-500">No se encontraron órdenes de servicio para este cliente.</p>
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Ventas</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Comprobantes emitidos</h3>
                    </div>
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        S/ {{ number_format($totalSales, 2) }}
                    </span>
                </div>

                @if ($sales->count())
                    <div class="space-y-3">
                        @foreach ($sales as $sale)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">{{ optional($sale->date)->format('d/m/Y H:i') ?: 'Sin fecha' }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $sale->number ?: $sale->order ?: 'Sin comprobante' }}</p>
                                    </div>
                                    <p class="text-base font-black text-orange-600">S/ {{ number_format((float) ($sale->total ?? 0), 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-900">Sin ventas registradas.</p>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Pagos</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Movimientos recibidos</h3>
                    </div>
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        S/ {{ number_format($totalPayments, 2) }}
                    </span>
                </div>

                @if ($payments->count())
                    <div class="space-y-3">
                        @foreach ($payments as $payment)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">{{ optional($payment->date)->format('d/m/Y H:i') ?: 'Sin fecha' }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $payment->number ?: 'Pago registrado' }}</p>
                                    </div>
                                    <p class="text-base font-black text-emerald-600">S/ {{ number_format((float) ($payment->total ?? 0), 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-900">Sin pagos registrados.</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endif
