@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    
    // --- ICONOS ---
    $SearchIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
    $ClearIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
    $PlusIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>');
    
    // --- DATOS HARDCODEADOS DE RECETAS ---
    $recipes = [
        [
            'id' => 1,
            'name' => 'Lomo Saltado Clásico',
            'description' => 'Trozos de lomo fino salteados al wok con cebolla, tomate y ají amarillo.',
            'category' => 'Plato de Fondo',
            'category_class' => 'badge-plato',
            'time' => '20 min',
            'method' => 'Wok',
            'method_icon' => 'ri-fire-line',
            'cost' => '18.50',
            'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 2,
            'name' => 'Ceviche de Pescado',
            'description' => 'Pesca del día marinada en limón sutil, con base de leche de tigre clásica.',
            'category' => 'Entrada',
            'category_class' => 'badge-entrada',
            'time' => '10 min',
            'method' => 'Frío',
            'method_icon' => 'ri-fridge-line',
            'cost' => '12.00',
            'image' => 'https://images.unsplash.com/photo-1535914697087-0b13575608d4?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 3,
            'name' => 'Causa de Pollo',
            'description' => 'Masa de papa amarilla prensada con ají amarillo, rellena de pollo.',
            'category' => 'Entrada',
            'category_class' => 'badge-entrada',
            'time' => '15 min',
            'method' => 'Manual',
            'method_icon' => 'ri-hand-heart-line',
            'cost' => '8.40',
            'image' => 'https://images.unsplash.com/photo-1601314167389-9a7065992984?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 4,
            'name' => 'Pisco Sour Clásico',
            'description' => 'Cóctel bandera a base de pisco quebranta, limón y jarabe.',
            'category' => 'Bebida',
            'category_class' => 'badge-bebida',
            'time' => '5 min',
            'method' => 'Barra',
            'method_icon' => 'ri-goblet-line',
            'cost' => '6.50',
            'image' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 5,
            'name' => 'Arroz con Mariscos',
            'description' => 'Arroz cremoso cocinado con langostinos, calamares y conchas negras.',
            'category' => 'Plato de Fondo',
            'category_class' => 'badge-plato',
            'time' => '35 min',
            'method' => 'Cocción',
            'method_icon' => 'ri-fire-line',
            'cost' => '22.00',
            'image' => 'https://images.unsplash.com/photo-1633384991695-9e6b87e2362f?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 6,
            'name' => 'Tiradito de Pescado',
            'description' => 'Finas láminas de pescado fresco con crema de ají amarillo y limón.',
            'category' => 'Entrada',
            'category_class' => 'badge-entrada',
            'time' => '12 min',
            'method' => 'Frío',
            'method_icon' => 'ri-fridge-line',
            'cost' => '14.50',
            'image' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 7,
            'name' => 'Suspiro Limeño',
            'description' => 'Manjar blanco coronado con merengue italiano y canela en polvo.',
            'category' => 'Postre',
            'category_class' => 'badge-postre',
            'time' => '25 min',
            'method' => 'Repostería',
            'method_icon' => 'ri-cake-3-line',
            'cost' => '5.80',
            'image' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 8,
            'name' => 'Chicha Morada',
            'description' => 'Bebida tradicional de maíz morado con frutas y especias aromáticas.',
            'category' => 'Bebida',
            'category_class' => 'badge-bebida',
            'time' => '45 min',
            'method' => 'Cocción',
            'method_icon' => 'ri-fire-line',
            'cost' => '3.20',
            'image' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=800&q=80'
        ]
    ];
    
    $totalRecipes = count($recipes);
@endphp

@section('content')

