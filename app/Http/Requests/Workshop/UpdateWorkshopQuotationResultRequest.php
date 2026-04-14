<?php

namespace App\Http\Requests\Workshop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkshopQuotationResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_result' => ['required', Rule::in(['open', 'won', 'lost', 'converted'])],
            'quotation_lost_reason' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('quotation_result') === 'lost') {
                $reason = trim((string) $this->input('quotation_lost_reason', ''));
                if ($reason === '') {
                    $validator->errors()->add(
                        'quotation_lost_reason',
                        'Indique el motivo cuando la cotizacion no se concreta.'
                    );
                }
            }
        });
    }
}
