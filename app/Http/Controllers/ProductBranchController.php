<?php

namespace App\Http\Controllers;

use App\Models\ProductBranch;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Branch;
use Illuminate\Http\Request;

class ProductBranchController extends Controller
{
    public function create(Product $product)
    {
        $branchId = session('branch_id');
        $currentBranch = Branch::find($branchId);
        $taxRates = TaxRate::where('status', true)->orderBy('order_num')->get();
        
        // Verificar si ya existe un ProductBranch para este producto y sucursal
        $productBranch = ProductBranch::where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();
        
        // Si existe, retornar vista de edición, si no, vista de creación
        if ($productBranch) {
            return response()->view('products.product_branch._form', [
                'product' => $product,
                'productBranch' => $productBranch,
                'currentBranch' => $currentBranch,
                'taxRates' => $taxRates,
                'isEdit' => true,
                'updateRoute' => route('admin.product_branches.update', $productBranch)
            ]);
        }
        
        return response()->view('products.product_branch._form', [
            'product' => $product,
            'productBranch' => null,
            'currentBranch' => $currentBranch,
            'taxRates' => $taxRates,
            'isEdit' => false,
            'storeRoute' => route('admin.products.product_branches.store', $product)
        ]);
    }

    public function store(Request $request, Product $product)
    {
        $viewId = $request->input('view_id');
        $branchId = session('branch_id');
        
        if (!$branchId) {
            return redirect()->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No se pudo determinar la sucursal. Por favor, inicia sesión nuevamente.');
        }

        // Verificar si ya existe - si existe, siempre editar, nunca crear duplicado
        $productBranch = ProductBranch::where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if ($productBranch) {
            // Si ya existe, actualizar el registro existente
            $validated = $request->validate([
                'stock' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'stock_minimum' => 'nullable|numeric|min:0',
                'stock_maximum' => 'nullable|numeric|min:0',
                'minimum_sell' => 'nullable|numeric|min:0',
                'minimum_purchase' => 'nullable|numeric|min:0',
                'tax_rate_id' => 'required|exists:tax_rates,id',
                'unit_sale' => 'nullable|string|in:Y,N',
            ]);

            $validated['stock_minimum'] = $validated['stock_minimum'] ?? 0.0;
            $validated['stock_maximum'] = $validated['stock_maximum'] ?? 0.0;
            $validated['unit_sale'] = $validated['unit_sale'] ?? 'N';

            $productBranch->update($validated);
            return redirect()->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Producto actualizado en sucursal correctamente. Stock: ' . $validated['stock'] . ', Precio: $' . number_format($validated['price'], 2));
        }

        $data = $request->validate([
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'stock_minimum' => 'nullable|numeric|min:0',
            'stock_maximum' => 'nullable|numeric|min:0',
            'minimum_sell' => 'nullable|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'required|exists:tax_rates,id',
            'unit_sale' => 'nullable|string|in:Y,N',
        ]);

        // Campos requeridos por la migración
        $data['branch_id'] = $branchId;
        $data['product_id'] = $product->id;
        
        // Campos decimal(24, 6) - Laravel manejará el formato automáticamente
        $data['stock_minimum'] = isset($data['stock_minimum']) && $data['stock_minimum'] !== '' 
            ? (float) $data['stock_minimum'] 
            : 0.0;
        $data['stock_maximum'] = isset($data['stock_maximum']) && $data['stock_maximum'] !== '' 
            ? (float) $data['stock_maximum'] 
            : 0.0;
        
        // Campos con valores por defecto
        $data['unit_sale'] = $data['unit_sale'] ?? 'N';
        $data['status'] = 'E';
        $data['favorite'] = 'N';
        $data['duration_minutes'] = 0.0;

        ProductBranch::create($data);
        return redirect()->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto agregado a sucursal correctamente. Stock: ' . $data['stock'] . ', Precio: $' . number_format($data['price'], 2));
    }

    public function update(Request $request, ProductBranch $productBranch)
    {
        $viewId = $request->input('view_id');
        $data = $request->validate([
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'stock_minimum' => 'nullable|numeric|min:0',
            'stock_maximum' => 'nullable|numeric|min:0',
            'minimum_sell' => 'nullable|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'required|exists:tax_rates,id',
            'unit_sale' => 'nullable|string|in:Y,N',
        ]);

        // Campos decimal(24, 6) - Laravel manejará el formato automáticamente
        $data['stock_minimum'] = isset($data['stock_minimum']) && $data['stock_minimum'] !== '' 
            ? (float) $data['stock_minimum'] 
            : 0.0;
        $data['stock_maximum'] = isset($data['stock_maximum']) && $data['stock_maximum'] !== '' 
            ? (float) $data['stock_maximum'] 
            : 0.0;
        
        // Campos con valores por defecto
        $data['unit_sale'] = $data['unit_sale'] ?? 'N';

        $productBranch->update($data);
        return redirect()->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])->with('status', 'Producto actualizado en sucursal correctamente.');
    }

    public function edit(ProductBranch $productBranch)
    {
        return view('products.product_branch.edit', compact('productBranch'));
    }

    public function storeGeneric(Request $request)
    {
        $viewId = $request->input('view_id');
        $productId = $request->input('product_id');
        
        if (!$productId) {
            return redirect()->route('admin.products.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['product_id' => 'El ID del producto es requerido.']);
        }
        
        $product = Product::findOrFail($productId);
        
        return $this->store($request, $product);
    }

}

