<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\StoreVehicleRequest;
use App\Http\Requests\Workshop\UpdateVehicleRequest;
use App\Models\Person;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopVehicleController extends Controller
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

        $vehicles = Vehicle::query()
            ->with(['client', 'branch', 'vehicleType'])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('brand', 'ILIKE', "%{$search}%")
                        ->orWhere('model', 'ILIKE', "%{$search}%")
                        ->orWhere('plate', 'ILIKE', "%{$search}%")
                        ->orWhere('vin', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $clients = Person::query()
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('roles', function ($query) use ($branchId) {
                $query->where('roles.id', 3);
                if ($branchId > 0) {
                    $query->where('role_person.branch_id', $branchId);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

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

        return view('workshop.vehicles.index', compact('vehicles', 'clients', 'search', 'vehicleTypes'));
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        $data = $request->validated();
        $vehicleType = VehicleType::query()->findOrFail((int) $data['vehicle_type_id']);
        $data['type'] = $vehicleType->name;

        Vehicle::query()->create(array_merge($data, [
            'company_id' => $branch->company_id,
            'branch_id' => $branchId,
            'status' => $request->input('status', 'active'),
            'current_mileage' => (int) $request->input('current_mileage', 0),
        ]));

        return back()->with('status', 'Vehiculo registrado correctamente.');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->assertVehicleScope($vehicle);
        $data = $request->validated();
        $vehicleType = VehicleType::query()->findOrFail((int) $data['vehicle_type_id']);
        $data['type'] = $vehicleType->name;
        $vehicle->update($data);

        return back()->with('status', 'Vehiculo actualizado correctamente.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->assertVehicleScope($vehicle);
        if ($vehicle->workshopMovements()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un vehiculo con ordenes de servicio asociadas.']);
        }
        if ($vehicle->appointments()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un vehiculo con citas asociadas.']);
        }
        $vehicle->delete();

        return back()->with('status', 'Vehiculo eliminado correctamente.');
    }

    private function assertVehicleScope(Vehicle $vehicle): void
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $vehicle->branch_id !== $branchId) {
            abort(404);
        }

        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        if ($branch && (int) $vehicle->company_id !== (int) $branch->company_id) {
            abort(404);
        }
    }
}

