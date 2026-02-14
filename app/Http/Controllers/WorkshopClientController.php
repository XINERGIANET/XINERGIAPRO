<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\Vehicle;
use App\Models\WorkshopMovement;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\Request;

class WorkshopClientController extends Controller
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

    public function show(Request $request, Person $person)
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        if ((int) $person->branch_id !== $branchId) {
            abort(404);
        }

        $vehicles = Vehicle::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $branch->company_id)
            ->orderByDesc('id')
            ->get();

        $appointments = \App\Models\Appointment::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $branch->company_id)
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        $orders = WorkshopMovement::query()
            ->with(['movement', 'vehicle'])
            ->where('client_person_id', $person->id)
            ->where('company_id', $branch->company_id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $sales = SalesMovement::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $totalOrders = (float) $orders->sum('total');
        $totalPaidOrders = (float) $orders->sum('paid_total');
        $debtOrders = max(0, $totalOrders - $totalPaidOrders);

        return view('workshop.clients.history', compact(
            'person',
            'vehicles',
            'appointments',
            'orders',
            'sales',
            'totalOrders',
            'totalPaidOrders',
            'debtOrders'
        ));
    }
}
