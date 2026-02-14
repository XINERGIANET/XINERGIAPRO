<?php

namespace App\Http\Requests\Workshop;

use App\Models\Branch;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function rules(): array
    {
        $branchId = (int) session('branch_id');
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        $vehicleId = (int) $this->route('vehicle')?->id;

        $rules = parent::rules();
        $rules['plate'] = [
            'nullable',
            'string',
            'max:255',
            Rule::unique('vehicles', 'plate')
                ->ignore($vehicleId)
                ->where(fn ($query) => $query->where('company_id', $companyId)),
        ];
        $rules['vin'] = [
            'nullable',
            'string',
            'max:255',
            Rule::unique('vehicles', 'vin')
                ->ignore($vehicleId)
                ->where(fn ($query) => $query->where('company_id', $companyId)),
        ];

        return $rules;
    }
}
