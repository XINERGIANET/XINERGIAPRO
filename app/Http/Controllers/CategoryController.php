<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
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

        $categories = Category::query()
            ->when($search, function ($query) use ($search) {
                $query->where('description', 'ILIKE', "%{$search}%")
                    ->orWhere('abbreviation', 'ILIKE', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'description'  => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'image'        => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
    
            $path = $request->file('image')->store('category', 'public');
            $data['image'] = $path;
        }
        
        Category::create($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria creada correctamente.');
    }

    public function edit(Request $request, Category $category)
    {
        return view('categories.edit', [
            'category' => $category,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'description'  => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'image'        => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }


            $path = $request->file('image')->store('category', 'public');
            $data['image'] = $path;
        }
        
        $category->update($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria actualizada correctamente.');
    }

    public function destroy(Request $request, Category $category)
    {
        $category->delete();
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria eliminada correctamente.');
    }
}
