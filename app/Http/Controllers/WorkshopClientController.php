<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\StoreWorkshopClientRequest;
use App\Http\Requests\Workshop\UpdateWorkshopClientRequest;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovements;
use App\Models\Location;
use App\Models\OrderMovement;
use App\Models\Person;
use App\Models\Profile;
use App\Models\Role;
use App\Models\SalesMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopMovement;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WorkshopClientController extends Controller
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
        [$branchId, $companyId] = $this->branchScope();
        $branch = Branch::query()->findOrFail($branchId);

        $search = trim((string) $request->input('search', ''));
        $type = (string) $request->input('type', '');
        $roleId = (int) $request->input('role_id', 0);

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->when($type !== '', function ($query) use ($type) {
                if ($type === 'CORPORATIVO') {
                    $query->where('person_type', 'RUC');
                    return;
                }
                if ($type === 'NATURAL') {
                    $query->where('person_type', '!=', 'RUC');
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('document_number', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                });
            })
            ->when($roleId > 0, function ($query) use ($roleId, $branchId) {
                $query->whereHas('roles', function ($roleQuery) use ($roleId, $branchId) {
                    $roleQuery->where('roles.id', $roleId)
                        ->where('role_person.branch_id', $branchId);
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $profiles = Profile::query()->orderBy('name')->get(['id', 'name']);

        return view('workshop.clients.index', [
            'clients' => $clients,
            'search' => $search,
            'type' => $type,
            'roleId' => $roleId,
            'roles' => $roles,
            'profiles' => $profiles,
            'selectedRoleIds' => old('roles', []),
            'selectedProfileId' => old('profile_id'),
            'userName' => old('user_name'),
        ] + $this->getLocationData(null, $branch->location_id));
    }

    public function store(StoreWorkshopClientRequest $request): RedirectResponse
    {
        [$branchId] = $this->branchScope();
        $data = $request->validated();
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, null);

        DB::transaction(function () use ($branchId, $data, $roleIds, $hasUserRole, $userData) {
            $person = Person::query()->create(array_merge(
                $data,
                ['branch_id' => $branchId]
            ));
            $this->syncRoles($person, $roleIds, $branchId);

            if ($hasUserRole) {
                User::create([
                    'name' => $userData['user_name'],
                    'email' => $person->email,
                    'password' => Hash::make($userData['password']),
                    'person_id' => $person->id,
                    'profile_id' => $userData['profile_id'],
                ]);
            }
        });

        return back()->with('status', 'Cliente registrado correctamente.');
    }

    public function update(UpdateWorkshopClientRequest $request, Person $person): RedirectResponse
    {
        [$branchId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);
        $data = $request->validated();
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, $person);

        DB::transaction(function () use ($person, $branchId, $data, $roleIds, $hasUserRole, $userData) {
            $person->update($data);
            $this->syncRoles($person, $roleIds, $branchId);

            if ($hasUserRole) {
                $user = $person->user;
                if ($user) {
                    $user->update([
                        'name' => $userData['user_name'],
                        'email' => $person->email,
                        'profile_id' => $userData['profile_id'],
                    ]);
                    if (!empty($userData['password'])) {
                        $user->update([
                            'password' => Hash::make($userData['password']),
                        ]);
                    }
                } else {
                    User::create([
                        'name' => $userData['user_name'],
                        'email' => $person->email,
                        'password' => Hash::make($userData['password']),
                        'person_id' => $person->id,
                        'profile_id' => $userData['profile_id'],
                    ]);
                }
            }
        });

        return back()->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        [$branchId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);

        $hasVehicles = Vehicle::query()->where('client_person_id', $person->id)->exists();
        $hasWorkshopOrders = WorkshopMovement::query()->where('client_person_id', $person->id)->exists();
        $hasSales = SalesMovement::query()
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id))
            ->exists();

        if ($hasVehicles || $hasWorkshopOrders || $hasSales) {
            return back()->withErrors([
                'error' => 'No se puede eliminar cliente con vehiculos, ordenes de servicio o ventas asociadas.',
            ]);
        }

        $person->delete();

        return back()->with('status', 'Cliente eliminado correctamente.');
    }

    public function show(Request $request, Person $person)
    {
        [$branchId, $companyId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);

        $vehicles = Vehicle::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        $appointments = Appointment::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        $orders = WorkshopMovement::query()
            ->with(['movement', 'vehicle'])
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $sales = SalesMovement::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $purchases = OrderMovement::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $payments = CashMovements::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $totalOrders = (float) $orders->sum('total');
        $totalPaidOrders = (float) $orders->sum('paid_total');
        $debtOrders = max(0, $totalOrders - $totalPaidOrders);
        $totalSales = (float) $sales->sum('total');
        $totalPurchases = (float) $purchases->sum('total');
        $totalPayments = (float) $payments->sum('total');

        return view('workshop.clients.history', compact(
            'person',
            'vehicles',
            'appointments',
            'orders',
            'sales',
            'purchases',
            'payments',
            'totalOrders',
            'totalPaidOrders',
            'debtOrders',
            'totalSales',
            'totalPurchases',
            'totalPayments'
        ));
    }

    private function assertClientScope(Person $person, int $branchId): void
    {
        if ((int) $person->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function validateRoles(Request $request): array
    {
        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        return array_values(array_unique(array_map('intval', $validated['roles'] ?? [])));
    }

    private function validateUserData(Request $request, bool $hasUserRole, ?Person $person): array
    {
        if (!$hasUserRole) {
            return [];
        }

        $rules = [
            'user_name' => ['required', 'string', 'max:255'],
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
        ];

        if ($person && $person->user) {
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $request->validate($rules);
    }

    private function syncRoles(Person $person, array $roleIds, int $branchId): void
    {
        $syncData = [];
        foreach ($roleIds as $roleId) {
            $syncData[$roleId] = ['branch_id' => $branchId];
        }
        $person->roles()->sync($syncData);
    }

    private function branchScope(): array
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        return [$branchId, (int) $branch->company_id];
    }

    private function getLocationData(?Person $person = null, ?int $defaultLocationId = null): array
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

        $selectedDistrictId = $person?->location_id ?? $defaultLocationId;
        $selectedProvinceId = null;
        $selectedDepartmentId = null;

        if ($selectedDistrictId) {
            $district = Location::find($selectedDistrictId);
            if ($district) {
                $province = $district->parent;
                $selectedProvinceId = $province?->id;
                $selectedDepartmentId = $province?->parent_location_id;
            }
        }

        return [
            'departments' => $departments,
            'provinces' => $provinces,
            'districts' => $districts,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedProvinceId' => $selectedProvinceId,
            'selectedDistrictId' => $selectedDistrictId,
        ];
    }
}
