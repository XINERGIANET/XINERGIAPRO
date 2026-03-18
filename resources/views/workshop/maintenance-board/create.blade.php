@extends('layouts.app')

@section('content')
<div x-data="{
    vehicles: @js($vehicles->map(function ($v) use ($clients) {
        $client = $clients->firstWhere('id', $v->client_person_id);
        $clientName = trim(((string) ($client->first_name ?? '')) . ' ' . ((string) ($client->last_name ?? '')));
        return [
            'id' => $v->id,
            'client_person_id' => $v->client_person_id,
            'vehicle_type_id' => $v->vehicle_type_id,
            'client_name' => $clientName,
            'label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')),
            'display_label' => trim($v->brand . ' ' . $v->model . ' ' . ($v->plate ? ('- ' . $v->plate) : '')) . ($clientName !== '' ? ' (Cliente: ' . $clientName . ')' : ''),
            'km' => (int) ($v->current_mileage ?? 0),
            'engine_displacement_cc' => $v->engine_displacement_cc ? (int) $v->engine_displacement_cc : null,
        ];
    })),
    clientsList: @js($clients->map(fn($c) => ['id' => $c->id, 'first_name' => $c->first_name, 'last_name' => $c->last_name, 'person_type' => $c->person_type, 'document_number' => $c->document_number, 'label' => trim($c->first_name . ' ' . $c->last_name . ' - ' . $c->person_type . ' ' . $c->document_number)])),
    departments: @js($departments ?? []),
    provinces: @js($provinces ?? []),
    districts: @js($districts ?? []),
    vehicleTypes: @js($vehicleTypes->map(fn($type) => ['id' => $type->id, 'name' => $type->name])),
    servicesCatalog: @js($services->map(fn($s) => [
        'id' => $s->id,
        'name' => $s->name,
        'base_price' => (float) $s->base_price,
        'type' => $s->type,
        'price_tiers' => $s->priceTiers->map(fn($tier) => [
            'max_cc' => (int) $tier->max_cc,
            'price' => (float) $tier->price,
        ])->values(),
    ])),
    historyBase: @js(route('workshop.clients.history', ['person' => '__PERSON__'])),
    historyUrl: '',
    historyHtml: '',
    historyLoading: false,
    historyRequestToken: 0,
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
        current_mileage: '',
        engine_displacement_cc: ''
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
    selectedServiceIds: @js(collect(old('service_lines', []))->pluck('service_id')->filter()->map(fn($id) => (string) $id)->values()),
    serviceLines: [],
    inventoryItemsByVehicleType: @js($inventoryItemsByVehicleType ?? []),
    inventoryChecks: @js(collect(old('inventory', []))->map(fn ($v) => (bool) $v)->all()),
    defaultInventoryItems: @js([
        ['item_key' => 'ESPEJOS', 'label' => 'Espejos'],
        ['item_key' => 'FARO_DELANTERO', 'label' => 'Faro delantero'],
        ['item_key' => 'DIRECCIONALES', 'label' => 'Direccionales'],
        ['item_key' => 'TAPON_GASOLINA', 'label' => 'Tapon de gasolina'],
        ['item_key' => 'PEDALES', 'label' => 'Pedales'],
        ['item_key' => 'CLAXON', 'label' => 'Claxon'],
        ['item_key' => 'ASIENTOS', 'label' => 'Asientos'],
        ['item_key' => 'LUZ_STOP_TRASERA', 'label' => 'Luz stop trasera'],
        ['item_key' => 'CUBIERTAS_COMPLETAS', 'label' => 'Cubiertas completas'],
        ['item_key' => 'TACOMETROS', 'label' => 'Tacometros'],
        ['item_key' => 'STEREO', 'label' => 'Stereo'],
        ['item_key' => 'PARABRISAS', 'label' => 'Parabrisas'],
        ['item_key' => 'TAPON_RADIADORES', 'label' => 'Tapon de radiadores'],
        ['item_key' => 'FILTRO_AIRE', 'label' => 'Filtro de aire'],
        ['item_key' => 'BATERIA', 'label' => 'Bateria'],
        ['item_key' => 'LLAVES', 'label' => 'Llaves'],
    ]),
    selectedVehicleTypeId: '',
    showInventory: @js($showInventoryDefault ?? true),
    syncVehicle() {
        const selected = this.vehicles.find(v => String(v.id) === String(this.selectedVehicleId));
        if (!selected) return;
        this.selectedClientId = selected.client_person_id ? String(selected.client_person_id) : '';
        this.mileageIn = selected.km ? String(selected.km) : '';
        this.vehicleSearch = selected.display_label || selected.label || '';
        this.selectedVehicleTypeId = selected.vehicle_type_id ? String(selected.vehicle_type_id) : String(this.quickVehicle.vehicle_type_id || '');
        this.syncHistoryUrl();
        this.refreshServiceLinePrices();
    },
    inventoryItemsForSelectedVehicle() {
        const typeId = String(this.selectedVehicleTypeId || this.quickVehicle.vehicle_type_id || '');
        const items = this.inventoryItemsByVehicleType[typeId] || [];
        return items.length ? items : this.defaultInventoryItems;
    },
    async openClientHistory() {
        if (!String(this.selectedClientId || '').trim()) return;
        this.syncHistoryUrl();
        const requestToken = ++this.historyRequestToken;
        this.historyLoading = true;
        this.historyHtml = '';
        this.$dispatch('open-maintenance-client-history');
        try {
            const response = await fetch(this.historyUrl, {
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
            });
            const html = await response.text();
            if (requestToken !== this.historyRequestToken) return;
            this.historyHtml = response.ok ? html : `<div class='p-6 text-sm text-red-600'>No se pudo cargar el historial del cliente.</div>`;
        } catch (error) {
            if (requestToken !== this.historyRequestToken) return;
            this.historyHtml = `<div class='p-6 text-sm text-red-600'>No se pudo cargar el historial del cliente.</div>`;
        } finally {
            if (requestToken === this.historyRequestToken) {
                this.historyLoading = false;
                this.$nextTick(() => this.$dispatch('maintenance-client-history-loaded'));
            }
        }
    },
    syncHistoryUrl() {
        this.historyUrl = this.selectedClientId
            ? `${this.historyBase.replace('__PERSON__', this.selectedClientId)}?modal=1&_=${Date.now()}`
            : '';
    },
    resolveDefaultClientId() {
        const matchClientesVarios = this.clientsList.find((client) => {
            const fullName = `${client.first_name || ''} ${client.last_name || ''}`.trim().toLowerCase();
            return fullName.includes('clientes') && fullName.includes('varios');
        });
        if (matchClientesVarios?.id) {
            return String(matchClientesVarios.id);
        }
        const firstClient = this.clientsList[0];
        return firstClient?.id ? String(firstClient.id) : '';
    },
    ensureQuickVehicleClient() {
        const fallbackClientId = this.resolveDefaultClientId();
        if (!String(this.selectedClientId || '').trim()) {
            this.selectedClientId = fallbackClientId;
        }
        if (!String(this.quickVehicle.client_person_id || '').trim()) {
            this.quickVehicle.client_person_id = this.selectedClientId || fallbackClientId;
        }
        this.syncHistoryUrl();
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
        this.openClientHistory();
    },
    onVehicleSearchInput() {
        this.vehicleDropdownOpen = true;
        if (!String(this.vehicleSearch || '').trim()) {
            this.selectedVehicleId = '';
            this.selectedClientId = '';
            this.refreshServiceLinePrices();
        }
    },
    closeVehicleDropdown() {
        this.vehicleDropdownOpen = false;
    },
    isServiceSelected(serviceId) {
        return this.selectedServiceIds.includes(String(serviceId));
    },
    orderedServiceTiers(service) {
        return Array.isArray(service?.price_tiers)
            ? [...service.price_tiers].sort((a, b) => Number(a.max_cc || 0) - Number(b.max_cc || 0))
            : [];
    },
    selectedVehicleCc() {
        const selected = this.vehicles.find(v => String(v.id) === String(this.selectedVehicleId));
        return Number(selected?.engine_displacement_cc || 0);
    },
    resolveServicePricing(service) {
        const tiers = this.orderedServiceTiers(service);
        const basePrice = Number(service?.base_price || 0);

        if (!tiers.length) {
            return {
                price: basePrice,
                label: basePrice > 0 ? 'Precio base' : 'Sin tarifa configurada',
            };
        }

        const vehicleCc = this.selectedVehicleCc();
        if (vehicleCc > 0) {
            const matchedTier = tiers.find(tier => vehicleCc <= Number(tier.max_cc || 0)) || tiers[tiers.length - 1];
            return {
                price: Number(matchedTier?.price || 0),
                label: `Hasta ${Number(matchedTier?.max_cc || 0)}cc`,
            };
        }

        if (basePrice > 0) {
            return {
                price: basePrice,
                label: 'Base / sin cilindrada',
            };
        }

        const firstTier = tiers[0];
        return {
            price: Number(firstTier?.price || 0),
            label: `Tabla desde ${Number(firstTier?.max_cc || 0)}cc`,
        };
    },
    resolveServicePrice(service) {
        return Number(this.resolveServicePricing(service).price || 0);
    },
    servicePricingLabel(service) {
        return this.resolveServicePricing(service).label;
    },
    refreshServiceLinePrices() {
        this.serviceLines = this.serviceLines.map(line => {
            const service = this.servicesCatalog.find(item => String(item.id) === String(line.service_id));
            if (!service) {
                return line;
            }

            return {
                ...line,
                unit_price: this.resolveServicePrice(service),
            };
        });
    },
    toggleService(serviceId) {
        const id = String(serviceId);
        if (this.isServiceSelected(id)) {
            this.selectedServiceIds = this.selectedServiceIds.filter(item => item !== id);
        } else {
            this.selectedServiceIds.push(id);
        }
        this.syncServiceLinesFromSelection();
    },
    syncServiceLinesFromSelection() {
        this.serviceLines = this.selectedServiceIds
            .map(id => {
                const service = this.servicesCatalog.find(s => String(s.id) === String(id));
                if (!service) return null;
                return {
                    service_id: String(service.id),
                    qty: 1,
                    unit_price: this.resolveServicePrice(service),
                };
            })
            .filter(Boolean);
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
        const fallbackClientId = this.resolveDefaultClientId();
        this.quickVehicle = {
            client_person_id: this.selectedClientId || fallbackClientId || '',
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
            current_mileage: this.mileageIn || '',
            engine_displacement_cc: ''
        };
        this.quickVehicleError = '';
    },
    toggleQuickVehicle() {
        this.creatingVehicle = !this.creatingVehicle;
        if (this.creatingVehicle) {
            this.resetQuickVehicle();
            this.ensureQuickVehicleClient();
        }
    },
    async saveQuickVehicle() {
        this.quickVehicleError = '';
        this.ensureQuickVehicleClient();
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
            this.syncHistoryUrl();
            this.refreshServiceLinePrices();
            this.creatingVehicle = false;
            this.resetQuickVehicle();
            this.openClientHistory();
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
            this.syncHistoryUrl();
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
            this.quickClient.fecha_nacimiento = payload?.fecha_nacimiento || '';
            this.quickClient.genero = payload?.genero || '';
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
    signaturePoint(evt) {
        const rect = this.signatureCanvas.getBoundingClientRect();
        const source = evt.touches?.[0] ?? evt;
        const scaleX = this.signatureCanvas.width / rect.width;
        const scaleY = this.signatureCanvas.height / rect.height;
        return {
            x: (source.clientX - rect.left) * scaleX / (window.devicePixelRatio || 1),
            y: (source.clientY - rect.top) * scaleY / (window.devicePixelRatio || 1),
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
    },
    damagePreviews: { 0: [], 1: [], 2: [], 3: [] },
    updateDamagePreviews(index, event) {
        const files = Array.from(event?.target?.files || []);
        this.damagePreviews[index] = files.map(file => ({
            name: file.name,
            url: URL.createObjectURL(file),
        }));
    }
}" x-init="$nextTick(() => { if (selectedVehicleId) { syncVehicle() } ensureQuickVehicleClient(); syncHistoryUrl(); initSignaturePad(); syncServiceLinesFromSelection(); })">
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
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cliente</label>
                        <div class="flex items-center gap-2">
                            <select x-model="quickVehicle.client_person_id" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" required>
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
                    <input x-model="quickVehicle.engine_displacement_cc" type="number" min="1" max="5000" class="h-10 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cilindrada (cc)">
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
            <div class="md:col-span-3 flex flex-col gap-3 md:flex-row md:items-end">
                <div class="w-full min-w-0 md:flex-[2.4_1_0%]">
                    <div class="relative flex w-full items-end gap-2">
                        <input type="hidden" name="vehicle_id" x-model="selectedVehicleId" required>
                        <div class="relative min-w-0 flex-1" @click.outside="closeVehicleDropdown()">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo</label>
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

                <div class="shrink-0" style="width: 96px; min-width: 96px; max-width: 96px; flex: 0 0 96px;">
                    <label class="mb-1 block text-sm font-medium text-gray-700">KM ing.</label>
                    <input
                        name="mileage_in"
                        type="number"
                        min="0"
                        x-model="mileageIn"
                        class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                        placeholder="KM ingreso">
                </div>
                <div class="min-w-0 w-full md:flex-[2.5_1_0%]">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Diagnostico</label>
                    <input name="diagnosis_text" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Diagnostico inicial (opcional)">
                </div>

                <div class="flex flex-col">
                    <label class="mb-1 block text-sm font-medium text-gray-700 opacity-0">Spacer</label>
                    <label class="inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap text-sm text-gray-700">
                        <input type="checkbox" name="tow_in" value="1" class="h-4 w-4 rounded border-gray-300">
                        Ingreso en grua
                    </label>
                </div>

            </div>

            <textarea name="observations" rows="3" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" placeholder="Observaciones"></textarea>

            <div class="rounded-xl border border-gray-200 bg-white p-4 md:col-span-3">
                <div class="mb-3">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" x-model="showInventory" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Mostrar inventario recibido</span>
                    </label>
                </div>

                <div x-show="showInventory" x-cloak>
                    <div class="mb-3">
                        <h4 class="text-sm font-semibold text-gray-800">Inspeccion e inventario recibido</h4>
                        <p class="text-xs text-gray-500">Estos datos se guardan al iniciar el mantenimiento.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        <template x-for="item in inventoryItemsForSelectedVehicle()" :key="item.item_key">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox"
                                    :name="`inventory[${item.item_key}]`"
                                    value="1"
                                    x-model="inventoryChecks[item.item_key]"
                                    class="h-4 w-4 rounded border-gray-300">
                                <span x-text="item.label"></span>
                            </label>
                        </template>
                    </div>
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
                                    <template x-for="(preview, pIndex) in (damagePreviews[{{ $idx }}] || [])" :key="`damage-preview-{{ $idx }}-${pIndex}`">
                                        <a :href="preview.url" target="_blank" class="block h-16 w-16 overflow-hidden rounded border border-gray-200">
                                            <img :src="preview.url" :alt="preview.name" class="h-full w-full object-cover">
                                        </a>
                                    </template>
                                </div>
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
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Trabajo a realizar</h4>
                    <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700" x-text="`${selectedServiceIds.length} seleccionado(s)`"></span>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-3">
                    <template x-for="service in servicesCatalog" :key="`service-check-${service.id}`">
                        <label class="inline-flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 hover:border-indigo-300">
                            <div class="min-w-0 flex-1">
                                <span class="block truncate font-medium" x-text="service.name"></span>
                                <span class="block text-xs text-gray-500" x-text="servicePricingLabel(service)"></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="whitespace-nowrap text-xs font-bold text-emerald-600" x-text="`S/ ${resolveServicePrice(service).toFixed(2)}`"></span>
                                <input
                                    type="checkbox"
                                    :checked="isServiceSelected(service.id)"
                                    @change="toggleService(service.id)"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                            </div>
                        </label>
                    </template>
                </div>

                <template x-for="(line, index) in serviceLines" :key="`line-hidden-${index}`">
                    <div>
                        <input type="hidden" :name="`service_lines[${index}][service_id]`" :value="line.service_id">
                        <input type="hidden" :name="`service_lines[${index}][qty]`" :value="line.qty">
                        <input type="hidden" :name="`service_lines[${index}][unit_price]`" :value="line.unit_price">
                    </div>
                </template>

                <div class="mt-3 border-t border-gray-200 pt-2 text-right text-sm font-semibold text-gray-800">
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
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
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

    <x-ui.modal
        x-data="{
            open: false,
            resetPosition() {
                this.$el.scrollTop = 0;
                this.$refs.historyViewport?.scrollTo({ top: 0, behavior: 'auto' });
                this.$refs.historyScroll?.scrollTo({ top: 0, behavior: 'auto' });
            },
            close() {
                this.open = false;
                this.resetPosition();
            }
        }"
        x-on:open-maintenance-client-history.window="open = true; $nextTick(() => resetPosition())"
        x-on:maintenance-client-history-loaded.window="if (open) { $nextTick(() => resetPosition()) }"
        x-on:close-maintenance-client-history.window="close()"
        :isOpen="false"
        :showCloseButton="false"
        :bodyScrollable="false"
        class="max-w-5xl w-[92vw]">
        <div x-ref="historyViewport" class="flex max-h-full min-h-0 flex-col p-4 sm:p-5" style="height: min(78vh, calc(100vh - 5rem), calc(100dvh - 5rem)); max-height: min(78vh, calc(100vh - 5rem), calc(100dvh - 5rem));">
            <div class="mb-3 flex shrink-0 items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Historial del cliente</h3>
                    <p class="mt-1 text-sm text-gray-500">Incluye fecha del servicio, tecnico responsable, observaciones y vehiculo.</p>
                </div>
                <button type="button" @click="close()" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <template x-if="historyLoading">
                    <div class="flex h-full min-h-[18rem] items-center justify-center bg-slate-50">
                        <div class="text-center">
                            <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-orange-500"></div>
                            <p class="mt-4 text-sm font-medium text-slate-500">Cargando historial del cliente...</p>
                        </div>
                    </div>
                </template>
                <template x-if="!historyLoading && historyHtml">
                    <div x-ref="historyScroll" class="h-full overflow-y-auto overscroll-contain bg-slate-50" style="overscroll-behavior: contain;">
                        <div  x-html="historyHtml"></div>
                    </div>
                </template>
                <template x-if="!historyLoading && !historyHtml">
                    <div class="flex h-full items-center justify-center bg-slate-50 text-sm font-medium text-gray-500">
                        Seleccione un cliente para ver su historial.
                    </div>
                </template>
            </div>
        </div>
    </x-ui.modal>
</div>
@endsection
