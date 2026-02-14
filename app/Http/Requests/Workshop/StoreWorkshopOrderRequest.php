<?php

namespace App\Http\Requests\Workshop;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Person;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkshopOrderRequest extends FormRequest
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
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'previous_workshop_movement_id' => ['nullable', 'integer', 'exists:workshop_movements,id'],
            'intake_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
            'mileage_out' => ['nullable', 'integer', 'min:0'],
            'tow_in' => ['nullable', 'boolean'],
            'diagnosis_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,diagnosis,awaiting_approval,approved,in_progress,finished,delivered,cancelled'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branchId = (int) session('branch_id');
            if ($branchId <= 0) {
                return;
            }

            $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
            if ($companyId <= 0) {
                return;
            }

            $vehicle = Vehicle::query()->find((int) $this->input('vehicle_id'));
            if (!$vehicle || (int) $vehicle->company_id !== $companyId) {
                $validator->errors()->add('vehicle_id', 'Vehiculo no pertenece a la empresa actual.');
                return;
            }

            if ($vehicle->branch_id && (int) $vehicle->branch_id !== $branchId) {
                $validator->errors()->add('vehicle_id', 'Vehiculo no pertenece a la sucursal actual.');
            }

            $client = Person::query()->find((int) $this->input('client_person_id'));
            if (!$client) {
                $validator->errors()->add('client_person_id', 'Cliente invalido.');
                return;
            }

            if ((int) $vehicle->client_person_id !== (int) $client->id) {
                $validator->errors()->add('client_person_id', 'El vehiculo no pertenece al cliente seleccionado.');
            }

            if ($this->filled('appointment_id')) {
                $appointment = Appointment::query()->find((int) $this->input('appointment_id'));
                if (!$appointment || (int) $appointment->company_id !== $companyId || (int) $appointment->branch_id !== $branchId) {
                    $validator->errors()->add('appointment_id', 'La cita no pertenece al contexto actual.');
                }
            }
        });
    }
}

