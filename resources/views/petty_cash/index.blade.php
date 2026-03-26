@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    use Illuminate\Support\Js;
    use Illuminate\Support\Facades\Route;

    // --- ICONOS ---
    $SearchIcon = new HtmlString(
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>',
    );
    $ClearIcon = new HtmlString(
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>',
    );

    $viewId = request('view_id');
    $operacionesCollection = collect($operaciones ?? []);
    $topOperations = $operacionesCollection->where('type', 'T');
    $rowOperations = $operacionesCollection->where('type', 'R');
    $buildCloseUrl = function ($targetViewId = null) use ($selectedBoxId) {
        if (!$selectedBoxId) {
            return '#';
        }

        $params = ['cash_register_id' => $selectedBoxId];
        if ($targetViewId) {
            $params['view_id'] = $targetViewId;
        }

        return route('admin.petty-cash.close', $params);
    };

    $resolveActionUrl = function ($action, $movement = null, $operation = null) use ($viewId, $selectedBoxId) {
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
                array_map(fn($name) => $name . '.index', $routeCandidates)
            );

            $routeName = null;
            foreach ($routeCandidates as $candidate) {
                if (Route::has($candidate)) {
                    $routeName = $candidate;
                    break;
                }
            }

            $url = '#';
            if ($routeName) {
                try {
                    if ($movement) {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId, 'movement' => $movement->id]);
                    } else {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId]);
                    }
                } catch (\Exception $e) {
                    try {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId]);
                    } catch (\Exception $e2) {
                        try {
                            $url = $movement ? route($routeName, $movement) : route($routeName);
                        } catch (\Exception $e3) {
                            $url = '#';
                        }
                    }
                }
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

    $resolveTextColor = function ($operation) {
        $action = $operation->action ?? '';
        if (str_contains($action, 'create') || str_contains($action, 'store')) {
            return '#111827';
        }
        return '#FFFFFF';
    };
@endphp

