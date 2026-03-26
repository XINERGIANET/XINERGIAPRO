<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivablePayable;
use App\Models\Card;
use App\Models\CashRegister;
use App\Models\DigitalWallet;
use App\Models\Operation;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Services\AccountReceivablePayableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AccountReceivablePayableController extends Controller
{
    public function receivables(Request $request)
    {
        return $this->renderIndex($request, AccountReceivablePayableService::TYPE_RECEIVABLE);
    }

    public function payables(Request $request)
    {
        return $this->renderIndex($request, AccountReceivablePayableService::TYPE_PAYABLE);
    }

    public function settle(
        Request $request,
        AccountReceivablePayable $account,
        AccountReceivablePayableService $service
    ) {
        $this->assertAccountScope($account);

        $branchId = (int) session('branch_id');
        $pendingAmount = $this->resolvePendingAmount($account);

        $validator = Validator::make($request->all(), [
            'settlement_account_id' => ['required', 'integer'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'comment' => ['nullable', 'string', 'max:65535'],
        ]);

        $validator->after(function ($validator) use ($request, $account, $branchId, $pendingAmount) {
            if ((int) $request->input('settlement_account_id') !== (int) $account->id) {
                $validator->errors()->add('general', 'La cuenta seleccionada no coincide con la operación solicitada.');
            }

            if ((float) $request->input('amount', 0) - $pendingAmount > 0.009) {
                $validator->errors()->add('amount', 'El monto supera el saldo pendiente.');
            }

            $cashRegisterId = (int) $request->input('cash_register_id', 0);
            if ($cashRegisterId > 0 && !CashRegister::query()->where('branch_id', $branchId)->whereKey($cashRegisterId)->exists()) {
                $validator->errors()->add('cash_register_id', 'La caja seleccionada no pertenece a la sucursal actual.');
            }

            $paymentMethod = PaymentMethod::query()->find((int) $request->input('payment_method_id', 0));
            if (!$paymentMethod || !$paymentMethod->status) {
                $validator->errors()->add('payment_method_id', 'Debe seleccionar un método de pago válido.');
                return;
            }

            if (mb_strtolower(trim((string) $paymentMethod->description), 'UTF-8') === 'deuda') {
                $validator->errors()->add('payment_method_id', 'La opción Deuda no puede usarse para registrar un abono.');
                return;
            }

            $kind = $this->inferPaymentMethodKind((string) ($paymentMethod->description ?? ''));

            if ($kind === 'card' && empty($request->input('card_id'))) {
                $validator->errors()->add('card_id', 'Debe seleccionar la tarjeta.');
            }

            if ($kind === 'wallet' && empty($request->input('digital_wallet_id'))) {
                $validator->errors()->add('digital_wallet_id', 'Debe seleccionar la billetera digital.');
            }

            if ($kind === 'card' && !empty($request->input('payment_gateway_id'))) {
                $allowedGateways = DB::table('payment_gateway_payment_method')
                    ->whereNull('deleted_at')
                    ->where('payment_method_id', (int) $paymentMethod->id)
                    ->pluck('payment_gateway_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                if (!in_array((int) $request->input('payment_gateway_id'), $allowedGateways, true)) {
                    $validator->errors()->add('payment_gateway_id', 'La pasarela no corresponde al método de pago seleccionado.');
                }
            }
        });

        $validated = $validator->validateWithBag('settlement');
        $paymentMethod = PaymentMethod::query()->findOrFail((int) $validated['payment_method_id']);
        $kind = $this->inferPaymentMethodKind((string) ($paymentMethod->description ?? ''));

        if ($kind === 'card') {
            $validated['digital_wallet_id'] = null;
        } elseif ($kind === 'wallet') {
            $validated['card_id'] = null;
            $validated['payment_gateway_id'] = null;
        } else {
            $validated['card_id'] = null;
            $validated['payment_gateway_id'] = null;
            $validated['digital_wallet_id'] = null;
        }

        $validated['reference'] = isset($validated['reference'])
            ? trim((string) $validated['reference'])
            : null;
        $validated['comment'] = isset($validated['comment'])
            ? trim((string) $validated['comment'])
            : null;

        try {
            $service->registerSettlement($account, $validated, $request->user());
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withErrors(['general' => $exception->getMessage()], 'settlement')
                ->withInput();
        }

        $message = $account->type === AccountReceivablePayableService::TYPE_RECEIVABLE
            ? 'Cobro registrado correctamente.'
            : 'Pago registrado correctamente.';

        return redirect()->back()->with('status', $message);
    }

    private function renderIndex(Request $request, string $type)
    {
        $branchId = (int) session('branch_id');
        $viewId = $request->input('view_id');
        $search = trim((string) $request->input('search', ''));
        $status = strtoupper((string) $request->input('status', 'ALL'));
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $records = AccountReceivablePayable::query()
            ->with([
                'cashMovement.movement.parentMovement.documentType',
                'cashMovement.movement.parentMovement.person',
                'cashMovement.movement.parentMovement.salesMovement',
                'cashMovement.movement.parentMovement.purchaseMovement',
            ])
            ->where('account_receivable_payables.branch_id', $branchId)
            ->where('account_receivable_payables.type', $type)
            ->when($status !== 'ALL', fn ($query) => $query->where('account_receivable_payables.status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('account_receivable_payables.number', 'ILIKE', "%{$search}%")
                        ->orWhereHas('cashMovement.movement.parentMovement', function ($movementQuery) use ($search) {
                            $movementQuery
                                ->where('number', 'ILIKE', "%{$search}%")
                                ->orWhere('person_name', 'ILIKE', "%{$search}%")
                                ->orWhere('comment', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("CASE account_receivable_payables.status WHEN 'NUEVO' THEN 0 WHEN 'PAGANDO' THEN 1 ELSE 2 END")
            ->orderByDesc('account_receivable_payables.date')
            ->paginate($perPage)
            ->withQueryString();

        $summaryBase = AccountReceivablePayable::query()
            ->where('account_receivable_payables.branch_id', $branchId)
            ->where('account_receivable_payables.type', $type);

        $totalAmount = (float) (clone $summaryBase)
            ->join('cash_movements', 'cash_movements.id', '=', 'account_receivable_payables.cash_movement_id')
            ->whereNull('cash_movements.deleted_at')
            ->sum('cash_movements.total');
        $totalPaid = (float) (clone $summaryBase)->sum('account_receivable_payables.total_paid');
        $totalPending = max(0, $totalAmount - $totalPaid);

        $pageTitle = $type === AccountReceivablePayableService::TYPE_RECEIVABLE
            ? 'Cuentas por cobrar'
            : 'Cuentas por pagar';
        $description = $type === AccountReceivablePayableService::TYPE_RECEIVABLE
            ? 'Controla las ventas registradas como deuda y su saldo pendiente.'
            : 'Controla las compras registradas como deuda y su saldo pendiente.';

        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN status IN ('A', '1') THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);
        $standardCashRegisterId = $cashRegisters->firstWhere('status', 'A')->id
            ?? $cashRegisters->firstWhere('status', '1')->id
            ?? $cashRegisters->first()->id
            ?? null;
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;

        $paymentMethodOptions = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description'])
            ->reject(fn ($method) => mb_strtolower(trim((string) ($method->description ?? '')), 'UTF-8') === 'deuda')
            ->map(fn ($method) => [
                'id' => (int) $method->id,
                'description' => (string) ($method->description ?? ''),
                'kind' => $this->inferPaymentMethodKind((string) ($method->description ?? '')),
            ])
            ->values();

        $cardOptions = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type'])
            ->map(fn ($card) => [
                'id' => (int) $card->id,
                'description' => (string) ($card->description ?? ''),
                'type' => (string) ($card->type ?? ''),
            ])
            ->values();

        $digitalWalletOptions = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description'])
            ->map(fn ($wallet) => [
                'id' => (int) $wallet->id,
                'description' => (string) ($wallet->description ?? ''),
            ])
            ->values();

        $paymentGatewayOptionsByMethod = collect(
            DB::table('payment_gateway_payment_method as pgpm')
                ->join('payment_gateways as pg', 'pg.id', '=', 'pgpm.payment_gateway_id')
                ->whereNull('pgpm.deleted_at')
                ->whereNull('pg.deleted_at')
                ->where('pg.status', true)
                ->orderBy('pg.order_num')
                ->orderBy('pg.description')
                ->get([
                    'pgpm.payment_method_id',
                    'pg.id',
                    'pg.description',
                ])
        )
            ->groupBy('payment_method_id')
            ->map(fn ($rows) => collect($rows)->map(fn ($row) => [
                'id' => (int) $row->id,
                'description' => (string) ($row->description ?? ''),
            ])->values())
            ->all();

        return view('accounts_receivable_payable.index', [
            'records' => $records,
            'search' => $search,
            'statusFilter' => $status === '' ? 'ALL' : $status,
            'perPage' => $perPage,
            'viewId' => $viewId,
            'pageTitle' => $pageTitle,
            'pageDescription' => $description,
            'type' => $type,
            'totalAmount' => (float) $totalAmount,
            'totalPaid' => $totalPaid,
            'totalPending' => (float) $totalPending,
            'operaciones' => $this->resolveOperations($viewId, $branchId),
            'cashRegisters' => $cashRegisters,
            'defaultCashRegisterId' => $standardCashRegisterId,
            'invoiceCashRegisterId' => $invoiceCashRegisterId,
            'paymentMethodOptions' => $paymentMethodOptions,
            'cardOptions' => $cardOptions,
            'digitalWalletOptions' => $digitalWalletOptions,
            'paymentGatewayOptionsByMethod' => $paymentGatewayOptionsByMethod,
            'settlementDraftRecord' => $this->resolveSettlementDraftRecord($branchId, $type, $viewId),
        ]);
    }

    private function resolveOperations($viewId, int $branchId)
    {
        $profileId = session('profile_id') ?? auth()->user()?->profile_id;
        if (!$viewId || !$branchId || !$profileId) {
            return collect();
        }

        return Operation::query()
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

    private function inferPaymentMethodKind(string $description): string
    {
        $value = mb_strtolower(trim($description), 'UTF-8');

        if (str_contains($value, 'tarjeta') || str_contains($value, 'card')) {
            return 'card';
        }

        if (str_contains($value, 'billetera') || str_contains($value, 'wallet')) {
            return 'wallet';
        }

        return 'other';
    }

    private function assertAccountScope(AccountReceivablePayable $account): void
    {
        if ((int) $account->branch_id !== (int) session('branch_id')) {
            abort(404);
        }
    }

    private function resolvePendingAmount(AccountReceivablePayable $account): float
    {
        $account->loadMissing('cashMovement');

        $total = (float) ($account->cashMovement?->total ?? 0);
        $paid = (float) $account->details()
            ->join('cash_movements', 'cash_movements.id', '=', 'account_receivable_payable_details.cash_movement_id')
            ->whereNull('cash_movements.deleted_at')
            ->sum('cash_movements.total');

        return max(0, round($total - $paid, 2));
    }

    private function resolveSettlementDraftRecord(int $branchId, string $type, $viewId): ?array
    {
        $accountId = (int) session()->getOldInput('settlement_account_id', 0);
        if ($accountId <= 0) {
            return null;
        }

        $record = AccountReceivablePayable::query()
            ->with([
                'cashMovement.movement.parentMovement.documentType',
                'cashMovement.movement.parentMovement.person',
            ])
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->find($accountId);

        if (!$record) {
            return null;
        }

        return $this->serializeSettlementRecord($record, $viewId);
    }

    private function serializeSettlementRecord(AccountReceivablePayable $record, $viewId): array
    {
        $cashMovement = $record->cashMovement;
        $cashEntryMovement = $cashMovement?->movement;
        $sourceMovement = $cashEntryMovement?->parentMovement ?: $cashEntryMovement;
        $total = (float) ($cashMovement?->total ?? 0);
        $paid = (float) ($record->total_paid ?? 0);
        $pending = max(0, round($total - $paid, 2));

        return [
            'id' => (int) $record->id,
            'action' => route('admin.cash-accounts.settle', ['account' => $record->id, 'view_id' => $viewId]),
            'number' => (string) ($record->number ?? ''),
            'person_label' => (string) ($sourceMovement?->person_name ?: ($cashEntryMovement?->person_name ?: '-')),
            'document_label' => trim((string) ($sourceMovement?->documentType?->name ?? '-') . ' ' . (string) ($sourceMovement?->number ?? '')),
            'date_label' => optional($record->date)->format('d/m/Y H:i') ?? '-',
            'due_date_label' => optional($record->due_date)->format('d/m/Y H:i') ?? '-',
            'currency' => (string) ($record->currency ?? 'PEN'),
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'preferred_cash_register_id' => $this->preferredCashRegisterIdForSettlement($record),
        ];
    }

    private function preferredCashRegisterIdForSettlement(AccountReceivablePayable $record): ?int
    {
        $branchId = (int) ($record->branch_id ?? 0);
        if ($branchId <= 0) {
            return null;
        }

        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN status IN ('A', '1') THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);

        $standardCashRegisterId = $cashRegisters->firstWhere('status', 'A')->id
            ?? $cashRegisters->firstWhere('status', '1')->id
            ?? $cashRegisters->first()->id
            ?? null;
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;

        $cashMovement = $record->cashMovement;
        $cashEntryMovement = $cashMovement?->movement;
        $sourceMovement = $cashEntryMovement?->parentMovement ?: $cashEntryMovement;
        $documentName = mb_strtolower(trim((string) ($sourceMovement?->documentType?->name ?? '')), 'UTF-8');

        return str_contains($documentName, 'factura') ? $invoiceCashRegisterId : $standardCashRegisterId;
    }

    private function getBranchConfiguredCashRegisterId(int $branchId, $cashRegisters, string $needle): ?int
    {
        $cashRegisters = collect($cashRegisters);

        if ($branchId <= 0) {
            return $cashRegisters->firstWhere('status', 'A')->id
                ?? $cashRegisters->firstWhere('status', '1')->id
                ?? $cashRegisters->first()->id
                ?? null;
        }

        $configuredValue = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->where('bp.branch_id', $branchId)
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'ILIKE', '%' . $needle . '%')
            ->value('bp.value');

        if (is_numeric($configuredValue)) {
            $configuredId = (int) $configuredValue;
            $exists = $cashRegisters->contains(fn ($cashRegister) => (int) ($cashRegister->id ?? 0) === $configuredId);
            if ($exists) {
                return $configuredId;
            }
        }

        return $cashRegisters->firstWhere('status', 'A')->id
            ?? $cashRegisters->firstWhere('status', '1')->id
            ?? $cashRegisters->first()->id
            ?? null;
    }
}
