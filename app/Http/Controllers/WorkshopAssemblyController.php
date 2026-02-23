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
use App\Models\Unit;
use App\Models\WorkshopAssembly;
use App\Models\WorkshopAssemblyCost;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $paymentMethods = PaymentMethod::query()->where('status', true)->orderBy('order_num')->get();

        return view('workshop.assemblies.index', compact(
            'assemblies',
            'costTable',
            'summaryByType',
            'month',
            'brandCompany',
            'vehicleType',
            'clients',
            'documentTypes',
            'cashRegisters',
            'paymentMethods'
        ));
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
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'assembled_at' => ['required', 'date'],
            'entry_at' => ['nullable', 'date'],
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
            'quantity' => (int) $data['quantity'],
            'unit_cost' => $resolvedUnitCost,
            'total_cost' => round(((int) $data['quantity']) * $resolvedUnitCost, 6),
            'assembled_at' => $data['assembled_at'],
            'entry_at' => $data['entry_at'] ?? now(),
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
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'assembled_at' => ['required', 'date'],
            'entry_at' => ['nullable', 'date'],
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
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 6),
            'assembled_at' => $data['assembled_at'],
            'entry_at' => $data['entry_at'] ?? $assembly->entry_at,
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
            fputcsv($out, ['Fecha', 'Empresa/Marca', 'Tipo Vehiculo', 'Modelo', 'Cilindrada', 'Color', 'VIN', 'Ingreso', 'Inicio', 'Fin', 'Salida', 'Cantidad', 'Costo Unitario', 'Costo Total', 'Observaciones']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->assembled_at)->format('Y-m-d'),
                    $row->brand_company,
                    $row->vehicle_type,
                    (string) $row->model,
                    (string) $row->displacement,
                    (string) $row->color,
                    (string) $row->vin,
                    optional($row->entry_at)->format('Y-m-d H:i'),
                    optional($row->started_at)->format('Y-m-d H:i'),
                    optional($row->finished_at)->format('Y-m-d H:i'),
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

    public function storeCost(Request $request): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();

        $data = $request->validate([
            'brand_company' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:60'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'apply_to_all_branches' => ['nullable', 'boolean'],
        ]);

        WorkshopAssemblyCost::updateOrCreate(
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

        return back()->with('status', 'Costo configurado correctamente.');
    }

    public function updateCost(Request $request, WorkshopAssemblyCost $cost): RedirectResponse
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

        return back()->with('status', 'Costo actualizado.');
    }

    public function destroyCost(WorkshopAssemblyCost $cost): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        if ((int)$cost->company_id !== $companyId) {
            abort(403);
        }

        $cost->delete();

        return back()->with('status', 'Costo eliminado.');
    }
    public function startAssembly(WorkshopAssembly $assembly): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $this->assertScope($assembly, $branchId, $companyId);

        $assembly->update(['started_at' => now()]);

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

    public function processMassiveSale(Request $request): RedirectResponse
    {
        [$branchId, $companyId] = $this->resolveContext();

        $data = $request->validate([
            'assembly_ids' => ['required', 'array', 'min:1'],
            'client_person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($data, $branchId, $companyId) {
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

                // 1. Crear Movimiento de Venta
                $movement = Movement::create([
                    'number' => $this->generateMovementNumber($branchId, $data['document_type_id']),
                    'moved_at' => now(),
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'person_id' => $client->id,
                    'person_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'responsible_id' => $userId,
                    'responsible_name' => $userName,
                    'comment' => $data['comment'] ?? 'Venta masiva de armados',
                    'status' => 'A',
                    'movement_type_id' => DocumentType::find($data['document_type_id'])->movement_type_id,
                    'document_type_id' => $data['document_type_id'],
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
                    'series' => '001',
                    'year' => (string) now()->year,
                    'detail_type' => 'DETALLADO',
                    'payment_type' => 'CONTADO',
                    'currency' => 'PEN',
                    'exchange_rate' => 1,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                    'status' => 'N',
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

                // 3. Registrar Pago si se proporciono caja
                if (!empty($data['cash_register_id']) && !empty($data['payment_method_id'])) {
                    $this->registerMassivePayment($sale, $data['cash_register_id'], $data['payment_method_id'], $branchId, $userId, $userName);
                }
            });

            return back()->with('status', 'Venta masiva generada correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    private function registerMassivePayment($sale, $cashRegisterId, $paymentMethodId, $branchId, $userId, $userName)
    {
        $cashRegister = CashRegister::findOrFail($cashRegisterId);
        $method = PaymentMethod::findOrFail($paymentMethodId);
        
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

        $cashMovementEntity = Movement::create([
            'number' => $this->generateMovementNumber($branchId, 9),
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
            'total' => $sale->total,
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

        CashMovementDetail::create([
            'cash_movement_id' => $cashMovement->id,
            'type' => 'PAGADO',
            'paid_at' => now(),
            'payment_method_id' => $paymentMethodId,
            'payment_method' => $method->description,
            'number' => $cashMovementEntity->number,
            'amount' => $sale->total,
            'comment' => 'Pago de venta masiva armados',
            'status' => 'A',
            'branch_id' => $branchId,
        ]);
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
