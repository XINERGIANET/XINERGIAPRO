<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        ProductType::ensureDefaultsForBranch($branchId);

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $viewId = $request->input('view_id');
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

        $productTypes = ProductType::query()
            ->where('branch_id', $branchId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%")
                        ->orWhere('behavior', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('product-types.index', compact('productTypes', 'search', 'perPage', 'viewId', 'operaciones'));
    }

    public function store(Request $request)
    {
        $branchId = (int) session('branch_id');
        ProductType::ensureDefaultsForBranch($branchId);

        ProductType::query()->create($this->validateData($request, $branchId));

        return redirect()
            ->route('product-types.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Tipo de producto creado correctamente.');
    }

    public function edit(Request $request, ProductType $productType)
    {
        $this->assertScope($productType);

        return view('product-types.edit', [
            'productType' => $productType,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, ProductType $productType)
    {
        $this->assertScope($productType);

        $productType->update($this->validateData($request, (int) $productType->branch_id, $productType));

        return redirect()
            ->route('product-types.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Tipo de producto actualizado correctamente.');
    }

    public function destroy(Request $request, ProductType $productType)
    {
        $this->assertScope($productType);

        DB::transaction(function () use ($productType) {
            $productType->products()->update([
                'product_type_id' => null,
                'updated_at' => now(),
            ]);

            $productType->forceDelete();
        });

        return redirect()
            ->route('product-types.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Tipo de producto eliminado correctamente.');
    }

    private function validateData(Request $request, int $branchId, ?ProductType $productType = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('product_types', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($productType?->id),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'behavior' => ['required', Rule::in(['SELLABLE', 'SUPPLY'])],
            'status' => ['required', 'boolean'],
        ]) + ['branch_id' => $branchId];
    }

    private function assertScope(ProductType $productType): void
    {
        abort_unless((int) $productType->branch_id === (int) session('branch_id'), 403);
    }
}
