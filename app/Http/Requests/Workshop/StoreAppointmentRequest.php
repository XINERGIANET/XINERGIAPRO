<?php

namespace App\Http\Requests\Workshop;

use App\Models\Appointment;
use App\Models\Person;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'client_person_id' => ['required', 'integer', 'exists:people,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'technician_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'status' => ['nullable', 'in:pending,confirmed,arrived,cancelled,no_show'],
            'source' => ['nullable', 'in:manual,web,whatsapp'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $vehicleId = (int) $this->input('vehicle_id');
            $clientId = (int) $this->input('client_person_id');
            $technicianId = $this->input('technician_person_id');
            $startAt = $this->input('start_at');
            $endAt = $this->input('end_at');
            $appointmentId = (int) ($this->route('appointment')?->id ?? 0);

            $vehicle = Vehicle::query()->find($vehicleId);
            if ($vehicle && (int) $vehicle->client_person_id !== $clientId) {
                $validator->errors()->add('client_person_id', 'El vehiculo no pertenece al cliente seleccionado.');
            }

            if ($startAt && strtotime($startAt) < now()->timestamp && !$this->isAdminUser()) {
                $validator->errors()->add('start_at', 'No puede registrar citas en el pasado.');
            }

            if ($technicianId && $startAt && $endAt) {
                $branchId = (int) session('branch_id');
                $overlap = Appointment::query()
                    ->where('technician_person_id', (int) $technicianId)
                    ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
                    ->where('id', '!=', $appointmentId)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($startAt, $endAt) {
                        $query->whereBetween('start_at', [$startAt, $endAt])
                            ->orWhereBetween('end_at', [$startAt, $endAt])
                            ->orWhere(function ($inner) use ($startAt, $endAt) {
                                $inner->where('start_at', '<=', $startAt)
                                    ->where('end_at', '>=', $endAt);
                            });
                    })
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('technician_person_id', 'El tecnico ya tiene una cita en ese rango horario.');
                }
            }

            if ($startAt && $endAt) {
                $duration = max(0, (int) ((strtotime($endAt) - strtotime($startAt)) / 60));
                $branchId = (int) session('branch_id');
                $baseDuration = (int) $this->branchParameter('WS_APPOINTMENT_DURATION_MIN', $branchId, 60);
                $tolerance = (int) $this->branchParameter('WS_DELAY_TOLERANCE_MIN', $branchId, 15);

                if (!$this->isAdminUser() && $duration > ($baseDuration + $tolerance)) {
                    $validator->errors()->add('end_at', 'La duración de la cita excede la duración configurada + tolerancia.');
                }
            }

            $branchId = (int) session('branch_id');
            if ($branchId > 0) {
                $clientBranch = Person::query()->where('id', $clientId)->value('branch_id');
                if ($clientBranch && (int) $clientBranch !== $branchId) {
                    $validator->errors()->add('client_person_id', 'El cliente no pertenece a la sucursal actual.');
                }
            }
        });
    }

    private function isAdminUser(): bool
    {
        $user = $this->user();
        $profileName = strtoupper((string) ($user?->profile?->name ?? ''));

        return $user?->profile_id === 1 || str_contains($profileName, 'ADMIN');
    }

    private function branchParameter(string $key, int $branchId, string|int|float $default): string|int|float
    {
        $parameter = DB::table('parameters')->where('description', $key)->first();
        if (!$parameter) {
            return $default;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        return $branchValue ?? $parameter->value ?? $default;
    }
}
