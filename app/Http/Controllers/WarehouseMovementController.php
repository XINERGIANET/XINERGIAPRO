<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Models\MovementType;
use App\Models\DocumentType;
use App\Models\Operation;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Services\KardexSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseMovementController extends Controller
{
    public function input(Request $request)
    {
        $branchId = session('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            abort(403, 'No se ha seleccionado una sucursal');
        }

        $productIdsInBranch = $this->productIdsForWarehouseBranch((int) $branchId);
        $productsQuery = Product::where('classification', 'GOOD')
            ->with(['category', 'baseUnit'])
            ->orderBy('description');
        if ($productIdsInBranch !== []) {
            $productsQuery->whereIn('id', $productIdsInBranch);
        } else {
            $productsQuery->whereRaw('1 = 0');
        }
        $products = $productsQuery->get();


        // Cargar productBranches del branch para tener información de stock y precio
        $productBranches = ProductBranch::where('branch_id', $branchId)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        $editingMovement = null;
        $editingCart = [];
        $editingComment = '';
        $editingReason = 'AJUSTE DE ENTRADA';
        $warehouseMovementId = $request->integer('warehouse_movement_id');
        if ($warehouseMovementId > 0) {
            $wm = WarehouseMovement::with(['movement.documentType', 'details.product.baseUnit'])
                ->where('id', $warehouseMovementId)
                ->where('branch_id', $branchId)
                ->first();
            if ($wm) {
                $docName = strtolower((string) ($wm->movement?->documentType?->name ?? ''));
                $docId = (int) ($wm->movement?->document_type_id ?? 0);
                $isEntry = $docId === 7 || str_contains($docName, 'entrada') || str_contains($docName, 'entry');
                if ($isEntry) {
                    $editingMovement = $wm;
                    $editingComment = (string) ($wm->movement?->comment ?? '');
                    $editingReason = (string) ($wm->movement?->reason ?? 'AJUSTE DE ENTRADA');
                    foreach ($wm->details as $d) {
                        $pb = $productBranches->get($d->product_id);
                        $editingCart[] = [
                            'id' => $d->product_id,
                            'name' => $d->product_snapshot['description'] ?? $d->product?->description ?? 'Sin nombre',
                            'code' => $d->product_snapshot['code'] ?? $d->product?->code ?? '',
                            'unit' => $d->unit?->description ?? $d->product?->baseUnit?->description ?? 'Unidad',
                            'quantity' => (float) $d->quantity,
                            'unitCost' => $pb ? (float) ($pb->avg_cost ?? $pb->price ?? 0) : 0,
                        ];
                    }
                }
            }
        }

        return view('warehouse_movements.entry', [
            'products' => $products,
            'productBranches' => $productBranches,
            'viewId' => $viewId,
            'title' => 'Entrada de Productos',
            'editingMovement' => $editingMovement,
            'editingCart' => $editingCart,
            'editingComment' => $editingComment,
            'editingReason' => $editingReason,
        ]);
    }

    public function entry(Request $request)
    {
        // Alias para mantener compatibilidad
        return $this->input($request);
    }

    public function output(Request $request)
    {
        $branchId = session('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            abort(403, 'No se ha seleccionado una sucursal');
        }

        $products = Product::where('classification', 'GOOD')
            ->with(['category', 'baseUnit'])
            ->orderBy('description')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::with(['category', 'baseUnit'])
                ->orderBy('description')
                ->get();
        }

        $productBranches = ProductBranch::where('branch_id', $branchId)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        $productsMapped = $products->map(function ($product) use ($productBranches) {
            $productBranch = $productBranches->get($product->id);
            $imageUrl = null;
            if ($product->image && !empty(trim($product->image))) {
                $imagePath = trim($product->image);
                if (strpos($imagePath, '\\') !== false || strpos($imagePath, 'C:') !== false || strpos($imagePath, 'Temp') !== false || strpos($imagePath, 'Windows') !== false) {
                    $imageUrl = null;
                } elseif (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                    $imageUrl = (strpos($imagePath, '\\') === false && strpos($imagePath, 'C:') === false) ? $imagePath : null;
                } elseif (str_starts_with($imagePath, 'storage/')) {
                    $imageUrl = asset($imagePath);
                } elseif (str_starts_with($imagePath, '/storage/')) {
                    $imageUrl = asset(ltrim($imagePath, '/'));
                } else {
                    $imageUrl = asset('storage/' . $imagePath);
                }
            }
            return [
                'id' => $product->id,
                'code' => $product->code ?? '',
                'name' => $product->description ?? 'Sin nombre',
                'img' => $imageUrl,
                'category' => $product->category ? $product->category->description : 'Sin categoría',
                'unit' => $product->baseUnit ? $product->baseUnit->description : 'Unidad',
                'currentStock' => $productBranch ? (int) ($productBranch->stock ?? 0) : 0,
                'price' => $productBranch ? (float) ($productBranch->price ?? 0) : 0,
            ];
        })->filter(fn($p) => !empty($p['name']))->values();

        $currentCompanyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        $profileId = (int) (session('profile_id') ?? optional(auth()->user())->profile_id);
        $allowedBranchIds = DB::table('profile_branch')
            ->where('profile_id', $profileId)
            ->whereNull('deleted_at')
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $isAdmin = $this->isAdminUser();
        $targetBranchesQuery = Branch::query()
            ->where('company_id', $currentCompanyId)
            ->where('id', '!=', $branchId);
        if (!$isAdmin) {
            if (empty($allowedBranchIds)) {
                $targetBranchesQuery->whereRaw('1=0');
            } else {
                $targetBranchesQuery->whereIn('id', $allowedBranchIds);
            }
        }
        $targetBranches = $targetBranchesQuery
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);

        $editingMovement = null;
        $editingCart = [];
        $editingComment = '';
        $warehouseMovementId = $request->integer('warehouse_movement_id');
        if ($warehouseMovementId > 0) {
            $wm = WarehouseMovement::with(['movement.documentType', 'details.product.baseUnit'])
                ->where('id', $warehouseMovementId)
                ->where('branch_id', $branchId)
                ->first();
            if ($wm) {
                $docName = strtolower((string) ($wm->movement?->documentType?->name ?? ''));
                $docId = (int) ($wm->movement?->document_type_id ?? 0);
                $isOutput = $docId === 8 || str_contains($docName, 'salida') || str_contains($docName, 'exit') || str_contains($docName, 'output');
                if ($isOutput) {
                    $editingMovement = $wm;
                    $editingComment = (string) ($wm->movement?->comment ?? '');
                    foreach ($wm->details as $d) {
                        $pb = $productBranches->get($d->product_id);
                        $stockNow = $pb ? (float) ($pb->stock ?? 0) : 0;
                        $qtyInMovement = (float) $d->quantity;
                        $editingCart[] = [
                            'id' => $d->product_id,
                            'name' => $d->product_snapshot['description'] ?? $d->product?->description ?? 'Sin nombre',
                            'code' => $d->product_snapshot['code'] ?? $d->product?->code ?? '',
                            'unit' => $d->unit?->description ?? $d->product?->baseUnit?->description ?? 'Unidad',
                            'quantity' => $qtyInMovement,
                            'currentStock' => (int) round($stockNow + $qtyInMovement),
                        ];
                    }
                    $productsMapped = $productsMapped->map(function ($p) use ($wm) {
                        $qtyInMovement = $wm->details->where('product_id', $p['id'])->sum('quantity');
                        if ($qtyInMovement > 0) {
                            $p['currentStock'] = (int) (($p['currentStock'] ?? 0) + $qtyInMovement);
                        }
                        return $p;
                    })->values();
                }
            }
        }

        return view('warehouse_movements.output', [
            'products' => $products,
            'productBranches' => $productBranches,
            'productsMapped' => $productsMapped,
            'branchId' => $branchId,
            'viewId' => $viewId,
            'targetBranches' => $targetBranches,
            'title' => 'Salida de Productos',
            'editingMovement' => $editingMovement,
            'editingCart' => $editingCart,
            'editingComment' => $editingComment,
        ]);
    }

    public function outputStore(Request $request)
    {
        $branchId = (int) session('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'No se ha seleccionado una sucursal',
            ], 403);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('product_branch', 'product_id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);
        $userId = session('user_id');
        $userName = session('user_name') ?? 'Sistema';
        $personId = session('person_id');
        $personName = session('person_fullname') ?? 'Sistema';

        try {
            DB::beginTransaction();

            $movementType = MovementType::where(function ($query) {
                $query->where('description', 'like', '%Almacén%')
                    ->orWhere('description', 'like', '%Warehouse%')
                    ->orWhere('description', 'like', '%Inventario%');
            })->first();

            if (!$movementType) {
                $movementType = MovementType::first();
                if (!$movementType) {
                    throw new \Exception('No se encontró un tipo de movimiento válido para almacén.');
                }
            }

            // DocumentType para salida (ID 8 según la base de datos)
            $documentType = DocumentType::find(8);
            if (!$documentType) {
                $documentType = DocumentType::where(function ($query) {
                    $query->where('name', 'like', '%Salida%')
                        ->orWhere('name', 'like', '%exit%')
                        ->orWhere('name', 'like', '%output%');
                })->first();
            }
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }
            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido para salida.');
            }

            $number = $this->generateWarehouseCorrelativeNumber((int) $branchId, $documentType, now());

            // Validar stock antes de crear el movimiento
            foreach ($request->items as $item) {
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->first();
                $stock = $productBranch ? (float) $productBranch->stock : 0;
                if ($stock < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    $name = $product ? $product->description : 'Producto #' . $item['product_id'];
                    throw new \Exception("Stock insuficiente para \"{$name}\". Disponible: {$stock}, solicitado: {$item['quantity']}.");
                }
            }

            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId > 0 ? $personId : null,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $request->comment ?? 'Salida de productos del almacén',
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);

            $warehouseMovement = WarehouseMovement::create([
                'status' => 'FINALIZED',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    throw new \Exception('Producto sin registro en esta sucursal (product_id: ' . $item['product_id'] . ').');
                }

                $currentStock = (float) ($productBranch->stock ?? 0);
                if ($currentStock < $item['quantity']) {
                    throw new \Exception('Stock insuficiente para ' . ($product->description ?? $product->id) . '. Disponible: ' . $currentStock);
                }

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $warehouseMovement->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                        'marca' => $product->marca,
                    ],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                $newStock = $currentStock - $item['quantity'];
                $productBranch->update(['stock' => $newStock]);
            }

            app(KardexSyncService::class)->syncMovement($movement);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos guardada correctamente',
                'movement_id' => $movement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la salida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function transferStore(Request $request)
    {
        $request->validate([
            'to_branch_id' => 'required|integer|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);

        $fromBranchId = (int) session('branch_id');
        $toBranchId = (int) $request->input('to_branch_id');
        $userId = session('user_id');
        $userName = session('user_name') ?? 'Sistema';
        $personId = session('person_id');
        $personName = session('person_fullname') ?? 'Sistema';

        if ($fromBranchId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se ha seleccionado sucursal de origen.',
            ], 422);
        }
        if ($toBranchId === $fromBranchId) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal destino debe ser distinta de la sucursal actual.',
            ], 422);
        }

        $originBranch = Branch::query()->find($fromBranchId);
        $destinationBranch = Branch::query()->find($toBranchId);
        if (!$originBranch || !$destinationBranch || (int) $originBranch->company_id !== (int) $destinationBranch->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal destino no pertenece a la misma empresa.',
            ], 422);
        }

        $profileId = (int) (session('profile_id') ?? optional(auth()->user())->profile_id);
        $canUseDestination = DB::table('profile_branch')
            ->where('profile_id', $profileId)
            ->where('branch_id', $toBranchId)
            ->whereNull('deleted_at')
            ->exists();
        if (!$canUseDestination && !$this->isAdminUser()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a la sucursal destino.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $movementType = MovementType::where(function ($query) {
                $query->where('description', 'like', '%Almac%')
                    ->orWhere('description', 'like', '%Warehouse%')
                    ->orWhere('description', 'like', '%Inventario%');
            })->first() ?? MovementType::firstOrFail();

            $outDocumentType = DocumentType::query()
                ->where('movement_type_id', $movementType->id)
                ->where(function ($query) {
                    $query->where('name', 'like', '%Salida%')->orWhere('name', 'like', '%Transferencia%');
                })
                ->first() ?? DocumentType::find(8) ?? DocumentType::query()->where('movement_type_id', $movementType->id)->firstOrFail();

            $inDocumentType = DocumentType::query()
                ->where('movement_type_id', $movementType->id)
                ->where(function ($query) {
                    $query->where('name', 'like', '%Entrada%')->orWhere('name', 'like', '%Transferencia%');
                })
                ->first() ?? DocumentType::find(7) ?? DocumentType::query()->where('movement_type_id', $movementType->id)->firstOrFail();

            foreach ($request->items as $item) {
                $originStock = (float) ProductBranch::query()
                    ->where('branch_id', $fromBranchId)
                    ->where('product_id', (int) $item['product_id'])
                    ->value('stock');
                if ($originStock < (float) $item['quantity']) {
                    throw new \RuntimeException('Stock insuficiente para transferencia del producto ID ' . $item['product_id']);
                }
            }

            $outCount = Movement::where('document_type_id', $outDocumentType->id)
                ->where('branch_id', $fromBranchId)
                ->whereDate('created_at', Carbon::today())
                ->count();
            $outNumber = str_pad((string) ($outCount + 1), 8, '0', STR_PAD_LEFT);
            $outMovement = Movement::create([
                'number' => $outNumber,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId > 0 ? $personId : null,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $request->comment ?? 'Transferencia de stock a sucursal ' . $toBranchId,
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $outDocumentType->id,
                'branch_id' => $fromBranchId,
                'parent_movement_id' => null,
            ]);
            $outWarehouse = WarehouseMovement::create([
                'status' => 'FINALIZED',
                'movement_id' => $outMovement->id,
                'branch_id' => $fromBranchId,
            ]);

            $inCount = Movement::where('document_type_id', $inDocumentType->id)
                ->where('branch_id', $toBranchId)
                ->whereDate('created_at', Carbon::today())
                ->count();
            $inNumber = str_pad((string) ($inCount + 1), 8, '0', STR_PAD_LEFT);
            $inMovement = Movement::create([
                'number' => $inNumber,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId > 0 ? $personId : null,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $request->comment ?? 'Transferencia recibida desde sucursal ' . $fromBranchId,
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $inDocumentType->id,
                'branch_id' => $toBranchId,
                'parent_movement_id' => $outMovement->id,
            ]);
            $inWarehouse = WarehouseMovement::create([
                'status' => 'FINALIZED',
                'movement_id' => $inMovement->id,
                'branch_id' => $toBranchId,
            ]);

            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail((int) $item['product_id']);
                $qty = (float) $item['quantity'];

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $outWarehouse->id,
                    'product_id' => $product->id,
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description, 'marca' => $product->marca],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => $qty,
                    'comment' => 'Transferencia salida',
                    'status' => 'A',
                    'branch_id' => $fromBranchId,
                ]);

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $inWarehouse->id,
                    'product_id' => $product->id,
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description, 'marca' => $product->marca],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => $qty,
                    'comment' => 'Transferencia ingreso',
                    'status' => 'A',
                    'branch_id' => $toBranchId,
                ]);

                $origin = ProductBranch::query()->where('branch_id', $fromBranchId)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
                $destiny = ProductBranch::query()->where('branch_id', $toBranchId)->where('product_id', $product->id)->lockForUpdate()->first();
                if (!$destiny) {
                    $destiny = ProductBranch::query()->create([
                        'product_id' => $product->id,
                        'branch_id' => $toBranchId,
                        'stock' => 0,
                        'price' => (float) $origin->price,
                        'avg_cost' => (float) ($origin->avg_cost ?? $origin->price ?? 0),
                        'status' => 'A',
                    ]);
                }

                $origin->update(['stock' => (float) $origin->stock - $qty]);
                $destiny->update(['stock' => (float) $destiny->stock + $qty]);
            }

            app(KardexSyncService::class)->syncMovement($outMovement);
            app(KardexSyncService::class)->syncMovement($inMovement);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Transferencia registrada correctamente.',
                'out_movement_id' => $outMovement->id,
                'in_movement_id' => $inMovement->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al transferir stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();

        // Obtener operaciones relacionadas con la vista, branch y profile
        if ($viewId && $branchId && $profileId) {
            $operaciones = Operation::query()
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

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $warehouseMovements = WarehouseMovement::query()
            ->where('branch_id', $branchId)
            ->with(['movement.movementType', 'movement.documentType', 'branch', 'details.unit', 'details.product'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('movement', function ($q) use ($search) {
                    $q->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('user_name', 'ILIKE', "%{$search}%");
                });
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereHas('movement', function ($q) use ($dateFrom) {
                    $q->whereDate('moved_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereHas('movement', function ($q) use ($dateTo) {
                    $q->whereDate('moved_at', '<=', $dateTo);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('warehouse_movements.index', [
            'warehouseMovements' => $warehouseMovements,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Movimientos de Almacén',
        ]);
    }

    public function store(Request $request)
    {
        $branchId = (int) session('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'No se ha seleccionado una sucursal',
            ], 403);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('product_branch', 'product_id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:120',
            'comment' => 'nullable|string|max:500',
        ]);
        $userId = (int) session('user_id');
        $userName = (string) (session('user_name') ?? 'Sistema');
        $personId = (int) (session('person_id') ?? 0);
        $personName = (string) (session('person_fullname') ?? $userName);
        $reason = trim((string) $request->input('reason', 'AJUSTE DE ENTRADA'));
        if ($reason === '') {
            $reason = 'AJUSTE DE ENTRADA';
        }

        try {
            DB::beginTransaction();

            // Buscar MovementType para almacén (asumiendo que existe uno con descripción relacionada)
            $movementType = MovementType::where(function ($query) {
                $query->where('description', 'like', '%Almacén%')
                    ->orWhere('description', 'like', '%Warehouse%')
                    ->orWhere('description', 'like', '%Inventario%');
            })->first();

            if (!$movementType) {
                // Si no existe, usar el primero disponible
                $movementType = MovementType::first();
                if (!$movementType) {
                    throw new \Exception('No se encontró un tipo de movimiento válido para almacén.');
                }
            }

            // Buscar DocumentType para entrada (ID 7 según la base de datos)
            // Primero intentar buscar por ID 7 directamente
            $documentType = DocumentType::find(7);

            // Si no existe el ID 7, buscar por nombre "Entrada" sin filtrar por movement_type_id
            if (!$documentType) {
                $documentType = DocumentType::where(function ($query) {
                    $query->where('name', 'like', '%Entrada%')
                        ->orWhere('name', 'like', '%entry%');
                })->first();
            }

            // Si aún no se encuentra, usar el primero del movement_type encontrado
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }

            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido para entrada.');
            }

            $number = $this->generateWarehouseCorrelativeNumber((int) $branchId, $documentType, now());

            // Crear Movement
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId > 0 ? $personId : null,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $userName,
                'comment' => $request->comment,
                'reason' => $reason,
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);

            // Crear WarehouseMovement
            $warehouseMovement = WarehouseMovement::create([
                'status' => 'FINALIZED',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            // Crear detalles y actualizar stock
            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    throw new \Exception('El producto no pertenece al stock de esta sucursal.');
                }

                // Crear WarehouseMovementDetail
                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $warehouseMovement->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                        'marca' => $product->marca,
                    ],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                // Actualizar stock y costo promedio ponderado.
                $quantity = (float) $item['quantity'];
                $unitCost = round((float) ($item['unit_cost'] ?? 0), 6);
                $currentStock = (float) ($productBranch->stock ?? 0);
                $currentCost = (float) ($productBranch->avg_cost ?? 0);
                if ($currentCost <= 0) {
                    $currentCost = (float) ($productBranch->price ?? 0);
                }
                $newStock = $currentStock + $quantity;
                $newAvgCost = $currentCost;
                if ($unitCost > 0 && $newStock > 0) {
                    $newAvgCost = round((($currentStock * $currentCost) + ($quantity * $unitCost)) / $newStock, 6);
                }

                $productBranch->update([
                    'stock' => $newStock,
                    'avg_cost' => $newAvgCost,
                ]);
            }

            app(KardexSyncService::class)->syncMovement($movement);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrada de productos guardada correctamente',
                'movement_id' => $movement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la entrada: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEntry(Request $request, $warehouseMovement)
    {
        $branchId = (int) session('branch_id');
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('product_branch', 'product_id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
        ]);
        $wm = WarehouseMovement::with(['movement.documentType', 'details'])
            ->where('id', $warehouseMovement->id ?? $warehouseMovement)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $docName = strtolower((string) ($wm->movement?->documentType?->name ?? ''));
        $docId = (int) ($wm->movement?->document_type_id ?? 0);
        if ($docId !== 7 && !str_contains($docName, 'entrada') && !str_contains($docName, 'entry')) {
            return response()->json(['success' => false, 'message' => 'El movimiento no es de tipo entrada.'], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($wm->details as $d) {
                $pb = ProductBranch::where('branch_id', $branchId)->where('product_id', $d->product_id)->lockForUpdate()->first();
                if ($pb) {
                    $newStock = (float) $pb->stock - (float) $d->quantity;
                    $pb->update(['stock' => max(0, $newStock)]);
                }
            }

            app(KardexSyncService::class)->deleteMovement($wm->movement_id);
            $wm->details()->delete();

            $movement = $wm->movement;
            $movement->update([
                'comment' => $request->input('comment'),
                'reason' => trim((string) $request->input('reason', $movement->reason)),
            ]);

            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();
                if (!$productBranch) {
                    throw new \Exception('El producto no pertenece al stock de esta sucursal.');
                }

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $wm->id,
                    'product_id' => $product->id,
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description, 'marca' => $product->marca],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                $quantity = (float) $item['quantity'];
                $unitCost = round((float) ($item['unit_cost'] ?? 0), 6);
                $currentStock = (float) ($productBranch->stock ?? 0);
                $currentCost = (float) ($productBranch->avg_cost ?? 0);
                if ($currentCost <= 0) {
                    $currentCost = (float) ($productBranch->price ?? 0);
                }
                $newStock = $currentStock + $quantity;
                $newAvgCost = $currentCost;
                if ($unitCost > 0 && $newStock > 0) {
                    $newAvgCost = round((($currentStock * $currentCost) + ($quantity * $unitCost)) / $newStock, 6);
                }
                $productBranch->update(['stock' => $newStock, 'avg_cost' => $newAvgCost]);
            }

            $movement->loadMissing(['warehouseMovement.details.unit']);
            app(KardexSyncService::class)->syncMovement($movement);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrada actualizada correctamente',
                'movement_id' => $movement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la entrada: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateOutput(Request $request, $warehouseMovement)
    {
        $branchId = (int) session('branch_id');
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('product_branch', 'product_id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);
        $wm = WarehouseMovement::with(['movement.documentType', 'details'])
            ->where('id', $warehouseMovement->id ?? $warehouseMovement)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $docName = strtolower((string) ($wm->movement?->documentType?->name ?? ''));
        $docId = (int) ($wm->movement?->document_type_id ?? 0);
        if ($docId !== 8 && !str_contains($docName, 'salida') && !str_contains($docName, 'exit') && !str_contains($docName, 'output')) {
            return response()->json(['success' => false, 'message' => 'El movimiento no es de tipo salida.'], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($wm->details as $d) {
                $pb = ProductBranch::where('branch_id', $branchId)->where('product_id', $d->product_id)->lockForUpdate()->first();
                if ($pb) {
                    $newStock = (float) $pb->stock + (float) $d->quantity;
                    $pb->update(['stock' => $newStock]);
                }
            }

            app(KardexSyncService::class)->deleteMovement($wm->movement_id);
            $wm->details()->delete();

            $wm->movement->update([
                'comment' => $request->input('comment') ?: $wm->movement->comment,
            ]);

            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();
                if (!$productBranch) {
                    throw new \Exception('Producto sin registro en esta sucursal (product_id: ' . $item['product_id'] . ').');
                }
                $currentStock = (float) ($productBranch->stock ?? 0);
                if ($currentStock < (float) $item['quantity']) {
                    throw new \Exception('Stock insuficiente para ' . ($product->description ?? $product->id) . '. Disponible: ' . $currentStock);
                }

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $wm->id,
                    'product_id' => $product->id,
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description, 'marca' => $product->marca],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                $newStock = $currentStock - (float) $item['quantity'];
                $productBranch->update(['stock' => $newStock]);
            }

            $wm->movement->loadMissing(['warehouseMovement.details.unit']);
            app(KardexSyncService::class)->syncMovement($wm->movement);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida actualizada correctamente',
                'movement_id' => $wm->movement_id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la salida: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Productos dados de alta en la sucursal (tabla product_branch).
     *
     * @return list<int>
     */
    private function productIdsForWarehouseBranch(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        return ProductBranch::query()
            ->where('branch_id', $branchId)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function isAdminUser(): bool
    {
        $user = auth()->user();
        $profileName = strtoupper((string) ($user?->profile?->name ?? ''));

        return (int) ($user?->profile_id ?? 0) === 1 || str_contains($profileName, 'ADMIN');
    }

    private function generateWarehouseCorrelativeNumber(int $branchId, DocumentType $documentType, \Carbon\CarbonInterface $when): string
    {
        $stockType = (string) ($documentType->stock ?? '');
        $prefix = $stockType === 'add' ? 'E' : ($stockType === 'subtract' ? 'S' : 'M');
        $date = $when->format('Ymd');

        $lastNumber = (string) (Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', (int) $documentType->id)
            ->where('number', 'like', $prefix . '-' . $date . '-%')
            ->orderByDesc('id')
            ->value('number') ?? '');

        $seq = 0;
        if ($lastNumber !== '' && preg_match('/^' . preg_quote($prefix, '/') . '\-' . $date . '\-(\d{1,6})$/', $lastNumber, $m)) {
            $seq = (int) $m[1];
        }

        $next = $seq + 1;
        return $prefix . '-' . $date . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function show($warehouseMovement)
    {
        $warehouseMovement = WarehouseMovement::with([
            'movement.movementType',
            'movement.documentType',
            'movement.branch',
            'branch',
            'details.unit',
            'details.product',
        ])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        return view('warehouse_movements.show', [
            'warehouseMovement' => $warehouseMovement,
            'title' => 'Ver Movimiento de Almacén',
        ]);
    }
    public function edit($warehouseMovement)
    {
        $viewId = request('view_id');
        $warehouseMovement = WarehouseMovement::with([
            'movement.movementType',
            'movement.documentType',
            'movement.branch',
            'branch',
            'details.unit',
            'details.product',
        ])->findOrFail($warehouseMovement->id ?? $warehouseMovement);

        $docType = $warehouseMovement->movement?->documentType;
        $docName = strtolower((string) ($docType->name ?? ''));
        $docId = (int) ($docType->id ?? 0);
        $isEntry = $docId === 7
            || str_contains($docName, 'entrada')
            || str_contains($docName, 'entry');

        $params = $viewId ? ['view_id' => $viewId] : [];
        $params['warehouse_movement_id'] = $warehouseMovement->id;

        if ($isEntry) {
            return redirect()->route('warehouse_movements.input', $params);
        }
        return redirect()->route('warehouse_movements.output', $params);
    }
    public function update($warehouseMovement, Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:A,C',
        ]);
        $warehouseMovement = WarehouseMovement::with(['movement.movementType', 'movement.documentType', 'branch'])->findOrFail($warehouseMovement->id);
        $warehouseMovement->update($request->all());
        return redirect()->route('warehouse_movements.show', ['warehouseMovement' => $warehouseMovement->id])->with('success', 'Movimiento de Almacén actualizado correctamente');
    }

    public function destroy($warehouseMovement)
    {
        $warehouseMovement = WarehouseMovement::with(['movement', 'details'])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        $movement = $warehouseMovement->movement;
        $viewId = request('view_id');

        DB::transaction(function () use ($warehouseMovement, $movement) {
            $warehouseMovement->details()->delete();
            $warehouseMovement->delete();
            if ($movement) {
                $movement->delete();
            }
        });

        $redirectUrl = route('warehouse_movements.index', $viewId ? ['view_id' => $viewId] : []);
        return redirect($redirectUrl)->with('success', 'Movimiento de almacén eliminado correctamente.');
    }
}
