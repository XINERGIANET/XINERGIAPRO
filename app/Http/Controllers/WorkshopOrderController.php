<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\ConsumeWorkshopPartRequest;
use App\Http\Requests\Workshop\GenerateWorkshopSaleRequest;
use App\Http\Requests\Workshop\RegisterWorkshopPaymentRequest;
use App\Http\Requests\Workshop\RefundWorkshopPaymentRequest;
use App\Http\Requests\Workshop\StoreWorkshopLineRequest;
use App\Http\Requests\Workshop\StoreWorkshopOrderRequest;
use App\Http\Requests\Workshop\UpdateWorkshopLineRequest;
use App\Http\Requests\Workshop\UpdateWorkshopOrderRequest;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\TaxRate;
use App\Models\VehicleType;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\WorkshopMovementTechnician;
use App\Models\Vehicle;
use App\Models\WorkshopService;
use App\Services\Workshop\WorkshopFlowService;
use App\Support\WorkshopAuthorization;
use App\Support\WorkshopOrdersExcelImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class WorkshopOrderController extends Controller
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
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $selectedStatus = $this->normalizeWorkshopOrderListStatus((string) $request->input('status', 'all'));
        $dateFrom = $this->normalizeWorkshopOrderListDate((string) $request->input('date_from', ''));
        $dateTo = $this->normalizeWorkshopOrderListDate((string) $request->input('date_to', ''));

        $orders = $this->workshopOrdersFilteredQuery($branchId, $companyId, $search, $selectedStatus, $dateFrom, $dateTo)
            ->with([
                'movement',
                'vehicle',
                'client' => fn ($query) => $query->withTrashed(),
                'details.service',
                'details.product',
                'details.technician' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('workshop.orders.index', compact('orders', 'search', 'perPage', 'selectedStatus', 'dateFrom', 'dateTo'));
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_mode' => ['required', 'string', Rule::in(['ids', 'filter'])],
            'ids' => ['required_if:bulk_mode,ids', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'view_id' => ['nullable', 'string'],
        ]);

        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        if ($validated['bulk_mode'] === 'filter') {
            $search = trim((string) ($validated['search'] ?? ''));
            $status = $this->normalizeWorkshopOrderListStatus((string) ($validated['status'] ?? 'all'));
            $idList = $this->workshopOrdersFilteredQuery($branchId, $companyId, $search, $status)->pluck('id')->all();
        } else {
            $idList = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));
        }

        if ($idList === []) {
            return back()->with('error', 'No hay ordenes para eliminar.');
        }

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($idList, &$deleted, &$skipped) {
            foreach (array_chunk($idList, 150) as $chunk) {
                $orders = WorkshopMovement::query()->whereIn('id', $chunk)->get();
                foreach ($orders as $order) {
                    if (!$this->orderMatchesSessionScope($order)) {
                        $skipped++;

                        continue;
                    }
                    if ($order->sales_movement_id || $order->cash_movement_id) {
                        $skipped++;

                        continue;
                    }
                    $movement = Movement::query()->find($order->movement_id);
                    $order->delete();
                    if ($movement) {
                        $movement->delete();
                    }
                    $deleted++;
                }
            }
        });

        $q = array_filter(['view_id' => $request->input('view_id')]);
        $msg = "Se eliminaron {$deleted} orden(es) de servicio.";
        if ($skipped > 0) {
            $msg .= " Se omitieron {$skipped} (sin permiso, no encontradas o con venta/pagos vinculados).";
        }

        return redirect()->route('workshop.orders.index', $q)->with('status', $msg);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $viewId = $request->input('view_id');

        $validator = Validator::make($request->all(), [
            'file' => ['required', File::types(['xlsx', 'xls', 'csv'])->max(10240)],
        ]);

        if ($validator->fails()) {
            $msg = (string) ($validator->errors()->first('file') ?: 'Archivo no vÃ¡lido. Usa .xlsx, .xls o .csv (mÃ¡x. 10 MB).');

            return redirect()
                ->route('workshop.orders.index', array_filter(['view_id' => $viewId]))
                ->withErrors($validator)
                ->with('error', $msg);
        }

        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        if ($branchId <= 0 || $companyId <= 0) {
            return redirect()
                ->route('workshop.orders.index', array_filter(['view_id' => $viewId]))
                ->with('error', 'Selecciona una sucursal para importar Ã³rdenes.');
        }

        $uploaded = $request->file('file');
        if (!$uploaded) {
            return redirect()
                ->route('workshop.orders.index', array_filter(['view_id' => $viewId]))
                ->withErrors(['file' => 'No se recibiÃ³ ningÃºn archivo.'])
                ->with('error', 'No se recibiÃ³ ningÃºn archivo.');
        }

        $ext = strtolower((string) $uploaded->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'xlsx';
        }

        $storedRelative = $uploaded->storeAs(
            'temp/workshop-order-imports',
            Str::uuid()->toString() . '.' . $ext,
            'local'
        );

        if ($storedRelative === false) {
            return redirect()
                ->route('workshop.orders.index', array_filter(['view_id' => $viewId]))
                ->withErrors(['file' => 'No se pudo guardar el archivo temporalmente.'])
                ->with('error', 'No se pudo guardar el archivo temporalmente.');
        }

        $fullPath = Storage::disk('local')->path($storedRelative);

        try {
            $rows = WorkshopOrdersExcelImport::extractRows($fullPath);
        } catch (\InvalidArgumentException $e) {
            Storage::disk('local')->delete($storedRelative);

            return redirect()
                ->route('workshop.orders.index', array_filter(['view_id' => $viewId]))
                ->withErrors(['file' => $e->getMessage()])
                ->with('error', $e->getMessage());
        }

        Storage::disk('local')->delete($storedRelative);

        $user = auth()->user();
        $userId = (int) ($user?->id ?? 0);
        $userName = (string) ($user?->name ?? 'Sistema');

        $created = 0;
        $rowErrors = [];

        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row, $branchId, $companyId, $userId, $userName) {
                    $docRaw = trim((string) $row['document']);
                    $clientFullName = trim((string) ($row['client_full_name'] ?? ''));
                    $clientPhone = trim((string) ($row['client_phone'] ?? ''));
                    $clientEmail = trim((string) ($row['client_email'] ?? ''));

                    if ($this->isImportAnonymousDocument($docRaw)) {
                        if ($clientFullName !== '') {
                            $clientPerson = $this->createImportClientPersonDocumentZeroNamed(
                                $clientFullName,
                                $clientPhone,
                                $clientEmail,
                                $branchId,
                                (int) $row['row_index']
                            );
                        } else {
                            $clientPerson = $this->ensureImportGeneralClientPerson($branchId);
                        }
                    } else {
                        $clientPerson = $this->ensureImportClientPersonByDocument($docRaw, $branchId, $companyId);
                    }

                    $vehicle = $this->resolveOrCreateVehicleForOrderImport(
                        $row,
                        $companyId,
                        $branchId,
                        $clientPerson
                    );

                    if (
                        $this->isImportAnonymousDocument($docRaw)
                        && $clientFullName !== ''
                        && (int) $vehicle->client_person_id !== (int) $clientPerson->id
                        && $this->vehicleClientCanBeReplacedFromExcelName($vehicle, $branchId)
                    ) {
                        $vehicle->update([
                            'client_person_id' => (int) $clientPerson->id,
                        ]);
                        $vehicle->refresh();
                    }


                    $glosas = $row['service_descriptions'];
                    if ($glosas === []) {
                        throw new \RuntimeException('OBSERVACIONES sin Ã­tems vÃ¡lidos despuÃ©s de separar por +.');
                    }

                    $intakeDate = $row['intake_date'] ?? now()->toDateString();

                    $orderData = [
                        'vehicle_id' => (int) $vehicle->id,
                        'client_person_id' => (int) $vehicle->client_person_id,
                        'intake_date' => $intakeDate,
                        'mileage_in' => $row['mileage_in'],
                        'observations' => $row['observations'],
                        'status' => 'finished',
                        'comment' => 'OS importada desde Excel',
                    ];

                    $order = $this->flowService->createOrder($orderData, $branchId, $userId, $userName);

                    foreach ($glosas as $desc) {
                        $this->flowService->addDetail($order, [
                            'line_type' => 'SERVICE',
                            'service_id' => null,
                            'product_id' => null,
                            'description' => $desc,
                            'qty' => 1,
                            'unit_price' => 0,
                            'discount_amount' => 0,
                            'tax_rate_id' => null,
                        ]);
                    }

                });
                $created++;
            } catch (\Throwable $e) {
                Log::warning('importExcel OS fila ' . $row['row_index'] . ': ' . $e->getMessage());
                $rowErrors[] = 'Fila ' . $row['row_index'] . ': ' . $e->getMessage();
            }
        }

        $msg = "Importación: {$created} orden(es) creada(s) como terminada(s), con servicios en glosa.";
        if ($rowErrors !== []) {
            $msg .= ' Errores: ' . implode(' | ', array_slice($rowErrors, 0, 8));
            if (count($rowErrors) > 8) {
                $msg .= ' (+' . (count($rowErrors) - 8) . ' mÃ¡s)';
            }
        }

        $redirect = redirect()
            ->route('workshop.orders.index', array_filter(['view_id' => $viewId]));

        if ($created > 0) {
            $redirect->with('status', $msg);
        } else {
            $redirect->with('error', $msg);
        }

        if ($created === 0 && $rowErrors !== []) {
            $redirect->withErrors(['file' => implode("\n", array_slice($rowErrors, 0, 5))]);
        }

        return $redirect;
    }

    private function resolveVehicleForOrderImport(string $plate, int $companyId, int $branchId): ?Vehicle
    {
        return $this->findVehicleByNormalizedPlate($plate, $companyId, $branchId);
    }

    private function findVehicleByNormalizedPlate(string $plate, int $companyId, int $branchId): ?Vehicle
    {
        $norm = $this->normalizePlateString($plate);
        if ($norm === '') {
            return null;
        }

        $candidates = Vehicle::withTrashed()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $vehicle) {
            $p = $this->normalizePlateString((string) $vehicle->plate);
            if ($p === $norm) {
                return $vehicle;
            }
        }

        return null;
    }

    private function normalizePlateString(string $plate): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim($plate)) ?? '');
    }

    private function isPlaceholderImportPlate(string $normalized): bool
    {
        if ($normalized === '') {
            return true;
        }

        $markers = ['-', '--', '.', '..', 'N/A', 'NA', 'S/N', 'SN', 'XXX', 'SINPLACA', '0', '00', 'â€”', 'â€“'];
        foreach ($markers as $m) {
            if ($normalized === strtoupper($m)) {
                return true;
            }
        }

        return false;
    }


    private function resolveOrCreateVehicleForOrderImport(
        array $row,
        int $companyId,
        int $branchId,
        Person $clientPerson
    ): Vehicle {
        $plate = (string) ($row['plate'] ?? '');
        $existing = $this->findVehicleByNormalizedPlate($plate, $companyId, $branchId);
        if ($existing) {
            if (method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }
            $updates = [];
            if ((int) $existing->branch_id !== $branchId) {
                $updates['branch_id'] = $branchId;
            }
            if ((int) $existing->client_person_id !== (int) $clientPerson->id && $this->isImportGeneralClientPerson($existing->client)) {
                $updates['client_person_id'] = $clientPerson->id;
            }
            if ($updates !== []) {
                $existing->update($updates);
                $existing->refresh();
            }

            return $existing;
        }

        $norm = $this->normalizePlateString($plate);
        $finalPlate = $this->isPlaceholderImportPlate($norm) ? null : $norm;

        $vehicleTypeId = 18;
        $vehicleType = VehicleType::query()->find(18);
        $typeName = $vehicleType?->name ? (string) $vehicleType->name : 'moto';

        $brand = trim((string) ($row['brand'] ?? ''));
        if ($brand === '' || $this->isImportDashText($brand)) {
            $brand = '-';
        }
        $model = trim((string) ($row['model'] ?? ''));
        if ($model === '' || $this->isImportDashText($model)) {
            $model = '-';
        }

        $mileage = array_key_exists('mileage_in', $row) && $row['mileage_in'] !== null
            ? (int) $row['mileage_in']
            : 0;
        $mileage = max(0, $mileage);

        $cc = $row['engine_displacement_cc'] ?? null;
        if ($cc !== null) {
            $cc = (int) $cc;
            if ($cc <= 0 || $cc > 5000) {
                $cc = null;
            }
        }

        $color = isset($row['color']) && $row['color'] !== null && $row['color'] !== ''
            ? mb_substr((string) $row['color'], 0, 100)
            : null;


        if ($finalPlate !== null) {
            $plateLessMatch = Vehicle::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('client_person_id', $clientPerson->id)
                ->where(function ($query) {
                    $query->whereNull('plate')
                        ->orWhereRaw("TRIM(COALESCE(plate, '')) = ''");
                })
                ->whereRaw("UPPER(TRIM(COALESCE(brand, ''))) = ?", [mb_strtoupper(trim($brand))])
                ->whereRaw("UPPER(TRIM(COALESCE(model, ''))) = ?", [mb_strtoupper(trim($model))])
                ->orderByDesc('id')
                ->first();

            if ($plateLessMatch) {
                $plateLessMatch->update([
                    'plate' => $finalPlate,
                    'client_person_id' => $clientPerson->id,
                    'branch_id' => $branchId,
                    'vehicle_type_id' => $vehicleTypeId,
                    'type' => mb_substr($typeName, 0, 30),
                    'brand' => mb_substr($brand, 0, 255),
                    'model' => mb_substr($model, 0, 255),
                    'color' => $color,
                    'engine_displacement_cc' => $cc,
                    'current_mileage' => $mileage,
                ]);

                return $plateLessMatch->fresh();
            }
        }
        return Vehicle::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'client_person_id' => $clientPerson->id,
            'vehicle_type_id' => $vehicleTypeId,
            'type' => mb_substr($typeName, 0, 30),
            'brand' => mb_substr($brand, 0, 255),
            'model' => mb_substr($model, 0, 255),
            'color' => $color,
            'plate' => $finalPlate,
            'engine_displacement_cc' => $cc,
            'current_mileage' => $mileage,
            'status' => 'active',
        ]);
    }

    private function isImportDashText(string $value): bool
    {
        $t = strtoupper(trim($value));

        return $t === '-' || $t === 'â€”' || $t === 'â€“' || $t === 'N/A' || $t === 'S/N';
    }

    private function isImportAnonymousDocument(string $doc): bool
    {
        $t = trim($doc);
        if ($t === '') {
            return true;
        }
        $u = strtoupper($t);
        if (in_array($u, ['-', 'N/A', 'S/N', 'SN', 'â€”', 'â€“'], true)) {
            return true;
        }
        if (preg_match('/^\d+$/', $t)) {
            return (int) $t === 0;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitImportPersonFullName(string $full): array
    {
        $full = preg_replace('/\s+/u', ' ', trim($full)) ?? '';
        if ($full === '') {
            return ['Cliente', 'Importacion'];
        }
        $parts = explode(' ', $full);
        if (count($parts) === 1) {
            return [mb_substr($parts[0], 0, 255), '-'];
        }
        $last = array_pop($parts);
        $first = implode(' ', $parts);

        return [mb_substr($first, 0, 255), mb_substr($last, 0, 255)];
    }

    private function normalizeImportPersonName(string $value): string
    {
        return mb_strtoupper(preg_replace('/\\s+/u', ' ', trim($value)) ?? '');
    }

    private function isImportGeneralClientPerson(?Person $person): bool
    {
        if (!$person) {
            return false;
        }

        $document = trim((string) $person->document_number);
        $fullName = $this->normalizeImportPersonName(
            trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''))
        );

        if ($fullName === '') {
            return true;
        }

        return $document === '0' && $fullName === 'CLIENTES VARIOS';
    }

    private function vehicleClientCanBeReplacedFromExcelName(Vehicle $vehicle, int $branchId): bool
    {
        $currentClient = $vehicle->relationLoaded('client')
            ? $vehicle->client
            : Person::query()->find($vehicle->client_person_id);

        return $this->isImportGeneralClientPerson($currentClient);
    }

    /**
     * Cliente con DNI/documento 0 pero nombre propio en Excel: siempre nueva persona (no CLIENTES VARIOS).
     */
    private function createImportClientPersonDocumentZeroNamed(
        string $fullName,
        string $phone,
        string $email,
        int $branchId,
        int $rowIndex
    ): Person {
        $branch = Branch::query()->findOrFail($branchId);
        $districtId = (int) ($branch->location_id ?? 0);
        if ($districtId <= 0) {
            throw new \RuntimeException('La sucursal no tiene distrito configurado; no se puede crear el cliente de la fila Â«' . $fullName . 'Â».');
        }

        [$firstName, $lastName] = $this->splitImportPersonFullName($fullName);

        $normalizedFullName = $this->normalizeImportPersonName($fullName);
        $existing = Person::query()
            ->where('branch_id', $branchId)
            ->whereRaw('TRIM(document_number) = ?', ['0'])
            ->get()
            ->first(function (Person $person) use ($normalizedFullName) {
                $personFullName = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));

                return $this->normalizeImportPersonName($personFullName) === $normalizedFullName;
            });

        if ($existing) {
            DB::table('role_person')->updateOrInsert(
                [
                    'role_id' => 3,
                    'person_id' => $existing->id,
                    'branch_id' => $branchId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $updates = [];
            $phoneNormExisting = trim($phone);
            if (
                $phoneNormExisting !== ''
                && $phoneNormExisting !== '0'
                && strtoupper($phoneNormExisting) !== 'N/A'
                && trim((string) $existing->phone) !== $phoneNormExisting
            ) {
                $updates['phone'] = mb_substr($phoneNormExisting, 0, 50);
            }

            $emailNormExisting = trim($email);
            if (
                $emailNormExisting !== ''
                && filter_var($emailNormExisting, FILTER_VALIDATE_EMAIL)
                && trim((string) $existing->email) !== $emailNormExisting
            ) {
                $updates['email'] = mb_substr($emailNormExisting, 0, 255);
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing->fresh();
        }

        $phoneNorm = trim((string) $phone);
        if ($phoneNorm === '' || $phoneNorm === '0' || strtoupper($phoneNorm) === 'N/A') {
            $phoneNorm = '0';
        }
        $phoneNorm = mb_substr($phoneNorm, 0, 50);

        $emailNorm = trim($email);
        if ($emailNorm === '' || strtoupper($emailNorm) === '0' || !filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
            $emailNorm = 'import.os.f' . $rowIndex . '.b' . $branchId . '.' . substr(sha1((string) microtime(true) . random_bytes(4)), 0, 10) . '@xinergia-import.local';
        } else {
            $emailNorm = mb_substr($emailNorm, 0, 255);
        }

        $person = Person::query()->create([
            'branch_id' => $branchId,
            'document_number' => '0',
            'person_type' => 'DNI',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phoneNorm,
            'email' => $emailNorm,
            'address' => '-',
            'location_id' => $districtId,
        ]);

        DB::table('role_person')->updateOrInsert(
            [
                'role_id' => 3,
                'person_id' => $person->id,
                'branch_id' => $branchId,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $person;
    }

    private function ensureImportGeneralClientPerson(int $branchId): Person
    {
        $branch = Branch::query()->findOrFail($branchId);
        $person = Person::query()->firstOrCreate(
            [
                'branch_id' => $branchId,
                'document_number' => '0',
                'person_type' => 'DNI',
            ],
            [
                'first_name' => 'CLIENTES VARIOS',
                'last_name' => '',
                'phone' => '-',
                'email' => 'clientes.varios.' . $branchId . '@xinergia.local',
                'address' => $branch->address ?: '-',
                'location_id' => $branch->location_id,
            ]
        );

        DB::table('role_person')->updateOrInsert(
            [
                'role_id' => 3,
                'person_id' => $person->id,
                'branch_id' => $branchId,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $person;
    }

    private function ensureImportClientPersonByDocument(string $document, int $branchId, int $companyId): Person
    {
        $doc = trim($document);
        if ($doc === '') {
            return $this->ensureImportGeneralClientPerson($branchId);
        }
        if ($this->isImportAnonymousDocument($doc)) {
            return $this->ensureImportGeneralClientPerson($branchId);
        }

        $person = Person::query()
            ->where('branch_id', $branchId)
            ->whereRaw('TRIM(document_number) = ?', [$doc])
            ->first();

        if ($person) {
            DB::table('role_person')->updateOrInsert(
                [
                    'role_id' => 3,
                    'person_id' => $person->id,
                    'branch_id' => $branchId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return $person;
        }

        return $this->ensureImportGeneralClientPerson($branchId);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('workshop.maintenance-board.create', $request->query());
    }

    public function store(StoreWorkshopOrderRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $branchId = (int) session('branch_id');

        $workshop = $this->flowService->createOrder(
            $request->validated(),
            $branchId,
            (int) $user?->id,
            (string) ($user?->name ?? 'Sistema')
        );

        return redirect()->route('workshop.orders.show', $workshop)
            ->with('status', 'Orden de servicio creada correctamente.');
    }

    public function show(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);

        $order->load([
            'movement',
            'vehicle.logs',
            'client',
            'appointment',
            'details.product',
            'details.service',
            'details.taxRate',
            'details.technician',
            'checklists.items',
            'damages',
            'damages.photos',
            'intakeInventory',
            'sale.details',
            'cash.details',
            'technicians.technician',
            'warranties.detail',
            'statusHistories.user',
            'audits.user',
        ]);

        $branchId = (int) session('branch_id');

        $services = WorkshopService::query()
            ->where('active', true)
            ->where(function ($query) use ($order, $branchId) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $order->company_id);
            })
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->where('classification', 'GOOD')
            ->with('baseUnit')
            ->orderBy('description')
            ->get();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('product_id');

        $taxRates = TaxRate::query()->orderBy('order_num')->get();

        $technicians = Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $documentTypes = DocumentType::query()
            ->whereHas('movementType', fn ($query) => $query->where('description', 'ILIKE', '%venta%'))
            ->orderBy('name')
            ->get();

        $cashRegisters = CashRegister::query()
            ->when(
                Schema::hasColumn('cash_registers', 'branch_id') && $branchId > 0,
                fn ($query) => $query->where('branch_id', $branchId)
            )
            ->orderBy('number')
            ->get();
        $paymentMethods = PaymentMethod::query()->where('status', true)->orderBy('order_num')->get();

        return view('workshop.orders.show', compact(
            'order',
            'services',
            'products',
            'productBranches',
            'taxRates',
            'technicians',
            'documentTypes',
            'cashRegisters',
            'paymentMethods'
        ));
    }

    public function update(UpdateWorkshopOrderRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        try {
            $this->flowService->updateOrder($order, $request->validated());
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Orden de servicio actualizada.');
    }

    public function addDetail(StoreWorkshopLineRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        try {
            $this->flowService->addDetail($order, $request->validated());
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Linea agregada a la OS.');
    }

    public function removeDetail(WorkshopMovement $order, WorkshopMovementDetail $detail): RedirectResponse
    {
        $this->assertOrderScope($order);
        if ((int) $detail->workshop_movement_id !== (int) $order->id) {
            abort(404);
        }

        try {
            $this->flowService->removeDetail($detail);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Linea eliminada.');
    }

    public function updateDetail(UpdateWorkshopLineRequest $request, WorkshopMovement $order, WorkshopMovementDetail $detail): RedirectResponse
    {
        $this->assertOrderScope($order);
        if ((int) $detail->workshop_movement_id !== (int) $order->id) {
            abort(404);
        }

        $user = auth()->user();
        try {
            $this->flowService->updateDetail(
                $detail,
                $request->validated(),
                (int) session('branch_id'),
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Linea actualizada correctamente.');
    }

    public function updateIntake(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $data = $request->validate([
            'inventory' => ['nullable', 'array'],
            'inventory.*' => ['nullable', 'boolean'],
            'damages' => ['nullable', 'array'],
            'damages.*.side' => ['nullable', 'in:RIGHT,LEFT,FRONT,BACK'],
            'damages.*.description' => ['nullable', 'string'],
            'damages.*.severity' => ['nullable', 'in:LOW,MED,HIGH'],
            'damages.*.photo_path' => ['nullable', 'string'],
            'damages.*.photos' => ['nullable', 'array'],
            'damages.*.photos.*' => ['nullable', 'image', 'max:6144'],
            'client_signature_data' => ['nullable', 'string'],
        ]);

        try {
            $branchId = (int) session('branch_id');
            $damagesWithPhotos = $this->uploadDamagePhotos($request, (array) ($data['damages'] ?? []), $branchId);
            $signaturePath = $this->storeSignatureFromDataUri((string) ($data['client_signature_data'] ?? ''), $branchId);

            $this->flowService->syncIntakeAndDamages(
                $order,
                $data['inventory'] ?? [],
                $damagesWithPhotos,
                [
                    'intake_client_signature_path' => $signaturePath,
                ]
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Inspeccion e inventario guardados.');
    }

    public function saveChecklist(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'type' => ['required', 'in:OS_INTAKE,GP_ACTIVATION,PDI,MAINTENANCE'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.group' => ['nullable', 'string', 'max:60'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.result' => ['nullable', 'string', 'max:30'],
            'items.*.action' => ['nullable', 'string', 'max:30'],
            'items.*.observation' => ['nullable', 'string'],
            'items.*.order_num' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $this->flowService->syncChecklist($order, $validated['type'], (int) auth()->id(), $validated['items']);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Checklist guardado.');
    }

    public function approve(WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $decision = strtolower((string) request('decision', 'approved'));
        $note = request('approval_note');
        try {
            $this->flowService->decideApproval($order, $decision, (int) auth()->id(), $note);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Decision de aprobacion registrada.');
    }

    public function generateQuotation(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        try {
            $this->flowService->generateQuotation($order, (int) auth()->id(), $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Cotizacion generada y enviada a aprobacion.');
    }

    public function consumePart(ConsumeWorkshopPartRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $detail = WorkshopMovementDetail::query()->findOrFail((int) $request->validated('detail_id'));
        if ((int) $detail->workshop_movement_id !== (int) $order->id) {
            abort(404);
        }

        $user = auth()->user();
        $action = strtolower((string) $request->validated('action', 'consume'));

        try {
            if ($action === 'reserve') {
                $this->flowService->reservePart(
                    $detail,
                    (int) session('branch_id'),
                    (int) $user?->id
                );
            } elseif ($action === 'release') {
                $this->flowService->releasePartReservation($detail);
            } elseif ($action === 'return') {
                $this->flowService->returnConsumedPart($detail);
            } else {
                $this->flowService->consumePart(
                    $detail,
                    (int) session('branch_id'),
                    (int) $user?->id,
                    (string) ($user?->name ?? 'Sistema'),
                    $request->validated('comment')
                );
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Operacion de stock ejecutada correctamente.');
    }

    public function generateSale(GenerateWorkshopSaleRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $user = auth()->user();

        try {
            $sale = $this->flowService->generateSale(
                $order,
                (int) $request->validated('document_type_id'),
                (int) session('branch_id'),
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema'),
                $request->validated('comment'),
                $request->validated('detail_ids')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Venta generada correctamente. ID venta: ' . $sale->id);
    }

    public function registerPayment(RegisterWorkshopPaymentRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $user = auth()->user();

        try {
            $this->flowService->registerPayment(
                $order,
                (int) $request->validated('cash_register_id'),
                $request->validated('payment_methods'),
                (int) session('branch_id'),
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema'),
                $request->validated('comment')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Pago registrado correctamente.');
    }

    public function deliver(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'mileage_out' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->flowService->markDelivered($order, $validated['mileage_out'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Vehiculo entregado correctamente.');
    }

    public function refundPayment(RefundWorkshopPaymentRequest $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $user = auth()->user();

        try {
            $this->flowService->refundPayment(
                $order,
                (int) $request->validated('cash_register_id'),
                (int) $request->validated('payment_method_id'),
                (float) $request->validated('amount'),
                (int) session('branch_id'),
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema'),
                (string) $request->validated('reason')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Devolucion registrada correctamente.');
    }

    public function destroy(WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        if ($order->sales_movement_id || $order->cash_movement_id) {
            return back()->withErrors(['error' => 'No se puede eliminar una OS con venta o pagos relacionados.']);
        }

        $movement = Movement::query()->find($order->movement_id);
        $order->delete();
        if ($movement) {
            $movement->delete();
        }

        return redirect()->route('workshop.orders.index')->with('status', 'Orden eliminada.');
    }

    public function assignTechnicians(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'technicians' => ['nullable', 'array'],
            'technicians.*.technician_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'technicians.*.commission_percentage' => ['nullable', 'numeric', 'min:0'],
        ]);

        $records = collect($validated['technicians'] ?? [])
            ->map(function ($row) {
                return [
                    'technician_person_id' => (int) ($row['technician_person_id'] ?? 0),
                    'commission_percentage' => round((float) ($row['commission_percentage'] ?? 0), 4),
                ];
            })
            ->filter(fn ($row) => $row['technician_person_id'] > 0)
            ->unique('technician_person_id')
            ->values();

        $deleteQuery = WorkshopMovementTechnician::query()
            ->where('workshop_movement_id', $order->id);
        if ($records->isNotEmpty()) {
            $deleteQuery->whereNotIn('technician_person_id', $records->pluck('technician_person_id')->all());
        }
        $deleteQuery->delete();

        foreach ($records as $record) {
            WorkshopMovementTechnician::query()->updateOrCreate(
                [
                    'workshop_movement_id' => $order->id,
                    'technician_person_id' => $record['technician_person_id'],
                ],
                [
                    'commission_percentage' => $record['commission_percentage'],
                    'commission_amount' => round(((float) $order->total * (float) $record['commission_percentage']) / 100, 6),
                ]
            );
        }

        return back()->with('status', 'Tecnicos asignados correctamente.');
    }

    public function cancel(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'auto_refund' => ['nullable', 'boolean'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);

        try {
            $user = auth()->user();
            $this->flowService->cancelOrder(
                $order,
                (int) auth()->id(),
                $validated['reason'],
                (bool) ($validated['auto_refund'] ?? false),
                isset($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null,
                isset($validated['payment_method_id']) ? (int) $validated['payment_method_id'] : null,
                (int) session('branch_id'),
                (string) ($user?->name ?? 'Sistema')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'OS anulada correctamente.');
    }

    public function reopen(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        try {
            $this->flowService->reopenOrder($order, (int) auth()->id(), $validated['reason']);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'OS reabierta correctamente.');
    }

    public function registerWarranty(Request $request, WorkshopMovement $order): RedirectResponse
    {
        $this->assertOrderScope($order);
        $validated = $request->validate([
            'workshop_movement_detail_id' => ['nullable', 'integer', 'exists:workshop_movement_details,id'],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $this->flowService->registerWarranty(
                $order,
                $validated['workshop_movement_detail_id'] ?? null,
                (int) $validated['days'],
                $validated['note'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Garantia registrada correctamente.');
    }

    private function assertOrderScope(WorkshopMovement $order): void
    {
        if (!$this->orderMatchesSessionScope($order)) {
            abort(404);
        }
    }

    private function orderMatchesSessionScope(WorkshopMovement $order): bool
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $order->branch_id !== $branchId) {
            return false;
        }

        $branch = $branchId > 0 ? Branch::query()->find($branchId) : null;
        if ($branch && (int) $order->company_id !== (int) $branch->company_id) {
            return false;
        }

        return true;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\WorkshopMovement>
     */
    private function workshopOrdersFilteredQuery(int $branchId, int $companyId, string $search, string $selectedStatus, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $selectedStatus = $this->normalizeWorkshopOrderListStatus($selectedStatus);

        return WorkshopMovement::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when(
                Schema::hasColumn('workshop_movements', 'quotation_source'),
                fn ($query) => $query->where(function ($scope) {
                    $scope->whereNull('quotation_source')
                        ->orWhere('quotation_source', '!=', 'external');
                })
            )
            ->when($selectedStatus !== 'all', fn ($query) => $query->where('status', $selectedStatus))
            ->when($dateFrom, fn ($query) => $query->whereDate('intake_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('intake_date', '<=', $dateTo))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('movement', fn ($movementQuery) => $movementQuery->where('number', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery
                            ->where('plate', 'ILIKE', "%{$search}%")
                            ->orWhere('brand', 'ILIKE', "%{$search}%")
                            ->orWhere('model', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery
                            ->where('document_number', 'ILIKE', "%{$search}%")
                            ->orWhere('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhereRaw("TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) ILIKE ?", ["%{$search}%"]));
                });
            });
    }

    private function normalizeWorkshopOrderListDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeWorkshopOrderListStatus(string $selectedStatus): string
    {
        $availableStatuses = [
            'all',
            'registered',
            'draft',
            'diagnosis',
            'awaiting_approval',
            'approved',
            'in_progress',
            'finished',
            'delivered',
            'cancelled',
            'open',
        ];
        if (!in_array($selectedStatus, $availableStatuses, true)) {
            return 'all';
        }

        return $selectedStatus;
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

