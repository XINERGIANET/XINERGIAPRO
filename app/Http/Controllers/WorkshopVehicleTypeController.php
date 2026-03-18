<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\VehicleType;
use App\Models\WorkshopVehicleIntakeInventoryItem;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopVehicleTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $routeName = (string) optional($request->route())->getName();
            if (str_starts_with($routeName, 'workshop.')) {
                WorkshopAuthorization::ensureAllowed($routeName);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);
        $companyId = (int) $branch->company_id;
        $perPage = (int) $request->input('per_page', 10);
        $search = trim((string) $request->input('search', ''));
        $types = VehicleType::query()
            ->where(function ($query) use ($companyId, $branchId) {
                $query->where(function ($inner) use ($companyId, $branchId) {
                    $inner->where('company_id', $companyId)
                        ->where('branch_id', $branchId);
                })->orWhereNull('company_id');
            })
            ->when($search !== '', fn ($query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->orderBy('company_id')
            ->orderBy('branch_id')
            ->orderBy('order_num')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $allCosts = \App\Models\WorkshopAssemblyCost::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('active', true)
            ->get();

        return view('workshop.vehicle-types.index', compact('types', 'search', 'allCosts', 'perPage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);
        $companyId = (int) $branch->company_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $name = mb_strtolower(trim((string) $validated['name']));
        VehicleType::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $name,
            ],
            [
                'order_num' => (int) ($validated['order_num'] ?? 0),
                'active' => (bool) ($validated['active'] ?? true),
            ]
        );

        return back()->with('status', 'Tipo de vehiculo registrado correctamente.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $vehicleType->update([
            'name' => mb_strtolower(trim((string) $validated['name'])),
            'order_num' => (int) ($validated['order_num'] ?? 0),
            'active' => (bool) ($validated['active'] ?? false),
        ]);

        return back()->with('status', 'Tipo de vehiculo actualizado correctamente.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        if ($vehicleType->vehicles()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un tipo de vehiculo en uso.']);
        }

        $vehicleType->delete();
        return back()->with('status', 'Tipo de vehiculo eliminado correctamente.');
    }

    public function inventoryEdit(Request $request, VehicleType $vehicleType): \Illuminate\View\View
    {
        $this->assertVehicleTypeScope($vehicleType);

        $items = WorkshopVehicleIntakeInventoryItem::query()
            ->withTrashed()
            ->where('vehicle_type_id', $vehicleType->id)
            ->orderBy('order_num')
            ->get(['item_key', 'label', 'order_num', 'deleted_at']);

        return view('workshop.vehicle-types.inventory', [
            'vehicleType' => $vehicleType,
            'items' => $items,
        ]);
    }

    public function inventoryUpdate(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        $validated = $request->validate([
            'new_item_label' => ['nullable', 'string', 'max:255'],
            'active_keys' => ['nullable', 'array'],
            'active_keys.*' => ['nullable', 'string', 'max:80'],
            'delete_keys' => ['nullable', 'array'],
            'delete_keys.*' => ['nullable', 'string', 'max:80'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:255'],
            'orders' => ['nullable', 'array'],
            'orders.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $activeKeys = array_map(fn ($k) => (string) $k, (array) ($validated['active_keys'] ?? []));
        $deleteKeys = array_map(fn ($k) => (string) $k, (array) ($validated['delete_keys'] ?? []));
        $labels = (array) ($validated['labels'] ?? []);
        $orders = (array) ($validated['orders'] ?? []);

        $existingItems = WorkshopVehicleIntakeInventoryItem::query()
            ->withTrashed()
            ->where('vehicle_type_id', $vehicleType->id)
            ->get(['item_key', 'label', 'order_num', 'deleted_at'])
            ->keyBy('item_key');

        // Elimina definitivamente (hard delete) los items marcados.
        foreach ($deleteKeys as $deleteKey) {
            $deleteKey = (string) $deleteKey;
            if (trim($deleteKey) === '') {
                continue;
            }

            $existing = WorkshopVehicleIntakeInventoryItem::query()
                ->withTrashed()
                ->where('vehicle_type_id', $vehicleType->id)
                ->where('item_key', $deleteKey)
                ->first();

            if ($existing) {
                $existing->forceDelete();
                $existingItems->forget($deleteKey);
            }
        }

        $submittedKeys = array_unique(array_merge(array_keys($labels), array_keys($orders)));

        foreach ($submittedKeys as $itemKey) {
            $itemKey = (string) $itemKey;
            if (trim($itemKey) === '') {
                continue;
            }

            // Si el item fue eliminado con el boton, no lo vuelvas a procesar en este request.
            if (in_array($itemKey, $deleteKeys, true)) {
                continue;
            }

            $label = isset($labels[$itemKey]) ? trim((string) $labels[$itemKey]) : null;
            $orderNum = isset($orders[$itemKey]) ? (int) $orders[$itemKey] : 0;
            $isActive = in_array($itemKey, $activeKeys, true);

            $existing = $existingItems->get($itemKey);

            if ($isActive) {
                if ($existing) {
                    if ($label !== null && $label !== '') {
                        $existing->label = $label;
                    }
                    $existing->order_num = $orderNum;
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->save();
                } else {
                    if ($label === null || $label === '') {
                        continue;
                    }

                    WorkshopVehicleIntakeInventoryItem::query()->create([
                        'vehicle_type_id' => $vehicleType->id,
                        'item_key' => $itemKey,
                        'label' => $label,
                        'order_num' => $orderNum,
                    ]);
                }
            } else {
                if ($existing) {
                    // Actualiza nombre/orden incluso si esta desactivado.
                    if ($label !== null && $label !== '') {
                        $existing->label = $label;
                    }
                    $existing->order_num = $orderNum;
                    $existing->save();

                    // Y asegura que quede desactivado.
                    if (!$existing->trashed()) {
                        $existing->delete();
                    }
                }
            }
        }

        $newItemLabel = trim((string) ($validated['new_item_label'] ?? ''));
        if ($newItemLabel !== '') {
            $nextOrderNum = (int) (WorkshopVehicleIntakeInventoryItem::query()
                ->where('vehicle_type_id', $vehicleType->id)
                ->max('order_num') ?? 0) + 1;

            $newItemKey = $this->generateInventoryItemKey($newItemLabel, $vehicleType->id);

            $existing = WorkshopVehicleIntakeInventoryItem::query()
                ->withTrashed()
                ->where('vehicle_type_id', $vehicleType->id)
                ->where('item_key', $newItemKey)
                ->first();

            if ($existing) {
                $existing->label = $newItemLabel;
                $existing->order_num = $nextOrderNum;
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->save();
            } else {
                WorkshopVehicleIntakeInventoryItem::query()->create([
                    'vehicle_type_id' => $vehicleType->id,
                    'item_key' => $newItemKey,
                    'label' => $newItemLabel,
                    'order_num' => $nextOrderNum,
                ]);
            }
        }

        return back()->with('status', 'Inventario por tipo actualizado correctamente.');
    }

    private function generateInventoryItemKey(string $label, int $vehicleTypeId): string
    {
        $base = strtoupper(trim((string) $label));
        $base = preg_replace('/[^A-Z0-9]+/', '_', (string) $base);
        $base = trim((string) $base, '_');

        if ($base === '') {
            $base = 'ITEM';
        }

        $candidate = $base;
        $suffix = 1;
        while (
            WorkshopVehicleIntakeInventoryItem::query()
                ->withTrashed()
                ->where('vehicle_type_id', $vehicleTypeId)
                ->where('item_key', $candidate)
                ->exists()
        ) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function assertVehicleTypeScope(VehicleType $vehicleType): void
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        if ($vehicleType->company_id === null) {
            abort(403, 'No se puede modificar tipos globales.');
        }

        if ((int) $vehicleType->company_id !== (int) $branch->company_id || (int) $vehicleType->branch_id !== $branchId) {
            abort(404);
        }
    }
}

