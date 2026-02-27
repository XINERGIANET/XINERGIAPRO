<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Operation;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\PurchaseMovement;
use App\Models\PurchaseMovementDetail;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $viewId = $request->input('view_id');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $branchId = (int) session('branch_id');
        $movementType = $this->resolvePurchaseMovementType();

        $operaciones = $this->resolveOperations($viewId, $branchId);

        $purchases = Movement::query()
            ->with([
                'person',
                'movementType',
                'documentType',
                'purchaseMovement.details.product',
                'purchaseMovement.details.unit',
            ])
            ->where('movement_type_id', $movementType->id)
            ->where('branch_id', $branchId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('user_name', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
            'perPage' => $perPage,
            'viewId' => $viewId,
            'operaciones' => $operaciones,
        ]);
    }

    public function create(Request $request)
    {
        return view('purchases.create', $this->getFormData($request));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePurchase($request);
        $branchId = (int) session('branch_id');
        $user = $request->user();

        DB::transaction(function () use ($validated, $branchId, $user) {
            $movementType = $this->resolvePurchaseMovementType();
            $person = Person::query()->where('id', $validated['person_id'])->where('branch_id', $branchId)->firstOrFail();
            $documentType = DocumentType::query()->findOrFail($validated['document_type_id']);
            $responsibleName = $user?->person
                ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                : ($user?->name ?? 'Sistema');

            $totals = $this->calculateTotals(
                $validated['items'],
                (float) $validated['tax_rate_percent'],
                $validated['includes_tax']
            );

            $movement = Movement::query()->create([
                'number' => $validated['number'],
                'moved_at' => $validated['moved_at'],
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => $person->id,
                'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                'responsible_id' => $user?->id,
                'responsible_name' => $responsibleName,
                'comment' => $validated['comment'] ?? null,
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
            ]);

            $purchase = PurchaseMovement::query()->create([
                'series' => $validated['series'] ?? '001',
                'year' => (string) date('Y', strtotime($validated['moved_at'])),
                'detail_type' => $validated['detail_type'],
                'includes_tax' => $validated['includes_tax'],
                'payment_type' => $validated['payment_type'],
                'affects_cash' => $validated['affects_cash'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'affects_kardex' => $validated['affects_kardex'],
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail((int) $item['product_id']);
                $quantity = (float) $item['quantity'];
                $amount = (float) $item['amount'];
                $unitId = (int) $item['unit_id'];

                PurchaseMovementDetail::query()->create([
                    'detail_type' => $validated['detail_type'],
                    'purchase_movement_id' => $purchase->id,
                    'code' => (string) ($product->code ?? 'SIN-CODIGO'),
                    'description' => (string) ($item['description'] ?? $product->description ?? 'Sin descripcion'),
                    'product_id' => $product->id,
                    'product_json' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unitId,
                    'tax_rate_id' => null,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'comment' => (string) ($item['comment'] ?? ''),
                    'status' => 'E',
                    'branch_id' => $branchId,
                ]);

                if ($validated['affects_kardex'] === 'S') {
                    $this->incrementBranchStock($branchId, $product->id, $quantity);
                }
            }
        });

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra registrada correctamente.');
    }

    public function edit(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);

        return view('purchases.edit', array_merge(
            $this->getFormData($request),
            ['purchase' => $purchase]
        ));
    }

    public function update(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);

        $validated = $this->validatePurchase($request);
        $branchId = (int) session('branch_id');

        DB::transaction(function () use ($purchase, $validated, $branchId) {
            $documentType = DocumentType::query()->findOrFail($validated['document_type_id']);
            $person = Person::query()->where('id', $validated['person_id'])->where('branch_id', $branchId)->firstOrFail();
            $user = request()->user();
            $responsibleName = $user?->person
                ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                : ($user?->name ?? 'Sistema');

            $oldPurchase = $purchase->purchaseMovement;
            $oldDetails = $oldPurchase->details;

            if ($oldPurchase->affects_kardex === 'S') {
                foreach ($oldDetails as $detail) {
                    if ($detail->product_id) {
                        $this->decrementBranchStock($branchId, (int) $detail->product_id, (float) $detail->quantity);
                    }
                }
            }

            $totals = $this->calculateTotals(
                $validated['items'],
                (float) $validated['tax_rate_percent'],
                $validated['includes_tax']
            );

            $purchase->update([
                'number' => $validated['number'],
                'moved_at' => $validated['moved_at'],
                'person_id' => $person->id,
                'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                'responsible_id' => $user?->id,
                'responsible_name' => $responsibleName,
                'comment' => $validated['comment'] ?? null,
                'document_type_id' => $documentType->id,
            ]);

            $oldPurchase->update([
                'series' => $validated['series'] ?? '001',
                'year' => (string) date('Y', strtotime($validated['moved_at'])),
                'detail_type' => $validated['detail_type'],
                'includes_tax' => $validated['includes_tax'],
                'payment_type' => $validated['payment_type'],
                'affects_cash' => $validated['affects_cash'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'affects_kardex' => $validated['affects_kardex'],
            ]);

            PurchaseMovementDetail::query()
                ->where('purchase_movement_id', $oldPurchase->id)
                ->delete();

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail((int) $item['product_id']);
                $quantity = (float) $item['quantity'];
                $amount = (float) $item['amount'];
                $unitId = (int) $item['unit_id'];

                PurchaseMovementDetail::query()->create([
                    'detail_type' => $validated['detail_type'],
                    'purchase_movement_id' => $oldPurchase->id,
                    'code' => (string) ($product->code ?? 'SIN-CODIGO'),
                    'description' => (string) ($item['description'] ?? $product->description ?? 'Sin descripcion'),
                    'product_id' => $product->id,
                    'product_json' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unitId,
                    'tax_rate_id' => null,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'comment' => (string) ($item['comment'] ?? ''),
                    'status' => 'E',
                    'branch_id' => $branchId,
                ]);

                if ($validated['affects_kardex'] === 'S') {
                    $this->incrementBranchStock($branchId, $product->id, $quantity);
                }
            }
        });

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra actualizada correctamente.');
    }

    public function destroy(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);
        $branchId = (int) session('branch_id');

        DB::transaction(function () use ($purchase, $branchId) {
            $purchaseModel = $purchase->purchaseMovement;
            if ($purchaseModel->affects_kardex === 'S') {
                foreach ($purchaseModel->details as $detail) {
                    if ($detail->product_id) {
                        $this->decrementBranchStock($branchId, (int) $detail->product_id, (float) $detail->quantity);
                    }
                }
            }

            PurchaseMovementDetail::query()->where('purchase_movement_id', $purchaseModel->id)->delete();
            $purchaseModel->delete();
            $purchase->delete();
        });

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra eliminada correctamente.');
    }

    private function validatePurchase(Request $request): array
    {
        return $request->validate([
            'moved_at' => ['required', 'date'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'number' => ['required', 'string', 'max:50'],
            'series' => ['nullable', 'string', 'max:20'],
            'detail_type' => ['required', Rule::in(['DETALLADO', 'GLOSA'])],
            'includes_tax' => ['required', Rule::in(['S', 'N'])],
            'payment_type' => ['required', Rule::in(['CONTADO', 'CREDITO'])],
            'affects_cash' => ['required', Rule::in(['S', 'N'])],
            'affects_kardex' => ['required', Rule::in(['S', 'N'])],
            'currency' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.001'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.comment' => ['nullable', 'string'],
        ]);
    }

    private function getFormData(Request $request): array
    {
        $branchId = (int) session('branch_id');
        $movementType = $this->resolvePurchaseMovementType();

        $people = Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', $movementType->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = Unit::query()->orderBy('description')->get(['id', 'description']);

        $products = Product::query()
            ->join('product_branch', function ($join) use ($branchId) {
                $join->on('product_branch.product_id', '=', 'products.id')
                    ->where('product_branch.branch_id', '=', $branchId);
            })
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->where('products.type', 'PRODUCT')
            ->orderBy('products.description')
            ->get([
                'products.id',
                'products.code',
                'products.description',
                'products.base_unit_id as unit_sale',
                'product_branch.price',
                'units.description as unit_name',
            ]);

        $defaultTaxRate = (float) (TaxRate::query()->where('status', true)->orderBy('order_num')->value('tax_rate') ?? 18);
        $purchaseNumberPreview = $documentTypes->isNotEmpty()
            ? $this->generatePurchaseNumber((int) $documentTypes->first()->id, $branchId)
            : '00000001';

        return [
            'viewId' => $request->input('view_id'),
            'people' => $people,
            'documentTypes' => $documentTypes,
            'units' => $units,
            'products' => $products,
            'defaultTaxRate' => $defaultTaxRate,
            'purchaseNumberPreview' => $purchaseNumberPreview,
        ];
    }

    private function resolvePurchaseMovementType(): MovementType
    {
        $movementType = MovementType::query()
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%compra%')
                    ->orWhere('description', 'ILIKE', '%purchase%');
            })
            ->orderBy('id')
            ->first();

        if (!$movementType) {
            $movementType = MovementType::query()->find(3);
        }

        if (!$movementType) {
            $movementType = MovementType::query()->orderBy('id')->firstOrFail();
        }

        return $movementType;
    }

    private function resolveOperations($viewId, int $branchId)
    {
        $profileId = session('profile_id') ?? auth()->user()?->profile_id;
        if (!$viewId || !$branchId || !$profileId) {
            return collect();
        }

        return Operation::query()
            ->select('operations.*')
            ->join('branch_operation', function ($join) use ($branchId) {
                $join->on('branch_operation.operation_id', '=', 'operations.id')
                    ->where('branch_operation.branch_id', $branchId)
                    ->where('branch_operation.status', 1)
                    ->whereNull('branch_operation.deleted_at');
            })
            ->join('operation_profile_branch', function ($join) use ($branchId, $profileId) {
                $join->on('operation_profile_branch.operation_id', '=', 'operations.id')
                    ->where('operation_profile_branch.branch_id', $branchId)
                    ->where('operation_profile_branch.profile_id', $profileId)
                    ->where('operation_profile_branch.status', 1)
                    ->whereNull('operation_profile_branch.deleted_at');
            })
            ->where('operations.status', 1)
            ->where('operations.view_id', $viewId)
            ->whereNull('operations.deleted_at')
            ->orderBy('operations.id')
            ->distinct()
            ->get();
    }

    private function generatePurchaseNumber(int $documentTypeId, int $branchId): string
    {
        $year = (int) now()->year;

        $query = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year)
            ->lockForUpdate();

        $lastCorrelative = 0;
        foreach ($query->pluck('number') as $number) {
            $raw = trim((string) $number);
            if ($raw !== '' && preg_match('/^\d+$/', $raw) === 1) {
                $lastCorrelative = max($lastCorrelative, (int) $raw);
            }
        }

        return str_pad((string) ($lastCorrelative + 1), 8, '0', STR_PAD_LEFT);
    }

    private function calculateTotals(array $items, float $taxRatePercent, string $includesTax): array
    {
        $lineTotal = 0.0;
        foreach ($items as $item) {
            $lineTotal += ((float) $item['quantity']) * ((float) $item['amount']);
        }

        $lineTotal = round($lineTotal, 2);
        $rate = round($taxRatePercent / 100, 6);

        if ($includesTax === 'S') {
            $subtotal = $rate > 0 ? round($lineTotal / (1 + $rate), 2) : $lineTotal;
            $tax = round($lineTotal - $subtotal, 2);
            $total = $lineTotal;
        } else {
            $subtotal = $lineTotal;
            $tax = round($subtotal * $rate, 2);
            $total = round($subtotal + $tax, 2);
        }

        return compact('subtotal', 'tax', 'total');
    }

    private function incrementBranchStock(int $branchId, int $productId, float $quantity): void
    {
        $pb = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$pb) {
            throw new \RuntimeException("El producto {$productId} no está configurado para esta sucursal.");
        }

        $pb->update([
            'stock' => round(((float) $pb->stock) + $quantity, 4),
        ]);
    }

    private function decrementBranchStock(int $branchId, int $productId, float $quantity): void
    {
        $pb = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$pb) {
            throw new \RuntimeException("El producto {$productId} no está configurado para esta sucursal.");
        }

        $newStock = round(((float) $pb->stock) - $quantity, 4);
        if ($newStock < 0) {
            throw new \RuntimeException("No se puede dejar stock negativo para el producto {$productId}.");
        }

        $pb->update(['stock' => $newStock]);
    }

    private function assertValidPurchaseMovement(Movement $movement): void
    {
        $movement->loadMissing('purchaseMovement');
        if (!$movement->purchaseMovement) {
            abort(404, 'Compra no encontrada.');
        }

        $branchId = (int) session('branch_id');
        if ((int) $movement->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para esta compra.');
        }
    }
}
