import '@hotwired/turbo';
import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import '../css/app.css'
// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

window.Alpine = Alpine;


window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;

// FullCalendar is only exposed if needed by legacy code, 
// but we prefer component-based initialization.
window.FullCalendar = Calendar;

const remixIconCatalog = {
    list: null,
    promise: null,
    error: null,
    source: 'https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css',
};

const resolveRemixIconSource = () => {
    const link = document.querySelector('link[href*="remixicon"]');
    if (link && link.href) {
        remixIconCatalog.source = link.href;
    }
};

const loadRemixIconList = () => {
    if (Array.isArray(remixIconCatalog.list)) {
        return Promise.resolve(remixIconCatalog.list);
    }
    if (remixIconCatalog.promise) {
        return remixIconCatalog.promise;
    }
    resolveRemixIconSource();
    remixIconCatalog.promise = fetch(remixIconCatalog.source)
        .then((response) => response.text())
        .then((css) => {
            const regex = /\.ri-([a-z0-9-]+)::?before/g;
            const icons = new Set();
            let match;
            while ((match = regex.exec(css)) !== null) {
                icons.add(`ri-${match[1]}`);
            }
            remixIconCatalog.list = Array.from(icons).sort();
            remixIconCatalog.error = null;
            return remixIconCatalog.list;
        })
        .catch((error) => {
            remixIconCatalog.list = [];
            remixIconCatalog.error = error || true;
            return remixIconCatalog.list;
        });
    return remixIconCatalog.promise;
};

