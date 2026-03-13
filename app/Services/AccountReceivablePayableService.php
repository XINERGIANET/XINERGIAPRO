<?php

namespace App\Services;

use App\Models\AccountReceivablePayable;
use App\Models\AccountReceivablePayableDetail;
use App\Models\Card;
use App\Models\CashMovements;
use App\Models\CashMovementDetail;
use App\Models\CashRegister;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Shift;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonInterface;
use RuntimeException;

class AccountReceivablePayableService
{
    public const TYPE_RECEIVABLE = 'COBRAR';
    public const TYPE_PAYABLE = 'PAGAR';

    public const STATUS_NEW = 'NUEVO';
    public const STATUS_PAYING = 'PAGANDO';
    public const STATUS_PAID = 'PAGADO';

    public function syncDebtAccount(CashMovements $cashMovement, string $type, ?CarbonInterface $dueDate = null): AccountReceivablePayable
    {
        $cashMovement->loadMissing('movement.parentMovement');

        $cashEntryMovement = $cashMovement->movement;
        $sourceMovement = $cashEntryMovement?->parentMovement ?: $cashEntryMovement;
        $sourceDate = $sourceMovement?->moved_at ?? $cashEntryMovement?->moved_at ?? now();
        $branchId = (int) ($cashMovement->branch_id ?? $cashEntryMovement?->branch_id ?? $sourceMovement?->branch_id ?? 0);

        $account = AccountReceivablePayable::query()->withTrashed()->firstOrNew([
            'cash_movement_id' => $cashMovement->id,
        ]);

        if ($account->trashed()) {
            $account->restore();
        }

        $account->fill([
            'number' => (string) ($sourceMovement?->number ?: $cashEntryMovement?->number ?: $cashMovement->id),
            'type' => $type,
            'date' => $sourceDate,
            'due_date' => $dueDate ?: ($account->due_date ?: $sourceDate),
            'currency' => (string) ($cashMovement->currency ?? 'PEN'),
            'exchange_rate' => (float) ($cashMovement->exchange_rate ?? 1),
            'branch_id' => $branchId,
            'situation' => 'E',
        ]);
        $account->save();

        return $this->refreshAccount($account);
    }

    public function removeDebtAccountByCashMovementId(int $cashMovementId): void
    {
        $account = AccountReceivablePayable::query()
            ->where('cash_movement_id', $cashMovementId)
            ->first();

        if (!$account) {
            return;
        }

        $account->details()->delete();
        $account->delete();
    }

    public function refreshAccount(AccountReceivablePayable $account): AccountReceivablePayable
    {
        $account->loadMissing('cashMovement');

        $currentTotal = (float) ($account->cashMovement?->total ?? 0);
        $currentTotalPaid = (float) $account->details()
            ->join('cash_movements', 'cash_movements.id', '=', 'account_receivable_payable_details.cash_movement_id')
            ->whereNull('cash_movements.deleted_at')
            ->sum('cash_movements.total');

        $account->forceFill([
            'total_paid' => round($currentTotalPaid, 2),
            'status' => $this->resolveStatus($currentTotal, $currentTotalPaid),
            'paid_at' => $currentTotal > 0 && $currentTotalPaid >= $currentTotal ? now() : null,
        ])->save();

        return $account->fresh(['cashMovement', 'details.cashMovement.movement.parentMovement']) ?? $account;
    }

