@extends('layouts.app')

@section('title', 'Entrada de Productos')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 dark:text-gray-400"><i class="ri-archive-line"></i></span>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                Entrada de Productos a almacén
            </h2>
        </div>
        <nav>
            <ol class="flex items-center gap-1.5">
                <li>
                    <a class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400" href="{{ url('/') }}">
                        Home
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400" href="{{ route('warehouse_movements.index', request('view_id') ? ['view_id' => request('view_id')] : []) }}">
                        Movimientos de Almacén
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                <li class="text-sm text-gray-800 dark:text-white/90">
                    Entrada de Productos
                </li>
            </ol>
        </nav>
    </div>

    <div class="flex items-start w-full bg-slate-100 fade-in min-h-screen" style="--brand:#3B82F6;">
        
        {{-- SECCIÓN IZQUIERDA: LISTA DE PRODUCTOS --}}
        <main class="flex-1 flex flex-col min-w-0">
            
            {{-- Header --}}
            <header class="h-20 px-6 flex items-center justify-between bg-white border-b border-gray-200 shadow-sm z-10">
                <div class="flex items-center gap-4">
                    <button onclick="goBack()" class="h-10 w-10 rounded-lg bg-gray-50 border border-gray-200 text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">
                            Entrada de Productos
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Agregar productos al inventario</p>
                    </div>
                </div>
                <div class="w-64 hidden md:block relative">
                    <input type="text" id="search-products" placeholder="Buscar productos..." 
                        class="w-full pl-9 pr-4 py-2 bg-gray-100 border-transparent focus:bg-white focus:border-blue-500 focus:ring-0 rounded-lg text-sm transition-all">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                </div>
            </header>

            {{-- Grid de Productos --}}
            <div class="p-6 bg-[#F3F4F6]">
                <h3 class="font-bold text-slate-700 mb-4 text-base" id="category-title">Categoría: General</h3>
                <div id="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; width: 100%;">
                    {{-- JS llenará esto --}}
                </div>
            </div>
        </main>

        {{-- SECCIÓN DERECHA: CARRITO DE ENTRADA --}}
        <aside class="w-[400px] bg-white border-l border-gray-300 flex flex-col shadow-2xl z-20 shrink-0 sticky top-0 h-screen">
            
            {{-- Header Carrito --}}
            <div class="h-16 px-6 border-b border-gray-200 bg-white flex justify-between items-center shrink-0">
                <h3 class="text-xl font-bold text-slate-800">Productos a Ingresar</h3>
                <span class="px-3 py-1 bg-green-50 text-green-700 rounded-full text-xs font-bold border border-green-100">Entrada</span>
            </div>

            {{-- Lista Items --}}
            <div id="cart-container" class="flex-1 overflow-y-auto p-5 space-y-3 bg-white"></div>

            {{-- Footer --}}
            <div class="p-6 bg-slate-100 border-t border-gray-300 shadow-[0_-5px_25px_rgba(0,0,0,0.05)] shrink-0 z-30">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comentario (opcional)</label>
                    <textarea id="movement-comment" rows="2" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        placeholder="Ej: Compra de proveedor, Transferencia de otra sucursal..."></textarea>
                </div>
                <div class="space-y-3 mb-5 text-sm">
                    <div class="flex justify-between text-gray-500 font-medium">
                        <span>Total de productos</span>
                        <span class="text-slate-700" id="total-products">0</span>
                    </div>
                    <div class="flex justify-between text-gray-500 font-medium">
                        <span>Total de unidades</span>
                        <span class="text-slate-700" id="total-quantity">0</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="goBack()" class="py-3.5 rounded-xl border border-gray-300 bg-white text-gray-700 font-bold hover:bg-gray-50 shadow-sm transition-all">
                        Cancelar
                    </button>
                    <button onclick="saveEntry()" class="py-3.5 rounded-xl bg-blue-600 text-white font-bold shadow-lg hover:bg-blue-700 active:scale-95 transition-all flex justify-center items-center gap-2">
                        <span>Guardar</span> <i class="fas fa-check-circle"></i>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <script>
        @php
            $branchId = session('branch_id');
            // Usar la variable $products y $productBranches que vienen del controlador
            $productsMapped = $products->map(function($product) use ($branchId, $productBranches) {
                // Buscar productBranch usando el keyBy que hicimos en el controlador
                $productBranch = $productBranches->get($product->id);
                
                // Manejar la URL de la imagen correctamente
                $imageUrl = null;
                if ($product->image && !empty($product->image)) {
                    $imagePath = trim($product->image);
                    
                    // Si la imagen contiene rutas de Windows o rutas absolutas, ignorarla
                    if (strpos($imagePath, '\\') !== false || 
                        strpos($imagePath, 'C:') !== false ||
                        strpos($imagePath, 'Temp') !== false ||
                        strpos($imagePath, 'Windows') !== false) {
                        // URL inválida, usar null para que el JS use placeholder
                        $imageUrl = null;
                    } elseif (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
                        // Ya es una URL completa válida
                        // Verificar que no contenga rutas de Windows incluso en URLs
                        if (strpos($imagePath, '\\') === false && strpos($imagePath, 'C:') === false) {
                            $imageUrl = $imagePath;
                        } else {
                            $imageUrl = null;
                        }
                    } elseif (strpos($imagePath, 'storage/') === 0) {
                        // Ruta relativa que empieza con storage/
                        $imageUrl = asset($imagePath);
                    } elseif (strpos($imagePath, '/storage/') === 0) {
                        // Ruta relativa que empieza con /storage/
                        $imageUrl = asset(ltrim($imagePath, '/'));
                    } elseif (strpos($imagePath, 'product/') === 0) {
                        // Ruta relativa que empieza con product/
                        $imageUrl = asset('storage/' . $imagePath);
                    } else {
                        // Asumir que es una ruta relativa dentro de storage/product
                        // Construir la ruta de storage correctamente
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
            })->filter(function($product) {
                // Filtrar productos sin nombre
                return !empty($product['name']);
            })->values();
        @endphp

        const productsData = @json($productsMapped);
        const branchId = @json($branchId);

        let selectedProducts = [];
        let filteredProducts = productsData;

        function getImageUrl(imgUrl) {
            if (imgUrl && imgUrl.trim() !== '') {
                return imgUrl;
            }
            // SVG placeholder simple codificado
            return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cmVjdCBmaWxsPSIjZTdlOWViIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5Y2EzYWYiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            if (!grid) {
                setTimeout(renderProducts, 100);
                return;
            }

            if (filteredProducts.length === 0) {
                grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">No se encontraron productos</div>';
                return;
            }

            try {
                const html = filteredProducts.map((prod, index) => {
                    try {
                        const imageUrl = getImageUrl(prod.img);
                        
                        // Escapar caracteres especiales para evitar problemas en el HTML
                        const safeName = String(prod.name || 'Sin nombre').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        const safeCode = String(prod.code || 'N/A').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        const safeUnit = String(prod.unit || 'Unidad').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        
                        return `
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-all cursor-pointer overflow-hidden"
                            onclick="addProduct(${prod.id})">
                            <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                                <img src="${imageUrl}" alt="${safeName}" 
                                    class="w-full h-full object-cover" 
                                    loading="lazy"
                                    onerror="this.onerror=null; this.src='https://via.placeholder.com/200x200?text=Sin+Imagen';">
                            </div>
                            <div class="p-3">
                                <h4 class="font-semibold text-sm text-gray-800 line-clamp-2 mb-1">${safeName}</h4>
                                <p class="text-xs text-gray-500 mb-2">Código: ${safeCode}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">
                                        <i class="ri-stack-line"></i> Stock: <strong>${prod.currentStock || 0}</strong>
                                    </span>
                                    <span class="text-xs font-medium text-green-600">
                                        ${safeUnit}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                    } catch (prodError) {
                        return `
                        <div class="bg-white rounded-lg shadow-sm border border-red-200 hover:shadow-md transition-all cursor-pointer overflow-hidden"
                            onclick="addProduct(${prod.id})">
                            <div class="aspect-square bg-gray-100 flex items-center justify-center">
                                <span class="text-gray-400 text-xs">Sin imagen</span>
                            </div>
                            <div class="p-3">
                                <h4 class="font-semibold text-sm text-gray-800 line-clamp-2 mb-1">${prod.name || 'Sin nombre'}</h4>
                                <p class="text-xs text-gray-500 mb-2">Código: ${prod.code || 'N/A'}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">
                                        <i class="ri-stack-line"></i> Stock: <strong>${prod.currentStock || 0}</strong>
                                    </span>
                                    <span class="text-xs font-medium text-green-600">
                                        ${prod.unit || 'Unidad'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                    }
                }).join('');

                grid.innerHTML = html;

            } catch (error) {
                grid.innerHTML = '<div class="col-span-full text-center py-8 text-red-500">Error al cargar productos: ' + error.message + '</div>';
            }
        }

        function addProduct(productId) {
            const product = productsData.find(p => p.id === productId);
            if (!product) return;

            const existing = selectedProducts.find(p => p.id === productId);
            if (existing) {
                existing.quantity += 1;
            } else {
                selectedProducts.push({
                    id: product.id,
                    code: product.code,
                    name: product.name,
                    unit: product.unit,
                    currentStock: product.currentStock,
                    quantity: 1,
                    comment: ''
                });
            }

            renderCart();
        }

        function removeProduct(productId) {
            selectedProducts = selectedProducts.filter(p => p.id !== productId);
            renderCart();
        }

        function updateQuantity(productId, delta) {
            const product = selectedProducts.find(p => p.id === productId);
            if (product) {
                product.quantity = Math.max(1, product.quantity + delta);
                renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cart-container');
            if (!container) return;

            if (selectedProducts.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 text-gray-400">
                        <i class="ri-shopping-cart-line text-4xl mb-3"></i>
                        <p class="text-sm">No hay productos seleccionados</p>
                        <p class="text-xs mt-1">Haz clic en un producto para agregarlo</p>
                    </div>
                `;
                document.getElementById('total-products').textContent = '0';
                document.getElementById('total-quantity').textContent = '0';
                return;
            }

            container.innerHTML = selectedProducts.map(prod => `
                <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h4 class="font-semibold text-sm text-gray-800">${prod.name}</h4>
                            <p class="text-xs text-gray-500">Código: ${prod.code || 'N/A'} | Stock actual: ${prod.currentStock}</p>
                        </div>
                        <button onclick="removeProduct(${prod.id})" 
                            class="text-red-500 hover:text-red-700 transition-colors ml-2">
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 border border-gray-300 rounded-lg">
                            <button onclick="updateQuantity(${prod.id}, -1)" 
                                class="px-3 py-1 text-gray-600 hover:bg-gray-100 transition-colors">
                                <i class="ri-subtract-line"></i>
                            </button>
                            <span class="px-3 py-1 font-semibold text-gray-800 min-w-[3rem] text-center">${prod.quantity}</span>
                            <button onclick="updateQuantity(${prod.id}, 1)" 
                                class="px-3 py-1 text-gray-600 hover:bg-gray-100 transition-colors">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                        <span class="text-sm text-gray-600">${prod.unit}</span>
                    </div>
                </div>
            `).join('');

            const totalProducts = selectedProducts.length;
            const totalQuantity = selectedProducts.reduce((sum, p) => sum + p.quantity, 0);
            document.getElementById('total-products').textContent = totalProducts;
            document.getElementById('total-quantity').textContent = totalQuantity;
        }

        function goBack() {
            const viewId = new URLSearchParams(window.location.search).get('view_id');
            const url = viewId 
                ? `{{ route('warehouse_movements.index') }}?view_id=${viewId}`
                : `{{ route('warehouse_movements.index') }}`;
            window.location.href = url;
        }

        async function saveEntry() {
            if (selectedProducts.length === 0) {
                alert('Por favor, selecciona al menos un producto');
                return;
            }

            const comment = document.getElementById('movement-comment').value.trim();
            
            const payload = {
                items: selectedProducts.map(p => ({
                    product_id: p.id,
                    quantity: p.quantity,
                    comment: p.comment || ''
                })),
                comment: comment || 'Entrada de productos al almacén',
                branch_id: branchId,
                movement_type: 'ENTRY'
            };

            try {
                const response = await fetch('{{ route("warehouse_movements.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    const viewId = new URLSearchParams(window.location.search).get('view_id');
                    const redirectUrl = viewId
                        ? `{{ route('warehouse_movements.index') }}?view_id=${viewId}`
                        : `{{ route('warehouse_movements.index') }}`;
                    sessionStorage.setItem('flash_success_message', data.message || 'Entrada guardada correctamente');
                    window.location.href = redirectUrl;
                } else {
                    alert(data.message || 'Error al guardar la entrada');
                }
            } catch (error) {
                alert('Error al guardar: ' + (error.message || 'Error de conexión'));
            }
        }

        // Búsqueda de productos
        document.getElementById('search-products')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            filteredProducts = productsData.filter(p => 
                p.name.toLowerCase().includes(search) || 
                (p.code && p.code.toLowerCase().includes(search))
            );
            renderProducts();
        });

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            const grid = document.getElementById('products-grid');
            if (grid) {
                renderProducts();
            } else {
                setTimeout(renderProducts, 500);
            }
            renderCart();
        });

        if (document.readyState !== 'loading') {
            setTimeout(function() {
                renderProducts();
                renderCart();
            }, 100);
        }

        setTimeout(function() {
            const grid = document.getElementById('products-grid');
            if (grid && filteredProducts.length > 0) {
                renderProducts();
            }
        }, 1000);
    </script>
@endsection
