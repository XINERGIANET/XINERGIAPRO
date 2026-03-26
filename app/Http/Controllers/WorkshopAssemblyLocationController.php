<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\WorkshopAssemblyLocation;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopAssemblyLocationController extends Controller
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
        [$branchId, $companyId] = $this->scope();
        $search = trim((string) $request->input('search', ''));

        $locations = WorkshopAssemblyLocation::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('address', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('workshop.assembly_locations.index', compact('locations', 'search'));
    }

    public function store(Request $request)
    {
        [$branchId, $companyId] = $this->scope();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'apply_to_all_branches' => ['nullable', 'boolean'],
        ]);

        $location = WorkshopAssemblyLocation::query()->create([
            'company_id' => $companyId,
            'branch_id' => empty($validated['apply_to_all_branches']) ? $branchId : null,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'location' => $location
            ]);
        }

        return back()->with('status', 'Ubicación creada correctamente.');
    }

    public function update(Request $request, WorkshopAssemblyLocation $assemblyLocation): RedirectResponse
    {
        [$branchId, $companyId] = $this->scope();
        abort_unless((int) $assemblyLocation->company_id === $companyId, 403);
        abort_unless($assemblyLocation->branch_id === null || (int) $assemblyLocation->branch_id === $branchId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $assemblyLocation->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return back()->with('status', 'Ubicación actualizada.');
    }

    public function destroy(WorkshopAssemblyLocation $assemblyLocation): RedirectResponse
    {
        [$branchId, $companyId] = $this->scope();
        abort_unless((int) $assemblyLocation->company_id === $companyId, 403);
        abort_unless($assemblyLocation->branch_id === null || (int) $assemblyLocation->branch_id === $branchId, 403);

        $assemblyLocation->delete();

        return back()->with('status', 'Ubicación eliminada.');
    }

    private function scope(): array
    {
        $branchId = (int) session('branch_id');
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');

        if ($branchId <= 0 || $companyId <= 0) {
            abort(403, 'No se pudo resolver la sucursal activa.');
        }

        return [$branchId, $companyId];
    }
}
