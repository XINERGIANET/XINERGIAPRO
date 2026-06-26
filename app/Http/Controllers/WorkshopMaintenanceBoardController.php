<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Branch;
use App\Models\Card;
use App\Models\CashRegister;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\PaymentGateways;
use App\Models\Category;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkshopAdditionalAccessory;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementTechnician;
use App\Models\WorkshopPartReplacementPair;
use App\Models\WorkshopPartReplacementPhoto;
use App\Models\WorkshopPreexistingDamage;
use App\Models\WorkshopStatusHistory;
use App\Models\WorkshopService;
use App\Models\WorkshopVehicleIntakeInventoryItem;
use App\Services\VehiclePlateLookupService;
use App\Services\Workshop\WorkshopCheckoutDocumentPreviewService;
use App\Services\Workshop\WorkshopFlowService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkshopMaintenanceBoardController extends Controller
{
    public function __construct(
        private readonly WorkshopFlowService $flowService,
        private readonly WorkshopCheckoutDocumentPreviewService $checkoutPreviewService
    )
    {
        $this->middleware(function ($request, $next) {
            $routeName = (string) optional($request->route())->getName();
            if (str_starts_with($routeName, 'workshop.')) {
                WorkshopAuthorization::ensureAllowed($routeName);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        [$branchId, $companyId] = $this->branchScope();
        $correctiveServicesEnabled = $this->isCorrectiveServiceEnabled($branchId);
        $allowedStatuses = [
            'draft',
            'diagnosis',
            'awaiting_approval',
            'approved',
            'awaiting_technician_start',
            'in_progress',
            'in_progress_external',
            'paused',
            'finished',
            'delivered',
            'cancelled',
        ];
        $selectedStatus = (string) $request->query('status', 'in_progress');
        $search = trim((string) $request->query('search', ''));
        if ($selectedStatus !== 'all' && !in_array($selectedStatus, $allowedStatuses, true)) {
            $selectedStatus = 'in_progress';
        }

        $cards = WorkshopMovement::query()
            ->with([
                'movement',
                'vehicle',
                'client',
                'details' => fn($query) => $query
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('deleted_at')
                    ->orderBy('id'),
                'details.service:id,name,type,base_price,is_terciarizado',
                'details.product:id,code,description',
                'statusHistories.user:id,name',
            ])
            ->withCount([
                'details as pending_billing_count' => fn($query) => $query->whereNull('sales_movement_id'),
                'technicians as assigned_technicians_count',
            ])
            ->withSum([
                'details as pending_billing_total' => fn($query) => $query->whereNull('sales_movement_id'),
            ], 'total')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($correctiveServicesEnabled, function ($query) {
                // Excluir correctivos en borrador (están en el tablero correctivo)
                $query->where(function ($q) {
                    $q->where('service_type', '!=', 'correctivo')
                        ->orWhere('status', '!=', 'draft');
                });
            })
            ->when(
                Schema::hasColumn('workshop_movements', 'quotation_source'),
                fn($query) => $query->where(function ($scope) {
                    $scope->whereNull('quotation_source')
                        ->orWhere('quotation_source', '!=', 'external');
                })
            )
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_strtolower($search, 'UTF-8');
                $query->where(function ($inner) use ($needle) {
                    $inner->whereHas('movement', function ($movementQuery) use ($needle) {
                        $movementQuery
                            ->whereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(comment, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('client', function ($clientQuery) use ($needle) {
                        $clientQuery
                            ->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(TRIM(COALESCE(first_name, \'\') || \' \' || COALESCE(last_name, \'\'))) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(document_number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(person_type, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('vehicle', function ($vehicleQuery) use ($needle) {
                        $vehicleQuery
                            ->whereRaw('LOWER(COALESCE(type, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(model, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(TRIM(COALESCE(brand, \'\') || \' \' || COALESCE(model, \'\'))) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(plate, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(vin, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(engine_number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(chassis_number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(serial_number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('CAST(COALESCE(engine_displacement_cc, 0) AS TEXT) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('details', function ($detailQuery) use ($needle) {
                        $detailQuery
                            ->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('client', function ($clientQuery) use ($needle) {
                        $clientQuery
                            ->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(document_number, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('vehicle', function ($vehicleQuery) use ($needle) {
                        $vehicleQuery
                            ->whereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(model, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(plate, \'\')) LIKE ?', ["%{$needle}%"]);
                    });
                });
            })
            ->when($selectedStatus !== 'all', function ($query) use ($selectedStatus) {
                if ($selectedStatus === 'in_progress') {
                    return $query->whereIn('status', ['awaiting_technician_start', 'in_progress', 'paused']);
                }
                if ($selectedStatus === 'in_progress_external') {
                    return $query->where('status', 'in_progress_external');
                }
                return $query->where('status', $selectedStatus);
            })
            ->orderByRaw("CASE status WHEN 'awaiting_technician_start' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'in_progress_external' THEN 3 WHEN 'paused' THEN 4 WHEN 'approved' THEN 5 WHEN 'awaiting_approval' THEN 6 WHEN 'diagnosis' THEN 7 ELSE 8 END")
            ->orderByDesc('id')
            ->paginate(18)
            ->withQueryString();

        $formData = $this->maintenanceFormData($branchId, $companyId);

        return view('workshop.maintenance-board.index', compact(
            'cards',
            'selectedStatus',
            'search'
        ) + $formData);
    }

    public function correctiveIndex(Request $request)
    {
        [$branchId, $companyId] = $this->branchScope();

        if (!$this->isCorrectiveServiceEnabled($branchId)) {
            return redirect()->route('workshop.maintenance-board.index')
                ->withErrors(['error' => 'La funcionalidad de servicio correctivo no está habilitada.']);
        }

        $search = trim((string) $request->query('search', ''));

        $cards = WorkshopMovement::query()
            ->with([
                'movement',
                'vehicle',
                'client',
                'details' => fn($query) => $query
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('deleted_at')
                    ->orderBy('id'),
                'details.service',
                'details.product',
                'technicians.technician',
            ])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('service_type', 'correctivo')
            ->where('status', 'draft')
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_strtolower($search, 'UTF-8');
                $query->where(function ($inner) use ($needle) {
                    $inner->whereHas('movement', function ($movementQuery) use ($needle) {
                        $movementQuery
                            ->whereRaw('LOWER(COALESCE(number, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(comment, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('client', function ($clientQuery) use ($needle) {
                        $clientQuery
                            ->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(TRIM(COALESCE(first_name, \'\') || \' \' || COALESCE(last_name, \'\'))) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(document_number, \'\')) LIKE ?', ["%{$needle}%"]);
                    })->orWhereHas('vehicle', function ($vehicleQuery) use ($needle) {
                        $vehicleQuery
                            ->whereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(model, \'\')) LIKE ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(plate, \'\')) LIKE ?', ["%{$needle}%"]);
                    });
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(18)
            ->withQueryString();

        $formData = $this->maintenanceFormData($branchId, $companyId);

        return view('workshop.maintenance-board.corrective', compact(
            'cards',
            'search'
        ) + $formData);
    }

    public function create(Request $request): \Illuminate\View\View
    {
        [$branchId, $companyId] = $this->branchScope();
        $formData = $this->maintenanceFormData($branchId, $companyId);

        $preFilledVehicleId = (string) $request->query('vehicle_id', '');
        $preFilledClientId = (string) $request->query('client_person_id', '');
        $preFilledDiagnosis = (string) $request->query('diagnosis', '');
        $preFilledAppointmentId = (string) $request->query('appointment_id', '');
        $preFilledQuotationId = (string) $request->query('quotation_id', '');

        $serviceType = $request->query('service_type', 'preventivo');

        $initialServiceLines = [];
        $initialProductLines = [];
        $editingVehicleLabel = '';
        $editingClientLabel = '';

        if ($preFilledQuotationId) {
            $quotation = \App\Models\WorkshopMovement::with([
                'vehicle',
                'client',
                'details' => fn($query) => $query->whereNull('sales_movement_id')->whereNull('deleted_at')->orderBy('id'),
                'details.service',
                'details.product',
            ])->find($preFilledQuotationId);

            if ($quotation) {
                if ($quotation->vehicle_id) {
                    $preFilledVehicleId = (string) $quotation->vehicle_id;
                    $editingVehicleLabel = trim(($quotation->vehicle->plate ?? '') . ' - ' . ($quotation->vehicle->brand ?? '') . ' ' . ($quotation->vehicle->model ?? ''));
                }
                if ($quotation->client_person_id) {
                    $preFilledClientId = (string) $quotation->client_person_id;
                    $editingClientLabel = trim(($quotation->client->document_number ?? '') . ' - ' . ($quotation->client->name ?? ''));
                }

                $initialServiceLines = $this->mapQuotationDetailsToInitialServiceLines($quotation->details);

                $initialProductLines = $quotation->details
                    ->where('line_type', 'PART')
                    ->values()
                    ->map(fn($d) => [
                        'detail_id' => 0, // 0 because it's new for the order
                        'product_id' => (string) ($d->product_id ?? ''),
                        'qty' => (float) $d->qty,
                        'unit_price' => (float) $d->unit_price,
                        'label' => (string) ($d->description ?? ''),
                    ])
                    ->all();
            }
        }

        return view('workshop.maintenance-board.create', $formData + [
            'editingOrder' => null,
            'initialServiceLines' => $initialServiceLines,
            'initialProductLines' => $initialProductLines,
            'initialInventoryChecks' => [],
            'editingVehicleLabel' => $editingVehicleLabel,
            'editingClientLabel' => $editingClientLabel,
            'editingDamageRows' => [],
            'editingDamagePhotoPreviews' => [0 => [], 1 => [], 2 => [], 3 => []],
            'editingSignatureUrl' => null,
            'preFilledVehicleId' => $preFilledVehicleId,
            'preFilledClientId' => $preFilledClientId,
            'preFilledDiagnosis' => $preFilledDiagnosis,
            'preFilledAppointmentId' => $preFilledAppointmentId,
            'preFilledQuotationId' => $preFilledQuotationId,
            'serviceType' => $serviceType,
        ]);
    }

    public function edit(WorkshopMovement $order): RedirectResponse|\Illuminate\View\View
    {
        $this->assertOrderScope($order);

        if ($order->sales_movement_id) {
            return redirect()
                ->route('workshop.maintenance-board.index')
                ->withErrors(['error' => 'La OS ya fue facturada, no se puede editar desde el tablero.']);
        }

        if (in_array((string) $order->status, ['cancelled', 'delivered'], true)) {
            return redirect()
                ->route('workshop.maintenance-board.index')
                ->withErrors(['error' => 'No se puede editar una OS anulada o entregada.']);
        }

        [$branchId, $companyId] = $this->branchScope();

        $order->load([
            'vehicle',
            'client',
            'intakeInventory',
            'additionalAccessories',
            'damages.photos',
            'details' => fn($query) => $query->whereNull('sales_movement_id')->whereNull('deleted_at')->orderBy('id'),
            'details.service',
            'details.product',
        ]);

        $formData = $this->maintenanceFormData($branchId, $companyId);
        if ($order->intakeInventory->isNotEmpty()) {
            $formData['showInventoryDefault'] = true;
        }
        if ($order->damages->isNotEmpty()) {
            $formData['showDamagesPreexistingDefault'] = true;
        }
        if (trim((string) ($order->intake_client_signature_path ?? '')) !== '') {
            $formData['showClientSignatureDefault'] = true;
        }

        $initialServiceLines = $order->details
            ->where('line_type', 'SERVICE')
            ->values()
            ->map(fn($d) => [
                'detail_id' => (int) $d->id,
                'service_id' => $d->service_id !== null ? (string) $d->service_id : '',
                'description' => (string) ($d->description ?? ''),
                'qty' => (float) $d->qty,
                'unit_price' => (float) $d->unit_price,
                'validity_months' => $d->validity_months,
                'is_terciarizado' => (bool) $d->is_terciarizado,
            ])
            ->all();

        $initialProductLines = $order->details
            ->where('line_type', 'PART')
            ->values()
            ->map(fn($d) => [
                'detail_id' => (int) $d->id,
                'product_id' => (string) ($d->product_id ?? ''),
                'qty' => (float) $d->qty,
                'unit_price' => (float) $d->unit_price,
                'label' => (string) ($d->description ?? ''),
            ])
            ->all();

        $vehicleTypeId = $order->vehicle?->vehicle_type_id;
        $inventoryKeys = WorkshopVehicleIntakeInventoryItem::query()
            ->when($vehicleTypeId, fn($q) => $q->where('vehicle_type_id', (int) $vehicleTypeId))
            ->orderBy('order_num')
            ->pluck('item_key')
            ->map(fn($k) => (string) $k)
            ->all();

        $initialInventoryChecks = [];
        foreach ($inventoryKeys as $key) {
            $initialInventoryChecks[$key] = (bool) $order->intakeInventory->firstWhere('item_key', $key)?->present;
        }

        $initialAccessoryChecks = [];
        foreach ($order->additionalAccessories as $accessoryRow) {
            if ($accessoryRow->accessory_id) {
                $initialAccessoryChecks[(string) $accessoryRow->accessory_id] = (bool) $accessoryRow->present;
            }
        }

        $editingVehicleLabel = trim(((string) ($order->vehicle?->brand ?? '')) . ' ' . ((string) ($order->vehicle?->model ?? '')) . (((string) ($order->vehicle?->plate ?? '') !== '' ? ' - ' . $order->vehicle->plate : '')));
        $editingClientLabel = trim(((string) ($order->client?->first_name ?? '')) . ' ' . ((string) ($order->client?->last_name ?? '')));

        $sidesOrder = ['RIGHT', 'LEFT', 'FRONT', 'BACK'];
        $editingDamageRows = [];
        $editingDamagePhotoPreviews = [0 => [], 1 => [], 2 => [], 3 => []];
        foreach ($sidesOrder as $idx => $side) {
            $d = $order->damages->first(fn($x) => strtoupper((string) $x->side) === $side);
            $editingDamageRows[] = [
                'description' => $d ? (string) ($d->description ?? '') : '',
                'severity' => $d ? (string) ($d->severity ?? '') : '',
            ];
            if ($d) {
                $urls = [];
                foreach ($d->photos as $photo) {
                    $path = (string) ($photo->photo_path ?? '');
                    if ($path !== '' && Storage::disk('public')->exists($path)) {
                        $urls[] = [
                            'url' => Storage::disk('public')->url($path),
                            'name' => basename($path),
                        ];
                    }
                }
                if ($urls === [] && $d->photo_path && Storage::disk('public')->exists((string) $d->photo_path)) {
                    $urls[] = [
                        'url' => Storage::disk('public')->url((string) $d->photo_path),
                        'name' => basename((string) $d->photo_path),
                    ];
                }
                $editingDamagePhotoPreviews[$idx] = $urls;
            }
        }

        $editingSignatureUrl = null;
        $sigPath = (string) ($order->intake_client_signature_path ?? '');
        if ($sigPath !== '' && Storage::disk('public')->exists($sigPath)) {
            $editingSignatureUrl = Storage::disk('public')->url($sigPath);
        }

        return view('workshop.maintenance-board.create', $formData + [
            'editingOrder' => $order,
            'initialServiceLines' => $initialServiceLines,
            'initialProductLines' => $initialProductLines,
            'initialInventoryChecks' => $initialInventoryChecks,
            'initialAccessoryChecks' => $initialAccessoryChecks,
            'editingVehicleLabel' => $editingVehicleLabel,
            'editingClientLabel' => $editingClientLabel,
            'editingDamageRows' => $editingDamageRows,
            'editingDamagePhotoPreviews' => $editingDamagePhotoPreviews,
            'editingSignatureUrl' => $editingSignatureUrl,
            'serviceType' => $order->service_type ?? 'preventivo',
            'editingDriverName' => (string) ($order->driver_name ?? ''),
            'editingDriverPhone' => (string) ($order->driver_phone ?? ''),
        ]);
    }

    private function maintenanceFormData(int $branchId, int $companyId): array
    {
        $branch = Branch::query()->findOrFail($branchId);
        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->orderBy('brand')
            ->orderBy('model')
            ->get(['id', 'client_person_id', 'vehicle_type_id', 'brand', 'model', 'plate', 'current_mileage', 'engine_displacement_cc', 'soat_vencimiento', 'revision_tecnica_vencimiento']);

        $vehicleTypes = VehicleType::query()
            ->where(function ($query) use ($companyId, $branchId) {
                $query->whereNull('company_id')
                    ->orWhere(function ($scope) use ($companyId, $branchId) {
                        $scope->where('company_id', $companyId)
                            ->where(function ($branchScope) use ($branchId) {
                                $branchScope->whereNull('branch_id')
                                    ->orWhere('branch_id', $branchId);
                            });
                    });
            })
            ->where('active', true)
            ->orderBy('company_id')
            ->orderBy('branch_id')
            ->orderBy('order_num')
            ->orderBy('name')
            ->get(['id', 'name']);

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', function ($query) use ($branchId) {
                $query->where('roles.id', 3)
                    ->where('role_person.branch_id', $branchId);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number', 'person_type']);

        $services = WorkshopService::query()
            ->with([
                'priceTiers:id,workshop_service_id,max_cc,price,order_num',
                'frequencies:id,workshop_service_id,km,multiplier,order_num',
            ])
            ->where('active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'base_price', 'type', 'frequency_enabled', 'frequency_each_km', 'has_validity']);

        $saleMovementTypeId = (int) \App\Models\MovementType::query()
            ->where('description', 'ILIKE', '%venta%')
            ->orderBy('id')
            ->value('id');

        $documentTypes = DocumentType::query()
            ->when($saleMovementTypeId > 0, fn($query) => $query->where('movement_type_id', $saleMovementTypeId))
            ->orderBy('name')
            ->get(['id', 'name', 'stock']);

        $cashRegisters = CashRegister::query()
            ->when(
                Schema::hasColumn('cash_registers', 'branch_id') && $branchId > 0,
                fn($query) => $query->where('branch_id', $branchId)
            )
            ->orderBy('number')
            ->get(['id', 'number', 'status']);

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description']);

        $technicians = \App\Models\Person::query()
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 2); // Role 2 = Empleado
            })
            ->when($branchId > 0, fn($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn($p) => [
                'id' => (int) $p->id,
                'name' => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
            ])
            ->values();

        $inventoryItemsByVehicleType = WorkshopVehicleIntakeInventoryItem::query()
            ->whereNull('deleted_at')
            ->orderBy('order_num')
            ->get(['vehicle_type_id', 'item_key', 'label'])
            ->groupBy('vehicle_type_id')
            ->map(fn($items) => $items->values()->map(fn($i) => [
                'item_key' => (string) $i->item_key,
                'label' => (string) $i->label,
            ])->values()->all())
            ->toArray();

        $additionalAccessories = WorkshopAdditionalAccessory::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->orderBy('order_num')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
            ])
            ->values()
            ->all();

        $products = ProductBranch::query()
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->whereNull('products.deleted_at')
            ->orderBy('products.description')
            ->get([
                'product_branch.product_id',
                'product_branch.price',
                'product_branch.stock',
                'product_branch.tax_rate_id',
                'products.code',
                'products.marca',
                'products.description',
            ])
            ->map(fn($row) => [
                'id' => (int) $row->product_id,
                'code' => (string) ($row->code ?? ''),
                'marca' => (string) ($row->marca ?? ''),
                'description' => (string) ($row->description ?? ''),
                'price' => (float) ($row->price ?? 0),
                'stock' => (float) ($row->stock ?? 0),
                'tax_rate_id' => $row->tax_rate_id ? (int) $row->tax_rate_id : null,
            ])
            ->values();

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

        $selectedDistrictId = $branch->location_id;
        $selectedProvinceId = null;
        $selectedDepartmentId = null;
        $selectedDistrictName = null;
        $selectedProvinceName = null;
        $selectedDepartmentName = null;

        if ($selectedDistrictId) {
            $district = Location::query()->find($selectedDistrictId);
            $selectedDistrictName = $district?->name;
            $selectedProvinceId = $district?->parent_location_id;
            if ($selectedProvinceId) {
                $province = Location::query()->find($selectedProvinceId);
                $selectedProvinceName = $province?->name;
                $selectedDepartmentId = $province?->parent_location_id;
                if ($selectedDepartmentId) {
                    $department = Location::query()->find($selectedDepartmentId);
                    $selectedDepartmentName = $department?->name;
                }
            }
        }

        $showInventoryDefault = $this->branchBooleanParameter($branchId, 'Mostrar inventario', true);
        $showDamagesPreexistingDefault = $this->branchBooleanParameter($branchId, [
            'Mostrar daños preexistentes',
            'Mostrar daños preexistentes',
        ], true);
        $showClientSignatureDefault = $this->branchBooleanParameter($branchId, [
            'Mostrar firma del cliente',
            'Mostrar firma de cliente',
        ], true);

        $editableServicePricesEnabled = $this->isCatalogServicePriceEditingEnabled($branchId);

        $branchModel = Branch::query()->with('electronicBillingConfig')->find($branchId);
        $isSunatActive = $branchModel?->electronicBillingConfig?->enabled ?? false;
        $isAnticipoEnabled = $this->branchBooleanParameter($branchId, ['Facturación con pago anticipado', 'Facturacion con pago anticipado'], false);

        return compact(
            'vehicles',
            'clients',
            'services',
            'vehicleTypes',
            'documentTypes',
            'cashRegisters',
            'paymentMethods',
            'products',
            'departments',
            'provinces',
            'districts',
            'selectedDepartmentId',
            'selectedProvinceId',
            'selectedDistrictId',
            'selectedDepartmentName',
            'selectedProvinceName',
            'selectedDistrictName',
            'technicians',
            'inventoryItemsByVehicleType',
            'additionalAccessories',
            'showInventoryDefault',
            'showDamagesPreexistingDefault',
            'showClientSignatureDefault',
            'editableServicePricesEnabled',
            'isSunatActive',
            'isAnticipoEnabled'
        ) + [
            'externalServicesEnabled' => $this->isExternalServiceEnabled($branchId),
            'correctiveServicesEnabled' => $this->isCorrectiveServiceEnabled($branchId),
            'driverInfoEnabled' => $this->isDriverInfoEnabled($branchId),
            'vehicleDocumentAlertsEnabled' => $this->isVehicleDocumentAlertsEnabled($branchId),
            'productQuickCreate' => $this->maintenanceProductQuickCreateData($branchId),
        ];
    }

    private function maintenanceProductQuickCreateData(int $branchId): array
    {
        ProductType::ensureDefaultsForBranch($branchId);

        return [
            'viewId' => request('view_id'),
            'productTypes' => ProductType::query()
                ->where('branch_id', $branchId)
                ->where('status', true)
                ->orderBy('name')
                ->get(),
            'nextProductCode' => $this->nextMaintenanceProductCode($branchId),
            'currentBranch' => Branch::query()->find($branchId),
            'taxRates' => TaxRate::query()->where('status', true)->orderBy('order_num')->get(),
            'suppliers' => Person::query()
                ->where('branch_id', $branchId)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'document_number']),
            'categories' => Category::query()
                ->forBranch($branchId)
                ->orderBy('description')
                ->get(),
            'units' => Unit::query()->orderBy('description')->get(['id', 'description']),
        ];
    }

    private function nextMaintenanceProductCode(int $branchId): string
    {
        if ($branchId <= 0) {
            return '1';
        }

        $lastCode = Product::query()
            ->join('product_branch', 'product_branch.product_id', '=', 'products.id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->orderByDesc('products.id')
            ->value('products.code');

        if (!$lastCode) {
            return '1';
        }

        $code = trim((string) $lastCode);
        if (preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $next = (string) ((int) $number + 1);

            return $prefix . str_pad($next, strlen($number), '0', STR_PAD_LEFT);
        }

        if (is_numeric($code)) {
            return (string) ((int) $code + 1);
        }

        return '1';
    }

    private function isExternalServiceEnabled(int $branchId): bool
    {
        $parameter = DB::table('parameters')
            ->where('description', 'Habilitar función de servicio externo (Taller)')
            ->where('status', 1)
            ->first();

        if (!$parameter) {
            return false;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        $val = strtolower(trim((string) ($branchValue ?? $parameter->value)));

        return in_array($val, ['si', 'yes', 'true', '1'], true);
    }

    private function branchBooleanParameter(int $branchId, string|array $descriptions, bool $default = true): bool
    {
        $descriptions = (array) $descriptions;

        $parameter = DB::table('parameters')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($descriptions) {
                foreach ($descriptions as $description) {
                    $query->orWhere('description', $description);
                }
            })
            ->first();

        if (!$parameter) {
            return $default;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        return $this->booleanParameterValue($branchValue ?? $parameter->value, $default);
    }

    private function booleanParameterValue(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = strtoupper(trim((string) $value));
        $normalized = str_replace(['Í', 'í'], 'I', $normalized);

        if (in_array($normalized, ['1', 'TRUE', 'YES', 'SI', 'S'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'FALSE', 'NO', 'N'], true)) {
            return false;
        }

        return $default;
    }

    private function isCorrectiveServiceEnabled(int $branchId): bool
    {
        $parameter = DB::table('parameters')
            ->where('description', 'Agregar Funcionalidad servicio correctivo')
            ->where('status', 1)
            ->first();

        if (!$parameter) {
            return false;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        $val = strtolower(trim((string) ($branchValue ?? $parameter->value)));

        return in_array($val, ['si', 'yes', 'true', '1'], true);
    }

    private function isDriverInfoEnabled(int $branchId): bool
    {
        $parameter = DB::table('parameters')
            ->where('description', 'Habilitar registro de chofer en OS')
            ->where('status', 1)
            ->first();

        if (!$parameter) {
            return false;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        $val = strtolower(trim((string) ($branchValue ?? $parameter->value)));

        return in_array($val, ['si', 'yes', 'true', '1'], true);
    }

    private function isVehicleDocumentAlertsEnabled(int $branchId): bool
    {
        $parameter = DB::table('parameters')
            ->where('description', 'Habilitar alertas de documentos de vehiculo (SOAT/RT)')
            ->where('status', 1)
            ->first();

        if (!$parameter) {
            return true;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        $val = strtolower(trim((string) ($branchValue ?? $parameter->value)));

        return in_array($val, ['si', 'yes', 'true', '1'], true);
    }

    private function isSaveGlosaAsServiceEnabled(int $branchId): bool
    {
        return $this->branchBooleanParameter($branchId, 'Guardar glosa como servicio de taller', false);
    }

    private function isCatalogServicePriceEditingEnabled(int $branchId): bool
    {
        return $this->branchBooleanParameter($branchId, 'Permitir editar precios en cotizacion de taller', false);
    }

    private function createServiceFromGlosa(int $companyId, int $branchId, string $description, float $price, bool $isTerciarizado): ?int
    {
        if (!$this->isSaveGlosaAsServiceEnabled($branchId)) {
            return null;
        }

        $existing = WorkshopService::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('name', $description)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $service = WorkshopService::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $description,
            'type' => 'preventivo', // Default to preventivo
            'base_price' => $price,
            'estimated_minutes' => 0,
            'active' => true,
            'is_terciarizado' => $isTerciarizado,
        ]);

        return $service->id;
    }



    public function store(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        [$branchId, $companyId] = $this->branchScope();
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'client_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:workshop_movements,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'tow_in' => ['nullable', 'boolean'],
            'intake_date' => ['nullable', 'date'],
            'diagnosis_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.*' => ['nullable', 'boolean'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['nullable', 'boolean'],
            'damages' => ['nullable', 'array'],
            'damages.*.side' => ['nullable', 'in:RIGHT,LEFT,FRONT,BACK'],
            'damages.*.description' => ['nullable', 'string'],
            'damages.*.severity' => ['nullable', 'in:LOW,MED,HIGH'],
            'damages.*.photos' => ['nullable', 'array'],
            'damages.*.photos.*' => ['nullable', 'image', 'max:6144'],
            'client_signature_data' => ['nullable', 'string'],
            'service_lines' => ['nullable', 'array'],
            'service_lines.*.detail_id' => ['nullable', 'integer'],
            'service_lines.*.service_id' => ['nullable', 'string', 'max:32'],
            'service_lines.*.description' => ['nullable', 'string', 'max:255'],
            'service_lines.*.qty' => ['nullable', 'numeric'],
            'service_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'service_lines.*.price_cc_override' => ['nullable', 'string', 'max:32'],
            'service_lines.*.validity_months' => ['nullable', 'in:6,12'],
            'service_lines.*.is_terciarizado' => ['nullable', 'boolean'],
            'service_type' => ['nullable', 'string', 'in:preventivo,correctivo'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.detail_id' => ['nullable', 'integer'],
            'product_lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['nullable', 'numeric', 'gte:0'],
            'product_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'save_mode' => ['nullable', 'string', 'in:partial,full'],
        ]);

        $isPartialSave = $request->input('save_mode', 'full') === 'partial';

        $vehicle = Vehicle::query()
            ->where('id', (int) $validated['vehicle_id'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $clientPersonId = isset($validated['client_person_id']) && (int) $validated['client_person_id'] > 0
            ? (int) $validated['client_person_id']
            : (int) $vehicle->client_person_id;

        if ($clientPersonId <= 0) {
            return back()->withErrors(['error' => 'El vehiculo seleccionado no tiene cliente asociado.']);
        }

        if ((int) $vehicle->client_person_id !== $clientPersonId) {
            return back()->withErrors(['error' => 'El vehiculo no pertenece al cliente seleccionado.']);
        }

        $parsedServiceLines = [];
        if (!$isPartialSave) {
            try {
                $parsedServiceLines = $this->parseMaintenanceBoardServiceLines((array) ($validated['service_lines'] ?? []));
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
            try {
                $this->assertCatalogServiceLinesAllowed($parsedServiceLines, $branchId, $companyId);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
        }
        $editableCatalogServicePrices = $this->isCatalogServicePriceEditingEnabled($branchId);
        $intakeDate = $this->normalizeMaintenanceBoardIntakeDate($validated['intake_date'] ?? null);

        $user = auth()->user();
        try {
            $damagesWithPhotos = $this->uploadDamagePhotos($request, (array) ($validated['damages'] ?? []), $branchId);
            $signaturePath = $this->storeSignatureFromDataUri((string) ($validated['client_signature_data'] ?? ''), $branchId);

            if ($isPartialSave) {
                $status = 'draft';
                $comment = 'Recepción parcial guardada — pendiente de servicios y/o repuestos';
            } elseif (($validated['service_type'] ?? 'preventivo') === 'correctivo') {
                $status = 'draft';
                $comment = 'Inicio de flujo correctivo - Fase Recepción';
            } else {
                $status = 'awaiting_approval';
                $comment = 'OS creada desde tablero y enviada a espera de aprobación';
            }

            if (!empty($validated['quotation_id'])) {
                $status = 'in_progress';
                $comment = 'OS generada desde cotización y pasada a reparación';
            }

            $data = array_merge($validated, [
                'vehicle_id' => (int) $validated['vehicle_id'],
                'client_person_id' => $clientPersonId,
                'appointment_id' => !empty($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
                'previous_workshop_movement_id' => !empty($validated['quotation_id']) ? (int) $validated['quotation_id'] : null,
                'intake_date' => $intakeDate,
                'mileage_in' => $validated['mileage_in'] ?? null,
                'fuel_level' => $validated['fuel_level'] ?? null,
                'tow_in' => (bool) ($validated['tow_in'] ?? false),
                'diagnosis_text' => $validated['diagnosis_text'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'status' => $status,
                'comment' => $comment,
                'service_type' => $validated['service_type'] ?? 'preventivo',
            ]);

            if (($validated['service_type'] ?? 'preventivo') === 'correctivo') {
                $data['corrective_phase'] = 'recepcion';
                $data['corrective_reception_at'] = Carbon::parse($intakeDate);
            }

            $workshop = $this->flowService->createOrder(
                $data,
                $branchId,
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema')
            );

            $this->flowService->syncIntakeAndDamages(
                $workshop,
                (array) ($validated['inventory'] ?? []),
                $damagesWithPhotos,
                [
                    'intake_client_signature_path' => $signaturePath,
                ]
            );
            $this->flowService->syncAdditionalAccessories($workshop, (array) ($validated['accessories'] ?? []));

            $catalogIds = collect($parsedServiceLines)
                ->where('kind', 'catalog')
                ->pluck('service_id')
                ->unique()
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->values()
                ->all();

            $serviceCatalog = $catalogIds === []
                ? collect()
                : WorkshopService::query()
                    ->with(['priceTiers', 'frequencies'])
                    ->whereIn('id', $catalogIds)
                    ->get()
                    ->keyBy('id');

            $mileageForFrequency = (int) ($validated['mileage_in'] ?? $vehicle->current_mileage ?? 0);

            if (!$isPartialSave) {
                foreach ($parsedServiceLines as $line) {
                    if ($line['kind'] === 'glosa') {
                        $serviceId = $this->createServiceFromGlosa($companyId, $branchId, $line['description'], (float) $line['unit_price'], (bool) ($line['is_terciarizado'] ?? false));

                        $this->flowService->addDetail($workshop, [
                            'line_type' => 'SERVICE',
                            'service_id' => $serviceId,
                            'description' => $line['description'],
                            'qty' => $line['qty'],
                            'unit_price' => $line['unit_price'],
                            'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? false),
                        ]);
                        continue;
                    }

                    $serviceId = (int) $line['service_id'];
                    $qty = $line['qty'];
                    $service = $serviceCatalog->get($serviceId);
                    if (!$service || $qty <= 0) {
                        continue;
                    }

                    $resolvedPrice = $this->resolveMaintenanceBoardServiceUnitPrice(
                        $service,
                        $vehicle,
                        $mileageForFrequency,
                        (string) ($line['price_cc_override'] ?? 'auto')
                    );
                    $unitPrice = $editableCatalogServicePrices
                        ? round((float) ($line['unit_price'] ?? $resolvedPrice), 6)
                        : round((float) $resolvedPrice, 6);
                    if ($unitPrice < 0) {
                        $unitPrice = 0;
                    }

                    $this->flowService->addDetail($workshop, [
                        'line_type' => 'SERVICE',
                        'service_id' => $serviceId,
                        'description' => (string) $service->name,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'validity_months' => $line['validity_months'] ?? null,
                        'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? $service->is_terciarizado ?? false),
                    ]);
                }

                $productLines = collect($validated['product_lines'] ?? [])
                    ->filter(fn($line) => !empty($line['product_id']))
                    ->values();

                foreach ($productLines as $line) {
                    $productId = (int) $line['product_id'];
                    $row = $this->productBranchRowForMaintenance($branchId, $productId);
                    if (!$row) {
                        throw new \RuntimeException('Hay productos no disponibles en la sucursal.');
                    }

                    $qty = round((float) ($line['qty'] ?? 0), 6);
                    if ($qty <= 0) {
                        continue;
                    }

                    $unitPrice = round((float) ($line['unit_price'] ?? (float) $row->price), 6);
                    if ($unitPrice < 0) {
                        $unitPrice = 0;
                    }

                    $this->flowService->addDetail($workshop, [
                        'line_type' => 'PART',
                        'product_id' => $productId,
                        'description' => $this->formatWorkshopProductPartDescription($row),
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'tax_rate_id' => $row->tax_rate_id ? (int) $row->tax_rate_id : null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        session()->flash('status', $isPartialSave
            ? 'Recepción parcial guardada. Otro usuario puede completar servicios y repuestos después.'
            : 'Servicio registrado correctamente.');

        $redirectUrl = route('workshop.maintenance-board.index', array_filter([
            'view_id' => $request->query('view_id'),
            'status' => $this->boardIndexFilterForStatus((string) $workshop->status),
        ]));

        if (($workshop->service_type ?? '') === 'correctivo') {
            $redirectUrl = route('workshop.maintenance-board.corrective', array_filter([
                'view_id' => $request->query('view_id'),
            ]));
        }

        if ($isPartialSave) {
            return redirect($redirectUrl);
        }

        return response()->view('workshop.maintenance-board.store_redirect', [
            'reportUrl' => route('workshop.pdf.order', $workshop),
            'redirectUrl' => $redirectUrl,
        ]);
    }

    public function update(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        if ($order->sales_movement_id) {
            return back()->withErrors(['error' => 'La OS ya fue facturada, no se puede editar.']);
        }

        if (in_array((string) $order->status, ['cancelled', 'delivered'], true)) {
            return back()->withErrors(['error' => 'No se puede editar una OS anulada o entregada.']);
        }

        [$branchId, $companyId] = $this->branchScope();

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'client_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tow_in' => ['nullable', 'boolean'],
            'intake_date' => ['nullable', 'date'],
            'diagnosis_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.*' => ['nullable', 'boolean'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['nullable', 'boolean'],
            'damages' => ['nullable', 'array'],
            'damages.*.side' => ['nullable', 'in:RIGHT,LEFT,FRONT,BACK'],
            'damages.*.description' => ['nullable', 'string'],
            'damages.*.severity' => ['nullable', 'in:LOW,MED,HIGH'],
            'damages.*.photos' => ['nullable', 'array'],
            'damages.*.photos.*' => ['nullable', 'image', 'max:6144'],
            'client_signature_data' => ['nullable', 'string'],
            'service_lines' => ['nullable', 'array'],
            'service_lines.*.detail_id' => ['nullable', 'integer'],
            'service_lines.*.service_id' => ['nullable', 'string', 'max:32'],
            'service_lines.*.description' => ['nullable', 'string', 'max:255'],
            'service_lines.*.qty' => ['nullable', 'numeric'],
            'service_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'service_lines.*.price_cc_override' => ['nullable', 'string', 'max:32'],
            'service_lines.*.validity_months' => ['nullable', 'in:6,12'],
            'service_lines.*.is_terciarizado' => ['nullable', 'boolean'],
            'service_type' => ['nullable', 'string', 'in:preventivo,correctivo'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.detail_id' => ['nullable', 'integer'],
            'product_lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['nullable', 'numeric', 'gte:0'],
            'product_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'save_mode' => ['nullable', 'string', 'in:partial,full'],
        ]);

        $isPartialSave = $request->input('save_mode', 'full') === 'partial';

        $vehicle = Vehicle::query()
            ->where('id', (int) $validated['vehicle_id'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $clientPersonId = isset($validated['client_person_id']) && (int) $validated['client_person_id'] > 0
            ? (int) $validated['client_person_id']
            : (int) $vehicle->client_person_id;

        if ($clientPersonId <= 0) {
            return back()->withErrors(['error' => 'El vehiculo seleccionado no tiene cliente asociado.']);
        }

        if ((int) $vehicle->client_person_id !== $clientPersonId) {
            return back()->withErrors(['error' => 'El vehiculo no pertenece al cliente seleccionado.']);
        }

        $parsedServiceLines = [];
        if (!$isPartialSave) {
            try {
                $parsedServiceLines = $this->parseMaintenanceBoardServiceLines((array) ($validated['service_lines'] ?? []));
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
            try {
                $this->assertCatalogServiceLinesAllowed($parsedServiceLines, $branchId, $companyId);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
        }
        $editableCatalogServicePrices = $this->isCatalogServicePriceEditingEnabled($branchId);
        $intakeDate = $this->normalizeMaintenanceBoardIntakeDate($validated['intake_date'] ?? null);

        $user = auth()->user();

        try {
            DB::transaction(function () use ($order, $request, $validated, $branchId, $companyId, $vehicle, $clientPersonId, $parsedServiceLines, $user, $editableCatalogServicePrices, $intakeDate, $isPartialSave) {
                $lockedOrder = WorkshopMovement::query()
                    ->where('id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->flowService->updateOrder($lockedOrder, [
                    'vehicle_id' => (int) $validated['vehicle_id'],
                    'client_person_id' => $clientPersonId,
                    'intake_date' => $intakeDate,
                    'mileage_in' => $validated['mileage_in'] ?? null,
                    'fuel_level' => $validated['fuel_level'] ?? null,
                    'tow_in' => (bool) ($validated['tow_in'] ?? false),
                    'diagnosis_text' => $validated['diagnosis_text'] ?? null,
                    'observations' => $validated['observations'] ?? null,
                    'service_type' => $validated['service_type'] ?? $order->service_type,
                    'driver_name' => $validated['driver_name'] ?? null,
                    'driver_phone' => $validated['driver_phone'] ?? null,
                ]);

                $damagesWithPhotos = $this->uploadDamagePhotos($request, (array) ($validated['damages'] ?? []), $branchId);
                $damagesWithPhotos = $this->mergeExistingWorkshopDamagePhotos($lockedOrder, $damagesWithPhotos);
                $signaturePath = $this->storeSignatureFromDataUri((string) ($validated['client_signature_data'] ?? ''), $branchId);
                $meta = [];
                if ($signaturePath) {
                    $meta['intake_client_signature_path'] = $signaturePath;
                }
                $this->flowService->syncIntakeAndDamages(
                    $lockedOrder,
                    (array) ($validated['inventory'] ?? []),
                    $damagesWithPhotos,
                    $meta,
                    true
                );
                $this->flowService->syncAdditionalAccessories($lockedOrder, (array) ($validated['accessories'] ?? []), true);

                $lockedOrder->refresh();

                if (!$isPartialSave) {
                $catalogIds = collect($parsedServiceLines)
                    ->where('kind', 'catalog')
                    ->pluck('service_id')
                    ->unique()
                    ->map(fn($id) => (int) $id)
                    ->filter(fn($id) => $id > 0)
                    ->values()
                    ->all();

                $serviceCatalog = $catalogIds === []
                    ? collect()
                    : WorkshopService::query()
                        ->with(['priceTiers', 'frequencies'])
                        ->whereIn('id', $catalogIds)
                        ->get()
                        ->keyBy('id');

                $mileageForFrequency = (int) ($validated['mileage_in'] ?? $vehicle->current_mileage ?? 0);

                $detailsById = $lockedOrder->details()
                    ->where('line_type', 'SERVICE')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                $submittedDetailIds = collect($parsedServiceLines)
                    ->pluck('detail_id')
                    ->filter(fn($id) => (int) $id > 0)
                    ->map(fn($id) => (int) $id)
                    ->all();
                foreach ($detailsById as $id => $detail) {
                    if (!in_array((int) $id, $submittedDetailIds, true)) {
                        $this->flowService->removeDetail($detail);
                    }
                }

                $lockedOrder->refresh();
                $detailsById = $lockedOrder->details()
                    ->where('line_type', 'SERVICE')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                foreach ($parsedServiceLines as $line) {
                    $qty = $line['qty'];
                    $detailId = (int) ($line['detail_id'] ?? 0);

                    if ($line['kind'] === 'glosa') {
                        if ($detailId > 0 && !$detailsById->has($detailId)) {
                            throw new \RuntimeException('Linea de servicio no valida para esta OS.');
                        }

                        $serviceId = $this->createServiceFromGlosa($companyId, $branchId, $line['description'], (float) $line['unit_price'], (bool) ($line['is_terciarizado'] ?? false));

                        if ($detailId > 0 && $detailsById->has($detailId)) {
                            $this->flowService->updateDetail(
                                $detailsById->get($detailId),
                                [
                                    'qty' => $qty,
                                    'unit_price' => $line['unit_price'],
                                    'description' => $line['description'],
                                    'service_id' => $serviceId,
                                    'validity_months' => $line['validity_months'] ?? null,
                                    'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? false),
                                ],
                                $branchId,
                                (int) ($user?->id ?? 0),
                                (string) ($user?->name ?? 'Sistema')
                            );
                        } else {
                            $this->flowService->addDetail($lockedOrder, [
                                'line_type' => 'SERVICE',
                                'service_id' => $serviceId,
                                'description' => $line['description'],
                                'qty' => $qty,
                                'unit_price' => $line['unit_price'],
                                'validity_months' => $line['validity_months'] ?? null,
                                'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? false),
                            ]);
                        }
                        continue;
                    }

                    $serviceId = (int) $line['service_id'];
                    $service = $serviceCatalog->get($serviceId);
                    if (!$service || $qty <= 0) {
                        continue;
                    }

                    $resolvedPrice = $this->resolveMaintenanceBoardServiceUnitPrice(
                        $service,
                        $vehicle,
                        $mileageForFrequency,
                        (string) ($line['price_cc_override'] ?? 'auto')
                    );

                    if ($detailId > 0 && !$detailsById->has($detailId)) {
                        throw new \RuntimeException('Linea de servicio no valida para esta OS.');
                    }

                    if ($detailId > 0 && $detailsById->has($detailId)) {
                        $this->flowService->updateDetail(
                            $detailsById->get($detailId),
                            [
                                'qty' => $qty,
                                'unit_price' => $editableCatalogServicePrices
                                    ? round((float) ($line['unit_price'] ?? $resolvedPrice), 6)
                                    : round((float) $resolvedPrice, 6),
                                'validity_months' => $line['validity_months'] ?? null,
                                'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? $service->is_terciarizado ?? false),
                            ],
                            $branchId,
                            (int) ($user?->id ?? 0),
                            (string) ($user?->name ?? 'Sistema')
                        );
                    } else {
                        $unitPrice = $editableCatalogServicePrices
                            ? round((float) ($line['unit_price'] ?? $resolvedPrice), 6)
                            : round((float) $resolvedPrice, 6);
                        if ($unitPrice < 0) {
                            $unitPrice = 0;
                        }
                        $this->flowService->addDetail($lockedOrder, [
                            'line_type' => 'SERVICE',
                            'service_id' => $serviceId,
                            'description' => (string) $service->name,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'validity_months' => $line['validity_months'] ?? null,
                            'is_terciarizado' => (bool) ($line['is_terciarizado'] ?? $service->is_terciarizado ?? false),
                        ]);
                    }
                }

                $lockedOrder->refresh();

                $productLines = collect($validated['product_lines'] ?? [])
                    ->filter(fn($line) => !empty($line['product_id']))
                    ->values();

                $partDetails = $lockedOrder->details()
                    ->where('line_type', 'PART')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                $submittedPartIds = $productLines->pluck('detail_id')->filter()->map(fn($id) => (int) $id)->all();
                foreach ($partDetails as $id => $detail) {
                    if (!in_array((int) $id, $submittedPartIds, true)) {
                        $this->flowService->removeDetail($detail);
                    }
                }

                $lockedOrder->refresh();
                $partDetails = $lockedOrder->details()
                    ->where('line_type', 'PART')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                foreach ($productLines as $line) {
                    $productId = (int) $line['product_id'];
                    $row = $this->productBranchRowForMaintenance($branchId, $productId);
                    if (!$row) {
                        throw new \RuntimeException('Hay productos no disponibles en la sucursal.');
                    }

                    $qty = round((float) ($line['qty'] ?? 0), 6);
                    if ($qty <= 0) {
                        continue;
                    }

                    $unitPrice = round((float) ($line['unit_price'] ?? (float) $row->price), 6);
                    if ($unitPrice < 0) {
                        $unitPrice = 0;
                    }

                    $description = $this->formatWorkshopProductPartDescription($row);
                    $detailId = isset($line['detail_id']) ? (int) $line['detail_id'] : 0;

                    if ($detailId > 0 && !$partDetails->has($detailId)) {
                        throw new \RuntimeException('Linea de producto no valida para esta OS.');
                    }

                    if ($detailId > 0 && $partDetails->has($detailId)) {
                        $this->flowService->updateDetail(
                            $partDetails->get($detailId),
                            [
                                'qty' => $qty,
                                'unit_price' => $unitPrice,
                                'description' => $description,
                                'tax_rate_id' => $row->tax_rate_id ? (int) $row->tax_rate_id : null,
                            ],
                            $branchId,
                            (int) ($user?->id ?? 0),
                            (string) ($user?->name ?? 'Sistema')
                        );
                    } else {
                        $this->flowService->addDetail($lockedOrder, [
                            'line_type' => 'PART',
                            'product_id' => $productId,
                            'description' => $description,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'tax_rate_id' => $row->tax_rate_id ? (int) $row->tax_rate_id : null,
                        ]);
                    }
                }
                }

                if (
                    !$isPartialSave
                    && (string) $lockedOrder->status === 'draft'
                    && (string) ($validated['service_type'] ?? $lockedOrder->service_type ?? 'preventivo') === 'preventivo'
                ) {
                    $this->recordWorkshopBoardStatusChange(
                        $lockedOrder,
                        'awaiting_approval',
                        'OS completada y enviada a espera de aprobación',
                        (int) ($user?->id ?? 0)
                    );
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->filled('advance_corrective_phase')) {
            $order->corrective_phase = 'cotizacion_entrega';
            $order->corrective_quote_delivered_at = now();
            $order->corrective_observations = ($order->corrective_observations ? $order->corrective_observations . "\n" : "") . "[" . now()->format('Y-m-d H:i') . " - Entrega de Cotización]: Generada desde edición de detalles.";
            $order->save();

            return redirect()->route('workshop.maintenance-board.corrective')
                ->with('status', 'Cotización generada y fase avanzada exitosamente.');
        }

        return $this->redirectToBoardWithStatus(
            (string) $order->fresh()->status,
            $isPartialSave
                ? 'Recepción parcial actualizada. Puede completar servicios y repuestos después.'
                : 'Orden de servicio actualizada.'
        );
    }

    public function quotation(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $validated = $request->validate([
            'quote_lines' => ['required', 'array', 'min:1'],
            'quote_lines.*.detail_id' => ['required', 'integer', 'exists:workshop_movement_details,id'],
            'quote_lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'quote_lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'quote_note' => ['nullable', 'string', 'max:500'],
        ]);

        $branchId = (int) session('branch_id');
        $user = auth()->user();

        try {
            DB::transaction(function () use ($order, $validated, $branchId, $user) {
                $lockedOrder = WorkshopMovement::query()
                    ->where('id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $editableStatuses = ['awaiting_approval', 'approved'];
                if (!in_array((string) $lockedOrder->status, $editableStatuses, true)) {
                    throw new \RuntimeException('La cotizacion solo se puede editar en espera de aprobacion o aprobado.');
                }

                $detailsById = $lockedOrder->details()
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                $submittedDetailIds = collect((array) $validated['quote_lines'])->pluck('detail_id')->map(fn($id) => (int) $id)->all();
                foreach ($detailsById as $id => $detail) {
                    if (!in_array((int) $id, $submittedDetailIds, true)) {
                        $this->flowService->removeDetail($detail);
                    }
                }

                $lockedOrder->refresh();
                $detailsById = $lockedOrder->details()
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                foreach ((array) $validated['quote_lines'] as $line) {
                    $detailId = (int) $line['detail_id'];
                    $detail = $detailsById->get($detailId);
                    if (!$detail) {
                        continue;
                    }

                    $this->flowService->updateDetail(
                        $detail,
                        [
                            'qty' => (float) $line['qty'],
                            'unit_price' => (float) $line['unit_price'],
                        ],
                        $branchId,
                        (int) ($user?->id ?? 0),
                        (string) ($user?->name ?? 'Sistema')
                    );
                }

                if ((string) $lockedOrder->status === 'awaiting_approval' && !empty($validated['quote_note'])) {
                    $this->flowService->updateOrder($lockedOrder, [
                        'observations' => trim(((string) $lockedOrder->observations . "\n" . '[Cotización] ' . (string) $validated['quote_note'])),
                    ]);
                }

                // Al aprobar cotización desde tablero, la OS pasa a estado aprobado.
                if ((string) $lockedOrder->status === 'awaiting_approval') {
                    $this->flowService->updateOrder($lockedOrder, [
                        'status' => 'approved',
                        'comment' => 'Cotización aprobada desde tablero',
                    ]);
                    $showAnticipo = true;
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($showAnticipo ?? false) {
            return $this->redirectToBoardWithStatus(
                'approved',
                'Cotización aprobada correctamente.',
                ['show_anticipo_modal_for_order' => $order->id]
            );
        }

        return $this->redirectToBoardWithStatus(
            (string) $order->fresh()->status,
            'Cotización actualizada correctamente.'
        );
    }

    public function storeVehicleQuick(Request $request): JsonResponse
    {
        [$branchId, $companyId] = $this->branchScope();

        $normalizeVehicleIdentifier = static function ($value): ?string {
            $normalized = trim((string) $value);

            if ($normalized === '' || $normalized === '-' || $normalized === '--') {
                return null;
            }

            return $normalized;
        };

        $request->merge([
            'plate' => $normalizeVehicleIdentifier($request->input('plate')),
            'vin' => $normalizeVehicleIdentifier($request->input('vin')),
            'engine_number' => $normalizeVehicleIdentifier($request->input('engine_number')),
            'chassis_number' => $normalizeVehicleIdentifier($request->input('chassis_number')),
            'serial_number' => $normalizeVehicleIdentifier($request->input('serial_number')),
        ]);

        $validated = $request->validate([
            'client_person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id')->where(fn($query) => $query->where('branch_id', $branchId)),
            ],
            'vehicle_type_id' => [
                'required',
                'integer',
                Rule::exists('vehicle_types', 'id')->where(function ($query) use ($companyId, $branchId) {
                    $query->whereNull('deleted_at')
                        ->where(function ($inner) use ($companyId, $branchId) {
                            $inner->whereNull('company_id')
                                ->orWhere(function ($scope) use ($companyId, $branchId) {
                                    $scope->where('company_id', $companyId)
                                        ->where(function ($branchScope) use ($branchId) {
                                            $branchScope->whereNull('branch_id')
                                                ->orWhere('branch_id', $branchId);
                                        });
                                });
                        });
                }),
            ],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4'],
            'color' => ['nullable', 'string', 'max:100'],
            'plate' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('vehicles', 'plate')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'vin' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('vehicles', 'vin')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'engine_number' => ['nullable', 'string', 'max:255'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'current_mileage' => ['nullable', 'integer', 'min:0'],
            'engine_displacement_cc' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'soat_vencimiento' => ['nullable', 'date'],
            'revision_tecnica_vencimiento' => ['nullable', 'date'],
        ]);

        $hasClientRole = Person::query()
            ->where('id', (int) $validated['client_person_id'])
            ->where('branch_id', $branchId)
            ->whereHas('roles', function ($query) use ($branchId) {
                $query->where('roles.id', 3)
                    ->where('role_person.branch_id', $branchId);
            })
            ->exists();

        if (!$hasClientRole) {
            return response()->json([
                'message' => 'La persona seleccionada no tiene rol de cliente.',
            ], 422);
        }

        if (
            trim((string) ($validated['plate'] ?? '')) === ''
            && trim((string) ($validated['vin'] ?? '')) === ''
            && trim((string) ($validated['engine_number'] ?? '')) === ''
        ) {
            return response()->json([
                'message' => 'Debe registrar placa o VIN o numero de motor.',
            ], 422);
        }

        $vehicleType = VehicleType::query()->findOrFail((int) $validated['vehicle_type_id']);
        $validated['type'] = $vehicleType->name;

        $vehicle = Vehicle::query()->create(array_merge($validated, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'status' => 'active',
            'current_mileage' => (int) ($validated['current_mileage'] ?? 0),
            'engine_displacement_cc' => isset($validated['engine_displacement_cc']) && $validated['engine_displacement_cc'] !== ''
                ? (int) $validated['engine_displacement_cc']
                : null,
        ]));

        return response()->json([
            'id' => $vehicle->id,
            'client_person_id' => (int) $vehicle->client_person_id,
            'vehicle_type_id' => (int) $vehicle->vehicle_type_id,
            'label' => trim($vehicle->brand . ' ' . $vehicle->model . ' ' . ($vehicle->plate ? ('- ' . $vehicle->plate) : '')),
            'km' => (int) ($vehicle->current_mileage ?? 0),
            'engine_displacement_cc' => $vehicle->engine_displacement_cc ? (int) $vehicle->engine_displacement_cc : null,
            'soat_vencimiento' => $vehicle->soat_vencimiento ? $vehicle->soat_vencimiento->format('Y-m-d') : null,
            'revision_tecnica_vencimiento' => $vehicle->revision_tecnica_vencimiento ? $vehicle->revision_tecnica_vencimiento->format('Y-m-d') : null,
        ]);
    }

    public function lookupVehicleByPlate(Request $request, VehiclePlateLookupService $lookup): JsonResponse
    {
        $result = $lookup->lookupByPlate((string) $request->query('plate', ''));

        if (!$result['status']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function storeClientQuick(Request $request): JsonResponse
    {
        [$branchId, $companyId] = $this->branchScope();
        $branch = Branch::query()->findOrFail($branchId);

        $validated = $request->validate([
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required_unless:person_type,RUC', 'nullable', 'string', 'max:255'],
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
            $existingPerson->roles()->syncWithoutDetaching([
                3 => ['branch_id' => $branchId], // Cliente
            ]);

            return response()->json([
                'id' => (int) $existingPerson->id,
                'person_type' => (string) $existingPerson->person_type,
                'document_number' => (string) $existingPerson->document_number,
                'first_name' => (string) $existingPerson->first_name,
                'last_name' => (string) ($existingPerson->last_name ?? ''),
                'name' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name)),
                'label' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name) . ' - ' . ((string) $existingPerson->person_type) . ' ' . ((string) $existingPerson->document_number)),
            ]);
        }

        $validated['phone'] = (string) ($validated['phone'] ?? '');
        $validated['email'] = (string) ($validated['email'] ?? '');
        $validated['last_name'] = strtoupper((string) $validated['person_type']) === 'RUC'
            ? ''
            : (string) ($validated['last_name'] ?? '');
        $validated['address'] = trim((string) ($validated['address'] ?? '')) ?: '-';
        $validated['location_id'] = (int) ($validated['location_id'] ?? 0) > 0
            ? (int) $validated['location_id']
            : $branchDistrictId;

        $person = DB::transaction(function () use ($validated, $branchId) {
            $person = Person::query()->create(array_merge(
                $validated,
                ['branch_id' => $branchId]
            ));

            $person->roles()->syncWithoutDetaching([
                3 => ['branch_id' => $branchId], // Cliente
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
            'label' => trim(((string) $person->first_name) . ' ' . ((string) $person->last_name) . ' - ' . ((string) $person->person_type) . ' ' . ((string) $person->document_number)),
        ]);
    }

    public function start(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'service_type' => ['required', 'string', 'in:internal,external'],
            'technician_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'glosa' => ['required_if:service_type,external', 'nullable', 'string', 'max:1000'],
        ]);

        $newBoardStatus = 'in_progress';

        try {
            DB::transaction(function () use ($order, $validated, &$newBoardStatus) {
                if ($validated['service_type'] === 'internal') {
                    if (!in_array((string) $order->status, ['approved', 'awaiting_technician_start'], true)) {
                        throw new \RuntimeException('Solo se puede iniciar un servicio aprobado o esperando inicio del tecnico.');
                    }

                    $technicianId = (int) ($validated['technician_person_id'] ?? 0);
                    if ($technicianId > 0) {
                        WorkshopMovementTechnician::query()->updateOrCreate(
                            [
                                'workshop_movement_id' => $order->id,
                                'technician_person_id' => $technicianId,
                            ],
                            [
                                'commission_percentage' => 0,
                                'commission_amount' => 0,
                            ]
                        );
                    } elseif (!$order->technicians()->exists()) {
                        throw new \RuntimeException('Debe asignar un tecnico antes de iniciar el servicio.');
                    }

                    $this->flowService->updateOrder($order, [
                        'status' => 'in_progress',
                        'comment' => 'Inicio de mantenimiento (Interno) desde tablero',
                    ]);
                } else {
                    $newBoardStatus = 'in_progress_external';
                    // External service
                    $glosa = trim($validated['glosa'] ?? '');
                    $newObservations = trim(($order->observations ?? '') . "\n" . "[SERVICIO EXTERNO - " . now()->format('Y-m-d H:i') . "] " . $glosa);

                    $this->flowService->updateOrder($order, [
                        'status' => 'in_progress_external',
                        'last_status' => $order->status,
                        'observations' => $newObservations,
                        'comment' => $glosa ?: 'Inicio de servicio externo desde tablero',

                    ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return $this->redirectToBoardWithStatus($newBoardStatus, 'Servicio iniciado.');
    }

    public function finishExternal(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $request->validate([
            'finished_photo' => ['required', 'image', 'max:10240'],
        ]);

        $targetStatus = 'approved';

        try {
            if ($order->status !== 'in_progress_external') {
                throw new \RuntimeException('Solo se pueden finalizar servicios externos que estén en progreso.');
            }

            $branchId = (int) session('branch_id');
            $photoPath = $order->finished_photo_path;
            if ($request->hasFile('finished_photo')) {
                $photoPath = $request->file('finished_photo')->store("workshop/finished/{$branchId}", 'public');
            }

            $targetStatus = (string) ($order->last_status ?? 'approved');

            $this->flowService->updateOrder($order, [
                'status' => $targetStatus,
                'last_status' => null,
                'finished_photo_path' => $photoPath,
                'comment' => "Finalización de servicio externo. Vuelve a estado {$targetStatus} para seguir el flujo.",
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return $this->redirectToBoardWithStatus($targetStatus, 'Servicio externo finalizado.');
    }

    public function finish(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $request->validate([
            'finished_photo' => ['nullable', 'image', 'max:10240'],
        ]);


        try {
            $branchId = (int) session('branch_id');
            $photoPath = $order->finished_photo_path;
            if ($request->hasFile('finished_photo')) {
                $photoPath = $request->file('finished_photo')->store("workshop/finished/{$branchId}", 'public');
            }

            $this->flowService->updateOrder($order, [
                'status' => 'finished',
                'finished_photo_path' => $photoPath,
                'comment' => 'Finalización desde tablero',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return $this->redirectToBoardWithStatus('finished', 'Servicio finalizado. Puede continuar con cobro y entrega.');
    }

    public function pause(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'pause_comment' => ['required', 'string', 'max:500'],
        ]);

        try {
            if ((string) $order->status !== 'in_progress') {
                throw new \RuntimeException('Solo se pueden pausar servicios en reparación.');
            }

            $this->flowService->updateOrder($order, [
                'status' => 'paused',
                'paused_at' => now(),
                'comment' => '[PAUSA] ' . $validated['pause_comment'],
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return $this->redirectToBoardWithStatus('paused', 'Servicio pausado correctamente.');
    }

    public function resume(WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        try {
            if ((string) $order->status !== 'paused') {
                throw new \RuntimeException('Solo se pueden reanudar servicios pausados.');
            }

            $pausedAt = $order->paused_at;
            $minutes = 0;
            if ($pausedAt) {
                $minutes = (int) now()->diffInMinutes($pausedAt);
            }

            $this->flowService->updateOrder($order, [
                'status' => 'in_progress',
                'paused_at' => null,
                'total_paused_minutes' => (int) ($order->total_paused_minutes ?? 0) + $minutes,
                'comment' => 'Reanudación de servicio desde tablero',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return $this->redirectToBoardWithStatus('in_progress', 'Servicio reanudado.');
    }

    public function checkoutPage(Request $request, WorkshopMovement $order): \Illuminate\View\View|RedirectResponse
    {
        $this->assertOrderScope($order);

        if (!in_array((string) $order->status, ['approved', 'in_progress', 'paused', 'finished'], true)) {
            return redirect()
                ->route('workshop.maintenance-board.index')
                ->withErrors(['error' => 'La venta y cobro solo esta disponible para OS aprobadas, en reparacion, pausadas o terminadas.']);
        }

        $isAnticipo = request()->has('anticipo') && request()->query('anticipo') == '1';

        if ($isAnticipo) {
            $pendingDebt = max(0, (float) $order->total - (float) $order->paid_total);
            if ($pendingDebt <= 0.00001) {
                return redirect()
                    ->route('workshop.maintenance-board.index')
                    ->withErrors(['error' => 'La OS ya no tiene saldo pendiente para registrar un pago anticipado.']);
            }
        }

        [$branchId, $companyId] = $this->branchScope();
        $formData = $this->maintenanceFormData($branchId, $companyId);

        $order->load([
            'movement',
            'branch',
            'vehicle',
            'client',
            'details' => fn($query) => $query
                ->whereNull('sales_movement_id')
                ->whereNull('deleted_at')
                ->orderBy('id'),
        ]);

        $pendingLines = $order->details->map(function ($detail) {
            return [
                'detail_id' => (int) $detail->id,
                'line_type' => (string) $detail->line_type,
                'description' => (string) ($detail->description ?? 'Detalle'),
                'qty' => (float) $detail->qty,
                'unit_price' => (float) $detail->unit_price,
                'subtotal' => (float) $detail->total,
            ];
        })->values();

        $apisunatService = app(\App\Services\ApisunatService::class);
        $allOrderAdvances = (!$isAnticipo && (float) $order->paid_total > 0)
            ? $this->resolveWorkshopAdvanceDocuments($order, false)
            : [];
        $previousAdvances = collect($allOrderAdvances)
            ->filter(function (array $advance) use ($apisunatService) {
                $movementId = (int) ($advance['movement_id'] ?? 0);
                if ($movementId <= 0) {
                    return false;
                }
                $movement = \App\Models\Movement::query()->find($movementId);

                return $movement && $apisunatService->isAdvanceSunatReady($movement);
            })
            ->values()
            ->all();
        $blockedAdvances = collect($allOrderAdvances)
            ->filter(fn (array $advance) => ! (bool) ($advance['sunat_ready'] ?? false))
            ->values()
            ->all();

        if ($isAnticipo) {
            $pendingLines->prepend([
                'detail_id' => 'anticipo',
                'line_type' => 'ANTICIPO',
                'description' => 'PAGO ANTICIPADO DE OS ' . ($order->movement?->number ?? ('#' . $order->id)),
                'qty' => 1,
                'unit_price' => 0,
                'subtotal' => 0,
            ]);
        } else {
            foreach ($previousAdvances as $advance) {
                $pendingLines->push([
                    'detail_id' => 'anticipo-' . (int) ($advance['movement_id'] ?? 0),
                    'line_type' => 'ANTICIPO',
                    'description' => trim((string) ($advance['document_name'] ?? 'Comprobante')) . ' ' . trim((string) ($advance['full_number'] ?? '')),
                    'qty' => 1,
                    'unit_price' => -1 * (float) ($advance['amount'] ?? 0),
                    'subtotal' => -1 * (float) ($advance['amount'] ?? 0),
                    'advance' => $advance,
                ]);
            }
        }

        $paymentMethodOptions = collect($formData['paymentMethods'] ?? collect())
            ->map(function ($method) {
                return [
                    'id' => (int) $method->id,
                    'description' => (string) ($method->description ?? ''),
                    'kind' => $this->inferPaymentMethodKind((string) ($method->description ?? '')),
                ];
            })
            ->values();

        $cardOptions = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon'])
            ->map(fn($card) => [
                'id' => (int) $card->id,
                'description' => (string) ($card->description ?? ''),
                'type' => (string) ($card->type ?? ''),
                'icon' => (string) ($card->icon ?? ''),
            ])
            ->values();

        $digitalWalletOptions = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description'])
            ->map(fn($wallet) => [
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
            ->map(fn($rows) => collect($rows)->map(fn($row) => [
                'id' => (int) $row->id,
                'description' => (string) ($row->description ?? ''),
            ])->values())
            ->all();

        $defaultDocumentTypeId = $this->getBranchDefaultSaleDocumentTypeId(
            $branchId,
            collect($formData['documentTypes'] ?? [])
        );
        $cashRegisters = collect($formData['cashRegisters'] ?? []);
        $standardCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja ventas');
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;
        $defaultCashRegisterId = $this->isInvoiceDocumentTypeId($defaultDocumentTypeId, collect($formData['documentTypes'] ?? []))
            ? $invoiceCashRegisterId
            : $standardCashRegisterId;

        return view('workshop.maintenance-board.checkout', array_merge($formData, [
            'order' => $order,
            'returnUrl' => $request->query('return_url'),
            'pendingLines' => $pendingLines,
            'previousAdvances' => $previousAdvances,
            'isAnticipo' => $isAnticipo ?? false,
            'totalOs' => (float) $order->total,
            'paidOs' => (float) $order->paid_total,
            'pendingOs' => $isAnticipo ? 0.00 : max(0, (float) $order->total - (float) $order->paid_total),
            'paymentMethodOptions' => $paymentMethodOptions,
            'cardOptions' => $cardOptions,
            'digitalWalletOptions' => $digitalWalletOptions,
            'paymentGatewayOptionsByMethod' => $paymentGatewayOptionsByMethod,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'defaultCashRegisterId' => $defaultCashRegisterId,
            'standardCashRegisterId' => $standardCashRegisterId,
            'invoiceCashRegisterId' => $invoiceCashRegisterId,
            'deliversOnConfirm' => (string) $order->status === 'finished',
            'blockedAdvances' => $blockedAdvances ?? [],
            'isSunatActive' => $apisunatService->isConfiguredForBranch($order->branch ?? null),
            'defaultCreditDays' => max(0, (int) ($order->client?->credit_days ?? 0)),
            'vehiclePlate' => trim((string) ($order->vehicle?->plate ?? '')),
            'serviceOrderNumber' => trim((string) ($order->movement?->number ?? '')) ?: ('#' . $order->id),
            'sunatDetraccionTypes' => $this->sunatDetraccionCatalogOptions(),
        ]));
    }

    public function previewCheckoutDocument(Request $request, WorkshopMovement $order): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $this->assertOrderScope($order);

        if (!in_array((string) $order->status, ['approved', 'in_progress', 'paused', 'finished'], true)) {
            abort(422, 'La vista previa solo está disponible para OS aprobadas, en reparación, pausadas o terminadas.');
        }

        [$branchId] = $this->branchScope();
        $format = $request->input('format') === 'ticket' ? 'ticket' : 'a4';

        $printData = $this->checkoutPreviewService->build($request, $order, $branchId);
        $printData['format'] = $format;

        $viewName = 'workshop.maintenance-board.print.sunat-comprobante';
        $html = view($viewName, $printData)->render();

        if ($request->boolean('html')) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $pdfBinary = $this->renderCheckoutPreviewPdf($html, $format);
        if ($pdfBinary === null) {
            return view($viewName, $printData);
        }

        $filename = 'vista-previa-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($printData['documentCode'] ?? 'comprobante')) . '.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function checkout(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $validated = $request->validate([
            'generate_sale' => ['nullable', 'boolean'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'billing_status' => ['nullable', 'string', Rule::in(['PENDING', 'INVOICED', 'NOT_APPLICABLE'])],
            'invoice_series' => ['nullable', 'string', 'max:20'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'sale_comment' => ['nullable', 'string'],
            'payment_type' => ['required', 'string', Rule::in(['CONTADO', 'DEUDA'])],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*.payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_methods.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_methods.*.reference' => ['nullable', 'string', 'max:100'],
            'payment_methods.*.payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'payment_methods.*.card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'payment_methods.*.bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'payment_methods.*.digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'payment_comment' => ['nullable', 'string'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['nullable', 'numeric', 'gte:0'],
            'product_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'debt_due_date' => ['nullable', 'date_format:Y-m-d'],
            'sunat_apply_detraccion' => ['nullable', 'boolean'],
            'sunat_detraccion_type' => ['nullable', 'string', 'max:3'],
            'sunat_detraccion_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sunat_detraccion_bank_account' => ['nullable', 'string', 'max:50'],
            'sunat_apply_retencion' => ['nullable', 'boolean'],
            'sunat_retencion_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'purchase_order_number' => ['nullable', 'string', 'max:50'],
        ]);

        $paymentType = strtoupper((string) ($validated['payment_type'] ?? 'CONTADO'));
        $isDebtCheckout = $paymentType === 'DEUDA';
        $validated['payment_methods'] = collect($validated['payment_methods'] ?? [])
            ->filter(fn($row) => !empty($row['payment_method_id']) && (float) ($row['amount'] ?? 0) > 0)
            ->values()
            ->all();

        $orderForPaymentRule = WorkshopMovement::query()->findOrFail($order->id);
        $amountPendingToCollect = max(0, (float) $orderForPaymentRule->total - (float) $orderForPaymentRule->paid_total);

        if (!$isDebtCheckout && empty($validated['payment_methods']) && $amountPendingToCollect > 0.000001) {
            throw ValidationException::withMessages([
                'payment_methods' => 'Debes registrar al menos un metodo de pago cuando el cobro es al contado.',
            ]);
        }

        $checkoutDocumentType = !empty($validated['document_type_id'])
            ? DocumentType::query()->find((int) $validated['document_type_id'])
            : null;
        $isCheckoutInvoiceDocument = $checkoutDocumentType
            && str_contains(mb_strtolower((string) ($checkoutDocumentType->name ?? '')), 'factura');
        $apisunatForCheckout = app(\App\Services\ApisunatService::class);
        $isCheckoutSunatActive = $apisunatForCheckout->isConfiguredForBranch(
            WorkshopMovement::query()->with('branch')->find($order->id)?->branch
        );

        if ($isDebtCheckout && $isCheckoutSunatActive && $isCheckoutInvoiceDocument && $request->boolean('sunat_apply_detraccion')) {
            if (trim((string) ($validated['sunat_detraccion_bank_account'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'sunat_detraccion_bank_account' => 'Indique la cuenta del Banco de la Nación para la detracción.',
                ]);
            }
        }

        $methodIds = collect($validated['payment_methods'] ?? [])
            ->pluck('payment_method_id')
            ->filter()
            ->map(fn($value) => (int) $value)
            ->unique()
            ->values();

        $methodsIndex = PaymentMethod::query()
            ->whereIn('id', $methodIds->all())
            ->get(['id', 'description'])
            ->keyBy('id');

        $gatewayMethodMap = collect(
            DB::table('payment_gateway_payment_method')
                ->whereNull('deleted_at')
                ->whereIn('payment_method_id', $methodIds->all())
                ->get(['payment_method_id', 'payment_gateway_id'])
        )
            ->groupBy('payment_method_id')
            ->map(fn($rows) => collect($rows)->pluck('payment_gateway_id')->map(fn($value) => (int) $value)->all());

        if (!$isDebtCheckout) {
            foreach ($validated['payment_methods'] as $index => $payment) {
                $method = $methodsIndex->get((int) ($payment['payment_method_id'] ?? 0));
                $kind = $this->inferPaymentMethodKind((string) ($method?->description ?? ''));

                if ($kind === 'card') {
                    if (empty($payment['card_id'])) {
                        throw ValidationException::withMessages([
                            "payment_methods.{$index}.card_id" => 'Debe seleccionar la tarjeta.',
                        ]);
                    }

                    if (!empty($payment['payment_gateway_id'])) {
                        $allowedGateways = $gatewayMethodMap->get((int) $payment['payment_method_id'], []);
                        if (!in_array((int) $payment['payment_gateway_id'], $allowedGateways, true)) {
                            throw ValidationException::withMessages([
                                "payment_methods.{$index}.payment_gateway_id" => 'La pasarela no corresponde al método de pago seleccionado.',
                            ]);
                        }
                    }

                    $validated['payment_methods'][$index]['digital_wallet_id'] = null;
                    $validated['payment_methods'][$index]['bank_id'] = null;
                } elseif ($kind === 'wallet') {
                    if (empty($payment['digital_wallet_id'])) {
                        throw ValidationException::withMessages([
                            "payment_methods.{$index}.digital_wallet_id" => 'Debe seleccionar la billetera digital.',
                        ]);
                    }

                    $validated['payment_methods'][$index]['card_id'] = null;
                    $validated['payment_methods'][$index]['payment_gateway_id'] = null;
                    $validated['payment_methods'][$index]['bank_id'] = null;
                } else {
                    $validated['payment_methods'][$index]['card_id'] = null;
                    $validated['payment_methods'][$index]['payment_gateway_id'] = null;
                    $validated['payment_methods'][$index]['digital_wallet_id'] = null;
                }

                $validated['payment_methods'][$index]['reference'] = isset($payment['reference'])
                    ? trim((string) $payment['reference'])
                    : null;
            }
        }

        $user = auth()->user();
        $branchId = (int) session('branch_id');
        $movementForApisunat = null;
        $isAnticipo = $request->has('anticipo') && $request->query('anticipo') == '1';
        $checkoutPreviousAdvances = [];
        if (! $isAnticipo && (float) $orderForPaymentRule->paid_total > 0) {
            $apisunatForCheckoutAdvances = app(\App\Services\ApisunatService::class);
            $checkoutPreviousAdvances = collect($this->resolveWorkshopAdvanceDocuments($orderForPaymentRule, false))
                ->filter(function (array $advance) use ($apisunatForCheckoutAdvances) {
                    $movementId = (int) ($advance['movement_id'] ?? 0);
                    if ($movementId <= 0) {
                        return false;
                    }
                    $movement = \App\Models\Movement::query()->find($movementId);

                    return $movement && $apisunatForCheckoutAdvances->isAdvanceSunatReady($movement);
                })
                ->values()
                ->all();
        }

        $checkoutDebtDueAt = $this->resolveCheckoutDebtDueAt($validated, $isDebtCheckout);
        $checkoutDocumentMeta = $this->buildCheckoutDocumentMeta(
            $request,
            $order,
            $isDebtCheckout,
            $isCheckoutSunatActive,
            $isCheckoutInvoiceDocument
        );

        $electronicResult = ['status' => 'NOT_CONFIGURED'];

        try {
            DB::transaction(function () use ($order, $validated, $branchId, $user, $paymentType, $isDebtCheckout, $isAnticipo, $checkoutPreviousAdvances, $checkoutDebtDueAt, $checkoutDocumentMeta, &$movementForApisunat, &$electronicResult) {
                $lockedOrder = WorkshopMovement::query()
                    ->where('id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $deliversOnConfirm = (string) $lockedOrder->status === 'finished';
                if (!in_array((string) $lockedOrder->status, ['approved', 'in_progress', 'paused', 'finished'], true)) {
                    throw new \RuntimeException('Solo se puede registrar venta y cobro cuando la OS esta aprobada, en reparacion, pausada o terminada.');
                }

                if ($isAnticipo) {
                    $pendingDebt = max(0, (float) $lockedOrder->total - (float) $lockedOrder->paid_total);
                    if ($pendingDebt <= 0.00001) {
                        throw new \RuntimeException('La OS ya no tiene saldo pendiente para registrar un pago anticipado.');
                    }
                }

                if (!$isAnticipo) {
                    $productLines = collect($validated['product_lines'] ?? [])
                        ->filter(fn($line) => !empty($line['product_id']))
                        ->values();

                    if ($productLines->isNotEmpty()) {
                        $productBranchIndex = ProductBranch::query()
                            ->where('branch_id', $branchId)
                            ->whereIn('product_id', $productLines->pluck('product_id')->map(fn($v) => (int) $v)->all())
                            ->whereNull('deleted_at')
                            ->get(['product_id', 'tax_rate_id'])
                            ->keyBy('product_id');

                        foreach ($productLines as $line) {
                            $productId = (int) $line['product_id'];
                            $productBranch = $productBranchIndex->get($productId);
                            if (!$productBranch) {
                                throw new \RuntimeException('Uno de los productos no pertenece a la sucursal actual.');
                            }

                            $product = \App\Models\Product::query()->find($productId);
                            if (!$product) {
                                throw new \RuntimeException('No se encontro uno de los productos seleccionados.');
                            }

                            $this->flowService->addDetail($lockedOrder, [
                                'line_type' => 'PART',
                                'product_id' => $productId,
                                'description' => $this->formatWorkshopProductPartDescription($product),
                                'qty' => (float) $line['qty'],
                                'unit_price' => (float) $line['unit_price'],
                                'tax_rate_id' => $productBranch->tax_rate_id ? (int) $productBranch->tax_rate_id : null,
                            ]);
                        }
                    }

                    $pendingPartDetails = \App\Models\WorkshopMovementDetail::query()
                        ->where('workshop_movement_id', $lockedOrder->id)
                        ->where('line_type', 'PART')
                        ->whereNull('sales_movement_id')
                        ->where('stock_consumed', false)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get();

                    foreach ($pendingPartDetails as $partDetail) {
                        $this->flowService->consumePart(
                            $partDetail,
                            $branchId,
                            (int) $user?->id,
                            (string) ($user?->name ?? 'Sistema'),
                            'Salida por venta/cobro OS #' . ($lockedOrder->movement?->number ?? $lockedOrder->id)
                        );
                    }
                }

                $pendingLines = (int) $lockedOrder->details()
                    ->whereNull('sales_movement_id')
                    ->count();

                $sale = null;
                if ($isAnticipo) {
                    $totalAnticipo = collect($validated['payment_methods'])->sum('amount');
                    if ($totalAnticipo <= 0.001) {
                        throw new \RuntimeException('Debe ingresar un monto válido para el anticipo (en los métodos de pago).');
                    }
                    if (empty($validated['document_type_id'])) {
                        throw new \RuntimeException('Debe seleccionar tipo de documento para emitir el anticipo.');
                    }

                    // Creamos el movimiento y venta del anticipo directamente.
                    // Para esto, usamos DB directo como lo hace FlowService, pero marcando is_advance.
                    $documentType = \App\Models\DocumentType::query()
                        ->where('id', $validated['document_type_id'])
                        ->first();
                    if (!$documentType) {
                        throw new \RuntimeException('El tipo de documento no corresponde a ventas.');
                    }

                    $isInvoiceDocument = mb_strpos(mb_strtolower((string) ($documentType->name ?? ''), 'UTF-8'), 'factura') !== false;
                    $resolvedBillingStatus = $isInvoiceDocument ? 'INVOICED' : 'NOT_APPLICABLE';
                    
                    $movement = \App\Models\Movement::query()->create([
                        'number' => $this->flowService->generateMovementNumber($branchId, $validated['document_type_id']),
                        'moved_at' => now(),
                        'user_id' => $user?->id,
                        'user_name' => $user?->name ?? 'Sistema',
                        'person_id' => $lockedOrder->client_person_id,
                        'person_name' => trim(($lockedOrder->client?->first_name ?? '') . ' ' . ($lockedOrder->client?->last_name ?? '')),
                        'responsible_id' => $user?->id,
                        'responsible_name' => $user?->name ?? 'Sistema',
                        'comment' => $validated['sale_comment'] ?? 'Pago anticipado OS #' . ($lockedOrder->movement?->number ?? $lockedOrder->id),
                        'status' => 'A',
                        'movement_type_id' => $documentType->movement_type_id,
                        'document_type_id' => $validated['document_type_id'],
                        'branch_id' => $branchId,
                        'parent_movement_id' => $lockedOrder->movement_id ? (int) $lockedOrder->movement_id : null,
                    ]);

                    $resolvedInvoiceSeries = $isInvoiceDocument ? trim((string) ($validated['invoice_series'] ?? '001')) : '001';
                    $resolvedInvoiceSeries = $resolvedInvoiceSeries ?: '001';

                    $sale = \App\Models\SalesMovement::query()->create([
                        'branch_snapshot' => [
                            'id' => $branchId,
                            'company_id' => $lockedOrder->company_id,
                        ],
                        'series' => $resolvedInvoiceSeries,
                        'billing_status' => $resolvedBillingStatus,
                        'billing_number' => null, // Omitimos correlativo manual por ahora para simplificar.
                        'year' => (string) now()->year,
                        'detail_type' => 'DETALLADO',
                        'consumption' => 'N',
                        'payment_type' => 'CONTADO',
                        'status' => 'N',
                        'sale_type' => 'RETAIL',
                        'currency' => 'PEN',
                        'exchange_rate' => 1,
                        'subtotal' => round($totalAnticipo / 1.18, 2),
                        'tax' => round($totalAnticipo - ($totalAnticipo / 1.18), 2),
                        'total' => $totalAnticipo,
                        'movement_id' => $movement->id,
                        'branch_id' => $branchId,
                        'is_advance' => true,
                    ]);

                    \App\Models\SalesMovementDetail::query()->create([
                        'detail_type' => 'DETALLADO',
                        'sales_movement_id' => $sale->id,
                        'code' => 'ANTICIPO',
                        'description' => 'PAGO ANTICIPADO DE OS #' . ($lockedOrder->movement?->number ?? $lockedOrder->id),
                        'product_id' => null,
                        'product_snapshot' => null,
                        'unit_id' => 27, // NIU as default fallback
                        'tax_rate_id' => 1, // Default IGV 18%
                        'tax_rate_snapshot' => null,
                        'quantity' => 1,
                        'amount' => $totalAnticipo,
                        'discount_percentage' => 0,
                        'original_amount' => round($totalAnticipo / 1.18, 2),
                        'comment' => 'OS #' . ($lockedOrder->movement?->number ?? $lockedOrder->id),
                        'parent_detail_id' => null,
                        'complements' => [],
                        'status' => 'A',
                        'branch_id' => $branchId,
                    ]);

                    $movementForApisunat = $movement;

                } else {
                    // Mantener la misma lógica operativa del módulo de ventas:
                    // si aún no existe venta asociada o hay líneas pendientes, primero se factura.
                    $mustGenerateSale = (bool) ($validated['generate_sale'] ?? false);
                    if ((int) ($lockedOrder->sales_movement_id ?? 0) <= 0 || $pendingLines > 0) {
                        $mustGenerateSale = true;
                    }

                    if ($mustGenerateSale) {
                        if (empty($validated['document_type_id'])) {
                            throw new \RuntimeException('Debe seleccionar tipo de documento para generar la venta.');
                        }

                        $sale = $this->flowService->generateSale(
                            $lockedOrder,
                            (int) $validated['document_type_id'],
                            $branchId,
                            (int) $user?->id,
                            (string) ($user?->name ?? 'Sistema'),
                            $validated['sale_comment'] ?? null,
                            null,
                            $validated['billing_status'] ?? null,
                            $validated['invoice_series'] ?? null,
                            $validated['invoice_number'] ?? null
                        );
                    }
                    
                    if ($sale && $sale->movement) {
                        $movementForApisunat = $sale->movement;
                    } elseif ($lockedOrder->salesMovement && $lockedOrder->salesMovement->movement) {
                        $movementForApisunat = $lockedOrder->salesMovement->movement;
                    }
                }

                $freshOrder = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                if (!$isAnticipo && $movementForApisunat) {
                    $movementForApisunat->loadMissing('salesMovement');
                    if ($movementForApisunat->salesMovement) {
                        $salesUpdate = [
                            'payment_type' => $isDebtCheckout ? 'CREDITO' : 'CONTADO',
                        ];
                        $salesUpdate['sunat_billing_meta'] = $checkoutDocumentMeta;
                        $movementForApisunat->salesMovement->update($salesUpdate);
                    }

                    if (count($checkoutPreviousAdvances) > 0) {
                        app(\App\Services\ApisunatService::class)->linkCheckoutAdvancesToFinalMovement(
                            $movementForApisunat,
                            $checkoutPreviousAdvances
                        );
                    }
                }

                $this->flowService->registerPayment(
                    $freshOrder,
                    (int) $validated['cash_register_id'],
                    $validated['payment_methods'],
                    $branchId,
                    (int) $user?->id,
                    (string) ($user?->name ?? 'Sistema'),
                    $validated['payment_comment'] ?? ($validated['sale_comment'] ?? null),
                    $paymentType,
                    $checkoutDebtDueAt
                );

                if (!$isAnticipo && $deliversOnConfirm) {
                    $afterPayment = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                    $this->flowService->updateOrder($afterPayment, [
                        'status' => 'delivered',
                        'delivery_date' => now()->format('Y-m-d H:i:s'),
                        'comment' => 'Entrega automatica al registrar venta y cobro desde tablero',
                    ]);
                }

                if ($movementForApisunat) {
                    $apisunatService = app(\App\Services\ApisunatService::class);
                    $orderForElectronic = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                    $electronicResult = $this->syncCheckoutElectronicInvoices(
                        $orderForElectronic,
                        $movementForApisunat,
                        $apisunatService,
                        (bool) $isAnticipo
                    );
                }
            });
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $order->refresh();

        $successMessage = 'Venta y cobro registrados correctamente.';
        $flash = [];
        if ($movementForApisunat && (($electronicResult['status'] ?? '') === 'SENT')) {
            $successMessage .= ' Comprobante electrónico enviado a SUNAT. Se descargarán el XML y el CDR automáticamente.';
            $flash['auto_download_xml_movement_id'] = (int) $movementForApisunat->id;
            $flash['auto_download_cdr_movement_id'] = (int) $movementForApisunat->id;
        }

        if ($request->filled('return_url')) {
            $redirect = redirect($request->input('return_url'))->with('status', $successMessage);
            foreach ($flash as $key => $value) {
                $redirect->with($key, $value);
            }
            return $redirect;
        }

        return $this->redirectToBoardWithStatus(
            (string) $order->status,
            $successMessage,
            $flash
        );
    }

    private function mergeExistingWorkshopDamagePhotos(WorkshopMovement $order, array $damagesWithPhotos): array
    {
        $existing = WorkshopPreexistingDamage::query()
            ->where('workshop_movement_id', $order->id)
            ->with('photos')
            ->get()
            ->keyBy(fn($d) => strtoupper((string) $d->side));

        foreach ($damagesWithPhotos as $index => $damage) {
            $side = strtoupper((string) ($damage['side'] ?? ''));
            if ($side === '') {
                continue;
            }
            $currentPhotos = isset($damage['photos']) && is_array($damage['photos']) ? $damage['photos'] : [];
            if ($currentPhotos !== []) {
                continue;
            }
            $row = $existing->get($side);
            if (!$row) {
                continue;
            }
            $paths = $row->photos->pluck('photo_path')->filter()->map(fn($p) => (string) $p)->values()->all();
            if ($paths === [] && $row->photo_path) {
                $paths = [(string) $row->photo_path];
            }
            if ($paths !== []) {
                $damagesWithPhotos[$index]['photos'] = $paths;
                $damagesWithPhotos[$index]['photo_path'] = $paths[0];
            }
        }

        return $damagesWithPhotos;
    }

    private function resolveMaintenanceBoardServiceUnitPrice(
        WorkshopService $service,
        Vehicle $vehicle,
        int $mileageForFrequency,
        string $priceCcOverride = 'auto'
    ): float {
        $override = trim($priceCcOverride);
        $unitPrice = null;

        if ($override === 'base' && (float) $service->base_price > 0) {
            $unitPrice = (float) $service->base_price;
        } elseif (str_starts_with($override, 'tier:')) {
            $rawMaxCc = trim(substr($override, 5));
            if (ctype_digit($rawMaxCc)) {
                $maxCc = (int) $rawMaxCc;
                $tiers = $service->relationLoaded('priceTiers')
                    ? $service->priceTiers
                    : $service->priceTiers()->get();
                $matchedTier = $tiers->first(fn($tier) => (int) ($tier->max_cc ?? 0) === $maxCc);
                if ($matchedTier) {
                    $unitPrice = (float) $matchedTier->price;
                }
            }
        }

        if ($unitPrice === null) {
            $unitPrice = (float) $service->resolvePriceForDisplacement((int) ($vehicle->engine_displacement_cc ?? 0));
        }

        $unitPrice = round($unitPrice, 6);
        if ($unitPrice < 0) {
            $unitPrice = 0;
        }

        if ((bool) ($service->frequency_enabled ?? false) && $mileageForFrequency > 0) {
            $validFrequencies = $service->frequencies
                ->filter(fn($f) => (int) ($f->km ?? 0) > 0 && ((int) $mileageForFrequency % (int) $f->km) === 0)
                ->sortByDesc(fn($f) => (int) $f->km)
                ->values();

            $multiplier = (float) ($validFrequencies->first()->multiplier ?? 1);
            $unitPrice = round((float) $unitPrice * $multiplier, 6);
        }

        return $unitPrice;
    }

    private function formatWorkshopProductPartDescription(object $row): string
    {
        $code = trim((string) ($row->code ?? ''));
        $marca = trim((string) ($row->marca ?? ''));
        $description = trim((string) ($row->description ?? ''));
        if ($description === '') {
            $description = 'Producto #' . (int) ($row->product_id ?? $row->id ?? 0);
        }
        $parts = array_values(array_filter(
            [$code, $marca !== '' ? $marca : null, $description],
            fn($p) => $p !== null && $p !== ''
        ));

        return $parts === [] ? '' : implode(' - ', $parts);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\WorkshopMovementDetail>|\Illuminate\Database\Eloquent\Collection  $details
     * @return array<int, array<string, mixed>>
     */
    private function mapQuotationDetailsToInitialServiceLines($details): array
    {
        return collect($details)
            ->whereIn('line_type', ['SERVICE', 'LABOR'])
            ->values()
            ->map(function ($detail) {
                $lineType = strtoupper((string) ($detail->line_type ?? ''));
                $hasCatalogService = $lineType === 'SERVICE' && !empty($detail->service_id);

                if (!$hasCatalogService) {
                    $description = trim((string) ($detail->description ?? ''));
                    if ($description === '') {
                        $description = $lineType === 'LABOR' ? 'Mano de obra' : 'Servicio';
                    }

                    return [
                        'detail_id' => 0,
                        'service_id' => '',
                        'description' => $description,
                        'qty' => (float) $detail->qty,
                        'unit_price' => (float) $detail->unit_price,
                        'validity_months' => $detail->validity_months,
                        'is_terciarizado' => (bool) $detail->is_terciarizado,
                        'line_uid' => 'qglosa_' . (int) $detail->id,
                    ];
                }

                return [
                    'detail_id' => 0,
                    'service_id' => (string) $detail->service_id,
                    'description' => (string) ($detail->description ?? ''),
                    'qty' => (float) $detail->qty,
                    'unit_price' => (float) $detail->unit_price,
                    'validity_months' => $detail->validity_months,
                    'is_terciarizado' => (bool) $detail->is_terciarizado,
                ];
            })
            ->all();
    }

    private function productBranchRowForMaintenance(int $branchId, int $productId): ?object
    {
        return ProductBranch::query()
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->whereNull('products.deleted_at')
            ->where('products.id', $productId)
            ->first([
                'product_branch.product_id',
                'product_branch.price',
                'product_branch.tax_rate_id',
                'products.description',
                'products.code',
                'products.marca',
            ]);
    }

    /**
     * @return array<int, array{kind: string, detail_id: int, service_id: ?int, description: string, qty: float, unit_price: float, price_cc_override?: string}>
     */
    private function parseMaintenanceBoardServiceLines(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sidRaw = trim((string) ($row['service_id'] ?? ''));
            $desc = trim((string) ($row['description'] ?? ''));
            $qty = round((float) ($row['qty'] ?? 0), 6);
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 6);
            $priceCcOverride = trim((string) ($row['price_cc_override'] ?? ''));
            $detailId = isset($row['detail_id']) && $row['detail_id'] !== '' && $row['detail_id'] !== null
                ? (int) $row['detail_id']
                : 0;
            $valRaw = mb_strtolower(trim((string) ($row['validity_months'] ?? '')), 'UTF-8');
            $validityMonths = ($valRaw === '6' || $valRaw === '12') ? (int) $valRaw : null;

            if ($qty <= 0) {
                continue;
            }

            if ($sidRaw !== '' && ctype_digit($sidRaw)) {
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        'service_lines' => 'El precio unitario no puede ser negativo.',
                    ]);
                }
                $out[] = [
                    'kind' => 'catalog',
                    'detail_id' => $detailId,
                    'service_id' => (int) $sidRaw,
                    'description' => $desc,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'price_cc_override' => $priceCcOverride,
                    'validity_months' => $validityMonths,
                    'is_terciarizado' => (bool) ($row['is_terciarizado'] ?? false),
                ];
                continue;
            }

            if ($desc !== '') {
                if (mb_strlen($desc, 'UTF-8') > 255) {
                    throw ValidationException::withMessages([
                        'service_lines' => 'La glosa no puede superar 255 caracteres.',
                    ]);
                }
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        'service_lines' => 'El precio unitario no puede ser negativo.',
                    ]);
                }
                $out[] = [
                    'kind' => 'glosa',
                    'detail_id' => $detailId,
                    'service_id' => null,
                    'description' => $desc,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'is_terciarizado' => (bool) ($row['is_terciarizado'] ?? false),
                ];
            }
        }

        return $out;
    }

    private function assertCatalogServiceLinesAllowed(array $parsedServiceLines, int $branchId, int $companyId): void
    {
        $ids = collect($parsedServiceLines)
            ->where('kind', 'catalog')
            ->pluck('service_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->filter(fn($id) => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $allowed = WorkshopService::query()
            ->where('active', true)
            ->whereIn('id', $ids)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->pluck('id')
            ->map(fn($value) => (int) $value)
            ->all();

        foreach ($ids as $id) {
            if (!in_array((int) $id, $allowed, true)) {
                throw ValidationException::withMessages([
                    'service_lines' => 'Hay servicios de catalogo no permitidos para esta sucursal o empresa.',
                ]);
            }
        }
    }

    private function normalizeMaintenanceBoardIntakeDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return now()->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return now()->format('Y-m-d H:i:s');
        }
    }

    private function assertOrderScope(WorkshopMovement $order): void
    {
        [$branchId, $companyId] = $this->branchScope();
        if ((int) $order->branch_id !== $branchId || (int) $order->company_id !== $companyId) {
            abort(404);
        }
    }

    private function branchScope(): array
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        return [$branchId, (int) $branch->company_id];
    }

    private function getBranchDefaultSaleDocumentTypeId(int $branchId, $documentTypes): ?int
    {
        $documentTypes = collect($documentTypes);

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
            $existsInSaleDocs = $documentTypes->contains(fn($d) => (int) $d->id === $configuredId);
            if ($existsInSaleDocs) {
                return $configuredId;
            }
        }

        return $documentTypes->first()?->id ? (int) $documentTypes->first()->id : null;
    }

    private function getBranchConfiguredCashRegisterId(int $branchId, $cashRegisters, string $needle): ?int
    {
        $cashRegisters = collect($cashRegisters);

        if ($branchId <= 0) {
            return $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
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
            $exists = $cashRegisters->contains(fn($cashRegister) => (int) ($cashRegister->id ?? 0) === $configuredId);
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

        $documentType = collect($documentTypes)->first(fn($item) => (int) ($item->id ?? 0) === (int) $documentTypeId);
        $name = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');

        return str_contains($name, 'factura');
    }

    private function inferPaymentMethodKind(string $description): string
    {
        $normalized = mb_strtolower(trim($description), 'UTF-8');

        if (
            str_contains($normalized, 'tarjeta')
            || str_contains($normalized, 'card')
            || str_contains($normalized, 'credito')
            || str_contains($normalized, 'débito')
            || str_contains($normalized, 'debito')
        ) {
            return 'card';
        }

        if (
            str_contains($normalized, 'billetera')
            || str_contains($normalized, 'wallet')
            || str_contains($normalized, 'yape')
            || str_contains($normalized, 'plin')
        ) {
            return 'wallet';
        }

        return 'other';
    }

    private function uploadDamagePhotos(Request $request, array $damages, int $branchId): array
    {
        foreach ($damages as $index => $damage) {
            $files = $request->file("damages.{$index}.photos", []);
            if (!is_array($files) || empty($files)) {
                continue;
            }

            $paths = [];
            foreach ($files as $file) {
                if (!$file) {
                    continue;
                }
                $paths[] = $file->store("workshop/intake/damages/{$branchId}", 'public');
            }

            if (!empty($paths)) {
                $damages[$index]['photos'] = $paths;
                $damages[$index]['photo_path'] = $paths[0];
            }
        }

        return $damages;
    }

    private function storeSignatureFromDataUri(string $signatureData, int $branchId): ?string
    {
        $signatureData = trim($signatureData);
        if ($signatureData === '' || !str_contains($signatureData, 'base64,')) {
            return null;
        }

        [$meta, $encoded] = explode('base64,', $signatureData, 2);
        if (!str_contains($meta, 'image/')) {
            return null;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) < 16) {
            return null;
        }

        $extension = 'png';
        if (str_contains($meta, 'image/jpeg')) {
            $extension = 'jpg';
        }

        $path = "workshop/intake/signatures/{$branchId}/sig_" . now()->format('YmdHisv') . '_' . mt_rand(1000, 9999) . ".{$extension}";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    public function tracking(\App\Models\WorkshopMovement $order): \Illuminate\View\View
    {
        $this->assertOrderScope($order);
        $order->load([
            'movement',
            'vehicle',
            'client',
            'statusHistories.user:id,name',
        ]);

        return view('workshop.maintenance-board.tracking', compact('order'));
    }

    public function advancePhase(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $validated = $request->validate([
            'next_phase' => ['required', 'string'],
            'date' => ['required', 'date'],
            'observations' => ['nullable', 'string'],
            'approved' => ['nullable', 'string', 'in:yes,no'],
            'technician_id' => ['nullable', 'integer'],
        ]);

        $nextPhase = $validated['next_phase'];
        $date = Carbon::parse($validated['date']);
        $obs = $validated['observations'];
        $approved = $validated['approved'] ?? 'yes';

        if ($nextPhase === 'cotizacion_aprobacion' && $approved === 'no') {
            $oldStatus = $order->status;

            $patch = [
                'status' => 'cancelled',
                'corrective_phase' => 'cotizacion_rechazada',
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('workshop_movements', 'approval_status')) {
                $patch['approval_status'] = 'rejected';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('workshop_movements', 'quotation_result')) {
                $patch['quotation_result'] = 'lost';
                $patch['quotation_lost_reason'] = $obs;
            }

            $order->update($patch);

            $order->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => 'cancelled',
                'user_id' => (int) $request->user()?->id,
                'note' => "Cotización Rechazada por el cliente." . (!empty($obs) ? " Observaciones: " . $obs : ""),
            ]);

            return redirect()->route('workshop.maintenance-board.corrective')->with('status', 'Cotización rechazada. El servicio ha sido cancelado.');
        }

        // Map phase to column
        $phaseToColumn = [
            'programacion' => 'corrective_scheduled_at',
            'eval_inicio' => 'corrective_eval_started_at',
            'eval_fin' => 'corrective_eval_finished_at',
            'cotizacion_entrega' => 'corrective_quote_delivered_at',
            'cotizacion_aprobacion' => 'corrective_quote_approved_at',
            'repuestos_solicitud' => 'corrective_parts_requested_at',
            'repuestos_entrega' => 'corrective_parts_delivered_at',
            'reparacion_inicio' => 'corrective_repair_started_at',
            'reparacion_fin' => 'corrective_repair_finished_at',
            'servicio_inicio' => null, // Mueve a tablero principal (Approved)
        ];

        $column = $phaseToColumn[$nextPhase] ?? null;

        if ($column) {
            $order->{$column} = $date;
        }

        $order->corrective_phase = $nextPhase;

        if ($obs) {
            $currentObs = $order->corrective_observations ? $order->corrective_observations . "\n" : "";
            $phaseLabel = ucfirst(str_replace('_', ' ', $nextPhase));
            $order->corrective_observations = $currentObs . "[" . now()->format('Y-m-d H:i') . " - {$phaseLabel}]: " . $obs;
        }

        if (!empty($validated['technician_id'])) {
            $techId = (int) $validated['technician_id'];
            if (!$order->technicians()->where('technician_person_id', $techId)->exists()) {
                $order->technicians()->create([
                    'technician_person_id' => $techId,
                ]);
            }
        }

        if ($nextPhase === 'reparacion_inicio') {
            // Al iniciar reparación (fase final administrativa), movemos al tablero principal como Aprobado
            $order->status = 'approved';
        }

        $order->save();

        return back()->with('status', 'Fase de servicio correctivo actualizada exitosamente.');
    }

    public function partsReplacementStatus(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);

        if (!in_array((string) $order->status, ['in_progress', 'paused'], true)) {
            return redirect()
                ->route('workshop.maintenance-board.index', request()->only('view_id'))
                ->with('status', 'El estado de repuestos solo está disponible para vehículos en reparación.');
        }

        $order->load([
            'movement',
            'vehicle',
            'client',
            'details.product',
            'partReplacementPairs.photos',
        ]);

        $pairsPayload = $this->buildPartsReplacementPairsPayload($order);

        return view('workshop.maintenance-board.parts-replacement-status', [
            'order' => $order,
            'pairsPayload' => $pairsPayload,
            'viewId' => request('view_id'),
        ]);
    }

    public function storePartsReplacementStatus(Request $request, WorkshopMovement $order)
    {
        $this->assertOrderScope($order);

        if (!in_array((string) $order->status, ['in_progress', 'paused'], true)) {
            return redirect()
                ->route('workshop.maintenance-board.index', $request->only('view_id'))
                ->with('status', 'El estado de repuestos solo está disponible para vehículos en reparación.');
        }

        $validated = $request->validate([
            'report_notes' => ['nullable', 'string', 'max:5000'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.id' => ['nullable', 'integer'],
            'pairs.*.old_part_name' => ['nullable', 'string', 'max:255'],
            'pairs.*.new_part_name' => ['nullable', 'string', 'max:255'],
            'pairs.*.old_part_notes' => ['nullable', 'string', 'max:2000'],
            'pairs.*.new_part_notes' => ['nullable', 'string', 'max:2000'],
            'pairs.*.old_photos' => ['nullable', 'array'],
            'pairs.*.old_photos.*' => ['nullable', 'image', 'max:10240'],
            'pairs.*.new_photos' => ['nullable', 'array'],
            'pairs.*.new_photos.*' => ['nullable', 'image', 'max:10240'],
            'delete_photo_ids' => ['nullable', 'array'],
            'delete_photo_ids.*' => ['integer'],
        ]);

        $branchId = (int) session('branch_id');
        $pairsInput = collect($validated['pairs'] ?? []);
        $deletePhotoIds = collect($validated['delete_photo_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        DB::transaction(function () use ($order, $validated, $pairsInput, $deletePhotoIds, $request, $branchId) {
            $order->parts_replacement_report_notes = trim((string) ($validated['report_notes'] ?? '')) ?: null;
            $order->save();

            if ($deletePhotoIds->isNotEmpty()) {
                $photosToDelete = WorkshopPartReplacementPhoto::query()
                    ->whereIn('id', $deletePhotoIds->all())
                    ->whereHas('pair', fn ($query) => $query->where('workshop_movement_id', $order->id))
                    ->get();

                foreach ($photosToDelete as $photo) {
                    if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
                        Storage::disk('public')->delete($photo->photo_path);
                    }
                    $photo->delete();
                }
            }

            $submittedPairIds = $pairsInput
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            $existingPairIds = $order->partReplacementPairs()->pluck('id')->map(fn ($id) => (int) $id);
            $pairIdsToDelete = $existingPairIds->diff($submittedPairIds);
            if ($pairIdsToDelete->isNotEmpty()) {
                $pairsToRemove = $order->partReplacementPairs()
                    ->whereIn('id', $pairIdsToDelete->all())
                    ->with('photos')
                    ->get();

                foreach ($pairsToRemove as $pairToRemove) {
                    foreach ($pairToRemove->photos as $photo) {
                        if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
                            Storage::disk('public')->delete($photo->photo_path);
                        }
                        $photo->delete();
                    }
                    $pairToRemove->delete();
                }
            }

            foreach ($pairsInput->values() as $sortOrder => $pairData) {
                $oldName = trim((string) ($pairData['old_part_name'] ?? ''));
                $newName = trim((string) ($pairData['new_part_name'] ?? ''));
                $oldNotes = trim((string) ($pairData['old_part_notes'] ?? ''));
                $newNotes = trim((string) ($pairData['new_part_notes'] ?? ''));

                $pairId = !empty($pairData['id']) ? (int) $pairData['id'] : null;
                $existingPair = $pairId
                    ? $order->partReplacementPairs()->where('id', $pairId)->first()
                    : null;

                $hasNewOldPhotos = !empty($request->file("pairs.{$sortOrder}.old_photos"));
                $hasNewNewPhotos = !empty($request->file("pairs.{$sortOrder}.new_photos"));
                $existingPhotosCount = $existingPair
                    ? $existingPair->photos()->whereNotIn('id', $deletePhotoIds->all())->count()
                    : 0;

                if (
                    $oldName === ''
                    && $newName === ''
                    && $oldNotes === ''
                    && $newNotes === ''
                    && !$hasNewOldPhotos
                    && !$hasNewNewPhotos
                    && $existingPhotosCount === 0
                ) {
                    if ($existingPair) {
                        $existingPair->delete();
                    }
                    continue;
                }

                if ($existingPair) {
                    $pair = $existingPair;
                    $pair->update([
                        'old_part_name' => $oldName !== '' ? $oldName : null,
                        'new_part_name' => $newName !== '' ? $newName : null,
                        'old_part_notes' => $oldNotes !== '' ? $oldNotes : null,
                        'new_part_notes' => $newNotes !== '' ? $newNotes : null,
                        'sort_order' => $sortOrder,
                    ]);
                } else {
                    $pair = $order->partReplacementPairs()->create([
                        'old_part_name' => $oldName !== '' ? $oldName : null,
                        'new_part_name' => $newName !== '' ? $newName : null,
                        'old_part_notes' => $oldNotes !== '' ? $oldNotes : null,
                        'new_part_notes' => $newNotes !== '' ? $newNotes : null,
                        'sort_order' => $sortOrder,
                    ]);
                }

                $this->storePartReplacementPhotos($request, $pair, $sortOrder, 'old', $branchId);
                $this->storePartReplacementPhotos($request, $pair, $sortOrder, 'new', $branchId);
            }
        });

        return redirect()
            ->route('workshop.maintenance-board.parts-replacement-status', array_filter([
                'order' => $order->id,
                'view_id' => $request->input('view_id'),
            ]))
            ->with('status', 'Estado de repuestos guardado correctamente.');
    }

    public function destroyPartsReplacementPhoto(Request $request, WorkshopMovement $order, WorkshopPartReplacementPhoto $photo)
    {
        $this->assertOrderScope($order);

        $pair = $photo->pair;
        if (!$pair || (int) $pair->workshop_movement_id !== (int) $order->id) {
            abort(404);
        }

        if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }

        $photo->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Fotografía eliminada.');
    }

    private function buildPartsReplacementPairsPayload(WorkshopMovement $order): array
    {
        $existing = $order->partReplacementPairs
            ->map(function (WorkshopPartReplacementPair $pair) {
                return [
                    'id' => (int) $pair->id,
                    'old_part_name' => (string) ($pair->old_part_name ?? ''),
                    'new_part_name' => (string) ($pair->new_part_name ?? ''),
                    'old_part_notes' => (string) ($pair->old_part_notes ?? ''),
                    'new_part_notes' => (string) ($pair->new_part_notes ?? ''),
                    'old_photos' => $pair->photos
                        ->where('photo_type', 'old')
                        ->values()
                        ->map(fn (WorkshopPartReplacementPhoto $photo) => [
                            'id' => (int) $photo->id,
                            'url' => Storage::disk('public')->url($photo->photo_path),
                        ])
                        ->all(),
                    'new_photos' => $pair->photos
                        ->where('photo_type', 'new')
                        ->values()
                        ->map(fn (WorkshopPartReplacementPhoto $photo) => [
                            'id' => (int) $photo->id,
                            'url' => Storage::disk('public')->url($photo->photo_path),
                        ])
                        ->all(),
                ];
            })
            ->values();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $fromParts = $order->details
            ->filter(fn ($detail) => strtoupper((string) $detail->line_type) === 'PART')
            ->values()
            ->map(function ($detail) {
                $label = trim((string) ($detail->description ?? ''));
                if ($label === '' && $detail->product) {
                    $code = trim((string) ($detail->product->code ?? ''));
                    $name = trim((string) ($detail->product->description ?? ''));
                    $label = $code !== '' ? ($code . ($name !== '' ? ' - ' . $name : '')) : $name;
                }

                return [
                    'id' => null,
                    'old_part_name' => $label,
                    'new_part_name' => '',
                    'old_part_notes' => '',
                    'new_part_notes' => '',
                    'old_photos' => [],
                    'new_photos' => [],
                ];
            });

        if ($fromParts->isNotEmpty()) {
            return $fromParts->all();
        }

        return [[
            'id' => null,
            'old_part_name' => '',
            'new_part_name' => '',
            'old_part_notes' => '',
            'new_part_notes' => '',
            'old_photos' => [],
            'new_photos' => [],
        ]];
    }

    private function storePartReplacementPhotos(
        Request $request,
        WorkshopPartReplacementPair $pair,
        int $pairIndex,
        string $photoType,
        int $branchId
    ): void {
        $files = $request->file("pairs.{$pairIndex}.{$photoType}_photos", []);
        if (!is_array($files) || empty($files)) {
            return;
        }

        $currentMaxSort = (int) $pair->photos()->where('photo_type', $photoType)->max('sort_order');
        $sort = $currentMaxSort;

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $sort++;
            $path = \App\Support\WorkshopPhotoOptimizer::storeOptimized(
                $file,
                "workshop/parts-replacement/{$branchId}/{$pair->id}",
                'public'
            );
            $pair->photos()->create([
                'photo_type' => $photoType,
                'photo_path' => $path,
                'sort_order' => $sort,
            ]);
        }
    }

    public function partsRequest(WorkshopMovement $order)
    {
        $branchId = (int) session('branch_id');

        $order->load([
            'details' => function ($q) {
                $q->where('line_type', 'PART')->orderBy('id');
            },
            'details.product',
            'details.supplier'
        ]);

        $products = \App\Models\ProductBranch::query()
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->whereNull('products.deleted_at')
            ->orderBy('products.description')
            ->get([
                'product_branch.product_id',
                'product_branch.price',
                'products.code',
                'products.marca',
                'products.description',
            ])
            ->map(fn($row) => (object) [
                'id' => (int) $row->product_id,
                'code' => (string) ($row->code ?? ''),
                'marca' => (string) ($row->marca ?? ''),
                'description' => (string) ($row->description ?? ''),
                'price' => (float) ($row->price ?? 0),
            ])
            ->values();

        $suppliers = \App\Models\Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        return view('workshop.maintenance-board.parts-request', compact('order', 'products', 'suppliers'));
    }

    public function storePartsRequest(Request $request, WorkshopMovement $order)
    {
        $branchId = (int) session('branch_id');

        $validated = $request->validate([
            'parts' => ['nullable', 'array'],
            'parts.*.detail_id' => ['nullable', 'integer'],
            'parts.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'parts.*.description' => ['nullable', 'string', 'max:255'],
            'parts.*.qty' => ['required', 'numeric', 'min:0.01'],
            'parts.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'parts.*.supplier_person_id' => ['nullable', 'integer', 'exists:people,id'],
        ]);

        $parts = $validated['parts'] ?? [];

        // First, handle existing PART lines. If they are not in the submitted parts, we might not delete them if they were quoted?
        // Actually, the view will send ALL parts. If a detail_id is missing, it should be deleted.
        $submittedDetailIds = collect($parts)->pluck('detail_id')->filter()->map(fn($id) => (int) $id)->all();
        $order->details()->where('line_type', 'PART')->whereNotIn('id', $submittedDetailIds)->delete();

        foreach ($parts as $partData) {
            $qty = round((float) $partData['qty'], 6);
            if ($qty <= 0)
                continue;

            $unitPrice = round((float) ($partData['unit_price'] ?? 0), 6);
            $supplierId = !empty($partData['supplier_person_id']) ? (int) $partData['supplier_person_id'] : null;

            if (!empty($partData['detail_id'])) {
                // Update existing
                $detail = $order->details()->find($partData['detail_id']);
                if ($detail && $detail->line_type === 'PART') {
                    $detail->update([
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'supplier_person_id' => $supplierId,
                        'description' => $partData['description'] ?? $detail->description, // update glosa desc if needed
                    ]);
                }
            } else {
                // Create new
                $description = $partData['description'] ?? '';
                if (empty($description) && !empty($partData['product_id'])) {
                    $product = \App\Models\Product::find($partData['product_id']);
                    if ($product) {
                        $description = $product->description . ($product->marca ? ' (' . $product->marca . ')' : '');
                    }
                }

                $this->flowService->addDetail($order, [
                    'line_type' => 'PART',
                    'product_id' => $partData['product_id'] ?? null,
                    'description' => $description,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                ]);

                // The flowService->addDetail doesn't have supplier_person_id in its input params normally, 
                // so we update the latest created detail (or we can just create it directly).
                // Let's get the latest detail and update it.
                $latest = $order->details()->where('line_type', 'PART')->latest('id')->first();
                if ($latest && $supplierId) {
                    $latest->update(['supplier_person_id' => $supplierId]);
                }
            }
        }

        // Advance phase to repuestos_solicitud
        $order->corrective_phase = 'repuestos_solicitud';
        $order->corrective_parts_requested_at = now();

        $obs = $request->input('observations');
        if ($obs) {
            $currentObs = $order->corrective_observations ? $order->corrective_observations . "\n" : "";
            $order->corrective_observations = $currentObs . "[" . now()->format('Y-m-d H:i') . " - Solicitud de Repuestos]: " . $obs;
        }

        $order->save();

        return redirect()->route('workshop.maintenance-board.corrective')
            ->with('status', 'Solicitud de repuestos registrada correctamente y fase avanzada.');
    }

    public function getLastDriverInfo(Request $request): JsonResponse
    {
        $vehicleId = (int) $request->query('vehicle_id');
        if (!$vehicleId) {
            return response()->json(['success' => false, 'message' => 'Vehicle ID is required.'], 400);
        }

        $lastMovement = WorkshopMovement::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('driver_name')
            ->orderByDesc('id')
            ->first(['driver_name', 'driver_phone']);

        return response()->json([
            'success' => true,
            'driver_name' => $lastMovement?->driver_name ?? '',
            'driver_phone' => $lastMovement?->driver_phone ?? '',
        ]);
    }

    private function syncCheckoutElectronicInvoices(
        WorkshopMovement $order,
        \App\Models\Movement $movementForApisunat,
        \App\Services\ApisunatService $apisunatService,
        bool $isAnticipo
    ): array {
        if (! $apisunatService->isConfiguredForBranch($movementForApisunat->branch)) {
            return ['status' => 'NOT_CONFIGURED'];
        }

        if (! $isAnticipo
            && (int) ($order->movement_id ?? 0) > 0
            && $apisunatService->saleHasExplicitAdvanceApplications($movementForApisunat)) {
            $pendingAdvances = \App\Models\SalesMovement::query()
                ->where('is_advance', true)
                ->whereHas('movement', function ($query) use ($order) {
                    $query->where('parent_movement_id', (int) $order->movement_id);
                })
                ->with(['movement.branch', 'movement.documentType', 'movement.salesMovement'])
                ->get();

            foreach ($pendingAdvances as $advanceSale) {
                $advanceMovement = $advanceSale->movement;
                if (! $advanceMovement || $apisunatService->isAdvanceSunatReady($advanceMovement)) {
                    continue;
                }
                if (! $apisunatService->isEligibleDocument($advanceMovement)) {
                    continue;
                }

                $advanceResult = $this->syncElectronicInvoiceForSale($advanceMovement, $apisunatService);
                $apisunatService->assertSaleElectronicEmissionSucceeded($advanceMovement, $advanceResult);
            }
        }

        $finalResult = $this->syncElectronicInvoiceForSale($movementForApisunat, $apisunatService);
        $apisunatService->assertSaleElectronicEmissionSucceeded($movementForApisunat, $finalResult);

        return $finalResult;
    }

    private function resolveWorkshopAdvanceDocuments(WorkshopMovement $order, bool $onlySunatReady = true): array
    {
        if ((int) ($order->movement_id ?? 0) <= 0) {
            return [];
        }

        $advances = \App\Models\SalesMovement::query()
            ->where('is_advance', true)
            ->whereHas('movement', function ($query) use ($order) {
                $query->where('parent_movement_id', (int) $order->movement_id);
            })
            ->with(['movement.documentType'])
            ->orderBy('id')
            ->get();

        $apisunatService = app(\App\Services\ApisunatService::class);

        return $advances->map(function (\App\Models\SalesMovement $sale) use ($apisunatService) {
            $movement = $sale->movement;
            $series = trim((string) ($movement?->electronic_invoice_series ?? $sale->series ?? ''));
            $correlative = trim((string) ($movement?->electronic_invoice_number ?? $sale->billing_number ?? $movement?->number ?? ''));
            $correlativeDigits = preg_replace('/\D+/', '', $correlative) ?: '';
            if ($correlativeDigits !== '') {
                $correlative = str_pad($correlativeDigits, 8, '0', STR_PAD_LEFT);
            }

            $fullNumber = ($series !== '' && $correlative !== '')
                ? $series . '-' . $correlative
                : trim((string) ($movement?->number ?? ('#' . $sale->id)));

            $docName = mb_strtolower(trim((string) ($movement?->documentType?->name ?? '')), 'UTF-8');
            $documentTypeCode = str_contains($docName, 'factura') ? '01' : '03';
            $sunatReady = $movement ? $apisunatService->isAdvanceSunatReady($movement) : false;

            return [
                'sales_movement_id' => (int) $sale->id,
                'movement_id' => (int) ($movement?->id ?? 0),
                'document_name' => trim((string) ($movement?->documentType?->name ?? 'Comprobante')),
                'document_type_code' => $documentTypeCode,
                'series' => $series,
                'correlative' => $correlative,
                'full_number' => $fullNumber,
                'amount' => round((float) $sale->total, 2),
                'issue_date' => optional($movement?->moved_at)->format('Y-m-d'),
                'issue_time' => optional($movement?->moved_at)->format('H:i:s'),
                'electronic_status' => trim((string) ($movement?->electronic_invoice_status ?? '')),
                'sunat_ready' => $sunatReady,
            ];
        })
            ->when($onlySunatReady, fn ($rows) => $rows->filter(fn (array $row) => (bool) ($row['sunat_ready'] ?? false)))
            ->values()
            ->all();
    }

    private function boardIndexFilterForStatus(string $status): string
    {
        return match ($status) {
            'in_progress', 'paused' => 'in_progress',
            'in_progress_external' => 'in_progress_external',
            default => $status,
        };
    }

    private function recordWorkshopBoardStatusChange(
        WorkshopMovement $order,
        string $newStatus,
        string $note,
        int $userId
    ): void {
        $fromStatus = (string) $order->status;
        if ($fromStatus === $newStatus) {
            return;
        }

        $order->update(['status' => $newStatus]);

        WorkshopStatusHistory::query()->create([
            'workshop_movement_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'user_id' => $userId > 0 ? $userId : null,
            'note' => $note,
        ]);
    }

    private function redirectToBoardWithStatus(string $orderStatus, ?string $message = null, array $flash = []): RedirectResponse
    {
        $autoRedirect = $this->branchBooleanParameter((int) session('branch_id'), 'Redirigir a pestaña de nuevo estado automáticamente', true);

        if (!$autoRedirect && request()->headers->get('referer')) {
            $redirect = redirect()->back();
        } else {
            $params = array_filter([
                'status' => $this->boardIndexFilterForStatus($orderStatus),
                'view_id' => request()->query('view_id'),
            ]);
            $redirect = redirect()->route('workshop.maintenance-board.index', $params);
        }

        if ($message !== null && $message !== '') {
            $redirect->with('status', $message);
        }

        foreach ($flash as $key => $value) {
            $redirect->with($key, $value);
        }

        return $redirect;
    }

    private function resolveCheckoutDebtDueAt(array $validated, bool $isDebtCheckout): ?Carbon
    {
        if (!$isDebtCheckout) {
            return null;
        }

        $dueDateStr = trim((string) ($validated['debt_due_date'] ?? ''));
        if ($dueDateStr !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $dueDateStr)->endOfDay();
            } catch (\Throwable $e) {
                // fallback a días de crédito
            }
        }

        return now()->startOfDay()
            ->addDays(max(0, (int) ($validated['credit_days'] ?? 0)))
            ->endOfDay();
    }

    private function buildCheckoutDocumentMeta(
        Request $request,
        WorkshopMovement $order,
        bool $isDebtCheckout,
        bool $isSunatActive,
        bool $isInvoiceDocument
    ): array {
        $serviceOrderNumber = trim((string) ($order->movement?->number ?? ''));
        if ($serviceOrderNumber === '') {
            $serviceOrderNumber = '#' . $order->id;
        }

        $creditDays = max(0, (int) $request->input('credit_days', 0));
        $dueDateStr = trim((string) $request->input('debt_due_date', ''));
        if ($isDebtCheckout && $dueDateStr === '' && $creditDays > 0) {
            $dueDateStr = now()->startOfDay()->addDays($creditDays)->format('Y-m-d');
        }

        $meta = [
            'service_order_number' => $serviceOrderNumber,
            'purchase_order_number' => trim((string) $request->input('purchase_order_number', '')),
            'vehicle_plate' => trim((string) ($order->vehicle?->plate ?? '')),
            'credit_days' => $creditDays,
            'credit_due_date' => $dueDateStr,
            'apply_detraccion' => false,
            'apply_retencion' => false,
        ];

        if ($isDebtCheckout && $isSunatActive && $isInvoiceDocument) {
            $meta['apply_detraccion'] = $request->boolean('sunat_apply_detraccion');
            $meta['apply_retencion'] = $request->boolean('sunat_apply_retencion');
            if ($meta['apply_detraccion']) {
                $meta['detraccion_type'] = trim((string) $request->input('sunat_detraccion_type', '020')) ?: '020';
                $meta['detraccion_percent'] = round((float) $request->input('sunat_detraccion_percent', 12), 2);
                $meta['detraccion_bank_account'] = trim((string) $request->input('sunat_detraccion_bank_account', ''));
            }
            if ($meta['apply_retencion']) {
                $meta['retencion_percent'] = round((float) $request->input('sunat_retencion_percent', 3), 2);
            }
        }

        return $meta;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function sunatDetraccionCatalogOptions(): array
    {
        return [
            ['code' => '020', 'label' => '020 - Mantenimiento y reparación de bienes muebles'],
            ['code' => '022', 'label' => '022 - Otros servicios empresariales'],
            ['code' => '037', 'label' => '037 - Servicios de transporte de carga'],
            ['code' => '030', 'label' => '030 - Mantenimiento y reparación de bienes muebles (alterno)'],
        ];
    }

    private function renderCheckoutPreviewPdf(string $html, string $format): ?string
    {
        $binary = $this->resolveWkhtmltopdfBinary();
        if (!$binary) {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $htmlFile = tempnam($tmpDir, 'checkout_preview_html_');
        $pdfFile = tempnam($tmpDir, 'checkout_preview_pdf_');
        if ($htmlFile === false || $pdfFile === false) {
            return null;
        }

        $htmlPath = $htmlFile . '.html';
        $pdfPath = $pdfFile . '.pdf';
        @rename($htmlFile, $htmlPath);
        @rename($pdfFile, $pdfPath);
        file_put_contents($htmlPath, $html);

        $extraArgs = $format === 'ticket'
            ? ['--page-width', '80mm', '--page-height', '297mm', '--margin-top', '2', '--margin-right', '2', '--margin-bottom', '2', '--margin-left', '2', '--disable-smart-shrinking']
            : [];

        $args = array_merge([
            $binary,
            '--enable-local-file-access',
            '--disable-javascript',
            '--load-error-handling', 'ignore',
            '--load-media-error-handling', 'ignore',
            '--encoding', 'utf-8',
            '--margin-top', '8',
            '--margin-right', '8',
            '--margin-bottom', '8',
            '--margin-left', '8',
        ], $extraArgs);

        if ($format !== 'ticket') {
            $args[] = '--page-size';
            $args[] = 'A4';
        }

        $args[] = $htmlPath;
        $args[] = $pdfPath;

        $process = new Process($args);

        try {
            $process->setTimeout(120);
            $process->run();
            if (!file_exists($pdfPath) || filesize($pdfPath) <= 0) {
                Log::warning('wkhtmltopdf fallo al generar vista previa de cobro', [
                    'error' => $process->getErrorOutput(),
                ]);

                return null;
            }

            $content = file_get_contents($pdfPath);

            return $content === false ? null : $content;
        } catch (\Throwable $e) {
            Log::warning('Error wkhtmltopdf vista previa cobro: ' . $e->getMessage());

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
            base_path('wkhtmltopdf/bin/wkhtmltopdf.exe'),
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\Snappy\\wkhtmltopdf.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function syncElectronicInvoiceForSale(\App\Models\Movement $movement, \App\Services\ApisunatService $apisunatService): array
    {
        if (!$apisunatService->isEligibleDocument($movement)) {
            return ['status' => 'SKIPPED'];
        }
        if (!$apisunatService->isConfiguredForBranch($movement->branch)) {
            return ['status' => 'NOT_CONFIGURED', 'message' => 'La sucursal no tiene Apisunat configurado.'];
        }
        try {
            $result = $apisunatService->emitSale($movement);
            $apisunatService->persistEmittedElectronicData($movement, $result);

            return $result;
        } catch (\Exception $e) {
            $movement->update([
                'electronic_invoice_provider' => 'apisunat',
                'electronic_invoice_status' => 'ERROR',
                'electronic_invoice_response' => ['error' => $e->getMessage()],
            ]);
            \Illuminate\Support\Facades\Log::error('Error emitiendo comprobante electrónico en POS: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
