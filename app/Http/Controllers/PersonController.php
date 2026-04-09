<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuOption;
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
            'selectedProfileId' => old('profile_id'),
            'userName' => old('user_name'),
            'operaciones' => $operaciones,
            'firstNameRequired' => strtoupper((string) $this->branchParameter('Nombres obligatorios', $branch->id, 'Si')) === 'SI',
            'lastNameRequired' => strtoupper((string) $this->branchParameter('Apellidos obligatorios', $branch->id, 'Si')) === 'SI',
        ] + $this->getLocationData(null, $branch->location_id));
    }

    public function profileMenuOptions(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $validated = $request->validate([
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
        ]);
        $profileId = (int) $validated['profile_id'];

        $ids = DB::table('user_permission')
            ->where('profile_id', $profileId)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->pluck('menu_option_id');

        $options = MenuOption::query()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($options);
    }

    public function apiReniec(Request $request)
    {
        $dni = (string) $request->query('dni', '');
        if (!preg_match('/^\d{8}$/', $dni)) {
            return response()->json([
                'status' => false,
                'message' => 'DNI invalido.',
            ], 422);
        }

        $response = Http::timeout(15)->get((string) config('apireniec.url'), [
            'document' => $dni,
            'key' => (string) config('apireniec.key'),
        ]);

        if (!$response->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo consultar RENIEC.',
            ], 422);
        }

        $data = (array) $response->json();
        $estado = (bool) ($data['estado'] ?? $data['status'] ?? false);
        $resultado = (array) ($data['resultado'] ?? []);
        $mensaje = (string) ($data['mensaje'] ?? $data['message'] ?? '');

        if ($estado && $resultado === []) {
            $hasPersonFields = ($data['nombres'] ?? '') !== ''
                || ($data['apellido_paterno'] ?? '') !== ''
                || ($data['apellido_materno'] ?? '') !== ''
                || ($data['nombre_completo'] ?? '') !== ''
                || ($data['name'] ?? '') !== '';
            if ($hasPersonFields) {
                $resultado = $data;
            }
        }

        if (!$estado || $resultado === []) {
            return response()->json([
                'status' => false,
                'message' => $mensaje !== '' ? $mensaje : 'No se encontro informacion en RENIEC.',
            ], 422);
        }

        $id = (string) ($resultado['id'] ?? $dni);
        $nombres = trim((string) ($resultado['nombres'] ?? ''));
        $apellidoPaterno = trim((string) ($resultado['apellido_paterno'] ?? ($resultado['apellidoPaterno'] ?? '')));
        $apellidoMaterno = trim((string) ($resultado['apellido_materno'] ?? ($resultado['apellidoMaterno'] ?? '')));
        $codigoVerificacion = trim((string) ($resultado['codigo_verificacion'] ?? ($resultado['codigoVerificacion'] ?? '')));
        $fechaNacimiento = $this->normalizeReniecDate((string) ($resultado['fecha_nacimiento'] ?? ($resultado['fechaNacimiento'] ?? '')));
        $genero = $this->normalizeReniecGender((string) ($resultado['genero'] ?? ($resultado['sexo'] ?? '')));

        // Si el proveedor no separa nombres/apellidos, separamos desde "name".
        if ($nombres === '' && $apellidoPaterno === '' && $apellidoMaterno === '') {
            $full = trim((string) ($resultado['nombre_completo'] ?? ($resultado['name'] ?? '')));
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full) ?: [];
                if (count($parts) >= 4) {
                    $nombres = trim(implode(' ', array_slice($parts, 0, count($parts) - 2)));
                    $apellidoPaterno = (string) ($parts[count($parts) - 2] ?? '');
                    $apellidoMaterno = (string) ($parts[count($parts) - 1] ?? '');
                } elseif (count($parts) === 3) {
                    $nombres = (string) ($parts[0] ?? '');
                    $apellidoPaterno = (string) ($parts[1] ?? '');
                    $apellidoMaterno = (string) ($parts[2] ?? '');
                } elseif (count($parts) === 2) {
                    $nombres = (string) ($parts[0] ?? '');
                    $apellidoPaterno = (string) ($parts[1] ?? '');
                } elseif (count($parts) === 1) {
                    $nombres = (string) ($parts[0] ?? '');
                }
            }
        }

        $nombreCompleto = trim(implode(' ', array_filter([$nombres, $apellidoPaterno, $apellidoMaterno])));
        if ($nombreCompleto === '') {
            return response()->json([
                'status' => false,
                'message' => 'No se encontro informacion en RENIEC.',
            ], 422);
        }

        $apellidosUnificados = trim(implode(' ', array_filter([$apellidoPaterno, $apellidoMaterno])));

        return response()->json([
            'status' => true,
            'message' => $mensaje !== '' ? $mensaje : 'Encontrado',
            'id' => $id,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'nombre_completo' => (string) ($resultado['nombre_completo'] ?? $nombreCompleto),
            'codigo_verificacion' => $codigoVerificacion,
            'fecha_nacimiento' => $fechaNacimiento,
            'genero' => $genero,
            'first_name' => $nombres,
            'last_name' => $apellidosUnificados,
            'name' => $nombreCompleto,
        ]);
    }

    private function normalizeReniecDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $matches)) {
            return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function normalizeReniecGender(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'M', 'MASCULINO', 'HOMBRE', 'MALE' => 'MASCULINO',
            'F', 'FEMENINO', 'MUJER', 'FEMALE' => 'FEMENINO',
            default => '',
        };
    }

    public function apiRuc(Request $request)
    {
        $ruc = (string) $request->query('ruc', '');
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return response()->json([
                'status' => false,
                'message' => 'RUC invalido.',
            ], 422);
        }

        $response = Http::timeout(15)->get((string) config('apireniec.ruc_url'), [
            'document' => $ruc,
            'key' => (string) config('apireniec.key'),
        ]);

        if (!$response->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo consultar RUC.',
            ], 422);
        }

        $data = (array) $response->json();
        $estado = (bool) ($data['estado'] ?? $data['status'] ?? false);
        $resultado = (array) ($data['resultado'] ?? []);
        $mensaje = (string) ($data['mensaje'] ?? $data['message'] ?? '');

        if (!$estado || empty($resultado)) {
            return response()->json([
                'status' => false,
                'message' => $mensaje !== '' ? $mensaje : 'No se encontro informacion para el RUC ingresado.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => $mensaje !== '' ? $mensaje : 'Encontrado',
            'ruc' => (string) ($resultado['id'] ?? $ruc),
            'legal_name' => trim((string) ($resultado['razon_social'] ?? ($resultado['nombre'] ?? ''))),
            'trade_name' => trim((string) ($resultado['nombre_comercial'] ?? '')),
            'address' => trim((string) ($resultado['direccion'] ?? '')),
            'department' => trim((string) ($resultado['departamento'] ?? '')),
            'province' => trim((string) ($resultado['provincia'] ?? '')),
            'district' => trim((string) ($resultado['distrito'] ?? '')),
            'condition' => trim((string) ($resultado['condicion'] ?? '')),
            'taxpayer_status' => trim((string) ($resultado['estado'] ?? '')),
            'raw' => $resultado,
        ]);
    }

    public function store(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $data = $this->validatePerson($request, $branch);
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $data['address'] = trim((string) ($data['address'] ?? ''));
        $data['branch_id'] = $branch->id;
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, null, $branch);

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
                    'default_menu_option_id' => $userData['default_menu_option_id'] ?? null,
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
            'firstNameRequired' => strtoupper((string) $this->branchParameter('Nombres obligatorios', $branch->id, 'Si')) === 'SI',
            'lastNameRequired' => strtoupper((string) $this->branchParameter('Apellidos obligatorios', $branch->id, 'Si')) === 'SI',
        ] + $this->getLocationData($person));
    }

    public function update(Request $request, Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $data = $this->validatePerson($request, $branch, $person);
        $data['phone'] = (string) ($data['phone'] ?? '');
        $data['email'] = (string) ($data['email'] ?? '');
        $data['address'] = trim((string) ($data['address'] ?? ''));
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, $person, $branch);

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
                        'default_menu_option_id' => $userData['default_menu_option_id'] ?? null,
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
                        'default_menu_option_id' => $userData['default_menu_option_id'] ?? null,
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
        $firstNameRequired = $this->branchParameter('Nombres obligatorios', $branch->id, 'Si');
        $lastNameRequired = $this->branchParameter('Apellidos obligatorios', $branch->id, 'Si');

        $firstNameRule = (strtoupper((string) $firstNameRequired) === 'SI') ? 'required' : 'nullable';
        
        return $request->validate([
            'first_name' => [$firstNameRule, 'string', 'max:255'],
            'last_name' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request, $lastNameRequired) {
                    if (strtoupper((string) $lastNameRequired) === 'SI' && strtoupper((string) $request->input('person_type')) !== 'RUC' && trim((string) $value) === '') {
                        $fail('El campo apellidos es obligatorio.');
                    }
                },
            ],
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

                    // if ($exists) {
                    //     $fail('El documento ya existe en otra persona de la misma empresa.');
                    // }
                },
            ],
            'address' => ['nullable', 'string', 'max:255'],
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

    private function validateUserData(Request $request, bool $hasUserRole, ?Person $person, Branch $branch): array
    {
        if (!$hasUserRole) {
            return [];
        }

        $rules = [
            'user_name' => ['required', 'string', 'max:255'],
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
            'default_menu_option_id' => [
                'nullable',
                'integer',
                'exists:menu_option,id',
                function ($attribute, $value, $fail) use ($request, $branch) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $profileId = (int) $request->input('profile_id');
                    if ($profileId < 1) {
                        $fail('Seleccione un perfil valido para el menu por defecto.');

                        return;
                    }
                    $ok = DB::table('user_permission')
                        ->join('menu_option', 'menu_option.id', '=', 'user_permission.menu_option_id')
                        ->where('user_permission.profile_id', $profileId)
                        ->where('user_permission.branch_id', $branch->id)
                        ->whereNull('user_permission.deleted_at')
                        ->where('user_permission.status', 1)
                        ->where('user_permission.menu_option_id', (int) $value)
                        ->where('menu_option.status', 1)
                        ->exists();
                    if (!$ok) {
                        $fail('El menu por defecto no esta permitido para este perfil en esta sucursal.');
                    }
                },
            ],
        ];

        if ($person && $person->user) {
            $rules['password'] = ['nullable', 'string', 'confirmed'];
        } else {
            $rules['password'] = ['required', 'string', 'confirmed'];
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

    private function branchParameter(string $key, int $branchId, string $default): string
    {
        $parameter = \Illuminate\Support\Facades\DB::table('parameters')->where('description', $key)->first();
        if (!$parameter) {
            return $default;
        }

        $branchValue = \Illuminate\Support\Facades\DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        return $branchValue ?? $parameter->value ?? $default;
    }
}
