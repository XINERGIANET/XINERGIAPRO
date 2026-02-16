<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\StoreWorkshopClientRequest;
use App\Http\Requests\Workshop\UpdateWorkshopClientRequest;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovements;
use App\Models\OrderMovement;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\Vehicle;
use App\Models\WorkshopMovement;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopClientController extends Controller
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
        [$branchId, $companyId] = $this->branchScope();

        $search = trim((string) $request->input('search', ''));
        $type = (string) $request->input('type', '');

        $clients = Person::query()
            ->where('branch_id', $branchId)
            ->when($type !== '', function ($query) use ($type) {
                if ($type === 'CORPORATIVO') {
                    $query->where('person_type', 'RUC');
                    return;
                }
                if ($type === 'NATURAL') {
                    $query->where('person_type', '!=', 'RUC');
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('document_number', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('workshop.clients.index', compact('clients', 'search', 'type'));
    }

    public function store(StoreWorkshopClientRequest $request): RedirectResponse
    {
        [$branchId] = $this->branchScope();

        Person::query()->create(array_merge(
            $request->validated(),
            ['branch_id' => $branchId]
        ));

        return back()->with('status', 'Cliente registrado correctamente.');
    }

    public function update(UpdateWorkshopClientRequest $request, Person $person): RedirectResponse
    {
        [$branchId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);

        $person->update($request->validated());

        return back()->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        [$branchId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);

        $hasVehicles = Vehicle::query()->where('client_person_id', $person->id)->exists();
        $hasWorkshopOrders = WorkshopMovement::query()->where('client_person_id', $person->id)->exists();
        $hasSales = SalesMovement::query()
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id))
            ->exists();

        if ($hasVehicles || $hasWorkshopOrders || $hasSales) {
            return back()->withErrors([
                'error' => 'No se puede eliminar cliente con vehiculos, ordenes de servicio o ventas asociadas.',
            ]);
        }

        $person->delete();

        return back()->with('status', 'Cliente eliminado correctamente.');
    }

    public function show(Request $request, Person $person)
    {
        [$branchId, $companyId] = $this->branchScope();
        $this->assertClientScope($person, $branchId);

        $vehicles = Vehicle::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        $appointments = Appointment::query()
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        $orders = WorkshopMovement::query()
            ->with(['movement', 'vehicle'])
            ->where('client_person_id', $person->id)
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $sales = SalesMovement::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $purchases = OrderMovement::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $payments = CashMovements::query()
            ->with('movement')
            ->whereHas('movement', fn ($query) => $query->where('person_id', $person->id)->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $totalOrders = (float) $orders->sum('total');
        $totalPaidOrders = (float) $orders->sum('paid_total');
        $debtOrders = max(0, $totalOrders - $totalPaidOrders);
        $totalSales = (float) $sales->sum('total');
        $totalPurchases = (float) $purchases->sum('total');
        $totalPayments = (float) $payments->sum('total');

        return view('workshop.clients.history', compact(
            'person',
            'vehicles',
            'appointments',
            'orders',
            'sales',
            'purchases',
            'payments',
            'totalOrders',
            'totalPaidOrders',
            'debtOrders',
            'totalSales',
            'totalPurchases',
            'totalPayments'
        ));
    }

    private function assertClientScope(Person $person, int $branchId): void
    {
        if ((int) $person->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function branchScope(): array
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        return [$branchId, (int) $branch->company_id];
    }
}
