<script>
(function () {
    const saveVehicleUrl = @json(route('workshop.maintenance-board.vehicles.store'));
    const defaultVehicleTypeId = @json((int) ($defaultVehicleTypeId ?? 0));

    const getEl = (id) => document.getElementById(id);

    const clearVehicleError = () => {
        const el = getEl('quick-vehicle-error');
        if (!el) return;
        el.textContent = '';
        el.classList.add('hidden');
    };

    const showVehicleError = (message) => {
        const el = getEl('quick-vehicle-error');
        if (!el) return;
        el.textContent = message;
        el.classList.remove('hidden');
    };

    let vehicleSaving = false;
    const toggleVehicleSaving = (loading) => {
        vehicleSaving = loading;
        const btn = getEl('quick-vehicle-save-button');
        const label = getEl('quick-vehicle-save-label');
        if (btn) btn.disabled = loading;
        if (label) label.textContent = loading ? 'Guardando...' : 'Guardar vehículo';
    };

    const resetQuickVehicleForm = () => {
        const typeSel = getEl('quick-vehicle-type-id');
        if (typeSel && defaultVehicleTypeId > 0) {
            typeSel.value = String(defaultVehicleTypeId);
        }
        const setv = (id, v) => {
            const n = getEl(id);
            if (n) n.value = v;
        };
        setv('quick-vehicle-brand', '');
        setv('quick-vehicle-model', '');
        setv('quick-vehicle-year', '');
        setv('quick-vehicle-color', '');
        setv('quick-vehicle-plate', '');
        setv('quick-vehicle-vin', '');
        setv('quick-vehicle-engine-number', '');
        setv('quick-vehicle-current-mileage', '0');
        clearVehicleError();
    };

    const closeQuickVehicleModal = () => {
        const modal = getEl('quick-vehicle-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        clearVehicleError();
        toggleVehicleSaving(false);
    };

    const openQuickVehicleModal = () => {
        const clientSel = getEl('quotation-external-client-select');
        const cid = String(clientSel?.value || '').trim();
        if (!cid) {
            window.alert('Seleccione un cliente primero.');
            return;
        }
        if (window.__quotationExternalHasVehicleTypes !== true) {
            window.alert('No hay tipos de vehículo configurados para esta sucursal.');
            return;
        }
        resetQuickVehicleForm();
        const hid = getEl('quick-vehicle-client-person-id');
        if (hid) hid.value = cid;
        const modal = getEl('quick-vehicle-modal');
        if (!modal) return;
        clearVehicleError();
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => getEl('quick-vehicle-brand')?.focus(), 40);
    };

    const buildVehiclePayload = () => {
        const yearRaw = String(getEl('quick-vehicle-year')?.value || '').trim();
        let yearVal = null;
        if (yearRaw !== '') {
            const y = parseInt(yearRaw, 10);
            if (Number.isFinite(y)) {
                yearVal = y;
            }
        }
        return {
            client_person_id: parseInt(String(getEl('quick-vehicle-client-person-id')?.value || '0'), 10),
            vehicle_type_id: parseInt(String(getEl('quick-vehicle-type-id')?.value || '0'), 10),
            brand: String(getEl('quick-vehicle-brand')?.value || '').trim(),
            model: String(getEl('quick-vehicle-model')?.value || '').trim(),
            year: yearVal,
            color: String(getEl('quick-vehicle-color')?.value || '').trim(),
            plate: String(getEl('quick-vehicle-plate')?.value || '').trim(),
            vin: String(getEl('quick-vehicle-vin')?.value || '').trim(),
            engine_number: String(getEl('quick-vehicle-engine-number')?.value || '').trim(),
            current_mileage: Math.max(0, parseInt(String(getEl('quick-vehicle-current-mileage')?.value || '0'), 10) || 0),
        };
    };

    const saveQuickVehicle = async () => {
        clearVehicleError();
        const payload = buildVehiclePayload();
        if (!payload.client_person_id) {
            showVehicleError('Seleccione un cliente en el formulario de cotización.');
            return;
        }
        if (!payload.vehicle_type_id) {
            showVehicleError('Seleccione el tipo de vehículo.');
            return;
        }
        if (!payload.brand || !payload.model) {
            showVehicleError('Marca y modelo son obligatorios.');
            return;
        }
        if (!payload.plate && !payload.vin && !payload.engine_number) {
            showVehicleError('Indique al menos placa, VIN o número de motor.');
            return;
        }

        try {
            toggleVehicleSaving(true);
            const response = await fetch(String(saveVehicleUrl || ''), {
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
                const firstErr = result?.errors ? Object.values(result.errors)[0]?.[0] : null;
                throw new Error(firstErr || result?.message || 'No se pudo registrar el vehículo.');
            }
            const vid = Number(result.id || 0);
            if (vid > 0) {
                window.__quotationExternalOldVehicleId = vid;
            }
            closeQuickVehicleModal();
            const reload = window.quotationExternalLoadVehicles;
            if (typeof reload === 'function') {
                reload(String(payload.client_person_id));
            }
        } catch (e) {
            showVehicleError(e?.message || 'Error registrando vehículo.');
        } finally {
            toggleVehicleSaving(false);
        }
    };

    getEl('open-quotation-quick-vehicle-modal')?.addEventListener('click', openQuickVehicleModal);
    getEl('quick-vehicle-close-button')?.addEventListener('click', closeQuickVehicleModal);
    getEl('quick-vehicle-cancel-button')?.addEventListener('click', closeQuickVehicleModal);
    getEl('quick-vehicle-modal-backdrop')?.addEventListener('click', closeQuickVehicleModal);
    getEl('quick-vehicle-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveQuickVehicle();
    });
})();
</script>
