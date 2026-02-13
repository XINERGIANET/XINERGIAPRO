<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Card;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SalesController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->input('search');
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

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $sales = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement'])
            ->where('movement_type_id', 2) //2 es venta
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('user_name', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        
        return view('sales.index', [
            'sales' => $sales,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ] + $this->getFormData());
    }

    public function create()
    {
        $branchId = session('branch_id');

        $products = Product::query()
            ->where('type', 'PRODUCT')
            ->with('category')
            ->orderBy('description')
            ->get()
            ->map(function (Product $product) {
                $imageUrl = ($product->image && !empty($product->image))
                    ? asset('storage/' . ltrim($product->image, '/'))
                    : null;

                return [
                    'id' => (int) $product->id,
                    'name' => $product->description,
                    'img' => $imageUrl,
                    'note' => $product->note ?? null,
                    'category' => $product->category ? $product->category->description : 'Sin categoria',
                ];
            })
            ->values();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->with(['product', 'taxRate'])
            ->get()
            ->filter(fn ($productBranch) => $productBranch->product !== null)
            ->map(function ($productBranch) {
                $taxRate = $productBranch->taxRate;
                $taxRatePct = $taxRate ? (float) $taxRate->tax_rate : null;
                return [
                    'id' => (int) $productBranch->id,
                    'product_id' => (int) $productBranch->product_id,
                    'price' => (float) $productBranch->price,
                    'tax_rate' => $taxRatePct,
                    'stock' => (float) ($productBranch->stock ?? 0),
                ];
            })
            ->values();

        return view('sales.create', [
            'products' => $products,
            'productBranches' => $productBranches,
            // Compatibilidad con implementaciones previas en la vista
            'productsBranches' => $productBranches,
        ]);
    }

    // POS: vista de cobro
    public function charge(Request $request)
    {
        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->where('movement_type_id', 2)
            ->get(['id', 'name']);
        
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        
        $paymentGateways = PaymentGateways::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);
        
        $cards = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon', 'order_num']);
        
        $cashRegisters = CashRegister::query()
            ->orderByRaw("CASE WHEN status = 'A' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);

        $branchId = session('branch_id');
        $people = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);

        $defaultClientId = Person::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereRaw('UPPER(first_name) = ?', ['CLIENTES'])
            ->whereRaw('UPPER(last_name) = ?', ['VARIOS'])
            ->value('id');

        if (!$defaultClientId) {
            $defaultClientId = 4;
        }
        
        // Si se pasa un movement_id, cargar la venta pendiente o con pago parcial
        $draftSale = null;
        $pendingAmount = 0;
        if ($request->has('movement_id')) {
            $movement = Movement::with(['salesMovement.details.product', 'cashMovement'])
                ->where('id', $request->movement_id)
                ->whereIn('status', ['P', 'A']) // Cargar si está pendiente o activo (puede tener deuda)
                ->first();
            
            if ($movement && $movement->salesMovement) {
                // Calcular el monto pendiente si hay una deuda
                $relatedCashMovement = $movement->cashMovement ?: $this->resolveCashMovementBySaleMovement($movement->id);
                if ($relatedCashMovement) {
                    $debt = DB::table('cash_movement_details')
                        ->where('cash_movement_id', $relatedCashMovement->id)
                        ->where('type', 'DEUDA')
                        ->where('status', 'A')
                        ->sum('amount');
                    $pendingAmount = $debt ?? 0;
                }
                
                $defaultTaxRate = TaxRate::where('status', true)->orderBy('order_num')->first();
                $defaultTaxPct = $defaultTaxRate ? (float) $defaultTaxRate->tax_rate : 18;
                $draftSale = [
                    'id' => $movement->id,
                    'number' => $movement->number,
                    'clientId' => $movement->person_id,
                    'items' => $movement->salesMovement->details->map(function ($detail) use ($defaultTaxPct) {
                        $taxRatePct = $defaultTaxPct;
                        if ($detail->tax_rate_snapshot && isset($detail->tax_rate_snapshot['tax_rate'])) {
                            $taxRatePct = (float) $detail->tax_rate_snapshot['tax_rate'];
                        }
                        $amountWithTax = (float) $detail->amount;
                        $quantity = (float) $detail->quantity ?: 1;
                        $priceWithTax = $quantity > 0 ? $amountWithTax / $quantity : 0;
                        return [
                            'pId' => $detail->product_id,
                            'name' => $detail->product->description ?? 'Producto #' . $detail->product_id,
                            'qty' => $quantity,
                            'price' => $priceWithTax,
                            'tax_rate' => $taxRatePct,
                            'note' => $detail->comment ?? '',
                            'product_note' => $detail->product->note ?? null,
                        ];
                    })->toArray(),
                    'clientName' => $movement->person_name ?? 'Público General',
                    'notes' => $movement->comment ?? '',
                    'pendingAmount' => $pendingAmount,
                    'product_notes' => $movement->salesMovement->details->pluck('product.note')->toArray(),
                ];
            }
        }
        
        // Obtener todos los productos para poder mostrar sus nombres cuando se carga desde localStorage
        $products = Product::pluck('description', 'id')->toArray();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId ?? 0)
            ->with('taxRate')
            ->get()
            ->map(fn ($pb) => [
                'product_id' => (int) $pb->product_id,
                'price' => (float) $pb->price,
                'tax_rate' => $pb->taxRate ? (float) $pb->taxRate->tax_rate : null,
            ])
            ->values();
        
        return view('sales.charge', [
            'documentTypes' => $documentTypes,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'cashRegisters' => $cashRegisters,
            'people' => $people,
            'defaultClientId' => $defaultClientId,
            'draftSale' => $draftSale,
            'pendingAmount' => $pendingAmount,
            'products' => $products,
            'productBranches' => $productBranches,
        ]);
    }
    // POS: procesar venta
    public function processSale(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.pId' => 'required|integer|exists:products,id',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'document_type_id' => 'required|integer|exists:document_types,id',
                'cash_register_id' => 'required|integer|exists:cash_registers,id',
                'person_id' => 'nullable|integer|exists:people,id',
                'payment_methods' => 'required|array|min:1',
                'payment_methods.*.payment_method_id' => 'required|integer|exists:payment_methods,id',
                'payment_methods.*.amount' => 'required|numeric|min:0.01',
                'payment_methods.*.payment_gateway_id' => 'nullable|integer|exists:payment_gateways,id',
                'payment_methods.*.card_id' => 'nullable|integer|exists:cards,id',
                'notes' => 'nullable|string',
                'movement_id' => 'nullable|integer|exists:movements,id', // ID del borrador a completar
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);
            
            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();
            
            // Si no hay turno de la sucursal, usar el primero disponible
            if (!$shift) {
                $shift = Shift::first();
            }
            
            if (!$shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }
            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();
            
            if (!$movementType) {
                $movementType = MovementType::first();
            }
            
            if (!$movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido. Por favor, crea un tipo de movimiento primero.');
            }
            
            $documentType = DocumentType::findOrFail($request->document_type_id);

            $selectedPerson = null;
            if (!empty($validated['person_id'])) {
                $selectedPerson = Person::query()
                    ->where('id', $validated['person_id'])
                    ->where('branch_id', $branchId)
                    ->firstOrFail();
            }

            // Obtener concepto de pago para ventas (Pago de cliente - ID 5)
            $paymentConcept = PaymentConcept::find(3); // Pago de cliente
            
            // Si no existe el ID 5, buscar por descripción
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%pago de cliente%')
                    ->orWhere('description', 'like', '%Pago de cliente%')
                    ->first();
            }
            
            // Si aún no se encuentra, buscar cualquier concepto de ingreso relacionado con venta
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%cliente%')
                    ->where('type', 'I')
                    ->first();
            }
            
            // Si aún no se encuentra, buscar cualquier concepto de ingreso
            if (!$paymentConcept) {
                $paymentConcept = PaymentConcept::where('type', 'I')->first();
            }
            
            if (!$paymentConcept) {
                throw new \Exception('No se encontró un concepto de pago válido. Por favor, crea un concepto de pago primero.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $calculated = $this->calculateSubtotalAndTaxFromItems($request->items, $branchId);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Caja seleccionada desde el formulario de cobro
            $cashRegister = CashRegister::find($validated['cash_register_id']);
            if (!$cashRegister) {
                throw new \Exception('No hay caja registradora disponible');
            }

            // Validar que la suma de los métodos de pago sea igual al total
            $totalPaymentMethods = array_sum(array_column($request->payment_methods, 'amount'));
            if (abs($totalPaymentMethods - $total) > 0.01) {
                throw new \Exception("La suma de los métodos de pago ({$totalPaymentMethods}) debe ser igual al total ({$total})");
            }

            // Recalcular con la misma regla (precio final con IGV incluido)
            $calculated = $this->calculateSubtotalAndTaxFromItems($request->items, $branchId);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Calcular el total recibido de todos los métodos de pago
            $amountReceived = $totalPaymentMethods;

            // Verificar si es un borrador a completar
            $isDraft = $request->has('movement_id') && $request->movement_id;
            $movement = null;
            $number = null;
            
            if ($isDraft) {
                // Cargar el movimiento existente (borrador)
                $movement = Movement::where('id', $request->movement_id)
                    ->where('branch_id', $branchId)
                    ->first();
                
                if (!$movement) {
                    throw new \Exception('No se encontró el movimiento de venta');
                }
                
                $number = $movement->number;
                
                // Actualizar el movimiento - siempre se completa el pago completo
                $movement->update([
                    'comment' => $request->notes ?? 'Venta completada desde punto de venta',
                    'status' => 'A', // Siempre Activo (pago completo)
                    'document_type_id' => $documentType->id,
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                ]);
                
                // Eliminar los detalles anteriores para recrearlos
                if ($movement->salesMovement) {
                    SalesMovementDetail::where('sales_movement_id', $movement->salesMovement->id)->delete();
                }
            } else {
                // Crear nuevo Movement
                $number = $this->generateSaleNumber(
                    (int) $documentType->id,
                    (int) $cashRegister->id,
                    true
                );
                
                $movement = Movement::create([
                    'number' => $number,
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->person->id,
                    'responsible_name' => $user?->person->first_name . ' ' . $user?->person->last_name ?? '-',
                    'comment' => $request->notes ?? '',
                    'status' => 'A', // Siempre Activo (pago completo)
                    'movement_type_id' => $movementType->id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                    'parent_movement_id' => null,
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                ]);
            } 

            // Crear o actualizar SalesMovement
            if ($isDraft && $movement->salesMovement) {
                // Actualizar el SalesMovement existente
                $salesMovement = $movement->salesMovement;
                $salesMovement->update([
                    'payment_type' => 'CASH', // Siempre CASH (pago completo)
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);
            } else {
                // Crear nuevo SalesMovement
                $salesMovement = SalesMovement::create([
                    'branch_snapshot' => [
                        'id' => $branch->id,
                        'legal_name' => $branch->legal_name,
                    ],
                    'series' => '001',
                    'year' => Carbon::now()->year,
                    'detail_type' => 'DETALLADO',
                    'consumption' => 'N',
                    'payment_type' => 'CONTADO', // Siempre CASH (pago completo)
                    'status' => 'N' ,
                    'sale_type' => 'MINORISTA',
                    'currency' => 'PEN',
                    'exchange_rate' => 3.5,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear SalesMovementDetails y actualizar stock (nota por producto en comment)
            foreach ($validated['items'] as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['pId']);
                
                // Bloquear el registro para evitar condiciones de carrera
                $productBranch = ProductBranch::with('taxRate')
                    ->where('product_id', $item['pId'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                }

                // Validar stock disponible
                $quantityToSell = (int) $item['qty'];
                $currentStock = (int) ($productBranch->stock ?? 0);
                
                // if ($currentStock < $quantityToSell) {
                //     throw new \Exception(
                //         "Stock insuficiente para el producto {$product->description}. " .
                //         "Stock disponible: {$currentStock}, Cantidad solicitada: {$quantityToSell}"
                //     );
                // }

                $unit = $product->baseUnit;
                if (!$unit) {
                    throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                }

                $taxRate = $productBranch->taxRate;
                $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $this->getDefaultTaxRateValue();

                // Precio de venta incluye impuesto.
                $itemTotal = (float) $item['qty'] * (float) $item['price'];
                $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
                $itemTax = $itemTotal - $itemSubtotal;

                // Nota por producto (compatibilidad note/comment) y normalización
                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;
                SalesMovementDetail::create([
                    'detail_type' => 'DETAILED',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unit->id,
                    'tax_rate_id' => $taxRate?->id,
                    'tax_rate_snapshot' => $taxRate ? [
                        'id' => $taxRate->id,
                        'description' => $taxRate->description,
                        'tax_rate' => $taxRate->tax_rate,
                    ] : null,
                    'quantity' => $item['qty'],
                    'amount' => $itemTotal,
                    'discount_percentage' => 0.000000,
                    'original_amount' => $itemSubtotal,
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                // Restar el stock del producto en la sucursal
                $newStock = $currentStock - $quantityToSell;
                $productBranch->update([
                    'stock' => max(0, $newStock) // Asegurar que no sea negativo
                ]);
            }
            
            // Crear/actualizar movimiento de caja separado del movimiento de venta
            $cashEntryMovement = $this->resolveCashEntryMovementBySaleMovement($movement->id);
         

            if (!$cashEntryMovement) {
                $cashEntryMovement = Movement::create([
                    'number' => $this->generateCashMovementNumber(
                        (int) $branchId,
                        (int) $cashRegister->id,
                        (int) $paymentConcept->id
                    ),
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->person->id,
                    'responsible_name' => $user?->person->first_name . ' ' . $user?->person->last_name ?? '-',
                    'comment' => 'Cobro de venta ' . $movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                    'branch_id' => $branchId,
                    'parent_movement_id' => $movement->id,
                ]);
            } else {
                $cashEntryMovement->update([
                    'moved_at' => now(),
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'comment' => 'Cobro de venta ' . $movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                ]);
            }

            // Crear o actualizar CashMovement (entrada de dinero)
            $cashMovement = CashMovements::where('movement_id', $cashEntryMovement->id)->first();

            if ($cashMovement) {
                $cashMovement->update([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                    'branch_id' => $branchId,
                ]);
                DB::table('cash_movement_details')
                    ->where('cash_movement_id', $cashMovement->id)
                    ->delete();
            } else {
                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time
                    ],
                    'movement_id' => $cashEntryMovement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear CashMovementDetail para cada método de pago
            foreach ($request->payment_methods as $paymentMethodData) {
                $paymentMethod = PaymentMethod::findOrFail($paymentMethodData['payment_method_id']);
                $paymentGateway = null;
                $card = null;
                
                if ($paymentMethodData['payment_gateway_id']) {
                    $paymentGateway = PaymentGateways::find($paymentMethodData['payment_gateway_id']);
                }
                
                if ($paymentMethodData['card_id']) {
                    $card = Card::find($paymentMethodData['card_id']);
                }
                
                DB::table('cash_movement_details')->insert([
                    'cash_movement_id' => $cashMovement->id,
                    'type' => 'PAGADO',
                    'paid_at' => now(),
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method' => $paymentMethod->description ?? '',
                    'number' => $cashEntryMovement->number,
                    'card_id' => $card?->id,
                    'card' => $card?->description ?? '',
                    'bank_id' => null,
                    'bank' => '',
                    'digital_wallet_id' => null,
                    'digital_wallet' => '',
                    'payment_gateway_id' => $paymentGateway?->id,
                    'payment_gateway' => $paymentGateway?->description ?? '',
                    'amount' => $paymentMethodData['amount'],
                    'comment' => $request->notes ?? 'Venta desde punto de venta - ' . $documentType->name,
                    'status' => 'A',
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta procesada correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'cash_movement_id' => $cashEntryMovement->id,
                    'number' => $number,
                    'total' => $total,
                ]
            ]);
            

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar la venta: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            $message = config('app.debug') 
                ? $e->getMessage() 
                : 'Error al procesar la venta';
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    // Guardar venta como borrador/pendiente (sin pago)
    public function saveDraft(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.pId' => 'required|integer|exists:products,id',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'document_type_id' => 'nullable|integer|exists:document_types,id',
                'notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);
            
            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();
            if (!$shift) {
                $shift = Shift::first();
            }
            if (!$shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }

            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();
            
            if (!$movementType) {
                $movementType = MovementType::first();
            }
            
            if (!$movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido.');
            }
            
            // Obtener documento por defecto si no se especifica
            $documentType = null;
            if ($request->document_type_id) {
                $documentType = DocumentType::find($request->document_type_id);
            }
            
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }
            
            if (!$documentType) {
                $documentType = DocumentType::first();
            }
            
            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $calculated = $this->calculateSubtotalAndTaxFromItems($request->items, $branchId);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Generar numero de movimiento con serie/correlativo por documento y caja activa
            $activeCashRegisterId = $this->resolveActiveCashRegisterId((int) $branchId);
            $number = $this->generateSaleNumber(
                (int) $documentType->id,
                $activeCashRegisterId,
                true
            );

            // Crear Movement con status 'P' (Pendiente) o 'I' (Inactivo)
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => null,
                'person_name' => 'Público General',
                'responsible_id' => $user?->id,
                'responsible_name' => $user?->name ?? 'Sistema',
                'comment' => ($request->notes ?? 'Venta pendiente de pago') . ' [BORRADOR]',
                'status' => 'P', // P = Pendiente
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time
                ],
            ]); 

            // Crear SalesMovement con payment_type 'CREDIT' (pendiente de pago)
            $salesMovement = SalesMovement::create([
                'branch_snapshot' => [
                    'id' => $branch->id,
                    'legal_name' => $branch->legal_name,
                ],
                'series' => '001',
                'year' => Carbon::now()->year,
                'detail_type' => 'DETALLADO',
                'consumption' => 'N',
                'payment_type' => 'CONTADO', 
                'status' => 'N',
                'sale_type' => 'MINORISTA',
                'currency' => 'PEN',
                'exchange_rate' => 1.000,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            // Crear SalesMovementDetails (sin restar stock porque es borrador; nota por producto en comment)
            foreach ($validated['items'] as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['pId']);
                $productBranch = ProductBranch::with('taxRate')
                    ->where('product_id', $item['pId'])
                    ->where('branch_id', $branchId)
                    ->first();

                if (!$productBranch) {
                    throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                }

                $unit = $product->baseUnit;
                if (!$unit) {
                    throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                }

                $taxRate = $productBranch->taxRate;
                $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $this->getDefaultTaxRateValue();

                // Precio de venta incluye impuesto.
                $itemTotal = (float) $item['qty'] * (float) $item['price'];
                $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
                $itemTax = $itemTotal - $itemSubtotal;

                // Nota por producto (compatibilidad note/comment) y normalización
                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;

                SalesMovementDetail::create([
                    'detail_type' => 'DETAILED',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unit->id,
                    'tax_rate_id' => $taxRate?->id,
                    'tax_rate_snapshot' => $taxRate ? [
                        'id' => $taxRate->id,
                        'description' => $taxRate->description,
                        'tax_rate' => $taxRate->tax_rate,
                    ] : null,
                    'quantity' => $item['qty'],
                    'amount' => $itemTotal,
                    'discount_percentage' => 0.000000,
                    'original_amount' => $itemSubtotal,
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta guardada como borrador correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'number' => $number,
                    'total' => $total,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar borrador de venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar borrador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validateSale($request);

        $user = $request->user();
        $personName = null;
        if (!empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name . ' ' . $person->last_name) : null;
        }

        Movement::create([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->name ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta creada correctamente.');
    }

    public function edit(Movement $sale)
    {
        return view('sales.edit', [
            'sale' => $sale,
        ] + $this->getFormData($sale));
    }

    public function update(Request $request, Movement $sale)
    {
        $data = $this->validateSale($request);

        $personName = null;
        if (!empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name . ' ' . $person->last_name) : null;
        }

        $sale->update([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta actualizada correctamente.');
    }

    public function destroy(Movement $sale)
    {
        $sale->delete();

        return redirect()
            ->route('admin.sales.index', request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [])
            ->with('status', 'Venta eliminada correctamente.');
    }

    private function validateSale(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:255'],
            'moved_at' => ['required', 'date'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'comment' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:1'],
            'movement_type_id' => ['required', 'integer', 'exists:movement_types,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_movement_id' => ['nullable', 'integer', 'exists:movements,id'],
        ]);
    }

    private function getFormData(?Movement $sale = null): array
    {
        $branches = Branch::query()->orderBy('legal_name')->get(['id', 'legal_name']);
        $people = Person::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $movementTypes = MovementType::query()->orderBy('description')->get(['id', 'description']);
        $documentTypes = DocumentType::query()->orderBy('name')->get(['id', 'name']);

        return [
            'branches' => $branches,
            'people' => $people,
            'movementTypes' => $movementTypes,
            'documentTypes' => $documentTypes,
        ];
    }

    /** Obtiene la tasa de impuesto por defecto del sistema (valor 0-1, ej: 0.18 para 18%). */
    private function getDefaultTaxRateValue(): float
    {
        $taxRate = TaxRate::where('status', true)->orderBy('order_num')->first();
        return $taxRate ? ((float) $taxRate->tax_rate) / 100 : 0.18;
    }

    /**
     * Calcula subtotal, IGV y total desde los ítems usando la tasa de impuesto de cada producto.
     * Usa la tasa configurada en ProductBranch->TaxRate; si no tiene, usa la tasa por defecto del sistema.
     */
    private function calculateSubtotalAndTaxFromItems(array $items, int $branchId): array
    {
        $defaultTaxPct = $this->getDefaultTaxRateValue();
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $productBranch = ProductBranch::with('taxRate')
                ->where('product_id', $item['pId'])
                ->where('branch_id', $branchId)
                ->first();

            $taxRate = $productBranch?->taxRate;
            $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $defaultTaxPct;

            $itemTotal = (float) ($item['qty'] ?? 0) * (float) ($item['price'] ?? 0);
            $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
            $itemTax = $itemTotal - $itemSubtotal;

            $subtotal += $itemSubtotal;
            $tax += $itemTax;
            $total += $itemTotal;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    public function reportSales(Request $request)
    {
        $branchId = session('branch_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $personId = $request->input('person_id');
        $documentTypeId = $request->input('document_type_id');
        $query = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement'])
            ->where('movement_type_id', 2)
            ->where('branch_id', $branchId)
            ->whereHas('salesMovement');
        if ($documentTypeId !== null && $documentTypeId !== '' && is_numeric($documentTypeId)) {
            $query->where('document_type_id', (int) $documentTypeId);
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('person_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }
        if ($personId !== null && $personId !== '') {
            $query->where('person_id', $personId);
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('moved_at', '>=', $dateFrom);
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('moved_at', '<=', $dateTo);
        }
        $sales = $query->orderBy('moved_at', 'desc')->paginate($perPage)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'sales' => $sales, 'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ]]);
        }

        $viewId = $request->input('view_id');

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sales.report', [
            'branchId' => $branchId,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'documentTypeId' => $documentTypeId,
            'documentTypes' => $documentTypes,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'sales' => $sales,
            'viewId' => $viewId,
        ]);
    }

    /**
     * Genera numero de venta en formato correlativo simple: 00000127.
     * Mantiene compatibilidad leyendo tambien numeros historicos con formato antiguo.
     */
    private function generateSaleNumber(int $documentTypeId, int $cashRegisterId, bool $reserve = true): string
    {
        $documentType = DocumentType::find($documentTypeId);
        if (!$documentType) {
            throw new \Exception('Tipo de documento no encontrado.');
        }

        $cashRegister = CashRegister::find($cashRegisterId);
        if (!$cashRegister) {
            throw new \Exception('Caja no encontrada.');
        }

        $branchId = (int) session('branch_id');
        if (!$branchId) {
            throw new \Exception('No se encontro sucursal en sesion.');
        }

        $year = (int) now()->year;

        $query = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year);

        if ($reserve) {
            $query->lockForUpdate();
        }

        $lastCorrelative = 0;
        $numbers = $query->pluck('number');

        foreach ($numbers as $number) {
            $raw = trim((string) $number);
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $raw) === 1) {
                $value = (int) $raw;
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }
                continue;
            }

            if (preg_match('/(\d+)-\d{4}$/', $raw, $matches) === 1) {
                $value = (int) $matches[1];
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }
            }
        }

        $nextCorrelative = $lastCorrelative + 1;

        return str_pad((string) $nextCorrelative, 8, '0', STR_PAD_LEFT);
    }

    private function resolveDocumentAbbreviation(string $documentName): string
    {
        $name = strtolower(trim($documentName));

        if (str_contains($name, 'boleta')) {
            return 'B';
        }
        if (str_contains($name, 'factura')) {
            return 'F';
        }
        if (str_contains($name, 'ticket')) {
            return 'T';
        }
        if (str_contains($name, 'nota')) {
            return 'N';
        }

        $firstLetter = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $documentName) ?: 'X', 0, 1));

        return $firstLetter !== '' ? $firstLetter : 'X';
    }

    private function resolveActiveCashRegisterId(int $branchId): int
    {
        $cashRegisterId = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->orderBy('id')
            ->value('id');

        if (!$cashRegisterId) {
            $cashRegisterId = CashRegister::query()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->value('id');
        }

        if (!$cashRegisterId) {
            throw new \Exception('No hay caja activa/disponible para generar el numero de venta.');
        }

        return (int) $cashRegisterId;
    }

    private function resolveCashMovementBySaleMovement(int $saleMovementId): ?CashMovements
    {
        $cashMovement = CashMovements::where('movement_id', $saleMovementId)->first();
        if ($cashMovement) {
            return $cashMovement;
        }

        $cashEntryMovementId = Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->value('id');

        return $cashEntryMovementId ? CashMovements::where('movement_id', $cashEntryMovementId)->first() : null;
    }

    private function resolveCashEntryMovementBySaleMovement(int $saleMovementId): ?Movement
    {
        return Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveCashMovementTypeId(): int
    {
        $movementTypeId = MovementType::query()
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%caja%')
                    ->orWhere('description', 'ILIKE', '%cash%');
            })
            ->orderBy('id')
            ->value('id');

        if (!$movementTypeId) {
            $movementTypeId = MovementType::find(4)?->id;
        }

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }

        if (!$movementTypeId) {
            throw new \Exception('No se encontro tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
    }


    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $lastRecord = Movement::query()
            ->select('movements.number')
            ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
            ->where('movements.branch_id', $branchId)
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->when($paymentConceptId !== null, function ($query) use ($paymentConceptId) {
                $query->where('cash_movements.payment_concept_id', $paymentConceptId);
            })
            ->lockForUpdate()
            ->orderByDesc('movements.number')
            ->first();

        $lastNumber = $lastRecord?->number;
        $nextSequence = $lastNumber ? ((int) $lastNumber + 1) : 1;

        return str_pad((string) $nextSequence, 8, '0', STR_PAD_LEFT);
    }
}
