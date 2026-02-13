@extends('layouts.app')

@section('title', 'Editar Receta: ' . $recipe->name)

@section('content')
<div class="mx-auto max-w-5xl">
    <!-- HEADER -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('recipe_book.index') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">Recetas</a>
            <i class="ri-arrow-right-s-line text-gray-400"></i>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">🍳 Editar Receta</h1>
        </div>
        <p class="text-gray-600 dark:text-gray-400">Actualiza los detalles y componentes de esta receta</p>
    </div>

    <!-- ERRORES DE VALIDACIÓN -->
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <div class="flex gap-3">
                <i class="ri-alert-fill text-xl text-red-600 dark:text-red-400 mt-0.5"></i>
                <div>
                    <h3 class="font-semibold text-red-800 dark:text-red-300 mb-2">Errores en el formulario</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm text-red-700 dark:text-red-400">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- FORMULARIO -->
    <form action="{{ route('recipe-book.update', $recipe) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        @include('recipe_book._form', ['recipe' => $recipe, 'categories' => $categories, 'units' => $units, 'products' => $products])

        <!-- BOTONES DE ACCIÓN -->
        <div class="sticky bottom-0 right-0 left-0 flex gap-3 p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 rounded-b-lg">
            <a
                href="{{ route('recipe-book.index') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700"
            >
                <i class="ri-arrow-left-line"></i> Cancelar
            </a>
            <button
                type="submit"
                class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
            >
                <i class="ri-save-line"></i> Actualizar Receta
            </button>
        </div>
    </form>
</div>
@endsection
