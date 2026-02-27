<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\ProductBranch;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkshopMovement;
use App\Models\WorkshopService;
use App\Services\Workshop\WorkshopFlowService;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
                    ->where('line_type', 'SERVICE')
                    ->whereNull('deleted_at')
                    ->orderBy('id'),
                'details.service:id,name,type,base_price',
            ])
            ->withCount([
                'details as pending_billing_count' => fn ($query) => $query->whereNull('sales_movement_id'),
            ])
            ->withSum([
                'details as pending_billing_total' => fn ($query) => $query->whereNull('sales_movement_id'),
            ], 'total')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_strtolower($search, 'UTF-8');
                $query->where(function ($inner) use ($needle) {
                    $inner->whereHas('client', function ($clientQuery) use ($needle) {
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
            ->when(
                $selectedStatus !== 'all',
                fn ($query) => $query->where('status', $selectedStatus),
                fn ($query) => $query->whereNotIn('status', ['delivered', 'cancelled'])
            )
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'approved' THEN 2 WHEN 'awaiting_approval' THEN 3 WHEN 'diagnosis' THEN 4 ELSE 5 END")
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

    public function create(): \Illuminate\View\View
    {
        [$branchId, $companyId] = $this->branchScope();
        $formData = $this->maintenanceFormData($branchId, $companyId);

        return view('workshop.maintenance-board.create', $formData);
    }

    private function maintenanceFormData(int $branchId, int $companyId): array
    {
        $branch = Branch::query()->findOrFail($branchId);
        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->orderBy('brand')
            ->orderBy('model')
            ->get(['id', 'client_person_id', 'brand', 'model', 'plate', 'current_mileage']);

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
            ->where('active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'base_price', 'type']);

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
                'products.description',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->product_id,
                'code' => (string) ($row->code ?? ''),
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
            'selectedDistrictName'
        );
    }
            

    public function store(Request $request): RedirectResponse
    {
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
            'service_lines.*.service_id' => ['required_with:service_lines', 'integer', 'exists:workshop_services,id'],
            'service_lines.*.qty' => ['required_with:service_lines', 'numeric', 'gt:0'],
            'service_lines.*.unit_price' => ['required_with:service_lines', 'numeric', 'gte:0'],
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

        $serviceLines = collect($validated['service_lines'] ?? [])
            ->filter(fn ($line) => !empty($line['service_id']))
            ->values();

        if ($serviceLines->isNotEmpty()) {
            $allowedServiceIds = WorkshopService::query()
                ->where('active', true)
                ->whereIn('id', $serviceLines->pluck('service_id')->map(fn ($value) => (int) $value)->all())
                ->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                })
                ->where(function ($query) use ($branchId) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->all();

            foreach ($serviceLines as $line) {
                if (!in_array((int) $line['service_id'], $allowedServiceIds, true)) {
                    return back()->withErrors(['error' => 'Hay servicios no permitidos para esta sucursal/empresa.']);
                }
            }
        }

        $user = auth()->user();
        try {
            $damagesWithPhotos = $this->uploadDamagePhotos($request, (array) ($validated['damages'] ?? []), $branchId);
            $signaturePath = $this->storeSignatureFromDataUri((string) ($validated['client_signature_data'] ?? ''), $branchId);

            $workshop = $this->flowService->createOrder([
                'vehicle_id' => (int) $validated['vehicle_id'],
                'client_person_id' => $clientPersonId,
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

            $serviceCatalog = WorkshopService::query()
                ->whereIn('id', $serviceLines->pluck('service_id')->map(fn ($value) => (int) $value)->all())
                ->get()
                ->keyBy('id');

            foreach ($serviceLines as $line) {
                $serviceId = (int) $line['service_id'];
                $qty = round((float) $line['qty'], 6);
                $unitPrice = round((float) $line['unit_price'], 6);
                $service = $serviceCatalog->get($serviceId);
                if (!$service || $qty <= 0 || $unitPrice < 0) {
                    continue;
                }

                $this->flowService->addDetail($workshop, [
                    'line_type' => 'SERVICE',
                    'service_id' => $serviceId,
                    'description' => (string) $service->name,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                ]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('workshop.maintenance-board.index')
            ->with('status', 'Servicio registrado y enviado a espera de aprobación.');
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

                $editableStatuses = ['awaiting_approval', 'approved', 'in_progress'];
                if (!in_array((string) $lockedOrder->status, $editableStatuses, true)) {
                    throw new \RuntimeException('La cotizacion solo se puede editar en espera de aprobacion, aprobado o en reparacion.');
                }

                $detailsById = $lockedOrder->details()
                    ->where('line_type', 'SERVICE')
                    ->whereNull('sales_movement_id')
                    ->get()
                    ->keyBy('id');

                foreach ((array) $validated['quote_lines'] as $line) {
                    $detailId = (int) $line['detail_id'];
                    $detail = $detailsById->get($detailId);
                    if (!$detail) {
                        throw new \RuntimeException('Uno de los servicios ya no esta disponible para cotizacion.');
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

                // Al aprobar cotización desde tablero, la OS pasa a estado aprobado.
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

        return back()->with('status', 'Cotización aprobada correctamente.');
    }

    public function storeVehicleQuick(Request $request): JsonResponse
    {
        [$branchId, $companyId] = $this->branchScope();

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
        ]));

        return response()->json([
            'id' => $vehicle->id,
            'client_person_id' => (int) $vehicle->client_person_id,
            'label' => trim($vehicle->brand . ' ' . $vehicle->model . ' ' . ($vehicle->plate ? ('- ' . $vehicle->plate) : '')),
            'km' => (int) ($vehicle->current_mileage ?? 0),
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

    public function start(WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        try {
            $this->flowService->updateOrder($order, [
                'status' => 'in_progress',
                'comment' => 'Inicio de mantenimiento desde tablero',
            ]);
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

        return view('workshop.maintenance-board.checkout', array_merge($formData, [
            'order' => $order,
            'pendingLines' => $pendingLines,
            'totalOs' => (float) $order->total,
            'paidOs' => (float) $order->paid_total,
            'pendingOs' => max(0, (float) $order->total - (float) $order->paid_total),
        ]));
    }

    public function checkout(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);

        $validated = $request->validate([
            'generate_sale' => ['nullable', 'boolean'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'sale_comment' => ['nullable', 'string'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_methods.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payment_methods.*.reference' => ['nullable', 'string', 'max:100'],
            'payment_methods.*.payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'payment_methods.*.card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'payment_methods.*.bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'payment_methods.*.digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'payment_comment' => ['nullable', 'string'],
            'product_lines' => ['nullable', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'exists:products,id'],
            'product_lines.*.qty' => ['required_with:product_lines', 'numeric', 'gt:0'],
            'product_lines.*.unit_price' => ['required_with:product_lines', 'numeric', 'gte:0'],
        ]);

        $user = auth()->user();
        $branchId = (int) session('branch_id');

        try {
            DB::transaction(function () use ($order, $validated, $branchId, $user) {
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
                            'description' => (string) ($product->description ?? ('Producto #' . $productId)),
                            'qty' => (float) $line['qty'],
                            'unit_price' => (float) $line['unit_price'],
                            'tax_rate_id' => $productBranch->tax_rate_id ? (int) $productBranch->tax_rate_id : null,
                        ]);
                    }
                }

                $pendingLines = (int) $lockedOrder->details()
                    ->whereNull('sales_movement_id')
                    ->count();

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

                    $this->flowService->generateSale(
                        $lockedOrder,
                        (int) $validated['document_type_id'],
                        $branchId,
                        (int) $user?->id,
                        (string) ($user?->name ?? 'Sistema'),
                        $validated['sale_comment'] ?? null,
                        null
                    );
                }

                $freshOrder = WorkshopMovement::query()->findOrFail($lockedOrder->id);

                $this->flowService->registerPayment(
                    $freshOrder,
                    (int) $validated['cash_register_id'],
                    $validated['payment_methods'],
                    $branchId,
                    (int) $user?->id,
                    (string) ($user?->name ?? 'Sistema'),
                    $validated['payment_comment'] ?? ($validated['sale_comment'] ?? null)
                );

                $afterPayment = WorkshopMovement::query()->findOrFail($lockedOrder->id);
                $this->flowService->updateOrder($afterPayment, [
                    'status' => 'delivered',
                    'delivery_date' => now()->format('Y-m-d H:i:s'),
                    'comment' => 'Entrega automatica al registrar venta y cobro desde tablero',
                ]);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('workshop.maintenance-board.index')
            ->with('status', 'Venta y cobro registrados. La OS fue entregada automaticamente.');
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
