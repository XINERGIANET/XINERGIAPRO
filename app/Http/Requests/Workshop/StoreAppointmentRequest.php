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
            'type' => ['required', 'in:service,other'],
            'vehicle_id' => ['required_if:type,service', 'nullable', 'integer', 'exists:vehicles,id'],
            'client_person_id' => ['required_if:type,service', 'nullable', 'integer', 'exists:people,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'technician_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'status' => ['nullable', 'in:pending,confirmed,arrived,cancelled,no_show'],
            'source' => ['nullable', 'in:manual,web,whatsapp'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de cita',
            'vehicle_id' => 'vehículo',
            'client_person_id' => 'cliente',
            'start_at' => 'fecha y hora de inicio',
            'end_at' => 'fecha y hora de fin',
            'reason' => 'motivo',
            'notes' => 'notas',
            'technician_person_id' => 'técnico o responsable',
            'status' => 'estado',
            'source' => 'origen',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Debe indicar el tipo de cita.',
            'type.in' => 'El tipo de cita debe ser servicio u otro.',
            'vehicle_id.required_if' => 'Para citas de servicio debe seleccionar un vehículo.',
            'vehicle_id.integer' => 'El vehículo seleccionado no es válido.',
            'vehicle_id.exists' => 'El vehículo seleccionado no existe o fue eliminado.',
            'client_person_id.required_if' => 'Para citas de servicio debe seleccionar un cliente.',
            'client_person_id.integer' => 'El cliente seleccionado no es válido.',
            'client_person_id.exists' => 'El cliente seleccionado no existe o fue eliminado.',
            'start_at.required' => 'La fecha y hora de inicio es obligatoria.',
            'start_at.date' => 'La fecha y hora de inicio no es válida.',
            'end_at.date' => 'La fecha y hora de fin no es válida.',
            'end_at.after' => 'La fecha y hora de fin debe ser posterior a la de inicio.',
            'reason.required' => 'El motivo de la cita es obligatorio.',
            'reason.string' => 'El motivo debe ser texto.',
            'reason.max' => 'El motivo no puede superar :max caracteres.',
            'notes.string' => 'Las notas deben ser texto.',
            'technician_person_id.integer' => 'El técnico o responsable seleccionado no es válido.',
            'technician_person_id.exists' => 'El técnico o responsable seleccionado no existe o fue eliminado.',
            'status.in' => 'El estado seleccionado no es válido.',
            'source.in' => 'El origen de la cita no es válido.',
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
                $validator->errors()->add('client_person_id', 'El vehículo no pertenece al cliente seleccionado.');
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
                        $query->where(function ($q) use ($startAt, $endAt) {
                            $q->whereBetween('start_at', [$startAt, $endAt])
                                ->orWhereBetween('end_at', [$startAt, $endAt]);
                        })
                        ->orWhere(function ($inner) use ($startAt, $endAt) {
                            $inner->where('start_at', '<=', $startAt)
                                ->where('end_at', '>=', $endAt);
                        });
                    })
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('technician_person_id', 'El técnico ya tiene una cita en ese rango horario.');
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
