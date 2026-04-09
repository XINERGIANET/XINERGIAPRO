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
        $branchId = (int) session('branch_id');
        $firstNameRequired = $this->branchParameter('Nombres obligatorios', $branchId, 'Si');
        $lastNameRequired = $this->branchParameter('Apellidos obligatorios', $branchId, 'Si');

        $firstNameRule = (strtoupper((string) $firstNameRequired) === 'SI') ? 'required' : 'nullable';
        $lastNameRule = (strtoupper((string) $lastNameRequired) === 'SI') ? 'required_unless:person_type,RUC' : 'nullable';

        return [
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => [$firstNameRule, 'string', 'max:255'],
            'last_name' => [$lastNameRule, 'nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'genero' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
        ];
    }

    private function branchParameter(string $key, int $branchId, string $default): string
    {
        $parameter = \Illuminate\Support\Facades\DB::table('parameters')->where('description', $key)->first();
        if (!$parameter) {
            return $default;
        }

        $branchValue = \Illuminate\Support\Facades\DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        return $branchValue ?? $parameter->value ?? $default;
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

        if (strtoupper((string) $this->input('person_type')) === 'RUC') {
            $this->merge(['last_name' => '']);
        }

        if ($branch && !$this->filled('location_id')) {
            $this->merge(['location_id' => $branch->location_id]);
        }
    }
}
