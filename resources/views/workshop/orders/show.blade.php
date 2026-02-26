@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Detalle Orden de Servicio" />

    <x-common.component-card title="OS {{ $order->movement?->number }}" desc="Estado actual: {{ $order->status }}">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.order', $order) }}" target="_blank">PDF OS</a>
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.activation', $order) }}" target="_blank">PDF GP</a>
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.pdi', $order) }}" target="_blank">PDF PDI</a>
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.maintenance', $order) }}" target="_blank">PDF Mant.</a>
            <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.parts', $order) }}" target="_blank">PDF Repuestos</a>
            @if($order->sale)
                <a class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white" href="{{ route('workshop.pdf.internal-sale', $order) }}" target="_blank">PDF Venta</a>
            @endif
            <form method="POST" action="{{ route('workshop.pdf.order.save', $order) }}">
                @csrf
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Guardar snapshot PDF</button>
            </form>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
            <div><p class="text-xs text-gray-500">Cliente</p><p class="font-semibold text-gray-900">{{ $order->client?->first_name }} {{ $order->client?->last_name }}</p></div>
            <div><p class="text-xs text-gray-500">Vehiculo</p><p class="font-semibold text-gray-900">{{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} {{ $order->vehicle?->plate }}</p></div>
            <div><p class="text-xs text-gray-500">KM Ingreso / Salida</p><p class="font-semibold text-gray-900">{{ $order->mileage_in ?: '-' }} / {{ $order->mileage_out ?: '-' }}</p></div>
            <div><p class="text-xs text-gray-500">Aprobacion / Pago</p><p class="font-semibold text-gray-900">{{ $order->approval_status ?? 'pending' }} / {{ $order->payment_status ?? 'pending' }}</p></div>
            <div><p class="text-xs text-gray-500">Total</p><p class="font-semibold text-gray-900">S/ {{ number_format((float) $order->total, 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Pagado</p><p class="font-semibold text-gray-900">S/ {{ number_format((float) $order->paid_total, 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Deuda</p><p class="font-semibold text-gray-900">S/ {{ number_format(max(0, (float)$order->total - (float)$order->paid_total), 2) }}</p></div>
            <div><p class="text-xs text-gray-500">Tecnicos</p><p class="font-semibold text-gray-900">{{ $order->technicians->map(fn($row) => trim(($row->technician?->first_name ?? '').' '.($row->technician?->last_name ?? '')))->filter()->join(', ') ?: 'Sin asignar' }}</p></div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Datos Generales</h3>
                <form method="POST" action="{{ route('workshop.orders.update', $order) }}" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <input type="datetime-local" name="intake_date" value="{{ optional($order->intake_date)->format('Y-m-d\\TH:i') }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                    <input type="datetime-local" name="delivery_date" value="{{ optional($order->delivery_date)->format('Y-m-d\\TH:i') }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                    <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        @foreach(['draft','diagnosis','awaiting_approval','approved','in_progress','finished','delivered','cancelled'] as $status)
                            <option value="{{ $status }}" @selected($order->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <input name="mileage_in" type="number" min="0" value="{{ $order->mileage_in }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM ingreso">
                    <input name="mileage_out" type="number" min="0" value="{{ $order->mileage_out }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM salida">
                    <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 px-3 text-sm"><input type="checkbox" name="tow_in" value="1" @checked($order->tow_in)> Ingreso en grua</label>
                    <textarea name="diagnosis_text" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" rows="2" placeholder="Diagnostico">{{ $order->diagnosis_text }}</textarea>
                    <textarea name="observations" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" rows="2" placeholder="Observaciones">{{ $order->observations }}</textarea>
                    <button class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white md:col-span-3">Actualizar datos generales</button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <form method="POST" action="{{ route('workshop.orders.quotation', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Cotizacion</h3>
                    <input name="note" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nota de cotizacion para cliente">
                    <button class="rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white">Generar cotizacion</button>
                </form>

                <form method="POST" action="{{ route('workshop.orders.approve', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Aprobacion Cliente</h3>
                    <select name="decision" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="approved">Aprobado</option>
                        <option value="partial">Aprobado parcial</option>
                        <option value="rejected">Rechazado</option>
                    </select>
                    <input name="approval_note" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nota de aprobacion/rechazo">
                    <button class="rounded-lg bg-indigo-700 px-3 py-2 text-xs font-semibold text-white">Registrar decision</button>
                </form>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Inspeccion e Inventario</h3>
                <form method="POST" action="{{ route('workshop.orders.intake.update', $order) }}" enctype="multipart/form-data" class="space-y-3"
                      x-data="{
                          clientSignatureData: '',
                          signatureCanvas: null,
                          signatureCtx: null,
                          isSigning: false,
                          init() {
                              this.signatureCanvas = this.$refs.clientSignatureCanvas || null;
                              if (!this.signatureCanvas) return;
                              this.resizeSignatureCanvas();
                              this.signatureCtx = this.signatureCanvas.getContext('2d');
                              if (!this.signatureCtx) return;
                              this.signatureCtx.lineWidth = 2;
                              this.signatureCtx.lineCap = 'round';
                              this.signatureCtx.strokeStyle = '#111827';
                              window.addEventListener('resize', () => this.resizeSignatureCanvas());
                          },
                          resizeSignatureCanvas() {
                              if (!this.signatureCanvas) return;
                              const rect = this.signatureCanvas.getBoundingClientRect();
                              const dpr = window.devicePixelRatio || 1;
                              const width = Math.max(1, Math.round(rect.width * dpr));
                              const height = Math.max(1, Math.round(rect.height * dpr));
                              if (this.signatureCanvas.width !== width || this.signatureCanvas.height !== height) {
                                  this.signatureCanvas.width = width;
                                  this.signatureCanvas.height = height;
                                  const ctx = this.signatureCanvas.getContext('2d');
                                  if (ctx) {
                                      ctx.setTransform(1, 0, 0, 1, 0, 0);
                                      ctx.scale(dpr, dpr);
                                      ctx.lineWidth = 2;
                                      ctx.lineCap = 'round';
                                      ctx.strokeStyle = '#111827';
                                  }
                              }
                          },
                          point(evt) {
                              const rect = this.signatureCanvas.getBoundingClientRect();
                              const src = evt.touches?.[0] ?? evt;
                              const scaleX = this.signatureCanvas.width / rect.width;
                              const scaleY = this.signatureCanvas.height / rect.height;
                              const dpr = window.devicePixelRatio || 1;
                              return {
                                  x: ((src.clientX - rect.left) * scaleX) / dpr,
                                  y: ((src.clientY - rect.top) * scaleY) / dpr
                              };
                          },
                          start(evt) {
                              if (!this.signatureCtx) return;
                              evt.preventDefault();
                              this.isSigning = true;
                              const p = this.point(evt);
                              this.signatureCtx.beginPath();
                              this.signatureCtx.moveTo(p.x, p.y);
                          },
                          draw(evt) {
                              if (!this.isSigning || !this.signatureCtx) return;
                              evt.preventDefault();
                              const p = this.point(evt);
                              this.signatureCtx.lineTo(p.x, p.y);
                              this.signatureCtx.stroke();
                          },
                          stop() {
                              if (!this.signatureCtx) return;
                              this.isSigning = false;
                              this.signatureCtx.closePath();
                              this.sync();
                          },
                          clear() {
                              if (!this.signatureCtx || !this.signatureCanvas) return;
                              this.signatureCtx.clearRect(0, 0, this.signatureCanvas.width, this.signatureCanvas.height);
                              this.clientSignatureData = '';
                          },
                          sync() {
                              if (!this.signatureCanvas) return;
                              this.clientSignatureData = this.signatureCanvas.toDataURL('image/png');
                          },
                          damagePreviews: { 0: [], 1: [], 2: [], 3: [] },
                          updateDamagePreviews(index, event) {
                              const files = Array.from(event?.target?.files || []);
                              this.damagePreviews[index] = files.map(file => ({
                                  name: file.name,
                                  url: URL.createObjectURL(file),
                              }));
                          }
                      }"
                      x-init="init()"
                      @submit="sync()">
                    @csrf
                    <input type="hidden" name="client_signature_data" x-model="clientSignatureData">
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
                        @foreach(['ESPEJOS','FARO_DELANTERO','LLAVES','BATERIA','DOCUMENTOS'] as $item)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm"><input type="checkbox" name="inventory[{{ $item }}]" value="1" @checked((bool) optional($order->intakeInventory->firstWhere('item_key', $item))->present)>{{ $item }}</label>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ([0 => ['value' => 'RIGHT', 'label' => 'Lado derecho'], 1 => ['value' => 'LEFT', 'label' => 'Lado izquierdo'], 2 => ['value' => 'FRONT', 'label' => 'Frente'], 3 => ['value' => 'BACK', 'label' => 'Atras']] as $idx => $side)
                            @php($existingDamage = $order->damages->firstWhere('side', $side['value']))
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <input type="hidden" name="damages[{{ $idx }}][side]" value="{{ $side['value'] }}">
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $side['label'] }}</p>
                                <textarea name="damages[{{ $idx }}][description]" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Descripcion del dano">{{ $existingDamage->description ?? '' }}</textarea>
                                <select name="damages[{{ $idx }}][severity]" class="mt-2 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                    <option value="">Severidad</option>
                                    <option value="LOW" @selected(($existingDamage->severity ?? '') === 'LOW')>Baja</option>
                                    <option value="MED" @selected(($existingDamage->severity ?? '') === 'MED')>Media</option>
                                    <option value="HIGH" @selected(($existingDamage->severity ?? '') === 'HIGH')>Alta</option>
                                </select>
                                <label class="mt-2 block text-xs font-medium text-gray-700">Evidencia fotografica (camara)</label>
                                <input type="file"
                                       x-ref="damageCameraInput{{ $idx }}"
                                       name="damages[{{ $idx }}][photos][]"
                                       accept="image/*"
                                       capture="environment"
                                       multiple
                                       @change="updateDamagePreviews({{ $idx }}, $event)"
                                       class="hidden">
                                <button type="button"
                                        @click="$refs.damageCameraInput{{ $idx }}.click()"
                                        class="mt-2 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                                    <i class="ri-camera-line"></i>
                                    <span>Abrir camara</span>
                                </button>
                                <p class="mt-1 text-[11px] text-gray-500">Toma una o varias fotos por cada lado.</p>
                                <div class="mt-2 flex flex-wrap gap-2" x-show="(damagePreviews[{{ $idx }}] || []).length > 0">
                                    <template x-for="(preview, pIndex) in (damagePreviews[{{ $idx }}] || [])" :key="`order-damage-preview-{{ $idx }}-${pIndex}`">
                                        <a :href="preview.url" target="_blank" class="block h-14 w-14 overflow-hidden rounded border border-gray-200">
                                            <img :src="preview.url" :alt="preview.name" class="h-full w-full object-cover">
                                        </a>
                                    </template>
                                </div>
                                @if($existingDamage && $existingDamage->photos->count())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($existingDamage->photos as $photo)
                                            <a href="{{ asset('storage/' . $photo->photo_path) }}" target="_blank" class="block h-14 w-14 overflow-hidden rounded border border-gray-200">
                                                <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Foto dano" class="h-full w-full object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Firma cliente</p>
                            <button type="button" @click="clear()" class="rounded border border-gray-200 px-2 py-1 text-xs">Limpiar</button>
                        </div>
                        <canvas x-ref="clientSignatureCanvas" width="700" height="180"
                                @mousedown="start($event)" @mousemove="draw($event)" @mouseup="stop()" @mouseleave="stop()"
                                @touchstart.prevent="start($event)" @touchmove.prevent="draw($event)" @touchend="stop()"
                                class="w-full rounded border border-dashed border-gray-300"></canvas>
                        @if($order->intake_client_signature_path)
                            <div class="mt-2">
                                <p class="mb-1 text-xs text-gray-500">Firma actual</p>
                                <a href="{{ asset('storage/' . $order->intake_client_signature_path) }}" target="_blank" class="inline-block rounded border border-gray-200">
                                    <img src="{{ asset('storage/' . $order->intake_client_signature_path) }}" alt="Firma cliente" class="h-16 object-contain">
                                </a>
                            </div>
                        @endif
                    </div>
                    <button class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white">Guardar inspeccion</button>
                </form>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Detalle de Lineas</h3>
                <form method="POST" action="{{ route('workshop.orders.details.store', $order) }}" class="mb-4 grid grid-cols-1 gap-2 md:grid-cols-4">
                    @csrf
                    <select name="line_type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="SERVICE">SERVICE</option>
                        <option value="LABOR">LABOR</option>
                        <option value="PART">PART</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                    <select name="service_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Servicio (opcional)</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <select name="product_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Repuesto (opcional)</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->description }}</option>
                        @endforeach
                    </select>
                    <select name="tax_rate_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Impuesto</option>
                        @foreach($taxRates as $tax)
                            <option value="{{ $tax->id }}">{{ $tax->description }} ({{ $tax->tax_rate }}%)</option>
                        @endforeach
                    </select>
                    <input name="description" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" placeholder="Descripcion" required>
                    <input name="qty" type="number" step="0.000001" min="0.000001" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="1" required>
                    <input name="unit_price" type="number" step="0.000001" min="0" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="0" required>
                    <select name="technician_person_id" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Tecnico</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->first_name }} {{ $tech->last_name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white md:col-span-4">Agregar linea</button>
                </form>

                <div class="table-responsive rounded-xl border border-gray-200">
                    <table class="w-full min-w-[1200px] text-sm">
                        <thead>
                            <tr>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Descripcion</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Cant</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">P.Unit</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Total</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Stock</th>
                                <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->details as $detail)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2">{{ $detail->line_type }}</td>
                                    <td class="px-3 py-2">{{ $detail->description }}</td>
                                    <td class="px-3 py-2">{{ $detail->qty }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) $detail->unit_price, 2) }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) $detail->total, 2) }}</td>
                                    <td class="px-3 py-2">{{ $detail->stock_status ?? ($detail->stock_consumed ? 'CONSUMIDO' : 'PENDIENTE') }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
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
                                                <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">@csrf<input type="hidden" name="detail_id" value="{{ $detail->id }}"><input type="hidden" name="action" value="reserve"><button class="rounded bg-cyan-700 px-2 py-1 text-white">Reservar</button></form>
                                                <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">@csrf<input type="hidden" name="detail_id" value="{{ $detail->id }}"><input type="hidden" name="action" value="release"><button class="rounded bg-slate-500 px-2 py-1 text-white">Liberar</button></form>
                                                <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">@csrf<input type="hidden" name="detail_id" value="{{ $detail->id }}"><input type="hidden" name="action" value="consume"><button class="rounded bg-amber-600 px-2 py-1 text-white">Consumir</button></form>
                                            @endif

                                            @if($detail->line_type === 'PART' && $detail->stock_consumed)
                                                <form method="POST" action="{{ route('workshop.orders.consume', $order) }}">@csrf<input type="hidden" name="detail_id" value="{{ $detail->id }}"><input type="hidden" name="action" value="return"><button class="rounded bg-rose-600 px-2 py-1 text-white">Devolver</button></form>
                                            @endif

                                            <form method="POST" action="{{ route('workshop.orders.details.destroy', [$order, $detail]) }}" onsubmit="return confirm('Eliminar linea?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded bg-red-600 px-2 py-1 text-white">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-3 text-gray-500">Sin lineas agregadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <form method="POST" action="{{ route('workshop.orders.technicians.assign', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Tecnicos de la OS</h3>
                    @php($assignedTechs = $order->technicians->values())
                    <div class="space-y-2">
                        @for($i = 0; $i < 3; $i++)
                            @php($assigned = $assignedTechs->get($i))
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <select name="technicians[{{ $i }}][technician_person_id]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                                    <option value="">Tecnico</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}" @selected((int)($assigned->technician_person_id ?? 0) === (int)$tech->id)>{{ $tech->first_name }} {{ $tech->last_name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.0001" min="0" name="technicians[{{ $i }}][commission_percentage]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="% comision" value="{{ $assigned->commission_percentage ?? '' }}">
                            </div>
                        @endfor
                    </div>
                    <button class="mt-2 rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white">Guardar tecnicos</button>
                </form>

                <form method="POST" action="{{ route('workshop.orders.deliver', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Entrega y Cierre</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="number" min="0" name="mileage_out" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM salida">
                        <button class="rounded-lg bg-black px-3 py-2 text-xs font-semibold text-white">Entregar vehiculo</button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">No se entrega si hay deuda pendiente (salvo configuracion).</p>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Bitacora del Vehiculo</h3>
                    <div class="table-responsive rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Fecha</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">KM</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($order->vehicle?->logs ?? collect())->sortByDesc('created_at')->take(20) as $log)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2">{{ $log->log_type }}</td>
                                        <td class="px-3 py-2">{{ $log->mileage }}</td>
                                        <td class="px-3 py-2">{{ $log->notes }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-3 text-gray-500">Sin registros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Historial de Estados</h3>
                    <div class="table-responsive rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Fecha</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">De</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">A</th>
                                    <th style="background-color:#63B7EC" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-white">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->statusHistories->sortByDesc('id')->take(30) as $history)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2">{{ $history->from_status ?: '-' }}</td>
                                        <td class="px-3 py-2">{{ $history->to_status }}</td>
                                        <td class="px-3 py-2">{{ $history->user?->name ?: ('#'.$history->user_id) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-3 text-gray-500">Sin cambios registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <form method="POST" action="{{ route('workshop.orders.warranty.store', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Registrar Garantia</h3>
                    <select name="workshop_movement_detail_id" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Toda la OS</option>
                        @foreach($order->details as $detail)
                            <option value="{{ $detail->id }}">{{ $detail->line_type }} - {{ $detail->description }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" max="3650" name="days" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" value="30" required>
                    <input name="note" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nota de garantia">
                    <button class="rounded-lg bg-indigo-700 px-3 py-2 text-xs font-semibold text-white">Registrar garantia</button>
                </form>

                <form method="POST" action="{{ route('workshop.orders.payment.refund', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Registrar Devolucion</h3>
                    <select name="cash_register_id" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Caja</option>
                        @foreach($cashRegisters as $cashRegister)
                            <option value="{{ $cashRegister->id }}">{{ $cashRegister->number }}</option>
                        @endforeach
                    </select>
                    <select name="payment_method_id" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="">Metodo</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->description }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Monto" required>
                    <input name="reason" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                    <button class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white">Registrar devolucion</button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <form method="POST" action="{{ route('workshop.orders.cancel', $order) }}" class="rounded-xl border border-red-300 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-red-700">Anular OS</h3>
                    <input name="reason" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo" required>
                    <label class="mb-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="auto_refund" value="1"> Revertir pagos automaticamente</label>
                    <button class="rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white">Anular</button>
                </form>

                <form method="POST" action="{{ route('workshop.orders.reopen', $order) }}" class="rounded-xl border border-amber-300 bg-white p-4">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-700">Reabrir OS (Admin)</h3>
                    <input name="reason" class="mb-2 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Motivo de reapertura" required>
                    <button class="rounded-lg bg-amber-700 px-3 py-2 text-xs font-semibold text-white">Reabrir</button>
                </form>
            </div>

            <form method="POST" action="{{ route('workshop.orders.checklists.store', $order) }}" class="rounded-xl border border-gray-200 bg-white p-4">
                @csrf
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Checklist Rapido</h3>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <select name="type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="OS_INTAKE">OS_INTAKE</option>
                        <option value="GP_ACTIVATION">GP_ACTIVATION</option>
                        <option value="PDI">PDI</option>
                        <option value="MAINTENANCE">MAINTENANCE</option>
                    </select>
                    <input name="items[0][group]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Grupo">
                    <input name="items[0][label]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Item" required>
                    <input name="items[0][result]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Resultado">
                    <input name="items[0][action]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Accion">
                    <input name="items[0][observation]" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Observacion">
                    <input type="number" name="items[0][order_num]" value="1" min="1" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
                </div>
                <button class="mt-3 rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Guardar checklist</button>
            </form>
        </div>
    </x-common.component-card>
</div>
@endsection