@section('content')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div x-data="{
        openRow: null,
        open: {{ $errors->any() ? 'true' : 'false' }},
        formConcept: '{{ old('comment') }}',
        formConceptId: '{{ old('payment_concept_id') }}',
        formDocId: '{{ old('document_type_id') }}',
        formAmount: '{{ old('amount') }}',
        ingresoId: '{{ $ingresoDocId }}',
        refIngresoId: '{{ $ingresoDocId }}',
        refEgresoId: '{{ $egresoDocId }}',
        listIngresos: {{ Js::from($conceptsIngreso) }},
        listEgresos: {{ Js::from($conceptsEgreso) }},
        currentConcepts: []
    }" {{-- LÃ“GICA DEL EVENTO --}}
        @open-movement-modal.window="
        let conceptText = $event.detail.concept || ''; 
        let receivedId = String($event.detail.docId);
        
        // Resetear formulario
        formConcept = conceptText;
        formAmount = ''; 
        formConceptId = ''; 
        formDocId = receivedId;

        // Filtrar listas segÃºn si es Ingreso o Egreso
        if (receivedId === refIngresoId) {
            
            if (conceptText === 'Apertura de caja') {
                // Caso: APERTURA -> Solo mostramos conceptos que digan 'apertura'
                currentConcepts = listIngresos.filter(c => c.description.toLowerCase().includes('apertura'));
                // Auto-seleccionar el primero si existe
                if (currentConcepts.length > 0) formConceptId = currentConcepts[0].id;
            } else {
                // Caso: INGRESO NORMAL -> Ocultamos lo que diga 'apertura'
                currentConcepts = listIngresos.filter(c => !c.description.toLowerCase().includes('apertura'));
            }
        }
        
        else {
            
            if (conceptText === 'Cierre de caja') {
                // Caso: CIERRE -> Solo mostramos conceptos que digan 'cierre'
                currentConcepts = listEgresos.filter(c => c.description.toLowerCase().includes('cierre'));
                // Auto-seleccionar el primero si existe
                if (currentConcepts.length > 0) formConceptId = currentConcepts[0].id;
            } else {
                // Caso: EGRESO NORMAL -> Ocultamos lo que diga 'cierre'
                currentConcepts = listEgresos.filter(c => !c.description.toLowerCase().includes('cierre'));
            }
        }

        // Abrir el modal
        open = true; 
    ">

        <x-common.page-breadcrumb pageTitle="Movimientos de Caja" />

        <x-common.component-card title="GestiÃ³n de Movimientos" desc="Control de ingresos, egresos y traslados de fondos.">

            <div class="flex flex-col gap-5">
                {{-- FILTROS (misma estructura que kardex: grid en 2 filas) --}}
                <form method="GET" action="{{ route('admin.petty-cash.index', ['cash_register_id' => $selectedBoxId]) }}" class="mb-6 space-y-5">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    @php
                        $pettyShiftOptions = collect([['value' => 'all', 'label' => 'Todos']]);
                        foreach ($shiftRelations ?? [] as $rel) {
                            $boxNum = $rel->cashMovementStart->cashRegister->number ?? '';
                            $shiftName = $rel->cashMovementStart->shift->name ?? 'Turno';
                            $started = $rel->started_at ? \Carbon\Carbon::parse($rel->started_at)->format('Y-m-d H:i:s') : '';
                            $ended = $rel->ended_at ? \Carbon\Carbon::parse($rel->ended_at)->format('Y-m-d H:i:s') : '';
                            $label = $boxNum ? "{$shiftName} | {$started}" : "{$shiftName} | {$started}";
                            if ($rel->status === '1') {
                                $label .= ' (En curso)';
                            } elseif ($ended) {
                                $label .= " - {$ended}";
                            }
                            $pettyShiftOptions->push(['value' => $rel->id, 'label' => $label]);
                        }
                        $conceptOptions = collect([['value' => '', 'label' => 'Todos']])->merge(
                            collect($paymentConceptsForFilter ?? [])->map(fn($c) => ['value' => (string)$c->id, 'label' => $c->description])
                        )->values()->all();
                    @endphp
                    {{-- Fila 1: Por página, Buscar (crece), Caja, Turno (crece) --}}
                    <div class="flex flex-wrap items-end gap-3 xl:flex-nowrap">
                        <div class="w-full sm:w-[140px] sm:flex-shrink-0">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Por página</label>
                            <x-form.select-autocomplete
                                name="per_page"
                                :value="$perPage ?? 10"
                                :options="collect([10, 20, 50, 100])->map(fn($n) => ['value' => $n, 'label' => $n . ' / página'])->values()->all()"
                                placeholder="Por página"
                                :submit-on-change="true"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                        <div class="flex-1 min-w-0 xl:min-w-[180px]">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Buscar</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                    {!! $SearchIcon !!}
                                </span>
                                <input type="text" name="search" value="{{ request('search') }}"
                                    placeholder="Buscar movimiento..."
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                        </div>
                        <div class="w-full sm:w-[120px] sm:flex-shrink-0" x-data="{
                            init() {
                                this.$nextTick(() => {
                                    const sel = this.$el.querySelector('select[name=cash_register_id]');
                                    if (sel) sel.addEventListener('change', () => {
                                        const base = '{{ url('/caja/caja-chica') }}/';
                                        const viewId = '{{ $viewId ?? '' }}';
                                        let u = base + sel.value;
                                        if (viewId) u += '?view_id=' + viewId;
                                        window.location = u;
                                    });
                                });
                            }
                        }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Caja</label>
                            <x-form.select-autocomplete
                                name="cash_register_id"
                                :value="$selectedBoxId"
                                :options="collect($cashRegisters ?? [])->map(fn($r) => ['value' => $r->id, 'label' => $r->number])->values()->all()"
                                placeholder="Caja"
                                :submit-on-change="false"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                        <div class="flex-1 min-w-0 xl:min-w-[180px]">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Turno</label>
                            <x-form.select-autocomplete
                                name="shift_relation_id"
                                :value="$selectedShiftId"
                                :options="$pettyShiftOptions->values()->all()"
                                placeholder="Todos"
                                :submit-on-change="true"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    </div>
                    {{-- Fila 2: Tipo, Concepto (crece), Desde, Hasta, Botones --}}
                    <div class="flex flex-wrap items-end gap-3 xl:flex-nowrap">
                        <div class="w-full sm:w-[140px] sm:flex-shrink-0">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                            <x-form.select-autocomplete
                                name="tipo_movimiento"
                                :value="$selectedTipoMovimiento ?? 'all'"
                                :options="[['value' => 'all', 'label' => 'Todos'], ['value' => 'ingreso', 'label' => 'Ingreso'], ['value' => 'egreso', 'label' => 'Egreso']]"
                                placeholder="Todos"
                                :submit-on-change="true"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                        <div class="w-full sm:w-[200px] sm:flex-shrink-0">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Concepto</label>
                            <x-form.select-autocomplete
                                name="payment_concept_id"
                                :value="$selectedPaymentConceptId ?? ''"
                                :options="$conceptOptions"
                                placeholder="Todos"
                                :submit-on-change="true"
                                inputClass="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                        <div class="flex-1 min-w-0 xl:min-w-[150px]">
                            <x-form.date-picker
                                id="petty-date-from"
                                name="date_from"
                                label="Desde"
                                :defaultDate="$dateFrom ?? null"
                                dateFormat="Y-m-d"
                                placeholder="dd/mm/yyyy"
                            />
                        </div>
                        <div class="flex-1 min-w-0 xl:min-w-[150px]">
                            <x-form.date-picker
                                id="petty-date-to"
                                name="date_to"
                                label="Hasta"
                                :defaultDate="$dateTo ?? null"
                                dateFormat="Y-m-d"
                                placeholder="dd/mm/yyyy"
                            />
                        </div>
                        <div class="flex flex-shrink-0 items-end gap-2">
                            <x-ui.button type="submit" size="md" variant="primary" class="h-11 px-5" style="background-color: #334155; border-color: #334155;">
                                <i class="ri-search-line text-gray-100"></i>
                                <span class="font-medium text-gray-100">Buscar</span>
                            </x-ui.button>
                            <x-ui.link-button size="md" variant="outline"
                                href="{{ route('admin.petty-cash.index', array_merge(['cash_register_id' => $selectedBoxId], $viewId ? ['view_id' => $viewId] : [])) }}"
                                class="h-11 px-5 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                                <i class="ri-refresh-line"></i>
                                <span class="font-medium">Limpiar</span>
                            </x-ui.link-button>
                        </div>
                    </div>
                </form>

                {{-- BOTONERA --}}
                <div class="flex flex-wrap gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @if ($topOperations->isNotEmpty())
                        @php $renderedTopOperation = false; @endphp
                        @foreach ($topOperations as $operation)
                            @php
                                $topTextColor = $resolveTextColor($operation);
                                $topColor = $operation->color ?: '#3B82F6';
                                $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                $topAction = $operation->action ?? '';
                                $topActionUrl = $resolveActionUrl($topAction, null, $operation);
                                $topActionLower = mb_strtolower($topAction);
                                $topNameLower = mb_strtolower($operation->name ?? '');

                                $isCreateLike = str_contains($topAction, 'create') || str_contains($topAction, 'store');
                                $isIncomeOp = str_contains($topActionLower, 'ingreso') || str_contains($topNameLower, 'ingreso');
                                $isExpenseOp = str_contains($topActionLower, 'egreso') || str_contains($topNameLower, 'egreso');
                                $isOpenOp = str_contains($topActionLower, 'apertura') || str_contains($topNameLower, 'apertura');
                                $isCloseOp = str_contains($topActionLower, 'cierre')
                                    || str_contains($topNameLower, 'cierre')
                                    || str_contains($topActionLower, 'cerrar')
                                    || str_contains($topNameLower, 'cerrar')
                                    || str_contains($topActionLower, 'close')
                                    || str_contains($topNameLower, 'close');
                                if ($isCloseOp && empty($operation->color)) {
                                    $topStyle = 'background-color: #FACC15; color: #111827;';
                                }
                                $targetViewId = !empty($operation->view_id_action) ? $operation->view_id_action : $viewId;
                                $closeActionUrl = $buildCloseUrl($targetViewId);

                                // En caja chica ingreso/egreso/apertura quedan en modal; cierre abre una vista dedicada.
                                $isPettyCashModalOp = $isCreateLike || $isIncomeOp || $isExpenseOp || $isOpenOp;
                                $modalDocId = ($isExpenseOp || $isCloseOp) ? $egresoDocId : $ingresoDocId;
                                $modalConcept = $isOpenOp ? 'Apertura de caja' : ($isCloseOp ? 'Cierre de caja' : '');
                            @endphp
                            @if ((!$hasOpening && !$isOpenOp) || ($hasOpening && $isOpenOp))
                                @continue
                            @endif
                            @php $renderedTopOperation = true; @endphp
                            @if ($isCloseOp)
                                <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $closeActionUrl }}">
                                    <i class="{{ $operation->icon }}"></i>
                                    <span>{{ $operation->name }}</span>
                                </x-ui.link-button>
                            @elseif ($isPettyCashModalOp)
                                <x-ui.button size="md" variant="primary" type="button" style="{{ $topStyle }}"
                                    @click="$dispatch('open-movement-modal', { concept: '{{ $modalConcept }}', docId: '{{ $modalDocId }}' })">
                                    <i class="{{ $operation->icon }}"></i>
                                    <span>{{ $operation->name }}</span>
                                </x-ui.button>
                            @else
                                <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                    <i class="{{ $operation->icon }}"></i>
                                    <span>{{ $operation->name }}</span>
                                </x-ui.link-button>
                            @endif
                        @endforeach
                        @if (!$renderedTopOperation)
                            @if (!$hasOpening)
                                <x-ui.button size="md" variant="primary" style="background-color: #3B82F6; color: #FFFFFF;"
                                    @click="$dispatch('open-movement-modal', { concept: 'Apertura de caja', docId: '{{ $ingresoDocId }}' })">
                                    <i class="ri-key-2-line"></i><span>Aperturar Caja</span>
                                </x-ui.button>
                            @else
                                <x-ui.button size="md" variant="primary" style="background-color: #00A389; color: #FFFFFF;"
                                    @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $ingresoDocId }}' })">
                                    <i class="ri-add-line"></i><span>Ingreso</span>
                                </x-ui.button>

                                <x-ui.button size="md" variant="primary"
                                    style="background-color: #EF4444; color: #FFFFFF; border: none;"
                                    @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $egresoDocId }}' })">
                                    <i class="ri-subtract-line mr-1"></i><span>Egreso</span>
                                </x-ui.button>

                                <x-ui.link-button size="md" style="background-color: #FACC15; color: #111827;"
                                    href="{{ $buildCloseUrl($viewId) }}">
                                    <i class="ri-lock-2-line"></i> Cerrar
                                </x-ui.link-button>
                            @endif
                        @endif
                    @else
                        @if (!$hasOpening)
                            <x-ui.button size="md" variant="primary" style="background-color: #3B82F6; color: #FFFFFF;"
                                @click="$dispatch('open-movement-modal', { concept: 'Apertura de caja', docId: '{{ $ingresoDocId }}' })">
                                <i class="ri-key-2-line"></i><span>Aperturar Caja</span>
                            </x-ui.button>
                        @else
                            <x-ui.button size="md" variant="primary" style="background-color: #00A389; color: #FFFFFF;"
                                @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $ingresoDocId }}' })">
                                <i class="ri-add-line"></i><span>Ingreso</span>
                            </x-ui.button>

                            <x-ui.button size="md" variant="primary"
                                style="background-color: #EF4444; color: #FFFFFF; border: none;"
                                @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $egresoDocId }}' })">
                                <i class="ri-subtract-line mr-1"></i><span>Egreso</span>
                            </x-ui.button>

                            <x-ui.link-button size="md" style="background-color: #FACC15; color: #111827;"
                                href="{{ $buildCloseUrl($viewId) }}">
                                <i class="ri-lock-2-line"></i> Cerrar
                            </x-ui.link-button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Efectivo en caja</span>
                    <x-ui.badge size="sm" variant="light" color="success">S/ {{ number_format((float) ($cashEfectivoTotal ?? 0), 2) }}</x-ui.badge>
                </div>
            </div>

            {{-- TABLA --}}
            <div
                class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <table class="w-full">
                        <thead style="background-color: #334155; color: #FFFFFF;">
                            <tr class="text-white" >
                                <th class="w-12 px-4 py-4 text-center first:rounded-tl-xl">
                                    <p class="font-medium text-theme-xs dark:text-white">Orden</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Numero</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Tipo</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Concepto</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Fecha</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Métodos de pago</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="hidden px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Usuario</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="hidden px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Caja</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="hidden px-3 py-3 text-left">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Turno</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-center">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Situación</p>
                                </th>
                                <th style="background-color: #334155; color: #FFFFFF;" class="px-3 py-3 text-center last:rounded-tr-xl">
                                    <p class="font-semibold text-white text-theme-xs uppercase">Operaciones</p>
                                </th>
                            </tr>
                        </thead>
                            @forelse ($movements as $movement)
                                @php
                                    $docName = $movement->documentType?->name ?? 'General';
                                    $conceptName = $movement->cashMovement?->paymentConcept?->description ?? '-';
                                    $isIngreso = stripos($docName, 'ingreso') !== false;
                                    $movementStatus = (string) ($movement->status ?? '1');
                                    $isActive = in_array($movementStatus, ['1', 'A'], true);
                                    $paymentSummary = collect($movement->cashMovement?->details ?? [])
                                        ->groupBy('payment_method')
                                        ->map(fn($items, $method) => trim(($method ?: 'Metodo') . ': ' . number_format($items->sum('amount'), 2)))
                                        ->values()
                                        ->implode(' | ');
                                @endphp
                                <tbody>
                                 <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <td class="px-4 py-4 text-center">
                                        <button type="button" @click="openRow === {{ $movement->id }} ? openRow = null : openRow = {{ $movement->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 dark:bg-brand-500 dark:text-white">
                                            <i class="ri-add-line" x-show="openRow !== {{ $movement->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $movement->id }}"></i>
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap align-middle">
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">{{ $movement->number }}</p>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap align-middle">
                                        <x-ui.badge variant="light" color="{{ $isIngreso ? 'success' : 'error' }}">{{ $isIngreso ? 'Ingreso' : 'Egreso' }}</x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 align-middle">
                                        <x-ui.badge variant="light" color="warning" class="text-[10px]">{{ $conceptName }}</x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap align-middle">
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">S/ {{ number_format($movement->cashMovement?->total ?? 0, 2) }}</p>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap align-middle">
                                         <div>
                                             <p class="text-gray-800 text-[11px] font-medium dark:text-white/90">{{ $movement->moved_at ? $movement->moved_at->format('j/m/Y') : '-' }}</p>
                                             <p class="text-gray-500 text-[10px] dark:text-gray-400">{{ $movement->moved_at ? $movement->moved_at->format('h:i:s A') : '' }}</p>
                                         </div>
                                    </td>
                                    <td class="px-3 py-3 align-middle">
                                        <p class="max-w-[280px] truncate text-[11px] font-medium text-gray-700 dark:text-gray-200" title="{{ $paymentSummary ?: '-' }}">{{ $paymentSummary ?: '-' }}</p>
                                    </td>
                                    <td class="hidden px-3 py-3 align-middle">
                                        <p class="text-gray-800 text-[11px] font-bold dark:text-white/90">{{ $movement->user_name ?: '-' }}</p>
                                    </td>
                                    <td class="hidden px-3 py-3 align-middle">
                                        <p class="text-gray-500 text-[10px] dark:text-gray-400 capitalize">{{ $movement->cashMovement?->cash_register ?: '-' }}</p>
                                    </td>
                                    <td class="hidden px-3 py-3 align-middle">
                                        <p class="text-gray-500 text-[10px] dark:text-gray-400 capitalize">{{ $movement->cashMovement?->shift?->name ?: '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        <x-ui.badge variant="light" color="{{ $isActive ? 'success' : 'error' }}">{{ $isActive ? 'Activado' : 'Desactivado' }}</x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            @if ($rowOperations->isNotEmpty())
                                                @foreach ($rowOperations as $operation)
                                                    @php
                                                        $action = $operation->action ?? '';
                                                        $isDelete = str_contains($action, 'destroy');
                                                        $actionUrl = $resolveActionUrl($action, $movement, $operation);
                                                        $textColor = $resolveTextColor($operation);
                                                        $buttonColor = $operation->color ?: '#3B82F6';
                                                        $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                        $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                    @endphp
                                                    @if ($isDelete)
                                                        <form method="POST" action="{{ $actionUrl }}" class="relative group js-swal-delete" data-swal-title="Eliminar movimiento?" data-swal-text="Se eliminara {{ $movement->number }}. Esta accion no se puede deshacer." data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar" data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                            @csrf
                                                            @method('DELETE')
                                                            @if ($viewId)
                                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                            @endif
                                                            <x-ui.button size="icon" variant="{{ $variant }}" type="submit" className="rounded-xl" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.button>
                                                            <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50 shadow-xl">
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
                                            @endif
                                        </div>
                                    </td>
                                <tr x-show="openRow === {{ $movement->id }}" x-cloak class="border-b border-gray-100 bg-gray-50/70 dark:bg-gray-800/40 dark:border-gray-800 transition-all duration-300">
                                    <td colspan="13" class="px-6 py-4">
                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 mb-2 max-w-6xl">
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Persona</p>
                                                <p class="mt-0.5 truncate text-sm font-semibold text-gray-700 dark:text-gray-200" title="{{ $movement->person_name ?: '-' }}">{{ $movement->person_name ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Responsable</p>
                                                <p class="mt-0.5 truncate text-sm font-semibold text-gray-700 dark:text-gray-200" title="{{ $movement->responsible_name ?: '-' }}">{{ $movement->responsible_name ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Usuario</p>
                                                <p class="mt-0.5 truncate text-sm font-semibold text-gray-700 dark:text-gray-200" title="{{ $movement->user_name ?: '-' }}">{{ $movement->user_name ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Moneda</p>
                                                <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $movement->cashMovement?->currency ?: 'PEN' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 lg:col-span-2">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Origen</p>
                                                <p class="mt-0.5 truncate text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                    @php
                                                        $originMovement = $movement->movement;
                                                        $originType = $originMovement?->movementType?->description ?? '-';
                                                        $originDocPrefix = $originMovement?->documentType?->name
                                                            ? strtoupper(substr($originMovement->documentType->name, 0, 1))
                                                            : '-';
                                                        $originSeries = $originMovement?->salesMovement?->series;
                                                    @endphp
                                                    {{ $originType }} - {{ $originDocPrefix }}{{ $originSeries ? $originSeries.'-' : '' }}{{ $movement->number }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">T. Cambio</p>
                                                <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ number_format((float) ($movement->cashMovement?->exchange_rate ?? 1), 3) }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Caja</p>
                                                <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $movement->cashMovement?->cash_register ?: '-' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Turno</p>
                                                <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $movement->cashMovement?->shift?->name ?: '-' }}</p>
                                            </div>

                                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/50 lg:col-span-6">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Comentario</p>
                                                <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $movement->comment ?: '-' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="13" class="px-6 py-12">
                                            <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                                <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                                    <i class="ri-inbox-2-line"></i>
                                                </div>
                                                <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay movimientos registrados.</p>
                                                <p class="text-gray-500">Crea un ingreso o egreso para comenzar.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            @endforelse
                </table>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->total() }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $movements->links('vendor.pagination.forced') }}
                </div>
            </div>

        </x-common.component-card>

        <x-ui.modal x-data="{}" x-show="open" x-cloak class="max-w-2xl z-[9999]" :showCloseButton="true">
            <div class="p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-gray-400">
                            <span x-text="formDocId == ingresoId ? 'OperaciÃ³n de Ingreso' : 'OperaciÃ³n de Egreso'"></span>
                        </p>
                        <h3 class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90"
                            x-text="formDocId == ingresoId ? 'Registrar Ingreso' : 'Registrar Egreso'">
                        </h3>
                    </div>
                </div>

                <form method="POST"
                    action="{{ route('admin.petty-cash.store', ['cash_register_id' => $selectedBoxId]) }}"
                    class="space-y-4">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="hidden" name="document_type_id" x-model="formDocId">

                    @include('petty_cash._form', ['movement' => null])

                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-ui.button type="submit" size="md" variant="primary" class="flex-1 sm:flex-none">
                            <i class="ri-save-line"></i><span>Guardar</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false" class="flex-1 sm:flex-none">
                            <i class="ri-close-line"></i><span>Cancelar</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>

    </div>
@endsection



