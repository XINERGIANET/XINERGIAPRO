@extends('layouts.app')

@section('content')
@php
    $editing = $editingOrder ?? null;
    $hasFormOld = session()->hasOldInput();
    $svcOld = old('service_lines');
    if ($hasFormOld && is_array($svcOld)) {
        $serviceLinesForUi = collect($svcOld)->map(function ($l) {
            $did = $l['detail_id'] ?? null;
            return [
                'detail_id' => $did !== null && $did !== '' ? (int) $did : null,
                'service_id' => (string) ($l['service_id'] ?? ''),
                'description' => (string) ($l['description'] ?? ''),
                'qty' => (float) ($l['qty'] ?? 1),
                'unit_price' => (float) ($l['unit_price'] ?? 0),
            ];
        })->values()->all();
    } else {
        $serviceLinesForUi = $initialServiceLines ?? [];
    }
    $selectedIdsFromLines = collect($serviceLinesForUi)
        ->pluck('service_id')
        ->filter(fn ($id) => $id !== null && $id !== '' && trim((string) $id) !== '')
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
    $prodOld = old('product_lines');
    if ($hasFormOld && is_array($prodOld)) {
        $productLinesForUi = collect($prodOld)->map(function ($l) {
            $did = $l['detail_id'] ?? null;
            return [
                'detail_id' => $did !== null && $did !== '' ? (int) $did : null,
                'product_id' => (string) ($l['product_id'] ?? ''),
                'qty' => (float) ($l['qty'] ?? 1),
                'unit_price' => (float) ($l['unit_price'] ?? 0),
            ];
        })->values()->all();
    } else {
        $productLinesForUi = $initialProductLines ?? [];
    }
    $invOld = old('inventory');
    if ($hasFormOld && is_array($invOld)) {
        $inventoryForUi = collect($invOld)->map(fn ($v) => (bool) $v)->all();
    } else {
        $inventoryForUi = $initialInventoryChecks ?? [];
    }
    $vehicleIdDefault = (string) old('vehicle_id', $editing ? (string) $editing->vehicle_id : ($preFilledVehicleId ?? ''));
    $clientIdDefault = (string) old('client_person_id', $editing ? (string) $editing->client_person_id : ($preFilledClientId ?? ''));
    $appointmentIdDefault = (string) old('appointment_id', $editing ? (string) $editing->appointment_id : ($preFilledAppointmentId ?? ''));
    $diagnosisDefault = (string) old('diagnosis_text', $editing ? (string) $editing->diagnosis_text : ($preFilledDiagnosis ?? ''));

    if (old('mileage_in', null) !== null && old('mileage_in', null) !== '') {
        $mileageDefault = (string) old('mileage_in');
    } elseif ($editing && $editing->mileage_in !== null) {
        $mileageDefault = (string) $editing->mileage_in;
    } else {
        $mileageDefault = '';
    }
