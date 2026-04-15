<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Card;
use App\Models\CashRegister;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\PaymentGateways;
use App\Models\Person;
use App\Models\ProductBranch;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementTechnician;
use App\Models\WorkshopPreexistingDamage;
use App\Models\WorkshopService;
use App\Models\WorkshopVehicleIntakeInventoryItem;
use App\Services\Workshop\WorkshopFlowService;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkshopMaintenanceBoardController extends Controller
{
    public function __construct(private readonly WorkshopFlowService $flowService)
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
        $allowedStatuses = [
            'draft',
            'diagnosis',
            'awaiting_approval',
            'approved',
            'in_progress',
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
                'details' => fn ($query) => $query
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('deleted_at')
                    ->orderBy('id'),
                'details.service:id,name,type,base_price',
                'details.product:id,code,description',
            ])
            ->withCount([
                'details as pending_billing_count' => fn ($query) => $query->whereNull('sales_movement_id'),
            ])
            ->withSum([
                'details as pending_billing_total' => fn ($query) => $query->whereNull('sales_movement_id'),
            ], 'total')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when(
                Schema::hasColumn('workshop_movements', 'quotation_source'),
                fn ($query) => $query->where(function ($scope) {
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
                    return $query->whereIn('status', ['in_progress', 'paused']);
                }
                return $query->where('status', $selectedStatus);
            })
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'paused' THEN 2 WHEN 'approved' THEN 3 WHEN 'awaiting_approval' THEN 4 WHEN 'diagnosis' THEN 5 ELSE 6 END")
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

    public function create(Request $request): \Illuminate\View\View
    {
        [$branchId, $companyId] = $this->branchScope();
        $formData = $this->maintenanceFormData($branchId, $companyId);

        $preFilledVehicleId = (string) $request->query('vehicle_id', '');
        $preFilledClientId = (string) $request->query('client_person_id', '');
        $preFilledDiagnosis = (string) $request->query('diagnosis', '');
        $preFilledAppointmentId = (string) $request->query('appointment_id', '');

        return view('workshop.maintenance-board.create', $formData + [
            'editingOrder' => null,
            'initialServiceLines' => [],
            'initialProductLines' => [],
            'initialInventoryChecks' => [],
            'editingVehicleLabel' => '',
            'editingClientLabel' => '',
            'editingDamageRows' => [],
            'editingDamagePhotoPreviews' => [0 => [], 1 => [], 2 => [], 3 => []],
            'editingSignatureUrl' => null,
            'preFilledVehicleId' => $preFilledVehicleId,
            'preFilledClientId' => $preFilledClientId,
            'preFilledDiagnosis' => $preFilledDiagnosis,
            'preFilledAppointmentId' => $preFilledAppointmentId,
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
            'damages.photos',
            'details' => fn ($query) => $query->whereNull('sales_movement_id')->whereNull('deleted_at')->orderBy('id'),
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

        $initialServiceLines = $order->details
            ->where('line_type', 'SERVICE')
            ->values()
            ->map(fn ($d) => [
                'detail_id' => (int) $d->id,
                'service_id' => $d->service_id !== null ? (string) $d->service_id : '',
                'description' => (string) ($d->description ?? ''),
                'qty' => (float) $d->qty,
                'unit_price' => (float) $d->unit_price,
                'validity_months' => $d->validity_months,
            ])
            ->all();

        $initialProductLines = $order->details
            ->where('line_type', 'PART')
            ->values()
            ->map(fn ($d) => [
                'detail_id' => (int) $d->id,
                'product_id' => (string) ($d->product_id ?? ''),
                'qty' => (float) $d->qty,
                'unit_price' => (float) $d->unit_price,
                'label' => (string) ($d->description ?? ''),
            ])
            ->all();

        $vehicleTypeId = $order->vehicle?->vehicle_type_id;
        $inventoryKeys = WorkshopVehicleIntakeInventoryItem::query()
            ->when($vehicleTypeId, fn ($q) => $q->where('vehicle_type_id', (int) $vehicleTypeId))
            ->orderBy('order_num')
            ->pluck('item_key')
            ->map(fn ($k) => (string) $k)
            ->all();

        $initialInventoryChecks = [];
        foreach ($inventoryKeys as $key) {
            $initialInventoryChecks[$key] = (bool) $order->intakeInventory->firstWhere('item_key', $key)?->present;
        }

        $editingVehicleLabel = trim(((string) ($order->vehicle?->brand ?? '')) . ' ' . ((string) ($order->vehicle?->model ?? '')) . (((string) ($order->vehicle?->plate ?? '') !== '' ? ' - ' . $order->vehicle->plate : '')));
        $editingClientLabel = trim(((string) ($order->client?->first_name ?? '')) . ' ' . ((string) ($order->client?->last_name ?? '')));

        $sidesOrder = ['RIGHT', 'LEFT', 'FRONT', 'BACK'];
        $editingDamageRows = [];
        $editingDamagePhotoPreviews = [0 => [], 1 => [], 2 => [], 3 => []];
        foreach ($sidesOrder as $idx => $side) {
            $d = $order->damages->first(fn ($x) => strtoupper((string) $x->side) === $side);
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
            'editingVehicleLabel' => $editingVehicleLabel,
            'editingClientLabel' => $editingClientLabel,
            'editingDamageRows' => $editingDamageRows,
            'editingDamagePhotoPreviews' => $editingDamagePhotoPreviews,
            'editingSignatureUrl' => $editingSignatureUrl,
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
            ->when($saleMovementTypeId > 0, fn ($query) => $query->where('movement_type_id', $saleMovementTypeId))
            ->orderBy('name')
            ->get(['id', 'name', 'stock']);

        $cashRegisters = CashRegister::query()
            ->when(
                Schema::hasColumn('cash_registers', 'branch_id') && $branchId > 0,
                fn ($query) => $query->where('branch_id', $branchId)
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
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
            ])
            ->values();

        $inventoryItemsByVehicleType = WorkshopVehicleIntakeInventoryItem::query()
            ->whereNull('deleted_at')
            ->orderBy('order_num')
            ->get(['vehicle_type_id', 'item_key', 'label'])
            ->groupBy('vehicle_type_id')
            ->map(fn ($items) => $items->values()->map(fn ($i) => [
                'item_key' => (string) $i->item_key,
                'label' => (string) $i->label,
            ])->values()->all())
            ->toArray();

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
            ->map(fn ($row) => [
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

        $showInventoryDefault = true;
        $showInventoryBaseParameter = DB::table('parameters')
            ->where('description', 'Mostrar inventario')
            ->first();

        if ($showInventoryBaseParameter) {
            $showInventoryBranchValue = DB::table('branch_parameters')
                ->where('parameter_id', $showInventoryBaseParameter->id)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->value('value');

            $rawValue = $showInventoryBranchValue ?? $showInventoryBaseParameter->value;
            $normalized = strtoupper(trim((string) $rawValue));
            $showInventoryDefault = in_array($normalized, ['1', 'TRUE', 'YES', 'SI', 'S'], true);
        }

        $showDamagesPreexistingDefault = true;
        $showDamagesPreexistingBaseParameter = DB::table('parameters')
            ->where('description', 'Mostrar daÃ±os preexistentes')
            ->first();

        if ($showDamagesPreexistingBaseParameter) {
            $showDamagesPreexistingBranchValue = DB::table('branch_parameters')
                ->where('parameter_id', $showDamagesPreexistingBaseParameter->id)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->value('value');

            $rawValue = $showDamagesPreexistingBranchValue ?? $showDamagesPreexistingBaseParameter->value;
            $normalized = strtoupper(trim((string) $rawValue));
            $showDamagesPreexistingDefault = in_array($normalized, ['1', 'TRUE', 'YES', 'SI', 'S'], true);
        }

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
            'selectedDepartmentName',
            'selectedProvinceName',
            'selectedDistrictName',
            'technicians',
            'inventoryItemsByVehicleType',
            'showInventoryDefault',
            'showDamagesPreexistingDefault'
        );
    }
            

    public function store(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        [$branchId, $companyId] = $this->branchScope();
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'client_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'tow_in' => ['nullable', 'boolean'],
            'diagnosis_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.*' => ['nullable', 'boolean'],
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
            'service_lines.*.unit_price' => ['nullable', 'numeric'],
            'service_lines.*.validity_months' => ['nullable', 'in:6,12'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.detail_id' => ['nullable', 'integer'],
            'product_lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['nullable', 'numeric', 'gte:0'],
            'product_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ]);

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

        $user = auth()->user();
        try {
            $damagesWithPhotos = $this->uploadDamagePhotos($request, (array) ($validated['damages'] ?? []), $branchId);
            $signaturePath = $this->storeSignatureFromDataUri((string) ($validated['client_signature_data'] ?? ''), $branchId);

            $workshop = $this->flowService->createOrder([
                'vehicle_id' => (int) $validated['vehicle_id'],
                'client_person_id' => $clientPersonId,
                'appointment_id' => !empty($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
                'intake_date' => now()->format('Y-m-d H:i:s'),
                'mileage_in' => $validated['mileage_in'] ?? null,
                'tow_in' => (bool) ($validated['tow_in'] ?? false),
                'diagnosis_text' => $validated['diagnosis_text'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'status' => 'awaiting_approval',
                'comment' => 'OS creada desde tablero y enviada a espera de aprobación',
            ], $branchId, (int) $user?->id, (string) ($user?->name ?? 'Sistema'));

            $this->flowService->syncIntakeAndDamages(
                $workshop,
                (array) ($validated['inventory'] ?? []),
                $damagesWithPhotos,
                [
                    'intake_client_signature_path' => $signaturePath,
                ]
            );

            $catalogIds = collect($parsedServiceLines)
                ->where('kind', 'catalog')
                ->pluck('service_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
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

            foreach ($parsedServiceLines as $line) {
                if ($line['kind'] === 'glosa') {
                    $this->flowService->addDetail($workshop, [
                        'line_type' => 'SERVICE',
                        'service_id' => null,
                        'description' => $line['description'],
                        'qty' => $line['qty'],
                        'unit_price' => $line['unit_price'],
                    ]);
                    continue;
                }

                $serviceId = (int) $line['service_id'];
                $qty = $line['qty'];
                $service = $serviceCatalog->get($serviceId);
                if (!$service || $qty <= 0) {
                    continue;
                }

                $resolvedPrice = $this->resolveMaintenanceBoardServiceUnitPrice($service, $vehicle, $mileageForFrequency);
                $unitPrice = round((float) ($line['unit_price'] ?? $resolvedPrice), 6);
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
                ]);
            }

            $productLines = collect($validated['product_lines'] ?? [])
                ->filter(fn ($line) => !empty($line['product_id']))
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
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        session()->flash('status', 'Servicio registrado y enviado a espera de aprobacion.');

        return response()->view('workshop.maintenance-board.store_redirect', [
            'reportUrl' => route('workshop.pdf.order', $workshop),
            'redirectUrl' => route('workshop.maintenance-board.index', array_filter([
                'view_id' => $request->query('view_id'),
            ])),
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
            'tow_in' => ['nullable', 'boolean'],
            'diagnosis_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.*' => ['nullable', 'boolean'],
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
            'service_lines.*.unit_price' => ['nullable', 'numeric'],
            'service_lines.*.validity_months' => ['nullable', 'in:6,12'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.detail_id' => ['nullable', 'integer'],
            'product_lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['nullable', 'numeric', 'gte:0'],
            'product_lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ]);

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

        $user = auth()->user();

        try {
            DB::transaction(function () use ($order, $request, $validated, $branchId, $vehicle, $clientPersonId, $parsedServiceLines, $user) {
                $lockedOrder = WorkshopMovement::query()
                    ->where('id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->flowService->updateOrder($lockedOrder, [
                    'vehicle_id' => (int) $validated['vehicle_id'],
                    'client_person_id' => $clientPersonId,
                    'mileage_in' => $validated['mileage_in'] ?? null,
                    'tow_in' => (bool) ($validated['tow_in'] ?? false),
                    'diagnosis_text' => $validated['diagnosis_text'] ?? null,
                    'observations' => $validated['observations'] ?? null,
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

                $lockedOrder->refresh();

                $catalogIds = collect($parsedServiceLines)
                    ->where('kind', 'catalog')
                    ->pluck('service_id')
                    ->unique()
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
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
                    ->filter(fn ($id) => (int) $id > 0)
                    ->map(fn ($id) => (int) $id)
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
                        if ($detailId > 0 && $detailsById->has($detailId)) {
                            $this->flowService->updateDetail(
                                $detailsById->get($detailId),
                                [
                                    'qty' => $qty,
                                    'unit_price' => $line['unit_price'],
                                    'description' => $line['description'],
                                    'validity_months' => $line['validity_months'] ?? null,
                                ],
                                $branchId,
                                (int) ($user?->id ?? 0),
                                (string) ($user?->name ?? 'Sistema')
                            );
                        } else {
                            $this->flowService->addDetail($lockedOrder, [
                                'line_type' => 'SERVICE',
                                'service_id' => null,
                                'description' => $line['description'],
                                'qty' => $qty,
                                'unit_price' => $line['unit_price'],
                                'validity_months' => $line['validity_months'] ?? null,
                            ]);
                        }
                        continue;
                    }

                    $serviceId = (int) $line['service_id'];
                    $service = $serviceCatalog->get($serviceId);
                    if (!$service || $qty <= 0) {
                        continue;
                    }

                    $resolvedPrice = $this->resolveMaintenanceBoardServiceUnitPrice($service, $vehicle, $mileageForFrequency);

                    if ($detailId > 0 && !$detailsById->has($detailId)) {
                        throw new \RuntimeException('Linea de servicio no valida para esta OS.');
                    }

                    if ($detailId > 0 && $detailsById->has($detailId)) {
                        $this->flowService->updateDetail(
                            $detailsById->get($detailId),
                            [
                                'qty' => $qty,
                                'unit_price' => round((float) ($line['unit_price'] ?? $resolvedPrice), 6),
                                'validity_months' => $line['validity_months'] ?? null,
                            ],
                            $branchId,
                            (int) ($user?->id ?? 0),
                            (string) ($user?->name ?? 'Sistema')
                        );
                    } else {
                        $unitPrice = round((float) ($line['unit_price'] ?? $resolvedPrice), 6);
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
                        ]);
                    }
                }

                $lockedOrder->refresh();

                $productLines = collect($validated['product_lines'] ?? [])
                    ->filter(fn ($line) => !empty($line['product_id']))
                    ->values();

                $partDetails = $lockedOrder->details()
                    ->where('line_type', 'PART')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                $submittedPartIds = $productLines->pluck('detail_id')->filter()->map(fn ($id) => (int) $id)->all();
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
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('workshop.maintenance-board.index')
            ->with('status', 'Orden de servicio actualizada.');
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

                $editableStatuses = ['awaiting_approval', 'approved', 'in_progress', 'paused'];
                if (!in_array((string) $lockedOrder->status, $editableStatuses, true)) {
                    throw new \RuntimeException('La cotizacion solo se puede editar en espera de aprobacion, aprobado, en reparacion o pausado.');
                }

                $detailsById = $lockedOrder->details()
                    ->whereIn('line_type', ['SERVICE', 'PART'])
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                $submittedDetailIds = collect((array) $validated['quote_lines'])->pluck('detail_id')->map(fn ($id) => (int) $id)->all();
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
                        'observations' => trim(((string) $lockedOrder->observations . "\n" . '[Cotizacion] ' . (string) $validated['quote_note'])),
                    ]);
                }

                // Al aprobar cotizaciÃ³n desde tablero, la OS pasa a estado aprobado.
                if ((string) $lockedOrder->status === 'awaiting_approval') {
                    $this->flowService->updateOrder($lockedOrder, [
                        'status' => 'approved',
                        'comment' => 'Cotizacion aprobada desde tablero',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'CotizaciÃ³n aprobada correctamente.');
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
                Rule::exists('people', 'id')->where(fn ($query) => $query->where('branch_id', $branchId)),
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
                Rule::unique('vehicles', 'plate')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'vin' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('vehicles', 'vin')->where(fn ($query) => $query->where('company_id', $companyId)),
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
            'label' => trim($vehicle->brand . ' ' . $vehicle->model . ' ' . ($vehicle->plate ? ('- ' . $vehicle->plate) : '')),
            'km' => (int) ($vehicle->current_mileage ?? 0),
            'engine_displacement_cc' => $vehicle->engine_displacement_cc ? (int) $vehicle->engine_displacement_cc : null,
            'soat_vencimiento' => $vehicle->soat_vencimiento ? $vehicle->soat_vencimiento->format('Y-m-d') : null,
            'revision_tecnica_vencimiento' => $vehicle->revision_tecnica_vencimiento ? $vehicle->revision_tecnica_vencimiento->format('Y-m-d') : null,
        ]);
    }

    public function lookupVehicleByPlate(Request $request): JsonResponse
    {
        $rawPlate = strtoupper(trim((string) $request->query('plate', '')));
        $plate = preg_replace('/[^A-Z0-9]/', '', $rawPlate) ?? '';
        if (strlen($plate) < 5) {
            return response()->json([
                'status' => false,
                'message' => 'Ingrese una placa valida.',
            ], 422);
        }

        $enabled = filter_var((string) config('vehicle_lookup.enabled', ''), FILTER_VALIDATE_BOOLEAN);
        $url = trim((string) config('vehicle_lookup.url', ''));
        $token = trim((string) config('vehicle_lookup.token', ''));
        $timeout = (int) config('vehicle_lookup.timeout', 15);
        $driver = trim((string) config('vehicle_lookup.driver', 'json_pe'));

        if (!$enabled || $url === '') {
            return response()->json([
                'status' => false,
                'message' => 'Consulta por placa no configurada. Define VEHICLE_PLATE_LOOKUP_* en .env.',
            ], 422);
        }

        if ($driver === 'json_pe') {
            if ($token === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Falta VEHICLE_PLATE_LOOKUP_TOKEN en .env (Bearer de json.pe).',
                ], 422);
            }

            $bodyKey = trim((string) config('vehicle_lookup.body_plate_key', 'placa'));
            if ($bodyKey === '') {
                $bodyKey = 'placa';
            }

            try {
                $response = Http::timeout($timeout > 0 ? $timeout : 15)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url, [$bodyKey => strtolower($plate)]);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'No se pudo conectar al proveedor json.pe.',
                ], 422);
            }

            $payload = (array) $response->json();
            $apiMessage = trim((string) ($payload['message'] ?? ''));

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => $apiMessage !== '' ? $apiMessage : 'El proveedor no pudo resolver la placa.',
                ], 422);
            }

            $ok = (bool) ($payload['success'] ?? false);
            if (!$ok) {
                return response()->json([
                    'status' => false,
                    'message' => $apiMessage !== '' ? $apiMessage : 'No se encontro informacion para esa placa.',
                ], 422);
            }

            $data = (array) ($payload['data'] ?? []);
            $brand = trim((string) ($data['marca'] ?? ''));
            $model = trim((string) ($data['modelo'] ?? ''));
            $color = trim((string) ($data['color'] ?? ''));
            $vin = trim((string) ($data['vin'] ?? ''));
            $engineNumber = trim((string) ($data['motor'] ?? ''));
            $serie = trim((string) ($data['serie'] ?? ''));
            $serialNumber = $serie;
            $chassisNumber = ($serie !== '' && $serie !== $vin) ? $serie : '';

            if ($brand === '' && $model === '' && $color === '' && $vin === '' && $engineNumber === '' && $serie === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontraron datos vehiculares para esa placa.',
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => $apiMessage !== '' ? $apiMessage : 'Datos de placa encontrados.',
                'plate' => strtoupper((string) ($data['placa'] ?? $plate)),
                'brand' => $brand,
                'model' => $model,
                'year' => '',
                'color' => $color,
                'vin' => $vin,
                'engine_number' => $engineNumber,
                'chassis_number' => $chassisNumber,
                'serial_number' => $serialNumber,
                'raw' => $data,
            ]);
        }

        $plateKey = trim((string) config('vehicle_lookup.query_plate_key', 'numero'));
        $tokenKey = trim((string) config('vehicle_lookup.query_token_key', 'token'));

        $query = [$plateKey !== '' ? $plateKey : 'numero' => $plate];
        if ($token !== '') {
            $query[$tokenKey !== '' ? $tokenKey : 'token'] = $token;
        }

        try {
            $response = Http::timeout($timeout > 0 ? $timeout : 15)->get($url, $query);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo conectar al proveedor de consulta vehicular.',
            ], 422);
        }

        if (!$response->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'El proveedor no pudo resolver la placa.',
            ], 422);
        }

        $payload = (array) $response->json();
        $source = (array) ($payload['resultado'] ?? $payload['result'] ?? $payload['data'] ?? $payload['vehicle'] ?? $payload);

        $brand = $this->plateLookupValue($source, ['marca', 'brand', 'vehicle.brand', 'data.marca']);
        $model = $this->plateLookupValue($source, ['modelo', 'model', 'vehicle.model', 'data.modelo']);
        $year = $this->plateLookupValue($source, ['anio', 'año', 'year', 'fabricacion', 'vehicle.year', 'data.anio']);
        $color = $this->plateLookupValue($source, ['color', 'vehicle.color', 'data.color']);
        $vin = $this->plateLookupValue($source, ['vin', 'nro_vin', 'numero_vin', 'vehicle.vin', 'data.vin']);
        $engineNumber = $this->plateLookupValue($source, ['motor', 'numero_motor', 'nro_motor', 'engine_number', 'vehicle.engine_number', 'data.motor']);
        $chassisNumber = $this->plateLookupValue($source, ['chasis', 'chassis', 'numero_chasis', 'nro_chasis', 'vehicle.chassis_number', 'data.chasis']);
        $serialNumber = $this->plateLookupValue($source, ['serie', 'serial', 'serial_number', 'vehicle.serial_number', 'data.serie']);

        if (
            $brand === ''
            && $model === ''
            && $year === ''
            && $color === ''
            && $vin === ''
            && $engineNumber === ''
            && $chassisNumber === ''
            && $serialNumber === ''
        ) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontraron datos vehiculares para esa placa.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Datos de placa encontrados.',
            'plate' => $plate,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'color' => $color,
            'vin' => $vin,
            'engine_number' => $engineNumber,
            'chassis_number' => $chassisNumber,
            'serial_number' => $serialNumber,
            'raw' => $source,
        ]);
    }

    public function storeClientQuick(Request $request): JsonResponse
    {
        [$branchId, $companyId] = $this->branchScope();
        $branch = Branch::query()->findOrFail($branchId);

        $validated = $request->validate([
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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
        $validated['address'] = trim((string) ($validated['address'] ?? '')) ?: '-';
        $validated['location_id'] = $branchDistrictId;

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
            'technician_person_id' => ['required', 'integer', 'exists:people,id'],
        ]);

        try {
            DB::transaction(function () use ($order, $validated) {
                // Assign technician
                WorkshopMovementTechnician::query()->updateOrCreate(
                    [
                        'workshop_movement_id' => $order->id,
                        'technician_person_id' => $validated['technician_person_id'],
                    ],
                    [
                        'commission_percentage' => 0,
                        'commission_amount' => 0,
                    ]
                );

                $this->flowService->updateOrder($order, [
                    'status' => 'in_progress',
                    'comment' => 'Inicio de mantenimiento desde tablero',
                ]);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Servicio iniciado.');
    }

    public function finish(WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        try {
            $this->flowService->updateOrder($order, [
                'status' => 'finished',
                'comment' => 'Finalizacion desde tablero',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Servicio finalizado. Puede continuar con cobro y entrega.');
    }

    public function pause(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'pause_comment' => ['required', 'string', 'max:500'],
        ]);

        try {
            if ((string) $order->status !== 'in_progress') {
                throw new \RuntimeException('Solo se pueden pausar servicios en reparaciÃ³n.');
            }

            $this->flowService->updateOrder($order, [
                'status' => 'paused',
                'paused_at' => now(),
                'comment' => '[PAUSA] ' . $validated['pause_comment'],
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Servicio pausado correctamente.');
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
                'comment' => 'ReanudaciÃ³n de servicio desde tablero',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Servicio reanudado.');
    }

    public function checkoutPage(WorkshopMovement $order): \Illuminate\View\View|RedirectResponse
    {
        $this->assertOrderScope($order);

        if ((string) $order->status !== 'finished') {
            return redirect()
                ->route('workshop.maintenance-board.index')
                ->withErrors(['error' => 'La venta y cobro solo esta disponible para OS terminadas.']);
        }

        [$branchId, $companyId] = $this->branchScope();
        $formData = $this->maintenanceFormData($branchId, $companyId);

        $order->load([
            'movement',
            'vehicle',
            'client',
            'details' => fn ($query) => $query
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
            ->map(fn ($card) => [
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
            'pendingLines' => $pendingLines,
            'totalOs' => (float) $order->total,
            'paidOs' => (float) $order->paid_total,
            'pendingOs' => max(0, (float) $order->total - (float) $order->paid_total),
            'paymentMethodOptions' => $paymentMethodOptions,
            'cardOptions' => $cardOptions,
            'digitalWalletOptions' => $digitalWalletOptions,
            'paymentGatewayOptionsByMethod' => $paymentGatewayOptionsByMethod,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'defaultCashRegisterId' => $defaultCashRegisterId,
            'standardCashRegisterId' => $standardCashRegisterId,
            'invoiceCashRegisterId' => $invoiceCashRegisterId,
        ]));
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
        ]);

        $paymentType = strtoupper((string) ($validated['payment_type'] ?? 'CONTADO'));
        $isDebtCheckout = $paymentType === 'DEUDA';
        $validated['payment_methods'] = collect($validated['payment_methods'] ?? [])
            ->filter(fn ($row) => !empty($row['payment_method_id']) && (float) ($row['amount'] ?? 0) > 0)
            ->values()
            ->all();

        $orderForPaymentRule = WorkshopMovement::query()->findOrFail($order->id);
        $amountPendingToCollect = max(0, (float) $orderForPaymentRule->total - (float) $orderForPaymentRule->paid_total);

        if (!$isDebtCheckout && empty($validated['payment_methods']) && $amountPendingToCollect > 0.000001) {
            throw ValidationException::withMessages([
                'payment_methods' => 'Debes registrar al menos un metodo de pago cuando el cobro es al contado.',
            ]);
        }

        $methodIds = collect($validated['payment_methods'] ?? [])
            ->pluck('payment_method_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
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
            ->map(fn ($rows) => collect($rows)->pluck('payment_gateway_id')->map(fn ($value) => (int) $value)->all());

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
                            "payment_methods.{$index}.payment_gateway_id" => 'La pasarela no corresponde al mÃ©todo de pago seleccionado.',
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

        try {
            DB::transaction(function () use ($order, $validated, $branchId, $user, $paymentType, $isDebtCheckout) {
                $lockedOrder = WorkshopMovement::query()
                    ->where('id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((string) $lockedOrder->status !== 'finished') {
                    throw new \RuntimeException('Solo se puede registrar venta y cobro cuando la OS esta terminada.');
                }

                $productLines = collect($validated['product_lines'] ?? [])
                    ->filter(fn ($line) => !empty($line['product_id']))
                    ->values();

                if ($productLines->isNotEmpty()) {
                    $productBranchIndex = ProductBranch::query()
                        ->where('branch_id', $branchId)
                        ->whereIn('product_id', $productLines->pluck('product_id')->map(fn ($v) => (int) $v)->all())
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

                $pendingLines = (int) $lockedOrder->details()
                    ->whereNull('sales_movement_id')
                    ->count();

                // Mantener la misma lÃ³gica operativa del mÃ³dulo de ventas:
                // si aÃºn no existe venta asociada o hay lÃ­neas pendientes, primero se factura.
                $mustGenerateSale = (bool) ($validated['generate_sale'] ?? false);
                if ((int) ($lockedOrder->sales_movement_id ?? 0) <= 0 || $pendingLines > 0) {
                    $mustGenerateSale = true;
                }

                if ($mustGenerateSale) {
                    if (empty($validated['document_type_id'])) {
                        throw new \RuntimeException('Debe seleccionar tipo de documento para generar la venta.');
                    }

                    $this->flowService->generateSale(
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

                $freshOrder = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                $freshOrder->salesMovement?->update([
                    'payment_type' => $isDebtCheckout ? 'CREDITO' : 'CONTADO',
                ]);

                $this->flowService->registerPayment(
                    $freshOrder,
                    (int) $validated['cash_register_id'],
                    $validated['payment_methods'],
                    $branchId,
                    (int) $user?->id,
                    (string) ($user?->name ?? 'Sistema'),
                    $validated['payment_comment'] ?? ($validated['sale_comment'] ?? null),
                    $paymentType
                );

                $afterPayment = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                $this->flowService->updateOrder($afterPayment, [
                    'status' => 'delivered',
                    'delivery_date' => now()->format('Y-m-d H:i:s'),
                    'comment' => 'Entrega automatica al registrar venta y cobro desde tablero',
                ]);
            });
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('workshop.maintenance-board.index')
            ->with('status', 'Venta y cobro registrados. La OS fue entregada automaticamente.');
    }

    private function mergeExistingWorkshopDamagePhotos(WorkshopMovement $order, array $damagesWithPhotos): array
    {
        $existing = WorkshopPreexistingDamage::query()
            ->where('workshop_movement_id', $order->id)
            ->with('photos')
            ->get()
            ->keyBy(fn ($d) => strtoupper((string) $d->side));

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
            $paths = $row->photos->pluck('photo_path')->filter()->map(fn ($p) => (string) $p)->values()->all();
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

    private function resolveMaintenanceBoardServiceUnitPrice(WorkshopService $service, Vehicle $vehicle, int $mileageForFrequency): float
    {
        $unitPrice = round(
            (float) $service->resolvePriceForDisplacement((int) ($vehicle->engine_displacement_cc ?? 0)),
            6
        );
        if ($unitPrice < 0) {
            $unitPrice = 0;
        }

        if ((bool) ($service->frequency_enabled ?? false) && $mileageForFrequency > 0) {
            $validFrequencies = $service->frequencies
                ->filter(fn ($f) => (int) ($f->km ?? 0) > 0 && ((int) $mileageForFrequency % (int) $f->km) === 0)
                ->sortByDesc(fn ($f) => (int) $f->km)
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
            fn ($p) => $p !== null && $p !== ''
        ));

        return $parts === [] ? '' : implode(' - ', $parts);
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
     * @return array<int, array{kind: string, detail_id: int, service_id: ?int, description: string, qty: float, unit_price: float}>
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
                    'validity_months' => $validityMonths,
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
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $id > 0)
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
            ->map(fn ($value) => (int) $value)
            ->all();

        foreach ($ids as $id) {
            if (!in_array((int) $id, $allowed, true)) {
                throw ValidationException::withMessages([
                    'service_lines' => 'Hay servicios de catalogo no permitidos para esta sucursal o empresa.',
                ]);
            }
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
            $existsInSaleDocs = $documentTypes->contains(fn ($d) => (int) $d->id === $configuredId);
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
            $exists = $cashRegisters->contains(fn ($cashRegister) => (int) ($cashRegister->id ?? 0) === $configuredId);
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

    private function plateLookupValue(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);
            $text = trim((string) ($value ?? ''));
            if ($text !== '' && $text !== '-' && strtoupper($text) !== 'NULL') {
                return $text;
            }
        }

        return '';
    }

    private function inferPaymentMethodKind(string $description): string
    {
        $normalized = mb_strtolower(trim($description), 'UTF-8');

        if (
            str_contains($normalized, 'tarjeta')
            || str_contains($normalized, 'card')
            || str_contains($normalized, 'credito')
            || str_contains($normalized, 'dÃ©bito')
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
}