window.iconPicker = function (initialValue) {
    const initialFromParam = initialValue != null && initialValue !== '' ? String(initialValue) : '';
    return {
        open: false,
        search: initialFromParam,
        icons: [],
        loading: false,
        error: false,
        maxVisible: 180,
        selected: initialFromParam,
        init() {
            const fromDataAttr = this.$el?.dataset?.initialIcon ?? '';
            const initial = initialFromParam || fromDataAttr || '';
            if (initial) {
                this.search = initial;
                this.selected = initial;
            }
            if (this.$refs?.iconInput) {
                const current = initial || this.$refs.iconInput.value || '';
                this.search = current;
                this.selected = current;
                if (current) {
                    this.$refs.iconInput.value = current;
                }
            }
        },
        loadIcons() {
            if (this.icons.length || this.loading) return;
            this.loading = true;
            this.error = false;
            loadRemixIconList()
                .then((icons) => {
                    this.icons = icons || [];
                    this.error = !!remixIconCatalog.error || this.icons.length === 0;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        get filteredIcons() {
            if (!this.search) return this.icons;
            const query = this.search.toLowerCase();
            return this.icons.filter((icon) => icon.includes(query));
        },
        get displayedIcons() {
            const icons = this.filteredIcons || [];
            if (!icons.length) return [];
            return icons.slice(0, this.maxVisible);
        },
        openDropdown() {
            this.open = true;
            this.loadIcons();
        },
        toggleDropdown() {
            if (this.open) {
                this.open = false;
                return;
            }
            this.openDropdown();
        },
        closeDropdown() {
            this.open = false;
        },
        select(icon) {
            this.selected = icon;
            this.search = icon;
            if (this.$refs?.iconInput) {
                this.$refs.iconInput.value = icon;
                this.$refs.iconInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
        },
        clear() {
            this.selected = '';
            this.search = '';
            if (this.$refs?.iconInput) {
                this.$refs.iconInput.value = '';
                this.$refs.iconInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
};

const loadingOverlay = (() => {
    let overlayEl = null;

    const getEl = () => {
        if (!overlayEl) {
            overlayEl = document.querySelector('[data-loading-overlay]');
        }
        return overlayEl;
    };

    const show = () => {
        const el = getEl();
        if (!el) return;
        el.classList.remove('hidden');
        el.setAttribute('aria-hidden', 'false');
    };

    const hide = () => {
        const el = getEl();
        if (!el) return;
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
    };

    return { show, hide };
})();

window.showLoadingModal = loadingOverlay.show;
window.hideLoadingModal = loadingOverlay.hide;

const bindGlobalLoadingOverlay = () => {
    if (!window.showLoadingModal || !window.hideLoadingModal) {
        return;
    }

    document.addEventListener('turbo:visit', () => {
        window.showLoadingModal();
    });

    const hideEvents = ['turbo:load', 'turbo:render', 'turbo:frame-load', 'turbo:frame-render'];
    hideEvents.forEach((eventName) => {
        document.addEventListener(eventName, () => {
            window.hideLoadingModal();
        });
    });
};

bindGlobalLoadingOverlay();

const shouldIgnoreLink = (link, event) => {
    if (!link) return true;
    if (link.closest('[data-no-loading]')) return true;
    if (link.hasAttribute('download')) return true;
    const href = link.getAttribute('href');
    if (!href) return true;
    if (href.startsWith('#')) return true;
    if (href.startsWith('javascript:')) return true;
    if (href.startsWith('mailto:') || href.startsWith('tel:')) return true;
    const target = link.getAttribute('target');
    if (target && target !== '_self') return true;
    if (event) {
        if (event.defaultPrevented) return true;
        if (event.button !== 0) return true;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return true;
    }
    return false;
};

const shouldIgnoreForm = (form, event) => {
    if (!form) return true;
    if (form.closest('[data-no-loading]')) return true;
    if (form.classList.contains('js-swal-delete')) return true;
    const target = form.getAttribute('target');
    if (target && target !== '_self') return true;
    if (event && event.defaultPrevented) return true;
    return false;
};

const bindLoadingOverlay = () => {
    if (window.__loadingOverlayBound) return;
    window.__loadingOverlayBound = true;

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (shouldIgnoreLink(link, event)) return;
        loadingOverlay.show();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (shouldIgnoreForm(form, event)) return;
        loadingOverlay.show();
    });

    document.addEventListener('pageshow', () => loadingOverlay.hide());

    if (window.Turbo) {
        document.addEventListener('turbo:visit', () => loadingOverlay.show());
        document.addEventListener('turbo:submit-start', () => loadingOverlay.show());
        document.addEventListener('turbo:submit-end', () => loadingOverlay.hide());
        document.addEventListener('turbo:render', () => loadingOverlay.hide());
        document.addEventListener('turbo:load', () => loadingOverlay.hide());
        document.addEventListener('turbo:before-cache', () => loadingOverlay.hide());
        document.addEventListener('turbo:frame-load', () => loadingOverlay.hide());
    } else {
        document.addEventListener('DOMContentLoaded', () => loadingOverlay.hide(), { once: true });
    }
};

bindLoadingOverlay();

const syncSelectAllState = (scope) => {
    if (!scope) return;
    const selectAll = scope.querySelector('input[data-select-all]');
    if (!selectAll) return;
    const items = Array.from(scope.querySelectorAll('input[data-select-item]'))
        .filter((input) => !input.disabled);
    if (items.length === 0) {
        selectAll.checked = false;
        return;
    }
    selectAll.checked = items.every((input) => input.checked);
};

const syncAllSelectAllStates = () => {
    document.querySelectorAll('[data-select-scope]').forEach(syncSelectAllState);
};

const bindSelectAllCheckboxes = () => {
    if (window.__selectAllBound) return;
    window.__selectAllBound = true;

    document.addEventListener('change', (event) => {
        const selectAll = event.target.closest('input[data-select-all]');
        if (selectAll) {
            const scope = selectAll.closest('[data-select-scope]') || selectAll.closest('form') || document;
            scope.querySelectorAll('input[data-select-item]').forEach((input) => {
                if (input.disabled) return;
                input.checked = selectAll.checked;
            });
            return;
        }

        const item = event.target.closest('input[data-select-item]');
        if (!item) return;
        const scope = item.closest('[data-select-scope]') || item.closest('form') || document;
        syncSelectAllState(scope);
    });

    document.addEventListener('turbo:load', syncAllSelectAllStates);
    document.addEventListener('turbo:render', syncAllSelectAllStates);
    document.addEventListener('DOMContentLoaded', syncAllSelectAllStates, { once: true });
};

bindSelectAllCheckboxes();

/**
 * Helpers para selects tipo autocomplete en el mismo x-data (sin scope anidado).
 * Uso en Blade: Object.assign(formAutocompleteHelpers(), { ... }) o ...formAutocompleteHelpers() en return.
 */
window.formAutocompleteHelpers = function formAutocompleteHelpers() {
    return {
        sacKeys: {},
        sacEnsure(key) {
            const k = String(key);
            if (!this.sacKeys[k]) {
                this.sacKeys[k] = { open: false, q: '' };
            }
            return this.sacKeys[k];
        },
        sacClose(key) {
            const ui = this.sacEnsure(String(key));
            ui.open = false;
            ui.q = '';
        },
        sacToggle(key) {
            const k = String(key);
            const ui = this.sacEnsure(k);
            const opening = !ui.open;
            Object.keys(this.sacKeys).forEach((sk) => {
                this.sacKeys[sk].open = false;
                this.sacKeys[sk].q = '';
            });
            if (opening) {
                ui.open = true;
                this.$nextTick(() => this.$refs.sacSearchInput?.focus?.());
            }
        },
        sacFiltered(key, list, labelPath = 'name') {
            const ui = this.sacEnsure(String(key));
            const q = String(ui.q || '').trim().toLowerCase();
            const arr = list || [];
            if (!q) {
                return arr;
            }
            const lp = String(labelPath || 'name');
            return arr.filter((item) => String(item?.[lp] ?? '').toLowerCase().includes(q));
        },
        sacLabel(key, list, value, labelPath = 'name', valuePath = 'id') {
            const v = String(value ?? '');
            const arr = list || [];
            const vp = String(valuePath || 'id');
            const lp = String(labelPath || 'name');
            const item = arr.find((i) => String(i?.[vp]) === v);
            return item ? String(item[lp] ?? '') : '';
        },
    };
};

Alpine.data('crudModal', (el) => {
    const parseJson = (value, fallback) => {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    };

    const initialForm = parseJson(el?.dataset?.form, {
        id: null,
        tax_id: '',
        legal_name: '',
        address: '',
    });

    return {
        open: parseJson(el?.dataset?.open, false),
        mode: parseJson(el?.dataset?.mode, 'create'),
        form: initialForm,
        createUrl: parseJson(el?.dataset?.createUrl, ''),
        updateBaseUrl: parseJson(el?.dataset?.updateBaseUrl, ''),
        get formAction() {
            return this.mode === 'create' ? this.createUrl : `${this.updateBaseUrl}/${this.form.id}`;
        },
        openCreate() {
            this.mode = 'create';
            this.form = { id: null, tax_id: '', legal_name: '', address: '' };
            this.open = true;
        },
        openEdit(company) {
            this.mode = 'edit';
            this.form = {
                id: company.id,
                tax_id: company.tax_id || '',
                legal_name: company.legal_name || '',
                address: company.address || '',
            };
            this.open = true;
        },
    };
});

if (window.Turbo) {
    window.Turbo.session.drive = true;
}

const bindSwalDelete = () => {
    document.querySelectorAll('.js-swal-delete').forEach((form) => {
        if (form.dataset.swalBound === 'true') return;
        form.dataset.swalBound = 'true';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!window.Swal) {
                form.submit();
                return;
            }
            const title = form.dataset.swalTitle || '¿Eliminar registro?';
            const text = form.dataset.swalText || 'Esta acción no se puede deshacer.';
            const icon = form.dataset.swalIcon || 'warning';
            const confirmText = form.dataset.swalConfirm || 'Sí, eliminar';
            const cancelText = form.dataset.swalCancel || 'Cancelar';
            const confirmColor = form.dataset.swalConfirmColor || '#ef4444';
            const cancelColor = form.dataset.swalCancelColor || '#6b7280';

            Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                confirmButtonColor: confirmColor,
                cancelButtonColor: cancelColor,
                reverseButtons: true,
                allowOutsideClick: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    if (window.showLoadingModal) {
                        window.showLoadingModal();
                    }
                    form.submit();
                }
            });
        });
    });
};

