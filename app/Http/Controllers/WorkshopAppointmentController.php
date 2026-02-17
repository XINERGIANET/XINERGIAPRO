<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workshop\StoreAppointmentRequest;
use App\Http\Requests\Workshop\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Person;
use App\Models\Vehicle;
use App\Services\Workshop\WorkshopFlowService;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopAppointmentController extends Controller
{
    public function __construct(private readonly WorkshopFlowService $flowService)
    {
        $this->middleware(function ($request, $next) {
            $routeName = (string) optional($request->route())->getName();
            if (str_starts_with($routeName, 'workshop.')) {
                WorkshopAuthorization::ensureAllowed($routeName);
            }
            return $next($request);
        });
    }

    public function events(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        $from = $request->input('start');
        $to = $request->input('end');

        $appointments = Appointment::query()
            ->with(['vehicle', 'client'])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($from, fn ($query) => $query->whereDate('start_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('start_at', '<=', $to))
            ->get();

        $events = $appointments->map(function ($app) {
            $title = ($app->client?->first_name ?? 'Cita') . ' - ' . ($app->vehicle?->plate ?? '');
            
            $start = $app->start_at;
            $end = $start->copy()->addHour();

            return [
                'id' => (string) $app->id,
                'title' => $title,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'allDay' => false,
                'backgroundColor' => $this->getStatusColor($app->status),
                'borderColor' => $this->getStatusColor($app->status),
                'textColor' => '#fff',
                'extendedProps' => [
                    'status' => $app->status,
                    'reason' => $app->reason,
                    'client' => ($app->client?->first_name ?? '') . ' ' . ($app->client?->last_name ?? ''),
                ]
            ];
        });

        return response()->json($events);
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#f59e0b',   // Amber
            'confirmed' => '#10b981', // Emerald
            'arrived' => '#3b82f6',   // Blue
            'cancelled' => '#ef4444', // Red
            'no_show' => '#6b7280',   // Gray
            default => '#244BB3',     // Default Blue
        };
    }

    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $appointments = Appointment::query()
            ->with(['vehicle', 'client', 'technician'])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('start_at', '>=', $from)
            ->whereDate('start_at', '<=', $to)
            ->orderBy('start_at')
            ->paginate(20)
            ->withQueryString();

        $vehicles = Vehicle::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('brand')
            ->orderBy('model')
            ->get(['id', 'brand', 'model', 'plate', 'client_person_id']);

        $clients = Person::query()
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('roles', function ($query) use ($branchId) {
                $query->where('roles.id', 3);
                if ($branchId > 0) {
                    $query->where('role_person.branch_id', $branchId);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $technicians = Person::query()
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('workshop.appointments.index', compact('appointments', 'vehicles', 'clients', 'technicians', 'from', 'to'));
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        Appointment::query()->create(array_merge(
            $request->validated(),
            [
                'company_id' => $branch->company_id,
                'branch_id' => $branchId,
                'status' => $request->input('status', 'pending'),
                'source' => $request->input('source', 'manual'),
            ]
        ));

        return back()->with('status', 'Cita registrada correctamente.');
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->assertAppointmentScope($appointment);
        $appointment->update($request->validated());

        return back()->with('status', 'Cita actualizada correctamente.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->assertAppointmentScope($appointment);
        if ($appointment->movement_id) {
            return back()->withErrors(['error' => 'No se puede eliminar una cita convertida a OS.']);
        }

        $appointment->delete();

        return back()->with('status', 'Cita eliminada correctamente.');
    }

    public function convertToOrder(Appointment $appointment): RedirectResponse
    {
        $this->assertAppointmentScope($appointment);
        if ($appointment->movement_id) {
            $existingWorkshop = \App\Models\WorkshopMovement::query()
                ->where('movement_id', $appointment->movement_id)
                ->first();

            if (!$existingWorkshop) {
                return back()->withErrors(['error' => 'La cita ya tiene movimiento pero no se encontro su OS relacionada.']);
            }

            return redirect()->route('workshop.orders.show', $existingWorkshop->id)
                ->with('status', 'La cita ya fue convertida a OS.');
        }

        $user = auth()->user();
        $branchId = (int) session('branch_id');

        $workshop = $this->flowService->createOrder([
            'vehicle_id' => $appointment->vehicle_id,
            'client_person_id' => $appointment->client_person_id,
            'appointment_id' => $appointment->id,
            'intake_date' => now(),
            'status' => 'diagnosis',
            'comment' => 'OS creada desde cita #' . $appointment->id,
        ], $branchId, (int) $user?->id, (string) ($user?->name ?? 'Sistema'));

        return redirect()->route('workshop.orders.show', $workshop)
            ->with('status', 'Cita convertida a orden de servicio.');
    }

    private function assertAppointmentScope(Appointment $appointment): void
    {
        $branchId = (int) session('branch_id');
        if ($branchId > 0 && (int) $appointment->branch_id !== $branchId) {
            abort(404);
        }

        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        if ($branch && (int) $appointment->company_id !== (int) $branch->company_id) {
            abort(404);
        }
    }
}
