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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $perPage = (int) $request->input('per_page', 10);

        $vehicles = $this->vehiclesFilteredQuery($branchId, $companyId, $search)
            ->with(['client', 'branch', 'vehicleType'])
            ->orderByDesc('id')
            ->paginate($perPage)
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

        return view('workshop.vehicles.index', [
            'vehicles' => $vehicles,
            'clients' => $clients,
            'search' => $search,
            'vehicleTypes' => $vehicleTypes,
            'per_page' => $perPage,
        ]);
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

    public function destroyBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_mode' => ['required', 'string', Rule::in(['ids', 'filter'])],
            'ids' => ['required_if:bulk_mode,ids', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'search' => ['nullable', 'string'],
        ]);

        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        if ($validated['bulk_mode'] === 'filter') {
            $search = trim((string) ($validated['search'] ?? ''));
            $idList = $this->vehiclesFilteredQuery($branchId, $companyId, $search)->pluck('id')->all();
        } else {
            $idList = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));
        }

        if ($idList === []) {
            return back()->with('error', 'No hay vehiculos para eliminar.');
        }

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($idList, &$deleted, &$skipped) {
            foreach (array_chunk($idList, 150) as $chunk) {
                $vehicles = Vehicle::query()->whereIn('id', $chunk)->get();
                foreach ($vehicles as $vehicle) {
                    if (!$this->vehicleMatchesSessionScope($vehicle)) {
                        $skipped++;

                        continue;
                    }
                    if ($vehicle->workshopMovements()->exists()) {
                        $skipped++;

                        continue;
                    }
                    if ($vehicle->appointments()->exists()) {
                        $skipped++;

                        continue;
                    }
                    $vehicle->delete();
                    $deleted++;
                }
            }
        });

        $msg = "Se eliminaron {$deleted} vehiculo(s).";
        if ($skipped > 0) {
            $msg .= " Se omitieron {$skipped} (sin permiso, no encontrados, con OS o citas vinculadas).";
        }

        return back()->with('status', $msg);
    }

    private function assertVehicleScope(Vehicle $vehicle): void
    {
        if (!$this->vehicleMatchesSessionScope($vehicle)) {
            abort(404);
        }
    }

    private function vehicleMatchesSessionScope(Vehicle $vehicle): bool
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $vehicle->branch_id !== $branchId) {
            return false;
        }

        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        if ($branch && (int) $vehicle->company_id !== (int) $branch->company_id) {
            return false;
        }

        return true;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Vehicle>
     */
    private function vehiclesFilteredQuery(int $branchId, int $companyId, string $search)
    {
        return Vehicle::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('brand', 'ILIKE', "%{$search}%")
                        ->orWhere('model', 'ILIKE', "%{$search}%")
                        ->orWhere('plate', 'ILIKE', "%{$search}%")
                        ->orWhere('vin', 'ILIKE', "%{$search}%");
                });
            });
    }
}

