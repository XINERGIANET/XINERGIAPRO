@php
    $selectedDepartmentId = old('department_id', $selectedDepartmentId ?? null);
    $selectedProvinceId = old('province_id', $selectedProvinceId ?? null);
    $selectedDistrictId = old('location_id', $selectedDistrictId ?? ($person->location_id ?? null));
    $selectedRoleIds = old('roles', $selectedRoleIds ?? []);
    $selectedProfileId = old('profile_id', $selectedProfileId ?? null);
    $userName = old('user_name', $userName ?? null);
    $defaultMenuOptionId = old('default_menu_option_id', $person?->user?->default_menu_option_id);
@endphp

<div
    class="grid gap-5"
    style="grid-template-columns: repeat(4, minmax(0, 1fr));"
    data-departments='@json($departments ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-provinces='@json($provinces ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-districts='@json($districts ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-department-id='@json(old('department_id', $selectedDepartmentId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-province-id='@json(old('province_id', $selectedProvinceId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-district-id='@json(old('location_id', $selectedDistrictId ?? ($person->location_id ?? null)), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-roles='@json($roles ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-selected-roles='@json($selectedRoleIds ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-profiles='@json($profiles ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-selected-profile='@json($selectedProfileId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-profile-menu-options-url="{{ (isset($company) && isset($branch)) ? route('admin.companies.branches.people.profile-menu-options', [$company, $branch]) : '' }}"
    data-default-menu-option-id='@json($defaultMenuOptionId, JSON_HEX_APOS | JSON_HEX_QUOT)'
    x-data="{
        departments: JSON.parse($el.dataset.departments || '[]'),
        provinces: JSON.parse($el.dataset.provinces || '[]'),
        districts: JSON.parse($el.dataset.districts || '[]'),
        departmentId: JSON.parse($el.dataset.departmentId || 'null') || '',
        provinceId: JSON.parse($el.dataset.provinceId || 'null') || '',
        districtId: JSON.parse($el.dataset.districtId || 'null') || '',
        roles: JSON.parse($el.dataset.roles || '[]'),
        profiles: JSON.parse($el.dataset.profiles || '[]'),
        selectedRoleIds: JSON.parse($el.dataset.selectedRoles || '[]'),
        selectedProfileId: JSON.parse($el.dataset.selectedProfile || 'null') || '',
        personType: @js(old('person_type', $person->person_type ?? 'DNI')),
        documentNumber: @js(old('document_number', $person->document_number ?? '')),
        firstName: @js(old('first_name', $person->first_name ?? '')),
        lastName: @js(old('last_name', $person->last_name ?? '')),
        fechaNacimiento: @js(old('fecha_nacimiento', $person->fecha_nacimiento ?? '')),
        genero: @js(old('genero', $person->genero ?? '')),
        phone: @js(old('phone', $person->phone ?? '')),
        email: @js(old('email', $person->email ?? '')),
        addressText: @js(old('address', $person->address ?? '')),
        lookupLoading: false,
        lookupError: '',
        rucLookupMeta: null,
        roleToAdd: '',
        userRoleId: '1',
        profileMenuOptionsUrl: ($el.dataset.profileMenuOptionsUrl || '').trim(),
        defaultMenuOptions: [],
        defaultMenuOptionId: (() => { const raw = JSON.parse($el.dataset.defaultMenuOptionId || 'null'); return raw != null && raw !== '' ? String(raw) : ''; })(),
        defaultMenuLoading: false,
        init() {
            if (!this.provinceId && this.districtId) {
                const district = this.districts.find(d => d.id == this.districtId);
                if (district) {
                    this.provinceId = district.parent_location_id ?? '';
                }
            }
            if (!this.departmentId && this.provinceId) {
                const province = this.provinces.find(p => p.id == this.provinceId);
                if (province) {
                    this.departmentId = province.parent_location_id ?? '';
                }
            }

            if (String(this.personType).toUpperCase() === 'RUC') {
                this.lastName = '';
                this.genero = '';
            }

            this.$watch('personType', (value) => {
                this.lookupError = '';
                if (String(value).toUpperCase() === 'RUC') {
                    this.lastName = '';
                    this.genero = '';
                } else {
                    this.rucLookupMeta = null;
                }
            });
            this.$watch('selectedProfileId', () => {
                this.loadDefaultMenuOptions();
            });
            this.$watch('selectedRoleIds', () => {
                if (this.hasUserRole() && this.selectedProfileId) {
                    this.loadDefaultMenuOptions();
                }
                if (!this.hasUserRole()) {
                    this.defaultMenuOptions = [];
                    this.defaultMenuOptionId = '';
                }
            });
            this.$nextTick(() => {
                if (this.hasUserRole() && this.selectedProfileId) {
                    this.loadDefaultMenuOptions();
                }
            });
        },
        async loadDefaultMenuOptions() {
            if (!this.profileMenuOptionsUrl || !this.selectedProfileId || !this.hasUserRole()) {
                this.defaultMenuOptions = [];
                return;
            }
            this.defaultMenuLoading = true;
            try {
                const url = new URL(this.profileMenuOptionsUrl, window.location.origin);
                url.searchParams.set('profile_id', String(this.selectedProfileId));
                const r = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await r.json();
                this.defaultMenuOptions = Array.isArray(data) ? data : [];
                const cur = String(this.defaultMenuOptionId || '');
                if (cur && !this.defaultMenuOptions.some(o => String(o.id) === cur)) {
                    this.defaultMenuOptionId = '';
                }
            } catch (e) {
                this.defaultMenuOptions = [];
            } finally {
                this.defaultMenuLoading = false;
            }
        },
        isRucPerson() {
            return String(this.personType || '').toUpperCase() === 'RUC';
        },
        addRole() {
            const roleId = parseInt(this.roleToAdd || 0, 10);
            if (!roleId) return;
            if (!this.selectedRoleIds.includes(roleId)) {
                this.selectedRoleIds.push(roleId);
            }
            this.roleToAdd = '';
        },
        toggleRole(roleId) {
            if (this.selectedRoleIds.includes(roleId)) {
                this.selectedRoleIds = this.selectedRoleIds.filter(id => id !== roleId);
            } else {
                this.selectedRoleIds.push(roleId);
            }
        },
        hasUserRole() {
            return this.selectedRoleIds.map(id => String(id)).includes(String(this.userRoleId));
        },
        get filteredProvinces() {
            return this.provinces.filter(p => p.parent_location_id == this.departmentId);
        },
        get filteredDistricts() {
            return this.districts.filter(d => d.parent_location_id == this.provinceId);
        },
        onDepartmentChange() {
            this.provinceId = '';
            this.districtId = '';
        },
        onProvinceChange() {
            this.districtId = '';
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
        normalizeText(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        },
        findDepartmentByName(name) {
            const target = this.normalizeText(name);
            return this.departments.find(item => this.normalizeText(item.name) === target) || null;
        },
        findProvinceByName(name, departmentId) {
            const target = this.normalizeText(name);
            return this.provinces.find(item =>
                String(item.parent_location_id || '') === String(departmentId || '') &&
                this.normalizeText(item.name) === target
            ) || null;
        },
        findDistrictByName(name, provinceId) {
            const target = this.normalizeText(name);
            return this.districts.find(item =>
                String(item.parent_location_id || '') === String(provinceId || '') &&
                this.normalizeText(item.name) === target
            ) || null;
        },
        applyLocationFromLookup(payload) {
            const department = this.findDepartmentByName(payload.department);
            if (!department) return;

            this.departmentId = String(department.id);

            const province = this.findProvinceByName(payload.province, department.id);
            if (!province) {
                this.provinceId = '';
                this.districtId = '';
                return;
            }

            this.provinceId = String(province.id);

            const district = this.findDistrictByName(payload.district, province.id);
            this.districtId = district ? String(district.id) : '';
        },
        normalizeApiDate(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';
            const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
            return match ? match[1] : '';
        },
        async fetchDocumentData() {
            this.lookupError = '';
            const type = String(this.personType || '').toUpperCase();

            if (type === 'DNI') {
                const dni = String(this.documentNumber || '').trim();
                if (!/^\d{8}$/.test(dni)) {
                    this.lookupError = 'Ingrese un DNI valido de 8 digitos.';
                    return;
                }
                this.lookupLoading = true;
                try {
                    const response = await fetch(`/api/reniec?dni=${encodeURIComponent(dni)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload?.status || (!payload?.name && !payload?.nombres && !payload?.nombre_completo && !payload?.first_name)) {
                        throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
                    }
                    const parsed = this.namesFromReniecPayload(payload);
                    this.firstName = parsed.first_name;
                    this.lastName = parsed.last_name;
                    this.fechaNacimiento = this.normalizeApiDate(payload?.fecha_nacimiento || '') || this.fechaNacimiento;
                    this.genero = payload?.genero || '';
                } catch (error) {
                    this.lookupError = error?.message || 'Error consultando RENIEC.';
                } finally {
                    this.lookupLoading = false;
                }
                return;
            }

            if (type === 'RUC') {
                const ruc = String(this.documentNumber || '').trim();
                if (!/^\d{11}$/.test(ruc)) {
                    this.lookupError = 'Ingrese un RUC valido de 11 digitos.';
                    return;
                }
                this.lookupLoading = true;
                try {
                    const response = await fetch(`/api/ruc?ruc=${encodeURIComponent(ruc)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload?.status) {
                        throw new Error(payload?.message || 'No se encontro informacion para el RUC ingresado.');
                    }
                    this.documentNumber = payload.ruc || ruc;
                    this.firstName = payload.legal_name || this.firstName;
                    this.lastName = '';
                    this.addressText = payload.address || this.addressText;
                    this.fechaNacimiento = this.normalizeApiDate(payload?.raw?.fecha_inscripcion || '');
                    this.genero = '';
                    this.applyLocationFromLookup(payload);
                    this.rucLookupMeta = {
                        trade_name: payload.trade_name || '',
                        condition: payload.condition || '',
                        taxpayer_status: payload.taxpayer_status || '',
                    };
                } catch (error) {
                    this.lookupError = error?.message || 'Error consultando RUC.';
                } finally {
                    this.lookupLoading = false;
                }
                return;
            }

            this.lookupError = 'La busqueda automatica solo aplica para DNI o RUC.';
        }
    }"
    x-init="init()"
>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo de persona</label>
        <select
            name="person_type"
            x-model="personType"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="DNI" @selected(old('person_type', $person->person_type ?? 'DNI') === 'DNI')>DNI</option>
            <option value="RUC" @selected(old('person_type', $person->person_type ?? 'DNI') === 'RUC')>RUC</option>
            <option value="CARNET DE EXTRANGERIA" @selected(old('person_type', $person->person_type ?? 'DNI') === 'CARNET DE EXTRANGERIA')>CARNET DE EXTRANGERIA</option>
            <option value="PASAPORTE" @selected(old('person_type', $person->person_type ?? 'DNI') === 'PASAPORTE')>PASAPORTE</option>
        </select>
        @error('person_type')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Documento</label>
        <div class="flex items-center gap-2">
            <input
                type="text"
                name="document_number"
                x-model="documentNumber"
                required
                :placeholder="isRucPerson() ? 'Ingrese el RUC' : 'Ingrese el documento'"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
            <button
                type="button"
                @click="fetchDocumentData()"
                :disabled="lookupLoading"
                class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
            >
                <i class="ri-search-line"></i>
            </button>
        </div>
        <p x-show="lookupError" x-cloak class="mt-1 text-xs text-red-600" x-text="lookupError"></p>
        @error('document_number')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            <span x-text="isRucPerson() ? 'Razon social' : 'Nombres'"></span>
            @if($firstNameRequired ?? true)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input
            type="text"
            name="first_name"
            x-model="firstName"
            :required="isRucPerson() || @js($firstNameRequired ?? true)"
            :placeholder="isRucPerson() ? 'Ingrese la razon social' : 'Ingrese los nombres'"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('first_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="!isRucPerson()" x-cloak>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Apellidos
            @if($lastNameRequired ?? true)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input
            type="text"
            name="last_name"
            x-model="lastName"
            :required="!isRucPerson() && @js($lastNameRequired ?? true)"
            placeholder="Ingrese los apellidos"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('last_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="isRucPerson() ? 'Fecha de inscripcion' : 'Fecha de nacimiento'"></label>
        <div class="flex items-center gap-2">
            <input
                type="date"
                x-ref="fechaNacimientoInput"
                name="fecha_nacimiento"
                x-model="fechaNacimiento"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            />
            <button
                type="button"
                @click="$refs.fechaNacimientoInput?.showPicker?.()"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                aria-label="Abrir calendario"
                title="Abrir calendario"
            >
                <i class="ri-calendar-line text-xl"></i>
            </button>
        </div>
        @error('fecha_nacimiento')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="!isRucPerson()" x-cloak>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Genero</label>
        <select
            name="genero"
            x-model="genero"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione genero</option>
            <option value="MASCULINO" @selected(old('genero', $person->genero ?? '') === 'MASCULINO')>MASCULINO</option>
            <option value="FEMENINO" @selected(old('genero', $person->genero ?? '') === 'FEMENINO')>FEMENINO</option>
            <option value="OTRO" @selected(old('genero', $person->genero ?? '') === 'OTRO')>OTRO</option>
        </select>
        @error('genero')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Telefono</label>
        <input
            type="text"
            name="phone"
            x-model="phone"
            placeholder="Ingrese el telefono"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('phone')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
        <input
            type="email"
            name="email"
            x-model="email"
            placeholder="Ingrese el email"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('email')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Direccion</label>
        <input
            type="text"
            name="address"
            x-model="addressText"
            placeholder="Ingrese la direccion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('address')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Departamento</label>
        <select
            name="department_id"
            x-model="departmentId"
            @change="onDepartmentChange()"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione departamento</option>
            <template x-for="department in departments" :key="department.id">
                <option :value="department.id" :selected="department.id == departmentId" x-text="department.name"></option>
            </template>
        </select>
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Provincia</label>
        <select
            name="province_id"
            x-model="provinceId"
            @change="onProvinceChange()"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione provincia</option>
            <template x-for="province in filteredProvinces" :key="province.id">
                <option :value="province.id" :selected="province.id == provinceId" x-text="province.name"></option>
            </template>
        </select>
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Distrito</label>
        <select
            name="location_id"
            x-model="districtId"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione distrito</option>
            <template x-for="district in filteredDistricts" :key="district.id">
                <option :value="district.id" :selected="district.id == districtId" x-text="district.name"></option>
            </template>
        </select>
        @error('location_id')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <template x-if="isRucPerson() && rucLookupMeta">
        <div class="col-span-4 grid gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Nombre comercial</p>
                <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.trade_name || '-'"></p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Condicion</p>
                <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.condition || '-'"></p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Estado</p>
                <p class="mt-1 text-sm text-slate-700" x-text="rucLookupMeta.taxpayer_status || '-'"></p>
            </div>
        </div>
    </template>

    <div class="col-span-4">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Roles</label>
        <div class="flex flex-wrap gap-x-6 gap-y-3">
            @foreach(($roles ?? []) as $role)
                <label class="inline-flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="roles[]"
                        value="{{ $role->id }}"
                        x-model="selectedRoleIds"
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $role->name }}</span>
                </label>
            @endforeach
        </div>
        @error('roles')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
        @error('roles.*')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-4 grid gap-5" x-show="hasUserRole()" x-cloak style="grid-template-columns: repeat(3, minmax(0, 1fr));">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Usuario</label>
            <input
                type="text"
                name="user_name"
                value="{{ old('user_name', $userName ?? '') }}"
                placeholder="Ingrese el usuario"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
            @error('user_name')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Perfil</label>
            <select
                name="profile_id"
                x-model="selectedProfileId"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            >
                <option value="">Seleccione perfil</option>
                <template x-for="profile in profiles" :key="profile.id">
                    <option :value="profile.id" x-text="profile.name"></option>
                </template>
            </select>
            @error('profile_id')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Menu por defecto</label>
            <select
                name="default_menu_option_id"
                x-model="defaultMenuOptionId"
                :disabled="!selectedProfileId || defaultMenuLoading"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 disabled:opacity-60"
            >
                <option value="">Dashboard (predeterminado)</option>
                <template x-for="opt in defaultMenuOptions" :key="opt.id">
                    <option :value="String(opt.id)" x-text="opt.name"></option>
                </template>
            </select>
            @error('default_menu_option_id')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Password</label>
            <input
                type="password"
                name="password"
                placeholder="Ingrese la password"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
            @error('password')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirmar password</label>
            <input
                type="password"
                name="password_confirmation"
                placeholder="Confirme la password"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
    </div>
</div>
