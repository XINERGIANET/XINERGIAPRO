<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Card;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\CashShiftRelation;
use App\Models\DocumentType;
use App\Models\DigitalWallet;
use App\Models\Location;
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
use App\Models\Operation;
use App\Services\AccountReceivablePayableService;
use App\Services\KardexSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Symfony\Component\Process\Process;

class SalesController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->input('search');
        $viewId = $request->input('view_id');
        $billingStatus = (string) $request->input('billing_status', 'all');
        $documentTypeId = (string) $request->input('document_type_id', 'all');
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

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $cashRegisters = collect();
        $selectedBoxId = $request->input('cash_register_id');
        if ($branchId) {
            $cashRegisters = CashRegister::query()
                ->where('branch_id', $branchId)
                ->where('status', '1')
                ->orderBy('number')
                ->get(['id', 'number']);
            if ($selectedBoxId && !$cashRegisters->contains('id', (int) $selectedBoxId)) {
                $selectedBoxId = null;
            }
            if (!$selectedBoxId && $cashRegisters->isNotEmpty()) {
                $selectedBoxId = $this->getBranchConfiguredCashRegisterId((int) $branchId, $cashRegisters, 'caja ventas');
            }
        }

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

        $selectedShiftRelation = null;
        if (is_int($selectedShiftId) && $selectedShiftId > 0 && $selectedBoxId) {
            $selectedShiftRelation = CashShiftRelation::query()
                ->where('id', $selectedShiftId)
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', fn ($q) => $q->where('cash_register_id', $selectedBoxId))
                ->first();
        }

        $saleDocumentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedDocumentTypeId = 'all';
        if ($documentTypeId !== 'all' && $saleDocumentTypes->contains(fn ($documentType) => (string) $documentType->id === $documentTypeId)) {
            $selectedDocumentTypeId = $documentTypeId;
        }

        $salesBaseQuery = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement.details.unit'])
            ->where('movement_type_id', 2) //2 es venta
            ->when($branchId, fn ($query) => $query->where('movements.branch_id', $branchId))
            ->when($selectedBoxId, function ($query) use ($selectedBoxId) {
                $query->where(function ($inner) use ($selectedBoxId) {
                    $inner->whereHas('cashMovement', fn ($cashQuery) => $cashQuery->where('cash_register_id', $selectedBoxId))
                        ->orWhereExists(function ($subQuery) use ($selectedBoxId) {
                            $subQuery->select(DB::raw(1))
                                ->from('movements as cash_entry_movement')
                                ->join('cash_movements', 'cash_movements.movement_id', '=', 'cash_entry_movement.id')
                                ->whereColumn('cash_entry_movement.parent_movement_id', 'movements.id')
                                ->where('cash_movements.cash_register_id', $selectedBoxId)
                                ->whereNull('cash_entry_movement.deleted_at')
                                ->whereNull('cash_movements.deleted_at');
                        });
                });
            })
            ->when($selectedShiftRelation, function ($query) use ($selectedShiftRelation) {
                $query->where('moved_at', '>=', $selectedShiftRelation->started_at);
                if ($selectedShiftRelation->ended_at !== null) {
                    $query->where('moved_at', '<=', $selectedShiftRelation->ended_at);
                }
            })
            ->when($selectedDocumentTypeId !== 'all', fn ($query) => $query->where('document_type_id', (int) $selectedDocumentTypeId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('user_name', 'ILIKE', "%{$search}%")
                        ->orWhereHas('salesMovement', function ($salesMovementQuery) use ($search) {
                            $salesMovementQuery
                                ->where('series', 'ILIKE', "%{$search}%")
                                ->orWhere('billing_number', 'ILIKE', "%{$search}%")
                                ->orWhere('billing_status', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->when($billingStatus === 'pending', fn ($query) => $query->whereHas('salesMovement', fn ($salesMovementQuery) => $salesMovementQuery->where('billing_status', 'PENDING')))
            ->when($billingStatus === 'invoiced', fn ($query) => $query->whereHas('salesMovement', fn ($salesMovementQuery) => $salesMovementQuery->where('billing_status', 'INVOICED')));

        $salesTotalAmount = (float) $salesBaseQuery->clone()
            ->join('sales_movements', 'sales_movements.movement_id', '=', 'movements.id')
            ->sum('sales_movements.total');

        $sales = $salesBaseQuery
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        
        return view('sales.index', [
            'sales' => $sales,
            'search' => $search,
            'billingStatus' => $billingStatus,
            'selectedDocumentTypeId' => $selectedDocumentTypeId,
            'saleDocumentTypes' => $saleDocumentTypes,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'salesTotalAmount' => $salesTotalAmount,
            'cashRegisters' => $cashRegisters,
            'selectedBoxId' => $selectedBoxId,
            'shiftRelations' => $shiftRelations,
            'selectedShiftId' => $selectedShiftId,
            'currentShiftRelationId' => $currentShiftRelationId,
        ] + $this->getFormData());
    }

    public function create()
    {
        return view('sales.create', $this->getSalesPosViewData());
    }

    public function storeClientQuick(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->with('location.parent.parent')->findOrFail($branchId);
        $companyId = (int) ($branch->company_id ?? 0);

        $personType = strtoupper((string) $request->input('person_type', ''));

        $validated = $request->validate([
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => [
                Rule::requiredIf($personType !== 'RUC'),
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'genero' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
        ]);

        $branchDistrictId = (int) ($branch->location_id ?? 0);
        if ($branchDistrictId <= 0) {
            return response()->json([
                'message' => 'La sucursal no tiene distrito configurado.',
            ], 422);
        }

        $existingPerson = Person::query()
            ->join('branches', 'branches.id', '=', 'people.branch_id')
            ->select('people.*')
            ->where('branches.company_id', $companyId)
            ->where('people.document_number', (string) $validated['document_number'])
            ->whereNull('people.deleted_at')
            ->first();

        if ($existingPerson) {
            $existingPerson->forceFill([
                'location_id' => (int) ($validated['location_id'] ?? $existingPerson->location_id ?? $branchDistrictId),
            ])->save();

            $existingPerson->roles()->syncWithoutDetaching([
                3 => ['branch_id' => $branchId],
            ]);

            return response()->json([
                'id' => (int) $existingPerson->id,
                'person_type' => (string) $existingPerson->person_type,
                'document_number' => (string) $existingPerson->document_number,
                'first_name' => (string) $existingPerson->first_name,
                'last_name' => (string) ($existingPerson->last_name ?? ''),
                'name' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name)),
                'label' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name)),
                'document' => (string) ($existingPerson->document_number ?? ''),
            ]);
        }

        $validated['last_name'] = trim((string) ($validated['last_name'] ?? ''));
        $validated['phone'] = (string) ($validated['phone'] ?? '');
        $validated['email'] = (string) ($validated['email'] ?? '');
        $validated['address'] = trim((string) ($validated['address'] ?? '')) ?: '-';
        $validated['location_id'] = (int) ($validated['location_id'] ?? $branchDistrictId);

        $person = DB::transaction(function () use ($validated, $branchId) {
            $person = Person::query()->create(array_merge(
                $validated,
                ['branch_id' => $branchId]
            ));

            $person->roles()->syncWithoutDetaching([
                3 => ['branch_id' => $branchId],
            ]);

            return $person;
        });

        return response()->json([
            'id' => (int) $person->id,
            'person_type' => (string) $person->person_type,
            'document_number' => (string) $person->document_number,
            'first_name' => (string) $person->first_name,
            'last_name' => (string) ($person->last_name ?? ''),
            'name' => trim(((string) $person->first_name) . ' ' . ((string) $person->last_name)),
            'label' => trim(((string) $person->first_name) . ' ' . ((string) $person->last_name)),
            'document' => (string) ($person->document_number ?? ''),
        ]);
    }

    private function getSalesPosViewData(?Movement $sale = null): array
    {
        $user = auth()->user();
        $branchId = (int) (session('branch_id') ?? $user?->branch_id ?? $user?->person?->branch_id);

        if (!$branchId) {
            abort(403, 'No se pudo determinar la sucursal del usuario logueado.');
        }

        if ($sale && (int) $sale->branch_id !== $branchId) {
            abort(403, 'La venta no pertenece a la sucursal actual.');
        }

        $branch = Branch::query()->with('location.parent.parent')->findOrFail($branchId);
        $branchDistrict = $branch->location;
        $branchProvince = $branchDistrict?->parent;
        $branchDepartment = $branchProvince?->parent;
        $locationData = $this->getLocationData((int) ($branch->location_id ?? 0));

        $products = Product::query()
            ->where('type', 'SELLABLE')
            ->with('category')
            ->orderBy('description')
            ->get()
            ->map(function (Product $product) {
                $imageUrl = ($product->image && !empty($product->image))
                    ? asset('storage/' . ltrim($product->image, '/'))
                    : null;

                return [
                    'id' => (int) $product->id,
                    'code' => (string) ($product->code ?? ''),
                    'name' => $product->description,
                    'img' => $imageUrl,
                    'note' => $product->note ?? null,
                    'category' => $product->category ? $product->category->description : 'Sin categoria',
                ];
            })
            ->values();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->with(['product', 'taxRate'])
            ->get()
            ->filter(fn ($productBranch) => $productBranch->product !== null)
            ->map(function ($productBranch) {
                $taxRate = $productBranch->taxRate;
                $taxRatePct = $taxRate ? (float) $taxRate->tax_rate : null;

                return [
                    'id' => (int) $productBranch->id,
                    'product_id' => (int) $productBranch->product_id,
                    'price' => (float) $productBranch->price,
                    'tax_rate' => $taxRatePct,
                    'stock' => (float) ($productBranch->stock ?? 0),
                ];
            })
            ->values();

        $people = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        $defaultClientId = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereRaw('UPPER(first_name) = ?', ['CLIENTES'])
            ->whereRaw('UPPER(last_name) = ?', ['VARIOS'])
            ->value('id');

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->orderBy('name')
            ->get(['id', 'name']);
        $defaultDocumentTypeId = $this->getBranchDefaultSaleDocumentTypeId($branchId, $documentTypes);

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $paymentGateways = PaymentGateways::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $cards = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon', 'order_num']);

        $digitalWallets = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        $units = Unit::query()
            ->orderBy('description')
            ->get(['id', 'description']);

        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN status = 'A' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status', 'series']);
        $standardCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja ventas');
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;
        $defaultCashRegisterId = $this->isInvoiceDocumentTypeId($defaultDocumentTypeId, $documentTypes)
            ? $invoiceCashRegisterId
            : $standardCashRegisterId;

        $initialSaleData = null;
        $posMode = 'create';

        if ($sale) {
            $initialSaleData = $this->serializeSaleForPosEditor($sale, $defaultDocumentTypeId, $defaultCashRegisterId);
            $defaultDocumentTypeId = (int) ($initialSaleData['document_type_id'] ?? $defaultDocumentTypeId);
            $defaultCashRegisterId = (int) ($initialSaleData['cash_register_id'] ?? $defaultCashRegisterId);
            $posMode = 'edit';
        }

        $defaultCashRegisterModel = $cashRegisters->firstWhere('id', $defaultCashRegisterId) ?? $cashRegisters->first();
        $saleSeriesPreview = (string) ($defaultCashRegisterModel?->series ?: '001');
        $saleNumberPreview = ($defaultDocumentTypeId > 0 && $defaultCashRegisterId > 0)
            ? $this->generateSaleNumber((int) $defaultDocumentTypeId, (int) $defaultCashRegisterId, false)
            : '00000001';
        $saleMovedAtDefault = now()->format('Y-m-d H:i');

        if ($initialSaleData) {
            $saleSeriesPreview = (string) ($initialSaleData['display_series'] ?? $initialSaleData['invoice_series'] ?? $saleSeriesPreview);
            $saleNumberPreview = (string) ($initialSaleData['number'] ?? $saleNumberPreview);
            $saleMovedAtDefault = (string) ($initialSaleData['moved_at'] ?? $saleMovedAtDefault);
        }

        return [
            'products' => $products,
            'productBranches' => $productBranches,
            'people' => $people,
            'defaultClientId' => $defaultClientId,
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'units' => $units,
            'cashRegisters' => $cashRegisters,
            'defaultCashRegisterId' => $defaultCashRegisterId,
            'standardCashRegisterId' => $standardCashRegisterId,
            'invoiceCashRegisterId' => $invoiceCashRegisterId,
            'productsBranches' => $productBranches,
            'initialSaleData' => $initialSaleData,
            'posMode' => $posMode,
            'invoiceMode' => request()->boolean('invoice_mode'),
            'quickClientStoreUrl' => route('admin.sales.clients.store'),
            'departments' => $locationData['departments'],
            'provinces' => $locationData['provinces'],
            'districts' => $locationData['districts'],
            'branchDepartmentId' => $locationData['selectedDepartmentId'],
            'branchProvinceId' => $locationData['selectedProvinceId'],
            'branchDistrictId' => $locationData['selectedDistrictId'],
            'branchDepartmentName' => $branchDepartment?->description,
            'branchProvinceName' => $branchProvince?->description,
            'branchDistrictName' => $branchDistrict?->description,
            'saleSeriesPreview' => $saleSeriesPreview,
            'saleNumberPreview' => $saleNumberPreview,
            'saleMovedAtDefault' => $saleMovedAtDefault,
        ];
    }

    /**
     * POS: serie (caja) y correlativo siguiente segun tipo de documento (solo vista previa).
     */
    public function previewSalePosHeader(Request $request)
    {
        $user = $request->user();
        $branchId = (int) (session('branch_id') ?? $user?->branch_id ?? $user?->person?->branch_id);
        if (!$branchId) {
            return response()->json(['message' => 'No se pudo determinar la sucursal.'], 422);
        }

        $documentTypeId = (int) $request->query('document_type_id', 0);
        $cashRegisterId = (int) $request->query('cash_register_id', 0);
        if ($documentTypeId <= 0 || $cashRegisterId <= 0) {
            return response()->json(['series' => '001', 'number' => '00000001']);
        }

        $cashRegister = CashRegister::query()
            ->where('id', $cashRegisterId)
            ->where('branch_id', $branchId)
            ->first();
        if (!$cashRegister) {
            return response()->json(['message' => 'Caja no valida para esta sucursal.'], 422);
        }

        $documentType = DocumentType::query()
            ->where('id', $documentTypeId)
            ->where('movement_type_id', 2)
            ->first();
        if (!$documentType) {
            return response()->json(['message' => 'Tipo de documento no valido.'], 422);
        }

        try {
            $number = $this->generateSaleNumber($documentTypeId, $cashRegisterId, false);
        } catch (\Throwable $e) {
            $number = '00000001';
        }

        return response()->json([
            'series' => (string) ($cashRegister->series ?: '001'),
            'number' => $number,
        ]);
    }

    private function getLocationData(?int $defaultLocationId = null): array
    {
        $departments = Location::query()
            ->where('type', 'department')
            ->orderBy('name')
            ->get(['id', 'name']);

        $provinces = Location::query()
            ->where('type', 'province')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $districts = Location::query()
            ->where('type', 'district')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $selectedDistrictId = $defaultLocationId ?: null;
        $selectedProvinceId = null;
        $selectedDepartmentId = null;

        if ($selectedDistrictId) {
            $district = Location::query()->find($selectedDistrictId);
            if ($district) {
                $selectedProvinceId = $district->parent_location_id;
                if ($selectedProvinceId) {
                    $province = Location::query()->find($selectedProvinceId);
                    $selectedDepartmentId = $province?->parent_location_id;
                }
            }
        }

        return [
            'departments' => $departments->values(),
            'provinces' => $provinces->values(),
            'districts' => $districts->values(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedProvinceId' => $selectedProvinceId,
            'selectedDistrictId' => $selectedDistrictId,
        ];
    }

    private function serializeSaleForPosEditor(Movement $sale, int $defaultDocumentTypeId, int $defaultCashRegisterId): array
    {
        $sale->loadMissing(['salesMovement.details.product']);
        $cashMovement = $this->resolveCashMovementBySaleMovement((int) $sale->id);
        $debtRegistered = $cashMovement
            ? DB::table('cash_movement_details')
                ->where('cash_movement_id', $cashMovement->id)
                ->where('status', 'A')
                ->where('type', 'DEUDA')
                ->exists()
            : false;
        $rawPaymentType = strtoupper((string) ($sale->salesMovement?->payment_type ?? 'CONTADO'));
        $paymentType = $debtRegistered || in_array($rawPaymentType, ['CREDITO', 'CREDIT', 'DEUDA'], true)
            ? 'DEUDA'
            : 'CONTADO';

        $paymentMethods = [];
        if ($cashMovement && $paymentType !== 'DEUDA') {
            $paymentMethods = DB::table('cash_movement_details')
                ->where('cash_movement_id', $cashMovement->id)
                ->where('status', 'A')
                ->where('type', '!=', 'DEUDA')
                ->orderBy('id')
                ->get([
                    'payment_method_id',
                    'amount',
                    'payment_gateway_id',
                    'card_id',
                    'digital_wallet_id',
                ])
                ->map(fn ($row) => [
                    'payment_method_id' => $row->payment_method_id ? (int) $row->payment_method_id : null,
                    'amount' => (float) ($row->amount ?? 0),
                    'payment_gateway_id' => $row->payment_gateway_id ? (int) $row->payment_gateway_id : null,
                    'card_id' => $row->card_id ? (int) $row->card_id : null,
                    'digital_wallet_id' => $row->digital_wallet_id ? (int) $row->digital_wallet_id : null,
                ])
                ->values()
                ->all();
        }

        $items = collect($sale->salesMovement?->details ?? [])
            ->sortBy('id')
            ->values()
            ->map(function (SalesMovementDetail $detail) {
                $quantity = (float) ($detail->quantity ?: 1);
                $discountPct = (float) ($detail->discount_percentage ?? 0);
                $discountFactor = max(0.0, 1 - ($discountPct / 100));
                $taxRatePct = (float) data_get($detail->tax_rate_snapshot, 'tax_rate', 0);
                $taxRateFactor = $taxRatePct > 0 ? ($taxRatePct / 100) : 0;
                $baseNetLineTotal = (float) ($detail->original_amount ?? 0);

                if ($baseNetLineTotal <= 0) {
                    $discountedGrossLineTotal = (float) ($detail->amount ?? 0);
                    $baseGrossLineTotal = $discountFactor > 0
                        ? ($discountedGrossLineTotal / $discountFactor)
                        : $discountedGrossLineTotal;
                    $baseNetLineTotal = $taxRateFactor > 0
                        ? ($baseGrossLineTotal / (1 + $taxRateFactor))
                        : $baseGrossLineTotal;
                }

                $baseGrossLineTotal = $taxRateFactor > 0
                    ? ($baseNetLineTotal * (1 + $taxRateFactor))
                    : $baseNetLineTotal;

                return [
                    'kind' => $detail->product_id ? 'product' : 'glosa',
                    'pId' => (int) ($detail->product_id ?? 0),
                    'name' => $detail->product->description ?? $detail->description ?? ('Producto #' . $detail->product_id),
                    'qty' => $quantity,
                    'price' => $quantity > 0 ? ($baseGrossLineTotal / $quantity) : 0,
                    'tax_rate' => $taxRatePct,
                    'unit_id' => $detail->unit_id ? (int) $detail->unit_id : null,
                    'note' => (string) ($detail->comment ?? ''),
                ];
            })
            ->all();

        $detailType = collect($items)->isNotEmpty() && collect($items)->every(fn (array $item) => ($item['kind'] ?? 'product') === 'glosa')
            ? 'GLOSA'
            : 'DETALLADO';

        $grossTotalBeforeDiscount = collect($items)->sum(function (array $item) {
            return ((float) ($item['qty'] ?? 0)) * ((float) ($item['price'] ?? 0));
        });
        $discountPercentage = collect($sale->salesMovement?->details ?? [])
            ->map(fn (SalesMovementDetail $detail) => (float) ($detail->discount_percentage ?? 0))
            ->first(fn (float $value) => $value > 0, 0.0);
        $discountAmount = max(
            0,
            $grossTotalBeforeDiscount - (float) ($sale->salesMovement?->total ?? 0)
        );

        $creditDays = 0;
        $debtDueDate = '';
        if ($debtRegistered && $cashMovement) {
            $debtRow = DB::table('cash_movement_details')
                ->where('cash_movement_id', $cashMovement->id)
                ->where('type', 'DEUDA')
                ->where('status', 'A')
                ->orderByDesc('id')
                ->first(['due_at']);
            if ($debtRow && $debtRow->due_at && $sale->moved_at) {
                try {
                    $dueCarbon = Carbon::parse($debtRow->due_at);
                    $debtDueDate = $dueCarbon->format('Y-m-d');
                    $creditDays = max(
                        0,
                        (int) Carbon::parse($sale->moved_at)->startOfDay()->diffInDays($dueCarbon->copy()->startOfDay(), false)
                    );
                } catch (\Throwable $e) {
                    $creditDays = 0;
                    $debtDueDate = '';
                }
            }
        }

        return [
            'id' => (int) $sale->id,
            'number' => (string) ($sale->number ?? ''),
            'moved_at' => optional($sale->moved_at)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
            'display_series' => (string) ($sale->salesMovement?->series ?: '001'),
            'clientId' => $sale->person_id ? (int) $sale->person_id : null,
            'clientName' => $sale->person_name ?: 'Publico General',
            'notes' => (string) ($sale->comment ?? ''),
            'document_type_id' => (int) ($sale->document_type_id ?: $defaultDocumentTypeId),
            'cash_register_id' => (int) ($cashMovement?->cash_register_id ?: $defaultCashRegisterId),
            'billing_status' => (string) ($sale->salesMovement?->billing_status ?: ($this->isInvoiceDocumentType($sale->documentType) ? 'INVOICED' : 'NOT_APPLICABLE')),
            'invoice_series' => (string) ($sale->salesMovement?->series ?: '001'),
            'invoice_number' => (string) ($sale->salesMovement?->billing_number ?: ($this->isInvoiceDocumentType($sale->documentType) ? ($sale->number ?? '') : '')),
            'payment_type' => $paymentType,
            'credit_days' => $creditDays,
            'debt_due_date' => $debtDueDate,
            'discount_type' => $discountPercentage > 0 ? 'PERCENTAGE' : 'NONE',
            'discount_value' => $discountPercentage > 0 ? round($discountPercentage, 6) : 0,
            'discount_amount' => round($discountAmount, 2),
            'detail_type' => $detailType,
            'items' => $items,
            'payment_methods' => $paymentMethods,
        ];
    }

    // POS: vista de cobro
    public function charge(Request $request)
    {
        $user = $request->user();
        $branchId = (int) (session('branch_id') ?? $user?->branch_id ?? $user?->person?->branch_id);
        if (!$branchId) {
            abort(403, 'No se pudo determinar la sucursal del usuario logueado.');
        }

        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->where('movement_type_id', 2)
            ->get(['id', 'name']);
        $defaultDocumentTypeId = $this->getBranchDefaultSaleDocumentTypeId($branchId, $documentTypes);
        
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        
        $paymentGateways = PaymentGateways::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        
        $cards = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon', 'order_num']);

        $digitalWallets = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        
        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN status = 'A' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);
        $standardCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja ventas');
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;
        $defaultCashRegisterId = $this->isInvoiceDocumentTypeId($defaultDocumentTypeId, $documentTypes)
            ? $invoiceCashRegisterId
            : $standardCashRegisterId;

        $people = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        $defaultClientId = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereRaw('UPPER(first_name) = ?', ['CLIENTES'])
            ->whereRaw('UPPER(last_name) = ?', ['VARIOS'])
            ->value('id');

        if (!$defaultClientId) {
            $defaultClientId = 4;
        }
        
        // Si se pasa un movement_id, cargar la venta pendiente o con pago parcial
        $draftSale = null;
        $pendingAmount = 0;
        if ($request->has('movement_id')) {
            $movement = Movement::with(['salesMovement.details.product', 'cashMovement'])
                ->where('id', $request->movement_id)
                ->whereIn('status', ['P', 'A']) // Cargar si está pendiente o activo (puede tener deuda)
                ->first();
            
            if ($movement && $movement->salesMovement) {
                // Calcular el monto pendiente si hay una deuda
                $relatedCashMovement = $movement->cashMovement ?: $this->resolveCashMovementBySaleMovement($movement->id);
                if ($relatedCashMovement) {
                    $debt = DB::table('cash_movement_details')
                        ->where('cash_movement_id', $relatedCashMovement->id)
                        ->where('type', 'DEUDA')
                        ->where('status', 'A')
                        ->sum('amount');
                    $pendingAmount = $debt ?? 0;
                }
                
                $defaultTaxRate = TaxRate::where('status', true)->orderBy('order_num')->first();
                $defaultTaxPct = $defaultTaxRate ? (float) $defaultTaxRate->tax_rate : 18;
                $draftSale = [
                    'id' => $movement->id,
                    'number' => $movement->number,
                    'clientId' => $movement->person_id,
                    'items' => $movement->salesMovement->details->map(function ($detail) use ($defaultTaxPct) {
                        $taxRatePct = $defaultTaxPct;
                        if ($detail->tax_rate_snapshot && isset($detail->tax_rate_snapshot['tax_rate'])) {
                            $taxRatePct = (float) $detail->tax_rate_snapshot['tax_rate'];
                        }
                        $amountWithTax = (float) $detail->amount;
                        $quantity = (float) $detail->quantity ?: 1;
                        $priceWithTax = $quantity > 0 ? $amountWithTax / $quantity : 0;
                        return [
                            'kind' => $detail->product_id ? 'product' : 'glosa',
                            'pId' => $detail->product_id,
                            'name' => $detail->product->description ?? $detail->description ?? 'Detalle',
                            'qty' => $quantity,
                            'price' => $priceWithTax,
                            'tax_rate' => $taxRatePct,
                            'note' => $detail->comment ?? '',
                            'product_note' => $detail->product->note ?? null,
                        ];
                    })->toArray(),
                    'clientName' => $movement->person_name ?? 'Público General',
                    'notes' => $movement->comment ?? '',
                    'pendingAmount' => $pendingAmount,
                    'product_notes' => $movement->salesMovement->details->pluck('product.note')->toArray(),
                ];
            }
        }
        
        // Obtener todos los productos para poder mostrar sus nombres cuando se carga desde localStorage
        $products = Product::pluck('description', 'id')->toArray();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId ?? 0)
            ->with('taxRate')
            ->get()
            ->map(fn ($pb) => [
                'product_id' => (int) $pb->product_id,
                'price' => (float) $pb->price,
                'tax_rate' => $pb->taxRate ? (float) $pb->taxRate->tax_rate : null,
            ])
            ->values();
        
        return view('sales.charge', [
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'cashRegisters' => $cashRegisters,
            'defaultCashRegisterId' => $defaultCashRegisterId,
            'standardCashRegisterId' => $standardCashRegisterId,
            'invoiceCashRegisterId' => $invoiceCashRegisterId,
            'people' => $people,
            'defaultClientId' => $defaultClientId,
            'draftSale' => $draftSale,
            'pendingAmount' => $pendingAmount,
            'products' => $products,
            'productBranches' => $productBranches,
        ]);
    }
    // POS: procesar venta
    public function processSale(Request $request)
    {
        $user = $request->user();
        $branchId = (int) (session('branch_id') ?? $user?->branch_id ?? $user?->person?->branch_id);
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo determinar la sucursal del usuario logueado.',
            ], 422);
        }

        $request->merge([
            'items' => collect((array) $request->input('items', []))
                ->filter(function ($item) {
                    $productId = (int) data_get($item, 'pId', 0);
                    $name = trim((string) data_get($item, 'name', ''));
                    return $productId > 0 || $name !== '';
                })
                ->values()
                ->all(),
        ]);

        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.kind' => 'nullable|string|in:product,glosa',
                'items.*.pId' => 'nullable|integer|exists:products,id',
                'items.*.name' => 'nullable|string|max:255',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.unit_id' => 'nullable|integer|exists:units,id',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
                'document_type_id' => 'required|integer|exists:document_types,id',
                'cash_register_id' => [
                    'required',
                    'integer',
                    Rule::exists('cash_registers', 'id')->where(fn ($query) => $query->where('branch_id', $branchId)),
                ],
                'person_id' => 'nullable|integer|exists:people,id',
                'payment_type' => 'required|string|in:CONTADO,DEUDA',
                'payment_methods' => 'nullable|array',
                'payment_methods.*.payment_method_id' => 'nullable|integer|exists:payment_methods,id',
                'payment_methods.*.amount' => 'nullable|numeric|min:0',
                'payment_methods.*.payment_gateway_id' => 'nullable|integer|exists:payment_gateways,id',
                'payment_methods.*.card_id' => 'nullable|integer|exists:cards,id',
                'payment_methods.*.digital_wallet_id' => 'nullable|integer|exists:digital_wallets,id',
                'billing_status' => 'nullable|string|in:PENDING,INVOICED,NOT_APPLICABLE',
                'invoice_series' => 'nullable|string|max:20',
                'invoice_number' => 'nullable|string|max:50',
                'discount_type' => 'nullable|string|in:NONE,PERCENTAGE,AMOUNT',
                'discount_value' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
                'series' => 'nullable|string|max:20',
                'number' => 'nullable|string|max:255',
                'movement_id' => 'nullable|integer|exists:movements,id', // ID del borrador a completar
                'moved_at' => 'nullable|string|max:32',
                'credit_days' => 'nullable|integer|min:0|max:3650',
                'debt_due_date' => 'nullable|date_format:Y-m-d',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        foreach ((array) ($validated['items'] ?? []) as $index => $item) {
            $productId = (int) ($item['pId'] ?? 0);
            $name = trim((string) ($item['name'] ?? ''));
            if ($productId <= 0 && $name === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => ["items.{$index}.name" => ['La glosa o detalle manual debe tener una descripción.']],
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $branch = Branch::findOrFail($branchId);
            
            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();
            
            // Si no hay turno de la sucursal, usar el primero disponible
            if (!$shift) {
                $shift = Shift::first();
            }
            
            if (!$shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }
            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();
            
            if (!$movementType) {
                $movementType = MovementType::first();
            }
            
            if (!$movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido. Por favor, crea un tipo de movimiento primero.');
            }
            
            $documentType = DocumentType::findOrFail($request->document_type_id);
            $isInvoiceDocument = $this->isInvoiceDocumentType($documentType);
            $billingStatus = $this->normalizeBillingStatus($validated['billing_status'] ?? null, $isInvoiceDocument);
            $paymentType = strtoupper((string) ($validated['payment_type'] ?? 'CONTADO'));
            $isDebtSale = $paymentType === 'DEUDA';
            $discountType = strtoupper((string) ($validated['discount_type'] ?? 'NONE'));
            $discountValue = (float) ($validated['discount_value'] ?? 0);
            $movedAt = now();
            if (!empty($validated['moved_at'])) {
                try {
                    $movedAt = Carbon::parse($validated['moved_at']);
                } catch (\Throwable $e) {
                    $movedAt = now();
                }
            }
            $debtDueAt = $movedAt->copy();
            if ($isDebtSale) {
                $dueDateStr = trim((string) ($validated['debt_due_date'] ?? ''));
                if ($dueDateStr !== '') {
                    try {
                        $debtDueAt = Carbon::parse($dueDateStr)->endOfDay();
                    } catch (\Throwable $e) {
                        $debtDueAt = $movedAt->copy()->addDays(max(0, (int) ($validated['credit_days'] ?? 0)));
                    }
                } else {
                    $debtDueAt = $movedAt->copy()->addDays(max(0, (int) ($validated['credit_days'] ?? 0)));
                }
            }
            $headerSeries = trim((string) ($validated['series'] ?? ''));
            $invoiceSeries = $isInvoiceDocument
                ? trim((string) ($validated['invoice_series'] ?? '001'))
                : '001';
            $invoiceNumber = $isInvoiceDocument && $billingStatus === 'INVOICED'
                ? trim((string) ($validated['invoice_number'] ?? ''))
                : null;

            if ($isInvoiceDocument && $billingStatus === 'INVOICED') {
                if ($invoiceSeries === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'invoice_series' => 'La serie es obligatoria cuando la factura ya esta facturada.',
                    ]);
                }

                if ($invoiceNumber === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'invoice_number' => 'El correlativo es obligatorio cuando la factura ya esta facturada.',
                    ]);
                }
            }

            $selectedPerson = null;
            if (!empty($validated['person_id'])) {
                $selectedPerson = Person::query()
                    ->where('id', $validated['person_id'])
                    ->where('branch_id', $branchId)
                    ->firstOrFail();
            }

            // Obtener concepto de pago para ventas (Pago de cliente - ID 5)
            $paymentConcept = PaymentConcept::find(3); // Pago de cliente
            
            // Si no existe el ID 5, buscar por descripción
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%pago de cliente%')
                    ->orWhere('description', 'like', '%Pago de cliente%')
                    ->first();
            }
            
            // Si aún no se encuentra, buscar cualquier concepto de ingreso relacionado con venta
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%cliente%')
                    ->where('type', 'I')
                    ->first();
            }
            
            // Si aún no se encuentra, buscar cualquier concepto de ingreso
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('type', 'I')->first();
            }
            
            if (!$paymentConcept) {
                throw new \Exception('No se encontró un concepto de pago válido. Por favor, crea un concepto de pago primero.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $calculated = $this->calculateSubtotalAndTaxFromItems($validated['items'], $branchId, $discountType, $discountValue);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Caja seleccionada desde el formulario de cobro
            $cashRegister = CashRegister::query()
                ->where('id', $validated['cash_register_id'])
                ->where('branch_id', $branchId)
                ->first();
            if (!$cashRegister) {
                throw new \Exception('La caja seleccionada no pertenece a la sucursal del usuario logueado.');
            }

            // Validar que la suma de los métodos de pago sea igual al total
            $paymentRows = collect($validated['payment_methods'] ?? [])
                ->filter(fn ($row) => !empty($row['payment_method_id']) && (float) ($row['amount'] ?? 0) > 0)
                ->values()
                ->all();

            if (!$isDebtSale && $total > 0.009 && empty($paymentRows)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_methods' => 'Debes registrar al menos un metodo de pago cuando la venta es al contado.',
                ]);
            }

            $totalPaymentMethods = array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $paymentRows));
            if (!$isDebtSale && abs($totalPaymentMethods - $total) > 0.01) {
                throw new \Exception("La suma de los métodos de pago ({$totalPaymentMethods}) debe ser igual al total ({$total})");
            }

            // Recalcular con la misma regla (precio final con IGV incluido)
            $calculated = $this->calculateSubtotalAndTaxFromItems($validated['items'], $branchId, $discountType, $discountValue);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Calcular el total recibido de todos los métodos de pago
            $amountReceived = $isDebtSale ? 0 : $totalPaymentMethods;

            // Verificar si es un borrador a completar
            $isDraft = $request->has('movement_id') && $request->movement_id;
            $movement = null;
            $number = null;
            
            if ($isDraft) {
                // Cargar el movimiento existente (borrador)
                $movement = Movement::where('id', $request->movement_id)
                    ->where('branch_id', $branchId)
                    ->first();
                
                if (!$movement) {
                    throw new \Exception('No se encontró el movimiento de venta');
                }
                
                $number = trim((string) ($validated['number'] ?? '')) ?: $movement->number;
                $headerSeries = $headerSeries !== ''
                    ? $headerSeries
                    : trim((string) ($movement->salesMovement?->series ?? ''));
                
                $previousMovementStatus = (string) $movement->status;

                // Actualizar el movimiento - siempre se completa el pago completo
                $movement->update([
                    'number' => $number,
                    'comment' => $request->notes ?? ($isDebtSale ? 'Venta registrada como deuda' : 'Venta completada desde punto de venta'),
                    'status' => 'A', // Siempre Activo (pago completo)
                    'document_type_id' => $documentType->id,
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'moved_at' => $movedAt,
                ]);
                
                // Al editar una venta activa, primero reponer el stock previo antes de recalcular.
                if ($movement->salesMovement) {
                    if ($previousMovementStatus === 'A') {
                        foreach ($movement->salesMovement->details()->get(['product_id', 'quantity']) as $existingDetail) {
                            if (!$existingDetail->product_id) {
                                continue;
                            }

                            $productBranchToRestore = ProductBranch::query()
                                ->where('product_id', $existingDetail->product_id)
                                ->where('branch_id', $branchId)
                                ->lockForUpdate()
                                ->first();

                            if ($productBranchToRestore) {
                                $productBranchToRestore->update([
                                    'stock' => (float) ($productBranchToRestore->stock ?? 0) + (float) ($existingDetail->quantity ?? 0),
                                ]);
                            }
                        }
                    }

                    SalesMovementDetail::where('sales_movement_id', $movement->salesMovement->id)->delete();
                }
            } else {
                // Crear nuevo Movement
                $number = $this->generateSaleNumber(
                    (int) $documentType->id,
                    (int) $cashRegister->id,
                    true
                );
                
                $movement = Movement::create([
                    'number' => $number,
                    'moved_at' => $movedAt,
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->id,
                    'responsible_name' => trim((string) ($user?->name ?? 'Sistema')),
                    'comment' => $request->notes ?? ($isDebtSale ? 'Venta registrada como deuda' : ''),
                    'status' => 'A', // Siempre Activo (pago completo)
                    'movement_type_id' => $movementType->id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                    'parent_movement_id' => null,
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                ]);
            } 

            if ($isInvoiceDocument && $billingStatus === 'INVOICED') {
                $this->ensureUniqueInvoiceReference(
                    $branchId,
                    (int) $documentType->id,
                    $invoiceSeries,
                    $invoiceNumber,
                    (int) $movement->id
                );
            }

            // Crear o actualizar SalesMovement
            if ($isDraft && $movement->salesMovement) {
                // Actualizar el SalesMovement existente
                $salesMovement = $movement->salesMovement;
                $salesMovement->update([
                    'series' => $isInvoiceDocument
                        ? ($invoiceSeries !== '' ? $invoiceSeries : ($salesMovement->series ?: '001'))
                        : ($headerSeries !== '' ? $headerSeries : ($salesMovement->series ?: ($cashRegister->series ?: '001'))),
                    'billing_status' => $billingStatus,
                    'billing_number' => $invoiceNumber,
                    'payment_type' => $isDebtSale ? 'CREDITO' : 'CONTADO',
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);
            } else {
                // Crear nuevo SalesMovement
                $salesMovement = SalesMovement::create([
                    'branch_snapshot' => [
                        'id' => $branch->id,
                        'legal_name' => $branch->legal_name,
                    ],
                    'series' => $isInvoiceDocument
                        ? ($invoiceSeries !== '' ? $invoiceSeries : '001')
                        : ($headerSeries !== '' ? $headerSeries : ($cashRegister->series ?: '001')),
                    'billing_status' => $billingStatus,
                    'billing_number' => $invoiceNumber,
                    'year' => Carbon::now()->year,
                    'detail_type' => 'DETALLADO',
                    'consumption' => 'N',
                    'payment_type' => $isDebtSale ? 'CREDITO' : 'CONTADO',
                    'status' => 'N' ,
                    'sale_type' => 'MINORISTA',
                    'currency' => 'PEN',
                    'exchange_rate' => 3.5,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear SalesMovementDetails y actualizar stock (nota por producto en comment)
            foreach (array_values($validated['items']) as $index => $item) {
                $productId = (int) ($item['pId'] ?? 0);
                $lineCalculated = $calculated['lines'][$index] ?? null;
                if (!$lineCalculated) {
                    throw new \Exception('No se pudo calcular el descuento de una línea de la venta');
                }

                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;

                if ($productId > 0) {
                    $product = Product::with('baseUnit')->findOrFail($productId);

                    $productBranch = ProductBranch::with('taxRate')
                        ->where('product_id', $productId)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();

                    if (!$productBranch) {
                        throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                    }

                    $quantityToSell = (int) $item['qty'];
                    $currentStock = (int) ($productBranch->stock ?? 0);
                    $unit = $product->baseUnit;
                    if (!$unit) {
                        throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                    }

                    $taxRate = $productBranch->taxRate;
                    SalesMovementDetail::create([
                        'detail_type' => 'DETAILED',
                        'sales_movement_id' => $salesMovement->id,
                        'code' => $product->code,
                        'description' => $product->description,
                        'product_id' => $product->id,
                        'product_snapshot' => [
                            'id' => $product->id,
                            'code' => $product->code,
                            'description' => $product->description,
                            'marca' => $product->marca,
                        ],
                        'unit_id' => $unit->id,
                        'tax_rate_id' => $taxRate?->id,
                        'tax_rate_snapshot' => $taxRate ? [
                            'id' => $taxRate->id,
                            'description' => $taxRate->description,
                            'tax_rate' => $taxRate->tax_rate,
                        ] : null,
                        'quantity' => $item['qty'],
                        'amount' => $lineCalculated['discounted_gross_total'],
                        'discount_percentage' => $calculated['discount_percentage'],
                        'original_amount' => $lineCalculated['net_total'],
                        'comment' => $detailNote,
                        'parent_detail_id' => null,
                        'complements' => [],
                        'status' => 'A',
                        'branch_id' => $branchId,
                    ]);

                    $productBranch->update([
                        'stock' => $currentStock - $quantityToSell
                    ]);
                    continue;
                }

                $defaultUnitId = Unit::query()->value('id');
                $manualUnitId = (int) ($item['unit_id'] ?? 0);
                $unitId = $manualUnitId > 0 ? $manualUnitId : (int) $defaultUnitId;
                if (!$unitId) {
                    throw new \Exception('No existen unidades registradas para guardar la glosa de la venta.');
                }

                SalesMovementDetail::create([
                    'detail_type' => 'GLOSA',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => '',
                    'description' => trim((string) ($item['name'] ?? '')) ?: 'Detalle',
                    'product_id' => null,
                    'product_snapshot' => null,
                    'unit_id' => $unitId,
                    'tax_rate_id' => null,
                    'tax_rate_snapshot' => $lineCalculated['tax_rate_value'] > 0 ? [
                        'description' => 'Manual',
                        'tax_rate' => round($lineCalculated['tax_rate_value'] * 100, 6),
                    ] : null,
                    'quantity' => $item['qty'],
                    'amount' => $lineCalculated['discounted_gross_total'],
                    'discount_percentage' => $calculated['discount_percentage'],
                    'original_amount' => $lineCalculated['net_total'],
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }
            
            app(KardexSyncService::class)->syncMovement($movement);

            // Crear/actualizar movimiento de caja separado del movimiento de venta.
            // Compatibilidad: algunas ventas antiguas guardaron el cash_movement directamente en el movement de venta.
            $legacyDirectCashMovement = CashMovements::query()
                ->where('movement_id', $movement->id)
                ->first();
            $cashEntryMovement = $this->resolveCashEntryMovementBySaleMovement($movement->id);

            if (!$cashEntryMovement && !$legacyDirectCashMovement) {
                $cashEntryMovement = Movement::create([
                    'number' => $this->generateCashMovementNumber(
                        (int) $branchId,
                        (int) $cashRegister->id,
                        (int) $paymentConcept->id
                    ),
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->id,
                    'responsible_name' => trim((string) ($user?->name ?? 'Sistema')),
                    'comment' => ($isDebtSale ? 'Registro de deuda de venta ' : 'Cobro de venta ') . $movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                    'branch_id' => $branchId,
                    'parent_movement_id' => $movement->id,
                ]);
            } elseif ($cashEntryMovement) {
                $cashEntryMovement->update([
                    'moved_at' => now(),
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'comment' => ($isDebtSale ? 'Registro de deuda de venta ' : 'Cobro de venta ') . $movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                ]);
            }

            // Crear o actualizar CashMovement (entrada de dinero)
            $cashMovement = $legacyDirectCashMovement;
            if (!$cashMovement && $cashEntryMovement) {
                $cashMovement = CashMovements::where('movement_id', $cashEntryMovement->id)->first();
            }
            $cashReferenceNumber = $cashEntryMovement?->number ?: $movement->number;

            if ($cashMovement) {
                $cashMovement->update([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                    'branch_id' => $branchId,
                ]);
                DB::table('cash_movement_details')
                    ->where('cash_movement_id', $cashMovement->id)
                    ->delete();
            } else {
                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                    'movement_id' => $cashEntryMovement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear CashMovementDetail para cada método de pago
            if ($isDebtSale) {
                $debtPaymentMethod = $this->resolveDebtPaymentMethod();

                DB::table('cash_movement_details')->insert([
                    'cash_movement_id' => $cashMovement->id,
                    'type' => 'DEUDA',
                    'due_at' => $debtDueAt,
                    'paid_at' => null,
                    'payment_method_id' => $debtPaymentMethod->id,
                    'payment_method' => $debtPaymentMethod->description ?? 'Deuda',
                    'number' => $cashReferenceNumber,
                    'card_id' => null,
                    'card' => '',
                    'bank_id' => null,
                    'bank' => '',
                    'digital_wallet_id' => null,
                    'digital_wallet' => '',
                    'payment_gateway_id' => null,
                    'payment_gateway' => '',
                    'amount' => $total,
                    'comment' => $request->notes ?? ('Venta registrada como deuda - ' . $documentType->name),
                    'status' => 'A',
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                app(AccountReceivablePayableService::class)->syncDebtAccount(
                    $cashMovement,
                    AccountReceivablePayableService::TYPE_RECEIVABLE,
                    $debtDueAt
                );
            } else {
                app(AccountReceivablePayableService::class)->removeDebtAccountByCashMovementId((int) $cashMovement->id);

                foreach ($paymentRows as $paymentMethodData) {
                $paymentMethod = PaymentMethod::findOrFail($paymentMethodData['payment_method_id']);
                $paymentMethodDescription = mb_strtolower((string) ($paymentMethod->description ?? ''));
                $paymentGateway = null;
                $card = null;
                $digitalWallet = null;

                if ((str_contains($paymentMethodDescription, 'billetera') || str_contains($paymentMethodDescription, 'wallet')) && empty($paymentMethodData['digital_wallet_id'])) {
                    throw new \Exception('Debe seleccionar la billetera digital del metodo de pago.');
                }

                if ((str_contains($paymentMethodDescription, 'tarjeta') || str_contains($paymentMethodDescription, 'card')) && empty($paymentMethodData['card_id'])) {
                    throw new \Exception('Debe seleccionar el detalle de tarjeta del metodo de pago.');
                }
                
                if ($paymentMethodData['payment_gateway_id']) {
                    $paymentGateway = PaymentGateways::find($paymentMethodData['payment_gateway_id']);
                }
                
                if ($paymentMethodData['card_id']) {
                    $card = Card::find($paymentMethodData['card_id']);
                }

                if (!empty($paymentMethodData['digital_wallet_id'])) {
                    $digitalWallet = DigitalWallet::find($paymentMethodData['digital_wallet_id']);
                }
                
                DB::table('cash_movement_details')->insert([
                    'cash_movement_id' => $cashMovement->id,
                    'type' => 'PAGADO',
                    'paid_at' => now(),
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method' => $paymentMethod->description ?? '',
                    'number' => $cashReferenceNumber,
                    'card_id' => $card?->id,
                    'card' => $card?->description ?? '',
                    'bank_id' => null,
                    'bank' => '',
                    'digital_wallet_id' => $digitalWallet?->id,
                    'digital_wallet' => $digitalWallet?->description ?? '',
                    'payment_gateway_id' => $paymentGateway?->id,
                    'payment_gateway' => $paymentGateway?->description ?? '',
                    'amount' => $paymentMethodData['amount'],
                    'comment' => $request->notes ?? 'Venta desde punto de venta - ' . $documentType->name,
                    'status' => 'A',
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta procesada correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'cash_movement_id' => $cashEntryMovement?->id ?? $cashMovement?->id,
                    'number' => $number,
                    'total' => $total,
                ]
            ]);
            

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar la venta: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            $message = config('app.debug') 
                ? $e->getMessage() 
                : 'Error al procesar la venta';
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    // Guardar venta como borrador/pendiente (sin pago)
    public function saveDraft(Request $request)
    {
        $request->merge([
            'items' => collect((array) $request->input('items', []))
                ->filter(function ($item) {
                    $productId = (int) data_get($item, 'pId', 0);
                    $name = trim((string) data_get($item, 'name', ''));
                    return $productId > 0 || $name !== '';
                })
                ->values()
                ->all(),
        ]);

        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.kind' => 'nullable|string|in:product,glosa',
                'items.*.pId' => 'nullable|integer|exists:products,id',
                'items.*.name' => 'nullable|string|max:255',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.unit_id' => 'nullable|integer|exists:units,id',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
                'document_type_id' => 'nullable|integer|exists:document_types,id',
                'payment_type' => 'nullable|string|in:CONTADO,DEUDA',
                'billing_status' => 'nullable|string|in:PENDING,INVOICED,NOT_APPLICABLE',
                'invoice_series' => 'nullable|string|max:20',
                'invoice_number' => 'nullable|string|max:50',
                'discount_type' => 'nullable|string|in:NONE,PERCENTAGE,AMOUNT',
                'discount_value' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        foreach ((array) ($validated['items'] ?? []) as $index => $item) {
            $productId = (int) ($item['pId'] ?? 0);
            $name = trim((string) ($item['name'] ?? ''));
            if ($productId <= 0 && $name === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => ["items.{$index}.name" => ['La glosa o detalle manual debe tener una descripción.']],
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);
            
            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();
            if (!$shift) {
                $shift = Shift::first();
            }
            if (!$shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }

            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();
            
            if (!$movementType) {
                $movementType = MovementType::first();
            }
            
            if (!$movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido.');
            }
            
            // Obtener documento por defecto si no se especifica
            $documentType = null;
            if ($request->document_type_id) {
                $documentType = DocumentType::find($request->document_type_id);
            }
            
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }
            
            if (!$documentType) {
                $documentType = DocumentType::first();
            }
            
            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $discountType = strtoupper((string) ($validated['discount_type'] ?? 'NONE'));
            $discountValue = (float) ($validated['discount_value'] ?? 0);
            $calculated = $this->calculateSubtotalAndTaxFromItems($validated['items'], $branchId, $discountType, $discountValue);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            $isInvoiceDocument = $this->isInvoiceDocumentType($documentType);
            $billingStatus = $this->normalizeBillingStatus($validated['billing_status'] ?? null, $isInvoiceDocument);
            $paymentType = strtoupper((string) ($validated['payment_type'] ?? 'CONTADO'));
            $invoiceSeries = $isInvoiceDocument
                ? trim((string) ($validated['invoice_series'] ?? '001'))
                : '001';
            $invoiceNumber = $isInvoiceDocument && $billingStatus === 'INVOICED'
                ? trim((string) ($validated['invoice_number'] ?? ''))
                : null;

            // Generar numero de movimiento con serie/correlativo por documento y caja activa
            $activeCashRegisterId = $this->resolveActiveCashRegisterId((int) $branchId);
            $number = $this->generateSaleNumber(
                (int) $documentType->id,
                $activeCashRegisterId,
                true
            );

            // Crear Movement con status 'P' (Pendiente) o 'I' (Inactivo)
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => null,
                'person_name' => 'Público General',
                'responsible_id' => $user?->id,
                'responsible_name' => $user?->name ?? 'Sistema',
                'comment' => ($request->notes ?? 'Venta pendiente de pago') . ' [BORRADOR]',
                'status' => 'P', // P = Pendiente
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time
                ],
            ]); 

            // Crear SalesMovement con payment_type 'CREDIT' (pendiente de pago)
            $salesMovement = SalesMovement::create([
                'branch_snapshot' => [
                    'id' => $branch->id,
                    'legal_name' => $branch->legal_name,
                ],
                'series' => $invoiceSeries !== '' ? $invoiceSeries : '001',
                'billing_status' => $billingStatus,
                'billing_number' => $invoiceNumber,
                'year' => Carbon::now()->year,
                'detail_type' => 'DETALLADO',
                'consumption' => 'N',
                'payment_type' => $paymentType === 'DEUDA' ? 'CREDITO' : 'CONTADO',
                'status' => 'N',
                'sale_type' => 'MINORISTA',
                'currency' => 'PEN',
                'exchange_rate' => 1.000,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            // Crear SalesMovementDetails (sin restar stock porque es borrador; nota por producto en comment)
            foreach (array_values($validated['items']) as $index => $item) {
                $productId = (int) ($item['pId'] ?? 0);
                $lineCalculated = $calculated['lines'][$index] ?? null;
                if (!$lineCalculated) {
                    throw new \Exception('No se pudo calcular el descuento de una línea del borrador');
                }

                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;

                if ($productId > 0) {
                    $product = Product::with('baseUnit')->findOrFail($productId);
                    $productBranch = ProductBranch::with('taxRate')
                        ->where('product_id', $productId)
                        ->where('branch_id', $branchId)
                        ->first();

                    if (!$productBranch) {
                        throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                    }

                    $unit = $product->baseUnit;
                    if (!$unit) {
                        throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                    }

                    $taxRate = $productBranch->taxRate;
                    SalesMovementDetail::create([
                        'detail_type' => 'DETAILED',
                        'sales_movement_id' => $salesMovement->id,
                        'code' => $product->code,
                        'description' => $product->description,
                        'product_id' => $product->id,
                        'product_snapshot' => [
                            'id' => $product->id,
                            'code' => $product->code,
                            'description' => $product->description,
                            'marca' => $product->marca,
                        ],
                        'unit_id' => $unit->id,
                        'tax_rate_id' => $taxRate?->id,
                        'tax_rate_snapshot' => $taxRate ? [
                            'id' => $taxRate->id,
                            'description' => $taxRate->description,
                            'tax_rate' => $taxRate->tax_rate,
                        ] : null,
                        'quantity' => $item['qty'],
                        'amount' => $lineCalculated['discounted_gross_total'],
                        'discount_percentage' => $calculated['discount_percentage'],
                        'original_amount' => $lineCalculated['net_total'],
                        'comment' => $detailNote,
                        'parent_detail_id' => null,
                        'complements' => [],
                        'status' => 'A',
                        'branch_id' => $branchId,
                    ]);
                    continue;
                }

                $defaultUnitId = Unit::query()->value('id');
                $manualUnitId = (int) ($item['unit_id'] ?? 0);
                $unitId = $manualUnitId > 0 ? $manualUnitId : (int) $defaultUnitId;
                if (!$unitId) {
                    throw new \Exception('No existen unidades registradas para guardar la glosa del borrador.');
                }

                SalesMovementDetail::create([
                    'detail_type' => 'GLOSA',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => '',
                    'description' => trim((string) ($item['name'] ?? '')) ?: 'Detalle',
                    'product_id' => null,
                    'product_snapshot' => null,
                    'unit_id' => $unitId,
                    'tax_rate_id' => null,
                    'tax_rate_snapshot' => $lineCalculated['tax_rate_value'] > 0 ? [
                        'description' => 'Manual',
                        'tax_rate' => round($lineCalculated['tax_rate_value'] * 100, 6),
                    ] : null,
                    'quantity' => $item['qty'],
                    'amount' => $lineCalculated['discounted_gross_total'],
                    'discount_percentage' => $calculated['discount_percentage'],
                    'original_amount' => $lineCalculated['net_total'],
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta guardada como borrador correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'number' => $number,
                    'total' => $total,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar borrador de venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar borrador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validateSale($request);

        $user = $request->user();
        $personName = null;
        if (!empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name . ' ' . $person->last_name) : null;
        }

        Movement::create([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->name ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta creada correctamente.');
    }

    public function edit(Movement $sale)
    {
        return view('sales.create', $this->getSalesPosViewData($sale));
    }

    public function update(Request $request, Movement $sale)
    {
        $data = $this->validateSale($request);

        $personName = null;
        if (!empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name . ' ' . $person->last_name) : null;
        }

        $sale->update([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta actualizada correctamente.');
    }

    public function invoice(Request $request, Movement $sale)
    {
        $branchId = (int) ($request->session()->get('branch_id') ?? 0);
        if ($branchId > 0 && (int) $sale->branch_id !== $branchId) {
            abort(404);
        }

        $sale->loadMissing(['salesMovement', 'documentType', 'person']);

        if (!$sale->isSalesInvoice()) {
            return back()
                ->withErrors(['error' => 'Solo las ventas con factura pueden usar este formulario.'])
                ->withInput();
        }

        if ($sale->salesBillingStatus() !== 'PENDING') {
            return back()
                ->withErrors(['error' => 'La venta seleccionada ya fue facturada.'])
                ->withInput();
        }

        $validator = validator($request->all(), [
            'invoice_sale_id' => ['required', 'integer', Rule::in([(int) $sale->id])],
            'person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id')->where(function ($query) use ($branchId) {
                    if ($branchId > 0) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'invoice_series' => ['required', 'string', 'max:20'],
            'invoice_number' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $person = Person::query()->findOrFail((int) $validated['person_id']);
        $invoiceSeries = trim((string) $validated['invoice_series']);
        $invoiceNumber = trim((string) $validated['invoice_number']);

        try {
            DB::transaction(function () use ($sale, $person, $invoiceSeries, $invoiceNumber, $branchId) {
                $salesMovement = $sale->salesMovement;
                if (!$salesMovement) {
                    throw new \RuntimeException('La venta no tiene registro comercial asociado.');
                }

                $this->ensureUniqueInvoiceReference(
                    $branchId > 0 ? $branchId : (int) $sale->branch_id,
                    (int) $sale->document_type_id,
                    $invoiceSeries,
                    $invoiceNumber,
                    (int) $sale->id
                );

                $sale->update([
                    'person_id' => (int) $person->id,
                    'person_name' => trim((string) ($person->first_name ?? '') . ' ' . (string) ($person->last_name ?? '')),
                ]);

                $salesMovement->update([
                    'series' => $invoiceSeries,
                    'billing_status' => 'INVOICED',
                    'billing_number' => $invoiceNumber,
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Factura registrada correctamente.');
    }

    public function destroy(Movement $sale)
    {
        app(KardexSyncService::class)->deleteMovement($sale->id);
        $sale->delete();

        return redirect()
            ->route('admin.sales.index', request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [])
            ->with('status', 'Venta eliminada correctamente.');
    }

    private function validateSale(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:255'],
            'moved_at' => ['required', 'date'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'comment' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:1'],
            'movement_type_id' => ['required', 'integer', 'exists:movement_types,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_movement_id' => ['nullable', 'integer', 'exists:movements,id'],
        ]);
    }

    private function getFormData(?Movement $sale = null): array
    {
        $branches = Branch::query()->orderBy('legal_name')->get(['id', 'legal_name']);
        $branchId = (int) (session('branch_id') ?? 0);
        $people = Person::query()
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);
        $movementTypes = MovementType::query()->orderBy('description')->get(['id', 'description']);
        $documentTypes = DocumentType::query()->orderBy('name')->get(['id', 'name']);

        return [
            'branches' => $branches,
            'people' => $people,
            'movementTypes' => $movementTypes,
            'documentTypes' => $documentTypes,
        ];
    }

    /** Obtiene la tasa de impuesto por defecto del sistema (valor 0-1, ej: 0.18 para 18%). */
    private function getDefaultTaxRateValue(): float
    {
        $taxRate = TaxRate::where('status', true)->orderBy('order_num')->first();
        return $taxRate ? ((float) $taxRate->tax_rate) / 100 : 0.18;
    }

    private function getBranchDefaultSaleDocumentTypeId(int $branchId, $documentTypes): ?int
    {
        if ($branchId <= 0) {
            return $documentTypes->first()?->id ? (int) $documentTypes->first()->id : null;
        }

        $configuredValue = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->where('bp.branch_id', $branchId)
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'ILIKE', '%tipo venta por defecto%')
            ->value('bp.value');

        if (is_numeric($configuredValue)) {
            $configuredId = (int) $configuredValue;
            $existsInSaleDocs = $documentTypes->contains(fn ($d) => (int) $d->id === $configuredId);
            if ($existsInSaleDocs) {
                return $configuredId;
            }
        }

        return $documentTypes->first()?->id ? (int) $documentTypes->first()->id : null;
    }

    private function getBranchConfiguredCashRegisterId(int $branchId, $cashRegisters, string $needle): ?int
    {
        if ($branchId <= 0) {
            return $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
        }

        $configuredValue = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->where('bp.branch_id', $branchId)
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'ILIKE', '%' . $needle . '%')
            ->orderBy('p.id')
            ->value('bp.value');

        if (is_numeric($configuredValue)) {
            $configuredId = (int) $configuredValue;
            $exists = $cashRegisters->contains(fn ($cashRegister) => (int) $cashRegister->id === $configuredId);
            if ($exists) {
                return $configuredId;
            }
        }

        return $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
    }

    private function isInvoiceDocumentTypeId(?int $documentTypeId, $documentTypes): bool
    {
        if ((int) $documentTypeId <= 0) {
            return false;
        }

        $documentType = collect($documentTypes)->first(fn ($item) => (int) ($item->id ?? 0) === (int) $documentTypeId);
        $name = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');

        return str_contains($name, 'factura');
    }

    /**
     * Calcula subtotal, IGV y total desde los ítems usando la tasa de impuesto de cada producto.
     * Usa la tasa configurada en ProductBranch->TaxRate; si no tiene, usa la tasa por defecto del sistema.
     */
    private function calculateSubtotalAndTaxFromItems(array $items, int $branchId, string $discountType = 'NONE', float $discountValue = 0.0): array
    {
        $defaultTaxPct = $this->getDefaultTaxRateValue();
        $preparedItems = [];
        $grossTotal = 0.0;

        foreach ($items as $index => $item) {
            $productId = (int) ($item['pId'] ?? 0);
            $manualTaxRate = max(0, (float) ($item['tax_rate'] ?? 0));
            $taxRateValue = $defaultTaxPct;

            if ($productId > 0) {
                $productBranch = ProductBranch::with('taxRate')
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->first();

                $taxRate = $productBranch?->taxRate;
                $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $defaultTaxPct;
            } elseif ($manualTaxRate > 0) {
                $taxRateValue = $manualTaxRate / 100;
            }

            $itemGrossTotal = max(0, (float) ($item['qty'] ?? 0) * (float) ($item['price'] ?? 0));
            $itemNetTotal = $taxRateValue > 0 ? ($itemGrossTotal / (1 + $taxRateValue)) : $itemGrossTotal;

            $preparedItems[$index] = [
                'gross_total' => $itemGrossTotal,
                'net_total' => $itemNetTotal,
                'tax_rate_value' => $taxRateValue,
            ];
            $grossTotal += $itemGrossTotal;
        }

        $normalizedDiscountType = strtoupper($discountType);
        if (!in_array($normalizedDiscountType, ['NONE', 'PERCENTAGE', 'AMOUNT'], true)) {
            $normalizedDiscountType = 'NONE';
        }

        $normalizedDiscountValue = max(0, (float) $discountValue);
        $effectiveDiscountPercentage = 0.0;
        $discountAmount = 0.0;

        if ($grossTotal > 0) {
            if ($normalizedDiscountType === 'PERCENTAGE') {
                $effectiveDiscountPercentage = min(100, $normalizedDiscountValue);
                $discountAmount = $grossTotal * ($effectiveDiscountPercentage / 100);
            } elseif ($normalizedDiscountType === 'AMOUNT') {
                $discountAmount = min($normalizedDiscountValue, $grossTotal);
                $effectiveDiscountPercentage = ($discountAmount / $grossTotal) * 100;
            }
        }

        $discountFactor = $grossTotal > 0
            ? max(0.0, 1 - ($discountAmount / $grossTotal))
            : 1.0;

        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;
        $lines = [];

        foreach ($preparedItems as $index => $preparedItem) {
            $discountedGrossTotal = $preparedItem['gross_total'] * $discountFactor;
            $discountedNetTotal = $preparedItem['tax_rate_value'] > 0
                ? ($discountedGrossTotal / (1 + $preparedItem['tax_rate_value']))
                : $discountedGrossTotal;
            $discountedTaxTotal = $discountedGrossTotal - $discountedNetTotal;

            $subtotal += $discountedNetTotal;
            $tax += $discountedTaxTotal;
            $total += $discountedGrossTotal;

            $lines[$index] = [
                'gross_total' => round($preparedItem['gross_total'], 6),
                'net_total' => round($preparedItem['net_total'], 6),
                'discounted_gross_total' => round($discountedGrossTotal, 6),
                'discounted_net_total' => round($discountedNetTotal, 6),
                'discounted_tax_total' => round($discountedTaxTotal, 6),
                'tax_rate_value' => $preparedItem['tax_rate_value'],
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'gross_total' => round($grossTotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'discount_percentage' => round($effectiveDiscountPercentage, 6),
            'discount_type' => $discountAmount > 0 ? $normalizedDiscountType : 'NONE',
            'discount_value' => $discountAmount > 0
                ? ($normalizedDiscountType === 'AMOUNT' ? round($discountAmount, 6) : round($effectiveDiscountPercentage, 6))
                : 0.0,
            'lines' => $lines,
        ];
    }

    public function reportSales(Request $request)
    {
        $branchId = session('branch_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $personId = $request->input('person_id');
        $documentTypeId = $request->input('document_type_id');
        $query = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement'])
            ->where('movement_type_id', 2)
            ->where('branch_id', $branchId)
            ->whereHas('salesMovement');
        if ($documentTypeId !== null && $documentTypeId !== '' && is_numeric($documentTypeId)) {
            $query->where('document_type_id', (int) $documentTypeId);
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('person_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }
        if ($personId !== null && $personId !== '') {
            $query->where('person_id', $personId);
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('moved_at', '>=', $dateFrom);
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('moved_at', '<=', $dateTo);
        }
        $sales = $query->orderBy('moved_at', 'desc')->paginate($perPage)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'sales' => $sales, 'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ]]);
        }

        $viewId = $request->input('view_id');

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sales.report', [
            'branchId' => $branchId,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'documentTypeId' => $documentTypeId,
            'documentTypes' => $documentTypes,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'sales' => $sales,
            'viewId' => $viewId,
        ]);
    }

    public function printPdf(Request $request, Movement $sale)
    {
        $sale = $this->resolvePrintableSale($sale);
        $printData = $this->buildSalePrintData($sale, $request);
        $printData['autoPrint'] = false;

        $html = view('sales.print.pdf', $printData)->render();
        $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, 'A4');

        if ($pdfBinary === null) {
            return view('sales.print.pdf', $printData);
        }

        $docName = $sale->salesDocumentCode();
        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $docName . '.pdf"',
        ]);
    }

    public function printTicket(Request $request, Movement $sale)
    {
        $sale = $this->resolvePrintableSale($sale);
        $printData = $this->buildSalePrintData($sale, $request);
        $printData['autoPrint'] = false;

        $html = view('sales.print.ticket', $printData)->render();
        $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, null, [
            '--page-width', '80mm',
            '--page-height', '220mm',
            '--margin-top', '0',
            '--margin-right', '0',
            '--margin-bottom', '0',
            '--margin-left', '0',
            '--print-media-type',
            '--disable-smart-shrinking',
            '--dpi', '203',
            '--zoom', '1.22',
        ]);

        if ($pdfBinary === null) {
            $printData['autoPrint'] = true;
            return view('sales.print.ticket', $printData);
        }

        $docName = $sale->salesDocumentCode();
        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $docName . '-ticket.pdf"',
        ]);
    }

    /**
     * Genera numero de venta en formato correlativo simple: 00000127.
     * Por sucursal, tipo de documento, año y caja (via cash_movements del cobro).
     * Mantiene compatibilidad leyendo tambien numeros historicos con formato antiguo.
     */
    private function generateSaleNumber(int $documentTypeId, int $cashRegisterId, bool $reserve = true): string
    {
        $documentType = DocumentType::find($documentTypeId);
        if (!$documentType) {
            throw new \Exception('Tipo de documento no encontrado.');
        }

        $cashRegister = CashRegister::find($cashRegisterId);
        if (!$cashRegister) {
            throw new \Exception('Caja no encontrada.');
        }

        $branchId = (int) session('branch_id');
        if (!$branchId) {
            throw new \Exception('No se encontro sucursal en sesion.');
        }

        $year = (int) now()->year;

        // Correlativo por caja: el cobro queda en cash_movements (movimiento hijo -> parent = venta).
        $saleIdsForCashRegister = CashMovements::query()
            ->join('movements as cash_entry_movement', 'cash_entry_movement.id', '=', 'cash_movements.movement_id')
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->where('cash_movements.branch_id', $branchId)
            ->whereNull('cash_movements.deleted_at')
            ->whereNull('cash_entry_movement.deleted_at')
            ->whereNotNull('cash_entry_movement.parent_movement_id')
            ->pluck('cash_entry_movement.parent_movement_id')
            ->unique()
            ->values()
            ->all();

        $query = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year);

        if ($saleIdsForCashRegister !== []) {
            $query->whereIn('id', $saleIdsForCashRegister);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($reserve) {
            $query->lockForUpdate();
        }

        $lastCorrelative = 0;
        $numbers = $query->pluck('number');

        foreach ($numbers as $number) {
            $raw = trim((string) $number);
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $raw) === 1) {
                $value = (int) $raw;
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }
                continue;
            }

            if (preg_match('/(\d+)-\d{4}$/', $raw, $matches) === 1) {
                $value = (int) $matches[1];
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }
            }
        }

        $nextCorrelative = $lastCorrelative + 1;

        return str_pad((string) $nextCorrelative, 8, '0', STR_PAD_LEFT);
    }

    private function resolveDocumentAbbreviation(string $documentName): string
    {
        $name = strtolower(trim($documentName));

        if (str_contains($name, 'boleta')) {
            return 'B';
        }
        if (str_contains($name, 'factura')) {
            return 'F';
        }
        if (str_contains($name, 'ticket')) {
            return 'T';
        }
        if (str_contains($name, 'nota')) {
            return 'N';
        }

        $firstLetter = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $documentName) ?: 'X', 0, 1));

        return $firstLetter !== '' ? $firstLetter : 'X';
    }

    private function isInvoiceDocumentType(?DocumentType $documentType): bool
    {
        $name = mb_strtolower((string) ($documentType?->name ?? ''), 'UTF-8');

        return str_contains($name, 'factura');
    }

    private function normalizeBillingStatus(?string $billingStatus, bool $isInvoiceDocument): string
    {
        if (!$isInvoiceDocument) {
            return 'NOT_APPLICABLE';
        }

        $normalized = strtoupper(trim((string) $billingStatus));

        return match ($normalized) {
            'INVOICED' => 'INVOICED',
            default => 'PENDING',
        };
    }

    private function ensureUniqueInvoiceReference(
        int $branchId,
        int $documentTypeId,
        string $series,
        string $billingNumber,
        ?int $currentMovementId = null
    ): void {
        $exists = SalesMovement::query()
            ->where('billing_status', 'INVOICED')
            ->where('series', $series)
            ->where('billing_number', $billingNumber)
            ->whereHas('movement', function ($query) use ($branchId, $documentTypeId, $currentMovementId) {
                $query
                    ->where('branch_id', $branchId)
                    ->where('document_type_id', $documentTypeId);

                if ($currentMovementId) {
                    $query->where('id', '!=', $currentMovementId);
                }
            })
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoice_number' => 'Ya existe una factura con esa serie y correlativo en esta sucursal.',
            ]);
        }
    }

    private function resolveActiveCashRegisterId(int $branchId): int
    {
        $cashRegisterId = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->orderBy('id')
            ->value('id');

        if (!$cashRegisterId) {
            $cashRegisterId = CashRegister::query()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->value('id');
        }

        if (!$cashRegisterId) {
            throw new \Exception('No hay caja activa/disponible para generar el numero de venta.');
        }

        return (int) $cashRegisterId;
    }

    private function resolveCashMovementBySaleMovement(int $saleMovementId): ?CashMovements
    {
        $cashMovement = CashMovements::where('movement_id', $saleMovementId)->first();
        if ($cashMovement) {
            return $cashMovement;
        }

        $cashEntryMovementId = Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->value('id');

        return $cashEntryMovementId ? CashMovements::where('movement_id', $cashEntryMovementId)->first() : null;
    }

    private function resolveCashEntryMovementBySaleMovement(int $saleMovementId): ?Movement
    {
        return Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->first();
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
            $movementTypeId = MovementType::find(4)?->id;
        }

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }

        if (!$movementTypeId) {
            throw new \Exception('No se encontro tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
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

    private function resolvePrintableSale(Movement $sale): Movement
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.unit',
            'salesMovement.details.product',
            'salesMovement.details.taxRate',
        ]);

        if ((int) $sale->movement_type_id !== 2 || !$sale->salesMovement) {
            abort(404, 'Venta no encontrada.');
        }

        return $sale;
    }

    private function buildSalePrintData(Movement $sale, Request $request): array
    {
        $sessionBranchId = (int) session('branch_id');
        $sessionBranch = $sessionBranchId ? Branch::find($sessionBranchId) : null;
        $branchForLogo = $sessionBranch ?: $sale->branch;

        $logoUrl = null;
        $logoFileUrl = null;
        $logoDataUri = null;
        if ($branchForLogo?->logo) {
            [$logoUrl, $logoFileUrl, $logoDataUri] = $this->resolveBranchLogoSources((string) $branchForLogo->logo);
        }

        $details = $sale->salesMovement->details
            ->sortBy('id')
            ->values();

        return [
            'sale' => $sale,
            'details' => $details,
            'branchForLogo' => $branchForLogo,
            'logoUrl' => $logoUrl,
            'logoFileUrl' => $logoFileUrl,
            'logoDataUri' => $logoDataUri,
            'printedAt' => now(),
            'paymentLabel' => $this->resolveSalePaymentLabel($sale),
            'viewId' => $request->input('view_id'),
        ];
    }

    private function resolveBranchLogoSources(string $storedLogo): array
    {
        $storedLogo = trim($storedLogo);
        if ($storedLogo === '') {
            return [null, null, null];
        }

        if (str_starts_with($storedLogo, 'http://') || str_starts_with($storedLogo, 'https://')) {
            return [$storedLogo, null, null];
        }

        $relativeLogoPath = $this->normalizeBranchLogoRelativePath($storedLogo);
        $logoUrl = $relativeLogoPath !== ''
            ? asset('storage/' . $relativeLogoPath)
            : null;

        if ($relativeLogoPath === '') {
            return [$logoUrl, null, null];
        }

        $localLogoPath = storage_path('app/public/' . $relativeLogoPath);
        if (!file_exists($localLogoPath)) {
            return [$logoUrl, null, null];
        }

        $normalized = str_replace('\\', '/', $localLogoPath);
        $logoFileUrl = 'file:///' . ltrim($normalized, '/');
        $logoDataUri = $this->buildImageDataUri($localLogoPath);

        return [$logoUrl, $logoFileUrl, $logoDataUri];
    }

    private function normalizeBranchLogoRelativePath(string $storedLogo): string
    {
        $normalized = str_replace('\\', '/', trim($storedLogo));
        $normalized = preg_replace('#^https?://[^/]+/#', '', $normalized) ?? $normalized;
        $normalized = ltrim($normalized, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return ltrim($normalized, '/');
    }

    private function buildImageDataUri(string $localPath): ?string
    {
        $content = @file_get_contents($localPath);
        if ($content === false) {
            return null;
        }

        $mimeType = @mime_content_type($localPath);
        if (!is_string($mimeType) || trim($mimeType) === '') {
            $extension = strtolower((string) pathinfo($localPath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    private function resolveSalePaymentLabel(Movement $sale): string
    {
        if (in_array(strtoupper((string) ($sale->salesMovement?->payment_type ?? '')), ['CREDIT', 'CREDITO', 'DEUDA'], true)) {
            return 'Credito';
        }

        $cashMovement = $sale->cashMovement ?: $this->resolveCashMovementBySaleMovement($sale->id);
        if (!$cashMovement) {
            return 'No especificado';
        }

        $methodName = DB::table('cash_movement_details as cmd')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'cmd.payment_method_id')
            ->where('cmd.cash_movement_id', $cashMovement->id)
            ->where('cmd.status', 'A')
            ->where('cmd.type', '!=', 'DEUDA')
            ->orderBy('cmd.id')
            ->value('pm.description');

        return $methodName ?: 'Mixto';
    }

    private function resolveDebtPaymentMethod(): PaymentMethod
    {
        $paymentMethod = PaymentMethod::query()
            ->where('description', 'ILIKE', 'deuda')
            ->where('status', true)
            ->first();

        if ($paymentMethod) {
            return $paymentMethod;
        }

        return PaymentMethod::query()->create([
            'description' => 'Deuda',
            'order_num' => (int) (PaymentMethod::query()->max('order_num') ?? 0) + 1,
            'status' => true,
        ]);
    }

    private function renderPdfWithWkhtmltopdf(string $html, ?string $pageSize = 'A4', array $extraArgs = []): ?string
    {
        $binary = $this->resolveWkhtmltopdfBinary();
        if (!$binary) {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $htmlFile = tempnam($tmpDir, 'sale_html_');
        $pdfFile = tempnam($tmpDir, 'sale_pdf_');

        if ($htmlFile === false || $pdfFile === false) {
            return null;
        }

        $htmlPath = $htmlFile . '.html';
        $pdfPath = $pdfFile . '.pdf';
        @rename($htmlFile, $htmlPath);
        @rename($pdfFile, $pdfPath);

        file_put_contents($htmlPath, $html);

        $args = array_merge([
            $binary,
            '--enable-local-file-access',
            '--disable-javascript',
            '--load-error-handling', 'ignore',
            '--load-media-error-handling', 'ignore',
            '--encoding', 'utf-8',
            '--margin-top', '10',
            '--margin-right', '10',
            '--margin-bottom', '10',
            '--margin-left', '10',
        ], $extraArgs);

        if (!empty($pageSize)) {
            $args[] = '--page-size';
            $args[] = $pageSize;
        }

        $args = array_merge($args, [
            $htmlPath,
            $pdfPath,
        ]);

        $process = new Process($args);

        try {
            $process->setTimeout(120);
            $process->run();
            $pdfExists = file_exists($pdfPath) && filesize($pdfPath) > 0;
            if (!$pdfExists) {
                Log::warning('wkhtmltopdf fallo al generar PDF', [
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);
                return null;
            }

            $content = file_get_contents($pdfPath);
            return $content === false ? null : $content;
        } catch (\Throwable $e) {
            Log::warning('Error ejecutando wkhtmltopdf: ' . $e->getMessage());
            return null;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    private function resolveWkhtmltopdfBinary(): ?string
    {
        $candidates = array_filter([
            env('WKHTML_PDF_BINARY'),
            env('WKHTMLTOPDF_BINARY'),
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf',
            '/opt/bin/wkhtmltopdf',
            '/opt/wkhtmltopdf/bin/wkhtmltopdf',
            '/var/www/Snappy/wkhtmltopdf',
            base_path('wkhtmltopdf/bin/wkhtmltopdf.exe'),
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
            'C:\Snappy\wkhtmltopdf.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