@endphp
<div x-data="Object.assign(typeof formAutocompleteHelpers === 'function' ? formAutocompleteHelpers() : {}, {
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
            'current_mileage' => $v->current_mileage ?? '',
            'engine_displacement_cc' => $v->engine_displacement_cc ? (int) $v->engine_displacement_cc : null,
            'soat_vencimiento' => $v->soat_vencimiento ? $v->soat_vencimiento->format('Y-m-d') : null,
            'revision_tecnica_vencimiento' => $v->revision_tecnica_vencimiento ? $v->revision_tecnica_vencimiento->format('Y-m-d') : null,
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
        'type' => $s->type !== null && $s->type !== '' ? strtolower(trim((string) $s->type)) : null,
        'has_validity' => (bool) $s->has_validity,
        'frequency_enabled' => (bool) $s->frequency_enabled,
        'frequency_each_km' => $s->frequency_each_km !== null ? (int) $s->frequency_each_km : null,
        'frequency_kms' => $s->frequencies->map(fn ($f) => (int) $f->km)->filter(fn ($k) => $k > 0)->values()->all(),
        'frequencies' => $s->frequencies
            ->map(fn ($f) => [
                'km' => (int) ($f->km ?? 0),
                'multiplier' => (float) ($f->multiplier ?? 1),
            ])
            ->filter(fn ($f) => (int) ($f['km'] ?? 0) > 0)
            ->values()
            ->all(),
        'price_tiers' => $s->priceTiers->map(fn($tier) => [
            'max_cc' => (int) $tier->max_cc,
            'price' => (float) $tier->price,
        ])->values(),
    ])),
    serviceTypeFilter: '',
    serviceKmFilterMode: 'all',
    serviceFilterKmLocal: '',
    historyBase: @js(route('workshop.vehicles.history', ['vehicle' => '__VEHICLE__'])),
    historyUrl: '',
    historyHtml: '',
    historyLoading: false,
    historyRequestToken: 0,
    selectedVehicleId: @js($vehicleIdDefault),
    vehicleSearch: '',
    filteredVehicleList: [],
    vehicleDropdownOpen: false,
    selectedClientId: @js($clientIdDefault),
    plateLookupUrl: @js(route('workshop.maintenance-board.vehicles.lookup-plate')),
    mileageIn: @js($mileageDefault),
    creatingVehicle: false,
    creatingVehicleLoading: false,
    lookingUpPlate: false,
    quickVehicleError: '',
    quickVehicleClientSearch: '',
    quickVehicleClientDropdownOpen: false,
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
        engine_displacement_cc: '',
        soat_vencimiento: '',
        revision_tecnica_vencimiento: ''
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
    productsCatalog: @js(collect($products ?? [])->map(function ($p) {
        $row = is_array($p) ? $p : (array) $p;
        $code = (string) ($row['code'] ?? '');
        $marca = trim((string) ($row['marca'] ?? ''));
        $desc = (string) ($row['description'] ?? '');
        $mid = $marca !== '' ? $marca . ' - ' : '';
        $label = trim($code !== '' ? $code . ' - ' . $mid . $desc : ($mid . $desc));
        return array_merge($row, ['label' => $label]);
    })->values()->all()),
    productLines: @js($productLinesForUi),
    editingMode: @json((bool) $editing),
    editingSignatureUrl: @js($editingSignatureUrl ?? null),
    editingVehicleLabel: @js($editingVehicleLabel ?? ''),
    editingClientLabel: @js($editingClientLabel ?? ''),
    selectedServiceIds: @js($selectedIdsFromLines),
    serviceCcOverrideById: {},
    servicePricesSeeded: false,
    preserveCustomCatalogPriceIds: {},
    totalsTick: 0,
    serviceLines: @js($serviceLinesForUi),
    inventoryItemsByVehicleType: @js($inventoryItemsByVehicleType ?? []),
    inventoryChecks: @js($inventoryForUi),
    selectedVehicleTypeId: '',
    showDamagesPreexisting: @js($showDamagesPreexistingDefault ?? true),
    diagnosisText: @js($diagnosisDefault),
    init() {
        if (this.selectedVehicleId) {
            this.$nextTick(() => {
                this.syncVehicle();
            });
        }
    },
    syncVehicle() {
        const selected = this.vehicles.find(v => String(v.id) === String(this.selectedVehicleId));
        if (!selected) return;
        this.selectedClientId = selected.client_person_id ? String(selected.client_person_id) : '';
        this.mileageIn = selected.km ? String(selected.km) : '';
        this.vehicleSearch = selected.display_label || selected.label || '';
        this.selectedVehicleTypeId = selected.vehicle_type_id ? String(selected.vehicle_type_id) : String(this.quickVehicle.vehicle_type_id || '');
        this.refreshVehicleFilter();
        this.syncHistoryUrl();
        this.refreshServiceLinePrices();
    },
    inventoryItemsForSelectedVehicle() {
        const typeId = String(this.selectedVehicleTypeId || this.quickVehicle.vehicle_type_id || '');
        const items = this.inventoryItemsByVehicleType[typeId] || [];
        return items;
    },
    async openClientHistory() {
        if (!String(this.selectedVehicleId || '').trim()) return;
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
            this.historyHtml = response.ok ? html : `<div class='p-6 text-sm text-red-600'>No se pudo cargar el historial del vehiculo.</div>`;
        } catch (error) {
            if (requestToken !== this.historyRequestToken) return;
            this.historyHtml = `<div class='p-6 text-sm text-red-600'>No se pudo cargar el historial del vehiculo.</div>`;
        } finally {
            if (requestToken === this.historyRequestToken) {
                this.historyLoading = false;
                this.$nextTick(() => this.$dispatch('maintenance-client-history-loaded'));
            }
        }
    },
    syncHistoryUrl() {
        this.historyUrl = this.selectedVehicleId
            ? `${this.historyBase.replace('__VEHICLE__', this.selectedVehicleId)}?modal=1&_=${Date.now()}`
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
        this.syncQuickVehicleClientSearch();
    },
    getQuickVehicleClientLabel(client) {
        const doc = String(client?.document_number ?? '').trim();
        const name = `${String(client?.first_name ?? '')} ${String(client?.last_name ?? '')}`.trim();
        if (doc && name) return `${doc} - ${name}`;
        return doc || name || '';
    },
    getClientById(id) {
        const idStr = String(id ?? '');
        return this.clientsList.find(c => String(c.id) === idStr) || null;
    },
    syncQuickVehicleClientSearch() {
        const client = this.getClientById(this.quickVehicle.client_person_id);
        this.quickVehicleClientSearch = this.getQuickVehicleClientLabel(client);
    },
    filteredQuickVehicleClients() {
        const q = String(this.quickVehicleClientSearch || '').trim().toLowerCase();
        if (!q) return this.clientsList.slice(0, 30);

        return this.clientsList
            .filter(c => {
                const doc = String(c.document_number ?? '').toLowerCase();
                const name = `${String(c.first_name ?? '')} ${String(c.last_name ?? '')}`.toLowerCase().trim();
                const label = this.getQuickVehicleClientLabel(c).toLowerCase();
                if (label.includes(q) || doc.includes(q) || name.includes(q)) return true;
                const parts = q.split(/\s*-\s*/).map(s => s.trim()).filter(Boolean);
                if (parts.length >= 2) {
                    return parts.every(part => doc.includes(part) || name.includes(part) || label.includes(part));
                }
                return false;
            })
            .slice(0, 30);
    },
    clearQuickVehicleClient() {
        this.quickVehicle.client_person_id = '';
        this.quickVehicleClientSearch = '';
        this.quickVehicleClientDropdownOpen = false;
    },
    selectQuickVehicleClient(client) {
        this.quickVehicle.client_person_id = String(client.id);
        this.quickVehicleClientSearch = this.getQuickVehicleClientLabel(client);
        this.quickVehicleClientDropdownOpen = false;
    },
    compactSearchText(s) {
        return String(s || '').toLowerCase().replace(/[\s\-_.]/g, '');
    },
    refreshVehicleFilter() {
        const q = String(this.vehicleSearch || '').trim().toLowerCase();
        if (!q) {
            this.filteredVehicleList = this.vehicles.slice();
            return;
        }
        const qCompact = this.compactSearchText(q);
        this.filteredVehicleList = this.vehicles
            .filter((v) => {
                const label = String(v.label || '').toLowerCase();
                const display = String(v.display_label || v.label || '').toLowerCase();
                const client = String(v.client_name || '').toLowerCase();
                if (label.includes(q) || display.includes(q) || client.includes(q)) {
                    return true;
                }
                if (qCompact.length >= 2) {
                    return this.compactSearchText(v.label).includes(qCompact)
                        || this.compactSearchText(v.display_label || v.label).includes(qCompact)
                        || this.compactSearchText(v.client_name).includes(qCompact);
                }
                return false;
            });
    },
    selectVehicle(vehicle) {
        this.selectedVehicleId = String(vehicle.id);
        this.vehicleSearch = vehicle.display_label || vehicle.label || '';
        this.vehicleDropdownOpen = false;
        this.syncVehicle();
        this.openClientHistory();
    },
    onVehicleSearchInput() {
        this.refreshVehicleFilter();
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
    resolveServiceFilterKm() {
        const raw = String(this.serviceFilterKmLocal || '').trim();
        if (raw !== '') {
            const n = parseInt(raw.replace(/\D/g, ''), 10);
            return Number.isFinite(n) && n >= 0 ? n : 0;
        }
        return parseInt(String(this.mileageIn || '').replace(/\D/g, ''), 10) || 0;
    },
    normalizeWorkshopServiceType(raw) {
        let t = String(raw ?? '').trim().toLowerCase();
        if (t.startsWith('prev')) {
            return 'preventivo';
        }
        if (t.startsWith('corr')) {
            return 'correctivo';
        }
        return t;
    },
    filteredServicesCatalog() {
        const km = this.resolveServiceFilterKm();
        const tf = this.normalizeWorkshopServiceType(this.serviceTypeFilter);
        const kmMode = this.serviceKmFilterMode || 'all';
        const list = Array.isArray(this.servicesCatalog) ? this.servicesCatalog : [];
        return list.filter((s) => {
            const t = this.normalizeWorkshopServiceType(s.type);
            if (tf === 'preventivo' && t !== 'preventivo') {
                return false;
            }
            if (tf === 'correctivo' && t !== 'correctivo') {
                return false;
            }
            if (kmMode !== 'by_mileage') {
                return true;
            }
            if (t === 'correctivo') {
                return true;
            }
            if (t !== 'preventivo') {
                return true;
            }
            if (!s.frequency_enabled) {
                return true;
            }
            if (km <= 0) {
                return true;
            }
            const kms = Array.isArray(s.frequency_kms) ? s.frequency_kms.filter((k) => Number(k) > 0) : [];
            if (kms.length > 0) {
                return kms.some((k) => km % Number(k) === 0);
            }
            const each = Number(s.frequency_each_km || 0);
            if (Number.isFinite(each) && each > 0) {
                return km % each === 0;
            }
            return true;
        });
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
    getServiceCcOverride(serviceId) {
        const raw = this.serviceCcOverrideById[String(serviceId)];
        return raw === undefined || raw === null || raw === '' ? 'auto' : String(raw);
    },
    setServiceCcOverride(serviceId, value) {
        const id = String(serviceId);
        if (this.preserveCustomCatalogPriceIds && this.preserveCustomCatalogPriceIds[id]) {
            const rest = { ...this.preserveCustomCatalogPriceIds };
            delete rest[id];
            this.preserveCustomCatalogPriceIds = rest;
        }
        const next = { ...this.serviceCcOverrideById };
        if (value === 'auto' || value === '' || value == null) {
            delete next[id];
        } else {
            next[id] = String(value);
        }
        this.serviceCcOverrideById = next;
        this.refreshServiceLinePrices();
    },
    servicePriceOptions(service) {
        const options = [{ value: 'auto', label: 'Según cil. vehículo' }];
        const basePrice = Number(service?.base_price || 0);
        if (basePrice > 0) {
            options.push({
                value: 'base',
                label: 'Precio base',
            });
        }
        this.orderedServiceTiers(service).forEach((tier) => {
            options.push({
                value: `tier:${tier.max_cc}`,
                label: `Hasta ${Number(tier.max_cc || 0)} cc`,
            });
        });
        return options;
    },
    resolveServicePricing(service) {
        const tiers = this.orderedServiceTiers(service);
        const basePrice = Number(service?.base_price || 0);
        const mode = this.getServiceCcOverride(service.id);

        if (!tiers.length) {
            return {
                price: basePrice,
                label: basePrice > 0 ? 'Precio base' : 'Sin tarifa configurada',
            };
        }

        if (mode === 'base' && basePrice > 0) {
            return {
                price: basePrice,
                label: 'Precio base',
            };
        }

        if (mode.startsWith('tier:')) {
            const rawMax = String(mode.slice(5)).trim();
            const maxCc = Number(rawMax);
            const tier = tiers.find((t) => String(t.max_cc) === rawMax)
                || tiers.find((t) => Number(t.max_cc || 0) === maxCc)
                || tiers.find((t) => Number(t.max_cc || 0) >= maxCc);
            if (tier) {
                return {
                    price: Number(tier.price || 0),
                    label: `Hasta ${Number(tier.max_cc || 0)}cc`,
                };
            }
        }

        const vehicleCc = this.selectedVehicleCc();
        if (vehicleCc > 0) {
            const matchedTier = tiers.find((tier) => vehicleCc <= Number(tier.max_cc || 0)) || tiers[tiers.length - 1];
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
        const basePrice = Number(this.resolveServicePricing(service).price || 0);
        const multiplier = Number(this.resolveServiceFrequencyMultiplier(service) || 1);
        return Number((basePrice * multiplier).toFixed(2));
    },
    servicePricingLabel(service) {
        return this.resolveServicePricing(service).label;
    },
    orderedServiceFrequencies(service) {
        const raw = Array.isArray(service?.frequencies) ? service.frequencies : [];
        return raw
            .map((item) => ({
                km: Number(item?.km || 0),
                multiplier: Number(item?.multiplier || 1),
            }))
            .filter((item) => item.km > 0 && Number.isFinite(item.multiplier) && item.multiplier > 0)
            .sort((a, b) => a.km - b.km);
    },
    resolveServiceFrequencyMultiplier(service) {
        if (!service?.frequency_enabled) {
            return 1;
        }
        const selectedKm = Number(this.resolveServiceFilterKm() || 0);
        if (!Number.isFinite(selectedKm) || selectedKm <= 0) {
            return 1;
        }
        const validFrequencies = this.orderedServiceFrequencies(service)
            .filter((item) => selectedKm % item.km === 0)
            .sort((a, b) => b.km - a.km);
        const frequency = validFrequencies[0] || null;
        return frequency ? Number(frequency.multiplier || 1) : 1;
    },
    autoResolvedPricing(service) {
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
            const matchedTier = tiers.find((tier) => vehicleCc <= Number(tier.max_cc || 0)) || tiers[tiers.length - 1];
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
    inferCcOverrideForSavedPrice(service, saved) {
        const p = Number(saved);
        if (!Number.isFinite(p)) {
            return null;
        }
        const tiers = this.orderedServiceTiers(service);
        const basePrice = Number(service?.base_price || 0);
        const eps = 0.02;
        const autoP = Number(this.autoResolvedPricing(service).price || 0);
        if (Math.abs(p - autoP) < eps) {
            return null;
        }
        if (basePrice > 0 && Math.abs(p - basePrice) < eps) {
            return 'base';
        }
        const matches = tiers.filter((t) => Math.abs(p - Number(t.price || 0)) < eps);
        if (matches.length) {
            matches.sort((a, b) => Number(a.max_cc) - Number(b.max_cc));
            return 'tier:' + matches[0].max_cc;
        }
        return null;
    },
    seedServiceCcOverridesFromLines() {
        const next = {};
        const preserve = {};
        const lines = Array.isArray(this.serviceLines) ? this.serviceLines : [];
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (this.isGlosaLine(line)) {
                continue;
            }
            const sid = String(line.service_id ?? '').trim();
            const service = this.servicesCatalog.find((item) => String(item.id) === sid);
            if (!service) {
                continue;
            }
            const p = Number(line.unit_price || 0);
            const autoP = Number(this.autoResolvedPricing(service).price || 0);
            const eps = 0.02;
            const inferred = this.inferCcOverrideForSavedPrice(service, p);
            if (inferred !== null && inferred !== 'auto') {
                next[sid] = String(inferred);
                continue;
            }
            if (this.editingMode && line.detail_id && Math.abs(p - autoP) >= eps) {
                preserve[sid] = true;
            }
        }
        this.serviceCcOverrideById = next;
        this.preserveCustomCatalogPriceIds = preserve;
    },
    refreshServiceLinePrices() {
        const next = this.serviceLines.map((line) => {
            if (this.isGlosaLine(line)) {
                return { ...line };
            }
            const sid = String(line.service_id ?? '').trim();
            const service = this.servicesCatalog.find((item) => String(item.id) === sid);
            if (!service) {
                return { ...line };
            }
            if (this.preserveCustomCatalogPriceIds && this.preserveCustomCatalogPriceIds[sid] && line.detail_id) {
                return { ...line };
            }
            if (!this.servicePricesSeeded && this.editingMode && line.detail_id) {
                return { ...line };
            }
            const price = this.resolveServicePrice(service);
            return {
                ...line,
                unit_price: price,
            };
        });
        this.serviceLines = next;
        this.totalsTick = (this.totalsTick || 0) + 1;
    },
    isGlosaLine(line) {
        return line == null || line.service_id == null || String(line.service_id).trim() === '';
    },
    lineKey(line, index) {
        if (line.detail_id) {
            return 'sl-d' + line.detail_id;
        }
        if (line.line_uid) {
            return line.line_uid;
        }
        return 'sl-s' + (line.service_id || '') + '-' + index;
    },
    addGlosaLine() {
        this.serviceLines.push({
            detail_id: null,
            service_id: '',
            description: '',
            qty: 1,
            unit_price: 0,
            line_uid: 'glosa_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9),
        });
    },
    removeServiceLineAt(index) {
        this.serviceLines.splice(index, 1);
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
        const glosas = this.serviceLines.filter((line) => this.isGlosaLine(line));
        const existingByService = {};
        this.serviceLines.forEach((line) => {
            if (!this.isGlosaLine(line) && line.service_id) {
                existingByService[String(line.service_id)] = line;
            }
        });
        const catalogLines = this.selectedServiceIds
            .map((id) => {
                const prev = existingByService[String(id)];
                if (prev) {
                    return prev;
                }
                const service = this.servicesCatalog.find(s => String(s.id) === String(id));
                if (!service) return null;
                return {
                    detail_id: null,
                    service_id: String(service.id),
                    description: '',
                    qty: 1,
                    unit_price: this.resolveServicePrice(service),
                    validity_months: service.has_validity ? (prev?.validity_months || '') : null,
                };
            })
            .filter(Boolean);
        this.serviceLines = catalogLines.concat(glosas);
        this.refreshServiceLinePrices();
    },
    lineSubtotal(line) {
        const qty = Number(line.qty || 0);
        const price = Number(line.unit_price || 0);
        return qty * price;
    },
    catalogPriceForProduct(productId) {
        const p = this.productsCatalog.find(x => String(x.id) === String(productId));
        return p ? Number(p.price || 0) : 0;
    },
    onProductLineProductChange(pline) {
        pline.unit_price = this.catalogPriceForProduct(pline.product_id);
    },
    addProductLine() {
        this.productLines.push({
            detail_id: null,
            product_id: '',
            qty: 1,
            unit_price: 0,
        });
    },
    removeProductLine(index) {
        this.productLines.splice(index, 1);
    },
    estimatedProductsTotal() {
        return this.productLines.reduce((sum, line) => {
            return sum + Number(line.qty || 0) * Number(line.unit_price || 0);
        }, 0);
    },
    estimatedTotal() {
        void this.serviceCcOverrideById;
        void this.totalsTick;
        const services = this.serviceLines.reduce((sum, line) => sum + this.lineSubtotal(line), 0);
        return services + this.estimatedProductsTotal();
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
        this.syncQuickVehicleClientSearch();
    },
    normalizePlateForLookup(value) {
        return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
    },
    async lookupQuickVehicleByPlate() {
        this.quickVehicleError = '';
        const normalizedPlate = this.normalizePlateForLookup(this.quickVehicle.plate);
        this.quickVehicle.plate = normalizedPlate;
        if (normalizedPlate.length < 5) {
            this.quickVehicleError = 'Ingrese una placa valida para buscar.';
            return;
        }
        this.lookingUpPlate = true;
        try {
            const response = await fetch(`${this.plateLookupUrl}?plate=${encodeURIComponent(normalizedPlate)}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();
            if (!response.ok || !payload?.status) {
                throw new Error(payload?.message || 'No se encontraron datos para la placa ingresada.');
            }
            this.quickVehicle.brand = String(payload.brand || this.quickVehicle.brand || '');
            this.quickVehicle.model = String(payload.model || this.quickVehicle.model || '');
            this.quickVehicle.year = String(payload.year || this.quickVehicle.year || '');
            this.quickVehicle.color = String(payload.color || this.quickVehicle.color || '');
            this.quickVehicle.vin = String(payload.vin || this.quickVehicle.vin || '');
            this.quickVehicle.engine_number = String(payload.engine_number || this.quickVehicle.engine_number || '');
            this.quickVehicle.chassis_number = String(payload.chassis_number || this.quickVehicle.chassis_number || '');
            this.quickVehicle.serial_number = String(payload.serial_number || this.quickVehicle.serial_number || '');
        } catch (error) {
            this.quickVehicleError = error?.message || 'No se pudo consultar la placa.';
        } finally {
            this.lookingUpPlate = false;
        }
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
        const kmStr = String(this.quickVehicle.current_mileage ?? '').trim();
        if (kmStr === '') {
            this.quickVehicleError = 'Debes registrar KM actual.';
            return;
        }

        const kmNum = Number(kmStr);
        if (!Number.isFinite(kmNum) || kmNum < 0) {
            this.quickVehicleError = 'KM actual debe ser un numero valido.';
            return;
        }

        const plate = String(this.quickVehicle.plate ?? '').trim();
        const vin = String(this.quickVehicle.vin ?? '').trim();
        const engineNumber = String(this.quickVehicle.engine_number ?? '').trim();
        if (plate === '' && vin === '' && engineNumber === '') {
            this.quickVehicleError = 'Debe registrar placa o VIN o numero de motor.';
            return;
        }

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
            this.refreshVehicleFilter();
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
    getDocumentStatus(date) {
        if (!date) return { label: 'NORMAL', color: 'bg-green-100 text-green-700', icon: 'ri-checkbox-circle-line' };
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const venc = new Date(date + 'T12:00:00'); // Use noon to avoid TZ issues
        venc.setHours(0, 0, 0, 0);
        const diff = Math.ceil((venc - today) / (1000 * 60 * 60 * 24));
        if (diff < 0) return { label: 'VENCIDO', color: 'bg-red-100 text-red-700', icon: 'ri-error-warning-line' };
        if (diff <= 30) return { label: 'POR VENCER', color: 'bg-yellow-100 text-yellow-700', icon: 'ri-time-line' };
        return { label: 'NORMAL', color: 'bg-green-100 text-green-700', icon: 'ri-checkbox-circle-line' };
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
            this.syncQuickVehicleClientSearch();
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
    namesFromReniecPayload(payload) {
        const n = String(payload?.first_name ?? payload?.nombres ?? '').trim();
        const apPat = String(payload?.apellido_paterno ?? '').trim();
        const apMat = String(payload?.apellido_materno ?? '').trim();
        const lastUnified = String(payload?.last_name ?? '').trim() || [apPat, apMat].filter(Boolean).join(' ');
        if (n !== '' || lastUnified !== '') {
            return { first_name: n, last_name: lastUnified };
        }
        return this.splitName(String(payload?.name ?? payload?.nombre_completo ?? ''));
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
            if (!response.ok || !payload?.status || (!payload?.name && !payload?.nombres && !payload?.nombre_completo && !payload?.first_name)) {
                throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
            }
            const parsed = this.namesFromReniecPayload(payload);
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
    async compressDamageImage(file) {
        if (!(file instanceof File) || !String(file.type || '').startsWith('image/')) {
            return file;
        }

        const maxDimension = 1600;
        const quality = 0.76;

        const imageUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });

        const image = await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = imageUrl;
        });

        let width = image.width || 0;
        let height = image.height || 0;

        if (width <= 0 || height <= 0) {
            return file;
        }

        if (width > maxDimension || height > maxDimension) {
            const ratio = Math.min(maxDimension / width, maxDimension / height);
            width = Math.max(1, Math.round(width * ratio));
            height = Math.max(1, Math.round(height * ratio));
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return file;
        }

        ctx.drawImage(image, 0, 0, width, height);

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', quality);
        });

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const nextName = String(file.name || 'damage.jpg').replace(/\.(png|webp|jpeg|jpg)$/i, '') + '.jpg';
        return new File([blob], nextName, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    },
    async updateDamagePreviews(index, event) {
        const input = event?.target;
        const files = Array.from(input?.files || []);
        const processedFiles = [];

        for (const file of files) {
            try {
                processedFiles.push(await this.compressDamageImage(file));
            } catch (error) {
                processedFiles.push(file);
            }
        }

        if (input) {
            const dataTransfer = new DataTransfer();
            processedFiles.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        this.damagePreviews[index] = processedFiles.map(file => ({
            name: file.name,
            url: URL.createObjectURL(file),
        }));
    },
})" x-init="$nextTick(() => { if (editingMode && editingVehicleLabel) { vehicleSearch = editingVehicleLabel; } if (selectedVehicleId) { syncVehicle() } else { refreshVehicleFilter(); } ensureQuickVehicleClient(); syncHistoryUrl(); initSignaturePad(); seedServiceCcOverridesFromLines(); servicePricesSeeded = true; refreshServiceLinePrices(); const __ep = @js($editingDamagePhotoPreviews ?? [0 => [], 1 => [], 2 => [], 3 => []]); [0,1,2,3].forEach((i) => { if (__ep && __ep[i] && __ep[i].length) { damagePreviews[i] = __ep[i]; } }); })">
    <x-common.page-breadcrumb
        :pageTitle="$editingOrder ? 'Editar ingreso a mantenimiento' : 'Nuevo Ingreso a Mantenimiento'"
        :crumbs="[
            ['label' => 'Tablero de Mantenimiento', 'url' => route('workshop.maintenance-board.index')],
            ['label' => $editingOrder ? 'Editar ingreso a mantenimiento' : 'Nuevo Ingreso a Mantenimiento'],
        ]"
    />

    <x-common.component-card :title="$editingOrder ? 'Editar ingreso a mantenimiento' : 'Nuevo ingreso a mantenimiento'" :desc="$editingOrder ? 'Actualiza datos de la OS, servicios y repuestos; el importe queda en cuenta para cobrar en venta y cobro.' : 'Registra el vehiculo, cliente y servicios para iniciar la OS desde una vista completa.'">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

   

        <form method="POST" action="{{ $editingOrder ? route('workshop.maintenance-board.update', $editingOrder) : route('workshop.maintenance-board.store') }}" enctype="multipart/form-data" data-turbo="false" @submit="syncSignature()" class="grid grid-cols-1 gap-3 md:grid-cols-3">
            @csrf
            @if($editingOrder)
                @method('PUT')
            @endif
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
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Cliente</label>
                        <div class="flex items-start gap-2">
                            <div class="relative w-full" @click.outside="quickVehicleClientDropdownOpen = false">
                                <input x-model="quickVehicleClientSearch"
                                    @focus="quickVehicleClientDropdownOpen = true"
                                    @click="quickVehicleClientDropdownOpen = true"
                                    @input="quickVehicleClientDropdownOpen = true"
                                    class="h-12 w-full rounded-2xl border border-slate-200 bg-white py-2 pl-4 pr-11 text-sm font-medium text-slate-700 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                                    placeholder="Buscar DNI o nombres"
                                    autocomplete="off"
                                    >
                                <button type="button"
                                    class="absolute right-2 top-1/2 z-10 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                    title="Limpiar cliente"
                                    x-show="String(quickVehicleClientSearch || '').trim() !== '' || String(quickVehicle.client_person_id || '').trim() !== ''"
                                    @click.stop="clearQuickVehicleClient()">
                                    <i class="ri-close-line text-lg"></i>
                                </button>
                                <div
                                    x-show="quickVehicleClientDropdownOpen"
                                    x-cloak
                                    class="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
                                >
                                    <template x-if="filteredQuickVehicleClients().length === 0">
                                        <p class="px-4 py-3 text-sm text-slate-500">Sin resultados.</p>
                                    </template>
                                    <template x-for="client in filteredQuickVehicleClients()" :key="`quick-client-${client.id}`">
                                        <button type="button"
                                            @click="selectQuickVehicleClient(client)"
                                            class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50 last:border-b-0">
                                            <span class="text-sm font-medium text-slate-800 truncate" x-text="`${client.document_number || ''} - ${((client.first_name || '') + ' ' + (client.last_name || '')).trim()}`"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <button class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-theme-xs"
                                    type="button"
                                    style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 12px 24px rgba(249,115,22,0.24);"
                                    @click="$dispatch('open-client-modal')">
                                <i class="ri-add-line text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de vehículo</label>
                        <select x-model="quickVehicle.vehicle_type_id" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                            <template x-for="type in vehicleTypes" :key="`type-${type.id}`">
                                <option :value="type.id" x-text="String(type.name || '').toUpperCase()"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Marca</label>
                        <input x-model="quickVehicle.brand" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Marca">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                        <input x-model="quickVehicle.model" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Modelo">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Año</label>
                        <input x-model="quickVehicle.year" type="number" min="1900" max="2100" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Año">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Color</label>
                        <input x-model="quickVehicle.color" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Color">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Placa <span class="text-red-600">*</span></label>
                        <div class="flex items-center gap-2">
                            <input x-model="quickVehicle.plate"
                                @blur="lookupQuickVehicleByPlate()"
                                @input="quickVehicle.plate = normalizePlateForLookup(quickVehicle.plate)"
                                class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Placa">
                            <button type="button"
                                @click="lookupQuickVehicleByPlate()"
                                :disabled="lookingUpPlate"
                                class="inline-flex h-10 shrink-0 items-center rounded-lg border border-blue-300 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="ri-search-line mr-1"></i>
                                <span x-text="lookingUpPlate ? 'Buscando...' : 'Buscar placa'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">VIN <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.vin" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="VIN">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro. motor <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.engine_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro. motor">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nro. chasis</label>
                        <input x-model="quickVehicle.chassis_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Nro. chasis">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Serial</label>
                        <input x-model="quickVehicle.serial_number" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Serial">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">KM actual <span class="text-red-600">*</span></label>
                        <input x-model="quickVehicle.current_mileage" type="number" min="0" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="KM actual">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cilindrada (cc)</label>
                        <input x-model="quickVehicle.engine_displacement_cc" type="number" min="1" max="5000" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Cilindrada (cc)">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">SOAT Vencimiento</label>
                        <input x-model="quickVehicle.soat_vencimiento" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Rev. Técnica Vencimiento</label>
                        <input x-model="quickVehicle.revision_tecnica_vencimiento" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                    </div>
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
                        <input type="hidden" name="appointment_id" value="{{ $appointmentIdDefault }}">
                        <div class="relative min-w-0 flex-1" @click.outside="closeVehicleDropdown()">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Vehiculo</label>
                            <input
                                x-model="vehicleSearch"
                                @focus="vehicleDropdownOpen = true; refreshVehicleFilter()"
                                @click="vehicleDropdownOpen = true; refreshVehicleFilter()"
                                @input="onVehicleSearchInput()"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Buscar vehiculo o cliente"
                                autocomplete="off"
                                required
                            >
                            <div class="mt-2 flex flex-wrap gap-2" x-show="selectedVehicleId" x-cloak>
                                <template x-if="vehicles.find(v => String(v.id) === String(selectedVehicleId))">
                                    <div class="flex flex-wrap gap-2">
                                        <div class="flex items-center gap-1 overflow-hidden rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-sm"
                                            :class="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).soat_vencimiento).color">
                                            <i :class="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).soat_vencimiento).icon"></i>
                                            <span>SOAT:</span>
                                            <span x-text="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).soat_vencimiento).label"></span>
                                            <span class="opacity-75" x-text="vehicles.find(v => String(v.id) === String(selectedVehicleId)).soat_vencimiento || '-'"></span>
                                        </div>
                                        <div class="flex items-center gap-1 overflow-hidden rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-sm"
                                            :class="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).revision_tecnica_vencimiento).color">
                                            <i :class="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).revision_tecnica_vencimiento).icon"></i>
                                            <span>REV. TÉCNICA:</span>
                                            <span x-text="getDocumentStatus(vehicles.find(v => String(v.id) === String(selectedVehicleId)).revision_tecnica_vencimiento).label"></span>
                                            <span class="opacity-75" x-text="vehicles.find(v => String(v.id) === String(selectedVehicleId)).revision_tecnica_vencimiento || '-'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
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
                                <template x-if="filteredVehicleList.length === 0">
                                    <p class="px-3 py-2 text-sm text-gray-500">Sin resultados.</p>
                                </template>
                                <template x-for="vehicle in filteredVehicleList" :key="`vehicle-search-${vehicle.id}`">
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
                    <input name="diagnosis_text" x-model="diagnosisText" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Diagnostico inicial (opcional)">
                </div>

                <div class="flex flex-col">
                    <label class="mb-1 block text-sm font-medium text-gray-700 opacity-0">Spacer</label>
                    <label class="inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap text-sm text-gray-700">
                        <input type="checkbox" name="tow_in" value="1" class="h-4 w-4 rounded border-gray-300" @checked(old('tow_in', optional($editingOrder)->tow_in ?? false))>
                        Ingreso en grua
                    </label>
                </div>

            </div>
            <textarea name="observations" rows="3" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-3" placeholder="Observaciones">{{ old('observations', optional($editingOrder)->observations ?? '') }}</textarea>

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

                <div class="mb-3 mt-4">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" x-model="showDamagesPreexisting" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Mostrar Daños preexistentes recibido</span>
                    </label>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3" x-show="showDamagesPreexisting" x-cloak>
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
                                <textarea name="damages[{{ $idx }}][description]" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Describe daño preexistente (opcional)">{{ old('damages.' . $idx . '.description', data_get($editingDamageRows, $idx . '.description', '')) }}</textarea>
                                <select name="damages[{{ $idx }}][severity]" class="mt-2 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                    <option value="">Severidad</option>
                                    <option value="LOW" @selected(old('damages.' . $idx . '.severity', data_get($editingDamageRows, $idx . '.severity', '')) === 'LOW')>Baja</option>
                                    <option value="MED" @selected(old('damages.' . $idx . '.severity', data_get($editingDamageRows, $idx . '.severity', '')) === 'MED')>Media</option>
                                    <option value="HIGH" @selected(old('damages.' . $idx . '.severity', data_get($editingDamageRows, $idx . '.severity', '')) === 'HIGH')>Alta</option>
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
                    <template x-if="editingMode && editingSignatureUrl">
                        <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Firma registrada</p>
                            <img :src="editingSignatureUrl" alt="Firma del cliente" class="max-h-28 rounded border border-slate-200 bg-white object-contain">
                            <p class="mt-2 text-[11px] text-slate-500">Puede limpiar el lienzo abajo y firmar de nuevo para reemplazarla.</p>
                        </div>
                    </template>
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
            <div class="rounded-xl border border-gray-200 bg-white p-4 md:col-span-3">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Repuestos / productos</h4>
                        <p class="text-xs text-gray-500">Se cargan a la OS con precio de lista; el cobro al cliente se hace despues en venta y cobro.</p>
                    </div>
                    <button type="button" @click="addProductLine()" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                        <i class="ri-add-line"></i><span class="ml-1">Agregar producto</span>
                    </button>
                </div>

                <template x-if="productLines.length === 0">
                    <p class="text-sm text-gray-500">Sin productos cargados.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(pline, pindex) in productLines" :key="pline.detail_id ? `pl-${pline.detail_id}` : `pl-new-${pindex}`">
                        <div class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-gray-50/80 p-3 md:flex-row md:flex-wrap md:items-end">
                            <input type="hidden" :name="`product_lines[${pindex}][detail_id]`" :value="pline.detail_id || ''">
                            <div class="min-w-0 flex-1 md:min-w-[220px]">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Producto</label>
                                <input type="hidden" :name="`product_lines[${pindex}][product_id]`" :value="pline.product_id">
                                <x-form.select-autocomplete-inline
                                    fieldKeyExpr="'pl-' + pindex"
                                    valueVar="pline.product_id"
                                    optionsListExpr="productsCatalog"
                                    optionLabel="label"
                                    optionValue="id"
                                    emptyText="Seleccionar producto..."
                                    pickExpr="pline.product_id = String(opt.id); onProductLineProductChange(pline)"
                                    inputClass="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                />
                            </div>
                            <div class="w-full md:w-24">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Cant.</label>
                                <input type="number" min="0.001" step="any" x-model="pline.qty" :name="`product_lines[${pindex}][qty]`" class="h-10 w-full rounded-lg border border-gray-300 px-2 text-sm" required>
                            </div>
                            <div class="w-full md:w-32">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">P. unit.</label>
                                <input type="number" min="0" step="0.01" x-model="pline.unit_price" :name="`product_lines[${pindex}][unit_price]`" class="h-10 w-full rounded-lg border border-gray-300 px-2 text-sm">
                            </div>
                            <button type="button" @click="removeProductLine(pindex)" class="h-10 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100 md:mb-0">Quitar</button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 md:col-span-3">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Trabajo a realizar</h4>
                    <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700" x-text="`${serviceLines.length} linea(s) en detalle`"></span>
                </div>

                <div class="mb-3 flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="min-w-0 flex-1 sm:max-w-[200px]">
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Tipo</label>
                        <select x-model="serviceTypeFilter" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">Todos</option>
                            <option value="preventivo">Preventivo</option>
                            <option value="correctivo">Correctivo</option>
                        </select>
                    </div>
                    <div class="w-full min-w-0 sm:w-48">
                        <label class="mb-1 block text-xs font-semibold text-gray-600">KM</label>
                        <select x-model="serviceFilterKmLocal" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="">KM de ingreso del vehículo</option>
                            @for ($km = 5000; $km <= 200000; $km += 5000)
                                <option value="{{ $km }}">{{ number_format($km, 0, ',', '.') }} km</option>
                            @endfor
                        </select>
                    </div>
                   
                </div>
                <p class="mb-3 text-[11px] text-gray-500">Elija un KM del listado o deje <span class="font-medium">KM de ingreso del vehículo</span> para usar el kilometraje del formulario. Con el filtro por intervalo, los <span class="font-medium">correctivos</span> siempre se muestran.</p>

                <template x-if="filteredServicesCatalog().length === 0">
                    <p class="mb-3 text-sm text-gray-500">No hay servicios que coincidan con los filtros. Cambie tipo o kilometraje.</p>
                </template>

                <div class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-3">
                    <template x-for="service in filteredServicesCatalog()" :key="`service-check-${service.id}`">
                        <div class="rounded-lg border border-gray-200 bg-white p-3 hover:border-indigo-300">
                            <label class="flex items-center justify-between gap-3 text-sm text-gray-800 cursor-pointer">
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate font-medium" x-text="service.name"></span>
                                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500" x-text="String(service.type || '') === 'preventivo' ? 'Preventivo' : (String(service.type || '') === 'correctivo' ? 'Correctivo' : (service.type || ''))"></span>
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
                            <template x-if="orderedServiceTiers(service).length > 0">
                                <div class="mt-2 flex justify-end pr-7">
                                    <select
                                        :value="getServiceCcOverride(service.id)"
                                        @click.stop
                                        @change="setServiceCcOverride(service.id, $event.target.value)"
                                        data-gsa-skip="true"
                                        class="h-8 w-[124px] rounded-md border border-gray-200 bg-gray-50 px-2 text-[11px] text-gray-600"
                                    >
                                        <template x-for="option in servicePriceOptions(service)" :key="`${service.id}-${option.value}`">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="service.has_validity && isServiceSelected(service.id)">
                                <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                    <span class="text-[11px] font-medium text-emerald-700">Vigencia próx. servicio:</span>
                                    <select 
                                        :value="(serviceLines.find(l => String(l.service_id) === String(service.id)) || {}).validity_months || ''"
                                        @change="let sl = serviceLines.find(l => String(l.service_id) === String(service.id)); if(sl) sl.validity_months = $event.target.value;"
                                        class="h-8 rounded border border-emerald-300 bg-emerald-50 px-2 text-xs text-emerald-800 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    >
                                        <option value="">No definir</option>
                                        <option value="6">En 6 meses</option>
                                        <option value="12">En 1 año</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="mt-4 rounded-lg border border-dashed border-indigo-200 bg-indigo-50/50 p-3 md:col-span-3">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h5 class="text-xs font-semibold uppercase tracking-wide text-indigo-900">Servicio por glosa (texto libre)</h5>
                        <button type="button" @click="addGlosaLine()" class="inline-flex items-center rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                            <i class="ri-add-line"></i><span class="ml-1">Agregar glosa</span>
                        </button>
                    </div>
                    <template x-for="(line, index) in serviceLines" :key="'glosa-' + lineKey(line, index)">
                        <template x-if="isGlosaLine(line)">
                            <div class="mb-2 flex flex-col gap-2 rounded-lg border border-indigo-100 bg-white p-2 sm:flex-row sm:flex-wrap sm:items-end">
                                <input type="hidden" :name="`service_lines[${index}][detail_id]`" :value="line.detail_id || ''">
                                <input type="hidden" :name="`service_lines[${index}][service_id]`" value="">
                                <div class="min-w-0 flex-1 sm:flex-[2]">
                                    <label class="mb-0.5 block text-[11px] font-semibold text-gray-600">Descripcion</label>
                                    <input type="text" x-model="line.description" maxlength="255" :name="`service_lines[${index}][description]`" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Ej. Trabajo especial / otro concepto">
                                </div>
                                <div class="w-full sm:w-24">
                                    <label class="mb-0.5 block text-[11px] font-semibold text-gray-600">Cant.</label>
                                    <input type="number" min="0.001" step="any" x-model="line.qty" :name="`service_lines[${index}][qty]`" class="h-10 w-full rounded-lg border border-gray-300 px-2 text-sm">
                                </div>
                                <div class="w-full sm:w-28">
                                    <label class="mb-0.5 block text-[11px] font-semibold text-gray-600">Monto</label>
                                    <input type="number" min="0" step="0.01" x-model="line.unit_price" :name="`service_lines[${index}][unit_price]`" class="h-10 w-full rounded-lg border border-gray-300 px-2 text-sm">
                                </div>
                                <button type="button" @click="removeServiceLineAt(index)" class="h-10 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100 sm:mb-0">Quitar</button>
                            </div>
                        </template>
                    </template>
                    <p class="text-xs text-gray-500" x-show="!serviceLines.some(l => isGlosaLine(l))" x-cloak>Use el boton para agregar un servicio que no este en el catalogo.</p>
                </div>

                <template x-for="(line, index) in serviceLines" :key="'hid-' + lineKey(line, index)">
                    <template x-if="!isGlosaLine(line)">
                        <div>
                            <input type="hidden" :name="`service_lines[${index}][detail_id]`" :value="line.detail_id || ''">
                            <input type="hidden" :name="`service_lines[${index}][service_id]`" :value="line.service_id">
                            <input type="hidden" :name="`service_lines[${index}][qty]`" x-model="line.qty">
                            <input type="hidden" :name="`service_lines[${index}][unit_price]`" x-model="line.unit_price">
                            <input type="hidden" :name="`service_lines[${index}][description]`" :value="line.description || ''">
                            <template x-if="line.validity_months">
                                <input type="hidden" :name="`service_lines[${index}][validity_months]`" :value="line.validity_months">
                            </template>
                        </div>
                    </template>
                </template>

                <div class="mt-3 border-t border-gray-200 pt-2 text-right text-sm font-semibold text-gray-800">
                    Total estimado: S/ <span x-text="(totalsTick, estimatedTotal().toFixed(2))"></span>
                </div>
            </div>

           
            <div class="md:col-span-3 mt-2 flex gap-2">
                <x-ui.button type="submit" size="md" variant="primary" style="background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff">
                    <i class="ri-play-circle-line"></i><span>{{ $editingOrder ? 'Guardar cambios' : 'Enviar a aprobación' }}</span>
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
                    <div class="flex items-center gap-2">
                        <input type="date" x-ref="quickClientFechaInput" x-model="quickClient.fecha_nacimiento" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-sm">
                        <button type="button" @click="$refs.quickClientFechaInput?.showPicker?.()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100" aria-label="Abrir calendario" title="Abrir calendario">
                            <i class="ri-calendar-line text-xl"></i>
                        </button>
                    </div>
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
                    <h3 class="text-lg font-semibold text-gray-800">Historial del vehiculo</h3>
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
