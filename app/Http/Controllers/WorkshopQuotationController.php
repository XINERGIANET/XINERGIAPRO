<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\SendWorkshopQuotationRequest;
use App\Http\Requests\Workshop\StoreExternalQuotationRequest;
use App\Http\Requests\Workshop\UpdateWorkshopQuotationResultRequest;
use App\Mail\WorkshopQuotationSentMail;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Operation;
use App\Models\Person;
use App\Models\ProductBranch;
use App\Models\TaxRate;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkshopMovement;
use App\Models\WorkshopService;
use App\Services\Workshop\WorkshopFlowService;
use App\Support\WorkshopQuotationExcelExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class WorkshopQuotationController extends Controller
{
    public function __construct(private readonly WorkshopFlowService $flowService)
    {
    }

    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);
        $profileId = (int) session('profile_id');
        $viewId = $request->input('view_id');

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $clientId = $request->input('client_id');
        $sourceFilter = $request->input('quotation_source');

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

        $quotationsQuery = $this->quotationListBaseQuery($companyId, $branchId, $clientId, $sourceFilter, $search);

        $stats = [
            'total' => 0,
            'won' => 0,
            'lost' => 0,
            'open' => 0,
        ];
        if (Schema::hasColumn('workshop_movements', 'quotation_result')) {
            $stats = [
                'total' => (clone $quotationsQuery)->count(),
                'won' => (clone $quotationsQuery)->whereIn('quotation_result', ['won', 'converted'])->count(),
                'lost' => (clone $quotationsQuery)->where('quotation_result', 'lost')->count(),
                'open' => (clone $quotationsQuery)->where('quotation_result', 'open')->count(),
            ];
        }

        $quotations = $quotationsQuery
            ->with(['movement', 'vehicle', 'client', 'details', 'deletedDetails', 'generatedOrder.movement', 'sale.movement'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', 3))
            ->orderBy('first_name')
            ->get();

        $taxRates = TaxRate::query()->orderBy('description')->get(['id', 'description', 'tax_rate']);
        $showQuotationExtras = Schema::hasColumn('workshop_movements', 'quotation_correlative');
        $showQuotationStats = Schema::hasColumn('workshop_movements', 'quotation_result');

        return view('workshop.quotations.index', compact(
            'quotations',
            'search',
            'perPage',
            'clients',
            'clientId',
            'operaciones',
            'stats',
            'sourceFilter',
            'taxRates',
            'showQuotationExtras',
            'showQuotationStats'
        ));
    }

    public function createExternal(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? Branch::query()->with('location.parent.parent')->find($branchId) : null;
        $locationData = $this->getLocationData((int) ($branch?->location_id ?? 0));

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', 3))
            ->orderBy('first_name')
            ->get();

        $vehicles = collect();
        $clientId = (int) old('client_person_id', 0);
        if ($clientId > 0) {
            $vehicles = Vehicle::query()
                ->where('client_person_id', $clientId)
                ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('plate')
                ->get();
        }

        $taxRates = TaxRate::query()->orderBy('description')->get(['id', 'description', 'tax_rate']);
        $defaultTaxRateId = $this->resolveDefaultTaxRateId($branchId);

        $productOptions = ProductBranch::query()
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->whereNull('products.deleted_at')
            ->orderBy('products.description')
            ->get([
                'product_branch.product_id',
                'product_branch.price',
                'product_branch.purchase_price',
                'product_branch.avg_cost',
                'product_branch.tax_rate_id',
                'products.code',
                'products.description',
            ]);

        $services = WorkshopService::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'base_price']);

        $companyId = (int) ($branch?->company_id ?? 0);
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

        $defaultVehicleTypeId = (int) (optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id
            ?? optional($vehicleTypes->first())->id
            ?? 0);

        return view('workshop.quotations.create-external', [
            'clients' => $clients,
            'vehicles' => $vehicles,
            'clientId' => $clientId,
            'taxRates' => $taxRates,
            'productOptions' => $productOptions,
            'services' => $services,
            'departments' => $locationData['departments'],
            'provinces' => $locationData['provinces'],
            'districts' => $locationData['districts'],
            'branchDepartmentId' => $locationData['selectedDepartmentId'],
            'branchProvinceId' => $locationData['selectedProvinceId'],
            'branchDistrictId' => $locationData['selectedDistrictId'],
            'quickClientStoreUrl' => route('admin.sales.clients.store'),
            'vehicleTypes' => $vehicleTypes,
            'defaultVehicleTypeId' => $defaultVehicleTypeId,
            'defaultTaxRateId' => $defaultTaxRateId,
        ]);
    }

    public function editExternal(Request $request, WorkshopMovement $quotation)
    {
        $this->assertQuotationScope($quotation);

        if ((string) ($quotation->quotation_source ?? '') !== 'external') {
            return redirect()
                ->route('admin.sales.quotations.index', array_filter(['view_id' => $request->input('view_id')]))
                ->withErrors(['error' => 'Solo las cotizaciones externas se editan desde este formulario.']);
        }

        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? Branch::query()->with('location.parent.parent')->find($branchId) : null;
        $locationData = $this->getLocationData((int) ($branch?->location_id ?? 0));

        $quotation->loadMissing(['details']);

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', 3))
            ->orderBy('first_name')
            ->get();

        $clientId = (int) old('client_person_id', (int) $quotation->client_person_id);

        $vehicles = collect();
        if ($clientId > 0) {
            $vehicles = Vehicle::query()
                ->where('client_person_id', $clientId)
                ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('plate')
                ->get();
        }

        $taxRates = TaxRate::query()->orderBy('description')->get(['id', 'description', 'tax_rate']);
        $defaultTaxRateId = $this->resolveDefaultTaxRateId($branchId);

        $productOptions = ProductBranch::query()
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->whereNull('products.deleted_at')
            ->orderBy('products.description')
            ->get([
                'product_branch.product_id',
                'product_branch.price',
                'product_branch.purchase_price',
                'product_branch.avg_cost',
                'product_branch.tax_rate_id',
                'products.code',
                'products.description',
            ]);

        $services = WorkshopService::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'base_price']);

        $companyId = (int) ($branch?->company_id ?? 0);
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

        $defaultVehicleTypeId = (int) (optional($vehicleTypes->firstWhere('name', 'moto lineal'))->id
            ?? optional($vehicleTypes->first())->id
            ?? 0);

        return view('workshop.quotations.create-external', [
            'quotation' => $quotation,
            'clients' => $clients,
            'vehicles' => $vehicles,
            'clientId' => $clientId,
            'taxRates' => $taxRates,
            'productOptions' => $productOptions,
            'services' => $services,
            'departments' => $locationData['departments'],
            'provinces' => $locationData['provinces'],
            'districts' => $locationData['districts'],
            'branchDepartmentId' => $locationData['selectedDepartmentId'],
            'branchProvinceId' => $locationData['selectedProvinceId'],
            'branchDistrictId' => $locationData['selectedDistrictId'],
            'quickClientStoreUrl' => route('admin.sales.clients.store'),
            'vehicleTypes' => $vehicleTypes,
            'defaultVehicleTypeId' => $defaultVehicleTypeId,
            'defaultTaxRateId' => $defaultTaxRateId,
        ]);
    }

    public function vehiclesForClient(Request $request): JsonResponse
    {
        $branchId = (int) session('branch_id');
        $clientId = (int) $request->query('client_person_id', 0);
        if ($clientId <= 0 || $branchId <= 0) {
            return response()->json(['vehicles' => []]);
        }

        $isClient = Person::query()
            ->where('id', $clientId)
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', 3))
            ->exists();

        if (!$isClient) {
            return response()->json(['message' => 'Cliente no valido.'], 422);
        }

        $rows = Vehicle::query()
            ->where('client_person_id', $clientId)
            ->where('branch_id', $branchId)
            ->orderBy('plate')
            ->get(['id', 'plate', 'brand', 'model']);

        return response()->json([
            'vehicles' => $rows->map(fn (Vehicle $v) => [
                'id' => (int) $v->id,
                'label' => trim(trim((string) $v->plate) . ' - ' . trim(trim((string) $v->brand) . ' ' . trim((string) $v->model))),
            ])->values(),
        ]);
    }

    public function storeExternal(StoreExternalQuotationRequest $request): RedirectResponse
    {
        $branchId = (int) session('branch_id');
        $user = $request->user();
        $userName = (string) ($user?->name ?? 'Sistema');

        try {
            $this->flowService->createExternalQuotation($request->validated(), $branchId, (int) $user?->id, $userName);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Cotizacion externa registrada correctamente.');
    }

    public function updateExternal(StoreExternalQuotationRequest $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        $branchId = (int) session('branch_id');
        $user = $request->user();
        $userName = (string) ($user?->name ?? 'Sistema');

        try {
            $this->flowService->updateExternalQuotation(
                $quotation,
                $request->validated(),
                $branchId,
                (int) $user?->id,
                $userName
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Cotizacion externa actualizada correctamente.');
    }

    public function destroyExternal(Request $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        try {
            $this->flowService->deleteExternalQuotation($quotation);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Cotizacion externa eliminada correctamente.');
    }

    public function excel(Request $request, WorkshopMovement $quotation): BinaryFileResponse|RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        try {
            $path = WorkshopQuotationExcelExport::buildPath($quotation);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'No se pudo generar el Excel: ' . $e->getMessage()]);
        }

        $name = 'cotizacion_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($quotation->quotation_correlative ?: $quotation->id)) . '.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, WorkshopMovement $quotation): \Illuminate\Contracts\View\View|\Illuminate\Http\Response
    {
        $this->assertQuotationScope($quotation);
        $quotation->loadMissing([
            'movement',
            'vehicle',
            'client',
            'branch.company',
            'branch.location.parent',
            'details.product',
            'details.service',
            'details.technician',
            'technicians.technician',
        ]);

        $branch = $quotation->branch instanceof Branch ? $quotation->branch : Branch::query()->with('company', 'location.parent')->find($quotation->branch_id);
        $terms = is_array($quotation->quotation_commercial_terms ?? null) ? $quotation->quotation_commercial_terms : [];

        $serviceLines = $quotation->details->filter(function ($d) {
            $t = strtoupper((string) $d->line_type);

            return $t === 'SERVICE' || $t === 'LABOR';
        })->values();
        $partLines = $quotation->details->filter(fn ($d) => strtoupper((string) $d->line_type) === 'PART')->values();

        $subtotalServices = (float) $serviceLines->sum(fn ($d) => (float) $d->total);
        $subtotalParts = (float) $partLines->sum(fn ($d) => (float) $d->total);

        $logoPath = WorkshopQuotationExcelExport::resolveBranchLogoPath($branch);
        $logoFileUri = null;
        if ($logoPath) {
            $normalized = str_replace('\\', '/', $logoPath);
            if (preg_match('/^[A-Za-z]:\//', $normalized)) {
                $logoFileUri = 'file:///' . $normalized;
            } else {
                $logoFileUri = 'file://' . $normalized;
            }
        }

        $cityName = trim((string) data_get($branch, 'location.parent.name', ''));
        if ($cityName === '') {
            $cityName = trim((string) ($branch?->location?->name ?? ''));
        }
        if ($cityName === '') {
            $cityName = 'Lima';
        }

        $intake = $quotation->intake_date;
        if ($intake) {
            $dateLine = $cityName . ', ' . $intake->locale('es')->translatedFormat('d \d\e F \d\e\l Y');
        } else {
            $dateLine = $cityName . ', ' . now()->locale('es')->translatedFormat('d \d\e F \d\e\l Y');
        }

        $docNumber = 'N°' . (string) ($quotation->quotation_correlative ?: ($quotation->movement?->number ?? str_pad((string) $quotation->id, 5, '0', STR_PAD_LEFT)));

        $branchDireccion = trim((string) data_get($branch, 'direccion', ''));
        $branchAddress = trim((string) data_get($branch, 'address', ''));
        $address = $branchDireccion !== '' ? $branchDireccion : $branchAddress;

        $branchPhone = $this->resolveQuotationTermContact($terms, ['branch_phone', 'phone', 'telefono', 'tel']);
        $branchEmail = $this->resolveQuotationTermContact($terms, ['branch_email', 'email', 'correo']);

        $companyName = $branch?->company?->legal_name ?? $branch?->legal_name ?? 'Empresa';

        $technicianNames = $quotation->technicians
            ->map(fn ($row) => trim(($row->technician?->first_name ?? '') . ' ' . ($row->technician?->last_name ?? '')))
            ->filter()
            ->values();
        $technicianLine = $technicianNames->isNotEmpty() ? $technicianNames->implode(', ') : '';

        $viewData = compact(
            'quotation',
            'branch',
            'terms',
            'serviceLines',
            'partLines',
            'subtotalServices',
            'subtotalParts',
            'logoFileUri',
            'dateLine',
            'docNumber',
            'address',
            'branchPhone',
            'branchEmail',
            'companyName',
            'technicianLine'
        );

        $html = view('workshop.quotations.pdf-liquidacion', $viewData)->render();
        $pdfBinary = $this->renderQuotationPdfWithWkhtmltopdf($html, 'A4', [
            '--orientation', 'Portrait',
            '--margin-top', '8',
            '--margin-right', '8',
            '--margin-bottom', '8',
            '--margin-left', '8',
        ]);

        if ($pdfBinary === null) {
            return view('workshop.quotations.pdf-liquidacion', $viewData);
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($quotation->quotation_correlative ?: $quotation->id)) ?? 'cotizacion';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liquidacion-servicio-' . $safe . '.pdf"',
        ]);
    }

    public function send(SendWorkshopQuotationRequest $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        try {
            $path = WorkshopQuotationExcelExport::buildPath($quotation);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'No se pudo generar el Excel: ' . $e->getMessage()]);
        }

        $email = (string) $request->validated('email');
        $fileName = 'cotizacion_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($quotation->quotation_correlative ?: $quotation->id)) . '.xlsx';
        $mailer = (string) config('mail.default', 'smtp');

        if (in_array($mailer, ['log', 'array'], true)) {
            @unlink($path);

            return back()->withErrors([
                'error' => "No se envio el correo: el mailer activo es '{$mailer}' (modo de prueba). Configura SMTP real en .env.",
            ]);
        }

        $fromAddress = (string) config('mail.from.address', '');
        if ($fromAddress === '' || Str::endsWith(Str::lower($fromAddress), '@example.com')) {
            @unlink($path);

            return back()->withErrors([
                'error' => 'No se envio el correo: revisa MAIL_FROM_ADDRESS en .env (actualmente no es valido para produccion).',
            ]);
        }

        try {
            Mail::to($email)->send(new WorkshopQuotationSentMail($quotation, $path, $fileName));
        } catch (\Throwable $e) {
            @unlink($path);

            $detail = $e->getMessage();
            if (str_contains($detail, 'You can only send testing emails') || str_contains($detail, 'verify a domain')) {
                $detail = 'Resend en modo prueba (onboarding@resend.dev): solo puedes enviar a tu correo de cuenta de Resend. '
                    . 'Para enviar a clientes, verifica un dominio en https://resend.com/domains y pon MAIL_FROM_ADDRESS con un correo de ese dominio (ej. noreply@tudominio.com).';
            }

            return back()->withErrors(['error' => 'No se pudo enviar el correo: ' . $detail]);
        }

        $mailerRoot = Mail::getFacadeRoot();
        if ($mailerRoot && method_exists($mailerRoot, 'failures')) {
            $failures = (array) $mailerRoot->failures();
            if ($failures !== []) {
                @unlink($path);

                return back()->withErrors([
                    'error' => 'No se pudo enviar el correo. Destinatarios rechazados: ' . implode(', ', $failures),
                ]);
            }
        }

        @unlink($path);

        $sentPatch = [];
        if (Schema::hasColumn('workshop_movements', 'quotation_client_email')) {
            $sentPatch['quotation_client_email'] = $email;
        }
        if (Schema::hasColumn('workshop_movements', 'quotation_sent_at')) {
            $sentPatch['quotation_sent_at'] = now();
        }
        if ($sentPatch !== []) {
            $quotation->update($sentPatch);
        }

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Cotizacion enviada por correo a ' . $email . '.');
    }

    public function updateResult(UpdateWorkshopQuotationResultRequest $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        try {
            $this->flowService->updateQuotationResult(
                $quotation,
                (string) $request->validated('quotation_result'),
                $request->validated('quotation_lost_reason'),
                (int) $request->user()?->id
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Resultado de cotizacion actualizado.');
    }

    public function generateOrder(Request $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);

        $user = $request->user();
        $branchId = (int) session('branch_id');

        try {
            $order = $this->flowService->generateOrderFromExternalQuotation(
                $quotation,
                $branchId,
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('workshop.orders.show', $order)
            ->with('status', 'Orden de servicio generada desde cotizacion externa.');
    }

    public function confirmPartsPurchase(Request $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);
        if ((string) ($quotation->quotation_source ?? '') !== 'external') {
            abort(404);
        }
        if ($quotation->vehicle_id) {
            return back()->withErrors(['error' => 'Esta accion solo aplica cuando la cotizacion no tiene vehiculo.']);
        }
        if (!$quotation->client_person_id) {
            return back()->withErrors(['error' => 'La cotizacion requiere cliente.']);
        }

        $terms = is_array($quotation->quotation_commercial_terms ?? null) ? $quotation->quotation_commercial_terms : [];
        $terms['parts_purchase_recorded'] = true;
        $quotation->update(['quotation_commercial_terms' => $terms]);

        $q = array_filter(['view_id' => $request->input('view_id')]);

        return redirect()
            ->route('admin.sales.quotations.index', $q)
            ->with('status', 'Se marco la compra de repuestos como atendida. Ya puede generar la venta.');
    }

    public function generatePartsSale(Request $request, WorkshopMovement $quotation): RedirectResponse
    {
        $this->assertQuotationScope($quotation);
        $user = $request->user();
        $branchId = (int) session('branch_id');

        try {
            $sale = $this->flowService->generatePartsSaleFromApprovedExternalQuotationWithoutVehicle(
                $quotation,
                $branchId,
                (int) $user?->id,
                (string) ($user?->name ?? 'Sistema')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $movementId = (int) ($sale->movement_id ?? 0);
        if ($movementId <= 0) {
            return back()->with('status', 'Venta generada correctamente. ID venta: ' . (int) $sale->id);
        }

        return redirect()
            ->route('admin.sales.edit', $movementId)
            ->with('status', 'Venta generada desde la cotizacion (sin vehiculo).');
    }

    private function resolveQuotationTermContact(array $terms, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $terms)) {
                continue;
            }
            $value = trim((string) ($terms[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function renderQuotationPdfWithWkhtmltopdf(string $html, ?string $pageSize = 'A4', array $extraArgs = []): ?string
    {
        $binary = $this->resolveWkhtmltopdfBinary();
        if (!$binary) {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $htmlFile = tempnam($tmpDir, 'quotation_html_');
        $pdfFile = tempnam($tmpDir, 'quotation_pdf_');

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

        $args[] = $htmlPath;
        $args[] = $pdfPath;
        $process = new Process($args);

        try {
            $process->setTimeout(120);
            $process->run();
            if (!file_exists($pdfPath) || filesize($pdfPath) <= 0) {
                Log::warning('wkhtmltopdf fallo al generar PDF de cotizacion', [
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);

                return null;
            }

            $content = file_get_contents($pdfPath);

            return $content === false ? null : $content;
        } catch (\Throwable $e) {
            Log::warning('Error ejecutando wkhtmltopdf en cotizacion: ' . $e->getMessage());

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

    private function assertQuotationScope(WorkshopMovement $quotation): void
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $quotation->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function quotationListBaseQuery(int $companyId, int $branchId, $clientId, mixed $sourceFilter, string $search)
    {
        return WorkshopMovement::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['awaiting_approval', 'approved', 'diagnosis'])
            ->when($clientId, fn ($query) => $query->where('client_person_id', $clientId))
            ->when(
                Schema::hasColumn('workshop_movements', 'quotation_source')
                && ($sourceFilter === 'internal' || $sourceFilter === 'external'),
                function ($query) use ($sourceFilter) {
                    $query->where('quotation_source', $sourceFilter);
                }
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('movement', fn ($movementQuery) => $movementQuery->where('number', 'ILIKE', "%{$search}%"));
                    if (Schema::hasColumn('workshop_movements', 'quotation_correlative')) {
                        $inner->orWhere('quotation_correlative', 'ILIKE', "%{$search}%");
                    }
                    $inner->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('plate', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery
                            ->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%"));
                });
            });
    }

    private function resolveDefaultTaxRateId(int $branchId): ?int
    {
        $configuredIgv = DB::table('parameters')
            ->leftJoin('branch_parameters', function ($join) use ($branchId) {
                $join->on('branch_parameters.parameter_id', '=', 'parameters.id')
                    ->where('branch_parameters.branch_id', $branchId)
                    ->whereNull('branch_parameters.deleted_at');
            })
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(parameters.description)) = ?', ['igv defecto'])
                    ->orWhereRaw('LOWER(TRIM(parameters.description)) = ?', ['igv por defecto'])
                    ->orWhereRaw('LOWER(TRIM(parameters.description)) = ?', ['ws_default_igv']);
            })
            ->where('parameters.status', 1)
            ->whereNull('parameters.deleted_at')
            ->orderByRaw("
                CASE
                    WHEN LOWER(TRIM(parameters.description)) = 'igv defecto' THEN 1
                    WHEN LOWER(TRIM(parameters.description)) = 'igv por defecto' THEN 2
                    WHEN LOWER(TRIM(parameters.description)) = 'ws_default_igv' THEN 3
                    ELSE 9
                END
            ")
            ->selectRaw('COALESCE(branch_parameters.value, parameters.value) as configured_igv')
            ->value('configured_igv');

        $configuredIgvValue = is_numeric($configuredIgv) ? (float) $configuredIgv : null;

        if ($configuredIgvValue !== null) {
            $match = TaxRate::query()
                ->where('status', true)
                ->whereRaw('ABS(tax_rate - ?) < 0.000001', [$configuredIgvValue])
                ->orderBy('order_num')
                ->value('id');
            if ($match) {
                return (int) $match;
            }
        }

        $fallback = TaxRate::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }

    /**
     * @return array{departments:mixed,provinces:mixed,districts:mixed,selectedDepartmentId:?int,selectedProvinceId:?int,selectedDistrictId:?int}
     */
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
}
