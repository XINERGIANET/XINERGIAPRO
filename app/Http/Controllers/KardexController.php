<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DocumentType;
use App\Models\Kardex;
use App\Models\Product;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function index(Request $request)
    {
        $viewId = $request->input('view_id');
        $search = trim((string) $request->input('search', ''));
        $productId = $request->input('product_id') ?? 'all';
        $categoryId = $request->input('category_id') ?? 'all';
        $documentTypeId = $request->input('document_type_id') ?? 'all';
        $situation = $request->input('situation') ?? 'all';
        $branchId = $request->session()->get('branch_id');
        $dateFrom = $request->input('date_from') ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?? now()->format('Y-m-d');

        $products = Product::where('kardex', 'S')->with(['baseUnit', 'category'])->orderBy('description')->get();
        $categories = Category::query()->orderBy('description')->get(['id', 'description']);
        $product = ($productId && $productId !== 'all' && is_numeric($productId)) ? Product::find($productId) : null;
        $branch = $branchId ? Branch::find($branchId) : null;
        $showAllProducts = ($productId === 'all');

        $typeOptions = DocumentType::query()
            ->whereIn('id', Kardex::query()
                ->when($branchId, fn ($query) => $query->where('sucursal_id', (int) $branchId))
                ->distinct()
                ->pluck('tipodocumento_id')
                ->filter()
                ->values()
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        $movements = Kardex::query()
            ->with([
                'product.category',
                'unit',
                'movement.movementType',
                'movement.documentType',
            ])
            ->when($branchId, fn ($query) => $query->where('sucursal_id', (int) $branchId))
            ->when(!$showAllProducts && $product, fn ($query) => $query->where('producto_id', (int) $product->id))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where(function ($inner) use ($search) {
                        $inner->where('code', 'ILIKE', "%{$search}%")
                            ->orWhere('description', 'ILIKE', "%{$search}%");
                    });
                });
            })
            ->when($categoryId !== 'all' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->whereHas('product', function ($productQuery) use ($categoryId) {
                    $productQuery->where('category_id', (int) $categoryId);
                });
            })
            ->when($documentTypeId !== 'all' && is_numeric($documentTypeId), fn ($query) => $query->where('tipodocumento_id', (int) $documentTypeId))
            ->when($situation !== 'all', fn ($query) => $query->where('situacion', (string) $situation))
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
                    'category' => $row->product?->category?->description ?? 'Sin categoria',
                    'situation' => (string) ($row->situacion ?? 'E'),
                ];
            })
            ->values();

        $summary = [
            'records' => $movements->count(),
            'entries' => (float) $movements->sum('entry'),
            'exits' => (float) $movements->sum('exit'),
            'valuation' => (float) $movements->sum(function (array $movement) {
                return ((float) ($movement['unit_price'] ?? 0)) * ((float) ($movement['quantity'] ?? 0));
            }),
        ];

        return view('kardex.index', compact(
            'viewId',
            'search',
            'productId',
            'categoryId',
            'documentTypeId',
            'situation',
            'branchId',
            'dateFrom',
            'dateTo',
            'products',
            'categories',
            'typeOptions',
            'product',
            'branch',
            'movements',
            'showAllProducts',
            'summary'
        ));
    }
}
