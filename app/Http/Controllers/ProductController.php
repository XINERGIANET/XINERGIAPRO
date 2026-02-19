<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Operation;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
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

        $products = Product::query()
            ->with(['category', 'baseUnit', 'productBranches.branch', 'productBranches.taxRate'])
            ->when($search, function ($query) use ($search) {
                $query->where('description', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('abbreviation', 'ILIKE', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::query()->orderBy('description')->get();
        $units = Unit::query()->orderBy('description')->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $currentBranch = Branch::find(session('branch_id'));
        $nextProductCode = $this->nextBranchProductCode((int) $branchId);

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'taxRates' => $taxRates,
            'currentBranch' => $currentBranch,
            'nextProductCode' => $nextProductCode,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
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
        
        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto creado correctamente.');
    }

    public function edit(Request $request, Product $product)
    {
        $categories = Category::query()->orderBy('description')->get();
        $units = Unit::query()->orderBy('description')->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $branchId = $request->session()->get('branch_id');
        $productBranch = $product->productBranches()
            ->where('branch_id', $branchId)
            ->first();

        return view('products.edit', [
            'product' => $product,
            'productBranch' => $productBranch,
            'categories' => $categories,
            'units' => $units,
            'taxRates' => $taxRates,
            'suppliers' => collect(), 
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
        
        // Actualizar producto
        $product->update($productData);
        
        // Actualizar o crear ProductBranch para la sucursal actual
        $branchId = $request->session()->get('branch_id');
        if ($branchId) {
            $productBranch = $product->productBranches()
                ->where('branch_id', $branchId)
                ->first();
            
            if ($productBranch) {
                $productBranch->update($branchData);
            } else {
                $branchData['product_id'] = $product->id;
                $branchData['branch_id'] = $branchId;
                $branchData['status'] = 'E';
                ProductBranch::create($branchData);
            }
        }
        
        $viewId = $request->input('view_id');
        
        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Request $request, Product $product)
    {
        // Eliminar la imagen si existe
        if ($product->image && !empty($product->image) && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }
        
        $product->delete();
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto eliminado correctamente.');
    }

    private function validateProduct(Request $request): array
    {
        $validated = $request->validate([
            // Datos del Producto
            'code' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:PRODUCT,INGREDENT'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'kardex' => ['required', 'string', 'in:S,N'],
            'status' => ['required', 'string', 'in:A,I'],
            'image' => ['nullable', 'sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'complement' => ['required', 'string', 'in:NO,HAS,IS'],
            'complement_mode' => ['nullable', 'string', 'in:,ALL,QUANTITY'],
            'classification' => ['required', 'string', 'in:GOOD,SERVICE'],
            'features' => ['nullable', 'string'],
            'recipe' => ['required', 'boolean'],

            // Datos de ProductBranch (Detalle por Sede)
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'numeric', 'min:0'],
            'stock_minimum' => ['required', 'numeric', 'min:0'],
            'stock_maximum' => ['required', 'numeric', 'min:0'],
            'minimum_sell' => ['required', 'numeric', 'min:0'],
            'minimum_purchase' => ['required', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'unit_sale' => ['nullable', 'string', 'max:50'],
            'expiration_date' => ['nullable', 'date'],
            'favorite' => ['required', 'string', 'in:S,N'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer'],
        ]);
        
        // Eliminar el campo image si está vacío o es null
        if (isset($validated['image']) && empty($validated['image'])) {
            unset($validated['image']);
        }
        
        return $validated;
    }

    private function prepareProductData(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'description' => $validated['description'],
            'abbreviation' => $validated['abbreviation'],
            'type' => $validated['type'],
            'category_id' => $validated['category_id'],
            'base_unit_id' => $validated['base_unit_id'],
            'kardex' => $validated['kardex'],
            'complement' => $validated['complement'],
            'complement_mode' => $validated['complement_mode'],
            'classification' => $validated['classification'],
            'features' => $validated['features'],
            'recipe' => (bool) $validated['recipe'],
        ];
    }

    private function prepareBranchData(array $validated): array
    {
        return [
            'status' => $validated['status'],
            'expiration_date' => $validated['expiration_date'],
            'stock_minimum' => $validated['stock_minimum'],
            'stock_maximum' => $validated['stock_maximum'],
            'minimum_sell' => $validated['minimum_sell'],
            'minimum_purchase' => $validated['minimum_purchase'],
            'favorite' => $validated['favorite'],
            'tax_rate_id' => $validated['tax_rate_id'],
            'unit_sale' => $validated['unit_sale'],
            'duration_minutes' => $validated['duration_minutes'],
            'supplier_id' => $validated['supplier_id'],
            'stock' => $validated['stock'],
            'price' => $validated['price'],
        ];
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