<style>
    /* Estilos para las tarjetas de recetas */
    .recipes-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    @media (max-width: 1400px) {
        .recipes-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 992px) {
        .recipes-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .recipes-grid {
            grid-template-columns: 1fr;
        }
    }

    .recipe-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(229, 231, 235, 0.8);
    }

    .recipe-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .recipe-image {
        position: relative;
        width: 100%;
        height: 280px;
        overflow: hidden;
        background: #e9ecef;
    }

    .recipe-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        transition: transform 0.5s ease;
    }

    .recipe-card:hover .recipe-image img {
        transform: scale(1.06);
    }

    .category-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.45rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        z-index: 2;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .badge-plato {
        background: #ffc107;
        color: #000;
    }

    .badge-entrada {
        background: #17a2b8;
        color: #fff;
    }

    .badge-bebida {
        background: #28a745;
        color: #fff;
    }

    .badge-postre {
        background: #dc3545;
        color: #fff;
    }

    .recipe-content {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .recipe-meta {
        display: flex;
        gap: 1rem;
        margin-bottom: 0.75rem;
        font-size: 0.8rem;
        color: #6c757d;
    }

    .recipe-meta span {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .recipe-meta i {
        font-size: 0.95rem;
    }

    .recipe-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.6rem;
        line-height: 1.3;
    }

    .recipe-description {
        font-size: 0.85rem;
        color: #6c757d;
        line-height: 1.5;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex-grow: 1;
    }

    .recipe-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e9ecef;
        margin-top: auto;
    }

    .price-info {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .price-label {
        font-size: 0.65rem;
        font-weight: 600;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .price-value {
        font-size: 1.3rem;
        font-weight: 800;
        color: #2c3e50;
    }

    .btn-view-recipe {
        padding: 0.5rem 1.15rem;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 20px;
        border: 1.5px solid #0d6efd;
        color: #0d6efd;
        background: transparent;
        transition: all 0.3s ease;
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-view-recipe:hover {
        background: #0d6efd;
        color: #fff;
        transform: translateX(3px);
    }

    /* Ajuste para el select en dark mode */
    .dark select {
        color-scheme: dark;
    }
</style>

<div x-data="{}">
    
    <x-common.page-breadcrumb pageTitle="Recetario" />

    <x-common.component-card title="Recetario Maestro" desc="Gestión de fichas técnicas y costos de platillos">
        
        <!-- Sección de búsqueda y filtros -->
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <form method="GET" action="#" class="flex flex-1 items-end gap-3">
                <!-- Campo de búsqueda -->
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Buscar Platillo
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            {!! $SearchIcon !!}
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Ej. Lomo Saltado, Ceviche..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                    </div>
                </div>

                <!-- Categoría -->
                <div class="w-48">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Categoría
                    </label>
                    <select 
                        name="category"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">Todas</option>
                        <option value="plato_fondo">Platos de Fondo</option>
                        <option value="entrada">Entradas</option>
                        <option value="postre">Postres</option>
                        <option value="bebida">Bebidas</option>
                    </select>
                </div>

                <!-- Estado -->
                <div class="w-40">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Estado
                    </label>
                    <select 
                        name="status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="development">En desarrollo</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #244BB3; border-color: #244BB3;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.button size="md" variant="outline" type="button" onclick="window.location.reload()" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.button>
                </div>
            </form>
            
            <div class="flex items-end">
                <x-ui.button
                    size="md"
                    variant="primary"
                    type="button"
                    @click="$dispatch('open-recipe-modal')"
                >
                    {!! $PlusIcon !!}
                    <span>Nueva Receta</span>
                </x-ui.button>
            </div>
        </div>

        <!-- Total de recetas -->
        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>Total de recetas</span>
                <x-ui.badge size="sm" variant="light" color="info">{{ $totalRecipes }}</x-ui.badge>
            </div>
        </div>

        <!-- Grid de tarjetas de recetas -->
        <div class="recipes-grid">
            
            @foreach($recipes as $recipe)
            <div class="recipe-card">
                <div class="recipe-image">
                    <span class="category-badge {{ $recipe['category_class'] }}">{{ $recipe['category'] }}</span>
                    <img src="{{ $recipe['image'] }}" alt="{{ $recipe['name'] }}">
                </div>
                <div class="recipe-content">
                    <div class="recipe-meta">
                        <span><i class="ri-time-line"></i> {{ $recipe['time'] }}</span>
                        <span><i class="{{ $recipe['method_icon'] }}"></i> {{ $recipe['method'] }}</span>
                    </div>
                    <h5 class="recipe-title">{{ $recipe['name'] }}</h5>
                    <p class="recipe-description">
                        {{ $recipe['description'] }}
                    </p>
                    <div class="recipe-footer">
                        <div class="price-info">
                            <span class="price-label">Costo Insumos</span>
                            <span class="price-value">S/ {{ $recipe['cost'] }}</span>
                        </div>
                        <button class="btn-view-recipe" data-id="{{ $recipe['id'] }}">Ver Ficha</button>
                    </div>
                </div>
            </div>
            @endforeach

        </div>

        <!-- Paginación (deshabilitada - datos hardcodeados) -->
        <!-- <div class="mt-6">
            Paginación aquí cuando se conecte a BD
        </div> -->

    </x-common.component-card>

    <!-- Modal para nueva receta -->
    <x-ui.modal x-data="{ open: false }" @open-recipe-modal.window="open = true" @close-recipe-modal.window="open = false" :isOpen="false" class="max-w-3xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-gray-400">Cocina</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">Nueva Receta</h3>
                    <p class="mt-1 text-sm text-gray-500">Ingresa la información de la ficha técnica.</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                    <i class="ri-restaurant-line"></i>
                </div>
            </div>

            <form method="POST" action="#" onsubmit="event.preventDefault(); alert('Funcionalidad disponible cuando se conecte a la base de datos');" class="space-y-6">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nombre del platillo
                        </label>
                        <input 
                            type="text" 
                            name="name"
                            placeholder="Ej. Lomo Saltado Criollo"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Categoría
                        </label>
                        <select 
                            name="category"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Seleccionar...</option>
                            <option value="plato_fondo">Plato de Fondo</option>
                            <option value="entrada">Entrada</option>
                            <option value="postre">Postre</option>
                            <option value="bebida">Bebida</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Tiempo (min)
                            </label>
                            <input 
                                type="number" 
                                name="time"
                                placeholder="20"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Costo (S/)
                            </label>
                            <input 
                                type="number" 
                                name="cost"
                                step="0.01"
                                placeholder="18.50"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Descripción
                        </label>
                        <textarea 
                            name="description"
                            rows="3"
                            placeholder="Descripción breve del platillo..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        ></textarea>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Guardar</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
    
</div>

@endsection