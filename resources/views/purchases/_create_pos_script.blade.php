<script>
function purchaseCreateForm(c) {
    const sacBase = typeof window.formAutocompleteHelpers === 'function' ? window.formAutocompleteHelpers() : {};
    return {
        ...sacBase,
        products: c.products || [],
        units: c.units || [],
        providers: c.providers || [],
        documentTypes: c.documentTypes || [],
        cashRegisters: c.cashRegisters || [],
        paymentMethods: c.paymentMethods || [],
        paymentGateways: c.paymentGateways || [],
        cards: c.cards || [],
        digitalWallets: c.digitalWallets || [],
        selectedProviderId: '',
        providerQuery: '',
        providerOpen: false,
        providerCursor: 0,
        asideTab: 'summary',
        productSearch: '',
        productSearchTimer: null,
        selectedCategory: 'General',
        detailType: c.initialDetailType || 'DETALLADO',
        creatingProviderLoading: false,
        quickProviderError: '',
        departments: c.departments || [],
        provinces: c.provinces || [],
        districts: c.districts || [],
        branchDepartmentId: String(c.branchDepartmentId || ''),
        branchProvinceId: String(c.branchProvinceId || ''),
        branchDistrictId: String(c.branchDistrictId || ''),
        branchDepartmentName: c.branchDepartmentName || '',
        branchProvinceName: c.branchProvinceName || '',
        branchDistrictName: c.branchDistrictName || '',
        quickProvider: {
            person_type: 'DNI',
            document_number: '',
            first_name: '',
            last_name: '',
            phone: '',
            email: '',
            address: '-',
            genero: '',
            fecha_nacimiento: '',
            credit_days: 0,
            department_id: String(c.branchDepartmentId || ''),
            province_id: String(c.branchProvinceId || ''),
            location_id: String(c.branchDistrictId || ''),
        },
        documentTypeId: Number(c.initialDocumentTypeId || 0),
        standardCashRegisterId: Number(c.standardCashRegisterId || 0),
        invoiceCashRegisterId: Number(c.invoiceCashRegisterId || 0),
        paymentType: c.initialPaymentType || 'CONTADO',
        affectsCash: c.initialAffectsCash || 'N',
        dueDate: c.initialDueDate || '',
        manualCreditDays: null,
        cashRegisterId: Number(c.initialCashRegisterId || 0),
        taxRate: Number(c.initialTaxRate || 18),
        includesTax: c.initialIncludesTax || 'N',
        currency: c.initialCurrency || 'PEN',
        exchangeRate: Number(c.initialExchangeRate || 3.5),
        affectsKardex: c.initialAffectsKardex || 'S',
        summaryUi: {
            detailType: { open: false, q: '' },
            affectsKardex: { open: false, q: '' },
            includesTax: { open: false, q: '' },
            currency: { open: false, q: '' },
        },
        items: [],
        paymentRows: [],
        movedAtListenerBound: false,

        init() {
            this.items = (c.initialItems || []).length
                ? (c.initialItems || []).map((i) => ({
                    product_id: Number(i.product_id || 0),
                    unit_id: Number(i.unit_id || 0),
                    description: i.description || '',
                    quantity: Number(i.quantity || 1),
                    amount: Number(i.amount || 0),
                    comment: i.comment || '',
                    product_query: '',
                    product_open: false,
                    product_cursor: 0,
                }))
                : [];

            if (Number(c.initialProviderId || 0) > 0) {
                const provider = this.providers.find((item) => Number(item.id) === Number(c.initialProviderId));
                if (provider) {
                    this.selectedProviderId = String(provider.id);
                    this.providerQuery = provider.document
                        ? `${provider.label} - ${provider.document}`
                        : provider.label;
                }
            }

            this.items.forEach((item, idx) => {
                if (Number(item.product_id || 0) > 0) {
                    this.setProductMeta(idx);
                }
            });

            if (!this.items.length && this.detailType === 'GLOSA') {
                this.addGlosaItem();
            }

            this.paymentRows = (c.initialRows || []).length
                ? (c.initialRows || []).map((row) => this.makePaymentRowFromExisting(row))
                : [];

            if (this.paymentType !== 'CONTADO') {
                this.affectsCash = 'S';
            }

            if (this.paymentType === 'CONTADO' && this.affectsCash !== 'S') {
                this.paymentRows = [];
            }

            if (this.paymentType === 'CONTADO' && this.affectsCash === 'S' && !this.paymentRows.length) {
                this.addPaymentRow();
            }

            this.syncPaymentAmounts();

            this.$nextTick(() => {
                this.bindMovedAtInput();
                this.syncCreditDueDate(false);
            });

            if (!c.isEditing) {
                this.syncCashRegisterByDocumentType();
            }

            if (String(this.quickProvider.person_type).toUpperCase() === 'RUC') {
                this.quickProvider.last_name = '';
                this.quickProvider.genero = '';
            }
            this.$watch('quickProvider.person_type', (value) => {
                this.quickProviderError = '';
                if (String(value).toUpperCase() === 'RUC') {
                    this.quickProvider.last_name = '';
                    this.quickProvider.genero = '';
                }
            });
        },

        isQuickProviderRuc() {
            return String(this.quickProvider.person_type || '').toUpperCase() === 'RUC';
        },

        normalizeQuickProviderLocationText(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        },

        findQuickProviderDepartmentByName(name) {
            const target = this.normalizeQuickProviderLocationText(name);
            return this.departments.find((item) => this.normalizeQuickProviderLocationText(item.name) === target) || null;
        },

        findQuickProviderProvinceByName(name, departmentId) {
            const target = this.normalizeQuickProviderLocationText(name);
            return this.provinces.find((item) => (
                String(item.parent_location_id || '') === String(departmentId || '')
                && this.normalizeQuickProviderLocationText(item.name) === target
            )) || null;
        },

        findQuickProviderDistrictByName(name, provinceId) {
            const target = this.normalizeQuickProviderLocationText(name);
            return this.districts.find((item) => (
                String(item.parent_location_id || '') === String(provinceId || '')
                && this.normalizeQuickProviderLocationText(item.name) === target
            )) || null;
        },

        applyQuickProviderLocationFromLookup(payload) {
            const department = this.findQuickProviderDepartmentByName(payload.department);
            if (!department) {
                return;
            }
            this.quickProvider.department_id = String(department.id);
            const province = this.findQuickProviderProvinceByName(payload.province, department.id);
            if (!province) {
                this.quickProvider.province_id = '';
                this.quickProvider.location_id = '';
                return;
            }
            this.quickProvider.province_id = String(province.id);
            const district = this.findQuickProviderDistrictByName(payload.district, province.id);
            this.quickProvider.location_id = district ? String(district.id) : '';
        },

        normalizeQuickProviderApiDate(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return '';
            }
            const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
            return match ? match[1] : '';
        },

        async fetchQuickProviderDocument() {
            const t = String(this.quickProvider.person_type || '').toUpperCase();
            if (t === 'DNI') {
                return this.fetchReniecQuickProvider();
            }
            if (t === 'RUC') {
                return this.fetchRucQuickProvider();
            }
            this.quickProviderError = 'La busqueda automatica solo aplica para DNI o RUC.';
        },

        async fetchRucQuickProvider() {
            this.quickProviderError = '';
            const ruc = String(this.quickProvider.document_number || '').trim();
            if (!/^\d{11}$/.test(ruc)) {
                this.quickProviderError = 'Ingrese un RUC valido de 11 digitos.';
                return;
            }

            this.creatingProviderLoading = true;

            try {
                const base = String(c.rucApiUrl || '').trim() || '/api/ruc';
                const response = await fetch(`${base}?ruc=${encodeURIComponent(ruc)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();

                if (!response.ok || payload?.status === false) {
                    throw new Error(payload?.message || 'No se encontro informacion para el RUC ingresado.');
                }

                this.quickProvider.document_number = payload.ruc || ruc;
                this.quickProvider.first_name = payload.legal_name || this.quickProvider.first_name;
                this.quickProvider.last_name = '';
                this.quickProvider.genero = '';
                if (payload.address) {
                    this.quickProvider.address = payload.address;
                }
                const raw = payload.raw || {};
                this.quickProvider.fecha_nacimiento = this.normalizeQuickProviderApiDate(raw.fecha_inscripcion || raw.fechaInscripcion || '');
                this.applyQuickProviderLocationFromLookup(payload);
            } catch (error) {
                this.quickProviderError = error?.message || 'Error consultando RUC.';
            } finally {
                this.creatingProviderLoading = false;
            }
        },

        activeTabStyle() {
            return 'background:linear-gradient(90deg,#ff7a00,#ff4d00);color:#fff;box-shadow:0 8px 18px rgba(249,115,22,.24);';
        },

        setAsideTab(tab) {
            this.asideTab = tab === 'payment' ? 'payment' : 'summary';
        },

        get summary() {
            const lineTotal = this.items.reduce((sum, item) => {
                return sum + ((Number(item.quantity) || 0) * (Number(item.amount) || 0));
            }, 0);
            const rate = (Number(this.taxRate) || 0) / 100;

            if (this.includesTax === 'S') {
                const subtotal = rate > 0 ? (lineTotal / (1 + rate)) : lineTotal;
                return {
                    subtotal,
                    tax: lineTotal - subtotal,
                    total: lineTotal,
                };
            }

            const subtotal = lineTotal;
            const tax = subtotal * rate;

            return {
                subtotal,
                tax,
                total: subtotal + tax,
            };
        },

        get totalPaid() {
            return this.paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
        },

        get paymentDifference() {
            return this.summary.total - this.totalPaid;
        },

        get selectedProvider() {
            return this.providers.find((provider) => Number(provider.id) === Number(this.selectedProviderId || 0)) || null;
        },

        get selectedProviderCreditDays() {
            if (this.selectedProvider != null) {
                return Number(this.selectedProvider.credit_days || 0);
            }
            return Number(this.manualCreditDays ?? 0);
        },

        currentDocumentType() {
            return this.documentTypes.find((item) => Number(item.id || 0) === Number(this.documentTypeId || 0)) || null;
        },

        isInvoiceDocumentSelected() {
            const name = String(this.currentDocumentType()?.name || '').toLowerCase();
            return name.includes('factura');
        },

        preferredCashRegisterIdForCurrentDocument() {
            const preferredId = this.isInvoiceDocumentSelected()
                ? this.invoiceCashRegisterId
                : this.standardCashRegisterId;

            return Number(preferredId || 0) || 0;
        },

        syncCashRegisterByDocumentType() {
            const preferredId = this.preferredCashRegisterIdForCurrentDocument();
            if (preferredId > 0) {
                this.cashRegisterId = preferredId;
            }
        },

        setDocumentType(value) {
            this.documentTypeId = Number(value || 0);
            this.syncCashRegisterByDocumentType();
        },

        get filteredProviders() {
            const term = (this.providerQuery || '').toLowerCase().trim();
            const list = term === ''
                ? this.providers
                : this.providers.filter((provider) => (
                    String(provider.label || '').toLowerCase().includes(term)
                    || String(provider.document || '').toLowerCase().includes(term)
                ));

            if (this.providerCursor >= list.length) {
                this.providerCursor = 0;
            }

            return list.slice(0, 40);
        },

        get filteredQuickProviderProvinces() {
            return this.provinces.filter((province) => (
                String(province.parent_location_id || '') === String(this.quickProvider.department_id || '')
            ));
        },

        get filteredQuickProviderDistricts() {
            return this.districts.filter((district) => (
                String(district.parent_location_id || '') === String(this.quickProvider.province_id || '')
            ));
        },

        selectProvider(provider) {
            this.selectedProviderId = String(provider.id);
            this.providerQuery = provider.document
                ? `${provider.label} - ${provider.document}`
                : provider.label;
            this.providerOpen = false;
            this.manualCreditDays = null;
            this.syncCreditDueDate(true);
        },

        clearProvider() {
            this.selectedProviderId = '';
            this.providerQuery = '';
            this.providerOpen = true;
            this.providerCursor = 0;
            this.manualCreditDays = null;
            this.syncCreditDueDate(true);
        },

        resetQuickProvider() {
            this.quickProvider = {
                person_type: 'DNI',
                document_number: '',
                first_name: '',
                last_name: '',
                phone: '',
                email: '',
                address: '-',
                genero: '',
                fecha_nacimiento: '',
                credit_days: 0,
                department_id: String(this.branchDepartmentId || ''),
                province_id: String(this.branchProvinceId || ''),
                location_id: String(this.branchDistrictId || ''),
            };
            this.quickProviderError = '';
        },

        onQuickProviderDepartmentChange() {
            this.quickProvider.province_id = '';
            this.quickProvider.location_id = '';
        },

        onQuickProviderProvinceChange() {
            this.quickProvider.location_id = '';
        },

        moveProviderCursor(step) {
            const list = this.filteredProviders;
            if (!list.length) {
                return;
            }

            const max = list.length - 1;
            const next = this.providerCursor + step;
            this.providerCursor = next < 0 ? max : (next > max ? 0 : next);
        },

        selectProviderByCursor() {
            const list = this.filteredProviders;
            if (list.length) {
                this.selectProvider(list[this.providerCursor] || list[0]);
            }
        },

        splitName(fullName) {
            const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);

            if (parts.length <= 1) {
                return { first_name: parts[0] || '', last_name: '' };
            }

            if (parts.length === 2) {
                return { first_name: parts[0], last_name: parts[1] };
            }

            if (parts.length === 3) {
                return { first_name: parts[0], last_name: parts.slice(1).join(' ') };
            }

            return {
                first_name: parts.slice(0, 2).join(' '),
                last_name: parts.slice(2).join(' '),
            };
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

        async fetchReniecQuickProvider() {
            this.quickProviderError = '';

            if (String(this.quickProvider.person_type).toUpperCase() !== 'DNI') {
                this.quickProviderError = 'La busqueda RENIEC solo aplica para DNI.';
                return;
            }

            const dni = String(this.quickProvider.document_number || '').trim();
            if (!/^\d{8}$/.test(dni)) {
                this.quickProviderError = 'Ingrese un DNI valido de 8 digitos.';
                return;
            }

            this.creatingProviderLoading = true;

            try {
                const base = String(c.reniecApiUrl || '').trim() || '/api/reniec';
                const response = await fetch(`${base}?dni=${encodeURIComponent(dni)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();

                if (!response.ok || !payload?.status || (!payload?.name && !payload?.nombres && !payload?.nombre_completo && !payload?.first_name)) {
                    throw new Error(payload?.message || 'No se encontro informacion en RENIEC.');
                }

                const parsed = this.namesFromReniecPayload(payload);
                this.quickProvider.first_name = parsed.first_name;
                this.quickProvider.last_name = parsed.last_name;
                this.quickProvider.fecha_nacimiento = payload?.fecha_nacimiento || '';
                this.quickProvider.genero = payload?.genero || '';
            } catch (error) {
                this.quickProviderError = error?.message || 'Error consultando RENIEC.';
            } finally {
                this.creatingProviderLoading = false;
            }
        },

        async saveQuickProvider() {
            this.quickProviderError = '';
            this.creatingProviderLoading = true;

            try {
                const bodyPayload = { ...this.quickProvider };
                if (String(bodyPayload.person_type || '').toUpperCase() === 'RUC') {
                    bodyPayload.last_name = '';
                    bodyPayload.genero = '';
                }

                const response = await fetch(String(c.quickProviderStoreUrl || ''), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(bodyPayload),
                });

                const payload = await response.json();

                if (!response.ok) {
                    const message = payload?.message || 'No se pudo registrar el proveedor.';
                    const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                    throw new Error(firstError || message);
                }

                this.providers.unshift({
                    id: Number(payload.id),
                    label: String(payload.label || payload.name || ''),
                    document: String(payload.document || payload.document_number || ''),
                    credit_days: Number(payload.credit_days || 0),
                });

                this.selectedProviderId = String(payload.id);
                this.providerQuery = payload.document
                    ? `${payload.label} - ${payload.document}`
                    : payload.label;
                this.providerOpen = false;
                this.syncCreditDueDate(true);
                this.resetQuickProvider();
                this.$dispatch('close-provider-modal');
            } catch (error) {
                this.quickProviderError = error?.message || 'Error registrando proveedor.';
            } finally {
                this.creatingProviderLoading = false;
            }
        },

        bindMovedAtInput() {
            if (this.movedAtListenerBound) {
                return;
            }

            const input = document.querySelector('[name="moved_at"]');
            if (!input) {
                return;
            }

            const resync = () => this.syncCreditDueDate(true);
            input.addEventListener('change', resync);
            input.addEventListener('input', resync);
            this.movedAtListenerBound = true;
        },

        readMovedAtValue() {
            const input = document.querySelector('[name="moved_at"]');
            return String(input?.value || c.initialMovedAt || '').trim();
        },

        parseDateTimeValue(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return null;
            }

            const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
            const parsed = new Date(normalized);

            return Number.isNaN(parsed.getTime()) ? null : parsed;
        },

        formatDateOnly(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        syncCreditDueDate(force = false) {
            if (this.paymentType !== 'CREDITO') {
                return;
            }

            if (this.dueDate && !force) {
                return;
            }

            const baseDate = this.parseDateTimeValue(this.readMovedAtValue()) || new Date();
            const nextDate = new Date(baseDate.getTime());
            nextDate.setDate(nextDate.getDate() + this.selectedProviderCreditDays);
            this.dueDate = this.formatDateOnly(nextDate);
        },

        setDueDate(value) {
            this.dueDate = String(value || '').trim();
        },

        applyCreditDaysFromInput(value) {
            const days = Math.max(0, parseInt(value, 10) || 0);
            if (this.selectedProvider) {
                this.selectedProvider.credit_days = days;
            } else {
                this.manualCreditDays = days;
            }
            this.syncCreditDueDate(true);
        },

        newItem() {
            return {
                product_id: 0,
                unit_id: 0,
                description: '',
                quantity: 1,
                amount: 0,
                comment: '',
                product_query: '',
                product_open: false,
                product_cursor: 0,
            };
        },

        productLabel(product) {
            return String(product.name || product.description || 'Sin nombre');
        },

        productAmount(product) {
            return Number(product.cost || product.price || 0);
        },

        normalizeCode(value) {
            return String(value || '').trim().toLowerCase();
        },

        clearCatalogSearch() {
            this.productSearch = '';
            this.$nextTick(() => document.getElementById('purchase-product-search')?.focus());
        },

        findUniqueCatalogProductByCode(term) {
            const needle = this.normalizeCode(term);
            if (!needle) {
                return null;
            }

            const matches = this.products.filter((product) => this.normalizeCode(product.code) === needle);
            return matches.length === 1 ? matches[0] : null;
        },

        tryAutoAddCatalogProduct(term) {
            if (this.detailType !== 'DETALLADO') {
                return false;
            }

            const product = this.findUniqueCatalogProductByCode(term);
            if (!product) {
                return false;
            }

            this.addProductCard(product);
            this.clearCatalogSearch();
            return true;
        },

        queueCatalogCodeAutoAdd(term) {
            window.clearTimeout(this.productSearchTimer);
            if (!String(term || '').trim()) {
                return;
            }

            this.productSearchTimer = window.setTimeout(() => {
                this.tryAutoAddCatalogProduct(term);
            }, 180);
        },

        flushCatalogCodeAutoAdd(term) {
            window.clearTimeout(this.productSearchTimer);
            this.tryAutoAddCatalogProduct(term);
        },

        get catalogCategories() {
            const unique = new Set();
            this.products.forEach((product) => {
                unique.add(String(product.category || 'Sin categoria').trim() || 'Sin categoria');
            });

            return ['General', ...Array.from(unique).sort((a, b) => a.localeCompare(b))];
        },

        paymentTypeOptions: [
            { value: 'CONTADO', label: 'CONTADO' },
            { value: 'CREDITO', label: 'CREDITO / DEUDA' },
        ],

        affectsCashOptions: [
            { value: 'S', label: 'Si' },
            { value: 'N', label: 'No' },
        ],

        quickProviderPersonTypeOptions: [
            { value: 'DNI', label: 'DNI' },
            { value: 'RUC', label: 'RUC' },
            { value: 'CARNET DE EXTRANGERIA', label: 'CARNET DE EXTRANGERIA' },
            { value: 'PASAPORTE', label: 'PASAPORTE' },
        ],

        quickProviderGeneroOptions: [
            { value: '', label: 'Seleccione genero' },
            { value: 'MASCULINO', label: 'MASCULINO' },
            { value: 'FEMENINO', label: 'FEMENINO' },
            { value: 'OTRO', label: 'OTRO' },
        ],

        get cashRegisterOptions() {
            return (this.cashRegisters || []).map((r) => ({
                id: r.id,
                label: r.status === 'A' ? `${r.number} (Activa)` : String(r.number),
            }));
        },

        cashRegisterDisplayLabel(id) {
            const r = (this.cashRegisters || []).find((c) => Number(c.id) === Number(id));
            if (!r) {
                return 'Seleccionar caja';
            }
            return r.status === 'A' ? `${r.number} (Activa)` : String(r.number);
        },

        paymentVariantRowLabel(row) {
            const v = (this.paymentMethodVariants || []).find((x) => x.key === row.method_variant_key);
            return v ? v.label : '';
        },

        get filteredCatalogProducts() {
            const term = String(this.productSearch || '').toLowerCase().trim();
            const list = this.products.filter((product) => {
                const category = String(product.category || 'Sin categoria').trim() || 'Sin categoria';
                const searchNeedle = `${String(product.code || '')} ${this.productLabel(product)} ${String(product.unit_name || '')} ${category}`.toLowerCase();

                if (this.selectedCategory !== 'General' && category !== this.selectedCategory) {
                    return false;
                }

                if (term && !searchNeedle.includes(term)) {
                    return false;
                }

                return true;
            });

            return list.slice(0, 60);
        },

        changeDetailType(value) {
            this.detailType = value === 'GLOSA' ? 'GLOSA' : 'DETALLADO';
            this.items = [];

            if (this.detailType === 'GLOSA') {
                this.addGlosaItem();
            }

            this.syncPaymentAmounts();
        },

        getSummaryOptions(key) {
            const map = {
                detailType: [
                    { value: 'DETALLADO', label: 'DETALLADO' },
                    { value: 'GLOSA', label: 'GLOSA' },
                ],
                affectsKardex: [
                    { value: 'S', label: 'Si' },
                    { value: 'N', label: 'No' },
                ],
                includesTax: [
                    { value: 'S', label: 'Si' },
                    { value: 'N', label: 'No' },
                ],
                currency: [
                    { value: 'PEN', label: 'PEN' },
                    { value: 'USD', label: 'USD' },
                ],
            };
            return map[key] || [];
        },

        summaryValue(key) {
            if (key === 'detailType') {
                return this.detailType;
            }
            if (key === 'affectsKardex') {
                return this.affectsKardex;
            }
            if (key === 'includesTax') {
                return this.includesTax;
            }
            if (key === 'currency') {
                return this.currency;
            }
            return '';
        },

        summarySelectedLabel(key) {
            const v = this.summaryValue(key);
            const opt = this.getSummaryOptions(key).find((o) => String(o.value) === String(v));
            return opt ? opt.label : '';
        },

        filterSummaryOptions(key) {
            const opts = this.getSummaryOptions(key);
            const q = String(this.summaryUi[key]?.q || '')
                .trim()
                .toLowerCase();
            if (!q) {
                return opts;
            }
            return opts.filter((o) => String(o.label || '').toLowerCase().includes(q));
        },

        selectSummaryOption(key, opt) {
            if (key === 'detailType') {
                this.changeDetailType(opt.value);
            } else if (key === 'affectsKardex') {
                this.affectsKardex = opt.value;
            } else if (key === 'includesTax') {
                this.includesTax = opt.value;
            } else if (key === 'currency') {
                this.currency = opt.value;
            }
            if (this.summaryUi[key]) {
                this.summaryUi[key].open = false;
                this.summaryUi[key].q = '';
            }
        },

        toggleSummaryDropdown(key) {
            const ui = this.summaryUi[key];
            if (!ui) {
                return;
            }
            const willOpen = !ui.open;
            Object.keys(this.summaryUi).forEach((k) => {
                this.summaryUi[k].open = false;
                this.summaryUi[k].q = '';
            });
            if (willOpen) {
                ui.open = true;
                this.$nextTick(() => {
                    const refs = {
                        detailType: this.$refs.summarySearchDetailType,
                        affectsKardex: this.$refs.summarySearchAffectsKardex,
                        includesTax: this.$refs.summarySearchIncludesTax,
                        currency: this.$refs.summarySearchCurrency,
                    };
                    const input = refs[key];
                    if (input && typeof input.focus === 'function') {
                        input.focus();
                    }
                });
            }
        },

        addItem() {
            this.items.push(this.newItem());
            this.syncPaymentAmounts();
        },

        defaultGlosaUnitId() {
            const list = this.units || [];
            const norm = (s) => String(s || '').trim().toLowerCase();
            const exact = list.find((u) => norm(u.description) === 'unidad(es)');
            if (exact) {
                return Number(exact.id || 0);
            }
            const startsUnidad = list.find((u) => /^unidad/i.test(String(u.description || '').trim()));
            return Number((startsUnidad || list[0])?.id || 0);
        },

        newGlosaItem() {
            const defaultUnitId = this.defaultGlosaUnitId();
            return {
                product_id: 0,
                unit_id: defaultUnitId,
                description: '',
                quantity: 1,
                amount: 0,
                comment: '',
                product_query: '',
                product_open: false,
                product_cursor: 0,
            };
        },

        addGlosaItem() {
            this.items.push(this.newGlosaItem());
            this.syncPaymentAmounts();
        },

        addProductCard(product) {
            const productId = Number(product.id || 0);
            if (!productId) {
                return;
            }

            const existing = this.items.find((item) => Number(item.product_id) === productId);
            if (existing) {
                existing.quantity = Number(existing.quantity || 0) + 1;
                this.syncPaymentAmounts();
                return;
            }

            const nextItem = {
                product_id: productId,
                unit_id: Number(product.unit_id || product.unit_sale || 0),
                description: this.productLabel(product),
                quantity: 1,
                amount: this.productAmount(product),
                comment: '',
                product_query: `${product.code || 'SIN'} - ${this.productLabel(product)}`,
                product_open: false,
                product_cursor: 0,
            };

            const placeholderIndex = this.items.findIndex((item) => Number(item.product_id || 0) === 0 && !String(item.description || '').trim());

            if (placeholderIndex >= 0) {
                this.items.splice(placeholderIndex, 1, nextItem);
            } else {
                this.items.push(nextItem);
            }

            this.syncPaymentAmounts();
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.syncPaymentAmounts();
        },

        updateItemQuantity(index, delta) {
            if (!this.items[index]) {
                return;
            }

            this.items[index].quantity = Number(this.items[index].quantity || 0) + Number(delta || 0);
            if (this.items[index].quantity <= 0) {
                this.removeItem(index);
                return;
            }

            this.syncPaymentAmounts();
        },

        setItemQuantity(index, value) {
            if (!this.items[index]) {
                return;
            }

            const parsed = Math.floor(Number(value));
            if (!Number.isFinite(parsed) || parsed <= 0) {
                this.removeItem(index);
                return;
            }

            this.items[index].quantity = parsed;
            this.syncPaymentAmounts();
        },

        sanitizeMoney(value) {
            const parsed = Number(value);
            return Number.isFinite(parsed) && parsed >= 0 ? Number(parsed.toFixed(6)) : 0;
        },

        setItemAmount(index, value) {
            if (!this.items[index]) {
                return;
            }

            this.items[index].amount = this.sanitizeMoney(value);
            this.syncPaymentAmounts();
        },

        setItemLineTotal(index, value) {
            if (!this.items[index]) {
                return;
            }

            const qty = Number(this.items[index].quantity || 0);
            const total = this.sanitizeMoney(value);
            this.items[index].amount = qty > 0 ? Number((total / qty).toFixed(6)) : 0;
            this.syncPaymentAmounts();
        },

        productById(id) {
            return this.products.find((product) => Number(product.id) === Number(id)) || null;
        },

        productImage(item) {
            const product = this.productById(item.product_id);
            return product?.img || '';
        },

        filteredProducts(item) {
            const term = String(item.product_query || '').toLowerCase().trim();
            const list = term === ''
                ? this.products
                : this.products.filter((product) => (
                    String(product.code || '').toLowerCase().includes(term)
                    || this.productLabel(product).toLowerCase().includes(term)
                    || String(product.unit_name || '').toLowerCase().includes(term)
                ));

            if (item.product_cursor >= list.length) {
                item.product_cursor = 0;
            }

            return list.slice(0, 40);
        },

        selectProduct(index, product) {
            this.items[index].product_id = Number(product.id);
            this.items[index].product_query = `${product.code || 'SIN'} - ${this.productLabel(product)}`;
            this.items[index].description = this.productLabel(product);
            this.items[index].product_open = false;
            this.setProductMeta(index);
            this.syncPaymentAmounts();
        },

        clearProduct(index) {
            Object.assign(this.items[index], {
                product_id: 0,
                product_query: '',
                description: '',
                unit_id: 0,
                amount: 0,
                product_open: true,
                product_cursor: 0,
            });
            this.syncPaymentAmounts();
        },

        moveProductCursor(index, step) {
            const item = this.items[index];
            const list = this.filteredProducts(item);
            if (!list.length) {
                return;
            }

            const max = list.length - 1;
            const next = item.product_cursor + step;
            item.product_cursor = next < 0 ? max : (next > max ? 0 : next);
        },

        selectProductByCursor(index) {
            const item = this.items[index];
            const list = this.filteredProducts(item);
            if (list.length) {
                this.selectProduct(index, list[item.product_cursor] || list[0]);
            }
        },

        setProductMeta(index) {
            const product = this.products.find((item) => Number(item.id) === Number(this.items[index].product_id));
            if (!product) {
                return;
            }

            if (!this.items[index].product_query) {
                this.items[index].product_query = `${product.code || 'SIN'} - ${this.productLabel(product)}`;
            }

            this.items[index].description = this.productLabel(product);

            if (!this.items[index].unit_id && (product.unit_id || product.unit_sale)) {
                this.items[index].unit_id = Number(product.unit_id || product.unit_sale);
            }

            if (!this.items[index].amount && (product.cost || product.price)) {
                this.items[index].amount = Number(product.cost || product.price);
            }
        },

        inferKind(description) {
            const normalized = String(description || '').toLowerCase();
            if (normalized.includes('tarjeta') || normalized.includes('card')) {
                return 'card';
            }

            if (normalized.includes('billetera') || normalized.includes('wallet')) {
                return 'wallet';
            }

            return 'plain';
        },

        cardTypeLabel(type) {
            const c = String(type || '').trim().toUpperCase();
            if (c === 'C') {
                return 'Crédito';
            }
            if (c === 'D') {
                return 'Débito';
            }
            return '';
        },

        get paymentMethodVariants() {
            return this.paymentMethods.flatMap((method) => {
                const methodId = Number(method.id);
                const description = String(method.description || '');
                const kind = this.inferKind(description);

                if (kind === 'wallet' && this.digitalWallets.length) {
                    return this.digitalWallets.map((wallet) => ({
                        key: `wallet:${methodId}:${Number(wallet.id)}`,
                        payment_method_id: methodId,
                        digital_wallet_id: Number(wallet.id),
                        card_id: null,
                        label: `${description} - ${wallet.description}`,
                        kind,
                    }));
                }

                if (kind === 'card' && this.cards.length) {
                    return this.cards.map((card) => {
                        const typePart = this.cardTypeLabel(card.type);
                        const base = `${description} - ${card.description}`;
                        const label = typePart ? `${base} (${typePart})` : base;
                        return {
                            key: `card:${methodId}:${Number(card.id)}`,
                            payment_method_id: methodId,
                            digital_wallet_id: null,
                            card_id: Number(card.id),
                            label,
                            kind,
                        };
                    });
                }

                return [{
                    key: `plain:${methodId}`,
                    payment_method_id: methodId,
                    digital_wallet_id: null,
                    card_id: null,
                    label: description,
                    kind,
                }];
            });
        },

        getPaymentVariantByKey(key) {
            return this.paymentMethodVariants.find((variant) => variant.key === key) || null;
        },

        getDefaultPaymentVariant() {
            return this.paymentMethodVariants[0] || null;
        },

        makePaymentRowFromExisting(row) {
            const cardKey = row.card_id ? `card:${Number(row.payment_method_id)}:${Number(row.card_id)}` : null;
            const walletKey = row.digital_wallet_id ? `wallet:${Number(row.payment_method_id)}:${Number(row.digital_wallet_id)}` : null;
            const plainKey = `plain:${Number(row.payment_method_id)}`;
            const variant = this.getPaymentVariantByKey(cardKey)
                || this.getPaymentVariantByKey(walletKey)
                || this.getPaymentVariantByKey(plainKey)
                || this.getDefaultPaymentVariant();

            return {
                method_variant_key: variant?.key || '',
                payment_method_id: Number(variant?.payment_method_id || row.payment_method_id || 0),
                card_id: Number(variant?.card_id || row.card_id || 0) || null,
                digital_wallet_id: Number(variant?.digital_wallet_id || row.digital_wallet_id || 0) || null,
                payment_gateway_id: Number(row.payment_gateway_id || 0) || null,
                amount: Number(row.amount || 0),
                kind: variant?.kind || 'plain',
            };
        },

        addPaymentRow() {
            const variant = this.getDefaultPaymentVariant();
            if (!variant) {
                return;
            }

            this.paymentRows.push({
                method_variant_key: variant.key,
                payment_method_id: variant.payment_method_id,
                card_id: variant.card_id,
                digital_wallet_id: variant.digital_wallet_id,
                payment_gateway_id: null,
                amount: 0,
                kind: variant.kind,
            });

            this.syncPaymentAmounts();
        },

        applyPaymentVariant(index) {
            const variant = this.getPaymentVariantByKey(this.paymentRows[index].method_variant_key);
            if (!variant) {
                return;
            }

            Object.assign(this.paymentRows[index], {
                payment_method_id: Number(variant.payment_method_id),
                card_id: variant.card_id ? Number(variant.card_id) : null,
                digital_wallet_id: variant.digital_wallet_id ? Number(variant.digital_wallet_id) : null,
                kind: variant.kind,
            });

            if (variant.kind !== 'card') {
                this.paymentRows[index].payment_gateway_id = null;
            }
        },

        removePaymentRow(index) {
            this.paymentRows.splice(index, 1);
            if (this.paymentType === 'CONTADO' && !this.paymentRows.length) {
                this.addPaymentRow();
            }
            this.syncPaymentAmounts();
        },

        onPaymentTypeChange() {
            if (this.paymentType === 'CONTADO') {
                if (this.affectsCash !== 'S' && this.affectsCash !== 'N') {
                    this.affectsCash = 'N';
                }
                if (!this.paymentRows.length) {
                    this.addPaymentRow();
                }
                this.syncPaymentAmounts();
                return;
            }

            this.affectsCash = 'S';
            this.paymentRows = [];
            this.syncCreditDueDate(true);
        },

        onAffectsCashChange() {
            if (this.paymentType !== 'CONTADO') {
                this.affectsCash = 'S';
                return;
            }

            if (this.affectsCash === 'S') {
                if (!this.paymentRows.length) {
                    this.addPaymentRow();
                }
                this.syncPaymentAmounts();
                return;
            }

            this.paymentRows = [];
        },

        syncPaymentAmounts() {
            if (this.paymentType !== 'CONTADO' || this.affectsCash !== 'S' || !this.paymentRows.length) {
                return;
            }

            const total = Number(this.summary.total || 0);
            if (this.paymentRows.length === 1) {
                this.paymentRows[0].amount = Number(total.toFixed(2));
                return;
            }

            const fixed = this.paymentRows.slice(0, -1).reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
            this.paymentRows[this.paymentRows.length - 1].amount = Math.max(0, Number((total - fixed).toFixed(2)));
        },

        money(value) {
            return `S/ ${Number(value || 0).toFixed(2)}`;
        },
    };
}
</script>
