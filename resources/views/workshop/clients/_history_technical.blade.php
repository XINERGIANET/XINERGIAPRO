<section class="font-sans text-slate-800">
    @php
        $statusLabels = [
            'draft' => 'Borrador',
            'diagnosis' => 'Diagnostico',
            'awaiting_approval' => 'En espera de aprobacion',
            'approved' => 'Aprobado',
            'in_progress' => 'En progreso',
            'finished' => 'Finalizado',
            'delivered' => 'Entregado',
            'cancelled' => 'Anulado',
            'registered' => 'Registrado',
            'registrado' => 'Registrado',
        ];
    @endphp
 

    @if ($orders->count())
        <div class="overflow-hidden rounded-2xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-900 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Orden</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Vehiculo</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Tecnico responsable</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Observaciones</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.15em]">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @foreach ($orders as $order)
                            @php
                                $serviceLines = $order->details->where('line_type', 'SERVICE')->values();
                                $technicianNames = $order->technicians
                                    ->map(fn ($row) => trim((string) (($row->technician?->first_name ?? '') . ' ' . ($row->technician?->last_name ?? ''))))
                                    ->filter()
                                    ->values();

                                if ($technicianNames->isEmpty()) {
                                    $technicianNames = $serviceLines
                                        ->map(fn ($detail) => trim((string) (($detail->technician?->first_name ?? '') . ' ' . ($detail->technician?->last_name ?? ''))))
                                        ->filter()
                                        ->unique()
                                        ->values();
                                }

                                $orderNumber = $order->movement?->number ?: 'Sin numero';
                                $vehicleLabel = trim((string) (($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')));
                                $observationText = trim((string) ($order->observations ?? ''));
                                $statusKey = strtolower(str_replace(' ', '_', (string) ($order->status ?: 'registered')));
                                $statusLabel = $statusLabels[$statusKey] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusKey));
                            @endphp
                            <tr class="align-top border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-4 text-sm font-semibold text-slate-700">
                                    {{ optional($order->intake_date ?? $order->created_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}
                                </td>
                                <td class="px-4 py-4 text-sm font-bold text-slate-900">
                                    {{ $orderNumber }}
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700">
                                    {{ $vehicleLabel !== '' ? $vehicleLabel : 'Sin vehiculo' }}
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700">
                                    {{ $technicianNames->join(', ') ?: 'No asignado' }}
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600">
                                    <p>{{ $observationText !== '' ? $observationText : 'Sin observaciones.' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                        {{ strtoupper($statusLabel) }}
                                    </span>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100 bg-slate-50/70">
                                <td colspan="6" class="px-4 pb-4 pt-0">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Servicios realizados</p>
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500">
                                                {{ $serviceLines->count() }} item(s)
                                            </span>
                                        </div>

                                        @if ($serviceLines->count())
                                            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                                @foreach ($serviceLines as $detail)
                                                    @php
                                                        $detailTech = trim((string) (($detail->technician?->first_name ?? '') . ' ' . ($detail->technician?->last_name ?? '')));
                                                        $detailName = $detail->service?->name ?: $detail->description ?: 'Servicio';
                                                        $detailQty = (float) ($detail->qty ?? 0);
                                                        $detailQtyLabel = fmod($detailQty, 1.0) === 0.0
                                                            ? number_format($detailQty, 0, '.', '')
                                                            : rtrim(rtrim(number_format($detailQty, 2, '.', ''), '0'), '.');
                                                    @endphp
                                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <p class="text-sm font-semibold text-slate-900">{{ $detailName }}</p>
                                                            <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-500">
                                                                x{{ $detailQtyLabel }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                                                            @if ($detailTech !== '')
                                                                <span>Tecnico: {{ $detailTech }}</span>
                                                            @endif
                                                            <span>Total: S/ {{ number_format((float) ($detail->total ?? 0), 2) }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-400">
                                                Sin detalle de servicios.
                                            </div>
                                        @endif
                                    </div>
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
            <p class="mt-1 text-sm text-slate-500">No se encontraron ordenes de servicio para este cliente.</p>
        </div>
    @endif
</section>
