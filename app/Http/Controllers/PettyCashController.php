<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Movement;
use Illuminate\Http\Request;
use App\Models\DocumentType;
use App\Models\CashRegister;
use App\Models\PaymentConcept;
use App\Models\CashMovements;
use App\Models\Shift;
use App\Models\CashShiftRelation;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Bank;
use App\Models\DigitalWallet;
use App\Models\Card;
use App\Models\CashMovementDetail;
use App\Models\Operation;
use Carbon\Carbon;


class PettyCashController extends Controller
{

    public function redirectBase(Request $request)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $firstBox = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->orderBy('number', 'asc')
            ->first();
        if ($firstBox) {
            $params = ['cash_register_id' => $firstBox->id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('admin.petty-cash.index', $params);
        }

        $redirectParams = [];
        if ($request->filled('view_id')) {
            $redirectParams['view_id'] = $request->input('view_id');
        }

        return redirect()
            ->route('boxes.index', $redirectParams)
            ->with('error', 'No hay cajas registradas para la sucursal activa.');
    }

    public function index(Request $request, $cash_register_id = null)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();
        if ($viewId && $branchId && $profileId) {
            $operaciones = Operation::query()
                ->select('operations.*')
                ->join('branch_operation', function ($join) use ($branchId) {
                    $join->on('branch_operation.operation_id', '=', 'operations.id')
                        ->where('branch_operation.branch_id', $branchId)
                        ->where('branch_operation.status', 1)
                        ->whereNull('branch_operation.deleted_at');
                })
                ->join('operation_profile_branch', function ($join) use ($branchId, $profileId) {
                    $join->on('operation_profile_branch.operation_id', '=', 'operations.id')
                        ->where('operation_profile_branch.branch_id', $branchId)
                        ->where('operation_profile_branch.profile_id', $profileId)
                        ->where('operation_profile_branch.status', 1)
                        ->whereNull('operation_profile_branch.deleted_at');
                })
                ->where('operations.status', 1)
                ->where('operations.view_id', $viewId)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.id')
                ->distinct()
                ->get();
        }
   // dd($viewId, $branchId, $profileId, $operaciones);
        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->orderBy('number', 'asc')
            ->get();
        $selectedBoxId = $cash_register_id;

        if ($selectedBoxId && !$cashRegisters->contains('id', (int) $selectedBoxId)) {
            $selectedBoxId = null;
        }

        if (empty($selectedBoxId) && $cashRegisters->isNotEmpty()) {
            $selectedBoxId = $cashRegisters->first()->id;
        }

