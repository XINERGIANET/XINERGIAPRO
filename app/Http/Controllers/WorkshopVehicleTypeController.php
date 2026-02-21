<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\VehicleType;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopVehicleTypeController extends Controller
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
        $branch = Branch::query()->findOrFail($branchId);
        $companyId = (int) $branch->company_id;
        $search = trim((string) $request->input('search', ''));

        $types = VehicleType::query()
            ->where(function ($query) use ($companyId, $branchId) {
                $query->where(function ($inner) use ($companyId, $branchId) {
                    $inner->where('company_id', $companyId)
                        ->where('branch_id', $branchId);
                })->orWhereNull('company_id');
            })
            ->when($search !== '', fn ($query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->orderBy('company_id')
            ->orderBy('branch_id')
            ->orderBy('order_num')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('workshop.vehicle-types.index', compact('types', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);
        $companyId = (int) $branch->company_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $name = mb_strtolower(trim((string) $validated['name']));
        VehicleType::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $name,
            ],
            [
                'order_num' => (int) ($validated['order_num'] ?? 0),
                'active' => (bool) ($validated['active'] ?? true),
            ]
        );

        return back()->with('status', 'Tipo de vehiculo registrado correctamente.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $vehicleType->update([
            'name' => mb_strtolower(trim((string) $validated['name'])),
            'order_num' => (int) ($validated['order_num'] ?? 0),
            'active' => (bool) ($validated['active'] ?? false),
        ]);

        return back()->with('status', 'Tipo de vehiculo actualizado correctamente.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        if ($vehicleType->vehicles()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un tipo de vehiculo en uso.']);
        }

        $vehicleType->delete();
        return back()->with('status', 'Tipo de vehiculo eliminado correctamente.');
    }

    private function assertVehicleTypeScope(VehicleType $vehicleType): void
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        if ($vehicleType->company_id === null) {
            abort(403, 'No se puede modificar tipos globales.');
        }

        if ((int) $vehicleType->company_id !== (int) $branch->company_id || (int) $vehicleType->branch_id !== $branchId) {
            abort(404);
        }
    }
}

