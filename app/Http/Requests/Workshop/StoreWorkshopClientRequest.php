<?php

namespace App\Http\Requests\Workshop;

use App\Models\Branch;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkshopClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'genero' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $branchId = (int) session('branch_id');
            $branch = Branch::query()->find($branchId);
            if (!$branch) {
                $validator->errors()->add('branch_id', 'No hay sucursal activa.');
                return;
            }

            $document = (string) $this->input('document_number');
            $exists = Person::query()
                ->join('branches', 'branches.id', '=', 'people.branch_id')
                ->where('branches.company_id', (int) $branch->company_id)
                ->where('people.document_number', $document)
                ->whereNull('people.deleted_at')
                ->exists();

            if ($exists) {
                $validator->errors()->add('document_number', 'El documento ya existe en la empresa.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->find($branchId);

        if ($branch && !$this->filled('location_id')) {
            $this->merge(['location_id' => $branch->location_id]);
        }
    }
}
