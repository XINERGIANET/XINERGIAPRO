<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovements;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\WorkshopMaintenanceReminder;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\WorkshopMovementTechnician;
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
        $dateFrom = Carbon::parse((string) $request->input('date_from', now()->startOfWeek()->toDateString()))->startOfDay();
        $dateTo = Carbon::parse((string) $request->input('date_to', now()->toDateString()))->endOfDay();
        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }
        $now = now();
        $today = now()->toDateString();

        $baseQuery = WorkshopMovement::query()
            ->from('workshop_movements')
            ->when($companyId > 0, fn ($q) => $q->where('workshop_movements.company_id', $companyId))
            ->when($branchId > 0, fn ($q) => $q->where('workshop_movements.branch_id', $branchId));

        $activeStatuses = ['draft', 'diagnosis', 'awaiting_approval', 'approved', 'in_progress'];
        $closedStatuses = ['finished', 'delivered'];
        $cancelledStatuses = ['cancelled'];

        $rangeQuery = (clone $baseQuery)
            ->whereBetween('intake_date', [$dateFrom, $dateTo]);

        $ordersActive = (clone $baseQuery)->whereIn('status', $activeStatuses)->count();
        $ordersClosedToday = (clone $baseQuery)
            ->whereIn('status', $closedStatuses)
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('finished_at', [$dateFrom, $dateTo])
                    ->orWhere(function ($inner) use ($dateFrom, $dateTo) {
                        $inner->whereNull('finished_at')
                            ->whereBetween('updated_at', [$dateFrom, $dateTo]);
                    });
            })
            ->count();
        $ordersPendingApproval = (clone $baseQuery)->where('status', 'awaiting_approval')->count();
        $vehiclesInWorkshop = (clone $baseQuery)->whereNotIn('status', array_merge($closedStatuses, $cancelledStatuses))->count();

        $todayInvoiced = SalesMovement::query()
            ->where('branch_id', $branchId)
            ->whereHas('movement', fn ($query) => $query->whereBetween('moved_at', [$dateFrom, $dateTo]))
            ->sum('total');
            
        $pendingCollection = (clone $baseQuery)->get(['total', 'paid_total'])->sum(function ($row) {
            return max(0, (float) $row->total - (float) $row->paid_total);
        });

        $appointmentsToday = Appointment::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('start_at', [$dateFrom, $dateTo])
            ->count();

        $statusBreakdown = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $topServices = WorkshopMovementDetail::query()
            ->join('workshop_movements as wm', 'wm.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->leftJoin('workshop_services as ws', 'ws.id', '=', 'workshop_movement_details.service_id')
            ->whereIn('workshop_movement_details.line_type', ['SERVICE', 'SERVCE'])
            ->whereBetween('wm.created_at', [$dateFrom, $dateTo])
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

        $days = collect();
        $cursor = $dateFrom->copy()->startOfDay();
        $lastDay = $dateTo->copy()->startOfDay();
        while ($cursor->lte($lastDay)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }
        $incomeByDay = [];
        foreach ($days as $day) {
            $incomeByDay[] = [
                'label' => $day->format('d/m'),
                'amount' => (float) SalesMovement::query()
                    ->where('branch_id', $branchId)
                    ->whereHas('movement', fn ($query) => $query->whereDate('moved_at', $day->toDateString()))
                    ->sum('total'),
            ];
        }

        $salesToday = SalesMovement::query()
            ->where('branch_id', $branchId)
            ->whereHas('movement', fn ($query) => $query->whereBetween('moved_at', [$dateFrom, $dateTo]))
            ->sum('total');

        $cashMovements = CashMovements::query()
            ->with('paymentConcept')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $income = (float) $cashMovements->filter(function ($row) {
            return strtoupper((string) ($row->paymentConcept->type ?? '')) === 'I';
        })->sum('total');

        $expensesCash = (float) $cashMovements->filter(function ($row) {
            return strtoupper((string) ($row->paymentConcept->type ?? '')) === 'E';
        })->sum('total');

        $expensesPurchases = (float) \App\Models\WorkshopPurchaseRecord::query()
            ->where('branch_id', $branchId)
            ->whereBetween('issued_at', [$dateFrom, $dateTo])
            ->sum('total');

        $expenses = $expensesCash + $expensesPurchases;

        $expensesTodayCash = (float) $cashMovements->filter(function ($row) use ($dateFrom, $dateTo) {
            return strtoupper((string) ($row->paymentConcept->type ?? '')) === 'E'
                && optional($row->created_at)?->between($dateFrom, $dateTo);
        })->sum('total');

        $expensesTodayPurchases = (float) \App\Models\WorkshopPurchaseRecord::query()
            ->where('branch_id', $branchId)
            ->whereBetween('issued_at', [$dateFrom, $dateTo])
            ->sum('total');

        $expensesToday = $expensesTodayCash + $expensesTodayPurchases;
        $utility = $income - $expenses;

        $servicesDone = WorkshopMovementDetail::query()
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->where('workshop_movements.branch_id', $branchId)
            ->whereBetween('workshop_movements.finished_at', [$dateFrom, $dateTo])
            ->whereIn('workshop_movement_details.line_type', ['SERVICE', 'SERVCE'])
            ->count();

        $productionAmount = (clone $baseQuery)
            ->whereIn('status', $closedStatuses)
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('finished_at', [$dateFrom, $dateTo])
                    ->orWhere(function ($inner) use ($dateFrom, $dateTo) {
                        $inner->whereNull('finished_at')
                            ->whereBetween('updated_at', [$dateFrom, $dateTo]);
                    });
            })
            ->sum('total');

        $maintenancesWeek = WorkshopMovementDetail::query()
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->leftJoin('workshop_services', 'workshop_services.id', '=', 'workshop_movement_details.service_id')
            ->where('workshop_movements.branch_id', $branchId)
            ->whereBetween('workshop_movements.finished_at', [$dateFrom, $dateTo])
            ->whereIn('workshop_movement_details.line_type', ['SERVICE', 'SERVCE'])
            ->where(function ($query) {
                $query->where('workshop_services.type', 'preventivo')
                    ->orWhere('workshop_movement_details.description', 'ILIKE', '%mantenimiento%');
            })
            ->count();

        $avgRepairMinutes = (clone $baseQuery)
            ->whereBetween('finished_at', [$dateFrom, $dateTo])
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->get(['started_at', 'finished_at', 'total_paused_minutes'])
            ->avg(fn ($row) => max(0, ($row->started_at?->diffInMinutes($row->finished_at) ?? 0) - (int) ($row->total_paused_minutes ?? 0)));

        $techProductivity = WorkshopMovementTechnician::query()
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_technicians.workshop_movement_id')
            ->join('people as tech', 'tech.id', '=', 'workshop_movement_technicians.technician_person_id')
            ->where('workshop_movements.branch_id', $branchId)
            ->whereBetween('workshop_movements.finished_at', [$dateFrom, $dateTo])
            ->groupBy('tech.id', 'tech.first_name', 'tech.last_name')
            ->orderByRaw('COUNT(DISTINCT workshop_movements.id) DESC')
            ->limit(5)
            ->get([
                'tech.id as technician_id',
                DB::raw("CONCAT(COALESCE(tech.first_name,''), ' ', COALESCE(tech.last_name,'')) as technician"),
                DB::raw('COUNT(DISTINCT workshop_movements.id) as orders'),
                DB::raw('AVG((EXTRACT(EPOCH FROM (workshop_movements.finished_at - workshop_movements.started_at))/60) - ABS(COALESCE(workshop_movements.total_paused_minutes, 0))) as avg_minutes')
            ]);

        $frequentClients = (clone $baseQuery)
            ->join('people', 'people.id', '=', 'workshop_movements.client_person_id')
            ->whereBetween('workshop_movements.intake_date', [$dateFrom, $dateTo])
            ->groupBy('people.id', 'people.first_name', 'people.last_name', 'people.document_number')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get([
                DB::raw("CONCAT(COALESCE(people.first_name,''), ' ', COALESCE(people.last_name,'')) as client"),
                'people.document_number',
                DB::raw('COUNT(*) as visits'),
            ]);

        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);
        $birthdays = Person::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('fecha_nacimiento')
            ->get()
            ->filter(function ($person) use ($weekStart, $weekEnd) {
                $birthday = Carbon::parse($person->fecha_nacimiento)->year($weekStart->year);
                return $birthday->betweenIncluded($weekStart, $weekEnd);
            })
            ->values();

        $reminders = WorkshopMaintenanceReminder::query()
            ->with(['vehicle:id,brand,model,plate', 'client:id,first_name,last_name,document_number'])
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('status', 'due')->orWhereDate('notify_at', '<=', now()->addDays(3)->toDateString());
            })
            ->orderBy('notify_at')
            ->limit(8)
            ->get();

        $pendingPayables = DB::table('purchase_movements')
            ->join('movements', 'movements.id', '=', 'purchase_movements.movement_id')
            ->where('purchase_movements.branch_id', $branchId)
            ->where(function ($query) {
                $query->where('purchase_movements.payment_type', 'CREDITO')
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('cash_movement_details')
                            ->join('cash_movements', 'cash_movements.id', '=', 'cash_movement_details.cash_movement_id')
                            ->whereColumn('cash_movements.movement_id', 'movements.id')
                            ->where('cash_movement_details.type', 'DEUDA');
                    });
            })
            ->sum('purchase_movements.total');

        $totalOrdersCount = (clone $baseQuery)->count();

        $recentOrders = (clone $rangeQuery)
            ->with(['movement:id,number,moved_at', 'vehicle:id,brand,model,plate', 'client:id,first_name,last_name,document_number'])
            ->latest('id')
            ->limit(6)
            ->get();

        $newOrdersInRange = (clone $baseQuery)
            ->whereBetween('intake_date', [$dateFrom, $dateTo])
            ->count();

        $salesTodayDetails = SalesMovement::query()
            ->with(['movement.documentType', 'movement.person', 'movement.branch'])
            ->where('branch_id', $branchId)
            ->whereHas('movement', fn ($query) => $query->whereBetween('moved_at', [$dateFrom, $dateTo]))
            ->get()
            ->sortByDesc(fn ($sale) => optional(optional($sale->movement)->moved_at)->timestamp)
            ->values();

        $expensesTodayDetails = collect([]);
        // Gastos de caja hoy
        $cashMovements->filter(function ($row) use ($dateFrom, $dateTo) {
            return strtoupper((string) ($row->paymentConcept->type ?? '')) === 'E'
                && optional($row->created_at)?->between($dateFrom, $dateTo);
        })->each(function($row) use ($expensesTodayDetails) {
            $expensesTodayDetails->push([
                'type' => 'CAJA',
                'description' => $row->description ?: ($row->paymentConcept->name ?? 'Gasto de caja'),
                'amount' => (float)$row->total,
                'date' => $row->created_at,
                'reference' => $row->reference_number
            ]);
        });
        // Compras hoy
        \App\Models\WorkshopPurchaseRecord::query()
            ->where('branch_id', $branchId)
            ->whereBetween('issued_at', [$dateFrom, $dateTo])
            ->get()
            ->each(function($row) use ($expensesTodayDetails) {
                $expensesTodayDetails->push([
                    'type' => 'COMPRA',
                    'description' => $row->provider_name ?: 'Proveedor varios',
                    'amount' => (float)$row->total,
                    'date' => $row->issued_at,
                    'reference' => $row->document_number
                ]);
            });
        $expensesTodayDetails = $expensesTodayDetails->sortByDesc('date')->values();

        $activeOrdersDetails = (clone $baseQuery)
            ->whereIn('status', $activeStatuses)
            ->with(['movement', 'vehicle', 'client'])
            ->latest('id')
            ->get();

        $maintenanceWeekDetails = WorkshopMovementDetail::query()
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->leftJoin('workshop_services', 'workshop_services.id', '=', 'workshop_movement_details.service_id')
            ->where('workshop_movements.branch_id', $branchId)
            ->whereBetween('workshop_movements.intake_date', [$dateFrom, $dateTo])
            ->whereIn('workshop_movement_details.line_type', ['SERVICE', 'SERVCE'])
            ->where(function ($query) {
                $query->where('workshop_services.type', 'preventivo')
                    ->orWhere('workshop_movement_details.description', 'ILIKE', '%mantenimiento%');
            })
            ->with(['workshopMovement.vehicle', 'workshopMovement.client', 'workshopMovement.movement'])
            ->select('workshop_movement_details.*')
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
            'salesToday' => (float) $salesToday,
            'expensesToday' => $expensesToday,
            'income' => $income,
            'expenses' => $expenses,
            'utility' => $utility,
            'ordersInRepair' => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'servicesDone' => (int) $servicesDone,
            'productionAmount' => (float) $productionAmount,
            'maintenancesWeek' => (int) $maintenancesWeek,
            'avgRepairMinutes' => (float) ($avgRepairMinutes ?? 0),
            'techProductivity' => $techProductivity,
            'frequentClients' => $frequentClients,
            'birthdays' => $birthdays,
            'reminders' => $reminders,
            'pendingPayables' => (float) $pendingPayables,
            'statusBreakdown' => $statusBreakdown,
            'topServices' => $topServices,
            'incomeByDay' => $incomeByDay,
            'recentOrders' => $recentOrders,
            'salesTodayDetails' => $salesTodayDetails,
            'expensesTodayDetails' => $expensesTodayDetails,
            'activeOrdersDetails' => $activeOrdersDetails,
            'maintenanceWeekDetails' => $maintenanceWeekDetails,
            'totalOrdersCount' => $totalOrdersCount,
            'newOrdersInRange' => (int) $newOrdersInRange,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
        ];

        return view('pages.dashboard.ecommerce', compact('dashboardData'));
    }
    public function techDetail(Request $request, int $technicianId)
    {
        $branchId = (int) $request->session()->get('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (optional($request->user())->person->branch_id ?? 0);
        }
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        
        $dateFrom = Carbon::parse((string) $request->input('date_from', now()->startOfWeek()->toDateString()))->startOfDay();
        $dateTo = Carbon::parse((string) $request->input('date_to', now()->toDateString()))->endOfDay();

        $details = WorkshopMovementTechnician::query()
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_technicians.workshop_movement_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'workshop_movements.vehicle_id')
            ->leftJoin('movements', 'movements.id', '=', 'workshop_movements.movement_id')
            ->where('workshop_movements.branch_id', $branchId)
            ->where('workshop_movement_technicians.technician_person_id', $technicianId)
            ->whereBetween('workshop_movements.finished_at', [$dateFrom, $dateTo])
            ->orderBy('workshop_movements.finished_at', 'desc')
            ->get([
                'movements.number as os_number',
                'vehicles.plate',
                'vehicles.brand',
                'vehicles.model',
                'workshop_movements.started_at',
                'workshop_movements.finished_at',
                'workshop_movements.total_paused_minutes'
            ])
            ->map(function($item) {
                $start = $item->started_at ? Carbon::parse($item->started_at) : null;
                $finish = $item->finished_at ? Carbon::parse($item->finished_at) : null;
                $grossMinutes = ($start && $finish) ? $start->diffInMinutes($finish) : 0;
                $paused = abs((int) ($item->total_paused_minutes ?? 0));
                $netMinutes = max(0, $grossMinutes - $paused);
                
                return [
                    'os' => $item->os_number ?? 'S/N',
                    'vehicle' => trim(($item->brand ?? '') . ' ' . ($item->model ?? '')) . ' (' . ($item->plate ?? '-') . ')',
                    'net_minutes' => round($netMinutes, 2),
                    'paused_minutes' => $paused,
                    'started_at' => $start ? $start->format('d/m/Y H:i') : '-',
                    'finished_at' => $finish ? $finish->format('d/m/Y H:i') : '-'
                ];
            });

        return response()->json($details);
    }
}
