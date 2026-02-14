<?php

namespace App\Http\Controllers;

use App\Models\WorkshopService;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopServiceCatalogController extends Controller
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
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);
        $search = trim((string) $request->input('search', ''));

        $services = WorkshopService::query()
            ->when($companyId > 0, fn ($query) => $query->where(function ($inner) use ($companyId) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->when($branchId > 0, fn ($query) => $query->where(function ($inner) use ($branchId) {
                $inner->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->when($search !== '', fn ($query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('workshop.services.index', compact('services', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:preventivo,correctivo'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        WorkshopService::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'base_price' => $validated['base_price'],
            'estimated_minutes' => $validated['estimated_minutes'],
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return back()->with('status', 'Servicio registrado correctamente.');
    }

    public function update(Request $request, WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:preventivo,correctivo'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $service->update($validated);

        return back()->with('status', 'Servicio actualizado correctamente.');
    }

    public function destroy(WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);
        $service->delete();

        return back()->with('status', 'Servicio eliminado correctamente.');
    }

    private function assertServiceScope(WorkshopService $service): void
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        if ($service->company_id && $companyId > 0 && (int) $service->company_id !== $companyId) {
            abort(404);
        }

        if ($service->branch_id && $branchId > 0 && (int) $service->branch_id !== $branchId) {
            abort(404);
        }
    }
}

