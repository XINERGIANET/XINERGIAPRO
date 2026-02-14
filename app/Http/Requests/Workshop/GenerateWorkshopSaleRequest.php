<?php

namespace App\Http\Requests\Workshop;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWorkshopSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'detail_ids' => ['nullable', 'array'],
            'detail_ids.*' => ['integer', 'exists:workshop_movement_details,id'],
            'comment' => ['nullable', 'string'],
        ];
    }
}

