<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SalesMovement;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\Request;

class WorkshopSalesRegisterController extends Controller
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
        $tab = strtolower((string) $request->input('tab', 'natural'));
        if (!in_array($tab, ['natural', 'corporativo'], true)) {
            $tab = 'natural';
        }

        $sales = SalesMovement::query()
            ->with(['movement', 'movement.person'])
            ->where('branch_id', $branchId)
            ->whereHas('movement', fn ($query) => $query->whereRaw("to_char(moved_at, 'YYYY-MM') = ?", [$month]))
            ->whereHas('movement.person', function ($query) use ($tab) {
                if ($tab === 'natural') {
                    $query->whereRaw("UPPER(COALESCE(person_type,'')) IN ('NATURAL','PERSONA NATURAL','PN')");
                } else {
                    $query->whereRaw("UPPER(COALESCE(person_type,'')) IN ('JURIDICA','PERSONA JURIDICA','EMPRESA','CORPORATIVO','PJ')");
                }
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $total = (float) $sales->getCollection()->sum('total');
        $subtotal = (float) $sales->getCollection()->sum('subtotal');
        $tax = (float) $sales->getCollection()->sum('tax');

        return view('workshop.sales-register.index', compact('sales', 'month', 'tab', 'total', 'subtotal', 'tax', 'branch'));
    }
}

