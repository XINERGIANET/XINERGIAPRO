<?php

namespace App\Support;

use App\Models\CashMovementDetail;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\WorkshopService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WorkshopServiceHistoryExcelImport
{
    public static function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);
        $userId = (int) auth()->id();
        $userName = auth()->user()->name ?? 'System';

        $cashRegister = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->first();

        $imported = 0;

        DB::transaction(function () use ($rows, $branch, $branchId, $userId, $userName, $cashRegister, &$imported) {
            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // Skip header

                $fechaStr = trim((string) ($row[0] ?? ''));
                if (empty($fechaStr)) continue;

                $documentos = trim((string) ($row[1] ?? ''));
                $serie = trim((string) ($row[2] ?? ''));
                $clienteNombre = trim((string) ($row[3] ?? ''));
                $dniRuc = trim((string) ($row[4] ?? ''));
                $servicioTipo = trim((string) ($row[5] ?? ''));
                $placa = trim((string) ($row[6] ?? ''));
                $marcaStr = trim((string) ($row[7] ?? ''));
                $modeloStr = trim((string) ($row[8] ?? ''));
                $descripcion = trim((string) ($row[9] ?? ''));
                $totalGeneral = (float) preg_replace('/[^\d.]/', '', (string) ($row[10] ?? 0));
                $medioPagoStr = trim((string) ($row[11] ?? ''));

                if (empty($placa)) continue;

                $fecha = null;
                try {
                    $fecha = Carbon::createFromFormat('d/m/Y', $fechaStr);
                } catch (\Exception $e) {
                    try {
                        $fecha = Carbon::parse($fechaStr);
                    } catch (\Exception $e2) {
                        $fecha = now();
                    }
                }

                $dniRuc = preg_replace('/[^\d]/', '', $dniRuc);
                $docType = strlen($dniRuc) === 11 ? '6' : '1'; // 6=RUC, 1=DNI

                $person = Person::query()->withTrashed()->where('document_number', $dniRuc)->where('branch_id', $branchId)->first();
                if ($person) {
                    if ($person->trashed()) {
                        $person->restore();
                    }
                } else {
                    $person = Person::query()->create([
                        'company_id' => $branch->company_id,
                        'branch_id' => $branchId,
                        'person_type' => $docType === '6' ? 'RUC' : 'DNI',
                        'document_type_id' => $docType,
                        'document_number' => $dniRuc,
                        'first_name' => $docType === '1' ? $clienteNombre : '',
                        'last_name' => '',
                        'business_name' => $docType === '6' ? $clienteNombre : '',
                        'search_name' => $clienteNombre,
                        'phone' => '',
                        'email' => '',
                        'address' => '-',
                        'location_id' => $branch->location_id ?? 1,
                        'status' => 'active',
                        'is_client' => true,
                    ]);
                    
                    // Assign client role to the new person
                    $person->roles()->syncWithoutDetaching([
                        3 => ['branch_id' => $branchId],
                    ]);
                }

                $placaFormatted = strtoupper(str_replace(['-', ' '], '', $placa));
                $vehicle = Vehicle::query()->withTrashed()->where('plate', $placaFormatted)->where('company_id', $branch->company_id)->first();
                if ($vehicle) {
                    if ($vehicle->trashed()) {
                        $vehicle->restore();
                    }
                } else {
                    $vehicleType = VehicleType::query()
                        ->where(function ($query) use ($branch) {
                            $query->whereNull('company_id')
                                  ->orWhere('company_id', $branch->company_id);
                        })
                        ->first();
                        
                    if (!$vehicleType) {
                        $vehicleType = VehicleType::query()->create([
                            'company_id' => $branch->company_id,
                            'branch_id' => $branchId,
                            'name' => 'Auto',
                            'active' => true,
                        ]);
                    }

                    $vehicleTypeId = $vehicleType->id;
                    $vehicleTypeName = $vehicleType->name;

                    $vehicle = Vehicle::query()->create([
                        'company_id' => $branch->company_id,
                        'branch_id' => $branchId,
                        'client_person_id' => $person->id,
                        'vehicle_type_id' => $vehicleTypeId,
                        'type' => $vehicleTypeName,
                        'brand' => strtoupper($marcaStr) ?: '-',
                        'model' => strtoupper($modeloStr) ?: '-',
                        'plate' => $placaFormatted,
                        'status' => 'active',
                    ]);
                }

                $osType = strtoupper($servicioTipo) === 'MC' ? 'correctivo' : 'preventivo';

                $movementTypeId = MovementType::query()->where('description', 'TALLER_OS')->value('id') ?? 1;
                $docTypeOsId = DocumentType::query()->where('movement_type_id', $movementTypeId)->value('id') ?? 1;

                $osMovement = Movement::query()->create([
                    'movement_type_id' => $movementTypeId,
                    'document_type_id' => $docTypeOsId,
                    'branch_id' => $branchId,
                    'number' => 'OS-IMP-' . time() . '-' . $imported,
                    'moved_at' => $fecha,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'person_id' => $person->id,
                    'person_name' => $person->search_name ?: 'Público General',
                    'responsible_id' => $userId,
                    'responsible_name' => $userName,
                    'comment' => 'Importación de Historial',
                    'status' => 'A',
                ]);

                $order = WorkshopMovement::query()->create([
                    'movement_id' => $osMovement->id,
                    'company_id' => $branch->company_id,
                    'branch_id' => $branchId,
                    'vehicle_id' => $vehicle->id,
                    'client_person_id' => $person->id,
                    'intake_date' => $fecha,
                    'delivery_date' => $fecha,
                    'observations' => $descripcion,
                    'status' => 'delivered',
                    'approval_status' => 'approved',
                    'payment_status' => 'paid',
                    'subtotal' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'paid_total' => 0,
                    'service_type' => $osType,
                ]);

                $servicesData = [];
                $totalCols = count($row);
                for ($colName = 12; $colName < $totalCols; $colName += 2) {
                    $colPrice = $colName + 1;
                    $sName = trim((string) ($row[$colName] ?? ''));
                    $sPrice = (float) preg_replace('/[^\d.]/', '', (string) ($row[$colPrice] ?? 0));
                    
                    if ($sName !== '') {
                        $servicesData[] = [
                            'name' => $sName,
                            'price' => $sPrice,
                        ];
                    }
                }

                if (empty($servicesData) && $descripcion !== '') {
                    $servicesData[] = [
                        'name' => 'Servicio Importado',
                        'price' => $totalGeneral,
                    ];
                }

                $orderTotal = 0;
                $igvRate = 0.18; 

                foreach ($servicesData as $sd) {
                    $tax = $sd['price'] - ($sd['price'] / (1 + $igvRate));
                    $subtotal = $sd['price'] / (1 + $igvRate);

                    WorkshopMovementDetail::query()->create([
                        'workshop_movement_id' => $order->id,
                        'line_type' => 'LABOR',
                        'description' => mb_substr($sd['name'], 0, 250),
                        'qty' => 1,
                        'unit_price' => $sd['price'],
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $sd['price'],
                        'stock_status' => 'not_applicable',
                        'branch_id' => $branchId,
                    ]);
                    $orderTotal += $sd['price'];
                }

                $orderTax = $orderTotal - ($orderTotal / (1 + $igvRate));
                $orderSubtotal = $orderTotal / (1 + $igvRate);

                $order->update([
                    'subtotal' => $orderSubtotal,
                    'tax' => $orderTax,
                    'total' => $orderTotal,
                    'paid_total' => $orderTotal,
                ]);

                if (!empty($documentos)) {
                    $isBoleta = str_contains(strtolower($documentos), 'boleta');
                    $isFactura = str_contains(strtolower($documentos), 'factura');
                    $saleMovementTypeId = MovementType::query()->whereRaw('LOWER(description) LIKE ?', ['%venta%'])->value('id') ?? 2;
                    
                    $saleDocType = null;
                    if ($isFactura) {
                        $saleDocType = DocumentType::query()->where('movement_type_id', $saleMovementTypeId)->whereRaw('LOWER(name) LIKE ?', ['%factura%'])->first();
                    } elseif ($isBoleta) {
                        $saleDocType = DocumentType::query()->where('movement_type_id', $saleMovementTypeId)->whereRaw('LOWER(name) LIKE ?', ['%boleta%'])->first();
                    }
                    if (!$saleDocType) {
                        $saleDocType = DocumentType::query()->where('movement_type_id', $saleMovementTypeId)->first();
                    }

                    if ($saleDocType) {
                        $saleMovement = Movement::query()->create([
                            'movement_type_id' => $saleMovementTypeId,
                            'document_type_id' => $saleDocType->id,
                            'branch_id' => $branchId,
                            'number' => $serie ?: 'IMP-' . time() . '-' . $imported,
                            'moved_at' => $fecha,
                            'user_id' => $userId,
                            'user_name' => $userName,
                            'person_id' => $person->id,
                            'person_name' => $person->search_name ?: 'Público General',
                            'responsible_id' => $userId,
                            'responsible_name' => $userName,
                            'comment' => 'Venta importada de taller',
                            'status' => 'A',
                        ]);

                        $salesMovement = SalesMovement::query()->create([
                            'movement_id' => $saleMovement->id,
                            'company_id' => $branch->company_id,
                            'branch_id' => $branchId,
                            'branch_snapshot' => [
                                'id' => $branch->id,
                                'legal_name' => $branch->name ?? 'Sucursal',
                            ],
                            'person_id' => $person->id,
                            'date' => $fecha,
                            'series' => $serie ?: '001',
                            'year' => $fecha->format('Y'),
                            'currency_id' => 1, 
                            'exchange_rate' => 1.000,
                            'subtotal' => $orderSubtotal,
                            'tax' => $orderTax,
                            'total' => $orderTotal,
                            'total_paid' => $orderTotal,
                            'status' => 'P', 
                        ]);

                        $order->update(['sales_movement_id' => $salesMovement->id]);

                        foreach ($servicesData as $sd) {
                            $tax = $sd['price'] - ($sd['price'] / (1 + $igvRate));
                            $subtotal = $sd['price'] / (1 + $igvRate);

                            SalesMovementDetail::query()->create([
                                'sales_movement_id' => $salesMovement->id,
                                'detail_type' => 'GLOSA',
                                'code' => '',
                                'description' => mb_substr($sd['name'], 0, 250),
                                'unit_id' => \App\Models\Unit::query()->value('id') ?? 1,
                                'quantity' => 1,
                                'amount' => $sd['price'],
                                'original_amount' => $sd['price'],
                                'discount_percentage' => 0,
                                'branch_id' => $branchId,
                            ]);
                        }

                        if ($cashRegister) {
                            if (empty($medioPagoStr)) {
                                $medioPagoStr = 'Efectivo';
                            }
                            $paymentMethod = PaymentMethod::query()
                                ->where('company_id', $branch->company_id)
                                ->whereRaw('LOWER(description) = ?', [strtolower($medioPagoStr)])
                                ->first();

                            if (!$paymentMethod) {
                                $paymentMethod = PaymentMethod::query()->create([
                                    'company_id' => $branch->company_id,
                                    'description' => strtoupper($medioPagoStr),
                                    'status' => true,
                                    'is_cash' => false, 
                                    'is_transfer' => true, 
                                ]);
                            }

                            $cashMovementTypeId = MovementType::query()->whereRaw('LOWER(description) LIKE ?', ['%caja%'])->value('id') ?? 4;
                            
                            $cashMovementRecord = Movement::query()->create([
                                'movement_type_id' => $cashMovementTypeId,
                                'document_type_id' => null,
                                'branch_id' => $branchId,
                                'number' => 'ING-' . time() . '-' . $imported,
                                'moved_at' => $fecha,
                                'user_id' => $userId,
                                'user_name' => $userName,
                                'person_id' => $person->id,
                                'person_name' => $person->search_name ?: 'Público General',
                                'responsible_id' => $userId,
                                'responsible_name' => $userName,
                                'comment' => 'Ingreso por venta importada',
                                'status' => 'A',
                                'parent_movement_id' => $saleMovement->id,
                            ]);

                            $cm = CashMovements::query()->create([
                                'movement_id' => $cashMovementRecord->id,
                                'cash_register_id' => $cashRegister->id,
                                'cash_register' => $cashRegister->number ?? 'Caja',
                                'total' => $orderTotal,
                                'currency' => 'PEN',
                                'exchange_rate' => 1.000,
                            ]);

                            CashMovementDetail::query()->create([
                                'cash_movement_id' => $cm->id,
                                'type' => 'CONTADO',
                                'payment_method_id' => $paymentMethod->id,
                                'payment_method' => $paymentMethod->description ?? 'EFECTIVO',
                                'amount' => $orderTotal,
                                'branch_id' => $branchId,
                            ]);
                        }
                    }
                }

                $imported++;
            }
        });

        return ['imported' => $imported];
    }
}
