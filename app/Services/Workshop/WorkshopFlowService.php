<?php

namespace App\Services\Workshop;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Card;
use App\Models\CashMovementDetail;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Models\WorkshopChecklist;
use App\Models\WorkshopChecklistItem;
use App\Models\WorkshopAudit;
use App\Models\WorkshopIntakeInventory;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\WorkshopPreexistingDamage;
use App\Models\WorkshopStatusHistory;
use App\Models\WorkshopMovementStatusLog;
use App\Models\WorkshopStockReservation;
use App\Models\WorkshopVehicleLog;
use App\Models\WorkshopWarranty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkshopFlowService
{
    private const STATUS_TRANSITIONS = [
        'draft' => ['diagnosis', 'cancelled'],
        'diagnosis' => ['awaiting_approval', 'cancelled'],
        'awaiting_approval' => ['approved', 'diagnosis', 'cancelled'],
        'approved' => ['in_progress', 'cancelled'],
        'in_progress' => ['finished', 'cancelled'],
        'finished' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    private const CHECKLIST_RESULTS = [
        'OS_INTAKE' => ['SI', 'NO'],
        'GP_ACTIVATION' => ['OK', 'FALTANTE', 'DANADO', 'DAÑADO'],
        'PDI' => ['DONE', 'NOT_DONE', 'CHECKED', 'UNCHECKED'],
        'MAINTENANCE' => ['SI', 'NO'],
    ];

    public function createOrder(array $data, int $branchId, int $userId, string $userName): WorkshopMovement
    {
        return DB::transaction(function () use ($data, $branchId, $userId, $userName) {
            $branch = Branch::query()->findOrFail($branchId);
            $intakeDate = isset($data['intake_date']) ? \Carbon\Carbon::parse($data['intake_date']) : now();
            $maxOrdersPerDay = (int) $this->paramNumber('WS_MAX_ORDERS_PER_DAY', $branchId, 100);
            if ($maxOrdersPerDay > 0) {
                $ordersToday = WorkshopMovement::query()
                    ->where('branch_id', $branchId)
                    ->whereDate('intake_date', $intakeDate->toDateString())
                    ->count();

                if ($ordersToday >= $maxOrdersPerDay) {
                    throw new \RuntimeException('Se alcanzó el máximo de OS por día para la sucursal.');
                }
            }
            $vehicle = Vehicle::query()
                ->where('id', (int) $data['vehicle_id'])
                ->where('company_id', (int) $branch->company_id)
                ->firstOrFail();

            $client = Person::query()->findOrFail((int) $data['client_person_id']);
            if ((int) $vehicle->client_person_id !== (int) $client->id) {
                throw new \RuntimeException('El vehiculo no pertenece al cliente seleccionado.');
            }

            $movementTypeId = $this->resolveMovementTypeId('TALLER_OS');
            $documentTypeId = $this->resolveDocumentTypeId($movementTypeId, 'Orden de Servicio', 'none');

            $movement = Movement::query()->create([
                'number' => $this->generateMovementNumber($branchId, $documentTypeId),
                'moved_at' => $intakeDate,
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $client->id,
                'person_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => (string) ($data['comment'] ?? 'Orden de servicio creada'),
                'status' => 'A',
                'movement_type_id' => $movementTypeId,
                'document_type_id' => $documentTypeId,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);

            $status = (string) ($data['status'] ?? 'draft');
            if (!array_key_exists($status, self::STATUS_TRANSITIONS)) {
                $status = 'draft';
            }

            $workshop = WorkshopMovement::query()->create([
                'movement_id' => $movement->id,
                'previous_workshop_movement_id' => $data['previous_workshop_movement_id'] ?? null,
                'company_id' => (int) $branch->company_id,
                'branch_id' => $branchId,
                'vehicle_id' => $vehicle->id,
                'client_person_id' => $client->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'intake_date' => $data['intake_date'] ?? now(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'mileage_in' => $data['mileage_in'] ?? null,
                'mileage_out' => $data['mileage_out'] ?? null,
                'tow_in' => (bool) ($data['tow_in'] ?? false),
                'diagnosis_text' => $data['diagnosis_text'] ?? null,
                'observations' => $data['observations'] ?? null,
                'status' => $status,
                'approval_status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'paid_total' => 0,
            ]);

            if (!empty($data['previous_workshop_movement_id'])) {
                $previous = WorkshopMovement::query()
                    ->where('id', (int) $data['previous_workshop_movement_id'])
                    ->where('company_id', (int) $branch->company_id)
                    ->first();

                if (!$previous || (int) $previous->vehicle_id !== (int) $vehicle->id) {
                    throw new \RuntimeException('La OS de garantia referenciada no coincide con el vehiculo actual.');
                }
            }

            if (!empty($data['appointment_id'])) {
                $appointment = Appointment::query()
                    ->where('id', (int) $data['appointment_id'])
                    ->where('branch_id', $branchId)
                    ->where('company_id', (int) $branch->company_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($appointment->movement_id) {
                    throw new \RuntimeException('La cita ya fue convertida a OS.');
                }

                $appointment->update([
                    'movement_id' => $movement->id,
                    'status' => 'arrived',
                ]);
            }

            if (!empty($data['mileage_in'])) {
                $this->logVehicleMileage($vehicle->id, $workshop->id, (int) $data['mileage_in'], 'INTAKE', $userId, 'Kilometraje de ingreso');
                $vehicle->update(['current_mileage' => (int) $data['mileage_in']]);
            }

            $this->recordStatusChange($workshop->id, null, $status, $userId, 'Creacion de OS');
            $this->cloneReusablePdiChecklist($workshop, $vehicle->id, $userId);
            $this->audit((int) $workshop->id, $userId, 'OS_CREATED', [
                'movement_id' => (int) $movement->id,
                'status' => $status,
                'vehicle_id' => (int) $vehicle->id,
                'client_person_id' => (int) $client->id,
            ]);

            return $workshop;
        });
    }

    public function updateOrder(WorkshopMovement $order, array $data): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $data) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->locked_at && !$this->isAdminUser()) {
                throw new \RuntimeException('La OS esta bloqueada. Solo un admin puede reabrirla.');
            }

            $this->assertWorkshopInCurrentBranch($order);

            $currentStatus = (string) $order->status;
            $nextStatus = (string) ($data['status'] ?? $currentStatus);
            $userId = (int) (Auth::id() ?? 0);

            if ($nextStatus !== $currentStatus) {
                $this->changeStatus($order, $nextStatus, $userId, (string) ($data['comment'] ?? 'Cambio de estado manual'));
            }

            if ($order->sales_movement_id && $this->payloadTouchesFinancialStructure($data)) {
                throw new \RuntimeException('La OS ya fue facturada. No se puede editar estructura financiera.');
            }

            $updateData = [
                'intake_date' => $data['intake_date'] ?? $order->intake_date,
                'delivery_date' => $data['delivery_date'] ?? $order->delivery_date,
                'mileage_in' => $data['mileage_in'] ?? $order->mileage_in,
                'mileage_out' => $data['mileage_out'] ?? $order->mileage_out,
                'tow_in' => array_key_exists('tow_in', $data) ? (bool) $data['tow_in'] : $order->tow_in,
                'diagnosis_text' => $data['diagnosis_text'] ?? $order->diagnosis_text,
                'observations' => $data['observations'] ?? $order->observations,
            ];

            $order->update($updateData);
            $this->audit((int) $order->id, $userId, 'OS_UPDATED', ['data' => $updateData]);

            if (array_key_exists('mileage_in', $data) && $data['mileage_in'] !== null) {
                $this->logVehicleMileage((int) $order->vehicle_id, (int) $order->id, (int) $data['mileage_in'], 'INTAKE', $userId, 'Actualizacion de km ingreso');
                $order->vehicle?->update(['current_mileage' => (int) $data['mileage_in']]);
            }

            if (array_key_exists('mileage_out', $data) && $data['mileage_out'] !== null) {
                $this->logVehicleMileage((int) $order->vehicle_id, (int) $order->id, (int) $data['mileage_out'], 'DELIVERY', $userId, 'Actualizacion de km salida');
                $order->vehicle?->update(['current_mileage' => (int) $data['mileage_out']]);
            }

            return $order->fresh();
        });
    }

    public function addDetail(WorkshopMovement $order, array $data): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($order, $data) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);
            $this->ensureOrderAllowsLineChanges($order);

            $lineType = (string) $data['line_type'];
            $qty = round((float) $data['qty'], 6);
            $unitPrice = round((float) $data['unit_price'], 6);
            $discount = round((float) ($data['discount_amount'] ?? 0), 6);

            if (
                $lineType === 'LABOR'
                && $order->previous_workshop_movement_id
                && $this->paramBool('WS_NO_LABOR_CHARGE_WARRANTY', (int) $order->branch_id, true)
            ) {
                $unitPrice = 0;
            }

            if ($qty <= 0) {
                throw new \RuntimeException('La cantidad debe ser mayor a 0.');
            }
            if ($unitPrice < 0) {
                throw new \RuntimeException('El precio unitario no puede ser negativo.');
            }
            if ($lineType === 'PART' && empty($data['product_id'])) {
                throw new \RuntimeException('La linea de repuesto requiere producto.');
            }

            $taxRateValue = 0;
            if (!empty($data['tax_rate_id'])) {
                $taxRateValue = ((float) TaxRate::query()->where('id', (int) $data['tax_rate_id'])->value('tax_rate')) / 100;
            } else {
                $taxRateValue = $this->resolveDefaultTaxRate();
            }

            $gross = $qty * $unitPrice;
            $net = max(0, $gross - $discount);
            $subtotal = $taxRateValue > 0 ? ($net / (1 + $taxRateValue)) : $net;
            $tax = $net - $subtotal;

            $detail = WorkshopMovementDetail::query()->create([
                'workshop_movement_id' => $order->id,
                'line_type' => $lineType,
                'stock_status' => $lineType === 'PART' ? 'pending' : 'not_applicable',
                'service_id' => $data['service_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'description' => $data['description'],
                'qty' => $qty,
                'reserved_qty' => 0,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_rate_id' => $data['tax_rate_id'] ?? null,
                'subtotal' => round($subtotal, 6),
                'tax' => round($tax, 6),
                'total' => round($net, 6),
                'technician_person_id' => $data['technician_person_id'] ?? null,
            ]);

            $this->recalculateOrderTotals($order);
            $this->audit((int) $order->id, (int) (Auth::id() ?? 0), 'OS_LINE_ADDED', [
                'detail_id' => (int) $detail->id,
                'line_type' => $lineType,
                'qty' => $qty,
                'unit_price' => $unitPrice,
            ]);

            return $detail;
        });
    }

    public function removeDetail(WorkshopMovementDetail $detail): void
    {
        DB::transaction(function () use ($detail) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);
            $this->assertWorkshopInCurrentBranch($order);
            $this->ensureOrderAllowsLineChanges($order);

            if ($detail->sales_movement_id || $order->sales_movement_id) {
                throw new \RuntimeException('No se puede eliminar una linea ya facturada.');
            }

            if ((bool) $detail->stock_consumed) {
                $this->revertConsumedDetail($detail);
            }

            $this->releaseReservations($detail, 'released');

            $detail->delete();
            $this->recalculateOrderTotals($order);
            $this->audit((int) $order->id, (int) (Auth::id() ?? 0), 'OS_LINE_REMOVED', ['detail_id' => (int) $detail->id]);
        });
    }

    public function updateDetail(WorkshopMovementDetail $detail, array $data, int $branchId, int $userId, string $userName): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($detail, $data, $branchId, $userId, $userName) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);
            $this->assertWorkshopInCurrentBranch($order);
            $this->ensureOrderAllowsLineChanges($order);

            if ((int) $order->branch_id !== $branchId) {
                throw new \RuntimeException('La linea no pertenece a la sucursal activa.');
            }

            $oldQty = (float) $detail->qty;
            $newQty = round((float) ($data['qty'] ?? $oldQty), 6);
            $newUnitPrice = round((float) ($data['unit_price'] ?? $detail->unit_price), 6);
            $newDiscount = round((float) ($data['discount_amount'] ?? $detail->discount_amount), 6);

            if ($newQty <= 0) {
                throw new \RuntimeException('La cantidad debe ser mayor a cero.');
            }
            if ($newUnitPrice < 0) {
                throw new \RuntimeException('El precio no puede ser negativo.');
            }

            if ($detail->line_type === 'PART' && (bool) $detail->stock_consumed && abs($newQty - $oldQty) > 0.000001) {
                $diff = round($newQty - $oldQty, 6);
                if ($diff > 0) {
                    $available = $this->availableStockForDetail((int) $detail->product_id, $branchId, (int) $detail->id);
                    $allowNegative = $this->paramBool('WS_ALLOW_NEGATIVE_STOCK', $branchId, false);
                    if (!$allowNegative && $available < $diff) {
                        throw new \RuntimeException('Stock insuficiente para incrementar cantidad consumida.');
                    }
                    $this->createWarehouseAdjustmentMovement($order, $detail, $diff, 'subtract', $branchId, $userId, $userName);
                } else {
                    $this->createWarehouseAdjustmentMovement($order, $detail, abs($diff), 'add', $branchId, $userId, $userName);
                }
            }

            $taxRateValue = 0;
            if (!empty($data['tax_rate_id'])) {
                $taxRateValue = ((float) TaxRate::query()->where('id', (int) $data['tax_rate_id'])->value('tax_rate')) / 100;
            } elseif ($detail->tax_rate_id) {
                $taxRateValue = ((float) TaxRate::query()->where('id', (int) $detail->tax_rate_id)->value('tax_rate')) / 100;
            } else {
                $taxRateValue = $this->resolveDefaultTaxRate();
            }

            $gross = $newQty * $newUnitPrice;
            $net = max(0, $gross - $newDiscount);
            $subtotal = $taxRateValue > 0 ? ($net / (1 + $taxRateValue)) : $net;
            $tax = $net - $subtotal;

            $detail->update([
                'description' => $data['description'] ?? $detail->description,
                'qty' => $newQty,
                'unit_price' => $newUnitPrice,
                'discount_amount' => $newDiscount,
                'tax_rate_id' => $data['tax_rate_id'] ?? $detail->tax_rate_id,
                'subtotal' => round($subtotal, 6),
                'tax' => round($tax, 6),
                'total' => round($net, 6),
                'technician_person_id' => $data['technician_person_id'] ?? $detail->technician_person_id,
            ]);

            $this->recalculateOrderTotals($order);

            return $detail->fresh();
        });
    }

    public function syncIntakeAndDamages(WorkshopMovement $order, array $inventory, array $damages): void
    {
        DB::transaction(function () use ($order, $inventory, $damages) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($this->isChecklistLocked($order) && !$this->isAdminUser()) {
                throw new \RuntimeException('No se puede modificar inspeccion/inventario despues de aprobado.');
            }

            foreach ($inventory as $itemKey => $present) {
                if (!is_string($itemKey) || trim($itemKey) === '') {
                    continue;
                }

                WorkshopIntakeInventory::query()->updateOrCreate(
                    [
                        'workshop_movement_id' => $order->id,
                        'item_key' => trim($itemKey),
                    ],
                    [
                        'present' => (bool) $present,
                    ]
                );
            }

            WorkshopPreexistingDamage::query()->where('workshop_movement_id', $order->id)->delete();
            foreach ($damages as $damage) {
                $description = trim((string) ($damage['description'] ?? ''));
                if ($description === '') {
                    continue;
                }

                WorkshopPreexistingDamage::query()->create([
                    'workshop_movement_id' => $order->id,
                    'side' => strtoupper((string) ($damage['side'] ?? 'FRONT')),
                    'description' => $description,
                    'severity' => $damage['severity'] ?? null,
                    'photo_path' => $damage['photo_path'] ?? null,
                ]);
            }
        });
    }

    public function syncChecklist(WorkshopMovement $order, string $type, int $userId, array $items): WorkshopChecklist
    {
        return DB::transaction(function () use ($order, $type, $userId, $items) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($this->isChecklistLocked($order) && !$this->isAdminUser()) {
                throw new \RuntimeException('El checklist queda congelado cuando la OS esta aprobada o posterior.');
            }

            $allowedTypes = ['OS_INTAKE', 'GP_ACTIVATION', 'PDI', 'MAINTENANCE'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new \RuntimeException('Tipo de checklist invalido.');
            }

            $version = (int) WorkshopChecklist::query()
                ->where('workshop_movement_id', $order->id)
                ->where('type', $type)
                ->max('version');

            $checklist = WorkshopChecklist::query()->create([
                'workshop_movement_id' => $order->id,
                'type' => $type,
                'version' => $version + 1,
                'created_by' => $userId,
            ]);

            foreach ($items as $index => $item) {
                $result = strtoupper(trim((string) ($item['result'] ?? '')));
                if ($result !== '') {
                    $this->validateChecklistResult($type, $result);
                }

                $checklist->items()->create([
                    'group' => $item['group'] ?? null,
                    'label' => $item['label'] ?? ('ITEM ' . ($index + 1)),
                    'result' => $result !== '' ? $result : null,
                    'action' => $item['action'] ?? null,
                    'observation' => $item['observation'] ?? null,
                    'order_num' => (int) ($item['order_num'] ?? ($index + 1)),
                ]);
            }

            return $checklist;
        });
    }

    public function approveOrder(WorkshopMovement $order, int $userId, ?string $approvalNote = null): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $userId, $approvalNote) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($order->status !== 'awaiting_approval') {
                throw new \RuntimeException('La OS debe estar en estado awaiting_approval para aprobar.');
            }

            $this->changeStatus($order, 'approved', $userId, 'Aprobacion del cliente');

            $order->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
                'approval_note' => $approvalNote,
            ]);

            return $order->fresh();
        });
    }

    public function decideApproval(WorkshopMovement $order, string $decision, int $userId, ?string $note = null): WorkshopMovement
    {
        $decision = strtolower(trim($decision));

        return DB::transaction(function () use ($order, $decision, $userId, $note) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($order->status !== 'awaiting_approval') {
                throw new \RuntimeException('La OS debe estar esperando aprobacion para registrar decision.');
            }

            if (!in_array($decision, ['approved', 'rejected', 'partial'], true)) {
                throw new \RuntimeException('Decision de aprobacion invalida.');
            }

            if ($decision === 'approved') {
                return $this->approveOrder($order, $userId, $note);
            }

            if ($decision === 'partial') {
                $this->changeStatus($order, 'approved', $userId, 'Aprobacion parcial del cliente');
                $order->update([
                    'approval_status' => 'partial',
                    'approved_at' => now(),
                    'approved_by' => $userId,
                    'approval_note' => $note,
                ]);

                return $order->fresh();
            }

            $this->changeStatus($order, 'diagnosis', $userId, 'Aprobacion rechazada por cliente');
            $order->update([
                'approval_status' => 'rejected',
                'approval_note' => $note,
            ]);

            return $order->fresh();
        });
    }

    public function generateQuotation(WorkshopMovement $order, int $userId, ?string $note = null): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $userId, $note) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if (in_array($order->status, ['cancelled', 'delivered'], true)) {
                throw new \RuntimeException('No se puede cotizar una OS cerrada.');
            }
            if ($order->details()->count() <= 0) {
                throw new \RuntimeException('Agregue lineas antes de generar cotizacion.');
            }

            if ($order->status !== 'awaiting_approval') {
                $this->changeStatus($order, 'awaiting_approval', $userId, $note ?: 'Cotizacion enviada a cliente');
            }

            $order->update([
                'approval_status' => 'pending',
                'approval_note' => $note ?: $order->approval_note,
            ]);

            return $order->fresh();
        });
    }

    public function reservePart(WorkshopMovementDetail $detail, int $branchId, int $userId): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($detail, $branchId, $userId) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($order->branch_id !== $branchId) {
                throw new \RuntimeException('La linea no pertenece a la sucursal actual.');
            }
            if ($detail->line_type !== 'PART') {
                throw new \RuntimeException('Solo los repuestos pueden reservar stock.');
            }
            if (!in_array($order->status, ['approved', 'in_progress'], true)) {
                throw new \RuntimeException('Solo se puede reservar stock cuando la OS esta aprobada o en reparacion.');
            }
            if ((bool) $detail->stock_consumed) {
                throw new \RuntimeException('La linea ya fue consumida.');
            }

            $required = (float) $detail->qty;
            $available = $this->availableStockForDetail((int) $detail->product_id, $branchId, (int) $detail->id);
            if ($available < $required) {
                throw new \RuntimeException('Stock insuficiente para reservar. Disponible: ' . $available . ', requerido: ' . $required);
            }

            WorkshopStockReservation::query()
                ->where('workshop_movement_detail_id', $detail->id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                ]);

            WorkshopStockReservation::query()->create([
                'workshop_movement_detail_id' => $detail->id,
                'product_id' => (int) $detail->product_id,
                'branch_id' => $branchId,
                'qty' => $required,
                'status' => 'reserved',
                'created_by' => $userId,
            ]);

            $detail->update([
                'stock_status' => 'reserved',
                'reserved_qty' => $required,
            ]);

            return $detail->fresh();
        });
    }

    public function releasePartReservation(WorkshopMovementDetail $detail): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($detail) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($detail->line_type !== 'PART') {
                throw new \RuntimeException('Solo los repuestos pueden liberar reserva.');
            }

            $this->releaseReservations($detail, 'released');
            $detail->update([
                'stock_status' => 'pending',
                'reserved_qty' => 0,
            ]);

            return $detail->fresh();
        });
    }

    public function consumePart(WorkshopMovementDetail $detail, int $branchId, int $userId, string $userName, ?string $comment = null): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($detail, $branchId, $userId, $userName, $comment) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);

            if ($order->branch_id !== $branchId) {
                throw new \RuntimeException('La linea no pertenece a la sucursal actual.');
            }

            if ($detail->line_type !== 'PART') {
                throw new \RuntimeException('Solo se puede consumir stock para lineas de tipo PART.');
            }
            if (!$detail->product_id) {
                throw new \RuntimeException('La linea no tiene producto asociado.');
            }
            if ((bool) $detail->stock_consumed) {
                throw new \RuntimeException('La linea ya fue consumida.');
            }
            if (in_array($order->status, ['draft', 'diagnosis', 'awaiting_approval', 'cancelled', 'delivered'], true)) {
                throw new \RuntimeException('La OS no permite consumo en su estado actual.');
            }

            $productBranch = ProductBranch::query()
                ->where('branch_id', $branchId)
                ->where('product_id', (int) $detail->product_id)
                ->lockForUpdate()
                ->first();

            if (!$productBranch) {
                throw new \RuntimeException('El producto no esta configurado en la sucursal actual.');
            }

            $qty = (float) $detail->qty;
            $available = $this->availableStockForDetail((int) $detail->product_id, $branchId, (int) $detail->id);
            $allowNegative = $this->paramBool('WS_ALLOW_NEGATIVE_STOCK', $branchId, false);
            if (!$allowNegative && $available < $qty) {
                throw new \RuntimeException('Stock insuficiente para consumir. Disponible: ' . $available . ', requerido: ' . $qty);
            }

            $warehouseTypeId = $this->resolveMovementTypeId('ALMACEN_TALLER');
            $warehouseDocId = $this->resolveDocumentTypeId($warehouseTypeId, 'Salida por Servicio', 'subtract');

            $movement = Movement::query()->create([
                'number' => $this->generateMovementNumber($branchId, $warehouseDocId),
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $order->client_person_id,
                'person_name' => trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')),
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $comment ?: 'Salida por servicio OS #' . $order->movement?->number,
                'status' => 'A',
                'movement_type_id' => $warehouseTypeId,
                'document_type_id' => $warehouseDocId,
                'branch_id' => $branchId,
                'parent_movement_id' => $order->movement_id,
            ]);

            $warehouse = WarehouseMovement::query()->create([
                'status' => 'FINALIZED',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            $product = Product::query()->findOrFail((int) $detail->product_id);
            $unitId = (int) ($product->base_unit_id ?: $this->resolveDefaultUnitId());

            WarehouseMovementDetail::query()->create([
                'warehouse_movement_id' => $warehouse->id,
                'product_id' => (int) $detail->product_id,
                'product_snapshot' => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'description' => $product->description,
                ],
                'unit_id' => $unitId,
                'quantity' => $qty,
                'comment' => 'Consumo desde OS #' . $order->movement?->number,
                'status' => 'A',
                'branch_id' => $branchId,
            ]);

            $newStock = (float) $productBranch->stock - $qty;
            if (!$allowNegative) {
                $newStock = max(0, $newStock);
            }

            $productBranch->update(['stock' => $newStock]);

            $detail->update([
                'stock_consumed' => true,
                'consumed_at' => now(),
                'warehouse_movement_id' => $warehouse->id,
                'stock_status' => 'consumed',
                'reserved_qty' => 0,
            ]);

            $this->releaseReservations($detail, 'consumed');

            if ($order->status === 'approved') {
                $this->changeStatus($order, 'in_progress', $userId, 'Inicio de reparacion por consumo de repuestos');
            }

            $this->audit((int) $order->id, $userId, 'PART_CONSUMED', [
                'detail_id' => (int) $detail->id,
                'product_id' => (int) $detail->product_id,
                'qty' => $qty,
                'warehouse_movement_id' => (int) $warehouse->id,
            ]);

            return $detail->fresh();
        });
    }

    public function returnConsumedPart(WorkshopMovementDetail $detail): WorkshopMovementDetail
    {
        return DB::transaction(function () use ($detail) {
            $detail = WorkshopMovementDetail::query()->lockForUpdate()->findOrFail($detail->id);
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($detail->workshop_movement_id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($detail->line_type !== 'PART') {
                throw new \RuntimeException('Solo lineas de repuesto pueden devolverse.');
            }
            if (!(bool) $detail->stock_consumed) {
                throw new \RuntimeException('La linea no fue consumida.');
            }
            if ($detail->sales_movement_id || $order->sales_movement_id) {
                throw new \RuntimeException('No puede devolver repuesto ya facturado.');
            }
            if (in_array($order->status, ['delivered', 'cancelled'], true)) {
                throw new \RuntimeException('No se puede devolver repuestos en OS entregada o anulada.');
            }

            $this->revertConsumedDetail($detail);
            $this->audit((int) $order->id, (int) (Auth::id() ?? 0), 'PART_RETURNED', [
                'detail_id' => (int) $detail->id,
                'product_id' => (int) $detail->product_id,
                'qty' => (float) $detail->qty,
            ]);

            return $detail->fresh();
        });
    }

    public function generateSale(
        WorkshopMovement $order,
        int $documentTypeId,
        int $branchId,
        int $userId,
        string $userName,
        ?string $comment = null,
        ?array $detailIds = null
    ): SalesMovement {
        return DB::transaction(function () use ($order, $documentTypeId, $branchId, $userId, $userName, $comment, $detailIds) {
            $order = WorkshopMovement::query()->with(['details', 'client'])->lockForUpdate()->findOrFail($order->id);
            if ($order->branch_id !== $branchId) {
                throw new \RuntimeException('La OS no pertenece a la sucursal actual.');
            }

            if (in_array($order->status, ['draft', 'diagnosis', 'awaiting_approval', 'cancelled'], true)) {
                throw new \RuntimeException('La OS aun no puede generar venta en su estado actual.');
            }
            if ($order->details->isEmpty()) {
                throw new \RuntimeException('La OS no tiene lineas para facturar.');
            }

            $allowMultiFull = $this->paramBool('WS_ALLOW_MULTI_FULL_SALES', $branchId, false);
            if ($order->sales_movement_id && !$allowMultiFull && empty($detailIds)) {
                throw new \RuntimeException('La OS ya tiene venta vinculada. Solo puede facturar adicionales pendientes.');
            }

            $candidateDetails = $order->details
                ->filter(fn ($line) => !$line->sales_movement_id)
                ->values();

            if (!empty($detailIds)) {
                $detailIndex = $candidateDetails->keyBy('id');
                $candidateDetails = collect($detailIds)
                    ->map(fn ($id) => $detailIndex->get((int) $id))
                    ->filter()
                    ->values();
            }

            if ($candidateDetails->isEmpty()) {
                throw new \RuntimeException('No hay lineas pendientes para facturar.');
            }

            $movementTypeId = $this->resolveSaleMovementTypeId();
            $documentType = DocumentType::query()
                ->where('id', $documentTypeId)
                ->where('movement_type_id', $movementTypeId)
                ->first();

            if (!$documentType) {
                throw new \RuntimeException('El tipo de documento no corresponde a ventas.');
            }

            $movement = Movement::query()->create([
                'number' => $this->generateMovementNumber($branchId, $documentTypeId),
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $order->client_person_id,
                'person_name' => trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')),
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $comment ?: 'Venta generada desde OS #' . $order->movement?->number,
                'status' => 'A',
                'movement_type_id' => $movementTypeId,
                'document_type_id' => $documentTypeId,
                'branch_id' => $branchId,
                'parent_movement_id' => $order->movement_id,
            ]);

            $branchSnapshot = [
                'id' => $branchId,
                'company_id' => $order->company_id,
            ];

            $sale = SalesMovement::query()->create([
                'branch_snapshot' => $branchSnapshot,
                'series' => '001',
                'year' => (string) now()->year,
                'detail_type' => 'DETAILED',
                'consumption' => 'N',
                'payment_type' => 'CREDIT',
                'status' => 'N',
                'sale_type' => 'RETAIL',
                'currency' => (string) $this->paramText('WS_CURRENCY', $branchId, 'PEN'),
                'exchange_rate' => 1,
                'subtotal' => (float) $candidateDetails->sum('subtotal'),
                'tax' => (float) $candidateDetails->sum('tax'),
                'total' => (float) $candidateDetails->sum('total'),
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            foreach ($candidateDetails as $detail) {
                $product = $detail->product_id ? Product::query()->find($detail->product_id) : null;
                $taxRate = $detail->tax_rate_id ? TaxRate::query()->find($detail->tax_rate_id) : null;
                $unitId = (int) ($product?->base_unit_id ?: $this->resolveDefaultUnitId());

                SalesMovementDetail::query()->create([
                    'detail_type' => 'DETAILED',
                    'sales_movement_id' => $sale->id,
                    'code' => $product?->code ?: ('WS-' . $detail->id),
                    'description' => $detail->description,
                    'product_id' => $product?->id,
                    'product_snapshot' => $product ? [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ] : null,
                    'unit_id' => $unitId,
                    'tax_rate_id' => $taxRate?->id,
                    'tax_rate_snapshot' => $taxRate ? [
                        'id' => $taxRate->id,
                        'description' => $taxRate->description,
                        'tax_rate' => $taxRate->tax_rate,
                    ] : null,
                    'quantity' => (float) $detail->qty,
                    'amount' => (float) $detail->total,
                    'discount_percentage' => 0,
                    'original_amount' => (float) $detail->subtotal,
                    'comment' => 'OS #' . $order->movement?->number,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                $detail->update(['sales_movement_id' => $sale->id]);
            }

            $remainingUninvoiced = WorkshopMovementDetail::query()
                ->where('workshop_movement_id', $order->id)
                ->whereNull('sales_movement_id')
                ->exists();

            if (!$remainingUninvoiced || !$order->sales_movement_id) {
                $order->update(['sales_movement_id' => $sale->id]);
            }

            $this->audit((int) $order->id, $userId, 'SALE_GENERATED', [
                'sales_movement_id' => (int) $sale->id,
                'total' => (float) $sale->total,
                'line_count' => $candidateDetails->count(),
            ]);

            return $sale;
        });
    }

    public function cancelOrder(
        WorkshopMovement $order,
        int $userId,
        string $reason,
        bool $autoRefund = false,
        ?int $cashRegisterId = null,
        ?int $paymentMethodId = null,
        ?int $branchId = null,
        ?string $userName = null
    ): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $userId, $reason, $autoRefund, $cashRegisterId, $paymentMethodId, $branchId, $userName) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($order->status === 'cancelled') {
                return $order;
            }

            $consumedDetails = WorkshopMovementDetail::query()
                ->where('workshop_movement_id', $order->id)
                ->where('stock_consumed', true)
                ->lockForUpdate()
                ->get();
            foreach ($consumedDetails as $detail) {
                $this->revertConsumedDetail($detail);
            }

            if ((float) $order->paid_total > 0) {
                if (!$autoRefund) {
                    throw new \RuntimeException('La OS tiene pagos. Active devolucion automatica o ejecute la devolucion antes de anular.');
                }
                if (!$cashRegisterId || !$paymentMethodId) {
                    throw new \RuntimeException('Debe indicar caja y metodo para revertir pagos al anular.');
                }
                $activeBranchId = $branchId ?: (int) $order->branch_id;
                $activeUserName = $userName ?: (string) (Auth::user()?->name ?? 'Sistema');

                $this->refundPayment(
                    $order,
                    $cashRegisterId,
                    $paymentMethodId,
                    (float) $order->paid_total,
                    $activeBranchId,
                    $userId,
                    $activeUserName,
                    'Anulacion OS'
                );

                $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            }

            if ($order->sales_movement_id) {
                $sale = SalesMovement::query()->find($order->sales_movement_id);
                if ($sale) {
                    $sale->update(['status' => 'C']);
                    $sale->movement?->update([
                        'status' => 'C',
                        'comment' => trim((string) $sale->movement?->comment) . ' | ANULADA DESDE OS',
                    ]);
                }
            }

            $this->changeStatus($order, 'cancelled', $userId, $reason);
            $order->update([
                'locked_at' => now(),
                'observations' => trim((string) ($order->observations ?? '') . PHP_EOL . '[ANULACION] ' . $reason),
            ]);

            if ($order->appointment_id) {
                Appointment::query()->where('id', $order->appointment_id)->update(['status' => 'cancelled']);
            }

            $this->audit((int) $order->id, $userId, 'OS_CANCELLED', ['reason' => $reason]);

            return $order->fresh();
        });
    }

    public function reopenOrder(WorkshopMovement $order, int $userId, string $reason): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $userId, $reason) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if (!$this->isAdminUser()) {
                throw new \RuntimeException('Solo un admin puede reabrir una OS.');
            }

            if (!in_array($order->status, ['cancelled', 'delivered'], true)) {
                throw new \RuntimeException('Solo se puede reabrir una OS cancelada o entregada.');
            }

            $this->changeStatus($order, 'draft', $userId, 'Reapertura admin: ' . $reason);
            $order->update([
                'locked_at' => null,
                'observations' => trim((string) ($order->observations ?? '') . PHP_EOL . '[REAPERTURA] ' . $reason),
            ]);

            return $order->fresh();
        });
    }

    public function registerWarranty(WorkshopMovement $order, ?int $detailId, int $days, ?string $note = null): WorkshopWarranty
    {
        return DB::transaction(function () use ($order, $detailId, $days, $note) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($days <= 0) {
                throw new \RuntimeException('La garantia debe tener al menos 1 día.');
            }

            if ($detailId) {
                $detail = WorkshopMovementDetail::query()
                    ->where('id', $detailId)
                    ->where('workshop_movement_id', $order->id)
                    ->first();
                if (!$detail) {
                    throw new \RuntimeException('La linea seleccionada no pertenece a la OS.');
                }
            }

            return WorkshopWarranty::query()->create([
                'workshop_movement_id' => $order->id,
                'workshop_movement_detail_id' => $detailId,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays($days)->toDateString(),
                'status' => 'active',
                'note' => $note,
            ]);
        });
    }

    public function registerPayment(
        WorkshopMovement $order,
        int $cashRegisterId,
        array $paymentMethods,
        int $branchId,
        int $userId,
        string $userName,
        ?string $comment = null
    ): CashMovements {
        return DB::transaction(function () use ($order, $cashRegisterId, $paymentMethods, $branchId, $userId, $userName, $comment) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->branch_id !== $branchId) {
                throw new \RuntimeException('La OS no pertenece a la sucursal actual.');
            }

            if ($order->status === 'cancelled') {
                throw new \RuntimeException('No se puede registrar pago en una OS anulada.');
            }

            $this->assertOpenCashShift($branchId);

            $cashRegister = CashRegister::query()->findOrFail($cashRegisterId);
            if (Schema::hasColumn('cash_registers', 'branch_id') && $cashRegister->branch_id && (int) $cashRegister->branch_id !== $branchId) {
                throw new \RuntimeException('La caja seleccionada no pertenece a la sucursal actual.');
            }
            $shift = Shift::query()->where('branch_id', $branchId)->orderBy('id')->first();
            if (!$shift) {
                throw new \RuntimeException('No hay turno configurado para la sucursal.');
            }

            $amount = 0;
            foreach ($paymentMethods as $payment) {
                $value = round((float) ($payment['amount'] ?? 0), 6);
                if ($value <= 0) {
                    throw new \RuntimeException('Todos los montos de pago deben ser mayores a cero.');
                }
                $amount += $value;
            }

            $currentDebt = max(0, (float) $order->total - (float) $order->paid_total);
            if ($amount > $currentDebt + 0.0001) {
                throw new \RuntimeException('El pago excede la deuda pendiente de la OS.');
            }

            $cashMovement = null;
            if ($order->cash_movement_id) {
                $cashMovement = CashMovements::query()->lockForUpdate()->find($order->cash_movement_id);
            }

            if (!$cashMovement) {
                $movementTypeId = $this->resolveCashMovementTypeId();
                $documentTypeId = $this->resolveDocumentTypeId($movementTypeId, 'Cobro Taller', 'none');
                $paymentConceptId = $this->resolvePaymentConceptId();

                $cashMovementEntity = Movement::query()->create([
                    'number' => $this->generateCashMovementNumber($branchId, $cashRegisterId, $paymentConceptId, (int) $shift->id),
                    'moved_at' => now(),
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'person_id' => $order->client_person_id,
                    'person_name' => trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')),
                    'responsible_id' => $userId,
                    'responsible_name' => $userName,
                    'comment' => $comment ?: 'Cobro OS #' . $order->movement?->number,
                    'status' => '1',
                    'movement_type_id' => $movementTypeId,
                    'document_type_id' => $documentTypeId,
                    'branch_id' => $branchId,
                    'parent_movement_id' => $order->sales_movement_id ?: $order->movement_id,
                ]);

                $cashMovement = CashMovements::query()->create([
                    'payment_concept_id' => $paymentConceptId,
                    'currency' => (string) $this->paramText('WS_CURRENCY', $branchId, 'PEN'),
                    'exchange_rate' => 1,
                    'total' => $amount,
                    'cash_register_id' => $cashRegisterId,
                    'cash_register' => (string) ($cashRegister->number ?? 'CAJA'),
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                    ],
                    'movement_id' => $cashMovementEntity->id,
                    'branch_id' => $branchId,
                ]);

                $order->update(['cash_movement_id' => $cashMovement->id]);
            } else {
                $cashMovement->update([
                    'total' => (float) $cashMovement->total + $amount,
                ]);
            }

            foreach ($paymentMethods as $payment) {
                $method = PaymentMethod::query()->findOrFail((int) $payment['payment_method_id']);
                $gateway = !empty($payment['payment_gateway_id'])
                    ? PaymentGateways::query()->find((int) $payment['payment_gateway_id'])
                    : null;
                $card = !empty($payment['card_id'])
                    ? Card::query()->find((int) $payment['card_id'])
                    : null;

                CashMovementDetail::query()->create([
                    'cash_movement_id' => $cashMovement->id,
                    'type' => 'PAGADO',
                    'due_at' => null,
                    'paid_at' => now(),
                    'payment_method_id' => $method->id,
                    'payment_method' => $method->description ?? '',
                    'number' => (string) ($payment['reference'] ?? ($cashMovement->movement?->number ?? '')),
                    'card_id' => $card?->id,
                    'card' => $card?->description ?? '',
                    'bank_id' => $payment['bank_id'] ?? null,
                    'bank' => '',
                    'digital_wallet_id' => $payment['digital_wallet_id'] ?? null,
                    'digital_wallet' => '',
                    'payment_gateway_id' => $gateway?->id,
                    'payment_gateway' => $gateway?->description ?? '',
                    'amount' => (float) $payment['amount'],
                    'comment' => $comment ?: 'Pago OS #' . $order->movement?->number,
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }

            $newPaidTotal = round((float) $order->paid_total + $amount, 6);
            $paymentStatus = 'pending';
            if ($newPaidTotal > 0 && $newPaidTotal < (float) $order->total) {
                $paymentStatus = 'partial';
            }
            if ($newPaidTotal >= (float) $order->total) {
                $paymentStatus = 'paid';
            }

            $order->update([
                'paid_total' => $newPaidTotal,
                'payment_status' => $paymentStatus,
            ]);
            $this->audit((int) $order->id, $userId, 'PAYMENT_REGISTERED', [
                'cash_movement_id' => (int) $cashMovement->id,
                'amount' => $amount,
                'payment_status' => $paymentStatus,
            ]);

            return $cashMovement->fresh();
        });
    }

    public function markDelivered(WorkshopMovement $order, ?int $mileageOut = null): WorkshopMovement
    {
        return DB::transaction(function () use ($order, $mileageOut) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($order->status !== 'finished') {
                throw new \RuntimeException('La OS debe estar terminada para entregar.');
            }

            $allowDebtDelivery = $this->paramBool('WS_ALLOW_DELIVERY_WITH_DEBT', (int) $order->branch_id, false);
            if (!$allowDebtDelivery && (float) $order->paid_total + 0.0001 < (float) $order->total) {
                throw new \RuntimeException('No se puede entregar con deuda pendiente.');
            }

            $requirePdi = $this->paramBool('WS_REQUIRE_PDI_FOR_DELIVERY', (int) $order->branch_id, true);
            if ($requirePdi) {
                $hasPdi = WorkshopChecklist::query()
                    ->where('workshop_movement_id', $order->id)
                    ->where('type', 'PDI')
                    ->exists();

                if (!$hasPdi) {
                    throw new \RuntimeException('La entrega requiere checklist PDI.');
                }
            }

            $requiredChecklistTypes = array_values(array_filter(array_map(
                fn ($value) => strtoupper(trim((string) $value)),
                explode(',', $this->paramText('WS_REQUIRED_CHECKLIST_TYPES', (int) $order->branch_id, 'PDI,OS_INTAKE'))
            )));
            foreach ($requiredChecklistTypes as $checklistType) {
                $exists = WorkshopChecklist::query()
                    ->where('workshop_movement_id', $order->id)
                    ->where('type', $checklistType)
                    ->exists();
                if (!$exists) {
                    throw new \RuntimeException('Falta checklist obligatorio para entrega: ' . $checklistType);
                }
            }

            $finalMileage = $mileageOut ?? $order->mileage_out;
            if ($finalMileage === null) {
                throw new \RuntimeException('Debe registrar kilometraje de salida para entregar.');
            }

            $userId = (int) (Auth::id() ?? 0);
            $this->changeStatus($order, 'delivered', $userId, 'Entrega de vehiculo');

            $order->update([
                'mileage_out' => (int) $finalMileage,
                'delivery_date' => now(),
                'locked_at' => now(),
            ]);

            $this->logVehicleMileage((int) $order->vehicle_id, (int) $order->id, (int) $finalMileage, 'DELIVERY', $userId, 'Entrega de vehiculo');
            $order->vehicle?->update(['current_mileage' => (int) $finalMileage]);
            $this->audit((int) $order->id, $userId, 'OS_DELIVERED', ['mileage_out' => (int) $finalMileage]);

            return $order->fresh();
        });
    }

    public function refundPayment(
        WorkshopMovement $order,
        int $cashRegisterId,
        int $paymentMethodId,
        float $amount,
        int $branchId,
        int $userId,
        string $userName,
        string $reason
    ): CashMovements {
        return DB::transaction(function () use ($order, $cashRegisterId, $paymentMethodId, $amount, $branchId, $userId, $userName, $reason) {
            $order = WorkshopMovement::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertWorkshopInCurrentBranch($order);

            if ($amount <= 0) {
                throw new \RuntimeException('Monto de devolucion invalido.');
            }
            if ((float) $order->paid_total < $amount) {
                throw new \RuntimeException('No puede devolver mas de lo cobrado en la OS.');
            }

            $this->assertOpenCashShift($branchId);
            $cashRegister = CashRegister::query()->findOrFail($cashRegisterId);
            $shift = Shift::query()->where('branch_id', $branchId)->orderBy('id')->first();
            if (!$shift) {
                throw new \RuntimeException('No hay turno configurado para la sucursal.');
            }

            $movementTypeId = $this->resolveCashMovementTypeId();
            $documentTypeId = $this->resolveDocumentTypeId($movementTypeId, 'Devolucion Taller', 'none');
            $paymentConceptId = $this->resolveRefundPaymentConceptId();

            $movement = Movement::query()->create([
                'number' => $this->generateCashMovementNumber($branchId, $cashRegisterId, $paymentConceptId, (int) $shift->id),
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $order->client_person_id,
                'person_name' => trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')),
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => 'Devolucion OS #' . $order->movement?->number . ' - ' . $reason,
                'status' => '1',
                'movement_type_id' => $movementTypeId,
                'document_type_id' => $documentTypeId,
                'branch_id' => $branchId,
                'parent_movement_id' => $order->cash?->movement_id ?: $order->movement_id,
            ]);

            $cashMovement = CashMovements::query()->create([
                'payment_concept_id' => $paymentConceptId,
                'currency' => (string) $this->paramText('WS_CURRENCY', $branchId, 'PEN'),
                'exchange_rate' => 1,
                'total' => $amount,
                'cash_register_id' => $cashRegisterId,
                'cash_register' => (string) ($cashRegister->number ?? 'CAJA'),
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ],
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            $method = PaymentMethod::query()->findOrFail($paymentMethodId);
            CashMovementDetail::query()->create([
                'cash_movement_id' => $cashMovement->id,
                'type' => 'PAGADO',
                'due_at' => null,
                'paid_at' => now(),
                'payment_method_id' => $method->id,
                'payment_method' => $method->description ?? '',
                'number' => $movement->number,
                'card_id' => null,
                'card' => '',
                'bank_id' => null,
                'bank' => '',
                'digital_wallet_id' => null,
                'digital_wallet' => '',
                'payment_gateway_id' => null,
                'payment_gateway' => '',
                'amount' => $amount,
                'comment' => 'Devolucion OS: ' . $reason,
                'status' => 'A',
                'branch_id' => $branchId,
            ]);

            $newPaid = round(max(0, (float) $order->paid_total - $amount), 6);
            $paymentStatus = 'pending';
            if ($newPaid > 0 && $newPaid < (float) $order->total) {
                $paymentStatus = 'partial';
            }
            if ($newPaid >= (float) $order->total) {
                $paymentStatus = 'paid';
            }

            $order->update([
                'paid_total' => $newPaid,
                'payment_status' => $paymentStatus,
            ]);
            $this->audit((int) $order->id, $userId, 'PAYMENT_REFUNDED', [
                'cash_movement_id' => (int) $cashMovement->id,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            return $cashMovement;
        });
    }

    private function changeStatus(WorkshopMovement $order, string $nextStatus, int $userId, ?string $note = null): void
    {
        $currentStatus = (string) $order->status;
        $nextStatus = trim($nextStatus);

        if (!array_key_exists($currentStatus, self::STATUS_TRANSITIONS)) {
            throw new \RuntimeException('Estado actual de OS no reconocido.');
        }

        $isReopenByAdmin = $this->isAdminUser()
            && in_array($currentStatus, ['cancelled', 'delivered'], true)
            && $nextStatus === 'draft';

        if (!in_array($nextStatus, self::STATUS_TRANSITIONS[$currentStatus], true) && !$isReopenByAdmin) {
            throw new \RuntimeException("No se permite pasar de {$currentStatus} a {$nextStatus}.");
        }

        $this->assertCanChangeStatus($currentStatus, $nextStatus);

        if ($nextStatus === 'cancelled') {
            $hasConsumed = WorkshopMovementDetail::query()
                ->where('workshop_movement_id', $order->id)
                ->where('stock_consumed', true)
                ->exists();

            if ($hasConsumed) {
                throw new \RuntimeException('No se puede anular una OS con repuestos consumidos sin revertir.');
            }

            $this->releaseOrderReservations($order);
        }

        $updates = ['status' => $nextStatus];

        if ($nextStatus === 'approved') {
            $updates['approved_at'] = now();
            $updates['approved_by'] = $userId > 0 ? $userId : null;
            $updates['approval_status'] = 'approved';
        }

        if ($nextStatus === 'in_progress' && !$order->started_at) {
            $updates['started_at'] = now();
        }

        if ($nextStatus === 'finished') {
            $updates['finished_at'] = now();
        }

        if ($nextStatus === 'delivered') {
            $updates['delivery_date'] = $order->delivery_date ?: now();
            $updates['locked_at'] = $order->locked_at ?: now();
        }

        if ($isReopenByAdmin) {
            $updates['locked_at'] = null;
        }

        $order->update($updates);
        $this->recordStatusChange((int) $order->id, $currentStatus, $nextStatus, $userId, $note);
    }

    private function ensureOrderAllowsLineChanges(WorkshopMovement $order): void
    {
        if (in_array($order->status, ['cancelled', 'delivered'], true)) {
            throw new \RuntimeException('No se pueden modificar lineas en este estado de OS.');
        }

        if ($order->sales_movement_id) {
            throw new \RuntimeException('No se pueden modificar lineas despues de facturar la OS.');
        }
    }

    private function revertConsumedDetail(WorkshopMovementDetail $detail): void
    {
        $order = WorkshopMovement::query()->findOrFail($detail->workshop_movement_id);

        $productBranch = ProductBranch::query()
            ->where('branch_id', $order->branch_id)
            ->where('product_id', $detail->product_id)
            ->lockForUpdate()
            ->first();

        if ($productBranch) {
            $productBranch->update([
                'stock' => (float) $productBranch->stock + (float) $detail->qty,
            ]);
        }

        if ($detail->warehouse_movement_id) {
            $warehouse = WarehouseMovement::query()->find($detail->warehouse_movement_id);
            if ($warehouse) {
                $warehouse->update(['status' => 'REVERTED']);
                $warehouse->movement?->update([
                    'comment' => trim((string) $warehouse->movement?->comment) . ' | REVERSADO POR ELIMINACION DE LINEA OS',
                ]);
            }
        }

        $detail->update([
            'stock_consumed' => false,
            'consumed_at' => null,
            'warehouse_movement_id' => null,
            'stock_status' => 'returned',
        ]);
    }

    private function createWarehouseAdjustmentMovement(
        WorkshopMovement $order,
        WorkshopMovementDetail $detail,
        float $qty,
        string $stockDirection,
        int $branchId,
        int $userId,
        string $userName
    ): void {
        $movementTypeId = $this->resolveMovementTypeId('ALMACEN_TALLER_AJUSTE');
        $documentName = $stockDirection === 'add' ? 'Ingreso ajuste OS' : 'Salida ajuste OS';
        $docStock = $stockDirection === 'add' ? 'add' : 'subtract';
        $documentTypeId = $this->resolveDocumentTypeId($movementTypeId, $documentName, $docStock);

        $movement = Movement::query()->create([
            'number' => $this->generateMovementNumber($branchId, $documentTypeId),
            'moved_at' => now(),
            'user_id' => $userId,
            'user_name' => $userName,
            'person_id' => $order->client_person_id,
            'person_name' => trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? '')),
            'responsible_id' => $userId,
            'responsible_name' => $userName,
            'comment' => 'Ajuste por cambio de cantidad en OS #' . $order->movement?->number,
            'status' => 'A',
            'movement_type_id' => $movementTypeId,
            'document_type_id' => $documentTypeId,
            'branch_id' => $branchId,
            'parent_movement_id' => $order->movement_id,
        ]);

        $warehouse = WarehouseMovement::query()->create([
            'status' => 'FINALIZED',
            'movement_id' => $movement->id,
            'branch_id' => $branchId,
        ]);

        $product = Product::query()->findOrFail((int) $detail->product_id);
        $unitId = (int) ($product->base_unit_id ?: $this->resolveDefaultUnitId());

        WarehouseMovementDetail::query()->create([
            'warehouse_movement_id' => $warehouse->id,
            'product_id' => (int) $detail->product_id,
            'product_snapshot' => [
                'id' => $product->id,
                'code' => $product->code,
                'description' => $product->description,
            ],
            'unit_id' => $unitId,
            'quantity' => $qty,
            'comment' => 'Ajuste por cambio de cantidad en linea OS',
            'status' => 'A',
            'branch_id' => $branchId,
        ]);

        $productBranch = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->where('product_id', (int) $detail->product_id)
            ->lockForUpdate()
            ->first();

        if (!$productBranch) {
            throw new \RuntimeException('No existe stock de sucursal para el repuesto a ajustar.');
        }

        $stock = (float) $productBranch->stock;
        $newStock = $stock;
        if ($stockDirection === 'add') {
            $newStock += $qty;
        } else {
            $newStock -= $qty;
            if (!$this->paramBool('WS_ALLOW_NEGATIVE_STOCK', $branchId, false)) {
                $newStock = max(0, $newStock);
            }
        }

        $productBranch->update(['stock' => $newStock]);
    }

    private function releaseReservations(WorkshopMovementDetail $detail, string $status): void
    {
        WorkshopStockReservation::query()
            ->where('workshop_movement_detail_id', $detail->id)
            ->where('status', 'reserved')
            ->update([
                'status' => $status,
                'released_at' => now(),
            ]);
    }

    private function releaseOrderReservations(WorkshopMovement $order): void
    {
        WorkshopStockReservation::query()
            ->whereIn('workshop_movement_detail_id', $order->details()->pluck('id'))
            ->where('status', 'reserved')
            ->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
    }

    private function recalculateOrderTotals(WorkshopMovement $order): void
    {
        $totals = WorkshopMovementDetail::query()
            ->where('workshop_movement_id', $order->id)
            ->selectRaw('COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(total),0) as total')
            ->first();

        $paid = (float) $order->paid_total;
        $total = (float) ($totals->total ?? 0);

        $paymentStatus = 'pending';
        if ($paid > 0 && $paid < $total) {
            $paymentStatus = 'partial';
        }
        if ($paid >= $total && $total > 0) {
            $paymentStatus = 'paid';
        }

        $order->update([
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'total' => $total,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function availableStockForDetail(int $productId, int $branchId, int $detailId): float
    {
        $physical = (float) ProductBranch::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->value('stock');

        $reservedTotal = (float) WorkshopStockReservation::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', 'reserved')
            ->sum('qty');

        $ownReserved = (float) WorkshopStockReservation::query()
            ->where('workshop_movement_detail_id', $detailId)
            ->where('status', 'reserved')
            ->sum('qty');

        $reservedByOthers = max(0, $reservedTotal - $ownReserved);

        return $physical - $reservedByOthers;
    }

    private function validateChecklistResult(string $type, string $result): void
    {
        $allowed = self::CHECKLIST_RESULTS[$type] ?? [];
        if ($allowed === []) {
            return;
        }

        if (!in_array($result, $allowed, true)) {
            throw new \RuntimeException('Resultado de checklist no valido para tipo ' . $type . '.');
        }
    }

    private function payloadTouchesFinancialStructure(array $data): bool
    {
        $blocked = ['vehicle_id', 'client_person_id', 'mileage_in', 'tow_in'];
        foreach ($blocked as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }

    private function isChecklistLocked(WorkshopMovement $order): bool
    {
        return in_array((string) $order->status, ['approved', 'in_progress', 'finished', 'delivered'], true);
    }

    private function assertCanChangeStatus(string $fromStatus, string $toStatus): void
    {
        $profile = strtoupper((string) (Auth::user()?->profile?->name ?? ''));
        $isAdmin = $this->isAdminUser();

        if ($isAdmin) {
            return;
        }

        $allowed = match ($toStatus) {
            'diagnosis', 'awaiting_approval' => ['RECEPCION', 'RECEPCIÓN', 'JEFE TALLER', 'TECNICO', 'TÉCNICO'],
            'approved' => ['RECEPCION', 'RECEPCIÓN', 'JEFE TALLER'],
            'in_progress', 'finished' => ['TECNICO', 'TÉCNICO', 'JEFE TALLER'],
            'delivered' => ['RECEPCION', 'RECEPCIÓN', 'CAJERO', 'JEFE TALLER'],
            'cancelled' => ['RECEPCION', 'RECEPCIÓN', 'JEFE TALLER'],
            default => [],
        };

        foreach ($allowed as $token) {
            if (str_contains($profile, $token)) {
                return;
            }
        }

        throw new \RuntimeException("No tiene permisos para pasar de {$fromStatus} a {$toStatus}.");
    }

    private function assertWorkshopInCurrentBranch(WorkshopMovement $order): void
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $order->branch_id !== $branchId) {
            throw new \RuntimeException('Acceso denegado para esta sucursal.');
        }

        $branch = Branch::query()->find($branchId);
        if ($branch && (int) $order->company_id !== (int) $branch->company_id) {
            throw new \RuntimeException('Acceso denegado para esta empresa.');
        }
    }

    private function resolveMovementTypeId(string $description): int
    {
        $record = MovementType::query()->where('description', $description)->first();
        if ($record) {
            return (int) $record->id;
        }

        return (int) MovementType::query()->insertGetId([
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveDocumentTypeId(int $movementTypeId, string $name, string $stock): int
    {
        $record = DocumentType::query()
            ->where('movement_type_id', $movementTypeId)
            ->where('name', $name)
            ->first();

        if ($record) {
            if ($record->stock !== $stock) {
                $record->update(['stock' => $stock]);
            }

            return (int) $record->id;
        }

        return (int) DocumentType::query()->insertGetId([
            'name' => $name,
            'stock' => $stock,
            'movement_type_id' => $movementTypeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveSaleMovementTypeId(): int
    {
        $typeId = MovementType::query()
            ->where('description', 'ILIKE', '%venta%')
            ->orderBy('id')
            ->value('id');

        if (!$typeId) {
            $typeId = MovementType::query()->find(2)?->id;
        }

        if (!$typeId) {
            throw new \RuntimeException('No se encontro tipo de movimiento de venta.');
        }

        return (int) $typeId;
    }

    private function resolveCashMovementTypeId(): int
    {
        $typeId = MovementType::query()
            ->where('description', 'ILIKE', '%caja%')
            ->orderBy('id')
            ->value('id');

        if (!$typeId) {
            $typeId = MovementType::query()->find(4)?->id;
        }

        if (!$typeId) {
            throw new \RuntimeException('No se encontro tipo de movimiento de caja.');
        }

        return (int) $typeId;
    }

    private function resolvePaymentConceptId(): int
    {
        $id = PaymentConcept::query()
            ->where('type', 'I')
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%cliente%')
                    ->orWhere('description', 'ILIKE', '%venta%')
                    ->orWhere('description', 'ILIKE', '%cobrar%');
            })
            ->orderBy('id')
            ->value('id');

        if (!$id) {
            $id = (int) PaymentConcept::query()->where('type', 'I')->orderBy('id')->value('id');
        }

        if (!$id) {
            throw new \RuntimeException('No existe concepto de pago de ingreso para registrar cobros.');
        }

        return (int) $id;
    }

    private function resolveRefundPaymentConceptId(): int
    {
        $id = PaymentConcept::query()
            ->where('type', 'E')
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%anul%')
                    ->orWhere('description', 'ILIKE', '%devol%');
            })
            ->orderBy('id')
            ->value('id');

        if (!$id) {
            $id = (int) PaymentConcept::query()->where('type', 'E')->orderBy('id')->value('id');
        }

        if (!$id) {
            throw new \RuntimeException('No existe concepto de pago de egreso para devoluciones.');
        }

        return (int) $id;
    }

    private function generateMovementNumber(int $branchId, int $documentTypeId): string
    {
        $year = (int) now()->year;

        $numbers = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year)
            ->lockForUpdate()
            ->pluck('number');

        $last = 0;
        foreach ($numbers as $number) {
            $raw = trim((string) $number);
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $raw) === 1) {
                $last = max($last, (int) $raw);
                continue;
            }

            if (preg_match('/(\d+)-\d{4}$/', $raw, $matches) === 1) {
                $last = max($last, (int) $matches[1]);
            }
        }

        return str_pad((string) ($last + 1), 8, '0', STR_PAD_LEFT);
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, int $paymentConceptId, ?int $shiftId = null): string
    {
        $lastRecord = Movement::query()
            ->select('movements.number')
            ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
            ->where('movements.branch_id', $branchId)
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->where('cash_movements.payment_concept_id', $paymentConceptId)
            ->when($shiftId, fn ($query) => $query->where('cash_movements.shift_id', $shiftId))
            ->lockForUpdate()
            ->orderByDesc('movements.number')
            ->first();

        $lastNumber = (int) ($lastRecord?->number ?? 0);

        return str_pad((string) ($lastNumber + 1), 8, '0', STR_PAD_LEFT);
    }

    private function resolveDefaultTaxRate(): float
    {
        $taxRate = TaxRate::query()->where('status', true)->orderBy('order_num')->value('tax_rate');
        if ($taxRate !== null) {
            return ((float) $taxRate) / 100;
        }

        return ((float) $this->paramNumber('WS_DEFAULT_IGV', (int) session('branch_id'), 18)) / 100;
    }

    private function resolveDefaultUnitId(): int
    {
        $id = Unit::query()->orderBy('id')->value('id');
        if (!$id) {
            throw new \RuntimeException('No existen unidades para registrar detalle de venta/almacen.');
        }

        return (int) $id;
    }

    private function assertOpenCashShift(int $branchId): void
    {
        $mustRequireOpenShift = $this->paramBool('WS_REQUIRE_OPEN_CASH_SHIFT', $branchId, true);
        if (!$mustRequireOpenShift) {
            return;
        }

        $opened = DB::table('cash_shift_relations')
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->whereNull('ended_at')
            ->exists();

        if (!$opened) {
            throw new \RuntimeException('Debe existir una caja/turno abierto para registrar pagos.');
        }
    }

    private function recordStatusChange(int $workshopId, ?string $from, string $to, int $userId, ?string $note): void
    {
        WorkshopStatusHistory::query()->create([
            'workshop_movement_id' => $workshopId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId > 0 ? $userId : null,
            'note' => $note,
        ]);

        WorkshopMovementStatusLog::query()->create([
            'workshop_movement_id' => $workshopId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId > 0 ? $userId : null,
            'note' => $note,
        ]);
    }

    private function logVehicleMileage(int $vehicleId, int $workshopId, int $mileage, string $type, int $userId, string $note): void
    {
        if ($userId <= 0) {
            return;
        }

        WorkshopVehicleLog::query()->create([
            'vehicle_id' => $vehicleId,
            'workshop_movement_id' => $workshopId,
            'mileage' => $mileage,
            'log_type' => $type,
            'notes' => $note,
            'created_by' => $userId,
        ]);
    }

    private function audit(int $workshopId, int $userId, string $event, array $payload = []): void
    {
        WorkshopAudit::query()->create([
            'workshop_movement_id' => $workshopId,
            'user_id' => $userId > 0 ? $userId : null,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    private function cloneReusablePdiChecklist(WorkshopMovement $currentOrder, int $vehicleId, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $hasCurrentPdi = WorkshopChecklist::query()
            ->where('workshop_movement_id', $currentOrder->id)
            ->where('type', 'PDI')
            ->exists();

        if ($hasCurrentPdi) {
            return;
        }

        $sourceChecklist = WorkshopChecklist::query()
            ->where('type', 'PDI')
            ->whereHas('workshopMovement', function ($query) use ($vehicleId, $currentOrder) {
                $query->where('vehicle_id', $vehicleId)
                    ->where('company_id', $currentOrder->company_id)
                    ->where('id', '!=', $currentOrder->id);
            })
            ->orderByDesc('id')
            ->first();

        if (!$sourceChecklist) {
            return;
        }

        $newChecklist = WorkshopChecklist::query()->create([
            'workshop_movement_id' => $currentOrder->id,
            'type' => 'PDI',
            'version' => (int) ($sourceChecklist->version ?? 1),
            'created_by' => $userId,
        ]);

        $sourceItems = WorkshopChecklistItem::query()
            ->where('checklist_id', $sourceChecklist->id)
            ->orderBy('order_num')
            ->orderBy('id')
            ->get();

        foreach ($sourceItems as $item) {
            WorkshopChecklistItem::query()->create([
                'checklist_id' => $newChecklist->id,
                'group' => $item->group,
                'label' => $item->label,
                'result' => $item->result,
                'action' => $item->action,
                'observation' => $item->observation,
                'order_num' => $item->order_num,
            ]);
        }
    }

    private function paramRaw(string $key, int $branchId): mixed
    {
        $baseParameter = DB::table('parameters')->where('description', $key)->first();
        if (!$baseParameter) {
            return null;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $baseParameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        return $branchValue ?? $baseParameter->value;
    }

    private function paramBool(string $key, int $branchId, bool $default): bool
    {
        $raw = $this->paramRaw($key, $branchId);
        if ($raw === null) {
            return $default;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        $value = strtoupper(trim((string) $raw));

        return in_array($value, ['1', 'TRUE', 'YES', 'SI', 'S'], true);
    }

    private function paramText(string $key, int $branchId, string $default): string
    {
        $raw = $this->paramRaw($key, $branchId);
        if ($raw === null) {
            return $default;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $default;
    }

    private function paramNumber(string $key, int $branchId, float $default): float
    {
        $raw = $this->paramRaw($key, $branchId);
        if ($raw === null || !is_numeric($raw)) {
            return $default;
        }

        return (float) $raw;
    }

    private function isAdminUser(): bool
    {
        $user = Auth::user();
        $profileName = strtoupper((string) ($user?->profile?->name ?? ''));

        return (int) ($user?->profile_id ?? 0) === 1 || str_contains($profileName, 'ADMIN');
    }
}

