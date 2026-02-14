<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Models\MovementType;
use App\Models\DocumentType;
use App\Models\Operation;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\Person;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Models\WorkshopPurchaseRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseMovementController extends Controller
{
    public function input(Request $request)
    {
        $branchId = session('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            abort(403, 'No se ha seleccionado una sucursal');
        }

        // Cargar productos igual que OrderController - todos los productos de tipo PRODUCT
        $products = Product::where('type', 'PRODUCT')
            ->with(['category', 'baseUnit'])
            ->orderBy('description')
            ->get();

        // Si no hay productos con type PRODUCT, cargar todos los productos sin filtro
        if ($products->isEmpty()) {
            $products = Product::with(['category', 'baseUnit'])
                ->orderBy('description')
                ->get();
        }


        // Cargar productBranches del branch para tener información de stock y precio
        $productBranches = ProductBranch::where('branch_id', $branchId)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        $suppliers = Person::query()
            ->where('branch_id', $branchId)
            ->where(function ($query) {
                $query->where('first_name', '<>', '')
                    ->orWhere('last_name', '<>', '');
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        return view('warehouse_movements.entry', [
            'products' => $products,
            'productBranches' => $productBranches,
            'suppliers' => $suppliers,
            'viewId' => $viewId,
            'title' => 'Entrada de Productos',
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

        $products = Product::where('type', 'PRODUCT')
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
            ->where('status', 1)
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

        return view('warehouse_movements.output', [
            'products' => $products,
            'productBranches' => $productBranches,
            'productsMapped' => $productsMapped,
            'branchId' => $branchId,
            'viewId' => $viewId,
            'targetBranches' => $targetBranches,
            'title' => 'Salida de Productos',
        ]);
    }

    public function outputStore(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);

        $branchId = session('branch_id');
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

            // Generar número de movimiento (secuencia independiente por tipo de documento)
            $todayCount = Movement::where('document_type_id', $documentType->id)
                ->where('branch_id', $branchId)
                ->whereDate('created_at', Carbon::today())
                ->count();
            $number = str_pad($todayCount + 1, 8, '0', STR_PAD_LEFT);

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
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $personId ?? $userId,
                'responsible_name' => $personName,
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
            ->where('status', 1)
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
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $personId ?? $userId,
                'responsible_name' => $personName,
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
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $personId ?? $userId,
                'responsible_name' => $personName,
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
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => $qty,
                    'comment' => 'Transferencia salida',
                    'status' => 'A',
                    'branch_id' => $fromBranchId,
                ]);

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $inWarehouse->id,
                    'product_id' => $product->id,
                    'product_snapshot' => ['id' => $product->id, 'code' => $product->code, 'description' => $product->description],
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
            ->with(['movement.movementType', 'movement.documentType', 'branch'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('movement', function ($q) use ($search) {
                    $q->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('user_name', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('warehouse_movements.index', [
            'warehouseMovements' => $warehouseMovements,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Movimientos de Almacén',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'supplier_person_id' => 'required|integer|exists:people,id',
            'purchase.document_kind' => 'required|string|in:FACTURA,BOLETA,RECIBO',
            'purchase.series' => 'nullable|string|max:20',
            'purchase.document_number' => 'required|string|max:50',
            'purchase.currency' => 'required|string|max:10',
            'purchase.igv_rate' => 'required|numeric|min:0|max:100',
            'purchase.issued_at' => 'required|date',
            'comment' => 'nullable|string|max:500',
        ]);

        $branchId = session('branch_id');
        $userId = session('user_id');
        $userName = session('user_name') ?? 'Sistema';
        $supplier = Person::query()
            ->where('id', (int) $request->input('supplier_person_id'))
            ->where('branch_id', $branchId)
            ->first();
        $companyId = (int) DB::table('branches')->where('id', $branchId)->value('company_id');

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar un proveedor valido de la sucursal actual.',
            ], 422);
        }

        $documentKind = (string) $request->input('purchase.document_kind');
        $series = (string) ($request->input('purchase.series') ?? '');
        $documentNumber = (string) $request->input('purchase.document_number');
        $currency = (string) $request->input('purchase.currency');
        $igvRate = round((float) $request->input('purchase.igv_rate'), 4);
        $issuedAt = (string) $request->input('purchase.issued_at');

        $existsPurchaseDoc = WorkshopPurchaseRecord::query()
            ->where('branch_id', $branchId)
            ->where('document_kind', $documentKind)
            ->where('series', $series !== '' ? $series : null)
            ->where('document_number', $documentNumber)
            ->exists();
        if ($existsPurchaseDoc) {
            return response()->json([
                'success' => false,
                'message' => 'Documento de compra duplicado para la sucursal.',
            ], 422);
        }

        $personId = (int) $supplier->id;
        $personName = trim((string) $supplier->first_name . ' ' . (string) $supplier->last_name);
        if ($personName === '') {
            $personName = (string) ($supplier->document_number ?: 'Proveedor');
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

            // Generar número de movimiento (secuencia independiente por tipo de documento)
            $todayCount = Movement::where('document_type_id', $documentType->id)
                ->where('branch_id', $branchId)
                ->whereDate('created_at', Carbon::today())
                ->count();
            $number = str_pad($todayCount + 1, 8, '0', STR_PAD_LEFT);

            // Crear Movement
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $personId ?? $userId,
                'responsible_name' => $personName,
                'comment' => $request->comment ?? 'Entrada de productos al almacén',
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
            $purchaseSubtotal = 0.0;
            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    // Si no existe ProductBranch, crearlo
                    $productBranch = ProductBranch::create([
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'stock' => 0,
                        'price' => 0,
                        'avg_cost' => 0,
                        'status' => 'A',
                        'supplier_id' => $personId,
                    ]);
                }

                // Crear WarehouseMovementDetail
                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $warehouseMovement->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                // Actualizar stock y costo promedio ponderado.
                $quantity = (float) $item['quantity'];
                $unitCost = round((float) $item['unit_cost'], 6);
                $purchaseSubtotal += ($quantity * $unitCost);
                $currentStock = (float) ($productBranch->stock ?? 0);
                $currentCost = (float) ($productBranch->avg_cost ?? 0);
                if ($currentCost <= 0) {
                    $currentCost = (float) ($productBranch->price ?? 0);
                }
                $newStock = $currentStock + $quantity;
                $newAvgCost = $newStock > 0
                    ? round((($currentStock * $currentCost) + ($quantity * $unitCost)) / $newStock, 6)
                    : 0;

                $productBranch->update([
                    'stock' => $newStock,
                    'avg_cost' => $newAvgCost,
                    'supplier_id' => $personId,
                ]);
            }

            $purchaseSubtotal = round($purchaseSubtotal, 6);
            $purchaseIgv = round(($purchaseSubtotal * $igvRate) / 100, 6);
            $purchaseTotal = round($purchaseSubtotal + $purchaseIgv, 6);

            WorkshopPurchaseRecord::query()->create([
                'movement_id' => $movement->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_person_id' => $personId,
                'document_kind' => $documentKind,
                'series' => $series !== '' ? $series : null,
                'document_number' => $documentNumber,
                'currency' => $currency,
                'igv_rate' => $igvRate,
                'subtotal' => $purchaseSubtotal,
                'igv' => $purchaseIgv,
                'total' => $purchaseTotal,
                'issued_at' => $issuedAt,
            ]);

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

    private function isAdminUser(): bool
    {
        $user = auth()->user();
        $profileName = strtoupper((string) ($user?->profile?->name ?? ''));

        return (int) ($user?->profile_id ?? 0) === 1 || str_contains($profileName, 'ADMIN');
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
        $branchId = session('branch_id');
        $warehouseMovement = WarehouseMovement::with([
            'movement.movementType',
            'movement.documentType',
            'movement.branch',
            'branch',
            'details.unit',
            'details.product',
        ])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        return view('warehouse_movements.edit', [
            'warehouseMovement' => $warehouseMovement,
            'branchId' => $branchId,
            'title' => 'Editar Movimiento de Almacén',
        ]);
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
}
