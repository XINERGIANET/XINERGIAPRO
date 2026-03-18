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

        $inventoryDefinitions = [
            'ESPEJOS' => 'Espejos',
            'FARO_DELANTERO' => 'Faro delantero',
            'DIRECCIONALES' => 'Direccionales',
            'TAPON_GASOLINA' => 'Tapon de gasolina',
            'PEDALES' => 'Pedales',
            'CLAXON' => 'Claxon',
            'ASIENTOS' => 'Asientos',
            'LUZ_STOP_TRASERA' => 'Luz stop trasera',
            'CUBIERTAS_COMPLETAS' => 'Cubiertas completas',
            'TACOMETROS' => 'Tacometros',
            'STEREO' => 'Stereo',
            'PARABRISAS' => 'Parabrisas',
            'TAPON_RADIADORES' => 'Tapon de radiadores',
            'FILTRO_AIRE' => 'Filtro de aire',
            'BATERIA' => 'Bateria',
            'LLAVES' => 'Llaves',
        ];

        $enabledItemKeys = WorkshopVehicleIntakeInventoryItem::query()
            ->where('vehicle_type_id', $vehicleType->id)
            ->pluck('item_key')
            ->map(fn ($k) => (string) $k)
            ->values()
            ->all();

        return view('workshop.vehicle-types.inventory', [
            'vehicleType' => $vehicleType,
            'inventoryDefinitions' => $inventoryDefinitions,
            'enabledItemKeys' => $enabledItemKeys,
        ]);
    }

    public function inventoryUpdate(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $this->assertVehicleTypeScope($vehicleType);

        $inventoryDefinitions = [
            'ESPEJOS' => 'Espejos',
            'FARO_DELANTERO' => 'Faro delantero',
            'DIRECCIONALES' => 'Direccionales',
            'TAPON_GASOLINA' => 'Tapon de gasolina',
            'PEDALES' => 'Pedales',
            'CLAXON' => 'Claxon',
            'ASIENTOS' => 'Asientos',
            'LUZ_STOP_TRASERA' => 'Luz stop trasera',
            'CUBIERTAS_COMPLETAS' => 'Cubiertas completas',
            'TACOMETROS' => 'Tacometros',
            'STEREO' => 'Stereo',
            'PARABRISAS' => 'Parabrisas',
            'TAPON_RADIADORES' => 'Tapon de radiadores',
            'FILTRO_AIRE' => 'Filtro de aire',
            'BATERIA' => 'Bateria',
            'LLAVES' => 'Llaves',
        ];

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'boolean'],
        ]);

        $items = (array) ($validated['items'] ?? []);
        $orderNum = 0;

        foreach ($inventoryDefinitions as $itemKey => $label) {
            $orderNum++;
            $checked = array_key_exists($itemKey, $items) ? (bool) $items[$itemKey] : false;

            $existing = WorkshopVehicleIntakeInventoryItem::query()
                ->withTrashed()
                ->where('vehicle_type_id', $vehicleType->id)
                ->where('item_key', $itemKey)
                ->first();

            if ($checked) {
                if ($existing) {
                    $existing->label = $label;
                    $existing->order_num = $orderNum;
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->save();
                } else {
                    WorkshopVehicleIntakeInventoryItem::query()->create([
                        'vehicle_type_id' => $vehicleType->id,
                        'item_key' => $itemKey,
                        'label' => $label,
                        'order_num' => $orderNum,
                    ]);
                }
            } else {
                if ($existing) {
                    $existing->delete();
                }
            }
        }

        return back()->with('status', 'Inventario por tipo actualizado correctamente.');
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

