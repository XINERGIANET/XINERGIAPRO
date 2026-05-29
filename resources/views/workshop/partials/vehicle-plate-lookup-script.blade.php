<script>
(function () {
    if (window.WorkshopVehiclePlateLookup) {
        return;
    }

    window.WorkshopVehiclePlateLookup = {
        normalizePlate(value) {
            return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
        },
        applyQuickVehicle(target, payload) {
            if (!target || !payload) {
                return;
            }
            target.brand = String(payload.brand || target.brand || '');
            target.model = String(payload.model || target.model || '');
            target.year = String(payload.year || target.year || '');
            target.color = String(payload.color || target.color || '');
            target.vin = String(payload.vin || target.vin || '');
            target.engine_number = String(payload.engine_number || target.engine_number || '');
            target.chassis_number = String(payload.chassis_number || target.chassis_number || '');
            target.serial_number = String(payload.serial_number || target.serial_number || '');
            if (payload.soat_vencimiento) {
                target.soat_vencimiento = String(payload.soat_vencimiento);
            }
        },
        applyNamedForm(form, payload) {
            if (!form || !payload) {
                return;
            }
            const setValue = (name, value) => {
                if (value === undefined || value === null || value === '') {
                    return;
                }
                const field = form.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = String(value);
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };

            setValue('plate', payload.plate);
            setValue('brand', payload.brand);
            setValue('model', payload.model);
            setValue('year', payload.year);
            setValue('color', payload.color);
            setValue('vin', payload.vin);
            setValue('engine_number', payload.engine_number);
            setValue('chassis_number', payload.chassis_number);
            setValue('serial_number', payload.serial_number);
            setValue('soat_vencimiento', payload.soat_vencimiento);
        },
        noticeClass(status) {
            const map = {
                vigente: 'border-green-200 bg-green-50 text-green-800',
                vencido: 'border-amber-200 bg-amber-50 text-amber-800',
                sin_fecha: 'border-blue-200 bg-blue-50 text-blue-800',
                no_encontrado: 'border-slate-200 bg-slate-50 text-slate-700',
                error: 'border-slate-200 bg-slate-50 text-slate-700',
            };

            return map[String(status || '').toLowerCase()] || 'border-slate-200 bg-slate-50 text-slate-700';
        },
        renderNotice(container, payload) {
            if (!container) {
                return;
            }
            const message = String(payload?.soat_message || '').trim();
            if (message === '') {
                container.classList.add('hidden');
                container.textContent = '';
                return;
            }
            container.className = `js-vehicle-plate-soat-notice mt-2 rounded-lg border px-3 py-2 text-xs ${this.noticeClass(payload.soat_status)}`;
            container.textContent = message;
            container.classList.remove('hidden');
        },
        async fetch(url, plate) {
            const normalizedPlate = this.normalizePlate(plate);
            if (normalizedPlate.length < 5) {
                throw new Error('Ingrese una placa valida para buscar.');
            }

            const response = await fetch(`${url}?plate=${encodeURIComponent(normalizedPlate)}`, {
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

            return payload;
        },
        bindVehicleForms(root, url) {
            if (!root || !url) {
                return;
            }

            root.querySelectorAll('form[data-vehicle-plate-lookup="true"]').forEach((form) => {
                if (form.dataset.plateLookupBound === 'true') {
                    return;
                }
                form.dataset.plateLookupBound = 'true';

                const button = form.querySelector('.js-vehicle-plate-lookup');
                const plateInput = form.querySelector('[data-vehicle-plate-input]');
                const notice = form.querySelector('.js-vehicle-plate-soat-notice');
                if (!button || !plateInput) {
                    return;
                }

                const runLookup = async () => {
                    const normalizedPlate = this.normalizePlate(plateInput.value);
                    plateInput.value = normalizedPlate;
                    button.disabled = true;
                    const original = button.innerHTML;
                    button.innerHTML = '<span>Buscando...</span>';
                    try {
                        const payload = await this.fetch(url, normalizedPlate);
                        this.applyNamedForm(form, payload);
                        this.renderNotice(notice, payload);
                    } catch (error) {
                        this.renderNotice(notice, {
                            soat_status: 'error',
                            soat_message: error?.message || 'No se pudo consultar la placa.',
                        });
                    } finally {
                        button.disabled = false;
                        button.innerHTML = original;
                    }
                };

                button.addEventListener('click', runLookup);
                plateInput.addEventListener('blur', () => {
                    if (this.normalizePlate(plateInput.value).length >= 5) {
                        runLookup();
                    }
                });
            });
        },
    };

    function initVehiclePlateLookupForms() {
        const host = document.querySelector('[data-vehicle-plate-lookup-url]');
        const url = host?.dataset?.vehiclePlateLookupUrl || '';
        if (url !== '' && window.WorkshopVehiclePlateLookup) {
            window.WorkshopVehiclePlateLookup.bindVehicleForms(document, url);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVehiclePlateLookupForms);
    } else {
        initVehiclePlateLookupForms();
    }

    document.addEventListener('turbo:load', initVehiclePlateLookupForms);
})();
</script>
