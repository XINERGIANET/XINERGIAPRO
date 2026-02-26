@extends('layouts.app')

@section('content')
<div x-data="{
    vehicles: @js($vehicles->map(function ($v) use ($clients) {
        $client = $clients->firstWhere('id', $v->client_person_id);
        $clientName = trim(((string) ($client->first_name ?? '')) . ' ' . ((string) ($client->last_name ?? '')));
        return [
            'id' => $v->id,
            'client_person_id' => $v->client_person_id,
            'client_name' => $clientName,
            'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')),
            'display_label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) . ($clientName !== '' ? ' (Cliente: ' . $clientName . ')' : ''),
            'km' => (int) ($v->current_mileage ?? 0),
        ];
    })),
    clientsList: @js($clients->map(fn($c) => ['id' => $c->id, 'first_name' => $c->first_name, 'last_name' => $c->last_name, 'person_type' => $c->person_type, 'document_number' => $c->document_number, 'label' => trim($c->first_name . ' ' . $c->last_name . ' - ' . $c->person_type . ' ' . $c->document_number)])),
    departments: @js($departments ?? []),
    provinces: @js($provinces ?? []),
    districts: @js($districts ?? []),
    vehicleTypes: @js($vehicleTypes->map(fn($type) => ['id' => $type->id, 'name' => $type->name])),
    servicesCatalog: @js($services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'base_price' => (float) $s->base_price, 'type' => $s->type])),
    selectedVehicleId: @js((string) old('vehicle_id', '')),
    vehicleSearch: '',
    vehicleDropdownOpen: false,
    selectedClientId: @js((string) old('client_person_id', '')),
    mileageIn: @js((string) old('mileage_in', '')),
    creatingVehicle: false,
    creatingVehicleLoading: false,
    quickVehicleError: '',
    creatingClientLoading: false,
    quickClientError: '',
    quickVehicle: {
        client_person_id: '',
        vehicle_type_id: @js(optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id ?? optional($vehicleTypes->first())->id ?? ''),
        brand: '',
        model: '',
        year: '',
        color: '',
        plate: '',
        vin: '',
        engine_number: '',
        chassis_number: '',
        serial_number: '',
        current_mileage: ''
    },
    quickClient: {
        person_type: 'DNI',
        document_number: '',
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        address: '-',
        location_id: @js($selectedDistrictId ?? ''),
        department_id: @js($selectedDepartmentId ?? ''),
        province_id: @js($selectedProvinceId ?? ''),
        genero: '',
        fecha_nacimiento: ''
    },
    branchDepartmentName: @js($selectedDepartmentName ?? ''),
    branchProvinceName: @js($selectedProvinceName ?? ''),
    branchDistrictName: @js($selectedDistrictName ?? ''),
    serviceLines: [{ service_id: '', qty: 1, unit_price: 0 }],
    syncVehicle() {
        const selected = this.vehicles.find(v => String(v.id) === String(this.selectedVehicleId));
        if (!selected) return;
        this.selectedClientId = selected.client_person_id ? String(selected.client_person_id) : '';
        this.mileageIn = selected.km ? String(selected.km) : '';
        this.vehicleSearch = selected.display_label || selected.label || '';
    },
    get filteredVehicles() {
        const q = String(this.vehicleSearch || '').trim().toLowerCase();
        if (!q) return this.vehicles.slice(0, 30);
        return this.vehicles
            .filter(v => {
                const vehicleText = String(v.label || '').toLowerCase();
                const clientText = String(v.client_name || '').toLowerCase();
                return vehicleText.includes(q) || clientText.includes(q);
            })
            .slice(0, 30);
    },
    selectVehicle(vehicle) {
        this.selectedVehicleId = String(vehicle.id);
        this.vehicleSearch = vehicle.display_label || vehicle.label || '';
        this.vehicleDropdownOpen = false;
        this.syncVehicle();
    },
    onVehicleSearchInput() {
        this.vehicleDropdownOpen = true;
        if (!String(this.vehicleSearch || '').trim()) {
            this.selectedVehicleId = '';
            this.selectedClientId = '';
        }
    },
    closeVehicleDropdown() {
        this.vehicleDropdownOpen = false;
    },
    addServiceLine() {
        this.serviceLines.push({ service_id: '', qty: 1, unit_price: 0 });
    },
    removeServiceLine(index) {
        if (this.serviceLines.length === 1) {
            this.serviceLines = [{ service_id: '', qty: 1, unit_price: 0 }];
            return;
        }
        this.serviceLines.splice(index, 1);
    },
    onServiceChange(index) {
        const service = this.servicesCatalog.find(s => String(s.id) === String(this.serviceLines[index].service_id));
        if (!service) return;
        this.serviceLines[index].unit_price = Number(service.base_price || 0);
    },
    lineSubtotal(line) {
        const qty = Number(line.qty || 0);
        const price = Number(line.unit_price || 0);
        return qty * price;
    },
    estimatedTotal() {
        return this.serviceLines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
    },
    get filteredProvinces() {
        return this.provinces.filter(p => String(p.parent_location_id) === String(this.quickClient.department_id || ''));
    },
    get filteredDistricts() {
        return this.districts.filter(d => String(d.parent_location_id) === String(this.quickClient.province_id || ''));
    },
    onClientDepartmentChange() {
        this.quickClient.province_id = '';
        this.quickClient.location_id = '';
    },
    onClientProvinceChange() {
        this.quickClient.location_id = '';
    },
    resetQuickVehicle() {
        this.quickVehicle = {
            client_person_id: this.selectedClientId || '',
            vehicle_type_id: @js(optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id ?? optional($vehicleTypes->first())->id ?? ''),
            brand: '',
            model: '',
            year: '',
            color: '',
            plate: '',
            vin: '',
            engine_number: '',
            chassis_number: '',
            serial_number: '',
            current_mileage: this.mileageIn || ''
        };
        this.quickVehicleError = '';
    },
    toggleQuickVehicle() {
        this.creatingVehicle = !this.creatingVehicle;
        if (this.creatingVehicle) {
            this.resetQuickVehicle();
        }
    },
    async saveQuickVehicle() {
        this.quickVehicleError = '';
        this.creatingVehicleLoading = true;
        try {
            const response = await fetch(@js(route('workshop.maintenance-board.vehicles.store')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @js(csrf_token()),
                },
                body: JSON.stringify(this.quickVehicle),
            });
            const payload = await response.json();
            if (!response.ok) {
                const message = payload?.message || 'No se pudo registrar el vehiculo.';
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || message);
            }
            this.vehicles.unshift(payload);
            const quickClient = this.clientsList.find(c => String(c.id) === String(payload.client_person_id));
            const quickClientName = quickClient ? `${quickClient.first_name || ''} ${quickClient.last_name || ''}`.trim() : '';
            this.vehicles[0].client_name = quickClientName;
            this.vehicles[0].display_label = `${payload.label || `Vehiculo #${payload.id}`}${quickClientName ? ` (Cliente: ${quickClientName})` : ''}`;
            this.selectedVehicleId = String(payload.id);
            this.selectedClientId = payload.client_person_id ? String(payload.client_person_id) : this.selectedClientId;
            this.mileageIn = payload.km ? String(payload.km) : this.mileageIn;
            this.vehicleSearch = this.vehicles[0].display_label || payload.label || '';
            this.creatingVehicle = false;
            this.resetQuickVehicle();
        } catch (error) {
            this.quickVehicleError = error?.message || 'Error registrando vehiculo.';
        } finally {
            this.creatingVehicleLoading = false;
        }
    },
    async saveQuickClient() {
        this.quickClientError = '';
        this.creatingClientLoading = true;
        try {
            const response = await fetch(@js(route('workshop.maintenance-board.clients.store')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @js(csrf_token()),
                },
                body: JSON.stringify(this.quickClient),
            });
            const payload = await response.json();
            if (!response.ok) {
                const message = payload?.message || 'No se pudo registrar el cliente.';
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || message);
            }

            this.clientsList.unshift(payload);
            this.selectedClientId = String(payload.id);
            this.quickVehicle.client_person_id = String(payload.id);
            this.quickClient = {
                person_type: 'DNI',
                document_number: '',
                first_name: '',
                last_name: '',
                phone: '',
                email: '',
                address: '-',
                location_id: @js($selectedDistrictId ?? ''),
                department_id: @js($selectedDepartmentId ?? ''),
                province_id: @js($selectedProvinceId ?? ''),
                genero: '',
                fecha_nacimiento: ''
            };
            this.$dispatch('close-client-modal');
        } catch (error) {
            this.quickClientError = error?.message || 'Error registrando cliente.';
        } finally {
            this.creatingClientLoading = false;
        }
    },
    splitName(fullName) {
        const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length <= 1) return { first_name: parts[0] || '', last_name: '' };
        if (parts.length === 2) return { first_name: parts[0], last_name: parts[1] };
        if (parts.length === 3) return { first_name: parts[0], last_name: parts.slice(1).join(' ') };
        return { first_name: parts.slice(0, 2).join(' '), last_name: parts.slice(2).join(' ') };
    },
    async fetchReniecQuickClient() {
        this.quickClientError = '';
        if (String(this.quickClient.person_type).toUpperCase() !== 'DNI') {
            this.quickClientError = 'La busqueda RENIEC solo aplica para DNI.';
            return;
        }
        const dni = String(this.quickClient.document_number || '').trim();
        if (!/^\d{8}$/.test(dni)) {
            this.quickClientError = 'Ingrese un DNI valido de 8 digitos.';
            return;
        }
        this.creatingClientLoading = true;
        try {
            const response = await fetch(`/api/reniec?dni=${encodeURIComponent(dni)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload?.status || !payload?.name) {
                throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
            }
            const parsed = this.splitName(payload.name);
            this.quickClient.first_name = parsed.first_name;
            this.quickClient.last_name = parsed.last_name;
        } catch (error) {
            this.quickClientError = error?.message || 'Error consultando RENIEC.';
        } finally {
            this.creatingClientLoading = false;
        }
    },
    clientSignatureData: '',
    signatureCanvas: null,
    signatureCtx: null,
    isSigning: false,
    initSignaturePad() {
        this.signatureCanvas = this.$refs.clientSignatureCanvas || null;
        if (!this.signatureCanvas) return;
        this.signatureCtx = this.signatureCanvas.getContext('2d');
        if (!this.signatureCtx) return;
        this.signatureCtx.lineWidth = 2;
        this.signatureCtx.lineCap = 'round';
        this.signatureCtx.strokeStyle = '#111827';
    },
    signaturePoint(evt) {
        const rect = this.signatureCanvas.getBoundingClientRect();
        const source = evt.touches?.[0] ?? evt;
        return {
            x: source.clientX - rect.left,
            y: source.clientY - rect.top,
        };
    },
    startSignature(evt) {
        if (!this.signatureCtx) return;
        evt.preventDefault();
        this.isSigning = true;
        const point = this.signaturePoint(evt);
        this.signatureCtx.beginPath();
        this.signatureCtx.moveTo(point.x, point.y);
    },
    drawSignature(evt) {
        if (!this.isSigning || !this.signatureCtx) return;
        evt.preventDefault();
        const point = this.signaturePoint(evt);
        this.signatureCtx.lineTo(point.x, point.y);
        this.signatureCtx.stroke();
    },
    stopSignature() {
        if (!this.signatureCtx) return;
        this.isSigning = false;
        this.signatureCtx.closePath();
        this.syncSignature();
    },
    clearSignature() {
        if (!this.signatureCtx || !this.signatureCanvas) return;
        this.signatureCtx.clearRect(0, 0, this.signatureCanvas.width, this.signatureCanvas.height);
        this.clientSignatureData = '';
    },
    syncSignature() {
        if (!this.signatureCanvas) return;
        this.clientSignatureData = this.signatureCanvas.toDataURL('image/png');
    }
}" x-init="$nextTick(() => { if (selectedVehicleId) { syncVehicle() } initSignaturePad() })">
    <x-common.page-breadcrumb
        pageTitle="Nuevo Ingreso a Mantenimiento"
        :crumbs="[
            ['label' => 'Tablero de Mantenimiento', 'url' => route('workshop.maintenance-board.index')],
            ['label' => 'Nuevo Ingreso a Mantenimiento'],
        ]"
    />

    <x-common.component-card title="Nuevo ingreso a mantenimiento" desc="Registra el vehiculo, cliente y servicios para iniciar la OS desde una vista completa.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

   

        <form method="POST" action="{{ route('workshop.maintenance-board.store') }}" enctype="multipart/form-data" @submit="syncSignature()" class="grid grid-cols-1 gap-3 md:grid-cols-3">
            @csrf
            <input type="hidden" name="client_person_id" x-model="selectedClientId">
            <input type="hidden" name="client_signature_data" x-model="clientSignatureData">
                <div x-show="creatingVehicle" x-cloak class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 md:col-span-3">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-indigo-800">Registrar vehiculo rapido</h4>
                    <button type="button" @click="creatingVehicle = false" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">Cerrar</button>
                </div>

                <div x-show="quickVehicleError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickVehicleError"></div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2">
                            <select x-model="quickVehicle.client_person_id" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <template x-for="client in clientsList" :key="`quick-client-${client.id}`">
                                    <option :value="client.id" x-text="`${client.first_name || ''} ${client.last_name || ''}`.trim() || `Cliente #${client.id}`"></option>
                                </template>
                            </select>
                            <button class="inline-flex items-center justify-center font-medium gap-2 rounded-xl transition px-5 py-3.5 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                                    type="button"
                                    style="background-color:#00A389;color:#fff;"
                                    @click="$dispatch('open-client-modal')">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </div>
                    <select x-model="quickVehicle.vehicle_type_id" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                        <template x-for="type in vehicleTypes" :key="`type-${type.id}`">
                            <option :value="type.id" x-text="type.name"></option>
                        </template>
                    </select>
                    <input x-model="quickVehicle.brand" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca">
                    <input x-model="quickVehicle.model" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo">
                    <input x-model="quickVehicle.year" type="number" min="1900" max="2100" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Anio">
                    <input x-model="quickVehicle.color" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                    <input x-model="quickVehicle.plate" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Placa">
                    <input x-model="quickVehicle.vin" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                    <input x-model="quickVehicle.engine_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro motor">
                    <input x-model="quickVehicle.chassis_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro chasis">
                    <input x-model="quickVehicle.serial_number" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                    <input x-model="quickVehicle.current_mileage" type="number" min="0" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM actual">
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button"
                            @click="saveQuickVehicle()"
                            :disabled="creatingVehicleLoading"
                            class="inline-flex h-10 items-center rounded-lg bg-indigo-700 px-4 text-xs font-semibold text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <i class="ri-save-line"></i>
                        <span class="ml-1" x-text="creatingVehicleLoading ? 'Guardando...' : 'Guardar vehiculo'"></span>
                    </button>
                    <span class="text-xs text-gray-600">Se agregara y seleccionara automaticamente.</span>
                </div>
            </div>
            <div class="md:col-span-1">
                <div class="relative flex items-center gap-2">
                    <input type="hidden" name="vehicle_id" x-model="selectedVehicleId" required>
                    <div class="relative w-full" @click.outside="closeVehicleDropdown()">
                        <input
                            x-model="vehicleSearch"
                            @focus="vehicleDropdownOpen = true"
                            @click="vehicleDropdownOpen = true"
                            @input="onVehicleSearchInput()"
                            class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                            placeholder="Buscar vehiculo o cliente"
                            autocomplete="off"
                            required
                        >
                        <div
                            x-show="vehicleDropdownOpen"
                            x-cloak
                            class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                        >
                            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-3 py-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Vehiculos</p>
                                <button type="button" @click="closeVehicleDropdown()" class="text-xs font-semibold text-gray-500 hover:text-gray-700">
                                    Cerrar
                                </button>
                            </div>
                            <template x-if="filteredVehicles.length === 0">
                                <p class="px-3 py-2 text-sm text-gray-500">Sin resultados.</p>
                            </template>
                            <template x-for="vehicle in filteredVehicles" :key="`vehicle-search-${vehicle.id}`">
                                <button
                                    type="button"
                                    @click="selectVehicle(vehicle)"
                                    class="flex w-full items-start justify-between border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-indigo-50"
                                >
                                    <span class="font-medium text-gray-800" x-text="vehicle.label || `Vehiculo #${vehicle.id}`"></span>
                                    <span class="ml-3 text-xs text-gray-500" x-text="vehicle.client_name ? `(${vehicle.client_name})` : ''"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <button type="button"
                            @click="toggleQuickVehicle()"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                        <i class="ri-add-line"></i>
                        <span class="ml-1 hidden sm:inline">Nuevo</span>
                    </button>
                </div>
            </div>

           

            <input name="mileage_in" type="number" min="0" x-model="mileageIn" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM ingreso">

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 md:col-span-1">
                <input type="checkbox" name="tow_in" value="1" class="h-4 w-4 rounded border-gray-300">
                Ingreso en grua
            </label>

            <input name="diagnosis_text" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-3   " placeholder="Diagnostico inicial (opcional)">
            <textarea name="observations" rows="3" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" placeholder="Observaciones"></textarea>

            <div class="rounded-xl border border-gray-200 bg-white p-4 md:col-span-3">
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-gray-800">Inspeccion e inventario recibido</h4>
                    <p class="text-xs text-gray-500">Estos datos se guardan al iniciar el mantenimiento.</p>
                </div>

                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    @foreach ([
                        'ESPEJOS' => 'Espejos',
                        'FARO_DELANTERO' => 'Faro delantero',
                        'DIRECCIONALES' => 'Direccionales',
                        'TAPON_GASOLINA' => 'Tapon de gasolina',
                        'PEDALES' => 'Pedales',
                        'CLAXON' => 'Claxon',
                        'ASIENTOS' => 'Asientos',
                        'LUZ_STOP_TRASERA' => 'Luz stop trasera',
                        'CUBIERTAS_COMPLETAS' => 'Cubiertas completas',
                        'TACOMETROS' => 'Tacometros',
                        'STEREO' => 'Stereo',
                        'PARABRISAS' => 'Parabrisas',
                        'TAPON_RADIADORES' => 'Tapon de radiadores',
                        'FILTRO_AIRE' => 'Filtro de aire',
                        'BATERIA' => 'Bateria',
                        'LLAVES' => 'Llaves',
                    ] as $key => $label)
                        <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" name="inventory[{{ $key }}]" value="1" class="h-4 w-4 rounded border-gray-300">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600">Daños preexistentes por lado</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ([
                            0 => ['value' => 'RIGHT', 'label' => 'Lado derecho'],
                            1 => ['value' => 'LEFT', 'label' => 'Lado izquierdo'],
                            2 => ['value' => 'FRONT', 'label' => 'Frente'],
                            3 => ['value' => 'BACK', 'label' => 'Atras'],
                        ] as $idx => $side)
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <input type="hidden" name="damages[{{ $idx }}][side]" value="{{ $side['value'] }}">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $side['label'] }}</label>
                                <textarea name="damages[{{ $idx }}][description]" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Describe daño preexistente (opcional)"></textarea>
                                <select name="damages[{{ $idx }}][severity]" class="mt-2 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                    <option value="">Severidad</option>
                                    <option value="LOW">Baja</option>
                                    <option value="MED">Media</option>
                                    <option value="HIGH">Alta</option>
                                </select>
                                <label class="mt-2 block text-xs font-medium text-gray-700">Evidencia fotografica (camara)</label>
                                <input type="file"
                                       x-ref="damageCameraInput{{ $idx }}"
                                       name="damages[{{ $idx }}][photos][]"
                                       accept="image/*"
                                       capture="environment"
                                       multiple
                                       class="hidden">
                                <button type="button"
                                        @click="$refs.damageCameraInput{{ $idx }}.click()"
                                        class="mt-2 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                                    <i class="ri-camera-line"></i>
                                    <span>Abrir camara</span>
                                </button>
                                <p class="mt-1 text-[11px] text-gray-500">Toma una o varias fotos por cada lado.</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 bg-white p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Firma del cliente</p>
                        <button type="button" @click="clearSignature()" class="rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Limpiar firma</button>
                    </div>
                    <canvas
                        x-ref="clientSignatureCanvas"
                        width="700"
                        height="180"
                        @mousedown="startSignature($event)"
                        @mousemove="drawSignature($event)"
                        @mouseup="stopSignature()"
                        @mouseleave="stopSignature()"
                        @touchstart.prevent="startSignature($event)"
                        @touchmove.prevent="drawSignature($event)"
                        @touchend="stopSignature()"
                        class="w-full rounded-lg border border-dashed border-gray-300 bg-white"
                    ></canvas>
                    <p class="mt-1 text-[11px] text-gray-500">Conformidad de ingreso del vehiculo.</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 md:col-span-3">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-800">Servicios a realizar</h4>
                    <button type="button" @click="addServiceLine()" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Agregar servicio</button>
                </div>
                <template x-for="(line, index) in serviceLines" :key="index">
                    <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <select :name="`service_lines[${index}][service_id]`" x-model="line.service_id" @change="onServiceChange(index)" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Selecciona servicio</option>
                                <template x-for="service in servicesCatalog" :key="service.id">
                                    <option :value="service.id" x-text="`${service.name} (${service.type})`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <input type="number" step="0.01" min="0.01" :name="`service_lines[${index}][qty]`" x-model="line.qty" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cant.">
                        </div>
                        <div class="md:col-span-3">
                            <input type="number" step="0.01" min="0" :name="`service_lines[${index}][unit_price]`" x-model="line.unit_price" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Precio">
                        </div>
                        <div class="md:col-span-1">
                            <button type="button" @click="removeServiceLine(index)" class="h-11 w-full rounded-lg bg-red-600 text-white">X</button>
                        </div>
                        <div class="md:col-span-12 text-right text-xs text-gray-600">
                            Subtotal linea: S/ <span x-text="lineSubtotal(line).toFixed(2)"></span>
                        </div>
                    </div>
                </template>
                <div class="mt-2 border-t border-gray-200 pt-2 text-right text-sm font-semibold text-gray-800">
                    Total estimado: S/ <span x-text="estimatedTotal().toFixed(2)"></span>
                </div>
            </div>

            <div class="md:col-span-3 mt-2 flex gap-2">
                <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                    <i class="ri-play-circle-line"></i><span>Iniciar mantenimiento</span>
                </x-ui.button>
                <x-ui.link-button size="md" variant="outline" href="{{ route('workshop.maintenance-board.index') }}">
                    <i class="ri-close-line"></i><span>Cancelar</span>
                </x-ui.link-button>
            </div>
        </form>
    </x-common.component-card>
    <x-ui.modal x-data="{ open: false }" x-on:open-client-modal.window="open = true" x-on:close-client-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cliente</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div x-show="quickClientError" class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="quickClientError"></div>

            <form @submit.prevent="saveQuickClient()" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                    <select x-model="quickClient.person_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
                        <option value="DNI">DNI</option>
                        <option value="RUC">RUC</option>
                        <option value="CARNET DE EXTRANGERIA">CARNET DE EXTRANGERIA</option>
                        <option value="PASAPORTE">PASAPORTE</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                    <div class="flex items-center gap-2">
                        <input x-model="quickClient.document_number" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Documento" required>
                        <button
                            type="button"
                            @click="fetchReniecQuickClient()"
                            :disabled="creatingClientLoading"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
                        >
                            <i class="ri-search-line"></i>
                            <span class="ml-1">Buscar</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Nombres</label>
                    <input x-model="quickClient.first_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nombres / Razon social" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                    <input x-model="quickClient.last_name" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Apellidos" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                    <input type="date" x-model="quickClient.fecha_nacimiento" onclick="this.showPicker && this.showPicker()" onfocus="this.showPicker && this.showPicker()" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                    <select x-model="quickClient.genero" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        <option value="">Seleccione genero</option>
                        <option value="MASCULINO">MASCULINO</option>
                        <option value="FEMENINO">FEMENINO</option>
                        <option value="OTRO">OTRO</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                    <input x-model="quickClient.phone" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el telefono">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" x-model="quickClient.email" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ingrese el email">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                    <input x-model="quickClient.address" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Direccion (opcional)">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                    <input type="text" :value="branchDepartmentName || '-'" readonly class="h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-700">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                    <input type="text" :value="branchProvinceName || '-'" readonly class="h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-700">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                    <input type="text" :value="branchDistrictName || '-'" readonly class="h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-700">
                </div>

                <div class="md:col-span-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                    <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox" checked disabled class="h-4 w-4 rounded border-gray-300 text-brand-500">
                        <span>Cliente</span>
                    </div>
                </div>

                <div class="md:col-span-4 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary" style="background-color:#00A389;color:#fff;">
                        <i class="ri-save-line"></i><span x-text="creatingClientLoading ? 'Guardando...' : 'Guardar cliente'"></span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
