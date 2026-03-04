<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use App\Models\Operation;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BoxController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
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

        $cash = CashRegister::query()
            ->where('branch_id', $branchId)
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('series', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('number', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return view('boxes.index', [
            'title' => 'Cajas Registradoras',
            'cash'  => $cash,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $validated = $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cash_registers', 'number')->where(function ($query) use ($branchId) {
                    return $query->where('branch_id', $branchId);
                }),
            ],
            'series' => 'required|string|max:10',
            'status' => 'required|boolean',
        ], [
            'number.required' => 'El número de caja es obligatorio.',
            'number.unique'   => 'Este número de caja ya existe.',
            'series.required' => 'La serie es obligatoria.',
        ]);
        
        try {
            CashRegister::create([
                'number'    => $validated['number'],
                'series'    => $validated['series'],
                'status'    => $validated['status'],
                'branch_id' => $branchId,
            ]);
            $viewId = $request->input('view_id');
            
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja creada correctamente');

        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al crear la caja: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, CashRegister $box)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $cash = CashRegister::query()
            ->where('branch_id', $branchId)
            ->paginate(10); 
        
        return view('boxes.edit', [
            'title' => 'Cajas',
            'cash'  => $cash,
            'box'   => $box,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, CashRegister $box)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $validated = $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cash_registers', 'number')
                    ->ignore($box->id)
                    ->where(function ($query) use ($branchId) {
                        return $query->where('branch_id', $branchId);
                    }),
            ],
            'series' => 'required|string|max:10',
            'status' => 'required|boolean',
        ]);
        
        try {
            $box->update([
                'number' => $validated['number'],
                'series' => $validated['series'],
                'status' => $validated['status'],
            ]);
            $viewId = $request->input('view_id');
            
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja actualizada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar la caja: ' . $e->getMessage());
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()])
            ->withInput();
        }
    }

    public function destroy(Request $request, CashRegister $box)
    {
        try {
            $box->delete();
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja eliminada correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}
