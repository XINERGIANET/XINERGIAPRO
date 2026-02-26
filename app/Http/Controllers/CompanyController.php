<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Operation;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
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
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $companies = Company::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('legal_name', 'ILIKE', "%{$search}%")
                        ->orWhere('tax_id', 'ILIKE', "%{$search}%")
                        ->orWhere('address', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('companies.index', [
            'companies' => $companies,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Empresas',
        ]);
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            $company = Company::create($data);

            $templateBranchId = (int) env('BRANCH_TEMPLATE_ID', 4);
            $templateBranch = Branch::query()->find($templateBranchId);
            $fallbackLocationId = $templateBranch?->location_id
                ?: DB::table('locations')->orderBy('id')->value('id');

            if (!$fallbackLocationId) {
                throw new \RuntimeException('No existe ubicación base para crear sucursal inicial.');
            }

            $branch = $company->branches()->create([
                'tax_id' => (string) $company->tax_id,
                'ruc' => (string) $company->tax_id,
                'legal_name' => (string) $company->legal_name,
                'logo' => null,
                'address' => (string) ($company->address ?: '-'),
                'location_id' => (int) $fallbackLocationId,
            ]);

            $this->replicateBranchConfiguration((int) $branch->id);
            $this->createDefaultBranchPersonAndUser($branch);
        });

        $redirectParams = $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [];

        return redirect()->route('admin.companies.index', $redirectParams)
            ->with('status', 'Empresa y sucursal inicial creadas correctamente.');
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        $company->update($data);

        $viewId = $request->input('view_id');

        return redirect()->route('admin.companies.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        $redirectParams = request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [];

        return redirect()->route('admin.companies.index', $redirectParams)
            ->with('status', 'Empresa eliminada correctamente.');
    }

    private function replicateBranchConfiguration(int $newBranchId): void
    {
        $templateBranchId = (int) env('BRANCH_TEMPLATE_ID', 4);
        if ($newBranchId === $templateBranchId) {
            return;
        }

        $now = now();

        $templateViews = DB::table('view_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['view_id']);

        foreach ($templateViews as $row) {
            $exists = DB::table('view_branch')
                ->where('branch_id', $newBranchId)
                ->where('view_id', $row->view_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('view_branch')->insert([
                    'view_id' => $row->view_id,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateProfiles = DB::table('profile_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['profile_id']);

        foreach ($templateProfiles as $row) {
            $exists = DB::table('profile_branch')
                ->where('branch_id', $newBranchId)
                ->where('profile_id', $row->profile_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('profile_branch')->insert([
                    'profile_id' => $row->profile_id,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateBranchOperations = DB::table('branch_operation')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['operation_id', 'status']);

        foreach ($templateBranchOperations as $row) {
            $exists = DB::table('branch_operation')
                ->where('branch_id', $newBranchId)
                ->where('operation_id', $row->operation_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('branch_operation')->insert([
                    'operation_id' => $row->operation_id,
                    'branch_id' => $newBranchId,
                    'status' => $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateOperationProfiles = DB::table('operation_profile_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['operation_id', 'profile_id', 'status']);

        foreach ($templateOperationProfiles as $row) {
            $exists = DB::table('operation_profile_branch')
                ->where('branch_id', $newBranchId)
                ->where('operation_id', $row->operation_id)
                ->where('profile_id', $row->profile_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('operation_profile_branch')->insert([
                    'operation_id' => $row->operation_id,
                    'profile_id' => $row->profile_id,
                    'branch_id' => $newBranchId,
                    'status' => $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateParameters = DB::table('branch_parameters')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['parameter_id', 'value']);

        foreach ($templateParameters as $row) {
            $exists = DB::table('branch_parameters')
                ->where('branch_id', $newBranchId)
                ->where('parameter_id', $row->parameter_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('branch_parameters')->insert([
                    'parameter_id' => $row->parameter_id,
                    'value' => $row->value,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templatePermissions = DB::table('user_permission as up')
            ->join('menu_option as mo', 'mo.id', '=', 'up.menu_option_id')
            ->where('up.branch_id', $templateBranchId)
            ->whereNull('up.deleted_at')
            ->where('mo.module_id', '!=', 1)
            ->get(['up.name', 'up.profile_id', 'up.menu_option_id', 'up.status']);

        foreach ($templatePermissions as $row) {
            $exists = DB::table('user_permission')
                ->where('branch_id', $newBranchId)
                ->where('profile_id', $row->profile_id)
                ->where('menu_option_id', $row->menu_option_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('user_permission')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $row->name,
                    'profile_id' => $row->profile_id,
                    'menu_option_id' => $row->menu_option_id,
                    'branch_id' => $newBranchId,
                    'status' => (bool) $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function createDefaultBranchPersonAndUser(Branch $branch): void
    {
        $generatedEmail = $branch->ruc . '@xinergia.local';

        $person = Person::create([
            'first_name' => $branch->legal_name,
            'last_name' => '',
            'person_type' => 'RUC',
            'phone' => '-',
            'email' => $generatedEmail,
            'document_number' => (string) $branch->ruc,
            'address' => $branch->address ?: '-',
            'location_id' => $branch->location_id,
            'branch_id' => $branch->id,
        ]);

        User::create([
            'name' => $branch->ruc,
            'email' => $generatedEmail,
            'password' => (string) $branch->ruc,
            'person_id' => $person->id,
            'profile_id' => 2,
        ]);

        $defaultRoleId = (int) env('BRANCH_DEFAULT_ROLE_ID', 1);
        if (!Role::where('id', $defaultRoleId)->exists()) {
            $defaultRoleId = (int) Role::query()->orderBy('id')->value('id');
        }

        if ($defaultRoleId > 0) {
            DB::table('role_person')->insert([
                'role_id' => $defaultRoleId,
                'person_id' => $person->id,
                'branch_id' => $branch->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