    public function registerSettlement(
        AccountReceivablePayable $account,
        array $payload,
        ?Authenticatable $user = null
    ): AccountReceivablePayable {
        return DB::transaction(function () use ($account, $payload, $user) {
            /** @var AccountReceivablePayable $lockedAccount */
            $lockedAccount = AccountReceivablePayable::query()
                ->with([
                    'cashMovement.movement.parentMovement',
                    'cashMovement.movement.parentMovement.documentType',
                ])
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAccount = $this->refreshAccount($lockedAccount);

            $total = (float) ($lockedAccount->cashMovement?->total ?? 0);
            $pending = max(0, round($total - (float) ($lockedAccount->total_paid ?? 0), 2));
            $amount = round((float) ($payload['amount'] ?? 0), 2);

            if ($pending <= 0) {
                throw new RuntimeException('La cuenta ya no tiene saldo pendiente.');
            }

            if ($amount <= 0) {
                throw new RuntimeException('El monto del cobro/pago debe ser mayor a cero.');
            }

            if ($amount - $pending > 0.009) {
                throw new RuntimeException('El monto supera el saldo pendiente de la cuenta.');
            }

            $branchId = (int) ($lockedAccount->branch_id ?? 0);
            $cashRegisterId = (int) ($payload['cash_register_id'] ?? 0);

            $cashRegister = CashRegister::query()
                ->where('branch_id', $branchId)
                ->find($cashRegisterId);

            if (!$cashRegister) {
                throw new RuntimeException('La caja seleccionada no pertenece a la sucursal actual.');
            }

            $shift = Shift::query()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->first() ?? Shift::query()->orderBy('id')->first();

            if (!$shift) {
                throw new RuntimeException('No hay turno disponible para registrar el movimiento de caja.');
            }

            $cashMovementTypeId = $this->resolveCashMovementTypeId();
            $cashDocumentTypeId = $lockedAccount->type === self::TYPE_RECEIVABLE
                ? $this->resolveCashIncomeDocumentTypeId($cashMovementTypeId)
                : $this->resolveCashExpenseDocumentTypeId($cashMovementTypeId);
            $paymentConcept = $lockedAccount->type === self::TYPE_RECEIVABLE
                ? $this->resolveReceivablePaymentConcept()
                : $this->resolvePayablePaymentConcept();

            $cashEntryMovement = $lockedAccount->cashMovement?->movement;
            $sourceMovement = $cashEntryMovement?->parentMovement ?: $cashEntryMovement;
            $paymentMethod = PaymentMethod::query()->findOrFail((int) $payload['payment_method_id']);
            $paymentGateway = !empty($payload['payment_gateway_id'])
                ? PaymentGateways::query()->find((int) $payload['payment_gateway_id'])
                : null;
            $card = !empty($payload['card_id'])
                ? Card::query()->find((int) $payload['card_id'])
                : null;
            $digitalWallet = !empty($payload['digital_wallet_id'])
                ? DigitalWallet::query()->find((int) $payload['digital_wallet_id'])
                : null;
            $movementNumber = $this->generateCashMovementNumber($branchId, $cashRegisterId, (int) $paymentConcept->id);
            $movementLabel = $lockedAccount->type === self::TYPE_RECEIVABLE ? 'Cobro' : 'Pago';
            $baseComment = trim((string) ($payload['comment'] ?? ''));
            if ($baseComment === '') {
                $baseComment = sprintf('%s de cuenta %s', $movementLabel, $lockedAccount->number);
            }

            $movement = Movement::query()->create([
                'number' => $movementNumber,
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => $sourceMovement?->person_id,
                'person_name' => $sourceMovement?->person_name
                    ?: ($cashEntryMovement?->person_name ?? 'Publico General'),
                'responsible_id' => $user?->id,
                'responsible_name' => $user?->name ?? 'Sistema',
                'comment' => $baseComment,
                'status' => '1',
                'movement_type_id' => $cashMovementTypeId,
                'document_type_id' => $cashDocumentTypeId,
                'branch_id' => $branchId,
                'parent_movement_id' => $sourceMovement?->id ?? $cashEntryMovement?->id,
            ]);

            $paymentCashMovement = CashMovements::query()->create([
                'payment_concept_id' => $paymentConcept->id,
                'currency' => (string) ($lockedAccount->currency ?? 'PEN'),
                'exchange_rate' => (float) ($lockedAccount->exchange_rate ?? 1),
                'total' => $amount,
                'cash_register_id' => $cashRegister->id,
                'cash_register' => $cashRegister->number ?? 'Caja Principal',
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ],
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            CashMovementDetail::query()->create([
                'cash_movement_id' => $paymentCashMovement->id,
                'type' => 'PAGADO',
                'due_at' => null,
                'paid_at' => now(),
                'payment_method_id' => $paymentMethod->id,
                'payment_method' => $paymentMethod->description ?? '',
                'number' => trim((string) ($payload['reference'] ?? '')) ?: $movementNumber,
                'card_id' => $card?->id,
                'card' => $card?->description ?? '',
                'bank_id' => null,
                'bank' => '',
                'digital_wallet_id' => $digitalWallet?->id,
                'digital_wallet' => $digitalWallet?->description ?? '',
                'payment_gateway_id' => $paymentGateway?->id,
                'payment_gateway' => $paymentGateway?->description ?? '',
                'amount' => $amount,
                'comment' => $baseComment,
                'status' => 'A',
                'branch_id' => $branchId,
            ]);

            AccountReceivablePayableDetail::query()->create([
                'account_receivable_payable_id' => $lockedAccount->id,
                'cash_movement_id' => $paymentCashMovement->id,
                'situation' => 'E',
                'branch_id' => $branchId,
            ]);

            return $this->refreshAccount($lockedAccount);
        });
    }

    private function resolveStatus(float $total, float $totalPaid): string
    {
        if ($total > 0 && $totalPaid >= $total) {
            return self::STATUS_PAID;
        }

        if ($totalPaid > 0) {
            return self::STATUS_PAYING;
        }

        return self::STATUS_NEW;
    }

    private function resolveCashMovementTypeId(): int
    {
        $movementTypeId = MovementType::query()
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%caja%')
                    ->orWhere('description', 'ILIKE', '%cash%');
            })
            ->orderBy('id')
            ->value('id');

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->find(4)?->id;
        }

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }

        if (!$movementTypeId) {
            throw new RuntimeException('No se encontro tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
    }

    private function resolveCashIncomeDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->where('name', 'ILIKE', '%ingreso%')
            ->orderBy('id')
            ->value('id');

        if (!$documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (!$documentTypeId) {
            throw new RuntimeException('No se encontro tipo de documento para ingreso de caja.');
        }

        return (int) $documentTypeId;
    }

    private function resolveCashExpenseDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->where('name', 'ILIKE', '%egreso%')
            ->orderBy('id')
            ->value('id');

        if (!$documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (!$documentTypeId) {
            throw new RuntimeException('No se encontro tipo de documento para egreso de caja.');
        }

        return (int) $documentTypeId;
    }

    private function resolveReceivablePaymentConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'I')
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%cuenta por cobrar%')
                    ->orWhere('description', 'ILIKE', '%cobro%')
                    ->orWhere('description', 'ILIKE', '%pago de cliente%')
                    ->orWhere('description', 'ILIKE', '%venta%');
            })
            ->orderBy('id')
            ->first();

        if (!$paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'I')
                ->orderBy('id')
                ->first();
        }

        if (!$paymentConcept) {
            throw new RuntimeException('No se encontro concepto de pago para registrar el cobro.');
        }

        return $paymentConcept;
    }

    private function resolvePayablePaymentConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'E')
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%cuenta por pagar%')
                    ->orWhere('description', 'ILIKE', '%pago de compra%')
                    ->orWhere('description', 'ILIKE', '%proveedor%')
                    ->orWhere('description', 'ILIKE', '%compra%');
            })
            ->orderBy('id')
            ->first();

        if (!$paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'E')
                ->orderBy('id')
                ->first();
        }

        if (!$paymentConcept) {
            throw new RuntimeException('No se encontro concepto de pago para registrar el egreso.');
        }

        return $paymentConcept;
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $lastRecord = Movement::query()
            ->select('movements.number')
            ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
            ->where('movements.branch_id', $branchId)
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->when($paymentConceptId !== null, function ($query) use ($paymentConceptId) {
                $query->where('cash_movements.payment_concept_id', $paymentConceptId);
            })
            ->lockForUpdate()
            ->orderByDesc('movements.number')
            ->first();

        $lastNumber = $lastRecord?->number;
        $nextSequence = $lastNumber ? ((int) $lastNumber + 1) : 1;

        return str_pad((string) $nextSequence, 8, '0', STR_PAD_LEFT);
    }
}