const ensureGlobalSelectAutocompleteStyles = () => {
    if (document.getElementById('global-select-autocomplete-styles')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'global-select-autocomplete-styles';
    style.textContent = `
        .gsa-native-hidden { display: none !important; }
        .gsa-wrap { position: relative; width: 100%; }
        .gsa-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            width: 100%;
            min-height: 2.75rem;
            border: 1px solid rgb(209 213 219);
            border-radius: .75rem;
            background: #fff;
            padding: .625rem .875rem;
            font-size: .875rem;
            color: rgb(31 41 55);
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            cursor: pointer;
        }
        .gsa-trigger:focus { outline: 2px solid rgba(59,130,246,.12); outline-offset: 1px; }
        .gsa-trigger[disabled] { opacity: .6; cursor: not-allowed; }
        .gsa-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left; }
        .gsa-placeholder { color: rgb(156 163 175); }
        .gsa-chevron { flex-shrink: 0; color: rgb(107 114 128); transition: transform .15s ease; }
        .gsa-wrap[data-open="true"] .gsa-chevron { transform: rotate(180deg); }
        .gsa-panel {
            position: absolute;
            top: calc(100% + .25rem);
            left: 0;
            right: 0;
            z-index: 80;
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 10px 25px rgba(15,23,42,.12);
        }
        .gsa-search {
            width: calc(100% - 1rem);
            height: 2.25rem;
            margin: .5rem;
            border: 1px solid rgb(229 231 235);
            border-radius: .5rem;
            background: rgb(249 250 251);
            padding: 0 .75rem;
            font-size: .875rem;
        }
        .gsa-list { max-height: 15rem; overflow: auto; padding: .25rem 0; }
        .gsa-option, .gsa-empty {
            width: 100%;
            padding: .625rem .875rem;
            font-size: .875rem;
            text-align: left;
        }
        .gsa-option:hover, .gsa-option.is-active { background: rgb(238 242 255); }
        .gsa-empty { color: rgb(107 114 128); }
    `;
    document.head.appendChild(style);
};

const createGlobalSelectAutocomplete = (select) => {
    if (!select || select.dataset.gsaEnhanced === 'true') {
        return;
    }
    if (select.multiple || Number(select.size || 0) > 1) {
        return;
    }
    if (select.classList.contains('sr-only') || select.getAttribute('aria-hidden') === 'true') {
        return;
    }
    if (
        select.closest('[data-gsa-skip="true"]')
        || select.closest('.gsa-wrap')
        || select.closest('.swal2-container')
        || select.closest('.swal2-popup')
    ) {
        return;
    }

    select.dataset.gsaEnhanced = 'true';
    select.classList.add('gsa-native-hidden');

    const wrap = document.createElement('div');
    wrap.className = 'gsa-wrap';
    wrap.dataset.open = 'false';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'gsa-trigger';
    trigger.disabled = !!select.disabled;
    trigger.innerHTML = `
        <span class="gsa-label"></span>
        <span class="gsa-chevron"><i class="ri-arrow-down-s-line"></i></span>
    `;

    const panel = document.createElement('div');
    panel.className = 'gsa-panel';
    panel.hidden = true;

    const search = document.createElement('input');
    search.type = 'text';
    search.className = 'gsa-search';
    search.placeholder = 'Buscar...';

    const list = document.createElement('div');
    list.className = 'gsa-list';

    panel.append(search, list);
    wrap.append(trigger, panel);
    select.insertAdjacentElement('afterend', wrap);

    const getOptions = () => Array.from(select.options || []).map((opt) => ({
        value: String(opt.value ?? ''),
        label: String(opt.textContent ?? '').trim(),
        disabled: !!opt.disabled,
        selected: !!opt.selected,
    }));

    const syncLabel = () => {
        const current = select.options[select.selectedIndex] || null;
        const labelEl = trigger.querySelector('.gsa-label');
        const text = current ? String(current.textContent ?? '').trim() : '';
        labelEl.textContent = text || 'Seleccionar...';
        labelEl.classList.toggle('gsa-placeholder', !text);
        trigger.disabled = !!select.disabled;
    };

    const close = () => {
        wrap.dataset.open = 'false';
        panel.hidden = true;
        search.value = '';
    };

    const render = () => {
        const q = search.value.trim().toLowerCase();
        const options = getOptions().filter((opt) => !q || opt.label.toLowerCase().includes(q));
        list.innerHTML = '';
        if (!options.length) {
            const empty = document.createElement('div');
            empty.className = 'gsa-empty';
            empty.textContent = 'Sin coincidencias.';
            list.appendChild(empty);
            return;
        }
        options.forEach((opt) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gsa-option' + (opt.selected ? ' is-active' : '');
            btn.textContent = opt.label;
            btn.disabled = opt.disabled;
            btn.addEventListener('click', () => {
                select.value = opt.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncLabel();
                close();
            });
            list.appendChild(btn);
        });
    };

    trigger.addEventListener('click', () => {
        if (trigger.disabled) {
            return;
        }
        const open = wrap.dataset.open === 'true';
        document.querySelectorAll('.gsa-wrap[data-open="true"]').forEach((el) => {
            if (el !== wrap) {
                el.dataset.open = 'false';
                const elPanel = el.querySelector('.gsa-panel');
                const elSearch = el.querySelector('.gsa-search');
                if (elPanel) elPanel.hidden = true;
                if (elSearch) elSearch.value = '';
            }
        });
        wrap.dataset.open = open ? 'false' : 'true';
        panel.hidden = open;
        if (!open) {
            render();
            setTimeout(() => search.focus(), 0);
        }
    });

    search.addEventListener('input', render);
    select.addEventListener('change', syncLabel);

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
            close();
        }
    });

    syncLabel();
};

