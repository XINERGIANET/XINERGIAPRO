<?php

namespace App\Http\Requests\Workshop;

use Illuminate\Foundation\Http\FormRequest;

class ConsumeWorkshopPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detail_id' => ['required', 'integer', 'exists:workshop_movement_details,id'],
            'action' => ['nullable', 'in:consume,reserve,release,return'],
            'comment' => ['nullable', 'string'],
        ];
    }
}

