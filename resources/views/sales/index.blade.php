@extends('layouts.app')

@section('content')
    @php
        use Illuminate\Support\Facades\Route;

        $viewId = request('view_id');
        $operacionesCollection = collect($operaciones ?? []);
        $topOperations = $operacionesCollection->where('type', 'T');
        $rowOperations = $operacionesCollection->where('type', 'R');

        $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
            if (!$action) {
                return '#';
            }

            if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                $url = $action;
            } else {
                $routeCandidates = [$action];
                if (!str_starts_with($action, 'admin.')) {
                    $routeCandidates[] = 'admin.' . $action;
                }
                $routeCandidates = array_merge(
                    $routeCandidates,
                    array_map(fn ($name) => $name . '.index', $routeCandidates)
                );

                $routeName = null;
                foreach ($routeCandidates as $candidate) {
                    if (Route::has($candidate)) {
                        $routeName = $candidate;
                        break;
                    }
                }

                if ($routeName) {
                    try {
                        $url = $model ? route($routeName, $model) : route($routeName);
                    } catch (\Exception $e) {
                        $url = '#';
                    }
                } else {
                    $url = '#';
                }
            }

            $targetViewId = $viewId;
            if ($operation && !empty($operation->view_id_action)) {
                $targetViewId = $operation->view_id_action;
            }

            if ($targetViewId && $url !== '#') {
                $separator = str_contains($url, '?') ? '&' : '?';
                $url .= $separator . 'view_id=' . urlencode($targetViewId);
            }

            return $url;
        };

        $invoicePeopleOptions = collect($people ?? [])
            ->map(function ($person) {
                $label = trim((string) ($person->first_name ?? '') . ' ' . (string) ($person->last_name ?? ''));

                return [
                    'id' => (int) $person->id,
                    'label' => $label !== '' ? $label : ('Persona #' . $person->id),
                    'document_number' => (string) ($person->document_number ?? ''),
                    'search' => mb_strtolower(trim($label . ' ' . ($person->document_number ?? '')), 'UTF-8'),
                ];
            })
            ->values()
            ->all();

        $invoiceModalSaleId = (int) old('invoice_sale_id', 0);
        $bootPersonId = (string) old('person_id', '');
        $bootPerson = collect($invoicePeopleOptions)->first(fn ($item) => (string) ($item['id'] ?? '') === $bootPersonId);
        $invoiceModalDraftPayload = $invoiceModalSaleId > 0
            ? [
                'action' => route('admin.sales.invoice', array_merge([$invoiceModalSaleId], $viewId ? ['view_id' => $viewId] : [])),
                'sale_id' => $invoiceModalSaleId,
                'sale_code' => (string) old('invoice_sale_code', 'Venta #' . $invoiceModalSaleId),
                'person_id' => $bootPersonId,
                'person_label' => $bootPerson['label'] ?? '',
                'invoice_series' => (string) old('invoice_series', '001'),
                'invoice_number' => (string) old('invoice_number', ''),
            ]
            : null;
    @endphp

    <div
        x-data="{
            openRow: null,
            peopleOptions: @js($invoicePeopleOptions),
            draftPayload: @js($invoiceModalDraftPayload),
            invoiceModalOpen: false,
            invoiceDropdownOpen: false,
            invoiceSearch: '',
            invoiceForm: {
                action: '',
                sale_id: '',
                sale_code: '',
                person_id: '',
                person_label: '',
                invoice_series: '001',
                invoice_number: '',
            },
            init() {
                this.ensureClosedWithoutSale();
                this.$watch('invoiceModalOpen', (value) => {
                    if (value && String(this.invoiceForm.sale_id || '').trim() === '') {
                        this.invoiceModalOpen = false;
                    }
                });
            },
            ensureClosedWithoutSale() {
                if (String(this.invoiceForm.sale_id || '').trim() === '') {
                    this.invoiceModalOpen = false;
                }
            },
            resolvePersonLabel(personId, fallback = '') {
                const match = this.peopleOptions.find((person) => String(person.id) === String(personId || ''));
                return match ? match.label : fallback;
            },
            resolvePersonId(personId, fallbackLabel = '') {
                if (String(personId || '') !== '') {
                    return String(personId);
                }

                const normalizedLabel = String(fallbackLabel || '').trim().toLowerCase();
                if (normalizedLabel === '') {
                    return '';
                }

                const match = this.peopleOptions.find((person) => String(person.label || '').trim().toLowerCase() === normalizedLabel);
                return match ? String(match.id) : '';
            },
            filteredPeople() {
                const needle = String(this.invoiceSearch || '').trim().toLowerCase();
                const base = needle === ''
                    ? this.peopleOptions
                    : this.peopleOptions.filter((person) => String(person.search || '').includes(needle));

                return base.slice(0, 8);
            },
            openInvoiceModal(payload) {
                const source = this.draftPayload && String(this.draftPayload.sale_id || '') === String(payload.sale_id || '')
                    ? { ...payload, ...this.draftPayload }
                    : payload;

                this.invoiceForm.action = String(source.action || '');
                this.invoiceForm.sale_id = String(source.sale_id || '');
                this.invoiceForm.sale_code = String(source.sale_code || '');
                this.invoiceForm.person_id = this.resolvePersonId(source.person_id || '', String(source.person_label || ''));
                this.invoiceForm.person_label = this.resolvePersonLabel(this.invoiceForm.person_id, String(source.person_label || ''));
                this.invoiceForm.invoice_series = String(source.invoice_series || '001');
                this.invoiceForm.invoice_number = String(source.invoice_number || '');
                this.invoiceSearch = this.invoiceForm.person_label;
                this.invoiceDropdownOpen = false;
                this.invoiceModalOpen = String(this.invoiceForm.sale_id || '').trim() !== '';
            },
            closeInvoiceModal() {
                this.invoiceDropdownOpen = false;
                this.invoiceModalOpen = false;
                this.invoiceForm.action = '';
                this.invoiceForm.sale_id = '';
                this.invoiceForm.sale_code = '';
                this.invoiceForm.person_id = '';
                this.invoiceForm.person_label = '';
                this.invoiceForm.invoice_series = '001';
                this.invoiceForm.invoice_number = '';
                this.invoiceSearch = '';
            },
            selectPerson(person) {
                this.invoiceForm.person_id = String(person.id || '');
                this.invoiceForm.person_label = String(person.label || '');
                this.invoiceSearch = this.invoiceForm.person_label;
                this.invoiceDropdownOpen = false;
            },
            onInvoiceSearchInput() {
                if (this.invoiceSearch !== this.invoiceForm.person_label) {
                    this.invoiceForm.person_id = '';
                }
                this.invoiceDropdownOpen = true;
            },
        }"
    >
        <x-common.page-breadcrumb pageTitle="Ventas" />

        <x-common.component-card title="Listado de ventas" desc="Gestiona las ventas registradas.">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if (session('import_duplicates') && count(session('import_duplicates')) > 0)
                @php $dups = session('import_duplicates'); @endphp
                <div x-data="{ open: true }" x-show="open"
                    class="mb-4 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-amber-200 dark:border-amber-700">
                        <div class="flex items-center gap-2">
                            <i class="ri-file-copy-2-line text-amber-600 text-lg"></i>
                            <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                {{ count($dups) }} venta(s) duplicada(s) — no fueron importadas
                            </span>
                        </div>
                        <button @click="open = false" class="text-amber-500 hover:text-amber-700 transition">
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>
                    <div class="overflow-x-auto px-4 py-3">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-amber-200 dark:border-amber-700">
                                    <th class="pb-2 text-left font-semibold text-amber-700 dark:text-amber-400 pr-4">Fila</th>
                                    <th class="pb-2 text-left font-semibold text-amber-700 dark:text-amber-400 pr-4">Fecha</th>
                                    <th class="pb-2 text-left font-semibold text-amber-700 dark:text-amber-400 pr-4">Cliente</th>
                                    <th class="pb-2 text-left font-semibold text-amber-700 dark:text-amber-400 pr-4">Descripción</th>
                                    <th class="pb-2 text-right font-semibold text-amber-700 dark:text-amber-400 pr-4">Total</th>
                                    <th class="pb-2 text-left font-semibold text-amber-700 dark:text-amber-400">Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dups as $dup)
                                    <tr class="border-b border-amber-100 dark:border-amber-800 last:border-0">
                                        <td class="py-1.5 pr-4 text-amber-700 dark:text-amber-300">#{{ $dup['fila'] }}</td>
                                        <td class="py-1.5 pr-4 text-gray-600 dark:text-gray-400">{{ $dup['fecha'] }}</td>
                                        <td class="py-1.5 pr-4 font-medium text-gray-800 dark:text-gray-200 max-w-[140px] truncate" title="{{ $dup['cliente'] }}">{{ $dup['cliente'] }}</td>
                                        <td class="py-1.5 pr-4 text-gray-700 dark:text-gray-300 max-w-[200px] truncate" title="{{ $dup['descripcion'] }}">{{ $dup['descripcion'] }}</td>
                                        <td class="py-1.5 pr-4 text-right font-semibold text-gray-800 dark:text-gray-200">S/ {{ $dup['total'] }}</td>
                                        <td class="py-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium
                                                {{ $dup['razon'] === 'Ya existe en el sistema' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                                <i class="{{ $dup['razon'] === 'Ya existe en el sistema' ? 'ri-database-2-line' : 'ri-file-copy-line' }}"></i>
                                                {{ $dup['razon'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (session('auto_download_xml_movement_id') || session('auto_download_cdr_movement_id'))
                <script>
                    window.addEventListener('load', function () {
                        const downloadUrls = [];
                        @if (session('auto_download_xml_movement_id'))
                            downloadUrls.push(@json(route('admin.sales.electronic.xml.download', (int) session('auto_download_xml_movement_id'))));
                        @endif
                        @if (session('auto_download_cdr_movement_id'))
                            downloadUrls.push(@json(route('admin.sales.electronic.cdr.download', (int) session('auto_download_cdr_movement_id'))));
                        @endif
                        downloadUrls.forEach(function (url, index) {
                            setTimeout(function () {
                                const iframe = document.createElement('iframe');
                                iframe.style.display = 'none';
                                iframe.src = url;
                                document.body.appendChild(iframe);
                                setTimeout(function () { iframe.remove(); }, 60000);
                            }, index * 900);
                        });
                    });
                </script>
            @endif
            @if ($errors->has('error'))
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    {{ $errors->first('error') }}
                </div>
            @endif
            @if ($errors->has('person_id') || $errors->has('invoice_series') || $errors->has('invoice_number'))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    {{ $errors->first('person_id') ?: ($errors->first('invoice_series') ?: $errors->first('invoice_number')) }}
                </div>
            @endif

            <div class="flex flex-col gap-4 xl:flex-row xl:flex-wrap xl:items-center xl:justify-between">
                <style>
                    .align-calendar-right .flatpickr-calendar.static {
                        right: 0 !important;
                        left: auto !important;
                        transform: none !important;
                    }
                </style>
                <form method="GET" class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end xl:min-w-full">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-36 flex-none">
                        <x-form.select-autocomplete
                            name="per_page"
                            :value="$perPage"
                            :options="collect([10, 20, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                            placeholder="Por página"
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="relative flex-1 min-w-[320px] w-full sm:w-auto">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por numero, persona o usuario"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="w-full sm:w-44 sm:flex-none">
                        <x-form.select-autocomplete
                            name="document_type_id"
                            :value="$selectedDocumentTypeId ?? 'all'"
                            :options="collect($saleDocumentTypes ?? [])->map(fn($d) => ['value' => $d->id, 'label' => $d->name])->prepend(['value' => 'all', 'label' => 'Todos los docs.'])->values()->all()"
                            placeholder="Todos los docs."
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="w-full sm:w-40 sm:flex-none">
                        <x-form.select-autocomplete
                            name="billing_status"
                            :value="$billingStatus ?? 'all'"
                            :options="[['value' => 'all', 'label' => 'Todas'], ['value' => 'pending', 'label' => 'Por facturar'], ['value' => 'invoiced', 'label' => 'Facturadas']]"
                            placeholder="Todas"
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="flex-none min-w-[160px] w-full sm:w-auto" @date-change="$el.closest('form').submit()">
                        <x-form.date-picker
                            id="sale-date-from"
                            name="date_from"
                            label="Inicio"
                            :defaultDate="$dateFrom ?? null"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div class="flex-none min-w-[160px] w-full sm:w-auto align-calendar-right" @date-change="$el.closest('form').submit()">
                        <x-form.date-picker
                            id="sale-date-to"
                            name="date_to"
                            label="Fin"
                            :defaultDate="$dateTo ?? null"
                            dateFormat="Y-m-d"
                            :altInput="true"
                            altFormat="d/m/Y"
                            locale="es"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div class="w-full sm:w-40 sm:flex-none">
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 sm:hidden">Caja</label>
                        <x-form.select-autocomplete
                            name="cash_register_id"
                            :value="$selectedBoxId ?? ''"
                            :options="collect([['value' => '', 'label' => 'Todas']])->merge(collect($cashRegisters ?? [])->map(fn($reg) => ['value' => $reg->id, 'label' => $reg->number]))->values()->all()"
                            placeholder="Todas"
                            :submit-on-change="true"
                            inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    @if (($selectedBoxId ?? null) && ($shiftRelations ?? collect())->isNotEmpty())
                        @php
                            $shiftOptions = collect([['value' => 'all', 'label' => 'Todos']]);
                            foreach ($shiftRelations as $rel) {
                                $boxNum = $rel->cashMovementStart->cashRegister->number ?? '';
                                $shiftName = $rel->cashMovementStart->shift->name ?? 'Turno';
                                $started = $rel->started_at ? \Carbon\Carbon::parse($rel->started_at)->format('Y-m-d H:i:s') : '';
                                $ended = $rel->ended_at ? \Carbon\Carbon::parse($rel->ended_at)->format('Y-m-d H:i:s') : '';
                                $label = $boxNum ? " {$shiftName} | {$started}" : "{$shiftName} | {$started}";
                                if ($rel->status === '1') {
                                    $label .= ' (En curso)';
                                } elseif ($ended) {
                                    $label .= " - {$ended}";
                                }
                                $shiftOptions->push(['value' => $rel->id, 'label' => $label]);
                            }
                        @endphp
                        <div class="w-full min-w-0 flex-1 sm:min-w-[320px]">
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 sm:hidden">Turno</label>
                            <x-form.select-autocomplete
                                name="shift_relation_id"
                                :value="$selectedShiftId ?? 'all'"
                                :options="$shiftOptions->values()->all()"
                                placeholder="Todos"
                                :submit-on-change="true"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    @endif
                    <div class="flex flex-1 flex-wrap items-center justify-between gap-2 min-w-fit sm:min-w-[300px]">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #334155; border-color: #334155;">
                                <i class="ri-search-line text-gray-100"></i>
                                <span class="font-medium text-gray-100">Buscar</span>
                            </x-ui.button>
                            <x-ui.link-button size="md" variant="outline" href="{{ route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                <i class="ri-refresh-line"></i>
                                <span class="font-medium">Limpiar</span>
                            </x-ui.link-button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @php $singleConfirm = session('import_single_sheet_confirm'); @endphp
                            <div
                                x-data="{
                                    open: {{ $singleConfirm ? 'true' : 'false' }},
                                    step: '{{ $singleConfirm ? 'confirm' : 'upload' }}',
                                    file: null,
                                    fileName: '',
                                    dragging: false,
                                    confirmData: {{ $singleConfirm ? json_encode($singleConfirm) : 'null' }}
                                }"
                                class="flex flex-wrap items-start gap-2"
                            >
                                <button
                                    type="button"
                                    @click="open = true"
                                    class="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95"
                                >
                                    <i class="ri-file-excel-2-line text-green-600 text-base"></i>
                                    <span>Importar Excel</span>
                                </button>

                                <template x-teleport="body">
                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        class="fixed inset-0 flex items-center justify-center px-4 backdrop-blur-sm bg-black/30"
                                        style="display:none; z-index:999999;"
                                        @click.self="open = false; file = null; fileName = ''"
                                    >
                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                                        >
                                            {{-- Header --}}
                                            <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-green-600">
                                                    <i class="ri-file-excel-2-line text-lg text-white"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Importar ventas desde Excel</h3>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Se registrarán en la sucursal actual</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="open = false; file = null; fileName = ''"
                                                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                                                >
                                                    <i class="ri-close-line text-xl"></i>
                                                </button>
                                            </div>

                                            {{-- Body --}}
                                            <div class="space-y-4 px-6 py-5">

                                                {{-- Step: confirmación hoja única --}}
                                                <div x-show="step === 'confirm'" x-cloak>
                                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                                                        <div class="flex items-start gap-3">
                                                            <i class="ri-error-warning-line text-amber-500 text-xl mt-0.5 flex-shrink-0"></i>
                                                            <div>
                                                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">
                                                                    Solo se encontró 1 hoja en el archivo
                                                                </p>
                                                                <p class="text-sm text-amber-700 dark:text-amber-400">
                                                                    Hoja detectada: <strong x-text="confirmData?.sheet"></strong>
                                                                    &mdash; Tipo: <span class="font-semibold" x-text="confirmData?.type"></span>
                                                                </p>
                                                                <p class="text-sm text-amber-700 dark:text-amber-400 mt-2">
                                                                    ¿Deseas continuar importando <strong>solo</strong> esta hoja?
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <form method="POST" action="{{ route('admin.sales.import-excel', $viewId ? ['view_id' => $viewId] : []) }}" class="mt-4">
                                                        @csrf
                                                        <input type="hidden" name="confirm_single" value="1">
                                                        <input type="hidden" name="temp_key"   x-bind:value="confirmData?.temp_key ?? ''">
                                                        <input type="hidden" name="sheet_name" x-bind:value="confirmData?.sheet ?? ''">
                                                        <div class="flex gap-3">
                                                            <button
                                                                type="submit"
                                                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-green-600 text-sm font-semibold text-white transition hover:bg-green-700 active:scale-95"
                                                            >
                                                                <i class="ri-check-line"></i>
                                                                Sí, importar solo <span class="ml-1" x-text="confirmData?.type"></span>
                                                            </button>
                                                            <a
                                                                href="{{ route('admin.sales.index', $viewId ? ['view_id' => $viewId] : []) }}"
                                                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 transition hover:bg-gray-50 active:scale-95"
                                                            >
                                                                <i class="ri-close-line"></i>
                                                                Cancelar
                                                            </a>
                                                        </div>
                                                    </form>
                                                </div>

                                                {{-- Step: subir archivo --}}
                                                <div x-show="step === 'upload'" x-cloak>

                                                {{-- Format table --}}
                                                <div>
                                                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Formato esperado del archivo:</p>
                                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                                        <table class="w-full text-xs">
                                                            <thead>
                                                                <tr style="background-color: #059669;">
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">FECHA</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">CLIENTE</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">DESCRIPCION</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">CANTIDAD</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">P. UNIT.</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">TOTAL VENTA</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">TIPO DE PAGO</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">TIPO DE OPERACIÓN</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-white">FORMA DE PAGO</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr class="bg-white dark:bg-gray-900">
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-400">opcional</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">GP MOTOS</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">ENSAMBLAJE...</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">1</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">45.00</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 font-semibold text-gray-900 dark:text-white">45.00</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">CREDITO</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">SERVICIO</td>
                                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-400">X FACTURAR</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                {{-- Info note --}}
                                                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                    Las columnas se detectan por encabezado — el orden no importa. Si la columna <strong>FECHA</strong> está vacía, se usa la fecha del campo <strong>PERIODO</strong> del archivo. Los clientes se buscan por nombre; si no existen, se crean automáticamente.
                                                </div>

                                                {{-- File upload form --}}
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sales.import-excel', $viewId ? ['view_id' => $viewId] : []) }}"
                                                    enctype="multipart/form-data"
                                                    data-turbo="false"
                                                    @submit="if (!file) { $event.preventDefault(); alert('Selecciona un archivo primero.'); }"
                                                >
                                                    @csrf

                                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Archivo Excel <span class="text-red-500">*</span>
                                                    </label>

                                                    <div
                                                        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 transition-colors"
                                                        :class="dragging ? 'border-green-400 bg-green-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
                                                        @dragover.prevent="dragging = true"
                                                        @dragleave.prevent="dragging = false"
                                                        @drop.prevent="
                                                            dragging = false;
                                                            const f = $event.dataTransfer.files[0];
                                                            if (f) { file = f; fileName = f.name; $refs.fileInput.files = $event.dataTransfer.files; }
                                                        "
                                                        @click="$refs.fileInput.click()"
                                                    >
                                                        <input
                                                            type="file"
                                                            name="file"
                                                            x-ref="fileInput"
                                                            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                                                            class="hidden"
                                                            @change="const f = $event.target.files[0]; if (f) { file = f; fileName = f.name; }"
                                                        >
                                                        <i
                                                            class="ri-upload-cloud-2-line mb-2 text-3xl"
                                                            :class="file ? 'text-green-500' : 'text-gray-400'"
                                                        ></i>
                                                        <p
                                                            class="text-sm font-medium"
                                                            :class="file ? 'text-green-700' : 'text-gray-700'"
                                                            x-text="file ? fileName : 'Arrastra tu archivo o haz clic para seleccionar'"
                                                        ></p>
                                                        <p class="mt-1 text-xs text-gray-400">.xlsx, .xls, .csv &bull; Máximo 10 MB</p>
                                                    </div>

                                                    <div class="mt-5 flex gap-3">
                                                        <button
                                                            type="submit"
                                                            class="flex h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-green-600 text-sm font-semibold text-white transition hover:bg-green-700 active:scale-95"
                                                        >
                                                            <i class="ri-upload-2-line"></i>
                                                            Importar
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="open = false; file = null; fileName = ''"
                                                            class="flex h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 transition hover:bg-gray-50 active:scale-95"
                                                        >
                                                            <i class="ri-close-line"></i>
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                </form>

                                                </div>{{-- /step upload --}}

                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            @if ($topOperations->isNotEmpty())
                                @foreach ($topOperations as $operation)
                                    @php
                                        $topColor = $operation->color ?: '#3B82F6';
                                        $topTextColor = str_contains($operation->action ?? '', 'sales.create') ? '#111827' : '#FFFFFF';
                                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                                    @endphp
                                    <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                        <i class="{{ $operation->icon }}"></i>
                                        <span>{{ $operation->name }}</span>
                                    </x-ui.link-button>
                                @endforeach
                            @else
                                <x-ui.link-button size="md" variant="primary" style="background-color: #12f00e; color: #111827;" href="{{ route('admin.sales.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                    <i class="ri-add-line"></i>
                                    <span>Nueva venta</span>
                                </x-ui.link-button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-white text-theme-xs uppercase">ID</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th style="background-color: #334155; color: #FFFFFF;" class= "px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th  style="background-color: #334155; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th  style="background-color: #334155; color: #FFFFFF;"  class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th  style="background-color: #334155; color: #FFFFFF;"  class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>
                            <th  style="background-color: #334155; color: #FFFFFF;"  class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Estado</p>
                            </th>
                            <th  style="background-color: #334155; color: #FFFFFF;"  class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr
                                class="group/row border-b border-gray-100 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 relative hover:z-[60]"
                                onmouseenter="this.querySelector('.sticky-left')?.style.setProperty('background-color', '#f9fafb', 'important')"
                                onmouseleave="this.querySelector('.sticky-left')?.style.setProperty('background-color', '#ffffff', 'important')"
                            >
                                <td class="px-5 py-3 text-center sticky-left group-hover/row:bg-gray-50 dark:group-hover/row:bg-white/5">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $sale->id }} ? openRow = null : openRow = {{ $sale->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600">
                                            <i class="ri-add-line" x-show="openRow !== {{ $sale->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $sale->id }}"></i>
                                        </button>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">#{{ $sale->id }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ $sale->salesDocumentCode() }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">
                                            {{ $sale->documentType?->name ?? '-' }}
                                        </p>
                                        @if ($sale->isSalesInvoice())
                                            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $sale->salesBillingStatus() === 'PENDING' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                {{ $sale->salesBillingStatusLabel() }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/ {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/ {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="font-bold text-brand-600 text-theme-sm dark:text-brand-400">S/ {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90 truncate max-w-[150px]" title="{{ $sale->person_name ?? 'Público General' }}">
                                        {{ $sale->person_name ?? 'Público General' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    @php
                                        $status = $sale->status ?? 'A';
                                        $badgeColor = 'success';
                                        $badgeText = 'Activo';
                                        if ($status === 'P') {
                                            $badgeColor = 'warning';
                                            $badgeText = 'Pendiente';
                                        } elseif ($status !== 'A') {
                                            $badgeColor = 'error';
                                            $badgeText = 'Inactivo';
                                        }
                                    @endphp
                                    <div class="flex justify-center">
                                        <x-ui.badge variant="light" color="{{ $badgeColor }}">
                                            {{ $badgeText }}
                                        </x-ui.badge>
                                    </div>
                                </td>
                                @php
                                    $invoicePayload = [
                                        'action' => route('admin.sales.invoice', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])),
                                        'sale_id' => (int) $sale->id,
                                        'sale_code' => (string) $sale->salesDocumentCode(),
                                        'person_id' => $sale->person_id ? (string) $sale->person_id : '',
                                        'person_label' => trim((string) ($sale->person_name ?? '')),
                                        'invoice_series' => trim((string) ($sale->salesMovement?->series ?? '001')) ?: '001',
                                        'invoice_number' => trim((string) ($sale->salesMovement?->billing_number ?? '')),
                                    ];
                                @endphp
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isCharge = str_contains($action, 'charge');
                                                    if ($isCharge && ($sale->status ?? 'A') !== 'P') {
                                                        continue;
                                                    }

                                                    $actionUrl = $resolveActionUrl($action, $sale, $operation);
                                                    if ($isCharge && $actionUrl !== '#') {
                                                        $separator = str_contains($actionUrl, '?') ? '&' : '?';
                                                        $actionUrl .= $separator . 'movement_id=' . urlencode($sale->id);
                                                    }

                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonTextColor = str_contains($action, 'edit') ? '#111827' : '#FFFFFF';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$buttonTextColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp

                                                @if ($isDelete)
                                                    <form
                                                        method="POST"
                                                        action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar venta?"
                                                        data-swal-text="Se eliminara la venta {{ $sale->salesDocumentCode() }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar"
                                                        data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            {{ $operation->name }}
                                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}" href="{{ $actionUrl }}" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                            {{ $operation->name }}
                                                            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if ($sale->isSalesInvoice() && $sale->salesBillingStatus() === 'PENDING')
                                                <div class="relative group">
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="primary"
                                                        type="button"
                                                        className="rounded-xl border-0 shadow-none"
                                                        style="background-color: #0f766e; color: #ffffff;"
                                                        onmouseover="this.style.backgroundColor='#115e59'"
                                                        onmouseout="this.style.backgroundColor='#0f766e'"
                                                        @click="openInvoiceModal({{ \Illuminate\Support\Js::from($invoicePayload) }})"
                                                        aria-label="Facturar"
                                                    >
                                                        <i class="ri-bill-line"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        Facturar
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="primary"
                                                        href="{{ route('admin.sales.print.pdf', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="rounded-xl border-0 shadow-none"
                                                        style="background-color: #ef4444; color: #ffffff;"
                                                        onmouseover="this.style.backgroundColor='#dc2626'"
                                                        onmouseout="this.style.backgroundColor='#ef4444'"
                                                        aria-label="Imprimir PDF" target="_blank"
                                                    >
                                                        <i class="ri-file-pdf-2-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        PDF
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="primary"
                                                        href="{{ route('admin.sales.print.ticket', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="rounded-xl border-0 shadow-none"
                                                        style="background-color: #8b5cf6; color: #ffffff;"
                                                        onmouseover="this.style.backgroundColor='#7c3aed'"
                                                        onmouseout="this.style.backgroundColor='#8b5cf6'"
                                                        aria-label="Imprimir Ticket" target="_blank"
                                                    >
                                                        <i class="ri-printer-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        Ticket
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                        @else
                                            @if(($sale->status ?? 'A') === 'P')
                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="primary"
                                                        href="{{ route('admin.sales.charge', array_merge(['movement_id' => $sale->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="bg-success-500 text-white hover:bg-success-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #10B981; color: #FFFFFF;"
                                                        aria-label="Cobrar"
                                                    >
                                                        <i class="ri-money-dollar-circle-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                        Cobrar
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif
                                            @if ($sale->isSalesInvoice() && $sale->salesBillingStatus() === 'PENDING')
                                                <div class="relative group">
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="primary"
                                                        type="button"
                                                        className="rounded-xl border-0 shadow-none"
                                                        style="background-color: #0f766e; color: #ffffff;"
                                                        onmouseover="this.style.backgroundColor='#115e59'"
                                                        onmouseout="this.style.backgroundColor='#0f766e'"
                                                        @click="openInvoiceModal({{ \Illuminate\Support\Js::from($invoicePayload) }})"
                                                        aria-label="Facturar"
                                                    >
                                                        <i class="ri-bill-line"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                        Facturar
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="edit"
                                                    href="{{ route('admin.sales.edit', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar"
                                                >
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                    Editar
                                                    <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </div>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sales.destroy', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="relative group js-swal-delete"
                                                data-swal-title="Eliminar venta?"
                                                data-swal-text="Se eliminara la venta {{ $sale->salesDocumentCode() }}. Esta accion no se puede deshacer."
                                                data-swal-confirm="Si, eliminar"
                                                data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444"
                                                data-swal-cancel-color="#6b7280"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <x-ui.button
                                                    size="icon"
                                                    variant="eliminate"
                                                    type="submit"
                                                    className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #EF4444; color: #FFFFFF;"
                                                    aria-label="Eliminar"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                    Eliminar
                                                    <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </form>

                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="primary"
                                                    href="{{ route('admin.sales.print.pdf', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border-0 shadow-none"
                                                    style="background-color: #ef4444; color: #ffffff;"
                                                    onmouseover="this.style.backgroundColor='#dc2626'"
                                                    onmouseout="this.style.backgroundColor='#ef4444'"
                                                    aria-label="Imprimir PDF"
                                                >
                                                    <i class="ri-file-pdf-2-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                    PDF
                                                    <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </div>
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="primary"
                                                    href="{{ route('admin.sales.print.ticket', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="rounded-xl border-0 shadow-none"
                                                    style="background-color: #8b5cf6; color: #ffffff;"
                                                    onmouseover="this.style.backgroundColor='#7c3aed'"
                                                    onmouseout="this.style.backgroundColor='#8b5cf6'"
                                                    aria-label="Imprimir Ticket"
                                                >
                                                    <i class="ri-printer-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                    Ticket
                                                    <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </div>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('admin.sales.resend-electronic', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                            class="relative group"
                                            style="display: none;"
                                            data-resend-electronic-invoice
                                            onsubmit="return confirm('¿Reenviar {{ $sale->salesDocumentCode() }} a SUNAT/Apisunat con el mismo correlativo?');"
                                        >
                                            @csrf
                                            @if ($viewId)
                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                            @endif
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="submit"
                                                className="rounded-xl border-0 shadow-none"
                                                style="background-color: #0891b2; color: #ffffff;"
                                                aria-label="Reenviar electrónico"
                                            >
                                                <i class="ri-refresh-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
                                                Reenviar SUNAT
                                            </span>
                                        </form>

                                        @include('sales.partials.electronic-invoice-actions', ['sale' => $sale])
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $sale->id }}" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                <td colspan="10" class="px-6 py-4">
                                    <div class="grid grid-cols-4 gap-3 sm:grid-cols-5">
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Persona</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->person_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->moved_at ? $sale->moved_at->format('d/m/Y H:i') : '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usuario</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->user_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</p>
                                            <p class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->responsible_name ?: '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de detalle</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->detail_type ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->currency ?? 'PEN' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">T. cambio</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($sale->salesMovement?->exchange_rate ?? 1), 3) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Por consumo</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ ($sale->salesMovement?->consumption ?? 'N') === 'Y' ? 'Sí' : 'No' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de pago</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ in_array(strtoupper((string) ($sale->salesMovement?->payment_type ?? '')), ['CREDITO', 'CREDIT', 'DEUDA'], true) ? 'CREDITO' : 'CONTADO' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Facturación</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesBillingStatusLabel() }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 sm:col-span-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Comentario</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ Str::limit($sale->comment ?? '-', 60) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado SUNAT</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->salesMovement?->status ?? '-' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Origen</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sale->movementType?->description ?? 'Venta' }} - {{ $sale->salesDocumentCode() }}</p>
                                        </div>
                                        @if ($sale->electronic_invoice_status === 'ERROR')
                                            <div class="rounded-lg border border-rose-200 bg-rose-50/80 px-4 py-2 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/40 sm:col-span-2">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-800 dark:text-rose-300">Error comprobante electrónico</p>
                                                <p class="mt-1 text-xs leading-relaxed text-rose-700 break-words whitespace-pre-wrap dark:text-rose-400">{{ $sale->electronicInvoiceErrorMessage() ?: 'Error desconocido al emitir el comprobante electrónico.' }}</p>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($sale->salesMovement?->details?->isNotEmpty())
                                        <div class="mt-4">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Detalle vendido</p>
                                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                                <table class="w-full min-w-[400px] text-sm">
                                                    <thead>
                                                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                                                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">Ítem</th>
                                                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">Cant.</th>
                                                            <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">Unidad</th>
                                                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">P. unit.</th>
                                                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($sale->salesMovement->details as $d)
                                                            @php
                                                                $qty = (float) ($d->quantity ?? 0);
                                                                $lineTotal = (float) ($d->amount ?? 0);
                                                                $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
                                                            @endphp
                                                            <tr class="border-b border-gray-100 dark:border-gray-700/50 last:border-0">
                                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                                                    {{ $d->code ? $d->code . ' - ' : '' }}{{ $d->description ?? '-' }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($qty, 2) }}</td>
                                                                <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $d->unit?->abbreviation ?? $d->unit?->description ?? '-' }}</td>
                                                                <td class="px-3 py-2 text-right tabular-nums text-gray-800 dark:text-gray-200">S/ {{ number_format($unitPrice, 2) }}</td>
                                                                <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-800 dark:text-gray-200">S/ {{ number_format($lineTotal, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-shopping-bag-3-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay ventas registradas.</p>
                                        <p class="text-gray-500">Crea la primera venta para comenzar.</p>
                                        <x-ui.link-button size="sm" variant="primary" href="{{ route('admin.sales.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar venta</span>
                                        </x-ui.link-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($sales->count() > 0)
@endif
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->total() }}</span>
                </div>
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    Total filtrado:
                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">S/ {{ number_format((float) ($salesTotalAmount ?? 0), 2) }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $sales->links('vendor.pagination.forced') }}
                </div>
            </div>
        </x-common.component-card>

        <template x-teleport="body">
            <div
                x-show="invoiceModalOpen"
                x-cloak
                x-effect="document.body.style.overflow = invoiceModalOpen ? 'hidden' : 'unset'"
                class="fixed inset-0 z-[100000] flex items-center justify-center overflow-hidden p-3 sm:p-6"
            >
                <div @click="closeInvoiceModal()" class="fixed inset-0 bg-gray-400/30 backdrop-blur-[32px]"></div>

                <div
                    @click.stop
                    class="relative flex w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-[#F4F6FA]"
                    style="max-height: min(calc(100vh - 1.5rem), calc(100dvh - 1.5rem));"
                >
                    <form method="POST" :action="invoiceForm.action" class="flex h-full flex-col">
                        @csrf
                        @if ($viewId)
                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                        @endif
                        <input type="hidden" name="invoice_sale_id" :value="invoiceForm.sale_id">
                        <input type="hidden" name="invoice_sale_code" :value="invoiceForm.sale_code">

                        <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Facturacion</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-800">Registrar factura</h3>
                                    <p class="mt-1 text-sm text-slate-500">Completa el cliente, la serie y el correlativo para marcar la venta como facturada.</p>
                                </div>
                                <button type="button" @click="closeInvoiceModal()" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                                    <i class="ri-close-line text-2xl"></i>
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-6 sm:px-8">
                            @if ($errors->any())
                                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Venta</p>
                                <p class="mt-2 text-lg font-bold text-slate-800" x-text="invoiceForm.sale_code || ('Venta #' + invoiceForm.sale_id)"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700">Cliente</label>
                                <div class="relative" @click.outside="invoiceDropdownOpen = false">
                                    <input type="hidden" name="person_id" :value="invoiceForm.person_id">
                                    <input
                                        x-model="invoiceSearch"
                                        @focus="invoiceDropdownOpen = true"
                                        @click="invoiceDropdownOpen = true"
                                        @input="onInvoiceSearchInput()"
                                        class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-500"
                                        placeholder="Buscar cliente por nombre o documento"
                                        autocomplete="off"
                                        required
                                    >
                                    <div x-show="invoiceDropdownOpen" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                                        <template x-if="filteredPeople().length === 0">
                                            <p class="px-4 py-3 text-sm text-slate-500">Sin resultados.</p>
                                        </template>
                                        <template x-for="person in filteredPeople()" :key="`invoice-person-${person.id}`">
                                            <button type="button" @click="selectPerson(person)" class="flex w-full items-start justify-between border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50">
                                                <span class="text-sm font-medium text-slate-800" x-text="person.label"></span>
                                                <span class="ml-3 text-xs text-slate-500" x-text="person.document_number || 'Sin documento'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                @error('person_id')
                                    <p class="text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium text-slate-700">Serie</label>
                                    <input
                                        type="text"
                                        name="invoice_series"
                                        x-model="invoiceForm.invoice_series"
                                        class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-500"
                                        placeholder="001"
                                        required
                                    >
                                    @error('invoice_series')
                                        <p class="text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium text-slate-700">Correlativo</label>
                                    <input
                                        type="text"
                                        name="invoice_number"
                                        x-model="invoiceForm.invoice_number"
                                        class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-500"
                                        placeholder="00000001"
                                        required
                                    >
                                    @error('invoice_number')
                                        <p class="text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-6 py-5 sm:px-8">
                            <x-ui.button size="md" variant="primary" type="submit" className="px-6">
                                <i class="ri-save-line"></i>
                                <span>Guardar factura</span>
                            </x-ui.button>
                            <x-ui.button size="md" variant="outline" type="button" className="px-6" @click="closeInvoiceModal()">
                                <i class="ri-close-line"></i>
                                <span>Cancelar</span>
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
    (function() {
        function showFlashToast() {
            const msg = sessionStorage.getItem('flash_success_message');
            if (!msg) return;
            sessionStorage.removeItem('flash_success_message');
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: msg,
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            }
        }
        showFlashToast();
        document.addEventListener('turbo:load', showFlashToast);
    })();
    </script>
    @endpush
@endsection
