<?php

namespace App\Http\Controllers;

use App\Models\WorkshopMovement;
use App\Models\Person;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WorkshopQuotationController extends Controller
{
    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);
        $profileId = (int) session('profile_id');
        $viewId = $request->input('view_id');

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $clientId = $request->input('client_id');

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

        $quotations = WorkshopMovement::query()
            ->with(['movement', 'vehicle', 'client', 'details', 'deletedDetails'])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            // Solo estados que pasan por cotización
            ->whereIn('status', ['awaiting_approval', 'approved', 'diagnosis'])
            ->when($clientId, fn ($query) => $query->where('client_person_id', $clientId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('movement', fn ($movementQuery) => $movementQuery->where('number', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('plate', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery
                            ->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn($q) => $q->where('roles.id', 3)) // Cliente
            ->orderBy('first_name')
            ->get();

        return view('workshop.quotations.index', compact('quotations', 'search', 'perPage', 'clients', 'clientId', 'operaciones'));
    }
}
