<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\SalesMovementDetail;
use App\Models\WarehouseMovementDetail;
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
        $movements = collect();
        $showAllProducts = ($productId === 'all');

        if ($showAllProducts) {
            $productIds = $this->getProductIdsWithMovements(
                $branchId ? (int) $branchId : null,
                $dateFrom,
                $dateTo
            );
            $productIds = array_values(array_intersect($productIds, $products->pluck('id')->all()));
            $productMap = Product::whereIn('id', $productIds)->get()->keyBy('id');
            foreach ($productIds as $pid) {
                $rows = $this->buildKardexMovements($pid, $branchId ? (int) $branchId : null, $dateFrom, $dateTo);
                $p = $productMap->get($pid);
                foreach ($rows as $r) {
                    $r['product_code'] = $p?->code ?? '-';
                    $r['product_description'] = $p?->description ?? '-';
                    $movements->push($r);
                }
            }
            $movements = $movements->sortBy(['date', 'product_code'])->values();
        } elseif ($product) {
            $movements = $this->buildKardexMovements(
                (int) $productId,
                $branchId ? (int) $branchId : null,
                $dateFrom,
                $dateTo
            );
        }

        return view('kardex.index', compact(
            'viewId', 'productId', 'branchId', 'dateFrom', 'dateTo',
            'products', 'product', 'branch', 'movements', 'showAllProducts'
        ));
    }

    private function buildKardexMovements(int $productId, ?int $branchId, string $dateFrom, string $dateTo): \Illuminate\Support\Collection
    {
        $dateFromStart = $dateFrom . ' 00:00:00';
        $dateToEnd = $dateTo . ' 23:59:59';

        $rows = collect();

        // 1. WarehouseMovementDetail (entradas y salidas según tipo de documento)
        $warehouseDetails = WarehouseMovementDetail::query()
            ->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('warehouseMovement.movement', fn ($q) => $q->whereBetween('moved_at', [$dateFromStart, $dateToEnd]))
            ->with(['warehouseMovement.movement.documentType', 'unit'])
            ->get();

        $product = Product::with('baseUnit')->find($productId);
        $unitName = $product?->baseUnit?->description ?? $product?->baseUnit?->abbreviation ?? '-';

        foreach ($warehouseDetails as $d) {
            $mov = $d->warehouseMovement?->movement;
            if (!$mov) {
                continue;
            }
            // Entrada: prefijo E- o tipo documento Entrada; Salida: prefijo S- o tipo documento Salida
            $docName = strtolower($mov->documentType?->name ?? '');
            $isEntry = str_starts_with((string) $mov->number, 'E-')
                || str_contains($docName, 'entrada')
                || str_contains($docName, 'entry');
            $qty = (float) $d->quantity;
            $detailUnit = $d->unit?->description ?? $d->unit?->abbreviation ?? $unitName;
            $rows->push([
                'date' => $mov->moved_at?->format('Y-m-d H:i:s'),
                'date_sort' => $mov->moved_at?->format('Y-m-d H:i:s'),
                'number' => $mov->number,
                'type' => $isEntry ? 'Entrada' : 'Salida',
                'entry' => $isEntry ? $qty : 0,
                'exit' => $isEntry ? 0 : $qty,
                'unit' => $detailUnit,
                'unit_price' => null,
                'origin' => $mov->movementType?->description . ' - ' . $mov->number
            ]);
        }

        // 2. SalesMovementDetail (siempre salida)
        $salesDetails = SalesMovementDetail::query()
            ->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('salesMovement.movement', fn ($q) => $q->whereBetween('moved_at', [$dateFromStart, $dateToEnd]))
            ->with(['salesMovement.movement.documentType', 'unit'])
            ->get();

        foreach ($salesDetails as $d) {
            $mov = $d->salesMovement?->movement;
            if (!$mov) {
                continue;
            }
            $docTypeName = $mov->documentType?->name ?? 'Venta';
            $qty = (float) $d->quantity;
            $detailUnit = $d->unit?->description ?? $d->unit?->abbreviation ?? $unitName;
            $unitPrice = $qty > 0 ? (float) $d->amount / $qty : null;
            $rows->push([
                'date' => $mov->moved_at?->format('Y-m-d H:i:s'),
                'date_sort' => $mov->moved_at?->format('Y-m-d H:i:s'),
                'number' => $mov->number,
                'type' => $docTypeName,
                'entry' => 0,
                'exit' => $qty,
                'unit' => $detailUnit,
                'unit_price' => $unitPrice,
                'origin' => $mov->movementType?->description . ' - ' . $mov->documentType?->name[0] . $mov->salesMovement?->series . ' - ' . $mov->number,
            ]);
        }

        $rows = $rows->sortBy('date_sort')->values();

        // Calcular saldo acumulado (saldo inicial antes del período)
        $openingBalance = $this->getOpeningBalance($productId, $branchId, $dateFromStart);
        $balance = $openingBalance;

        $result = $rows->map(function ($r) use (&$balance) {
            $previousStock = $balance;
            $balance += ($r['entry'] ?? 0) - ($r['exit'] ?? 0);
            $r['previous_stock'] = $previousStock;
            $r['balance'] = $balance;
            $r['quantity'] = ($r['entry'] ?? 0) > 0 ? $r['entry'] : $r['exit'];
            unset($r['date_sort']);
            return $r;
        });

        if ($openingBalance != 0 && $result->isNotEmpty()) {
            $result->prepend([
                'date' => $dateFrom . ' 00:00',
                'number' => '-',
                'type' => 'Saldo inicial',
                'entry' => 0,
                'exit' => 0,
                'previous_stock' => 0,
                'quantity' => 0,
                'balance' => $openingBalance,
                'unit' => $unitName,
                'unit_price' => null,
                'origin' => '-',
            ]);
        }

        return $result->values();
    }

    private function getOpeningBalance(int $productId, ?int $branchId, string $beforeDate): float
    {
        $balance = 0.0;

        $warehouseDetails = WarehouseMovementDetail::query()
            ->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('warehouseMovement.movement', fn ($q) => $q->where('moved_at', '<', $beforeDate))
            ->with('warehouseMovement.movement.documentType')
            ->get();

        foreach ($warehouseDetails as $d) {
            $mov = $d->warehouseMovement?->movement;
            if (!$mov) {
                continue;
            }
            $qty = (float) $d->quantity;
            $docName = strtolower($mov->documentType?->name ?? '');
            $isEntry = str_starts_with((string) $mov->number, 'E-')
                || str_contains($docName, 'entrada')
                || str_contains($docName, 'entry');
            $balance += $isEntry ? $qty : -$qty;
        }

        $salesQty = SalesMovementDetail::query()
            ->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('salesMovement.movement', fn ($q) => $q->where('moved_at', '<', $beforeDate))
            ->sum('quantity');
        $balance -= (float) $salesQty;

        return $balance;
    }

    private function getProductIdsWithMovements(?int $branchId, string $dateFrom, string $dateTo): array
    {
        $dateFromStart = $dateFrom . ' 00:00:00';
        $dateToEnd = $dateTo . ' 23:59:59';
        $ids = collect();

        $ids = $ids->merge(
            WarehouseMovementDetail::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereHas('warehouseMovement.movement', fn ($q) => $q->whereBetween('moved_at', [$dateFromStart, $dateToEnd]))
                ->pluck('product_id')
        );

        $ids = $ids->merge(
            SalesMovementDetail::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereHas('salesMovement.movement', fn ($q) => $q->whereBetween('moved_at', [$dateFromStart, $dateToEnd]))
                ->pluck('product_id')
        );

        return $ids->unique()->filter()->values()->all();
    }
}
