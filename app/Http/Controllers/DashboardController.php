<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $branchId = (int) $request->session()->get('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (optional($request->user())->person->branch_id ?? 0);
        }
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        $now = now();

        $baseQuery = WorkshopMovement::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId));

        $activeStatuses = ['draft', 'diagnosis', 'awaiting_approval', 'approved', 'in_progress'];
        $closedStatuses = ['finished', 'delivered'];
        $cancelledStatuses = ['cancelled'];

        $ordersActive = (clone $baseQuery)->whereIn('status', $activeStatuses)->count();
        $ordersClosedToday = (clone $baseQuery)
            ->whereIn('status', $closedStatuses)
            ->where(function ($query) use ($now) {
                $query->whereDate('finished_at', $now->toDateString())
                    ->orWhere(function ($inner) use ($now) {
                        $inner->whereNull('finished_at')
                            ->whereDate('updated_at', $now->toDateString());
                    });
            })
            ->count();
        $ordersPendingApproval = (clone $baseQuery)->where('status', 'awaiting_approval')->count();
        $vehiclesInWorkshop = (clone $baseQuery)->whereNotIn('status', array_merge($closedStatuses, $cancelledStatuses))->count();

        $todayInvoiced = (clone $baseQuery)
            ->whereDate('updated_at', $now->toDateString())
            ->sum('paid_total');
        $pendingCollection = (clone $baseQuery)->get(['total', 'paid_total'])->sum(function ($row) {
            return max(0, (float) $row->total - (float) $row->paid_total);
        });

        $appointmentsToday = Appointment::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('start_at', $now->toDateString())
            ->count();

        $statusBreakdown = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $monthStart = $now->copy()->startOfMonth();
        $topServices = WorkshopMovementDetail::query()
            ->join('workshop_movements as wm', 'wm.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->leftJoin('workshop_services as ws', 'ws.id', '=', 'workshop_movement_details.service_id')
            ->whereIn('workshop_movement_details.line_type', ['SERVICE', 'SERVCE'])
            ->whereDate('wm.created_at', '>=', $monthStart->toDateString())
            ->when($companyId > 0, fn ($q) => $q->where('wm.company_id', $companyId))
            ->when($branchId > 0, fn ($q) => $q->where('wm.branch_id', $branchId))
            ->groupBy('ws.name', 'workshop_movement_details.description')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get([
                DB::raw("COALESCE(ws.name, workshop_movement_details.description, 'Servicio') as name"),
                DB::raw('COUNT(*) as qty'),
                DB::raw('SUM(workshop_movement_details.total) as amount'),
            ]);

        $days = collect(range(6, 0))->map(fn ($offset) => $now->copy()->subDays($offset));
        $incomeByDay = [];
        foreach ($days as $day) {
            $incomeByDay[] = [
                'label' => $day->format('d/m'),
                'amount' => (float) (clone $baseQuery)
                    ->whereDate('updated_at', $day->toDateString())
                    ->sum('paid_total'),
            ];
        }

        $recentOrders = (clone $baseQuery)
            ->with(['movement:id,number,moved_at', 'vehicle:id,brand,model,plate', 'client:id,first_name,last_name,document_number'])
            ->latest('id')
            ->limit(6)
            ->get();

        $dashboardData = [
            'branchName' => (string) (Branch::query()->where('id', $branchId)->value('legal_name') ?? 'Sucursal actual'),
            'ordersActive' => $ordersActive,
            'ordersClosedToday' => $ordersClosedToday,
            'ordersPendingApproval' => $ordersPendingApproval,
            'vehiclesInWorkshop' => $vehiclesInWorkshop,
            'appointmentsToday' => $appointmentsToday,
            'todayInvoiced' => (float) $todayInvoiced,
            'pendingCollection' => (float) $pendingCollection,
            'statusBreakdown' => $statusBreakdown,
            'topServices' => $topServices,
            'incomeByDay' => $incomeByDay,
            'recentOrders' => $recentOrders,
        ];

        return view('pages.dashboard.ecommerce', compact('dashboardData'));
    }
}
