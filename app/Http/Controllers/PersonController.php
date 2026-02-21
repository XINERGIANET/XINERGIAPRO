<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Location;
use App\Models\Movement;
use App\Models\Operation;
use App\Models\Person;
use App\Models\Vehicle;
use App\Models\WorkshopMovement;
use App\Models\Profile;
use App\Models\Role;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PersonController extends Controller
{
    public function index(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $search = $request->input('search');
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
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
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $profiles = Profile::query()->orderBy('name')->get(['id', 'name']);

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $people = $branch->people()
            ->with(['location', 'user.profile'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('document_number', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('branches.people.index', [
            'company' => $company,
            'branch' => $branch,
            'people' => $people,
            'search' => $search,
            'perPage' => $perPage,
            'roles' => $roles,
            'profiles' => $profiles,
            'selectedRoleIds' => old('roles', []),
            'selectedProfileId' => old('profile_id'),
            'userName' => old('user_name'),
            'operaciones' => $operaciones,
        ] + $this->getLocationData(null, $branch->location_id));
    }
        public function apiReniec(Request $request)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('apireniec.token')
        ])->get(config('apireniec.url'), [
            'numero' => $request->dni
        ]);

        $data = $response->json();

        if ($response->successful()) {

            return response()->json([
                'status' => true,
                'name' => $data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno']
            ]);
        } else {

            return response()->json([
                'status' => false
            ]);
        }
    }

    public function store(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $data = $this->validatePerson($request, $branch);
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $data['branch_id'] = $branch->id;
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, null);

        DB::transaction(function () use ($branch, $data, $roleIds, $hasUserRole, $userData) {
            $person = $branch->people()->create($data);
            $this->syncRoles($person, $roleIds, $branch->id);

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

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Personal creado correctamente.');
    }

    public function edit(Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $profiles = Profile::query()->orderBy('name')->get(['id', 'name']);
        $selectedRoleIds = old('roles', $person->roles()->pluck('roles.id')->all());
        $user = $person->user;

        return view('branches.people.edit', [
            'company' => $company,
            'branch' => $branch,
            'person' => $person,
            'roles' => $roles,
            'profiles' => $profiles,
            'selectedRoleIds' => $selectedRoleIds,
            'selectedProfileId' => old('profile_id', $user?->profile_id),
            'userName' => old('user_name', $user?->name),
        ] + $this->getLocationData($person));
    }

    public function update(Request $request, Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $data = $this->validatePerson($request, $branch, $person);
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, $person);

        DB::transaction(function () use ($person, $branch, $data, $roleIds, $hasUserRole, $userData) {
            $person->update($data);
            $this->syncRoles($person, $roleIds, $branch->id);

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

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Personal actualizado correctamente.');
    }

    public function destroy(Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);

        $hasVehicles = Vehicle::query()->where('client_person_id', $person->id)->exists();
        $hasWorkshopOrders = WorkshopMovement::query()->where('client_person_id', $person->id)->exists();
        $hasSales = Movement::query()
            ->where('person_id', $person->id)
            ->whereHas('salesMovement')
            ->exists();

        if ($hasVehicles || $hasWorkshopOrders || $hasSales) {
            return back()->withErrors([
                'error' => 'No se puede eliminar cliente con vehiculos, ordenes de servicio o ventas asociadas.',
            ]);
        }

        $person->delete();

        $viewId = request()->input('view_id');

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Personal eliminado correctamente.');
    }

    public function updatePassword(Request $request, Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $person->user;
        if (!$user) {
            $viewId = $request->input('view_id');

            return redirect()
                ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
                ->with('status', 'La persona no tiene usuario asociado.');
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Contraseña actualizada correctamente.');
    }

    private function validatePerson(Request $request, Branch $branch, ?Person $currentPerson = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'string', 'max:30'],
            'person_type' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'document_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($branch, $currentPerson) {
                    $exists = Person::query()
                        ->join('branches', 'branches.id', '=', 'people.branch_id')
                        ->where('branches.company_id', $branch->company_id)
                        ->where('people.document_number', (string) $value)
                        ->whereNull('people.deleted_at')
                        ->when($currentPerson, fn ($query) => $query->where('people.id', '!=', $currentPerson->id))
                        ->exists();

                    if ($exists) {
                        $fail('El documento ya existe en otra persona de la misma empresa.');
                    }
                },
            ],
            'address' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);
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

    private function resolveBranch(Company $company, Branch $branch): Branch
    {
        if ($branch->company_id !== $company->id) {
            abort(404);
        }

        return $branch;
    }

    private function resolvePerson(Branch $branch, Person $person): Person
    {
        if ($person->branch_id !== $branch->id) {
            abort(404);
        }

        return $person;
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
