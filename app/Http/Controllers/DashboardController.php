<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovements;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\WorkshopMaintenanceReminder;
use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\WorkshopMovementTechnician;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $activeStatuses = ['draft', 'diagnosis', 'awaiting_approval', 'approved', 'awaiting_technician_start', 'in_progress'];
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
            ->get([
                DB::raw("CONCAT(COALESCE(people.first_name,''), ' ', COALESCE(people.last_name,'')) as client"),
                'people.document_number',
                DB::raw('COUNT(*) as visits'),
                DB::raw('SUM(workshop_movements.total) as total_spent'),
            ])->filter(fn($c) => (float)$c->total_spent > 0)->values();

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

        $indicatorCharts = $this->collectIndicatorCharts($branchId, $dateFrom, $dateTo);

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
            'indicatorCharts' => $indicatorCharts,
        ];

        return view('pages.dashboard.ecommerce', compact('dashboardData'));
    }

    /**
     * Indicadores ejecutivos (facturación mensual, segmentos, gastos, cotizaciones).
     */
    protected function collectIndicatorCharts(int $branchId, Carbon $dateFrom, Carbon $dateTo): array
    {
        if ($branchId <= 0) {
            return [
                'empty' => true,
            ];
        }

        $monthCursor = now()->copy()->startOfYear();
        $monthEnd = now()->copy()->endOfMonth();
        $monthKeys = [];
        $monthLabels = [];
        while ($monthCursor->lte($monthEnd)) {
            $k = $monthCursor->format('Y-m');
            $monthKeys[] = $k;
            $monthLabels[] = ucfirst(mb_strtolower($monthCursor->copy()->locale('es')->translatedFormat('M Y')));
            $monthCursor->addMonth();
        }

        $bucket = [];
        foreach ($monthKeys as $k) {
            $bucket[$k] = ['fact' => 0.0, 'nofact' => 0.0];
        }

        $salesAgg = SalesMovement::query()
            ->join('movements as m', 'm.id', '=', 'sales_movements.movement_id')
            ->where('sales_movements.branch_id', $branchId)
            ->whereBetween('m.moved_at', [now()->startOfYear(), now()->endOfMonth()])
            ->select(['sales_movements.total', 'sales_movements.billing_status', 'm.moved_at'])
            ->get();

        foreach ($salesAgg as $row) {
            $k = Carbon::parse($row->moved_at)->format('Y-m');
            if (!isset($bucket[$k])) {
                continue;
            }
            $amt = (float) $row->total;
            $st = strtoupper((string) ($row->billing_status ?? ''));
            if ($st === 'INVOICED') {
                $bucket[$k]['fact'] += $amt;
            } else {
                $bucket[$k]['nofact'] += $amt;
            }
        }

        $monthlySeriesFact = [];
        $monthlySeriesNofact = [];
        $monthlySeriesTotal = [];
        $monthlyRows = [];

        foreach ($monthKeys as $i => $mk) {
            $fact = round($bucket[$mk]['fact'], 2);
            $nof = round($bucket[$mk]['nofact'], 2);
            $tot = round($fact + $nof, 2);
            $monthlySeriesFact[] = $fact;
            $monthlySeriesNofact[] = $nof;
            $monthlySeriesTotal[] = $tot;

            [, $y] = array_pad(explode('-', $mk, 3), 2, '');
            $parts = explode('-', $mk);
            $yearPart = isset($parts[0]) ? (int) $parts[0] : (int) now()->year;
            $monthPart = isset($parts[1]) ? (int) $parts[1] : (int) now()->month;
            $monthlyRows[] = [
                'year' => $yearPart,
                'month_label' => $monthLabels[$i],
                'fact' => $fact,
                'nofact' => $nof,
                'total' => $tot,
            ];
        }

        $clientGroupExpr = "
            COALESCE(
                TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))),
                NULLIF(TRIM(m.person_name), ''),
                '(Sin nombre)'
            )
        ";

        $topClients = DB::table('sales_movements as sm')
            ->join('movements as m', 'm.id', '=', 'sm.movement_id')
            ->leftJoin('people as p', 'p.id', '=', 'm.person_id')
            ->where('sm.branch_id', $branchId)
            ->whereBetween('m.moved_at', [$dateFrom, $dateTo])
            ->selectRaw($clientGroupExpr . ' as client_label')
            ->selectRaw('SUM(sm.total) as total_sale')
            ->groupBy(DB::raw($clientGroupExpr))
            ->orderByDesc(DB::raw('SUM(sm.total)'))
            ->limit(14)
            ->get();

        $clientsLabels = [];
        $clientsData = [];
        foreach ($topClients as $tc) {
            $clientsLabels[] = $this->shortClientLabel((string) $tc->client_label);
            $clientsData[] = round((float) $tc->total_sale, 2);
        }

        $monthsInRangeSales = SalesMovement::query()
            ->join('movements as m', 'm.id', '=', 'sales_movements.movement_id')
            ->where('sales_movements.branch_id', $branchId)
            ->whereBetween('m.moved_at', [$dateFrom, $dateTo])
            ->select(['sales_movements.total', 'm.moved_at'])
            ->get();

        $timelineBucket = [];
        foreach ($monthsInRangeSales as $r) {
            $k = Carbon::parse($r->moved_at)->format('Y-m');
            if (!isset($timelineBucket[$k])) {
                $timelineBucket[$k] = 0.0;
            }
            $timelineBucket[$k] += (float) $r->total;
        }
        $linearGrowthTrend = $this->buildLinearGrowthTrendData($branchId);

        $topProducts = SalesMovementDetail::query()
            ->join('sales_movements as sm', 'sm.id', '=', 'sales_movement_details.sales_movement_id')
            ->join('movements as m', 'm.id', '=', 'sm.movement_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'sales_movement_details.product_id')
            ->where('sales_movement_details.branch_id', $branchId)
            ->whereNotNull('sales_movement_details.product_id')
            ->whereBetween('m.moved_at', [$dateFrom, $dateTo])
            ->groupBy('sales_movement_details.product_id')
            ->orderByDesc(DB::raw('SUM(sales_movement_details.amount)'))
            ->limit(12)
            ->get([
                DB::raw('MAX(COALESCE(pr.description, sales_movement_details.description, \'Sin descripcion\')) as prod_name'),
                DB::raw('SUM(sales_movement_details.quantity) as qty_sum'),
                DB::raw('SUM(sales_movement_details.amount) as amount_sum'),
            ]);

        $productLabels = [];
        $productAmounts = [];
        foreach ($topProducts as $tp) {
            $productLabels[] = $this->shortClientLabel((string) $tp->prod_name, 36);
            $productAmounts[] = round((float) $tp->amount_sum, 2);
        }

        $monthsInFilter = max(1, (int) $dateFrom->copy()->startOfMonth()->diffInMonths($dateTo->copy()->endOfMonth()) + 1);
        $expenseHistoricalFrom = now()->copy()->subMonths(6)->startOfMonth();

        $expenseRowsAgg = DB::table('cash_movements as cm')
            ->join('payment_concepts as pc', 'pc.id', '=', 'cm.payment_concept_id')
            ->where('cm.branch_id', $branchId)
            ->whereNull('cm.deleted_at')
            ->whereRaw('UPPER(TRIM(pc.type)) = ?', ['E'])
            ->whereBetween('cm.created_at', [$dateFrom, $dateTo])
            ->groupBy(['pc.id', 'pc.description'])
            ->selectRaw('pc.id as concept_id')
            ->selectRaw('pc.description as concept_label')
            ->selectRaw('SUM(cm.total) as total_real')
            ->orderByDesc(DB::raw('SUM(cm.total)'))
            ->limit(18)
            ->get();

        $expHistorical = DB::table('cash_movements as cm')
            ->join('payment_concepts as pc', 'pc.id', '=', 'cm.payment_concept_id')
            ->where('cm.branch_id', $branchId)
            ->whereNull('cm.deleted_at')
            ->whereRaw('UPPER(TRIM(pc.type)) = ?', ['E'])
            ->whereBetween('cm.created_at', [$expenseHistoricalFrom, $dateTo])
            ->groupBy('pc.id')
            ->selectRaw('pc.id as concept_id')
            ->selectRaw('SUM(cm.total) as total_h')
            ->pluck('total_h', 'concept_id');

        $expenseProj = [];
        $expenseReal = [];
        $expenseLabels = [];
        $expenseTableRows = [];
        foreach ($expenseRowsAgg as $row) {
            $label = mb_strtoupper((string) ($row->concept_label ?? 'OTROS'));
            $hid = isset($row->concept_id) ? (int) $row->concept_id : null;
            $historicalPortion = $hid && isset($expHistorical[$hid])
                ? (float) $expHistorical[$hid] / max(6, 6)
                : (float) $row->total_real / max(1, $monthsInFilter);
            $proj = round($historicalPortion * $monthsInFilter, 2);
            $real = round((float) $row->total_real, 2);

            $expenseLabels[] = $this->shortClientLabel($label, 24);
            $expenseProj[] = $proj > 0 ? $proj : $real;
            $expenseReal[] = $real;
            $expenseTableRows[] = [
                'label' => $label,
                'projected' => $proj > 0 ? $proj : $real,
                'real' => $real,
            ];
        }

        $quotesStats = [];
        $wmCols = Schema::hasColumn('workshop_movements', 'quotation_source');

        $qQuot = WorkshopMovement::query()
            ->where('workshop_movements.branch_id', $branchId)
            ->whereBetween('workshop_movements.updated_at', [$dateFrom, $dateTo]);

        $applyQuotFilter = static function ($q) use ($wmCols) {
            return $wmCols ? $q->where('quotation_source', 'external') : $q;
        };

        $quotesStats['total'] = $applyQuotFilter(clone $qQuot)->count();
        $quotesStats['approved'] = $applyQuotFilter(clone $qQuot)->where('status', 'approved')->count();
        $quotesStats['awaiting'] = $applyQuotFilter(clone $qQuot)->where('status', 'awaiting_approval')->count();

        $quotesStats['converted'] = 0;
        if (Schema::hasColumn('workshop_movements', 'quotation_result')) {
            $quotesStats['converted'] = $applyQuotFilter(clone $qQuot)->whereIn('quotation_result', ['converted', 'won'])->count();
        }

        return [
            'empty' => false,
            'month_labels' => array_values(array_map(fn ($lbl) => (string) $lbl, $monthLabels)),
            'month_rows' => $monthlyRows,
            'series_monthly_fact' => $monthlySeriesFact,
            'series_monthly_nofact' => $monthlySeriesNofact,
            'series_monthly_total' => $monthlySeriesTotal,
            'clients_labels' => $clientsLabels,
            'clients_data' => $clientsData,
            'linear_growth_rows' => $linearGrowthTrend['rows'],
            'linear_growth_chart_categories' => $linearGrowthTrend['chart_categories'],
            'linear_growth_tv_series' => $linearGrowthTrend['tv_series'],
            'product_labels' => $productLabels,
            'product_amounts' => $productAmounts,
            'product_qty_top' => $topProducts->map(fn ($tp) => [
                'name' => (string) $tp->prod_name,
                'qty' => round((float) $tp->qty_sum, 2),
                'amount' => round((float) $tp->amount_sum, 2),
            ])->values()->all(),
            'expense_labels' => $expenseLabels,
            'expense_projected' => $expenseProj,
            'expense_real' => $expenseReal,
            'expense_rows' => $expenseTableRows,
            'quotes_stats' => $quotesStats,
            'range_label' => $dateFrom->format('d/m/Y') . ' — ' . $dateTo->format('d/m/Y'),
        ];
    }

    protected function shortClientLabel(string $name, int $max = 26): string
    {
        $t = preg_replace('/\s+/', ' ', trim($name));

        return $t !== '' && mb_strlen($t) > $max ? mb_substr($t, 0, $max - 2) . '…' : (string) $t;
    }

    /**
     * Serie «TENDENCIA CRECIMIENTO LINEAL»: 17 meses, escenario nuevo cliente (excel) sobre regresión de los 9 primeros meses de ventas reales.
     *
     * @return array{rows: array<int, array<string, mixed>>, chart_categories: array<int, string>, tv_series: array<int, float>}
     */
    protected function buildLinearGrowthTrendData(int $branchId): array
    {
        $spanMonths = (int) max(12, env('INDICATOR_LINEAR_MONTHS_SPAN', 17));
        $motocorpMonthly = (float) env('INDICATOR_PROJ_MOTOCORP', 2000);

        $startMonth = now()->copy()->startOfMonth()->subMonths($spanMonths - 1);

        $salesByYm = [];
        $saleRows = SalesMovement::query()
            ->join('movements as m', 'm.id', '=', 'sales_movements.movement_id')
            ->where('sales_movements.branch_id', $branchId)
            ->where('m.moved_at', '>=', $startMonth->copy()->startOfDay())
            ->where('m.moved_at', '<=', now()->copy()->endOfDay())
            ->get(['sales_movements.total', 'm.moved_at']);

        foreach ($saleRows as $sr) {
            $k = Carbon::parse($sr->moved_at)->format('Y-m');
            $salesByYm[$k] = ($salesByYm[$k] ?? 0.0) + (float) $sr->total;
        }

        $histTotals = [];
        $cursorHist = $startMonth->copy();
        for ($i = 1; $i <= min(9, $spanMonths); $i++) {
            $yk = $cursorHist->format('Y-m');
            $histTotals[] = isset($salesByYm[$yk]) ? round((float) $salesByYm[$yk], 2) : 0.0;
            $cursorHist->addMonth();
        }

        [$intercept, $slope] = $this->leastSquaresLine(range(1, count($histTotals)), array_values($histTotals));

        $rows = [];
        $categories = [];
        $tvSeries = [];

        $c = $startMonth->copy();
        for ($mesNum = 1; $mesNum <= $spanMonths; $mesNum++) {
            $yk = $c->format('Y-m');
            $actualRounded = isset($salesByYm[$yk]) ? round((float) $salesByYm[$yk], 2) : 0.0;
            $isFutureMonthStart = $c->copy()->startOfMonth()->gt(now()->copy()->startOfMonth());
            $forecastBase = round(max(0.0, $intercept + $slope * (float) $mesNum), 2);

            if ($mesNum <= 9) {
                $tv = $actualRounded;
            } elseif (!$isFutureMonthStart && $actualRounded > 0.01) {
                $tv = $actualRounded;
            } else {
                $tv = $forecastBase;
            }

            $mot = null;
            $lifRow = null;
            $proy = null;

            if ($mesNum >= 10) {
                $mot = $motocorpMonthly;
                $lifRow = match ($mesNum) {
                    10 => 200.0,
                    11 => 300.0,
                    default => 400.0,
                };
                $proy = round($tv + $mot + $lifRow, 2);
            }

            $locale = $c->copy()->locale('es');

            $rows[] = [
                'mes_no' => $mesNum,
                'mes_title' => (string) $locale->translatedFormat('F Y'),
                'tv_ventas' => $tv,
                'nuevo_cliente_motocorp' => $mot,
                'nuevo_cliente_lifan' => $lifRow,
                'proy_lineal_total' => $proy,
            ];

            $lblMonth = ucfirst(mb_strtolower((string) $c->copy()->locale('es')->translatedFormat('F Y')));
            $categories[] = trim((string) $mesNum . ' — ' . $lblMonth);
            $tvSeries[] = $tv;

            $c->addMonth();
        }

        return [
            'rows' => $rows,
            'chart_categories' => array_values(array_map(static fn ($s) => (string) $s, $categories)),
            'tv_series' => array_values(array_map(static fn ($f) => (float) round($f, 2), $tvSeries)),
        ];
    }

    /**
     * @param  array<int, float|int>  $xs
     * @param  array<int, float|int>  $ys
     * @return array{0: float, 1: float} [intercepto, pendiente]
     */
    protected function leastSquaresLine(array $xs, array $ys): array
    {
        $n = count($xs);
        if ($n !== count($ys) || $n === 0) {
            return [0.0, 0.0];
        }

        $sumX = array_sum(array_map('floatval', $xs));
        $sumY = array_sum(array_map('floatval', $ys));

        $sumXs = array_sum(array_map(static fn ($v) => (float) $v * (float) $v, $xs));

        $sumXY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += (float) $xs[$i] * (float) $ys[$i];
        }

        $denom = ($n * $sumXs - $sumX * $sumX);

        if (abs($denom) < 1e-9) {
            $meanY = $n > 0 ? $sumY / $n : 0.0;

            return [$meanY, 0.0];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [(float) $intercept, (float) $slope];
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
