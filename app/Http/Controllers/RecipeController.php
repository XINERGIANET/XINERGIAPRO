<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('category');
        $status = $request->input('status');
        $perPage = (int) $request->input('per_page', 12);
        
        $recipes = Recipe::query()
            ->with(['category', 'yieldUnit', 'ingredients.product', 'ingredients.unit'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::orderBy('description')->get();

        return view('recipe_book.index', [
            'recipes' => $recipes,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    public function create()
    {
        $categories = Category::orderBy('description')->get();
        $units = Unit::orderBy('description')->get();
        $products = Product::where('status', 'A')->orderBy('description')->get();

        return view('recipe_book.create', [
            'categories' => $categories,
            'units' => $units,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $imagePath = null;
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && $file->getRealPath()) {
                try {
                    $directory = storage_path('app/public/recipes');
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    $path = $file->store('recipes', 'public');
                    if ($path && !empty($path)) {
                        $imagePath = $path;
                    }
                } catch (\Exception $e) {
                    Log::error('Error al guardar imagen de receta: ' . $e->getMessage());
                }
            }
        }

        $validated = $this->validateRecipe($request);
        
        if ($imagePath) {
            $validated['image'] = $imagePath;
        }

        $recipe = Recipe::create($validated);

        // Guardar ingredientes
        if ($request->has('ingredients')) {
            $this->saveIngredients($recipe, $request->input('ingredients'));
        }

        return redirect()
            ->route('admin.recipe_book.index')
            ->with('status', 'Receta creada correctamente.');
    }

    public function edit(Recipe $recipe)
    {
        $categories = Category::orderBy('description')->get();
        $units = Unit::orderBy('description')->get();
        $products = Product::where('status', 'A')->orderBy('description')->get();

        return view('recipe_book.edit', [
            'recipe' => $recipe,
            'categories' => $categories,
            'units' => $units,
            'products' => $products,
        ]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $validated = $this->validateRecipe($request);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && $file->getRealPath()) {
                try {
                    if ($recipe->image && Storage::disk('public')->exists($recipe->image)) {
                        Storage::disk('public')->delete($recipe->image);
                    }
                    $directory = storage_path('app/public/recipes');
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    $path = $file->store('recipes', 'public');
                    if ($path && !empty($path)) {
                        $validated['image'] = $path;
                    }
                } catch (\Exception $e) {
                    Log::error('Error al actualizar imagen de receta: ' . $e->getMessage());
                }
            }
        }

        $recipe->update($validated);

        // Actualizar ingredientes
        if ($request->has('ingredients')) {
            $recipe->ingredients()->delete();
            $this->saveIngredients($recipe, $request->input('ingredients'));
        }

        return redirect()
            ->route('admin.recipe_book.index')
            ->with('status', 'Receta actualizada correctamente.');
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        if ($recipe->image && Storage::disk('public')->exists($recipe->image)) {
            Storage::disk('public')->delete($recipe->image);
        }

        $recipe->delete();

        return redirect()
            ->route('admin.recipe_book.index')
            ->with('status', 'Receta eliminada correctamente.');
    }

    public function show(Recipe $recipe)
    {
        $recipe->load(['category', 'yieldUnit', 'ingredients.product', 'ingredients.unit']);
        
        return view('recipe_book.show', [
            'recipe' => $recipe,
        ]);
    }

    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:recipes,code,' . ($request->route('recipe')->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'yield_unit_id' => ['required', 'integer', 'exists:units,id'],
            'preparation_time' => ['nullable', 'integer', 'min:1'],
            'preparation_method' => ['nullable', 'string', 'max:50'],
            'yield_quantity' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', 'in:A,I'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function saveIngredients(Recipe $recipe, array $ingredients): void
    {
        foreach ($ingredients as $index => $ingredient) {
            if (empty($ingredient['product_id'])) {
                continue;
            }

            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'product_id' => $ingredient['product_id'],
                'unit_id' => $ingredient['unit_id'],
                'quantity' => $ingredient['quantity'],
                'notes' => $ingredient['notes'] ?? null,
                'unit_cost' => $ingredient['unit_cost'] ?? 0,
                'order' => $index,
            ]);
        }

        // Recalcular costo total
        $recipe->update([
            'cost_total' => $recipe->ingredients()->sum('total_cost'),
        ]);
    }
}
