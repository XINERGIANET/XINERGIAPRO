<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Operation;
use Illuminate\Http\Request;

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

        Company::create($data);

        $redirectParams = $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [];

        return redirect()->route('admin.companies.index', $redirectParams)
            ->with('status', 'Empresa creada correctamente.');
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
}
