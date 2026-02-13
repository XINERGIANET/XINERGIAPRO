<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();

        // 1. Customers Count
        $customersCount = \App\Models\Person::count();

        // 2. Orders Metrics (Current Month vs Previous Month)
        $currentMonthOrders = \App\Models\OrderMovement::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        
        $prevMonthOrders = \App\Models\OrderMovement::whereMonth('created_at', $now->copy()->subMonth()->month)
            ->whereYear('created_at', $now->copy()->subMonth()->year)
            ->count();

        $ordersDiff = 0;
        if ($prevMonthOrders > 0) {
            $ordersDiff = (($currentMonthOrders - $prevMonthOrders) / $prevMonthOrders) * 100;
        } elseif ($currentMonthOrders > 0) {
            $ordersDiff = 100;
        }

        // 3. Monthly Sales (Current Year)
        $salesByMonth = \App\Models\SalesMovement::selectRaw('EXTRACT(MONTH FROM created_at) as month, SUM(total) as total')
            ->whereYear('created_at', $now->year)
            ->groupBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();

        $monthlySalesData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlySalesData[] = (float) ($salesByMonth[$i] ?? 0);
        }

        // 4. Statistics Trend (Sales and Revenue/Subtotal)
        $subtotalByMonth = \App\Models\SalesMovement::selectRaw('EXTRACT(MONTH FROM created_at) as month, SUM(subtotal) as subtotal')
            ->whereYear('created_at', $now->year)
            ->groupBy('month')
            ->get()
            ->pluck('subtotal', 'month')
            ->toArray();

        $monthlySubtotalData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlySubtotalData[] = (float) ($subtotalByMonth[$i] ?? 0);
        }

        // 5. Recent Orders
        $recentOrders = \App\Models\OrderMovementDetail::with(['product.category', 'orderMovement'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($detail) {
                return [
                    'name' => $detail->description ?: ($detail->product->description ?? 'Producto'),
                    'variants' => $detail->product ? $detail->product->productBranches()->count() : 0,
                    'image' => ($detail->product && $detail->product->image) ? asset('storage/' . $detail->product->image) : '/images/product/product-01.jpg',
                    'category' => $detail->product->category->description ?? 'General',
                    'price' => 'S/' . number_format($detail->amount, 2),
                    'status' => $detail->status ?: 'Entregado', // Fallback status
                ];
            });

        // 6. Table Occupancy
        $totalTables = \App\Models\Table::count();
        $occupiedTables = \App\Models\Table::whereIn('situation', ['OCUPADA', 'ocupada', 'PENDIENTE', 'Pendiente'])->count();
        $occupancyRate = $totalTables > 0 ? ($occupiedTables / $totalTables) * 100 : 0;

        // 7. Financial Balance (Income vs Expenses)
        $monthlyIncome = \App\Models\CashMovements::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->whereHas('paymentConcept', function ($q) {
                $q->where('type', 'I');
            })->sum('total');

        $monthlyExpense = \App\Models\CashMovements::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->whereHas('paymentConcept', function ($q) {
                $q->where('type', 'E');
            })->sum('total');

        $netBalance = $monthlyIncome - $monthlyExpense;

        $dashboardData = [
            'customersCount' => number_format($customersCount),
            'ordersCount' => number_format($currentMonthOrders),
            'ordersDiff' => number_format($ordersDiff, 2),
            'ordersTrend' => $ordersDiff >= 0 ? 'up' : 'down',
            'monthlySales' => $monthlySalesData,
            'monthlySubtotal' => $monthlySubtotalData,
            'recentOrders' => $recentOrders,
            'occupancyData' => [
                'total' => $totalTables,
                'occupied' => $occupiedTables,
                'rate' => round($occupancyRate, 2),
            ],
            'financialData' => [
                'income' => (float) $monthlyIncome,
                'expense' => (float) $monthlyExpense,
                'balance' => (float) $netBalance,
            ],
        ];

        return view('pages.dashboard.ecommerce', compact('dashboardData'));
    }
}
