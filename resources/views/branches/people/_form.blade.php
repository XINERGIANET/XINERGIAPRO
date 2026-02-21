@php
    $selectedDepartmentId = old('department_id', $selectedDepartmentId ?? null);
    $selectedProvinceId = old('province_id', $selectedProvinceId ?? null);
    $selectedDistrictId = old('location_id', $selectedDistrictId ?? ($person->location_id ?? null));
    $selectedRoleIds = old('roles', $selectedRoleIds ?? []);
    $selectedProfileId = old('profile_id', $selectedProfileId ?? null);
    $userName = old('user_name', $userName ?? null);
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
        reniecLoading: false,
        reniecError: '',
        roleToAdd: '',
        userRoleId: 1,
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
            return this.selectedRoleIds.includes(this.userRoleId);
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
        async fetchReniec() {
            this.reniecError = '';
            if (String(this.personType).toUpperCase() !== 'DNI') {
                this.reniecError = 'La busqueda RENIEC solo aplica para DNI.';
                return;
            }
            const dni = String(this.documentNumber || '').trim();
            if (!/^\d{8}$/.test(dni)) {
                this.reniecError = 'Ingrese un DNI valido de 8 digitos.';
                return;
            }
            this.reniecLoading = true;
            try {
                const response = await fetch(`/api/reniec?dni=${encodeURIComponent(dni)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const payload = await response.json();
                if (!response.ok || !payload?.status || !payload?.name) {
                    throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
                }
                const parsed = this.splitName(payload.name);
                this.firstName = parsed.first_name;
                this.lastName = parsed.last_name;
            } catch (error) {
                this.reniecError = error?.message || 'Error consultando RENIEC.';
            } finally {
                this.reniecLoading = false;
            }
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
                placeholder="Ingrese el documento"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
            <button
                type="button"
                @click="fetchReniec()"
                :disabled="reniecLoading"
                class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60"
            >
                <i class="ri-search-line"></i>
                <span class="ml-1" x-text="reniecLoading ? 'Buscando...' : 'Buscar'"></span>
            </button>
        </div>
        <p x-show="reniecError" x-cloak class="mt-1 text-xs text-red-600" x-text="reniecError"></p>
        @error('document_number')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombres</label>
        <input
            type="text"
            name="first_name"
            x-model="firstName"
            required
            placeholder="Ingrese los nombres"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('first_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>


    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Apellidos</label>
        <input
            type="text"
            name="last_name"
            x-model="lastName"
            required
            placeholder="Ingrese los apellidos"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('last_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Fecha de nacimiento</label>
        <input
            type="date"
            name="fecha_nacimiento"
            value="{{ old('fecha_nacimiento', $person->fecha_nacimiento ?? '') }}"
            onclick="this.showPicker && this.showPicker()"
            onfocus="this.showPicker && this.showPicker()"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
        @error('fecha_nacimiento')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Genero</label>
        <select
            name="genero"
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
            value="{{ old('phone', $person->phone ?? '') }}"
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
            value="{{ old('email', $person->email ?? '') }}"
            placeholder="Ingrese el email"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('email')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 ">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Direccion</label>
        <input
            type="text"
            name="address"
            value="{{ old('address', $person->address ?? '') }}"
            required
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

    <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Roles</label>
        <div class="flex items-center gap-4 flex-nowrap">
            <template x-for="role in roles" :key="role.id">
                <label class="inline-flex items-center gap-2 whitespace-nowrap  bg-white px-4 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">
                    <input
                        type="checkbox"
                        name="roles[]"
                        :value="role.id"
                        :checked="selectedRoleIds.includes(role.id)"
                        @change="toggleRole(role.id)"
                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10"
                    />
                    <span x-text="role.name"></span>
                </label>
            </template>
        </div>
    </div>

    <template x-if="hasUserRole()">
        <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4">
            <div class="rounded-2xl border border-brand-100 bg-brand-50/40 p-4 dark:border-brand-500/20 dark:bg-brand-500/5">
               
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Usuario</label>
                        <input
                            type="text"
                            name="user_name"
                            value="{{ $userName }}"
                            placeholder="Ingrese el usuario"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Contraseña</label>
                        <input
                            type="password"
                            name="password"
                            placeholder="Ingrese la contraseña"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirmar contraseña</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            placeholder="Confirme la contraseña"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Perfil</label>
                        <select
                            name="profile_id"
                            x-model="selectedProfileId"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Seleccione perfil</option>
                            <template x-for="profile in profiles" :key="profile.id">
                                <option :value="profile.id" :selected="profile.id == selectedProfileId" x-text="profile.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
