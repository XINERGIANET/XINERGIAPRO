<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\WorkshopAssembly;
use App\Models\WorkshopAssemblyCost;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('workshop.assemblies.index', compact(
            'assemblies',
            'costTable',
            'summaryByType',
            'month',
            'brandCompany',
            'vehicleType'
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
