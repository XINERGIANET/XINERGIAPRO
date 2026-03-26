<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashMovements;
use App\Models\CashMovementDetail;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Technician;
use App\Models\Unit;
use App\Models\WorkshopAssembly;
use App\Models\WorkshopAssemblyCost;
use App\Models\WorkshopAssemblyLocation;
use App\Models\DigitalWallet;
use App\Models\Card;
use App\Models\PaymentGateways;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Accounting\AccountReceivablePayableService;

class WorkshopAssemblyController extends Controller
{
    public function __construct()
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
        [$branchId, $companyId] = $this->resolveContext();

        $month = (string) $request->input('month', now()->format('Y-m'));
        $brandCompany = trim((string) $request->input('brand_company', ''));
        $vehicleType = trim((string) $request->input('vehicle_type', ''));

        $assemblies = WorkshopAssembly::query()
            ->with(['location:id,name,address', 'responsibleTechnician:id,first_name,last_name,document_number'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereRaw("to_char(assembled_at, 'YYYY-MM') = ?", [$month])
            ->when($brandCompany !== '', fn ($query) => $query->where('brand_company', 'ILIKE', "%{$brandCompany}%"))
            ->when($vehicleType !== '', fn ($query) => $query->where('vehicle_type', 'ILIKE', "%{$vehicleType}%"))
            ->orderByDesc('assembled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $costTable = WorkshopAssemblyCost::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('active', true)
            ->orderBy('brand_company')
            ->orderBy('vehicle_type')
            ->get();

        $summaryByType = WorkshopAssembly::query()
            ->selectRaw('brand_company, vehicle_type, SUM(quantity) as total_qty, SUM(total_cost) as total_cost')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereRaw("to_char(assembled_at, 'YYYY-MM') = ?", [$month])
            ->groupBy('brand_company', 'vehicle_type')
            ->orderBy('brand_company')
            ->orderBy('vehicle_type')
            ->get();

        $assemblyLocations = WorkshopAssemblyLocation::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get();

        $technicians = Person::query()
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 2); // Role 2 = Empleado
            })
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $vehicleTypes = \App\Models\VehicleType::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('order_num')
            ->orderBy('name')
            ->get();

