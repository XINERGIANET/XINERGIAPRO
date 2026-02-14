@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">OS {{ $order->movement?->number }} - {{ $order->status }}</h1>
        <div class="flex gap-2">
            <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.order', $order) }}" target="_blank">PDF OS</a>
            <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.activation', $order) }}" target="_blank">PDF GP</a>
            <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.pdi', $order) }}" target="_blank">PDF PDI</a>
            <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.maintenance', $order) }}" target="_blank">PDF Mant.</a>
            <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.parts', $order) }}" target="_blank">PDF Repuestos</a>
            @if($order->sale)
                <a class="rounded bg-slate-700 px-3 py-2 text-white" href="{{ route('workshop.pdf.internal-sale', $order) }}" target="_blank">PDF Venta</a>
            @endif
            <form method="POST" action="{{ route('workshop.pdf.order.save', $order) }}">
                @csrf
                <button class="rounded bg-slate-900 px-3 py-2 text-white">Guardar snapshot PDF</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-300 bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 rounded border p-4 md:grid-cols-2">
        <div><strong>Cliente:</strong> {{ $order->client?->first_name }} {{ $order->client?->last_name }}</div>
        <div><strong>Vehiculo:</strong> {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</div>
        <div><strong>Km ingreso:</strong> {{ $order->mileage_in }}</div>
        <div><strong>Km salida:</strong> {{ $order->mileage_out }}</div>
        <div><strong>Aprobacion:</strong> {{ $order->approval_status ?? 'pending' }}</div>
        <div><strong>Pago:</strong> {{ $order->payment_status ?? 'pending' }}</div>
        <div><strong>Total:</strong> {{ number_format((float) $order->total, 2) }}</div>
        <div><strong>Pagado:</strong> {{ number_format((float) $order->paid_total, 2) }} | <strong>Deuda:</strong> {{ number_format(max(0, (float)$order->total - (float)$order->paid_total), 2) }}</div>
        <div class="md:col-span-2"><strong>Técnicos asignados:</strong>
            {{ $order->technicians->map(fn($row) => trim(($row->technician?->first_name ?? '').' '.($row->technician?->last_name ?? '')))->filter()->join(', ') ?: 'Sin asignar' }}
        </div>
    </div>

    <form method="POST" action="{{ route('workshop.orders.update', $order) }}" class="grid grid-cols-1 gap-2 rounded border p-4 md:grid-cols-3">
        @csrf
        @method('PUT')
        <input type="datetime-local" name="intake_date" value="{{ optional($order->intake_date)->format('Y-m-d\TH:i') }}" class="rounded border px-3 py-2" required>
        <input type="datetime-local" name="delivery_date" value="{{ optional($order->delivery_date)->format('Y-m-d\TH:i') }}" class="rounded border px-3 py-2">
        <select name="status" class="rounded border px-3 py-2">
            @foreach(['draft','diagnosis','awaiting_approval','approved','in_progress','finished','delivered','cancelled'] as $status)
                <option value="{{ $status }}" @selected($order->status === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <input name="mileage_in" type="number" min="0" value="{{ $order->mileage_in }}" class="rounded border px-3 py-2" placeholder="KM ingreso">
        <input name="mileage_out" type="number" min="0" value="{{ $order->mileage_out }}" class="rounded border px-3 py-2" placeholder="KM salida">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="tow_in" value="1" @checked($order->tow_in)> Ingreso en grua</label>
        <textarea name="diagnosis_text" class="rounded border px-3 py-2 md:col-span-3" rows="2" placeholder="Diagnostico">{{ $order->diagnosis_text }}</textarea>
        <textarea name="observations" class="rounded border px-3 py-2 md:col-span-3" rows="2" placeholder="Observaciones">{{ $order->observations }}</textarea>
        <button class="rounded bg-blue-600 px-3 py-2 text-white md:col-span-3">Actualizar datos generales</button>
    </form>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('workshop.orders.quotation', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Cotización</h2>
            <input name="note" class="mb-2 w-full rounded border px-3 py-2" placeholder="Nota de cotizacion para cliente">
            <button class="rounded bg-teal-700 px-3 py-2 text-white">Generar cotización</button>
        </form>

        <form method="POST" action="{{ route('workshop.orders.approve', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Aprobacion</h2>
            <div class="grid grid-cols-1 gap-2">
                <select name="decision" class="rounded border px-3 py-2">
                    <option value="approved">Aprobado</option>
                    <option value="partial">Aprobado parcial</option>
                    <option value="rejected">Rechazado</option>
                </select>
                <input name="approval_note" class="rounded border px-3 py-2" placeholder="Nota de aprobacion/rechazo">
                <button class="rounded bg-indigo-600 px-3 py-2 text-white">Registrar decision</button>
            </div>
        </form>

        <form method="POST" action="{{ route('workshop.orders.intake.update', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Inventario de recepcion (SI/NO)</h2>
            <div class="grid grid-cols-2 gap-2">
                @foreach(['ESPEJOS','FARO_DELANTERO','LLAVES','BATERIA','DOCUMENTOS'] as $item)
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="inventory[{{ $item }}]" value="1" @checked((bool) optional($order->intakeInventory->firstWhere('item_key', $item))->present)>{{ $item }}</label>
                @endforeach
            </div>
            <h3 class="mt-3 font-medium">Daño preexistente</h3>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <select name="damages[0][side]" class="rounded border px-2 py-1">
                    <option value="RIGHT">RIGHT</option>
                    <option value="LEFT">LEFT</option>
                    <option value="FRONT">FRONT</option>
                    <option value="BACK">BACK</option>
                </select>
                <input name="damages[0][description]" class="rounded border px-2 py-1" placeholder="Descripcion">
                <select name="damages[0][severity]" class="rounded border px-2 py-1">
                    <option value="LOW">LOW</option>
                    <option value="MED">MED</option>
                    <option value="HIGH">HIGH</option>
                </select>
            </div>
            <button class="mt-3 rounded bg-slate-700 px-3 py-2 text-white">Guardar inspeccion</button>
        </form>
    </div>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Agregar linea (servicio/mano de obra/repuesto/otros)</h2>
        <form method="POST" action="{{ route('workshop.orders.details.store', $order) }}" class="grid grid-cols-1 gap-2 md:grid-cols-4">
            @csrf
            <select name="line_type" class="rounded border px-3 py-2" required>
                <option value="SERVICE">SERVICE</option>
                <option value="LABOR">LABOR</option>
                <option value="PART">PART</option>
                <option value="OTHER">OTHER</option>
            </select>
            <select name="service_id" class="rounded border px-3 py-2">
                <option value="">Servicio (opcional)</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            <select name="product_id" class="rounded border px-3 py-2">
                <option value="">Repuesto (opcional)</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->description }}</option>
                @endforeach
            </select>
            <select name="tax_rate_id" class="rounded border px-3 py-2">
                <option value="">Impuesto</option>
                @foreach($taxRates as $tax)
                    <option value="{{ $tax->id }}">{{ $tax->description }} ({{ $tax->tax_rate }}%)</option>
                @endforeach
            </select>
            <input name="description" class="rounded border px-3 py-2 md:col-span-2" placeholder="Descripcion" required>
            <input name="qty" type="number" step="0.000001" min="0.000001" class="rounded border px-3 py-2" value="1" required>
            <input name="unit_price" type="number" step="0.000001" min="0" class="rounded border px-3 py-2" value="0" required>
            <select name="technician_person_id" class="rounded border px-3 py-2">
                <option value="">Tecnico</option>
                @foreach($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
                @endforeach
            </select>
            <button class="rounded bg-emerald-600 px-3 py-2 text-white md:col-span-4">Agregar linea</button>
        </form>

        <div class="mt-4 overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Descripcion</th>
                        <th class="p-2 text-left">Cant</th>
                        <th class="p-2 text-left">P.Unit</th>
                        <th class="p-2 text-left">Total</th>
                        <th class="p-2 text-left">Stock</th>
                        <th class="p-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->details as $detail)
                        <tr class="border-t">
                            <td class="p-2">{{ $detail->line_type }}</td>
                            <td class="p-2">{{ $detail->description }}</td>
                            <td class="p-2">{{ $detail->qty }}</td>
                            <td class="p-2">{{ number_format((float) $detail->unit_price, 2) }}</td>
                            <td class="p-2">{{ number_format((float) $detail->total, 2) }}</td>
                            <td class="p-2">{{ $detail->stock_status ?? ($detail->stock_consumed ? 'CONSUMIDO' : 'PENDIENTE') }}</td>
                            <td class="p-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('workshop.orders.details.update', [$order, $detail]) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" step="0.000001" min="0.000001" name="qty" value="{{ $detail->qty }}" class="w-20 rounded border px-1 py-1">
                                    <input type="number" step="0.000001" min="0" name="unit_price" value="{{ $detail->unit_price }}" class="w-24 rounded border px-1 py-1">
                                    <input type="hidden" name="description" value="{{ $detail->description }}">
                                    <input type="hidden" name="discount_amount" value="{{ $detail->discount_amount ?? 0 }}">
                                    <input type="hidden" name="tax_rate_id" value="{{ $detail->tax_rate_id }}">
                                    <input type="hidden" name="technician_person_id" value="{{ $detail->technician_person_id }}">
                                    <button class="rounded bg-blue-700 px-2 py-1 text-white">Actualizar</button>
                                </form>
                                @if($detail->line_type === 'PART' && !$detail->stock_consumed)
                                    <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">
                                        @csrf
                                        <input type="hidden" name="detail_id" value="{{ $detail->id }}">
                                        <input type="hidden" name="action" value="reserve">
                                        <button class="rounded bg-cyan-700 px-2 py-1 text-white">Reservar</button>
                                    </form>
                                    <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">
                                        @csrf
                                        <input type="hidden" name="detail_id" value="{{ $detail->id }}">
                                        <input type="hidden" name="action" value="release">
                                        <button class="rounded bg-slate-500 px-2 py-1 text-white">Liberar reserva</button>
                                    </form>
                                    <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">
                                        @csrf
                                        <input type="hidden" name="detail_id" value="{{ $detail->id }}">
                                        <input type="hidden" name="action" value="consume">
                                        <button class="rounded bg-amber-600 px-2 py-1 text-white">Consumir stock</button>
                                    </form>
                                @endif
                                @if($detail->line_type === 'PART' && $detail->stock_consumed)
                                    <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">
                                        @csrf
                                        <input type="hidden" name="detail_id" value="{{ $detail->id }}">
                                        <input type="hidden" name="action" value="return">
                                        <button class="rounded bg-rose-600 px-2 py-1 text-white">Devolver repuesto</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('workshop.orders.details.destroy', [$order, $detail]) }}" onsubmit="return confirm('Eliminar linea?')">
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
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('workshop.orders.technicians.assign', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Tecnicos de la OS</h2>
            @php($assignedTechs = $order->technicians->values())
            <div class="space-y-2">
                @for($i = 0; $i < 3; $i++)
                    @php($assigned = $assignedTechs->get($i))
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <select name="technicians[{{ $i }}][technician_person_id]" class="rounded border px-3 py-2">
                            <option value="">Tecnico</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" @selected((int)($assigned->technician_person_id ?? 0) === (int)$tech->id)>{{ $tech->first_name }} {{ $tech->last_name }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.0001" min="0" name="technicians[{{ $i }}][commission_percentage]" class="rounded border px-3 py-2" placeholder="% comision" value="{{ $assigned->commission_percentage ?? '' }}">
                    </div>
                @endfor
            </div>
            <button class="mt-2 rounded bg-slate-700 px-3 py-2 text-white">Guardar tecnicos</button>
        </form>

        <form method="POST" action="{{ route('workshop.orders.sale', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Generar venta desde OS</h2>
            <select name="document_type_id" class="mb-2 w-full rounded border px-3 py-2" required>
                <option value="">Tipo de documento</option>
                @foreach($documentTypes as $documentType)
                    <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                @endforeach
            </select>
            <label class="mb-2 block text-sm font-medium">Lineas a facturar (vacío = todas pendientes)</label>
            <div class="mb-2 max-h-40 overflow-auto rounded border p-2 text-sm">
                @foreach($order->details as $detail)
                    @if(!$detail->sales_movement_id)
                        <label class="flex items-center gap-2 py-1">
                            <input type="checkbox" name="detail_ids[]" value="{{ $detail->id }}">
                            <span>{{ $detail->line_type }} - {{ $detail->description }} ({{ number_format((float)$detail->total,2) }})</span>
                        </label>
                    @endif
                @endforeach
            </div>
            <input name="comment" class="mb-2 w-full rounded border px-3 py-2" placeholder="Comentario venta">
            <button class="rounded bg-purple-700 px-3 py-2 text-white">Generar Venta</button>
        </form>

        <form method="POST" action="{{ route('workshop.orders.warranty.store', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Registrar garantía</h2>
            <select name="workshop_movement_detail_id" class="mb-2 w-full rounded border px-3 py-2">
                <option value="">Toda la OS</option>
                @foreach($order->details as $detail)
                    <option value="{{ $detail->id }}">{{ $detail->line_type }} - {{ $detail->description }}</option>
                @endforeach
            </select>
            <input type="number" min="1" max="3650" name="days" class="mb-2 w-full rounded border px-3 py-2" placeholder="Días de garantía" value="30" required>
            <input name="note" class="mb-2 w-full rounded border px-3 py-2" placeholder="Nota de garantía">
            <button class="rounded bg-indigo-700 px-3 py-2 text-white">Registrar garantía</button>
            <div class="mt-2 text-sm text-gray-700">
                @foreach($order->warranties as $warranty)
                    <div class="border-t py-1">
                        {{ $warranty->starts_at?->format('Y-m-d') }} a {{ $warranty->ends_at?->format('Y-m-d') }}
                        @if($warranty->detail)
                            - {{ $warranty->detail->description }}
                        @endif
                    </div>
                @endforeach
            </div>
        </form>

        <form method="POST" action="{{ route('workshop.orders.payment', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Registrar pago</h2>
            <select name="cash_register_id" class="mb-2 w-full rounded border px-3 py-2" required>
                <option value="">Caja</option>
                @foreach($cashRegisters as $cashRegister)
                    <option value="{{ $cashRegister->id }}">{{ $cashRegister->number }}</option>
                @endforeach
            </select>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <select name="payment_methods[0][payment_method_id]" class="rounded border px-3 py-2" required>
                    <option value="">Metodo</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->description }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0.01" name="payment_methods[0][amount]" class="rounded border px-3 py-2" placeholder="Monto" required>
                <input name="payment_methods[0][reference]" class="rounded border px-3 py-2 md:col-span-2" placeholder="Referencia operacion (Yape/Plin/Tarjeta)">
            </div>
            <input name="comment" class="my-2 w-full rounded border px-3 py-2" placeholder="Comentario pago">
            <button class="rounded bg-emerald-700 px-3 py-2 text-white">Registrar pago</button>
        </form>

        <form method="POST" action="{{ route('workshop.orders.payment.refund', $order) }}" class="rounded border p-4">
            @csrf
            <h2 class="mb-2 font-semibold">Registrar devolución</h2>
            <select name="cash_register_id" class="mb-2 w-full rounded border px-3 py-2" required>
                <option value="">Caja</option>
                @foreach($cashRegisters as $cashRegister)
                    <option value="{{ $cashRegister->id }}">{{ $cashRegister->number }}</option>
                @endforeach
            </select>
            <select name="payment_method_id" class="mb-2 w-full rounded border px-3 py-2" required>
                <option value="">Método</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}">{{ $method->description }}</option>
                @endforeach
            </select>
            <input type="number" step="0.01" min="0.01" name="amount" class="mb-2 w-full rounded border px-3 py-2" placeholder="Monto devolución" required>
            <input name="reason" class="mb-2 w-full rounded border px-3 py-2" placeholder="Motivo de devolución" required>
            <button class="rounded bg-rose-700 px-3 py-2 text-white">Registrar devolución</button>
        </form>
    </div>

    <form method="POST" action="{{ route('workshop.orders.deliver', $order) }}" class="rounded border p-4">
        @csrf
        <h2 class="mb-2 font-semibold">Entrega / cierre</h2>
        <div class="flex flex-wrap items-center gap-2">
            <input type="number" min="0" name="mileage_out" class="rounded border px-3 py-2" placeholder="KM salida">
            <button class="rounded bg-black px-3 py-2 text-white">Entregar vehiculo</button>
        </div>
        <p class="mt-2 text-sm text-gray-600">Regla: no entrega si hay deuda pendiente.</p>
    </form>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('workshop.orders.cancel', $order) }}" class="rounded border border-red-300 p-4">
            @csrf
            <h2 class="mb-2 font-semibold text-red-700">Anular OS</h2>
            <input name="reason" class="mb-2 w-full rounded border px-3 py-2" placeholder="Motivo de anulacion" required>
            <label class="mb-2 inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="auto_refund" value="1">
                Revertir pagos automaticamente
            </label>
            <select name="cash_register_id" class="mb-2 w-full rounded border px-3 py-2">
                <option value="">Caja (si hay devolucion)</option>
                @foreach($cashRegisters as $cashRegister)
                    <option value="{{ $cashRegister->id }}">{{ $cashRegister->number }}</option>
                @endforeach
            </select>
            <select name="payment_method_id" class="mb-2 w-full rounded border px-3 py-2">
                <option value="">Metodo (si hay devolucion)</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}">{{ $method->description }}</option>
                @endforeach
            </select>
            <button class="rounded bg-red-700 px-3 py-2 text-white">Anular</button>
        </form>

        <form method="POST" action="{{ route('workshop.orders.reopen', $order) }}" class="rounded border border-amber-300 p-4">
            @csrf
            <h2 class="mb-2 font-semibold text-amber-700">Reabrir OS (admin)</h2>
            <input name="reason" class="mb-2 w-full rounded border px-3 py-2" placeholder="Motivo de reapertura" required>
            <button class="rounded bg-amber-700 px-3 py-2 text-white">Reabrir</button>
        </form>
    </div>

    <form method="POST" action="{{ route('workshop.orders.checklists.store', $order) }}" class="rounded border p-4">
        @csrf
        <h2 class="mb-2 font-semibold">Guardar checklist rapido</h2>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <select name="type" class="rounded border px-3 py-2" required>
                <option value="OS_INTAKE">OS_INTAKE</option>
                <option value="GP_ACTIVATION">GP_ACTIVATION</option>
                <option value="PDI">PDI</option>
                <option value="MAINTENANCE">MAINTENANCE</option>
            </select>
            <input name="items[0][group]" class="rounded border px-3 py-2" placeholder="Grupo">
            <input name="items[0][label]" class="rounded border px-3 py-2" placeholder="Item" required>
            <input name="items[0][result]" class="rounded border px-3 py-2" placeholder="Resultado (OK/SI/DONE)">
            <input name="items[0][action]" class="rounded border px-3 py-2" placeholder="Accion PDI">
            <input name="items[0][observation]" class="rounded border px-3 py-2" placeholder="Observacion">
            <input type="number" name="items[0][order_num]" value="1" min="1" class="rounded border px-3 py-2">
        </div>
        <button class="mt-2 rounded bg-slate-800 px-3 py-2 text-white">Guardar checklist</button>
    </form>

    <div class="rounded border p-4">
        <h2 class="mb-2 font-semibold">Bitácora del vehículo</h2>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">KM</th>
                        <th class="p-2 text-left">Nota</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($order->vehicle?->logs ?? collect())->sortByDesc('created_at')->take(20) as $log)
                        <tr class="border-t">
                            <td class="p-2">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="p-2">{{ $log->log_type }}</td>
                            <td class="p-2">{{ $log->mileage }}</td>
                            <td class="p-2">{{ $log->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-2 text-gray-500">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded border p-4">
            <h2 class="mb-2 font-semibold">Historial de estados</h2>
            <div class="overflow-x-auto rounded border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-left">De</th>
                            <th class="p-2 text-left">A</th>
                            <th class="p-2 text-left">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->statusHistories->sortByDesc('id')->take(30) as $history)
                            <tr class="border-t">
                                <td class="p-2">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $history->from_status ?: '-' }}</td>
                                <td class="p-2">{{ $history->to_status }}</td>
                                <td class="p-2">{{ $history->user?->name ?: ('#'.$history->user_id) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-2 text-gray-500">Sin cambios registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded border p-4">
            <h2 class="mb-2 font-semibold">Auditoría de eventos</h2>
            <div class="overflow-x-auto rounded border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-left">Evento</th>
                            <th class="p-2 text-left">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->audits->sortByDesc('id')->take(30) as $audit)
                            <tr class="border-t">
                                <td class="p-2">{{ optional($audit->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="p-2">{{ $audit->event }}</td>
                                <td class="p-2">{{ $audit->user?->name ?: ('#'.$audit->user_id) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-2 text-gray-500">Sin eventos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

