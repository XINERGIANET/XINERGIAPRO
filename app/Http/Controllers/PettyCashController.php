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


class PettyCashController extends Controller
{

    public function redirectBase(Request $request)
    {
        $firstBox = CashRegister::where('status', '1')->first();
        if ($firstBox) {
            $params = ['cash_register_id' => $firstBox->id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('admin.petty-cash.index', $params);
        }
        abort(404, 'No hay cajas registradas');
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
        $cashRegisters = CashRegister::where('status', '1')->orderBy('number', 'asc')->get();
        $selectedBoxId = $cash_register_id;

        if (empty($selectedBoxId) && $cashRegisters->isNotEmpty()) {
            $selectedBoxId = $cashRegisters->first()->id;
        }

        // --- LÓGICA DE ESTADO DE CAJA (MODIFICADO) ---
        $lastShiftRelation = CashShiftRelation::where('branch_id', session('branch_id'))
            ->whereHas('cashMovementStart', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            })
            ->latest('id')
            ->first();

        $hasOpening = $lastShiftRelation && $lastShiftRelation->status == '1';

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
            ->when($search, function ($query, $search) {
                $needle = mb_strtolower((string) $search, 'UTF-8');
                $query->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(COALESCE(person_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(user_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(responsible_name, \'\')) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', ["%{$needle}%"]);
                });
            })
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

    public function store(Request $request, $cash_register_id)
    {
        $request->merge(['cash_register_id' => $cash_register_id]);

        $validated = $request->validate([
            'comment'            => 'required|string|max:255',
            'document_type_id'   => 'nullable|exists:document_types,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'shift_id'           => 'required|exists:shifts,id',

            'payments'           => 'required|array|min:1',
            'payments.*.amount'  => 'required|numeric|min:0.00',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.number'  => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($request, $validated, $cash_register_id) {
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
                    ->where('cash_movements.cash_register_id', $cash_register_id)
                    ->latest('movements.id')
                    ->lockForUpdate()
                    ->first();

                $nextSequence = $lastRecord ? intval($lastRecord->number) + 1 : 1;
                $generatedNumber = str_pad($nextSequence, 8, '0', STR_PAD_LEFT);

                $totalAmount = (float) collect($request->payments)->sum('amount');
                $concept = PaymentConcept::findOrFail((int) $validated['payment_concept_id']);
                $conceptName = mb_strtolower((string) $concept->description, 'UTF-8');
                $isClosingConcept = str_contains($conceptName, 'cierre');

                // En cierre se registra automaticamente el efectivo real disponible en caja.
                if ($isClosingConcept) {
                    $totalAmount = (float) $this->resolveCurrentCashAmount((int) $cash_register_id, (int) session('branch_id'));
                }

                $movement = Movement::create([
                    'number'             => $generatedNumber,
                    'moved_at'           => now(),
                    'user_id'            => session('user_id'),
                    'user_name'          => session('user_name'),
                    'person_id'          => session('person_id'),
                    'person_name'        => session('person_fullname'),
                    'responsible_id'     => session('person_id'),
                    'responsible_name'   => session('person_fullname'),
                    'comment'            => $validated['comment'],
                    'status'             => '1',
                    'movement_type_id'   => $typeId,
                    'document_type_id'   => $request->document_type_id,
                    'branch_id'          => session('branch_id'),
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                ]);

                $box = CashRegister::find($request->cash_register_id);
                $boxName = $box ? $box->number : 'Caja Desconocida';

                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'currency'           => 'PEN',
                    'exchange_rate'      => 3.71,
                    'total'              => $totalAmount,
                    'cash_register_id'   => $cash_register_id,
                    'cash_register'      => $boxName,
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                    'movement_id'        => $movement->id,
                    'branch_id'          => session('branch_id'),
                ]);

                $payments = $request->payments;
                if ($isClosingConcept) {
                    $payments = [[
                        'amount' => $totalAmount,
                        'payment_method_id' => 1,
                        'payment_method' => 'Efectivo',
                        'number' => null,
                        'card_id' => null,
                        'bank_id' => null,
                        'digital_wallet_id' => null,
                        'payment_gateway_id' => null,
                    ]];
                }

                foreach ($payments as $paymentData) {

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

                    $individualComment = ($paymentData['payment_method_id'] != 1 && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $validated['comment'];

                    CashMovementDetail::create([
                        'cash_movement_id'   => $cashMovement->id,
                        'branch_id'          => session('branch_id'),

                        'type'               => 'PAGADO',
                        'status'             => 'A',
                        'paid_at'            => now(),

                        'amount'             => $paymentData['amount'],
                        'payment_method_id'  => $paymentData['payment_method_id'],
                        'payment_method'     => $paymentData['payment_method'] ?? 'Desconocido',
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
                        ->whereHas('cashMovementStart', function ($query) use ($cash_register_id) {
                            $query->where('cash_register_id', $cash_register_id);
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

            $params = ['cash_register_id' => $cash_register_id];
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
            'comment'            => 'required|string|max:255',
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

                $movement->update([
                    'comment'        => $validated['comment'],
                    'shift_id'       => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                    'document_type_id' => $request->document_type_id
                ]);

                $cashMovement->update([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'total'              => $newTotalAmount,
                    'shift_id'           => $selectedShift->id,
                    'shift_snapshot'     => $shiftSnapshotJson,
                ]);

                CashMovementDetail::where('cash_movement_id', $cashMovement->id)->delete();

                foreach ($request->payments as $paymentData) {

                    $cardName = !empty($paymentData['card_id']) ? Card::find($paymentData['card_id'])?->description : null;
                    $bankName = !empty($paymentData['bank_id']) ? Bank::find($paymentData['bank_id'])?->description : null;
                    $walletName = !empty($paymentData['digital_wallet_id']) ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description : null;
                    $gatewayName = !empty($paymentData['payment_gateway_id']) ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description : null;

                    $individualComment = ($paymentData['payment_method_id'] != 1 && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $validated['comment'];

                    CashMovementDetail::create([
                        'cash_movement_id'   => $cashMovement->id,
                        'branch_id'          => session('branch_id'),
                        'type'               => 'PAGADO',
                        'status'             => 'A',
                        'paid_at'            => $movement->moved_at,

                        'amount'             => $paymentData['amount'],
                        'payment_method_id'  => $paymentData['payment_method_id'],
                        'payment_method'     => $paymentData['payment_method'] ?? 'Desconocido',

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

                $movement->delete();
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('admin.petty-cash.index', $params)
                ->with('success', 'Movimiento eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
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
