<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Operation;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\Person;
use App\Models\ProductType;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\DocumentType;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Services\KardexSyncService;
use App\Support\ProductBranchExcelImport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = (int) $request->input('category_id', 0);
        $productTypeId = (int) $request->input('product_type_id', 0);
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();
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

        ProductType::ensureDefaultsForBranch((int) $branchId);

        $products = Product::query()
            ->with([
                'category',
                'baseUnit',
                'productType',
                'productBranches' => function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                        ->with(['branch', 'taxRate']);
                },
            ])
            ->whereHas('productBranches', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('description', 'ILIKE', "%{$search}%")
                        ->orWhere('code', 'ILIKE', "%{$search}%")
                        ->orWhere('abbreviation', 'ILIKE', "%{$search}%")
                        ->orWhere('marca', 'ILIKE', "%{$search}%");
                });
            })
            ->when($categoryId > 0, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($productTypeId > 0, function ($query) use ($productTypeId) {
                $query->where('product_type_id', $productTypeId);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::query()
            ->forBranch((int) $branchId)
            ->orderBy('description')
            ->get();
        $units = Unit::query()->orderBy('description')->get();
        $productTypes = ProductType::query()
            ->where('branch_id', $branchId)
            ->where('status', true)
            ->orderBy('name')
            ->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $suppliers = Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);
        $currentBranch = Branch::find(session('branch_id'));
        $nextProductCode = $this->nextBranchProductCode((int) $branchId);

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'productTypes' => $productTypes,
            'taxRates' => $taxRates,
            'currentBranch' => $currentBranch,
            'nextProductCode' => $nextProductCode,
            'search' => $search,
            'selectedCategoryId' => $categoryId,
            'selectedProductTypeId' => $productTypeId,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'suppliers' => $suppliers,
        ]);
    }

    public function importExcel(Request $request)
    {
        $viewId = $request->input('view_id');

        $validator = Validator::make($request->all(), [
            'file' => ['required', File::types(['xlsx', 'xls', 'csv'])->max(10240)],
        ]);

        if ($validator->fails()) {
            $msg = (string) ($validator->errors()->first('file') ?: 'Archivo no válido. Usa .xlsx, .xls o .csv (máx. 10 MB).');

            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors($validator)
                ->with('error', $msg);
        }

        $branchId = (int) $request->session()->get('branch_id');

        if ($branchId <= 0) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'Selecciona una sucursal para importar productos.');
        }

        $uploaded = $request->file('file');
        if (!$uploaded) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['file' => 'No se recibió ningún archivo.'])
                ->with('error', 'No se recibió ningún archivo.');
        }

        $ext = strtolower((string) $uploaded->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'xlsx';
        }

        $storedRelative = $uploaded->storeAs(
            'temp/product-imports',
            Str::uuid()->toString() . '.' . $ext,
            'local'
        );

        if ($storedRelative === false) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['file' => 'No se pudo guardar el archivo temporalmente.'])
                ->with('error', 'No se pudo guardar el archivo temporalmente.');
        }

        $fullPath = Storage::disk('local')->path($storedRelative);

        try {
            $rows = ProductBranchExcelImport::extractRows($fullPath);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['file' => $e->getMessage()])
                ->with('error', $e->getMessage());
        } finally {
            Storage::disk('local')->delete($storedRelative);
        }

        ProductType::ensureDefaultsForBranch($branchId);

        $productType = ProductType::query()
            ->where('branch_id', $branchId)
            ->where('status', true)
            ->whereIn('behavior', ['SELLABLE', 'VENDIBLE'])
            ->orderByRaw("CASE UPPER(behavior) WHEN 'SELLABLE' THEN 0 WHEN 'VENDIBLE' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();

        if (!$productType) {
            $productType = ProductType::query()
                ->where('branch_id', $branchId)
                ->where('status', true)
                ->whereRaw('LOWER(name) LIKE ?', ['%producto final%'])
                ->orderBy('id')
                ->first();
        }

        if (!$productType) {
            $productType = ProductType::query()
                ->where('branch_id', $branchId)
                ->where('status', true)
                ->orderBy('id')
                ->first();
        }

        if (!$productType) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No hay tipo de producto activo para esta sucursal. Crea uno primero.');
        }

        $baseUnitId = (int) (Unit::query()
            ->whereRaw('LOWER(description) LIKE ?', ['%unidad%'])
            ->orderBy('id')
            ->value('id') ?? Unit::query()->orderBy('id')->value('id'));

        if ($baseUnitId <= 0) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No hay unidades de medida registradas.');
        }

        $imported = 0;

        try {
            DB::transaction(function () use ($rows, $branchId, $productType, $baseUnitId, &$imported) {
                foreach ($rows as $row) {
                    $category = $this->findOrCreateCategoryForBranch($row['category'], $branchId);
                    $code = $this->nextBranchProductCode($branchId);

                    $product = Product::query()->create([
                        'code' => $code,
                        'description' => $row['description'],
                        'abbreviation' => $row['description'],
                        'marca' => $row['marca'] !== '' ? $row['marca'] : null,
                        'type' => $productType->behavior,
                        'product_type_id' => $productType->id,
                        'category_id' => $category->id,
                        'base_unit_id' => $baseUnitId,
                        'kardex' => 'S',
                        'complement' => 'NO',
                        'complement_mode' => '',
                        'classification' => 'GOOD',
                        'features' => null,
                        'recipe' => false,
                    ]);

                    ProductBranch::query()->create([
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'status' => 'A',
                        'stock' => $row['stock'],
                        'price' => 0,
                        'purchase_price' => 0,
                        'stock_minimum' => 0,
                        'stock_maximum' => 0,
                        'minimum_sell' => 0,
                        'minimum_purchase' => 0,
                        'favorite' => 'N',
                        'tax_rate_id' => null,
                        'unit_sale' => 'N',
                        'duration_minutes' => null,
                        'supplier_id' => null,
                        'expiration_date' => null,
                    ]);

                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            Log::error('importExcel productos: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $err = 'Error al importar: ' . $e->getMessage();

            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['file' => $err])
                ->with('error', $err);
        }

        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', "Importación lista: {$imported} producto(s) creados en esta sucursal.");
    }

    public function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $sheet->setCellValue('A1', 'CATEGORÍA');
        $sheet->setCellValue('B1', 'DESCRIPCIÓN');
        $sheet->setCellValue('C1', 'MARCA');
        $sheet->setCellValue('D1', 'STOCK ACTUAL');

        $sheet->setCellValue('A2', 'REPUESTOS VARIOS');
        $sheet->setCellValue('B2', 'EJEMPLO: descripción del producto');
        $sheet->setCellValue('C2', 'MARCA EJEMPLO');
        $sheet->setCellValue('D2', 0);

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'plantilla_importacion_productos.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request)
    {
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            if ($file->isValid() && $file->getRealPath() && is_readable($file->getRealPath())) {
                try {
                    // Asegurar que el directorio existe
                    $directory = storage_path('app/public/product');
                    if (!is_dir($directory)) {
                        $created = @mkdir($directory, 0755, true);
                        if (!$created) {
                            Log::error(message: 'Failed to create directory: ' . $directory);
                        }
                    }
                    
                    // Verificar permisos del directorio
                    if (is_dir($directory)) {
                    }   
                    $path = $file->store('product', 'public');
                    
                    if ($path && !empty($path)) {
                        $imagePath = $path;
                    } else {
                            Log::warning(message: 'El path de la imagen está vacío después de guardar');
                    }
                } catch (\Exception $e) {
                    Log::error(message: 'Error al guardar imagen del producto: ' . $e->getMessage());
                }
            } else {
                Log::warning(message: 'El archivo de imagen no es válido o no tiene path');
            }
        } else {
            Log::info(message: 'No image file in request');
        }
        
        $validated = $this->validateProduct($request);        
        $productData = $this->prepareProductData($validated);
        $branchData = $this->prepareBranchData($validated);
        
        if ($imagePath !== null && $imagePath !== '') {
            $productData['image'] = is_string($imagePath) ? $imagePath : (string) $imagePath;
            Log::info('Image path added to data: ' . $productData['image']);
        }

        $product = Product::create($productData);
        
        // Crear ProductBranch para la sucursal actual
        $branchId = $request->session()->get('branch_id');
        if ($branchId) {
            $branchData['product_id'] = $product->id;
            $branchData['branch_id'] = $branchId;
            $branchData['status'] = 'A';
            ProductBranch::create($branchData);
        }
        
        $viewId = $request->input('view_id');

        if ($request->input('after_create') === 'purchase_create') {
            return redirect()
                ->route('admin.purchases.create', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Producto creado correctamente. Buscalo en el catalogo para agregarlo a la compra.');
        }

        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto creado correctamente.');
    }

    public function edit(Request $request, Product $product)
    {
        $branchId = $request->session()->get('branch_id');
        $categories = Category::query()
            ->forBranch((int) $branchId)
            ->orderBy('description')
            ->get();
        $units = Unit::query()->orderBy('description')->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        ProductType::ensureDefaultsForBranch((int) $branchId);
        $productTypes = ProductType::query()
            ->where(function ($query) use ($branchId, $product) {
                $query->where(function ($inner) use ($branchId) {
                    $inner->where('branch_id', $branchId)
                        ->where('status', true);
                });
                if (!empty($product->product_type_id)) {
                    $query->orWhere('id', (int) $product->product_type_id);
                }
            })
            ->orderBy('name')
            ->get();
        $productBranch = $product->productBranches()
            ->where('branch_id', $branchId)
            ->first();
        $suppliers = Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        return view('products.edit', [
            'product' => $product,
            'productBranch' => $productBranch,
            'categories' => $categories,
            'units' => $units,
            'productTypes' => $productTypes,
            'taxRates' => $taxRates,
            'suppliers' => $suppliers,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request);
        $productData = $this->prepareProductData($validated);
        $branchData = $this->prepareBranchData($validated);
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && $file->getRealPath()) {
                try {
                    if ($product->image && !empty($product->image) && Storage::disk('public')->exists($product->image)) {
                        Storage::disk('public')->delete($product->image);
                    }
                    $directory = storage_path('app/public/product');
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    $path = $file->store('product', 'public');
                    if ($path && $path !== '') {
                        $productData['image'] = is_string($path) ? $path : (string) $path;
                    } else {
                        Log::warning(message: 'El path de la imagen está vacío después de guardar');
                    }
                } catch (\Exception $e) {
                    Log::error(message: 'Error al actualizar imagen del producto: ' . $e->getMessage());
                }
            }
        }
        
        DB::transaction(function () use ($request, $product, $productData, $branchData) {
            // Actualizar producto
            $product->update($productData);
            
            // Actualizar o crear ProductBranch para la sucursal actual
            $branchId = $request->session()->get('branch_id');
            if (!$branchId) {
                return;
            }
            
            $productBranch = ProductBranch::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();
            
            if (!$productBranch) {
                $branchData['product_id'] = $product->id;
                $branchData['branch_id'] = $branchId;
                $branchData['status'] = $branchData['status'] ?? 'A';
                $productBranch = ProductBranch::query()->create($branchData);
                return;
            }
            
            $oldStock = (float) ($productBranch->stock ?? 0);
            $newStock = (float) ($branchData['stock'] ?? $oldStock);
            $stockDelta = $newStock - $oldStock;
            
            $branchDataWithoutStock = $branchData;
            unset($branchDataWithoutStock['stock']);
            
            if (!empty($branchDataWithoutStock)) {
                $productBranch->update($branchDataWithoutStock);
            }
            
            if (abs($stockDelta) < 0.000001) {
                return;
            }
            
            $movementType = MovementType::query()
                ->where(function ($query) {
                    $query->where('description', 'like', '%Almac%')
                        ->orWhere('description', 'like', '%Warehouse%')
                        ->orWhere('description', 'like', '%Inventario%');
                })
                ->first() ?? MovementType::query()->firstOrFail();
            
            $isEntry = $stockDelta > 0;
            $documentType = $isEntry
                ? (DocumentType::query()->find(7)
                    ?? DocumentType::query()->where(function ($query) {
                        $query->where('name', 'like', '%Entrada%')
                            ->orWhere('name', 'like', '%entry%');
                    })->first())
                : (DocumentType::query()->find(8)
                    ?? DocumentType::query()->where(function ($query) {
                        $query->where('name', 'like', '%Salida%')
                            ->orWhere('name', 'like', '%exit%')
                            ->orWhere('name', 'like', '%output%');
                    })->first());
            
            if (!$documentType) {
                $documentType = DocumentType::query()
                    ->where('movement_type_id', $movementType->id)
                    ->firstOrFail();
            }
            
            $prefix = $isEntry ? 'E' : 'S';
            $date = now()->format('Ymd');
            $lastNumber = (string) (Movement::query()
                ->where('branch_id', (int) $branchId)
                ->where('document_type_id', (int) $documentType->id)
                ->where('number', 'like', $prefix . '-' . $date . '-%')
                ->orderByDesc('id')
                ->value('number') ?? '');
            $seq = 0;
            if ($lastNumber !== '' && preg_match('/^' . preg_quote($prefix, '/') . '\-' . $date . '\-(\d{1,6})$/', $lastNumber, $m)) {
                $seq = (int) $m[1];
            }
            $number = $prefix . '-' . $date . '-' . str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
            
            $user = $request->user();
            $userName = (string) ($user?->name ?? 'Sistema');
            
            $movement = Movement::query()->create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $userName,
                'person_id' => $user?->person?->id,
                'person_name' => $user?->person
                    ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                    : $userName,
                'responsible_id' => $user?->id,
                'responsible_name' => $userName,
                'comment' => 'AJUSTE DE STOCK POR EDICIÓN DE PRODUCTO',
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);
            
            $warehouseMovement = WarehouseMovement::query()->create([
                'status' => 'FINALIZED',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);
            
            $product->loadMissing('baseUnit');
            WarehouseMovementDetail::query()->create([
                'warehouse_movement_id' => $warehouseMovement->id,
                'product_id' => $product->id,
                'product_snapshot' => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'marca' => $product->marca,
                ],
                'unit_id' => $product->baseUnit?->id ?? 1,
                'quantity' => abs($stockDelta),
                'comment' => $isEntry
                    ? ('AJUSTE ENTRADA. Stock: ' . number_format($oldStock, 2, '.', '') . ' -> ' . number_format($newStock, 2, '.', ''))
                    : ('AJUSTE SALIDA. Stock: ' . number_format($oldStock, 2, '.', '') . ' -> ' . number_format($newStock, 2, '.', '')),
                'status' => 'A',
                'branch_id' => $branchId,
            ]);
            
            $productBranch->update(['stock' => $newStock]);
            
            app(KardexSyncService::class)->syncMovement($movement);
        });
        
        $viewId = $request->input('view_id');
        
        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Request $request, Product $product)
    {
        $branchId = $request->session()->get('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            return redirect()
                ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No hay sucursal seleccionada.');
        }

        $destroyOutcome = 'full';

        DB::transaction(function () use ($product, $branchId, &$destroyOutcome) {
            $productBranch = ProductBranch::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->first();

            $removedThisBranch = false;
            if ($productBranch) {
                $productBranch->delete();
                $removedThisBranch = true;
            }

            $hasOtherBranches = ProductBranch::query()
                ->where('product_id', $product->id)
                ->exists();

            if ($hasOtherBranches) {
                $destroyOutcome = $removedThisBranch ? 'branch_only' : 'no_op';

                return;
            }

            if ($product->image && !empty($product->image) && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $product->delete();
        });

        if ($destroyOutcome === 'branch_only') {
            $statusMessage = 'Producto quitado de esta sucursal. Sigue existiendo en otras sedes.';
        } elseif ($destroyOutcome === 'no_op') {
            $statusMessage = 'Este producto no estaba vinculado a la sucursal actual.';
        } else {
            $statusMessage = 'Producto eliminado correctamente.';
        }

        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', $statusMessage);
    }

    private function validateProduct(Request $request): array
    {
        $branchId = (int) $request->session()->get('branch_id');
        $selectedProductType = ProductType::query()
            ->where('id', (int) $request->input('product_type_id'))
            ->where('branch_id', $branchId)
            ->where('status', true)
            ->first();
        $isSupplyType = $selectedProductType
            && in_array(strtoupper((string) $selectedProductType->behavior), ['SUPPLY', 'SUMINISTRO'], true);
        $detailNumericRules = $isSupplyType
            ? ['nullable', 'numeric', 'min:0']
            : ['required', 'numeric', 'min:0'];

        $validated = $request->validate([
            // Datos del Producto
            'code' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:120'],
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'kardex' => ['required', 'string', 'in:S,N'],
            'status' => ['nullable', 'string', 'in:A,I'],
            'image' => ['nullable', 'sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'complement' => ['nullable', 'string', 'in:NO,HAS,IS'],
            'complement_mode' => ['nullable', 'string', 'in:,ALL,QUANTITY'],
            'classification' => ['nullable', 'string', 'in:GOOD,SERVICE'],
            'features' => ['nullable', 'string'],
            'recipe' => ['nullable', 'boolean'],

            // Datos de ProductBranch (Detalle por Sede)
            'price' => $detailNumericRules,
            'purchase_price' => $detailNumericRules,
            'stock' => $detailNumericRules,
            'stock_minimum' => $detailNumericRules,
            'stock_maximum' => $detailNumericRules,
            'minimum_sell' => $detailNumericRules,
            'minimum_purchase' => $detailNumericRules,
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'unit_sale' => ['nullable', 'string', 'in:S,N'],
            'expiration_date' => ['nullable', 'date'],
            'favorite' => ['required', 'string', 'in:S,N'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer'],
        ]);
        
        // Eliminar el campo image si está vacío o es null
        if (isset($validated['image']) && empty($validated['image'])) {
            unset($validated['image']);
        }

        $productType = $selectedProductType;

        if (!$productType) {
            throw ValidationException::withMessages([
                'product_type_id' => 'El tipo de producto no pertenece a la sucursal actual.',
            ]);
        }

        if (in_array(strtoupper((string) $productType->behavior), ['SUPPLY', 'SUMINISTRO'], true)) {
            $validated['price'] = 0;
            $validated['purchase_price'] = 0;
            $validated['stock'] = 0;
            $validated['stock_minimum'] = 0;
            $validated['stock_maximum'] = 0;
            $validated['minimum_sell'] = 0;
            $validated['minimum_purchase'] = 0;
            $validated['unit_sale'] = 'N';
            $validated['expiration_date'] = null;
        }

        $validated['status'] = $validated['status'] ?? 'A';
        $validated['complement'] = 'NO';
        $validated['complement_mode'] = '';
        $validated['classification'] = 'GOOD';
        $validated['unit_sale'] = $validated['unit_sale'] ?? 'N';
        
        return $validated;
    }

    private function prepareProductData(array $validated): array
    {
        $productType = ProductType::query()->findOrFail((int) $validated['product_type_id']);

        $marca = trim((string) ($validated['marca'] ?? ''));

        return [
            'code' => $validated['code'],
            'description' => $validated['description'],
            'abbreviation' => $validated['abbreviation'],
            'marca' => $marca !== '' ? $marca : null,
            'type' => $productType->behavior,
            'product_type_id' => $productType->id,
            'category_id' => $validated['category_id'],
            'base_unit_id' => $validated['base_unit_id'],
            'kardex' => $validated['kardex'],
            'complement' => 'NO',
            'complement_mode' => '',
            'classification' => 'GOOD',
            'features' => $validated['features'] ?? null,
            'recipe' => (bool) ($validated['recipe'] ?? false),
        ];
    }

    private function prepareBranchData(array $validated): array
    {
        return [
            'status' => $validated['status'],
            'expiration_date' => $validated['expiration_date'] ?? null,
            'stock_minimum' => $validated['stock_minimum'],
            'stock_maximum' => $validated['stock_maximum'],
            'minimum_sell' => $validated['minimum_sell'],
            'minimum_purchase' => $validated['minimum_purchase'],
            'favorite' => $validated['favorite'],
            'tax_rate_id' => $validated['tax_rate_id'] ?? null,
            'unit_sale' => $validated['unit_sale'] ?? 'N',
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'stock' => $validated['stock'],
            'price' => $validated['price'],
            'purchase_price' => $validated['purchase_price'],
        ];
    }

    private function findOrCreateCategoryForBranch(string $label, int $branchId): Category
    {
        $label = trim($label);
        if ($label === '') {
            $label = 'Sin categoría';
        }

        $needle = mb_strtolower($label, 'UTF-8');

        $existing = Category::query()
            ->forBranch($branchId)
            ->whereRaw('LOWER(TRIM(description)) = ?', [$needle])
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = Category::query()->create([
            'description' => mb_substr($label, 0, 255),
            'abbreviation' => mb_substr($label, 0, 255),
        ]);

        $category->branches()->syncWithoutDetaching([
            $branchId => [
                'menu_type' => 'GENERAL',
                'status' => 'E',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return $category;
    }

    private function nextBranchProductCode(int $branchId): string
    {
        if ($branchId <= 0) {
            return '1';
        }

        $lastCode = Product::query()
            ->join('product_branch', 'product_branch.product_id', '=', 'products.id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->orderByDesc('products.id')
            ->value('products.code');

        if (!$lastCode) {
            return '1';
        }

        $code = trim((string) $lastCode);

        if (preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $next = (string) ((int) $number + 1);

            return $prefix . str_pad($next, strlen($number), '0', STR_PAD_LEFT);
        }

        if (is_numeric($code)) {
            return (string) ((int) $code + 1);
        }

        return '1';
    }
}