        // Turnos por caja: relaciones de turno para la caja seleccionada
        $shiftRelations = collect();
        $currentShiftRelationId = null;
        if ($selectedBoxId) {
            $shiftRelations = CashShiftRelation::query()
                ->with([
                    'cashMovementStart:id,cash_register_id,shift_id,movement_id',
                    'cashMovementStart.shift:id,name',
                    'cashMovementStart.cashRegister:id,number',
                ])
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', function ($query) use ($selectedBoxId) {
                    $query->where('cash_register_id', $selectedBoxId);
                })
                ->orderByDesc('started_at')
                ->get();
            $currentRelation = $shiftRelations->firstWhere('status', '1');
            $currentShiftRelationId = $currentRelation ? (int) $currentRelation->id : null;
        }
        $selectedShiftId = $request->input('shift_relation_id');
        if ($selectedShiftId !== null && $selectedShiftId !== '') {
            $selectedShiftId = $selectedShiftId === 'all' ? 'all' : (int) $selectedShiftId;
        } else {
            $selectedShiftId = $currentShiftRelationId !== null ? $currentShiftRelationId : 'all';
        }

        $selectedTipoMovimiento = $request->input('tipo_movimiento', 'all');
        if (!in_array($selectedTipoMovimiento, ['all', 'ingreso', 'egreso'], true)) {
            $selectedTipoMovimiento = 'all';
        }

        $selectedPaymentConceptId = $request->input('payment_concept_id');
        if ($selectedPaymentConceptId !== null && $selectedPaymentConceptId !== '' && $selectedPaymentConceptId !== 'all') {
            $selectedPaymentConceptId = (int) $selectedPaymentConceptId;
        } else {
            $selectedPaymentConceptId = null;
        }
        $paymentConceptsForFilter = PaymentConcept::query()
            ->orderBy('type')
            ->orderBy('description')
            ->get(['id', 'description', 'type']);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $dateFromCarbon = null;
        $dateToCarbon = null;
        if ($dateFrom) {
            try {
                $dateFromCarbon = Carbon::parse($dateFrom)->startOfDay();
            } catch (\Exception $e) {
                $dateFrom = null;
            }
        }
        if ($dateTo) {
            try {
                $dateToCarbon = Carbon::parse($dateTo)->endOfDay();
            } catch (\Exception $e) {
                $dateTo = null;
            }
        }

        // --- LÓGICA DE ESTADO DE CAJA (MODIFICADO) ---
        $hasOpening = CashShiftRelation::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereHas('cashMovementStart', function ($query) use ($branchId, $selectedBoxId) {
                $query->where('branch_id', $branchId)
                    ->when($selectedBoxId, fn($cashQuery) => $cashQuery->where('cash_register_id', $selectedBoxId))
                    ->whereHas('cashRegister', function ($cashRegisterQuery) use ($branchId) {
                        $cashRegisterQuery->where('branch_id', $branchId);
                    });
            })
            ->exists();

        $documentTypes = DocumentType::where('movement_type_id', 4)->get();

        $docIngreso = $documentTypes->firstWhere('name', 'Ingreso');
        $ingresoDocId = $docIngreso ? $docIngreso->id : '';
        $docEgreso = $documentTypes->firstWhere('name', 'Egreso');
        $egresoDocId = $docEgreso ? $docEgreso->id : '';

        $conceptsIngreso = PaymentConcept::where('type', 'I')
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Apertura%');
            })
            ->get();

        $conceptsEgreso = PaymentConcept::where('type', 'E')
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Cierre%');
            })
            ->get();

        $selectedShiftRelation = null;
        if (is_int($selectedShiftId) && $selectedShiftId > 0) {
            $selectedShiftRelation = CashShiftRelation::query()
                ->where('id', $selectedShiftId)
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', fn ($q) => $q->where('cash_register_id', $selectedBoxId))
                ->first();
        }

        $movements = Movement::query()
            ->with([
                'documentType',
                'movementType',
                'cashMovement',
                'cashMovement.details',
                'cashMovement.paymentConcept',
                'cashMovement.shift',
            ])
            ->whereHas('cashMovement', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            })
            ->when($selectedShiftRelation, function ($query) use ($selectedShiftRelation) {
                $query->where('moved_at', '>=', $selectedShiftRelation->started_at);
                if ($selectedShiftRelation->ended_at !== null) {
                    $query->where('moved_at', '<=', $selectedShiftRelation->ended_at);
                }
            })
            ->when($search, function ($query, $search) {
                $needle = mb_strtolower((string) $search, 'UTF-8');
                $query->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(COALESCE(person_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(user_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(responsible_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->when($selectedTipoMovimiento === 'ingreso' && $ingresoDocId !== '', fn ($query) => $query->where('document_type_id', $ingresoDocId))
            ->when($selectedTipoMovimiento === 'egreso' && $egresoDocId !== '', fn ($query) => $query->where('document_type_id', $egresoDocId))
            ->when($selectedPaymentConceptId !== null, function ($query) use ($selectedPaymentConceptId) {
                $query->whereHas('cashMovement', fn ($q) => $q->where('payment_concept_id', $selectedPaymentConceptId));
            })
            ->when($dateFromCarbon !== null, fn ($query) => $query->where('moved_at', '>=', $dateFromCarbon))
            ->when($dateToCarbon !== null, fn ($query) => $query->where('moved_at', '<=', $dateToCarbon))
            ->orderBy('moved_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $shifts = Shift::where('branch_id', session('branch_id'))->get();

        $paymentMethods = PaymentMethod::where('status', true)->orderBy('order_num', 'asc')->get();

        $banks = Bank::where('status', true)->orderBy('order_num', 'asc')->get();

        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();

        $digitalWallets = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();

        $cards = Card::where('status', true)->orderBy('order_num', 'asc')->get();

        $cashEfectivoTotal = (float) DB::table('cash_movement_details as cmd')
            ->join('cash_movements as cm', 'cm.id', '=', 'cmd.cash_movement_id')
            ->join('movements as m', 'm.id', '=', 'cm.movement_id')
            ->leftJoin('document_types as dt', 'dt.id', '=', 'm.document_type_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'cmd.payment_method_id')
            ->where('cm.cash_register_id', $selectedBoxId)
            ->where('m.branch_id', $branchId)
            ->whereNull('m.deleted_at')
            ->where(function ($query) {
                $query->whereRaw("LOWER(COALESCE(pm.description, cmd.payment_method, '')) LIKE '%efectivo%'")
                    ->orWhereRaw("LOWER(COALESCE(pm.description, cmd.payment_method, '')) LIKE '%cash%'");
            })
            ->selectRaw("
                COALESCE(
                    SUM(
                        CASE
                            WHEN LOWER(COALESCE(dt.name, '')) LIKE '%egreso%' THEN -cmd.amount
                            ELSE cmd.amount
                        END
                    ),
                    0
                ) as total
            ")
            ->value('total');

        return view('petty_cash.index', [
            'title'           => 'Caja Chica',
            'movements'       => $movements,
            'documentTypes'   => $documentTypes,
            'hasOpening'      => $hasOpening,
            'ingresoDocId'    => $ingresoDocId,
            'egresoDocId'     => $egresoDocId,
            'cashRegisters'   => $cashRegisters,
            'conceptsIngreso' => $conceptsIngreso,
            'conceptsEgreso'  => $conceptsEgreso,
            'selectedBoxId'   => $selectedBoxId,
            'shifts'          => $shifts,
            'shiftRelations'   => $shiftRelations,
            'selectedShiftId' => $selectedShiftId,
            'currentShiftRelationId' => $currentShiftRelationId,
            'selectedTipoMovimiento' => $selectedTipoMovimiento,
            'paymentConceptsForFilter' => $paymentConceptsForFilter,
            'selectedPaymentConceptId' => $selectedPaymentConceptId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,

            'paymentMethods'  => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'banks'           => $banks,
            'digitalWallets'  => $digitalWallets,
            'cards'           => $cards,
            'operaciones'     => $operaciones,
            'perPage'         => $perPage,
            'cashEfectivoTotal' => $cashEfectivoTotal,
        ]);
    }

    public function show(Request $request, $cash_register_id, $movement_id)
    {
        $movement = Movement::with([
            'documentType',
            'cashMovement.details',
            'cashMovement.shift',
            'cashMovement.paymentConcept',
        ])->findOrFail($movement_id);
        $viewId = $request->input('view_id');
        return view('petty_cash.show', compact('cash_register_id', 'movement', 'viewId'));
    }

    public function closePage(Request $request, $cash_register_id)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $cashRegister = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->findOrFail($cash_register_id);

        $activeRelation = $this->findActiveCashRelation((int) $cashRegister->id, $branchId);
        if (!$activeRelation) {
            $params = ['cash_register_id' => $cashRegister->id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()
                ->route('admin.petty-cash.index', $params)
                ->with('error', 'La caja seleccionada no tiene una apertura activa para cerrar.');
        }

        $closeSummary = $this->buildClosePageData($activeRelation, $cashRegister, $branchId);
        $viewId = $request->input('view_id');

        return view('petty_cash.close', array_merge($closeSummary, [
            'title' => 'Caja chica | Cerrar caja',
            'cashRegister' => $cashRegister,
            'activeRelation' => $activeRelation,
            'viewId' => $viewId,
            'cashRegisterId' => (int) $cashRegister->id,
            'shiftId' => (int) ($activeRelation->cashMovementStart?->shift_id ?? 0),
            'shiftName' => (string) ($activeRelation->cashMovementStart?->shift?->name ?? 'Sin turno'),
            'personLabel' => '0 - CLIENTES VARIOS',
            'responsibleLabel' => (string) (session('user_name') ?: session('person_fullname') ?: 'CAJERO'),
            'movementComment' => (string) old('comment', 'Cierre de caja'),
            'coins' => $this->buildDenominationRows([
                ['key' => 'coin_010', 'label' => '10 centimos', 'value' => 0.10],
                ['key' => 'coin_020', 'label' => '20 centimos', 'value' => 0.20],
                ['key' => 'coin_050', 'label' => '50 centimos', 'value' => 0.50],
                ['key' => 'coin_1', 'label' => '1 sol', 'value' => 1],
                ['key' => 'coin_2', 'label' => '2 soles', 'value' => 2],
                ['key' => 'coin_5', 'label' => '5 soles', 'value' => 5],
            ], (array) old('counting.coins', [])),
            'bills' => $this->buildDenominationRows([
                ['key' => 'bill_10', 'label' => '10 soles', 'value' => 10],
                ['key' => 'bill_20', 'label' => '20 soles', 'value' => 20],
                ['key' => 'bill_50', 'label' => '50 soles', 'value' => 50],
                ['key' => 'bill_100', 'label' => '100 soles', 'value' => 100],
                ['key' => 'bill_200', 'label' => '200 soles', 'value' => 200],
            ], (array) old('counting.bills', [])),
        ]));
    }

    public function closeStore(Request $request, $cash_register_id)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $cashRegister = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->findOrFail($cash_register_id);

        $activeRelation = $this->findActiveCashRelation((int) $cashRegister->id, $branchId);
        if (!$activeRelation) {
            $params = ['cash_register_id' => $cashRegister->id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()
                ->route('admin.petty-cash.index', $params)
                ->with('error', 'La caja seleccionada no tiene una apertura activa para cerrar.');
        }

        $closingConcept = $this->resolveClosingConcept();
        $egresoDocument = $this->resolveCashDocumentType('egreso');
        $cashMethod = $this->resolveCashPaymentMethod();

        if (!$closingConcept || !$egresoDocument || !$cashMethod) {
            return back()
                ->withErrors([
                    'error' => 'No se pudo preparar el cierre de caja. Verifica el concepto de cierre, el documento de egreso y el metodo de pago Efectivo.',
                ])
                ->withInput();
        }

        $request->merge([
            'cash_register_id' => (int) $cashRegister->id,
            'document_type_id' => (int) $egresoDocument->id,
            'payment_concept_id' => (int) $closingConcept->id,
            'shift_id' => (int) ($activeRelation->cashMovementStart?->shift_id ?? 0),
            'comment' => trim((string) $request->input('comment')) !== '' ? trim((string) $request->input('comment')) : 'Cierre de caja',
            'payments' => [[
                'amount' => (float) $this->resolveCurrentCashAmount((int) $cashRegister->id, $branchId),
                'payment_method_id' => (int) $cashMethod->id,
                'payment_method' => (string) $cashMethod->description,
                'number' => null,
                'card_id' => null,
                'bank_id' => null,
                'digital_wallet_id' => null,
                'payment_gateway_id' => null,
            ]],
        ]);

        return $this->store($request, $cash_register_id);
    }

    public function store(Request $request, $cash_register_id)
    {
        $selectedCashRegisterId = (int) ($request->input('cash_register_id') ?: $cash_register_id);
        $request->merge(['cash_register_id' => $selectedCashRegisterId]);

        $validated = $request->validate([
            'comment'            => 'nullable|string|max:255',
            'cash_register_id'   => 'required|exists:cash_registers,id',
            'document_type_id'   => 'nullable|exists:document_types,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'shift_id'           => 'required|exists:shifts,id',

            'payments'           => 'required|array|min:1',
            'payments.*.amount'  => 'required|numeric|min:0.00',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.number'  => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($request, $validated) {
                $selectedCashRegisterId = (int) $validated['cash_register_id'];
                $selectedShift = Shift::findOrFail($request->shift_id);
                $shiftSnapshotData = [
                    'name'       => $selectedShift->name,
                    'start_time' => $selectedShift->start_time,
                    'end_time'   => $selectedShift->end_time
                ];
                $shiftSnapshotJson = json_encode($shiftSnapshotData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $typeId = 4;
                $lastRecord = Movement::select('movements.*')
                    ->join('cash_movements', 'movements.id', '=', 'cash_movements.movement_id')
                    ->where('movements.movement_type_id', $typeId)
                    ->where('cash_movements.cash_register_id', $selectedCashRegisterId)
                    ->latest('movements.id')
                    ->lockForUpdate()
                    ->first();

                $nextSequence = $lastRecord ? intval($lastRecord->number) + 1 : 1;
                $generatedNumber = str_pad($nextSequence, 8, '0', STR_PAD_LEFT);

                $totalAmount = (float) collect($request->payments)->sum('amount');
                $concept = PaymentConcept::findOrFail((int) $validated['payment_concept_id']);
                $conceptName = mb_strtolower((string) $concept->description, 'UTF-8');
                $isClosingConcept = str_contains($conceptName, 'cierre');
                $countingSnapshot = $isClosingConcept ? $this->buildCountingSnapshot($request) : null;
                $cashMethod = $isClosingConcept ? $this->resolveCashPaymentMethod() : null;

                // En cierre se registra automaticamente el efectivo real disponible en caja.
                if ($isClosingConcept) {
                    if (!$cashMethod) {
                        throw new \RuntimeException('No se encontro el metodo de pago Efectivo para registrar el cierre de caja.');
                    }
                    $totalAmount = (float) $this->resolveCurrentCashAmount($selectedCashRegisterId, (int) session('branch_id'));
                }

                $baseComment = $validated['comment'] ?? null;

                $movement = Movement::create([
                    'number'             => $generatedNumber,
                    'moved_at'           => now(),
                    'user_id'            => session('user_id'),
                    'user_name'          => session('user_name'),
                    'person_id'          => session('person_id'),
                    'person_name'        => session('person_fullname'),
                    'responsible_id'     => session('user_id'),
                    'responsible_name'   => session('user_name') ?? session('person_fullname'),
                    'comment'            => $baseComment,
                    'status'             => '1',
                    'movement_type_id'   => $typeId,
                    'document_type_id'   => $request->document_type_id,
                    'branch_id'          => session('branch_id'),
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                ]);

                $box = CashRegister::find($selectedCashRegisterId);
                $boxName = $box ? $box->number : 'Caja Desconocida';

                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'currency'           => 'PEN',
                    'exchange_rate'      => 3.71,
                    'total'              => $totalAmount,
                    'cash_register_id'   => $selectedCashRegisterId,
                    'cash_register'      => $boxName,
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                    'counting_snapshot'  => $countingSnapshot,
                    'movement_id'        => $movement->id,
                    'branch_id'          => session('branch_id'),
                ]);

                $payments = $request->payments;
                if ($isClosingConcept) {
                    $payments = [[
                        'amount' => $totalAmount,
                        'payment_method_id' => $cashMethod->id,
                        'payment_method' => $cashMethod->description,
                        'number' => null,
                        'card_id' => null,
                        'bank_id' => null,
                        'digital_wallet_id' => null,
                        'payment_gateway_id' => null,
                    ]];
                }

                foreach ($payments as $paymentData) {
                    $paymentMethodName = (string) ($paymentData['payment_method'] ?? PaymentMethod::find($paymentData['payment_method_id'])?->description ?? 'Desconocido');
                    $isCashPayment = $this->isCashMethod($paymentMethodName);

                    $cardName = !empty($paymentData['card_id'])
                        ? Card::find($paymentData['card_id'])?->description
                        : null;

                    $bankName = !empty($paymentData['bank_id'])
                        ? Bank::find($paymentData['bank_id'])?->description
                        : null;

                    $walletName = !empty($paymentData['digital_wallet_id'])
                        ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description
                        : null;

                    $gatewayName = !empty($paymentData['payment_gateway_id'])
                        ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description
                        : null;

                    $individualComment = (!$isCashPayment && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $baseComment;

                    CashMovementDetail::create([
                        'cash_movement_id'   => $cashMovement->id,
                        'branch_id'          => session('branch_id'),

                        'type'               => 'PAGADO',
                        'status'             => 'A',
                        'paid_at'            => now(),

                        'amount'             => $paymentData['amount'],
                        'payment_method_id'  => $paymentData['payment_method_id'],
                        'payment_method'     => $paymentMethodName,
                        'comment'            => $individualComment,

                        'number'             => $paymentData['number'] ?? null,

                        'card_id'            => $paymentData['card_id'] ?? null,
                        'card'               => $cardName,

                        'bank_id'            => $paymentData['bank_id'] ?? null,
                        'bank'               => $bankName,

                        'digital_wallet_id'  => $paymentData['digital_wallet_id'] ?? null,
                        'digital_wallet'     => $walletName,

                        'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                        'payment_gateway'    => $gatewayName,
                    ]);
                }

                // LÓGICA DE APERTURA / CIERRE
                if (str_contains($conceptName, 'apertura')) {
                    CashShiftRelation::create([
                        'started_at'             => now(),
                        'status'                 => '1',
                        'cash_movement_start_id' => $cashMovement->id,
                        'branch_id'              => session('branch_id'),
                    ]);
                } elseif (str_contains($conceptName, 'cierre')) {
                    $openRelation = CashShiftRelation::where('branch_id', session('branch_id'))
                        ->where('status', '1')
                        ->whereHas('cashMovementStart', function ($query) use ($selectedCashRegisterId) {
                            $query->where('cash_register_id', $selectedCashRegisterId);
                        })
                        ->latest('id')
                        ->first();

                    if ($openRelation) {
                        $openRelation->update([
                            'ended_at'             => now(),
                            'status'               => '0',
                            'cash_movement_end_id' => $cashMovement->id,
                        ]);
                    }
                }
            });

            $params = ['cash_register_id' => $selectedCashRegisterId];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('admin.petty-cash.index', $params)
                ->with('success', 'Movimiento registrado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al guardar: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, $cash_register_id, $id)
    {
        $movement = Movement::with(['cashMovement.details', 'cashMovement'])->findOrFail($id);

        $currentConceptId = $movement->cashMovement->payment_concept_id;
        $currentConcept   = PaymentConcept::find($currentConceptId);
        $desc = $currentConcept ? strtolower($currentConcept->description) : '';
        $isSpecialEvent = str_contains($desc, 'apertura') || str_contains($desc, 'cierre');

        if ($isSpecialEvent) {
            if ($currentConcept->type == 'I') {
                $conceptsIngreso = collect([$currentConcept]);
                $conceptsEgreso  = collect([]);
            } else {
                $conceptsIngreso = collect([]);
                $conceptsEgreso  = collect([$currentConcept]);
            }
        } else {
            $conceptsIngreso = PaymentConcept::where('type', 'I')
                ->where('restricted', false)
                ->get();

            $conceptsEgreso = PaymentConcept::where('type', 'E')
                ->where('restricted', false)
                ->get();

            if ($currentConcept && $currentConcept->restricted && !$isSpecialEvent) {
                if ($currentConcept->type == 'I') {
                    $conceptsIngreso->push($currentConcept);
                } else {
                    $conceptsEgreso->push($currentConcept);
                }
            }
        }

        $shifts          = Shift::all();
        $cards           = Card::where('status', true)->orderBy('order_num', 'asc')->get();
        $banks           = Bank::where('status', true)->orderBy('order_num', 'asc')->get();
        $digitalWallets  = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();
        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();

        $viewId = $request->input('view_id');

        return view('petty_cash.edit', compact(
            'cash_register_id',
            'movement',
            'shifts',
            'conceptsIngreso',
            'conceptsEgreso',
            'cards',
            'banks',
            'digitalWallets',
            'paymentGateways',
            'viewId'
        ));
    }

    public function update(Request $request, $cash_register_id, $id)
    {
        $validated = $request->validate([
            'comment'            => 'nullable|string|max:255',
            'shift_id'           => 'required|exists:shifts,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'payments'           => 'required|array|min:1',
            'payments.*.amount'  => 'required|numeric|min:0.01',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.number'  => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($request, $validated, $id, $cash_register_id) {

                $movement = Movement::findOrFail($id);
                $cashMovement = CashMovements::where('movement_id', $movement->id)->firstOrFail();

                $selectedShift = Shift::findOrFail($request->shift_id);
                $shiftSnapshotJson = json_encode([
                    'name'       => $selectedShift->name,
                    'start_time' => $selectedShift->start_time,
                    'end_time'   => $selectedShift->end_time
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $newTotalAmount = collect($request->payments)->sum('amount');

                $baseComment = $validated['comment'] ?? null;
                $updatedConcept = PaymentConcept::findOrFail((int) $validated['payment_concept_id']);
                $updatedConceptName = mb_strtolower((string) $updatedConcept->description, 'UTF-8');
                $isClosingConcept = str_contains($updatedConceptName, 'cierre');
                $countingSnapshot = $cashMovement->counting_snapshot;
                if ($isClosingConcept && $request->has('counting')) {
                    $countingSnapshot = $this->buildCountingSnapshot($request);
                } elseif (!$isClosingConcept) {
                    $countingSnapshot = null;
                }

                $movement->update([
                    'comment'        => $baseComment,
                    'shift_id'       => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                    'document_type_id' => $request->document_type_id
                ]);

                $cashMovement->update([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'total'              => $newTotalAmount,
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                    'counting_snapshot'  => $countingSnapshot,
                ]);

                CashMovementDetail::where('cash_movement_id', $cashMovement->id)->delete();

                foreach ($request->payments as $paymentData) {
                    $paymentMethodName = (string) ($paymentData['payment_method'] ?? PaymentMethod::find($paymentData['payment_method_id'])?->description ?? 'Desconocido');
                    $isCashPayment = $this->isCashMethod($paymentMethodName);

                    $cardName = !empty($paymentData['card_id']) ? Card::find($paymentData['card_id'])?->description : null;
                    $bankName = !empty($paymentData['bank_id']) ? Bank::find($paymentData['bank_id'])?->description : null;
                    $walletName = !empty($paymentData['digital_wallet_id']) ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description : null;
                    $gatewayName = !empty($paymentData['payment_gateway_id']) ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description : null;

                    $individualComment = (!$isCashPayment && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $baseComment;

                    CashMovementDetail::create([
                        'cash_movement_id'   => $cashMovement->id,
                        'branch_id'          => session('branch_id'),
                        'type'               => 'PAGADO',
                        'status'             => 'A',
                        'paid_at'            => $movement->moved_at,

                        'amount'             => $paymentData['amount'],
                        'payment_method_id'  => $paymentData['payment_method_id'],
                        'payment_method'     => $paymentMethodName,

                        'comment'            => $individualComment,

                        'number'             => $paymentData['number'] ?? null,
                        'card_id'            => $paymentData['card_id'] ?? null,
                        'card'               => $cardName,
                        'bank_id'            => $paymentData['bank_id'] ?? null,
                        'bank'               => $bankName,
                        'digital_wallet_id'  => $paymentData['digital_wallet_id'] ?? null,
                        'digital_wallet'     => $walletName,
                        'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                        'payment_gateway'    => $gatewayName,
                    ]);
                }
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('admin.petty-cash.index', $params)
                ->with('success', 'Movimiento actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, $cash_register_id, $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $movement = Movement::with('cashMovement.details')->findOrFail($id);

                if ($movement->cashMovement) {
                    $movement->cashMovement->details()->delete();
                    $movement->cashMovement()->delete();
                }
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('admin.petty-cash.index', $params)
                ->with('success', 'Movimiento de caja eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }

    private function findActiveCashRelation(int $cashRegisterId, int $branchId): ?CashShiftRelation
    {
        return CashShiftRelation::query()
            ->with([
                'cashMovementStart:id,total,cash_register_id,shift_id,movement_id,branch_id',
                'cashMovementStart.shift:id,name',
                'cashMovementStart.cashRegister:id,number,series',
                'cashMovementStart.movement:id,number,moved_at,person_name,responsible_name,user_name,comment,document_type_id',
            ])
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereHas('cashMovementStart', function ($query) use ($cashRegisterId) {
                $query->where('cash_register_id', $cashRegisterId);
            })
            ->latest('id')
            ->first();
    }

    private function resolveClosingConcept(): ?PaymentConcept
    {
        return PaymentConcept::query()
            ->where('type', 'E')
            ->whereRaw("LOWER(description) LIKE '%cierre%'")
            ->orderBy('id')
            ->first();
    }

    private function resolveCashDocumentType(string $name): ?DocumentType
    {
        return DocumentType::query()
            ->where('movement_type_id', 4)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
            ->first();
    }

    private function resolveCashPaymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereRaw("LOWER(description) LIKE '%efectivo%'")
                    ->orWhereRaw("LOWER(description) LIKE '%cash%'");
            })
            ->orderBy('order_num')
            ->orderBy('id')
            ->first();
    }

    private function buildDenominationRows(array $definitions, array $oldRows): array
    {
        $rows = [];

        foreach ($definitions as $index => $definition) {
            $previous = $oldRows[$index] ?? [];
            $rows[] = [
                'key' => (string) $definition['key'],
                'label' => (string) $definition['label'],
                'value' => (float) $definition['value'],
                'quantity' => (int) ($previous['quantity'] ?? 0),
                'note' => (string) ($previous['note'] ?? ''),
            ];
        }

        return $rows;
    }

    private function buildCountingSnapshot(Request $request): ?array
    {
        $coins = collect((array) $request->input('counting.coins', []))
            ->map(function ($row) {
                $value = (float) ($row['value'] ?? 0);
                $quantity = (int) ($row['quantity'] ?? 0);

                return [
                    'key' => (string) ($row['key'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'value' => $value,
                    'quantity' => $quantity,
                    'note' => trim((string) ($row['note'] ?? '')),
                    'subtotal' => round($value * $quantity, 2),
                ];
            })
            ->values()
            ->all();

        $bills = collect((array) $request->input('counting.bills', []))
            ->map(function ($row) {
                $value = (float) ($row['value'] ?? 0);
                $quantity = (int) ($row['quantity'] ?? 0);

                return [
                    'key' => (string) ($row['key'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'value' => $value,
                    'quantity' => $quantity,
                    'note' => trim((string) ($row['note'] ?? '')),
                    'subtotal' => round($value * $quantity, 2),
                ];
            })
            ->values()
            ->all();

        return [
            'currency' => 'PEN',
            'coins' => $coins,
            'bills' => $bills,
            'real_total' => round(collect($coins)->sum('subtotal') + collect($bills)->sum('subtotal'), 2),
        ];
    }

    private function buildClosePageData(CashShiftRelation $relation, CashRegister $cashRegister, int $branchId): array
    {
        $from = Carbon::parse($relation->started_at);
        $to = $relation->ended_at ? Carbon::parse($relation->ended_at) : now();

        $periodMovements = CashMovements::query()
            ->with([
                'paymentConcept:id,description,type',
                'shift:id,name',
                'movement:id,number,moved_at,user_name,person_name,responsible_name,comment,document_type_id',
                'movement.documentType:id,name',
                'movement.salesMovement:id,movement_id',
                'details:id,cash_movement_id,type,payment_method_id,payment_method,amount,card,digital_wallet,payment_gateway,bank,comment,paid_at',
            ])
            ->where('branch_id', $branchId)
            ->where('cash_register_id', $cashRegister->id)
            ->whereHas('movement', function ($query) use ($from, $to) {
                $query->whereBetween('moved_at', [$from, $to])
                    ->whereNull('deleted_at');
            })
            ->when($relation->cash_movement_end_id, fn($query) => $query->where('id', '!=', $relation->cash_movement_end_id))
            ->orderBy('id')
            ->get();

        $openingCash = 0.0;
        $cashSales = 0.0;
        $otherCashIncome = 0.0;
        $cashExpenses = 0.0;
        $totalSales = 0.0;
        $totalOtherIncome = 0.0;
        $totalExpenses = 0.0;
        $detailGroups = [];
        $categoryOrder = [
            'opening' => 0,
            'sale' => 1,
            'income' => 2,
            'expense' => 3,
            'closing' => 4,
        ];

        foreach ($periodMovements as $cashMovement) {
            $conceptLabel = trim((string) ($cashMovement->paymentConcept?->description ?? 'Sin concepto'));
            $category = $this->classifyCloseMovementCategory($cashMovement);
            $movementTypeLabel = $category === 'expense' ? 'Egreso' : 'Ingreso';

            if ($category === 'income') {
                $totalOtherIncome += (float) $cashMovement->total;
            } elseif ($category === 'expense') {
                $totalExpenses += (float) $cashMovement->total;
            }

            foreach ($cashMovement->details as $detail) {
                $amount = (float) $detail->amount;
                $method = $this->resolveClosePageMethodLabel($detail);
                $suffix = $this->resolveCloseDetailSuffix($detail);
                $detailType = mb_strtoupper((string) ($detail->type ?: 'PAGADO'), 'UTF-8');
                if ($category === 'sale' && $detailType !== 'DEUDA') {
                    $totalSales += $amount;
                }
                $groupKey = implode('|', [$detailType, $category, $conceptLabel, $method, $suffix]);

                if (!isset($detailGroups[$groupKey])) {
                    $detailGroups[$groupKey] = [
                        'type' => $detailType,
                        'type_label' => $this->resolveCloseDetailTypeLabel($detailType),
                        'flow_label' => $movementTypeLabel,
                        'category' => $category,
                        'sort_order' => $categoryOrder[$category] ?? 99,
                        'amount' => 0.0,
                        'method' => $method,
                        'detail' => $suffix,
                        'note' => $conceptLabel,
                        'records' => [],
                    ];
                }

                $detailGroups[$groupKey]['amount'] += $amount;

                $recordKey = (string) $cashMovement->id;
                if (!isset($detailGroups[$groupKey]['records'][$recordKey])) {
                    $detailGroups[$groupKey]['records'][$recordKey] = [
                        'number' => (string) ($cashMovement->movement?->number ?? str_pad((string) $cashMovement->id, 8, '0', STR_PAD_LEFT)),
                        'type_label' => $movementTypeLabel,
                        'payment_type_label' => $this->resolveCloseDetailTypeLabel($detailType),
                        'concept' => $conceptLabel,
                        'movement_total' => (float) $cashMovement->total,
                        'method_total' => 0.0,
                        'moved_at' => optional($cashMovement->movement?->moved_at)->format('Y-m-d h:i:s A') ?? '-',
                        'user_name' => (string) ($cashMovement->movement?->user_name ?? '-'),
                        'cash_register' => (string) ($cashRegister->number ?? '-'),
                        'shift' => (string) ($cashMovement->shift?->name ?? 'Sin turno'),
                        'person_name' => (string) ($cashMovement->movement?->person_name ?: 'CLIENTES VARIOS'),
                        'method' => $method,
                        'suffix' => $suffix,
                        'payment_label' => '',
                    ];
                }

                $detailGroups[$groupKey]['records'][$recordKey]['method_total'] += $amount;

                if ($this->isCashMethod($method)) {
                    if ($category === 'opening') {
                        $openingCash += $amount;
                    } elseif ($category === 'sale') {
                        $cashSales += $amount;
                    } elseif ($category === 'income') {
                        $otherCashIncome += $amount;
                    } elseif ($category === 'expense') {
                        $cashExpenses += $amount;
                    }
                }
            }
        }

        $detailGroups = collect($detailGroups)
            ->map(function ($group) {
                $group['records'] = collect($group['records'])
                    ->map(function ($record) {
                        $record['payment_label'] = $record['method'] . ': S/ ' . number_format((float) $record['method_total'], 2);
                        if ($record['suffix'] !== '') {
                            $record['payment_label'] .= ' (' . $record['suffix'] . ')';
                        }
                        unset($record['method'], $record['suffix']);

                        return $record;
                    })
                    ->values()
                    ->all();

                $group['detail_label'] = $group['detail'] !== ''
                    ? $group['detail']
                    : ($this->isCashMethod($group['method']) ? 'Efectivo' : 'Sin detalles adicionales');
                $group['records_count'] = count($group['records']);
                $group['modal_title'] = 'Caja chica | ' . $group['note'] . ' | ' . $group['method'];
                if ($group['detail'] !== '' && !$this->isCashMethod($group['method'])) {
                    $group['modal_title'] .= ' | ' . $group['detail'];
                }

                return $group;
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['note', 'asc'],
                ['method', 'asc'],
                ['detail_label', 'asc'],
            ])
            ->values()
            ->all();

        $cashInBox = round($openingCash + $cashSales + $otherCashIncome - $cashExpenses, 2);

        return [
            'startedAt' => $from,
            'systemCash' => $cashInBox,
            'openingCash' => round($openingCash, 2),
            'cashSales' => round($cashSales, 2),
            'otherCashIncome' => round($otherCashIncome, 2),
            'cashExpenses' => round($cashExpenses, 2),
            'cashWithoutOpening' => round($cashSales + $otherCashIncome - $cashExpenses, 2),
            'totalSales' => round($totalSales, 2),
            'totalOtherIncome' => round($totalOtherIncome, 2),
            'totalExpenses' => round($totalExpenses, 2),
            'detailGroups' => $detailGroups,
        ];
    }

    private function classifyCloseMovementCategory(CashMovements $cashMovement): string
    {
        $conceptName = mb_strtolower((string) ($cashMovement->paymentConcept?->description ?? ''), 'UTF-8');
        $documentName = mb_strtolower((string) ($cashMovement->movement?->documentType?->name ?? ''), 'UTF-8');
        $conceptType = mb_strtoupper((string) ($cashMovement->paymentConcept?->type ?? ''), 'UTF-8');

        if (str_contains($conceptName, 'apertura')) {
            return 'opening';
        }

        if (str_contains($conceptName, 'cierre')) {
            return 'closing';
        }

        if ($conceptType === 'E' || str_contains($documentName, 'egreso')) {
            return 'expense';
        }

        if (
            str_contains($conceptName, 'pago de cliente')
            || str_contains($conceptName, 'venta')
            || !is_null($cashMovement->movement?->salesMovement)
        ) {
            return 'sale';
        }

        return 'income';
    }

    private function resolveCloseDetailTypeLabel(string $type): string
    {
        return match (mb_strtoupper($type, 'UTF-8')) {
            'DEUDA' => 'Deuda',
            default => 'Pagado',
        };
    }

    private function isCashMethod(?string $method): bool
    {
        $value = mb_strtolower(trim((string) $method), 'UTF-8');

        return str_contains($value, 'efectivo') || str_contains($value, 'cash');
    }

    /**
     * Etiqueta para columna "Medio" en cierre de caja: la deuda no es un medio de pago real.
     */
    private function resolveClosePageMethodLabel(CashMovementDetail $detail): string
    {
        $detailType = mb_strtoupper((string) ($detail->type ?? ''), 'UTF-8');
        $raw = trim((string) ($detail->payment_method ?? ''));

        if ($detailType === 'DEUDA' || mb_strtolower($raw, 'UTF-8') === 'deuda') {
            return 'Crédito';
        }

        return $raw !== '' ? $raw : 'Sin medio';
    }

    private function resolveCloseDetailSuffix(CashMovementDetail $detail): string
    {
        $parts = array_filter([
            trim((string) ($detail->bank ?? '')),
            trim((string) ($detail->card ?? '')),
            trim((string) ($detail->digital_wallet ?? '')),
            trim((string) ($detail->payment_gateway ?? '')),
        ]);

        return implode(' | ', $parts);
    }

    private function resolveCurrentCashAmount(int $cashRegisterId, int $branchId): float
    {
        return (float) DB::table('cash_movement_details as cmd')
            ->join('cash_movements as cm', 'cm.id', '=', 'cmd.cash_movement_id')
            ->join('movements as m', 'm.id', '=', 'cm.movement_id')
            ->leftJoin('document_types as dt', 'dt.id', '=', 'm.document_type_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'cmd.payment_method_id')
            ->where('cm.cash_register_id', $cashRegisterId)
            ->where('m.branch_id', $branchId)
            ->whereNull('m.deleted_at')
            ->where(function ($query) {
                $query->whereRaw("LOWER(COALESCE(pm.description, cmd.payment_method, '')) LIKE '%efectivo%'")
                    ->orWhereRaw("LOWER(COALESCE(pm.description, cmd.payment_method, '')) LIKE '%cash%'");
            })
            ->selectRaw("
                COALESCE(
                    SUM(
                        CASE
                            WHEN LOWER(COALESCE(dt.name, '')) LIKE '%egreso%' THEN -cmd.amount
                            ELSE cmd.amount
                        END
                    ),
                    0
                ) as total
            ")
            ->value('total');
    }
}