        return view('workshop.assemblies.index', compact(
            'assemblies',
            'costTable',
            'summaryByType',
            'month',
            'brandCompany',
            'vehicleType', 
            'assemblyLocations',
            'technicians',
            'vehicleTypes'
        ));
    }

    public function checkoutPage(Request $request): \Illuminate\View\View|RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();

        $assemblyIds = $request->query('ids', []);
        
        if (empty($assemblyIds) || !is_array($assemblyIds)) {
            return redirect()->route('workshop.assemblies.index')
                ->withErrors(['error' => 'No seleccionó ningún armado para el cobro.']);
        }

        $assemblies = WorkshopAssembly::query()
            ->whereIn('id', $assemblyIds)
            ->where('branch_id', $branchId)
            ->where('company_id', $companyId)
            ->whereNull('sales_movement_id')
            ->get();

        if ($assemblies->isEmpty()) {
            return redirect()->route('workshop.assemblies.index')
                ->withErrors(['error' => 'Los armados seleccionados no son válidos o ya han sido facturados.']);
        }

        $clients = Person::query()
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
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

        $paymentMethodsRaw = PaymentMethod::query()->where('status', true)->orderBy('order_num')->get();

        $paymentMethodOptions = $paymentMethodsRaw->map(function ($method) {
            return [
                'id' => (int) $method->id,
                'description' => (string) ($method->description ?? ''),
                'kind' => $this->inferPaymentMethodKind((string) ($method->description ?? '')),
            ];
        })->values();

        $digitalWalletOptions = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('id')
            ->get();

        $cardOptions = Card::query()
            ->where('status', true)
            ->orderBy('id')
            ->get();

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

        return view('workshop.assemblies.checkout', compact(
            'assemblies',
            'clients',
            'documentTypes',
            'cashRegisters',
            'paymentMethodOptions',
            'digitalWalletOptions',
            'cardOptions',
            'paymentGatewayOptionsByMethod'
        ));
    }

    private function inferPaymentMethodKind(string $description): string
    {
        $desc = strtolower($description);
        if (str_contains($desc, 'tarjeta') || str_contains($desc, 'niubiz') || str_contains($desc, 'visa') || str_contains($desc, 'mastercard') || str_contains($desc, 'pago link')) {
            return 'card';
        }

        if (str_contains($desc, 'yape') || str_contains($desc, 'plin') || str_contains($desc, 'billetera')) {
            return 'wallet';
        }

        if (str_contains($desc, 'transferencia') || str_contains($desc, 'deposito') || str_contains($desc, 'cuenta')) {
            return 'transfer';
        }

        if (str_contains($desc, 'efectivo') || str_contains($desc, 'cash')) {
            return 'cash';
        }

        return 'other';
    }

    public function store(Request $request): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();

        $data = $request->validate([
            'brand_company' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'displacement' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'vin' => ['nullable', 'string', 'max:100'],
            'workshop_assembly_location_id' => ['nullable', 'integer', 'exists:workshop_assembly_locations,id'],
            'responsible_technician_person_id' => [
                'nullable',
                'integer',
                Rule::exists('people', 'id')->where(function ($query) use ($branchId) {
                    if ($branchId > 0) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'assembled_at' => ['required', 'date'],
            'entry_at' => ['nullable', 'date'],
            'estimated_delivery_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $resolvedUnitCost = $data['unit_cost'] ?? null;
        if ($resolvedUnitCost === null) {
            $resolvedUnitCost = (float) WorkshopAssemblyCost::query()
                ->where('company_id', $companyId)
                ->where('brand_company', $data['brand_company'])
                ->where('vehicle_type', $data['vehicle_type'])
                ->where(function ($query) use ($branchId) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->where('active', true)
                ->orderByRaw('branch_id is null')
                ->value('unit_cost');
        }
        $resolvedUnitCost = round((float) $resolvedUnitCost, 6);

        WorkshopAssembly::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'brand_company' => $data['brand_company'],
            'vehicle_type' => $data['vehicle_type'],
            'model' => $data['model'] ?? null,
            'displacement' => $data['displacement'] ?? null,
            'color' => $data['color'] ?? null,
            'vin' => $data['vin'] ?? null,
            'workshop_assembly_location_id' => $data['workshop_assembly_location_id'] ?? null,
            'responsible_technician_person_id' => $data['responsible_technician_person_id'] ?? null,
            'quantity' => (int) $data['quantity'],
            'unit_cost' => $resolvedUnitCost,
            'total_cost' => round(((int) $data['quantity']) * $resolvedUnitCost, 6),
            'assembled_at' => $data['assembled_at'],
            'entry_at' => $data['entry_at'] ?? now(),
            'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
            'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', 'Registro de armado creado correctamente.');
    }

    public function update(Request $request, WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);

        $data = $request->validate([
            'brand_company' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'displacement' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'vin' => ['nullable', 'string', 'max:100'],
            'workshop_assembly_location_id' => ['nullable', 'integer', 'exists:workshop_assembly_locations,id'],
            'responsible_technician_person_id' => [
                'nullable',
                'integer',
                Rule::exists('people', 'id')->where(function ($query) use ($branchId) {
                    if ($branchId > 0) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'assembled_at' => ['required', 'date'],
            'entry_at' => ['nullable', 'date'],
            'estimated_delivery_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $unitCost = round((float) $data['unit_cost'], 6);
        $quantity = (int) $data['quantity'];

        $assembly->update([
            'brand_company' => $data['brand_company'],
            'vehicle_type' => $data['vehicle_type'],
            'model' => $data['model'] ?? null,
            'displacement' => $data['displacement'] ?? null,
            'color' => $data['color'] ?? null,
            'vin' => $data['vin'] ?? null,
            'workshop_assembly_location_id' => $data['workshop_assembly_location_id'] ?? null,
            'responsible_technician_person_id' => $data['responsible_technician_person_id'] ?? null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 6),
            'assembled_at' => $data['assembled_at'],
            'entry_at' => $data['entry_at'] ?? $assembly->entry_at,
            'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
            'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Registro de armado actualizado.');
    }

    public function destroy(WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);
        $assembly->delete();

        return back()->with('status', 'Registro de armado eliminado.');
    }

    public function exportMonthlyCsv(Request $request): StreamedResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $month = (string) $request->input('month', now()->format('Y-m'));

        $rows = WorkshopAssembly::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereRaw("to_char(assembled_at, 'YYYY-MM') = ?", [$month])
            ->orderBy('assembled_at')
            ->orderBy('brand_company')
            ->orderBy('vehicle_type')
            ->get();

        $filename = "armados_{$month}_sucursal_{$branchId}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Fecha', 'Empresa/Marca', 'Tipo Vehiculo', 'Modelo', 'Cilindrada', 'Color', 'VIN', 'Ubicacion', 'Tecnico', 'Ingreso', 'Entrega estimada', 'Inicio', 'Fin', 'Tiempo estimado', 'Tiempo real', 'Diferencia', 'Salida', 'Cantidad', 'Costo Unitario', 'Costo Total', 'Observaciones']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->assembled_at)->format('Y-m-d'),
                    $row->brand_company,
                    $row->vehicle_type,
                    (string) $row->model,
                    (string) $row->displacement,
                    (string) $row->color,
                    (string) $row->vin,
                    optional($row->location)->name,
                    trim((string) optional($row->responsibleTechnician)->first_name . ' ' . (string) optional($row->responsibleTechnician)->last_name),
                    optional($row->entry_at)->format('Y-m-d H:i'),
                    optional($row->estimated_delivery_at)->format('Y-m-d H:i'),
                    optional($row->started_at)->format('Y-m-d H:i'),
                    optional($row->finished_at)->format('Y-m-d H:i'),
                    (int) $row->estimated_minutes,
                    $row->actual_repair_minutes,
                    $row->estimated_vs_real_minutes,
                    optional($row->exit_at)->format('Y-m-d H:i'),
                    (int) $row->quantity,
                    number_format((float) $row->unit_cost, 6, '.', ''),
                    number_format((float) $row->total_cost, 6, '.', ''),
                    (string) ($row->notes ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeCost(Request $request)
    {
        [$branchId, $companyId] = $this->resolveContext();

        $data = $request->validate([
            'brand_company' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:60'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'apply_to_all_branches' => ['nullable', 'boolean'],
        ]);

        $cost = WorkshopAssemblyCost::updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => empty($data['apply_to_all_branches']) ? $branchId : null,
                'brand_company' => $data['brand_company'],
                'vehicle_type' => $data['vehicle_type'],
            ],
            [
                'unit_cost' => round((float) $data['unit_cost'], 6),
                'active' => true,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Costo configurado correctamente.',
                'cost' => $cost
            ]);
        }

        return back()->with('status', 'Costo configurado correctamente.');
    }

    public function updateCost(Request $request, WorkshopAssemblyCost $cost)
    {
        [$branchId, $companyId] = $this->resolveContext();
        if ((int)$cost->company_id !== $companyId) {
            abort(403);
        }

        $data = $request->validate([
            'brand_company' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:60'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $cost->update([
            'brand_company' => $data['brand_company'],
            'vehicle_type' => $data['vehicle_type'],
            'unit_cost' => round((float) $data['unit_cost'], 6),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Costo actualizado.',
                'cost' => $cost
            ]);
        }

        return back()->with('status', 'Costo actualizado.');
    }

    public function destroyCost(Request $request, WorkshopAssemblyCost $cost)
    {
        [$branchId, $companyId] = $this->resolveContext();
        if ((int)$cost->company_id !== $companyId) {
            abort(403);
        }

        $cost->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Costo eliminado.'
            ]);
        }

        return back()->with('status', 'Costo eliminado.');
    }
    public function startAssembly(WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);

        $estimatedDeliveryAt = $assembly->estimated_delivery_at;
        if (!$estimatedDeliveryAt && (int) $assembly->estimated_minutes > 0) {
            $estimatedDeliveryAt = now()->copy()->addMinutes((int) $assembly->estimated_minutes);
        }

        $assembly->update([
            'started_at' => now(),
            'estimated_delivery_at' => $estimatedDeliveryAt,
        ]);

        return back()->with('status', 'Armado iniciado correctamente.');
    }

    public function finishAssembly(WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);

        if (!$assembly->started_at) {
            return back()->with('error', 'Debe iniciar el armado primero.');
        }

        $assembly->update(['finished_at' => now()]);

        return back()->with('status', 'Armado finalizado correctamente.');
    }

    public function registerExit(WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);

        if (!$assembly->finished_at) {
            return back()->with('error', 'Debe finalizar el armado primero.');
        }

        $assembly->update(['exit_at' => now()]);

        return back()->with('status', 'Salida registrada correctamente.');
    }

    public function processMassiveSale(Request $request, AccountReceivablePayableService $arpService): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();

        $data = $request->validate([
            'assembly_ids' => ['required', 'array', 'min:1'],
            'client_person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'payment_type' => ['required', 'string', 'in:CONTADO,DEUDA'],
            'billing_status' => ['nullable', 'string', 'in:PENDING,INVOICED,NOT_APPLICABLE'],
            'invoice_series' => ['nullable', 'string', 'max:10'],
            'invoice_number' => ['nullable', 'string', 'max:20'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*.payment_method_id' => ['required_with:payment_methods', 'integer', 'exists:payment_methods,id'],
            'payment_methods.*.amount' => ['required_with:payment_methods', 'numeric', 'min:0.01'],
            'payment_methods.*.reference' => ['nullable', 'string', 'max:255'],
            'payment_methods.*.card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'payment_methods.*.digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'payment_methods.*.payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($data, $branchId, $companyId, $arpService) {
                $assemblies = WorkshopAssembly::query()
                    ->whereIn('id', $data['assembly_ids'])
                    ->where('branch_id', $branchId)
                    ->where('company_id', $companyId)
                    ->whereNull('sales_movement_id')
                    ->lockForUpdate()
                    ->get();

                if ($assemblies->isEmpty()) {
                    throw new \RuntimeException('No se encontraron armados validos o ya han sido facturados.');
                }

                $client = Person::findOrFail($data['client_person_id']);
                $userId = auth()->id();
                $userName = auth()->user()->name ?? 'Sistema';
                $documentType = DocumentType::findOrFail($data['document_type_id']);

                $isInvoice = str_contains(strtolower($documentType->name), 'factura') || str_contains(strtolower($documentType->name), 'boleta');
                $billingStatus = $data['billing_status'] ?? 'NOT_APPLICABLE';
                $invoiceSeries = $data['invoice_series'] ?? null;
                $invoiceNumber = $data['invoice_number'] ?? null;

                if (!$isInvoice) {
                    $billingStatus = 'NOT_APPLICABLE';
                    $invoiceSeries = null;
                    $invoiceNumber = null;
                }

                $paymentType = $data['payment_type'];

                // 1. Crear Movimiento de Venta
                $movement = Movement::create([
                    'number' => $this->generateMovementNumber($branchId, $documentType->id),
                    'moved_at' => now(),
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'person_id' => $client->id,
                    'person_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'responsible_id' => $userId,
                    'responsible_name' => $userName,
                    'comment' => $data['comment'] ?? 'Venta masiva de armados',
                    'status' => 'A',
                    'movement_type_id' => $documentType->movement_type_id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                ]);

                $total = $assemblies->sum('total_cost');
                $taxRate = TaxRate::where('tax_rate', '>', 0)->first(); // Buscamos IGV por defecto
                $taxPercent = ($taxRate?->tax_rate ?? 18) / 100;
                
                $subtotal = round($total / (1 + $taxPercent), 2);
                $tax = $total - $subtotal;

                // 2. Crear SalesMovement
                $sale = SalesMovement::create([
                    'branch_snapshot' => ['id' => $branchId, 'company_id' => $companyId],
                    'series' => $isInvoice && $billingStatus === 'INVOICED' ? $invoiceSeries : '001',
                    'year' => (string) now()->year,
                    'detail_type' => 'DETALLADO',
                    'payment_type' => $paymentType,
                    'currency' => 'PEN',
                    'exchange_rate' => 1,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                    'status' => $billingStatus === 'INVOICED' ? 'F' : 'N', // Facturado o Pendiente
                    'billing_status' => $billingStatus,
                    'invoice_series' => $isInvoice ? $invoiceSeries : null,
                    'invoice_number' => $isInvoice ? $invoiceNumber : null,
                ]);

                $unit = Unit::where('abbreviation', 'NIU')->first() ?: Unit::first();

                foreach ($assemblies as $assembly) {
                    SalesMovementDetail::create([
                        'sales_movement_id' => $sale->id,
                        'detail_type' => 'DETALLADO',
                        'code' => 'ARM-' . $assembly->id,
                        'description' => "ARMADO: {$assembly->brand_company} {$assembly->vehicle_type} {$assembly->model}",
                        'unit_id' => $unit?->id,
                        'tax_rate_id' => $taxRate?->id,
                        'tax_rate_snapshot' => $taxRate ? [
                            'id' => $taxRate->id,
                            'description' => $taxRate->description,
                            'tax_rate' => $taxRate->tax_rate,
                        ] : null,
                        'quantity' => $assembly->quantity,
                        'amount' => $assembly->total_cost,
                        'discount_percentage' => 0,
                        'original_amount' => $assembly->unit_cost,
                        'status' => 'A',
                        'branch_id' => $branchId,
                    ]);

                    $assembly->update(['sales_movement_id' => $sale->id]);
                }

                // 3. Registrar Pago o Cuenta por Cobrar
                if ($paymentType === 'DEUDA') {
                    $arpService->createFromSalesMovement($sale, session('company_id'));
                } elseif (!empty($data['payment_methods']) && !empty($data['cash_register_id'])) {
                    $this->registerMassiveMultiplePayments($sale, $data['cash_register_id'], $data['payment_methods'], $branchId, $userId, $userName);
                }
            });

            return redirect()->route('workshop.assemblies.index')->with('status', 'Venta masiva generada correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    private function registerMassiveMultiplePayments($sale, $cashRegisterId, array $paymentMethodsData, $branchId, $userId, $userName)
    {
        $cashRegister = CashRegister::findOrFail($cashRegisterId);
        $shift = Shift::where('branch_id', $branchId)->orderBy('id')->first();
        if (!$shift) {
            throw new \RuntimeException('No hay un turno/shift configurado para esta sucursal.');
        }

        // Asumimos concepto de pago de ingreso (Ventas)
        $paymentConceptId = DB::table('payment_concepts')
            ->where('type', 'I')
            ->where(function($q) {
                $q->where('description', 'ILIKE', '%venta%')
                  ->orWhere('description', 'ILIKE', '%cliente%');
            })
            ->value('id') ?: 1;

        $totalProvided = collect($paymentMethodsData)->sum('amount');
        if ($totalProvided < $sale->total) {
            throw new \RuntimeException("El monto proporcionado (S/ {$totalProvided}) no cubre el total de la venta (S/ {$sale->total}).");
        }

        $cashMovementEntity = Movement::create([
            'number' => $this->generateMovementNumber($branchId, 9), // Ticket/Recibo de Caja
            'moved_at' => now(),
            'user_id' => $userId,
            'user_name' => $userName,
            'person_id' => $sale->movement->person_id,
            'person_name' => $sale->movement->person_name,
            'responsible_id' => $userId,
            'responsible_name' => $userName,
            'comment' => 'Pago de venta masiva armados',
            'status' => '1',
            'movement_type_id' => 4, // CAJA
            'document_type_id' => 9, // TICKET/RECIBO
            'branch_id' => $branchId,
            'parent_movement_id' => $sale->movement_id,
        ]);

        $cashMovement = CashMovements::create([
            'payment_concept_id' => $paymentConceptId,
            'currency' => 'PEN',
            'exchange_rate' => 1,
            'total' => $totalProvided,
            'cash_register_id' => $cashRegisterId,
            'cash_register' => $cashRegister->number,
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
            'movement_id' => $cashMovementEntity->id,
            'branch_id' => $branchId,
        ]);

        foreach ($paymentMethodsData as $pmData) {
            $method = PaymentMethod::findOrFail($pmData['payment_method_id']);
            
            CashMovementDetail::create([
                'cash_movement_id' => $cashMovement->id,
                'type' => 'PAGADO',
                'paid_at' => now(),
                'payment_method_id' => $method->id,
                'payment_method' => $method->description,
                'number' => $cashMovementEntity->number,
                'amount' => $pmData['amount'],
                'reference' => $pmData['reference'] ?? null,
                'card_id' => $pmData['card_id'] ?? null,
                'digital_wallet_id' => $pmData['digital_wallet_id'] ?? null,
                'payment_gateway_id' => $pmData['payment_gateway_id'] ?? null,
                'comment' => 'Múltiples métodos de pago - Armados',
                'status' => 'A',
                'branch_id' => $branchId,
            ]);
        }
    }

    private function generateMovementNumber(int $branchId, int $documentTypeId): string
    {
        $year = (int) now()->year;

        $numbers = Movement::where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year)
            ->pluck('number');

        $lastCorrelative = 0;
        foreach ($numbers as $number) {
            $raw = trim((string) $number);
            if (preg_match('/^\d+$/', $raw) === 1) {
                $lastCorrelative = max($lastCorrelative, (int) $raw);
            }
        }

        return str_pad((string) ($lastCorrelative + 1), 8, '0', STR_PAD_LEFT);
    }

    private function resolveContext(): array
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            abort(403, 'No hay sucursal activa.');
        }
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        if ($companyId <= 0) {
            abort(403, 'No se encontro empresa para la sucursal.');
        }

        return [$branchId, $companyId];
    }

    private function assertScope(WorkshopAssembly $assembly, int $branchId, int $companyId): void
    {
        if ((int) $assembly->branch_id !== $branchId || (int) $assembly->company_id !== $companyId) {
            abort(404);
        }
    }
}
