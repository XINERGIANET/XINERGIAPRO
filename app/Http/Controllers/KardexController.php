<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Kardex;
use App\Models\Product;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function index(Request $request)
    {
        $viewId = $request->input('view_id');
        $productId = $request->input('product_id') ?? 'all';
        $branchId = $request->session()->get('branch_id');
        $dateFrom = $request->input('date_from') ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?? now()->format('Y-m-d');

        $products = Product::where('kardex', 'S')->with('baseUnit')->orderBy('description')->get();
        $product = ($productId && $productId !== 'all' && is_numeric($productId)) ? Product::find($productId) : null;
        $branch = $branchId ? Branch::find($branchId) : null;
        $showAllProducts = ($productId === 'all');

        $movements = Kardex::query()
            ->with([
                'product',
                'unit',
                'movement.movementType',
                'movement.documentType',
            ])
            ->when($branchId, fn ($query) => $query->where('sucursal_id', (int) $branchId))
            ->when(!$showAllProducts && $product, fn ($query) => $query->where('producto_id', (int) $product->id))
            ->whereBetween('fecha', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(function (Kardex $row) {
                $movement = $row->movement;

                return [
                    'date' => $row->fecha?->format('Y-m-d H:i:s'),
                    'number' => $movement?->number ?? '-',
                    'type' => $movement?->documentType?->name ?? 'Movimiento',
                    'entry' => (float) $row->cantidad > 0 ? (float) $row->cantidad : 0,
                    'exit' => (float) $row->cantidad < 0 ? abs((float) $row->cantidad) : 0,
                    'unit' => $row->unit?->description ?? $row->unit?->abbreviation ?? '-',
                    'unit_price' => $row->preciounitario !== null ? (float) $row->preciounitario : null,
                    'origin' => ($movement?->movementType?->description ?? 'Movimiento') . ' - ' . ($movement?->number ?? '-'),
                    'previous_stock' => (float) $row->stockanterior,
                    'quantity' => abs((float) $row->cantidad),
                    'balance' => (float) $row->stockactual,
                    'currency' => $row->moneda ?: 'PEN',
                    'product_code' => $row->product?->code ?? '-',
                    'product_description' => $row->product?->description ?? '-',
                ];
            })
            ->values();

        return view('kardex.index', compact(
            'viewId',
            'productId',
            'branchId',
            'dateFrom',
            'dateTo',
            'products',
            'product',
            'branch',
            'movements',
            'showAllProducts'
        ));
    }
}