const enhanceGlobalSelectAutocompletes = (root = document) => {
    ensureGlobalSelectAutocompleteStyles();
    root.querySelectorAll('select').forEach(createGlobalSelectAutocomplete);
};

const initPage = () => {
    enhanceGlobalSelectAutocompletes();
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }
    if (document.querySelector('#chartBalance')) {
        import('./components/chart/chart-balance').then(module => module.initChartBalance());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }

    if (document.querySelector('#workshop-calendar')) {
        import('./components/workshop-calendar').then(module => module.initWorkshopCalendar());
    }
};

let alpineBooted = false;

const bootAlpine = () => {
    if (alpineBooted) {
        return;
    }
    Alpine.start();
    alpineBooted = true;
};

document.addEventListener('turbo:before-cache', () => {
    // Clear all chart and calendar containers before taking a snapshot
    // This is the definitive fix for duplication bugs when using the 'back' button with Turbo
    document.querySelectorAll('[id^="chart"], #calendar, [id*="calendar"]').forEach((el) => {
        el.innerHTML = '';
    });

    if (window.Alpine && alpineBooted) {
        Alpine.destroyTree(document.body);
    }
});

document.addEventListener('turbo:load', () => {
    if (window.Alpine) {
        if (!alpineBooted) {
            bootAlpine();
        } else {
            Alpine.initTree(document.body);
        }
    }
    initPage();
    bindSwalDelete();
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.Turbo) {
        return;
    }
    bootAlpine();
    initPage();
    bindSwalDelete();
});
