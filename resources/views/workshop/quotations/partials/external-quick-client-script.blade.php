<script>
(function () {
    const quickClientStoreUrl = @json($quickClientStoreUrl ?? route('admin.sales.clients.store'));
    const vehiclesForClientUrl = @json(route('admin.sales.quotations.vehicles-for-client'));
    const reniecApiUrl = @json(route('api.reniec'));
    const rucApiUrl = @json(route('api.ruc'));
    const departments = Array.isArray(@json($departments ?? [])) ? @json($departments ?? []) : [];
    const provinces = Array.isArray(@json($provinces ?? [])) ? @json($provinces ?? []) : [];
    const districts = Array.isArray(@json($districts ?? [])) ? @json($districts ?? []) : [];
    const branchDepartmentId = String(@json($branchDepartmentId ?? ''));
    const branchProvinceId = String(@json($branchProvinceId ?? ''));
    const branchDistrictId = String(@json($branchDistrictId ?? ''));
    let quickClientLoading = false;

    const syncAutocompleteDisplay = (selectEl) => {
        if (!selectEl) return;
        selectEl.dispatchEvent(new CustomEvent('sync-autocomplete-display'));
    };

    const normalizeApiDate = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const matchIso = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        if (matchIso) return matchIso[1];
        const matchSlash = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (matchSlash) return `${matchSlash[3]}-${matchSlash[2]}-${matchSlash[1]}`;
        return '';
    };

    const parseFullName = (fullName) => {
        const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return { first_name: '', last_name: '' };
        }
        if (parts.length === 1) {
            return { first_name: parts[0], last_name: '' };
        }
        return {
            first_name: parts.slice(0, -2).join(' ') || parts[0],
            last_name: parts.slice(-2).join(' '),
        };
    };

    const getQuickClientElements = () => ({
        modal: document.getElementById('quick-client-modal'),
        error: document.getElementById('quick-client-error'),
        personType: document.getElementById('quick-client-person-type'),
        documentNumber: document.getElementById('quick-client-document-number'),
        firstName: document.getElementById('quick-client-first-name'),
        lastName: document.getElementById('quick-client-last-name'),
        lastNameWrap: document.getElementById('quick-client-last-name-wrap'),
        firstNameLabel: document.getElementById('quick-client-first-name-label'),
        lastNameLabel: document.getElementById('quick-client-last-name-label'),
        dateLabel: document.getElementById('quick-client-date-label'),
        date: document.getElementById('quick-client-date'),
        gender: document.getElementById('quick-client-gender'),
        genderWrap: document.getElementById('quick-client-gender-wrap'),
        phone: document.getElementById('quick-client-phone'),
        email: document.getElementById('quick-client-email'),
        address: document.getElementById('quick-client-address'),
        department: document.getElementById('quick-client-department'),
        province: document.getElementById('quick-client-province'),
        district: document.getElementById('quick-client-district'),
        saveButton: document.getElementById('quick-client-save-button'),
        saveLabel: document.getElementById('quick-client-save-label'),
        searchButton: document.getElementById('quick-client-search-button'),
    });

    const normalizeLocationText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const renderSelectOptions = (select, items, selectedValue, placeholder) => {
        if (!select) return;
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = String(item.name || item.description || '');
            if (String(option.value) === String(selectedValue || '')) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    };

    const renderQuickClientLocationOptions = () => {
        const { department, province, district } = getQuickClientElements();
        const selectedDepartmentId = String(department?.value || '');
        const selectedProvinceId = String(province?.value || '');
        const selectedDistrictId = String(district?.value || '');

        renderSelectOptions(department, departments, selectedDepartmentId, 'Seleccione departamento');
        renderSelectOptions(
            province,
            provinces.filter((item) => String(item.parent_location_id || '') === selectedDepartmentId),
            selectedProvinceId,
            'Seleccione provincia'
        );
        renderSelectOptions(
            district,
            districts.filter((item) => String(item.parent_location_id || '') === selectedProvinceId),
            selectedDistrictId,
            'Seleccione distrito'
        );
    };

    const setQuickClientLocation = (departmentId, provinceId, districtId) => {
        const { department, province, district } = getQuickClientElements();
        if (department) department.value = String(departmentId || '');
        renderQuickClientLocationOptions();
        if (province) province.value = String(provinceId || '');
        renderQuickClientLocationOptions();
        if (district) district.value = String(districtId || '');
        renderQuickClientLocationOptions();
    };

    const onQuickClientDepartmentChange = () => {
        const { province, district } = getQuickClientElements();
        if (province) province.value = '';
        if (district) district.value = '';
        renderQuickClientLocationOptions();
    };

    const onQuickClientProvinceChange = () => {
        const { district } = getQuickClientElements();
        if (district) district.value = '';
        renderQuickClientLocationOptions();
    };

    const findDepartmentByName = (name) => {
        const target = normalizeLocationText(name);
        return departments.find((item) => normalizeLocationText(item.name) === target) || null;
    };

    const findProvinceByName = (name, departmentId) => {
        const target = normalizeLocationText(name);
        return provinces.find((item) => (
            String(item.parent_location_id || '') === String(departmentId || '')
            && normalizeLocationText(item.name) === target
        )) || null;
    };

    const findDistrictByName = (name, provinceId) => {
        const target = normalizeLocationText(name);
        return districts.find((item) => (
            String(item.parent_location_id || '') === String(provinceId || '')
            && normalizeLocationText(item.name) === target
        )) || null;
    };

    const applyQuickClientLocationFromLookup = (payload) => {
        const department = findDepartmentByName(payload?.department || '');
        if (!department) return;

        const province = findProvinceByName(payload?.province || '', department.id);
        const district = province ? findDistrictByName(payload?.district || '', province.id) : null;

        setQuickClientLocation(
            department.id,
            province ? province.id : '',
            district ? district.id : ''
        );
    };

    const clearQuickClientError = () => {
        const { error } = getQuickClientElements();
        if (!error) return;
        error.textContent = '';
        error.classList.add('hidden');
    };

    const showQuickClientError = (message) => {
        const { error } = getQuickClientElements();
        if (!error) return;
        error.textContent = message;
        error.classList.remove('hidden');
    };

    const toggleQuickClientLoading = (loading) => {
        quickClientLoading = loading;
        const { saveButton, saveLabel, searchButton } = getQuickClientElements();
        if (saveButton) saveButton.disabled = loading;
        if (searchButton) searchButton.disabled = loading;
        if (saveLabel) saveLabel.textContent = loading ? 'Guardando...' : 'Guardar cliente';
    };

    const syncQuickClientPersonTypeUI = () => {
        const {
            personType,
            firstNameLabel,
            lastName,
            lastNameWrap,
            lastNameLabel,
            dateLabel,
            gender,
            genderWrap,
            firstName,
        } = getQuickClientElements();
        const type = String(personType?.value || 'DNI').toUpperCase();
        const isRuc = type === 'RUC';

        if (firstNameLabel) {
            firstNameLabel.textContent = isRuc ? 'Razon social' : 'Nombres';
        }
        if (firstName) {
            firstName.placeholder = isRuc ? 'Razon social' : 'Nombres / Razon social';
        }
        if (lastNameLabel) {
            lastNameLabel.textContent = 'Apellidos';
        }
        if (lastNameWrap) {
            lastNameWrap.classList.toggle('hidden', isRuc);
        }
        if (lastName) {
            lastName.required = !isRuc;
            if (isRuc) {
                lastName.value = '';
            }
        }
        if (dateLabel) {
            dateLabel.textContent = isRuc ? 'Fecha de inscripcion' : 'Fecha de nacimiento';
        }
        if (genderWrap) {
            genderWrap.classList.toggle('hidden', isRuc);
        }
        if (gender && isRuc) {
            gender.value = '';
            syncAutocompleteDisplay(gender);
        }
    };

    const resetQuickClientForm = () => {
        const {
            personType,
            documentNumber,
            firstName,
            lastName,
            date,
            gender,
            phone,
            email,
            address,
        } = getQuickClientElements();

        if (personType) {
            personType.value = 'DNI';
            syncAutocompleteDisplay(personType);
        }
        if (documentNumber) documentNumber.value = '';
        if (firstName) firstName.value = '';
        if (lastName) lastName.value = '';
        if (date) date.value = '';
        if (gender) {
            gender.value = '';
            syncAutocompleteDisplay(gender);
        }
        if (phone) phone.value = '';
        if (email) email.value = '';
        if (address) address.value = '';
        clearQuickClientError();
        syncQuickClientPersonTypeUI();
        setQuickClientLocation(branchDepartmentId, branchProvinceId, branchDistrictId);
    };

    const openQuickClientModal = () => {
        const { modal, documentNumber } = getQuickClientElements();
        resetQuickClientForm();
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => documentNumber?.focus(), 40);
    };

    const closeQuickClientModal = () => {
        const { modal } = getQuickClientElements();
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        clearQuickClientError();
        toggleQuickClientLoading(false);
    };

    const getQuickClientPayload = () => {
        const {
            personType,
            documentNumber,
            firstName,
            lastName,
            date,
            gender,
            phone,
            email,
            address,
            district,
        } = getQuickClientElements();

        return {
            person_type: String(personType?.value || 'DNI').trim(),
            document_number: String(documentNumber?.value || '').trim(),
            first_name: String(firstName?.value || '').trim(),
            last_name: String(lastName?.value || '').trim(),
            fecha_nacimiento: String(date?.value || '').trim(),
            genero: String(gender?.value || '').trim(),
            phone: String(phone?.value || '').trim(),
            email: String(email?.value || '').trim(),
            address: String(address?.value || '').trim(),
            location_id: String(district?.value || '').trim(),
        };
    };

    const fetchQuickClientDocumentData = async () => {
        clearQuickClientError();
        const {
            personType,
            documentNumber,
            firstName,
            lastName,
            date,
            gender,
            address,
        } = getQuickClientElements();
        const type = String(personType?.value || 'DNI').toUpperCase();
        const documentValue = String(documentNumber?.value || '').trim();

        if (type === 'DNI') {
            if (!/^\d{8}$/.test(documentValue)) {
                showQuickClientError('Ingrese un DNI valido de 8 digitos.');
                return;
            }

            try {
                toggleQuickClientLoading(true);
                const response = await fetch(`${reniecApiUrl}?dni=${encodeURIComponent(documentValue)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok || payload?.status === false) {
                    throw new Error(payload?.message || 'Error consultando RENIEC.');
                }

                const fnVal = String(payload?.first_name ?? payload?.nombres ?? '').trim();
                const apPat = String(payload?.apellido_paterno ?? '').trim();
                const apMat = String(payload?.apellido_materno ?? '').trim();
                const lnUnified = String(payload?.last_name ?? '').trim() || [apPat, apMat].filter(Boolean).join(' ');
                const parsed = (!fnVal && !lnUnified)
                    ? parseFullName(payload?.name || payload?.nombre_completo || '')
                    : { first_name: fnVal, last_name: lnUnified };
                if (firstName) firstName.value = String(fnVal || parsed.first_name || '').trim();
                if (lastName) lastName.value = String(lnUnified || parsed.last_name || '').trim();
                if (date) date.value = normalizeApiDate(payload?.fecha_nacimiento || '');
                if (gender) {
                    gender.value = String(payload?.genero || '').trim();
                    syncAutocompleteDisplay(gender);
                }
            } catch (error) {
                showQuickClientError(error?.message || 'Error consultando RENIEC.');
            } finally {
                toggleQuickClientLoading(false);
            }
            return;
        }

        if (type === 'RUC') {
            if (!/^\d{11}$/.test(documentValue)) {
                showQuickClientError('Ingrese un RUC valido de 11 digitos.');
                return;
            }

            try {
                toggleQuickClientLoading(true);
                const response = await fetch(`${rucApiUrl}?ruc=${encodeURIComponent(documentValue)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok || payload?.status === false) {
                    throw new Error(payload?.message || 'Error consultando RUC.');
                }

                if (firstName) firstName.value = String(payload?.legal_name || '').trim();
                if (lastName) lastName.value = '';
                if (address) address.value = String(payload?.address || '').trim();
                if (date) date.value = normalizeApiDate(payload?.raw?.fecha_inscripcion || payload?.raw?.fechaInscripcion || '');
                applyQuickClientLocationFromLookup(payload);
            } catch (error) {
                showQuickClientError(error?.message || 'Error consultando RUC.');
            } finally {
                toggleQuickClientLoading(false);
            }
            return;
        }

        showQuickClientError('La busqueda automatica solo aplica para DNI o RUC.');
    };

    const applyNewClientToQuotationForm = (result) => {
        const sel = document.getElementById('quotation-external-client-select');
        const id = Number(result.id || 0);
        if (!id || !sel) {
            return;
        }
        const label = String(result.label || result.name || `${result.first_name || ''} ${result.last_name || ''}`).trim() || 'Cliente';
        let found = false;
        for (let i = 0; i < sel.options.length; i += 1) {
            if (Number(sel.options[i].value) === id) {
                sel.options[i].selected = true;
                found = true;
                break;
            }
        }
        if (!found) {
            const opt = document.createElement('option');
            opt.value = String(id);
            opt.textContent = label;
            sel.appendChild(opt);
        }
        sel.value = String(id);
        dispatchSelectUiSync(sel);
    };

    const saveQuickClient = async () => {
        clearQuickClientError();
        const payload = getQuickClientPayload();

        if (!payload.document_number || !payload.first_name || (!payload.last_name && payload.person_type !== 'RUC')) {
            showQuickClientError('Completa los campos obligatorios del cliente.');
            return;
        }

        try {
            toggleQuickClientLoading(true);
            const response = await fetch(String(quickClientStoreUrl || ''), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(result?.message || 'Error registrando cliente.');
            }

            applyNewClientToQuotationForm(result);
            closeQuickClientModal();
            loadVehiclesForQuotationClient(String(result.id || ''));
        } catch (error) {
            showQuickClientError(error?.message || 'Error registrando cliente.');
        } finally {
            toggleQuickClientLoading(false);
        }
    };

    const dispatchSelectUiSync = (selectEl) => {
        if (!selectEl) {
            return;
        }
        selectEl.dispatchEvent(new Event('input', { bubbles: true }));
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const syncQuotationVehicleEmptyHint = (vehicleCount, clientPersonId) => {
        const hint = document.getElementById('quotation-external-vehicle-empty-hint');
        const addBtn = document.getElementById('open-quotation-quick-vehicle-modal');
        const cid = Number(clientPersonId || 0);
        if (addBtn) {
            const hasTypes = window.__quotationExternalHasVehicleTypes === true;
            addBtn.disabled = cid <= 0 || !hasTypes;
        }
        if (!hint) {
            return;
        }
        const show = cid > 0 && vehicleCount === 0;
        hint.classList.toggle('hidden', !show);
    };

    const loadVehiclesForQuotationClient = (clientPersonId) => {
        const vehicleSelect = document.getElementById('quotation-external-vehicle-select');
        const submitBtn = document.getElementById('quotation-external-submit');
        const cid = Number(clientPersonId || 0);
        if (submitBtn) {
            submitBtn.disabled = cid <= 0;
        }
        if (!vehicleSelect) {
            return;
        }
        vehicleSelect.innerHTML = '<option value="">Sin vehículo</option>';
        if (cid <= 0) {
            syncQuotationVehicleEmptyHint(0, 0);
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => dispatchSelectUiSync(vehicleSelect));
            });
            return;
        }
        const url = new URL(vehiclesForClientUrl, window.location.origin);
        url.searchParams.set('client_person_id', String(cid));
        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json().then((data) => ({ res, data })))
            .then(({ res, data }) => {
                if (!res.ok) {
                    return;
                }
                const list = data.vehicles || [];
                list.forEach((v) => {
                    const opt = document.createElement('option');
                    opt.value = String(v.id);
                    opt.textContent = String(v.label || '');
                    vehicleSelect.appendChild(opt);
                });
                syncQuotationVehicleEmptyHint(list.length, cid);
                const applyVehicleSelection = () => {
                    const keep = Number(window.__quotationExternalOldVehicleId || 0);
                    if (keep > 0 && vehicleSelect.querySelector(`option[value="${keep}"]`)) {
                        vehicleSelect.value = String(keep);
                    } else if (list.length > 0) {
                        vehicleSelect.value = String(list[0].id);
                    } else {
                        vehicleSelect.value = '';
                    }
                    dispatchSelectUiSync(vehicleSelect);
                };
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(applyVehicleSelection);
                });
            })
            .catch(() => { /* ignorar */ });
    };

    window.quotationExternalLoadVehicles = loadVehiclesForQuotationClient;

    const onQuotationExternalClientChanged = () => {
        const sel = document.getElementById('quotation-external-client-select');
        loadVehiclesForQuotationClient(sel ? sel.value : '');
    };

    document.getElementById('open-quotation-quick-client-modal')?.addEventListener('click', openQuickClientModal);
    document.getElementById('quick-client-close-button')?.addEventListener('click', closeQuickClientModal);
    document.getElementById('quick-client-cancel-button')?.addEventListener('click', closeQuickClientModal);
    document.getElementById('quick-client-modal-backdrop')?.addEventListener('click', closeQuickClientModal);
    document.getElementById('quick-client-search-button')?.addEventListener('click', fetchQuickClientDocumentData);
    document.getElementById('quick-client-date-picker-btn')?.addEventListener('click', () => {
        const el = document.getElementById('quick-client-date');
        if (el && typeof el.showPicker === 'function') {
            el.showPicker();
        }
    });
    document.getElementById('quick-client-person-type')?.addEventListener('change', syncQuickClientPersonTypeUI);
    document.getElementById('quick-client-department')?.addEventListener('change', onQuickClientDepartmentChange);
    document.getElementById('quick-client-province')?.addEventListener('change', onQuickClientProvinceChange);
    document.getElementById('quick-client-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveQuickClient();
    });
    document.getElementById('quotation-external-client-select')?.addEventListener('change', onQuotationExternalClientChanged);
    onQuotationExternalClientChanged();
})();
</script>
