<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Person;
use App\Models\WorkshopPurchaseRecord;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\Request;

class WorkshopPurchaseController extends Controller
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
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        $month = (string) $request->input('month', now()->format('Y-m'));
        $supplierId = (int) $request->input('supplier_id', 0);
        $documentKind = strtoupper(trim((string) $request->input('document_kind', '')));
        $scopeBranchId = (int) $request->input('branch_id', $branchId);
        $perPage = (int) $request->input('per_page', 10);

        $isAdmin = ((int) (auth()->user()?->profile_id ?? 0) === 1) || str_contains(strtoupper((string) (auth()->user()?->profile?->name ?? '')), 'ADMIN');
        if (!$isAdmin) {
            $scopeBranchId = $branchId;
        }

        $branchIds = Branch::query()
            ->where('company_id', $branch->company_id)
            ->pluck('id')
            ->all();

        if (!in_array($scopeBranchId, $branchIds, true)) {
            $scopeBranchId = $branchId;
        }

        $records = WorkshopPurchaseRecord::query()
            ->with(['movement', 'supplier', 'movement.documentType', 'movement.purchaseMovement'])
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $scopeBranchId)
            ->whereRaw("to_char(issued_at, 'YYYY-MM') = ?", [$month])
            ->when($supplierId > 0, fn ($query) => $query->where('supplier_person_id', $supplierId))
            ->when($documentKind !== '', fn ($query) => $query->where('document_kind', $documentKind))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $monthSummary = WorkshopPurchaseRecord::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $scopeBranchId)
            ->whereRaw("to_char(issued_at, 'YYYY-MM') = ?", [$month])
            ->selectRaw('COUNT(*) as total_docs, COALESCE(SUM(total), 0) as total_amount')
            ->first();

        $pendingCreditTotal = WorkshopPurchaseRecord::query()
            ->join('movements', 'movements.id', '=', 'workshop_purchase_records.movement_id')
            ->join('purchase_movements', 'purchase_movements.movement_id', '=', 'movements.id')
            ->where('workshop_purchase_records.company_id', $branch->company_id)
            ->where('workshop_purchase_records.branch_id', $scopeBranchId)
            ->whereRaw("to_char(workshop_purchase_records.issued_at, 'YYYY-MM') = ?", [$month])
            ->where('purchase_movements.payment_type', 'CREDITO')
            ->sum('workshop_purchase_records.total');

        $suppliers = Person::query()
            ->where('branch_id', $scopeBranchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        $branches = Branch::query()
            ->where('company_id', $branch->company_id)
            ->orderBy('legal_name')
            ->selectRaw('id, legal_name as name, COALESCE(ruc, \'\') as code')
            ->get();

        return view('workshop.purchases.index', compact(
            'records',
            'suppliers',
            'branches',
            'month',
            'supplierId',
            'documentKind',
            'scopeBranchId',
            'isAdmin',
            'perPage',
            'monthSummary',
            'pendingCreditTotal'
        ));
    }
}
