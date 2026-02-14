<?php

namespace App\Http\Controllers;

use App\Models\WorkshopMovement;
use App\Models\WorkshopMovementDetail;
use App\Models\Product;
use App\Support\WorkshopAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class WorkshopReportController extends Controller
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
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $orders = WorkshopMovement::query()
            ->with(['movement', 'vehicle', 'client'])
            ->where('branch_id', $branchId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($dateFrom, fn ($query) => $query->whereDate('intake_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('intake_date', '<=', $dateTo))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'count' => (clone $orders->getCollection())->count(),
            'subtotal' => $orders->getCollection()->sum('subtotal'),
            'tax' => $orders->getCollection()->sum('tax'),
            'total' => $orders->getCollection()->sum('total'),
            'paid_total' => $orders->getCollection()->sum('paid_total'),
        ];

        $byStatus = WorkshopMovement::query()
            ->select('status', DB::raw('COUNT(*) as qty'))
            ->where('branch_id', $branchId)
            ->groupBy('status')
            ->pluck('qty', 'status')
            ->toArray();

        $topServices = WorkshopMovementDetail::query()
            ->select('description', DB::raw('COUNT(*) as qty'))
            ->where('line_type', 'SERVICE')
            ->whereHas('workshopMovement', fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('description')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $topParts = WorkshopMovementDetail::query()
            ->select('description', DB::raw('SUM(qty) as qty'))
            ->where('line_type', 'PART')
            ->whereHas('workshopMovement', fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('description')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $incomeByDay = WorkshopMovement::query()
            ->selectRaw('DATE(intake_date) as day, SUM(total) as total, SUM(paid_total) as paid')
            ->where('branch_id', $branchId)
            ->whereDate('intake_date', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $clientsWithDebt = WorkshopMovement::query()
            ->selectRaw('client_person_id, SUM(total - paid_total) as debt')
            ->with('client:id,first_name,last_name')
            ->where('branch_id', $branchId)
            ->groupBy('client_person_id')
            ->havingRaw('SUM(total - paid_total) > 0')
            ->orderByDesc('debt')
            ->limit(10)
            ->get();

        $productivityByTechnician = DB::table('workshop_movement_details')
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->join('people', 'people.id', '=', 'workshop_movement_details.technician_person_id')
            ->selectRaw("people.id as technician_id, CONCAT(people.first_name, ' ', people.last_name) as technician, COUNT(*) as lines_done, COALESCE(SUM(workshop_movement_details.total),0) as billed_total")
            ->where('workshop_movements.branch_id', $branchId)
            ->whereNotNull('workshop_movement_details.technician_person_id')
            ->groupBy('people.id', 'people.first_name', 'people.last_name')
            ->orderByDesc('lines_done')
            ->limit(10)
            ->get();

        $marginByOrder = WorkshopMovement::query()
            ->with(['movement', 'details'])
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (WorkshopMovement $order) use ($branchId) {
                $partCost = 0;
                foreach ($order->details->where('line_type', 'PART') as $detail) {
                    $row = DB::table('product_branch')
                        ->where('branch_id', $branchId)
                        ->where('product_id', $detail->product_id)
                        ->select('avg_cost', 'price')
                        ->first();
                    $unitCost = (float) (($row->avg_cost ?? 0) > 0 ? $row->avg_cost : ($row->price ?? 0));
                    $partCost += ((float) $detail->qty * $unitCost);
                }

                return [
                    'order' => $order,
                    'part_cost' => $partCost,
                    'margin' => (float) $order->total - $partCost,
                ];
            });

        $hoursReport = WorkshopMovement::query()
            ->with('details.service')
            ->where('branch_id', $branchId)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (WorkshopMovement $order) {
                $estimatedMinutes = (int) $order->details
                    ->filter(fn ($line) => $line->service !== null)
                    ->sum(fn ($line) => (int) ($line->service->estimated_minutes ?? 0));

                $realMinutes = max(0, $order->started_at?->diffInMinutes($order->finished_at) ?? 0);

                return [
                    'order' => $order,
                    'estimated_minutes' => $estimatedMinutes,
                    'real_minutes' => $realMinutes,
                ];
            });

        $ordersByTechnician = DB::table('workshop_movement_details')
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->join('people', 'people.id', '=', 'workshop_movement_details.technician_person_id')
            ->selectRaw("people.id as technician_id, CONCAT(people.first_name, ' ', people.last_name) as technician, COUNT(DISTINCT workshop_movements.id) as orders")
            ->where('workshop_movements.branch_id', $branchId)
            ->whereNotNull('workshop_movement_details.technician_person_id')
            ->groupBy('people.id', 'people.first_name', 'people.last_name')
            ->orderByDesc('orders')
            ->limit(10)
            ->get();

        $stockMinimum = DB::table('product_branch')
            ->join('products', 'products.id', '=', 'product_branch.product_id')
            ->selectRaw('products.description, product_branch.stock, product_branch.stock_minimum')
            ->where('product_branch.branch_id', $branchId)
            ->whereNotNull('product_branch.stock_minimum')
            ->whereColumn('product_branch.stock', '<=', 'product_branch.stock_minimum')
            ->orderBy('products.description')
            ->limit(30)
            ->get();

        $kardexProducts = Product::query()
            ->where('type', 'PRODUCT')
            ->orderBy('description')
            ->get(['id', 'description']);

        return view('workshop.reports.index', compact(
            'orders',
            'totals',
            'status',
            'dateFrom',
            'dateTo',
            'byStatus',
            'topServices',
            'topParts',
            'incomeByDay',
            'clientsWithDebt',
            'productivityByTechnician',
            'marginByOrder',
            'hoursReport',
            'ordersByTechnician',
            'stockMinimum',
            'kardexProducts'
        ));
    }

    public function serviceOrderPdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'details.product', 'checklists.items', 'damages', 'intakeInventory']);

        return view('workshop.pdf.order', compact('order'));
    }

    public function activationPdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'checklists.items']);

        return view('workshop.pdf.activation', compact('order'));
    }

    public function pdiPdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'checklists.items']);

        return view('workshop.pdf.pdi', compact('order'));
    }

    public function maintenancePdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'checklists.items']);

        return view('workshop.pdf.maintenance', compact('order'));
    }

    public function partsSummaryPdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'details.product', 'details.warehouseMovement']);

        return view('workshop.pdf.parts_summary', compact('order'));
    }

    public function internalSalePdf(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'client', 'vehicle', 'sale.movement', 'sale.details.product', 'sale.details.unit']);

        if (!$order->sale) {
            abort(404, 'La OS no tiene venta vinculada.');
        }

        return view('workshop.pdf.internal_sale', compact('order'));
    }

    public function saveOrderPdfSnapshot(WorkshopMovement $order)
    {
        $this->assertOrderScope($order);
        $order->load(['movement', 'vehicle', 'client', 'details.product', 'checklists.items', 'damages', 'intakeInventory']);

        $html = view('workshop.pdf.order', compact('order'))->render();
        $number = $order->movement?->number ?: ('os-' . $order->id);
        $path = 'workshop_pdfs/' . now()->format('Y/m') . '/OS-' . $number . '-' . now()->format('His') . '.html';
        Storage::disk('local')->put($path, $html);

        return back()->with('status', 'Snapshot de PDF guardado en storage/app/' . $path);
    }

    private function assertOrderScope(WorkshopMovement $order): void
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $order->branch_id !== $branchId) {
            abort(404);
        }

        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        if ($branch && (int) $order->company_id !== (int) $branch->company_id) {
            abort(404);
        }
    }
}

