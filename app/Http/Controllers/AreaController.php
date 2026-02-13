<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Operation;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $branchId = session('branch_id');
        $search = $request->input('search');
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

        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ILIKE', "%{$search}%")
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('legal_name', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('areas.index', compact('areas', 'operaciones', 'search', 'perPage'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ], [
            'name.required' => 'El nombre del area es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        try {
            Area::create([
                'name' => $validated['name'],
                'branch_id' => session('branch_id'),
            ]);

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area creada correctamente');
        } catch (\Exception $e) {
            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al crear el area: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, Area $area)
    {
        $viewId = $request->input('view_id');
        return view('areas.edit', compact('area', 'viewId'));
    }

    public function update(Request $request, Area $area)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ], [
            'name.required' => 'El nombre del area es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        try {
            $area->update([
                'name' => $validated['name'],
                'branch_id' => session('branch_id'),
            ]);

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area actualizada correctamente');
        } catch (\Exception $e) {
            \Log::error('Error al actualizar el area: ' . $e->getMessage());

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al actualizar el area: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, Area $area)
    {
        try {
            $area->delete();

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area eliminada correctamente');
        } catch (\Exception $e) {
            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al eliminar el area: ' . $e->getMessage()]);
        }
    }
}
